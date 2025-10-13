<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';
require_once 'classes/User.php';
require_once 'classes/Sensitive.php';
require_once 'classes/Mail.php';
require_once 'classes/Password.php';

Helpers::requireRole('admin');

// Establish selected user context via POST -> session; avoid exposing in URL
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['user_id'])) {
  $_SESSION['__admin_view_user_js'] = (string)$_POST['user_id'];
}
$userId = $_SESSION['__admin_view_user_js'] ?? null;
if (!$userId) { Helpers::redirect('admin_job_seekers.php'); }

// Handle status changes via POST for CSRF-like basic check
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
  $action = $_POST['action'];
  $reason = trim((string)($_POST['reason'] ?? ''));
  $pdoC = Database::getConnection();
  try {
    $rowC = $pdoC->prepare("SELECT name,email,pwd_id_status,job_seeker_status FROM users WHERE user_id=? AND role='job_seeker' LIMIT 1");
    $rowC->execute([$userId]);
    $before = $rowC->fetch(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    $rowC = $pdoC->prepare("SELECT name,email,pwd_id_status FROM users WHERE user_id=? AND role='job_seeker' LIMIT 1");
    $rowC->execute([$userId]);
    $before = $rowC->fetch(PDO::FETCH_ASSOC) ?: [];
  }
  $prevPwd = $before['pwd_id_status'] ?? null;
  $prevAcct = $before['job_seeker_status'] ?? 'Active';

  $ok = false; $msg = ''; $emailInfo = '';
  $pwdFinal = null; $acctFinal = null;
  if (in_array($action,['Verified','Rejected','Pending'],true)) {
    if ($action==='Pending') {
      $ok = $pdoC->prepare("UPDATE users SET pwd_id_status='Pending' WHERE user_id=? AND role='job_seeker'")->execute([$userId]);
      $pwdFinal = $ok ? 'Pending' : null;
    } else {
      $ok = User::setPwdIdStatus($userId,$action);
      $pwdFinal = $ok ? $action : null;
    }
    $msg = $ok ? ('PWD ID status updated to '.$action.'.') : 'Failed updating status.';
    // email for PWD change
    if ($ok && $pwdFinal !== null && $pwdFinal !== $prevPwd) {
      if (Mail::isEnabled()) {
        $subject = match($pwdFinal) {
          'Verified' => 'Your PWD ID Has Been Verified',
          'Rejected' => 'Your PWD ID Verification Was Rejected',
          'Pending'  => 'Your PWD ID Status Set Back to Pending',
          default    => 'Your PWD ID Status Updated'
        };
        $body = '<p>Hello '.htmlspecialchars($before['name']).',</p>';
        $issuedPass = null;
        if ($pwdFinal==='Verified') {
          $body.='<p>Your PWD ID has been <strong>verified</strong>.</p>';
          try {
            $issuedPass = PasswordHelper::assignInitialPasswordIfMissing($userId);
          } catch (Throwable $e) { $issuedPass = null; }
          if ($issuedPass) {
            $body .= '<p>Your initial password is: <strong>'.htmlspecialchars($issuedPass).'</strong></p>';
            $body .= '<p>You can now log in here: <a href="'.BASE_URL.'/login">'.BASE_URL.'/login</a></p>';
          }
        }
        elseif ($pwdFinal==='Rejected') $body.='<p>Your PWD ID verification was <strong>rejected</strong>.</p>';
        else $body.='<p>Your PWD ID status is now <strong>'.htmlspecialchars($pwdFinal).'</strong>.</p>';
        if ($reason !== '') {
          $body .= '<p><strong>Reason:</strong><br>'.nl2br(htmlspecialchars($reason)).'</p>';
        }
        $body.='<p>Regards,<br>The Admin Team</p>';
        if (Mail::isEnabled()) {
          $sr = Mail::send($before['email'],$before['name'],$subject,$body);
          $emailInfo = $sr['success'] ? ' Email sent.' : (($sr['error']==='SMTP disabled') ? ' (Email not sent: SMTP disabled.)' : ' (Email failed: '.htmlspecialchars($sr['error']).')');
        } else {
          if ($issuedPass) {
            $emailInfo = ' (SMTP disabled; initial password generated: '.htmlspecialchars($issuedPass).')';
          } else {
            $emailInfo = ' (Email not sent: SMTP disabled.)';
          }
        }
      } else {
        $emailInfo = ' (Email not sent: SMTP disabled.)';
      }
      // Persist reason + log
      User::persistStatusReason($userId, $reason, false);
      User::logStatusChange($_SESSION['user_id'], $userId, 'job_seeker', 'pwd_id_status', $pwdFinal, $reason);
    } elseif ($ok && $pwdFinal === $prevPwd) {
      $msg = 'PWD ID status already '.$prevPwd.'.';
    }
  } elseif (in_array($action,['Suspend','Activate'],true)) {
    if ($action==='Suspend') { $ok = User::updateJobSeekerStatus($userId,'Suspended'); $acctFinal = $ok ? 'Suspended' : null; }
    else { $ok = User::updateJobSeekerStatus($userId,'Active'); $acctFinal = $ok ? 'Active' : null; }
    $msg = $ok ? ('Account '.$action.'d.') : ('Failed to '.$action.' account.');
    if ($ok && $acctFinal !== null && $acctFinal !== $prevAcct) {
      if (Mail::isEnabled()) {
        $subject = $acctFinal==='Suspended' ? 'Your Account Has Been Suspended' : 'Your Account Has Been Re-Activated';
        $body = '<p>Hello '.htmlspecialchars($before['name']).',</p>';
        if ($acctFinal==='Suspended') $body.='<p>Your account has been <strong>suspended</strong>. You cannot use the portal until it is re-activated.</p>';
        else $body.='<p>Your account has been <strong>re-activated</strong>. You may now log in and use the portal.</p>';
        if ($reason !== '') { $body.='<p><strong>Reason:</strong><br>'.nl2br(htmlspecialchars($reason)).'</p>'; }
        $body.='<p>Regards,<br>The Admin Team</p>';
        $sr2 = Mail::send($before['email'],$before['name'],$subject,$body);
        $emailInfo = $sr2['success'] ? ' Email sent.' : (($sr2['error']==='SMTP disabled') ? ' (Email not sent: SMTP disabled.)' : ' (Email failed: '.htmlspecialchars($sr2['error']).')');
      } else {
        $emailInfo = ' (Email not sent: SMTP disabled.)';
      }
      // Persist reason + log (mark suspension specifically)
      User::persistStatusReason($userId, $reason, $acctFinal==='Suspended');
      User::logStatusChange($_SESSION['user_id'], $userId, 'job_seeker', 'job_seeker_status', $acctFinal, $reason);
    } elseif ($ok && $acctFinal === $prevAcct) {
      $msg = 'Account is already '.$prevAcct.'.';
    }
  }
  if ($ok) Helpers::flash('msg',$msg.$emailInfo); else Helpers::flash('error',$msg ?: 'Action failed.');
  Helpers::redirect('admin_job_seeker_view');
}

