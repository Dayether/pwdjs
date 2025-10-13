<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';

Helpers::requireRole('admin');

$pdo = Database::getConnection();
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalEmployers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='employer'")->fetchColumn();
$totalSeekers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='job_seeker'")->fetchColumn();
$totalAdmins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$totalJobs = (int)$pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
$pendingJobs = 0; $approvedJobs = 0; $rejectedJobs = 0;
try {
  $approvedJobs = (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE moderation_status='Approved'")->fetchColumn();
  $pendingJobs  = (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE moderation_status='Pending'")->fetchColumn();
  $rejectedJobs = (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE moderation_status='Rejected'")->fetchColumn();
} catch (Throwable $e) {}
$pendingEmployers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='employer' AND COALESCE(employer_status,'Pending')='Pending'")->fetchColumn();
$pendingPwd = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='job_seeker' AND COALESCE(pwd_id_status,'None')='Pending'")->fetchColumn();
$todayJobs = 0;
try {
  $stmt = $pdo->query("SELECT COUNT(*) FROM jobs WHERE DATE(created_at)=CURDATE()");
  $todayJobs = (int)$stmt->fetchColumn();
} catch (Throwable $e) { $todayJobs = 0; }

// Job seeker disability breakdown (from disability_type if set, else disability)
$disCategories = [
  'Learning disability',
  'Vision impairment',
  'Communication disorder',
  'Intellectual disability',
  'Orthopedic disability',
  'Chronic illness',
  'Hearing loss',
  'Speech impairment',
  'Hearing disability',
  'Physical disability'
];
$disMap = array_fill_keys(array_map('strtolower',$disCategories), 0);
$disOther = 0; $disUnspec = 0;
try {
  $q = $pdo->query("SELECT TRIM(LOWER(COALESCE(disability_type, disability))) AS label, COUNT(*) c FROM users WHERE role='job_seeker' GROUP BY label");
  $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach ($rows as $r) {
    $label = $r['label'] ?? '';
    $cnt = (int)($r['c'] ?? 0);
    if ($label === '' || $label === null) { $disUnspec += $cnt; continue; }
    if (array_key_exists($label, $disMap)) { $disMap[$label] += $cnt; }
    else { $disOther += $cnt; }
  }
} catch (Throwable $e) { /* ignore breakdown errors */ }

// Build Top-N list (default N=5; use 'all' to show all)
$topNParam = isset($_GET['topN']) ? strtolower(trim($_GET['topN'])) : '5';
$topN = 5;
if ($topNParam === 'all') { $topN = PHP_INT_MAX; }
elseif (ctype_digit($topNParam)) { $topN = max(1, min(20, (int)$topNParam)); }

$pairs = [];
foreach ($disMap as $k=>$v) { $pairs[] = ['key'=>$k,'label'=>ucwords($k), 'count'=>$v]; }
usort($pairs, function($a,$b){ return $b['count'] <=> $a['count']; });

$topList = array_slice($pairs, 0, $topN);
$shownTotal = array_sum(array_column($topList,'count'));
$remaining = max(0, ($totalSeekers - $shownTotal));
// Add Other and Unspecified into remaining appropriately
$otherBundle = max(0, $disOther + $disUnspec + ($totalSeekers - ($disOther + $disUnspec + array_sum(array_column($pairs,'count'))))); // safety


include 'includes/header.php';
?>
<div class="admin-layout" style="margin-top:0;">
  <?php include 'includes/admin_sidebar.php'; ?>
  <div class="admin-main">
<style>
  /* Dashboard enhanced styling */
  .dash-header{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1.4rem;flex-wrap:wrap;border-bottom:1px solid rgba(255,255,255,.07);padding-bottom:.55rem;}
  @media (prefers-color-scheme: light){.dash-header{border-color:rgba(0,0,0,.08);} }
  .dash-header h2{font-size:1.35rem;font-weight:700;letter-spacing:.6px;display:flex;align-items:center;gap:.6rem;margin:0;line-height:1.1;position:relative;background:linear-gradient(90deg,#fff,#93c5fd);-webkit-background-clip:text;background-clip:text;color:transparent;}
  .dash-header h2 i{background:linear-gradient(135deg,#60a5fa,#818cf8);-webkit-background-clip:text;background-clip:text;color:transparent;filter:drop-shadow(0 2px 4px rgba(0,0,0,.35));}
  @media (prefers-color-scheme: light){.dash-header h2{background:linear-gradient(90deg,#1e293b,#2563eb);color:transparent;} .dash-header h2 i{background:linear-gradient(135deg,#2563eb,#4f46e5);} }
  .metrics-grid{--cols:4;display:grid;gap:1rem;margin-bottom:1.5rem;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));}
  .metric-card{position:relative;overflow:hidden;border:1px solid rgba(255,255,255,.06);background:linear-gradient(140deg,#151f34,#0e1421);border-radius:14px;padding:1rem 1rem 1rem 1rem;display:flex;flex-direction:column;justify-content:space-between;min-height:120px}
  .metric-card:before{content:"";position:absolute;inset:0;pointer-events:none;background:radial-gradient(circle at 85% 15%,rgba(255,255,255,.09),transparent 60%);}  
  .metric-icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;font-weight:600;margin-bottom:.35rem;background:linear-gradient(135deg,#2d3d62,#182235);box-shadow:0 4px 10px -4px rgba(0,0,0,.4);}
  .metric-label{font-size:.7rem;letter-spacing:.08em;font-weight:600;text-transform:uppercase;color:#b6c2d8;margin-bottom:.15rem;}
  .metric-value{font-size:1.9rem;font-weight:700;line-height:1;color:#fff;}
  .metric-change{font-size:.65rem;font-weight:500;margin-top:.4rem;display:inline-flex;align-items:center;gap:.25rem;border-radius:20px;padding:.25rem .55rem;background:rgba(255,255,255,.08);color:#c3cfe4;}
  .metric-card.employers .metric-icon{background:linear-gradient(135deg,#1f7748,#0d3d26);} 
  .metric-card.seekers .metric-icon{background:linear-gradient(135deg,#0f4c81,#09263f);} 
  .metric-card.jobs .metric-icon{background:linear-gradient(135deg,#8041d9,#3f196d);} 
  .metric-card.pending .metric-icon{background:linear-gradient(135deg,#aa6b08,#4a2f01);} 
  .metric-card.pwd .metric-icon{background:linear-gradient(135deg,#a32020,#4a0c0c);} 
  .quick-actions{display:grid;gap:.75rem;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));}
  .quick-actions a{position:relative;text-decoration:none;font-size:.8rem;font-weight:600;letter-spacing:.5px;padding:.9rem 1rem;border:1px solid rgba(255,255,255,.07);background:linear-gradient(135deg,#18263d,#132033);color:#d7e4f7;border-radius:10px;display:flex;align-items:center;justify-content:space-between;transition:.25s;} 
  .quick-actions a:hover{border-color:#3a63ff;background:linear-gradient(135deg,#1d3050,#15253d);color:#fff;box-shadow:0 6px 14px -8px rgba(0,0,0,.6);} 
  .section-card{border:1px solid rgba(255,255,255,.08);background:#101a2b;border-radius:16px;padding:1.15rem 1.25rem;margin-bottom:1.4rem;box-shadow:0 4px 18px -10px rgba(0,0,0,.55);}  
  .section-title{font-size:.75rem;font-weight:700;letter-spacing:.14em;color:#7e8ca3;text-transform:uppercase;margin:0 0 1rem;}
  .progress-slim{height:6px;border-radius:4px;overflow:hidden;background:#1e293b;margin-top:.45rem;}
  .progress-slim span{display:block;height:100%;background:linear-gradient(90deg,#3b82f6,#60a5fa);} 
  .dist-row{display:flex;align-items:center;justify-content:space-between;font-size:.7rem;font-weight:600;color:#a6b4c9;margin-top:.65rem;}
  .dist-row:first-of-type{margin-top:0;}
  .trend-positive{color:#4ade80;}
  .trend-warning{color:#fbbf24;}
  .trend-danger{color:#f87171;}
  @media (max-width: 680px){.metric-value{font-size:1.55rem}}
  .fade-in-up{animation:fadeInUp .6s cubic-bezier(.4,.14,.3,1) both;}
  @keyframes fadeInUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
  .count-animate{opacity:0;transition:opacity .3s .15s;}
  .count-animate.visible{opacity:1;}
</style>

<?php
// Derived percentages (avoid division by zero)
$percentEmployers = $totalUsers ? round(($totalEmployers / $totalUsers) * 100, 1) : 0;
$percentSeekers   = $totalUsers ? round(($totalSeekers / $totalUsers) * 100, 1) : 0;
$percentAdmins    = $totalUsers ? round(($totalAdmins / $totalUsers) * 100, 1) : 0;
$pendingEmployerPct = $totalEmployers ? round(($pendingEmployers / $totalEmployers) * 100, 1) : 0;
$pendingPwdPct      = $totalSeekers ? round(($pendingPwd / $totalSeekers) * 100, 1) : 0;
?>

<div class="dash-header fade-in-up">
  <h2><i class="bi bi-speedometer2"></i> Admin Dashboard</h2>
  
</div>

<div class="metrics-grid">
  <div class="metric-card fade-in-up" style="--i:1" data-order="1">
    <div class="metric-icon"><i class="bi bi-people"></i></div>
    <div class="metric-label">Total Users</div>
    <div class="metric-value count-animate" data-count="<?php echo $totalUsers; ?>">0</div>
    <div class="metric-change"><i class="bi bi-activity"></i>Platform size</div>
  </div>
  <div class="metric-card seekers fade-in-up" style="--i:2">
    <div class="metric-icon"><i class="bi bi-person-badge"></i></div>
    <div class="metric-label">Job Seekers</div>
    <div class="metric-value count-animate text-primary" data-count="<?php echo $totalSeekers; ?>">0</div>
    <div class="metric-change trend-positive"><i class="bi bi-arrow-up"></i><?php echo $percentSeekers; ?>% of users</div>
  </div>
  <div class="metric-card employers fade-in-up" style="--i:3">
    <div class="metric-icon"><i class="bi bi-building"></i></div>
    <div class="metric-label">Employers</div>
    <div class="metric-value count-animate text-success" data-count="<?php echo $totalEmployers; ?>">0</div>
    <div class="metric-change"><i class="bi bi-pie-chart"></i><?php echo $percentEmployers; ?>% share</div>
  </div>
  <div class="metric-card fade-in-up" style="--i:4">
    <div class="metric-icon"><i class="bi bi-shield-lock"></i></div>
    <div class="metric-label">Admins</div>
    <div class="metric-value count-animate text-secondary" data-count="<?php echo $totalAdmins; ?>">0</div>
    <div class="metric-change"><i class="bi bi-patch-check"></i><?php echo $percentAdmins; ?>% oversight</div>
  </div>
  <div class="metric-card jobs fade-in-up" style="--i:5">
    <div class="metric-icon"><i class="bi bi-briefcase"></i></div>
    <div class="metric-label">Total Jobs</div>
    <div class="metric-value count-animate" data-count="<?php echo $totalJobs; ?>">0</div>
    <div class="metric-change"><i class="bi bi-lightning"></i>All time</div>
  </div>
  <div class="metric-card fade-in-up" style="--i:6">
    <div class="metric-icon"><i class="bi bi-calendar-event"></i></div>
    <div class="metric-label">Jobs Today</div>
    <div class="metric-value count-animate text-info" data-count="<?php echo $todayJobs; ?>">0</div>
    <div class="metric-change"><i class="bi bi-clock-history"></i>Today</div>
  </div>
  <div class="metric-card pending fade-in-up" style="--i:7">
    <div class="metric-icon"><i class="bi bi-hourglass-split"></i></div>
    <div class="metric-label">Pending Employers</div>
    <div class="metric-value count-animate text-warning" data-count="<?php echo $pendingEmployers; ?>">0</div>
    <div class="metric-change trend-warning"><i class="bi bi-stopwatch"></i><?php echo $pendingEmployerPct; ?>% of employers</div>
  </div>
  <div class="metric-card pwd fade-in-up" style="--i:8">
    <div class="metric-icon"><i class="bi bi-person-check"></i></div>
    <div class="metric-label">Pending PWD IDs</div>
    <div class="metric-value count-animate text-danger" data-count="<?php echo $pendingPwd; ?>">0</div>
    <div class="metric-change trend-danger"><i class="bi bi-exclamation-triangle"></i><?php echo $pendingPwdPct; ?>% of seekers</div>
  </div>
</div>

<div class="section-card fade-in-up">
  <div class="section-title">Quick Actions</div>
  <div class="quick-actions">
    <a href="admin_employers.php"><span><i class="bi bi-building me-1"></i>Employers</span><i class="bi bi-arrow-right-short fs-5"></i></a>
    <a href="admin_job_seekers.php"><span><i class="bi bi-person-badge me-1"></i>Job Seekers</span><i class="bi bi-arrow-right-short fs-5"></i></a>
    <a href="employer_jobs.php"><span><i class="bi bi-briefcase me-1"></i>Jobs</span><i class="bi bi-arrow-right-short fs-5"></i></a>
    <a href="admin_reports.php"><span><i class="bi bi-flag me-1"></i>Reports</span><i class="bi bi-arrow-right-short fs-5"></i></a>
    <a href="admin_support_tickets.php"><span><i class="bi bi-life-preserver me-1"></i>Support</span><i class="bi bi-arrow-right-short fs-5"></i></a>
    
  </div>
</div>

<div class="section-card fade-in-up">
  <div class="section-title">User Distribution</div>
  <div class="dist-row"><span>Employers</span><span><?php echo $percentEmployers; ?>%</span></div>
  <div class="progress-slim"><span style="width:<?php echo $percentEmployers; ?>%"></span></div>
  <div class="dist-row"><span>Job Seekers</span><span><?php echo $percentSeekers; ?>%</span></div>
  <div class="progress-slim"><span style="width:<?php echo $percentSeekers; ?>%;background:linear-gradient(90deg,#0ea5e9,#38bdf8)"></span></div>
  <div class="dist-row"><span>Admins</span><span><?php echo $percentAdmins; ?>%</span></div>
  <div class="progress-slim"><span style="width:<?php echo $percentAdmins; ?>%;background:linear-gradient(90deg,#6366f1,#818cf8)"></span></div>
</div>

<div class="section-card fade-in-up">
  <div class="section-title">Verification Backlog</div>
  <div class="dist-row"><span>Pending Employers</span><span><?php echo $pendingEmployerPct; ?>%</span></div>
  <div class="progress-slim"><span style="width:<?php echo $pendingEmployerPct; ?>%;background:linear-gradient(90deg,#f59e0b,#fbbf24)"></span></div>
  <div class="dist-row"><span>Pending PWD IDs</span><span><?php echo $pendingPwdPct; ?>%</span></div>
  <div class="progress-slim"><span style="width:<?php echo $pendingPwdPct; ?>%;background:linear-gradient(90deg,#ef4444,#f87171)"></span></div>
  <div class="mt-3 small text-secondary">Focus on clearing high percentage areas to keep platform onboarding smooth.</div>
</div>

<div class="section-card fade-in-up">
  <div class="section-title">Job Seekers by Disability</div>
  <div class="d-flex gap-2 mb-2 small">
    <a class="btn btn-sm btn-outline-light <?php echo ($topN===5?'active':''); ?>" href="?topN=5">Top 5</a>
    <a class="btn btn-sm btn-outline-light <?php echo ($topN===10?'active':''); ?>" href="?topN=10">Top 10</a>
    <a class="btn btn-sm btn-outline-light <?php echo ($topN===PHP_INT_MAX?'active':''); ?>" href="?topN=all">All</a>
  </div>
  <?php if ($totalSeekers > 0): ?>
    <?php foreach ($topList as $row): if ($row['count']<=0) continue; $pct = round(($row['count']/$totalSeekers)*100,1); ?>
      <div class="dist-row"><span><?php echo htmlspecialchars($row['label']); ?></span><span><?php echo number_format($row['count']); ?> (<?php echo $pct; ?>%)</span></div>
      <div class="progress-slim"><span style="width:<?php echo $pct; ?>%;background:linear-gradient(90deg,#3b82f6,#60a5fa)"></span></div>
    <?php endforeach; ?>
    <?php
      $othersCount = $disOther + $disUnspec + max(0, $remaining - $disOther - $disUnspec);
      if ($othersCount > 0):
        $pct = round(($othersCount/$totalSeekers)*100,1);
    ?>
      <div class="dist-row"><span>Others/Unspecified</span><span><?php echo number_format($othersCount); ?> (<?php echo $pct; ?>%)</span></div>
      <div class="progress-slim"><span style="width:<?php echo $pct; ?>%;background:linear-gradient(90deg,#6366f1,#818cf8)"></span></div>
    <?php endif; ?>
  <?php else: ?>
    <div class="small text-secondary">No job seeker disability data yet.</div>
  <?php endif; ?>

  <canvas id="disPie" height="160" style="margin-top:12px"></canvas>
</div>

<script>
// Animated counter when in view
(()=>{
  const items=[...document.querySelectorAll('.count-animate')];
  if(!items.length) return;
  const ease=(t)=>1-Math.pow(1-t,3);
  const obs=new IntersectionObserver((ents)=>{
    ents.forEach(ent=>{
      if(ent.isIntersecting){
        animate(ent.target);
        obs.unobserve(ent.target);
      }
    });
  },{threshold:.4});
  items.forEach(i=>obs.observe(i));
  function animate(el){
    const end=parseInt(el.dataset.count||'0',10);
    const dur=900;
    const startT=performance.now();
    function frame(now){
      const p=Math.min(1,(now-startT)/dur);
      const val=Math.floor(ease(p)*end);
      el.textContent=val.toLocaleString();
      if(p<1) requestAnimationFrame(frame);
    }
    el.classList.add('visible');
    requestAnimationFrame(frame);
  }
})();

// Pie chart for disability distribution (Top-N + Others)
(()=>{
  const ctx = document.getElementById('disPie');
  if (!ctx) return;
  // Prepare data from PHP
  const labels = [
    <?php foreach ($topList as $r): if ($r['count']>0): ?>'<?php echo addslashes($r['label']); ?>',<?php endif; endforeach; ?>
    <?php $othersCount = $disOther + $disUnspec + max(0, $remaining - $disOther - $disUnspec); if ($othersCount>0): ?>'Others/Unspecified'<?php endif; ?>
  ].filter(Boolean);
  const data = [
    <?php foreach ($topList as $r): if ($r['count']>0): echo (int)$r['count'].','; endif; endforeach; ?>
    <?php echo ($othersCount>0) ? (int)$othersCount : ''; ?>
  ].filter(v=>v!=='' && v!==undefined);
  if (!labels.length || !data.length) return;
  const colors = ['#60a5fa','#34d399','#f472b6','#f59e0b','#a78bfa','#22d3ee','#f87171','#10b981','#c084fc','#fb7185','#94a3b8'];
  const bg = labels.map((_,i)=>colors[i % colors.length]);
  // Load Chart.js via CDN if not present
  function make(){
    // eslint-disable-next-line no-undef
    new Chart(ctx, {
      type: 'pie',
      data: { labels, datasets: [{ data, backgroundColor: bg }] },
      options: { plugins: { legend: { position: 'bottom' } } }
    });
  }
  if (window.Chart) { make(); }
  else {
    const s=document.createElement('script');
    s.src='https://cdn.jsdelivr.net/npm/chart.js';
    s.onload=make;
    document.head.appendChild(s);
  }
})();
</script>

<?php include 'includes/footer.php'; ?>
