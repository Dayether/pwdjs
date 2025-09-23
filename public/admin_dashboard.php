<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';

Helpers::requireRole('admin');

$pdo = Database::getConnection();
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalEmployers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='employer'")->fetchColumn();
$totalSeekers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='job_seeker'")->fetchColumn();
$totalAdmins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$totalJobs = (int)$pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
$pendingEmployers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='employer' AND COALESCE(employer_status,'Pending')='Pending'")->fetchColumn();
$pendingPwd = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='job_seeker' AND COALESCE(pwd_id_status,'None')='Pending'")->fetchColumn();
$todayJobs = 0;
try {
  $stmt = $pdo->query("SELECT COUNT(*) FROM jobs WHERE DATE(created_at)=CURDATE()");
  $todayJobs = (int)$stmt->fetchColumn();
} catch (Throwable $e) { $todayJobs = 0; }

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h2 class="h5 fw-semibold mb-0"><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</h2>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body py-3">
        <div class="text-muted small">Total Users</div>
        <div class="fw-bold fs-4"><?php echo $totalUsers; ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body py-3">
        <div class="text-muted small">Job Seekers</div>
        <div class="fw-bold fs-4 text-primary"><?php echo $totalSeekers; ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body py-3">
        <div class="text-muted small">Employers</div>
        <div class="fw-bold fs-4 text-success"><?php echo $totalEmployers; ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body py-3">
        <div class="text-muted small">Admins</div>
        <div class="fw-bold fs-4 text-secondary"><?php echo $totalAdmins; ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-sm-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body py-3">
        <div class="text-muted small">Total Jobs</div>
        <div class="fw-bold fs-4"><?php echo $totalJobs; ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body py-3">
        <div class="text-muted small">Jobs Today</div>
        <div class="fw-bold fs-4 text-info"><?php echo $todayJobs; ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body py-3">
        <div class="text-muted small">Pending Employers</div>
        <div class="fw-bold fs-4 text-warning"><?php echo $pendingEmployers; ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body py-3">
        <div class="text-muted small">Pending PWD Verifications</div>
        <div class="fw-bold fs-4 text-danger"><?php echo $pendingPwd; ?></div>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
