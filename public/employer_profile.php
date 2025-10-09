<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';

Helpers::requireLogin();
// If a logged-in job seeker tries to force open an employer-only management page, redirect.
if (Helpers::isJobSeeker()) {
  // Viewing an employer profile is allowed for everyone typically, but the requirement
  // asks to treat employer pages as restricted when forced by job seekers.
  // We'll allow viewing public employer profiles; only restrict management pages elsewhere.
}

$viewerId   = $_SESSION['user_id'] ?? null;
$viewerRole = $_SESSION['role'] ?? '';
$userParam  = $_GET['user_id'] ?? '';

if ($userParam === '' && $viewerRole === 'employer') {
    $userParam = $viewerId;
}

$employer = User::findById($userParam);
$backUrl  = $_SERVER['HTTP_REFERER'] ?? 'index.php';

if (!$employer || $employer->role !== 'employer') {
    include '../includes/header.php';
    include '../includes/nav.php';
    echo '<div class="container py-5"><div class="alert alert-danger">Employer profile not found.</div></div>';
    include '../includes/footer.php';
    exit;
}

$isSelf = ($viewerRole === 'employer' && $viewerId === $employer->user_id);
$canSeePrivate = $isSelf || ($viewerRole === 'admin');

/* Load jobs */
$jobs = [];
try {
    $pdo = Database::getConnection();
  if ($canSeePrivate) {
    $stmt = $pdo->prepare("
      SELECT job_id, title, status, created_at, employment_type, salary_min, salary_max, salary_currency, moderation_status
      FROM jobs
      WHERE employer_id=?
      ORDER BY created_at DESC
      LIMIT 200
    ");
    $stmt->execute([$employer->user_id]);
  } else {
    $stmt = $pdo->prepare("
      SELECT job_id, title, status, created_at, employment_type, salary_min, salary_max, salary_currency
      FROM jobs
      WHERE employer_id=? AND moderation_status='Approved'
      ORDER BY created_at DESC
      LIMIT 200
    ");
    $stmt->execute([$employer->user_id]);
  }
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $jobs = [];
}

include '../includes/header.php';
include '../includes/nav.php';

$joinDate = null;
if (!empty($employer->created_at)) {
    $ts = strtotime($employer->created_at);
    if ($ts && $ts > 0) $joinDate = date('Y-m-d',$ts);
}
?>
<style>
/* Employer Profile Enhanced UI */
.emp-profile-hero {position:relative;padding:2.8rem 0 2.2rem;margin-bottom:1.2rem;}
.emp-profile-hero::before{content:"";position:absolute;inset:0;background:
  radial-gradient(circle at 12% 18%,rgba(255,193,7,.18),transparent 60%),
  radial-gradient(circle at 88% 8%,rgba(102,16,242,.18),transparent 65%),
  linear-gradient(135deg,#f4f9ff,#ffffff);}
.emp-profile-hero h1{font-weight:800;font-size:clamp(2rem,3.6vw,2.65rem);letter-spacing:-.7px;margin:0 0 .55rem; background:linear-gradient(135deg,var(--primary-blue),var(--primary-purple));-webkit-background-clip:text;background-clip:text;color:transparent;}
.emp-profile-hero .sub{max-width:640px;color:#4a5a67;font-size:.9rem;}
/* New compact metrics pills */
.emp-metrics{display:flex;flex-wrap:wrap;gap:.85rem;margin-top:.6rem;justify-content:center;}
.emp-metric{position:relative;background:linear-gradient(145deg,#ffffff,#f3f8ff);border:1px solid #d9e6f2;padding:.85rem 1.05rem .95rem;border-radius:1rem;box-shadow:0 10px 28px -12px rgba(13,110,253,.28),0 4px 14px rgba(0,0,0,.05);min-width:150px;flex:0 0 auto;overflow:hidden;display:flex;flex-direction:column;gap:.4rem;text-align:center;}
.emp-metric::before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 18% 25%,rgba(255,193,7,.25),transparent 55%),radial-gradient(circle at 82% 15%,rgba(102,16,242,.25),transparent 60%);mix-blend-mode:overlay;opacity:.55;pointer-events:none;}
.emp-metric .lbl{font-size:.6rem;font-weight:700;letter-spacing:.85px;text-transform:uppercase;color:#516372;display:flex;align-items:center;gap:.45rem;margin:0;}
.emp-metric .icon{width:28px;height:28px;border-radius:.7rem;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--primary-blue),var(--primary-purple));color:#fff;font-size:.95rem;box-shadow:0 4px 12px -4px rgba(13,110,253,.55);}
.emp-metric .val{font-size:1.55rem;font-weight:800;letter-spacing:-.6px;background:linear-gradient(135deg,var(--primary-blue),var(--primary-purple));-webkit-background-clip:text;background-clip:text;color:transparent;line-height:1;}
.emp-metric.joined .val{font-size:1.05rem;font-weight:700;letter-spacing:-.3px;}
@media (prefers-reduced-motion: no-preference){.emp-metric{transition:transform .35s cubic-bezier(.25,.46,.45,.94), box-shadow .35s ease;} .emp-metric:hover{transform:translateY(-6px);box-shadow:0 16px 34px -14px rgba(13,110,253,.45),0 6px 20px rgba(0,0,0,.08);} }
@media (max-width:575.98px){.emp-metric{min-width:calc(50% - .85rem);padding:.75rem .9rem .8rem;} .emp-metric .val{font-size:1.35rem;} }
/* Inline name pill variant */
.emp-metrics.name-inline{margin-top:0;}
.emp-metric.name{background:transparent;border:0;box-shadow:none;padding:0 .4rem 0;min-width:auto;gap:0;}
.emp-metric.name::before{display:none;}
.emp-metric.name .lbl{display:none;}
.emp-metric.name .val{font-size:clamp(2rem,3.6vw,2.65rem);letter-spacing:-.7px;font-weight:800;line-height:1;background:linear-gradient(135deg,var(--primary-blue),var(--primary-purple));-webkit-background-clip:text;background-clip:text;color:transparent;}
.emp-metric.name .icon{display:none;}
.emp-hero-grid{display:grid;grid-template-columns:270px 1fr;gap:2.2rem;align-items:start;}
@media (max-width:991.98px){.emp-hero-grid{grid-template-columns:1fr;gap:1.75rem;}}
.emp-actions{display:flex;flex-wrap:wrap;gap:.55rem;margin-top:.95rem;}
.emp-actions .btn{display:inline-flex;align-items:center;gap:.4rem;}
.emp-hero-left{display:flex;flex-direction:column;gap:1rem;}
.emp-hero-left-inner{background:#ffffff;border:1px solid #e3ebf4;border-radius:1rem;padding:1.1rem 1.1rem 1.2rem;box-shadow:0 8px 24px -10px rgba(13,110,253,.22),0 4px 12px rgba(0,0,0,.05);}
.emp-hero-left .ep-avatar{width:110px;height:110px;margin:0 auto;}
.emp-hero-left .emp-actions{justify-content:center;}
.ep-shell{position:relative;}
.ep-left-card, .ep-jobs-card{position:relative;background:#ffffff;border:1px solid rgba(13,110,253,.12);border-radius:1.15rem;padding:1.4rem 1.35rem 1.55rem;box-shadow:0 10px 30px -12px rgba(13,110,253,.25),0 4px 14px rgba(0,0,0,.06);overflow:hidden;}
.ep-left-card::before, .ep-jobs-card::before{content:"";position:absolute;inset:0;pointer-events:none;background:radial-gradient(circle at 14% 20%,rgba(255,193,7,.2),transparent 60%),radial-gradient(circle at 85% 15%,rgba(102,16,242,.18),transparent 60%);mix-blend-mode:overlay;opacity:.55;}
.ep-avatar{width:96px;height:96px;border-radius:50%;overflow:hidden;flex-shrink:0;box-shadow:0 6px 18px -6px rgba(13,110,253,.45);border:3px solid #fff;position:relative;background:#f5f7fb;display:flex;align-items:center;justify-content:center;font-size:2rem;color:#6c7a86;}
.ep-meta-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.55rem;margin-top:1.1rem;}
.ep-stat{background:#fff;border:1px solid #e3ebf4;border-radius:.75rem;padding:.55rem .65rem .6rem;position:relative;overflow:hidden;font-size:.6rem;font-weight:600;text-transform:uppercase;letter-spacing:.65px;color:#5b6b78;display:flex;flex-direction:column;gap:.2rem;}
.ep-stat .val{font-size:1.05rem;font-weight:700;letter-spacing:-.3px;background:linear-gradient(135deg,var(--primary-blue),var(--primary-purple));-webkit-background-clip:text;background-clip:text;color:transparent;line-height:1;}
.ep-details-dl{margin:1.15rem 0 0;}
.ep-details-dl dt{font-size:.58rem;font-weight:700;letter-spacing:.75px;text-transform:uppercase;color:#5d6c7a;margin-bottom:.15rem;}
.ep-details-dl dd{margin:0 0 .65rem;font-size:.8rem;font-weight:500;color:#223747;}
.ep-badge-status{display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .6rem;font-size:.6rem;font-weight:700;letter-spacing:.6px;text-transform:uppercase;border-radius:1rem;background:#eef2f6;color:#344655;}
.ep-badge-status.approved{background:#e4f9ed;color:#0b6a32;}
.ep-badge-status.suspended{background:#ffe8e3;color:#7d2d11;}
.ep-badge-status.rejected{background:#f1f3f5;color:#4d5963;}
.ep-badge-status.pending{background:#fff6e0;color:#7a4b00;}
.ep-jobs-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1rem;}
.ep-jobs-head h2{font-size:1rem;font-weight:600;letter-spacing:.4px;display:flex;align-items:center;gap:.5rem;margin:0;}
.ep-jobs-table thead th{font-size:.6rem;letter-spacing:.65px;text-transform:uppercase;font-weight:700;color:#5d6c7a;}
.ep-jobs-table td a{text-decoration:none;font-weight:600;color:#1d3553;}
.ep-jobs-table td a:hover{color:#0d6efd;}
.ep-job-badge{display:inline-flex;align-items:center;gap:.3rem;font-size:.58rem;font-weight:700;letter-spacing:.6px;text-transform:uppercase;padding:.35rem .55rem;border-radius:1rem;}
.ep-job-badge.open{background:#e7f8ed;color:#0b6a32;}
.ep-job-badge.closed{background:#f1f3f5;color:#4d5963;}
.ep-job-badge.suspended{background:#fff2d9;color:#7b4d00;}
.ep-empty{background:#f5f8fb;border:1px dashed #d5e2ed;padding:1.1rem;border-radius:.9rem;font-size:.75rem;color:#4d6173;}
@media (max-width:575.98px){.ep-left-card,.ep-jobs-card{padding:1.05rem 1rem 1.3rem;} .ep-meta-grid{grid-template-columns:repeat(auto-fit,minmax(120px,1fr));}}
</style>

<div class="emp-profile-hero">
  <div class="container">
    <div class="emp-hero-grid">
      <aside class="emp-hero-left fade-up">
        <div class="emp-hero-left-inner text-center">
          <div class="ep-avatar mb-2">
            <?php if (!empty($employer->profile_picture)): ?>
              <img src="../<?php echo htmlspecialchars($employer->profile_picture); ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;"/>
            <?php else: ?>
              <i class="bi bi-person"></i>
            <?php endif; ?>
          </div>
          <div class="emp-actions">
            <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <a href="company.php?user_id=<?php echo urlencode($employer->user_id); ?>" class="btn btn-outline-secondary btn-sm" title="View public page"><i class="bi bi-eye"></i></a>
            <?php if ($isSelf): ?>
              <a href="employer_edit.php" class="btn btn-outline-primary btn-sm" title="Edit"><i class="bi bi-pencil-square"></i></a>
              <a href="employer_dashboard.php" class="btn btn-gradient btn-sm" title="Dashboard"><i class="bi bi-speedometer2"></i></a>
              <a href="jobs_create.php" class="btn btn-accent btn-sm" title="Post Job"><i class="bi bi-plus-lg"></i></a>
            <?php endif; ?>
          </div>
        </div>
      </aside>
      <div class="emp-hero-right min-w-0">
        <div class="emp-metrics name-inline fade-up fade-delay-2" aria-label="Quick statistics and employer name">
          <div class="emp-metric name"><span class="val emp-name"><?php echo htmlspecialchars($employer->company_name ?: $employer->name ?: 'Employer'); ?></span></div>
          <div class="emp-metric"><span class="lbl"><span class="icon"><i class="bi bi-briefcase"></i></span>Jobs Posted</span><span class="val"><?php echo count($jobs); ?></span></div>
          <?php $openCt = count(array_filter($jobs, fn($j)=>$j['status']==='Open')); $closedCt = count(array_filter($jobs, fn($j)=>$j['status']==='Closed')); ?>
          <div class="emp-metric"><span class="lbl"><span class="icon"><i class="bi bi-play-fill"></i></span>Open</span><span class="val"><?php echo $openCt; ?></span></div>
          <div class="emp-metric"><span class="lbl"><span class="icon"><i class="bi bi-stop-fill"></i></span>Closed</span><span class="val"><?php echo $closedCt; ?></span></div>
          <?php if ($joinDate): ?><div class="emp-metric joined"><span class="lbl"><span class="icon"><i class="bi bi-calendar-event"></i></span>Joined</span><span class="val"><?php echo htmlspecialchars(date('M y', strtotime($joinDate))); ?></span></div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="container ep-shell pb-4">
  <div class="row g-4">
    <div class="col-lg-5">
      <div class="ep-left-card h-100">
        <h2 class="visually-hidden">Company Details</h2>
        <dl class="ep-details-dl">
          <dt>Company Name</dt>
          <dd><?php echo htmlspecialchars($employer->company_name ?: $employer->name); ?></dd>
          <?php if ($employer->company_website): ?>
            <dt>Website</dt>
            <dd><a href="<?php echo htmlspecialchars($employer->company_website); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($employer->company_website); ?></a></dd>
          <?php endif; ?>
          <?php if ($employer->company_phone): ?>
            <dt>Phone</dt>
            <dd><?php echo htmlspecialchars($employer->company_phone); ?></dd>
          <?php endif; ?>
          <?php if ($employer->company_owner_name): ?>
            <dt>Owner / Proprietor</dt>
            <dd><?php echo htmlspecialchars($employer->company_owner_name); ?></dd>
          <?php endif; ?>
          <?php if ($employer->contact_person_position): ?>
            <dt>Contact Position</dt>
            <dd><?php echo htmlspecialchars($employer->contact_person_position); ?></dd>
          <?php endif; ?>
          <?php if ($employer->contact_person_phone && $canSeePrivate): ?>
            <dt>Contact Phone</dt>
            <dd><?php echo htmlspecialchars($employer->contact_person_phone); ?></dd>
          <?php endif; ?>
          <?php if ($canSeePrivate && $employer->business_email): ?>
            <dt>Business Email</dt>
            <dd><?php echo htmlspecialchars($employer->business_email); ?></dd>
          <?php endif; ?>
          <?php if ($canSeePrivate && $employer->business_permit_number): ?>
            <dt>Permit #</dt>
            <dd><?php echo htmlspecialchars($employer->business_permit_number); ?></dd>
          <?php endif; ?>
          <?php if ($canSeePrivate && $employer->employer_status): ?>
            <dt>Status</dt>
            <dd>
              <?php $st=$employer->employer_status; $cls=strtolower($st); ?>
              <span class="ep-badge-status <?php echo htmlspecialchars($cls); ?>">
                <i class="bi bi-<?php echo $st==='Approved'?'check-circle':($st==='Suspended'?'exclamation-triangle':($st==='Rejected'?'x-circle':'hourglass-split')); ?>"></i>
                <?php echo htmlspecialchars($st); ?>
              </span>
            </dd>
          <?php endif; ?>
          <?php if ($joinDate): ?>
            <dt>Joined</dt>
            <dd><?php echo htmlspecialchars($joinDate); ?></dd>
          <?php endif; ?>
        </dl>
      </div>
    </div>
    <div class="col-lg-7">
      <div class="ep-jobs-card">
        <div class="ep-jobs-head">
          <h2><i class="bi bi-briefcase"></i><span>Jobs Posted</span><span class="badge bg-light text-dark ms-1" style="font-size:.65rem;"><?php echo count($jobs); ?></span></h2>
          <?php if ($isSelf): ?><a href="jobs_create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i><span>Post Job</span></a><?php endif; ?>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-2" role="toolbar" aria-label="Filter jobs by status">
          <button type="button" class="btn btn-sm btn-outline-secondary active" data-filter="all">All</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-filter="open">Open</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-filter="closed">Closed</button>
        </div>
        <?php if (!$jobs): ?>
          <div class="ep-empty"><i class="bi bi-info-circle me-1"></i>No jobs posted yet.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle ep-jobs-table mb-0">
              <thead class="table-light">
                <tr>
                  <th scope="col">Title</th>
                  <th scope="col">Status</th>
                  <th scope="col">Type</th>
                  <th scope="col">Salary</th>
                  <th scope="col">Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($jobs as $j): ?>
                  <?php $rowStatus = strtolower($j['status']); ?>
                  <tr data-status="<?php echo htmlspecialchars($rowStatus); ?>">
                    <td><a href="job_view.php?job_id=<?php echo urlencode($j['job_id']); ?>"><?php echo htmlspecialchars($j['title']); ?></a></td>
                    <td>
                      <?php $js=$j['status']; $jbCls=strtolower($js); ?>
                      <span class="ep-job-badge <?php echo htmlspecialchars($jbCls); ?>">
                        <i class="bi bi-<?php echo $js==='Open'?'play-fill':($js==='Closed'?'stop-fill':'pause-fill'); ?>"></i><?php echo htmlspecialchars($js); ?>
                      </span>
                    </td>
                    <td><?php echo htmlspecialchars($j['employment_type']); ?></td>
                    <td>
                      <?php
                        $cur = $j['salary_currency'] ?: 'PHP';
                        $min = $j['salary_min'] ?: null;
                        $max = $j['salary_max'] ?: null;
                        if ($min && $max) {
                          echo htmlspecialchars($cur.' '.number_format($min).' - '.number_format($max));
                        } elseif ($min || $max) {
                          $one = $min ?: $max;
                          echo htmlspecialchars($cur.' '.number_format($one));
                        } else {
                          echo '—';
                        }
                      ?>
                    </td>
                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($j['created_at']))); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
  (function(){
    const toolbar = document.querySelector('[aria-label="Filter jobs by status"]');
    if (!toolbar) return;
    const buttons = Array.from(toolbar.querySelectorAll('button[data-filter]'));
    const rows = Array.from(document.querySelectorAll('.ep-jobs-table tbody tr'));
    function applyFilter(f){
      rows.forEach(tr=>{
        const s = (tr.getAttribute('data-status')||'').toLowerCase();
        tr.style.display = (f==='all' || s===f) ? '' : 'none';
      });
    }
    buttons.forEach(btn=>{
      btn.addEventListener('click', ()=>{
        buttons.forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        applyFilter(btn.getAttribute('data-filter'));
      });
    });
  })();
</script>