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

    // New fields
    public string $location_city = '';
    public string $location_region = '';
    public string $remote_option = 'Work From Home';
    public string $employment_type = 'Full time';
    public string $salary_currency = 'PHP';
    public ?int $salary_min = null;
    public ?int $salary_max = null;
    public string $salary_period = 'monthly';

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
                   remote_option, employment_type, salary_currency, salary_min, salary_max, salary_period
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

        $stmt = $pdo->prepare("
            INSERT INTO jobs (
              job_id, employer_id, title, description,
              required_experience, required_education, required_skills_input, accessibility_tags,
              location_city, location_region,
              remote_option, employment_type,
              salary_currency, salary_min, salary_max, salary_period
            ) VALUES (
              :job_id, :employer_id, :title, :description,
              :required_experience, :required_education, :required_skills_input, :accessibility_tags,
              :location_city, :location_region,
              :remote_option, :employment_type,
              :salary_currency, :salary_min, :salary_max, :salary_period
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
        ]);

        if ($ok) {
            if (method_exists('Helpers', 'parseSkillInput') && method_exists('Skill', 'assignSkillsToJob')) {
                $skillsRaw = Helpers::parseSkillInput($data['required_skills_input'] ?? '');
                Skill::assignSkillsToJob($job_id, $skillsRaw);
            }
        }

        return $ok;
    }

    // Corrected signature to match call site: Job::update($job_id, $data, $employer_id)
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
              salary_period = :salary_period
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
        ]);

        if ($ok) {
            if (method_exists('Helpers', 'parseSkillInput') && method_exists('Skill', 'assignSkillsToJob')) {
                $skillsRaw = Helpers::parseSkillInput($data['required_skills_input'] ?? '');
                Skill::assignSkillsToJob($job_id, $skillsRaw);
            }
        }

        return $ok;
    }

    // Owner-scoped delete
    public static function delete(string $job_id, string $employer_id): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM jobs WHERE job_id = ? AND employer_id = ? LIMIT 1");
        return $stmt->execute([$job_id, $employer_id]);
    }

    // Admin-only delete: remove related rows and then the job (no employer constraint)
    public static function adminDelete(string $job_id): bool {
        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();

            // Delete applications tied to this job
            $stmt = $pdo->prepare("DELETE FROM applications WHERE job_id = ?");
            $stmt->execute([$job_id]);

            // Delete job_skills mapping if your schema uses it
            try {
                $stmt = $pdo->prepare("DELETE FROM job_skills WHERE job_id = ?");
                $stmt->execute([$job_id]);
            } catch (PDOException $e) {
                // 42S02 = base table or view not found; ignore if mapping table doesn't exist
                if ($e->getCode() !== '42S02') throw $e;
            }

            // Finally, delete the job
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
}