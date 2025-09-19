<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/Skill.php';
require_once __DIR__ . '/Taxonomy.php';
require_once __DIR__ . '/Job.php';
require_once __DIR__ . '/User.php';

class Application {
    public string $application_id;
    public string $user_id;
    public string $job_id;
    public string $status;
    public float $match_score;
    public int $relevant_experience;
    public string $application_education;
    public string $created_at;

    public function __construct(array $data = []) {
        $this->application_id       = $data['application_id'] ?? '';
        $this->user_id              = $data['user_id'] ?? '';
        $this->job_id               = $data['job_id'] ?? '';
        $this->status               = $data['status'] ?? 'Pending';
        $this->match_score          = isset($data['match_score']) ? (float)$data['match_score'] : 0.0;
        $this->relevant_experience  = isset($data['relevant_experience']) ? (int)$data['relevant_experience'] : 0;
        $this->application_education= $data['application_education'] ?? '';
        $this->created_at           = $data['created_at'] ?? '';
    }

    /**
     * Create an application and compute match score.
     * Prevent duplicate application by same user to same job.
     */
    public static function createWithDetails(User $user, Job $job, int $relevantYears, array $selectedSkillIds, ?string $applicationEducation): bool {
        $pdo = Database::getConnection();

        // Duplicate check
        $check = $pdo->prepare("SELECT application_id FROM applications WHERE user_id = ? AND job_id = ?");
        $check->execute([$user->user_id, $job->job_id]);
        if ($check->fetch()) return false;

        // Canonicalize candidate education
        $appEduCanon = Taxonomy::canonicalizeEducation($applicationEducation ?? '');
        if ($appEduCanon === null) $appEduCanon = ''; // unrecognized => treat as unspecified

        $match = self::calculateMatchScoreFromInput($job, $relevantYears, $selectedSkillIds, $appEduCanon);

        $application_id = Helpers::generateSmartId('APP');

        $stmt = $pdo->prepare("INSERT INTO applications 
            (application_id, user_id, job_id, status, match_score, relevant_experience, application_education)
            VALUES (:application_id, :user_id, :job_id, 'Pending', :match_score, :relevant_experience, :application_education)");

        $ok = $stmt->execute([
            ':application_id'       => $application_id,
            ':user_id'              => $user->user_id,
            ':job_id'               => $job->job_id,
            ':match_score'          => $match,
            ':relevant_experience'  => max(0, $relevantYears),
            ':application_education'=> $appEduCanon
        ]);

        if (!$ok) return false;

        // Save selected skills (filtered to job's)
        Skill::assignSkillIdsToApplication($application_id, $job->job_id, $selectedSkillIds);

        return true;
    }

    public static function listByUser(string $user_id): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT a.*, j.title 
            FROM applications a 
            JOIN jobs j ON a.job_id = j.job_id 
            WHERE a.user_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    public static function listByJob(string $job_id): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT a.*, u.name 
            FROM applications a 
            JOIN users u ON a.user_id = u.user_id
            WHERE a.job_id = ?
            ORDER BY a.match_score DESC, a.created_at ASC
        ");
        $stmt->execute([$job_id]);
        return $stmt->fetchAll();
    }

    /**
     * Scoring System (Total 100):
     *  Experience: 40%
     *    - If job required_experience == 0 => full 40
     *    - Else min(candidateYears, requiredYears)/requiredYears * 40
     *
     *  Skills: 40%
     *    - All job skills (general + required)
     *    - Let totalJobSkills = count(job skills). If 0 => full 40
     *    - matched = count of unique candidate-selected skill IDs that exist in job's skills
     *    - skillScore = (matched / totalJobSkills) * 40
     *
     *  Education: 20%
     *    - If job has no required education => full 20
     *    - Else compare rank (candidateRank / requiredRank) * 20 (capped at 20) if candidateRank < requiredRank
     *      full 20 if candidateRank >= requiredRank
     *
     * NOTE: The method expects candidate education already canonicalized ('' allowed).
     */
    public static function calculateMatchScoreFromInput(Job $job, int $relevantYears, array $selectedSkillIds, string $appEducationCanon): float
    {
        // --- Experience (40) ---
        $expRequirement = max(0, (int)$job->required_experience);
        if ($expRequirement <= 0) {
            $expScore = 40.0;
        } else {
            $ratio = min($relevantYears, $expRequirement) / max(1, $expRequirement);
            $expScore = 40.0 * $ratio;
        }

        // --- Skills (40) ---
        $jobSkillIds    = Skill::getJobSkillIds($job->job_id);
        $jobSkillCount  = count($jobSkillIds);
        if ($jobSkillCount === 0) {
            $skillScore = 40.0;
        } else {
            $jobSet   = array_flip($jobSkillIds);
            $matched  = 0;
            $seen     = [];
            foreach ($selectedSkillIds as $sid) {
                $sid = trim((string)$sid);
                if ($sid === '' || isset($seen[$sid])) continue;
                $seen[$sid] = true;
                if (isset($jobSet[$sid])) $matched++;
            }
            $skillScore = 40.0 * ($matched / $jobSkillCount);
        }

        // --- Education (20) ---
        $eduMap        = Taxonomy::educationRankMap();
        $requiredCanon = Taxonomy::canonicalizeEducation($job->required_education ?? '');
        if ($requiredCanon === null) $requiredCanon = ''; // unrecognized => treat as none

        if ($requiredCanon === '') {
            $eduScore = 20.0;
        } else {
            $requiredRank  = $eduMap[mb_strtolower($requiredCanon)] ?? 0;
            $candidateRank = $eduMap[mb_strtolower($appEducationCanon)] ?? 0;

            if ($requiredRank <= 0) {
                $eduScore = 20.0;
            } elseif ($candidateRank >= $requiredRank) {
                $eduScore = 20.0;
            } else {
                $eduScore = 20.0 * ($candidateRank / max(1, $requiredRank));
            }
        }

        // Total
        $total = $expScore + $skillScore + $eduScore;
        if ($total < 0)   $total = 0;
        if ($total > 100) $total = 100;
        return round($total, 2);
    }
}