<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Taxonomy.php';
require_once __DIR__ . '/Job.php';

class User {
    public string $user_id;
    public string $name;
    public string $email;
    public string $password; // hashed
    public string $role;
    public ?int $experience;
    public ?string $education;
    public ?string $disability;
    public ?string $resume;
    public ?string $video_intro;

    // Employer fields
    public string $company_name;
    public string $business_email;
    public ?string $company_website;     // NEW
    public ?string $company_phone;       // NEW
    public string $business_permit_number;
    public string $employer_status; // Pending, Approved, Suspended, Rejected
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

        $this->company_name = $data['company_name'] ?? '';
        $this->business_email = $data['business_email'] ?? '';
        $this->company_website = $data['company_website'] ?? null;   // NEW
        $this->company_phone   = $data['company_phone']   ?? null;   // NEW
        $this->business_permit_number = $data['business_permit_number'] ?? '';
        $this->employer_status = $data['employer_status'] ?? 'Pending';
        $this->employer_doc = $data['employer_doc'] ?? null;
    }

    public static function register(array $input): bool {
        $pdo = Database::getConnection();

        // Unique email
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$input['email']]);
        if ($stmt->fetch()) return false;

        $user_id = Helpers::generateSmartId('USR');
        $hash = password_hash($input['password'], PASSWORD_DEFAULT);

        // Default values
        $experience = 0;
        $education = '';
        $resume = null;
        $video = null;

        // Employer-specific fields
        $company = '';
        $bizEmail = '';
        $permit = '';
        $empStatus = 'Pending';
        $empDoc = null;

        if ($input['role'] === 'employer') {
            $company = trim($input['company_name'] ?? '');
            $bizEmail = trim($input['business_email'] ?? '');
            $permit = trim($input['business_permit_number'] ?? '');
            $empStatus = 'Pending';
            $empDoc = $input['employer_doc'] ?? null; // path if uploaded
        }

        // ADDED NORMALIZE PERMIT:
        // Para maiwasan ang duplicate entry '' sa unique index,
        // ginagawa nating NULL kapag wala talagang laman.
        // Kung gusto mong gawing required kapag employer, pwede mong idagdag:
        // if ($input['role']==='employer' && $permit==='') return false; (pero hindi ko ginawa per instruction mo na huwag magbawas/baguhin ang behavior)
        if ($permit === '') {
            $permit = null;
        }

        // Note: company_website and company_phone are optional and can be NULL by default,
        // so we don't need to include them in INSERT.
        $stmt = $pdo->prepare("INSERT INTO users
            (user_id, name, email, password, role, experience, education, disability, resume, video_intro,
             company_name, business_email, business_permit_number, employer_status, employer_doc)
            VALUES
            (:user_id, :name, :email, :password, :role, :experience, :education, :disability, :resume, :video_intro,
             :company_name, :business_email, :business_permit_number, :employer_status, :employer_doc)");

        return $stmt->execute([
            ':user_id' => $user_id,
            ':name' => $input['name'],
            ':email' => $input['email'],
            ':password' => $hash,
            ':role' => $input['role'],
            ':experience' => $experience,
            ':education' => $education,
            ':disability' => $input['disability'] ?? null,
            ':resume' => $resume,
            ':video_intro' => $video,
            ':company_name' => $company,
            ':business_email' => $bizEmail,
            ':business_permit_number' => $permit, // now NULL kapag walang laman
            ':employer_status' => $empStatus,
            ':employer_doc' => $empDoc
        ]);
    }

    public static function authenticate(string $email, string $password): ?User {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row && password_verify($password, $row['password'])) {
            return new User($row);
        }
        return null;
    }

    public static function findById(string $user_id): ?User {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
               // SELECT * will include company_website and company_phone if they exist
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();
        return $row ? new User($row) : null;
    }

    public static function updateEmployerStatus(string $user_id, string $status): bool {
        $pdo = Database::getConnection();
        $allowed = ['Pending','Approved','Suspended','Rejected'];
        if (!in_array($status, $allowed, true)) return false;

        $stmt = $pdo->prepare("UPDATE users SET employer_status = :st WHERE user_id = :uid AND role = 'employer'");
        $ok = $stmt->execute([':st'=>$status, ':uid'=>$user_id]);

        // Propagate to jobs (requires jobs.status column)
        if ($ok) {
            if ($status === 'Approved') {
                // Re-open only those previously Suspended by the system
                Job::setStatusByEmployer($user_id, 'Open', 'Suspended');
            } elseif ($status === 'Suspended' || $status === 'Rejected') {
                // Suspend all jobs under this employer
                Job::setStatusByEmployer($user_id, 'Suspended');
            }
        }
        return $ok;
    }

    public static function updateProfile(string $user_id, array $data): bool {
        $pdo = Database::getConnection();

        $fields = [
            'name' => $data['name'],
            'disability' => $data['disability'] ?? null,
        ];
        if (isset($data['resume'])) $fields['resume'] = $data['resume'];
        if (isset($data['video_intro'])) $fields['video_intro'] = $data['video_intro'];

        // Allow employers to update company info if provided
        if (isset($data['company_name'])) $fields['company_name'] = $data['company_name'];
        if (isset($data['business_email'])) $fields['business_email'] = $data['business_email'];
        if (isset($data['company_website'])) $fields['company_website'] = $data['company_website']; // NEW
        if (isset($data['company_phone']))   $fields['company_phone']   = $data['company_phone'];   // NEW
        if (isset($data['business_permit_number'])) {
            $permit = trim($data['business_permit_number']);
            if ($permit === '') $permit = null; // ADDED: normalize on update too
            $fields['business_permit_number'] = $permit;
        }
        if (isset($data['employer_doc'])) $fields['employer_doc'] = $data['employer_doc'];

        $set = [];
        $params = [];
        foreach ($fields as $k => $v) {
            $set[] = "$k = :$k";
            $params[":$k"] = $v;
        }
        $params[':user_id'] = $user_id;

        $sql = "UPDATE users SET " . implode(', ', $set) . " WHERE user_id = :user_id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /* =========================================================
       ADDED: Post-registration patch method to store website & phone
       Without editing the original register() (add-only fix).
       ========================================================= */
    public static function updateEmployerContactByEmail(string $email, ?string $website, ?string $phone): bool {
        $pdo = Database::getConnection();
        $website = trim((string)$website);
        $phone   = trim((string)$phone);
        if ($website === '') $website = null;
        if ($phone === '') $phone = null;

        $stmt = $pdo->prepare("
            UPDATE users
               SET company_website = :cw,
                   company_phone   = :cp
             WHERE email = :email
               AND role = 'employer'
            LIMIT 1
        ");
        return $stmt->execute([
            ':cw' => $website,
            ':cp' => $phone,
            ':email' => $email
        ]);
    }

    /* OPTIONAL helper (not yet used but handy) */
    public static function updateEmployerContactById(string $user_id, ?string $website, ?string $phone): bool {
        $pdo = Database::getConnection();
        $website = trim((string)$website);
        $phone   = trim((string)$phone);
        if ($website === '') $website = null;
        if ($phone === '') $phone = null;
        $stmt = $pdo->prepare("
            UPDATE users
               SET company_website = :cw,
                   company_phone   = :cp
             WHERE user_id = :uid
               AND role = 'employer'
            LIMIT 1
        ");
        return $stmt->execute([
            ':cw' => $website,
            ':cp' => $phone,
            ':uid' => $user_id
        ]);
    }
}