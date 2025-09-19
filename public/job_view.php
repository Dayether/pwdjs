<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/Job.php';
require_once '../classes/Skill.php';
require_once '../classes/User.php';

$job_id = $_GET['job_id'] ?? '';
$job = Job::findById($job_id);
if (!$job) {
    include '../includes/header.php';
    include '../includes/nav.php';
    echo '<div class="container py-5"><div class="alert alert-danger">Job not found.</div></div>';
    include '../includes/footer.php';
    exit;
}

$employer = User::findById($job->employer_id);
$jobSkills = Skill::getSkillsForJob($job->job_id);
$skillNames = array_column($jobSkills, 'name');

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="row">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body p-4">
        <h1 class="h4 fw-semibold mb-2"><?php echo Helpers::sanitizeOutput($job->title); ?></h1>
        <div class="text-muted small mb-3">
          Posted: <?php echo htmlspecialchars(date('M d, Y', strtotime($job->created_at))); ?>
          · Employer: <?php echo htmlspecialchars($employer?->company_name ?: $employer?->name ?: ''); ?>
          <?php if ($job->location_city || $job->location_region): ?>
            · Location: <?php echo htmlspecialchars(trim($job->location_city . ', ' . $job->location_region, ', ')); ?>
          <?php endif; ?>
        </div>

        <?php if ($skillNames): ?>
          <div class="mb-3">
            <h6 class="fw-semibold mb-2"><i class="bi bi-list-check me-1"></i>Required Skills</h6>
            <?php foreach ($skillNames as $sn): ?>
              <span class="badge text-bg-secondary me-1 mb-1"><?php echo htmlspecialchars($sn); ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php
          $accTags = array_filter(array_map('trim', explode(',', $job->accessibility_tags ?? '')));
          if ($accTags):
        ?>
          <div class="mb-3">
            <h6 class="fw-semibold mb-2"><i class="bi bi-universal-access me-1"></i>Accessibility</h6>
            <?php foreach ($accTags as $t): ?>
              <span class="badge text-bg-info me-1 mb-1"><?php echo htmlspecialchars($t); ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="mb-3">
          <h6 class="fw-semibold mb-2"><i class="bi bi-file-text me-1"></i>Description</h6>
          <div class="text-body">
            <?php echo nl2br(Helpers::sanitizeOutput($job->description)); ?>
          </div>
        </div>

        <div class="mb-3">
          <h6 class="fw-semibold mb-2"><i class="bi bi-mortarboard me-1"></i>Requirements Summary</h6>
          <ul class="small mb-0">
            <li>Experience: <?php echo (int)$job->required_experience; ?> year(s)</li>
            <li>Education: <?php echo $job->required_education ? Helpers::sanitizeOutput($job->required_education) : 'Any'; ?></li>
            <li>Employment Type: <?php echo Helpers::sanitizeOutput($job->employment_type); ?></li>
            <li>Remote: 100% Work From Home</li>
          </ul>
        </div>

        <div class="mb-3">
          <h6 class="fw-semibold mb-2"><i class="bi bi-cash-stack me-1"></i>Compensation</h6>
          <?php
            if ($job->salary_min !== null || $job->salary_max !== null) {
              $range = '';
              if ($job->salary_min !== null && $job->salary_max !== null) {
                $range = number_format($job->salary_min) . ' - ' . number_format($job->salary_max);
              } elseif ($job->salary_min !== null) {
                $range = number_format($job->salary_min) . '+';
              } elseif ($job->salary_max !== null) {
                $range = 'Up to ' . number_format($job->salary_max);
              }
              echo '<span class="badge text-bg-success">' . htmlspecialchars($job->salary_currency) . ' ' . $range . ' / ' . htmlspecialchars($job->salary_period) . '</span>';
            } else {
              echo '<span class="text-muted small">Not specified</span>';
            }
          ?>
        </div>

        <?php if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'job_seeker'): ?>
          <hr>
          <div>
            <a class="btn btn-primary btn-lg" href="apply_job.php?job_id=<?php echo urlencode($job->job_id); ?>">
              <i class="bi bi-send me-1"></i>Apply Now
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h5 class="fw-semibold mb-3"><i class="bi bi-building me-1"></i>Employer</h5>
        <?php if ($employer): ?>
          <div class="small mb-2">
            <strong><?php echo htmlspecialchars($employer->company_name ?: $employer->name); ?></strong><br>
            <span class="text-muted"><?php echo htmlspecialchars($employer->email); ?></span>
          </div>
          <a class="btn btn-outline-secondary btn-sm" href="employer_jobs.php?employer_id=<?php echo urlencode($employer->user_id); ?>">
            View all jobs
          </a>
        <?php else: ?>
          <p class="text-muted small mb-0">Employer details not available.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>