$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=? AND role='job_seeker' LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
  Helpers::flash('error','Job seeker not found.');
  unset($_SESSION['__admin_view_user_js']);
  Helpers::redirect('admin_job_seekers.php');
}

$decryptedPwdId = null;
// New schema uses single encrypted field pwd_id_number (base64) + last4; older code referenced pwd_id_enc + iv.
if (!empty($user['pwd_id_number'])) {
  try {
    $decryptedPwdId = Sensitive::decrypt($user['pwd_id_number']);
  } catch (Throwable $e) {
    $decryptedPwdId = null;
  }
  // If decrypt failed (null) BUT stored value looks like raw/plain (was never encrypted), allow showing raw.
  if ($decryptedPwdId === null) {
    $rawCandidate = $user['pwd_id_number'];
    if (preg_match('/^[A-Za-z0-9\-]{4,40}$/', (string)$rawCandidate)) {
      $decryptedPwdId = (string)$rawCandidate; // treat as plaintext
    }
  }
}

include 'includes/header.php';
?>
<?php $backUrl = Helpers::getLastPage('admin_job_seekers.php'); ?>
<div class="admin-layout">
  <?php include 'includes/admin_sidebar.php'; ?>
  <div class="admin-main">
<style>
  /* Job seeker verification enhanced heading styling */
  .js-verif-topbar{display:flex;flex-direction:column;gap:.9rem;margin-bottom:1.15rem;animation:fadeIn .45s ease both}
  .js-verif-topbar .bar{display:flex;align-items:center;flex-wrap:wrap;gap:.75rem;border-bottom:1px solid rgba(255,255,255,.07);padding-bottom:.55rem}
  @media (prefers-color-scheme: light){.js-verif-topbar .bar{border-color:rgba(0,0,0,.08);} }
  .js-verif-topbar h2{margin:0;font-size:1.28rem;font-weight:700;letter-spacing:.55px;display:flex;align-items:center;gap:.55rem;line-height:1.05;background:linear-gradient(90deg,#ffffff,#93c5fd);-webkit-background-clip:text;background-clip:text;color:transparent;position:relative}
  .js-verif-topbar h2 i{background:linear-gradient(135deg,#60a5fa,#818cf8);-webkit-background-clip:text;background-clip:text;color:transparent;filter:drop-shadow(0 2px 4px rgba(0,0,0,.35));}
  @media (prefers-color-scheme: light){.js-verif-topbar h2{background:linear-gradient(90deg,#1e293b,#2563eb);} .js-verif-topbar h2 i{background:linear-gradient(135deg,#2563eb,#4f46e5);} }
  .js-verif-topbar a.back-btn{--clr-border:rgba(255,255,255,.15);--clr-fg:#c4d2e4;display:inline-flex;align-items:center;justify-content:center;gap:.4rem;height:34px;padding:.45rem .85rem;font-size:.7rem;font-weight:600;letter-spacing:.05em;border:1px solid var(--clr-border);background:#182637;color:var(--clr-fg);border-radius:8px;text-decoration:none;transition:.25s}
  .js-verif-topbar a.back-btn:hover{background:#22364d;color:#fff;border-color:#3b5f89}
  @media (prefers-color-scheme: light){.js-verif-topbar a.back-btn{background:#f1f5f9;color:#334155;border-color:#cbd5e1;} .js-verif-topbar a.back-btn:hover{background:#e2e8f0;border-color:#94a3b8;color:#1e293b;} }
  @keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
</style>

<div class="js-verif-topbar">
  <div class="bar">
    <a href="<?php echo htmlspecialchars($backUrl); ?>" class="back-btn" onclick="if(document.referrer){history.back(); return false;}"><i class="bi bi-arrow-left"></i><span>Back</span></a>
    <h2><i class="bi bi-person-badge-check"></i> Job Seeker Verification</h2>
  </div>
</div>

<?php if (!empty($_SESSION['flash']['msg'])): ?>
  <div class="alert alert-success alert-dismissible fade show auto-dismiss">
    <?php echo htmlspecialchars($_SESSION['flash']['msg']); unset($_SESSION['flash']['msg']); ?>
    <button class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash']['error'])): ?>
  <div class="alert alert-danger alert-dismissible fade show auto-dismiss">
    <?php echo htmlspecialchars($_SESSION['flash']['error']); unset($_SESSION['flash']['error']); ?>
    <button class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Profile Information</span>
        <?php
          $st = ($user['pwd_id_status'] ?? 'None') ?: 'None';
          $badgeClass = match($st){
            'Verified' => 'text-bg-success',
            'Pending'  => 'text-bg-warning',
            'Rejected' => 'text-bg-danger',
            default    => 'text-bg-secondary'
          };
        ?>
        <span class="badge <?php echo $badgeClass; ?>">Status: <?php echo htmlspecialchars($st); ?></span>
      </div>
      <div class="card-body small">
        <dl class="row mb-0">
          <dt class="col-sm-4">Name</dt><dd class="col-sm-8"><?php $v = trim((string)($user['name'] ?? '')); echo $v!=='' ? Helpers::sanitizeOutput($v) : 'Not available'; ?></dd>
          <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?php $v = trim((string)($user['email'] ?? '')); echo $v!=='' ? Helpers::sanitizeOutput($v) : 'Not available'; ?></dd>
          <dt class="col-sm-4">PWD ID (Full)</dt><dd class="col-sm-8">
            <?php
              $last4 = trim((string)($user['pwd_id_last4'] ?? ''));
              if ($decryptedPwdId !== null) {
                echo Helpers::sanitizeOutput($decryptedPwdId);
              } else {
                echo $last4 !== '' ? 'Partial (****'.Helpers::sanitizeOutput($last4).')' : 'Not available';
              }
            ?>
          </dd>
          <dt class="col-sm-4">PWD ID (Last 4)</dt><dd class="col-sm-8"><?php echo $last4 !== '' ? Helpers::sanitizeOutput($last4) : 'Not available'; ?></dd>
          <dt class="col-sm-4">Disability Type</dt><dd class="col-sm-8"><?php $v = trim((string)($user['disability_type'] ?? '')); echo $v!=='' ? Helpers::sanitizeOutput($v) : 'Not available'; ?></dd>
          <dt class="col-sm-4">Education</dt><dd class="col-sm-8"><?php $v = trim((string)($user['education_level'] ?? '')); echo $v!=='' ? Helpers::sanitizeOutput($v) : 'Not available'; ?></dd>
          <dt class="col-sm-4">Created</dt><dd class="col-sm-8"><?php $v = trim((string)($user['created_at'] ?? '')); echo $v!=='' ? htmlspecialchars($v) : 'Not available'; ?></dd>
          <dt class="col-sm-4">Updated</dt><dd class="col-sm-8"><?php $v = trim((string)($user['updated_at'] ?? '')); echo $v!=='' ? htmlspecialchars($v) : 'Not available'; ?></dd>
          <?php
            // Show reason columns if present
            $reasonCols = [];
            try {
              $cols = Database::getConnection()->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
              foreach ($cols as $c) { $reasonCols[$c['Field']] = true; }
            } catch (Throwable $e) {}
            if (!empty($reasonCols['last_status_reason'])): $rs = trim((string)($user['last_status_reason']??'')); ?>
              <dt class="col-sm-4">Last Status Reason</dt><dd class="col-sm-8"><?php echo $rs!==''? nl2br(Helpers::sanitizeOutput($rs)) : '—'; ?></dd>
            <?php endif; if (!empty($reasonCols['last_suspension_reason']) && ($user['job_seeker_status']??'')==='Suspended'): $sr = trim((string)($user['last_suspension_reason']??'')); ?>
              <dt class="col-sm-4">Suspension Reason</dt><dd class="col-sm-8"><?php echo $sr!==''? nl2br(Helpers::sanitizeOutput($sr)) : '—'; ?></dd>
            <?php endif; ?>
        </dl>
      </div>
    </div>

    <div class="card border-0 shadow-sm mt-4">
      <div class="card-header bg-white fw-semibold">Preferences (Mini Resume)</div>
      <div class="card-body small">
        <?php
          $salParts=[];
          if (!empty($user['expected_salary_min'])) $salParts[] = number_format((int)$user['expected_salary_min']);
          if (!empty($user['expected_salary_max'])) $salParts[] = number_format((int)$user['expected_salary_max']);
          $salRange = implode(' - ', $salParts);
          $salPeriod = !empty($user['expected_salary_period']) ? ucfirst($user['expected_salary_period']) : '';
          $salCur = $user['expected_salary_currency'] ?? '';
          $salaryDisp = $salRange ? trim($salCur.' '.$salRange.' '.$salPeriod) : '';
        ?>
        <dl class="row mb-0">
          <dt class="col-sm-4">Work Setup</dt><dd class="col-sm-8"><?php echo $user['preferred_work_setup'] ? Helpers::sanitizeOutput($user['preferred_work_setup']) : '—'; ?></dd>
          <dt class="col-sm-4">Preferred Location</dt><dd class="col-sm-8"><?php echo $user['preferred_location'] ? Helpers::sanitizeOutput($user['preferred_location']) : '—'; ?></dd>
          <dt class="col-sm-4">Expected Salary</dt><dd class="col-sm-8"><?php echo $salaryDisp ? Helpers::sanitizeOutput($salaryDisp) : '—'; ?></dd>
          <dt class="col-sm-4">Interests</dt><dd class="col-sm-8"><?php echo $user['interests'] ? nl2br(Helpers::sanitizeOutput($user['interests'])) : '—'; ?></dd>
          <dt class="col-sm-4">Accessibility</dt><dd class="col-sm-8"><?php echo $user['accessibility_preferences'] ? Helpers::sanitizeOutput($user['accessibility_preferences']) : '—'; ?></dd>
        </dl>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold">Verification Actions</div>
      <div class="card-body">
        <div class="d-grid gap-2">
          <button data-reason-action="Verified" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Verify</button>
          <button data-reason-action="Pending" class="btn btn-warning"><i class="bi bi-arrow-counterclockwise me-1"></i>Set Pending</button>
          <button data-reason-action="Rejected" class="btn btn-danger"><i class="bi bi-x-circle me-1"></i>Reject</button>
        </div>
      </div>
    </div>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold">Account Controls</div>
      <div class="card-body">
        <?php $acct = $user['job_seeker_status'] ?? 'Active'; ?>
        <div class="d-grid gap-2">
          <?php if ($acct==='Suspended'): ?>
            <button data-reason-action="Activate" class="btn btn-primary"><i class="bi bi-person-check me-1"></i>Activate Account</button>
          <?php else: ?>
            <button data-reason-action="Suspend" class="btn btn-outline-secondary"><i class="bi bi-person-dash me-1"></i>Suspend Account</button>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">Security Notes</div>
      <div class="card-body small text-muted">
        <p class="mb-1">Full PWD ID is decrypted only for admins on this page. Store or copy only when necessary.</p>
        <p class="mb-0">Changing to Verified allows this user to log in. Rejected prevents login until set Pending and then Verified again.</p>
      </div>
    </div>
  </div>
</div>
  </div>

<!-- Reason Modal -->
<div class="modal fade" id="reasonModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" id="reasonForm">
        <div class="modal-header py-2">
          <h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i><span id="reasonModalTitle">Provide Reason</span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" id="reasonAction" value="">
          <input type="hidden" name="csrf" value="<?php echo session_id(); ?>">
          <div class="mb-3">
            <label for="reasonText" class="form-label">Reason (required)</label>
            <textarea class="form-control" id="reasonText" name="reason" rows="4" required placeholder="Ilahad nang malinaw ang dahilan ng aksyon..."></textarea>
            <div class="form-text">This reason will be saved and included in the email notification.</div>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const modalEl = document.getElementById('reasonModal');
  const form = document.getElementById('reasonForm');
  const actionInput = document.getElementById('reasonAction');
  const titleSpan = document.getElementById('reasonModalTitle');
  const txt = document.getElementById('reasonText');
  function openReason(action){
    actionInput.value = action;
    titleSpan.textContent = 'Provide Reason for ' + action;
    txt.value='';
    if (window.bootstrap){ window.bootstrap.Modal.getOrCreateInstance(modalEl).show(); }
    else { modalEl.style.display='block'; }
  }
  document.querySelectorAll('[data-reason-action]').forEach(btn=>{
    btn.addEventListener('click', e=>{ e.preventDefault(); openReason(btn.getAttribute('data-reason-action')); });
  });
})();
</script>

<?php include 'includes/footer.php'; ?>
