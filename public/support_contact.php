<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';

$prefillName  = $_SESSION['name'] ?? '';
$prefillEmail = $_SESSION['email'] ?? '';

$errors = [];
$success = false;

// Build safe back URL (same pattern as job_view)
function resolve_back_url(string $fallback = 'index.php'): string {
    $raw = $_GET['return'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    if (!$raw) return $fallback;
    $p = parse_url($raw);
    // Reject if external (has scheme or host) or contains ..
    if (isset($p['scheme']) || isset($p['host'])) return $fallback;
    $path = $p['path'] ?? '';
    if ($path === '' || strpos($path, '..') !== false) return $fallback;
    $url = ltrim($path, '/');
    if (!empty($p['query'])) $url .= '?' . $p['query'];
    return $url;
}
$backUrl = resolve_back_url('index.php');

// Optional preselect subject via query (?subject=Account%20Suspension)
$incomingSubject = trim($_GET['subject'] ?? '');

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
                ':message'   => $message,
            ]);
            $success = true;
            $prefillName  = $name;
            $prefillEmail = $email;
            $incomingSubject = ''; // clear selected subject after success
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
      <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
      </a>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h2 class="h4 fw-semibold mb-3"><i class="bi bi-life-preserver me-2"></i>Contact Support</h2>
        <p class="text-muted small mb-4">
          Having trouble with your employer account or need assistance? Send us a message and we’ll get back to you.
        </p>

        <?php if ($success): ?>
          <div class="alert alert-success alert-dismissible fade show auto-dismiss" role="alert">
            <i class="bi bi-check-circle me-2"></i>Your support request has been submitted. We will reach out via email.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php foreach ($errors as $e): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($e); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endforeach; ?>

        <form method="post" class="row g-3">
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
            <?php
              $subjects = [
                'General Inquiry',
                'Account Suspension',
                'Employer Verification',
                'Bug / Technical Issue',
                'Feature Request',
                'Other'
              ];
              $selectedSubject = $success ? '' : ($incomingSubject ?: ($_POST['subject'] ?? ''));
            ?>
            <select name="subject" class="form-select" required>
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
            <textarea name="message" rows="6" class="form-control" required><?php
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
        <div class="small text-muted">
          For suspended employer accounts, include company name and any reference numbers to speed up verification.
        </div>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
// Optional: auto-dismiss success
document.querySelectorAll('.alert.auto-dismiss').forEach(function(el){
  setTimeout(()=>{ try { bootstrap.Alert.getOrCreateInstance(el).close(); } catch(e){} }, 4500);
});
</script>