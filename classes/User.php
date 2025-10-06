<?php
require_once __DIR__.'/Database.php';
require_once __DIR__.'/Helpers.php';

class User {
    public string $user_id;
    public string $name;
    public string $email;
    public string $role;

    public ?string $disability = null;
    public ?string $disability_type = null;
    public ?string $disability_severity = null;
    public ?string $assistive_devices = null;

    public ?string $date_of_birth = null;
    public ?string $gender = null;
    public ?string $phone = null;
    public ?string $region = null;
    public ?string $province = null;
    public ?string $city = null;
    public ?string $full_address = null;

    public ?string $education = null;
    public ?string $education_level = null;
    public ?string $primary_skill_summary = null;

    public ?string $resume = null;
    public ?string $video_intro = null;

    // Preferences / Mini-resume additions
    public ?string $expected_salary_currency = null;
    public ?int $expected_salary_min = null;
    public ?int $expected_salary_max = null;
    public ?string $expected_salary_period = null; // monthly|yearly|hourly
    public ?string $interests = null; // free text or comma-separated tags
    public ?string $accessibility_preferences = null; // comma-separated tags
    public ?string $preferred_location = null; // e.g., City/Region
    public ?string $preferred_work_setup = null; // On-site|Hybrid|Remote

    public ?string $pwd_id_number = null;
    public ?string $pwd_id_last4 = null;
    public ?string $pwd_id_status = null;
    public ?string $job_seeker_status = null; // Active | Suspended
    public ?string $last_status_reason = null; // generic reason for last status change
    public ?string $last_suspension_reason = null; // reason specifically when suspended

    public ?int $experience = null;
    public ?int $profile_completeness = null;
    public ?string $profile_last_calculated = null;

    /* Employer fields */
    public ?string $company_name = null;
    public ?string $business_email = null;
    public ?string $company_website = null;
    public ?string $company_phone = null;
    public ?string $business_permit_number = null;
    public ?string $employer_status = null;
    public ?string $employer_doc = null;
    public ?string $profile_picture = null; // new profile image path

    public ?string $created_at = null;
    public ?string $password = null;

    public function __construct(array $row) {
        foreach ($row as $k=>$v) $this->$k = $v;
    }

