<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';
require_once '../classes/Application.php';
require_once '../classes/Mail.php';

if (session_status()===PHP_SESSION_NONE) {
    session_start();
}
Helpers::requireLogin();
// Allow employers; admins can also view. If neither, flash + redirect to their dashboard.
if (!Helpers::isEmployer() && !Helpers::isAdmin()) {
  Helpers::flash('error','You do not have permission to access that page.');
  Helpers::redirectToRoleDashboard();
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

// Handle POST decision with optional feedback
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'], $_POST['application_id'])) {
  $action = $_POST['action'];
  $appId  = (string)$_POST['application_id'];
  $feedback = trim((string)($_POST['feedback'] ?? ''));
  $map = ['approve'=>'Approved','decline'=>'Declined','pending'=>'Pending'];
  if (isset($map[$action])) {
    $newStatus = $map[$action];
    $ok = Application::updateStatus($appId, $newStatus, $_SESSION['user_id'], $feedback);
    if ($ok) {
      $emailInfo = '';
      try {
        // Fetch applicant email + job details for notification
        $pdoE = Database::getConnection();
        $stE = $pdoE->prepare("SELECT a.application_id, a.user_id, a.job_id, u.email, u.name, j.title
                    FROM applications a
                    JOIN users u ON u.user_id = a.user_id
                    JOIN jobs j  ON j.job_id  = a.job_id
                    WHERE a.application_id = ? LIMIT 1");
        $stE->execute([$appId]);
        $rowE = $stE->fetch(PDO::FETCH_ASSOC);
      } catch (Throwable $e) { $rowE = null; }

      if ($rowE) {
        $toEmail = $rowE['email'];
        $toName  = $rowE['name'];
        $jobTitle = $rowE['title'] ?: 'the job you applied to';
        $subject = ($newStatus==='Approved' ? 'Application Approved: ' : ($newStatus==='Declined' ? 'Application Declined: ' : 'Application Update: ')) . $jobTitle;
        $body  = '<p>Hello '.htmlspecialchars($toName).',</p>';
        if ($newStatus==='Approved') {
          $body .= '<p>Your application for <strong>'.htmlspecialchars($jobTitle).'</strong> has been <strong>approved</strong>.</p>';
        } elseif ($newStatus==='Declined') {
          $body .= '<p>Your application for <strong>'.htmlspecialchars($jobTitle).'</strong> has been <strong>declined</strong>.</p>';
        } else {
          $body .= '<p>Your application status has been updated to <strong>'.htmlspecialchars($newStatus).'</strong>.</p>';
        }
        if ($feedback !== '') {
          $body .= '<p><strong>Message from the employer:</strong><br>'.nl2br(htmlspecialchars($feedback)).'</p>';
        }
        $body .= '<p>You can view your application here: <a href="'.BASE_URL.'/applications">'.BASE_URL.'/applications</a></p>';
        $body .= '<p>Job link: <a href="'.BASE_URL.'/job_view?job_id='.urlencode($rowE['job_id']).'">'.BASE_URL.'/job_view?job_id='.htmlspecialchars($rowE['job_id'])."</a></p>";
        $body .= '<p>Regards,<br>The Team</p>';

        if (Mail::isEnabled()) {
          $sendRes = Mail::send($toEmail, $toName, $subject, $body);
          if ($sendRes['success']) {
            $emailInfo = ' Email sent to applicant.';
          } else {
            $emailInfo = $sendRes['error']==='SMTP disabled' ? ' (Email not sent: SMTP disabled.)' : ' (Email failed: '.htmlspecialchars($sendRes['error']).')';
          }
        } else {
          $emailInfo = ' (Email not sent: SMTP disabled.)';
        }
      }

      Helpers::flash('msg','Application updated.'.($emailInfo?:''));
    } else {
      Helpers::flash('error','Failed to update application.');
    }
  }
  Helpers::redirect('employer_applicants.php?job_id='.urlencode($job_id));
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
<!-- Toasts (status updates) -->
<?php
$rawFlash = $_SESSION['flash'] ?? [];
if (isset($_SESSION['flash'])) unset($_SESSION['flash']);
$toastMsgs = [];
if (!empty($rawFlash['msg']) && trim($rawFlash['msg'])!=='') {
  $toastMsgs[] = ['type'=>'success','icon'=>'bi-check-circle','message'=>htmlspecialchars($rawFlash['msg'])];
}
if (!empty($rawFlash['error']) && trim($rawFlash['error'])!=='') {
  $toastMsgs[] = ['type'=>'danger','icon'=>'bi-exclamation-triangle','message'=>htmlspecialchars($rawFlash['error'])];
}
?>
<div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1080;">
  <?php foreach ($toastMsgs as $t): ?>
    <div class="toast align-items-center text-bg-<?php echo $t['type']; ?> border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4200">
      <div class="d-flex">
        <div class="toast-body"><i class="bi <?php echo $t['icon']; ?> me-2"></i><?php echo $t['message']; ?></div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
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
            <th>Actions</th>
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
            <td class="small">
              <div class="d-flex gap-1">
                <?php if ($ap['status']!=='Approved'): ?>
                  <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#feedbackModal" data-app-id="<?php echo htmlspecialchars($ap['application_id']); ?>" data-action="approve">
                    <i class="bi bi-check2"></i>
                  </button>
                <?php endif; ?>
                <?php if ($ap['status']!=='Declined'): ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#feedbackModal" data-app-id="<?php echo htmlspecialchars($ap['application_id']); ?>" data-action="decline">
                    <i class="bi bi-x"></i>
                  </button>
                <?php endif; ?>
              </div>
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

<!-- Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Application Decision</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="application_id" id="decisionAppId" value="">
        <input type="hidden" name="action" id="decisionAction" value="">
        <div class="mb-3">
          <label class="form-label">Message for the applicant (optional)</label>
          <textarea name="feedback" class="form-control" rows="4" placeholder="Provide next steps or notes..."></textarea>
          <div class="form-text">This will be visible to the applicant in their Applications list.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Decision</button>
      </div>
    </form>
  </div>
</div>

<script>
const modal = document.getElementById('feedbackModal');
modal.addEventListener('show.bs.modal', event => {
  const btn = event.relatedTarget;
  const appId = btn.getAttribute('data-app-id');
  const action = btn.getAttribute('data-action');
  document.getElementById('decisionAppId').value = appId;
  document.getElementById('decisionAction').value = action;
});
</script>

<?php include '../includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('#toastContainer .toast').forEach(function(el){
    try { bootstrap.Toast.getOrCreateInstance(el).show(); } catch(e){}
  });
});
</script>