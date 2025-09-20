<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';

session_start();

$prefillName  = $_SESSION['name']  ?? '';
$prefillEmail = $_SESSION['email'] ?? '';
$userRole     = $_SESSION['role']  ?? null;

$errors  = [];
$success = false;

$subjects = [
    'Account Suspension',
    'Login Issue',
    'Password Reset Problem',
    'Employer Verification',
    'Job Posting Issue',
    'Job Application Issue',
    'Profile / Resume Issue',
    'Accessibility Feedback',
    'Feature Request',
    'Bug / Technical Issue',
    'Other'
];

// Enhanced safe back URL resolver
function resolve_back_url(): ?string {
    // 1. Explicit return param
    $candidate = $_GET['return'] ?? '';

    // 2. If no return param, use HTTP_REFERER
    if ($candidate === '' && !empty($_SERVER['HTTP_REFERER'])) {
        $candidate = $_SERVER['HTTP_REFERER'];
    }

    if ($candidate === '') {
        return null; // Force JS history fallback later
    }

    // Parse and validate (allow only same host OR relative path)
    $parsed = parse_url($candidate);

    // If full URL with host different from current host => reject
    if (!empty($parsed['host']) && $parsed['host'] !== ($_SERVER['HTTP_HOST'] ?? '')) {
        return null;
    }

    // Build relative path (if scheme/host present but same host we keep path+query)
    $path = $parsed['path'] ?? '';
    if ($path === '') return null;

    // Do not return to the same support page (avoid loop)
    $selfName = basename($_SERVER['PHP_SELF']);
    if (basename($path) === $selfName) {
        return null;
    }

    if (strpos($path, '..') !== false) return null; // basic traversal guard

    $relative = ltrim($path, '/');
    if (isset($parsed['query'])) {
        $relative .= '?' . $parsed['query'];
    }

    // Small safeguard: ensure it ends with .php or no extension (like index)
    // (Optional – you can remove if not needed)
    return $relative === '' ? null : $relative;
}

$backUrl = resolve_back_url();

// Prefill subject via query
$incomingSubject = trim($_GET['subject'] ?? '');

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '')    $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if ($subject === '') $errors[] = 'Subject is required.';
    if ($message === '') $errors[] = 'Message is required.';

    if (!$errors) {
        try {
            $pdo = Database::getConnection();
            $ticket_id = 'TCK-' . bin2hex(random_bytes(5));
            $stmt = $pdo->prepare("
                INSERT INTO support_tickets (ticket_id, user_id, name, email, subject, message, status)
                VALUES (:ticket_id, :user_id, :name, :email, :subject, :message, 'Open')
            ");
            $stmt->execute([
                ':ticket_id' => $ticket_id,
                ':user_id'   => $_SESSION['user_id'] ?? null,
                ':name'      => $name,
                ':email'     => $email,
                ':subject'   => $subject,
                ':message'   => $message
            ]);
            $success         = true;
            $prefillName     = $name;
            $prefillEmail    = $email;
            $incomingSubject = '';
        } catch (Throwable $e) {
            $errors[] = 'Unable to submit your request right now.';
        }
    }
}

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="row justify-content-center">
  <div class="col-lg-9 col-xl-7">

    <!-- Back button -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <?php if ($backUrl): ?>
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-outline-secondary btn-sm" id="backBtn">
          <i class="bi bi-arrow-left me-1"></i>Back
        </a>
      <?php else: ?>
        <!-- JS history fallback -->
        <button type="button" class="btn btn-outline-secondary btn-sm" id="backBtn"
                data-fallback="index.php">
          <i class="bi bi-arrow-left me-1"></i>Back
        </button>
      <?php endif; ?>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h2 class="h4 fw-semibold mb-3">
          <i class="bi bi-life-preserver me-2"></i>Contact Support
        </h2>
        <p class="text-muted small mb-4">
          Need assistance with your account, applications, job posts, or something else? Send us a message and we'll respond as soon as possible.
        </p>

        <?php if ($success): ?>
          <div class="alert alert-success alert-dismissible fade show auto-dismiss" role="alert">
            <i class="bi bi-check-circle me-2"></i>Your support request has been submitted. Please watch your email for an update.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php foreach ($errors as $e): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($e); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endforeach; ?>

        <?php $selectedSubject = $success ? '' : ($incomingSubject ?: ($_POST['subject'] ?? '')); ?>

        <form method="post" class="row g-3" id="supportForm">
          <div class="col-md-6">
            <label class="form-label">Name</label>
            <input name="name" class="form-control" required value="<?php echo htmlspecialchars($prefillName); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control" required value="<?php echo htmlspecialchars($prefillEmail); ?>">
          </div>

          <div class="col-12">
            <label class="form-label">Subject</label>
            <select name="subject" id="subjectSelect" class="form-select" required>
              <option value="" disabled <?php echo $selectedSubject===''?'selected':''; ?>>Select a subject</option>
              <?php foreach ($subjects as $s): ?>
                <option value="<?php echo htmlspecialchars($s); ?>" <?php if ($selectedSubject === $s) echo 'selected'; ?>>
                  <?php echo htmlspecialchars($s); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label">Message</label>
            <textarea name="message" id="messageField" rows="6" class="form-control" required><?php
              echo htmlspecialchars($_POST['message'] ?? ($success ? '' : ''));
            ?></textarea>
          </div>

          <div class="col-12 d-grid">
            <button class="btn btn-primary">
              <i class="bi bi-envelope-paper me-1"></i>Submit Request
            </button>
          </div>
        </form>

        <hr class="my-4">
        <div id="dynamicHelpNote" class="small text-muted">
          Provide as much detail as possible so we can assist you faster.
        </div>

      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
