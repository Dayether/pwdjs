<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/Job.php';
require_once '../classes/User.php';

Helpers::requireLogin();
if (!Helpers::isEmployer()) Helpers::redirect('index.php');

$me = User::findById($_SESSION['user_id']);
$jobs = Job::listByEmployer($me->user_id);

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h2 class="h5 fw-semibold mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>My Jobs</h2>
  <a href="jobs_create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Post a Job</a>
</div>

<?php if ($msg = ($_SESSION['flash']['msg'] ?? null)): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check2-circle me-2"></i><?php echo htmlspecialchars($msg); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<div class="table-responsive">
  <table class="table table-sm align-middle table-hover border">
    <thead class="table-light">
      <tr>
        <th>Title</th>
        <th>Original office</th>
        <th>Work type</th>
        <th>Salary</th>
        <th>Posted</th>
        <th class="text-end">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($jobs as $j): ?>
      <?php
        $salaryStr = '—';
        if ($j['salary_min'] !== null || $j['salary_max'] !== null) {
          if ($j['salary_min'] !== null && $j['salary_max'] !== null) {
            $salaryStr = $j['salary_currency'] . ' ' . number_format($j['salary_min']) . '–' . number_format($j['salary_max']);
          } elseif ($j['salary_min'] !== null) {
            $salaryStr = $j['salary_currency'] . ' ' . number_format($j['salary_min']) . '+';
          } elseif ($j['salary_max'] !== null) {
            $salaryStr = 'Up to ' . $j['salary_currency'] . ' ' . number_format($j['salary_max']);
          }
          $salaryStr .= ' / ' . ucfirst($j['salary_period']);
        }
      ?>
      <tr>
        <td>
          <a class="fw-semibold" href="job_view.php?job_id=<?php echo urlencode($j['job_id']); ?>">
            <?php echo htmlspecialchars($j['title']); ?>
          </a>
          <?php if ($j['status'] !== 'Open'): ?>
            <span class="badge bg-secondary ms-1"><?php echo htmlspecialchars($j['status']); ?></span>
          <?php endif; ?>
        </td>
        <td><?php echo htmlspecialchars(trim($j['location_city'] . ', ' . $j['location_region'], ', ')); ?></td>
        <td><?php echo htmlspecialchars($j['remote_option'] . ' · ' . $j['employment_type']); ?></td>
        <td><?php echo htmlspecialchars($salaryStr); ?></td>
        <td><?php echo htmlspecialchars(date('M d, Y', strtotime($j['created_at']))); ?></td>
        <td class="text-end">
          <a class="btn btn-outline-primary btn-sm" href="job_view.php?job_id=<?php echo urlencode($j['job_id']); ?>">
            View
          </a>
          <!-- WALA NANG EDIT BUTTON DITO -->
          <a class="btn btn-outline-danger btn-sm"
             onclick="return confirm('Delete this job? This cannot be undone.');"
             href="job_delete.php?job_id=<?php echo urlencode($j['job_id']); ?>">
            Delete
          </a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$jobs): ?>
      <tr><td colspan="6" class="text-center text-muted py-4">No jobs posted yet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php include '../includes/footer.php'; ?>