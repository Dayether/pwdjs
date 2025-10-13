<?php
if (!isset($currentPage)) $currentPage = basename($_SERVER['PHP_SELF']);
function nav_active($file,$currentPage){ return $file===$currentPage?'active':''; }
$loggedIn = !empty($_SESSION['user_id']);
$role = $_SESSION['role'] ?? null;
$showPostJobButton = ($loggedIn && $role==='employer');
$flashes = $_SESSION['global_flashes'] ?? [];
if (isset($_SESSION['global_flashes'])) unset($_SESSION['global_flashes']);

$profileLink = 'profile_edit.php';
if ($loggedIn && $role==='employer') {
  $profileLink='employer_profile.php';
} elseif ($loggedIn && $role==='admin') {
  $profileLink=''; // no profile page for admin
} elseif ($loggedIn && $role==='job_seeker') {
  $profileLink='job_seeker_profile.php'; // self-view; edit button available inside
}
?>
<?php $isIndex = ($currentPage === 'index.php'); ?>
<nav class="navbar navbar-expand-lg navbar-themed<?php echo $isIndex ? '' : ' sticky-top'; ?>">
  <div class="container-fluid px-3 px-lg-4">
    <?php
      // Reuse favicon (set in header) as nav logo if available on disk
      $logoFallback = 'assets/images/hero/logo.png';
      $logoCandidates = [
        'favicon.png',
        'favicon.ico',
        'assets/favicon.png',
        $logoFallback
      ];
      $foundLogo = $logoFallback;
      foreach ($logoCandidates as $cand) {
        $disk = __DIR__.'/../'.ltrim($cand,'/');
        if (is_file($disk)) { $foundLogo = $cand; break; }
      }
    ?>
    <a class="navbar-brand fw-semibold d-flex align-items-center gap-2 <?php echo nav_active('about.php',$currentPage); ?>" href="<?php echo rtrim(BASE_URL,'/'); ?>/about" aria-label="PWD Portal Home">
      <img src="<?php echo htmlspecialchars($foundLogo); ?>" alt="PWD Portal Logo" class="brand-logo" onerror="this.src='<?php echo htmlspecialchars($logoFallback); ?>';" />
      <span class="text-ink">PWD Portal</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php if (!($loggedIn && $role==='employer')): ?>
          <li class="nav-item">
            <a class="nav-link <?php echo nav_active('index.php',$currentPage); ?>" href="<?php echo rtrim(BASE_URL,'/'); ?>/">
              <i class="bi bi-search me-1"></i>Find Jobs
            </a>
          </li>
        <?php endif; ?>

        <?php if ($loggedIn && $role==='job_seeker'): ?>
          <li class="nav-item">
            <a class="nav-link <?php echo nav_active('user_dashboard.php',$currentPage); ?>" href="user_dashboard.php">
              <i class="bi bi-speedometer2 me-1"></i>Dashboard
            </a>
          </li>
        <?php endif; ?>

        <?php if ($loggedIn && $role==='employer'): ?>
          <li class="nav-item">
            <a class="nav-link <?php echo nav_active('employer_dashboard.php',$currentPage); ?>" href="employer_dashboard.php">
              <i class="bi bi-speedometer2 me-1"></i>Dashboard
            </a>
          </li>
          <?php /* Candidates link removed for employer per request */ ?>
        <?php endif; ?>

        <?php if ($loggedIn && $role==='admin'): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?php echo in_array($currentPage,['admin_employers.php','admin_reports.php','admin_support_tickets.php','admin_job_seekers.php','admin_job_seeker_view.php'])?'active':''; ?>" href="#" data-bs-toggle="dropdown">
              <i class="bi bi-shield-lock me-1"></i>Admin
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item <?php echo nav_active('admin_dashboard.php',$currentPage); ?>" href="admin_dashboard.php">Dashboard</a></li>
              <li><a class="dropdown-item <?php echo nav_active('admin_employers.php',$currentPage); ?>" href="admin_employers.php">Employers</a></li>
              <li><a class="dropdown-item <?php echo nav_active('admin_job_seekers.php',$currentPage); ?>" href="admin_job_seekers.php">Job Seekers</a></li>
              <li><a class="dropdown-item <?php echo nav_active('admin_reports.php',$currentPage); ?>" href="admin_reports.php">Reports</a></li>
              <li><a class="dropdown-item <?php echo nav_active('admin_support_tickets.php',$currentPage); ?>" href="admin_support_tickets.php">Support Tickets</a></li>
            </ul>
          </li>
        <?php endif; ?>
      </ul>

      <ul class="navbar-nav mb-2 mb-lg-0 align-items-lg-center">
        <?php if ($showPostJobButton): ?>
          <li class="nav-item me-2">
            <a class="btn btn-accent btn-sm" href="jobs_create.php">
              <i class="bi bi-plus-lg me-1"></i>Post a Job
            </a>
          </li>
        <?php endif; ?>

        <?php if (!$loggedIn): ?>
          <li class="nav-item">
            <a class="nav-link <?php echo nav_active('register.php',$currentPage); ?>" href="register.php">Register</a>
          </li>
          <li class="nav-item ms-lg-2">
            <a class="btn btn-light btn-sm" href="login.php">
              <i class="bi bi-box-arrow-in-right me-1"></i>Login
            </a>
          </li>
        <?php else: ?>
          <?php if ($role === 'admin'): ?>
            <li class="nav-item d-flex align-items-center me-2">
              <span class="small fw-semibold text-ink"><i class="bi bi-shield-lock me-1"></i><?php echo htmlspecialchars($_SESSION['name']); ?></span>
            </li>
            <li class="nav-item">
              <a class="btn btn-light btn-sm" href="logout.php" data-confirm-title="Log out" data-confirm="Are you sure you want to log out?" data-confirm-yes="Log out" data-confirm-no="Stay logged in">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
              </a>
            </li>
          <?php else: ?>
            <?php
              $avatarPath = null;
              if (!empty($_SESSION['user_id'])) {
                try {
                  $pdoAvatar = Database::getConnection();
                  $stmtA = $pdoAvatar->prepare("SELECT profile_picture FROM users WHERE user_id=? LIMIT 1");
                  $stmtA->execute([$_SESSION['user_id']]);
                  $avatarPath = $stmtA->fetchColumn() ?: null;
                } catch (Throwable $e) { $avatarPath = null; }
              }
            ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                <?php if ($avatarPath): ?>
                  <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Avatar" class="rounded-circle border" style="width:32px;height:32px;object-fit:cover;">
                <?php else: ?>
                  <span class="rounded-circle bg-light d-inline-flex justify-content-center align-items-center" style="width:32px;height:32px;">
                    <i class="bi bi-person" style="font-size:1rem;"></i>
                  </span>
                <?php endif; ?>
                <span class="small fw-semibold text-ink"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?php echo $profileLink ?: '#'; ?>">View Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                  <a class="dropdown-item d-flex align-items-center gap-2" href="logout.php" data-confirm-title="Log out" data-confirm="Are you sure you want to log out?" data-confirm-yes="Log out" data-confirm-no="Stay logged in">
                    <i class="bi bi-box-arrow-right"></i><span>Logout</span>
                  </a>
                </li>
              </ul>
            </li>
          <?php endif; ?>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<style>
  /* Navbar redesign to blend with hero */
  .navbar-themed {
    background: linear-gradient(90deg, rgba(13,110,253,.35), rgba(102,16,242,.35));
    backdrop-filter: blur(14px) saturate(160%);
    -webkit-backdrop-filter: blur(14px) saturate(160%);
    border-bottom: 1px solid rgba(255,255,255,.18);
    border-radius: 0 !important;
    margin:0 !important;
    transition: background .35s ease, box-shadow .35s ease, padding .35s ease;
    box-shadow: none;
  }
  .navbar-themed.navbar-scrolled {
    background: linear-gradient(90deg,#0d6efd,#6610f2);
    box-shadow: 0 6px 24px -6px rgba(0,0,0,.35);
  }
  .navbar-themed .navbar-brand .text-ink { color:#fff !important; }
  .navbar-themed .navbar-brand span.rounded-circle { box-shadow:0 0 0 2px rgba(255,255,255,.4); }
  .navbar-themed .nav-link { color:#f1f4f9; font-weight:500; position:relative; padding:.55rem 1rem; border-radius:32px; }
  .navbar-themed .nav-link:hover, .navbar-themed .nav-link:focus { color:#fff; background:rgba(255,255,255,.15); }
  .navbar-themed .nav-link.active { color:#fff; background:rgba(255,255,255,.28); font-weight:600; }
  .navbar-themed .btn-light.btn-sm { background:#fff; color:#0d3a66; border:0; box-shadow:0 4px 14px -4px rgba(0,0,0,.35); }
  .navbar-themed .btn-light.btn-sm:hover { background:#f1f5ff; }
  .navbar-themed .btn-accent.btn-sm { box-shadow:0 4px 16px -4px rgba(255,193,7,.45); }
  .navbar-themed .brand-logo { height:32px; width:auto; display:block; object-fit:contain; }
  @media (max-width: 575.98px){ .navbar-themed .brand-logo { height:28px; } }
  @media (max-width: 991.98px){
    .navbar-themed .nav-link { padding:.5rem .75rem; }
  }
  /* Dropdown layering */
  .navbar .dropdown-menu { z-index: 2000; backdrop-filter: blur(12px); background:rgba(255,255,255,.95); position:absolute; }
  .navbar .dropdown-menu .dropdown-item:hover { background: linear-gradient(90deg,#eef5ff,#e6f0ff); }
  /* Smooth hide white gap below nav when at top (only useful when sticky) */
  body { margin:0; }
  body.has-sticky-nav{ scroll-padding-top: 76px; }
  .navbar { margin:0 !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
  const nav = document.querySelector('.navbar-themed');
  if(!nav) return;
  if (nav.classList.contains('sticky-top')) {
    document.body.classList.add('has-sticky-nav');
  }
  function onScroll(){
    if(window.scrollY > 40){
      nav.classList.add('navbar-scrolled');
    } else {
      nav.classList.remove('navbar-scrolled');
    }
  }
  onScroll();
  window.addEventListener('scroll', onScroll, {passive:true});
});
</script>
<?php
if (!empty($flashes)) {
  echo '<div class="container mt-3">';
  foreach ($flashes as $f) {
    $type = htmlspecialchars($f['type'] ?? 'info');
    $msg  = $f['message'] ?? '';
    echo '<div class="alert alert-' . $type . ' alert-dismissible fade show auto-dismiss" role="alert">'
       . '<i class="bi bi-info-circle me-2"></i>' . $msg
       . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
       . '</div>';
  }
  echo '</div>';
}
?>