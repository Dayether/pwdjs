<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';
require_once 'classes/Report.php';
require_once 'classes/Job.php';

Helpers::requireLogin();
Helpers::requireRole('job_seeker');

$job_id = $_GET['job_id'] ?? ($_POST['job_id'] ?? '');
$job = Job::findById($job_id);
if (!$job) Helpers::redirect('index.php');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim($_POST['reason'] ?? '');
    $details = trim($_POST['details'] ?? '');
    if (!$reason) $errors[] = 'Reason is required.';
    if (empty($errors)) {
        if (Report::create($job->job_id, $_SESSION['user_id'], $reason, $details)) {
            Helpers::flash('msg', 'Thank you. Your report has been submitted.');
            Helpers::redirect('job_view.php?job_id=' . urlencode($job->job_id));
        } else {
            $errors[] = 'Failed to submit report.';
        }
    }
}

include 'includes/header.php';
include 'includes/nav.php';
?>
<div class="row justify-content-center">
  <div class="col-lg-10 col-xl-8">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h2 class="h5 fw-semibold mb-3"><i class="bi bi-flag me-2"></i>Report Job: <?php echo Helpers::sanitizeOutput($job->title); ?></h2>
        <?php foreach ($errors as $e): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($e); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endforeach; ?>
        <form method="post" class="row g-3">
          <input type="hidden" name="job_id" value="<?php echo htmlspecialchars($job->job_id); ?>">
          <div class="col-md-6">
            <label class="form-label">Reason</label>
            <select name="reason" class="form-select" required>
              <option value="">Select a reason</option>
              <option value="Spam/Scam" <?php if(($_POST['reason'] ?? '')==='Spam/Scam') echo 'selected'; ?>>Spam/Scam</option>
              <option value="Fake or Misleading" <?php if(($_POST['reason'] ?? '')==='Fake or Misleading') echo 'selected'; ?>>Fake or Misleading</option>
              <option value="Discriminatory" <?php if(($_POST['reason'] ?? '')==='Discriminatory') echo 'selected'; ?>>Discriminatory</option>
              <option value="Inappropriate Content" <?php if(($_POST['reason'] ?? '')==='Inappropriate Content') echo 'selected'; ?>>Inappropriate Content</option>
              <option value="Other" <?php if(($_POST['reason'] ?? '')==='Other') echo 'selected'; ?>>Other</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Details (optional)</label>
            <textarea name="details" class="form-control" rows="5" placeholder="Provide more information (optional)"><?php echo htmlspecialchars($_POST['details'] ?? ''); ?></textarea>
          </div>
          <div class="col-12 d-grid">
            <button class="btn btn-danger btn-lg"><i class="bi bi-flag me-1"></i>Submit Report</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>