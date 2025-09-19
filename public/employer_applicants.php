<?php
/**
 * Employer view: list applicants for a specific job the employer owns.
 * ADD-ONLY file (no removals from existing codebase).
 */
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/Application.php';
require_once '../classes/Taxonomy.php'; // in case education canonical display is needed

Helpers::requireLogin();

if (!Helpers::isEmployer()) {
    Helpers::flash('err','Access denied.');
    Helpers::redirect('index.php');
    exit;
}

$pdo = Database::getConnection();

// Read job_id
$job_id = trim($_GET['job_id'] ?? '');
if ($job_id === '') {
    Helpers::flash('err','Missing job_id.');
    Helpers::redirect('employer_dashboard.php');
    exit;
}

// Confirm ownership of job
$jobStmt = $pdo->prepare("SELECT j.job_id, j.title, j.status, j.created_at 
                          FROM jobs j 
                          WHERE j.job_id = :job_id AND j.employer_id = :employer_id
                          LIMIT 1");
$jobStmt->execute([
    ':job_id' => $job_id,
    ':employer_id' => $_SESSION['user_id']
]);
$job = $jobStmt->fetch();

if (!$job) {
    Helpers::flash('err','Job not found or not owned by you.');
    Helpers::redirect('employer_dashboard.php');
    exit;
}

// Filters
$statusFilter = trim($_GET['status'] ?? '');
$validStatuses = ['Pending','Approved','Declined'];
if ($statusFilter !== '' && !in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}

$search = trim($_GET['q'] ?? '');

// Pagination
$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

// Build base query
$where = ["a.job_id = :job_id"];
$params = [':job_id' => $job_id];

if ($statusFilter !== '') {
    $where[] = "a.status = :statusFilter";
    $params[':statusFilter'] = $statusFilter;
}
if ($search !== '') {
    $where[] = "u.name LIKE :search";
    $params[':search'] = '%' . $search . '%';
}

$whereSql = implode(' AND ', $where);

// Count total
$countSql = "SELECT COUNT(*) 
             FROM applications a 
             JOIN users u ON u.user_id = a.user_id
             WHERE $whereSql";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();

// Fetch paginated applicants
$listSql = "SELECT a.application_id, a.user_id, a.status, a.match_score,
                   a.relevant_experience, a.application_education, a.created_at,
                   u.name
            FROM applications a
            JOIN users u ON u.user_id = a.user_id
            WHERE $whereSql
            ORDER BY a.match_score DESC, a.created_at ASC
            LIMIT :limit OFFSET :offset";

$listStmt = $pdo->prepare($listSql);
foreach ($params as $k => $v) $listStmt->bindValue($k, $v);
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$applicants = $listStmt->fetchAll();

// Pagination helpers
$totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
$qs = $_GET;
unset($qs['p']);
$baseQS = http_build_query($qs);
function buildPageLink($p, $baseQS) {
    return 'employer_applicants.php?' . ($baseQS ? ($baseQS . '&') : '') . 'p=' . $p;
}

// Include layout
include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
      <div>
        <h2 class="h5 fw-semibold mb-1">
          <i class="bi bi-people-fill me-2"></i>Applicants
        </h2>
        <div class="small text-muted">
          Job: <strong><?php echo Helpers::sanitizeOutput($job['title']); ?></strong>
          <span class="ms-2 badge text-bg-<?php 
              echo $job['status']==='Open' ? 'success' : ($job['status']==='Suspended' ? 'warning' : 'secondary'); 
          ?>"><?php echo Helpers::sanitizeOutput($job['status']); ?></span>
          <span class="ms-2">Total: <?php echo number_format($total); ?></span>
        </div>
      </div>
      <div>
        <a class="btn btn-sm btn-outline-secondary" href="employer_dashboard.php">
          <i class="bi bi-arrow-left"></i> Back
        </a>
      </div>
    </div>

    <form method="get" class="row g-2 mb-3">
      <input type="hidden" name="job_id" value="<?php echo htmlspecialchars($job_id); ?>">
      <div class="col-12 col-sm-4 col-md-3">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach ($validStatuses as $st): ?>
            <option value="<?php echo htmlspecialchars($st); ?>" <?php if ($st===$statusFilter) echo 'selected'; ?>>
              <?php echo htmlspecialchars($st); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-sm-4 col-md-4">
        <label class="form-label small mb-1">Search Applicant</label>
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Name..."
               value="<?php echo htmlspecialchars($search); ?>">
      </div>
      <div class="col-12 col-sm-4 col-md-3 d-flex align-items-end">
        <button class="btn btn-primary btn-sm me-2">
          <i class="bi bi-funnel"></i> Filter
        </button>
        <a class="btn btn-outline-secondary btn-sm" 
           href="employer_applicants.php?job_id=<?php echo urlencode($job_id); ?>">
          Reset
        </a>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead class="table-light">
          <tr>
            <th style="min-width:160px;">Applicant</th>
            <th>Match</th>
            <th>Relevant Exp (yrs)</th>
            <th>Education</th>
            <th>Status</th>
            <th>Applied</th>
            <th style="width:130px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($applicants): ?>
            <?php foreach ($applicants as $a): 
              $appId = $a['application_id'];
              $match = (float)$a['match_score'];
              $status = $a['status'];
              $exp = (int)$a['relevant_experience'];
              $edu = $a['application_education'] ?: '—';
              $badgeCls = $status === 'Pending' ? 'secondary' : ($status === 'Approved' ? 'success' : 'danger');
            ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?php echo Helpers::sanitizeOutput($a['name']); ?></div>
                  <div class="small text-muted">
                    <a class="text-decoration-none" 
                       href="job_seeker_profile.php?user_id=<?php echo urlencode($a['user_id']); ?>" target="_blank">
                      View profile <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                  </div>
                </td>
                <td>
                  <div class="d-flex align-items-center" style="min-width:90px;">
                    <div class="progress flex-grow-1 me-2" style="height:6px;">
                      <div class="progress-bar bg-primary" role="progressbar"
                        style="width: <?php echo max(0,min(100,$match)); ?>%;"
                        aria-valuenow="<?php echo (int)$match; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <span class="badge text-bg-primary"><?php echo number_format($match,2); ?></span>
                  </div>
                </td>
                <td><?php echo $exp; ?></td>
                <td><?php echo Helpers::sanitizeOutput($edu); ?></td>
                <td>
                  <span class="badge text-bg-<?php echo $badgeCls; ?>">
                    <?php echo htmlspecialchars($status); ?>
                  </span>
                </td>
                <td>
                  <span class="small text-muted">
                    <?php echo date('M j, Y', strtotime($a['created_at'])); ?>
                  </span>
                </td>
                <td>
                  <div class="btn-group btn-group-sm" role="group">
                    <?php if ($status !== 'Approved'): ?>
                      <a class="btn btn-outline-success"
                         href="applications.php?action=approve&application_id=<?php echo urlencode($appId); ?>"
                         onclick="return confirm('Approve this application?');">
                        <i class="bi bi-check2-circle"></i>
                      </a>
                    <?php endif; ?>
                    <?php if ($status !== 'Declined'): ?>
                      <a class="btn btn-outline-danger"
                         href="applications.php?action=decline&application_id=<?php echo urlencode($appId); ?>"
                         onclick="return confirm('Decline this application?');">
                        <i class="bi bi-x-circle"></i>
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">
                <div class="mb-2"><i class="bi bi-inbox fs-3"></i></div>
                <div>No applicants found<?php echo $statusFilter || $search ? ' for current filters.' : '.'; ?></div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <nav aria-label="Applicants pagination">
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item <?php if ($page<=1) echo 'disabled'; ?>">
            <a class="page-link" href="<?php echo buildPageLink(max(1,$page-1), $baseQS); ?>">&laquo;</a>
          </li>
          <?php
            // Simple window
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            for ($p=$start; $p<=$end; $p++):
          ?>
            <li class="page-item <?php if ($p===$page) echo 'active'; ?>">
              <a class="page-link" href="<?php echo buildPageLink($p, $baseQS); ?>"><?php echo $p; ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?php if ($page>=$totalPages) echo 'disabled'; ?>">
            <a class="page-link" href="<?php echo buildPageLink(min($totalPages,$page+1), $baseQS); ?>">&raquo;</a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>

<?php include '../includes/footer.php'; ?>