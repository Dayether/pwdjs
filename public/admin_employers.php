<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';

Helpers::requireLogin();
if (($_SESSION['role'] ?? '') !== 'admin') Helpers::redirect('index.php');

$pdo = Database::getConnection();

// List employers (Pending first)
$stmt = $pdo->query("SELECT user_id, name, email, company_name, business_email, business_permit_number, employer_status, employer_doc, created_at
                     FROM users WHERE role='employer' ORDER BY employer_status='Pending' DESC, created_at DESC");
$rows = $stmt->fetchAll();

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="card border-0 shadow-sm">
  <div class="card-body p-4">
    <h2 class="h5 fw-semibold mb-3"><i class="bi bi-shield-lock me-2"></i>Admin · Employers</h2>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead class="table-light">
          <tr>
            <th>Company</th><th>Business Email</th><th>Permit No.</th><th>Status</th><th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td>
                <div class="fw-semibold"><?php echo Helpers::sanitizeOutput($r['company_name'] ?: '(none)'); ?></div>
                <div class="small text-muted"><?php echo Helpers::sanitizeOutput($r['name']); ?> · <?php echo Helpers::sanitizeOutput($r['email']); ?></div>
                <?php if (!empty($r['employer_doc'])): ?>
                  <div class="small"><a target="_blank" href="../<?php echo htmlspecialchars($r['employer_doc']); ?>">View document</a></div>
                <?php endif; ?>
              </td>
              <td><?php echo Helpers::sanitizeOutput($r['business_email'] ?: '(none)'); ?></td>
              <td><?php echo Helpers::sanitizeOutput($r['business_permit_number'] ?: '(none)'); ?></td>
              <td>
                <span class="badge <?php
                  echo $r['employer_status']==='Approved'?'text-bg-success':($r['employer_status']==='Pending'?'text-bg-warning':'text-bg-danger');
                ?>"><?php echo Helpers::sanitizeOutput($r['employer_status']); ?></span>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="admin_employer_view.php?user_id=<?php echo urlencode($r['user_id']); ?>">
                  <i class="bi bi-eye me-1"></i>View
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No employers found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>