<?php
if (!isset($currentPage)) {
  $currentPage = basename($_SERVER['PHP_SELF']);
}
function nav_active($file, $currentPage) {
  return $file === $currentPage ? 'active' : '';
}

$loggedIn = !empty($_SESSION['user_id']);
$role = $_SESSION['role'] ?? null;
$showPostJobButton = ($loggedIn && $role === 'employer');

$flashes = $_SESSION['global_flashes'] ?? [];
if (isset($_SESSION['global_flashes'])) unset($_SESSION['global_flashes']);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <!-- Brand (About page link) -->
    <a class="navbar-brand fw-semibold <?php echo nav_active('about.php',$currentPage); ?>" href="about.php">
      <i class="bi bi-universal-access me-1"></i>PWD Portal
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link <?php echo nav_active('index.php',$currentPage); ?>" href="index.php">
            <i class="bi bi-search me-1"></i>Find Jobs
          </a>
        </li>

        <?php if ($loggedIn && $role === 'job_seeker'): ?>
          <li class="nav-item">
            <a class="nav-link <?php echo nav_active('user_dashboard.php',$currentPage); ?>" href="user_dashboard.php">
              <i class="bi bi-speedometer2 me-1"></i>Dashboard
            </a>
          </li>
          <!-- Removed separate 'My Applications' nav item; now inside dashboard -->
        <?php endif; ?>

        <?php if ($loggedIn && $role === 'employer'): ?>
          <li class="nav-item">
            <a class="nav-link <?php echo nav_active('employer_dashboard.php',$currentPage); ?>" href="employer_dashboard.php">
              <i class="bi bi-speedometer2 me-1"></i>Dashboard
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo nav_active('employer_dashboard.php',$currentPage); ?>" href="employer_dashboard.php#jobs">
              <i class="bi bi-briefcase me-1"></i>My Jobs
            </a>
          </li>
        <?php endif; ?>

        <?php if ($loggedIn && $role === 'admin'): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?php echo in_array($currentPage,['admin_employers.php','admin_reports.php','admin_support_tickets.php'])?'active':''; ?>" href="#" data-bs-toggle="dropdown">
              <i class="bi bi-shield-lock me-1"></i>Admin
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item <?php echo nav_active('admin_employers.php',$currentPage); ?>" href="admin_employers.php">Employers</a></li>
              <li><a class="dropdown-item <?php echo nav_active('admin_reports.php',$currentPage); ?>" href="admin_reports.php">Reports</a></li>
              <li><a class="dropdown-item <?php echo nav_active('admin_support_tickets.php',$currentPage); ?>" href="admin_support_tickets.php">Support Tickets</a></li>
            </ul>
          </li>
        <?php endif; ?>
      </ul>

      <ul class="navbar-nav mb-2 mb-lg-0">
        <?php if ($showPostJobButton): ?>
          <li class="nav-item me-2">
            <a class="btn btn-primary btn-sm" href="jobs_create.php">
              <i class="bi bi-plus-lg me-1"></i>Post a Job
            </a>
          </li>
        <?php endif; ?>

        <?php if (!$loggedIn): ?>
          <li class="nav-item">
            <a class="nav-link <?php echo nav_active('register.php',$currentPage); ?>" href="register.php">Register</a>
          </li>
          <li class="nav-item ms-lg-2">
            <a class="btn btn-outline-light btn-sm" href="login.php">
              <i class="bi bi-box-arrow-in-right me-1"></i>Login
            </a>
          </li>
        <?php else: ?>
          <li class="nav-item me-2">
            <a class="btn btn-outline-light btn-sm <?php echo nav_active('profile_edit.php',$currentPage); ?>" href="job_seeker_profile.php">
              <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($_SESSION['name']); ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="btn btn-outline-light btn-sm"
               href="logout.php"
               data-confirm-title="Log out"
               data-confirm="Are you sure you want to log out?"
               data-confirm-yes="Log out"
               data-confirm-no="Stay logged in">
              <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
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