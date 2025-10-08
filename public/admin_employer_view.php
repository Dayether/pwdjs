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
  $reason = trim((string)($_POST['reason'] ?? ''));
  $map = [
    'approve'  => 'Approved',
    'suspend'  => 'Suspended',
    'pending'  => 'Pending',
    'reject'   => 'Rejected'
  ];
  if (isset($map[$action])) {
    $newStatus = $map[$action];
  // DUPLICATE EMAIL GUARD: generate fingerprint BEFORE update (status+reason) to detect repeat submissions
  if (!isset($_SESSION['__status_change_fps'])) $_SESSION['__status_change_fps'] = [];
  // Prune old (older than 2 hours) to keep session small
  foreach ($_SESSION['__status_change_fps'] as $fpKey=>$ts) { if ($ts < time()-7200) unset($_SESSION['__status_change_fps'][$fpKey]); }
  $fingerprint = hash('sha256', $user_id.'|'.$newStatus.'|'.mb_strtolower($reason));

  // Optimistic current status re-check right before performing update
  try {
    $pdoDup = Database::getConnection();
    $stmtDup = $pdoDup->prepare('SELECT employer_status FROM users WHERE user_id=? LIMIT 1');
    $stmtDup->execute([$user_id]);
    $currentForGuard = $stmtDup->fetchColumn() ?: 'Pending';
  } catch (Throwable $e) { $currentForGuard = null; }

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
        // Duplicate guard: skip email/password issuance if fingerprint already processed OR current status changed earlier
        $alreadyProcessed = isset($_SESSION['__status_change_fps'][$fingerprint]);
        if ($alreadyProcessed || ($currentForGuard !== null && $currentForGuard === $newStatus && $__currentStatusBeforeAction !== $newStatus)) {
          $emailInfo = ' (Duplicate action detected: notification suppressed)';
        } else {
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
          if ($reason !== '') { $body .= '<p><strong>Reason:</strong><br>'.nl2br(htmlspecialchars($reason)).'</p>'; }
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
          // Mark fingerprint as processed only after attempt (avoid resend)
          $_SESSION['__status_change_fps'][$fingerprint] = time();
        }
        }
      }
  // Persist reason + log
  User::persistStatusReason($user_id, $reason, $newStatus==='Suspended');
  User::logStatusChange($_SESSION['user_id'], $user_id, 'employer', 'employer_status', $newStatus, $reason);
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


