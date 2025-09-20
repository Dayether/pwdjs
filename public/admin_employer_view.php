<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';

if (session_status()===PHP_SESSION_NONE){
    session_start();
}
Helpers::requireLogin();
if (!Helpers::isAdmin()) {
  Helpers::redirect('index.php');
}

/* ADDED: store page for back usage */
Helpers::storeLastPage();

$user_id = $_GET['user_id'] ?? '';
if ($user_id === '') {
  Helpers::flash('error','Missing user_id.');
  Helpers::redirect('admin_employers.php');
}

if (isset($_GET['action'])) {
  $action = $_GET['action'];
  $map = [
    'approve'  => 'Approved',
    'suspend'  => 'Suspended',
    'pending'  => 'Pending',
    'reject'   => 'Rejected'
  ];
  if (isset($map[$action])) {
    if (User::updateEmployerStatus($user_id, $map[$action])) {
      Helpers::flash('msg', 'Employer status updated to ' . $map[$action] . '.');
    } else {
      Helpers::flash('msg', 'Failed to update employer status.');
    }
  }
  Helpers::redirect('admin_employer_view.php?user_id=' . urlencode($user_id));
  exit;
}

$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT u.*, (SELECT COUNT(*) FROM jobs j WHERE j.employer_id = u.user_id) AS job_count
                       FROM users u WHERE u.user_id = ? AND u.role = 'employer' LIMIT 1");
$stmt->execute([$user_id]);
$emp = $stmt->fetch();

if (!$emp) {
  Helpers::flash('msg', 'Employer not found.');
  Helpers::redirect('admin_employers.php');
}

$status = $emp['employer_status'] ?: 'Pending';

$stmtJobs = $pdo->prepare("SELECT job_id, title, created_at FROM jobs WHERE employer_id = ? ORDER BY created_at DESC");
$stmtJobs->execute([$emp['user_id']]);
$jobs = $stmtJobs->fetchAll();

function doc_ext($path) {
  return strtolower(pathinfo($path, PATHINFO_EXTENSION));
}

include '../includes/header.php';
include '../includes/nav.php';

/* ADDED: use last page override default list page */
$backUrl = Helpers::getLastPage('admin_employers.php');

/* ADDED START: Flash rendering (no removals) */
$__fl = Helpers::getFlashes();
if ($__fl) {
  echo '<div class="mb-3">';
  foreach ($__fl as $k=>$msg) {
    if (trim($msg)==='') $msg = 'Action completed.'; // fallback so hindi blank
    $type = match($k){
      'error','danger' => 'danger',
      'success'        => 'success',
      'warning','auth' => 'warning',
      'msg','info'     => 'info',
      default          => 'primary'
    };
    echo '<div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">';
    echo '<i class="bi bi-info-circle me-2"></i>'.htmlspecialchars($msg);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    echo '</div>';
  }
  echo '</div>';
}
/* ADDED END */
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h2 class="h5 fw-semibold mb-0"><i class="bi bi-building-check me-2"></i>Employer Profile</h2>
  <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small">Company</div>
            <h3 class="h5 fw-semibold mb-1"><?php echo Helpers::sanitizeOutput($emp['company_name'] ?: '(none)'); ?></h3>
            <div class="small text-muted">Permit / Registration No.: <span class="fw-semibold"><?php echo Helpers::sanitizeOutput($emp['business_permit_number'] ?: '(none)'); ?></span></div>
          </div>
          <div>
            <span class="badge <?php echo $status==='Approved'?'text-bg-success':($status==='Pending'?'text-bg-warning':($status==='Suspended'?'text-bg-danger':'text-bg-secondary')); ?>">
              <?php echo htmlspecialchars($status); ?>
            </span>
          </div>
        </div>

        <hr>

        <div class="row g-3">
          <div class="col-md-6">
            <div class="text-muted small">Business Email</div>
            <div class="fw-medium"><?php echo Helpers::sanitizeOutput($emp['business_email'] ?: '(none)'); ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Account Owner</div>
            <div class="fw-medium"><?php echo Helpers::sanitizeOutput($emp['name']); ?></div>
            <div class="small text-muted"><?php echo Helpers::sanitizeOutput($emp['email']); ?></div>
          </div>

          <div class="col-md-6">
            <div class="text-muted small">Created At</div>
            <div class="fw-medium"><?php echo htmlspecialchars(date('M d, Y', strtotime($emp['created_at']))); ?></div>
          </div>
            <div class="col-md-6">
              <div class="text-muted small">Jobs Posted</div>
              <div class="fw-medium"><?php echo (int)$emp['job_count']; ?></div>
            </div>
        </div>

        <hr>

        <div class="small text-muted mb-2">Actions</div>
        <div class="d-flex flex-wrap gap-2">
          <a href="?user_id=<?php echo urlencode($emp['user_id']); ?>&action=approve" class="btn btn-sm btn-success">Approve</a>
          <a href="?user_id=<?php echo urlencode($emp['user_id']); ?>&action=pending" class="btn btn-sm btn-warning">Set Pending</a>
            <a href="?user_id=<?php echo urlencode($emp['user_id']); ?>&action=suspend" class="btn btn-sm btn-danger">Suspend</a>
          <a href="?user_id=<?php echo urlencode($emp['user_id']); ?>&action=reject" class="btn btn-sm btn-secondary">Reject</a>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
      <div class="card-body p-4">
        <h3 class="h6 fw-semibold mb-3"><i class="bi bi-card-list me-1"></i>Recent Jobs</h3>
        <?php if ($jobs): ?>
          <ul class="list-unstyled small mb-0">
            <?php foreach ($jobs as $j): ?>
              <li class="mb-1">
                <a class="text-decoration-none" href="job_view.php?job_id=<?php echo urlencode($j['job_id']); ?>">
                  <?php echo Helpers::sanitizeOutput($j['title']); ?>
                </a>
                <span class="text-muted"> · <?php echo htmlspecialchars(date('M d, Y', strtotime($j['created_at']))); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="text-muted small">No jobs posted yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h3 class="h6 fw-semibold mb-3">
          <i class="bi bi-file-earmark-text me-1"></i>Verification Document
        </h3>
        <?php
          $doc = $emp['employer_doc'] ?? '';
          if ($doc):
            $ext = doc_ext($doc);
        ?>
          <div class="small mb-2">
            File: <span class="fw-semibold"><?php echo Helpers::sanitizeOutput(basename($doc)); ?></span>
          </div>
          <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp'], true)): ?>
            <img src="<?php echo Helpers::sanitizeOutput($doc); ?>" alt="Document" class="img-fluid rounded border">
          <?php elseif ($ext === 'pdf'): ?>
            <a href="<?php echo Helpers::sanitizeOutput($doc); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-filetype-pdf me-1"></i>Open PDF
            </a>
          <?php else: ?>
            <a href="<?php echo Helpers::sanitizeOutput($doc); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-box-arrow-up-right me-1"></i>Open Document
            </a>
          <?php endif; ?>
        <?php else: ?>
          <div class="text-muted small">No document uploaded.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>