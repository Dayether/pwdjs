<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/Taxonomy.php';
require_once '../classes/Search.php';
require_once '../classes/Matching.php';
require_once '../classes/User.php';
require_once '../classes/Application.php';
require_once '../classes/Job.php';
require_once '../classes/Matching.php';
require_once '../classes/User.php';

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
$sort    = trim($_GET['sort'] ?? 'newest'); // newest|oldest|pay_high|pay_low
$perPage = 12;
$offset  = ($page - 1) * $perPage;

$eduLevels  = Taxonomy::educationLevels();
$accessTags = Taxonomy::accessibilityTags();

// Determine if user is actively searching/filtering
$hasFilters = ($q !== '' || $edu !== '' || $maxExp !== '' || $tag !== '' || $region !== '' || $city !== '' || $minPay !== '');

// Related suggestions to display as chips under the search (only when searching)
$relatedSuggestions = [];
if ($q !== '') {
  try { $relatedSuggestions = Search::suggest($q, 8); } catch (Exception $e) { $relatedSuggestions = []; }
}

// Log search keyword into history for logged-in job seekers
if ($q !== '' && Helpers::isLoggedIn() && Helpers::isJobSeeker()) {
  Search::saveQuery($_SESSION['user_id'], $q);
}

// Data containers
$jobs = [];
$total = 0;
$companies = [];
$companiesTotal = 0;

if ($hasFilters) {
  // Build job filters (Approved employers, WFH only)
  $where = [
    "u.role = 'employer'",
    "u.employer_status = 'Approved'",
    "j.remote_option = 'Work From Home'"
  ];
  $params = [];

  if ($q !== '') { $where[] = "(j.title LIKE :q OR j.description LIKE :q OR u.company_name LIKE :q)"; $params[':q'] = '%' . $q . '%'; }
  if ($edu !== '') { $where[] = "(j.required_education = :edu OR j.required_education = '' OR j.required_education IS NULL)"; $params[':edu'] = $edu; }
  if ($maxExp !== '') { $where[] = "j.required_experience <= :maxExp"; $params[':maxExp'] = $maxExp; }
  if ($tag !== '') { $where[] = "FIND_IN_SET(:tag, j.accessibility_tags)"; $params[':tag'] = $tag; }
  if ($region !== '') { $where[] = "j.location_region LIKE :region"; $params[':region'] = '%' . $region . '%'; }
  if ($city !== '') { $where[] = "j.location_city LIKE :city"; $params[':city'] = '%' . $city . '%'; }
  if ($minPay !== '') { $where[] = "((j.salary_max IS NOT NULL AND j.salary_max >= :minPay) OR (j.salary_min IS NOT NULL AND j.salary_min >= :minPay))"; $params[':minPay'] = $minPay; }

  $whereSql = implode(' AND ', $where);

  // Count jobs
  $sqlCount = "SELECT COUNT(*) FROM jobs j JOIN users u ON u.user_id = j.employer_id WHERE $whereSql";
  $stmt = $pdo->prepare($sqlCount);
  foreach ($params as $k => $v) $stmt->bindValue($k, $v);
  $stmt->execute();
  $total = (int)$stmt->fetchColumn();

  // Determine sort order
  $orderSql = 'j.created_at DESC';
  if ($sort === 'oldest') $orderSql = 'j.created_at ASC';
  if ($sort === 'pay_high') $orderSql = '(COALESCE(j.salary_max,j.salary_min)) DESC, j.created_at DESC';
  if ($sort === 'pay_low') $orderSql = '(COALESCE(j.salary_min,j.salary_max)) ASC, j.created_at DESC';

  // Fetch paged job list
  $sqlList = "
    SELECT j.job_id, j.title, j.description, j.created_at, j.required_experience, j.required_education,
      j.location_city, j.location_region, j.employment_type,
      j.salary_currency, j.salary_min, j.salary_max, j.salary_period,
      j.accessibility_tags, j.job_image,
      u.company_name, u.user_id AS employer_id
    FROM jobs j
    JOIN users u ON u.user_id = j.employer_id
    WHERE $whereSql
  ORDER BY $orderSql
    LIMIT :limit OFFSET :offset";
  $stmt = $pdo->prepare($sqlList);
  foreach ($params as $k => $v) $stmt->bindValue($k, $v);
  $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $jobs = $stmt->fetchAll();
} else {
  // Company directory (only approved employers who posted WFH jobs)
  try {
    $sqlCountCo = "SELECT COUNT(DISTINCT u.user_id)
                   FROM users u
                   JOIN jobs j ON j.employer_id = u.user_id
                   WHERE u.role='employer' AND u.employer_status='Approved' AND j.remote_option='Work From Home'";
    $companiesTotal = (int)$pdo->query($sqlCountCo)->fetchColumn();

    $sqlCo = "SELECT u.user_id, u.company_name, u.profile_picture, u.city, u.region, COUNT(j.job_id) AS jobs_count
              FROM users u
              JOIN jobs j ON j.employer_id = u.user_id
              WHERE u.role='employer' AND u.employer_status='Approved' AND j.remote_option='Work From Home'
              GROUP BY u.user_id
              ORDER BY jobs_count DESC, u.company_name ASC
              LIMIT :limit OFFSET :offset";
    $st = $pdo->prepare($sqlCo);
    $st->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $st->bindValue(':offset', $offset, PDO::PARAM_INT);
    $st->execute();
    $companies = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    $companies = [];
    $companiesTotal = 0;
  }
}

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
$pages = max(1, (int)ceil(($hasFilters ? $total : $companiesTotal) / $perPage));

