<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (Helpers::isLoggedIn()) {
  $r = $_SESSION['role'] ?? '';
  if ($r === 'admin')      Helpers::redirect('admin_employers.php');
  elseif ($r === 'employer') Helpers::redirect('employer_dashboard.php');
  else                     Helpers::redirect('user_dashboard.php');
}

// Prefill email if coming right after registration
$prefillEmail = '';
if (!empty($_SESSION['prefill_email'])) {
    $prefillEmail = $_SESSION['prefill_email'];
    unset($_SESSION['prefill_email']);
}

$errors = [];
$flashMessages = method_exists('Helpers','getFlashes') ? Helpers::getFlashes() : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($email === '' || $pass === '') {
        $errors[] = 'Please enter both email and password.';
    } else {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($pass, $row['password'])) {
            $errors[] = 'Invalid email or password.';
        } else {
            // Employer gating
            if ($row['role'] === 'employer') {
                $estatus = $row['employer_status'] ?? 'Pending';
                if ($estatus !== 'Approved') {
                    $errors[] = 'Employer account not approved (status: '.$estatus.').';
                }
            }
            // Job seeker gating (PWD ID)
            if (!$errors && $row['role'] === 'job_seeker') {
                $s = $row['pwd_id_status'] ?? 'None';
                if ($s !== 'Verified') {
                    if ($s === 'Pending')
                        $errors[] = 'Your PWD ID is pending admin verification.';
                    elseif ($s === 'Rejected')
                        $errors[] = 'Your PWD ID was rejected.';
                    else
                        $errors[] = 'PWD ID not verified.';
                }
            }

            if (!$errors) {
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['role']    = $row['role'];
                $_SESSION['name']    = $row['name'];
                $_SESSION['email']   = $row['email'];

                if ($row['role'] === 'admin') {
                    Helpers::redirect('admin_employers.php');
                } elseif ($row['role'] === 'employer') {
                    Helpers::redirect('employer_dashboard.php');
                } else {
                    Helpers::redirect('user_dashboard.php');
                }
            }
        }
    }
}

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="row justify-content-center">
  <div class="col-lg-8 col-xl-6">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h2 class="h5 fw-semibold mb-3"><i class="bi bi-box-arrow-in-right me-2"></i>Login</h2>

        <?php foreach ($flashMessages as $f): ?>
          <div class="alert alert-<?php echo htmlspecialchars($f['type']); ?> alert-dismissible fade show">
            <?php echo $f['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endforeach; ?>

        <?php foreach ($errors as $e): ?>
          <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($e); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endforeach; ?>

        <form method="post" novalidate>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ($prefillEmail ?? '')); ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <div class="d-grid">
            <button class="btn btn-primary">
              <i class="bi bi-box-arrow-in-right me-1"></i>Login
            </button>
          </div>
        </form>

        <div class="mt-3 small">
          Don't have an account? <a href="register.php">Register</a>
        </div>
        <div class="alert alert-info mt-3 py-2 small mb-0">
          Need help with your account? <a href="support.php" class="fw-semibold text-decoration-none">Contact Support</a>.
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>