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
    public ?string $applicable_pwd_types = null; // legacy single/CSV value(s)

    public string $location_city = '';
    public string $location_region = '';
    public string $remote_option = 'Work From Home';
    public string $employment_type = 'Full time';
    public string $salary_currency = 'PHP';
    public ?int $salary_min = null;
    public ?int $salary_max = null;
    public string $salary_period = 'monthly';
    public ?string $job_image = null;

    public string $status = 'Open';
    // Moderation fields (new)
    public string $moderation_status = 'Pending'; // Pending|Approved|Rejected
    public ?string $moderation_reason = null;
    public ?string $moderation_decided_at = null;
    public ?string $moderation_decided_by = null;
    public string $created_at = '';
    public ?string $archived_at = null; // soft delete timestamp

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
        $this->applicable_pwd_types  = $row['applicable_pwd_types'] ?? null;
        $this->location_city         = $row['location_city'] ?? '';
        $this->location_region       = $row['location_region'] ?? '';
        $this->remote_option         = $row['remote_option'] ?? 'Work From Home';
        $this->employment_type       = $row['employment_type'] ?? 'Full time';
        $this->salary_currency       = $row['salary_currency'] ?? 'PHP';
        $this->salary_min            = array_key_exists('salary_min',$row) ? (is_null($row['salary_min'])? null : (int)$row['salary_min']) : null;
        $this->salary_max            = array_key_exists('salary_max',$row) ? (is_null($row['salary_max'])? null : (int)$row['salary_max']) : null;
        $this->salary_period         = $row['salary_period'] ?? 'monthly';
        $this->job_image             = $row['job_image'] ?? null;
        $this->status                = $row['status'] ?? 'Open';
        $this->moderation_status     = $row['moderation_status'] ?? ($row ? 'Approved' : 'Pending');
        $this->moderation_reason     = $row['moderation_reason'] ?? null;
        $this->moderation_decided_at = $row['moderation_decided_at'] ?? null;
        $this->moderation_decided_by = $row['moderation_decided_by'] ?? null;
        $this->created_at            = $row['created_at'] ?? '';
        $this->archived_at           = $row['archived_at'] ?? null;
    }

    /* Retrieval */
    public static function findById(string $job_id, bool $includeArchived = false): ?Job {
        $pdo = Database::getConnection();
        $sql = "SELECT * FROM jobs WHERE job_id = ?" . ($includeArchived? '' : ' AND archived_at IS NULL');
        $st = $pdo->prepare($sql); $st->execute([$job_id]);
        $row = $st->fetch();
        return $row ? new Job($row) : null;
    }

    public static function listByEmployer(string $employer_id, bool $includeArchived = false): array {
        $pdo = Database::getConnection();
        $sql = "SELECT job_id,title,created_at,location_city,location_region,remote_option,employment_type,salary_currency,salary_min,salary_max,salary_period,status,archived_at,moderation_status,moderation_reason FROM jobs WHERE employer_id = ?" . ($includeArchived? '' : ' AND archived_at IS NULL') . ' ORDER BY created_at DESC';
        $st = $pdo->prepare($sql); $st->execute([$employer_id]);
        return $st->fetchAll();
    }

    public static function listApplicablePwdTypes(string $job_id): array {
        $pdo = Database::getConnection();
        $st = $pdo->prepare('SELECT pwd_type FROM job_applicable_pwd_types WHERE job_id = ? ORDER BY pwd_type');
        $st->execute([$job_id]);
        return array_map(fn($r)=>$r['pwd_type'],$st->fetchAll());
    }

    /* Create / Update */
    public static function create(array $data, string $employer_id): bool {
        $pdo = Database::getConnection();
        $job_id = Helpers::generateSmartId('JOB');
        $reqEdu = Taxonomy::canonicalizeEducation($data['required_education'] ?? '');
        if ($reqEdu === null) $reqEdu = '';
        $remote = 'Work From Home';
        $status = 'Open';

    $stmt = $pdo->prepare("INSERT INTO jobs (job_id, employer_id, title, description, required_experience, required_education, required_skills_input, accessibility_tags, applicable_pwd_types, location_city, location_region, remote_option, employment_type, salary_currency, salary_min, salary_max, salary_period, job_image, status, moderation_status) VALUES (:job_id,:employer_id,:title,:description,:required_experience,:required_education,:required_skills_input,:accessibility_tags,:applicable_pwd_types,:location_city,:location_region,:remote_option,:employment_type,:salary_currency,:salary_min,:salary_max,:salary_period,:job_image,:status,:moderation_status)");

        $ok = $stmt->execute([
            ':job_id' => $job_id,
            ':employer_id'=>$employer_id,
            ':title'=>$data['title'],
            ':description'=>$data['description'],
            ':required_experience'=>(int)($data['required_experience']??0),
            ':required_education'=>$reqEdu,
            ':required_skills_input'=>$data['required_skills_input'] ?? '',
            ':accessibility_tags'=>$data['accessibility_tags'] ?? '',
            ':applicable_pwd_types'=>$data['applicable_pwd_types'] ?? null,
            ':location_city'=>$data['location_city'] ?? '',
            ':location_region'=>$data['location_region'] ?? '',
            ':remote_option'=>$remote,
            ':employment_type'=>$data['employment_type'] ?? 'Full time',
            ':salary_currency'=>$data['salary_currency'] ?? 'PHP',
            ':salary_min'=>($data['salary_min'] ?? null) !== '' ? $data['salary_min'] : null,
            ':salary_max'=>($data['salary_max'] ?? null) !== '' ? $data['salary_max'] : null,
            ':salary_period'=>$data['salary_period'] ?? 'monthly',
            ':job_image'=>$data['job_image'] ?? null,
            ':status'=>$status,
            ':moderation_status'=>'Pending',
        ]);

        if ($ok) {
            $skillsRaw = Helpers::parseSkillInput($data['required_skills_input'] ?? '');
            Skill::assignSkillsToJob($job_id, $skillsRaw);
            self::syncApplicablePwdTypes($job_id, $data['applicable_pwd_types'] ?? null);
        }
        return $ok;
    }

    /* Moderation */
    public static function listPendingModeration(int $limit = 100): array {
        $pdo = Database::getConnection();
        $st = $pdo->prepare("SELECT j.*, u.company_name FROM jobs j JOIN users u ON u.user_id=j.employer_id WHERE j.archived_at IS NULL AND j.moderation_status='Pending' ORDER BY j.created_at ASC LIMIT ?");
        $st->bindValue(1,$limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    public static function moderate(string $job_id, string $actor_user_id, string $action, ?string $reason = null): bool {
        $allowed = ['approve','reject'];
        if (!in_array($action,$allowed,true)) return false;
        $targetStatus = $action === 'approve' ? 'Approved' : 'Rejected';
        $pdo = Database::getConnection();
        // Fetch current state
        $st = $pdo->prepare("SELECT moderation_status, moderation_reason FROM jobs WHERE job_id=? LIMIT 1");
        $st->execute([$job_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        $curStatus = $row['moderation_status'] ?? 'Pending';
        $curReason = (string)($row['moderation_reason'] ?? '');
        $newReason = trim((string)$reason) ?: null;

        // No-op if nothing changes
        if ($curStatus === $targetStatus && ($newReason ?? '') === $curReason) {
            return true;
        }

        // Apply update regardless of prior status
        $upd = $pdo->prepare("UPDATE jobs SET moderation_status=?, moderation_reason=?, moderation_decided_at=NOW(), moderation_decided_by=? WHERE job_id=? LIMIT 1");
        $ok = $upd->execute([$targetStatus, $newReason, $actor_user_id, $job_id]);
        if ($ok && $upd->rowCount()>=0) { // rowCount may be 0 on same values; we handled no-op above
            // Audit log
            try {
                $details = [ 'job_id'=>$job_id, 'old_status'=>$curStatus, 'new_status'=>$targetStatus, 'reason'=>$reason ];
                $pdo->prepare("INSERT INTO admin_tasks_log (task, actor_user_id, mode, users_scanned, users_updated, jobs_scanned, jobs_updated, details) VALUES ('job_moderation', ?, 'single', 0,0,0,1, ?)")
                    ->execute([$actor_user_id, json_encode($details, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
            } catch (Throwable $e) {}
        }
        return $ok;
    }

    public static function update(string $job_id, array $data, string $employer_id): bool {
        $pdo = Database::getConnection();
        $reqEdu = Taxonomy::canonicalizeEducation($data['required_education'] ?? '');
        if ($reqEdu === null) $reqEdu = '';
        $remote = 'Work From Home';

        $stmt = $pdo->prepare("UPDATE jobs SET title=:title, description=:description, required_experience=:required_experience, required_education=:required_education, required_skills_input=:required_skills_input, accessibility_tags=:accessibility_tags, applicable_pwd_types=:applicable_pwd_types, location_city=:location_city, location_region=:location_region, remote_option=:remote_option, employment_type=:employment_type, salary_currency=:salary_currency, salary_min=:salary_min, salary_max=:salary_max, salary_period=:salary_period, job_image=:job_image WHERE job_id=:job_id AND employer_id=:employer_id LIMIT 1");

        $ok = $stmt->execute([
            ':title'=>$data['title'],
            ':description'=>$data['description'],
            ':required_experience'=>(int)($data['required_experience']??0),
            ':required_education'=>$reqEdu,
            ':required_skills_input'=>$data['required_skills_input'] ?? '',
            ':accessibility_tags'=>$data['accessibility_tags'] ?? '',
            ':applicable_pwd_types'=>$data['applicable_pwd_types'] ?? null,
            ':location_city'=>$data['location_city'] ?? '',
            ':location_region'=>$data['location_region'] ?? '',
            ':remote_option'=>$remote,
            ':employment_type'=>$data['employment_type'] ?? 'Full time',
            ':salary_currency'=>$data['salary_currency'] ?? 'PHP',
            ':salary_min'=>($data['salary_min'] ?? null) !== '' ? $data['salary_min'] : null,
            ':salary_max'=>($data['salary_max'] ?? null) !== '' ? $data['salary_max'] : null,
            ':salary_period'=>$data['salary_period'] ?? 'monthly',
            ':job_image'=>$data['job_image'] ?? null,
            ':job_id'=>$job_id,
            ':employer_id'=>$employer_id,
        ]);

        if ($ok) {
            $skillsRaw = Helpers::parseSkillInput($data['required_skills_input'] ?? '');
            Skill::assignSkillsToJob($job_id, $skillsRaw);
            self::syncApplicablePwdTypes($job_id, $data['applicable_pwd_types'] ?? null);
        }
        return $ok;
    }

    /* Soft Delete / Restore */
    public static function delete(string $job_id, string $employer_id): bool {
        $pdo = Database::getConnection();
        $st = $pdo->prepare('UPDATE jobs SET archived_at = NOW() WHERE job_id = ? AND employer_id = ? AND archived_at IS NULL LIMIT 1');
        $st->execute([$job_id, $employer_id]);
        return $st->rowCount() > 0;
    }

    public static function restore(string $job_id, string $employer_id): bool {
        $pdo = Database::getConnection();
        $st = $pdo->prepare('UPDATE jobs SET archived_at = NULL WHERE job_id = ? AND employer_id = ? AND archived_at IS NOT NULL LIMIT 1');
        $st->execute([$job_id, $employer_id]);
        return $st->rowCount() > 0;
    }

    public static function adminDelete(string $job_id, bool $hard = false): bool {
        $pdo = Database::getConnection();
        if (!$hard) {
            $st = $pdo->prepare('UPDATE jobs SET archived_at = NOW() WHERE job_id = ? AND archived_at IS NULL LIMIT 1');
            $st->execute([$job_id]);
            return $st->rowCount() > 0;
        }
        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM applications WHERE job_id = ?')->execute([$job_id]);
            try { $pdo->prepare('DELETE FROM job_skills WHERE job_id = ?')->execute([$job_id]); } catch (\PDOException $e) { if ($e->getCode() !== '42S02') throw $e; }
            $st = $pdo->prepare('DELETE FROM jobs WHERE job_id = ? LIMIT 1');
            $st->execute([$job_id]);
            $deleted = $st->rowCount() > 0;
            $pdo->commit();
            return $deleted;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return false;
        }
    }

    public static function adminRestore(string $job_id): bool {
        $pdo = Database::getConnection();
        $st = $pdo->prepare('UPDATE jobs SET archived_at = NULL WHERE job_id = ? AND archived_at IS NOT NULL LIMIT 1');
        $st->execute([$job_id]);
        return $st->rowCount() > 0;
    }

    /* Status Helpers */
    public static function setStatus(string $job_id, string $status): bool {
        $allowed = ['Open','Suspended','Closed'];
        if (!in_array($status,$allowed,true)) return false;
        $pdo = Database::getConnection();
        $st = $pdo->prepare('UPDATE jobs SET status = :st WHERE job_id = :id LIMIT 1');
        return $st->execute([':st'=>$status, ':id'=>$job_id]);
    }

    public static function setStatusByEmployer(string $employer_id, string $status, ?string $onlyCurrentStatus = null): int {
        $allowed = ['Open','Suspended','Closed'];
        if (!in_array($status,$allowed,true)) return 0;
        $pdo = Database::getConnection();
        if ($onlyCurrentStatus) {
            $st = $pdo->prepare('UPDATE jobs SET status = :st WHERE employer_id = :eid AND status = :cur');
            $st->execute([':st'=>$status, ':eid'=>$employer_id, ':cur'=>$onlyCurrentStatus]);
        } else {
            $st = $pdo->prepare('UPDATE jobs SET status = :st WHERE employer_id = :eid');
            $st->execute([':st'=>$status, ':eid'=>$employer_id]);
        }
        return $st->rowCount();
    }

    /* Similarity */
    protected static function normalizeTitle(string $title): string {
        $t = mb_strtolower($title); $t = preg_replace('/[^a-z0-9 ]+/u',' ', $t); $t = preg_replace('/\s+/u',' ', $t); return trim($t);
    }

    public static function findSimilarByEmployer(string $employer_id, array $newData, float $thresholdPercent = 85.0, int $scan = 25): array {
        $pdo = Database::getConnection();
        $st = $pdo->prepare('SELECT job_id, title, created_at, status FROM jobs WHERE employer_id = ? AND archived_at IS NULL ORDER BY created_at DESC LIMIT ?');
        $st->bindValue(1,$employer_id); $st->bindValue(2,$scan, PDO::PARAM_INT); $st->execute();
        $rows = $st->fetchAll(); if (!$rows) return [];
        $newNorm = self::normalizeTitle($newData['title'] ?? ''); if ($newNorm==='') return [];
        $matches = [];
        foreach ($rows as $r) {
            $oldNorm = self::normalizeTitle($r['title']); if ($oldNorm==='') continue; $percent=0.0; similar_text($newNorm,$oldNorm,$percent); $exact=($oldNorm===$newNorm);
            if ($exact || $percent >= $thresholdPercent) {
                $matches[] = [ 'job_id'=>$r['job_id'], 'title'=>$r['title'], 'created_at'=>$r['created_at'], 'status'=>$r['status'], 'percent'=>round($percent,1), 'exact_match'=>$exact ];
            }
        }
        return $matches;
    }

    /* Normalized PWD Types Sync */
    protected static function parsePwdTypes(?string $raw): array {
        if (!$raw) return []; $parts = array_filter(array_map('trim', explode(',', $raw)), fn($v)=>$v!==''); $uniq = array_values(array_unique($parts)); sort($uniq,SORT_STRING|SORT_FLAG_CASE); return $uniq;
    }
    public static function syncApplicablePwdTypes(string $job_id, ?string $raw): void {
        $types = self::parsePwdTypes($raw); $pdo = Database::getConnection(); $existing = self::listApplicablePwdTypes($job_id); $toDelete = array_diff($existing,$types); $toAdd = array_diff($types,$existing);
        if ($toDelete) { $in = implode(',', array_fill(0,count($toDelete),'?')); $st = $pdo->prepare("DELETE FROM job_applicable_pwd_types WHERE job_id = ? AND pwd_type IN ($in)"); $st->execute(array_merge([$job_id], array_values($toDelete))); }
        if ($toAdd) { $st = $pdo->prepare('INSERT IGNORE INTO job_applicable_pwd_types (job_id,pwd_type) VALUES (?,?)'); foreach ($toAdd as $t) { $st->execute([$job_id,$t]); } }
    }
}