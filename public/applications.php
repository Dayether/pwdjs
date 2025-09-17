<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/Application.php';

Helpers::requireLogin();

if (Helpers::isEmployer() && isset($_GET['action'], $_GET['application_id'])) {
    $action = $_GET['action'];
    $app_id = $_GET['application_id'];
    if (in_array($action, ['approve','decline'])) {
        $status = $action === 'approve' ? 'Approved' : 'Declined';
        if (Application::updateStatus($app_id, $status, $_SESSION['user_id'])) {
            Helpers::flash('msg','Application status updated.');
        }
    }
    Helpers::redirect($_SERVER['HTTP_REFERER'] ?? 'employer_dashboard.php');
}

if (Helpers::isJobSeeker()) {
    $apps = Application::listByUser($_SESSION['user_id']);
} else {
    Helpers::redirect('employer_dashboard.php');
}

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="card border-0 shadow-sm">
  <div class="card-body p-4">
    <h2 class="h5 fw-semibold mb-3"><i class="bi bi-list-check me-2"></i>My Applications</h2>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead class="table-light">
          <tr><th>Job Title</th><th>Status</th><th>Match Score</th><th>Applied On</th></tr>
        </thead>
        <tbody>
          <?php foreach ($apps as $a): ?>
            <tr>
              <td><a class="link-primary" href="job_view.php?job_id=<?php echo urlencode($a['job_id']); ?>"><?php echo Helpers::sanitizeOutput($a['title']); ?></a></td>
              <td><?php echo Helpers::sanitizeOutput($a['status']); ?></td>
              <td><span class="badge text-bg-primary"><?php echo number_format($a['match_score'],2); ?></span></td>
              <td><span class="text-muted small"><?php echo date('M j, Y', strtotime($a['created_at'])); ?></span></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$apps): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">No applications yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>