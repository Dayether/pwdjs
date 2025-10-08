<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';

Helpers::requireRole('admin');

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
?>
<div class="admin-layout">
  <?php include '../includes/admin_sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-page-header mb-3">
      <div class="page-title-block">
        <h1 class="page-title"><i class="bi bi-buildings"></i><span>Employers</span></h1>
        <p class="page-sub">Manage, approve and monitor employer organizations.</p>
      </div>
      <div class="page-actions">
        <a href="admin_employer_create.php" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-building-add me-1"></i>Create Employer</a>
      </div>
    </div>
<style>
  /* Employers admin enhancements */
  .admin-page-header{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:1.25rem;padding:0 .25rem .2rem;border-bottom:1px solid rgba(255,255,255,.07);}
  .admin-page-header .page-title{margin:0;font-size:1.35rem;font-weight:600;display:flex;align-items:center;gap:.65rem;color:#f0f6ff;letter-spacing:.5px;}
  .admin-page-header .page-title i{font-size:1.55rem;line-height:1;color:#6cb2ff;filter:drop-shadow(0 2px 4px rgba(0,0,0,.4));}
  .admin-page-header .page-sub{margin:.15rem 0 0;font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;font-weight:600;color:#6e829b;}
  .admin-page-header .page-actions{display:flex;align-items:center;gap:.6rem;margin-left:auto;}
  .admin-page-header .btn.btn-primary{background:linear-gradient(135deg,#2d6bff,#5146ff);border:1px solid #2d6bff;padding:.5rem .9rem;font-weight:600;font-size:.72rem;letter-spacing:.4px;}
  .admin-page-header .btn.btn-primary i{margin-right:.35rem;}
  @media (max-width:640px){
    .admin-page-header{align-items:flex-start;}
    .admin-page-header .page-actions{width:100%;justify-content:flex-start;}
  }
  .employer-topbar{display:flex;flex-direction:column;gap:.85rem;margin-bottom:1.1rem}
  .chips{display:flex;flex-wrap:wrap;gap:.5rem}
  .chip{--bg:#162335;--bd:rgba(255,255,255,.08);position:relative;display:inline-flex;align-items:center;gap:.4rem;font-size:.65rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;padding:.55rem .7rem;border:1px solid var(--bd);background:var(--bg);color:#c2d2e6;border-radius:8px;line-height:1;cursor:default;}
  .chip[data-filter]{cursor:pointer;transition:.25s}
  .chip[data-filter]:hover{background:#1d3556;color:#fff;border-color:#2f5fa8}
  .chip.active{background:linear-gradient(135deg,#224a88,#18355f);color:#fff;border-color:#4d7ed4;box-shadow:0 4px 12px -6px rgba(0,0,0,.6)}
  .chip .count{background:rgba(255,255,255,.08);padding:.2rem .45rem;border-radius:5px;font-weight:600;font-size:.65rem;}
  .filter-bar{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center}
  .filter-bar .search-box{flex:1 1 220px;position:relative}
  .filter-bar .search-box input{background:#101b2b;border:1px solid #233246;color:#dbe6f5;padding:.55rem .75rem .55rem 2rem;font-size:.8rem;border-radius:8px;width:100%;}
  .filter-bar .search-box i{position:absolute;left:.6rem;top:50%;transform:translateY(-50%);color:#6a7b92;font-size:.9rem}
  .filter-bar select{background:#101b2b;border:1px solid #233246;color:#dbe6f5;font-size:.75rem;padding:.55rem .65rem;border-radius:8px}
  .filter-bar button.reset-btn{background:#182739;border:1px solid #23384f;color:#9fb4cc;font-size:.72rem;padding:.55rem .75rem;border-radius:8px;font-weight:600;letter-spacing:.5px}
  .filter-bar button.reset-btn:hover{background:#213249;color:#fff}
  .table-wrapper{position:relative;border:1px solid rgba(255,255,255,.06);background:#0f1827;border-radius:16px;overflow:hidden;box-shadow:0 6px 22px -12px rgba(0,0,0,.65)}
  table.employers-table{margin:0;border-collapse:separate;border-spacing:0;width:100%;}
  table.employers-table thead th{background:#142134;color:#ced8e6;font-size:.65rem;font-weight:600;letter-spacing:.09em;text-transform:uppercase;padding:.75rem .9rem;border-bottom:1px solid #1f2e45;position:sticky;top:0;z-index:2}
  table.employers-table tbody td{padding:.9rem .9rem;vertical-align:top;font-size:.78rem;color:#d2dbe7;border-bottom:1px solid #132031;}
  table.employers-table tbody tr:last-child td{border-bottom:none}
  table.employers-table tbody tr{transition:.2s;background:linear-gradient(90deg,rgba(255,255,255,0) 0%,rgba(255,255,255,.015) 100%)}
  table.employers-table tbody tr:hover{background:rgba(255,255,255,.04)}
  .status-badge{display:inline-flex;align-items:center;gap:.35rem;font-size:.62rem;font-weight:600;letter-spacing:.05em;padding:.4rem .55rem;border-radius:6px;text-transform:uppercase;}
  .st-Approved{background:linear-gradient(135deg,#1f7a46,#0f3d24);color:#d8ffe9;border:1px solid #1c5b36}
  .st-Pending{background:linear-gradient(135deg,#8a660c,#3f2f07);color:#fff1c7;border:1px solid #5f460b}
  .st-Suspended{background:linear-gradient(135deg,#8a1d1d,#470e0e);color:#ffe1e1;border:1px solid #611414}
  .st-Rejected{background:linear-gradient(135deg,#515b67,#2a3037);color:#e3e9ef;border:1px solid #3b444d}
  .view-btn{background:#132840;border:1px solid #1f3a57;color:#c4d8ef;font-size:.65rem;font-weight:600;padding:.45rem .7rem;border-radius:7px;display:inline-flex;align-items:center;gap:.35rem;text-decoration:none;transition:.25s}
  .view-btn:hover{background:#1c3a57;color:#fff;border-color:#2f5a87}
  .company-cell .main{font-weight:600;color:#fff;font-size:.8rem;margin-bottom:.15rem}
  .company-cell .sub{font-size:.65rem;color:#8fa3ba;line-height:1.2}
  .company-cell a{font-size:.62rem}
  .empty-state{padding:2.5rem 1rem;text-align:center;color:#7f8fa1;font-size:.85rem}
  .fade-in{animation:fadeIn .5s ease both}
  @keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
  /* Responsive card fallback */
  @media (max-width:880px){
    .table-wrapper{border:none;background:transparent;box-shadow:none}
    table.employers-table,table.employers-table thead,table.employers-table tbody,table.employers-table th,table.employers-table td,table.employers-table tr{display:block}
    table.employers-table thead{display:none}
    table.employers-table tbody tr{background:#132133;border:1px solid #1f3147;border-radius:12px;margin-bottom:.85rem;padding:.75rem .9rem}
    table.employers-table tbody td{border:none;padding:.35rem 0;font-size:.75rem}
    table.employers-table tbody td.actions{margin-top:.4rem}
  }
  /* Slightly widen table area & spacing overrides */
  .table-wrapper{margin-left:-.5rem;margin-right:-.5rem;}
  @media (min-width:1200px){.table-wrapper{margin-left:-1rem;margin-right:-1rem;}}
  table.employers-table thead th{padding:.95rem 1.05rem;}
  table.employers-table tbody td{padding:1rem 1.05rem;}
  .company-cell .main{font-size:.85rem;}
</style>
<?php if ($__flashes): ?>
  <div class="mb-3 fade-in">
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
      <div class="alert alert-<?php echo $t; ?> alert-dismissible fade show auto-dismiss small py-2" role="alert" style="border-left:4px solid rgba(255,255,255,.3)">
        <i class="bi bi-<?php echo $icon; ?> me-2"></i><?php echo htmlspecialchars($m); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="employer-topbar fade-in">
  <div class="chips" id="statusChips">
    <div class="chip active" data-filter="">Total <span class="count"><?php echo $counts['total']; ?></span></div>
    <div class="chip" data-filter="Approved">Approved <span class="count"><?php echo $counts['Approved']; ?></span></div>
    <div class="chip" data-filter="Pending">Pending <span class="count"><?php echo $counts['Pending']; ?></span></div>
    <div class="chip" data-filter="Suspended">Suspended <span class="count"><?php echo $counts['Suspended']; ?></span></div>
    <div class="chip" data-filter="Rejected">Rejected <span class="count"><?php echo $counts['Rejected']; ?></span></div>
    <?php if ($counts['Other']): ?>
      <div class="chip" data-filter="Other">Other <span class="count"><?php echo $counts['Other']; ?></span></div>
    <?php endif; ?>
  </div>
  <div class="filter-bar">
    <div class="search-box"><i class="bi bi-search"></i><input type="text" id="searchInput" placeholder="Search company / email..."></div>
    <select id="statusSelect" aria-label="Status select">
      <option value="">All Statuses</option>
      <option value="Pending">Pending</option>
      <option value="Approved">Approved</option>
      <option value="Suspended">Suspended</option>
      <option value="Rejected">Rejected</option>
    </select>
    <button type="button" class="reset-btn" id="resetFilters"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
  </div>
</div>

<div class="table-wrapper fade-in" id="tableWrapper">
  <table class="employers-table" id="employersTable">
    <thead>
      <tr>
        <th data-sort="company">Company</th>
        <th data-sort="business_email">Business Email</th>
        <th data-sort="phone">Phone</th>
        <th data-sort="permit">Permit No.</th>
        <th data-sort="status">Status</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($displayRows as $r): ?>
      <tr data-status="<?php echo htmlspecialchars($r['employer_status'] ?: 'Pending'); ?>">
        <td class="company-cell">
          <div class="main"><?php echo Helpers::sanitizeOutput($r['company_name'] ?: '(none)'); ?></div>
          <div class="sub"><?php echo Helpers::sanitizeOutput($r['name']); ?> · <?php echo Helpers::sanitizeOutput($r['email']); ?></div>
          <?php if (!empty($r['employer_doc'])): ?>
            <div class="sub"><a target="_blank" href="../<?php echo htmlspecialchars($r['employer_doc']); ?>">View document</a></div>
          <?php endif; ?>
        </td>
        <td><?php echo Helpers::sanitizeOutput($r['business_email'] ?: '(none)'); ?></td>
        <td><?php echo Helpers::sanitizeOutput($r['company_phone'] ?: '(none)'); ?></td>
        <td><?php echo Helpers::sanitizeOutput($r['business_permit_number'] ?: '(none)'); ?></td>
        <td>
          <?php $st = $r['employer_status'] ?: 'Pending'; ?>
          <span class="status-badge st-<?php echo htmlspecialchars($st); ?>">
            <i class="bi bi-circle-fill" style="font-size:.5rem"></i><?php echo Helpers::sanitizeOutput($st); ?>
          </span>
        </td>
        <td class="actions" style="text-align:right">
          <form method="post" action="admin_employer_view" class="d-inline">
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($r['user_id']); ?>">
            <button type="submit" class="view-btn"><i class="bi bi-eye"></i>View</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$displayRows): ?>
      <tr><td colspan="6" class="empty-state"><?php echo $hasStatusFilter ? 'No employers match that status.' : 'No employers found.'; ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
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
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
// Auto-dismiss alerts
document.querySelectorAll('.alert.auto-dismiss').forEach(el=>{setTimeout(()=>{try{bootstrap.Alert.getOrCreateInstance(el).close();}catch(e){}},4000);});

const searchInput=document.getElementById('searchInput');
const statusSelect=document.getElementById('statusSelect');
const chips=document.getElementById('statusChips');
const resetBtn=document.getElementById('resetFilters');
const rows=[...document.querySelectorAll('#employersTable tbody tr')];

function applyFilters(){
  const q=(searchInput.value||'').toLowerCase().trim();
  const st=statusSelect.value;
  let visibleCount=0;
  rows.forEach(r=>{
    const status=r.getAttribute('data-status')||'';
    const text=r.innerText.toLowerCase();
    const statusMatch=!st || status===st;
    const searchMatch=!q || text.includes(q);
    const show=statusMatch && searchMatch;
    r.style.display=show?'':'none';
    if(show) visibleCount++;
  });
  document.querySelector('.chip.active .count')?.classList.add('pulse');
}

searchInput?.addEventListener('input',()=>{applyFilters();});
statusSelect?.addEventListener('change',()=>{applyFilters();syncChipFromSelect();});
resetBtn?.addEventListener('click',()=>{searchInput.value='';statusSelect.value='';[...chips.querySelectorAll('.chip')].forEach(c=>c.classList.remove('active'));chips.querySelector('[data-filter=""]')?.classList.add('active');applyFilters();});

chips?.addEventListener('click',e=>{
  const c=e.target.closest('.chip[data-filter]');
  if(!c) return;
  [...chips.querySelectorAll('.chip')].forEach(x=>x.classList.remove('active'));
  c.classList.add('active');
  const val=c.getAttribute('data-filter');
  statusSelect.value=val||'';
  applyFilters();
});

function syncChipFromSelect(){
  const val=statusSelect.value||'';
  [...chips.querySelectorAll('.chip')].forEach(ch=>{ch.classList.toggle('active',(ch.getAttribute('data-filter')||'')===val);});
}

// Sortable columns (basic text sort)
document.querySelectorAll('#employersTable thead th[data-sort]').forEach(th=>{
  th.style.cursor='pointer';
  th.addEventListener('click',()=>{
    const key=th.getAttribute('data-sort');
    const tbody=th.closest('table').querySelector('tbody');
    const currentDir=th.getAttribute('data-dir')==='asc'?'desc':'asc';
    th.setAttribute('data-dir',currentDir);
    const factor=currentDir==='asc'?1:-1;
    const arr=[...tbody.querySelectorAll('tr')];
    arr.sort((a,b)=>{
      const ta=a.innerText.toLowerCase();
      const tb=b.innerText.toLowerCase();
      if(key==='status'){return (a.getAttribute('data-status')||'').localeCompare(b.getAttribute('data-status')||'')*factor;}
      if(key==='company'){return (a.querySelector('.company-cell .main')?.innerText.toLowerCase()||'').localeCompare(b.querySelector('.company-cell .main')?.innerText.toLowerCase()||'')*factor;}
      if(key==='business_email'){return (a.children[1]?.innerText.toLowerCase()||'').localeCompare(b.children[1]?.innerText.toLowerCase()||'')*factor;}
      if(key==='phone'){return (a.children[2]?.innerText.toLowerCase()||'').localeCompare(b.children[2]?.innerText.toLowerCase()||'')*factor;}
      if(key==='permit'){return (a.children[3]?.innerText.toLowerCase()||'').localeCompare(b.children[3]?.innerText.toLowerCase()||'')*factor;}
      return ta.localeCompare(tb)*factor;
    });
    arr.forEach(tr=>tbody.appendChild(tr));
  });
});

applyFilters();
</script>