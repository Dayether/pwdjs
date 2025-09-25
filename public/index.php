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
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body p-3 p-md-4">

    <style>
      /* Emphasis styling for filter labels & inputs */
      .filter-bold-label { font-weight:600 !important; }
      .filter-bold {
        font-weight:600;
      }
      .filter-bold::placeholder {
        font-weight:500;
        color:#6c757d;
      }
      .filter-bold:focus {
        border-width:2px;
        box-shadow:none;
      }
      /* Optional: make select text bold as well */
      select.filter-bold option { font-weight:500; }
    </style>

  <form class="row g-2 align-items-end" method="get">
      <div class="col-lg-4">
        <label class="form-label filter-bold-label">Keyword</label>
        <input type="text" name="q" class="form-control filter-bold" placeholder="Title or company" value="<?php echo htmlspecialchars($q); ?>">
      </div>
      <div class="col-md-3 col-lg-2">
        <label class="form-label filter-bold-label">Education</label>
        <select name="edu" class="form-select filter-bold">
          <option value="">Any</option>
          <?php foreach ($eduLevels as $lvl): ?>
            <option value="<?php echo htmlspecialchars($lvl); ?>" <?php if ($edu === $lvl) echo 'selected'; ?>>
              <?php echo htmlspecialchars($lvl); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 col-lg-2">
        <label class="form-label filter-bold-label">Max exp (yrs)</label>
        <input type="number" name="max_exp" min="0" class="form-control filter-bold" value="<?php echo htmlspecialchars($maxExp); ?>">
      </div>
      <div class="col-md-3 col-lg-2">
        <label class="form-label filter-bold-label">Region</label>
        <input name="region" class="form-control filter-bold" placeholder="e.g., Metro Manila" value="<?php echo htmlspecialchars($region); ?>">
      </div>
      <div class="col-md-3 col-lg-2">
        <label class="form-label filter-bold-label">City</label>
        <input name="city" class="form-control filter-bold" placeholder="e.g., Parañaque City" value="<?php echo htmlspecialchars($city); ?>">
      </div>
      <div class="col-md-3 col-lg-2">
        <label class="form-label filter-bold-label">Min monthly pay (PHP)</label>
        <input type="number" name="min_pay" min="0" class="form-control filter-bold" value="<?php echo htmlspecialchars($minPay); ?>">
      </div>
      <div class="col-md-3 col-lg-2">
        <label class="form-label filter-bold-label">Accessibility</label>
        <select name="tag" class="form-select filter-bold">
          <option value="">Any</option>
            <?php foreach ($accessTags as $t): ?>
              <option value="<?php echo htmlspecialchars($t); ?>" <?php if ($tag === $t) echo 'selected'; ?>>
                <?php echo htmlspecialchars($t); ?>
              </option>
            <?php endforeach; ?>
        </select>
      </div>
      <div class="col-lg-2 d-grid">
        <button class="btn btn-primary fw-semibold"><i class="bi bi-search me-1"></i>Search</button>
      </div>
      <div class="col-auto">
        <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-x-circle me-1"></i>Clear</a>
      </div>
    </form>
    <div class="text-muted small mt-2">
      Work From Home only. Showing <?php echo count($jobs); ?> of <?php echo $total; ?> result<?php echo $total===1?'':'s'; ?><?php if ($pages > 1) echo ' · Page ' . $page . ' of ' . $pages; ?>
    </div>
  </div>
</div>

<style>
.job-card { border-radius: 12px; overflow:hidden; }
.job-thumb { width: 100%; height: 140px; object-fit: cover; background:#f6f7f9; }
.job-meta { font-size: .85rem; }
.tag-badge { font-size:.7rem; border:1px solid #e4e7eb; background:#f8fafc; }
</style>

<div class="row g-3">
  <?php foreach ($jobs as $job): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card job-card shadow-sm h-100 border-0">
        <?php if (!empty($job['job_image'])): ?>
          <img class="job-thumb" src="../<?php echo htmlspecialchars($job['job_image']); ?>" alt="Job image">
        <?php else: ?>
          <div class="job-thumb d-flex align-items-center justify-content-center text-muted">
            <i class="bi bi-briefcase" style="font-size:1.5rem"></i>
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

<?php include '../includes/footer.php'; ?>