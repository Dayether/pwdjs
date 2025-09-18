<?php
require_once '../config/config.php';
require_once '../classes/Helpers.php';

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="card border-0 shadow-sm">
  <div class="card-body p-4">
    <h2 class="h5 fw-semibold mb-3"><i class="bi bi-life-preserver me-2"></i>Contact Support</h2>
    <p class="text-muted">If your account was suspended or you need assistance, reach us using the details below.</p>

    <div class="row g-3">
      <div class="col-md-6">
        <div class="border rounded p-3 h-100">
          <div class="fw-semibold mb-1"><i class="bi bi-envelope me-2"></i>Email</div>
          <div><a href="mailto:support@example.com">support@example.com</a></div>
          <small class="text-muted">We typically reply within 1–2 business days.</small>
        </div>
      </div>
      <div class="col-md-6">
        <div class="border rounded p-3 h-100">
          <div class="fw-semibold mb-1"><i class="bi bi-chat-dots me-2"></i>Message</div>
          <div>
            <a class="btn btn-outline-primary btn-sm" href="mailto:support@example.com?subject=PWDJS%20Support%20Request">
              <i class="bi bi-send me-1"></i>Send a message
            </a>
          </div>
          <small class="text-muted d-block mt-2">Include your account email and any relevant screenshots.</small>
        </div>
      </div>
    </div>

    <div class="alert alert-secondary mt-3 mb-0">
      Tip: If you’re an employer and can’t post jobs due to status, please mention your company name and any verification documents you have submitted.
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>