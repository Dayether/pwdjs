<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/Taxonomy.php';

include '../includes/header.php';
include '../includes/nav.php';

$pdo = Database::getConnection();

// Inputs
$q       = trim($_GET['q'] ?? '');
$edu     = trim($_GET['edu'] ?? '');
$maxExp  = ($_GET['max_exp'] ?? '') !== '' ? max(0, (int)$_GET['max_exp']) : '';
$tag     = trim($_GET['tag'] ?? '');
$region  = trim($_GET['region'] ?? '');
$city    = trim($_GET['city'] ?? '');
$minPay  = ($_GET['min_pay'] ?? '') !== '' ? max(0, (int)$_GET['min_pay']) : '';
$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

$eduLevels  = Taxonomy::educationLevels();
$accessTags = Taxonomy::accessibilityTags();

// Only Approved employers AND WFH jobs
$where = [
  "u.role = 'employer'",
  "u.employer_status = 'Approved'",
  "j.remote_option = 'Work From Home'"
];
$params = [];

if ($q !== '') {
  $where[] = "(j.title LIKE :q OR j.description LIKE :q OR u.company_name LIKE :q)";
  $params[':q'] = '%' . $q . '%';
}
if ($edu !== '') {
  $where[] = "(j.required_education = :edu OR j.required_education = '' OR j.required_education IS NULL)";
  $params[':edu'] = $edu;
}
if ($maxExp !== '') {
  $where[] = "j.required_experience <= :maxExp";
  $params[':maxExp'] = $maxExp;
}
if ($tag !== '') {
  $where[] = "FIND_IN_SET(:tag, j.accessibility_tags)";
  $params[':tag'] = $tag;
}
if ($region !== '') {
  $where[] = "j.location_region LIKE :region";
  $params[':region'] = '%' . $region . '%';
}
if ($city !== '') {
  $where[] = "j.location_city LIKE :city";
  $params[':city'] = '%' . $city . '%';
}
if ($minPay !== '') {
  $where[] = "((j.salary_max IS NOT NULL AND j.salary_max >= :minPay) OR (j.salary_min IS NOT NULL AND j.salary_min >= :minPay))";
  $params[':minPay'] = $minPay;
}

$whereSql = implode(' AND ', $where);

// Count
$sqlCount = "SELECT COUNT(*) FROM jobs j JOIN users u ON u.user_id = j.employer_id WHERE $whereSql";
$stmt = $pdo->prepare($sqlCount);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$total = (int)$stmt->fetchColumn();

// Page
$sqlList = "
  SELECT j.job_id, j.title, j.created_at, j.required_experience, j.required_education,
    j.location_city, j.location_region, j.employment_type,
    j.salary_currency, j.salary_min, j.salary_max, j.salary_period,
    j.accessibility_tags, j.job_image,
    u.company_name, u.user_id AS employer_id
  FROM jobs j
  JOIN users u ON u.user_id = j.employer_id
  WHERE $whereSql
  ORDER BY j.created_at DESC
  LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sqlList);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$jobs = $stmt->fetchAll();

// =============================
// Landing Hero Stats (aggregates)
// =============================
try {
  // Total approved employers
  $stmtEmp = $pdo->query("SELECT COUNT(*) FROM users WHERE role='employer' AND employer_status='Approved'");
  $totalEmployers = (int)$stmtEmp->fetchColumn();

  // Total active jobs (all employment types) by approved employers
  $stmtJobsAll = $pdo->query("SELECT COUNT(*) FROM jobs j JOIN users u ON u.user_id = j.employer_id WHERE u.role='employer' AND u.employer_status='Approved'");
  $totalJobsAll = (int)$stmtJobsAll->fetchColumn();

  // Total WFH jobs (even if not filtered)
  $stmtJobsWFH = $pdo->query("SELECT COUNT(*) FROM jobs j JOIN users u ON u.user_id = j.employer_id WHERE u.role='employer' AND u.employer_status='Approved' AND j.remote_option='Work From Home'");
  $totalWFHJobs = (int)$stmtJobsWFH->fetchColumn();

  // Distinct regions represented by WFH jobs
  $stmtRegions = $pdo->query("SELECT COUNT(DISTINCT CONCAT(IFNULL(j.location_region,''), '|', IFNULL(j.location_city,''))) FROM jobs j JOIN users u ON u.user_id = j.employer_id WHERE u.role='employer' AND u.employer_status='Approved' AND j.remote_option='Work From Home'");
  $totalLocations = (int)$stmtRegions->fetchColumn();
} catch (Exception $e) {
  $totalEmployers = $totalJobsAll = $totalWFHJobs = $totalLocations = 0; // fail gracefully
}

