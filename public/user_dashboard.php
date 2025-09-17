<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';

Helpers::requireLogin();
if (!Helpers::isJobSeeker()) Helpers::redirect('index.php');
$user = User::findById($_SESSION['user_id']);

include '../includes/header.php';
include '../includes/nav.php';
?>
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
              <div class="fw-semibold"><?php echo Helpers::sanitizeOutput($user->education ?: 'Not specified'); ?></div>
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