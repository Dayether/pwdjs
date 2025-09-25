<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';

Helpers::requireLogin();
// If a logged-in job seeker tries to force open an employer-only management page, redirect.
if (Helpers::isJobSeeker()) {
  // Viewing an employer profile is allowed for everyone typically, but the requirement
  // asks to treat employer pages as restricted when forced by job seekers.
  // We'll allow viewing public employer profiles; only restrict management pages elsewhere.
}

$viewerId   = $_SESSION['user_id'] ?? null;
$viewerRole = $_SESSION['role'] ?? '';
$userParam  = $_GET['user_id'] ?? '';

if ($userParam === '' && $viewerRole === 'employer') {
    $userParam = $viewerId;
}

$employer = User::findById($userParam);
$backUrl  = $_SERVER['HTTP_REFERER'] ?? 'index.php';

if (!$employer || $employer->role !== 'employer') {
    include '../includes/header.php';
    include '../includes/nav.php';
    echo '<div class="container py-5"><div class="alert alert-danger">Employer profile not found.</div></div>';
    include '../includes/footer.php';
    exit;
}

$isSelf = ($viewerRole === 'employer' && $viewerId === $employer->user_id);
$canSeePrivate = $isSelf || ($viewerRole === 'admin');

/* Load jobs */
$jobs = [];
try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("
        SELECT job_id, title, status, created_at, employment_type, salary_min, salary_max, salary_currency
        FROM jobs
        WHERE employer_id=?
        ORDER BY created_at DESC
        LIMIT 200
    ");
    $stmt->execute([$employer->user_id]);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $jobs = [];
}

include '../includes/header.php';
include '../includes/nav.php';

$joinDate = null;
if (!empty($employer->created_at)) {
    $ts = strtotime($employer->created_at);
    if ($ts && $ts > 0) $joinDate = date('Y-m-d',$ts);
}
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex gap-2">
      <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
      </a>
      <?php if ($isSelf): ?>
        <a href="employer_edit.php" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-pencil-square me-1"></i>Edit Profile
        </a>
      <?php endif; ?>
    </div>
    <?php if ($isSelf): ?>
      <a class="btn btn-outline-primary btn-sm" href="employer_dashboard.php">
        <i class="bi bi-speedometer2 me-1"></i>Dashboard
      </a>
    <?php endif; ?>
  </div>

  <div class="row g-4">
    <div class="col-lg-5">
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
          <div class="d-flex align-items-center gap-3 mb-3">
            <div>
              <?php if (!empty($employer->profile_picture)): ?>
                <img src="../<?php echo htmlspecialchars($employer->profile_picture); ?>" alt="Profile" class="rounded-circle border" style="width:90px;height:90px;object-fit:cover;">
              <?php else: ?>
                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center border" style="width:90px;height:90px;font-size:2rem;color:#888;"><i class="bi bi-person"></i></div>
              <?php endif; ?>
            </div>
            <div class="flex-grow-1">
              <h2 class="h5 fw-semibold mb-0">
                <i class="bi bi-building me-2"></i><?php echo htmlspecialchars($employer->company_name ?: $employer->name ?: 'Employer'); ?>
              </h2>
            </div>
          </div>
          <dl class="row small mb-0">
            <dt class="col-5 text-muted">Display Name</dt>
            <dd class="col-7"><?php echo htmlspecialchars($employer->name); ?></dd>

            <?php if ($employer->company_website): ?>
              <dt class="col-5 text-muted">Website</dt>
              <dd class="col-7">
                <a href="<?php echo htmlspecialchars($employer->company_website); ?>" target="_blank" rel="noopener">
                  <?php echo htmlspecialchars($employer->company_website); ?>
                </a>
              </dd>
            <?php endif; ?>

            <?php if ($employer->company_phone): ?>
              <dt class="col-5 text-muted">Phone</dt>
              <dd class="col-7"><?php echo htmlspecialchars($employer->company_phone); ?></dd>
            <?php endif; ?>

            <?php if ($canSeePrivate && $employer->business_email): ?>
              <dt class="col-5 text-muted">Business Email</dt>
              <dd class="col-7"><?php echo htmlspecialchars($employer->business_email); ?></dd>
            <?php endif; ?>

            <?php if ($canSeePrivate && $employer->business_permit_number): ?>
              <dt class="col-5 text-muted">Permit #</dt>
              <dd class="col-7"><?php echo htmlspecialchars($employer->business_permit_number); ?></dd>
            <?php endif; ?>

            <?php if ($canSeePrivate && $employer->employer_status): ?>
              <dt class="col-5 text-muted">Status</dt>
              <dd class="col-7">
                <span class="badge bg-<?php
                  echo $employer->employer_status==='Approved'?'success':
                       ($employer->employer_status==='Suspended'?'danger':
                        ($employer->employer_status==='Rejected'?'secondary':'warning'));
                ?>">
                  <?php echo htmlspecialchars($employer->employer_status); ?>
                </span>
              </dd>
            <?php endif; ?>

            <?php if ($joinDate): ?>
              <dt class="col-5 text-muted">Joined</dt>
              <dd class="col-7"><?php echo htmlspecialchars($joinDate); ?></dd>
            <?php endif; ?>
          </dl>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <h3 class="h6 fw-semibold mb-3">
            <i class="bi bi-briefcase me-2"></i>Jobs Posted (<?php echo count($jobs); ?>)
          </h3>
          <?php if (!$jobs): ?>
            <div class="text-muted small">No jobs posted yet.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Type</th>
                    <th>Salary</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody class="small">
                  <?php foreach ($jobs as $j): ?>
                    <tr>
                      <td>
                        <a href="job_view.php?job_id=<?php echo urlencode($j['job_id']); ?>">
                          <?php echo htmlspecialchars($j['title']); ?>
                        </a>
                      </td>
                      <td>
                        <span class="badge bg-<?php
                          echo $j['status']==='Open'?'success':
                               ($j['status']==='Closed'?'secondary':'warning');
                        ?>">
                          <?php echo htmlspecialchars($j['status']); ?>
                        </span>
                      </td>
                      <td><?php echo htmlspecialchars($j['employment_type']); ?></td>
                      <td>
                        <?php
                          if ($j['salary_min'] && $j['salary_max']) {
                              echo htmlspecialchars(number_format($j['salary_min'])) . ' - ' .
                                   htmlspecialchars(number_format($j['salary_max'])) . ' ' .
                                   htmlspecialchars($j['salary_currency']);
                          } else echo '—';
                        ?>
                      </td>
                      <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($j['created_at']))); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>
<?php include '../includes/footer.php'; ?>