// Pagination links
$qs = $_GET; unset($qs['p']);
$baseQS = http_build_query($qs);
$prevLink = 'index.php' . ($baseQS ? ('?' . $baseQS . '&') : '?') . 'p=' . max(1, $page - 1);
$nextLink = 'index.php' . ($baseQS ? ('?' . $baseQS . '&') : '?') . 'p=' . ($page + 1);
$pages = max(1, (int)ceil($total / $perPage));

function fmt_salary($cur, $min, $max, $period) {
  if ($min === null && $max === null) return 'Salary not specified';
  $fmt = function($n){ return number_format((int)$n); };
  $range = ($min !== null && $max !== null && $min != $max)
    ? "{$fmt($min)}–{$fmt($max)}"
    : $fmt($min ?? $max);
  return "{$cur} {$range} / " . ucfirst($period);
}
?>
<!-- Landing Hero Section -->
<section class="landing-hero hero-white-text parallax" style="background: linear-gradient(135deg, rgba(13,110,253,.65), rgba(102,16,242,.72)), url('assets/images/hero/bg5.jpg') center top / contain no-repeat, linear-gradient(135deg, #f0f7ff 0%, #ffffff 65%); background-color:#0d6efd;">
  <div class="container hero-content">
    <div class="row align-items-center g-4">
      <div class="col-12">
  <h1 class="hero-title mt-3 mb-3 fade-up fade-delay-2" style="color:#fff !important; -webkit-text-fill-color:#fff !important;">Find Accessible <span class="text-gradient-brand" style="color:#fff !important; -webkit-text-fill-color:#fff !important; background:none !important;">Work From Home</span> Jobs</h1>
        <p class="hero-lead mb-4 fade-up fade-delay-3">Browse curated Work From Home roles from verified, accessibility‑conscious employers. Search, filter, and apply to jobs that value your skills and support your success.</p>
        <!-- CTAs removed per request: only text retained in hero -->
        
      </div>
    </div>
  </div>
</section>

