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
$flashMessages = method_exists('Helpers','getStructuredFlashes') ? Helpers::getStructuredFlashes() : [];

// Per-email throttle (5 failed attempts -> 15 minutes cooldown)
$THROTTLE_MAX_ATTEMPTS = 5;
$THROTTLE_COOLDOWN_SEC = 15 * 60; // 15 minutes
$throttleDir = __DIR__ . '/../uploads/.throttle';
if (!is_dir($throttleDir)) { @mkdir($throttleDir, 0777, true); }
$cooldownRemaining = 0; // dynamic per email after POST
$throttle = null; // will load after email known
$throttleFile = null;
$emailNotRegistered = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';

  if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
  }

    $emailKey = strtolower($email);
  if ($emailKey !== '') {
    $throttleFile = $throttleDir . '/' . md5('email:'.$emailKey) . '.json';
    $throttle = ['attempts'=>0,'blocked_until'=>0,'last_failed'=>0];
    if (is_file($throttleFile)) {
      $raw = @file_get_contents($throttleFile);
      if ($raw) {
        $data = json_decode($raw,true);
        if (is_array($data)) {
          $throttle['attempts'] = (int)($data['attempts'] ?? 0);
          $throttle['blocked_until'] = (int)($data['blocked_until'] ?? 0);
          $throttle['last_failed'] = (int)($data['last_failed'] ?? 0);
        }
      }
    }
    // Decay logic: if no failures for an entire cooldown window, reset attempts
    if ($throttle['attempts'] > 0 && (time() - (int)$throttle['last_failed']) >= $THROTTLE_COOLDOWN_SEC) {
      $throttle['attempts'] = 0;
      $throttle['last_failed'] = 0;
      @file_put_contents($throttleFile, json_encode($throttle));
    }
    if (time() < $throttle['blocked_until']) {
      $cooldownRemaining = max(0, $throttle['blocked_until'] - time());
      $mins = (int)floor($cooldownRemaining / 60);
      $secs = $cooldownRemaining % 60;
      $errors[] = 'Too many failed login attempts for this email. Try again in ' . $mins . 'm ' . $secs . 's.';
    }
  }

    if (empty($errors) && ($email === '' || $pass === '')) {
        $errors[] = 'Please enter both email and password.';
    }

  if (empty($errors)) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      $errors[] = 'Email is not registered.';
      $emailNotRegistered = true;
    }
    // If user exists but no password issued yet, show clear message and do not count as failed attempt
    elseif (empty($row['password'])) {
      $errors[] = 'Your initial password has not been issued yet. Please wait for the approval email.';
    } elseif (!password_verify($pass, $row['password'])) {
            // Only track if we have a throttleFile (i.e., email provided)
            if ($throttleFile) {
                $currentAttempts = (int)($throttle['attempts'] ?? 0);
                $newAttempts = $currentAttempts + 1;
        if ($newAttempts >= $THROTTLE_MAX_ATTEMPTS) {
          $throttle['blocked_until'] = time() + $THROTTLE_COOLDOWN_SEC;
          $throttle['attempts'] = 0; // reset attempts after blocking
          $throttle['last_failed'] = time();
          @file_put_contents($throttleFile, json_encode($throttle));
                    $cooldownRemaining = $THROTTLE_COOLDOWN_SEC;
                    $mins = (int)floor($THROTTLE_COOLDOWN_SEC / 60);
                    $errors[] = 'Too many failed login attempts for this email. Try again in ' . $mins . 'm 0s.';
                } else {
          $throttle['attempts'] = $newAttempts;
          $throttle['last_failed'] = time();
          @file_put_contents($throttleFile, json_encode($throttle));
                    $remaining = $THROTTLE_MAX_ATTEMPTS - $newAttempts;
                    $errors[] = 'Invalid email or password. Attempt ' . $newAttempts . ' of ' . $THROTTLE_MAX_ATTEMPTS . ' for this email (' . $remaining . ' remaining).';
                }
            } else {
                $errors[] = 'Invalid email or password.';
            }
        } else {
      // Employer gating
      if (strtolower((string)$row['role']) === 'employer') {
        $estatus = trim((string)($row['employer_status'] ?? 'Pending'));
        if (strcasecmp($estatus, 'Approved') !== 0) {
          $errors[] = 'Employer account not approved (status: '.$estatus.'). You will receive your password via email once approved.';
        }
      }
      // Job seeker gating (account suspension + PWD ID)
      if (!$errors && strtolower((string)$row['role']) === 'job_seeker') {
        $acctStatus = trim((string)($row['job_seeker_status'] ?? 'Active'));
        if (strcasecmp($acctStatus, 'Suspended') === 0) {
          $errors[] = 'Your account is suspended. Please contact support.';
        }
      }
      if (!$errors && strtolower((string)$row['role']) === 'job_seeker') {
        $s = trim((string)($row['pwd_id_status'] ?? 'None'));
        if (strcasecmp($s, 'Verified') !== 0) {
          if (strcasecmp($s, 'Pending') === 0) {
            $errors[] = 'Your PWD ID is pending admin verification. You will receive your password via email once verified.';
          } elseif (strcasecmp($s, 'Rejected') === 0) {
            $errors[] = 'Your PWD ID was rejected.';
          } else {
            $errors[] = 'PWD ID not verified.';
          }
        }
      }

      if (!$errors) {
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['role']    = $row['role'];
                $_SESSION['name']    = $row['name'];
                $_SESSION['email']   = $row['email'];

        // Clear throttle for this email on success
        if ($throttleFile && is_file($throttleFile)) { @unlink($throttleFile); }

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

        <?php if ($cooldownRemaining > 0): ?>
          <?php $m = (int)floor($cooldownRemaining/60); $s=$cooldownRemaining%60; ?>
          <div class="alert alert-danger alert-dismissible fade show" id="loginCooldownAlert">
            Too many failed login attempts for this email. Please try again in
            <strong><span id="loginCooldownTimer" data-remaining="<?php echo (int)$cooldownRemaining; ?>"><?php echo $m; ?>m <?php echo $s; ?>s</span></strong>.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php $errors = []; // suppress duplicate static errors when showing dynamic countdown ?>
        <?php endif; ?>

        <?php foreach ($errors as $e): ?>
          <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($e); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endforeach; ?>
        <?php if ($emailNotRegistered): ?>
          <div class="alert alert-info alert-dismissible fade show">
            Email not registered. <a href="register.php" class="alert-link">Register here</a>.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

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
          Need help with your account? <a href="support_contact.php" class="fw-semibold text-decoration-none">Contact Support</a>.
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
<?php if ($cooldownRemaining > 0): ?>
<script>
(function(){
  const el = document.getElementById('loginCooldownTimer');
  if (!el) return;
  let rem = parseInt(el.getAttribute('data-remaining'),10) || 0;
  function tick(){
    if (rem <= 0) return;
    rem -= 1;
    const m = Math.floor(rem/60);
    const s = rem % 60;
    el.textContent = m + 'm ' + s + 's';
    if (rem > 0) setTimeout(tick, 1000);
  }
  setTimeout(tick, 1000);
})();
</script>
<?php endif; ?>