<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';
require_once '../classes/Name.php';
require_once '../classes/Taxonomy.php';

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

  // Employer-only fields
    $company_name    = trim($_POST['company_name'] ?? '');
    $business_email  = trim($_POST['business_email'] ?? '');
    $company_website = trim($_POST['company_website'] ?? '');
    $company_phone   = trim($_POST['company_phone'] ?? '');
    $business_permit_number = trim($_POST['business_permit_number'] ?? '');
  $company_owner_name = trim($_POST['company_owner_name'] ?? '');
  $contact_person_position = trim($_POST['contact_person_position'] ?? '');
  $contact_person_phone = trim($_POST['contact_person_phone'] ?? '');

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

  // Disability handling (general PWD categories)
  $disability = '';
  $selectedDis = $dis_sel;
  if ($dis_sel && $dis_sel !== 'None') {
    $disability = $dis_sel;
  }
  // Enforce disability requirement for Job Seekers
  if ($role === 'job_seeker') {
    if ($selectedDis === '' || $selectedDis === 'None') {
      $errors[] = 'Please select your disability (None is not allowed for Job Seeker accounts).';
    }
  }

  // Employer validations
  if ($role === 'employer') {
    if ($company_name === '') $errors[] = 'Company name is required for Employer accounts.';
    if ($company_owner_name === '') $errors[] = 'Owner / Proprietor name is required for Employer accounts.';
    if ($business_permit_number === '') {
      $errors[] = 'Business Permit Number is required for Employer accounts.';
    } else {
      if (!preg_match('/^[A-Za-z0-9\-\/]{4,40}$/', $business_permit_number)) {
        $errors[] = 'Business Permit Number format invalid (letters, numbers, dash, slash; 4-40 chars).';
      }
    }
    if ($contact_person_phone !== '' && !preg_match('/^[0-9 +().-]{6,30}$/', $contact_person_phone)) {
      $errors[] = 'Contact person phone format invalid.';
    }
    if ($company_phone !== '' && !preg_match('/^[0-9 +().-]{6,30}$/', $company_phone)) {
      $errors[] = 'Company phone format invalid.';
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
      } catch (Throwable $e) { /* ignore */ }
    }
  }

  // PWD ID validation (strict) for job seeker
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
      // password omitted (set later)
      'role' => $role,
      'disability' => $disability ?: null,
      'phone' => $phone,
      'company_name' => $role === 'employer' ? $company_name : '',
      'business_email' => $role === 'employer' ? $business_email : '',
      'company_website' => $role === 'employer' ? $company_website : '',
      'company_phone' => $role === 'employer' ? $company_phone : '',
      'business_permit_number' => $role === 'employer' ? $business_permit_number : '',
      'company_owner_name' => $role === 'employer' ? $company_owner_name : '',
      'contact_person_position' => $role === 'employer' ? $contact_person_position : '',
      'contact_person_phone' => $role === 'employer' ? $contact_person_phone : '',
      'pwd_id_number' => $role === 'job_seeker' ? $pwd_id_number : ''
    ]);

    if ($ok) {
      if ($role === 'job_seeker') {
        Helpers::flash('msg','Registration successful. Your PWD ID is pending admin verification. You will receive your initial password via email once approved.');
      } else {
        Helpers::flash('msg','Registration successful. Employer account pending review. You will receive your initial password via email once approved.');
      }
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
<div class="auth-page fade-up">
  <div class="auth-shell panels-touch">
    <div class="auth-card">
      <div class="auth-card-header">
        <div class="title-icon"><i class="bi bi-person-plus"></i></div>
        <h2 class="h4 mb-1">Create Your Account</h2>
        <p class="text-muted mb-0 small">Join the inclusive employment community.</p>
      </div>
      <div class="auth-card-body">
        <?php foreach ($errors as $e): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($e); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endforeach; ?>
        <?php if (method_exists('Helpers','getStructuredFlashes')): $fl = Helpers::getStructuredFlashes(); foreach($fl as $f): ?>
          <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?php echo $f['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endforeach; endif; ?>

        <form method="post" class="row g-3 needs-validation mt-1" novalidate>
          <div class="col-md-6 input-floating-label">
            <label>Name</label>
            <input name="name" type="text" class="form-control" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
          </div>
          <div class="col-md-6 input-floating-label">
            <label>Email</label>
            <input name="email" type="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
          </div>
          <div class="col-md-6 input-floating-label">
            <label>Phone (optional)</label>
            <input name="phone" type="text" class="form-control" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
          </div>
          <div class="col-md-6 input-floating-label">
            <label>Account Type</label>
            <select name="role" id="roleSelect" class="form-select" required>
              <option value="job_seeker" <?php echo (($_POST['role'] ?? '')==='job_seeker')?'selected':''; ?>>Job Seeker</option>
              <option value="employer" <?php echo (($_POST['role'] ?? '')==='employer')?'selected':''; ?>>Employer</option>
            </select>
          </div>

          <div class="col-md-6 input-floating-label" id="disabilityWrapper" style="display: <?php echo (($_POST['role'] ?? 'job_seeker')==='job_seeker')?'block':'none'; ?>;">
            <label>Disability <span class="text-danger" id="disabilityReqStar" style="display:none">*</span></label>
            <select name="disability" id="disabilitySelect" class="form-select">
              <?php
                $rolePosted = $_POST['role'] ?? 'job_seeker';
                $selected = $_POST['disability'] ?? '';
                echo '<option value=""'.($selected===''?' selected':'').'>— Select —</option>';
                $opts = Taxonomy::disabilityCategories();
                foreach ($opts as $o) {
                  echo '<option value="'.htmlspecialchars($o).'"'.($selected===$o?' selected':'').'>'.htmlspecialchars($o).'</option>';
                }
              ?>
            </select>
          </div>

          <div class="col-md-6 input-floating-label" id="pwdIdContainer" style="display: <?php echo (($_POST['role'] ?? 'job_seeker')==='job_seeker')?'block':'none'; ?>">
            <label>Government PWD ID Number <span class="text-danger">*</span></label>
            <input type="text" name="pwd_id_number" class="form-control" placeholder="Enter your Government PWD ID" value="<?php echo htmlspecialchars($_POST['pwd_id_number'] ?? ''); ?>">
          </div>

          <div id="employerSection" style="display: <?php echo (($_POST['role'] ?? '')==='employer')?'block':'none'; ?>">
            <div class="row g-3 mt-1">
              <div class="col-md-6 input-floating-label">
                <label>Company Name</label>
                <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
              </div>
              <div class="col-md-6 input-floating-label">
                <label>Owner / Proprietor Name</label>
                <input type="text" name="company_owner_name" class="form-control" value="<?php echo htmlspecialchars($_POST['company_owner_name'] ?? ''); ?>">
              </div>
              <div class="col-md-6 input-floating-label">
                <label>Business Email (optional)</label>
                <input type="email" name="business_email" class="form-control" value="<?php echo htmlspecialchars($_POST['business_email'] ?? ''); ?>">
              </div>
              <div class="col-md-6 input-floating-label">
                <label>Company Website (optional)</label>
                <input type="text" name="company_website" class="form-control" placeholder="https://..." value="<?php echo htmlspecialchars($_POST['company_website'] ?? ''); ?>">
              </div>
              <div class="col-md-6 input-floating-label">
                <label>Company Phone (optional)</label>
                <input type="text" name="company_phone" class="form-control" value="<?php echo htmlspecialchars($_POST['company_phone'] ?? ''); ?>">
              </div>
              <div class="col-md-6 input-floating-label">
                <label>Contact Person Position (optional)</label>
                <input type="text" name="contact_person_position" class="form-control" value="<?php echo htmlspecialchars($_POST['contact_person_position'] ?? ''); ?>">
              </div>
              <div class="col-md-6 input-floating-label">
                <label>Contact Person Phone (optional)</label>
                <input type="text" name="contact_person_phone" class="form-control" value="<?php echo htmlspecialchars($_POST['contact_person_phone'] ?? ''); ?>" placeholder="e.g. +63 912 345 6789">
              </div>
              <div class="col-md-6 input-floating-label">
                <label>Business Permit # <span class="text-danger">*</span></label>
                <input type="text" name="business_permit_number" id="businessPermitInput" class="form-control" required pattern="[A-Za-z0-9\-/]{4,40}" value="<?php echo htmlspecialchars($_POST['business_permit_number'] ?? ''); ?>">
              </div>
            </div>
          </div>

          <div class="col-12 d-grid mt-2">
            <button class="btn btn-gradient py-2">
              <i class="bi bi-person-plus me-1"></i>Create Account
            </button>
          </div>
          <div class="col-12">
            <div class="form-note mt-2">Initial password will be emailed after approval / verification.</div>
          </div>
        </form>

        <div class="form-sep">Have an account?</div>
        <div class="small text-center auth-links">
          <a href="login.php">Log in here</a>
        </div>
      </div>
    </div>

    <div class="auth-side-panel d-none d-md-flex flex-column justify-content-center text-center side-panel-centered">
      <div class="side-panel-inner">
        <h3 class="h4 fw-bold mb-3">Inclusive Talent Platform</h3>
        <p class="mb-4 text-white-75 small">We bridge employers and talented persons with disabilities through verified and skill-driven profiles.</p>
        <ul class="auth-feature-list small">
          <li><span class="fi-icon"><i class="bi bi-people"></i></span><span>Diverse, verified community</span></li>
          <li><span class="fi-icon"><i class="bi bi-layers"></i></span><span>Skill & experience tracking</span></li>
          <li><span class="fi-icon"><i class="bi bi-badge-ad"></i></span><span>Employer compliance checks</span></li>
          <li><span class="fi-icon"><i class="bi bi-award"></i></span><span>Certifications & achievements</span></li>
        </ul>
        <div class="small text-white-50 mt-4 pt-2">&copy; <?php echo date('Y'); ?> PWD Employment & Skills Portal</div>
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
const disabilityWrapper = document.getElementById('disabilityWrapper');
const disabilityReqStar = document.getElementById('disabilityReqStar');
const businessPermitInput = document.getElementById('businessPermitInput');
const formEl = document.querySelector('form');

function updateRoleSections(){
  if (roleSelect.value === 'employer') {
    employerSection.style.display = 'block';
    pwdIdContainer.style.display = 'none';
    if (businessPermitInput) businessPermitInput.setAttribute('required','required');
    disabilityReqStar.style.display = 'none';
    disabilitySelect.removeAttribute('required');
    if (disabilityWrapper) disabilityWrapper.style.display = 'none';
  } else {
    employerSection.style.display = 'none';
    pwdIdContainer.style.display = 'block';
    if (businessPermitInput) businessPermitInput.removeAttribute('required');
    disabilityReqStar.style.display = '';
    disabilitySelect.setAttribute('required','required');
    // no free-text option in general categories
    if (disabilityWrapper) disabilityWrapper.style.display = 'block';
  }
}

roleSelect.addEventListener('change', updateRoleSections);


roleSelect.addEventListener('change', ()=>{
  const val = roleSelect.value;
  const cur = disabilitySelect.value;
  const base = [
    'Learning disability',
    'Vision impairment',
    'Communication disorder',
    'Intellectual disability',
    'Orthopedic disability',
    'Chronic illness',
    'Hearing loss',
    'Speech impairment',
    'Hearing disability',
    'Physical disability'
  ];
  const list = base; // Employers won't see this field
  disabilitySelect.innerHTML = '';
  const ph = document.createElement('option'); ph.value=''; ph.textContent='— Select —';
  disabilitySelect.appendChild(ph);
  list.forEach(o=>{ const opt=document.createElement('option'); opt.value=o; opt.textContent=o; disabilitySelect.appendChild(opt); });
  const toSelect = list.includes(cur) ? cur : '';
  disabilitySelect.value = toSelect;
  // no dependent fields to toggle
});

formEl.addEventListener('submit', (e)=>{
  if (roleSelect.value === 'job_seeker') {
    if (!disabilitySelect.value || disabilitySelect.value === 'None') {
      e.preventDefault();
      disabilitySelect.setCustomValidity('Please select your disability (None is not allowed).');
      disabilitySelect.reportValidity();
      return;
    } else {
      disabilitySelect.setCustomValidity('');
    }
  }
});

updateRoleSections();
</script>
<script>
// Floating label activation
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('.input-floating-label input, .input-floating-label select').forEach(inp => {
    const wrap = inp.closest('.input-floating-label');
    function sync(){
      if (!wrap) return;
      // For selects and inputs with a placeholder, keep label floated to avoid visual overlap
      if (inp.tagName === 'SELECT' || inp.hasAttribute('placeholder')) {
        wrap.classList.add('filled');
        return; // always floated
      }
      if ((inp.value||'').trim() !== '') wrap.classList.add('filled'); else wrap.classList.remove('filled');
    }
    inp.addEventListener('focus', ()=> wrap && wrap.classList.add('focused'));
    inp.addEventListener('blur', ()=> wrap && wrap.classList.remove('focused'));
    inp.addEventListener('input', sync);
    sync();
  });
});
</script>
