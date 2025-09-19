<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';

$prefillName  = $_SESSION['name'] ?? '';
$prefillEmail = $_SESSION['email'] ?? '';

$errors = [];
$success = false;

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
            $subject = $message = '';
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
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h2 class="h4 fw-semibold mb-3"><i class="bi bi-life-preserver me-2"></i>Contact Support</h2>
        <p class="text-muted small mb-4">
          Having trouble with your employer account or need assistance? Send us a message and we’ll get back to you.
        </p>

        <?php if ($success): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>Your support request has been submitted. We will reach out via email.
            <button type="button" class="btn btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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
            <input name="name" type="text" class="form-control" required value="<?php echo htmlspecialchars($_POST['name'] ?? $prefillName); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? $prefillEmail); ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Subject</label>
            <input name="subject" type="text" maxlength="150" class="form-control" required value="<?php echo htmlspecialchars($_POST['subject'] ?? ($subject ?? '')); ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Message</label>
            <textarea name="message" rows="6" class="form-control" required><?php echo htmlspecialchars($_POST['message'] ?? ($message ?? '')); ?></textarea>
          </div>
          <div class="col-12 d-grid">
            <button class="btn btn-primary btn-lg"><i class="bi bi-envelope-paper me-1"></i>Submit Request</button>
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