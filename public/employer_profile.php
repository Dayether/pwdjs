<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';
require_once '../classes/Job.php';

$empId = $_GET['user_id'] ?? '';
$employer = $empId ? User::findById($empId) : null;

include '../includes/header.php';
include '../includes/nav.php';

if (!$employer || $employer->role !== 'employer') {
    echo '<div class="container py-5"><div class="alert alert-danger">Employer profile not found.</div></div>';
    include '../includes/footer.php';
    exit;
}

// Optional: Only show if Approved (else limited info)
$limited = ($employer->employer_status !== 'Approved');

// Normalize website
$website = $employer->company_website;
if ($website) {
    $website = trim($website);
    if ($website !== '' && !preg_match('~^https?://~i', $website)) {
        $website = 'https://' . $website;
    }
}

// Fetch open jobs by this employer
$jobs = [];
try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT job_id, title, employment_type, created_at, status
                           FROM jobs
                           WHERE employer_id = ?
                           ORDER BY created_at DESC");
    $stmt->execute([$employer->user_id]);
    $jobs = $stmt->fetchAll();
} catch (Throwable $e) {
    $jobs = [];
}
?>
<div class="container py-4" id="main-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>

  <div class="row">
    <div class="col-lg-5 mb-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <h2 class="h5 fw-semibold mb-3">
            <i class="bi bi-building me-2"></i>
            <?php echo htmlspecialchars($employer->company_name ?: $employer->name); ?>
          </h2>
          <div class="mb-2">
            <span class="badge
              <?php
                echo $employer->employer_status==='Approved'?'text-bg-success':
                     ($employer->employer_status==='Suspended'?'text-bg-warning':
                     ($employer->employer_status==='Rejected'?'text-bg-danger':'text-bg-secondary'));
              ?>">
              <?php echo htmlspecialchars($employer->employer_status ?: 'Status'); ?>
            </span>
          </div>

          <dl class="mb-0 small">
            <dt class="text-muted">Business Email</dt>
            <dd><?php echo htmlspecialchars($employer->business_email ?: $employer->email); ?></dd>

            <?php if (!$limited && $website): ?>
              <dt class="text-muted">Website</dt>
              <dd><a href="<?php echo htmlspecialchars($website); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($website); ?></a></dd>
            <?php endif; ?>

            <?php if (!$limited && $employer->company_phone): ?>
              <dt class="text-muted">Phone</dt>
              <dd><?php echo htmlspecialchars($employer->company_phone); ?></dd>
            <?php endif; ?>

            <?php if ($limited): ?>
              <dt class="text-muted">Information</dt>
              <dd>Full company details are visible once employer is approved.</dd>
            <?php endif; ?>
          </dl>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <h3 class="h6 fw-semibold mb-3"><i class="bi bi-briefcase me-2"></i>Jobs by this Employer (<?php echo count($jobs); ?>)</h3>
          <?php if (!$jobs): ?>
            <div class="text-muted small">No jobs posted yet.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Posted</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($jobs as $j):
                      $st = $j['status'] ?? 'Open';
                      $badge = $st==='Open'?'success':($st==='Suspended'?'warning':($st==='Closed'?'secondary':'secondary'));
                  ?>
                    <tr>
                      <td>
                        <a class="text-decoration-none fw-semibold" href="job_view.php?job_id=<?php echo urlencode($j['job_id']); ?>">
                          <?php echo htmlspecialchars($j['title']); ?>
                        </a>
                      </td>
                      <td><?php echo htmlspecialchars($j['employment_type']); ?></td>
                      <td><span class="badge text-bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($st); ?></span></td>
                      <td><span class="small text-muted"><?php echo date('M j, Y', strtotime($j['created_at'])); ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>