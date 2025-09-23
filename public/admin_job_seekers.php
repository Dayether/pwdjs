<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';
require_once '../classes/Sensitive.php';
require_once '../classes/Mail.php';

Helpers::requireRole('admin');

// Remember this page for Back from the detail view
Helpers::storeLastPage();

// Filters
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = trim($_GET['q'] ?? '');

// Actions are handled in the detail view page now.

$pdo = Database::getConnection();
$where = "role='job_seeker'";
$params = [];
if ($statusFilter !== '') {
    $where .= " AND pwd_id_status=?";
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql = "SELECT user_id,name,email,pwd_id_last4,pwd_id_status,job_seeker_status,created_at FROM users WHERE $where ORDER BY (pwd_id_status='Pending') DESC, created_at DESC LIMIT 300";
try {
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  // Fallback if migration not yet applied (no job_seeker_status column)
  $fallbackSql = str_replace(',job_seeker_status','', $sql);
  $stmt = $pdo->prepare($fallbackSql);
  $stmt->execute($params);
  $rowsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  // Inject default Active status so page still works
  $rows = [];
  foreach ($rowsRaw as $r) {
    if (!isset($r['job_seeker_status'])) $r['job_seeker_status'] = 'Active';
    $rows[] = $r;
  }
  $_SESSION['flash']['error'] = 'Note: job_seeker_status column missing. Run migration 20250922_add_job_seeker_status.sql to enable suspension feature.';
}

$counts = User::jobSeekerCounts();

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h2 class="h5 fw-semibold mb-0"><i class="bi bi-people me-2"></i>Admin · Job Seekers Verification</h2>
</div>

<?php if (!empty($_SESSION['flash']['msg'])): ?>
  <div class="alert alert-success alert-dismissible fade show auto-dismiss">
    <?php echo htmlspecialchars($_SESSION['flash']['msg']); unset($_SESSION['flash']['msg']); ?>
    <button class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash']['error'])): ?>
  <div class="alert alert-danger alert-dismissible fade show auto-dismiss">
    <?php echo htmlspecialchars($_SESSION['flash']['error']); unset($_SESSION['flash']['error']); ?>
    <button class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-md-8">
    <form class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach(['Pending','Verified','Rejected','None'] as $st): ?>
            <option value="<?php echo $st; ?>" <?php if($statusFilter===$st) echo 'selected'; ?>><?php echo $st; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label small mb-1">Search</label>
        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="form-control form-control-sm" placeholder="Name or email">
      </div>
      <div class="col-md-4 d-grid">
        <button class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
      </div>
    </form>
  </div>
  <div class="col-md-4">
    <div class="border rounded p-2 small bg-body-tertiary h-100">
      <div><span class="badge text-bg-primary">Total</span> <?php echo (int)$counts['total']; ?></div>
      <div><span class="badge text-bg-secondary">None</span> <?php echo (int)$counts['None']; ?></div>
      <div><span class="badge text-bg-warning">Pending</span> <?php echo (int)$counts['Pending']; ?></div>
      <div><span class="badge text-bg-success">Verified</span> <?php echo (int)$counts['Verified']; ?></div>
      <div><span class="badge text-bg-danger">Rejected</span> <?php echo (int)$counts['Rejected']; ?></div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr class="small text-uppercase text-muted">
            <th>Name</th>
            <th>Email</th>
            <th class="text-center">PWD ID (Last4)</th>
            <th class="text-center">PWD ID Status</th>
            <th class="text-center">Account</th>
          
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="5" class="text-center small text-muted py-4">No job seekers found.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <?php
              $st = $r['pwd_id_status'] ?: 'None';
              $acct = $r['job_seeker_status'] ?: 'Active';
              $badgeClass = match($st){
                'Verified' => 'text-bg-success',
                'Pending'  => 'text-bg-warning',
                'Rejected' => 'text-bg-danger',
                default    => 'text-bg-secondary'
              };
              $acctBadge = $acct === 'Suspended' ? 'text-bg-danger' : 'text-bg-success';
            ?>
            <tr>
              <td class="small fw-semibold"><?php echo Helpers::sanitizeOutput($r['name']); ?></td>
              <td class="small text-muted"><?php echo Helpers::sanitizeOutput($r['email']); ?></td>
              <td class="small text-center"><?php echo $r['pwd_id_last4'] ? '****'.$r['pwd_id_last4'] : '—'; ?></td>
              <td class="small text-center"><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($st); ?></span></td>
              <td class="small text-end">
                <form method="post" action="admin_job_seeker_view" class="d-inline">
                  <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($r['user_id']); ?>">
                  <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-box-arrow-up-right me-1"></i>View
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
