<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';
require_once '../classes/Job.php';

Helpers::requireLogin();
if (($_SESSION['role'] ?? '') !== 'admin') Helpers::redirect('index.php');

$user_id = $_GET['user_id'] ?? '';
if (!$user_id) {
  Helpers::flash('msg', 'Missing employer id.');
  Helpers::redirect('admin_employers.php');
}

// Status actions handled here (only in the view)
if (isset($_GET['action'])) {
  $map = ['approve'=>'Approved','suspend'=>'Suspended','reject'=>'Rejected','pending'=>'Pending'];
  $action = $_GET['action'];
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

// Jobs by employer
$stmtJobs = $pdo->prepare("SELECT job_id, title, created_at FROM jobs WHERE employer_id = ? ORDER BY created_at DESC");
$stmtJobs->execute([$emp['user_id']]);
$jobs = $stmtJobs->fetchAll();

// Helper to detect document type
function doc_ext($path) {
  return strtolower(pathinfo($path, PATHINFO_EXTENSION));
}

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h2 class="h5 fw-semibold mb-0"><i class="bi bi-building-check me-2"></i>Employer Profile</h2>
  <a href="admin_employers.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
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
            <span class="badge <?php echo $status==='Approved'?'text-bg-success':($status==='Pending'?'text-bg-warning':'text-bg-danger'); ?>">
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
            <div class="text-muted small">Company Website</div>
            <div class="fw-medium">
              <?php if (!empty($emp['company_website'])): ?>
                <a target="_blank" rel="noopener" href="<?php echo htmlspecialchars($emp['company_website']); ?>">Visit website</a>
              <?php else: ?>
                (none)
              <?php endif; ?>
            </div>
          </div>

          <div class="col-md-6">
            <div class="text-muted small">Company Phone</div>
            <div class="fw-medium"><?php echo Helpers::sanitizeOutput($emp['company_phone'] ?: '(none)'); ?></div>
          </div>

          <div class="col-md-6">
            <div class="text-muted small">Registered</div>
            <div class="fw-medium"><?php echo !empty($emp['created_at']) ? date('M j, Y H:i', strtotime($emp['created_at'])) : '(unknown)'; ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Jobs Posted</div>
            <div class="fw-medium"><?php echo (int)($emp['job_count'] ?? 0); ?></div>
          </div>
        </div>

        <div class="mt-3">
          <div class="btn-group">
            <a class="btn btn-sm btn-outline-secondary" href="admin_employer_view.php?action=pending&user_id=<?php echo urlencode($emp['user_id']); ?>">Set Pending</a>
            <a class="btn btn-sm btn-outline-success" href="admin_employer_view.php?action=approve&user_id=<?php echo urlencode($emp['user_id']); ?>">Approve</a>
            <a class="btn btn-sm btn-outline-warning" href="admin_employer_view.php?action=suspend&user_id=<?php echo urlencode($emp['user_id']); ?>">Suspend</a>
            <a class="btn btn-sm btn-outline-danger" href="admin_employer_view.php?action=reject&user_id=<?php echo urlencode($emp['user_id']); ?>" onclick="return confirm('Reject employer?')">Reject</a>
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
      <div class="card-body p-4">
        <h3 class="h6 fw-semibold mb-3"><i class="bi bi-briefcase me-2"></i>Jobs by this employer</h3>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead class="table-light">
              <tr><th>Title</th><th>Posted</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($jobs as $j): ?>
                <tr>
                  <td class="fw-medium"><?php echo Helpers::sanitizeOutput($j['title']); ?></td>
                  <td><span class="text-muted small"><?php echo date('M j, Y', strtotime($j['created_at'])); ?></span></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-info" target="_blank" href="job_view.php?job_id=<?php echo urlencode($j['job_id']); ?>"><i class="bi bi-box-arrow-up-right me-1"></i>Open</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$jobs): ?>
                <tr><td colspan="3" class="text-center text-muted py-4">No jobs posted.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h3 class="h6 fw-semibold mb-3"><i class="bi <?php echo !empty($emp['employer_doc']) ? 'bi-file-earmark-text' : 'bi-file-earmark' ; ?> me-2"></i>Verification Document</h3>

        <?php if (!empty($emp['employer_doc'])): ?>
          <?php $doc = $emp['employer_doc']; $ext = doc_ext($doc); ?>
          <div class="mb-2">
            <a class="btn btn-sm btn-outline-primary" target="_blank" href="../<?php echo htmlspecialchars($doc); ?>">
              <i class="bi bi-download me-1"></i>Open / Download
            </a>
          </div>

          <?php if (in_array($ext, ['png','jpg','jpeg','gif','webp'])): ?>
            <img src="../<?php echo htmlspecialchars($doc); ?>" alt="Business document" class="img-fluid border rounded">
          <?php elseif ($ext === 'pdf'): ?>
            <iframe src="../<?php echo htmlspecialchars($doc); ?>" style="width:100%; min-height:520px" class="border rounded"></iframe>
          <?php else: ?>
            <div class="alert alert-secondary small mb-0">Preview not available. Use the Open / Download button above.</div>
          <?php endif; ?>
        <?php else: ?>
          <div class="alert alert-secondary mb-0">No document uploaded.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>