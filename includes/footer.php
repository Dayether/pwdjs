    </main><!-- /main content area -->

    <footer class="footer bg-dark text-white-50 py-4 mt-auto">
      <div class="container d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
        <div class="small">
          <span class="text-white-75">PWD Employment &amp; Skills Portal</span> &middot;
          <span>&copy; <?php echo date('Y'); ?></span>
        </div>
        <div class="small d-flex flex-wrap align-items-center">
          <?php if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'employer'): ?>
            <a class="link-light link-underline-opacity-0 me-3" href="jobs_create.php">Post a Job</a>
          <?php endif; ?>

          <?php if (!empty($_SESSION['user_id'])): ?>
            <a class="link-light link-underline-opacity-0 me-3 d-inline-flex align-items-center" href="support_contact.php">
              <i class="bi bi-life-preserver me-1"></i>Support
            </a>
          <?php endif; ?>

          <a class="link-light link-underline-opacity-0 me-3" href="security_privacy.php">Security &amp; Privacy</a>
          <a class="link-light link-underline-opacity-0" href="terms.php">Terms &amp; Conditions</a>
        </div>
      </div>
      <div class="container mt-3 small text-white-50">
       
      </div>
    </footer>

    <!-- Global confirmation modal (kept) -->
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

</div><!-- /page-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* Auto-dismiss flash alerts */
document.querySelectorAll('.alert.auto-dismiss').forEach(el=>{
  setTimeout(()=>{ try { bootstrap.Alert.getOrCreateInstance(el).close(); } catch(e){} },4000);
});

/* Global confirmation handler */
(function(){
  const modalEl = document.getElementById('confirmModal');
  if (!modalEl) return;
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  let targetHref = null;

  document.addEventListener('click', function(e){
    const link = e.target.closest('a[data-confirm]');
    if (!link) return;
    e.preventDefault();
    targetHref = link.getAttribute('href');

    modalEl.querySelector('#confirmModalLabel').textContent =
      link.getAttribute('data-confirm-title') || 'Confirm';
    modalEl.querySelector('#confirmModalMessage').textContent =
      link.getAttribute('data-confirm') || 'Are you sure?';
    modalEl.querySelector('#confirmModalYes').textContent =
      link.getAttribute('data-confirm-yes') || 'Yes';
    modalEl.querySelector('#confirmModalNo').textContent =
      link.getAttribute('data-confirm-no') || 'Cancel';
    modal.show();
  });

  modalEl.querySelector('#confirmModalYes').addEventListener('click', ()=>{
    if (targetHref) window.location.href = targetHref;
  });
})();
</script>
</body>
</html>