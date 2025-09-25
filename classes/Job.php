<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/Taxonomy.php';
require_once __DIR__ . '/Skill.php';

class Job {
    public string $job_id = '';
    public string $employer_id = '';
    public string $title = '';
    public string $description = '';
    public int $required_experience = 0;
    public ?string $required_education = null;
    public ?string $required_skills_input = null;
    public ?string $accessibility_tags = null;

    public string $location_city = '';
    public string $location_region = '';
    public string $remote_option = 'Work From Home';
    public string $employment_type = 'Full time';
    public string $salary_currency = 'PHP';
    public ?int $salary_min = null;
    public ?int $salary_max = null;
    public string $salary_period = 'monthly';
    public ?string $job_image = null; // path relative to public root

    public string $status = 'Open'; // Open, Suspended, Closed
    public string $created_at = '';

    public function __construct(array $row = []) {
        if (!$row) return;
        $this->job_id                = $row['job_id'] ?? '';
        $this->employer_id           = $row['employer_id'] ?? '';
        $this->title                 = $row['title'] ?? '';
        $this->description           = $row['description'] ?? '';
        $this->required_experience   = isset($row['required_experience']) ? (int)$row['required_experience'] : 0;
        $this->required_education    = $row['required_education'] ?? null;
        $this->required_skills_input = $row['required_skills_input'] ?? null;
        $this->accessibility_tags    = $row['accessibility_tags'] ?? null;

        $this->location_city   = $row['location_city'] ?? '';
        $this->location_region = $row['location_region'] ?? '';
        $this->remote_option   = $row['remote_option'] ?? 'Work From Home';
        $this->employment_type = $row['employment_type'] ?? 'Full time';
        $this->salary_currency = $row['salary_currency'] ?? 'PHP';
        $this->salary_min      = array_key_exists('salary_min', $row) ? (is_null($row['salary_min']) ? null : (int)$row['salary_min']) : null;
        $this->salary_max      = array_key_exists('salary_max', $row) ? (is_null($row['salary_max']) ? null : (int)$row['salary_max']) : null;
        $this->salary_period   = $row['salary_period'] ?? 'monthly';
    $this->job_image       = $row['job_image'] ?? null;

        $this->status          = $row['status'] ?? 'Open';
        $this->created_at      = $row['created_at'] ?? '';
    }

