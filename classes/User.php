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

    public ?string $pwd_id_number = null;
    public ?string $pwd_id_last4 = null;
    public ?string $pwd_id_status = null;

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

    public ?string $created_at = null;
    public ?string $password = null;

    public function __construct(array $row) {
        foreach ($row as $k=>$v) $this->$k = $v;
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

    /* Extended to include employer fields */
    public static function updateProfileExtended(string $userId, array $data): bool {
        $allowed = [
            // shared / job seeker
            'name','disability','resume','video_intro',
            'date_of_birth','gender','phone','region','province','city','full_address',
            'education','education_level','primary_skill_summary',
            'disability_type','disability_severity','assistive_devices',
            'pwd_id_number','pwd_id_last4',
            // employer-specific
            'company_name','business_email','company_website','company_phone',
            'business_permit_number','employer_doc'
        ];

        if (isset($data['pwd_id_number']) && $data['pwd_id_number'] !== '') {
            if (class_exists('Sensitive')) {
                $data['pwd_id_number'] = Sensitive::encrypt($data['pwd_id_number']);
            }
        }

        $set = [];
        $vals = [];
        foreach ($data as $k=>$v) {
            if (in_array($k,$allowed,true)) {
                $set[] = "$k = ?";
                $vals[] = $v;
            }
        }
        if (!$set) return false;
        $vals[] = $userId;
        $sql = "UPDATE users SET ".implode(',',$set)." WHERE user_id=?";
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($vals);
    }

    public static function register(array $input): bool {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$input['email']]);
        if ($stmt->fetch()) return false;

        $user_id = Helpers::generateSmartId('USR');
        $hash = password_hash($input['password'], PASSWORD_DEFAULT);

        $experience = 0;
        $education  = '';
        $disability = $input['disability'] ?? null;

        $company = '';
        $bizEmail = '';
        $permit = null;
        $empStatus = 'Pending';
        $empDoc = null;

        if (($input['role'] ?? '') === 'employer') {
            $company  = trim($input['company_name'] ?? '');
            $bizEmail = trim($input['business_email'] ?? '');
            $permit   = trim($input['business_permit_number'] ?? '');
            if ($permit === '') $permit = null;
        }

        $pwdIdEncrypted = null;
        $pwdIdLast4 = null;
        $pwdStatus = 'None';

        if (($input['role'] ?? '') === 'job_seeker') {
            $rawPwdId = preg_replace('/\s+/', '', $input['pwd_id_number'] ?? '');
            if ($rawPwdId !== '') {
                if (class_exists('Sensitive')) {
                    $pwdIdEncrypted = Sensitive::encrypt($rawPwdId);
                } else {
                    $pwdIdEncrypted = $rawPwdId;
                }
                $pwdIdLast4 = substr($rawPwdId, -4);
                $pwdStatus = 'Pending';
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO users
            (user_id, name, email, password, role, experience, education, disability,
             company_name, business_email, business_permit_number, employer_status, employer_doc,
             pwd_id_number, pwd_id_last4, pwd_id_status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

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
            $pwdStatus
        ]);
    }
}