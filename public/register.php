<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';
require_once '../classes/Name.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (Helpers::isLoggedIn()) {
  $r = $_SESSION['role'] ?? '';
  if ($r === 'admin')      Helpers::redirect('admin_employers.php');
  elseif ($r === 'employer') Helpers::redirect('employer_dashboard.php');
  else                     Helpers::redirect('user_dashboard.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_raw     = $_POST['name'] ?? '';
    $email_raw    = $_POST['email'] ?? '';
  // No password at registration; admin will issue upon approval/verification

    $role_raw     = $_POST['role'] ?? 'job_seeker';
    $dis_sel      = $_POST['disability'] ?? '';
    $dis_other    = trim($_POST['disability_other'] ?? '');

  // Employer-only fields
    $company_name    = trim($_POST['company_name'] ?? '');
    $business_email  = trim($_POST['business_email'] ?? '');
    $company_website = trim($_POST['company_website'] ?? '');
    $company_phone   = trim($_POST['company_phone'] ?? '');
    $business_permit_number = trim($_POST['business_permit_number'] ?? '');

    // PWD ID (REQUIRED FOR JOB SEEKER)
    $pwd_id_number = trim($_POST['pwd_id_number'] ?? '');

  // Phone (optional at registration)
  $phone = trim($_POST['phone'] ?? '');

    // Normalize Name
    $displayName = Name::normalizeDisplayName($name_raw);
    if ($displayName === '') {
        $errors[] = 'Please enter your name properly (e.g. "Juan D. Dela Cruz").';
    }

    // Email
    $email = mb_strtolower(trim($email_raw));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

  // No password validation here

    // Role
    $allowedRoles = ['job_seeker','employer'];
    $role = in_array($role_raw, $allowedRoles, true) ? $role_raw : 'job_seeker';

  // Disability handling (wheelchair-focused)
  $disability = '';
  $selectedDis = $dis_sel;
  if ($dis_sel === 'Other Physical Disability') {
    $disability = $dis_other;
  } elseif ($dis_sel && $dis_sel !== 'None') {
    $disability = $dis_sel;
  }

    // Employer validations
    if ($role === 'employer') {
        if ($company_name === '') $errors[] = 'Company name is required for Employer accounts.';
    if ($business_permit_number === '') {
      $errors[] = 'Business Permit Number is required for Employer accounts.';
    } else {
      if (!preg_match('/^[A-Za-z0-9\-\/]{4,40}$/', $business_permit_number)) {
        $errors[] = 'Business Permit Number format invalid (letters, numbers, dash, slash; 4-40 chars).';
      }
    }
        if ($business_email !== '' && !filter_var($business_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid company email.';
        }
        if ($company_website !== '' && !filter_var($company_website, FILTER_VALIDATE_URL)) {
            $errors[] = 'Invalid company website (include http:// or https://).';
        }
    // Check uniqueness of Business Permit Number
    if (!$errors && $business_permit_number !== '') {
      try {
        $pdoChk = Database::getConnection();
        $st = $pdoChk->prepare("SELECT 1 FROM users WHERE business_permit_number = ? LIMIT 1");
        $st->execute([$business_permit_number]);
        if ($st->fetchColumn()) {
          $errors[] = 'Business Permit Number is already registered.';
        }
      } catch (Throwable $e) { /* ignore and let insert handle errors */ }
    }
    }

    // PWD ID VALIDATION (NOW STRICTLY REQUIRED FOR JOB SEEKER)
    if ($role === 'job_seeker') {
        if ($pwd_id_number === '') {
            $errors[] = 'Government PWD ID Number is required.';
        } else {
            if (!preg_match('/^[A-Za-z0-9\-]{4,30}$/', $pwd_id_number)) {
                $errors[] = 'PWD ID Number format invalid (letters, numbers, dash; 4-30 chars).';
            }
        }
    }

    if (!$errors) {
    $ok = User::register([
            'name' => $displayName,
            'email' => $email,
            // 'password' omitted; set by admin upon approval/verification
            'role' => $role,
            'disability' => $disability ?: null,
      'phone' => $phone,

            // Employer
            'company_name' => $role === 'employer' ? $company_name : '',
            'business_email' => $role === 'employer' ? $business_email : '',
            'company_website' => $role === 'employer' ? $company_website : '',
            'company_phone' => $role === 'employer' ? $company_phone : '',
            'business_permit_number' => $role === 'employer' ? $business_permit_number : '',

            // Job seeker PWD ID (required)
            'pwd_id_number' => $role === 'job_seeker' ? $pwd_id_number : ''
        ]);

        if ($ok) {
      if ($role === 'job_seeker') {
        Helpers::flash('msg','Registration successful. Your PWD ID is pending admin verification. You will receive your initial password via email once approved.');
      } else {
        Helpers::flash('msg','Registration successful. Employer account pending review. You will receive your initial password via email once approved.');
      }
      // Store email for login prefill
      $_SESSION['prefill_email'] = $email;
            Helpers::redirect('login.php');
        } else {
            $errors[] = 'Registration failed (email might already be registered or missing required data).';
        }
    }
}

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="row justify-content-center">
  <div class="col-lg-8 col-xl-6">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h2 class="h5 fw-semibold mb-3">
          <i class="bi bi-person-plus me-2"></i>Create Account
        </h2>

        <?php foreach ($errors as $e): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($e); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endforeach; ?>

        <form method="post" class="row g-3 needs-validation" novalidate>
          <div class="col-md-6">
            <label class="form-label">Name</label>
            <input name="name" type="text" class="form-control" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
          </div>
            <!-- Replace removed password fields with Phone -->
            <div class="col-md-6">
              <label class="form-label">Phone (optional)</label>
              <input name="phone" type="text" class="form-control" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
            </div>

          <div class="col-md-6">
            <label class="form-label">Account Type</label>
            <select name="role" id="roleSelect" class="form-select" required>
              <option value="job_seeker" <?php echo (($_POST['role'] ?? '')==='job_seeker')?'selected':''; ?>>Job Seeker</option>
              <option value="employer" <?php echo (($_POST['role'] ?? '')==='employer')?'selected':''; ?>>Employer</option>
            </select>
          </div>

          <div class="col-md-6" id="disabilityWrapper">
            <label class="form-label">Disability</label>
            <select name="disability" id="disabilitySelect" class="form-select">
              <?php
                $selected = $_POST['disability'] ?? 'None';
                $opts = [
                  'None',
                  'Spinal Cord Injury',
                  'Musculoskeletal Condition (e.g., cerebral palsy, muscular dystrophy)',
                  'Amputee (lower limb)',
                  'Neurological Condition (e.g., multiple sclerosis, spina bifida)',
                  'Other Physical Disability'
                ];
                foreach ($opts as $o) {
                    echo '<option value="'.htmlspecialchars($o).'" '.($selected===$o?'selected':'').'>'.$o.'</option>';
                }
              ?>
            </select>
            <input type="text"
                   name="disability_other"
                   id="disabilityOtherInput"
                   class="form-control mt-2 <?php echo ($selected==='Other Physical Disability')?'':'d-none'; ?>"
                   placeholder="Specify disability"
                   value="<?php echo htmlspecialchars($_POST['disability_other'] ?? ''); ?>">
          </div>

          <!-- PWD ID FIELD NOW REQUIRED FOR JOB SEEKER -->
          <div class="col-md-12" id="pwdIdContainer" style="display: <?php echo (($_POST['role'] ?? 'job_seeker')==='job_seeker')?'block':'none'; ?>">
            <label class="form-label">Government PWD ID Number <span class="text-danger">*</span></label>
            <input type="text"
                   name="pwd_id_number"
                   class="form-control"
                   placeholder="Enter your Government PWD ID"
                   value="<?php echo htmlspecialchars($_POST['pwd_id_number'] ?? ''); ?>">
            <div class="form-text">
              Required. Will be encrypted and set to Pending for admin verification.
            </div>
          </div>

          <!-- Employer section -->
          <div id="employerSection" style="display: <?php echo (($_POST['role'] ?? '')==='employer')?'block':'none'; ?>">
            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Company Name</label>
                <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Business Email (optional)</label>
                <input type="email" name="business_email" class="form-control" value="<?php echo htmlspecialchars($_POST['business_email'] ?? ''); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Company Website (optional)</label>
                <input type="text" name="company_website" class="form-control" placeholder="https://..." value="<?php echo htmlspecialchars($_POST['company_website'] ?? ''); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Company Phone (optional)</label>
                <input type="text" name="company_phone" class="form-control" value="<?php echo htmlspecialchars($_POST['company_phone'] ?? ''); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Business Permit # <span class="text-danger">*</span></label>
                <input type="text" name="business_permit_number" id="businessPermitInput" class="form-control" required pattern="[A-Za-z0-9\-/]{4,40}" value="<?php echo htmlspecialchars($_POST['business_permit_number'] ?? ''); ?>">
                <div class="form-text">Required. 4-40 chars (letters, numbers, dash or slash).</div>
              </div>
            </div>
          </div>

          <div class="col-12 d-grid mt-2">
            <button class="btn btn-primary">
              <i class="bi bi-person-plus me-1"></i>Create Account
            </button>
          </div>
          <div class="col-12">
            <div class="small text-muted mt-2">
              An initial password will be emailed to you after your account is approved/verified.
            </div>
          </div>
        </form>

        <hr class="mt-4">
        <div class="small text-muted">
          Already have an account? <a href="login.php">Log in</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
const roleSelect = document.getElementById('roleSelect');
const employerSection = document.getElementById('employerSection');
const pwdIdContainer = document.getElementById('pwdIdContainer');
const disabilitySelect = document.getElementById('disabilitySelect');
const disabilityOtherInput = document.getElementById('disabilityOtherInput');
const businessPermitInput = document.getElementById('businessPermitInput');

roleSelect.addEventListener('change', ()=>{
  if (roleSelect.value === 'employer') {
    employerSection.style.display = 'block';
    pwdIdContainer.style.display = 'none';
    if (businessPermitInput) businessPermitInput.setAttribute('required','required');
  } else {
    employerSection.style.display = 'none';
    pwdIdContainer.style.display = 'block';
    if (businessPermitInput) businessPermitInput.removeAttribute('required');
  }
});

disabilitySelect.addEventListener('change', ()=>{
  if (disabilitySelect.value === 'Other Physical Disability') {
    disabilityOtherInput.classList.remove('d-none');
  } else {
    disabilityOtherInput.classList.add('d-none');
  }
});
</script>