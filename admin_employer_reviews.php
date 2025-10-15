<?php
require_once 'config/config.php';
require_once 'classes/Helpers.php';
require_once 'classes/Database.php';

Helpers::requireLogin();
if (($_SESSION['role'] ?? '') !== 'admin') {
    Helpers::redirect('index.php');
    exit;
}

$pdo = Database::getConnection();

// Actions: approve/reject/delete
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $a = strtolower(trim((string)$_GET['action']));
    if ($id > 0) {
        if ($a === 'approve' || $a === 'reject') {
            $status = $a === 'approve' ? 'Approved' : 'Rejected';
            $st = $pdo->prepare("UPDATE employer_reviews SET status=? WHERE id=? LIMIT 1");
            $st->execute([$status, $id]);
            Helpers::flash('msg', 'Review '.$status.'.');
        } elseif ($a === 'delete') {
            $st = $pdo->prepare("DELETE FROM employer_reviews WHERE id=? LIMIT 1");
            $st->execute([$id]);
            Helpers::flash('msg', 'Review deleted.');
        }
    }
    Helpers::redirect('admin_employer_reviews.php');
    exit;
}

$filter = $_GET['status'] ?? 'Pending';
$allowed = ['Pending','Approved','Rejected','All'];
if (!in_array($filter, $allowed, true)) $filter = 'Pending';

$where = '';$params=[];
if ($filter !== 'All') { $where = 'WHERE r.status = ?'; $params[] = $filter; }

$sql = "SELECT r.*, u.company_name, u.name AS employer_name, ru.name AS reviewer_name, ru.email AS reviewer_email
        FROM employer_reviews r
        LEFT JOIN users u ON u.user_id = r.employer_id
        LEFT JOIN users ru ON ru.user_id = r.reviewer_user_id
        $where
        ORDER BY r.created_at DESC
        LIMIT 300";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

include 'includes/header.php';
?>
<div class="admin-layout">
  <?php include 'includes/admin_sidebar.php'; ?>
  <div class="admin-main">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="h6 fw-semibold mb-0"><i class="bi bi-chat-left-text me-2"></i>Employer Reviews</h2>
      <div>
        <form method="get" class="d-inline">
          <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach ($allowed as $opt): ?>
              <option value="<?= htmlspecialchars($opt); ?>" <?php if ($filter===$opt) echo 'selected'; ?>><?= htmlspecialchars($opt); ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

    <?php if (!empty($_SESSION['flash']['msg'])): ?>
      <div class="alert alert-info py-2 px-3 mb-3"><i class="bi bi-info-circle me-2"></i><?= htmlspecialchars($_SESSION['flash']['msg']); unset($_SESSION['flash']['msg']); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Employer</th>
              <th>Reviewer</th>
              <th>Rating</th>
              <th>Comment</th>
              <th>Status</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No reviews found.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['id']; ?></td>
              <td>
                <div class="small fw-semibold"><?= htmlspecialchars($r['company_name'] ?: $r['employer_name'] ?: $r['employer_id']); ?></div>
                <div class="text-muted small">#<?= htmlspecialchars($r['employer_id']); ?></div>
              </td>
              <td>
                <div class="small"><?= htmlspecialchars($r['reviewer_name'] ?: 'User'); ?></div>
                <div class="text-muted small"><?= htmlspecialchars($r['reviewer_email'] ?: ''); ?></div>
              </td>
              <td><?= (int)$r['rating']; ?>/5</td>
              <td style="max-width:340px">
                <div class="small text-truncate" title="<?= htmlspecialchars($r['comment'] ?: ''); ?>"><?= htmlspecialchars(mb_strimwidth((string)($r['comment'] ?: ''), 0, 120, '…')); ?></div>
              </td>
              <td><span class="badge text-bg-<?php echo $r['status']==='Approved'?'success':($r['status']==='Rejected'?'secondary':'warning'); ?>"><?= htmlspecialchars($r['status']); ?></span></td>
              <td class="text-nowrap small"><?= htmlspecialchars(date('M d, Y H:i', strtotime($r['created_at']))); ?></td>
              <td class="text-nowrap">
                <div class="btn-group btn-group-sm">
                  <a class="btn btn-outline-success" href="admin_employer_reviews.php?action=approve&id=<?= (int)$r['id']; ?>">Approve</a>
                  <a class="btn btn-outline-warning" href="admin_employer_reviews.php?action=reject&id=<?= (int)$r['id']; ?>">Reject</a>
                  <a class="btn btn-outline-danger" href="admin_employer_reviews.php?action=delete&id=<?= (int)$r['id']; ?>" data-confirm-title="Delete Review" data-confirm="Delete this review?" data-confirm-yes="Delete" data-confirm-no="Cancel">Delete</a>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
