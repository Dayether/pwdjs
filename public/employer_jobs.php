<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ADDED: store last page */
Helpers::storeLastPage();

$pdo = Database::getConnection();
$employer_id = $_GET['employer_id'] ?? '';

$emp = User::findById($employer_id);
if (!$emp || $emp->role !== 'employer' || ($emp->employer_status ?? 'Pending') !== 'Approved') {
  Helpers::redirect('index.php');
}

$stmt = $pdo->prepare("
  SELECT job_id, title, created_at, location_city, location_region, employment_type,
         salary_currency, salary_min, salary_max, salary_period
  FROM jobs
  WHERE employer_id = ? AND remote_option = 'Work From Home'
  ORDER BY created_at DESC
");
$stmt->execute([$employer_id]);
$jobs = $stmt->fetchAll();

include '../includes/header.php';
include '../includes/nav.php';

function fmt_salary($cur, $min, $max, $period) {
  if ($min === null && $max === null) return 'Salary not specified';
  $fmt = function($n){ return number_format((int)$n); };
  $range = ($min !== null && $max !== null && $min != $max)
    ? "{$fmt($min)}–{$fmt($max)}"
    : $fmt($min ?? $max);
  return "{$cur} {$range} / " . ucfirst($period ?: 'monthly');
}

/* ADDED: use last_page override for Back */
$backUrl = Helpers::getLastPage('index.php');
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h2 class="h5 fw-semibold mb-0"><i class="bi bi-briefcase me-2"></i>WFH jobs at <?php echo Helpers::sanitizeOutput($emp->company_name ?? 'This employer'); ?></h2>
  <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars($backUrl); ?>"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="row g-3">
  <?php foreach ($jobs as $job): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <h3 class="h6 fw-semibold mb-1">
            <a class="text-decoration-none" href="job_view.php?job_id=<?php echo urlencode($job['job_id']); ?>">
              <?php echo Helpers::sanitizeOutput($job['title']); ?>
            </a>
          </h3>
          <div class="small mb-1">
            <i class="bi bi-geo-alt me-1"></i>Original office:
            <?php
              echo Helpers::sanitizeOutput(trim(($job['location_city'] ?: ''), ' '));
              if ($job['location_city'] && $job['location_region']) echo ', ';
              echo Helpers::sanitizeOutput($job['location_region'] ?: '');
            ?>
          </div>
          <div class="d-flex flex-wrap gap-1 mb-2">
            <span class="badge text-bg-light border">Work From Home</span>
            <span class="badge text-bg-light border"><?php echo Helpers::sanitizeOutput($job['employment_type']); ?></span>
          </div>
          <div class="small">
            <i class="bi bi-cash-coin me-1"></i><?php
              echo htmlspecialchars(fmt_salary($job['salary_currency'] ?: 'PHP', $job['salary_min'], $job['salary_max'], $job['salary_period']));
            ?>
          </div>
        </div>
        <div class="card-footer bg-white border-0 pt-0 pb-3 px-3 d-flex justify-content-between align-items-center">
          <span class="text-muted small"><?php echo date('M j, Y', strtotime($job['created_at'])); ?></span>
          <a class="btn btn-sm btn-outline-primary" href="job_view.php?job_id=<?php echo urlencode($job['job_id']); ?>">View</a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (!$jobs): ?>
    <div class="col-12">
      <div class="alert alert-secondary">No WFH jobs posted yet.</div>
    </div>
  <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>