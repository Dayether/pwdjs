<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$flashes = Helpers::getFlashes();

// Decide if we should show the "Post a Job" button (only for Approved employers)
$showPostJobButton = false;
if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'employer') {
  require_once '../classes/User.php';
  $navUser = User::findById($_SESSION['user_id']);
  if ($navUser && ($navUser->employer_status ?? 'Pending') === 'Approved') {
    $showPostJobButton = true;
  }
}

function nav_active($file, $current) {
  return $file === $current ? 'active' : '';
}
?>
<a class="visually-hidden-focusable position-absolute top-0 start-0 m-2" href="#main-content">Skip to main content</a>

<div class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
  <div class="container">
    <!-- Brand links to About page -->
    <a
      class="navbar-brand d-flex align-items-center fw-semibold <?php echo $currentPage==='about.php' ? 'text-decoration-underline' : ''; ?>"
      href="about.php"
      title="About this portal"
    >
      <i class="bi bi-briefcase me-2"></i> PWD Portal
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div id="mainNav" class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">

        <li class="nav-item">
          <a class="nav-link <?php echo nav_active('index.php',$currentPage); ?>" href="index.php">
            <i class="bi bi-house-door me-1"></i>Find Jobs
          </a>
        </li>

        <?php if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'job_seeker'): ?>
          <li class="nav-item">
            <a class="nav-link <?php echo nav_active('user_dashboard.php',$currentPage); ?>" href="user_dashboard.php">
              <i class="bi bi-grid me-1"></i>Dashboard
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo nav_active('applications.php',$currentPage); ?>" href="applications.php">
              <i class="bi bi-list-check me-1"></i>My Applications
            </a>
          </li>
          <!-- Removed duplicate Profile nav item for job seekers -->
        <?php elseif (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'employer'): ?>
          <li class="nav-item">
            <a class="nav-link <?php echo nav_active('employer_dashboard.php',$currentPage); ?>" href="employer_dashboard.php">
              <i class="bi bi-building me-1"></i>Employer Dashboard
            </a>
          </li>
        <?php endif; ?>

        <?php if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin'): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?php echo in_array($currentPage, ['admin_employers.php','admin_reports.php','admin_support_tickets.php'])?'active':''; ?>" href="#" data-bs-toggle="dropdown">
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

        <?php if (empty($_SESSION['user_id'])): ?>
          <li class="nav-item">
            <a class="nav-link <?php echo nav_active('register.php',$currentPage); ?>" href="register.php">Register</a>
          </li>
          <li class="nav-item ms-lg-2">
            <a class="btn btn-outline-light btn-sm" href="login.php">
              <i class="bi bi-box-arrow-in-right me-1"></i>Login
            </a>
          </li>
        <?php else: ?>
          <!-- User profile shortcut (kept) -->
            <li class="nav-item me-2">
              <a class="btn btn-outline-light btn-sm <?php echo nav_active('profile_edit.php',$currentPage); ?>" href="profile_edit.php">
                <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($_SESSION['name']); ?>
              </a>
            </li>
            <li class="nav-item">
              <a
                class="btn btn-outline-light btn-sm"
                href="logout.php"
                data-confirm-title="Log out"
                data-confirm="Are you sure you want to log out?"
                data-confirm-yes="Log out"
                data-confirm-no="Stay logged in"
              >
                <i class="bi bi-box-arrow-right me-1"></i>Logout
              </a>
            </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<?php
// Global flash alerts
if (!empty($flashes)) {
  echo '<div class="container mt-3">';
  foreach ($flashes as $f) {
    $type = htmlspecialchars($f['type'] ?? 'info');
    $msg  = $f['message'] ?? '';
    echo '<div class="alert alert-' . $type . ' alert-dismissible fade show auto-dismiss" role="alert">'
       . '<i class="bi bi-info-circle me-2"></i>' . $msg
       . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
       . '</div>';
  }
  echo '</div>';
}
?>