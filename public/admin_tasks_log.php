<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';

Helpers::requireRole('admin');

$pdo = Database::getConnection();
$task = trim($_GET['task'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = '1=1';
$params = [];
if ($task !== '') { $where .= ' AND task = ?'; $params[] = $task; }

$total = 0;
try {
  $st = $pdo->prepare("SELECT COUNT(*) FROM admin_tasks_log WHERE $where");
  $st->execute($params);
  $total = (int)$st->fetchColumn();
} catch (Throwable $e) { $total = 0; }

$rows = [];
try {
  $sql = "SELECT id, task, actor_user_id, mode, users_scanned, users_updated, jobs_scanned, jobs_updated, created_at FROM admin_tasks_log WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
  $st = $pdo->prepare($sql);
  $i = 1;
  foreach ($params as $p) { $st->bindValue($i++, $p); }
  $st->bindValue($i++, $perPage, PDO::PARAM_INT);
  $st->bindValue($i++, $offset, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $rows = []; }

include '../includes/header.php';
?>
<div class="admin-layout">
  <?php $currentPage='admin_tasks_log.php'; include '../includes/admin_sidebar.php'; ?>
  <div class="admin-main">
    <div class="dash-header" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem;flex-wrap:wrap;">
      <h2 style="font-size:1.05rem;font-weight:600;letter-spacing:.5px;display:flex;align-items:center;gap:.55rem;margin:0"><i class="bi bi-clipboard-data"></i> Admin Tasks Log</h2>
      <div class="d-flex gap-2">
        <a href="admin_normalize_disabilities_ui.php" class="btn btn-sm btn-outline-light"><i class="bi bi-braces me-1"></i>Normalize</a>
      </div>
    </div>

    <div class="section-card" style="border:1px solid rgba(255,255,255,.08);background:#101a2b;border-radius:16px;padding:1.15rem 1.25rem;margin-bottom:1.4rem;box-shadow:0 4px 18px -10px rgba(0,0,0,.55);">
      <div class="section-title">Filters</div>
      <form class="row g-2 align-items-end" method="get">
        <div class="col-md-4">
          <label class="form-label small">Task</label>
          <select name="task" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="normalize_disabilities" <?php echo $task==='normalize_disabilities'?'selected':''; ?>>Normalize Disabilities</option>
          </select>
        </div>
        <div class="col-auto"><button class="btn btn-sm btn-primary">Apply</button></div>
        <div class="col-auto"><a class="btn btn-sm btn-outline-secondary" href="admin_tasks_log.php">Reset</a></div>
      </form>
    </div>

    <div class="table-wrapper" style="position:relative;border:1px solid rgba(255,255,255,.06);background:#0f1827;border-radius:16px;overflow:hidden;box-shadow:0 6px 22px -12px rgba(0,0,0,.65)">
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-dark"><tr>
            <th>ID</th>
            <th>Task</th>
            <th>Actor</th>
            <th>Mode</th>
            <th>Users</th>
            <th>Jobs</th>
            <th>Timestamp</th>
          </tr></thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-center text-secondary">No logs found.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?php echo (int)$r['id']; ?></td>
              <td><?php echo htmlspecialchars($r['task']); ?></td>
              <td><?php echo htmlspecialchars($r['actor_user_id']); ?></td>
              <td><span class="badge bg-secondary"><?php echo htmlspecialchars($r['mode']); ?></span></td>
              <td><span class="badge bg-info text-dark"><?php echo (int)$r['users_updated']; ?></span> / <?php echo (int)$r['users_scanned']; ?></td>
              <td><span class="badge bg-info text-dark"><?php echo (int)$r['jobs_updated']; ?></span> / <?php echo (int)$r['jobs_scanned']; ?></td>
              <td><?php echo htmlspecialchars($r['created_at']); ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php $pages = max(1, (int)ceil($total / $perPage)); if ($pages>1): ?>
    <nav aria-label="Pagination"><ul class="pagination">
      <?php $qs=$_GET; for($p=1;$p<=$pages;$p++): $qs['p']=$p; $url='admin_tasks_log.php?'.http_build_query($qs); ?>
        <li class="page-item <?php echo $p==$page?'active':''; ?>"><a class="page-link" href="<?php echo htmlspecialchars($url); ?>"><?php echo $p; ?></a></li>
      <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