function fmt_salary($cur, $min, $max, $period) {
  if ($min === null && $max === null) return 'Salary not specified';
  $fmt = function($n){ return number_format((int)$n); };
  $range = ($min !== null && $max !== null && $min != $max)
    ? "{$fmt($min)}–{$fmt($max)}"
    : $fmt($min ?? $max);
  return "{$cur} {$range} / " . ucfirst($period);
}
?>
<div class="job-filters-card mb-4" style="margin-top: 1rem;">
  <div class="job-filters-inner p-3 p-md-4">
    <a id="job-filters"></a>
    <div class="filters-heading d-flex align-items-center gap-2 flex-wrap">
      <div class="fh-icon" aria-hidden="true"><i class="bi bi-funnel"></i></div>
      <h2 id="filters-title" class="mb-0">Find Accessible Work From Home Jobs</h2>
      <?php if ($hasFilters): ?>
        <span class="badge rounded-pill text-bg-light ms-1" title="Total results"><?php echo (int)$total; ?> job<?php echo ((int)$total===1?'':'s'); ?></span>
      <?php endif; ?>
    </div>
    <p class="filters-sub" id="filters-desc">Quickly search verified WFH roles from approved employers.</p>
    <?php if ($hasFilters): ?>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2" aria-label="Sort options">
      <?php
        $sorts = [
          'newest' => ['label' => 'Newest', 'icon' => 'bi-clock'],
          'oldest' => ['label' => 'Oldest', 'icon' => 'bi-clock-history'],
          'pay_high' => ['label' => 'High pay', 'icon' => 'bi-graph-up'],
          'pay_low' => ['label' => 'Low pay', 'icon' => 'bi-graph-down'],
        ];
        foreach ($sorts as $key=>$conf):
          $qsSort = $_GET; $qsSort['sort'] = $key; $qsSort['p'] = 1; $url = 'index.php?' . http_build_query($qsSort);
          $active = ($sort === $key);
      ?>
        <a href="<?php echo htmlspecialchars($url); ?>" class="btn btn-sm <?php echo $active ? 'btn-primary' : 'btn-outline-primary'; ?> rounded-pill">
          <i class="bi <?php echo $conf['icon']; ?> me-1"></i><?php echo htmlspecialchars($conf['label']); ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <form class="row g-3 align-items-end" method="get" role="search" aria-labelledby="filters-title" aria-describedby="filters-desc filters-results-line">
      <div class="col-lg-5">
        <label class="form-label filter-bold-label" for="filter-keyword" style="font-size:1rem">Keyword</label>
        <div class="input-icon-group position-relative">
          <span class="i-icon" aria-hidden="true"><i class="bi bi-search"></i></span>
          <input id="filter-keyword" type="text" name="q" class="form-control filter-bold" placeholder="Title or company" value="<?php echo htmlspecialchars($q); ?>" style="height:3.1rem;font-size:1.05rem;" autocomplete="off" aria-autocomplete="list" aria-expanded="false" aria-owns="kw-suggest-list">
          <!-- Suggestions/History dropdown -->
          <div id="kw-suggest" class="dropdown-menu shadow" style="display:none; position:absolute; top:100%; left:0; right:0; z-index:1050; max-height:300px; overflow:auto;">
            <div class="small text-muted px-3 pt-2" id="kw-suggest-header" style="display:none;">Suggestions</div>
            <ul id="kw-suggest-list" class="list-unstyled mb-0"></ul>
            <div class="d-flex align-items-center justify-content-between px-3 pt-2" id="kw-history-bar" style="display:none;">
              <div class="small text-muted" id="kw-history-header">Recent searches</div>
              <button id="kw-clear-history" type="button" class="btn btn-sm btn-outline-secondary">Clear</button>
            </div>
            <ul id="kw-history-list" class="list-unstyled mb-2"></ul>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-lg-2">
        <label class="form-label filter-bold-label" for="filter-edu" style="font-size:1rem">Education</label>
        <div class="input-icon-group">
          <span class="i-icon" aria-hidden="true"><i class="bi bi-mortarboard"></i></span>
          <select id="filter-edu" name="edu" class="form-select filter-bold" style="height:3.1rem;font-size:1.05rem;">
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
        <label class="form-label filter-bold-label" for="filter-exp" style="font-size:1rem">Max exp (yrs)</label>
        <div class="input-icon-group">
          <span class="i-icon" aria-hidden="true"><i class="bi bi-graph-up"></i></span>
          <input id="filter-exp" type="number" name="max_exp" min="0" class="form-control filter-bold" value="<?php echo htmlspecialchars($maxExp); ?>" style="height:3.1rem;font-size:1.05rem;">
        </div>
      </div>
      <div class="col-md-3 col-lg-2">
        <label class="form-label filter-bold-label" for="filter-region" style="font-size:1rem">Region</label>
        <div class="input-icon-group">
          <span class="i-icon" aria-hidden="true"><i class="bi bi-geo"></i></span>
          <input id="filter-region" name="region" class="form-control filter-bold" placeholder="e.g., Metro Manila" value="<?php echo htmlspecialchars($region); ?>" style="height:3.1rem;font-size:1.05rem;">
        </div>
      </div>
      <div class="col-md-3 col-lg-2">
        <label class="form-label filter-bold-label" for="filter-city" style="font-size:1rem">City</label>
        <div class="input-icon-group">
          <span class="i-icon" aria-hidden="true"><i class="bi bi-buildings"></i></span>
          <input id="filter-city" name="city" class="form-control filter-bold" placeholder="e.g., Parañaque City" value="<?php echo htmlspecialchars($city); ?>" style="height:3.1rem;font-size:1.05rem;">
        </div>
      </div>
      <div class="col-md-3 col-lg-2">
        <label class="form-label filter-bold-label" for="filter-pay" style="font-size:1rem">Min monthly pay (PHP)</label>
        <div class="input-icon-group">
          <span class="i-icon" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
          <input id="filter-pay" type="number" name="min_pay" min="0" class="form-control filter-bold" value="<?php echo htmlspecialchars($minPay); ?>" style="height:3.1rem;font-size:1.05rem;">
        </div>
      </div>
      <div class="col-md-3 col-lg-2">
        <label class="form-label filter-bold-label" for="filter-access" style="font-size:1rem">Accessibility</label>
        <div class="input-icon-group">
          <span class="i-icon" aria-hidden="true"><i class="bi bi-universal-access"></i></span>
          <select id="filter-access" name="tag" class="form-select filter-bold" style="height:3.1rem;font-size:1.05rem;">
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
        <button class="btn btn-primary fw-semibold" style="height:3.1rem;font-size:1.05rem;"><i class="bi bi-search me-1" aria-hidden="true"></i><span>Search</span></button>
      </div>
      <div class="col-auto">
        <a class="btn btn-outline-secondary" href="index.php" style="height:3.1rem;font-size:1.05rem;"><i class="bi bi-x-circle me-1" aria-hidden="true"></i><span>Clear</span></a>
      </div>
    </form>
    <div id="filters-results-line" class="filters-result-line" aria-live="polite">
      <span class="dot" aria-hidden="true"></span>
      <?php if ($hasFilters): ?>
        Work From Home only · Showing <?php echo count($jobs); ?> of <?php echo $total; ?> result<?php echo $total===1?'':'s'; ?><?php if ($pages > 1) echo ' · Page ' . $page . ' of ' . $pages; ?>
      <?php else: ?>
        Approved WFH employers · Showing <?php echo count($companies); ?> of <?php echo $companiesTotal; ?> compan<?php echo $companiesTotal===1?'y':'ies'; ?><?php if ($pages > 1) echo ' · Page ' . $page . ' of ' . $pages; ?>
      <?php endif; ?>
    </div>
    <?php if (!empty($relatedSuggestions)): ?>
    <div class="mt-2" aria-label="Related searches">
      <div class="small text-muted mb-1">Related searches:</div>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($relatedSuggestions as $s):
          $text = is_array($s) ? ($s['text'] ?? '') : (string)$s;
          if ($text === '' || strcasecmp($text, $q) === 0) continue;
          $qs2 = $_GET; $qs2['q'] = $text; $url = 'index.php?' . http_build_query($qs2);
          $cnt = is_array($s) && isset($s['count']) ? (int)$s['count'] : 0;
        ?>
          <a class="btn btn-sm btn-outline-primary rounded-pill" href="<?php echo htmlspecialchars($url); ?>">
            <?php echo htmlspecialchars($text); ?>
            <?php if ($cnt > 0): ?><span class="badge text-bg-light ms-1"><?php echo $cnt; ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if (!$hasFilters): ?>
