<?php
require_once 'config/config.php';
require_once 'classes/Helpers.php';
require_once 'classes/Database.php';

Helpers::requireLogin();
if (($_SESSION['role'] ?? '') !== 'admin') {
    Helpers::redirect('index.php');
    exit;
}

// Small helpers
function _safe_truncate(string $s, int $width = 120, string $end = '…'): string {
  if (function_exists('mb_strimwidth')) {
    return mb_strimwidth($s, 0, $width, $end);
  }
  if (strlen($s) <= $width) return $s;
  return substr($s, 0, max(0, $width - strlen($end))) . $end;
}

function _table_exists(PDO $pdo, string $table): bool {
  // Fast path that doesn't require information_schema privileges
  try {
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    if ($stmt->fetchColumn()) return true;
  } catch (Throwable $e) {
    // ignore and try fallback
  }
  // Fallback #1: simple probe
  try {
    $pdo->query("SELECT 1 FROM `".$table."` LIMIT 1");
    return true;
  } catch (Throwable $e) {
    // ignore and try fallback #2
  }
  // Fallback #2: information_schema (may be restricted on shared hosts)
  try {
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $stmt->execute([DB_NAME, $table]);
    return (bool)$stmt->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
}

$pdo = Database::getConnection();
$tableError = null;
$loadError = null;
$hasTable = false;
try {
  $pdo->query('SELECT 1 FROM `employer_reviews` LIMIT 1');
  $hasTable = true;
} catch (Throwable $e) {
  $hasTable = false;
}

// Handle create table (self-heal) action
if (!$hasTable && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_table') {
  // Optional CSRF; only admin can be here
  if (!Helpers::verifyCsrf($_POST['csrf'] ?? '')) {
    Helpers::flash('msg', 'Invalid session token. Please refresh and try again.');
    Helpers::redirect('admin_employer_reviews.php');
    exit;
  }
  try {
    $sqlCreate = "
CREATE TABLE IF NOT EXISTS employer_reviews (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employer_id VARCHAR(32) NOT NULL,
  reviewer_user_id VARCHAR(32) NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment TEXT NULL,
  status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_employer_created (employer_id, created_at),
  KEY idx_employer_status (employer_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
    $pdo->exec($sqlCreate);
    Helpers::flash('msg', 'employer_reviews table has been created.');
    Helpers::redirect('admin_employer_reviews.php');
    exit;
  } catch (Throwable $e) {
    Helpers::flash('msg', 'Failed to create employer_reviews table: ' . $e->getMessage());
    Helpers::redirect('admin_employer_reviews.php');
    exit;
  }
}

// Actions: approve/reject/delete (wrapped to avoid fatal if table missing)
if (isset($_GET['action'], $_GET['id'])) {
  try {
    $id = (int)$_GET['id'];
    $a = strtolower(trim((string)$_GET['action']));
    $backStatus = $_GET['status'] ?? 'All';
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
  } catch (Throwable $e) {
    Helpers::flash('msg', 'Employer reviews table not found. Please run migration 20251002_employer_reviews.sql.');
  }
  Helpers::redirect('admin_employer_reviews.php?status=' . urlencode($backStatus));
  exit;
}

$filter = $_GET['status'] ?? 'All';
$allowed = ['Pending','Approved','Rejected','All'];
if (!in_array($filter, $allowed, true)) $filter = 'All';

$where = '';$params=[];
if ($filter !== 'All') { $where = 'WHERE r.status = ?'; $params[] = $filter; }

// Load rows (catch missing table)
$rows = [];
$debugMsg = null;
if (!$hasTable) {
  $tableError = 'Employer reviews table not found. Please apply migration file config/migrations/20251002_employer_reviews.sql to your database.';
} else {
  try {
    // Note: Some hosts create employer_reviews with utf8mb4_uca1400_ai_ci while users.user_id is utf8mb4_general_ci.
    // Joining across different collations can throw "Illegal mix of collations" errors. We coerce the comparison to a common collation.
    $sql = "SELECT r.*, u.company_name, u.name AS employer_name, ru.name AS reviewer_name, ru.email AS reviewer_email
            FROM employer_reviews r
            LEFT JOIN users u ON u.user_id = r.employer_id COLLATE utf8mb4_general_ci
            LEFT JOIN users ru ON ru.user_id = r.reviewer_user_id COLLATE utf8mb4_general_ci
            $where
            ORDER BY r.created_at DESC
            LIMIT 300";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
      $debugMsg = 'Join query failed: ' . $e->getMessage();
    }
    // Fallback without JOINs to at least render the raw rows
    try {
      $sql = "SELECT r.* FROM employer_reviews r $where ORDER BY r.created_at DESC LIMIT 300";
      $st = $pdo->prepare($sql);
      $st->execute($params);
      $baseRows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
      // Optionally enrich reviewer and employer names using separate lookups (avoids collation join issues)
      $rows = $baseRows;
      if ($rows) {
        $empIds = [];$revIds = [];
        foreach ($rows as $r) { $empIds[$r['employer_id']] = true; if (!empty($r['reviewer_user_id'])) { $revIds[$r['reviewer_user_id']] = true; } }
        $empMap = [];$revMap = [];
        if ($empIds) {
          $in = str_repeat('?,', count($empIds)); $in = rtrim($in, ',');
          $st2 = $pdo->prepare("SELECT user_id, company_name, name FROM users WHERE user_id IN ($in)");
          $st2->execute(array_keys($empIds));
          foreach ($st2->fetchAll(PDO::FETCH_ASSOC) as $u) { $empMap[$u['user_id']] = $u; }
        }
        if ($revIds) {
          $in = str_repeat('?,', count($revIds)); $in = rtrim($in, ',');
          $st3 = $pdo->prepare("SELECT user_id, name, email FROM users WHERE user_id IN ($in)");
          $st3->execute(array_keys($revIds));
          foreach ($st3->fetchAll(PDO::FETCH_ASSOC) as $u) { $revMap[$u['user_id']] = $u; }
        }
        foreach ($rows as &$r) {
          $eu = $empMap[$r['employer_id']] ?? null;
          $ru = !empty($r['reviewer_user_id']) ? ($revMap[$r['reviewer_user_id']] ?? null) : null;
          $r['company_name'] = $eu['company_name'] ?? null;
          $r['employer_name'] = $eu['name'] ?? null;
          $r['reviewer_name'] = $ru['name'] ?? null;
          $r['reviewer_email'] = $ru['email'] ?? null;
        }
        unset($r);
      }
      // Surface a soft note for admins if a join issue was detected
      $loadError = null; // clear the hard error since we have data
    } catch (Throwable $e2) {
      $loadError = 'Failed to load reviews. Please retry or contact support.';
    }
  }
}

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

    <?php if ($tableError): ?>
      <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($tableError); ?></div>
      <div class="card">
        <div class="card-body small">
          <p class="mb-1">Quick fix:</p>
          <ol class="mb-0">
            <li>Open <code>config/migrations/20251002_employer_reviews.sql</code>.</li>
            <li>Run the SQL against your live site database (same DB used by this app).</li>
            <li>Refresh this page.</li>
          </ol>
          <hr>
          <form method="post" class="d-inline">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(Helpers::csrfToken()); ?>">
            <input type="hidden" name="action" value="create_table">
            <button class="btn btn-sm btn-primary"><i class="bi bi-hammer me-1"></i>Create table now</button>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!$tableError && $loadError): ?>
      <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2"></i><?= htmlspecialchars($loadError); ?></div>
    <?php endif; ?>

    <?php if (!$tableError && $debugMsg): ?>
      <div class="alert alert-warning small"><i class="bi bi-bug me-2"></i><?= htmlspecialchars($debugMsg); ?></div>
    <?php endif; ?>

    <?php if (!$tableError && !$rows): ?>
      <div class="alert alert-secondary py-2 px-3 mb-3"><i class="bi bi-info-circle me-2"></i>No <?= htmlspecialchars($filter === 'All' ? '' : $filter.' '); ?>reviews found.</div>
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
          <?php if (!$rows && !$tableError): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No reviews found.</td></tr>
          <?php elseif(!$tableError): foreach ($rows as $r): ?>
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
                <div class="small text-truncate" title="<?= htmlspecialchars($r['comment'] ?: ''); ?>"><?= htmlspecialchars(_safe_truncate((string)($r['comment'] ?: ''), 120, '…')); ?></div>
              </td>
              <td><span class="badge text-bg-<?php echo $r['status']==='Approved'?'success':($r['status']==='Rejected'?'secondary':'warning'); ?>"><?= htmlspecialchars($r['status']); ?></span></td>
              <td class="text-nowrap small"><?= htmlspecialchars(date('M d, Y H:i', strtotime($r['created_at']))); ?></td>
              <td class="text-nowrap">
                <div class="btn-group btn-group-sm">
                  <a class="btn btn-outline-success" href="admin_employer_reviews.php?action=approve&id=<?= (int)$r['id']; ?>&status=<?= urlencode($filter); ?>">Approve</a>
                  <a class="btn btn-outline-warning" href="admin_employer_reviews.php?action=reject&id=<?= (int)$r['id']; ?>&status=<?= urlencode($filter); ?>"><?php echo ($r['status']==='Approved'?'Hide':'Reject'); ?></a>
                  <a class="btn btn-outline-danger" href="admin_employer_reviews.php?action=delete&id=<?= (int)$r['id']; ?>&status=<?= urlencode($filter); ?>" data-confirm-title="Delete Review" data-confirm="Delete this review?" data-confirm-yes="Delete" data-confirm-no="Cancel">Delete</a>
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
