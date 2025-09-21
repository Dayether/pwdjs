<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';
require_once '../classes/Sensitive.php';

Helpers::requireLogin();
if (($_SESSION['role'] ?? '') !== 'admin') {
    Helpers::redirect('index.php');
}

$userId = $_GET['user_id'] ?? null;
if (!$userId) {
    Helpers::redirect('admin_job_seekers.php');
}

// Handle status changes via POST for CSRF-like basic check
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if (in_array($action,['Verified','Rejected','Pending'],true)) {
        if ($action==='Pending') {
            $pdo = Database::getConnection();
            $res = $pdo->prepare("UPDATE users SET pwd_id_status='Pending' WHERE user_id=? AND role='job_seeker'")->execute([$userId]);
        } else {
            $res = User::setPwdIdStatus($userId,$action);
        }
        if ($res) Helpers::flash('msg','Status updated to '.$action.'.'); else Helpers::flash('error','Failed updating status.');
        Helpers::redirect('admin_job_seeker_view.php?user_id='.$userId);
    }
}

$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=? AND role='job_seeker' LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    Helpers::flash('error','Job seeker not found.');
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
include '../includes/nav.php';
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <a href="admin_job_seekers.php" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
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
          $st = $user['pwd_id_status'] ?: 'None';
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
          <dt class="col-sm-4">Name</dt><dd class="col-sm-8"><?php echo Helpers::sanitizeOutput($user['name']); ?></dd>
          <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?php echo Helpers::sanitizeOutput($user['email']); ?></dd>
          <dt class="col-sm-4">PWD ID (full)</dt><dd class="col-sm-8"><?php echo $decryptedPwdId ? Helpers::sanitizeOutput($decryptedPwdId) : '—'; ?></dd>
          <dt class="col-sm-4">PWD ID Last4</dt><dd class="col-sm-8"><?php echo $user['pwd_id_last4'] ? '****'.$user['pwd_id_last4'] : '—'; ?></dd>
          <dt class="col-sm-4">Disability Type</dt><dd class="col-sm-8"><?php echo Helpers::sanitizeOutput($user['disability_type'] ?? ''); ?></dd>
          <dt class="col-sm-4">Education</dt><dd class="col-sm-8"><?php echo Helpers::sanitizeOutput($user['education_level'] ?? ''); ?></dd>
          <dt class="col-sm-4">Created</dt><dd class="col-sm-8"><?php echo htmlspecialchars($user['created_at']); ?></dd>
          <dt class="col-sm-4">Updated</dt><dd class="col-sm-8"><?php echo htmlspecialchars($user['updated_at']); ?></dd>
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
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">Security Notes</div>
      <div class="card-body small text-muted">
        <p class="mb-1">Full PWD ID is decrypted only for admins on this page. Store or copy only when necessary.</p>
        <p class="mb-0">Changing to Verified allows this user to log in. Rejected prevents login until set Pending and then Verified again.</p>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
