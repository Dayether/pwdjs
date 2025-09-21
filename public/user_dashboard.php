<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';
require_once '../classes/ProfileCompleteness.php';

Helpers::requireLogin();
if (!Helpers::isJobSeeker()) Helpers::redirect('index.php');

$user = User::findById($_SESSION['user_id']);

// Recompute completeness every 15 mins or if empty
$recalcNeeded = empty($user->profile_last_calculated) || (time() - strtotime($user->profile_last_calculated ?? '1970-01-01')) > 900;
if ($recalcNeeded) {
    $percent = ProfileCompleteness::compute($user->user_id);
    $user->profile_completeness = $percent;
} else {
    $percent = (int)$user->profile_completeness;
}

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body p-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span class="fw-semibold"><i class="bi bi-person-check me-2"></i>Profile Completeness</span>
      <span class="badge bg-primary"><?php echo $percent; ?>%</span>
    </div>
    <div class="progress" style="height:10px;">
      <div class="progress-bar <?php echo $percent<50?'bg-danger':($percent<80?'bg-warning':''); ?>"
           role="progressbar"
           style="width:<?php echo $percent; ?>%;"
           aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
    </div>
    <?php if ($percent < 100): ?>
      <div class="mt-2 small">
        <a href="profile_edit.php" class="text-decoration-none">Improve your profile to attract employers.</a>
      </div>
    <?php else: ?>
      <div class="mt-2 small text-success">Great! Your profile is complete.</div>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h2 class="h5 fw-semibold mb-0"><i class="bi bi-grid me-2"></i>Welcome, <?php echo Helpers::sanitizeOutput($user->name); ?></h2>
          <a class="btn btn-sm btn-outline-primary" href="profile_edit.php"><i class="bi bi-pencil me-1"></i>Edit Profile</a>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="p-3 rounded border bg-white h-100">
              <div class="text-muted small">Education</div>
              <div class="fw-semibold">
                <?php
                  $edu = $user->education_level ?: $user->education;
                  echo Helpers::sanitizeOutput($edu ?: 'Not specified');
                ?>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="p-3 rounded border bg-white h-100">
              <div class="text-muted small">Disability</div>
              <div class="fw-semibold"><?php echo Helpers::sanitizeOutput($user->disability ?: 'Not specified'); ?></div>
            </div>
          </div>
        </div>
        <div class="mt-3">
          <a class="btn btn-primary" href="index.php"><i class="bi bi-search me-1"></i>Find Jobs</a>
          <a class="btn btn-outline-secondary" href="applications.php"><i class="bi bi-list-check me-1"></i>My Applications</a>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h3 class="h6 fw-semibold mb-2">Documents</h3>
        <div class="small">
          <div class="mb-1"><i class="bi bi-file-earmark-pdf text-danger me-2"></i>Resume:
            <?php if ($user->resume): ?>
              <a href="../<?php echo htmlspecialchars($user->resume); ?>" target="_blank">View</a>
            <?php else: ?>
              <span class="text-muted">Not uploaded</span>
            <?php endif; ?>
          </div>
          <div><i class="bi bi-camera-video text-primary me-2"></i>Video Intro:
            <?php if ($user->video_intro): ?>
              <a href="../<?php echo htmlspecialchars($user->video_intro); ?>" target="_blank">Watch</a>
            <?php else: ?>
              <span class="text-muted">Not uploaded</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>