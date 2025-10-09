<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/Job.php';
require_once '../classes/User.php';
require_once '../classes/Mail.php';

Helpers::requireRole('admin');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
Helpers::storeLastPage();

$pdo = Database::getConnection();
$errors = [];$messages=[];

// Filters
$statusFilter = ucfirst(strtolower(trim($_GET['status'] ?? 'All')));
if (!in_array($statusFilter, ['Pending','Approved','Rejected','All'], true)) { $statusFilter = 'Pending'; }
$q = trim($_GET['q'] ?? '');

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $job_id = trim($_POST['job_id'] ?? '');
  $action = $_POST['action'] ?? '';
  $reason = trim((string)($_POST['reason'] ?? ''));
  if ($job_id==='') { $errors[]='Missing job id.'; }
  if (!in_array($action,['approve','reject'],true)) { $errors[]='Invalid action.'; }
  if ($action==='reject' && $reason==='') { $errors[]='Reason is required for rejection.'; }
  if (!$errors) {
    $ok = Job::moderate($job_id, $_SESSION['user_id'], $action, $reason);
    if ($ok) {
      // Notify employer
      try {
        $st = $pdo->prepare("SELECT j.title, u.email, u.name, u.company_name FROM jobs j JOIN users u ON u.user_id=j.employer_id WHERE j.job_id=? LIMIT 1");
        $st->execute([$job_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
      } catch (Throwable $e) { $row = null; }
      if ($row) {
        $toEmail = $row['email']; $toName=$row['name']; $company=$row['company_name'] ?: 'your company';
        $subject = $action==='approve' ? 'Your Job Posting Was Approved' : 'Your Job Posting Was Rejected';
        $body = '<p>Hello '.htmlspecialchars($toName).',</p>';
        $body .= '<p>Your job posting <strong>'.htmlspecialchars($row['title']).'</strong> for <strong>'.htmlspecialchars($company).'</strong> has been <strong>'.($action==='approve'?'approved':'rejected').'</strong>.</p>';
        if ($reason!=='') $body .= '<p><strong>Reason:</strong><br>'.nl2br(htmlspecialchars($reason)).'</p>';
        $body .= '<p>Regards,<br>Admin Team</p>';
        if (Mail::isEnabled()) {
          $res = Mail::send($toEmail,$toName,$subject,$body);
          if ($res['success']) $messages[]='Action saved. Email notification sent.';
          else $messages[]='Action saved. Email not sent: '.htmlspecialchars($res['error']);
        } else {
          $messages[]='Action saved. Email not sent: SMTP disabled.';
        }
      } else {
        $messages[]='Action saved.';
      }
    } else {
      $errors[]='Action failed or already decided.';
    }
  }
}

// Counts for chips
$counts = ['total'=>0,'Approved'=>0,'Pending'=>0,'Rejected'=>0,'Other'=>0];
try {
  $rows = $pdo->query("SELECT moderation_status AS s, COUNT(*) c FROM jobs WHERE archived_at IS NULL GROUP BY moderation_status")->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach ($rows as $r) {
    $s = $r['s'] ?: 'Other'; $c = (int)$r['c'];
    $counts['total'] += $c;
    if (isset($counts[$s])) $counts[$s] += $c; else $counts['Other'] += $c;
  }
} catch (Throwable $e) {}

// Dataset fetch according to filter
$list = [];
try {
  $sql = "SELECT j.*, u.company_name, jt.pwd_types FROM jobs j 
          JOIN users u ON u.user_id=j.employer_id 
          LEFT JOIN (
            SELECT job_id, GROUP_CONCAT(DISTINCT pwd_type ORDER BY pwd_type SEPARATOR ',') AS pwd_types
            FROM job_applicable_pwd_types
            GROUP BY job_id
          ) jt ON jt.job_id = j.job_id
          WHERE j.archived_at IS NULL";
  $params = [];
  if ($statusFilter !== 'All') { $sql .= " AND j.moderation_status=?"; $params[] = $statusFilter; }
  if ($q !== '') { $sql .= " AND (j.title LIKE ? OR u.company_name LIKE ?)"; $params[] = '%'.$q.'%'; $params[] = '%'.$q.'%'; }
  $sql .= " ORDER BY j.created_at DESC LIMIT 500";
  $st = $pdo->prepare($sql); $st->execute($params); $list = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $list = []; }

include '../includes/header.php';
?>
<div class="admin-layout">
  <?php include '../includes/admin_sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-page-header mb-3">
      <div class="page-title-block">
        <h1 class="page-title"><i class="bi bi-clipboard-check"></i><span>Jobs Moderation</span></h1>
        <p class="page-sub">Review, approve or reject job postings before they go live.</p>
      </div>
      <div class="page-actions"></div>
    </div>

<style>
/* Reuse admin look from Employers page */
.admin-page-header{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:1.25rem;padding:0 .25rem .2rem;border-bottom:1px solid rgba(255,255,255,.07);} 
.admin-page-header .page-title{margin:0;font-size:1.35rem;font-weight:600;display:flex;align-items:center;gap:.65rem;color:#f0f6ff;letter-spacing:.5px;} 
.admin-page-header .page-title i{font-size:1.55rem;line-height:1;color:#6cb2ff;filter:drop-shadow(0 2px 4px rgba(0,0,0,.4));}
.admin-page-header .page-sub{margin:.15rem 0 0;font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;font-weight:600;color:#6e829b;}
.chips{display:flex;flex-wrap:wrap;gap:.5rem;margin:1rem 0 .9rem}
.chip{--bg:#162335;--bd:rgba(255,255,255,.08);display:inline-flex;align-items:center;gap:.4rem;font-size:.65rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;padding:.55rem .7rem;border:1px solid var(--bd);background:var(--bg);color:#c2d2e6;border-radius:8px;line-height:1;text-decoration:none}
.chip .count{background:rgba(255,255,255,.08);padding:.2rem .45rem;border-radius:5px;font-weight:700;font-size:.65rem}
.chip.active{background:linear-gradient(135deg,#224a88,#18355f);color:#fff;border-color:#4d7ed4;box-shadow:0 4px 12px -6px rgba(0,0,0,.6)}
.filter-bar{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;margin-bottom:.9rem}
.filter-bar .search-box{flex:1 1 260px;position:relative}
.filter-bar .search-box input{background:#101b2b;border:1px solid #233246;color:#dbe6f5;padding:.55rem .75rem .55rem 2rem;font-size:.8rem;border-radius:8px;width:100%}
.filter-bar .search-box i{position:absolute;left:.6rem;top:50%;transform:translateY(-50%);color:#6a7b92;font-size:.9rem}
.table-wrapper{position:relative;border:1px solid rgba(255,255,255,.06);background:#0f1827;border-radius:16px;overflow:hidden;box-shadow:0 6px 22px -12px rgba(0,0,0,.65)}
table.jobs-table{margin:0;border-collapse:separate;border-spacing:0;width:100%}
table.jobs-table thead th{background:#142134;color:#ced8e6;font-size:.65rem;font-weight:600;letter-spacing:.09em;text-transform:uppercase;padding:.75rem .9rem;border-bottom:1px solid #1f2e45;position:sticky;top:0;z-index:2}
table.jobs-table tbody td{padding:.9rem .9rem;vertical-align:top;font-size:.78rem;color:#d2dbe7;border-bottom:1px solid #132031}
table.jobs-table tbody tr:last-child td{border-bottom:none}
.badge-mod{display:inline-flex;align-items:center;gap:.35rem;font-size:.62rem;font-weight:700;letter-spacing:.05em;padding:.35rem .55rem;border-radius:6px;text-transform:uppercase}
.badge-mod.pending{background:linear-gradient(135deg,#8a660c,#3f2f07);color:#fff1c7;border:1px solid #5f460b}
.badge-mod.approved{background:linear-gradient(135deg,#1f7a46,#0f3d24);color:#d8ffe9;border:1px solid #1c5b36}
.badge-mod.rejected{background:linear-gradient(135deg,#8a1d1d,#470e0e);color:#ffe1e1;border:1px solid #611414}
.decision-form .btn{font-size:.72rem}
.decision-form input[name=reason]{min-width:240px}
.tags{display:flex;flex-wrap:wrap;gap:.35rem}
.tag{display:inline-flex;align-items:center;padding:.18rem .45rem;border-radius:999px;font-size:.62rem;font-weight:700;letter-spacing:.03em;color:#cfe3ff;background:rgba(108,178,255,.12);border:1px solid rgba(108,178,255,.35)}
</style>

    <div class="chips">
      <?php
        $mk = function($label,$count,$val) use ($statusFilter){
          $active = ($statusFilter === $val) ? 'active' : '';
          $href = 'admin_jobs_moderation.php?status='.urlencode($val);
          return '<a class="chip '.$active.'" href="'.$href.'">'.htmlspecialchars($label).' <span class="count">'.(int)$count.'</span></a>';
        };
        echo $mk('Total', $counts['total'], 'All');
        echo $mk('Pending', $counts['Pending'], 'Pending');
        echo $mk('Approved', $counts['Approved'], 'Approved');
        echo $mk('Rejected', $counts['Rejected'], 'Rejected');
      ?>
    </div>

    <form class="filter-bar" method="get" action="admin_jobs_moderation.php">
      <div class="search-box">
        <i class="bi bi-search"></i>
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search job title or company">
      </div>
      <select name="status" class="form-select form-select-sm" style="max-width:160px">
        <?php foreach (['Pending','Approved','Rejected','All'] as $opt): ?>
          <option value="<?php echo $opt; ?>" <?php if($opt===$statusFilter) echo 'selected'; ?>><?php echo $opt; ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-sm btn-secondary" type="submit">Apply</button>
      <a class="btn btn-sm btn-outline-light" href="admin_jobs_moderation.php">Reset</a>
    </form>

    <?php foreach ($messages as $m): ?>
      <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-1"></i><?php echo $m; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-1"></i><?php echo htmlspecialchars($e); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endforeach; ?>

    <div class="table-wrapper">
      <table class="jobs-table">
        <thead>
          <tr>
            <th>Job</th>
            <th>Employer</th>
            <th>Posted</th>
            <th>Moderation</th>
            <th style="width:420px">Decision</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$list): ?>
            <tr><td colspan="5" class="text-muted small">No jobs to display.</td></tr>
          <?php else: foreach ($list as $j):
            $mod = $j['moderation_status'] ?? 'Pending';
            $badgeCls = strtolower($mod);
          ?>
            <tr>
              <td>
                <div class="fw-semibold"><a href="job_view.php?job_id=<?php echo urlencode($j['job_id']); ?>" target="_blank" class="text-decoration-none"><?php echo htmlspecialchars($j['title']); ?></a></div>
                <?php 
                  $typesCsv = trim((string)($j['pwd_types'] ?? ($j['applicable_pwd_types'] ?? '')));
                  if ($typesCsv !== '') {
                    $parts = array_filter(array_map('trim', explode(',', $typesCsv)), fn($v)=>$v!=='');
                    $parts = array_values(array_unique($parts));
                ?>
                  <div class="tags mt-1">
                    <?php foreach ($parts as $t): ?>
                      <span class="tag"><?php echo htmlspecialchars($t); ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php } ?>
              </td>
              <td><?php echo htmlspecialchars($j['company_name'] ?? ''); ?></td>
              <td class="text-nowrap"><?php echo htmlspecialchars(date('M j, Y', strtotime($j['created_at']))); ?></td>
              <td>
                <span class="badge-mod <?php echo $badgeCls; ?>"><?php echo htmlspecialchars($mod); ?></span>
                <?php if (($j['moderation_reason'] ?? '') !== ''): ?>
                  <div class="small text-muted mt-1">Reason: <?php echo htmlspecialchars($j['moderation_reason']); ?></div>
                <?php endif; ?>
              </td>
              <td>
                <form method="post" class="decision-form d-flex gap-2 align-items-start flex-wrap">
                  <input type="hidden" name="job_id" value="<?php echo htmlspecialchars($j['job_id']); ?>">
                  <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                  <input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>">
                  <select name="action" class="form-select form-select-sm" style="max-width:140px">
                    <option value="approve" <?php echo $mod==='Approved'?'selected':''; ?>>Approve</option>
                    <option value="reject" <?php echo $mod==='Rejected'?'selected':''; ?>>Reject</option>
                  </select>
                  <div class="reason-wrap <?php echo $mod==='Rejected' ? '' : 'd-none'; ?>" style="flex:1 1 260px;min-width:260px;">
                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason (required for rejection)" value="<?php echo htmlspecialchars($j['moderation_reason'] ?? ''); ?>" <?php echo $mod==='Rejected' ? 'required' : ''; ?>>
                  </div>
                  <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-save"></i> Save</button>
                </form>
                <div class="small text-muted mt-1">Decided: <?php echo htmlspecialchars($j['moderation_decided_at'] ? date('M j, Y H:i', strtotime($j['moderation_decided_at'])) : '—'); ?></div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  </div><!-- /.admin-main -->
</div><!-- /.admin-layout -->

<?php include '../includes/footer.php'; ?>
<script>
  (function(){
    function updateRow(form){
      var sel = form.querySelector('select[name="action"]');
      var wrap = form.querySelector('.reason-wrap');
      var input = form.querySelector('input[name="reason"]');
      if (!sel || !wrap || !input) return;
      var show = (sel.value === 'reject');
      wrap.classList.toggle('d-none', !show);
      input.required = !!show;
    }
    document.querySelectorAll('.decision-form').forEach(function(form){
      var sel = form.querySelector('select[name="action"]');
      if (!sel) return;
      sel.addEventListener('change', function(){ updateRow(form); });
      // initialize state
      updateRow(form);
    });
  })();
</script>