<!-- Landing Hero Section moved below search -->
<section class="landing-hero hero-white-text parallax" style="background: linear-gradient(135deg, rgba(13,110,253,.65), rgba(102,16,242,.72)), url('assets/images/hero/bg5.jpg') center top / contain no-repeat, linear-gradient(135deg, #f0f7ff 0%, #ffffff 65%); background-color:#0d6efd;">
  <div class="container hero-content">
    <div class="row align-items-center g-4">
      <div class="col-12">
        <h1 class="hero-title mt-3 mb-3 fade-up fade-delay-2" style="color:#fff !important; -webkit-text-fill-color:#fff !important;">Find Accessible <span class="text-gradient-brand" style="color:#fff !important; -webkit-text-fill-color:#fff !important; background:none !important;">Work From Home</span> Jobs</h1>
        <p class="hero-lead mb-4 fade-up fade-delay-3">Browse curated Work From Home roles from verified, accessibility‑conscious employers. Search, filter, and apply to jobs that value your skills and support your success.</p>
      </div>
    </div>
  </div>
  
</section>
<?php endif; ?>

<script>
(function(){
  const input = document.getElementById('filter-keyword');
  const wrap = document.getElementById('kw-suggest');
  const sugHeader = document.getElementById('kw-suggest-header');
  const sugList = document.getElementById('kw-suggest-list');
  const hisHeader = document.getElementById('kw-history-header');
  const hisBar = document.getElementById('kw-history-bar');
  const hisList = document.getElementById('kw-history-list');
  const clearBtn = document.getElementById('kw-clear-history');
  let lastQ = '';
  let hideTimer = null;
  let itemsOrder = [];
  let focusIndex = -1;

  function show(){ wrap.style.display = 'block'; input.setAttribute('aria-expanded','true'); }
  function hide(){ wrap.style.display = 'none'; input.setAttribute('aria-expanded','false'); }
  function clearLists(){ sugList.innerHTML=''; hisList.innerHTML=''; sugHeader.style.display='none'; hisHeader.style.display='none'; hisBar.style.display='none'; itemsOrder = []; focusIndex = -1; }

  function renderListText(ul, items){
    ul.innerHTML = '';
    items.forEach(txt => {
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.href = 'javascript:void(0)';
      a.className = 'dropdown-item';
      a.textContent = txt;
      a.addEventListener('click', ()=>{ input.value = txt; hide(); input.form && input.form.submit(); });
      li.appendChild(a);
      ul.appendChild(li);
      itemsOrder.push(a);
    });
  }

  function renderListWithCounts(ul, items){
    ul.innerHTML = '';
    items.forEach(obj => {
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.href = 'javascript:void(0)';
      a.className = 'dropdown-item d-flex justify-content-between align-items-center';
      const label = document.createElement('span');
      label.textContent = obj.text || '';
      const badge = document.createElement('span');
      badge.className = 'badge text-bg-light';
      if (obj.count && obj.count > 0) badge.textContent = obj.count;
      a.appendChild(label);
      a.appendChild(badge);
      a.addEventListener('click', ()=>{ input.value = obj.text || ''; hide(); input.form && input.form.submit(); });
      li.appendChild(a);
      ul.appendChild(li);
      itemsOrder.push(a);
    });
  }

  async function fetchJSON(url){
    try{ const r = await fetch(url, {credentials:'same-origin'}); if(!r.ok) return null; return await r.json(); }catch(e){ return null; }
  }

  let debounce;
  input.addEventListener('input', () => {
    if (debounce) clearTimeout(debounce);
    debounce = setTimeout(async () => {
      const q = (input.value || '').trim();
      if (q.length < 2) { clearLists(); await loadHistory(); if(hisList.children.length) show(); else hide(); return; }
      if (q === lastQ) return; lastQ = q;
      const data = await fetchJSON('api_search.php?action=suggest&q=' + encodeURIComponent(q));
      clearLists();
      const suggestions = (data && Array.isArray(data.suggestions)) ? data.suggestions : [];
      if (suggestions.length) { sugHeader.style.display='block'; renderListWithCounts(sugList, suggestions); }
      await loadHistory();
      if (suggestions.length || hisList.children.length) show(); else hide();
    }, 200);
  });

  input.addEventListener('focus', async ()=>{
    await loadHistory(); if (hisList.children.length) { show(); }
  });

  input.addEventListener('blur', ()=>{
    hideTimer = setTimeout(hide, 150);
  });
  wrap.addEventListener('mousedown', ()=>{ if (hideTimer) { clearTimeout(hideTimer); hideTimer=null; } });

  async function loadHistory(){
    const data = await fetchJSON('api_search.php?action=history');
    const history = (data && Array.isArray(data.history)) ? data.history : [];
    if (history.length){ hisHeader.style.display='block'; hisBar.style.display='flex'; renderListText(hisList, history); }
  }

  clearBtn?.addEventListener('click', async ()=>{
    const r = await fetchJSON('api_search.php?action=clear_history');
    if (r && r.ok) { clearLists(); hide(); }
  });

  function moveFocus(dir){
    if (!itemsOrder.length) return;
    focusIndex = (focusIndex + dir + itemsOrder.length) % itemsOrder.length;
    itemsOrder.forEach((el,i)=>{ el.classList.toggle('active', i===focusIndex); });
  }
  input.addEventListener('keydown', (e)=>{
    if (wrap.style.display !== 'block') return;
    if (e.key === 'ArrowDown') { e.preventDefault(); moveFocus(1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); moveFocus(-1); }
    else if (e.key === 'Enter') { if (focusIndex>=0 && itemsOrder[focusIndex]) { e.preventDefault(); itemsOrder[focusIndex].click(); } }
    else if (e.key === 'Escape') { hide(); }
  });
})();
</script>

