<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';

if (session_status()===PHP_SESSION_NONE) {
    session_start();
}
Helpers::requireLogin();
if (!Helpers::isEmployer() && !Helpers::isAdmin()) {
    Helpers::redirect('index.php');
}

/* ADDED: store page */
Helpers::storeLastPage();

$job_id = $_GET['job_id'] ?? '';
if ($job_id === '') {
    Helpers::flash('error','Missing job_id.');
    Helpers::redirect('employer_dashboard.php');
}

$pdo = Database::getConnection();
$stmtJob = $pdo->prepare("SELECT * FROM jobs WHERE job_id = ? LIMIT 1");
$stmtJob->execute([$job_id]);
$job = $stmtJob->fetch();
if (!$job) {
    Helpers::flash('error','Job not found.');
    Helpers::redirect('employer_dashboard.php');
}

if (Helpers::isEmployer() && $job['employer_id'] !== ($_SESSION['user_id'] ?? '')) {
    Helpers::flash('error','Unauthorized for this job.');
    Helpers::redirect('employer_dashboard.php');
}

$validStatuses = ['Pending','Approved','Declined'];
$statusFilter  = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');
$page          = max(1,(int)($_GET['p'] ?? 1));
$perPage       = 12;
$offset        = ($page-1)*$perPage;

$where  = ['a.job_id = :job_id'];
$params = [':job_id' => $job_id];

if ($statusFilter !== '' && in_array($statusFilter,$validStatuses,true)) {
    $where[]='a.status = :st';
    $params[':st']=$statusFilter;
}
if ($search!=='') {
    $where[]='u.name LIKE :q';
    $params[':q']='%'.$search.'%';
}

$sqlCount="
    SELECT COUNT(*) FROM applications a
    JOIN users u ON u.user_id = a.user_id
    WHERE ".implode(' AND ',$where);
$stmtCount=$pdo->prepare($sqlCount);
$stmtCount->execute($params);
$total=(int)$stmtCount->fetchColumn();

$sqlList="
    SELECT a.application_id, a.user_id, a.status, a.match_score, a.relevant_experience,
           a.application_education, a.created_at, u.name
    FROM applications a
    JOIN users u ON u.user_id = a.user_id
    WHERE ".implode(' AND ',$where)."
    ORDER BY a.match_score DESC, a.created_at ASC
    LIMIT :limit OFFSET :offset
";
$listStmt=$pdo->prepare($sqlList);
foreach($params as $k=>$v){
    $listStmt->bindValue($k,$v);
}
$listStmt->bindValue(':limit',$perPage,PDO::PARAM_INT);
$listStmt->bindValue(':offset',$offset,PDO::PARAM_INT);
$listStmt->execute();
$applicants=$listStmt->fetchAll();

$totalPages = $total>0 ? (int)ceil($total/$perPage) : 1;
$qs=$_GET;
unset($qs['p']);
$baseQS=http_build_query($qs);
function buildPageLink($p,$baseQS){
    return 'employer_applicants.php?'.($baseQS?($baseQS.'&'):'').'p='.$p;
}

include '../includes/header.php';
include '../includes/nav.php';

/* ADDED: last page for back (default dashboard) */
$backUrl = Helpers::getLastPage('employer_dashboard.php');
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
        <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars($backUrl); ?>">
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
            <th>Applicant</th>
            <th>Match %</th>
            <th>Status</th>
            <th>Applied</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($applicants as $ap): ?>
          <tr>
            <td class="small">
              <strong><?php echo Helpers::sanitizeOutput($ap['name']); ?></strong><br>
              <span class="text-muted"><?php echo Helpers::sanitizeOutput($ap['application_education'] ?: ''); ?></span>
            </td>
            <td><?php echo number_format($ap['match_score']); ?>%</td>
            <td>
              <span class="badge text-bg-<?php
                echo $ap['status']==='Approved'?'success':($ap['status']==='Declined'?'secondary':'warning');
              ?>">
                <?php echo Helpers::sanitizeOutput($ap['status']); ?>
              </span>
            </td>
            <td class="small text-muted">
              <?php echo htmlspecialchars(date('M d, Y', strtotime($ap['created_at']))); ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$applicants): ?>
          <tr><td colspan="4" class="text-center small text-muted">No applicants found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <nav>
        <ul class="pagination pagination-sm mt-3">
          <?php for ($p=1; $p<=$totalPages; $p++): ?>
            <li class="page-item <?php if ($p==$page) echo 'active'; ?>">
              <a class="page-link" href="<?php echo htmlspecialchars(buildPageLink($p, $baseQS)); ?>">
                <?php echo $p; ?>
              </a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>

  </div>
</div>

<?php include '../includes/footer.php'; ?>