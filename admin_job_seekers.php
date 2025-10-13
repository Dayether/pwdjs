<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';
require_once 'classes/User.php';
require_once 'classes/Sensitive.php';
require_once 'classes/Mail.php';
require_once 'classes/Taxonomy.php';

Helpers::requireRole('admin');

// Remember this page for Back from the detail view
Helpers::storeLastPage();

// Filters
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = trim($_GET['q'] ?? '');
$workSetupFilter = trim($_GET['work_setup'] ?? ''); // On-site|Hybrid|Remote
$accTagFilter = trim($_GET['acc'] ?? ''); // one accessibility tag

// Actions are handled in the detail view page now.

$pdo = Database::getConnection();
$where = "u.role='job_seeker'";
$params = [];
if ($statusFilter !== '') {
  $where .= " AND u.pwd_id_status=?";
    $params[] = $statusFilter;
}
if ($search !== '') {
  $where .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($workSetupFilter !== '') {
  $where .= " AND u.preferred_work_setup = ?";
  $params[] = $workSetupFilter;
}
// Optional join for accessibility tag filtering
$join = '';
if ($accTagFilter !== '') {
  $join = "JOIN user_accessibility_prefs ap ON ap.user_id = u.user_id AND ap.tag = ?";
  $params[] = $accTagFilter;
}
$sql = "SELECT u.user_id,u.name,u.email,u.pwd_id_last4,u.pwd_id_status,u.job_seeker_status,u.preferred_work_setup,u.created_at
    FROM users u
    $join
    WHERE $where
    ORDER BY (u.pwd_id_status='Pending') DESC, u.created_at DESC
    LIMIT 300";
try {
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  // Fallback if migration not yet applied (no job_seeker_status column)
  $fallbackSql = str_replace(',u.job_seeker_status','', $sql);
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

include 'includes/header.php';
?>
<div class="admin-layout">
  <?php include 'includes/admin_sidebar.php'; ?>
  <div class="admin-main">
<style>
  .js-topbar{display:flex;flex-direction:column;gap:.85rem;margin-bottom:1.15rem}
  .js-chips{display:flex;flex-wrap:wrap;gap:.5rem}
  .js-chip{--bg:#162335;--bd:rgba(255,255,255,.08);display:inline-flex;align-items:center;gap:.45rem;font-size:.65rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;padding:.55rem .75rem;border:1px solid var(--bd);background:var(--bg);color:#c4d2e4;border-radius:9px;cursor:pointer;transition:.25s}
  .js-chip:hover{background:#203754;color:#fff}
  .js-chip.active{background:linear-gradient(135deg,#1f4d89,#163657);color:#fff;border-color:#3e74c4;box-shadow:0 4px 12px -6px rgba(0,0,0,.6)}
  .js-chip .count{background:rgba(255,255,255,.09);padding:.2rem .5rem;border-radius:6px;font-size:.62rem;font-weight:600}
  .js-filters{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center}
  .js-filters .search-box{flex:1 1 260px;position:relative}
  .js-filters .search-box input{background:#101b2b;border:1px solid #233246;color:#dbe6f5;padding:.6rem .75rem .6rem 2rem;font-size:.78rem;border-radius:9px;width:100%}
  .js-filters .search-box i{position:absolute;left:.65rem;top:50%;transform:translateY(-50%);color:#6c7c91;font-size:.9rem}
  .js-filters select{background:#101b2b;border:1px solid #233246;color:#dbe6f5;font-size:.72rem;padding:.55rem .65rem;border-radius:8px}
  .js-filters button.reset-btn{background:#182739;border:1px solid #23384f;color:#9fb4cc;font-size:.7rem;padding:.55rem .75rem;border-radius:8px;font-weight:600;letter-spacing:.5px}
  .js-filters button.reset-btn:hover{background:#213249;color:#fff}
  .seekers-wrapper{position:relative;border:1px solid rgba(255,255,255,.06);background:#0f1827;border-radius:16px;overflow:hidden;box-shadow:0 6px 22px -12px rgba(0,0,0,.65)}
  table.seekers-table{margin:0;border-collapse:separate;border-spacing:0;width:100%}
  table.seekers-table thead th{background:#142134;color:#ced8e6;font-size:.63rem;font-weight:600;letter-spacing:.09em;text-transform:uppercase;padding:.75rem .85rem;border-bottom:1px solid #1f2e45;position:sticky;top:0;z-index:2;cursor:pointer}
  table.seekers-table tbody td{padding:.85rem .9rem;vertical-align:middle;font-size:.74rem;color:#d2dbe7;border-bottom:1px solid #132031}
  table.seekers-table tbody tr:last-child td{border-bottom:none}
  table.seekers-table tbody tr{transition:.2s}
  table.seekers-table tbody tr:hover{background:rgba(255,255,255,.04)}
  .status-badge{display:inline-flex;align-items:center;gap:.35rem;font-size:.6rem;font-weight:600;letter-spacing:.05em;padding:.38rem .55rem;border-radius:6px;text-transform:uppercase}
  .pwd-Verified{background:linear-gradient(135deg,#1f7a46,#0f3d24);color:#d8ffe9;border:1px solid #1c5b36}
  .pwd-Pending{background:linear-gradient(135deg,#8a660c,#3f2f07);color:#fff1c7;border:1px solid #5f460b}
  .pwd-Rejected{background:linear-gradient(135deg,#8a1d1d,#470e0e);color:#ffe1e1;border:1px solid #611414}
  .pwd-None{background:linear-gradient(135deg,#515b67,#2a3037);color:#e3e9ef;border:1px solid #3b444d}
  .acct-Suspended{background:linear-gradient(135deg,#8a1d1d,#470e0e);color:#ffe1e1;border:1px solid #611414}
  .acct-Active{background:linear-gradient(135deg,#1f4d89,#163657);color:#d1e9ff;border:1px solid #1d436e}
  .view-btn{background:#132840;border:1px solid #1f3a57;color:#c4d8ef;font-size:.6rem;font-weight:600;padding:.45rem .65rem;border-radius:7px;display:inline-flex;align-items:center;gap:.35rem;transition:.25s}
  .view-btn:hover{background:#1c3a57;color:#fff;border-color:#2f5a87}
  .empty-state{padding:2.3rem 1rem;text-align:center;color:#7f8fa1;font-size:.8rem}
  .fade-in{animation:fadeIn .5s ease both}
  @keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
  @media (max-width:880px){
    .seekers-wrapper{border:none;background:transparent;box-shadow:none}
    table.seekers-table,table.seekers-table thead,table.seekers-table tbody,table.seekers-table th,table.seekers-table td,table.seekers-table tr{display:block}
    table.seekers-table thead{display:none}
    table.seekers-table tbody tr{background:#132133;border:1px solid #1f3147;border-radius:12px;margin-bottom:.85rem;padding:.75rem .9rem}
    table.seekers-table tbody td{border:none;padding:.35rem 0;font-size:.7rem}
    table.seekers-table tbody td.actions{margin-top:.4rem}
  }
  /* widen wrapper like employers */
  .seekers-wrapper{margin-left:-.5rem;margin-right:-.5rem}
  @media (min-width:1200px){.seekers-wrapper{margin-left:-1rem;margin-right:-1rem}}
</style>

<?php if (!empty($_SESSION['flash']['msg'])): ?>
  <div class="alert alert-success alert-dismissible fade show auto-dismiss small py-2 mb-3 fade-in">
    <?php echo htmlspecialchars($_SESSION['flash']['msg']); unset($_SESSION['flash']['msg']); ?>
    <button class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash']['error'])): ?>
  <div class="alert alert-danger alert-dismissible fade show auto-dismiss small py-2 mb-3 fade-in">
    <?php echo htmlspecialchars($_SESSION['flash']['error']); unset($_SESSION['flash']['error']); ?>
    <button class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="js-topbar fade-in">
  <div class="js-chips" id="jsStatusChips">
    <div class="js-chip active" data-filter="">Total <span class="count"><?php echo (int)$counts['total']; ?></span></div>
    <div class="js-chip" data-filter="None">None <span class="count"><?php echo (int)$counts['None']; ?></span></div>
    <div class="js-chip" data-filter="Pending">Pending <span class="count"><?php echo (int)$counts['Pending']; ?></span></div>
    <div class="js-chip" data-filter="Verified">Verified <span class="count"><?php echo (int)$counts['Verified']; ?></span></div>
    <div class="js-chip" data-filter="Rejected">Rejected <span class="count"><?php echo (int)$counts['Rejected']; ?></span></div>
  </div>
  <div class="js-filters">
    <div class="search-box"><i class="bi bi-search"></i><input type="text" id="jsSearch" placeholder="Search name or email..."></div>
    <select id="jsStatusSelect">
      <option value="">All Statuses</option>
      <option value="None">None</option>
      <option value="Pending">Pending</option>
      <option value="Verified">Verified</option>
      <option value="Rejected">Rejected</option>
    </select>
    <select id="jsWorkSetup">
      <?php $wsParam = htmlspecialchars($workSetupFilter); ?>
      <option value="" <?php echo $wsParam===''?'selected':''; ?>>Work Setup</option>
      <option value="On-site" <?php echo $wsParam==='On-site'?'selected':''; ?>>On-site</option>
      <option value="Hybrid" <?php echo $wsParam==='Hybrid'?'selected':''; ?>>Hybrid</option>
      <option value="Remote" <?php echo $wsParam==='Remote'?'selected':''; ?>>Remote</option>
    </select>
    <select id="jsAccTag">
      <?php $accParam = htmlspecialchars($accTagFilter); ?>
      <option value="" <?php echo $accParam===''?'selected':''; ?>>Accessibility</option>
      <?php if (class_exists('Taxonomy') && method_exists('Taxonomy','accessibilityTags')): foreach (Taxonomy::accessibilityTags() as $tag): ?>
        <option value="<?php echo htmlspecialchars($tag); ?>" <?php echo $accParam===$tag?'selected':''; ?>><?php echo htmlspecialchars($tag); ?></option>
      <?php endforeach; endif; ?>
    </select>
    <button type="button" class="reset-btn" id="jsReset"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
  </div>
</div>

<div class="seekers-wrapper fade-in" id="seekersWrapper">
  <table class="seekers-table" id="seekersTable">
    <thead>
      <tr>
        <th data-sort="name">Name</th>
        <th data-sort="email">Email</th>
        <th data-sort="pwd">PWD ID (Last4)</th>
        <th data-sort="pwd_status">PWD ID Status</th>
  <th data-sort="acct">Account</th>
  <th>Work Setup</th>
  <th>Accessibility</th>
  <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="empty-state">No job seekers found.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <?php
          $st = $r['pwd_id_status'] ?: 'None';
          $acct = $r['job_seeker_status'] ?: 'Active';
          $last4 = $r['pwd_id_last4'] ? '****'.$r['pwd_id_last4'] : '—';
          // Fetch filters data
          $ws = (string)($r['preferred_work_setup'] ?? '');
          $accList = User::listAccessibilityPrefs($r['user_id']);
          $accDataAttr = $accList ? htmlspecialchars(implode('|',$accList)) : '';
          $accDisp = $accList ? implode(', ', $accList) : '';
        ?>
        <tr data-status="<?php echo htmlspecialchars($st); ?>" data-acct="<?php echo htmlspecialchars($acct); ?>" data-ws="<?php echo htmlspecialchars($ws); ?>" data-acc="<?php echo $accDataAttr; ?>">
          <td class="fw-semibold" style="color:#fff;font-size:.78rem"><?php echo Helpers::sanitizeOutput($r['name']); ?></td>
          <td style="color:#93a6bb;font-size:.72rem"><?php echo Helpers::sanitizeOutput($r['email']); ?></td>
          <td style="text-align:center;font-size:.7rem;color:#d7e3ef"><?php echo $last4; ?></td>
          <td style="text-align:center">
            <span class="status-badge pwd-<?php echo htmlspecialchars($st); ?>">
              <i class="bi bi-circle-fill" style="font-size:.45rem"></i><?php echo htmlspecialchars($st); ?>
            </span>
          </td>
          <td><span class="status-badge acct-<?php echo htmlspecialchars($acct); ?>"><?php echo htmlspecialchars($acct); ?></span></td>
          <td><?php echo $ws?htmlspecialchars($ws):'—'; ?></td>
          <td><?php echo $accDisp?htmlspecialchars($accDisp):'—'; ?></td>
          <td class="actions" style="text-align:right">
            <form method="post" action="admin_job_seeker_view" class="d-inline">
              <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($r['user_id']); ?>">
              <button type="submit" class="view-btn"><i class="bi bi-eye"></i>View</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
// Auto-dismiss
document.querySelectorAll('.alert.auto-dismiss').forEach(el=>{setTimeout(()=>{try{bootstrap.Alert.getOrCreateInstance(el).close();}catch(e){}},4000);});
const search=document.getElementById('jsSearch');
const statusSelect=document.getElementById('jsStatusSelect');
const workSetup=document.getElementById('jsWorkSetup');
const accTag=document.getElementById('jsAccTag');
const chips=document.getElementById('jsStatusChips');
const resetBtn=document.getElementById('jsReset');
const rows=[...document.querySelectorAll('#seekersTable tbody tr')];

function apply(){
  const q=(search.value||'').toLowerCase().trim();
  const st=statusSelect.value||'';
  const ws=workSetup.value||'';
  const at=accTag.value||'';
  let shown=0;
  rows.forEach(r=>{
    const rs=r.getAttribute('data-status')||'';
    const rws=r.getAttribute('data-ws')||'';
    const rats=(r.getAttribute('data-acc')||'').split('|').filter(Boolean);
    const text=r.innerText.toLowerCase();
    const okStatus=!st||rs===st;
    const okWS=!ws||rws===ws;
    const okAcc=!at||rats.includes(at);
    const okSearch=!q||text.includes(q);
    const show=okStatus&&okWS&&okAcc&&okSearch;
    r.style.display=show?'':'none';
    if(show) shown++;
  });
}
search?.addEventListener('input',apply);
statusSelect?.addEventListener('change',()=>{apply();syncChip();});
workSetup?.addEventListener('change',apply);
accTag?.addEventListener('change',apply);
chips?.addEventListener('click',e=>{const c=e.target.closest('.js-chip');if(!c) return;[...chips.querySelectorAll('.js-chip')].forEach(x=>x.classList.remove('active'));c.classList.add('active');statusSelect.value=c.getAttribute('data-filter')||'';apply();});
resetBtn?.addEventListener('click',()=>{search.value='';statusSelect.value='';workSetup.value='';accTag.value='';[...chips.querySelectorAll('.js-chip')].forEach(x=>x.classList.remove('active'));chips.querySelector('[data-filter=""]')?.classList.add('active');apply();});
function syncChip(){const v=statusSelect.value||'';[...chips.querySelectorAll('.js-chip')].forEach(ch=>{ch.classList.toggle('active',(ch.getAttribute('data-filter')||'')===v);});}

// Sort
document.querySelectorAll('#seekersTable thead th[data-sort]').forEach(th=>{th.addEventListener('click',()=>{const key=th.getAttribute('data-sort');const tbody=th.closest('table').querySelector('tbody');const dir=th.getAttribute('data-dir')==='asc'?'desc':'asc';th.setAttribute('data-dir',dir);const f=dir==='asc'?1:-1;const arr=[...tbody.querySelectorAll('tr')];arr.sort((a,b)=>{const get=(row)=>{switch(key){case 'name':return row.children[0].innerText.toLowerCase();case 'email':return row.children[1].innerText.toLowerCase();case 'pwd':return row.children[2].innerText.toLowerCase();case 'pwd_status':return row.getAttribute('data-status')||'';case 'acct':return row.getAttribute('data-acct')||'';default:return row.innerText.toLowerCase();}};return get(a).localeCompare(get(b))*f;});arr.forEach(tr=>tbody.appendChild(tr));});});
apply();
</script>