    /**
     * Internal: ensure reason columns exist (so code does not break if migration not yet run).
     */
    private static function ensureReasonColumnsCached(): array {
        static $cache = null;
        if ($cache !== null) return $cache;
        $cache = ['last_status_reason'=>false,'last_suspension_reason'=>false];
        try {
            $pdo = Database::getConnection();
            $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $c) {
                $f = $c['Field'] ?? '';
                if (isset($cache[$f])) $cache[$f] = true;
            }
        } catch (Throwable $e) {}
        return $cache;
    }

    /** Store last status reason (and optionally suspension reason). */
    public static function persistStatusReason(string $userId, ?string $reason, bool $isSuspension=false): void {
        $reason = trim((string)$reason);
        if ($reason === '') return; // don't store empty
        $cols = self::ensureReasonColumnsCached();
        if (!$cols['last_status_reason'] && !$cols['last_suspension_reason']) return; // nothing to do
        $pdo = Database::getConnection();
        $set = [];$vals=[];
        if ($cols['last_status_reason']) { $set[]='last_status_reason=?'; $vals[]=$reason; }
        if ($isSuspension && $cols['last_suspension_reason']) { $set[]='last_suspension_reason=?'; $vals[]=$reason; }
        if (!$set) return;
        $vals[]=$userId;
        try {
            $st = $pdo->prepare('UPDATE users SET '.implode(',', $set).' WHERE user_id=? LIMIT 1');
            $st->execute($vals);
        } catch (Throwable $e) {}
    }

    /** Insert audit log row for status change */
    public static function logStatusChange(string $actorUserId, string $targetUserId, string $targetRole, string $field, string $newValue, ?string $reason): void {
        try {
            $pdo = Database::getConnection();
            $details = [
                'target_user_id' => $targetUserId,
                'target_role'    => $targetRole,
                'changed_field'  => $field,
                'new_value'      => $newValue,
                'reason'         => $reason,
            ];
            $st = $pdo->prepare("INSERT INTO admin_tasks_log (task, actor_user_id, mode, users_scanned, users_updated, jobs_scanned, jobs_updated, details) VALUES ('status_change', ?, 'single', 0, 0, 0, 0, ?)");
            $st->execute([$actorUserId, json_encode($details, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
        } catch (Throwable $e) { /* ignore logging failure */ }
    }

    public static function findById(string $id): ?self {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return new self($row);
    }

    public static function findByEmail(string $email): ?self {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return new self($row);
    }

    /* Extended to include employer + PWD fields */
    public static function updateProfileExtended(string $userId, array $data): bool {
        $allowed = [
            // shared / job seeker
            'name','disability','resume','video_intro',
            'date_of_birth','gender','phone','region','province','city','full_address',
            'education','education_level','primary_skill_summary',
            'disability_type','disability_severity','assistive_devices',
            'pwd_id_number','pwd_id_last4',
            // preferences
            'expected_salary_currency','expected_salary_min','expected_salary_max','expected_salary_period',
            'interests','accessibility_preferences','preferred_location','preferred_work_setup',
            // profile_picture will be conditionally allowed after schema check
            // employer-specific
            'company_name','business_email','company_website','company_phone',
            'business_permit_number','employer_doc'
        ];

        // Detect existing columns on `users` table once and cache
        static $userColumns = null;
        if ($userColumns === null) {
            $userColumns = [];
            try {
                $pdoCheck = Database::getConnection();
                $cols = $pdoCheck->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $c) {
                    $name = $c['Field'] ?? null;
                    if ($name) $userColumns[$name] = true;
                }
            } catch (Throwable $e) {
                // If introspection fails, keep as empty to avoid adding unknown fields
                $userColumns = [];
            }
        }
        // Conditionally allow profile_picture if column exists
        if (isset($userColumns['profile_picture'])) {
            $allowed[] = 'profile_picture';
        } else {
            unset($data['profile_picture']);
        }

        // If changing PWD ID, encrypt + reset status to Pending
        if (isset($data['pwd_id_number']) && $data['pwd_id_number'] !== '') {
            $raw = preg_replace('/\s+/', '', $data['pwd_id_number']);
            if (class_exists('Sensitive')) {
                $data['pwd_id_number'] = Sensitive::encrypt($raw);
            } else {
                $data['pwd_id_number'] = $raw;
            }
            $data['pwd_id_last4'] = substr($raw, -4);
            $allowed[] = 'pwd_id_status';
            $data['pwd_id_status'] = 'Pending';
        }

        $set = [];
        $vals = [];
        foreach ($data as $k=>$v) {
            if (in_array($k,$allowed,true) && isset($userColumns[$k])) {
                $set[] = "$k = ?";
                $vals[] = $v;
            }
        }
        if (!$set) return false;
        $vals[] = $userId;
        $sql = "UPDATE users SET ".implode(',',$set)." WHERE user_id=?";
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($vals);
        } catch (Throwable $e) {
            // Swallow and return false so the caller can surface a friendly message
            return false;
        }
    }

    public static function register(array $input): bool {
        $pdo = Database::getConnection();

        // Email uniqueness
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$input['email']]);
        if ($stmt->fetch()) return false;

    $user_id = Helpers::generateSmartId('USR');
    $hash = null; // password to be issued by admin upon approval/verification

    $experience = 0;
    $education  = '';
    $disability = $input['disability'] ?? null;
    $phone      = trim((string)($input['phone'] ?? '')) ?: null;

        // Employer defaults
        $company = '';
        $bizEmail = '';
        $permit = null;
        $empStatus = 'Pending';
        $empDoc = null;

        if (($input['role'] ?? '') === 'employer') {
            $company  = trim($input['company_name'] ?? '');
            $bizEmail = trim($input['business_email'] ?? '');
            $permit   = trim($input['business_permit_number'] ?? '');
            // Enforce non-empty business permit for employers (defensive; UI also validates)
            if ($permit === '') {
                return false; // missing required business permit
            }
            // Basic format safeguard (alphanumeric, dash, slash, 4-40 chars)
            if (!preg_match('/^[A-Za-z0-9\-\/]{4,40}$/', $permit)) {
                return false; // invalid format
            }
        }

        // PWD ID (REQUIRED now for job_seeker)
        $pwdIdEncrypted = null;
        $pwdIdLast4 = null;
        $pwdStatus = 'None';

        if (($input['role'] ?? '') === 'job_seeker') {
            $rawPwdId = preg_replace('/\s+/', '', $input['pwd_id_number'] ?? '');
            if ($rawPwdId === '') {
                // Hard requirement
                return false;
            }
            if (class_exists('Sensitive')) {
                $pwdIdEncrypted = Sensitive::encrypt($rawPwdId);
            } else {
                $pwdIdEncrypted = $rawPwdId;
            }
            $pwdIdLast4 = substr($rawPwdId, -4);
            $pwdStatus = 'Pending';
        }

        $stmt = $pdo->prepare("
            INSERT INTO users
            (user_id, name, email, password, role, experience, education, disability,
             company_name, business_email, business_permit_number, employer_status, employer_doc,
             pwd_id_number, pwd_id_last4, pwd_id_status, phone)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

            try {
        return $stmt->execute([
            $user_id,
            trim($input['name']),
            trim($input['email']),
            $hash,
            $input['role'],
            $experience,
            $education,
            $disability,
            $company,
            $bizEmail,
            $permit,
            $empStatus,
            $empDoc,
            $pwdIdEncrypted,
            $pwdIdLast4,
            $pwdStatus,
            $phone
        ]);
            } catch (PDOException $e) {
                // Duplicate business permit unique key
                if (strpos($e->getMessage(), 'uniq_business_permit') !== false) {
                    return false;
                }
                throw $e;
            }
    }

    /* ===================== Accessibility Preferences (normalized) ===================== */
    public static function listAccessibilityPrefs(string $userId): array {
        try {
            $pdo = Database::getConnection();
            $st = $pdo->prepare("SELECT tag FROM user_accessibility_prefs WHERE user_id=? ORDER BY tag ASC");
            $st->execute([$userId]);
            return array_column($st->fetchAll(PDO::FETCH_ASSOC),'tag');
        } catch (Throwable $e) { return []; }
    }

    public static function setAccessibilityPrefs(string $userId, array $tags): bool {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $norm = [];
            foreach ($tags as $t) {
                $tt = trim((string)$t);
                if ($tt !== '') $norm[$tt] = true;
            }
            $uniqueTags = array_keys($norm);
            $pdo->prepare("DELETE FROM user_accessibility_prefs WHERE user_id=?")->execute([$userId]);
            if ($uniqueTags) {
                $ins = $pdo->prepare("INSERT INTO user_accessibility_prefs (user_id, tag) VALUES (?,?)");
                foreach ($uniqueTags as $tg) $ins->execute([$userId, $tg]);
            }
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            try { $pdo->rollBack(); } catch (Throwable $e2) {}
            return false;
        }
    }

    /* ===================== ADMIN HELPERS FOR JOB SEEKERS ===================== */

    public static function listJobSeekers(?string $statusFilter = null): array {
        $pdo = Database::getConnection();
        $where = "role='job_seeker'";
        $params = [];
        if ($statusFilter !== null && $statusFilter !== '') {
            $where .= " AND pwd_id_status = ?";
            $params[] = $statusFilter;
        }
        $sql = "
            SELECT user_id,name,email,disability,pwd_id_last4,pwd_id_status,created_at
            FROM users
            WHERE $where
            ORDER BY (pwd_id_status='Pending') DESC, created_at DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function jobSeekerCounts(): array {
        $pdo = Database::getConnection();
        $rows = $pdo->query("
            SELECT pwd_id_status AS status, COUNT(*) c
            FROM users
            WHERE role='job_seeker'
            GROUP BY pwd_id_status
        ")->fetchAll(PDO::FETCH_ASSOC);
        $out = [
            'total'    => 0,
            'Verified' => 0,
            'Pending'  => 0,
            'Rejected' => 0,
            'None'     => 0,
            'Other'    => 0
        ];
        foreach ($rows as $r) {
            $s = $r['status'] ?: 'None';
            $out['total'] += (int)$r['c'];
            if (isset($out[$s])) {
                $out[$s] += (int)$r['c'];
            } else {
                $out['Other'] += (int)$r['c'];
            }
        }
        return $out;
    }

    public static function getFullPwdId(User $user): ?string {
        if (!$user->pwd_id_number) return null;
        if (class_exists('Sensitive')) {
            try { return Sensitive::decrypt($user->pwd_id_number); }
            catch (Throwable $e) { return null; }
        }
        return $user->pwd_id_number;
    }

    public static function setPwdIdStatus(string $userId, string $status): bool {
        if (!in_array($status,['Verified','Rejected'],true)) return false;
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE users SET pwd_id_status=? WHERE user_id=? AND role='job_seeker'");
        return $stmt->execute([$status,$userId]);
    }

    /* ===================== ADMIN: JOB SEEKER ACCOUNT STATUS (SUSPEND) ===================== */
    public static function updateJobSeekerStatus(string $userId, string $newStatus): bool {
        $valid = ['Active','Suspended'];
        if (!in_array($newStatus,$valid,true)) return false;
        $pdo = Database::getConnection();
        $cur = $pdo->prepare("SELECT job_seeker_status FROM users WHERE user_id=? AND role='job_seeker' LIMIT 1");
        $cur->execute([$userId]);
        $current = $cur->fetchColumn();
        if ($current === false) return false;
        if ($current === $newStatus) return true;
        $upd = $pdo->prepare("UPDATE users SET job_seeker_status=? WHERE user_id=? AND role='job_seeker'");
        return $upd->execute([$newStatus,$userId]);
    }

    /* ===================== ADMIN: EMPLOYER STATUS ===================== */
    public static function updateEmployerStatus(string $userId, string $newStatus): bool {
        $valid = ['Approved','Suspended','Pending','Rejected'];
        if (!in_array($newStatus,$valid,true)) return false;
        $pdo = Database::getConnection();
        // Check current status first (avoid unnecessary write; allow idempotent true)
        $curStmt = $pdo->prepare("SELECT employer_status FROM users WHERE user_id=? AND role='employer' LIMIT 1");
        $curStmt->execute([$userId]);
        $current = $curStmt->fetchColumn();
        if ($current === false) return false; // not found / not employer
        if ($current === $newStatus) return true; // already that status
        $upd = $pdo->prepare("UPDATE users SET employer_status=? WHERE user_id=? AND role='employer'");
        return $upd->execute([$newStatus,$userId]);
    }

    /* ===================== CANDIDATE SALARY SEARCH (EMPLOYERS) ===================== */
    // Contract:
    //  Inputs (array criteria): budget_min (int|null), budget_max (int|null), period (monthly|yearly|hourly), include_unspecified(bool), page(int>=1), limit(int<=100)
    //  Overlap logic: candidate_min <= employer_budget_max AND candidate_max >= employer_budget_min
    //  Null handling: candidates with NULL min or max are excluded from indexed overlap query; optionally appended if include_unspecified=true
    //  Returns: ['results'=>[], 'page'=>int, 'limit'=>int, 'has_more'=>bool, 'total_overlapping'=>int]
    public static function searchCandidatesBySalary(array $criteria): array {
        $pdo = Database::getConnection();

        $budgetMin = isset($criteria['budget_min']) && is_numeric($criteria['budget_min']) ? (int)$criteria['budget_min'] : null;
        $budgetMax = isset($criteria['budget_max']) && is_numeric($criteria['budget_max']) ? (int)$criteria['budget_max'] : null;
        $period    = isset($criteria['period']) ? strtolower((string)$criteria['period']) : 'monthly';
        $includeUnspecified = !empty($criteria['include_unspecified']);
        $page  = isset($criteria['page']) && (int)$criteria['page'] > 0 ? (int)$criteria['page'] : 1;
        $limit = isset($criteria['limit']) && (int)$criteria['limit'] > 0 ? (int)$criteria['limit'] : 25;
        if ($limit > 100) $limit = 100; // safety cap

        $allowedPeriods = ['monthly','yearly','hourly'];
        if (!in_array($period, $allowedPeriods, true)) {
            $period = 'monthly';
        }

        // Normalize budgets: if only one given, mirror to avoid open-ended huge range issues
        if ($budgetMin === null && $budgetMax !== null) $budgetMin = 0;
        if ($budgetMax === null && $budgetMin !== null) $budgetMax = $budgetMin; // treat exact value
        if ($budgetMin === null && $budgetMax === null) {
            // Default broad range (will effectively return lowest expected first)
            $budgetMin = 0;
            $budgetMax = 999999999; // sufficiently high sentinel
        }
        if ($budgetMin > $budgetMax) {
            [$budgetMin,$budgetMax] = [$budgetMax,$budgetMin];
        }

        $offset = ($page - 1) * $limit;
        $overlapSql = "
            SELECT user_id,name,primary_skill_summary,expected_salary_currency,expected_salary_min,expected_salary_max,expected_salary_period,profile_picture,experience
            FROM users
            WHERE role='job_seeker'
              AND (job_seeker_status IS NULL OR job_seeker_status!='Suspended')
              AND expected_salary_period = :period
              AND expected_salary_min IS NOT NULL
              AND expected_salary_max IS NOT NULL
              AND expected_salary_min <= :bmax
              AND expected_salary_max >= :bmin
            ORDER BY expected_salary_min ASC, expected_salary_max ASC, name ASC
            LIMIT $limit OFFSET $offset
        ";
        $st = $pdo->prepare($overlapSql);
        $st->execute([
            ':period' => $period,
            ':bmax'   => $budgetMax,
            ':bmin'   => $budgetMin
        ]);
        $results = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Total overlapping (for pagination UI). Separate COUNT(*) to leverage index with same predicates.
        $countSql = "
            SELECT COUNT(*) FROM users
            WHERE role='job_seeker'
              AND (job_seeker_status IS NULL OR job_seeker_status!='Suspended')
              AND expected_salary_period = :period
              AND expected_salary_min IS NOT NULL
              AND expected_salary_max IS NOT NULL
              AND expected_salary_min <= :bmax
              AND expected_salary_max >= :bmin
        ";
        $cst = $pdo->prepare($countSql);
        $cst->execute([
            ':period' => $period,
            ':bmax'   => $budgetMax,
            ':bmin'   => $budgetMin
        ]);
        $totalOverlapping = (int)$cst->fetchColumn();

        // Optionally append unspecified salary candidates (null min or max) AFTER overlap results if include_unspecified requested and page=1.
        // We only append on first page to avoid duplication complexity across pages.
        if ($includeUnspecified && $page === 1 && count($results) < $limit) {
            $remaining = $limit - count($results);
            if ($remaining > 0) {
                $unsql = "
                    SELECT user_id,name,primary_skill_summary,expected_salary_currency,expected_salary_min,expected_salary_max,expected_salary_period,profile_picture,experience
                    FROM users
                    WHERE role='job_seeker'
                      AND (job_seeker_status IS NULL OR job_seeker_status!='Suspended')
                      AND (expected_salary_min IS NULL OR expected_salary_max IS NULL)
                      AND (expected_salary_period = :period OR expected_salary_period IS NULL)
                    ORDER BY name ASC
                    LIMIT $remaining
                ";
                $ust = $pdo->prepare($unsql);
                $ust->execute([':period'=>$period]);
                $unspecified = $ust->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $results = array_merge($results, $unspecified);
            }
        }

        $hasMore = ($offset + $limit) < $totalOverlapping; // Only counts overlapping set (unspecified not counted towards total pages)

        return [
            'results'           => $results,
            'page'              => $page,
            'limit'             => $limit,
            'has_more'          => $hasMore,
            'total_overlapping' => $totalOverlapping,
            'budget_min'        => $budgetMin,
            'budget_max'        => $budgetMax,
            'period'            => $period,
            'include_unspecified' => (bool)$includeUnspecified
        ];
    }
}