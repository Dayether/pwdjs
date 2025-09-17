<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/Report.php';
require_once '../classes/Job.php';

Helpers::requireLogin();
if (($_SESSION['role'] ?? '') !== 'admin') Helpers::redirect('index.php');

// Actions
if (isset($_GET['action'], $_GET['report_id'])) {
    $action   = $_GET['action'];
    $report_id = $_GET['report_id'];
    $job_id   = $_GET['job_id'] ?? '';

    if ($action === 'resolve') {
        Report::resolve($report_id);
        Helpers::flash('msg', 'Report resolved.');
    } elseif ($action === 'delete_job' && $job_id) {
        if (Job::adminDelete($job_id)) {
            // Optionally mark the report resolved after deletion
            Report::resolve($report_id);
            Helpers::flash('msg', 'Job deleted and report resolved.');
        } else {
            Helpers::flash('msg', 'Failed to delete job.');
        }
    }
    Helpers::redirect('admin_reports.php');
}

$open = Report::listOpen();

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="card border-0 shadow-sm">
  <div class="card-body p-4">
    <h2 class="h5 fw-semibold mb-3"><i class="bi bi-flag me-2"></i>Admin · Job Reports</h2>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead class="table-light">
          <tr>
            <th>Job</th>
            <th>Reporter</th>
            <th>Reason</th>
            <th>Details</th>
            <th>Created</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($open as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['job_title'] ?? ($r['job_id'] ?? '—')); ?></td>
              <td><?php echo htmlspecialchars($r['reporter_name'] ?? '—'); ?></td>
              <td><?php echo htmlspecialchars($r['reason'] ?? '—'); ?></td>
              <td class="text-wrap" style="max-width: 360px;"><?php echo htmlspecialchars($r['details'] ?? '—'); ?></td>
              <td><?php echo htmlspecialchars(date('M j, Y', strtotime($r['created_at'] ?? 'now'))); ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-secondary me-1" href="job_view.php?job_id=<?php echo urlencode($r['job_id'] ?? ''); ?>" target="_blank">View job</a>
                <a class="btn btn-sm btn-outline-success me-1" href="admin_reports.php?action=resolve&report_id=<?php echo urlencode($r['report_id']); ?>">Resolve</a>
                <?php if (!empty($r['job_id'])): ?>
                  <a class="btn btn-sm btn-outline-danger" href="admin_reports.php?action=delete_job&report_id=<?php echo urlencode($r['report_id']); ?>&job_id=<?php echo urlencode($r['job_id']); ?>" onclick="return confirm('Delete this job? This will also remove its applications.');">Delete job</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$open): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No open reports.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>