// Auto-dismiss success
document.querySelectorAll('.alert.auto-dismiss').forEach(function(el){
  setTimeout(()=>{ try { bootstrap.Alert.getOrCreateInstance(el).close(); } catch(e){} }, 4500);
});

const subjectSelect = document.getElementById('subjectSelect');
const helpNote      = document.getElementById('dynamicHelpNote');
const userRole      = <?php echo json_encode($userRole); ?>;

function updateHelpNote() {
  const val = subjectSelect.value;
  let txt = 'Provide as much detail as possible so we can assist you faster.';
  switch (val) {
    case 'Account Suspension':
      txt = userRole === 'employer'
        ? 'Include company name, business permit number, and why you believe the suspension is incorrect.'
        : 'Describe what you were doing before the suspension and any warning emails you received.';
      break;
    case 'Employer Verification':
      txt = 'List company name, business permit number, and documents already uploaded.';
      break;
    case 'Job Posting Issue':
      txt = 'Mention the Job Title / Job ID and the exact error or unexpected behavior.';
      break;
    case 'Job Application Issue':
      txt = 'Include Job ID (URL) you applied to and what went wrong (upload failed, status stuck, etc.).';
      break;
    case 'Profile / Resume Issue':
      txt = 'Indicate which section (experience, education, resume upload, video intro) has the problem.';
      break;
    case 'Accessibility Feedback':
      txt = 'Tell us the page/component and assistive tech or device you are using.';
      break;
    case 'Feature Request':
      txt = 'Describe the feature, who benefits, and any similar examples you like.';
      break;
    case 'Bug / Technical Issue':
      txt = 'Provide steps to reproduce, expected vs. actual behavior, browser/device.';
      break;
    case 'Login Issue':
      txt = 'State any error messages, when it started, and your browser/device.';
      break;
    case 'Password Reset Problem':
      txt = 'Tell us if you received the reset email and at which step it failed.';
      break;
    case 'Other':
      txt = 'Explain your concern in detail.';
      break;
  }
  helpNote.textContent = txt;
}

if (subjectSelect) {
  subjectSelect.addEventListener('change', updateHelpNote);
  updateHelpNote();
}

// Back button JS fallback (history.back) if we had no safe URL
const backBtn = document.getElementById('backBtn');
if (backBtn && backBtn.tagName === 'BUTTON') {
  backBtn.addEventListener('click', function(){
    if (window.history.length > 1) {
      window.history.back();
    } else {
      // Fallback if no history
      window.location.href = this.getAttribute('data-fallback') || 'index.php';
    }
  });
}
</script>