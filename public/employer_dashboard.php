<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/Job.php';
require_once '../classes/User.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

Helpers::requireLogin();
// Enforce employer role with friendly flash + redirect fallback
Helpers::requireRole('employer');

Helpers::storeLastPage();

/* Current employer */
$me   = User::findById($_SESSION['user_id']);
$jobs = Job::listByEmployer($me->user_id); // returns array rows (or objects)

$structuredFlashes = [];
$rawFlashSnapshot  = $_SESSION['flash'] ?? [];

if (method_exists('Helpers','getFlashes')) {
    $assoc = Helpers::getFlashes();
    if ($assoc) {
        foreach ($assoc as $k=>$v) {
            $msg = trim((string)$v);
            if ($msg==='') continue;
            $type = match($k) {
                'error','danger' => 'danger',
                'success','msg'  => 'success',
                'warn','warning' => 'warning',
                default          => 'info'
            };
            $structuredFlashes[] = ['type'=>$type,'message'=>$msg];
        }
    }
} else {
    if ($rawFlashSnapshot) {
        foreach ($rawFlashSnapshot as $k=>$v) {
            $msg = trim((string)$v);
            if ($msg==='') continue;
            $type = match($k) {
                'error','danger' => 'danger',
                'success','msg'  => 'success',
                'warn','warning' => 'warning',
                default          => 'info'
            };
            $structuredFlashes[] = ['type'=>$type,'message'=>$msg];
        }
        unset($_SESSION['flash']);
    }
}

/* Job stats */
$totalJobs      = count($jobs);
$openJobs       = count(array_filter($jobs, fn($j)=>($j['status'] ?? '')==='Open'));
$closedJobs     = count(array_filter($jobs, fn($j)=>($j['status'] ?? '')==='Closed'));
$suspendedJobs  = count(array_filter($jobs, fn($j)=>($j['status'] ?? '')==='Suspended'));

/* Single CSRF token for all inline action links (status changes etc.) */
$pageCsrf = Helpers::csrfToken();

/* Applications & recent apps */
$recentApps = [];
$applicationsToday = 0;
try {
    if ($totalJobs) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT a.application_id, a.job_id, a.status, a.created_at, a.match_score,
                   j.title, u.name AS applicant_name
            FROM applications a
            JOIN jobs j ON a.job_id = j.job_id
            JOIN users u ON u.user_id = a.user_id
            WHERE j.employer_id = ?
            ORDER BY a.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$me->user_id]);
        $recentApps = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtToday = $pdo->prepare("
            SELECT COUNT(*) FROM applications a
            JOIN jobs j ON a.job_id = j.job_id
            WHERE j.employer_id = ? AND DATE(a.created_at)=CURDATE()
        ");
        $stmtToday->execute([$me->user_id]);
        $applicationsToday = (int)$stmtToday->fetchColumn();
    }
} catch (Throwable $e) {
    $recentApps = [];
    $applicationsToday = 0;
}

