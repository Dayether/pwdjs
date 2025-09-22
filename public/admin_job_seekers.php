<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';
require_once '../classes/Sensitive.php';
require_once '../classes/Mail.php';

Helpers::requireLogin();
if (($_SESSION['role'] ?? '') !== 'admin') {
    Helpers::redirect('index.php');
}

// Filters
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = trim($_GET['q'] ?? '');

// Actions (verify / reject / pending)
if (isset($_GET['action'], $_GET['user_id'])) {
  $action = $_GET['action'];
  $uid = $_GET['user_id'];
  // get current before change
  $pdoChk = Database::getConnection();
  $stChk = $pdoChk->prepare("SELECT pwd_id_status,name,email FROM users WHERE user_id=? AND role='job_seeker' LIMIT 1");
  $stChk->execute([$uid]);
  $before = $stChk->fetch(PDO::FETCH_ASSOC) ?: null;
  $prevStatus = $before['pwd_id_status'] ?? null;

  $finalStatus = null; // track new status if updated
  $baseMsg = null;
  $ok = false;
  if ($action === 'verify') {
    $ok = User::setPwdIdStatus($uid,'Verified');
    $finalStatus = $ok ? 'Verified' : null;
    $baseMsg = $ok ? 'PWD ID verified.' : 'Failed to verify.';
  } elseif ($action === 'reject') {
    $ok = User::setPwdIdStatus($uid,'Rejected');
    $finalStatus = $ok ? 'Rejected' : null;
    $baseMsg = $ok ? 'PWD ID rejected.' : 'Failed to reject.';
  } elseif ($action === 'pending') {
    $st = $pdoChk->prepare("UPDATE users SET pwd_id_status='Pending' WHERE user_id=? AND role='job_seeker'");
    $ok = $st->execute([$uid]);
    $finalStatus = $ok ? 'Pending' : null;
    $baseMsg = $ok ? 'Set back to Pending.' : 'Failed to update.';
  }

  $emailInfo = '';
  if ($ok && $finalStatus !== null && $prevStatus !== $finalStatus) {
    if ($before && Mail::isEnabled()) {
      $toEmail = $before['email'];
      $toName  = $before['name'];
      $subject = match($finalStatus) {
        'Verified' => 'Your PWD ID Has Been Verified',
        'Rejected' => 'Your PWD ID Verification Was Rejected',
        'Pending'  => 'Your PWD ID Status Set Back to Pending',
        default    => 'Your PWD ID Status Updated'
      };
      $body  = '<p>Hello '.htmlspecialchars($toName).',</p>';
      if ($finalStatus === 'Verified') {
        $body .= '<p>Your PWD ID has been <strong>verified</strong>. You now have full access to job application features requiring verified status.</p>';
      } elseif ($finalStatus === 'Rejected') {
        $body .= '<p>Your PWD ID verification was <strong>rejected</strong>. You may re-upload or contact support for clarification.</p>';
      } elseif ($finalStatus === 'Pending') {
        $body .= '<p>Your PWD ID status has been set back to <strong>Pending</strong>. We will notify you once it is reviewed again.</p>';
      } else {
        $body .= '<p>Your PWD ID status has been updated to <strong>'.htmlspecialchars($finalStatus).'</strong>.</p>';
      }
      $body .= '<p>Regards,<br>The Admin Team</p>';
      $sendRes = Mail::send($toEmail, $toName, $subject, $body);
      if ($sendRes['success']) {
        $emailInfo = ' Email notification sent.';
      } else {
        if ($sendRes['error'] === 'SMTP disabled') {
          $emailInfo = ' (Email not sent: SMTP disabled.)';
        } else {
          $emailInfo = ' (Email failed: '.htmlspecialchars($sendRes['error']).')';
        }
      }
    } elseif ($before && !Mail::isEnabled()) {
      $emailInfo = ' (Email not sent: SMTP disabled.)';
    }
  }

  if ($baseMsg !== null) {
    if ($ok) {
      Helpers::flash('msg', $baseMsg.$emailInfo);
    } else {
      Helpers::flash('error', $baseMsg);
    }
  }
  Helpers::redirect('admin_job_seekers.php');
}

$pdo = Database::getConnection();
$where = "role='job_seeker'";
$params = [];
if ($statusFilter !== '') {
    $where .= " AND pwd_id_status=?";
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql = "SELECT user_id,name,email,pwd_id_last4,pwd_id_status,created_at FROM users WHERE $where ORDER BY (pwd_id_status='Pending') DESC, created_at DESC LIMIT 300";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$counts = User::jobSeekerCounts();

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h2 class="h5 fw-semibold mb-0"><i class="bi bi-people me-2"></i>Admin · Job Seekers Verification</h2>
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

<div class="row g-3 mb-3">
  <div class="col-md-8">
    <form class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach(['Pending','Verified','Rejected','None'] as $st): ?>
            <option value="<?php echo $st; ?>" <?php if($statusFilter===$st) echo 'selected'; ?>><?php echo $st; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label small mb-1">Search</label>
        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="form-control form-control-sm" placeholder="Name or email">
      </div>
      <div class="col-md-4 d-grid">
        <button class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
      </div>
    </form>
  </div>
  <div class="col-md-4">
    <div class="border rounded p-2 small bg-body-tertiary h-100">
      <div><span class="badge text-bg-primary">Total</span> <?php echo (int)$counts['total']; ?></div>
      <div><span class="badge text-bg-secondary">None</span> <?php echo (int)$counts['None']; ?></div>
      <div><span class="badge text-bg-warning">Pending</span> <?php echo (int)$counts['Pending']; ?></div>
      <div><span class="badge text-bg-success">Verified</span> <?php echo (int)$counts['Verified']; ?></div>
      <div><span class="badge text-bg-danger">Rejected</span> <?php echo (int)$counts['Rejected']; ?></div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr class="small text-uppercase text-muted">
            <th>Name</th>
            <th>Email</th>
            <th class="text-center">PWD ID (Last4)</th>
            <th class="text-center">Status</th>
            <th class="text-end" style="width: 180px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="5" class="text-center small text-muted py-4">No job seekers found.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <?php
              $st = $r['pwd_id_status'] ?: 'None';
              $badgeClass = match($st){
                'Verified' => 'text-bg-success',
                'Pending'  => 'text-bg-warning',
                'Rejected' => 'text-bg-danger',
                default    => 'text-bg-secondary'
              };
            ?>
            <tr>
              <td class="small fw-semibold"><a class="text-decoration-none" href="admin_job_seeker_view.php?user_id=<?php echo urlencode($r['user_id']); ?>"><?php echo Helpers::sanitizeOutput($r['name']); ?></a></td>
              <td class="small text-muted"><?php echo Helpers::sanitizeOutput($r['email']); ?></td>
              <td class="small text-center"><?php echo $r['pwd_id_last4'] ? '****'.$r['pwd_id_last4'] : '—'; ?></td>
              <td class="small text-center"><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($st); ?></span></td>
              <td class="text-end small">
                <div class="btn-group btn-group-sm">
                  <a class="btn btn-outline-success" href="?action=verify&user_id=<?php echo urlencode($r['user_id']); ?>" onclick="return confirm('Verify this PWD ID?');">Verify</a>
                  <a class="btn btn-outline-warning" href="?action=pending&user_id=<?php echo urlencode($r['user_id']); ?>" onclick="return confirm('Set back to Pending?');">Pending</a>
                  <a class="btn btn-outline-danger" href="?action=reject&user_id=<?php echo urlencode($r['user_id']); ?>" onclick="return confirm('Reject this PWD ID?');">Reject</a>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
