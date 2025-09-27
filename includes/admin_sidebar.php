<?php
if (!isset($currentPage)) $currentPage = basename($_SERVER['PHP_SELF']);
$adminNavItems = [
  ['file' => 'admin_dashboard.php', 'icon' => 'speedometer2', 'label' => 'Dashboard'],
  ['file' => 'admin_employers.php', 'icon' => 'buildings', 'label' => 'Employers'],
  ['file' => 'admin_job_seekers.php', 'icon' => 'people', 'label' => 'Job Seekers'],
  ['file' => 'admin_reports.php', 'icon' => 'flag', 'label' => 'Reports'],
  ['file' => 'admin_support_tickets.php', 'icon' => 'life-preserver', 'label' => 'Support'],
];
?>
<aside class="admin-sidebar" id="adminSidebar" aria-label="Admin navigation">
  <div class="admin-sidebar-inner">
    <div class="admin-brand d-flex align-items-center mb-3">
      <span class="admin-brand-icon me-2"><i class="bi bi-shield-lock"></i></span>
      <span class="fw-semibold small">Admin Panel</span>
      <button class="btn btn-sm btn-outline-light ms-auto d-lg-none" id="adminSidebarClose" aria-label="Close sidebar">&times;</button>
    </div>
    <nav class="admin-nav flex-grow-1">
      <ul class="list-unstyled m-0 p-0">
        <?php foreach ($adminNavItems as $item):
          $active = ($currentPage === $item['file']);
        ?>
          <li>
            <a href="<?php echo $item['file']; ?>" class="admin-nav-link <?php echo $active?'active':''; ?>" aria-current="<?php echo $active?'page':'false'; ?>">
              <i class="bi bi-<?php echo $item['icon']; ?> me-2"></i>
              <span><?php echo htmlspecialchars($item['label']); ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div class="mt-4 small text-center text-muted">
      <a href="logout.php" class="btn btn-sm btn-outline-light w-100" data-confirm-title="Log out" data-confirm="Are you sure you want to log out?" data-confirm-yes="Yes, log out" data-confirm-no="Cancel">Logout</a>
    </div>
  </div>
</aside>

<button class="admin-sidebar-toggle btn btn-primary btn-sm d-lg-none" id="adminSidebarToggle" aria-label="Open admin menu"><i class="bi bi-list"></i></button>

<style>
:root {
  --admin-sidebar-width: 300px; /* widened from 250px */
  --admin-sidebar-bg: #0f172a; /* slate-900 */
  --admin-sidebar-bg-alt: #1e293b; /* slate-800 */
  --admin-sidebar-border: #1e293b;
  --admin-sidebar-link: #d1d5db;
  --admin-sidebar-link-hover: #fff;
  --admin-sidebar-link-active-bg: linear-gradient(90deg,#2563eb,#4f46e5);
  --admin-sidebar-link-active: #fff;
}
@media (prefers-color-scheme: light){
  :root { --admin-sidebar-bg:#ffffff; --admin-sidebar-bg-alt:#f1f5f9; --admin-sidebar-border:#e2e8f0; --admin-sidebar-link:#334155; --admin-sidebar-link-hover:#1e293b; }
}
.admin-layout {display:flex; align-items:stretch;}
.admin-sidebar {width:var(--admin-sidebar-width); background:var(--admin-sidebar-bg); color:#fff; min-height:calc(100vh - 0px); position:relative; z-index:1040; box-shadow:2px 0 8px -2px rgba(0,0,0,.25);}
.admin-sidebar-inner {padding:1.25rem 1.1rem 1.4rem; display:flex; flex-direction:column; height:100%;}
.admin-brand-icon {display:inline-flex; width:34px; height:34px; align-items:center; justify-content:center; background:linear-gradient(135deg,#2563eb,#4f46e5); border-radius:10px; font-size:1rem; color:#fff;}
.admin-nav-link {display:flex; align-items:center; gap:.45rem; padding:.7rem .95rem; border-radius:.75rem; font-size:.84rem; font-weight:600; text-decoration:none; color:var(--admin-sidebar-link); position:relative; letter-spacing:.25px; transition:background .15s,color .15s, box-shadow .15s;}
.admin-nav-link:hover {background:var(--admin-sidebar-bg-alt); color:var(--admin-sidebar-link-hover);} 
.admin-nav-link.active {background:var(--admin-sidebar-link-active-bg); color:var(--admin-sidebar-link-active); box-shadow:0 6px 16px -6px rgba(0,0,0,.45);} 
.admin-nav-link.active::before {content:""; position:absolute; left:-10px; top:50%; translate:0 -50%; width:5px; height:65%; background:#60a5fa; border-radius:5px; box-shadow:0 0 0 1px rgba(255,255,255,.25);} 

/* Slight separator under brand */
.admin-brand {padding-bottom:.75rem; margin-bottom:1rem; border-bottom:1px solid rgba(255,255,255,.08);} 
@media (prefers-color-scheme: light){ .admin-brand {border-color: #e2e8f0;} }

/* Toggle button (mobile) */
.admin-sidebar-toggle {position:fixed; left:1rem; bottom:1rem; z-index:1030; box-shadow:0 4px 14px -4px rgba(0,0,0,.4);} 

/* Mobile offcanvas behavior */
@media (max-width: 991.98px){
  .admin-sidebar {position:fixed; left:0; top:0; bottom:0; transform:translateX(-100%); transition:transform .3s ease; width:var(--admin-sidebar-width);}
  .admin-sidebar.show {transform:translateX(0);} 
  body.sidebar-open {overflow:hidden;} 
}

/* Main area wrapper (we'll wrap content) */
.admin-main {flex:1; padding:1.7rem 1.75rem 2.75rem; background:linear-gradient(180deg,#f8fafc,#f1f5f9); min-height:100vh;}
@media (prefers-color-scheme: dark){ .admin-main {background:linear-gradient(180deg,#0f172a,#1e293b);} }

/* Cards minimal polish */
.admin-main .card {border-radius:.9rem;}

/* Scrollbar styling inside sidebar */
.admin-sidebar {overflow-y:auto;}
.admin-sidebar::-webkit-scrollbar{width:8px;} 
.admin-sidebar::-webkit-scrollbar-track{background:rgba(255,255,255,.05);} 
.admin-sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,.25); border-radius:4px;} 
@media (prefers-color-scheme: light){
  .admin-sidebar::-webkit-scrollbar-track{background:#f1f5f9;} 
  .admin-sidebar::-webkit-scrollbar-thumb{background:#cbd5e1;} 
}

/* Close button mobile */
#adminSidebarClose {line-height:1; padding:.25rem .5rem;}
</style>
<script>
(function(){
  const sidebar = document.getElementById('adminSidebar');
  const openBtn = document.getElementById('adminSidebarToggle');
  const closeBtn = document.getElementById('adminSidebarClose');
  function open(){ sidebar.classList.add('show'); document.body.classList.add('sidebar-open'); }
  function close(){ sidebar.classList.remove('show'); document.body.classList.remove('sidebar-open'); }
  if(openBtn) openBtn.addEventListener('click',open); if(closeBtn) closeBtn.addEventListener('click',close);
  // Close when clicking outside (mobile)
  document.addEventListener('click', e=>{ if(window.innerWidth < 992 && sidebar.classList.contains('show')){ if(!sidebar.contains(e.target) && !openBtn.contains(e.target)) close(); } });
  // Highlight nav link when hash changes if needed (future anchor sections)
})();
</script>
