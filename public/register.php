<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/Name.php';
require_once '../classes/User.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect inputs safely
    $name_raw     = $_POST['name']  ?? '';
    $email_raw    = $_POST['email'] ?? '';
    $password     = $_POST['password'] ?? '';
    $confirm      = $_POST['confirm_password'] ?? '';

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
            Helpers::flash('msg', 'Registration successful. Please log in.');
            Helpers::redirect('login.php');
        } else {
            $errors[] = 'Failed to create account. The email may already be registered.';
        }
    }
}

include '../includes/header.php';
include '../includes/nav.php';

// Keep selected values after validation errors
$roleSel = $_POST['role'] ?? 'job_seeker';
$disSel  = $_POST['disability'] ?? '';
?>
<div class="card border-0 shadow-sm">
  <div class="card-body p-4">
    <h2 class="h5 fw-semibold mb-3"><i class="bi bi-person-plus me-2"></i>Create your account</h2>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($e); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endforeach; ?>

    <form method="post" class="row g-3">
      <!-- Name -->
      <div class="col-12">
        <label class="form-label">Full Name</label>
        <input
          name="name"
          class="form-control"
          placeholder="First M. Surname"
          value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
          required
          pattern=".{3,}"
          title="Enter at least first and last name (e.g., John M. Doe)">
        <small class="text-muted">Format: First name, optional middle initial, and surname (e.g., John M. Doe)</small>
      </div>

      <!-- Email -->
      <div class="col-md-6">
        <label class="form-label">Email</label>
        <input
          type="email"
          name="email"
          class="form-control"
          placeholder="name@example.com"
          value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
          required>
      </div>

      <!-- Role -->
      <div class="col-md-6">
        <label class="form-label">Register as</label>
        <select name="role" id="role_select" class="form-select">
          <?php
            $opts = ['job_seeker' => 'Job Seeker', 'employer' => 'Employer'];
            foreach ($opts as $val => $label) {
              $sel = ($roleSel === $val) ? 'selected' : '';
              echo '<option value="' . htmlspecialchars($val) . '" ' . $sel . '>' . htmlspecialchars($label) . '</option>';
            }
          ?>
        </select>
      </div>

      <!-- Employer-only fields (hidden for Job Seeker) -->
      <div id="employer_fields" class="col-12" style="display:none;">
        <div class="border rounded p-3">
          <h3 class="h6 fw-semibold mb-3"><i class="bi bi-building me-2"></i>Employer details</h3>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">Company name</label>
              <input name="company_name" id="company_name" class="form-control"
                     value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>"
                     placeholder="Your company name">
            </div>
            <div class="col-md-6">
              <label class="form-label">Company email (optional)</label>
              <input type="email" name="business_email" id="business_email" class="form-control"
                     value="<?php echo htmlspecialchars($_POST['business_email'] ?? ''); ?>"
                     placeholder="hr@company.com">
            </div>
            <div class="col-md-6">
              <label class="form-label">Company website (optional)</label>
              <input type="url" name="company_website" id="company_website" class="form-control"
                     value="<?php echo htmlspecialchars($_POST['company_website'] ?? ''); ?>"
                     placeholder="https://example.com">
            </div>
            <div class="col-md-6">
              <label class="form-label">Company phone (optional)</label>
              <input name="company_phone" id="company_phone" class="form-control"
                     value="<?php echo htmlspecialchars($_POST['company_phone'] ?? ''); ?>"
                     placeholder="+63 900 000 0000">
            </div>
            <div class="col-md-6">
              <label class="form-label">Business permit / registration no. (optional)</label>
              <input name="business_permit_number" id="business_permit_number" class="form-control"
                     value="<?php echo htmlspecialchars($_POST['business_permit_number'] ?? ''); ?>"
                     placeholder="e.g., SEC/DTI/Mayor’s permit no.">
            </div>
            <div class="col-12 text-muted small">
              Employer accounts are reviewed; status starts as Pending until approved.
            </div>
          </div>
        </div>
      </div>

      <!-- Passwords -->
      <div class="col-md-6">
        <label class="form-label">Password</label>
        <input
          type="password"
          name="password"
          class="form-control"
          minlength="6"
          required
          placeholder="Minimum 6 characters">
      </div>
      <div class="col-md-6">
        <label class="form-label">Confirm Password</label>
        <input
          type="password"
          name="confirm_password"
          class="form-control"
          minlength="6"
          required
          placeholder="Re-type password">
      </div>

      <!-- Disability (optional; kept) -->
      <div class="col-12">
        <label class="form-label">Disability (optional)</label>
        <div class="row g-2">
          <div class="col-md-6">
            <select name="disability" class="form-select" id="disability_select">
              <?php
                $options = ['' => 'None', 'Visual' => 'Visual', 'Hearing' => 'Hearing', 'Mobility' => 'Mobility', 'Cognitive' => 'Cognitive', 'Speech' => 'Speech', 'Other' => 'Other (specify)'];
                foreach ($options as $val => $label) {
                    $sel = ($disSel === $val) ? 'selected' : '';
                    echo '<option value="' . htmlspecialchars($val) . '" ' . $sel . '>' . htmlspecialchars($label) . '</option>';
                }
              ?>
            </select>
          </div>
          <div class="col-md-6">
            <input
              id="disability_other"
              name="disability_other"
              class="form-control"
              placeholder="If Other, please specify"
              value="<?php echo htmlspecialchars($_POST['disability_other'] ?? ''); ?>">
          </div>
        </div>
        <small class="text-muted">Sharing this helps employers provide accommodations, but it’s optional.</small>
      </div>

      <div class="col-12 d-grid">
        <button class="btn btn-primary">
          <i class="bi bi-check2-circle me-1"></i>Create account
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Name: normalize to "First M. Surname" on blur
(function(){
  const input = document.querySelector('input[name="name"]');
  if (!input) return;

  const titleCaseComponent = (s) => {
    s = (s||'').toLowerCase();
    return s.split('-').map(part =>
      part.split("'").map(p => p ? p[0].toUpperCase() + p.slice(1) : '').join("'")
    ).join('-');
  };
  const particles = new Set(['da','de','del','dela','di','du','la','las','le','les','van','von','y','bin','binti','ibn','al']);
  const suffixMap = { jr:'Jr.', sr:'Sr.', ii:'II', iii:'III', iv:'IV', v:'V' };

  const normalize = (raw) => {
    let s = (raw||'').trim().replace(/\s+/g,' ');
    if (!s) return '';
    let toks = s.split(' ');
    toks = toks.map((t,i) => {
      const low = t.toLowerCase();
      if (i>0 && particles.has(low)) return low;
      return titleCaseComponent(t);
    });
    const lastLow = (toks[toks.length-1]||'').toLowerCase();
    if (suffixMap[lastLow]) toks[toks.length-1] = suffixMap[lastLow];

    if (toks.length < 2) return toks.join(' ');

    const first = toks[0];
    const last  = toks[toks.length-1];
    let mi = '';
    for (let i=1;i<toks.length-1;i++) {
      const p = toks[i].trim();
      if (p) { mi = p[0].toUpperCase() + '.'; break; }
    }
    return [first, mi, last].filter(Boolean).join(' ');
  };

  input.addEventListener('blur', () => { input.value = normalize(input.value); });
})();

