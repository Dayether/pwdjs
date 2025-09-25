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
<style>
  .dash-card-stat { border-left:4px solid #0d6efd; }
  .dash-card-stat.open { border-color:#198754; }
  .dash-card-stat.suspended { border-color:#ffc107; }
  .dash-card-stat.closed { border-color:#6c757d; }
  .dash-card-stat.apps { border-color:#6610f2; }
  .jobs-filter-input { max-width:260px; }
  .sticky-tabs { position:sticky; top:56px; z-index:1020; background:#fff; padding-top:.5rem; }
  @media (max-width: 575.98px){
    .sticky-tabs { top:56px; }
  }
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
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="h5 fw-semibold mb-0"><i class="bi bi-speedometer2 me-2"></i>Employer Dashboard</h2>
    <a href="jobs_create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Post a Job</a>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-4 col-lg-3">
      <div class="card dash-card-stat border-0 shadow-sm h-100">
        <div class="card-body py-3">
          <div class="text-muted small">Total Jobs</div>
          <div class="fw-bold fs-5"><?php echo $totalJobs; ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-md-4 col-lg-3">
      <div class="card dash-card-stat open border-0 shadow-sm h-100">
        <div class="card-body py-3">
          <div class="text-muted small">Open</div>
            <div class="fw-bold fs-5 text-success"><?php echo $openJobs; ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-md-4 col-lg-3">
      <div class="card dash-card-stat suspended border-0 shadow-sm h-100">
        <div class="card-body py-3">
          <div class="text-muted small">Suspended</div>
          <div class="fw-bold fs-5 text-warning"><?php echo $suspendedJobs; ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-md-4 col-lg-3">
      <div class="card dash-card-stat closed border-0 shadow-sm h-100">
        <div class="card-body py-3">
          <div class="text-muted small">Closed</div>
          <div class="fw-bold fs-5 text-secondary"><?php echo $closedJobs; ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-md-4 col-lg-3">
      <div class="card dash-card-stat apps border-0 shadow-sm h-100">
        <div class="card-body py-3">
          <div class="text-muted small">Applications Today</div>
          <div class="fw-bold fs-5 text-primary"><?php echo $applicationsToday; ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Applications -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h6 fw-semibold mb-0"><i class="bi bi-envelope-open me-1"></i>Recent Applications</h3>
        <a href="#jobs" class="small text-decoration-none" data-dash-tab="jobs">Go to Jobs &raquo;</a>
      </div>
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
</section>

<!-- JOBS TAB -->
<section id="jobs" class="dash-section d-none">
  <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
    <h2 class="h5 fw-semibold mb-2 mb-lg-0"><i class="bi bi-briefcase me-2"></i>My Jobs</h2>
    <div class="d-flex flex-wrap gap-2">
      <input type="text" id="jobFilterInput" class="form-control form-control-sm jobs-filter-input" placeholder="Search jobs...">
      <select id="statusFilter" class="form-select form-select-sm">
        <option value="">All Status</option>
        <option value="Open">Open</option>
        <option value="Suspended">Suspended</option>
        <option value="Closed">Closed</option>
      </select>
      <a href="jobs_create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Post Job</a>
    </div>
  </div>

  <div class="table-responsive">
    <table id="jobsTable" class="table table-sm align-middle table-hover border mb-0">
      <thead class="table-light">
        <tr>
          <th>Title</th>
          <th>Location</th>
          <th>Type</th>
          <th>Salary</th>
          <th>Status</th>
          <th>Posted</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$jobs): ?>
        <tr><td colspan="7" class="text-muted small">You have not posted any jobs yet.</td></tr>
      <?php else: ?>
        <?php foreach ($jobs as $j):
          $status = $j['status'] ?? 'Open';
          $badge = $status==='Open'?'success':($status==='Closed'?'secondary':($status==='Suspended'?'warning':'dark'));
          $salary = '—';
          if (!empty($j['salary_min']) && !empty($j['salary_max'])) {
              $salary = 'PHP '.number_format($j['salary_min']).'–'.number_format($j['salary_max']).' / '.ucfirst($j['salary_period'] ?? 'monthly');
          }
          $locationParts = array_filter([$j['location_city'] ?? '', $j['location_region'] ?? '']);
          $loc = $locationParts ? implode(', ',$locationParts) : '—';
        ?>
          <tr data-title="<?php echo htmlspecialchars(mb_strtolower($j['title'])); ?>"
              data-status="<?php echo htmlspecialchars($status); ?>">
            <td>
              <a href="job_view.php?job_id=<?php echo urlencode($j['job_id']); ?>">
                <?php echo htmlspecialchars($j['title']); ?>
              </a>
            </td>
            <td class="small"><?php echo htmlspecialchars($loc); ?></td>
            <td class="small">
              <?php echo htmlspecialchars(($j['remote_option'] ?? 'Work From Home').' · '.($j['employment_type'] ?? 'Full time')); ?>
            </td>
            <td class="small"><?php echo htmlspecialchars($salary); ?></td>
            <td><span class="badge text-bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($status); ?></span></td>
            <td class="small text-nowrap"><?php echo htmlspecialchars(date('M d, Y', strtotime($j['created_at']))); ?></td>
            <td class="text-center text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="jobs_edit.php?job_id=<?php echo urlencode($j['job_id']); ?>" title="Edit"><i class="bi bi-pencil"></i></a>
              <a class="btn btn-sm btn-outline-secondary" href="employer_applicants.php?job_id=<?php echo urlencode($j['job_id']); ?>" title="Applicants"><i class="bi bi-people"></i></a>
              <a class="btn btn-sm btn-outline-danger" href="jobs_delete.php?job_id=<?php echo urlencode($j['job_id']); ?>"
                 onclick="return confirm('Delete this job? This cannot be undone.');" title="Delete">
                 <i class="bi bi-trash"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php include '../includes/footer.php'; ?>

<script>
(function(){
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
  const rows = document.querySelectorAll('#jobsTable tbody tr');
  function applyFilter(){
    const q = (filterInput.value||'').trim().toLowerCase();
    const st = statusFilter.value;
    rows.forEach(r=>{
      const title = r.getAttribute('data-title') || '';
      const status = r.getAttribute('data-status') || '';
      const matchText   = !q || title.indexOf(q) !== -1;
      const matchStatus = !st || status === st;
      r.style.display = (matchText && matchStatus) ? '' : 'none';
    });
  }
  if (filterInput) filterInput.addEventListener('input', applyFilter);
  if (statusFilter) statusFilter.addEventListener('change', applyFilter);
})();
</script>