    public static function findById(string $job_id): ?Job {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM jobs WHERE job_id = ?");
        $stmt->execute([$job_id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return new Job($row);
    }

    public static function listByEmployer(string $employer_id): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT job_id, title, created_at, location_city, location_region,
                   remote_option, employment_type, salary_currency, salary_min, salary_max, salary_period, status
            FROM jobs
            WHERE employer_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$employer_id]);
        return $stmt->fetchAll();
    }

    public static function create(array $data, string $employer_id): bool {
        $pdo = Database::getConnection();
        $job_id = Helpers::generateSmartId('JOB');

        $reqEdu = Taxonomy::canonicalizeEducation($data['required_education'] ?? '');
        if ($reqEdu === null) $reqEdu = '';

        $remote = 'Work From Home';
        $status = 'Open';

        $stmt = $pdo->prepare("
            INSERT INTO jobs (
              job_id, employer_id, title, description,
              required_experience, required_education, required_skills_input, accessibility_tags,
              location_city, location_region,
              remote_option, employment_type,
                            salary_currency, salary_min, salary_max, salary_period, job_image,
              status
            ) VALUES (
              :job_id, :employer_id, :title, :description,
              :required_experience, :required_education, :required_skills_input, :accessibility_tags,
              :location_city, :location_region,
              :remote_option, :employment_type,
                            :salary_currency, :salary_min, :salary_max, :salary_period, :job_image,
              :status
            )
        ");

        $ok = $stmt->execute([
            ':job_id'               => $job_id,
            ':employer_id'          => $employer_id,
            ':title'                => $data['title'],
            ':description'          => $data['description'],
            ':required_experience'  => (int)($data['required_experience'] ?? 0),
            ':required_education'   => $reqEdu,
            ':required_skills_input'=> $data['required_skills_input'] ?? '',
            ':accessibility_tags'   => $data['accessibility_tags'] ?? '',
            ':location_city'        => $data['location_city'] ?? '',
            ':location_region'      => $data['location_region'] ?? '',
            ':remote_option'        => $remote,
            ':employment_type'      => $data['employment_type'] ?? 'Full time',
            ':salary_currency'      => $data['salary_currency'] ?? 'PHP',
            ':salary_min'           => ($data['salary_min'] ?? null) !== '' ? $data['salary_min'] : null,
            ':salary_max'           => ($data['salary_max'] ?? null) !== '' ? $data['salary_max'] : null,
            ':salary_period'        => $data['salary_period'] ?? 'monthly',
            ':status'               => $status,
            ':job_image'            => $data['job_image'] ?? null,
        ]);

        if ($ok) {
            $skillsRaw = Helpers::parseSkillInput($data['required_skills_input'] ?? '');
            Skill::assignSkillsToJob($job_id, $skillsRaw);
        }
        return $ok;
    }

    public static function update(string $job_id, array $data, string $employer_id): bool {
        $pdo = Database::getConnection();

        $reqEdu = Taxonomy::canonicalizeEducation($data['required_education'] ?? '');
        if ($reqEdu === null) $reqEdu = '';

        $remote = 'Work From Home';

        $stmt = $pdo->prepare("
            UPDATE jobs SET
              title = :title,
              description = :description,
              required_experience = :required_experience,
              required_education = :required_education,
              required_skills_input = :required_skills_input,
              accessibility_tags = :accessibility_tags,
              location_city = :location_city,
              location_region = :location_region,
              remote_option = :remote_option,
              employment_type = :employment_type,
              salary_currency = :salary_currency,
              salary_min = :salary_min,
              salary_max = :salary_max,
                            salary_period = :salary_period,
                            job_image = :job_image
            WHERE job_id = :job_id AND employer_id = :employer_id
            LIMIT 1
        ");

        $ok = $stmt->execute([
            ':title'               => $data['title'],
            ':description'         => $data['description'],
            ':required_experience' => (int)($data['required_experience'] ?? 0),
            ':required_education'  => $reqEdu,
            ':required_skills_input'=> $data['required_skills_input'] ?? '',
            ':accessibility_tags'  => $data['accessibility_tags'] ?? '',
            ':location_city'       => $data['location_city'] ?? '',
            ':location_region'     => $data['location_region'] ?? '',
            ':remote_option'       => $remote,
            ':employment_type'     => $data['employment_type'] ?? 'Full time',
            ':salary_currency'     => $data['salary_currency'] ?? 'PHP',
            ':salary_min'          => ($data['salary_min'] ?? null) !== '' ? $data['salary_min'] : null,
            ':salary_max'          => ($data['salary_max'] ?? null) !== '' ? $data['salary_max'] : null,
            ':salary_period'       => $data['salary_period'] ?? 'monthly',
            ':job_id'              => $job_id,
            ':employer_id'         => $employer_id,
            ':job_image'           => $data['job_image'] ?? null,
        ]);

        if ($ok) {
            $skillsRaw = Helpers::parseSkillInput($data['required_skills_input'] ?? '');
            Skill::assignSkillsToJob($job_id, $skillsRaw);
        }
        return $ok;
    }

    public static function delete(string $job_id, string $employer_id): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM jobs WHERE job_id = ? AND employer_id = ? LIMIT 1");
        return $stmt->execute([$job_id, $employer_id]);
    }

    public static function adminDelete(string $job_id): bool {
        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM applications WHERE job_id = ?")->execute([$job_id]);
            try {
                $pdo->prepare("DELETE FROM job_skills WHERE job_id = ?")->execute([$job_id]);
            } catch (PDOException $e) {
                if ($e->getCode() !== '42S02') throw $e;
            }
            $stmt = $pdo->prepare("DELETE FROM jobs WHERE job_id = ? LIMIT 1");
            $stmt->execute([$job_id]);
            $deleted = $stmt->rowCount() > 0;
            $pdo->commit();
            return $deleted;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return false;
        }
    }

    public static function setStatus(string $job_id, string $status): bool {
        $allowed = ['Open','Suspended','Closed'];
        if (!in_array($status, $allowed, true)) return false;
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE jobs SET status = :st WHERE job_id = :id LIMIT 1");
        return $stmt->execute([':st'=>$status, ':id'=>$job_id]);
    }

    public static function setStatusByEmployer(string $employer_id, string $status, ?string $onlyCurrentStatus = null): int {
        $allowed = ['Open','Suspended','Closed'];
        if (!in_array($status, $allowed, true)) return 0;
        $pdo = Database::getConnection();
        if ($onlyCurrentStatus) {
            $stmt = $pdo->prepare("UPDATE jobs SET status = :st WHERE employer_id = :eid AND status = :cur");
            $stmt->execute([':st'=>$status, ':eid'=>$employer_id, ':cur'=>$onlyCurrentStatus]);
        } else {
            $stmt = $pdo->prepare("UPDATE jobs SET status = :st WHERE employer_id = :eid");
            $stmt->execute([':st'=>$status, ':eid'=>$employer_id]);
        }
        return $stmt->rowCount();
    }

    /* ==============================
       DUPLICATE / SIMILARITY HELPERS
       ============================== */

    protected static function normalizeTitle(string $title): string {
        $t = mb_strtolower($title);
        // remove non alphanumeric (keep spaces), collapse spaces
        $t = preg_replace('/[^a-z0-9 ]+/u',' ', $t);
        $t = preg_replace('/\s+/u',' ', $t);
        return trim($t);
    }

    /**
     * Find similar jobs for the employer based on title similarity.
     * - Scans last $scan recent jobs of that employer
     * - Returns array of [job_id,title,created_at,status,percent,exact_match]
     * - percent based on similar_text() vs normalized titles
     */
    public static function findSimilarByEmployer(string $employer_id, array $newData, float $thresholdPercent = 85.0, int $scan = 25): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT job_id, title, created_at, status
            FROM jobs
            WHERE employer_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $employer_id);
        $stmt->bindValue(2, $scan, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (!$rows) return [];

        $newNorm = self::normalizeTitle($newData['title'] ?? '');
        if ($newNorm === '') return [];

        $matches = [];
        foreach ($rows as $r) {
            $oldNorm = self::normalizeTitle($r['title']);
            if ($oldNorm === '') continue;

            $percent = 0.0;
            // Use similar_text for a simple measure
            similar_text($newNorm, $oldNorm, $percent);

            $exact = ($oldNorm === $newNorm);
            if ($exact || $percent >= $thresholdPercent) {
                $matches[] = [
                    'job_id'      => $r['job_id'],
                    'title'       => $r['title'],
                    'created_at'  => $r['created_at'],
                    'status'      => $r['status'],
                    'percent'     => round($percent, 1),
                    'exact_match' => $exact,
                ];
            }
        }
        return $matches;
    }
}