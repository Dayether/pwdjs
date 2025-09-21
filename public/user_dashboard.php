<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';
require_once '../classes/Application.php';
require_once '../classes/Job.php';

Helpers::requireLogin();
if (!Helpers::isJobSeeker()) Helpers::redirect('index.php');

$user = User::findById($_SESSION['user_id']);

/* Fetch applications for this user */
$apps = Application::listByUser($user->user_id); // returns array (job_id, title, status, match_score, created_at, etc.)
/* Build job status map (Open/Suspended/Closed) */
$jobStatusMap = [];
try {
    if ($apps) {
        $jobIds = array_values(array_unique(array_map(fn($a)=>$a['job_id'], $apps)));
        if ($jobIds) {
            $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT job_id, status FROM jobs WHERE job_id IN ($placeholders)");
            $stmt->execute($jobIds);
            foreach ($stmt->fetchAll() as $row) {
                $jobStatusMap[$row['job_id']] = $row['status'] ?? 'Open';
            }
        }
    }
} catch (Throwable $e) {}

$RECENT_LIMIT = 8;
$recentApps = array_slice($apps, 0, $RECENT_LIMIT);

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h2 class="h5 fw-semibold mb-0">
            <i class="bi bi-grid me-2"></i>Welcome, <?php echo Helpers::sanitizeOutput($user->name); ?>
          </h2>
          <a class="btn btn-sm btn-outline-primary" href="profile_edit.php">
            <i class="bi bi-pencil me-1"></i>Edit Profile
          </a>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="p-3 rounded border bg-white h-100">
              <div class="text-muted small">Education</div>
              <div class="fw-semibold">
                <?php echo Helpers::sanitizeOutput($user->education ?: 'Not specified'); ?>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="p-3 rounded border bg-white h-100">
              <div class="text-muted small">Disability</div>
              <div class="fw-semibold">
                <?php echo Helpers::sanitizeOutput($user->disability ?: 'Not specified'); ?>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-3 d-flex flex-wrap gap-2">
          <a class="btn btn-primary" href="index.php">
            <i class="bi bi-search me-1"></i>Find Jobs
          </a>
          <a class="btn btn-outline-secondary" href="profile_edit.php">
            <i class="bi bi-person-lines-fill me-1"></i>Update Profile
          </a>
        </div>
      </div>
    </div>

    <!-- Optional: Stats summary -->
    <div class="row g-3">
      <div class="col-sm-4">
        <div class="card border-0 shadow-sm">
          <div class="card-body text-center py-3">
            <div class="text-muted small">Applied Jobs</div>
            <div class="fw-bold fs-5"><?php echo count($apps); ?></div>
          </div>
        </div>
      </div>
      <div class="col-sm-4">
        <?php
          $approvedCount = count(array_filter($apps, fn($a)=>($a['status'] ?? '')==='Approved'));
        ?>
        <div class="card border-0 shadow-sm">
          <div class="card-body text-center py-3">
            <div class="text-muted small">Approved</div>
            <div class="fw-bold fs-5 text-success"><?php echo $approvedCount; ?></div>
          </div>
        </div>
      </div>
      <div class="col-sm-4">
        <?php
          $pendingCount = count(array_filter($apps, fn($a)=>($a['status'] ?? '')==='Pending'));
        ?>
        <div class="card border-0 shadow-sm">
          <div class="card-body text-center py-3">
            <div class="text-muted small">Pending</div>
            <div class="fw-bold fs-5 text-warning"><?php echo $pendingCount; ?></div>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- Recent Applications -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body p-4 d-flex flex-column">
        <h3 class="h6 fw-semibold mb-3">
          <i class="bi bi-list-check me-2"></i>My Recent Applications
        </h3>

        <?php if (!$recentApps): ?>
          <div class="alert alert-secondary small mb-0">
            You have not applied to any jobs yet.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Job</th>
                  <th class="text-center">Match</th>
                  <th>Status</th>
                  <th class="text-nowrap">Applied</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentApps as $a): ?>
                  <?php
                    $status = $a['status'] ?? 'Pending';
                    $badgeClass = match($status) {
                      'Approved' => 'success',
                      'Declined' => 'danger',
                      'Pending'  => 'warning',
                      default    => 'secondary'
                    };
                    $jobState = $jobStatusMap[$a['job_id']] ?? 'Open';
                    $jobStateBadge = $jobState !== 'Open'
                      ? '<span class="badge bg-secondary ms-1">'.htmlspecialchars($jobState).'</span>' : '';
                  ?>
                  <tr>
                    <td class="small">
                      <a class="text-decoration-none" href="job_view.php?job_id=<?php echo urlencode($a['job_id']); ?>">
                        <?php echo Helpers::sanitizeOutput($a['title']); ?>
                      </a>
                      <?php echo $jobStateBadge; ?>
                    </td>
                    <td class="text-center small">
                      <?php
                        $ms = isset($a['match_score']) && $a['match_score'] !== null
                          ? (int)$a['match_score'] : null;
                        echo $ms !== null ? $ms.'%' : '—';
                      ?>
                    </td>
                    <td class="small">
                      <span class="badge text-bg-<?php echo $badgeClass; ?>">
                        <?php echo htmlspecialchars($status); ?>
                      </span>
                    </td>
                    <td class="small text-nowrap">
                      <?php echo htmlspecialchars(date('M d', strtotime($a['created_at']))); ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <?php if (count($apps) > $RECENT_LIMIT): ?>
            <div class="mt-3 text-end">
              <a class="small" href="applications.php">View all (<?php echo count($apps); ?>)</a>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>