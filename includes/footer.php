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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-dismiss flash alerts
document.querySelectorAll('.alert.auto-dismiss').forEach(function(el) {
  setTimeout(function() {
    try { bootstrap.Alert.getOrCreateInstance(el).close(); } catch (e) {}
  }, 4000);
});
</script>
</body>
</html>