<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';
require_once 'classes/User.php';
require_once 'classes/Name.php';
require_once 'classes/Mail.php'; // Needed for Mail::isEnabled() & Mail::send
require_once 'classes/Password.php'; // For PasswordHelper

Helpers::requireRole('admin');
if (session_status() === PHP_SESSION_NONE) session_start();

$errors = [];
$created = false; $initialPassword = null; $newUserId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_raw    = $_POST['name'] ?? '';
    $email_raw   = $_POST['email'] ?? '';
    $company_name = trim($_POST['company_name'] ?? '');
    $business_email = trim($_POST['business_email'] ?? '');
    $company_website = trim($_POST['company_website'] ?? '');
    $company_phone = trim($_POST['company_phone'] ?? '');
    $business_permit_number = trim($_POST['business_permit_number'] ?? '');
  $company_owner_name = trim($_POST['company_owner_name'] ?? '');
  $contact_person_position = trim($_POST['contact_person_position'] ?? '');
  $contact_person_phone = trim($_POST['contact_person_phone'] ?? '');
    $issue_password = true; // always generate initial password
    $auto_approve = isset($_POST['auto_approve']);

  $displayName = Name::normalizeDisplayName($name_raw);
  if ($displayName === '') $errors[] = 'Please enter a valid contact person name.';
  $ownerDisplay = Name::normalizeDisplayName($company_owner_name);

    $email = mb_strtolower(trim($email_raw));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email.';

  if ($company_name === '') $errors[] = 'Company name is required.';
  if ($company_owner_name === '') $errors[] = 'Company owner / proprietor name is required.';
  if ($contact_person_phone !== '' && !preg_match('/^[0-9 +().-]{6,30}$/', $contact_person_phone)) $errors[] = 'Contact person phone format invalid.';
  if ($company_phone !== '' && !preg_match('/^[0-9 +().-]{6,30}$/', $company_phone)) $errors[] = 'Company phone format invalid.';
    if ($business_permit_number === '') {
        $errors[] = 'Business Permit Number is required.';
    } elseif (!preg_match('/^[A-Za-z0-9\-\/]{4,40}$/', $business_permit_number)) {
        $errors[] = 'Business Permit Number format invalid.';
    } else {
        try {
            $pdoChk = Database::getConnection();
            $st = $pdoChk->prepare('SELECT 1 FROM users WHERE business_permit_number=? LIMIT 1');
            $st->execute([$business_permit_number]);
            if ($st->fetchColumn()) $errors[] = 'Business Permit Number already exists.';
        } catch (Throwable $e) { /* ignore */ }
    }
    if ($business_email !== '' && !filter_var($business_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid business email.';
    if ($company_website !== '' && !filter_var($company_website, FILTER_VALIDATE_URL)) $errors[] = 'Invalid company website (include http:// or https://).';

  if (!$errors) {
    $ok = User::register([
      'name' => $ownerDisplay ?: $displayName,
            'email' => $email,
            'role' => 'employer',
            'disability' => null,
            'phone' => $company_phone,
            'company_name' => $company_name,
            'business_email' => $business_email,
            'company_website' => $company_website,
            'company_phone' => $company_phone,
            'business_permit_number' => $business_permit_number,
      'company_owner_name' => $company_owner_name,
      'contact_person_position' => $contact_person_position,
      'contact_person_phone' => $contact_person_phone,
            'pwd_id_number' => ''
        ]);
        if ($ok) {
            // Retrieve user_id
            $pdo = Database::getConnection();
            $row = $pdo->prepare('SELECT user_id FROM users WHERE email=? LIMIT 1');
            $row->execute([$email]);
            $newUserId = $row->fetchColumn();
            if ($auto_approve && $newUserId) {
                try {
                    $stmtU = $pdo->prepare('UPDATE users SET employer_status="Approved" WHERE user_id=?');
                    $stmtU->execute([$newUserId]);
                } catch (Throwable $e) { /* ignore */ }
            }
      if ($newUserId && class_exists('PasswordHelper')) {
        try { 
          $initialPassword = PasswordHelper::assignInitialPasswordIfMissing($newUserId); 
        } catch (Throwable $e) { 
          error_log('Password generation error: '.$e->getMessage());
          $initialPassword = null; 
        }
      }
            $created = true;
      Helpers::flash('msg','Employer account created'.($auto_approve?' & approved':'').' successfully.');
      if ($initialPassword) {
        Helpers::flash('msg','Initial password (copy now): <code>'.htmlspecialchars($initialPassword).'</code>');
      } else {
        Helpers::flash('error','Password generation failed (no password assigned).');
      }
            // Optionally send email (reuse Mail if available)
      if ($newUserId && class_exists('Mail') && $initialPassword) {
        try {
          $bodyLines = [];
          $bodyLines[] = 'Your employer account has been created by an administrator.';
          if ($auto_approve) $bodyLines[] = 'Status: Approved';
          if ($initialPassword) $bodyLines[] = 'Initial Password: '.htmlspecialchars($initialPassword);
          $htmlBody = '<p>'.implode('</p><p>',$bodyLines).'</p>';
          Mail::send($email, $company_name ?: $displayName, 'Employer Account Created', $htmlBody);
        } catch (Throwable $e) { /* ignore send failure */ }
      }
        } else {
            $errors[] = 'Creation failed. Email may already exist.';
        }
    }
}

include 'includes/header.php';
?>
<div class="admin-layout">
  <?php include 'includes/admin_sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-page-header mb-3">
      <div class="page-title-block">
        <h1 class="page-title"><i class="bi bi-building-add"></i><span>Create Employer</span></h1>
        <p class="page-sub">Add a new employer organization account.</p>
      </div>
      <div class="page-actions">
        <a href="admin_employers.php" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left"></i> Back to list</a>
      </div>
    </div>
    <style>
      /* Reusable admin page header (Create Employer) */
      .admin-page-header{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:1.25rem;padding:0 .25rem .35rem;border-bottom:1px solid rgba(255,255,255,.07);} 
      .admin-page-header .page-title{margin:0;font-size:1.35rem;font-weight:600;display:flex;align-items:center;gap:.65rem;color:#f0f6ff;letter-spacing:.5px;} 
      .admin-page-header .page-title i{font-size:1.55rem;line-height:1;color:#6cb2ff;filter:drop-shadow(0 2px 4px rgba(0,0,0,.4));} 
      .admin-page-header .page-sub{margin:.15rem 0 0;font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;font-weight:600;color:#6e829b;} 
      .admin-page-header .page-actions{display:flex;align-items:center;gap:.6rem;margin-left:auto;} 
      .admin-page-header .btn-outline-light{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.25);color:#dbe6f5;font-weight:600;font-size:.7rem;letter-spacing:.4px;padding:.5rem .85rem;border-radius:8px;} 
      .admin-page-header .btn-outline-light:hover{background:rgba(255,255,255,.15);color:#fff;} 
      @media (max-width:640px){.admin-page-header{align-items:flex-start}.admin-page-header .page-actions{width:100%;justify-content:flex-start}}
    </style>

    <?php $___fl = Helpers::getFlashes(); foreach($___fl as $k=>$msg): $type = ($k==='error'||$k==='danger')?'danger':(($k==='success')?'success':'info'); ?>
      <div class="alert alert-<?php echo $type; ?> py-2 small mb-2">
        <?php if($type==='success'): ?><i class="bi bi-check-circle me-1"></i><?php elseif($type==='danger'): ?><i class="bi bi-exclamation-triangle me-1"></i><?php else: ?><i class="bi bi-info-circle me-1"></i><?php endif; ?>
        <?php echo htmlspecialchars($msg); ?>
      </div>
    <?php endforeach; ?>

    <?php foreach($errors as $e): ?>
      <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i><?php echo htmlspecialchars($e); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endforeach; ?>

    <?php if ($created && $newUserId): ?>
      <div class="card mb-4 shadow-sm">
        <div class="card-body small">
          <h6 class="fw-semibold mb-2"><i class="bi bi-check-circle text-success me-1"></i>Employer Created</h6>
          <p class="mb-1">User ID: <code><?php echo htmlspecialchars($newUserId); ?></code></p>
          <?php if ($initialPassword): ?><p class="mb-1">Initial Password: <code><?php echo htmlspecialchars($initialPassword); ?></code></p><?php endif; ?>
          <a href="admin_employer_view.php?user_id=<?php echo urlencode($newUserId); ?>" class="btn btn-sm btn-primary mt-2"><i class="bi bi-eye"></i> View Profile</a>
        </div>
      </div>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-body">
        <form method="post" class="row g-3" novalidate>
          <div class="col-md-6">
            <label class="form-label">Contact Person Name</label>
            <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Primary Login Email</label>
            <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Contact Person Position (optional)</label>
            <input type="text" name="contact_person_position" class="form-control" value="<?php echo htmlspecialchars($_POST['contact_person_position'] ?? ''); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Contact Person Phone (optional)</label>
            <input type="text" name="contact_person_phone" class="form-control" value="<?php echo htmlspecialchars($_POST['contact_person_phone'] ?? ''); ?>" placeholder="e.g. +63 912 345 6789">
          </div>
          <div class="col-md-6">
            <label class="form-label">Company Name</label>
            <input type="text" name="company_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Business Permit #</label>
            <input type="text" name="business_permit_number" class="form-control" required pattern="[A-Za-z0-9\-/]{4,40}" value="<?php echo htmlspecialchars($_POST['business_permit_number'] ?? ''); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Company Owner / Proprietor Name</label>
            <input type="text" name="company_owner_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['company_owner_name'] ?? ''); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Display Name (Account)</label>
            <input type="text" id="adminDisplayNameMirror" class="form-control" value="<?php echo htmlspecialchars($_POST['company_owner_name'] ?? ''); ?>" disabled>
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
          <div class="col-12">
            <div class="form-check form-switch mb-1">
              <input class="form-check-input" type="checkbox" id="autoApprove" name="auto_approve" <?php if(!empty($_POST['auto_approve'])) echo 'checked'; ?>>
              <label for="autoApprove" class="form-check-label">Approve immediately (set status to Approved)</label>
            </div>
            <div class="small text-muted">Initial password will ALWAYS be generated & emailed automatically.</div>
            <?php if (!Mail::isEnabled()): ?>
              <div class="alert alert-warning py-2 small mt-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Email delivery disabled (SMTP_ENABLE=false). Copy password manually and send to employer.</div>
            <?php endif; ?>
          </div>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Employer</button>
            <a href="admin_employers.php" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>

  <div class="security-note mt-4">
    <div class="icon-wrap"><i class="bi bi-shield-lock"></i></div>
    <div class="note-text">
      <strong>Security:</strong> Passwords are never stored in plaintext. The generated initial password is shown only once on creation. Copy it immediately if email delivery is disabled or fails.
    </div>
  </div>
  <style>
    .security-note{position:relative;display:flex;align-items:flex-start;gap:.9rem;background:linear-gradient(135deg,#101d2e,#0d1624);border:1px solid #1f3147;padding:.9rem 1.05rem;border-radius:14px;font-size:.72rem;line-height:1.35;color:#c9d7e6;box-shadow:0 4px 18px -10px rgba(0,0,0,.55);} 
    .security-note .icon-wrap{background:rgba(108,178,255,.1);width:38px;height:38px;display:flex;align-items:center;justify-content:center;border-radius:11px;border:1px solid rgba(108,178,255,.35);flex-shrink:0;} 
    .security-note .icon-wrap i{font-size:1.15rem;color:#6cb2ff;filter:drop-shadow(0 2px 6px rgba(108,178,255,.4));} 
    .security-note strong{color:#fff;font-weight:600;letter-spacing:.4px;} 
    @media (max-width:600px){.security-note{flex-direction:row;font-size:.7rem;padding:.85rem .95rem}.security-note .icon-wrap{width:34px;height:34px}} 
  </style>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const ownerInput = document.querySelector('input[name="company_owner_name"]');
  const dn = document.getElementById('adminDisplayNameMirror');
  if (ownerInput && dn) {
    ownerInput.addEventListener('input', ()=> { dn.value = ownerInput.value; });
  }
});
</script>
