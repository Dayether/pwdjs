<?php
require_once __DIR__ . '/Taxonomy.php';

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
        $this->application_id = $data['application_id'] ?? '';
        $this->user_id = $data['user_id'] ?? '';
        $this->job_id = $data['job_id'] ?? '';
        $this->status = $data['status'] ?? 'Pending';
        $this->match_score = isset($data['match_score']) ? (float)$data['match_score'] : 0;
        $this->relevant_experience = isset($data['relevant_experience']) ? (int)$data['relevant_experience'] : 0;
        $this->application_education = $data['application_education'] ?? '';
        $this->created_at = $data['created_at'] ?? '';
    }

    // Create application with per-application details
    public static function createWithDetails(User $user, Job $job, int $relevantYears, array $selectedSkillIds, ?string $applicationEducation): bool {
        $pdo = Database::getConnection();

        // prevent duplicates
        $check = $pdo->prepare("SELECT application_id FROM applications WHERE user_id = ? AND job_id = ?");
        $check->execute([$user->user_id, $job->job_id]);
        if ($check->fetch()) return false;

        $appEduCanon = Taxonomy::canonicalizeEducation($applicationEducation ?? '');
        if ($appEduCanon === null) $appEduCanon = ''; // unknown -> blank

        $match = self::calculateMatchScoreFromInput($job, $relevantYears, $selectedSkillIds, $appEduCanon);

        $application_id = Helpers::generateSmartId('APP');
        $stmt = $pdo->prepare("INSERT INTO applications (application_id, user_id, job_id, status, match_score, relevant_experience, application_education)
            VALUES (:application_id, :user_id, :job_id, 'Pending', :match_score, :relevant_experience, :application_education)");
        $ok = $stmt->execute([
            ':application_id' => $application_id,
            ':user_id' => $user->user_id,
            ':job_id' => $job->job_id,
            ':match_score' => $match,
            ':relevant_experience' => max(0, $relevantYears),
            ':application_education' => $appEduCanon
        ]);
        if (!$ok) return false;

        Skill::assignSkillIdsToApplication($application_id, $job->job_id, $selectedSkillIds);
        return true;
    }

    public static function listByUser(string $user_id): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT a.*, j.title FROM applications a JOIN jobs j ON a.job_id = j.job_id WHERE a.user_id = ? ORDER BY a.created_at DESC");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    public static function listByJob(string $job_id): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT a.*, u.name FROM applications a 
            JOIN users u ON a.user_id = u.user_id
            WHERE a.job_id = ?
            ORDER BY a.match_score DESC, a.created_at ASC");
        $stmt->execute([$job_id]);
        return $stmt->fetchAll();
    }

    public static function updateStatus(string $application_id, string $status, string $employer_id): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT a.application_id FROM applications a
            JOIN jobs j ON a.job_id = j.job_id
            WHERE a.application_id = :app AND j.employer_id = :emp");
        $stmt->execute([':app'=>$application_id, ':emp'=>$employer_id]);
        if (!$stmt->fetch()) return false;

        $upd = $pdo->prepare("UPDATE applications SET status = :status WHERE application_id = :app");
        return $upd->execute([':status'=>$status, ':app'=>$application_id]);
    }

    /**
     * Matching algorithm weights:
     * - Relevant experience: 50%
     * - Skills match: 40%
     * - Education: 10% (meets or exceeds required level)
     */
    public static function calculateMatchScoreFromInput(Job $job, int $relevantYears, array $selectedSkillIds, ?string $applicationEducation): float {
        $score = 0.0;

        // Experience (50%)
        $expWeight = 50.0;
        if ($job->required_experience <= 0) {
            $score += $expWeight;
        } else {
            $ratio = max(0.0, min(1.0, $relevantYears / $job->required_experience));
            $score += $ratio * $expWeight;
        }

        // Skills (40%)
        $skillsWeight = 40.0;
        $jobSkillIds = Skill::getJobSkillIds($job->job_id);
        $jobSkillIds = array_values(array_unique(array_map('strval', $jobSkillIds)));
        $selected = array_values(array_unique(array_map('strval', $selectedSkillIds)));
        if (count($jobSkillIds) > 0) {
            $matchCount = count(array_intersect($selected, $jobSkillIds));
            $score += ($matchCount / count($jobSkillIds)) * $skillsWeight;
        } else {
            $score += $skillsWeight;
        }

        // Education (10%) - meets or exceeds required
        $eduWeight = 10.0;
        $jobEdu = Taxonomy::canonicalizeEducation($job->required_education ?? '');
        if ($jobEdu === '' || $jobEdu === null) {
            $score += $eduWeight; // Any
        } else {
            $appRank = Taxonomy::educationRank($applicationEducation);
            $reqRank = Taxonomy::educationRank($jobEdu);
            if ($appRank >= $reqRank && $appRank >= 0) {
                $score += $eduWeight;
            } else {
                // optional partial credit if one step below requirement:
                if ($appRank === $reqRank - 1) {
                    $score += $eduWeight * 0.5;
                }
            }
        }

        return round($score, 2);
    }
}