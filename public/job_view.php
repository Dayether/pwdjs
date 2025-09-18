<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/Job.php';
require_once '../classes/Application.php';
require_once '../classes/User.php';
require_once '../classes/Skill.php';

$job_id = $_GET['job_id'] ?? '';
$job = Job::findById($job_id);
if (!$job) { header('Location: index.php'); exit; }

$role = $_SESSION['role'] ?? '';
$isLoggedIn = !empty($_SESSION['user_id']);
$isEmployerOfJob = ($isLoggedIn && $_SESSION['user_id'] === $job->employer_id && Helpers::isEmployer());
$viewerIsAdmin = ($role === 'admin');

$employer = User::findById($job->employer_id);
$employerStatus = $employer->employer_status ?? 'Pending';
if (!$viewerIsAdmin && !$isEmployerOfJob && $employerStatus !== 'Approved') { header('Location: index.php'); exit; }

// Safe fallbacks
$employmentType = $job->employment_type ?? 'Full time';
$origCity       = $job->location_city   ?? '';
$origRegion     = $job->location_region ?? '';
$salaryCurrency = $job->salary_currency ?? 'PHP';
$salaryMin      = property_exists($job, 'salary_min') ? $job->salary_min : null;
$salaryMax      = property_exists($job, 'salary_max') ? $job->salary_max : null;
$salaryPeriod   = $job->salary_period   ?? 'monthly';

$showApplyPanel = (!$isLoggedIn || $role === 'job_seeker');

$applicants = $isEmployerOfJob ? Application::listByJob($job->job_id) : [];
$jobSkillsCsv = Skill::getSkillNamesForJob($job->job_id);
$jobSkills = array_filter(array_map('trim', explode(',', $jobSkillsCsv)));
$tags = array_filter(array_map('trim', explode(',', $job->accessibility_tags)));

