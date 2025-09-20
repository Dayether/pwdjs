<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';

if (session_status()===PHP_SESSION_NONE) {
    session_start();
}

/* ADDED: store last page so back logic has context when leaving login */
Helpers::storeLastPage();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    } else {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetchObject('User');

            if (!$user || !password_verify($password, $user->password)) {
                $errors[] = 'Invalid credentials.';
            } else {
                if ($user->role === 'employer') {
                    $status = $user->employer_status ?? 'Pending';
                    $supportLink = '<a href="' . Helpers::supportLink('Employer Verification') . '" class="alert-link">contact support</a>';
                    if ($status !== 'Approved') {
                        if ($status === 'Pending') {
                            $errors[] = 'Your employer account is pending approval. Please wait or ' . $supportLink . '.';
                        } elseif ($status === 'Suspended') {
                            $errors[] = 'Your employer account is suspended. Please ' . $supportLink . '.';
                        } elseif ($status === 'Rejected') {
                            $errors[] = 'Your verification was rejected. Please ' . $supportLink . '.';
                        } else {
                            $errors[] = 'Employer account not approved. Please ' . $supportLink . '.';
                        }
                    }
                }

                if (!$errors) {
                    $_SESSION['user_id'] = $user->user_id;
                    $_SESSION['role']    = $user->role;
                    $_SESSION['name']    = $user->name;
                    $_SESSION['email']   = $user->email;

                    if ($user->role === 'admin') {
                        Helpers::redirect('admin_employers.php');
                    } elseif ($user->role === 'employer') {
                        Helpers::redirect('employer_dashboard.php');
                    } else {
                        Helpers::redirect('user_dashboard.php');
                    }
                }
            }
        } catch (Throwable $e) {
            $errors[] = 'Login failed unexpectedly.';
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
        <h2 class="h4 fw-semibold mb-3">
          <i class="bi bi-box-arrow-in-right me-2"></i>Login
        </h2>

        <?php foreach ($errors as $e): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($e); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
            <button class="btn btn-primary btn-lg">
              <i class="bi bi-box-arrow-in-right me-1"></i>Login
            </button>
          </div>
        </form>

        <div class="text-muted small mt-3">
          Don’t have an account? <a href="register.php">Register</a>
        </div>

        <div class="mt-3">
          <div class="alert alert-info py-2 px-3 mb-0 small">
            Need help with your account?
            <a href="<?php echo Helpers::supportLink(); ?>" class="alert-link">Contact Support</a>.
            <div class="mt-2 small">
              Quick links:
              <a href="<?php echo Helpers::supportLink('Account Suspension'); ?>" class="text-decoration-none">Account Suspension</a> &middot;
              <a href="<?php echo Helpers::supportLink('Employer Verification'); ?>" class="text-decoration-none">Employer Verification</a> &middot;
              <a href="<?php echo Helpers::supportLink('Login Issue'); ?>" class="text-decoration-none">Login Issue</a>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
document.querySelectorAll('.alert.auto-dismiss').forEach(el=>{
  setTimeout(()=>{ try { bootstrap.Alert.getOrCreateInstance(el).close(); } catch(e){} },4000);
});
</script>