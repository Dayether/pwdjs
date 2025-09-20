<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';

Helpers::requireLogin();
if (($_SESSION['role'] ?? '') !== 'admin') Helpers::redirect('index.php');

if (session_status() === PHP_SESSION_NONE) { session_start(); }
Helpers::storeLastPage();

/* Single filter: status */
$filter_status = trim($_GET['status'] ?? '');
$hasStatusFilter = ($filter_status !== '');

/* Build WHERE only if status chosen */
$whereParts = ["role='employer'"];
$params = [];
if ($hasStatusFilter) {
    $whereParts[] = "LOWER(COALESCE(employer_status,'Pending')) = LOWER(?)";
    $params[] = $filter_status;
}
$whereSql = implode(' AND ', $whereParts);

$pdo = Database::getConnection();

/* Base full list (for counts + default view) */
$stmt = $pdo->query("
  SELECT
    user_id,
    name,
    email,
    company_name,
    business_email,
    company_website,
    company_phone,
    business_permit_number,
    employer_status,
    employer_doc,
    created_at
  FROM users
  WHERE role='employer'
  ORDER BY employer_status='Pending' DESC, created_at DESC
");
$allRows = $stmt->fetchAll();

/* Filtered list (status only) */
$filteredRows = [];
if ($hasStatusFilter) {
    $sqlFiltered = "
      SELECT
        user_id,
        name,
        email,
        company_name,
        business_email,
        company_website,
        company_phone,
        business_permit_number,
        employer_status,
        employer_doc,
        created_at
      FROM users
      WHERE $whereSql
      ORDER BY employer_status='Pending' DESC, created_at DESC
    ";
    $stmtF = $pdo->prepare($sqlFiltered);
    $stmtF->execute($params);
    $filteredRows = $stmtF->fetchAll();
}

/* Decide dataset (only ONE table shown) */
$displayRows = $hasStatusFilter ? $filteredRows : $allRows;

/* Diagnostic (only when nothing to show) */
$diagnostic = [];
if (!$displayRows) {
    $diagStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='employer'");
    $diagnostic['total_employers'] = (int)$diagStmt->fetchColumn();
    $diagDist = $pdo->query("
        SELECT COALESCE(NULLIF(TRIM(employer_status),''),'(NULL or Empty)') AS stat, COUNT(*) c
        FROM users
        WHERE role='employer'
        GROUP BY stat
        ORDER BY c DESC
    ")->fetchAll();
    $diagnostic['status_distribution'] = $diagDist;

    if ($diagnostic['total_employers'] > 0 && $hasStatusFilter) {
        $diagnostic['note'] = 'Employers exist but the chosen status returned 0 rows.';
    } elseif ($diagnostic['total_employers'] === 0) {
        $diagnostic['note'] = 'No employer records found.';
    } else {
        $diagnostic['note'] = 'Unexpected empty result with no filters.';
    }
}

/* Counts (from full list) */
$counts = [
    'total'     => 0,
    'Approved'  => 0,
    'Pending'   => 0,
    'Suspended' => 0,
    'Rejected'  => 0,
    'Other'     => 0
];
foreach ($allRows as $__r) {
    $st = $__r['employer_status'] ?: 'Pending';
    $counts['total']++;
    if (isset($counts[$st])) $counts[$st]++; else $counts['Other']++;
}

/* Flashes */
$__rawFlashCopy = $_SESSION['flash'] ?? [];
$__flashes = method_exists('Helpers','getFlashes') ? Helpers::getFlashes() : [];
if (!$__flashes && $__rawFlashCopy) {
    foreach ($__rawFlashCopy as $k=>$v) {
        $t = in_array($k,['error','danger'])?'danger':($k==='success'?'success':'info');
        $__flashes[] = ['type'=>$t,'message'=>$v];
    }
}

include '../includes/header.php';
include '../includes/nav.php';
?>
<?php if ($__flashes): ?>
  <div class="mb-3">
    <?php foreach ($__flashes as $f):
      $t = htmlspecialchars($f['type'] ?? 'info');
      $m = trim((string)($f['message'] ?? ''));
      if ($m==='') $m='Action completed.';
      $icon = match($t){
        'success'=>'check-circle',
        'danger'=>'exclamation-triangle',
        'warning'=>'exclamation-circle',
        default=>'info-circle'
      };
    ?>
      <div class="alert alert-<?php echo $t; ?> alert-dismissible fade show auto-dismiss" role="alert">
        <i class="bi bi-<?php echo $icon; ?> me-2"></i><?php echo htmlspecialchars($m); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2 px-3 small">
    <div class="d-flex flex-wrap gap-3 align-items-center">
      <div><strong>Total:</strong> <?php echo $counts['total']; ?></div>
      <div><span class="badge text-bg-success">Approved</span> <?php echo $counts['Approved']; ?></div>
      <div><span class="badge text-bg-warning">Pending</span> <?php echo $counts['Pending']; ?></div>
      <div><span class="badge text-bg-danger">Suspended</span> <?php echo $counts['Suspended']; ?></div>
      <div><span class="badge text-bg-secondary">Rejected</span> <?php echo $counts['Rejected']; ?></div>
      <?php if ($counts['Other']): ?>
        <div><span class="badge text-bg-info">Other</span> <?php echo $counts['Other']; ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Simplified filter form (status only) -->
<form class="card border-0 shadow-sm mb-3" method="get">
  <div class="card-body py-3 px-3">
    <div class="row g-2 align-items-end">
      <div class="col-sm-4 col-md-3 col-lg-2">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">-- All --</option>
          <?php foreach (['Pending','Approved','Suspended','Rejected'] as $__stOpt): ?>
            <option value="<?php echo $__stOpt; ?>" <?php if (strcasecmp($__stOpt,$filter_status)===0) echo 'selected'; ?>>
              <?php echo $__stOpt; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-8 col-md-9 col-lg-10 d-flex gap-2">
        <button class="btn btn-primary btn-sm"><i class="bi bi-filter me-1"></i>Apply</button>
        <a href="admin_employers.php" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </div>
  </div>
</form>

<div class="card border-0 shadow-sm">
  <div class="card-body p-4">
    <h2 class="h5 fw-semibold mb-3"><i class="bi bi-shield-lock me-2"></i>Admin · Employers</h2>
    <div class="table-responsive">
      <table class="table align-middle" id="employersTable">
        <thead class="table-light">
          <tr>
            <th>Company</th>
            <th>Business Email</th>
            <th>Phone</th>
            <th>Permit No.</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($displayRows as $r): ?>
            <tr>
              <td>
                <div class="fw-semibold">
                  <?php echo Helpers::sanitizeOutput($r['company_name'] ?: '(none)'); ?>
                </div>
                <div class="small text-muted">
                  <?php echo Helpers::sanitizeOutput($r['name']); ?> · <?php echo Helpers::sanitizeOutput($r['email']); ?>
                </div>
                <?php if (!empty($r['employer_doc'])): ?>
                  <div class="small">
                    <a target="_blank" href="../<?php echo htmlspecialchars($r['employer_doc']); ?>">View document</a>
                  </div>
                <?php endif; ?>
              </td>
              <td><?php echo Helpers::sanitizeOutput($r['business_email'] ?: '(none)'); ?></td>
              <td><?php echo Helpers::sanitizeOutput($r['company_phone'] ?: '(none)'); ?></td>
              <td><?php echo Helpers::sanitizeOutput($r['business_permit_number'] ?: '(none)'); ?></td>
              <td>
                <span class="badge <?php
                  echo $r['employer_status']==='Approved'?'text-bg-success':(
                        $r['employer_status']==='Pending'?'text-bg-warning':(
                        $r['employer_status']==='Suspended'?'text-bg-danger':(
                        $r['employer_status']==='Rejected'?'text-bg-secondary':'text-bg-secondary')));
                ?>">
                  <?php echo Helpers::sanitizeOutput($r['employer_status'] ?: 'Pending'); ?>
                </span>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="admin_employer_view.php?user_id=<?php echo urlencode($r['user_id']); ?>">
                  <i class="bi bi-eye me-1"></i>View
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$displayRows): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">
              <?php echo $hasStatusFilter ? 'No employers match that status.' : 'No employers found.'; ?>
            </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
  <div class="card border-0 shadow-sm mt-3">
    <div class="card-body p-3">
      <h6 class="fw-semibold mb-2"><i class="bi bi-bug me-1"></i>Debug Info</h6>
      <?php if ($diagnostic): ?>
        <pre class="small mb-0"><?php echo htmlspecialchars(print_r($diagnostic, true)); ?></pre>
      <?php else: ?>
        <div class="small text-muted">No diagnostic triggered (rows available).</div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
<script>
document.querySelectorAll('.alert.auto-dismiss').forEach(el=>{
  setTimeout(()=>{ try{ bootstrap.Alert.getOrCreateInstance(el).close(); }catch(e){} },4000);
});
</script>