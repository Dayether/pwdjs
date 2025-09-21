<?php
require_once __DIR__ . '/Job.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/Sensitive.php';

class User {

    // Core
    public string $user_id;
    public string $name;
    public string $email;
    public string $password;
    public string $role;
    public ?int $experience;
    public ?string $education;
    public ?string $disability;
    public ?string $resume;
    public ?string $video_intro;

    // Extended personal
    public ?string $date_of_birth = null;
    public ?string $gender = null;
    public ?string $phone = null;
    public ?string $region = null;
    public ?string $province = null;
    public ?string $city = null;
    public ?string $full_address = null;
    public ?string $education_level = null;
    public ?string $primary_skill_summary = null;

    // Disability extended
    public ?string $disability_type = null;
    public ?string $disability_severity = null;
    public ?string $assistive_devices = null;
    public ?string $pwd_id_number = null; // encrypted
    public ?string $pwd_id_last4 = null;
    public ?string $pwd_id_status = null; // None|Pending|Verified|Rejected

    // Completeness
    public ?int $profile_completeness = null;
    public ?string $profile_last_calculated = null;

    // Employer-specific
    public string $company_name;
    public string $business_email;
    public ?string $company_website;
    public ?string $company_phone;
    public string $business_permit_number;
    public string $employer_status;
    public ?string $employer_doc;

    public function __construct(array $data = []) {
        $this->user_id     = $data['user_id']     ?? '';
        $this->name        = $data['name']        ?? '';
        $this->email       = $data['email']       ?? '';
        $this->password    = $data['password']    ?? '';
        $this->role        = $data['role']        ?? 'job_seeker';
        $this->experience  = isset($data['experience']) ? (int)$data['experience'] : 0;
        $this->education   = $data['education']   ?? '';
        $this->disability  = $data['disability']  ?? null;
        $this->resume      = $data['resume']      ?? null;
        $this->video_intro = $data['video_intro'] ?? null;

        $this->date_of_birth = $data['date_of_birth'] ?? null;
        $this->gender        = $data['gender'] ?? null;
        $this->phone         = $data['phone'] ?? null;
        $this->region        = $data['region'] ?? null;
        $this->province      = $data['province'] ?? null;
        $this->city          = $data['city'] ?? null;
        $this->full_address  = $data['full_address'] ?? null;

        $this->education_level       = $data['education_level'] ?? null;
        $this->primary_skill_summary = $data['primary_skill_summary'] ?? null;

        $this->disability_type     = $data['disability_type'] ?? null;
        $this->disability_severity = $data['disability_severity'] ?? null;
        $this->assistive_devices   = $data['assistive_devices'] ?? null;
        $this->pwd_id_number       = $data['pwd_id_number'] ?? null;
        $this->pwd_id_last4        = $data['pwd_id_last4'] ?? null;
        $this->pwd_id_status       = $data['pwd_id_status'] ?? null;

        $this->profile_completeness    = isset($data['profile_completeness']) ? (int)$data['profile_completeness'] : 0;
        $this->profile_last_calculated = $data['profile_last_calculated'] ?? null;

        $this->company_name           = $data['company_name'] ?? '';
        $this->business_email         = $data['business_email'] ?? '';
        $this->company_website        = $data['company_website'] ?? null;
        $this->company_phone          = $data['company_phone'] ?? null;
        $this->business_permit_number = $data['business_permit_number'] ?? '';
        $this->employer_status        = $data['employer_status'] ?? 'Pending';
        $this->employer_doc           = $data['employer_doc'] ?? null;
    }

    public static function findById(string $id): ?User {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return new self($row);
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

        // Employer-specific
        $company = '';
        $bizEmail = '';
        $permit = null;
        $empStatus = 'Pending';
        $empDoc = null;

        if ($input['role'] === 'employer') {
            $company  = trim($input['company_name'] ?? '');
            $bizEmail = trim($input['business_email'] ?? '');
            $permit   = trim($input['business_permit_number'] ?? '');
            if ($permit === '') $permit = null;
        }

        // Job Seeker PWD ID logic
        $pwdIdEncrypted = null;
        $pwdIdLast4 = null;
        $pwdStatus = 'None';

        if ($input['role'] === 'job_seeker') {
            $rawPwdId = preg_replace('/\s+/', '', $input['pwd_id_number'] ?? '');
            if ($rawPwdId !== '') {
                $pwdIdEncrypted = Sensitive::encrypt($rawPwdId);
                $pwdIdLast4 = substr($rawPwdId, -4);
                $pwdStatus = 'Pending'; // awaiting admin validation
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

    public static function updateProfileExtended(string $userId, array $data): bool {
        $allowed = [
            'name','disability','resume','video_intro',
            'date_of_birth','gender','phone','region','province','city','full_address',
            'education_level','primary_skill_summary',
            'disability_type','disability_severity','assistive_devices',
            'pwd_id_number','pwd_id_last4'
        ];
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
}