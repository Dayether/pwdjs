<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($password === '') {
        $errors[] = 'Enter your password.';
    }

    if (!$errors) {
        $user = User::authenticate($email, $password);
        if (!$user) {
            $errors[] = 'Invalid email or password.';
        } else {
            // Gate employer login by status
            if ($user->role === 'employer') {
                $status = $user->employer_status ?: 'Pending';
                if ($status !== 'Approved') {
                    if ($status === 'Pending') {
                        $errors[] = 'Your employer account is pending approval. You cannot log in yet. Please wait for an admin to approve your account.';
                    } elseif ($status === 'Suspended') {
                        $errors[] = 'Your employer account is suspended. Please contact support.';
                    } elseif ($status === 'Rejected') {
                        $errors[] = 'Your employer verification was rejected. Please contact support.';
                    } else {
                        $errors[] = 'Your employer account is not approved. Please contact support.';
                    }
                }
            }

            // If no errors after status checks, proceed to log in
            if (!$errors) {
                $_SESSION['user_id'] = $user->user_id;
                $_SESSION['role']    = $user->role;
                $_SESSION['name']    = $user->name;
                $_SESSION['email']   = $user->email;

                // Redirect by role
                if ($user->role === 'admin') {
                    Helpers::redirect('admin_employers.php');
                } elseif ($user->role === 'employer') {
                    Helpers::redirect('employer_dashboard.php');
                } else { // job_seeker
                    Helpers::redirect('user_dashboard.php');
                }
                exit;
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
        <h2 class="h4 fw-semibold mb-3"><i class="bi bi-box-arrow-in-right me-2"></i>Login</h2>

        <?php foreach ($errors as $e): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($e); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endforeach; ?>

        <form method="post" class="row g-3">
          <div class="col-12">
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control form-control-lg" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Password</label>
            <input name="password" type="password" class="form-control form-control-lg" required>
          </div>
          <div class="col-12 d-grid">
            <button class="btn btn-primary btn-lg"><i class="bi bi-box-arrow-in-right me-1"></i>Login</button>
          </div>
        </form>

        <div class="text-muted small mt-3">
          Don’t have an account? <a href="register.php">Register</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>