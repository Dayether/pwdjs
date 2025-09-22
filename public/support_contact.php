<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/Mail.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* WAG i-store ang page na ito as last_page (excluded sa Helpers)
   kaya walang Helpers::storeLastPage() dito para hindi ma-overwrite ang previous. */

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

/* Existing resolver (HINDI inalis) */
function resolve_back_url_original(): ?string {
    $candidate = $_GET['return'] ?? '';
    if ($candidate === '' && !empty($_SERVER['HTTP_REFERER'])) {
        $candidate = $_SERVER['HTTP_REFERER'];
    }
    if ($candidate === '') return null;
    $parsed = parse_url($candidate);
    if (!empty($parsed['host']) && $parsed['host'] !== ($_SERVER['HTTP_HOST'] ?? '')) {
        return null;
    }
    $path = $parsed['path'] ?? '';
    if ($path === '') return null;
    $selfName = basename($_SERVER['PHP_SELF']);
    if (basename($path) === $selfName) return null;
    if (strpos($path, '..') !== false) return null;
    $relative = ltrim($path, '/');
    if (isset($parsed['query'])) {
        $relative .= '?' . $parsed['query'];
    }
    return $relative === '' ? null : $relative;
}

/* =========================================================
   Normalizer para alisin ang duplicated leading segments
   Example:
     pwdjs/public/login.php  -> login.php
     public/login.php        -> login.php
     pwdjs/login.php         -> login.php
     /pwdjs/public/login.php -> login.php
   ========================================================= */
function normalize_relative_path(?string $p): ?string {
    if (!$p) return null;
    $query = '';
    if (str_contains($p, '?')) {
        [$pathOnly, $query] = explode('?', $p, 2);
    } else {
        $pathOnly = $p;
    }
    $pathOnly = ltrim($pathOnly, '/');

    $prefixes = [
        'pwdjs/public/',
        'public/',
        'pwdjs/'
    ];
    foreach ($prefixes as $pref) {
        if (stripos($pathOnly, $pref) === 0) {
            $pathOnly = substr($pathOnly, strlen($pref));
            // Remove a second accidental prefix
            foreach ($prefixes as $pref2) {
                if (stripos($pathOnly, $pref2) === 0) {
                    $pathOnly = substr($pathOnly, strlen($pref2));
                }
            }
            break;
        }
    }

    $self = basename($_SERVER['PHP_SELF'] ?? 'support_contact.php');
    if (basename($pathOnly) === $self) {
        return null;
    }

    if ($pathOnly === '' || $pathOnly === '.') return null;
    if (strpos($pathOnly, '..') !== false) return null;

    $final = $pathOnly;
    if ($query !== '') $final .= '?' . $query;
    return $final;
}

/* =========================================================
   Enhanced resolver (wraps original then normalizes).
   Order:
     1. return param / HTTP_REFERER
     2. session last_page
     3. index.php
   ========================================================= */
function resolve_back_url_enhanced(): string {
    $candidate = resolve_back_url_original();
    $norm = normalize_relative_path($candidate);
    if ($norm === null && !empty($_SESSION['last_page'])) {
        $lp = normalize_relative_path($_SESSION['last_page']);
        if ($lp !== null) $norm = $lp;
    }
    if ($norm === null) $norm = 'index.php';
    return $norm;
}

$backUrl = resolve_back_url_enhanced();

/* Prefill subject */
$incomingSubject = trim($_GET['subject'] ?? '');

/* Handle submit */
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

      // Log original ticket as first "user" entry (if replies table exists)
      try {
        $stmt2 = $pdo->prepare("INSERT INTO support_ticket_replies (ticket_id, sender_role, sender_user_id, message, email_sent) VALUES (?,?,?,?,0)");
        $stmt2->execute([$ticket_id, 'user', $_SESSION['user_id'] ?? null, $message]);
      } catch (Throwable $e) { /* ignore if table absent */ }

      // Send acknowledgment email (if enabled)
      if (Mail::isEnabled()) {
        $ackSubject = 'Support Ticket Received: ' . $subject . ' (' . $ticket_id . ')';
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeSubj = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $preview  = nl2br(htmlspecialchars(mb_strimwidth($message, 0, 800, '...'), ENT_QUOTES, 'UTF-8'));
        $htmlAck = '<p>Hi ' . $safeName . ',</p>'
          . '<p>We received your support request with subject: <strong>' . $safeSubj . '</strong>.</p>'
          . '<p><strong>Ticket ID:</strong> ' . $ticket_id . '</p>'
          . '<p>Below is a copy of your message:</p>'
          . '<blockquote style="border-left:4px solid #0d6efd;padding:6px 10px;background:#f8f9fa">' . $preview . '</blockquote>'
          . '<p style="font-size:12px;color:#666">Our team will review and reply. Please keep the Ticket ID for reference.</p>';
        $altAck = "We received your support ticket (ID: $ticket_id)\nSubject: $subject\n\n" . mb_strimwidth($message, 0, 800, '...');
        Mail::send($email, $name, $ackSubject, $htmlAck, $altAck);
      }
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

    <div class="d-flex justify-content-between align-items-center mb-3">
      <?php if ($backUrl): ?>
        <a href="<?php echo htmlspecialchars($backUrl); ?>"
           class="btn btn-outline-secondary btn-sm"
           id="backBtn"
           data-original="<?php echo htmlspecialchars($backUrl); ?>">
          <i class="bi bi-arrow-left me-1"></i>Back
        </a>
      <?php else: ?>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="backBtn" data-fallback="index.php">
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
document.querySelectorAll('.alert.auto-dismiss').forEach(el=>{
  setTimeout(()=>{ try { bootstrap.Alert.getOrCreateInstance(el).close(); } catch(e){} },4500);
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

// Back button JS fallback to clean duplicated segments (pwdjs/public/pwdjs/public/)
const backBtn = document.getElementById('backBtn');
if (backBtn) {
  if (backBtn.tagName === 'A') {
    let href = backBtn.getAttribute('href') || '';
    if (/pwdjs\/public\/.*pwdjs\/public\//i.test(href)) {
      const idx = href.toLowerCase().lastIndexOf('pwdjs/public/');
      if (idx !== -1) {
        href = href.substring(idx + 'pwdjs/public/'.length);
      }
      href = href.replace(/^public\//i,'').replace(/^pwdjs\//i,'');
      if (href === '' || href === 'support_contact.php') {
        href = 'index.php';
      }
      backBtn.setAttribute('href', href);
    }
  } else if (backBtn.tagName === 'BUTTON') {
    backBtn.addEventListener('click', function() {
      if (history.length > 1) history.back();
      else window.location.href = this.dataset.fallback || 'index.php';
    });
  }
}
</script>