<div class="job-filters-card mb-4">
  <div class="job-filters-inner p-3 p-md-4">
    <a id="job-filters"></a>
    <div class="filters-heading">
      <div class="fh-icon" aria-hidden="true"><i class="bi bi-funnel"></i></div>
      <h2 id="filters-title" class="mb-0">Refine Your Search</h2>
    </div>
    <p class="filters-sub" id="filters-desc">Use filters to narrow down Work From Home roles from approved employers.</p>
    <form class="row g-2 align-items-end" method="get" role="search" aria-labelledby="filters-title" aria-describedby="filters-desc filters-results-line">
      <div class="col-lg-4">
        <label class="form-label filter-bold-label" for="filter-keyword">Keyword</label>
        <div class="input-icon-group">
          <span class="i-icon" aria-hidden="true"><i class="bi bi-search"></i></span>
          <input id="filter-keyword" type="text" name="q" class="form-control filter-bold" placeholder="Title or company" value="<?php echo htmlspecialchars($q); ?>">
        </div>
      </div>
      <div class="col-md-3 col-lg-2">
        <label class="form-label filter-bold-label" for="filter-edu">Education</label>
        <div class="input-icon-group">
          <span class="i-icon" aria-hidden="true"><i class="bi bi-mortarboard"></i></span>
          <select id="filter-edu" name="edu" class="form-select filter-bold">
            <option value="">Any</option>
            <?php foreach ($eduLevels as $lvl): ?>
              <option value="<?php echo htmlspecialchars($lvl); ?>" <?php if ($edu === $lvl) echo 'selected'; ?>>
                <?php echo htmlspecialchars($lvl); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="col-md-3 col-lg-2">
        <label class="form-label filter-bold-label" for="filter-exp">Max exp (yrs)</label>
        <div class="input-icon-group">
          <span class="i-icon" aria-hidden="true"><i class="bi bi-graph-up"></i></span>
          <input id="filter-exp" type="number" name="max_exp" min="0" class="form-control filter-bold" value="<?php echo htmlspecialchars($maxExp); ?>">
        </div>
      </div>
      <div class="col-md-3 col-lg-2">
        <label class="form-label filter-bold-label" for="filter-region">Region</label>
        <div class="input-icon-group">
          <span class="i-icon" aria-hidden="true"><i class="bi bi-geo"></i></span>
          <input id="filter-region" name="region" class="form-control filter-bold" placeholder="e.g., Metro Manila" value="<?php echo htmlspecialchars($region); ?>">
        </div>
      </div>
      <div class="col-md-3 col-lg-2">
        <label class="form-label filter-bold-label" for="filter-city">City</label>
        <div class="input-icon-group">
          <span class="i-icon" aria-hidden="true"><i class="bi bi-buildings"></i></span>
          <input id="filter-city" name="city" class="form-control filter-bold" placeholder="e.g., Parañaque City" value="<?php echo htmlspecialchars($city); ?>">
        </div>
      </div>
      <div class="col-md-3 col-lg-2">
        <label class="form-label filter-bold-label" for="filter-pay">Min monthly pay (PHP)</label>
        <div class="input-icon-group">
          <span class="i-icon" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
          <input id="filter-pay" type="number" name="min_pay" min="0" class="form-control filter-bold" value="<?php echo htmlspecialchars($minPay); ?>">
        </div>
      </div>
      <div class="col-md-3 col-lg-2">
        <label class="form-label filter-bold-label" for="filter-access">Accessibility</label>
        <div class="input-icon-group">
          <span class="i-icon" aria-hidden="true"><i class="bi bi-universal-access"></i></span>
          <select id="filter-access" name="tag" class="form-select filter-bold">
            <option value="">Any</option>
              <?php foreach ($accessTags as $t): ?>
                <option value="<?php echo htmlspecialchars($t); ?>" <?php if ($tag === $t) echo 'selected'; ?>>
                  <?php echo htmlspecialchars($t); ?>
                </option>
              <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="col-lg-2 d-grid">
        <button class="btn btn-primary fw-semibold"><i class="bi bi-search me-1" aria-hidden="true"></i><span>Search</span></button>
      </div>
      <div class="col-auto">
        <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-x-circle me-1" aria-hidden="true"></i><span>Clear</span></a>
      </div>
    </form>
    <div id="filters-results-line" class="filters-result-line" aria-live="polite">
      <span class="dot" aria-hidden="true"></span>
      Work From Home only · Showing <?php echo count($jobs); ?> of <?php echo $total; ?> result<?php echo $total===1?'':'s'; ?><?php if ($pages > 1) echo ' · Page ' . $page . ' of ' . $pages; ?>
    </div>
  </div>
</div>

<!-- Job card inline styles consolidated into global stylesheet -->

