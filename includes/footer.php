</div>
<footer class="footer bg-dark text-white-50 mt-auto py-4">
  <div class="container d-flex flex-column flex-md-row align-items-center justify-content-between">
    <div class="small">
      <span class="text-white-75">PWD Employment & Skills Portal</span> ·
      <span>&copy; <?php echo date('Y'); ?></span>
    </div>
    <div class="small">
      <a class="link-light link-underline-opacity-0 me-3" href="index.php">Find Jobs</a>
      <?php if (!empty($_SESSION['user_id']) && $_SESSION['role']==='employer'): ?>
        <a class="link-light link-underline-opacity-0 me-3" href="jobs_create.php">Post a Job</a>
      <?php endif; ?>
      <a class="link-light link-underline-opacity-0" href="profile_edit.php">Profile</a>
    </div>
  </div>
</footer>

<!-- Reusable confirmation modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmModalLabel">Please confirm</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0" id="confirmModalMessage">Are you sure?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="confirmModalNo">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmModalYes">Yes</button>
      </div>
    </div>
  </div>
</div>

<!-- Close sticky page wrapper -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-dismiss flash alerts
document.querySelectorAll('.alert.auto-dismiss').forEach(function(el) {
  setTimeout(function() {
    try { bootstrap.Alert.getOrCreateInstance(el).close(); } catch (e) {}
  }, 4000);
});

// Global confirmation handler: any element with data-confirm will show a modal
(function(){
  const modalEl = document.getElementById('confirmModal');
  const modal = modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
  if (!modalEl || !modal) return;

  const titleEl = modalEl.querySelector('#confirmModalLabel');
  const msgEl = modalEl.querySelector('#confirmModalMessage');
  const yesBtn = modalEl.querySelector('#confirmModalYes');
  const noBtn = modalEl.querySelector('#confirmModalNo');

  let pending = null;

  document.addEventListener('click', function(e){
    const trigger = e.target.closest('[data-confirm]');
    if (!trigger) return;

    // Only act on enabled, visible elements
    if (trigger.disabled) return;

    e.preventDefault();

    // Read attributes
    const title = trigger.getAttribute('data-confirm-title') || 'Please confirm';
    const message = trigger.getAttribute('data-confirm') || trigger.getAttribute('data-confirm-message') || 'Are you sure?';
    const yesText = trigger.getAttribute('data-confirm-yes') || 'Yes';
    const noText = trigger.getAttribute('data-confirm-no') || 'Cancel';
    const method = trigger.getAttribute('data-method') || (trigger.tagName === 'BUTTON' ? 'submit' : 'get');
    const href = trigger.getAttribute('href') || trigger.getAttribute('data-href') || '';

    // Populate modal
    if (titleEl) titleEl.textContent = title;
    if (msgEl) msgEl.textContent = message;
    if (yesBtn) yesBtn.textContent = yesText;
    if (noBtn) noBtn.textContent = noText;

    // Store action
    pending = { trigger, method, href };

    // Show modal
    modal.show();
  });

  if (yesBtn) {
    yesBtn.addEventListener('click', function(){
      if (!pending) return;
      const { trigger, method, href } = pending;
      pending = null;
      modal.hide();

      if (method === 'submit') {
        const form = trigger.closest('form');
        if (form) form.submit();
        return;
      }
      if (method === 'post') {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = href || '#';
        document.body.appendChild(form);
        form.submit();
        return;
      }
      // Default: GET navigation
      if (href) window.location.href = href;
    });
  }
})();
</script>
</body>
</html>