function fmt_salary($cur, $min, $max, $period) {
  if ($min === null && $max === null) return null;
  $fmt = function($n){ return number_format((int)$n); };
  $range = ($min !== null && $max !== null && $min != $max)
    ? "{$fmt($min)}–{$fmt($max)}"
    : $fmt($min ?? $max);
  return "{$cur} {$range} / " . ucfirst($period ?: 'monthly');
}

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="row g-3">
  <div class="<?php echo $showApplyPanel ? 'col-lg-8' : 'col-lg-12'; ?>">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
          <div>
            <h2 class="h4 fw-semibold mb-0"><?php echo Helpers::sanitizeOutput($job->title); ?></h2>
            <div class="text-muted small">
              <?php echo Helpers::sanitizeOutput($employer->company_name ?? ''); ?>
              · <a href="employer_jobs.php?employer_id=<?php echo urlencode($employer->user_id); ?>">View all jobs</a>
            </div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <small class="text-muted"><?php echo date('M j, Y', strtotime($job->created_at)); ?></small>
            <?php if ($isEmployerOfJob || $viewerIsAdmin): ?>
              <a class="btn btn-sm btn-outline-secondary" href="jobs_edit.php?job_id=<?php echo urlencode($job->job_id); ?>">
                <i class="bi bi-pencil-square me-1"></i>Edit job
              </a>
            <?php endif; ?>
          </div>
        </div>

        <?php if (in_array($job->status, ['Suspended','Closed'], true)): ?>
          <div class="alert alert-danger mt-3 mb-0">
            This job is currently <?php echo htmlspecialchars($job->status); ?> and may not accept new applications.
          </div>
        <?php endif; ?>

        <div class="mt-3 d-flex flex-wrap gap-2">
          <span class="badge text-bg-light border"><i class="bi bi-house-door me-1"></i>Work From Home</span>
          <span class="badge text-bg-light border"><i class="bi bi-briefcase me-1"></i><?php echo Helpers::sanitizeOutput($employmentType); ?></span>
          <span class="badge text-bg-light border"><i class="bi bi-geo-alt me-1"></i>Original office:
            <?php
              $loc = trim($origCity + ($origCity && $origRegion ? ', ' : '') + $origRegion);
              // Using concatenation with '.' to avoid PHP warning
              $loc = trim($origCity . ($origCity && $origRegion ? ', ' : '') . $origRegion);
              echo Helpers::sanitizeOutput($loc);
            ?>
          </span>
          <?php if ($s = fmt_salary($salaryCurrency, $salaryMin, $salaryMax, $salaryPeriod)): ?>
            <span class="badge text-bg-light border"><i class="bi bi-cash-coin me-1"></i><?php echo htmlspecialchars($s); ?></span>
          <?php endif; ?>
        </div>

        <?php if ($tags): ?>
          <div class="mt-2">
            <?php foreach ($tags as $t): ?>
              <span class="badge badge-accessibility me-1"><?php echo Helpers::sanitizeOutput($t); ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($jobSkills): ?>
          <div class="mt-2 d-flex flex-wrap gap-1">
            <?php foreach ($jobSkills as $s): ?>
              <span class="badge badge-skill"><?php echo Helpers::sanitizeOutput($s); ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <hr class="my-3">
        <div class="job-description">
          <p class="mb-0"><?php echo nl2br(Helpers::sanitizeOutput($job->description)); ?></p>
        </div>
      </div>
    </div>

    <?php if ($isEmployerOfJob): ?>
      <div class="card border-0 shadow-sm mt-3">
        <div class="card-body p-4">
          <h3 class="h6 fw-semibold mb-3"><i class="bi bi-people me-2"></i>Applicants (Ranked by Match Score)</h3>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead class="table-light">
                <tr><th>Name</th><th>Relevant Exp</th><th>Education</th><th>Match</th><th>Status</th><th class="text-end">Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($applicants as $app): ?>
                  <tr>
                    <td><?php echo Helpers::sanitizeOutput($app['name']); ?></td>
                    <td><?php echo (int)$app['relevant_experience']; ?> yrs</td>
                    <td><?php echo Helpers::sanitizeOutput($app['application_education'] ?: 'Not specified'); ?></td>
                    <td><span class="badge text-bg-primary"><?php echo number_format($app['match_score'],2); ?></span></td>
                    <td><?php echo Helpers::sanitizeOutput($app['status']); ?></td>
                    <td class="text-end">
                      <?php if ($app['status'] === 'Pending'): ?>
                        <a class="btn btn-sm btn-outline-success me-1" href="applications.php?action=approve&application_id=<?php echo urlencode($app['application_id']); ?>">Approve</a>
                        <a class="btn btn-sm btn-outline-danger" href="applications.php?action=decline&application_id=<?php echo urlencode($app['application_id']); ?>">Decline</a>
                      <?php else: ?>
                        <span class="text-muted small">No actions</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$applicants): ?>
                  <tr><td colspan="6" class="text-center text-muted py-4">No applicants yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($showApplyPanel): ?>
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <h3 class="h6 fw-semibold mb-2">Apply for this job</h3>
          <?php if (in_array($job->status, ['Suspended','Closed'], true)): ?>
            <div class="alert alert-warning">
              This job is not accepting new applications at the moment.
            </div>
          <?php endif; ?>
          <p class="text-muted small">Provide your relevant experience, education, and select the matching skills.</p>
          <?php if (!$isLoggedIn): ?>
            <a class="btn btn-outline-secondary w-100" href="login.php">Login to Apply</a>
          <?php else: ?>
            <div class="d-grid gap-2">
              <a class="btn btn-success <?php echo in_array($job->status, ['Suspended','Closed'], true) ? 'disabled' : ''; ?>" href="<?php echo in_array($job->status, ['Suspended','Closed'], true) ? '#' : 'job_apply.php?job_id='.urlencode($job->job_id); ?>">
                <i class="bi bi-send me-1"></i>Start Application
              </a>
              <a class="btn btn-outline-danger" href="job_report.php?job_id=<?php echo urlencode($job->job_id); ?>"><i class="bi bi-flag me-1"></i>Report this job</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>