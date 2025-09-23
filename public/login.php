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

// Simple per-IP throttle (5 failed attempts -> 15 minutes cooldown)
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$THROTTLE_MAX_ATTEMPTS = 5;
$THROTTLE_COOLDOWN_SEC = 15 * 60; // 15 minutes
$throttleDir = __DIR__ . '/../uploads/.throttle';
if (!is_dir($throttleDir)) { @mkdir($throttleDir, 0777, true); }
$throttleFile = $throttleDir . '/' . md5($ip) . '.json';
$throttle = [ 'attempts' => 0, 'blocked_until' => 0 ];
if (is_file($throttleFile)) {
  $raw = @file_get_contents($throttleFile);
  if ($raw) {
    $data = json_decode($raw, true);
    if (is_array($data)) {
      $throttle['attempts'] = (int)($data['attempts'] ?? 0);
      $throttle['blocked_until'] = (int)($data['blocked_until'] ?? 0);
    }
  }
}

// If blocked, show remaining time and skip processing
$cooldownRemaining = 0;
if (time() < $throttle['blocked_until']) {
  $cooldownRemaining = max(0, $throttle['blocked_until'] - time());
  $mins = (int)floor($cooldownRemaining / 60);
  $secs = $cooldownRemaining % 60;
  $errors[] = 'Too many failed login attempts. Please try again in ' . $mins . 'm ' . $secs . 's.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
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
      $currentAttempts = (int)$throttle['attempts'];
      $newAttempts = $currentAttempts + 1;
      if ($newAttempts >= $THROTTLE_MAX_ATTEMPTS) {
        $throttle['blocked_until'] = time() + $THROTTLE_COOLDOWN_SEC;
        $throttle['attempts'] = 0; // reset after applying cooldown
        @file_put_contents($throttleFile, json_encode($throttle));
        $cooldownRemaining = $THROTTLE_COOLDOWN_SEC;
        $mins = (int)floor($THROTTLE_COOLDOWN_SEC / 60);
        $errors[] = 'Too many failed login attempts. Please try again in ' . $mins . 'm 0s.';
      } else {
        $throttle['attempts'] = $newAttempts;
        @file_put_contents($throttleFile, json_encode($throttle));
        $remaining = $THROTTLE_MAX_ATTEMPTS - $newAttempts;
        $errors[] = 'Invalid email or password. Attempt ' . $newAttempts . ' of ' . $THROTTLE_MAX_ATTEMPTS . ' before cooldown (' . $remaining . ' remaining).';
      }
    } else {
      // Employer gating
      if (strtolower((string)$row['role']) === 'employer') {
        $estatus = trim((string)($row['employer_status'] ?? 'Pending'));
        if (strcasecmp($estatus, 'Approved') !== 0) {
          $errors[] = 'Employer account not approved (status: '.$estatus.').';
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
            $errors[] = 'Your PWD ID is pending admin verification.';
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

        // Clear throttle on successful login
        if (is_file($throttleFile)) { @unlink($throttleFile); }

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
            Too many failed login attempts. Please try again in
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