<style>
  /* Employer verification enhanced heading styling (consistent with dashboard & tickets) */
  .emp-verif-topbar{display:flex;flex-direction:column;gap:.9rem;margin-bottom:1.15rem;animation:fadeIn .45s ease both}
  .emp-verif-topbar .bar{display:flex;align-items:center;flex-wrap:wrap;gap:.75rem;border-bottom:1px solid rgba(255,255,255,.07);padding-bottom:.55rem}
  @media (prefers-color-scheme: light){.emp-verif-topbar .bar{border-color:rgba(0,0,0,.08);} }
  .emp-verif-topbar h2{margin:0;font-size:1.28rem;font-weight:700;letter-spacing:.55px;display:flex;align-items:center;gap:.55rem;line-height:1.05;background:linear-gradient(90deg,#ffffff,#93c5fd);-webkit-background-clip:text;background-clip:text;color:transparent;position:relative}
  .emp-verif-topbar h2 i{background:linear-gradient(135deg,#60a5fa,#818cf8);-webkit-background-clip:text;background-clip:text;color:transparent;filter:drop-shadow(0 2px 4px rgba(0,0,0,.35));}
  @media (prefers-color-scheme: light){.emp-verif-topbar h2{background:linear-gradient(90deg,#1e293b,#2563eb);} .emp-verif-topbar h2 i{background:linear-gradient(135deg,#2563eb,#4f46e5);} }
  .emp-verif-topbar a.back-btn{--clr-border:rgba(255,255,255,.15);--clr-fg:#c4d2e4;display:inline-flex;align-items:center;justify-content:center;gap:.4rem;height:34px;padding:.45rem .85rem;font-size:.7rem;font-weight:600;letter-spacing:.05em;border:1px solid var(--clr-border);background:#182637;color:var(--clr-fg);border-radius:8px;text-decoration:none;transition:.25s}
  .emp-verif-topbar a.back-btn:hover{background:#22364d;color:#fff;border-color:#3b5f89}
  @media (prefers-color-scheme: light){.emp-verif-topbar a.back-btn{background:#f1f5f9;color:#334155;border-color:#cbd5e1;} .emp-verif-topbar a.back-btn:hover{background:#e2e8f0;border-color:#94a3b8;color:#1e293b;} }
  @keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
</style>

<div class="emp-verif-topbar">
  <div class="bar">
    <a href="<?php echo htmlspecialchars($backUrl); ?>" class="back-btn" onclick="if(document.referrer){history.back();return false;}"><i class="bi bi-arrow-left"></i><span>Back</span></a>
    <h2><i class="bi bi-building-check"></i> Employer Verification</h2>
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
          <dt class="col-sm-4">Owner / Proprietor</dt><dd class="col-sm-8"><?php $v=trim((string)($emp['company_owner_name']??'')); echo $v!==''?Helpers::sanitizeOutput($v):'Not available'; ?></dd>
          <dt class="col-sm-4">Account Owner</dt><dd class="col-sm-8"><?php $v=trim((string)($emp['name']??'')); echo $v!==''?Helpers::sanitizeOutput($v):'Not available'; ?></dd>
          <dt class="col-sm-4">Owner Email</dt><dd class="col-sm-8"><?php $v=trim((string)($emp['email']??'')); echo $v!==''?Helpers::sanitizeOutput($v):'Not available'; ?></dd>
          <dt class="col-sm-4">Contact Position</dt><dd class="col-sm-8"><?php $v=trim((string)($emp['contact_person_position']??'')); echo $v!==''?Helpers::sanitizeOutput($v):'—'; ?></dd>
          <dt class="col-sm-4">Contact Phone</dt><dd class="col-sm-8"><?php $v=trim((string)($emp['contact_person_phone']??'')); echo $v!==''?Helpers::sanitizeOutput($v):'—'; ?></dd>
          <dt class="col-sm-4">Created</dt><dd class="col-sm-8"><?php $v=trim((string)($emp['created_at']??'')); echo $v!==''?htmlspecialchars($v):'Not available'; ?></dd>
          <dt class="col-sm-4">Jobs Posted</dt><dd class="col-sm-8"><?php $v=(string)($emp['job_count'] ?? '0'); echo $v!==''?htmlspecialchars($v):'0'; ?></dd>
          <?php
            $reasonCols = [];
            try { $cols = Database::getConnection()->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC); foreach ($cols as $c) $reasonCols[$c['Field']]=true; } catch (Throwable $e) {}
            if (!empty($reasonCols['last_status_reason'])): $rs = trim((string)($emp['last_status_reason']??'')); ?>
              <dt class="col-sm-4">Last Status Reason</dt><dd class="col-sm-8"><?php echo $rs!==''? nl2br(Helpers::sanitizeOutput($rs)) : '—'; ?></dd>
            <?php endif; if (!empty($reasonCols['last_suspension_reason']) && ($emp['employer_status']??'')==='Suspended'): $sr = trim((string)($emp['last_suspension_reason']??'')); ?>
              <dt class="col-sm-4">Suspension Reason</dt><dd class="col-sm-8"><?php echo $sr!==''? nl2br(Helpers::sanitizeOutput($sr)) : '—'; ?></dd>
            <?php endif; ?>
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
        <div class="d-grid gap-2">
          <button data-reason-action="approve" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Approve</button>
          <button data-reason-action="pending" class="btn btn-warning"><i class="bi bi-arrow-counterclockwise me-1"></i>Set Pending</button>
          <button data-reason-action="suspend" class="btn btn-danger"><i class="bi bi-x-circle me-1"></i>Suspend</button>
          <button data-reason-action="reject" class="btn btn-secondary"><i class="bi bi-slash-circle me-1"></i>Reject</button>
        </div>
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
            <a href="../<?php echo Helpers::sanitizeOutput($doc); ?>" target="_blank" class="d-inline-block" style="max-width:100%" title="Open full image in new tab">
              <img src="../<?php echo Helpers::sanitizeOutput($doc); ?>" alt="Verification Document" class="img-fluid rounded border" style="cursor:zoom-in;">
            </a>
            <div class="small mt-2"><a href="../<?php echo Helpers::sanitizeOutput($doc); ?>" target="_blank"><i class="bi bi-box-arrow-up-right me-1"></i>Open Image in New Tab</a></div>
          <?php elseif ($ext === 'pdf'): ?>
            <a href="../<?php echo Helpers::sanitizeOutput($doc); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-filetype-pdf me-1"></i>Open PDF
            </a>
          <?php else: ?>
            <a href="../<?php echo Helpers::sanitizeOutput($doc); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
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

<!-- Reason Modal -->
<div class="modal fade" id="reasonModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" id="reasonForm">
        <div class="modal-header py-2">
          <h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i><span id="reasonModalTitle">Provide Reason</span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($emp['user_id']); ?>">
          <input type="hidden" name="action" id="reasonAction" value="">
          <input type="hidden" name="csrf" value="<?php echo session_id(); ?>">
          <div class="mb-3">
            <label for="reasonText" class="form-label">Reason (required)</label>
            <textarea class="form-control" id="reasonText" name="reason" rows="4" required placeholder="Explain clearly the reason for this action..."></textarea>
            <div class="form-text">Will be stored & included in the email sent to the employer.</div>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const modalEl = document.getElementById('reasonModal');
  const actionInput = document.getElementById('reasonAction');
  const titleSpan = document.getElementById('reasonModalTitle');
  const txt = document.getElementById('reasonText');
  function openReason(action){
    actionInput.value = action;
    titleSpan.textContent = 'Provide Reason for ' + action.charAt(0).toUpperCase() + action.slice(1);
    txt.value='';
    if (window.bootstrap){ window.bootstrap.Modal.getOrCreateInstance(modalEl).show(); }
    else { modalEl.style.display='block'; }
  }
  document.querySelectorAll('[data-reason-action]').forEach(btn=>{
    btn.addEventListener('click', e=>{ e.preventDefault(); openReason(btn.getAttribute('data-reason-action')); });
  });
})();
</script>

<?php include '../includes/footer.php'; ?>