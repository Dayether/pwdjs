<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';
require_once '../classes/Name.php'; /* assuming you have a Name utility; keep existing includes */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (Helpers::isLoggedIn()) {
    Helpers::redirect('index.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_raw     = $_POST['name'] ?? '';
    $email_raw    = $_POST['email'] ?? '';
    $password     = $_POST['password'] ?? '';
    $confirm      = $_POST['confirm'] ?? '';

    // Keep existing features
    $role_raw     = $_POST['role'] ?? 'job_seeker';
    $dis_sel      = $_POST['disability'] ?? '';
    $dis_other    = trim($_POST['disability_other'] ?? '');

    // Employer-only fields
    $company_name    = trim($_POST['company_name'] ?? '');
    $business_email  = trim($_POST['business_email'] ?? '');
    $company_website = trim($_POST['company_website'] ?? '');
    $company_phone   = trim($_POST['company_phone'] ?? '');
    $business_permit_number = trim($_POST['business_permit_number'] ?? '');

    // Name normalization
    $displayName = Name::normalizeDisplayName($name_raw);
    if ($displayName === '') {
        $errors[] = 'Please enter your name as "First M. Surname" (middle initial optional).';
    }

    // Email validation
    $email = mb_strtolower(trim($email_raw));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    // Password validation
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // Role: only public roles
    $allowedRoles = ['job_seeker','employer'];
    $role = in_array($role_raw, $allowedRoles, true) ? $role_raw : 'job_seeker';

    // Disability: optional
    $disability = '';
    if ($dis_sel === 'Other') {
        $disability = $dis_other;
    } elseif ($dis_sel && $dis_sel !== 'None') {
        $disability = $dis_sel;
    }

    // Employer validations (only if Employer)
    if ($role === 'employer') {
        if ($company_name === '') $errors[] = 'Company name is required for Employer accounts.';
        if ($business_email !== '' && !filter_var($business_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid company email address.';
        }
        if ($company_website !== '' && !filter_var($company_website, FILTER_VALIDATE_URL)) {
            $errors[] = 'Please enter a valid company website URL (including http:// or https://).';
        }
        // company_phone optional
        // business_permit_number optional
    }

    if (!$errors) {
        $ok = User::register([
            'name' => $displayName,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'disability' => $disability ?: null,

            // Employer fields
            'company_name' => $role === 'employer' ? $company_name : '',
            'business_email' => $role === 'employer' ? $business_email : '',
            'company_website' => $role === 'employer' ? $company_website : '',
            'company_phone' => $role === 'employer' ? $company_phone : '',
            'business_permit_number' => $role === 'employer' ? $business_permit_number : '',
        ]);

        if ($ok) {
            Helpers::flash('msg', 'Registration successful. Please log in.'); // ORIGINAL LINE (kept)

            // ADDED START: Overwrite the flash message for employer accounts to reflect pending approval
            if ($role === 'employer') {
                Helpers::flash('msg', 'Registration successful. Your employer account is pending approval.');
            }
            // ADDED END

            /* =========================================================
               ADDED: Persist company_website & company_phone (missing in INSERT)
               Using helper method to avoid modifying original User::register().
               ========================================================= */
            if ($role === 'employer' && ($company_website !== '' || $company_phone !== '')) {
                User::updateEmployerContactByEmail($email, $company_website, $company_phone);
            }

            Helpers::redirect('login.php');
        } else {
            $errors[] = 'Registration failed (email may already be in use).';
        }
    }
}

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="row justify-content-center">
  <div class="col-lg-8 col-xl-7">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h2 class="h5 fw-semibold mb-3"><i class="bi bi-person-plus me-2"></i>Create Account</h2>

        <?php foreach ($errors as $e): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($e); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endforeach; ?>

        <form method="post" class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Name</label>
            <input name="name" class="form-control" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Password</label>
            <input name="password" type="password" class="form-control" required>
          </div>
            <div class="col-md-6">
              <label class="form-label">Confirm Password</label>
              <input name="confirm" type="password" class="form-control" required>
            </div>

          <div class="col-md-6">
            <label class="form-label">Account Type</label>
            <select name="role" class="form-select">
              <option value="job_seeker" <?php if(($_POST['role'] ?? '')==='job_seeker') echo 'selected'; ?>>Job Seeker</option>
              <option value="employer" <?php if(($_POST['role'] ?? '')==='employer') echo 'selected'; ?>>Employer</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Disability</label>
            <select name="disability" class="form-select">
              <option value="None">None</option>
              <option value="Visual" <?php if(($_POST['disability'] ?? '')==='Visual') echo 'selected'; ?>>Visual</option>
              <option value="Hearing" <?php if(($_POST['disability'] ?? '')==='Hearing') echo 'selected'; ?>>Hearing</option>
              <option value="Mobility" <?php if(($_POST['disability'] ?? '')==='Mobility') echo 'selected'; ?>>Mobility</option>
              <option value="Other" <?php if(($_POST['disability'] ?? '')==='Other') echo 'selected'; ?>>Other</option>
            </select>
          </div>

          <div class="col-12" id="disabilityOtherWrap" style="<?php echo (($_POST['disability'] ?? '')==='Other')?'':'display:none;'; ?>">
            <label class="form-label">If Other, please specify</label>
            <input name="disability_other" class="form-control" value="<?php echo htmlspecialchars($_POST['disability_other'] ?? ''); ?>">
          </div>

          <!-- Employer-only section -->
          <div id="employerFields" style="<?php echo (($_POST['role'] ?? '')==='employer')?'':'display:none;'; ?>">
            <hr>
            <h6 class="fw-semibold mb-2"><i class="bi bi-building me-1"></i>Employer Details</h6>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Company Name</label>
                <input name="company_name" class="form-control" value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Business Email (optional)</label>
                <input name="business_email" type="email" class="form-control" value="<?php echo htmlspecialchars($_POST['business_email'] ?? ''); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Company Website (optional)</label>
                <input name="company_website" class="form-control" placeholder="https://example.com" value="<?php echo htmlspecialchars($_POST['company_website'] ?? ''); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Company Phone (optional)</label>
                <input name="company_phone" class="form-control" value="<?php echo htmlspecialchars($_POST['company_phone'] ?? ''); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Permit / Business No. (optional)</label>
                <input name="business_permit_number" class="form-control" value="<?php echo htmlspecialchars($_POST['business_permit_number'] ?? ''); ?>">
              </div>
            </div>
          </div>

          <div class="col-12 d-grid mt-3">
            <button class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Create Account</button>
          </div>
        </form>

        <hr class="my-4">
        <div class="small">Already have an account? <a href="login.php">Log in</a></div>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
const roleSelect = document.querySelector('select[name="role"]');
const employerFields = document.getElementById('employerFields');
const disSel = document.querySelector('select[name="disability"]');
const disOtherWrap = document.getElementById('disabilityOtherWrap');

function toggleEmployer() {
  if (roleSelect.value === 'employer') employerFields.style.display = '';
  else employerFields.style.display = 'none';
}
function toggleDisOther() {
  if (disSel.value === 'Other') disOtherWrap.style.display = '';
  else disOtherWrap.style.display = 'none';
}
roleSelect.addEventListener('change', toggleEmployer);
disSel.addEventListener('change', toggleDisOther);
</script>