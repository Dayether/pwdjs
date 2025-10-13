<?php
require_once 'config/config.php';
require_once 'classes/Helpers.php';

Helpers::requireRole('admin');

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

include 'includes/header.php';
?>
<div class="admin-layout">
  <?php $currentPage='admin_normalize_disabilities_ui.php'; include 'includes/admin_sidebar.php'; ?>
  <div class="admin-main">
    <div class="dash-header" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem;flex-wrap:wrap;">
      <h2 style="font-size:1.05rem;font-weight:600;letter-spacing:.5px;display:flex;align-items:center;gap:.55rem;margin:0"><i class="bi bi-braces"></i> Normalize Disability Labels</h2>
      <div class="d-flex gap-2">
        <a href="admin_tasks_log.php" class="btn btn-sm btn-outline-light"><i class="bi bi-clipboard-data me-1"></i>Tasks Log</a>
      </div>
    </div>

    <div class="section-card" style="border:1px solid rgba(255,255,255,.08);background:#101a2b;border-radius:16px;padding:1.15rem 1.25rem;margin-bottom:1.4rem;box-shadow:0 4px 18px -10px rgba(0,0,0,.55);">
      <div class="section-title" style="color:#fff;">About</div>
      <div class="small" style="color:#fff;">Convert existing Users (disability / disability_type) and Jobs (applicable_pwd_types) to the registration labels. Use Preview first; Apply will write changes. Admin-only.</div>
    </div>

    <div class="section-card" style="border:1px solid rgba(255,255,255,.08);background:#101a2b;border-radius:16px;padding:1.15rem 1.25rem;margin-bottom:1.4rem;box-shadow:0 4px 18px -10px rgba(0,0,0,.55);">
      <div id="last" class="small mb-2" style="color:#fff;">Loading last run…</div>
      <div class="d-flex gap-2 flex-wrap mb-2">
        <button id="btn-preview" class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i> Preview</button>
        <form id="applyForm" method="post" class="d-inline" onsubmit="return confirm('Apply normalization now? This will update data.');">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
          <button id="btn-apply" class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check2-circle"></i> Apply</button>
        </form>
      </div>
      <div class="table-wrapper" style="position:relative;border:1px solid rgba(255,255,255,.06);background:#0f1827;border-radius:16px;overflow:hidden;box-shadow:0 6px 22px -12px rgba(0,0,0,.65)">
        <div class="p-2">
          <div id="result" class="small" style="white-space:pre-wrap; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; color:#fff;"></div>
          <div id="tables" style="display:none;">
            <h6 class="mt-3">Users (examples)</h6>
            <div class="table-responsive small">
              <table class="table table-sm table-bordered mb-3" id="tbl-users">
                <thead><tr><th>User ID</th><th>Disability (from → to)</th><th>Type (from → to)</th></tr></thead>
                <tbody></tbody>
              </table>
            </div>
            <h6>Jobs (examples)</h6>
            <div class="table-responsive small">
              <table class="table table-sm table-bordered" id="tbl-jobs">
                <thead><tr><th>Job ID</th><th>Applicable PWD (from → to)</th></tr></thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
  </div>
</div>
<script>
const resDiv = document.getElementById('result');
const tblWrap = document.getElementById('tables');
const uBody = document.querySelector('#tbl-users tbody');
const jBody = document.querySelector('#tbl-jobs tbody');
const lastDiv = document.getElementById('last');

async function loadLast(){
  try{
    const r = await fetch('admin_normalize_disabilities.php?action=last', { credentials:'same-origin' });
    const data = await r.json();
    if (data && data.last) {
      const l = data.last;
      lastDiv.textContent = `Last run: ${l.created_at || 'N/A'} by ${l.actor_user_id || 'N/A'} — users: ${l.users_updated||0}/${l.users_scanned||0}, jobs: ${l.jobs_updated||0}/${l.jobs_scanned||0}`;
    } else {
      lastDiv.textContent = 'No previous runs logged.';
    }
  } catch(e){ lastDiv.textContent = 'No previous runs logged.'; }
}
loadLast();

async function preview(){
  resDiv.textContent = 'Loading preview...';
  tblWrap.style.display = 'none';
  uBody.innerHTML=''; jBody.innerHTML='';
  const r = await fetch('admin_normalize_disabilities.php?action=dry-run', { credentials:'same-origin' });
  const data = await r.json();
  resDiv.textContent = JSON.stringify(data, null, 2);
  if (data && data.report) fillTables(data.report);
}

function fillTables(report){
  tblWrap.style.display = 'block';
  (report.users?.examples||[]).forEach(ex => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${ex.user_id}</td><td>${escapeHtml(ex.from?.disability||'') } → <strong>${escapeHtml(ex.to?.disability||'')}</strong></td><td>${escapeHtml(ex.from?.disability_type||'')} → <strong>${escapeHtml(ex.to?.disability_type||'')}</strong></td>`;
    uBody.appendChild(tr);
  });
  (report.jobs?.examples||[]).forEach(ex => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${ex.job_id}</td><td>${escapeHtml(ex.from||'')} → <strong>${escapeHtml(ex.to||'')}</strong></td>`;
    jBody.appendChild(tr);
  });
}

function escapeHtml(s){ const d=document.createElement('div'); d.textContent= s??''; return d.innerHTML; }

document.getElementById('btn-preview').addEventListener('click', preview);

document.getElementById('applyForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const form = e.currentTarget;
  const fd = new FormData(form);
  resDiv.textContent = 'Applying changes...';
  tblWrap.style.display = 'none';
  uBody.innerHTML=''; jBody.innerHTML='';
  const r = await fetch('admin_normalize_disabilities.php?action=apply', {
    method:'POST',
    body: fd,
    credentials:'same-origin'
  });
  const data = await r.json();
  resDiv.textContent = JSON.stringify(data, null, 2);
  if (data && data.report) fillTables(data.report);
  await loadLast();
});
</script>
<?php include 'includes/footer.php'; ?>
