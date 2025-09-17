<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/Job.php';
require_once '../classes/User.php';

Helpers::requireLogin();
if (!Helpers::isEmployer()) Helpers::redirect('index.php');

$me = User::findById($_SESSION['user_id']);
$status = $me->employer_status ?? 'Pending';

include '../includes/header.php';
include '../includes/nav.php';

if ($status !== 'Approved'): ?>
  <div class="alert alert-warning mt-3">
    Your employer account is <?php echo htmlspecialchars($status); ?>. You can manage jobs only after approval.
  </div>
<?php else:
  $jobs = Job::listByEmployer($me->user_id);
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h2 class="h5 fw-semibold mb-0"><i class="bi bi-grid me-2"></i>My Jobs</h2>
  <a class="btn btn-primary" href="jobs_create.php"><i class="bi bi-plus-lg me-1"></i>Post a Job</a>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Title</th>
            <th class="d-none d-md-table-cell">Original office</th>
            <th class="d-none d-md-table-cell">Work type</th>
            <th class="d-none d-md-table-cell">Salary</th>
            <th>Posted</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($jobs as $j): ?>
            <tr>
              <td>
                <a href="job_view.php?job_id=<?php echo urlencode($j['job_id']); ?>">
                  <?php echo Helpers::sanitizeOutput($j['title']); ?>
                </a>
              </td>
              <td class="d-none d-md-table-cell">
                <?php
                  $loc = trim(($j['location_city'] ?? '') . ((($j['location_city'] ?? '') && ($j['location_region'] ?? '')) ? ', ' : '') . ($j['location_region'] ?? ''));
                  echo Helpers::sanitizeOutput($loc ?: '—');
                ?>
              </td>
              <td class="d-none d-md-table-cell">
                <?php echo Helpers::sanitizeOutput(($j['remote_option'] ?? 'Work From Home') . ' · ' . ($j['employment_type'] ?? 'Full time')); ?>
              </td>
              <td class="d-none d-md-table-cell">
                <?php
                  $cur = $j['salary_currency'] ?? 'PHP';
                  $min = $j['salary_min'] ?? null;
                  $max = $j['salary_max'] ?? null;
                  $per = $j['salary_period'] ?? 'monthly';
                  if ($min === null && $max === null) {
                    echo '—';
                  } else {
                    $fmt = function($n){ return number_format((int)$n); };
                    $range = ($min !== null && $max !== null && $min != $max) ? "{$fmt($min)}–{$fmt($max)}" : $fmt($min ?? $max);
                    echo htmlspecialchars("{$cur} {$range} / " . ucfirst($per));
                  }
                ?>
              </td>
              <td><?php echo date('M j, Y', strtotime($j['created_at'])); ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary me-1" href="job_view.php?job_id=<?php echo urlencode($j['job_id']); ?>">View</a>
                <a class="btn btn-sm btn-outline-secondary me-1" href="jobs_edit.php?job_id=<?php echo urlencode($j['job_id']); ?>">Edit</a>
                <a class="btn btn-sm btn-outline-danger" href="jobs_edit.php?delete=1&job_id=<?php echo urlencode($j['job_id']); ?>" onclick="return confirm('Delete this job?');">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$jobs): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">You have not posted any jobs yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>
<?php include '../includes/footer.php'; ?>