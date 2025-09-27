<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';
require_once '../classes/Sensitive.php';
require_once '../classes/Mail.php';
require_once '../classes/Password.php';

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
        $body.='<p>Regards,<br>The Admin Team</p>';
        $sr2 = Mail::send($before['email'],$before['name'],$subject,$body);
        $emailInfo = $sr2['success'] ? ' Email sent.' : (($sr2['error']==='SMTP disabled') ? ' (Email not sent: SMTP disabled.)' : ' (Email failed: '.htmlspecialchars($sr2['error']).')');
      } else {
        $emailInfo = ' (Email not sent: SMTP disabled.)';
      }
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
if (!empty($user['pwd_id_enc'])) {
    try {
        $decryptedPwdId = Sensitive::decrypt($user['pwd_id_enc'], $user['pwd_id_iv']);
    } catch (Exception $e) {
        $decryptedPwdId = null;
    }
}

include '../includes/header.php';
?>
<?php $backUrl = Helpers::getLastPage('admin_job_seekers.php'); ?>
<div class="admin-layout">
  <?php include '../includes/admin_sidebar.php'; ?>
  <div class="admin-main">
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-sm btn-outline-secondary me-2" onclick="if(document.referrer){history.back(); return false;}"><i class="bi bi-arrow-left"></i></a>
    <span class="h5 fw-semibold align-middle">Job Seeker Verification</span>
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
          <dt class="col-sm-4">PWD ID (full)</dt><dd class="col-sm-8"><?php echo $decryptedPwdId ? Helpers::sanitizeOutput($decryptedPwdId) : 'Not available'; ?></dd>
          <dt class="col-sm-4">PWD ID Last4</dt><dd class="col-sm-8"><?php $v = trim((string)($user['pwd_id_last4'] ?? '')); echo $v!=='' ? '****'.Helpers::sanitizeOutput($v) : 'Not available'; ?></dd>
          <dt class="col-sm-4">Disability Type</dt><dd class="col-sm-8"><?php $v = trim((string)($user['disability_type'] ?? '')); echo $v!=='' ? Helpers::sanitizeOutput($v) : 'Not available'; ?></dd>
          <dt class="col-sm-4">Education</dt><dd class="col-sm-8"><?php $v = trim((string)($user['education_level'] ?? '')); echo $v!=='' ? Helpers::sanitizeOutput($v) : 'Not available'; ?></dd>
          <dt class="col-sm-4">Created</dt><dd class="col-sm-8"><?php $v = trim((string)($user['created_at'] ?? '')); echo $v!=='' ? htmlspecialchars($v) : 'Not available'; ?></dd>
          <dt class="col-sm-4">Updated</dt><dd class="col-sm-8"><?php $v = trim((string)($user['updated_at'] ?? '')); echo $v!=='' ? htmlspecialchars($v) : 'Not available'; ?></dd>
        </dl>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold">Verification Actions</div>
      <div class="card-body">
        <form method="post" class="d-grid gap-2">
          <input type="hidden" name="csrf" value="<?php echo session_id(); ?>">
          <button name="action" value="Verified" class="btn btn-success" onclick="return confirm('Mark as Verified?');"><i class="bi bi-check2-circle me-1"></i>Verify</button>
          <button name="action" value="Pending" class="btn btn-warning" onclick="return confirm('Set back to Pending?');"><i class="bi bi-arrow-counterclockwise me-1"></i>Set Pending</button>
          <button name="action" value="Rejected" class="btn btn-danger" onclick="return confirm('Reject this PWD ID?');"><i class="bi bi-x-circle me-1"></i>Reject</button>
        </form>
      </div>
    </div>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold">Account Controls</div>
      <div class="card-body">
        <?php $acct = $user['job_seeker_status'] ?? 'Active'; ?>
        <form method="post" class="d-grid gap-2">
          <input type="hidden" name="csrf" value="<?php echo session_id(); ?>">
          <?php if ($acct==='Suspended'): ?>
            <button name="action" value="Activate" class="btn btn-primary" onclick="return confirm('Re-activate this account?');"><i class="bi bi-person-check me-1"></i>Activate Account</button>
          <?php else: ?>
            <button name="action" value="Suspend" class="btn btn-outline-secondary" onclick="return confirm('Suspend this account?');"><i class="bi bi-person-dash me-1"></i>Suspend Account</button>
          <?php endif; ?>
        </form>
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

<?php include '../includes/footer.php'; ?>