<div class="row g-3">
  <?php foreach ($jobs as $job): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card job-card shadow-sm h-100 border-0">
        <?php if (!empty($job['job_image'])): ?>
          <img class="job-thumb" src="../<?php echo htmlspecialchars($job['job_image']); ?>" alt="Job image for <?php echo htmlspecialchars($job['title']); ?>">
        <?php else: ?>
          <div class="job-thumb d-flex align-items-center justify-content-center text-muted" aria-hidden="true">
            <i class="bi bi-briefcase" style="font-size:1.5rem" aria-hidden="true"></i>
          </div>
        <?php endif; ?>
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-1">
            <h3 class="h6 fw-semibold mb-0 me-2">
              <a class="text-decoration-none" href="job_view.php?job_id=<?php echo urlencode($job['job_id']); ?>"><?php echo Helpers::sanitizeOutput($job['title']); ?></a>
            </h3>
            <div class="text-muted small job-meta"><?php echo date('M j, Y', strtotime($job['created_at'])); ?></div>
          </div>
          <div class="text-muted small mb-2">
            <?php echo Helpers::sanitizeOutput($job['company_name'] ?? ''); ?> ·
            <a class="text-decoration-none" href="employer_jobs.php?employer_id=<?php echo urlencode($job['employer_id']); ?>">View all</a>
          </div>
          <div class="d-flex flex-wrap gap-1 mb-2">
            <span class="badge tag-badge"><i class="bi bi-house-door me-1"></i>WFH</span>
            <span class="badge tag-badge"><i class="bi bi-briefcase me-1"></i><?php echo Helpers::sanitizeOutput($job['employment_type']); ?></span>
            <?php if (!empty($job['accessibility_tags'])): $firstTag = explode(',', $job['accessibility_tags'])[0]; ?>
              <span class="badge tag-badge"><?php echo htmlspecialchars(trim($firstTag)); ?></span>
            <?php endif; ?>
          </div>
          <div class="small mb-2">
            <?php $cur=$job['salary_currency']?:'PHP'; $min=$job['salary_min']; $max=$job['salary_max']; $per=$job['salary_period']?:'monthly'; ?>
            <i class="bi bi-cash-coin me-1"></i><?php echo htmlspecialchars(fmt_salary($cur,$min,$max,$per)); ?>
          </div>
          <div class="small text-muted">
            <i class="bi bi-geo-alt me-1"></i>
            <?php $cityTxt=$job['location_city']?:''; $regionTxt=$job['location_region']?:''; $sep=($cityTxt&&$regionTxt)?', ':''; echo Helpers::sanitizeOutput($cityTxt.$sep.$regionTxt); ?>
          </div>
        </div>
        <div class="card-footer bg-white border-0 pt-0 pb-3 px-3">
          <a class="btn btn-sm btn-outline-primary w-100" href="job_view.php?job_id=<?php echo urlencode($job['job_id']); ?>">View</a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$jobs): ?>
    <div class="col-12"><div class="alert alert-secondary">No jobs found. Try different filters.</div></div>
  <?php endif; ?>
</div>

<?php if ($pages > 1): ?>
  <nav class="mt-3" aria-label="Job pagination">
    <ul class="pagination">
      <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
        <a class="page-link" href="<?php echo htmlspecialchars($prevLink); ?>" tabindex="-1">Previous</a>
      </li>
      <li class="page-item disabled"><span class="page-link">Page <?php echo $page; ?> of <?php echo $pages; ?></span></li>
      <li class="page-item <?php echo $page >= $pages ? 'disabled' : ''; ?>">
        <a class="page-link" href="<?php echo htmlspecialchars($nextLink); ?>">Next</a>
      </li>
    </ul>
  </nav>
<?php endif; ?>

<!-- FEATURES SECTION -->
<section class="features-section">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">Platform Benefits</div>
      <h2 class="section-heading text-gradient-brand">Why Choose Our Portal</h2>
      <p class="section-sub">Purpose-built to help talent and accessibility-focused employers connect through transparent, remote-first opportunities.</p>
    </div>
    <div class="feature-grid">
      <div class="feature-card">
        <div class="feature-icon"><i class="bi bi-universal-access"></i></div>
        <h3>Accessibility Focus</h3>
        <p>Jobs tagged for different accessibility needs help you quickly find supportive roles.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="bi bi-building-check"></i></div>
        <h3>Verified Employers</h3>
        <p>Employers undergo approval so you can trust the legitimacy of postings.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="bi bi-funnel"></i></div>
        <h3>Powerful Filters</h3>
        <p>Search by education, experience, accessibility tags, pay, and location.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="bi bi-speedometer2"></i></div>
        <h3>Fast Applications</h3>
        <p>Apply directly and track your applications without leaving the platform.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="bi bi-clipboard-data"></i></div>
        <h3>Transparent Details</h3>
        <p>Key role data (salary, type, location) surfaced clearly for quick evaluation.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="bi bi-shield-check"></i></div>
        <h3>Secure & Private</h3>
        <p>Your account & application data handled with privacy best practices.</p>
      </div>
    </div>
  </div>