include '../includes/header.php';
include '../includes/nav.php';
?>
<meta name="csrf-token" content="<?php echo htmlspecialchars($pageCsrf); ?>">
<style>
  /* Dashboard hero for employer (reuse user hero vibe) */
  .em-hero { position:relative; padding:3rem 0 2rem; }
  .em-hero::before { content:""; position:absolute; inset:0; background:linear-gradient(135deg, rgba(13,110,253,.08), rgba(102,16,242,.08)); }
  .em-hero h1 { font-weight:800; font-size:clamp(1.9rem,3.6vw,2.5rem); letter-spacing:-.6px; background:linear-gradient(135deg,var(--primary-blue),var(--primary-purple)); -webkit-background-clip:text; background-clip:text; color:transparent; margin:0 0 .6rem; }
  .em-hero .em-sub { max-width:760px; font-size:.95rem; color:#4c5b68; }
  .em-actions { display:flex; flex-wrap:wrap; gap:.65rem; margin-top:1.15rem; }
  .em-actions .btn { display:inline-flex; align-items:center; gap:.4rem; }
  .em-stats-grid { display:grid; gap:1rem; margin-top:1.5rem; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); }
  .em-stat { position:relative; background:#ffffff; border:1px solid rgba(13,110,253,.12); border-radius:1rem; padding:.9rem .85rem .95rem; overflow:hidden; box-shadow:0 6px 18px -8px rgba(13,110,253,.22), 0 3px 8px rgba(0,0,0,.04); }
  .em-stat::before { content:""; position:absolute; inset:0; background:radial-gradient(circle at 15% 18%, rgba(255,193,7,.18), transparent 60%), radial-gradient(circle at 85% 15%, rgba(102,16,242,.18), transparent 60%); opacity:.5; mix-blend-mode:overlay; pointer-events:none; }
  .em-stat .label { font-size:.6rem; font-weight:700; letter-spacing:.75px; text-transform:uppercase; color:#5d6c7a; margin-bottom:.25rem; }
  .em-stat .value { font-size:1.45rem; font-weight:700; letter-spacing:-.5px; background:linear-gradient(135deg,var(--primary-blue),var(--primary-purple)); -webkit-background-clip:text; background-clip:text; color:transparent; }
  .em-stat .pill { position:absolute; top:.55rem; right:.55rem; font-size:.55rem; background:#eef6ff; color:#1d4879; font-weight:600; letter-spacing:.6px; padding:.25rem .45rem; border-radius:.5rem; }
  .dash-card-stat { border-left:4px solid #0d6efd; }
  .dash-card-stat.open { border-color:#198754; }
  .dash-card-stat.suspended { border-color:#ffc107; }
  .dash-card-stat.closed { border-color:#6c757d; }
  .dash-card-stat.apps { border-color:#6610f2; }
  .jobs-filter-input { max-width:260px; }
  /* NOTE: Lowered z-index below Bootstrap .sticky-top (1020) so navbar dropdown isn't hidden */
  .sticky-tabs { position:sticky; top:70px; /* offset below main site nav (approx 56px + spacing) */ z-index:900; background:#ffffffec; backdrop-filter:blur(8px) saturate(180%); padding:.55rem .65rem .5rem; border-bottom:1px solid #e4edf6; box-shadow:0 4px 10px -6px rgba(0,0,0,.08); }
  .sticky-tabs .nav-link { padding:.45rem .85rem; border-radius:.7rem; font-weight:600; }
  .sticky-tabs .nav-link.active { background:linear-gradient(135deg,var(--primary-blue),var(--primary-purple)); color:#fff !important; box-shadow:0 4px 12px -4px rgba(13,110,253,.45); }
  .recent-app-card { position:relative; background:#ffffff; border:1px solid #e4edf6; border-radius:1rem; padding:1.15rem 1.1rem 1.05rem; box-shadow:0 8px 24px -10px rgba(13,110,253,.22), 0 4px 12px rgba(0,0,0,.05); }
  .jobs-table-card { position:relative; background:#ffffff; border:1px solid #e4edf6; border-radius:1.15rem; box-shadow:0 10px 30px -12px rgba(13,110,253,.25), 0 4px 16px rgba(0,0,0,.05); }
  .jobs-table-card::before { content:""; position:absolute; inset:0; background:radial-gradient(circle at 12% 18%, rgba(255,193,7,.20), transparent 60%), radial-gradient(circle at 85% 15%, rgba(102,16,242,.20), transparent 60%); mix-blend-mode:overlay; opacity:.45; pointer-events:none; }
  .jobs-table-card .card-body { position:relative; z-index:2; }
  .mini-heading { font-size:.7rem; font-weight:700; letter-spacing:.75px; text-transform:uppercase; color:#5d6c7a; margin:0 0 .65rem; display:flex; align-items:center; gap:.45rem; }
  .mini-heading .icon { width:2rem; height:2rem; border-radius:.6rem; background:linear-gradient(135deg,var(--primary-blue),var(--primary-purple)); display:inline-flex; align-items:center; justify-content:center; color:#fff; font-size:.9rem; box-shadow:0 4px 12px -4px rgba(13,110,253,.55); }
  .table-sm th { font-size:.65rem; letter-spacing:.65px; text-transform:uppercase; }
  .quick-cta { display:grid; gap:.75rem; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); margin-top:1.4rem; }
  .quick-cta a { text-decoration:none; position:relative; background:#fff; border:1px solid #e4edf6; border-radius:.85rem; padding:.8rem .85rem .85rem; font-size:.75rem; font-weight:600; letter-spacing:.4px; color:#233544; box-shadow:0 6px 16px -8px rgba(13,110,253,.22), 0 3px 8px rgba(0,0,0,.05); transition:.3s; display:flex; align-items:center; gap:.5rem; }
  .quick-cta a .qc-icon { width:34px; height:34px; border-radius:.7rem; background:linear-gradient(135deg,var(--primary-blue),var(--primary-purple)); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:1rem; box-shadow:0 4px 12px -4px rgba(13,110,253,.55); }
  .quick-cta a:hover { transform:translateY(-4px); box-shadow:0 12px 30px -10px rgba(13,110,253,.35), 0 6px 18px rgba(0,0,0,.07); }
  @media (max-width: 575.98px){ .sticky-tabs { top:62px; padding:.5rem .5rem .45rem; } }
</style>

<div class="sticky-tabs mb-3 border-bottom">
  <ul class="nav nav-pills small">
    <li class="nav-item">
      <a class="nav-link active" href="#overview" data-dash-tab="overview"><i class="bi bi-speedometer2 me-1"></i>Overview</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="#jobs" data-dash-tab="jobs"><i class="bi bi-briefcase me-1"></i>Jobs</a>
    </li>
  </ul>
</div>

<?php if ($structuredFlashes): ?>
  <div class="mb-3">
    <?php foreach ($structuredFlashes as $f):
      $t = htmlspecialchars($f['type']);
      $m = $f['message']; 
      $icon = match($t){
        'success'=>'check2-circle',
        'danger'=>'exclamation-triangle',
        'warning'=>'exclamation-circle',
        default=>'info-circle'
      };
    ?>
      <div class="alert alert-<?php echo $t; ?> alert-dismissible fade show flash-auto" role="alert">
        <i class="bi bi-<?php echo $icon; ?> me-2"></i><?php echo $m; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- OVERVIEW TAB -->
<section id="overview" class="dash-section">
  <div class="em-hero">
    <div class="container px-0">
      <h1 class="fade-up fade-delay-2">Hello, <?php echo Helpers::sanitizeOutput($me->name); ?></h1>
      <p class="em-sub fade-up fade-delay-3">Manage your job postings, track candidate interest, and optimize hiring for inclusive talent.</p>
      <div class="em-actions fade-up fade-delay-4">
        <a href="employer_profile.php" class="btn btn-outline-secondary order-0"><i class="bi bi-building"></i><span>Company Profile</span></a>
        <a href="#jobs" class="btn btn-outline-primary" data-dash-tab="jobs"><i class="bi bi-briefcase"></i><span>View Jobs</span></a>
        <a href="jobs_create.php" class="btn btn-gradient"><i class="bi bi-plus-lg"></i><span>Post Job</span></a>
      </div>
      <div class="em-stats-grid fade-up fade-delay-5" role="list" aria-label="Job statistics">
        <div class="em-stat" role="listitem"><div class="label">Total Jobs</div><div class="value"><?php echo $totalJobs; ?></div><div class="pill">All</div></div>
        <div class="em-stat" role="listitem"><div class="label">Open</div><div class="value"><?php echo $openJobs; ?></div><div class="pill" style="background:#e6f8ed; color:#10632c;">Active</div></div>
        <div class="em-stat" role="listitem"><div class="label">Suspended</div><div class="value"><?php echo $suspendedJobs; ?></div><div class="pill" style="background:#fff6e0; color:#7a4b00;">Hold</div></div>
        <div class="em-stat" role="listitem"><div class="label">Closed</div><div class="value"><?php echo $closedJobs; ?></div><div class="pill" style="background:#f1f3f5; color:#4d5963;">Done</div></div>
        <div class="em-stat" role="listitem"><div class="label">Apps Today</div><div class="value"><?php echo $applicationsToday; ?></div><div class="pill" style="background:#eef6ff;">New</div></div>
      </div>
    </div>
  </div>

  <div class="row g-4 align-items-start mt-1">
    <div class="col-lg-7">
      <div class="recent-app-card mb-4 fade-up fade-delay-4">
        <div class="mini-heading"><span class="icon"><i class="bi bi-envelope-open"></i></span><span>Recent Applications</span></div>
        <?php if (!$recentApps): ?>
          <div class="text-muted small">No applications yet.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Applicant</th>
                  <th>Job</th>
                  <th>Status</th>
                  <th>Match</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody class="small">
              <?php foreach ($recentApps as $ra):
                $s = $ra['status'];
                $badge = $s==='Approved'?'success':($s==='Declined'?'danger':($s==='Pending'?'warning':'secondary'));
              ?>
                <tr>
                  <td><?php echo htmlspecialchars($ra['applicant_name']); ?></td>
                  <td><a href="job_view.php?job_id=<?php echo urlencode($ra['job_id']); ?>"><?php echo htmlspecialchars($ra['title']); ?></a></td>
                  <td><span class="badge text-bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($s); ?></span></td>
                  <td><?php echo htmlspecialchars((int)$ra['match_score']); ?>%</td>
                  <td><?php echo htmlspecialchars(date('M d', strtotime($ra['created_at']))); ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="recent-app-card mb-4 fade-up fade-delay-5">
        <div class="mini-heading"><span class="icon"><i class="bi bi-lightning-charge"></i></span><span>Quick Actions</span></div>
        <div class="quick-cta">
          <a href="jobs_create.php"><span class="qc-icon"><i class="bi bi-plus-lg"></i></span><span>Post New Job</span></a>
          <a href="#jobs" data-dash-tab="jobs"><span class="qc-icon"><i class="bi bi-briefcase"></i></span><span>Manage Jobs</span></a>
          <a href="employer_profile.php"><span class="qc-icon"><i class="bi bi-building"></i></span><span>Company Profile</span></a>
          <a href="support_contact.php"><span class="qc-icon"><i class="bi bi-life-preserver"></i></span><span>Support</span></a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- JOBS TAB -->
<section id="jobs" class="dash-section d-none">
  <div class="jobs-manage-card">
    <div class="jm-head d-flex flex-wrap align-items-center justify-content-between gap-2">
      <h2 class="h5 fw-semibold mb-0 d-flex align-items-center gap-2"><i class="bi bi-briefcase"></i><span>My Jobs</span></h2>
      <div class="jm-filters d-flex flex-wrap align-items-center gap-2">
        <div class="position-relative">
          <i class="bi bi-search jm-search-ic"></i>
          <input type="text" id="jobFilterInput" class="form-control form-control-sm jobs-filter-input ps-4" placeholder="Search jobs" aria-label="Search jobs">
        </div>
        <select id="statusFilter" class="form-select form-select-sm" aria-label="Filter by status">
          <option value="">All Status</option>
          <option value="Open">Open</option>
          <option value="Suspended">Suspended</option>
          <option value="Closed">Closed</option>
        </select>
        <a href="jobs_create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Post Job</a>
      </div>
    </div>
    <div class="jm-meta small text-muted mt-1" id="jobsMeta" aria-live="polite"></div>
    <div class="jm-table-wrapper mt-3">
      <table id="jobsTable" class="table table-sm align-middle table-hover mb-0 d-none d-md-table" aria-describedby="jobsMeta">
        <thead class="table-light">
          <tr>
            <th scope="col">Job</th>
            <th scope="col">Type</th>
            <th scope="col">Salary</th>
            <th scope="col">Status</th>
            <th scope="col">Posted</th>
            <th scope="col" class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$jobs): ?>
          <tr><td colspan="6" class="text-muted small">You have not posted any jobs yet.</td></tr>
        <?php else: ?>
          <?php foreach ($jobs as $j):
            $status = $j['status'] ?? 'Open';
            $mod = $j['moderation_status'] ?? 'Approved';
            $modReason = trim((string)($j['moderation_reason'] ?? ''));
            $badge = $status==='Open'?'success':($status==='Closed'?'secondary':($status==='Suspended'?'warning':'dark'));
            $salary = '—';
            if (!empty($j['salary_min']) && !empty($j['salary_max'])) {
                $salary = 'PHP '.number_format($j['salary_min']).'–'.number_format($j['salary_max']).' / '.ucfirst($j['salary_period'] ?? 'monthly');
            }
            $locationParts = array_filter([$j['location_city'] ?? '', $j['location_region'] ?? '']);
            $loc = $locationParts ? implode(', ',$locationParts) : '—';
            $img = $j['job_image'] ?? null;
          ?>
            <tr data-title="<?php echo htmlspecialchars(mb_strtolower($j['title'])); ?>" data-status="<?php echo htmlspecialchars($status); ?>">
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="jm-thumb">
                    <?php if ($img): ?>
                      <img src="../<?php echo htmlspecialchars($img); ?>" alt="" loading="lazy">
                    <?php else: ?>
                      <span class="jm-thumb-fallback"><i class="bi bi-briefcase"></i></span>
                    <?php endif; ?>
                  </div>
                  <div class="jm-jobtext">
                    <a href="job_view.php?job_id=<?php echo urlencode($j['job_id']); ?>" class="jm-title-link"><?php echo htmlspecialchars($j['title']); ?></a>
                    <div class="jm-sub small text-muted"><?php echo htmlspecialchars($loc); ?></div>
                  </div>
                </div>
              </td>
              <td class="small"><?php echo htmlspecialchars(($j['remote_option'] ?? 'Work From Home').' · '.($j['employment_type'] ?? 'Full time')); ?></td>
              <td class="small"><?php echo htmlspecialchars($salary); ?></td>
              <td>
                <span class="jm-status jm-status-<?php echo strtolower($status); ?>">
                  <i class="bi bi-<?php echo $status==='Open'?'play-fill':($status==='Suspended'?'pause-fill':'stop-fill'); ?> me-1"></i><?php echo htmlspecialchars($status); ?>
                </span>
                <?php if ($mod !== 'Approved'): ?>
                  <div class="small mt-1">
                    <span class="badge rounded-pill text-bg-<?php echo $mod==='Pending'?'warning':'danger'; ?>">
                      <?php echo htmlspecialchars($mod); ?>
                    </span>
                    <?php if ($mod==='Rejected' && $modReason!==''): ?>
                      <span class="text-muted ms-1" title="Reason">(<?php echo htmlspecialchars($modReason); ?>)</span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td class="small text-nowrap"><?php echo htmlspecialchars(date('M d, Y', strtotime($j['created_at']))); ?></td>
              <td class="text-center text-nowrap">
                <div class="btn-group btn-group-sm" role="group" aria-label="Actions for <?php echo htmlspecialchars($j['title']); ?>">
                  <a class="btn btn-outline-secondary" href="job_view.php?job_id=<?php echo urlencode($j['job_id']); ?>" aria-label="View job"><i class="bi bi-box-arrow-up-right"></i></a>
                  <a class="btn btn-outline-primary" href="jobs_edit.php?job_id=<?php echo urlencode($j['job_id']); ?>" aria-label="Edit job"><i class="bi bi-pencil"></i></a>
                  <a class="btn btn-outline-info" href="employer_applicants.php?job_id=<?php echo urlencode($j['job_id']); ?>" aria-label="View applicants"><i class="bi bi-people"></i></a>
                  <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-warning dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Change status">
                      <i class="bi bi-sliders"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                      <?php if ($status !== 'Open'): ?>
                        <li><a class="dropdown-item" href="jobs_status.php?job_id=<?php echo urlencode($j['job_id']); ?>&to=Open&csrf=<?php echo $pageCsrf; ?>">Set Open</a></li>
                      <?php endif; ?>
                      <?php if ($status !== 'Suspended'): ?>
                        <li><a class="dropdown-item" href="jobs_status.php?job_id=<?php echo urlencode($j['job_id']); ?>&to=Suspended&csrf=<?php echo $pageCsrf; ?>">Suspend</a></li>
                      <?php endif; ?>
                      <?php if ($status !== 'Closed'): ?>
                        <li><a class="dropdown-item" href="jobs_status.php?job_id=<?php echo urlencode($j['job_id']); ?>&to=Closed&csrf=<?php echo $pageCsrf; ?>">Close</a></li>
                      <?php endif; ?>
                    </ul>
                  </div>
                  <a class="btn btn-outline-danger" href="jobs_delete.php?job_id=<?php echo urlencode($j['job_id']); ?>" aria-label="Delete job" data-confirm-title="Delete Job" data-confirm="Delete this job? This cannot be undone." data-confirm-yes="Delete" data-confirm-no="Cancel"><i class="bi bi-trash"></i></a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>

      <!-- Mobile Cards -->
      <div class="jm-cards d-md-none" id="jobsCards">
        <?php if ($jobs): foreach ($jobs as $j):
          $status = $j['status'] ?? 'Open';
          $mod = $j['moderation_status'] ?? 'Approved';
          $modReason = trim((string)($j['moderation_reason'] ?? ''));
          $salary = (!empty($j['salary_min']) && !empty($j['salary_max'])) ? 'PHP '.number_format($j['salary_min']).'–'.number_format($j['salary_max']).' / '.ucfirst($j['salary_period'] ?? 'monthly') : '—';
          $locationParts = array_filter([$j['location_city'] ?? '', $j['location_region'] ?? '']);
          $loc = $locationParts ? implode(', ',$locationParts) : '—';
          $img = $j['job_image'] ?? null;
        ?>
        <div class="jm-card" data-title="<?php echo htmlspecialchars(mb_strtolower($j['title'])); ?>" data-status="<?php echo htmlspecialchars($status); ?>">
          <div class="jm-card-head d-flex gap-2">
            <div class="jm-thumb">
              <?php if ($img): ?>
                <img src="../<?php echo htmlspecialchars($img); ?>" alt="" loading="lazy">
              <?php else: ?>
                <span class="jm-thumb-fallback"><i class="bi bi-briefcase"></i></span>
              <?php endif; ?>
            </div>
            <div class="flex-grow-1">
              <a href="job_view.php?job_id=<?php echo urlencode($j['job_id']); ?>" class="jm-title-link"><?php echo htmlspecialchars($j['title']); ?></a>
              <div class="jm-sub small text-muted"><?php echo htmlspecialchars($loc); ?></div>
            </div>
            <span class="jm-status jm-status-<?php echo strtolower($status); ?> small flex-shrink-0"><i class="bi bi-<?php echo $status==='Open'?'play-fill':($status==='Suspended'?'pause-fill':'stop-fill'); ?> me-1"></i><?php echo htmlspecialchars($status); ?></span>
          </div>
          <?php if ($mod !== 'Approved'): ?>
            <div class="small mt-1">
              <span class="badge rounded-pill text-bg-<?php echo $mod==='Pending'?'warning':'danger'; ?>"><?php echo htmlspecialchars($mod); ?></span>
              <?php if ($mod==='Rejected' && $modReason!==''): ?>
                <span class="text-muted ms-1" title="Reason">(<?php echo htmlspecialchars($modReason); ?>)</span>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <div class="jm-card-body small mt-2">
            <div class="mb-1"><i class="bi bi-cpu me-1"></i><?php echo htmlspecialchars(($j['remote_option'] ?? 'Work From Home').' · '.($j['employment_type'] ?? 'Full time')); ?></div>
            <div class="mb-1"><i class="bi bi-cash-coin me-1"></i><?php echo htmlspecialchars($salary); ?></div>
            <div class="jm-actions btn-group btn-group-sm mt-2" role="group" aria-label="Actions for <?php echo htmlspecialchars($j['title']); ?>">
              <a class="btn btn-outline-secondary" href="job_view.php?job_id=<?php echo urlencode($j['job_id']); ?>" aria-label="View job"><i class="bi bi-box-arrow-up-right"></i></a>
              <a class="btn btn-outline-primary" href="jobs_edit.php?job_id=<?php echo urlencode($j['job_id']); ?>" aria-label="Edit job"><i class="bi bi-pencil"></i></a>
              <a class="btn btn-outline-info" href="employer_applicants.php?job_id=<?php echo urlencode($j['job_id']); ?>" aria-label="View applicants"><i class="bi bi-people"></i></a>
              <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-warning dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Change status">
                  <i class="bi bi-sliders"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <?php if ($status !== 'Open'): ?>
                    <li><a class="dropdown-item" href="jobs_status.php?job_id=<?php echo urlencode($j['job_id']); ?>&to=Open&csrf=<?php echo $pageCsrf; ?>">Set Open</a></li>
                  <?php endif; ?>
                  <?php if ($status !== 'Suspended'): ?>
                    <li><a class="dropdown-item" href="jobs_status.php?job_id=<?php echo urlencode($j['job_id']); ?>&to=Suspended&csrf=<?php echo $pageCsrf; ?>">Suspend</a></li>
                  <?php endif; ?>
                  <?php if ($status !== 'Closed'): ?>
                    <li><a class="dropdown-item" href="jobs_status.php?job_id=<?php echo urlencode($j['job_id']); ?>&to=Closed&csrf=<?php echo $pageCsrf; ?>">Close</a></li>
                  <?php endif; ?>
                </ul>
              </div>
              <a class="btn btn-outline-danger" href="jobs_delete.php?job_id=<?php echo urlencode($j['job_id']); ?>" aria-label="Delete job" data-confirm-title="Delete Job" data-confirm="Delete this job? This cannot be undone." data-confirm-yes="Delete" data-confirm-no="Cancel"><i class="bi bi-trash"></i></a>
            </div>
          </div>
          <div class="jm-posted small text-muted mt-1">Posted: <?php echo htmlspecialchars(date('M d, Y', strtotime($j['created_at']))); ?></div>
        </div>
        <?php endforeach; else: ?>
          <div class="text-muted small">You have not posted any jobs yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php include '../includes/footer.php'; ?>

<script>
(function(){
  /* ===== Jobs Management Enhancement ===== */
  const tabLinks = document.querySelectorAll('[data-dash-tab]');
  const sections = {
    overview: document.getElementById('overview'),
    jobs: document.getElementById('jobs')
  };
  function showSection(name){
    for (const k in sections) {
      if (sections[k]) sections[k].classList.toggle('d-none', k!==name);
    }
    tabLinks.forEach(a=>{
      if (a.dataset.dashTab===name) a.classList.add('active');
      else a.classList.remove('active');
    });
    if (history.pushState) {
      const base = window.location.href.split('#')[0];
      history.replaceState(null,'', base + '#' + name);
    }
  }
  tabLinks.forEach(a=>{
    a.addEventListener('click', function(e){
      e.preventDefault();
      showSection(this.dataset.dashTab);
    });
  });
  const hash = window.location.hash.replace('#','');
  if (hash && sections[hash]) showSection(hash);

  const filterInput = document.getElementById('jobFilterInput');
  const statusFilter = document.getElementById('statusFilter');
  const rows = Array.from(document.querySelectorAll('#jobsTable tbody tr'));
  const cards = Array.from(document.querySelectorAll('#jobsCards .jm-card'));
  const meta = document.getElementById('jobsMeta');
  function match(item,q,st){
    const title = item.getAttribute('data-title') || '';
    const status = item.getAttribute('data-status') || '';
    const matchText   = !q || title.indexOf(q) !== -1;
    const matchStatus = !st || status === st;
    return matchText && matchStatus;
  }
  function applyFilter(){
    const q = (filterInput?.value||'').trim().toLowerCase();
    const st = statusFilter?.value||'';
    let shown = 0;
    rows.forEach(r=>{ const ok = match(r,q,st); r.style.display = ok?'':'none'; if(ok) shown++; });
    cards.forEach(c=>{ const ok = match(c,q,st); c.style.display = ok?'':'none'; });
    const total = rows.length || cards.length;
    if(meta){ meta.textContent = total?`Showing ${shown} of ${total} job${total!==1?'s':''}`:''; }
  }
  if (filterInput) filterInput.addEventListener('input', ()=>{ clearTimeout(filterInput._t); filterInput._t=setTimeout(applyFilter,180); });
  if (statusFilter) statusFilter.addEventListener('change', applyFilter);
  applyFilter();

  /* ===== AJAX Status Change ===== */
  const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  const liveRegion = (function(){
    let lr = document.getElementById('jobsStatusLive');
    if(!lr){
      lr = document.createElement('div');
      lr.id='jobsStatusLive';
      lr.className='visually-hidden';
      lr.setAttribute('aria-live','polite');
      document.body.appendChild(lr);
    }
    return lr;
  })();

  function updateStatusPill(container,newStatus){
    const mapIcon = {
      Open:'play-fill',
      Suspended:'pause-fill',
      Closed:'stop-fill'
    };
    const pill = container.querySelector('.jm-status');
    if(pill){
      pill.className = 'jm-status jm-status-'+ newStatus.toLowerCase();
      pill.innerHTML = `<i class="bi bi-${mapIcon[newStatus]||'play-fill'} me-1"></i>${newStatus}`;
    }
    // reflect dataset for filtering
    if(container.dataset){
      container.dataset.status = newStatus;
    }
  }

  function rebuildDropdown(menu,newStatus){
    if(!menu) return;
    const statuses=['Open','Suspended','Closed'];
    menu.innerHTML='';
    statuses.filter(s=>s!==newStatus).forEach(s=>{
      const li=document.createElement('li');
      const a=document.createElement('a');
      a.className='dropdown-item';
      a.href=`jobs_status.php?ajax=1&job_id=${menu.dataset.jobId}&to=${encodeURIComponent(s)}&csrf=${encodeURIComponent(csrfToken)}`;
      a.textContent = (s==='Open'?'Set Open':(s==='Suspended'?'Suspend':'Close'));
      li.appendChild(a); menu.appendChild(li);
    });
  }

  function handleStatusClick(e){
    const a = e.target.closest('a.dropdown-item');
    if(!a) return;
    if(!a.href.includes('jobs_status.php')) return; // not our link
    e.preventDefault();
    const url = new URL(a.href, window.location.origin);
    url.searchParams.set('ajax','1');
    fetch(url.toString(), { credentials:'same-origin' })
      .then(r=>r.json())
      .then(data=>{
        if(!data.ok){ throw new Error(data.error||'Failed'); }
        const jobId = data.job_id;
        const newStatus = data.current;
        // table row
        const row = document.querySelector(`#jobsTable tbody tr td .btn-group button.dropdown-toggle[data-job-id='${jobId}']`)?.closest('tr');
        // fallback: find by data-title parent with matching actions
        const rowAlt = [...document.querySelectorAll('#jobsTable tbody tr')].find(r=>r.querySelector(`a[href*='job_id=${jobId}']`));
        const containerRow = row || rowAlt;
        if(containerRow){ updateStatusPill(containerRow,newStatus); }
        // card
        const card = [...document.querySelectorAll('.jm-card')].find(c=>c.querySelector(`a[href*='job_id=${jobId}']`));
        if(card){ updateStatusPill(card,newStatus); }
        // rebuild dropdown menus for this job
        const menus = document.querySelectorAll(`ul.dropdown-menu[data-job-id='${jobId}']`);
        menus.forEach(m=> rebuildDropdown(m,newStatus));
        liveRegion.textContent = `Job ${jobId} status updated to ${newStatus}`;
      })
      .catch(err=>{
        liveRegion.textContent = `Status update failed: ${err.message}`;
      });
  }

  // attribute hooks for job id on menus
  document.querySelectorAll('#jobsTable tbody tr .dropdown-menu, .jm-card .dropdown-menu').forEach((menu)=>{
    const anchor = menu.closest('td, .jm-card')?.querySelector('a[href*="job_id="]');
    if(anchor){
      const jobIdMatch = anchor.href.match(/job_id=(\d+)/);
      if(jobIdMatch){ menu.dataset.jobId = jobIdMatch[1]; }
    }
  });
  document.addEventListener('click', handleStatusClick);

  // Keep aria-expanded in sync for Bootstrap dropdown toggles
  document.addEventListener('shown.bs.dropdown', function(e){
    const btn = e.target.querySelector('.dropdown-toggle');
    if(btn) btn.setAttribute('aria-expanded','true');
  });
  document.addEventListener('hidden.bs.dropdown', function(e){
    const btn = e.target.querySelector('.dropdown-toggle');
    if(btn) btn.setAttribute('aria-expanded','false');
  });
})();
</script>

<style>
/* Scoped Jobs Management Styles */
.jobs-manage-card{position:relative;background:#ffffff;border:1px solid #e3e8ee;border-radius:18px;padding:1.35rem 1.5rem 1.8rem;box-shadow:0 6px 26px -10px rgba(13,110,253,.22),0 4px 14px rgba(0,0,0,.05);} 
.jobs-manage-card::before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 12% 18%,rgba(255,193,7,.18),transparent 60%),radial-gradient(circle at 85% 15%,rgba(102,16,242,.18),transparent 60%);mix-blend-mode:overlay;opacity:.5;pointer-events:none;} 
.jm-head h2{font-weight:600;letter-spacing:.4px;} 
.jm-filters .form-control,.jm-filters .form-select{min-width:140px;} 
.jm-search-ic{position:absolute;left:.6rem;top:50%;transform:translateY(-50%);color:#6c7a86;font-size:.85rem;} 
.jm-thumb{width:40px;height:40px;border-radius:10px;overflow:hidden;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid #dbe3ea;} 
.jm-thumb img{width:100%;height:100%;object-fit:cover;} 
.jm-thumb-fallback{display:inline-flex;align-items:center;justify-content:center;color:#576b7b;font-size:1.1rem;} 
.jm-title-link{font-weight:600;text-decoration:none;color:#1d3553;} 
.jm-title-link:hover{color:#0d6efd;} 
.jm-status{display:inline-flex;align-items:center;font-size:.65rem;font-weight:700;letter-spacing:.6px;text-transform:uppercase;padding:.35rem .55rem;border-radius:1rem;background:#eef2f6;color:#344655;} 
.jm-status-open{background:#e7f8ed;color:#0b6a32;} 
.jm-status-suspended{background:#fff2d9;color:#7b4d00;} 
.jm-status-closed{background:#f1f3f5;color:#4d5963;} 
.jm-cards{display:grid;gap:1rem;} 
.jm-card{border:1px solid #e4edf6;border-radius:1rem;padding:.95rem 1rem 1.05rem;background:#ffffff;box-shadow:0 6px 20px -10px rgba(13,110,253,.22),0 4px 10px rgba(0,0,0,.05);position:relative;} 
.jm-card:hover{box-shadow:0 10px 28px -12px rgba(13,110,253,.3),0 6px 18px rgba(0,0,0,.07);} 
.jm-card .jm-actions .btn{font-size:.65rem;padding:.3rem .45rem;} 
@media (max-width:575.98px){.jobs-manage-card{padding:1.05rem 1rem 1.35rem;} .jm-filters{width:100%;} .jm-filters .form-control{flex:1;} }
</style>