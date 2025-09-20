<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';

Helpers::requireLogin();
if (($_SESSION['role'] ?? '') !== 'admin') Helpers::redirect('index.php');

/* ADDED: remember this page for back navigation */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
Helpers::storeLastPage();

/* ADDED: Capture incoming optional filters (add-only, original query remains) */
$filter_status = trim($_GET['status'] ?? '');
$filter_q      = trim($_GET['q'] ?? '');

/* ADDED: Build WHERE fragments (non-destructive) */
$whereParts = ["role='employer'"];
$params = [];
if ($filter_status !== '') {
    // Accept case-insensitive
    $whereParts[] = "LOWER(COALESCE(employer_status,'Pending')) = LOWER(?)";
    $params[] = $filter_status;
}
if ($filter_q !== '') {
    $whereParts[] = "(company_name LIKE ? OR name LIKE ? OR email LIKE ? OR business_permit_number LIKE ?)";
    $like = "%$filter_q%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}
$whereSql = implode(' AND ', $whereParts);

$pdo = Database::getConnection();

/* ORIGINAL QUERY (kept EXACT) */
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
$rows = $stmt->fetchAll();

/* ADDED: Filtered query (runs in addition; DOES NOT replace original $rows; we keep both) */
$filteredRows = [];
if ($filter_status !== '' || $filter_q !== '') {
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

/* ADDED: Decide which dataset to show: if filters applied, show filtered, else original */
$displayRows = ($filter_status !== '' || $filter_q !== '') ? $filteredRows : $rows;

/* ADDED: Fallback diagnostic if displayRows is empty */
$diagnostic = [];
if (!$displayRows) {
    // Count how many employers total
    $diagStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='employer'");
    $diagnostic['total_employers'] = (int)$diagStmt->fetchColumn();

    // Status distribution
    $diagDist = $pdo->query("
        SELECT COALESCE(NULLIF(TRIM(employer_status),''),'(NULL or Empty)') AS stat, COUNT(*) c
        FROM users
        WHERE role='employer'
        GROUP BY stat
        ORDER BY c DESC
    ")->fetchAll();

    $diagnostic['status_distribution'] = $diagDist;

    // If there are employers, but filters hide them, we note it.
    if ($diagnostic['total_employers'] > 0 && ($filter_status !== '' || $filter_q !== '')) {
        $diagnostic['note'] = 'Employers exist but current filters returned 0 rows.';
    } elseif ($diagnostic['total_employers'] === 0) {
        $diagnostic['note'] = 'No employer records found in database.';
    } else {
        $diagnostic['note'] = 'Unexpected: query returned 0 without filters. Possible DB permission/cache issue.';
    }
}

/* ADDED: Aggregate counts (for summary header) */
$counts = [
    'total'     => 0,
    'Approved'  => 0,
    'Pending'   => 0,
    'Suspended' => 0,
    'Rejected'  => 0,
    'Other'     => 0
];
foreach ($rows as $__r) {
    $st = $__r['employer_status'] ?: 'Pending';
    $counts['total']++;
    if (isset($counts[$st])) $counts[$st]++; else $counts['Other']++;
}

/* ADDED: Get flashes (non-destructive addition) */
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
<!-- ADDED: Flash renderer -->
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

<!-- ADDED: Employers summary -->
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

<!-- ADDED: Filter form (non-destructive) -->
<form class="card border-0 shadow-sm mb-3" method="get">
  <div class="card-body py-3 px-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
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
      <div class="col-md-5">
        <label class="form-label small mb-1">Search (Company / Owner / Email / Permit)</label>
        <input name="q" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_q); ?>">
      </div>
      <div class="col-md-4 d-flex gap-2">
        <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Apply</button>
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
            <th>Website</th>
            <th>Phone</th>
            <th>Permit No.</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <!-- ORIGINAL LOOP (kept) -->
          <?php foreach ($rows as $r): ?>
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
              <td>
                <?php if (!empty($r['company_website'])): ?>
                  <a href="<?php echo htmlspecialchars($r['company_website']); ?>" target="_blank" rel="noopener">Visit</a>
                <?php else: ?>
                  (none)
                <?php endif; ?>
              </td>
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
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No employers found.</td></tr>
          <?php endif; ?>
          <!-- ADDED: If filters applied, show filtered dataset separately (to avoid removing original block) -->
          <?php if (($filter_status !== '' || $filter_q !== '') && $filteredRows): ?>
            <!-- ADDED NOTE: Filtered Results (duplicate view but via filters) -->
            <tr class="table-secondary">
              <td colspan="7" class="small fw-semibold">Filtered results (below) — original unfiltered list shown above.</td>
            </tr>
            <?php foreach ($filteredRows as $fr): ?>
              <tr>
                <td>
                  <div class="fw-semibold">
                    <?php echo Helpers::sanitizeOutput($fr['company_name'] ?: '(none)'); ?>
                  </div>
                  <div class="small text-muted">
                    <?php echo Helpers::sanitizeOutput($fr['name']); ?> · <?php echo Helpers::sanitizeOutput($fr['email']); ?>
                  </div>
                  <?php if (!empty($fr['employer_doc'])): ?>
                    <div class="small">
                      <a target="_blank" href="../<?php echo htmlspecialchars($fr['employer_doc']); ?>">View document</a>
                    </div>
                  <?php endif; ?>
                </td>
                <td><?php echo Helpers::sanitizeOutput($fr['business_email'] ?: '(none)'); ?></td>
                <td>
                  <?php if (!empty($fr['company_website'])): ?>
                    <a href="<?php echo htmlspecialchars($fr['company_website']); ?>" target="_blank" rel="noopener">Visit</a>
                  <?php else: ?>
                    (none)
                  <?php endif; ?>
                </td>
                <td><?php echo Helpers::sanitizeOutput($fr['company_phone'] ?: '(none)'); ?></td>
                <td><?php echo Helpers::sanitizeOutput($fr['business_permit_number'] ?: '(none)'); ?></td>
                <td>
                  <span class="badge <?php
                    echo $fr['employer_status']==='Approved'?'text-bg-success':(
                          $fr['employer_status']==='Pending'?'text-bg-warning':(
                          $fr['employer_status']==='Suspended'?'text-bg-danger':(
                          $fr['employer_status']==='Rejected'?'text-bg-secondary':'text-bg-secondary')));
                  ?>">
                    <?php echo Helpers::sanitizeOutput($fr['employer_status'] ?: 'Pending'); ?>
                  </span>
                </td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="admin_employer_view.php?user_id=<?php echo urlencode($fr['user_id']); ?>">
                    <i class="bi bi-eye me-1"></i>View
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$filteredRows): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">No filtered results.</td></tr>
            <?php endif; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ADDED: Diagnostic debug card -->
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
<!-- ADDED: JS helpers -->
<script>
document.querySelectorAll('.alert.auto-dismiss').forEach(el=>{
  setTimeout(()=>{ try{ bootstrap.Alert.getOrCreateInstance(el).close(); }catch(e){} },4000);
});

// Highlight if table unexpectedly empty while counts > 0
(function(){
  const total = <?php echo (int)$counts['total']; ?>;
  const table = document.getElementById('employersTable');
  if (table && total > 0) {
    const dataRows = table.querySelectorAll('tbody tr');
    if (dataRows.length === 1 && /No employers found/i.test(dataRows[0].textContent)) {
      dataRows[0].classList.add('table-danger');
      dataRows[0].title = 'Diagnostic: counts say there are employers, but listing is empty.';
    }
  }
})();
</script>