<!-- Job card inline styles consolidated into global stylesheet -->

<?php if ($hasFilters): ?>
  <div class="container py-2">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <div class="small text-muted"><i class="bi bi-list-ul me-1"></i> Results</div>
    <div class="badge bg-light text-dark"><?php echo (int)$total; ?> job<?php echo ((int)$total===1?'':'s'); ?></div>
  </div>
  <div class="row g-3" id="two-pane">
    <?php if ($jobs): ?>
      <div class="col-xl-7">
        <div id="leftPane" class="pane-scroll">
        <div class="list-group" id="job-list" role="listbox" aria-label="Job results list">
          <?php
            $me = null; $meSkills = []; $meEduCanon = ''; $meYears = 0;
            if (Helpers::isLoggedIn() && Helpers::isJobSeeker()) {
              try {
                $me = User::findById($_SESSION['user_id']);
                if ($me) {
                  $meSkills = Matching::userSkillIds($me->user_id);
                  $meEduCanon = Taxonomy::canonicalizeEducation($me->education_level ?: $me->education ?: '') ?? '';
                  $meYears = (int)($me->experience ?? 0);
                }
              } catch (Throwable $e) { $me = null; }
            }
          ?>
          <?php foreach ($jobs as $i => $job): ?>
            <?php
              $jid = htmlspecialchars((string)$job['job_id']);
              $title = htmlspecialchars($job['title'] ?? '');
              $company = htmlspecialchars($job['company_name'] ?? '');
              $loc = htmlspecialchars(trim(($job['location_city'] ?? '') . (isset($job['location_region']) && $job['location_region'] ? ', ' . $job['location_region'] : '')));
              $etype = htmlspecialchars($job['employment_type'] ?? '');
              $created = htmlspecialchars(date('M j, Y', strtotime($job['created_at'] ?? 'now')));
              $tagsText = trim((string)($job['accessibility_tags'] ?? ''));
              $salary = fmt_salary($job['salary_currency'] ?? 'PHP', $job['salary_min'] ?? null, $job['salary_max'] ?? null, $job['salary_period'] ?? 'month');
            ?>
            <a href="javascript:void(0)" class="list-group-item list-group-item-action py-3 job-item" data-id="<?php echo $jid; ?>" data-index="<?php echo $i; ?>" aria-selected="false">
              <div class="d-flex w-100 justify-content-between">
                <h5 class="mb-1 d-flex align-items-center gap-2">
                  <?php echo $title; ?>
                  <?php
                  $matchBadge = '';
                  if ($me) {
                    try {
                      $jobObj = new Job($job);
                      $score = Application::calculateMatchScoreFromInput($jobObj, $meYears, $meSkills, $meEduCanon);
                      $pct = (int)round($score);
                      $cls = $pct >= 75 ? 'text-bg-success' : ($pct >= 50 ? 'text-bg-warning' : 'text-bg-secondary');
                      $matchBadge = '<span class="badge badge-match '.$cls.'" title="Estimated match score based on your profile">'.$pct.'%</span>';
                    } catch (Throwable $e) { /* ignore */ }
                  }
                  echo $matchBadge;
                  ?>
                </h5>
                <small class="text-muted"><?php echo $created; ?></small>
              </div>
              <p class="mb-1 text-muted"><?php echo $company; ?> · <?php echo $etype; ?><?php if ($loc) echo ' · ' . $loc; ?></p>
              <div class="small">
                <span class="me-2"><i class="bi bi-cash-coin" aria-hidden="true"></i> <?php echo htmlspecialchars($salary); ?></span>
                <?php if ($tagsText !== ''): ?>
                  <?php foreach (explode(',', $tagsText) as $t): $t=trim($t); if (!$t) continue; ?>
                    <span class="badge bg-light text-dark border me-1 mb-1"><?php echo htmlspecialchars($t); ?></span>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
        </div>
      </div>
      <div class="col-xl-5">
        <div id="rightPane" class="pane-scroll">
          <div id="job-detail-panel" class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted">Select a job to view details here.</div>
            </div>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="col-12"><div class="alert alert-secondary">No jobs found. Try different filters.</div></div>
    <?php endif; ?>
  </div>
  </div>
  <?php if ($jobs): ?>
  <script>
  (function(){
    const jobsData = <?php
      $jobsForJs = array_map(function($j){
        return [
          'id' => (string)($j['job_id'] ?? ''),
          'title' => (string)($j['title'] ?? ''),
          'company' => (string)($j['company_name'] ?? ''),
          'city' => (string)($j['location_city'] ?? ''),
          'region' => (string)($j['location_region'] ?? ''),
          'employment_type' => (string)($j['employment_type'] ?? ''),
          'salary_currency' => (string)($j['salary_currency'] ?? ''),
          'salary_min' => isset($j['salary_min']) ? (int)$j['salary_min'] : null,
          'salary_max' => isset($j['salary_max']) ? (int)$j['salary_max'] : null,
          'salary_period' => (string)($j['salary_period'] ?? ''),
          'experience' => (string)($j['required_experience'] ?? ''),
          'education' => (string)($j['required_education'] ?? ''),
          'tags' => (string)($j['accessibility_tags'] ?? ''),
          'created_at' => (string)($j['created_at'] ?? ''),
          'job_image' => (string)($j['job_image'] ?? ''),
          'description' => (string)($j['description'] ?? ''),
        ];
      }, $jobs);
      echo json_encode($jobsForJs, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    ?>;

  const list = document.getElementById('job-list');
  const panel = document.getElementById('job-detail-panel');
  const rightPane = document.getElementById('rightPane');

  // Build a fast lookup by job id to avoid any equality quirks
  const jobsMap = Object.create(null);
  for (const j of jobsData) { if (j && typeof j.id !== 'undefined') jobsMap[String(j.id)] = j; }

    function fmt(n){ return (n||n===0) ? n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',') : ''; }
    function fmtSalary(cur, min, max, period){
      if (min==null && max==null) return 'Salary not specified';
      let range = (min!=null && max!=null && min!==max) ? `${fmt(min)}–${fmt(max)}` : fmt(min ?? max);
      const per = period ? period.charAt(0).toUpperCase() + period.slice(1) : '';
      return `${cur} ${range} / ${per}`;
    }

    function escapeHtml(s){ const div=document.createElement('div'); div.textContent=s??''; return div.innerHTML; }

    function renderDetail(job){
      const loc = [job.city, job.region].filter(Boolean).join(', ');
      const salary = fmtSalary(job.salary_currency || 'PHP', job.salary_min, job.salary_max, job.salary_period || 'month');
      const tags = (job.tags || '').split(',').map(t=>t.trim()).filter(Boolean);
      const created = job.created_at ? new Date(job.created_at).toLocaleDateString(undefined, {month:'short', day:'numeric', year:'numeric'}) : '';
      panel.innerHTML = `
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h4 class="mb-1">${escapeHtml(job.title)}</h4>
              <div class="text-muted mb-2">${escapeHtml(job.company)} · ${escapeHtml(job.employment_type || '')}${loc? ' · ' + escapeHtml(loc): ''}</div>
              <div class="mb-2"><i class="bi bi-cash-coin" aria-hidden="true"></i> ${escapeHtml(salary)}</div>
              ${tags.length ? `<div class="mb-2">${tags.map(t=>`<span class='badge bg-light text-dark border me-1 mb-1'>${escapeHtml(t)}</span>`).join('')}</div>` : ''}
              <div class="small text-muted">Posted ${escapeHtml(created)}</div>
            </div>
          </div>
          <hr/>
          <div class="job-desc" style="white-space:pre-wrap">${escapeHtml(job.description || 'No description provided.')}</div>
        </div>
        <div class="card-footer bg-white d-flex gap-2">
          <a class="btn btn-primary" href="job_view.php?job_id=${encodeURIComponent(job.id)}"><i class="bi bi-box-arrow-up-right me-1"></i>View posting</a>
          <a class="btn btn-outline-primary" href="job_apply.php?job_id=${encodeURIComponent(job.id)}"><i class="bi bi-send me-1"></i>Apply</a>
        </div>`;
    }

    function selectItem(el){
      // Prefer data-index (stable ordering) to avoid any id parsing issues
      const idx = parseInt(el.getAttribute('data-index') || '-1', 10);
      let job = (!Number.isNaN(idx) && idx >= 0 && idx < jobsData.length) ? jobsData[idx] : undefined;
      if (!job) {
        const idAttr = el.getAttribute('data-id') || '';
        job = jobsMap[String(idAttr)];
      }
      if (!job) { console && console.warn && console.warn('Job not found for element', el); return; }
      list.querySelectorAll('.job-item').forEach(a=>{ a.classList.remove('active'); a.setAttribute('aria-selected','false'); });
      el.classList.add('active');
      el.setAttribute('aria-selected','true');
      renderDetail(job);
      // Reset detail scroll to top so user sees new content start
      if (rightPane) rightPane.scrollTop = 0;
    }

    list?.addEventListener('click', (e)=>{ const a=e.target.closest('.job-item'); if(a) selectItem(a); });
    list?.addEventListener('keydown', (e)=>{
      const items = Array.from(list.querySelectorAll('.job-item'));
      let idx = items.findIndex(x=>x.classList.contains('active'));
      if (e.key==='ArrowDown'){ e.preventDefault(); idx = Math.min(items.length-1, idx+1); items[idx]?.focus(); selectItem(items[idx]); }
      if (e.key==='ArrowUp'){ e.preventDefault(); idx = Math.max(0, idx-1); items[idx]?.focus(); selectItem(items[idx]); }
      if (e.key==='Enter'){ const a=document.activeElement.closest('.job-item'); if(a) selectItem(a); }
    });

    // Default: do not auto-select; show placeholder until user clicks an item
  })();
  </script>
  <div class="d-flex justify-content-center mt-3">
    <?php if ($page < $pages): ?>
      <?php $qsMore = $_GET; $qsMore['p'] = $page + 1; $moreUrl = 'index.php?' . http_build_query($qsMore); ?>
      <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars($moreUrl); ?>#job-filters"><i class="bi bi-plus-lg me-1"></i>Load more</a>
    <?php else: ?>
      <span class="text-muted small">End of results</span>
    <?php endif; ?>
  </div>
  <?php endif; ?>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($companies as $co): ?>
      <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm h-100 border-0">
          <div class="card-body d-flex align-items-center gap-3">
            <div style="width:56px;height:56px;border-radius:50%;overflow:hidden;background:#f5f7fb;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <?php if (!empty($co['profile_picture'])): ?>
                <img src="../<?php echo htmlspecialchars($co['profile_picture']); ?>" alt="Logo" style="width:100%;height:100%;object-fit:cover;"/>
              <?php else: ?>
                <i class="bi bi-building" style="font-size:1.35rem;color:#6c757d" aria-hidden="true"></i>
              <?php endif; ?>
            </div>
            <div class="min-w-0">
              <h3 class="h6 fw-semibold mb-1 text-truncate"><?php echo htmlspecialchars($co['company_name'] ?: 'Company'); ?></h3>
              <div class="small text-muted text-truncate">
                <?php $loc=trim(($co['city']?:'').(($co['city']??'') && ($co['region']??'') ? ', ' : '').($co['region']?:'')); echo htmlspecialchars($loc); ?>
              </div>
              <div class="mt-1"><span class="badge bg-light text-dark"><i class="bi bi-briefcase me-1"></i><?php echo (int)$co['jobs_count']; ?> job<?php echo ((int)$co['jobs_count']===1?'':'s'); ?></span></div>
            </div>
          </div>
          <div class="card-footer bg-white border-0 pt-0 pb-3 px-3">
            <a class="btn btn-sm btn-outline-primary w-100" href="company.php?user_id=<?php echo urlencode($co['user_id']); ?>">View company</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$companies): ?>
      <div class="col-12"><div class="alert alert-secondary">No approved employers with WFH jobs yet.</div></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

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
<?php if ($hasFilters): ?>
<style>
  /* Hide marketing sections during active search */
  .landing-hero, .features-section, .steps-section, .testimonials-section, .cta-section { display: none !important; }
  /* Two-pane independent scroll areas */
  .pane-scroll { overflow: auto; }
  /* Make job items keyboard-focusable styling */
  #job-list .job-item { outline: none; display:block; }
  #job-list .job-item:focus { box-shadow: 0 0 0 .2rem rgba(13,110,253,.25); }
  /* Add comfortable spacing between job items on the left */
  #job-list .list-group-item { margin-bottom: .5rem; border-radius: .5rem; border: 1px solid rgba(0,0,0,.2) !important; }
  #job-list .list-group-item:hover { border-color: rgba(0,0,0,.3) !important; }
  #job-list .job-item.active { border-color: #0d6efd !important; }
  #job-list .list-group-item:last-child { margin-bottom: 0; }
  .badge-match { font-size: .72rem; letter-spacing:.2px; }
</style>
<?php endif; ?>

<?php if ($hasFilters): ?>
<script>
(function(){
  // Dynamic height for left/right panes so they scroll without affecting the page
  const container = document.getElementById('two-pane');
  const left = document.getElementById('leftPane');
  const right = document.getElementById('rightPane');
  if (!container || !left || !right) return;

  function updateHeights(){
    const rect = container.getBoundingClientRect();
    const viewportH = window.innerHeight || document.documentElement.clientHeight;
    const available = viewportH - rect.top - 24; // leave some bottom space
    const h = Math.max(260, available);
    left.style.height = h + 'px';
    left.style.maxHeight = h + 'px';
    right.style.height = h + 'px';
    right.style.maxHeight = h + 'px';
  }
  updateHeights();
  window.addEventListener('resize', updateHeights);
  window.addEventListener('orientationchange', updateHeights);
})();
</script>
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