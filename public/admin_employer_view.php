<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';
require_once '../classes/Mail.php';
require_once '../classes/Password.php';

Helpers::requireRole('admin');

/* Do NOT call storeLastPage here so back button points to the listing/search page instead of this view */

// Establish selected employer via POST -> session; avoid exposing in URL
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['user_id'])) {
  $_SESSION['__admin_view_user_emp'] = (string)$_POST['user_id'];
}
$user_id = $_SESSION['__admin_view_user_emp'] ?? '';
if ($user_id === '') {
  Helpers::flash('error','Missing user selection.');
  Helpers::redirect('admin_employers.php');
}

/* =========================================================
   ADDED (pre-fetch current status BEFORE action so we can build
   a fallback message even if flashes get consumed incorrectly)
   ========================================================= */
$__currentStatusBeforeAction = null;
if ($user_id !== '') {
  try {
    $pdoPre = Database::getConnection();
    $stmtPre = $pdoPre->prepare("SELECT employer_status FROM users WHERE user_id=? LIMIT 1");
    $stmtPre->execute([$user_id]);
    $__currentStatusBeforeAction = $stmtPre->fetchColumn() ?: 'Pending';
  } catch (Throwable $e) {
    $__currentStatusBeforeAction = null;
  }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
  $action = $_POST['action'];
  $map = [
    'approve'  => 'Approved',
    'suspend'  => 'Suspended',
    'pending'  => 'Pending',
    'reject'   => 'Rejected'
  ];
  if (isset($map[$action])) {
    $newStatus = $map[$action];
    $updated = User::updateEmployerStatus($user_id, $newStatus);
    if ($updated) {
      // If status didn't actually change, avoid creating a misleading "updated" flash
      if ($__currentStatusBeforeAction === $newStatus) {
        Helpers::flash('msg', 'Employer status is already ' . $newStatus . '.');
        $_SESSION['__status_update_msg'] = 'Employer status is already ' . $newStatus . '.';
  Helpers::redirect('admin_employer_view');
        exit;
      }
      $baseMsg = 'Employer status updated to ' . $newStatus . '.';
      $emailInfo = '';
      // Only send if status actually changed
      if ($__currentStatusBeforeAction !== $newStatus) {
        // Fetch user email + name for notification
        try {
          $pdoE = Database::getConnection();
          $stE = $pdoE->prepare("SELECT name,email,company_name FROM users WHERE user_id=? AND role='employer' LIMIT 1");
          $stE->execute([$user_id]);
          $empRow = $stE->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { $empRow = null; }
        if ($empRow) {
          $toEmail = $empRow['email'];
          $toName  = $empRow['name'];
          $company = $empRow['company_name'] ?: 'your company';
          $subject = match($newStatus) {
            'Approved'  => 'Your Employer Account Has Been Approved',
            'Suspended' => 'Your Employer Account Has Been Suspended',
            'Rejected'  => 'Your Employer Account Application Was Rejected',
            default     => 'Your Employer Account Status Updated'
          };
          $body  = '<p>Hello '.htmlspecialchars($toName).',</p>';
          $body .= '<p>Your employer account for <strong>'.htmlspecialchars($company).'</strong> has been updated to status: <strong>'.htmlspecialchars($newStatus).'</strong>.</p>';
          $issuedPass = null;
          if ($newStatus === 'Approved') {
            try {
              $issuedPass = PasswordHelper::assignInitialPasswordIfMissing($user_id);
            } catch (Throwable $e) { $issuedPass = null; }
            if ($issuedPass) {
              $body .= '<p>Your initial password is: <strong>'.htmlspecialchars($issuedPass).'</strong></p>';
              $body .= '<p>You can now log in here: <a href="'.BASE_URL.'/login">'.BASE_URL.'/login</a></p>';
            } else {
              $body .= '<p>You can now post jobs and manage applicants.</p>';
            }
          } elseif ($newStatus === 'Suspended') {
            $body .= '<p>Job posting and applicant management are temporarily disabled. Please contact support if you believe this is an error.</p>';
          } elseif ($newStatus === 'Rejected') {
            $body .= '<p>Your application was not approved. You may reply with additional documentation or contact support for clarification.</p>';
          } else {
            $body .= '<p>Your application is pending review. We will notify you once a decision is made.</p>';
          }
          $body .= '<p>Regards,<br>The Admin Team</p>';
          if (Mail::isEnabled()) {
            $sendRes = Mail::send($toEmail, $toName, $subject, $body);
            if ($sendRes['success']) {
              $emailInfo = ' Email notification sent.';
            } else {
              if ($sendRes['error'] === 'SMTP disabled') {
                $emailInfo = ' (Email not sent: SMTP disabled.)';
              } else {
                $emailInfo = ' (Email failed: '.htmlspecialchars($sendRes['error']).')';
              }
            }
          } else {
            if ($issuedPass) {
              $emailInfo = ' (SMTP disabled; initial password generated: '.htmlspecialchars($issuedPass).')';
            } else {
              $emailInfo = ' (Email not sent: SMTP disabled.)';
            }
          }
        }
      }
      Helpers::flash('msg', $baseMsg.$emailInfo);
      $_SESSION['__status_update_msg'] = $baseMsg.$emailInfo; /* recovery mirror */
    } else {
      Helpers::flash('msg', 'Failed to update employer status.');
      $_SESSION['__status_update_msg'] = 'Failed to update employer status.'; /* ADDED */
    }
  } else {
    /* ADDED: unknown action fallback */
    $_SESSION['__status_update_msg'] = 'Unknown employer status action.';
  }
  Helpers::redirect('admin_employer_view');
  exit;
}

$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT u.*, (SELECT COUNT(*) FROM jobs j WHERE j.employer_id = u.user_id) AS job_count
                       FROM users u WHERE u.user_id = ? AND u.role = 'employer' LIMIT 1");
$stmt->execute([$user_id]);
$emp = $stmt->fetch();

if (!$emp) {
  Helpers::flash('msg', 'Employer not found.');
  $_SESSION['__status_update_msg'] = 'Employer not found.'; /* ADDED */
  unset($_SESSION['__admin_view_user_emp']);
  Helpers::redirect('admin_employers.php');
}

$status = $emp['employer_status'] ?: 'Pending';

$stmtJobs = $pdo->prepare("SELECT job_id, title, created_at FROM jobs WHERE employer_id = ? ORDER BY created_at DESC");
$stmtJobs->execute([$emp['user_id']]);
$jobs = $stmtJobs->fetchAll();

function doc_ext($path) {
  return strtolower(pathinfo($path, PATHINFO_EXTENSION));
}

include '../includes/header.php';

/* ADDED: use last page override default list page */
$backUrl = Helpers::getLastPage('admin_employers.php');

/* =========================================================
   ADDED START: RESILIENT FLASH + FALLBACK INDICATOR BLOCK
   (Original lines kept; we now add data-origin so we can dedup later)
   ========================================================= */
$__rawFlashSessionCopy = $_SESSION['flash'] ?? []; // snapshot

$__structured = [];
if (method_exists('Helpers','getFlashes')) {
  $rawFlashes = Helpers::getFlashes(); // returns assoc key => message (strings)
  if (is_array($rawFlashes)) {
    foreach ($rawFlashes as $k=>$v) {
      $t = match($k){
        'error','danger'=>'danger',
        'success'=>'success',
        'auth'=>'warning',
        default=>'info'
      };
      $__structured[] = ['type'=>$t,'message'=>(string)$v];
    }
  }
}

// Fallback to snapshot if still empty (e.g., no getFlashes method or was already consumed)
if (!$__structured && $__rawFlashSessionCopy) {
  foreach ($__rawFlashSessionCopy as $k=>$v) {
    $t = match($k){
      'error','danger'=>'danger',
      'success'=>'success',
      'auth'=>'warning',
      default=>'info'
    };
    $__structured[] = ['type'=>$t,'message'=>(string)$v];
  }
}

$__needsSynthetic = false;
foreach ($__structured as $f) {
    $msg = trim((string)($f['message'] ?? ''));
    if ($msg === '') { $__needsSynthetic = true; break; }
}
if (!$__structured) $__needsSynthetic = true;

$__syntheticMessages = [];
if ($__needsSynthetic) {
    $synthetic = $_SESSION['__status_update_msg'] ?? '';
    if ($synthetic === '') {
        $synthetic = 'Employer status is ' . $status . '.';
    }
    $__syntheticMessages[] = ['type'=>'info','message'=>$synthetic];
}

$__finalFlashList = $__structured;
if ($__syntheticMessages) {
    $existingTexts = array_map(fn($x)=>$x['message'],$__finalFlashList);
    foreach ($__syntheticMessages as $s) {
        if (!in_array($s['message'],$existingTexts,true)) {
            $__finalFlashList[] = $s;
        }
    }
}

if ($__finalFlashList) {
  echo '<div id="__emp_status_flash_block" class="mb-3">';
    foreach ($__finalFlashList as $f) {
        $type = htmlspecialchars($f['type'] ?? 'info');
        $msg  = trim((string)($f['message'] ?? ''));
        if ($msg === '') $msg = 'Action completed.';
        $icon = match($type){
          'success'=>'check-circle',
          'danger'=>'exclamation-triangle',
          'warning'=>'exclamation-circle',
          default=>'info-circle'
        };
        echo '<div class="alert alert-'.$type.' alert-dismissible fade show auto-dismiss" role="alert" data-origin="resilient-block" data-msg="'.htmlspecialchars($msg,ENT_QUOTES).'">';
        echo '<i class="bi bi-'.$icon.' me-2"></i>'.htmlspecialchars($msg);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
    echo '</div>';
}
?>
<div class="admin-layout">
  <?php include '../includes/admin_sidebar.php'; ?>
  <div class="admin-main">
<script>
/* ADDED JS PATCH:
   Fills empty alerts (previous logic) with message if needed.
*/
(function(){
  const currentStatus = <?php echo json_encode($status); ?>;
  const synth = <?php echo json_encode($_SESSION['__status_update_msg'] ?? ''); ?>;
  document.querySelectorAll('.alert.alert-info, .alert.alert-success, .alert.alert-warning, .alert.alert-danger')
    .forEach(function(al){
      const btn = al.querySelector('.btn-close');
      const raw = al.cloneNode(true);
      const innerTxt = Array.from(al.childNodes)
          .filter(n=>n.nodeType===3)
          .map(n=>n.textContent).join('').trim();
      const hasMeaningful = innerTxt.length > 1;
      if (!hasMeaningful) {
        let injected = al.getAttribute('data-msg') || synth;
        if (!injected) injected = 'Employer status is ' + currentStatus + '.';
        const span = document.createElement('span');
        span.textContent = ' ' + injected;
        if (btn) al.insertBefore(span, btn);
        else al.appendChild(span);
      }
    });
})();
</script>

<script>
(function(){
  // Run after previous scripts
  const seen = new Set();
  document.querySelectorAll('.alert').forEach(al=>{
    // Collect textual message excluding the close button
    const clone = al.cloneNode(true);
    const btn = clone.querySelector('.btn-close');
    if (btn) btn.remove();
    const msg = clone.textContent.replace(/\s+/g,' ').trim();
    if (!msg) return;
    if (seen.has(msg)) {
      // Remove duplicates (later ones)
      al.parentNode && al.parentNode.removeChild(al);
    } else {
      seen.add(msg);
    }
  });
})();
</script>


<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-sm btn-outline-secondary me-2" onclick="if(document.referrer){history.back();return false;}"><i class="bi bi-arrow-left"></i></a>
    <span class="h5 fw-semibold align-middle">Employer Verification</span>
  </div>
  
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Profile Information</span>
        <?php
          $st = ($status ?? 'Pending') ?: 'Pending';
          $badgeClass = match($st){
            'Approved'  => 'text-bg-success',
            'Pending'   => 'text-bg-warning',
            'Suspended' => 'text-bg-danger',
            'Rejected'  => 'text-bg-secondary',
            default     => 'text-bg-secondary'
          };
        ?>
        <span class="badge <?php echo $badgeClass; ?>">Status: <?php echo htmlspecialchars($st); ?></span>
      </div>
      <div class="card-body small">
        <dl class="row mb-0">
          <dt class="col-sm-4">Company</dt><dd class="col-sm-8"><?php $v=trim((string)($emp['company_name']??'')); echo $v!==''?Helpers::sanitizeOutput($v):'Not available'; ?></dd>
          <dt class="col-sm-4">Business Email</dt><dd class="col-sm-8"><?php $v=trim((string)($emp['business_email']??'')); echo $v!==''?Helpers::sanitizeOutput($v):'Not available'; ?></dd>
          <dt class="col-sm-4">Permit / Reg. No.</dt><dd class="col-sm-8"><?php $v=trim((string)($emp['business_permit_number']??'')); echo $v!==''?Helpers::sanitizeOutput($v):'Not available'; ?></dd>
          <dt class="col-sm-4">Account Owner</dt><dd class="col-sm-8"><?php $v=trim((string)($emp['name']??'')); echo $v!==''?Helpers::sanitizeOutput($v):'Not available'; ?></dd>
          <dt class="col-sm-4">Owner Email</dt><dd class="col-sm-8"><?php $v=trim((string)($emp['email']??'')); echo $v!==''?Helpers::sanitizeOutput($v):'Not available'; ?></dd>
          <dt class="col-sm-4">Created</dt><dd class="col-sm-8"><?php $v=trim((string)($emp['created_at']??'')); echo $v!==''?htmlspecialchars($v):'Not available'; ?></dd>
          <dt class="col-sm-4">Jobs Posted</dt><dd class="col-sm-8"><?php $v=(string)($emp['job_count'] ?? '0'); echo $v!==''?htmlspecialchars($v):'0'; ?></dd>
        </dl>
      </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
      <div class="card-body p-4">
        <h3 class="h6 fw-semibold mb-3"><i class="bi bi-card-list me-1"></i>Recent Jobs</h3>
        <?php if ($jobs): ?>
          <ul class="list-unstyled small mb-0">
            <?php foreach ($jobs as $j): ?>
              <li class="mb-1">
                <a class="text-decoration-none" href="job_view.php?job_id=<?php echo urlencode($j['job_id']); ?>">
                  <?php echo Helpers::sanitizeOutput($j['title']); ?>
                </a>
                <span class="text-muted"> · <?php echo htmlspecialchars(date('M d, Y', strtotime($j['created_at']))); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="text-muted small">No jobs posted yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold">Verification Actions</div>
      <div class="card-body">
        <form method="post" class="d-grid gap-2">
          <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($emp['user_id']); ?>">
          <button name="action" value="approve" class="btn btn-success" onclick="return confirm('Approve this employer?');"><i class="bi bi-check2-circle me-1"></i>Approve</button>
          <button name="action" value="pending" class="btn btn-warning" onclick="return confirm('Set employer back to Pending?');"><i class="bi bi-arrow-counterclockwise me-1"></i>Set Pending</button>
          <button name="action" value="suspend" class="btn btn-danger" onclick="return confirm('Suspend this employer?');"><i class="bi bi-x-circle me-1"></i>Suspend</button>
          <button name="action" value="reject" class="btn btn-secondary" onclick="return confirm('Reject this employer?');"><i class="bi bi-slash-circle me-1"></i>Reject</button>
        </form>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">Verification Document</div>
      <div class="card-body">
        <?php
          $doc = $emp['employer_doc'] ?? '';
          if ($doc):
            $ext = doc_ext($doc);
        ?>
          <div class="small mb-2">
            File: <span class="fw-semibold"><?php echo Helpers::sanitizeOutput(basename($doc)); ?></span>
          </div>
          <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp'], true)): ?>
            <img src="<?php echo Helpers::sanitizeOutput($doc); ?>" alt="Document" class="img-fluid rounded border">
          <?php elseif ($ext === 'pdf'): ?>
            <a href="<?php echo Helpers::sanitizeOutput($doc); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-filetype-pdf me-1"></i>Open PDF
            </a>
          <?php else: ?>
            <a href="<?php echo Helpers::sanitizeOutput($doc); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-box-arrow-up-right me-1"></i>Open Document
            </a>
          <?php endif; ?>
        <?php else: ?>
          <div class="text-muted small">No document uploaded.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
  </div>

<?php include '../includes/footer.php'; ?>