</section>

<!-- STEPS SECTION -->
<section class="steps-section">
  <div class="container">
    <div class="text-center">
      <div class="section-eyebrow">How It Works</div>
      <h2 class="section-heading">Get Started in 4 Steps</h2>
      <p class="section-sub">Simple, streamlined, and designed for accessibility. Your next opportunity is a few clicks away.</p>
    </div>
    <div class="steps-timeline">
      <div class="step-item">
        <div class="step-number">1</div>
        <h4>Create your account</h4>
        <p>Sign up as a job seeker or employer and complete your profile.</p>
      </div>
      <div class="step-item">
        <div class="step-number">2</div>
        <h4>Search & filter roles</h4>
        <p>Use advanced filters to zero in on roles aligned with your needs.</p>
      </div>
      <div class="step-item">
        <div class="step-number">3</div>
        <h4>Apply & engage</h4>
        <p>Submit applications and interact with employers directly.</p>
      </div>
      <div class="step-item">
        <div class="step-number">4</div>
        <h4>Grow your career</h4>
        <p>Track progress, expand skills, and pursue new remote opportunities.</p>
      </div>
    </div>
  </div>
</section>

<!-- TESTIMONIALS (Static Sample) -->
<section class="testimonials-section">
  <div class="container">
    <div class="text-center">
      <div class="section-eyebrow">Testimonials</div>
      <h2 class="section-heading text-gradient-brand">Community Voices</h2>
      <p class="section-sub">Real experiences from users finding meaningful remote work through the platform.</p>
    </div>
    <div class="testimonial-row">
      <div class="testimonial-card">
        <div class="quote">“The accessibility tags saved me so much time. I quickly found roles that understood my needs.”</div>
        <div class="user">
          <div class="testimonial-avatar">AL</div>
          <div>
            <div class="fw-semibold small">Ana L.</div>
            <div class="testimonial-meta">JOB SEEKER</div>
          </div>
        </div>
      </div>
      <div class="testimonial-card">
        <div class="quote">“Posting jobs here helped us reach talented applicants we were missing elsewhere.”</div>
        <div class="user">
          <div class="testimonial-avatar">RG</div>
          <div>
            <div class="fw-semibold small">Ramon G.</div>
            <div class="testimonial-meta">HR LEAD</div>
          </div>
        </div>
      </div>
      <div class="testimonial-card">
        <div class="quote">“The platform is clean, fast, and focused on inclusion. It’s become my daily job search hub.”</div>
        <div class="user">
          <div class="testimonial-avatar">MT</div>
          <div>
            <div class="fw-semibold small">Mark T.</div>
            <div class="testimonial-meta">JOB SEEKER</div>
          </div>
        </div>
      </div>
      <div class="testimonial-card">
        <div class="quote">“Approval and verification flows give us confidence in the quality of applicants and employers.”</div>
        <div class="user">
          <div class="testimonial-avatar">JL</div>
          <div>
            <div class="fw-semibold small">Jessa L.</div>
            <div class="testimonial-meta">OPERATIONS</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA SECTION -->
<section class="cta-section text-white">
  <div class="container">
    <div class="cta-inner">
      <h2 class="cta-title">Ready to Find or Post Inclusive Remote Jobs?</h2>
      <p class="cta-lead">Join the growing community connecting talent and employers through accessibility-centered opportunities.</p>
      <div class="d-flex flex-wrap justify-content-center gap-2">
        <a href="register.php" class="btn btn-light btn-lg px-4 fw-semibold shadow-sm"><i class="bi bi-person-plus me-1"></i> Get Started</a>
        <a href="#job-filters" class="btn btn-light btn-lg px-4 fw-semibold shadow-sm"><i class="bi bi-search me-1"></i> Browse Jobs</a>
      </div>
    </div>
  </div>
</section>

<?php include '../includes/footer.php'; ?>