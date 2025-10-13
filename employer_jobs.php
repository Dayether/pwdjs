<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';
require_once 'classes/User.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require login so we can enforce role-based access
Helpers::requireLogin();

// Job seekers should not access employer pages directly
if (Helpers::isJobSeeker()) {
  Helpers::flash('error','You do not have permission to access that page.');
  Helpers::redirectToRoleDashboard();
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
  SELECT j.job_id, j.title, j.created_at, j.location_city, j.location_region, j.employment_type,
         j.salary_currency, j.salary_min, j.salary_max, j.salary_period, j.job_image,
         COALESCE(jt.pwd_types, j.applicable_pwd_types) AS pwd_types
  FROM jobs j
  LEFT JOIN (
    SELECT job_id, GROUP_CONCAT(DISTINCT pwd_type ORDER BY pwd_type SEPARATOR ',') AS pwd_types
    FROM job_applicable_pwd_types
    GROUP BY job_id
  ) jt ON jt.job_id = j.job_id
  WHERE j.employer_id = ? AND j.remote_option = 'Work From Home' AND j.moderation_status='Approved'
  ORDER BY j.created_at DESC
");
$stmt->execute([$employer_id]);
$jobs = $stmt->fetchAll();

include 'includes/header.php';
include 'includes/nav.php';

function fmt_salary($cur, $min, $max, $period) {
  if ($min === null && $max === null) return 'Salary not specified';
  $fmt = function($n){ return number_format((int)$n); };
  if ($min !== null && $max !== null && $min != $max) {
    $range = $fmt($min) . '–' . $fmt($max);
  } else {
    $value = ($min ?? $max);
    $range = $fmt($value);
  }
  return $cur . ' ' . $range . ' / ' . ucfirst($period ?: 'monthly');
}

/* ADDED: use last_page override for Back */
$backUrl = Helpers::getLastPage('index.php');
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h2 class="h5 fw-semibold mb-0"><i class="bi bi-briefcase me-2"></i>WFH jobs at <?php echo Helpers::sanitizeOutput($emp->company_name ?? 'This employer'); ?></h2>
  <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars($backUrl); ?>"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<style>
.job-card-thumb{width:100%;height:140px;object-fit:cover;border-top-left-radius:.5rem;border-top-right-radius:.5rem}
</style>
<div class="row g-3">
  <?php foreach ($jobs as $job): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <?php if (!empty($job['job_image'])): ?>
          <img class="job-card-thumb" src="<?php echo htmlspecialchars($job['job_image']); ?>" alt="Job image">
        <?php endif; ?>
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
            <?php if (!empty($job['pwd_types'])): ?>
              <?php $parts = array_values(array_unique(array_filter(array_map('trim', explode(',', $job['pwd_types']))))); ?>
              <?php foreach ($parts as $pt): ?>
                <span class="badge bg-primary-subtle text-primary-emphasis border"><?php echo htmlspecialchars($pt); ?></span>
              <?php endforeach; ?>
            <?php endif; ?>
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

<?php include 'includes/footer.php'; ?>