// Disability: show extra input only when "Other" selected
(function(){
  const sel = document.getElementById('disability_select');
  const other = document.getElementById('disability_other');
  if (!sel || !other) return;

  const sync = () => {
    const isOther = sel.value === 'Other';
    other.disabled = !isOther;
    other.required = false; // optional overall
    other.style.display = isOther ? 'block' : 'none';
  };
  sel.addEventListener('change', sync);
  sync();
})();

// Role: show Employer fields only when role=employer, and require company_name then
(function(){
  const roleSel = document.getElementById('role_select');
  const block = document.getElementById('employer_fields');
  const name   = document.getElementById('company_name');
  const cemail = document.getElementById('business_email');
  const cweb   = document.getElementById('company_website');
  const cphone = document.getElementById('company_phone');

  if (!roleSel || !block) return;

  const setDisabled = (el, disabled) => { if (el) el.disabled = disabled; };

  const syncRole = () => {
    const isEmp = roleSel.value === 'employer';
    block.style.display = isEmp ? 'block' : 'none';

    // Enable fields when Employer; disable when Job Seeker so they don't submit
    setDisabled(name,   !isEmp);
    setDisabled(cemail, !isEmp);
    setDisabled(cweb,   !isEmp);
    setDisabled(cphone, !isEmp);

    // Only require company_name for employer
    if (name) name.required = isEmp;
  };

  roleSel.addEventListener('change', syncRole);
  // Initialize on load (respect postback selection)
  syncRole();
})();
</script>

<?php include '../includes/footer.php'; ?>