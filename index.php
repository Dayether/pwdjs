<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';
require_once 'classes/Taxonomy.php';
require_once 'classes/Search.php';
require_once 'classes/Matching.php';
require_once 'classes/User.php';
require_once 'classes/Application.php';
require_once 'classes/Job.php';
require_once 'classes/Matching.php';
require_once 'classes/User.php';

include 'includes/header.php';
include 'includes/nav.php';
// Profile completeness nudge (job seekers)
include_once 'includes/profile_nudge.php';

$pdo = Database::getConnection();

// Inputs
// NEW Simplified Search Inputs (Professor feedback): What / Where / Disability
// Maintain backward compatibility by still reading legacy parameters if present.
$whatRaw = trim($_GET['what'] ?? '');
if ($whatRaw === '' && isset($_GET['q'])) {
    $whatRaw = trim($_GET['q']);
}
$whereUnified = trim($_GET['where'] ?? '');
// If unified where not used, allow legacy region/city individually
$region  = trim($_GET['region'] ?? '');
$city    = trim($_GET['city'] ?? '');
if ($whereUnified === '' && $region !== '' && $city !== '') {
    $whereUnified = $city; // prefer specific city if both provided
}
$disability = trim($_GET['disability'] ?? ($_GET['pwd_type'] ?? ''));

// Legacy / advanced (hidden in simplified UI but still processed if manually supplied)
$q       = $whatRaw; // keep existing variable name for suggestion/history logic downstream
$edu     = trim($_GET['edu'] ?? '');
$maxExp  = ($_GET['max_exp'] ?? '') !== '' ? max(0, (int)$_GET['max_exp']) : '';
$tag     = trim($_GET['tag'] ?? '');
$minPay  = ($_GET['min_pay'] ?? '') !== '' ? max(0, (int)$_GET['min_pay']) : '';
$includeUnspecPay = isset($_GET['inc_pay_unspec']) && $_GET['inc_pay_unspec'] === '1';
$page    = max(1, (int)($_GET['p'] ?? 1));
$sort    = trim($_GET['sort'] ?? 'newest'); // newest|oldest|pay_high|pay_low
$perPage = 12;
$offset  = ($page - 1) * $perPage;

$eduLevels  = Taxonomy::educationLevels();
$accessTags = Taxonomy::accessibilityTags();

// Determine if user is actively searching/filtering
$hasFilters = ($q !== '' || $whereUnified !== '' || $disability !== '' || $edu !== '' || $maxExp !== '' || $tag !== '' || $region !== '' || $city !== '' || $minPay !== '');

// Related suggestions to display as chips under the search (only when searching)
$relatedSuggestions = [];
if ($q !== '') {
    try {
        $relatedSuggestions = Search::suggest($q, 8);
    } catch (Exception $e) {
        $relatedSuggestions = [];
    }
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

// Canonical disability list comes from Taxonomy to match registration & job posting forms
$disabilityOptions = Taxonomy::disabilityCategories();

if ($hasFilters) {
    // Build job filters (Approved employers, WFH only)
    $where = [
        "u.role = 'employer'",
        "u.employer_status = 'Approved'",
        "j.remote_option = 'Work From Home'",
        "j.moderation_status = 'Approved'" // only approved jobs visible in search
    ];
    $params = [];
    $extraJoin = '';

    if ($q !== '') {
        // Title-only search: split into tokens and require each token to appear in the title
        $tokens = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);
        if (!$tokens) {
            $where[] = "j.title LIKE :q1";
            $params[':q1'] = '%' . $q . '%';
        } else {
            $cl = [];
            $i = 0;
            foreach ($tokens as $tok) {
                $i++;
                $ph = ":q$i";
                $cl[] = "j.title LIKE $ph";
                $params[$ph] = '%' . $tok . '%';
            }
            $where[] = '(' . implode(' AND ', $cl) . ')';
        }
    }
    if ($whereUnified !== '') {
        $where[] = "(j.location_region LIKE :where OR j.location_city LIKE :where)";
        $params[':where'] = '%' . $whereUnified . '%';
    }
    // Hierarchical education filter: selecting a higher level includes all lower (user assumed qualified above requirement)
    if ($edu !== '') {
        // Build ordered list + rank map (ascending order already guaranteed by Taxonomy::educationLevels())
        $ranked = array_values($eduLevels); // e.g. [Elementary, High School, ..., Doctorate]
        $idx = array_search($edu, $ranked, true);
        if ($idx === false) {
            // Fallback to equality behavior if value not recognized
            $where[] = "(j.required_education = :edu OR j.required_education = '' OR j.required_education IS NULL)";
            $params[':edu'] = $edu;
        } else {
            $eligible = array_slice($ranked, 0, $idx + 1); // all lower-or-equal levels
            // Prepare placeholders
            $eduPh = [];
            foreach ($eligible as $i => $lvlName) {
                $ph = ":edu_lvl_$i";
                $eduPh[] = $ph;
                $params[$ph] = $lvlName;
            }
            // Include blank / NULL meaning "no specific requirement"
            // Use double quotes outside so we can include single quotes for empty string literal safely
            $where[] = "( (j.required_education IN (" . implode(',', $eduPh) . ")) OR j.required_education = '' OR j.required_education IS NULL)";
        }
    }
    // Experience logic already matches the idea: if you specify max years you want jobs requiring <= that.
    if ($maxExp !== '') {
        $where[] = "j.required_experience <= :maxExp";
        $params[':maxExp'] = $maxExp;
    }
    if ($tag !== '') {
        $where[] = "FIND_IN_SET(:tag, j.accessibility_tags)";
        $params[':tag'] = $tag;
    }
    // Legacy region/city filters (ignored if unified where used) maintained for backward compatibility
    if ($whereUnified === '') {
        if ($region !== '') {
            $where[] = "j.location_region LIKE :region";
            $params[':region'] = '%' . $region . '%';
        }
        if ($city !== '') {
            $where[] = "j.location_city LIKE :city";
            $params[':city'] = '%' . $city . '%';
        }
    }
    if ($minPay !== '') {
        // Refined min pay logic with optional inclusion of fully unspecified salaries:
        //  - Explicit min exists AND >= desired
        //  - OR no min but max exists AND max >= desired
        //  - OR (when includeUnspecPay) both min & max are NULL (unspecified pay)
        if ($includeUnspecPay) {
            $where[] = "( (j.salary_min IS NOT NULL AND j.salary_min >= :minPay) OR (j.salary_min IS NULL AND j.salary_max IS NOT NULL AND j.salary_max >= :minPay) OR (j.salary_min IS NULL AND j.salary_max IS NULL) )";
        } else {
            $where[] = "( (j.salary_min IS NOT NULL AND j.salary_min >= :minPay) OR (j.salary_min IS NULL AND j.salary_max IS NOT NULL AND j.salary_max >= :minPay) )";
        }
        $params[':minPay'] = $minPay;
    } elseif (!$includeUnspecPay) {
        // If no min pay filter but still excluding unspecified is FALSE -> default show all.
        // Nothing to add. If we wanted to hide unspecified by default we would add condition here; current behavior keeps them.
    }

    // Disability filter (normalized mapping table)
    if ($disability !== '') {
        $extraJoin .= " JOIN job_applicable_pwd_types jap ON jap.job_id = j.job_id AND jap.pwd_type = :disability";
        $params[':disability'] = $disability;
    }

    $whereSql = implode(' AND ', $where);

    // Count jobs
    $sqlCount = "SELECT COUNT(DISTINCT j.job_id) FROM jobs j JOIN users u ON u.user_id = j.employer_id $extraJoin WHERE $whereSql";
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
    SELECT DISTINCT j.job_id, j.title, j.description, j.created_at, j.required_experience, j.required_education,
      j.location_city, j.location_region, j.employment_type,
      j.salary_currency, j.salary_min, j.salary_max, j.salary_period,
      j.accessibility_tags, j.job_image,
      COALESCE(jt.pwd_types, j.applicable_pwd_types) AS pwd_types,
      u.company_name, u.user_id AS employer_id
    FROM jobs j
    JOIN users u ON u.user_id = j.employer_id
    LEFT JOIN (
      SELECT job_id, GROUP_CONCAT(DISTINCT pwd_type ORDER BY pwd_type SEPARATOR ',') AS pwd_types
      FROM job_applicable_pwd_types
      GROUP BY job_id
    ) jt ON jt.job_id = j.job_id
    $extraJoin
    WHERE $whereSql
  ORDER BY $orderSql
    LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sqlList);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $jobs = $stmt->fetchAll();
    // Count unspecified pay jobs on current page (both min & max NULL)
    $unspecifiedCountPage = 0;
    if ($jobs) {
        foreach ($jobs as $jj) {
            if (array_key_exists('salary_min', $jj) && array_key_exists('salary_max', $jj) && $jj['salary_min'] === null && $jj['salary_max'] === null) {
                $unspecifiedCountPage++;
            }
        }
    }
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
    $stmtJobsAll = $pdo->query("SELECT COUNT(*) FROM jobs j JOIN users u ON u.user_id = j.employer_id WHERE u.role='employer' AND u.employer_status='Approved' AND j.moderation_status='Approved'");
    $totalJobsAll = (int)$stmtJobsAll->fetchColumn();

    // Total WFH jobs (even if not filtered)
    $stmtJobsWFH = $pdo->query("SELECT COUNT(*) FROM jobs j JOIN users u ON u.user_id = j.employer_id WHERE u.role='employer' AND u.employer_status='Approved' AND j.remote_option='Work From Home' AND j.moderation_status='Approved'");
    $totalWFHJobs = (int)$stmtJobsWFH->fetchColumn();

    // Distinct regions represented by WFH jobs
    $stmtRegions = $pdo->query("SELECT COUNT(DISTINCT CONCAT(IFNULL(j.location_region,''), '|', IFNULL(j.location_city,''))) FROM jobs j JOIN users u ON u.user_id = j.employer_id WHERE u.role='employer' AND u.employer_status='Approved' AND j.remote_option='Work From Home' AND j.moderation_status='Approved'");
    $totalLocations = (int)$stmtRegions->fetchColumn();
} catch (Exception $e) {
    $totalEmployers = $totalJobsAll = $totalWFHJobs = $totalLocations = 0; // fail gracefully
}

// Pagination links
$qs = $_GET;
unset($qs['p']);
$baseQS = http_build_query($qs);
$prevLink = 'index.php' . ($baseQS ? ('?' . $baseQS . '&') : '?') . 'p=' . max(1, $page - 1);
$nextLink = 'index.php' . ($baseQS ? ('?' . $baseQS . '&') : '?') . 'p=' . ($page + 1);
$pages = max(1, (int)ceil(($hasFilters ? $total : $companiesTotal) / $perPage));

function fmt_salary($cur, $min, $max, $period)
{
    if ($min === null && $max === null) return 'Salary not specified';
    $fmt = function ($n) {
        return number_format((int)$n);
    };
    $range = ($min !== null && $max !== null && $min != $max)
        ? "{$fmt($min)}–{$fmt($max)}"
        : $fmt($min ?? $max);
    return "{$cur} {$range} / " . ucfirst($period);
}
?>
<style>
    /* Accessible Color Palette */
    :root {
        --color-primary: #1E3A8A;
        --color-secondary: #14B8A6;
        --color-accent: #FACC15;
        --color-bg: #F9FAFB;
        --color-text: #111827;
        --color-error: #DC2626;
        --color-primary-rgb: 30, 58, 138;
        --color-secondary-rgb: 20, 184, 166;
        --color-accent-rgb: 250, 204, 21;
    }

    /* Compact filter bar adjustments */
    .compact-filters {
        background: #ffffff;
        border: none;
        border-bottom: 1px solid #e2e8f0;
        border-radius: 0 !important;
        box-shadow: none;
    }

    .compact-filters * {
        border-radius: 0 !important;
    }

    .compact-filters .form-control,
    .compact-filters .form-select,
    .compact-filters .input-icon-group {
        border-radius: 0 !important;
    }

    .filters-condensed label.filter-bold-label {
        margin-bottom: .25rem;
        font-weight: 600;
    }

    .filters-condensed .form-control,
    .filters-condensed .form-select {
        padding: 0 1rem;
        display: flex;
        align-items: center;
        line-height: 1;
    }

    .filters-condensed #filter-what {
        height: 3.1rem !important;
        padding-left: 2.5rem;
    }

    .filters-condensed #filter-where,
    .filters-condensed #filter-disability {
        height: 3.1rem !important;
    }

    .filters-condensed #filter-where {
        padding-left: 2.5rem;
    }

    .filters-condensed #filter-disability {
        padding-left: 2.5rem;
    }

    .filters-condensed button.btn,
    .filters-condensed a.btn {
        height: 3.1rem !important;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }

    /* Icon positioning for input fields */
    .input-icon-group {
        position: relative;
    }

    .input-icon-group .i-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        z-index: 10;
        pointer-events: none;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .job-filters-card {
        position: sticky;
        top: 0;
        z-index: 50;
        background: #ffffff;
        backdrop-filter: none;
        border-radius: 0 !important;
    }

    .job-filters-inner {
        border-radius: 0 !important;
    }

    /* Give more space to results by reducing margin below filters */
    .job-filters-card+.container {
        margin-top: .35rem;
    }

    /* Two-pane layout tweaks */
    #two-pane {
        --gap: 1rem;
    }

    @media (min-width:992px) {

        /* Override to new 55/45 ratio */
        #two-pane.two-pane-flex {
            display: flex;
            flex-wrap: nowrap;
            align-items: stretch;
            margin-left: -.75rem;
            margin-right: -.75rem;
            padding-left: 0;
            padding-right: 0;
        }

        /* Edge-to-edge feel: increase list a bit while keeping readable detail */
        #two-pane .col-results {
            flex: 0 0 58%;
            max-width: 58%;
            display: flex;
            flex-direction: column;
            padding-left: .75rem;
            padding-right: .35rem;
        }

        #two-pane .col-detail {
            flex: 0 0 42%;
            max-width: 42%;
            display: flex;
            flex-direction: column;
            position: relative;
            padding-right: .75rem;
            padding-left: .65rem;
        }

        #two-pane .pane-divider {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, rgba(0, 0, 0, .08), rgba(0, 0, 0, .02));
        }

        #two-pane .pane-scroll {
            padding-right: .5rem;
        }

        #two-pane .detail-scroll {
            padding-left: 0;
            padding-right: .25rem;
        }

        #two-pane .detail-card {
            border: 1px solid #e2e8f0;
            background: #fff;
            box-shadow: 0 1px 2px -1px rgba(0, 0, 0, .08), 0 2px 4px -2px rgba(0, 0, 0, .06);
        }

        /* Tighten list item horizontal padding so more text fits */
        #leftPane .list-group-item {
            padding: 0.85rem 0.9rem;
        }

        #leftPane .list-group-item h5 {
            font-size: 1rem;
            margin-bottom: .35rem;
        }

        #leftPane .list-group-item small {
            font-size: .74rem;
        }
    }

    @media (max-width:991.98px) {
        #two-pane.two-pane-flex {
            display: block;
        }

        #two-pane .pane-divider {
            display: none;
        }

        #two-pane .detail-scroll {
            margin-top: 1rem;
            padding-left: 0;
        }
    }

    /* Provide more vertical room for list items */
    #leftPane .list-group-item {
        min-height: 112px;
    }

    #leftPane {
        scrollbar-width: thin;
    }

    #rightPane {
        scrollbar-width: thin;
    }

    /* Increase list group card visual weight */
    #job-list .list-group-item {
        background: #fff;
    }

    body.search-active .landing-hero,
    body.search-active .callouts-duo-section,
    body.search-active .cta-section {
        display: none !important;
    }

    body.search-active {
        background: #F9FAFB;
    }

    /* Scroll shadow indicators */
    .pane-scroll {
        position: relative;
    }

    .pane-scroll:before,
    .pane-scroll:after {
        content: "";
        position: sticky;
        left: 0;
        right: 0;
        z-index: 4;
        height: 12px;
        display: block;
    }

    .pane-scroll:before {
        top: 0;
        background: linear-gradient(rgba(255, 255, 255, .9), rgba(255, 255, 255, 0));
    }

    .pane-scroll:after {
        bottom: 0;
        background: linear-gradient(rgba(255, 255, 255, 0), rgba(255, 255, 255, .9));
    }

    /* Detail panel card full height */
    #rightPane #job-detail-panel {
        min-height: 100%;
    }

    /* Reduce heading spacing */
    #filters-title {
        font-size: 1.05rem;
    }

    .filters-sub {
        display: none;
    }

    .filters-heading {
        margin-bottom: .35rem;
    }

    .filters-floating-meta {
        position: absolute;
        top: 1.1rem;
        right: .75rem;
    }

    .job-filters-inner {
        position: relative;
    }

    @media (max-width:991.98px) {
        .filters-floating-meta {
            display: none !important;
        }
    }

    /* Related search chips smaller */
    [aria-label="Related searches"] .btn {
        font-size: .7rem;
        padding: .25rem .6rem;
    }

    .action-bar-compressed .btn {
        white-space: nowrap;
    }

    /* Option A uniform compact controls */
    .action-bar-compressed .btn,
    .action-bar-compressed .badge,
    .action-bar-compressed .sort-chips .btn {
        height: 34px;
        padding: .35rem .7rem;
        font-size: .73rem;
        line-height: 1.05;
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
    }

    .action-bar-compressed .sort-chips .btn {
        font-size: .68rem;
        padding: .35rem .65rem;
    }

    .action-bar-compressed .btn i {
        font-size: .85em;
        margin-right: .35rem;
    }

    .action-bar-compressed .badge {
        display: inline-flex;
        align-items: center;
        font-size: .65rem;
        padding: .35rem .55rem;
        line-height: 1;
    }

    .action-bar-compressed .text-primary.small {
        font-size: .7rem !important;
    }

    .action-bar-compressed .btn-outline-primary.active {
        color: #fff;
        background: var(--primary-blue);
        border-color: var(--primary-blue);
    }

    /* Remove height override from earlier */
    .action-bar-compressed .btn.btn-primary,
    .action-bar-compressed .btn.btn-outline-secondary {
        height: 34px !important;
    }

    @media (max-width:1199.98px) {
        .action-bar-compressed .btn span {
            display: none !important;
        }

        .action-bar-compressed .btn i {
            margin-right: 0;
        }
    }

    /* New floating meta cluster to remove left gap */
    /* Inline sorts merged with related searches */
    [aria-label="Related searches"] .sort-chip {
        font-size: .68rem;
        padding: .3rem .75rem;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        border-radius: 2rem;
    }

    [aria-label="Related searches"] .sort-chip i {
        margin-right: .35rem;
        font-size: .85em;
    }

    [aria-label="Related searches"] .sort-chip.active {
        background: var(--primary-blue);
        border-color: var(--primary-blue);
        color: #fff;
    }

    [aria-label="Related searches"] .sort-meta-pill {
        font-size: .68rem;
        padding: .3rem .75rem;
        line-height: 1;
        border-radius: 2rem;
        display: inline-flex;
        align-items: center;
        border: 1px solid var(--primary-blue);
        color: var(--primary-blue);
        background: #fff;
        font-weight: 600;
    }

    [aria-label="Related searches"] .sort-count-badge {
        background: #fff;
        border: 1px solid var(--primary-blue);
        color: var(--primary-blue);
        font-weight: 600;
        padding: .3rem .6rem;
        font-size: .65rem;
        display: inline-flex;
        align-items: center;
        border-radius: 2rem;
    }

    @media (max-width:991.98px) {
        .filters-sort-row {
            justify-content: flex-start;
        }
    }

    @media (min-width:992px) {
        .action-bar-compressed {
            margin-top: -.25rem;
        }
    }

    @media (max-width:1199.98px) {
        .action-bar-compressed .btn i {
            margin-right: 0;
        }

        .action-bar-compressed .btn span {
            display: none !important;
        }
    }

    /* === Landing micro-interactions (purely visual) === */
    :root {
        --hover-shadow: 0 14px 32px -18px rgba(var(--primary-blue-rgb), .35), 0 8px 18px -16px rgba(2, 6, 23, .18);
    }

    /* Inputs: soft glow on hover/focus */
    .compact-filters .form-control:hover,
    .compact-filters .form-select:hover {
        border-color: rgba(var(--primary-blue-rgb), .3);
        box-shadow: 0 0 0 .18rem rgba(var(--primary-blue-rgb), .12);
    }

    .compact-filters .form-control:focus,
    .compact-filters .form-select:focus {
        box-shadow: 0 0 0 .22rem rgba(var(--accent-yellow-rgb), .28);
    }

    /* Buttons */
    .filters-condensed .btn.btn-primary {
        background: linear-gradient(90deg, #1E3A8A, #14B8A6);
        border: none;
        border-radius: 8px;
        font-weight: 600;
        box-shadow: 0 4px 12px -4px rgba(30, 58, 138, 0.4);
        transition: all .2s ease;
        height: 3.1rem !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        color: #ffffff;
    }

    .filters-condensed .btn.btn-primary:hover {
        background: linear-gradient(90deg, #1E3A8A, #0D9488);
        box-shadow: 0 6px 20px -4px rgba(30, 58, 138, 0.5);
        transform: translateY(-2px);
    }

    .filters-condensed .btn.btn-outline-secondary {
        background: #ffffff;
        border: 2px solid #1E3A8A;
        border-radius: 8px;
        color: #1E3A8A;
        font-weight: 600;
        transition: all .2s ease;
        height: 3.1rem !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }

    .filters-condensed .btn.btn-outline-secondary:hover {
        background: #F9FAFB;
        border-color: #14B8A6;
        color: #14B8A6;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px -4px rgba(30, 58, 138, 0.2);
    }

    .filters-condensed .btn.btn-outline-secondary:active {
        transform: translateY(0);
    }

    .filters-condensed .btn.btn-primary:active {
        transform: translateY(0);
    }

    /* Filter action buttons styling */
    .filter-action-btn {
        height: 3.1rem !important;
        font-size: 1rem;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 0.5rem;
        padding: 0 1.5rem !important;
        white-space: nowrap;
        line-height: 1 !important;
    }

    .filter-action-btn i {
        font-size: 1.1rem;
        line-height: 1;
    }

    @media (max-width: 991.98px) {
        .filter-action-btn {
            width: 100%;
        }
    }

    /* Related search chips */
    [aria-label="Related searches"] a.btn {
        transition: transform .12s ease, box-shadow .15s ease, background-color .15s ease, border-color .15s ease;
    }

    [aria-label="Related searches"] a.btn:hover {
        transform: scale(1.03);
        box-shadow: 0 10px 18px -12px rgba(var(--primary-blue-rgb), .25);
        border-color: rgba(var(--primary-blue-rgb), .3);
        background: var(--neutral-light);
    }

    /* Job list items hover enhancement */
    #job-list .list-group-item {
        transition: box-shadow .18s ease, transform .12s ease, border-color .15s ease, background-color .15s ease;
    }

    #job-list .list-group-item:hover {
        background: #fafdff;
    }

    /* Callouts: slight image zoom on hover */
    .callout-card {
        transition: transform .18s ease, box-shadow .2s ease, background-size .25s ease;
    }

    .callout-card:hover {
        background-size: 48% auto !important;
    }

    /* Reveal-on-scroll */
    @keyframes fadeSlideUp {
        from {
            opacity: 0;
            transform: translateY(8px);
        }

        to {
            opacity: 1;
            transform: none;
        }
    }

    [data-reveal] {
        opacity: 0;
        transform: translateY(8px);
    }

    .reveal-in {
        animation: fadeSlideUp .28s ease-out both;
    }

    /* Ensure profile dropdown is not covered by the sticky search bar (index only) */
    .navbar.navbar-themed {
        position: relative;
        z-index: 3000;
    }

    .navbar.navbar-themed .dropdown-menu {
        z-index: 5000 !important;
    }

    .job-filters-card {
        z-index: 50;
        margin-top: 0 !important;
    }
</style>
<div class="job-filters-card mb-3" style="margin-top:0;" data-reveal>
    <div class="job-filters-inner p-2 p-md-3 compact-filters">
        <a id="job-filters"></a>
        <!-- Original heading kept for mobile only -->
        <div class="d-lg-none mb-2">
            <div class="filters-heading d-flex align-items-center gap-2 flex-wrap mb-1">
                <div class="fh-icon" aria-hidden="true"><i class="bi bi-funnel"></i></div>
                <h2 id="filters-title" class="mb-0" style="font-size:1rem;">Find Accessible Work From Home Jobs</h2>
                <?php if ($hasFilters): ?>
                    <span class="badge rounded-pill text-bg-light ms-1" title="Total results"><?php echo (int)$total; ?> job<?php echo ((int)$total === 1 ? '' : 's'); ?></span>
                <?php endif; ?>
            </div>
            <p class="filters-sub small text-muted mb-2" id="filters-desc">Quickly search verified WFH roles from approved employers.</p>
            <?php if ($hasFilters): ?>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2" aria-label="Sort options">
                    <?php
                    $sorts = [
                        'newest' => ['label' => 'Newest', 'icon' => 'bi-clock'],
                        'oldest' => ['label' => 'Oldest', 'icon' => 'bi-clock-history'],
                        'pay_high' => ['label' => 'High pay', 'icon' => 'bi-graph-up'],
                        'pay_low' => ['label' => 'Low pay', 'icon' => 'bi-graph-down'],
                    ];
                    foreach ($sorts as $key => $conf):
                        $qsSort = $_GET;
                        $qsSort['sort'] = $key;
                        $qsSort['p'] = 1;
                        $url = 'index.php?' . http_build_query($qsSort);
                        $active = ($sort === $key);
                    ?>
                        <a href="<?php echo htmlspecialchars($url); ?>" class="btn btn-sm <?php echo $active ? 'btn-primary' : 'btn-outline-primary'; ?> rounded-pill">
                            <i class="bi <?php echo $conf['icon']; ?> me-1"></i><?php echo htmlspecialchars($conf['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <!-- Floating meta removed; integrated into form row for alignment -->
        <form class="row g-2 align-items-end filters-condensed" method="get" role="search" aria-labelledby="filters-title" aria-describedby="filters-desc">
            <div class="col-12 col-lg-4 col-xl-5 order-1">
                <label class="form-label filter-bold-label" for="filter-what" style="font-size:1rem">What</label>
                <div class="input-icon-group position-relative">
                    <span class="i-icon" aria-hidden="true"><i class="bi bi-search"></i></span>
                    <input id="filter-what" type="text" name="what" class="form-control filter-bold" placeholder="Job title" value="<?php echo htmlspecialchars($q); ?>" style="height:3.1rem;font-size:1.05rem;" autocomplete="off" aria-autocomplete="list" aria-expanded="false" aria-owns="kw-suggest-list">
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
            <div class="col-6 col-md-4 col-lg-3 col-xl-2 order-2">
                <label class="form-label filter-bold-label" for="filter-where" style="font-size:1rem">Where</label>
                <div class="input-icon-group">
                    <span class="i-icon" aria-hidden="true"><i class="bi bi-geo"></i></span>
                    <input id="filter-where" name="where" class="form-control filter-bold" placeholder="Region or city" value="<?php echo htmlspecialchars($whereUnified); ?>" style="height:3.1rem;font-size:1.05rem;">
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2 col-xl-2 order-3">
                <label class="form-label filter-bold-label" for="filter-disability" style="font-size:1rem">Disability</label>
                <div class="input-icon-group">
                    <span class="i-icon" aria-hidden="true"><i class="bi bi-person-wheelchair"></i></span>
                    <select id="filter-disability" name="disability" class="form-select filter-bold" style="height:3.1rem;font-size:1.05rem;">
                        <option value="">Any</option>
                        <?php foreach ($disabilityOptions as $opt): ?>
                            <option value="<?php echo htmlspecialchars($opt); ?>" <?php if ($disability === $opt) echo 'selected'; ?>><?php echo htmlspecialchars($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-12 col-md-4 col-lg-3 col-xl-3 order-4 d-flex align-items-end gap-2 action-bar-compressed">
                <button type="submit" class="btn btn-primary fw-semibold flex-grow-1 flex-lg-grow-0 filter-action-btn">
                    <i class="bi bi-search" aria-hidden="true"></i><span>Search</span>
                </button>
                <a class="btn btn-outline-secondary flex-grow-1 flex-lg-grow-0 filter-action-btn" href="index.php">
                    <i class="bi bi-x-circle" aria-hidden="true"></i><span>Clear</span>
                </a>
            </div>
            <!-- Sort row removed: merged into Related searches area -->
            <!-- Advanced filters (legacy) removed as requested -->
        </form>
        <!-- Status line removed as requested -->
        <?php if ($hasFilters && ($minPay !== '' || $includeUnspecPay)): ?>
            <div class="mt-2 d-flex flex-wrap gap-2" aria-label="Active pay filters">
                <?php if ($minPay !== ''): ?>
                    <span class="badge rounded-pill text-bg-primary d-inline-flex align-items-center gap-1">
                        <i class="bi bi-cash-coin"></i> Min <?php echo number_format((int)$minPay); ?>
                    </span>
                <?php endif; ?>
                <?php if ($includeUnspecPay): ?>
                    <span class="badge rounded-pill text-bg-secondary d-inline-flex align-items-center gap-1">
                        <i class="bi bi-question-circle"></i> Including unspecified
                    </span>
                    <?php if (($unspecifiedCountPage ?? 0) > 0): ?>
                        <span class="badge rounded-pill text-bg-light border d-inline-flex align-items-center gap-1 text-muted">
                            + <?php echo (int)$unspecifiedCountPage; ?> job<?php echo $unspecifiedCountPage === 1 ? '' : 's'; ?> w/o pay data
                        </span>
                    <?php endif; ?>
                <?php endif; ?>
                <span class="small text-muted">Logic: show jobs where stated minimum ≥ your value, or only a maximum exists and still ≥ your value.</span>
            </div>
            <?php if ($minPay !== '' && (int)$minPay >= 50000 && $includeUnspecPay): ?>
                <div id="highMinPayInfo" class="alert alert-info px-3 py-2 mt-2 mb-0 small" role="note">
                    High minimum selected (<?php echo number_format((int)$minPay); ?>). Unspecified salary jobs are still listed – review details before applying.
                </div>
            <?php else: ?>
                <div id="highMinPayInfo" class="d-none"></div>
            <?php endif; ?>
        <?php endif; ?>
        <div class="mt-2" aria-label="Related searches">
            <?php if (!empty($relatedSuggestions)): ?><div class="small text-muted mb-1">Related searches:</div><?php endif; ?>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <?php if (!empty($relatedSuggestions)): ?>
                    <?php foreach ($relatedSuggestions as $s):
                        $text = is_array($s) ? ($s['text'] ?? '') : (string)$s;
                        if ($text === '' || strcasecmp($text, $q) === 0) continue;
                        // Build URL that sets 'what' directly and clears 'q' conflicts; also reset to page 1
                        $qs2 = $_GET;
                        unset($qs2['q']);
                        $qs2['what'] = $text;
                        $qs2['p'] = 1;
                        $url = 'index.php?' . http_build_query($qs2);
                        $cnt = is_array($s) && isset($s['count']) ? (int)$s['count'] : 0;
                    ?>
                        <a class="btn btn-sm btn-outline-primary rounded-pill" href="<?php echo htmlspecialchars($url); ?>">
                            <?php echo htmlspecialchars($text); ?>
                            <?php if ($cnt > 0): ?><span class="badge text-bg-light ms-1"><?php echo $cnt; ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($hasFilters): ?>
                    <div class="sort-meta-pill"><i class="bi bi-funnel"></i><span>WFH Jobs</span></div>
                    <span class="sort-count-badge" title="Total results"><?php echo (int)$total; ?></span>
                    <?php foreach ($sorts as $key => $conf): $qsSort = $_GET;
                        $qsSort['sort'] = $key;
                        $qsSort['p'] = 1;
                        $url = 'index.php?' . http_build_query($qsSort);
                        $active = ($sort === $key); ?>
                        <a href="<?php echo htmlspecialchars($url); ?>" class="btn btn-sm btn-outline-primary rounded-pill sort-chip <?php echo $active ? 'active' : ''; ?>" <?php if ($active) echo 'aria-current="true"'; ?>>
                            <i class="bi <?php echo $conf['icon']; ?>"></i><span><?php echo htmlspecialchars($conf['label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!$hasFilters): ?>
    <!-- Promotional hero below search: background image with CTA card -->
    <style>
        .promo-auth-hero {
            position: relative !important;
            overflow: visible !important;
            margin-top: 0 !important;
            margin-bottom: 1rem !important;
            padding-bottom: 4rem !important;
        }

        /* Decorative separator at bottom */
        .promo-auth-hero::after {
            content: "" !important;
            position: absolute !important;
            bottom: 0 !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            width: 60px !important;
            height: 60px !important;
            background: radial-gradient(circle, rgba(30, 58, 138, 0.08) 0%, rgba(20, 184, 166, 0.08) 50%, transparent 70%) !important;
            border-radius: 50% !important;
        }

        @media (min-width: 768px) {
            .promo-auth-hero {
                margin-bottom: 6rem !important;
                padding-bottom: 5rem !important;
            }

            .promo-auth-hero::after {
                width: 80px !important;
                height: 80px !important;
            }
        }

        @media (min-width: 992px) {
            .promo-auth-hero {
                margin-bottom: 1rem !important;
                padding-bottom: 6rem !important;
            }

            .promo-auth-hero::after {
                width: 100px !important;
                height: 100px !important;
            }
        }

        /* Frame (blue outline target): centered holder that contains the background */
        .promo-auth-hero .hero-frame {
            position: relative;
            margin: 0 auto;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 26px 56px -28px rgba(2, 6, 23, .30), 0 16px 36px -24px rgba(2, 6, 23, .18);
        }

        .promo-auth-hero .hero-frame {
            min-height: 360px;
            max-width: 1080px;
        }

        @media (min-width: 768px) {
            .promo-auth-hero .hero-frame {
                min-height: 460px;
            }
        }

        @media (min-width: 992px) {
            .promo-auth-hero .hero-frame {
                min-height: 520px;
                max-width: 1120px;
            }
        }

        @media (min-width: 1200px) {
            .promo-auth-hero .hero-frame {
                min-height: 560px;
                max-width: 1160px;
            }
        }

        @media (max-width: 575.98px) {
            .promo-auth-hero .hero-frame {
                min-height: 320px;
                border-radius: 14px;
            }
        }

        .promo-auth-hero .hero-bg {
            position: absolute;
            inset: 0;
            background: center/cover no-repeat;
            filter: none;
            transform: translateZ(0);
        }

        .promo-auth-hero .hero-frame::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(0, 0, 0, .08), rgba(0, 0, 0, .12));
            pointer-events: none;
            z-index: 1;
        }

        .promo-auth-hero .hero-layer {
            position: absolute;
            inset: 0;
            z-index: 2;
            display: flex;
            align-items: flex-start;
            padding: 1rem;
        }

        @media (min-width: 768px) {
            .promo-auth-hero .hero-layer {
                padding: 1.5rem;
            }
        }

        /* Keep default top padding so card doesn't move up when image is raised */
        /* Card (red outline target) – larger to reduce whitespace */
        .promo-auth-hero .promo-card-wrap {
            width: 380px;
            max-width: 96vw;
        }

        @media (min-width: 768px) {
            .promo-auth-hero .promo-card-wrap {
                width: 420px;
            }
        }

        @media (min-width: 992px) {
            .promo-auth-hero .promo-card-wrap {
                width: 460px;
            }
        }

        @media (min-width: 1200px) {
            .promo-auth-hero .promo-card-wrap {
                width: 500px;
            }
        }

        .promo-auth-hero .promo-card {
            position: relative;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: .95rem;
            box-shadow: 0 12px 28px -18px rgba(2, 6, 23, .18), 0 8px 22px -16px rgba(2, 6, 23, .10);
            transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
        }

        .promo-auth-hero .promo-card .card-body {
            padding: 1.25rem !important;
        }

        @media (min-width: 768px) {
            .promo-auth-hero .promo-card .card-body {
                padding: 1.75rem !important;
            }
        }

        @media (min-width: 992px) {
            .promo-auth-hero .promo-card .card-body {
                padding: 2rem !important;
            }
        }

        .promo-auth-hero .promo-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 40px -24px rgba(2, 6, 23, .22), 0 12px 26px -18px rgba(2, 6, 23, .14);
        }

        /* Hero Badge */
        .hero-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            background: linear-gradient(90deg, #1E3A8A, #14B8A6);
            color: #ffffff;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 0 4px 12px -4px rgba(30, 58, 138, 0.5);
            animation: badge-pulse 2s ease-in-out infinite;
        }

        .hero-badge i {
            color: #FACC15;
            animation: star-twinkle 1.5s ease-in-out infinite;
        }

        @keyframes badge-pulse {

            0%,
            100% {
                box-shadow: 0 4px 12px -4px rgba(30, 58, 138, 0.5);
            }

            50% {
                box-shadow: 0 6px 20px -4px rgba(30, 58, 138, 0.7);
            }
        }

        @keyframes star-twinkle {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.7;
                transform: scale(1.1);
            }
        }

        /* Stats Badges */
        .stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.9rem;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(30, 58, 138, 0.2);
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #1E3A8A;
            transition: all 0.2s ease;
            backdrop-filter: blur(8px);
        }

        .stat-badge:hover {
            background: rgba(255, 255, 255, 1);
            border-color: rgba(30, 58, 138, 0.4);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px -4px rgba(30, 58, 138, 0.3);
        }

        .stat-badge i {
            color: #14B8A6;
            font-size: 1rem;
        }

        .promo-auth-hero .promo-title {
            font-weight: 800;
            letter-spacing: .2px;
            color: #111827;
            font-size: 1.45rem;
            line-height: 1.28;
        }

        @media (min-width: 768px) {
            .promo-auth-hero .promo-title {
                font-size: 1.65rem;
            }
        }

        @media (min-width: 992px) {
            .promo-auth-hero .promo-title {
                font-size: 1.85rem;
            }
        }

        .promo-auth-hero .promo-sub {
            color: #111827;
            margin-bottom: .75rem;
            font-size: .95rem;
            opacity: 0.8;
        }

        @media (min-width: 768px) {
            .promo-auth-hero .promo-sub {
                font-size: 1rem;
            }
        }

        .promo-auth-hero .promo-points {
            list-style: none;
            padding-left: 0;
            margin: 0 0 .75rem;
        }

        .promo-auth-hero .promo-points li {
            display: flex;
            align-items: flex-start;
            gap: .6rem;
            color: #111827;
            font-size: .95rem;
            margin-bottom: .3rem;
        }

        @media (min-width: 768px) {
            .promo-auth-hero .promo-points li {
                font-size: 1rem;
            }
        }

        .promo-auth-hero .promo-points li i {
            color: #14B8A6;
            margin-top: .2rem;
            font-size: .85rem;
        }

        .promo-auth-hero .btn-hero {
            height: 52px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            border-radius: 10px;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        @media (min-width: 768px) {
            .promo-auth-hero .btn-hero {
                height: 56px;
                font-size: 1.05rem;
            }
        }

        @media (min-width: 992px) {
            .promo-auth-hero .btn-hero {
                height: 58px;
            }
        }

        .promo-auth-hero .btn-hero:focus {
            box-shadow: 0 0 0 .25rem rgba(173, 216, 255, 0.5);
        }

        .promo-auth-hero .btn-hero.btn-primary {
            background: linear-gradient(90deg, #1E3A8A, #14B8A6);
            border: none;
            box-shadow: 0 8px 20px -6px rgba(30, 58, 138, 0.5);
        }

        .promo-auth-hero .btn-hero.btn-primary:hover {
            background: linear-gradient(90deg, #1E3A8A, #0D9488);
            transform: translateY(-2px);
            box-shadow: 0 12px 28px -8px rgba(30, 58, 138, 0.6);
        }

        .promo-auth-hero .btn-hero.btn-primary i {
            font-size: 1.2rem;
        }

        .promo-auth-hero .btn-hero.btn-outline-primary {
            background: #fff;
            border: 2px solid #1E3A8A;
            color: #1E3A8A;
        }

        .promo-auth-hero .btn-hero.btn-outline-primary:hover {
            background: #F9FAFB;
            border-color: #14B8A6;
            color: #14B8A6;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px -6px rgba(30, 58, 138, 0.3);
        }

        /* Trust indicator styling */
        .promo-auth-hero .text-muted {
            font-size: 0.82rem;
            color: #111827;
            opacity: 0.7;
        }

        .promo-auth-hero .text-muted i {
            color: #14B8A6;
            font-size: 1rem;
        }

        .promo-auth-hero .promo-divider {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            color: #6b7280;
            margin: .1rem 0 .1rem;
        }

        .promo-auth-hero .promo-divider::before,
        .promo-auth-hero .promo-divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }

        .promo-auth-hero .promo-divider span {
            font-size: .8rem;
            padding: 0 .25rem;
        }

        /* Keep consistent hiding when searching */
        body.search-active .promo-auth-hero {
            display: none !important;
        }

        /* === Hero layout overrides (ensure effect even if external CSS is cached) === */
        /* Clean left side (no gradient wash from frame) */
        .promo-auth-hero .hero-frame::before {
            content: none !important;
        }

        /* Neutral background for hero section */
        section.landing-hero.promo-auth-hero {
            background: #F9FAFB !important;
        }

        /* Variables for precise positioning */
        .promo-auth-hero {
            --hero-pad: 16px;
            --card-w: 380px;
            --overlap: 12px;
            --bg-focus-y: 22%;
            --img-offset-y: 0px;
            --bg-shift-x: 0px;
        }

        @media (min-width: 768px) {
            .promo-auth-hero {
                --hero-pad: 24px;
                --card-w: 420px;
                --overlap: 14px;
                --bg-focus-y: 20%;
                --img-offset-y: 0px;
                --bg-shift-x: 0px;
            }
        }

        @media (min-width: 992px) {
            .promo-auth-hero {
                --card-w: 460px;
            }
        }

        @media (min-width: 1200px) {
            .promo-auth-hero {
                --card-w: 500px;
            }
        }

        /* Make the frame clip the image so it fills perfectly with rounded corners */
        .promo-auth-hero .hero-frame {
            overflow: hidden !important;
            background: transparent !important;
            box-shadow: none !important;
        }

        /* Right-side rounded rectangle photo whose left edge starts after half of the register card */
        @media (min-width: 768px) {
            .promo-auth-hero .hero-bg {
                position: absolute !important;
                /* Position it to the right side */
                left: 40% !important;
                right: 0 !important;
                top: var(--hero-pad) !important;
                bottom: var(--hero-pad) !important;
                width: calc(60% - var(--hero-pad)) !important;
                height: calc(100% - calc(var(--hero-pad) * 2)) !important;
                border-radius: 20px !important;
                box-shadow: none !important;
                /* Position image more to the right and center vertically */
                background-position: 75% 50% !important;
                background-repeat: no-repeat !important;
                background-size: cover !important;
                background-color: transparent !important;
                will-change: transform, filter;
                transition: transform .6s ease, filter .4s ease;
                opacity: 0.95;
                z-index: 1;
            }

            /* Center the card vertically and ensure it's above the image */
            .promo-auth-hero .hero-layer {
                align-items: center !important;
                position: relative;
                z-index: 2;
            }

            .promo-auth-hero .promo-card-wrap {
                margin-left: clamp(.15rem, 1.2vw, 1rem);
            }

            .promo-auth-hero .promo-card {
                position: relative;
                z-index: 3;
            }
        }

        @media (min-width: 992px) {
            .promo-auth-hero .hero-bg {
                left: 45% !important;
                width: calc(55% - var(--hero-pad)) !important;
                background-position: 70% 50% !important;
            }
        }

        @media (min-width: 1200px) {
            .promo-auth-hero .hero-bg {
                left: 48% !important;
                width: calc(52% - var(--hero-pad)) !important;
                background-position: 65% 50% !important;
            }
        }

        /* Hover/focus interaction for hero image */
        .promo-auth-hero:hover .hero-bg,
        .promo-auth-hero:focus-within .hero-bg {
            transform: scale(1.04);
            filter: saturate(1.03) contrast(1.02);
        }

        @media (max-width: 575.98px) {

            .promo-auth-hero:hover .hero-bg,
            .promo-auth-hero:focus-within .hero-bg {
                transform: scale(1.02);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .promo-auth-hero .hero-bg {
                transition: filter .2s ease;
            }

            .promo-auth-hero:hover .hero-bg,
            .promo-auth-hero:focus-within .hero-bg {
                transform: none;
            }
        }

        /* Move registration card slightly to the left (image unchanged) */
        @media (min-width: 768px) {
            .promo-auth-hero .promo-card-wrap {
                margin-left: -36px !important;
            }
        }

        @media (min-width: 992px) {
            .promo-auth-hero .promo-card-wrap {
                margin-left: -54px !important;
            }
        }

        @media (min-width: 1200px) {
            .promo-auth-hero .promo-card-wrap {
                margin-left: -72px !important;
            }
        }
    </style>
    <section class="landing-hero promo-auth-hero" style="--bg-focus-y: 6%; --bg-shift-x: 24px;">
        <div class="container">
            <div class="hero-frame">
                <div class="hero-bg" style="background-image:url('assets/images/hero/pwdbg.jpg');"></div>
                <div class="hero-layer">
                    <div class="row justify-content-start w-100 g-0">
                        <div class="col-12 col-sm-9 col-md-auto">
                            <div class="promo-card-wrap">
                                <div class="promo-card card" data-reveal>
                                    <div class="card-body p-3 p-md-4">
                                        <!-- Badge/Label -->
                                        <div class="mb-3">
                                            <span class="hero-badge">
                                                <i class="bi bi-star-fill me-1"></i>
                                                Trusted by <?php echo number_format($totalEmployers); ?>+ Employers
                                            </span>
                                        </div>

                                        <h2 class="h4 promo-title mb-3">Find the right PWD job for you on PWD Portal</h2>
                                        <p class="promo-sub mb-3">Sign in to see jobs matched to your skills, location, and accessibility needs.</p>

                                        <!-- Stats Badges -->
                                        <div class="d-flex flex-wrap gap-2 mb-3">
                                            <div class="stat-badge">
                                                <i class="bi bi-briefcase-fill"></i>
                                                <span><?php echo number_format($totalWFHJobs); ?>+ WFH Jobs</span>
                                            </div>
                                            <div class="stat-badge">
                                                <i class="bi bi-geo-alt-fill"></i>
                                                <span><?php echo number_format($totalLocations); ?>+ Locations</span>
                                            </div>
                                        </div>

                                        <ul class="promo-points">
                                            <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Verified, WFH-friendly employers</span></li>
                                            <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Jobs tagged by PWD accessibility types</span></li>
                                            <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Apply fast and track your progress</span></li>
                                        </ul>

                                        <div class="d-grid gap-2">
                                            <a href="register.php" class="btn btn-primary btn-hero fw-semibold">
                                                <i class="bi bi-person-plus me-2" aria-hidden="true"></i>
                                                <span>Get Started - It's Free</span>
                                            </a>
                                            <div class="promo-divider"><span>or</span></div>
                                            <a href="login.php" class="btn btn-outline-primary btn-hero fw-semibold">
                                                <i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>
                                                <span>Sign in to Your Account</span>
                                            </a>
                                        </div>

                                        <!-- Trust indicator -->
                                        <div class="text-center mt-3">
                                            <small class="text-muted d-flex align-items-center justify-content-center gap-1">
                                                <i class="bi bi-shield-check"></i>
                                                <span>100% Free. No credit card required.</span>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<script>
    // Dynamic client-side helper for high min pay info bubble visibility
    document.addEventListener('DOMContentLoaded', function() {
        const payInput = document.getElementById('filter-pay');
        const incUnspec = document.getElementById('inc-pay-unspec');
        const info = document.getElementById('highMinPayInfo');
        if (!payInput || !incUnspec || !info) return;

        function updateInfo() {
            const val = parseInt(payInput.value, 10);
            const show = !isNaN(val) && val >= 50000 && incUnspec.checked;
            if (show) {
                info.classList.remove('d-none');
                info.classList.add('alert', 'alert-info', 'px-3', 'py-2', 'mt-2', 'mb-0', 'small');
                info.textContent = 'High minimum selected (' + val.toLocaleString() + '). Unspecified salary jobs are still listed – review details before applying.';
            } else {
                info.classList.add('d-none');
            }
        }
        payInput.addEventListener('input', updateInfo);
        incUnspec.addEventListener('change', updateInfo);
    });
</script>

<script>
    (function() {
        // Updated to support simplified 'What' field (id=filter-what)
        const input = document.getElementById('filter-what');
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

        // --- Portalize dropdown so it never gets clipped by parent overflow ---
        // Move the existing wrapper (#kw-suggest) out to <body> once.
        if (wrap && wrap.parentNode !== document.body) {
            wrap.parentNode.removeChild(wrap);
            document.body.appendChild(wrap);
            wrap.style.position = 'fixed'; // anchor to viewport
            wrap.style.left = '0px';
            wrap.style.top = '0px';
            wrap.style.width = '0px';
            wrap.style.maxHeight = '400px'; // will be resized dynamically
            wrap.style.overflowY = 'auto';
            wrap.style.zIndex = 2000; // above other UI
        }

        function positionDropdown() {
            if (!wrap || wrap.style.display === 'none') return;
            const r = input.getBoundingClientRect();
            const availableBelow = window.innerHeight - r.bottom - 16;
            const maxH = Math.max(150, Math.min(420, availableBelow));
            wrap.style.top = (r.bottom) + 'px';
            wrap.style.left = (r.left) + 'px';
            wrap.style.width = r.width + 'px';
            wrap.style.maxHeight = maxH + 'px';
        }

        function show() {
            wrap.style.display = 'block';
            input.setAttribute('aria-expanded', 'true');
            positionDropdown();
        }

        function hide() {
            wrap.style.display = 'none';
            input.setAttribute('aria-expanded', 'false');
        }

        function clearLists() {
            sugList.innerHTML = '';
            hisList.innerHTML = '';
            sugHeader.style.display = 'none';
            hisHeader.style.display = 'none';
            hisBar.style.display = 'none';
            itemsOrder = [];
            focusIndex = -1;
        }

        function renderListText(ul, items) {
            ul.innerHTML = '';
            items.forEach(txt => {
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.href = 'javascript:void(0)';
                a.className = 'dropdown-item';
                a.textContent = txt;
                a.addEventListener('click', () => {
                    input.value = txt;
                    hide();
                    input.form && input.form.submit();
                });
                li.appendChild(a);
                ul.appendChild(li);
                itemsOrder.push(a);
            });
        }

        function renderListWithCounts(ul, items) {
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
                a.addEventListener('click', () => {
                    input.value = obj.text || '';
                    hide();
                    input.form && input.form.submit();
                });
                li.appendChild(a);
                ul.appendChild(li);
                itemsOrder.push(a);
            });
        }

        async function fetchJSON(url) {
            try {
                const r = await fetch(url, {
                    credentials: 'same-origin'
                });
                if (!r.ok) return null;
                return await r.json();
            } catch (e) {
                return null;
            }
        }

        let debounce;
        input.addEventListener('input', () => {
            if (debounce) clearTimeout(debounce);
            debounce = setTimeout(async () => {
                const q = (input.value || '').trim();
                if (q.length < 2) {
                    clearLists();
                    await loadHistory();
                    if (hisList.children.length) show();
                    else hide();
                    return;
                }
                if (q === lastQ) return;
                lastQ = q;
                const data = await fetchJSON('api_search?action=suggest&q=' + encodeURIComponent(q));
                clearLists();
                const suggestions = (data && Array.isArray(data.suggestions)) ? data.suggestions : [];
                if (suggestions.length) {
                    sugHeader.style.display = 'block';
                    renderListWithCounts(sugList, suggestions);
                }
                await loadHistory();
                if (suggestions.length || hisList.children.length) show();
                else hide();
                positionDropdown();
            }, 200);
        });

        input.addEventListener('focus', async () => {
            await loadHistory();
            if (hisList.children.length) {
                show();
            }
            positionDropdown();
        });

        input.addEventListener('blur', () => {
            hideTimer = setTimeout(hide, 150);
        });
        wrap.addEventListener('mousedown', () => {
            if (hideTimer) {
                clearTimeout(hideTimer);
                hideTimer = null;
            }
        });
        window.addEventListener('resize', () => {
            positionDropdown();
        });
        window.addEventListener('scroll', () => {
            positionDropdown();
        }, true);

        async function loadHistory() {
            const data = await fetchJSON('api_search?action=history');
            const history = (data && Array.isArray(data.history)) ? data.history : [];
            if (history.length) {
                hisHeader.style.display = 'block';
                hisBar.style.display = 'flex';
                renderListText(hisList, history);
            }
        }

        clearBtn?.addEventListener('click', async () => {
            const r = await fetchJSON('api_search?action=clear_history');
            if (r && r.ok) {
                clearLists();
                hide();
            }
        });

        function moveFocus(dir) {
            if (!itemsOrder.length) return;
            focusIndex = (focusIndex + dir + itemsOrder.length) % itemsOrder.length;
            itemsOrder.forEach((el, i) => {
                el.classList.toggle('active', i === focusIndex);
            });
        }
        input.addEventListener('keydown', (e) => {
            if (wrap.style.display !== 'block') return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                moveFocus(1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                moveFocus(-1);
            } else if (e.key === 'Enter') {
                if (focusIndex >= 0 && itemsOrder[focusIndex]) {
                    e.preventDefault();
                    itemsOrder[focusIndex].click();
                }
            } else if (e.key === 'Escape') {
                hide();
            }
        });
    })();
</script>

<!-- Job card inline styles consolidated into global stylesheet -->

<?php if ($hasFilters): ?>
    <div class="container py-2">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="small text-muted"><i class="bi bi-list-ul me-1"></i> Results</div>
            <div class="badge bg-light text-dark"><?php echo (int)$total; ?> job<?php echo ((int)$total === 1 ? '' : 's'); ?></div>
        </div>
        <div class="row g-3 two-pane-flex" id="two-pane">
            <?php if ($jobs): ?>
                <div class="col-xl-7 col-results flex-results">
                    <div id="leftPane" class="pane-scroll">
                        <div class="list-group" id="job-list" role="listbox" aria-label="Job results list">
                            <?php
                            $me = null;
                            $meSkills = [];
                            $meEduCanon = '';
                            $meYears = 0;
                            if (Helpers::isLoggedIn() && Helpers::isJobSeeker()) {
                                try {
                                    $me = User::findById($_SESSION['user_id']);
                                    if ($me) {
                                        $meSkills = Matching::userSkillIds($me->user_id);
                                        $meEduCanon = Taxonomy::canonicalizeEducation($me->education_level ?: $me->education ?: '') ?? '';
                                        $meYears = (int)($me->experience ?? 0);
                                    }
                                } catch (Throwable $e) {
                                    $me = null;
                                }
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
                                            <?php if (Helpers::isLoggedIn() && Helpers::isJobSeeker()): ?>
                                                <?php
                                                // Fast check from precomputed set in JS is not available in PHP here,
                                                // so we add a placeholder span to be toggled by JS after selection.
                                                ?>
                                                <span class="badge text-bg-success applied-badge" style="display:none;">Applied</span>
                                            <?php endif; ?>
                                            <?php
                                            $matchBadge = '';
                                            if ($me) {
                                                try {
                                                    $jobObj = new Job($job);
                                                    $score = Application::calculateMatchScoreFromInput($jobObj, $meYears, $meSkills, $meEduCanon);
                                                    $pct = (int)round($score);
                                                    $cls = $pct >= 75 ? 'text-bg-success' : ($pct >= 50 ? 'text-bg-warning' : 'text-bg-secondary');
                                                    $matchBadge = '<span class="badge badge-match ' . $cls . '" title="Estimated match score based on your profile">' . $pct . '%</span>';
                                                } catch (Throwable $e) { /* ignore */
                                                }
                                            }
                                            echo $matchBadge;
                                            ?>
                                        </h5>
                                        <small class="text-muted"><?php echo $created; ?></small>
                                    </div>
                                    <p class="mb-1 text-muted"><?php echo $company; ?> · <?php echo $etype; ?><?php if ($loc) echo ' · ' . $loc; ?></p>
                                    <div class="small">
                                        <span class="me-2"><i class="bi bi-cash-coin" aria-hidden="true"></i> <?php echo htmlspecialchars($salary); ?></span>
                                        <?php
                                        $pwdCsv = trim((string)($job['pwd_types'] ?? ''));
                                        if ($pwdCsv !== '') {
                                            $parts = array_filter(array_map('trim', explode(',', $pwdCsv)), fn($v) => $v !== '');
                                            $parts = array_values(array_unique($parts));
                                            foreach ($parts as $pt) {
                                                echo '<span class="badge bg-primary-subtle text-primary-emphasis border me-1 mb-1">' . htmlspecialchars($pt) . '</span>';
                                            }
                                        }
                                        ?>
                                        <?php if ($tagsText !== ''): ?>
                                            <?php foreach (explode(',', $tagsText) as $t): $t = trim($t);
                                                if (!$t) continue; ?>
                                                <span class="badge bg-light text-dark border me-1 mb-1"><?php echo htmlspecialchars($t); ?></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-xl-5 col-detail flex-detail">
                    <div class="pane-divider d-none d-xl-block" aria-hidden="true"></div>
                    <div id="rightPane" class="pane-scroll detail-scroll">
                        <div id="job-detail-panel" class="card shadow-sm h-100 detail-card">
                            <div class="card-body">
                                <div class="text-muted">Select a job to view details here.</div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-secondary">No jobs found. Try different filters.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($jobs): ?>
        <script>
            (function() {
                const jobsData = <?php
                                    // Pre-compute which jobs the current user has already applied to
                                    $appliedSet = [];
                                    $viewerForElig = null;
                                    if (Helpers::isLoggedIn() && Helpers::isJobSeeker()) {
                                        try {
                                            $viewerForElig = User::findById($_SESSION['user_id']);
                                        } catch (Throwable $e) {
                                            $viewerForElig = null;
                                        }
                                    }
                                    if (Helpers::isLoggedIn() && Helpers::isJobSeeker() && $jobs) {
                                        try {
                                            $ids = array_map(fn($r) => $r['job_id'], $jobs);
                                            $ids = array_filter($ids, fn($v) => $v !== null && $v !== '');
                                            if ($ids) {
                                                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                                                $pdoTmp = Database::getConnection();
                                                $stmtAp = $pdoTmp->prepare("SELECT job_id FROM applications WHERE user_id = ? AND job_id IN ($placeholders)");
                                                $params = array_merge([$_SESSION['user_id']], $ids);
                                                $stmtAp->execute($params);
                                                $rows = $stmtAp->fetchAll(PDO::FETCH_COLUMN) ?: [];
                                                foreach ($rows as $jid) {
                                                    $appliedSet[(string)$jid] = true;
                                                }
                                            }
                                        } catch (Throwable $e) { /* ignore */
                                        }
                                    }

                                    $jobsForJs = array_map(function ($j) use ($appliedSet, $viewerForElig) {
                                        $eligOk = null;
                                        $eligReasons = [];
                                        if ($viewerForElig) {
                                            try {
                                                $elig = Matching::canApply($viewerForElig, new Job($j));
                                                $eligOk = (bool)($elig['ok'] ?? false);
                                                $eligReasons = is_array($elig['reasons'] ?? null) ? $elig['reasons'] : [];
                                            } catch (Throwable $e) {
                                                $eligOk = null;
                                                $eligReasons = [];
                                            }
                                        }
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
                                            'applied' => isset($appliedSet[(string)($j['job_id'] ?? '')]),
                                            'elig_ok' => $eligOk,
                                            'elig_reasons' => $eligReasons
                                        ];
                                    }, $jobs);
                                    echo json_encode($jobsForJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                                    ?>;

                const list = document.getElementById('job-list');
                const panel = document.getElementById('job-detail-panel');
                const rightPane = document.getElementById('rightPane');

                // Current user context injected from PHP
                const CURRENT_USER = {
                    loggedIn: <?php echo Helpers::isLoggedIn() ? 'true' : 'false'; ?>,
                    role: <?php echo json_encode($_SESSION['role'] ?? ''); ?>,
                    csrf: <?php echo json_encode(Helpers::csrfToken()); ?>,
                    loginUrl: <?php
                                $redir = 'index.php' . (!empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '');
                                echo json_encode('login.php?redirect=' . $redir);
                                ?>
                };
                const HARD_LOCK = <?php echo Matching::hardLock() ? 'true' : 'false'; ?>;

                // Build a fast lookup by job id to avoid any equality quirks
                const jobsMap = Object.create(null);
                for (const j of jobsData) {
                    if (j && typeof j.id !== 'undefined') jobsMap[String(j.id)] = j;
                }

                function fmt(n) {
                    return (n || n === 0) ? n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',') : '';
                }

                function fmtSalary(cur, min, max, period) {
                    if (min == null && max == null) return 'Salary not specified';
                    let range = (min != null && max != null && min !== max) ? `${fmt(min)}–${fmt(max)}` : fmt(min ?? max);
                    const per = period ? period.charAt(0).toUpperCase() + period.slice(1) : '';
                    return `${cur} ${range} / ${per}`;
                }

                function escapeHtml(s) {
                    const div = document.createElement('div');
                    div.textContent = s ?? '';
                    return div.innerHTML;
                }

                function renderDetail(job) {
                    const loc = [job.city, job.region].filter(Boolean).join(', ');
                    const salary = fmtSalary(job.salary_currency || 'PHP', job.salary_min, job.salary_max, job.salary_period || 'month');
                    const tags = (job.tags || '').split(',').map(t => t.trim()).filter(Boolean);
                    const created = job.created_at ? new Date(job.created_at).toLocaleDateString(undefined, {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric'
                    }) : '';
                    const wasApplied = !!job.applied;
                    const eligKnown = (typeof job.elig_ok !== 'undefined' && job.elig_ok !== null);
                    const isHardLocked = HARD_LOCK && CURRENT_USER.loggedIn && CURRENT_USER.role === 'job_seeker' && eligKnown && job.elig_ok === false;
                    const warnBlock = isHardLocked ? `
        <div class="alert alert-warning mb-2 small"><i class="bi bi-shield-exclamation me-1"></i>
          You can't apply to this job because your profile doesn't meet the minimum requirements.
        </div>` : '';

                    panel.innerHTML = `
        <div class="card-body">
          <div class="detail-header sticky-header">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <h4 class="mb-1">${escapeHtml(job.title)}</h4>
                <div class="text-muted mb-2">${escapeHtml(job.company)} · ${escapeHtml(job.employment_type || '')}${loc? ' · ' + escapeHtml(loc): ''}</div>
                <div class="mb-2"><i class="bi bi-cash-coin" aria-hidden="true"></i> ${escapeHtml(salary)}</div>
                ${tags.length ? `<div class="mb-2">${tags.map(t=>`<span class='badge bg-light text-dark border me-1 mb-1'>${escapeHtml(t)}</span>`).join('')}</div>` : ''}
                <div class="small text-muted">Posted ${escapeHtml(created)}</div>
              </div>
            </div>
          </div>
          <hr/>
          <div class="job-desc" style="white-space:pre-wrap">${escapeHtml(job.description || 'No description provided.')}</div>
        </div>
        <div class="card-footer bg-white">
          ${warnBlock}
          <div class="d-flex gap-2">
            <a class="btn btn-primary" href="job_view.php?job_id=${encodeURIComponent(job.id)}"><i class="bi bi-box-arrow-up-right me-1"></i>View posting</a>
            ${wasApplied
              ? `<button type="button" class="btn btn-success" disabled><i class="bi bi-check2-circle me-1"></i>Applied</button>`
              : (isHardLocked
                  ? `<button type="button" class="btn btn-secondary" disabled><i class="bi bi-lock me-1"></i>Apply Disabled</button>`
                  : `<button type="button" class="btn btn-outline-primary" data-action="apply" data-job-id="${escapeHtml(job.id)}" data-job-title="${escapeHtml(job.title)}" data-href="job_view.php?job_id=${encodeURIComponent(job.id)}&apply=1" onclick="return window.__idxApply && window.__idxApply(this);"><i class="bi bi-send me-1"></i>Apply Now</button>`
                )
            }
          </div>
        </div>`;

                    // Fade-in animation
                    try {
                        panel.querySelector('.card-body')?.classList.add('fade-in');
                        panel.querySelector('.card-footer')?.classList.add('fade-in');
                    } catch (_) {}

                    // Wire Apply button
                    const applyBtn = panel.querySelector('[data-action="apply"]');
                    if (applyBtn) {
                        applyBtn.addEventListener('click', (e) => {
                            e && e.preventDefault && e.preventDefault();
                            const jid = applyBtn.getAttribute('data-job-id');
                            const jtitle = applyBtn.getAttribute('data-job-title') || 'this job';
                            if (job.applied) {
                                showToast('You have already applied to this job.', 'info');
                                return;
                            }
                            if (HARD_LOCK && CURRENT_USER.loggedIn && CURRENT_USER.role === 'job_seeker' && eligKnown && job.elig_ok === false) {
                                const reasons = Array.isArray(job.elig_reasons) && job.elig_reasons.length ? ('\n- ' + job.elig_reasons.join('\n- ')) : '';
                                showToast('You can\'t apply yet. Please review the job requirements.' + (reasons ? '\n' + reasons : ''), 'warning');
                                return;
                            }
                            openApplyConfirm(jid, jtitle, applyBtn);
                        });
                    }
                }

                function renderSkeleton() {
                    panel.innerHTML = `
        <div class="card-body">
          <div class="skeleton-title skeleton-line mb-2"></div>
          <div class="skeleton-sub skeleton-line w-50 mb-3"></div>
          <div class="skeleton-chip-group mb-2">
            <span class="skeleton-chip"></span>
            <span class="skeleton-chip"></span>
            <span class="skeleton-chip"></span>
          </div>
          <div class="skeleton-line w-75 mb-1"></div>
          <div class="skeleton-line w-100 mb-1"></div>
          <div class="skeleton-line w-90 mb-1"></div>
        </div>
        <div class="card-footer bg-white d-flex gap-2">
          <div class="skeleton-btn"></div>
          <div class="skeleton-btn"></div>
        </div>`;
                }

                function selectItem(el) {
                    // Prefer data-index (stable ordering) to avoid any id parsing issues
                    const idx = parseInt(el.getAttribute('data-index') || '-1', 10);
                    let job = (!Number.isNaN(idx) && idx >= 0 && idx < jobsData.length) ? jobsData[idx] : undefined;
                    if (!job) {
                        const idAttr = el.getAttribute('data-id') || '';
                        job = jobsMap[String(idAttr)];
                    }
                    if (!job) {
                        console && console.warn && console.warn('Job not found for element', el);
                        return;
                    }
                    list.querySelectorAll('.job-item').forEach(a => {
                        a.classList.remove('active');
                        a.setAttribute('aria-selected', 'false');
                    });
                    el.classList.add('active');
                    el.setAttribute('aria-selected', 'true');
                    // Skeleton then render
                    renderSkeleton();
                    setTimeout(() => {
                        renderDetail(job);
                    }, 120);
                    // Reset detail scroll to top so user sees new content start
                    if (rightPane) rightPane.scrollTop = 0;

                    // Update Applied badge visibility for this item based on job.applied
                    const badge = el.querySelector('.applied-badge');
                    if (badge) {
                        if (job && job.applied) {
                            badge.style.display = 'inline-block';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                }

                list?.addEventListener('click', (e) => {
                    const a = e.target.closest('.job-item');
                    if (a) selectItem(a);
                });
                list?.addEventListener('keydown', (e) => {
                    const items = Array.from(list.querySelectorAll('.job-item'));
                    let idx = items.findIndex(x => x.classList.contains('active'));
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        idx = Math.min(items.length - 1, idx + 1);
                        items[idx]?.focus();
                        selectItem(items[idx]);
                    }
                    if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        idx = Math.max(0, idx - 1);
                        items[idx]?.focus();
                        selectItem(items[idx]);
                    }
                    if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                        e.preventDefault();
                        const a = document.activeElement.closest('.job-item');
                        if (a) selectItem(a);
                    }
                });

                // Default: do not auto-select; show placeholder until user clicks an item
            })();
        </script>

        <!-- Apply Confirm Modal -->
        <div class="modal fade" id="applyConfirmModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h5 class="modal-title"><i class="bi bi-send me-2"></i>Apply to this job?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2">You're about to apply to:</div>
                        <div class="fw-semibold" id="applyJobTitle">Job Title</div>
                        <div class="small text-muted mt-2">We'll use your current profile details. You can update your profile anytime.</div>
                        <div class="alert alert-warning small mt-3 d-none" id="applyWarn"></div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">No</button>
                        <button type="button" class="btn btn-primary" id="applyYesBtn"><i class="bi bi-check2 me-1"></i>Yes, apply</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast container -->
        <div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1080;"></div>

        <script>
            // Minimal toast helper
            function showToast(message, type = 'info', delay = 3800) {
                if (!message || !message.trim()) return;
                const c = document.getElementById('toastContainer');
                const iconMap = {
                    success: 'bi-check-circle',
                    danger: 'bi-x-circle',
                    warning: 'bi-exclamation-triangle',
                    info: 'bi-info-circle'
                };
                const el = document.createElement('div');
                el.className = 'toast align-items-center text-bg-' + type + ' border-0 mb-2';
                el.setAttribute('role', 'alert');
                el.setAttribute('aria-live', 'assertive');
                el.setAttribute('aria-atomic', 'true');
                el.innerHTML = '<div class="d-flex">\
      <div class="toast-body"><i class="bi ' + (iconMap[type] || iconMap.info) + ' me-2"></i>' + message + '</div>\
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>\
    </div>';
                c.appendChild(el);
                if (window.bootstrap)(new bootstrap.Toast(el, {
                    delay
                })).show();
            }

            function showActionToast(message, actionLabel, onAction, type = 'success', delay = 4200) {
                if (!message || !message.trim()) return;
                const c = document.getElementById('toastContainer');
                const iconMap = {
                    success: 'bi-check-circle',
                    danger: 'bi-x-circle',
                    warning: 'bi-exclamation-triangle',
                    info: 'bi-info-circle'
                };
                const el = document.createElement('div');
                el.className = 'toast align-items-center text-bg-' + type + ' border-0 mb-2';
                el.setAttribute('role', 'alert');
                el.setAttribute('aria-live', 'assertive');
                el.setAttribute('aria-atomic', 'true');
                el.innerHTML = `
      <div class="d-flex align-items-center">
        <div class="toast-body flex-grow-1"><i class="bi ${iconMap[type]||iconMap.info} me-2"></i>${message}</div>
        <button type="button" class="btn btn-light btn-sm me-2" data-action="toast-action">${actionLabel}</button>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>`;
                c.appendChild(el);
                const t = window.bootstrap ? new bootstrap.Toast(el, {
                    delay
                }) : null;
                if (onAction) {
                    el.querySelector('[data-action="toast-action"]').addEventListener('click', (e) => {
                        e.preventDefault();
                        onAction();
                        t && t.hide();
                    });
                }
                t && t.show();
            }

            let applyCtx = {
                jobId: null,
                jobTitle: '',
                btn: null
            };
            async function quickApply(jobId, btnEl, yesBtnEl) {
                // Set loading states
                let originalYesHtml = null;
                if (yesBtnEl) {
                    originalYesHtml = yesBtnEl.innerHTML;
                    yesBtnEl.disabled = true;
                    yesBtnEl.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';
                }
                let originalBtnHtml = null;
                if (btnEl) {
                    originalBtnHtml = btnEl.innerHTML;
                    btnEl.disabled = true;
                    btnEl.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';
                }
                try {
                    const fd = new FormData();
                    fd.append('action', 'quick_apply');
                    fd.append('job_id', jobId);
                    fd.append('csrf', CURRENT_USER.csrf || '');
                    const r = await fetch('api_apply', {
                        method: 'POST',
                        body: fd,
                        credentials: 'same-origin'
                    });
                    const data = await r.json().catch(() => null);
                    if (!r.ok || !data) throw new Error('Network error');
                    if (data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }
                    if (data.ok) {
                        showActionToast(data.message || 'Application submitted.', 'Undo', async () => {
                            if (!confirm('Cancel this application?')) return;
                            try {
                                const fd2 = new FormData();
                                fd2.append('job_id', jobId);
                                fd2.append('csrf', CURRENT_USER.csrf || '');
                                const r2 = await fetch('api_application_cancel', {
                                    method: 'POST',
                                    body: fd2,
                                    credentials: 'same-origin'
                                });
                                const j2 = await r2.json().catch(() => null);
                                if (!r2.ok || !j2) throw new Error('Network error');
                                if (j2.ok) {
                                    if (btnEl) {
                                        btnEl.classList.remove('btn-success');
                                        btnEl.classList.add('btn-outline-primary');
                                        btnEl.disabled = false;
                                        btnEl.innerHTML = '<i class="bi bi-send me-1"></i>Apply Now';
                                    }
                                    const job = jobsMap[String(jobId)];
                                    if (job) job.applied = false;
                                    const li = document.querySelector('.job-item[data-id="' + CSS.escape(String(jobId)) + '"]');
                                    li && li.querySelector('.applied-badge') && li.querySelector('.applied-badge').remove();
                                    showToast('Application cancelled.', 'success');
                                } else {
                                    showToast(j2.message || 'Failed to cancel.', 'warning');
                                }
                            } catch (e) {
                                showToast('Something went wrong. Please try again.', 'danger');
                            }
                        }, 'success');
                        if (btnEl) {
                            btnEl.classList.remove('btn-outline-primary');
                            btnEl.classList.add('btn-success');
                            btnEl.disabled = true;
                            btnEl.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Applied';
                        }
                        const job = jobsMap[String(jobId)];
                        if (job) job.applied = true;
                    } else {
                        showToast((data && data.message) || 'Unable to apply.', 'warning');
                        if (data && data.reasons && data.reasons.length) {
                            const warnEl = document.getElementById('applyWarn');
                            if (warnEl) {
                                warnEl.textContent = data.reasons.join('\n');
                                warnEl.classList.remove('d-none');
                            }
                        }
                    }
                } catch (e) {
                    showToast('Something went wrong. Please try again.', 'danger');
                } finally {
                    if (yesBtnEl) {
                        yesBtnEl.disabled = false;
                        yesBtnEl.innerHTML = originalYesHtml || '<i class="bi bi-check2 me-1"></i>Yes, apply';
                    }
                    if (btnEl) {
                        const isApplied = btnEl.classList.contains('btn-success');
                        if (!isApplied) {
                            btnEl.disabled = false;
                            btnEl.innerHTML = originalBtnHtml || '<i class="bi bi-send me-1"></i>Apply Now';
                        }
                    }
                }
            }

            function openApplyConfirm(jobId, jobTitle, btnEl) {
                // Not logged in or not job seeker -> redirect to login
                if (!CURRENT_USER.loggedIn || CURRENT_USER.role !== 'job_seeker') {
                    showToast('Please login as Job Seeker to apply.', 'warning', 1800);
                    setTimeout(() => {
                        window.location.href = CURRENT_USER.loginUrl;
                    }, 1500);
                    return;
                }
                applyCtx = {
                    jobId: jobId,
                    jobTitle: jobTitle,
                    btn: btnEl
                };
                const titleEl = document.getElementById('applyJobTitle');
                const warnEl = document.getElementById('applyWarn');
                if (titleEl) titleEl.textContent = jobTitle || 'this job';
                if (warnEl) {
                    warnEl.classList.add('d-none');
                    warnEl.textContent = '';
                }
                const modalEl = document.getElementById('applyConfirmModal');
                if (window.bootstrap && modalEl) {
                    try {
                        if (modalEl.parentElement !== document.body) document.body.appendChild(modalEl);
                    } catch (_) {}
                    const m = new bootstrap.Modal(modalEl);
                    m.show();
                } else if (modalEl) {
                    // Manual modal fallback (no Bootstrap)
                    try {
                        // prepare backdrop
                        const bd = document.createElement('div');
                        bd.className = 'modal-backdrop fade show';
                        bd.style.zIndex = 1990;
                        document.body.appendChild(bd);
                        // show modal
                        document.body.classList.add('modal-open');
                        modalEl.style.display = 'block';
                        modalEl.classList.add('show');
                        modalEl.setAttribute('aria-modal', 'true');
                        modalEl.removeAttribute('aria-hidden');
                        // attach one-time close handlers
                        const closeEls = modalEl.querySelectorAll('[data-bs-dismiss="modal"], .btn-close');
                        const manualClose = () => {
                            try {
                                modalEl.classList.remove('show');
                                modalEl.style.display = 'none';
                                modalEl.setAttribute('aria-hidden', 'true');
                                modalEl.removeAttribute('aria-modal');
                                document.body.classList.remove('modal-open');
                                bd.remove();
                            } catch (_) {}
                        };
                        closeEls.forEach(el => {
                            el.addEventListener('click', manualClose, {
                                once: true
                            });
                        });
                        // store to hide later after submit
                        window.__manualApplyModalClose = manualClose;
                    } catch (_) {
                        // Fallback to native confirm if manual modal fails
                        if (confirm('Apply to "' + (jobTitle || 'this job') + '"?')) {
                            quickApply(jobId, btnEl, null);
                        }
                    }
                }
            }

            // Expose to global for reliability with delegated handlers
            window.openApplyConfirm = openApplyConfirm;

            (function() {
                const yesBtn = document.getElementById('applyYesBtn');
                if (!yesBtn) return;
                yesBtn.addEventListener('click', async function() {
                    if (!applyCtx.jobId) return;
                    await quickApply(applyCtx.jobId, applyCtx.btn, yesBtn);
                    // Hide modal if Bootstrap available
                    const modalEl = document.getElementById('applyConfirmModal');
                    if (window.bootstrap && modalEl) {
                        bootstrap.Modal.getInstance(modalEl)?.hide();
                    } else if (typeof window.__manualApplyModalClose === 'function') {
                        try {
                            window.__manualApplyModalClose();
                        } catch (_) {}
                    }
                });
            })();

            // Explicit global helper so Apply button works even if scoping/binding fails
            window.__idxApply = function(btn) {
                try {
                    const jid = btn.getAttribute('data-job-id');
                    const jtitle = btn.getAttribute('data-job-title') || 'this job';
                    openApplyConfirm(jid, jtitle, btn);
                } catch (e) {
                    // last resort
                    const href = btn.getAttribute('data-href');
                    if (href) window.location.href = href;
                }
                return false;
            };

            // Safety net: delegated handler in case the local panel handler didn't bind
            // Ensures clicking the Apply button in the right panel always opens confirmation
            document.addEventListener('click', function(e) {
                const btn = e.target.closest('#job-detail-panel [data-action="apply"]');
                if (!btn) return;
                e.preventDefault();
                const jid = btn.getAttribute('data-job-id');
                const jtitle = btn.getAttribute('data-job-title') || 'this job';
                openApplyConfirm(jid, jtitle, btn);
            });
        </script>
        <style>
            /* Ensure modal is always above any pane/hero layers */
            .modal {
                z-index: 2000;
            }

            .modal-backdrop {
                z-index: 1990;
            }
        </style>
        <!-- Safety-net: ensure Apply Now opens modal even if other scripts fail to bind -->
        <script>
            (function() {
                function fallbackOpenApply(anchorEl, ev) {
                    try {
                        ev && ev.preventDefault && ev.preventDefault();
                    } catch (_) {}
                    var jid = anchorEl.getAttribute('data-job-id') || '';
                    var jtitle = anchorEl.getAttribute('data-job-title') || 'this job';
                    window.__pendingApply = {
                        id: jid,
                        btn: anchorEl
                    };
                    // Set modal title if present
                    var t = document.getElementById('applyJobTitle');
                    if (t) t.textContent = jtitle;
                    var modalEl = document.getElementById('applyConfirmModal');
                    if (window.bootstrap && modalEl) {
                        try {
                            if (modalEl.parentElement !== document.body) document.body.appendChild(modalEl);
                        } catch (_) {}
                        try {
                            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
                            return;
                        } catch (_) {}
                    }
                    // Fallback: native confirm then navigate
                    if (confirm('Apply to "' + jtitle + '"?')) {
                        var href = anchorEl.getAttribute('href') || anchorEl.getAttribute('data-href');
                        if (href) window.location.href = href;
                    }
                }

                // Global delegated click: if page-specific openApplyConfirm exists, let it handle; else use fallback
                document.addEventListener('click', function(e) {
                    var target = e.target || e.srcElement;
                    if (!target || !target.closest) return;
                    var a = target.closest('#job-detail-panel [data-action="apply"]');
                    if (!a) return;
                    if (typeof window.openApplyConfirm === 'function') return; // page script will handle
                    fallbackOpenApply(a, e);
                });

                // When Yes is clicked in modal but page did not set applyCtx/handler, navigate as a last resort
                document.addEventListener('click', function(e) {
                    var yes = e.target && e.target.closest && e.target.closest('#applyYesBtn');
                    if (!yes) return;
                    try {
                        if (typeof window.applyCtx !== 'undefined' && window.applyCtx && window.applyCtx.jobId) return; // page handler active
                    } catch (_) {}
                    if (window.__pendingApply && window.__pendingApply.btn) {
                        var href = window.__pendingApply.btn.getAttribute('href') || window.__pendingApply.btn.getAttribute('data-href');
                        if (href) {
                            e.preventDefault();
                            window.location.href = href;
                        }
                    }
                });
            })();
        </script>
        <div class="d-flex justify-content-center mt-3">
            <?php if ($page < $pages): ?>
                <?php $qsMore = $_GET;
                $qsMore['p'] = $page + 1;
                $moreUrl = 'index.php?' . http_build_query($qsMore); ?>
                <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars($moreUrl); ?>#job-filters"><i class="bi bi-plus-lg me-1"></i>Load more</a>
            <?php else: ?>
                <span class="text-muted small">End of results</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php else: ?>
    <section class="trusted-employers-section" id="employers">
        <div class="container">
            <div class="section-head mb-3">
                <div class="section-eyebrow">Our Partners</div>
                <h2 class="section-heading">Find your next employer</h2>
                <p class="section-sub">Explore company profiles to find the right workplace for you. Learn about jobs, reviews, company culture, perks and benefits.</p>
            </div>

            <div class="emp-carousel-wrap position-relative">
                <button class="emp-nav btn btn-light btn-sm emp-nav-prev" type="button" aria-label="Scroll left"><i class="bi bi-chevron-left"></i></button>
                <div class="employer-carousel" id="empCarousel" role="list" aria-label="Approved WFH employers">
                    <?php foreach ($companies as $co): ?>
                        <?php
                        $name = trim((string)($co['company_name'] ?? 'Company'));
                        $initial = mb_strtoupper(mb_substr($name, 0, 1));
                        $img = trim((string)($co['profile_picture'] ?? ''));
                        $jobsC = (int)($co['jobs_count'] ?? 0);
                        $label = $name . ' (' . $jobsC . ' job' . ($jobsC === 1 ? '' : 's') . ')';
                        ?>
                        <a role="listitem" class="emp-card" href="company.php?user_id=<?php echo urlencode($co['user_id']); ?>" title="<?php echo htmlspecialchars($label); ?>" aria-label="View company: <?php echo htmlspecialchars($label); ?>">
                            <div class="logo-wrap" aria-hidden="true">
                                <?php if ($img !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($name); ?> logo">
                                <?php else: ?>
                                    <span class="emp-initial"><?php echo htmlspecialchars($initial); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="emp-info">
                                <div class="emp-name" title="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?></div>
                                <div class="emp-jobs"><span class="badge bg-light text-dark border"><?php echo $jobsC; ?> Job<?php echo ($jobsC === 1 ? '' : 's'); ?></span></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <button class="emp-nav btn btn-light btn-sm emp-nav-next" type="button" aria-label="Scroll right"><i class="bi bi-chevron-right"></i></button>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4">
                <?php
                $hasMoreCompanies = ($companiesTotal > (is_array($companies) ? count($companies) : 0));
                $nextCompaniesPage = max(1, (int)($page + 1));
                $seeMoreUrl = 'index.php?p=' . urlencode($nextCompaniesPage) . '#employers';
                ?>
                <div>
                    <?php if ($hasMoreCompanies): ?>
                        <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars($seeMoreUrl); ?>" style="border-width: 2px; padding: 0.6rem 1.5rem; font-weight: 600; border-radius: 50px; transition: all 0.2s ease;">
                            <i class="bi bi-arrow-right-circle me-2"></i>
                            <span>See more employers</span>
                        </a>
                    <?php endif; ?>
                </div>
                <?php if ($companiesTotal > 0): ?>
                    <div class="emp-dots" role="tablist" aria-label="Employer pages">
                        <?php for ($i = 1; $i <= $pages; $i++): ?>
                            <a href="<?php echo 'index.php?p=' . $i . '#employers'; ?>" class="dot <?php echo ($i === $page ? 'active' : ''); ?>" role="tab" aria-selected="<?php echo ($i === $page ? 'true' : 'false'); ?>" aria-label="Go to page <?php echo $i; ?>"></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php if (!$companies): ?>
        <div class="alert alert-secondary">No approved employers with WFH jobs yet.</div>
    <?php endif; ?>
    <style>
        .trusted-employers-section {
            padding: 3.5rem 0 4rem !important;
            margin-bottom: 50px !important;
            background: linear-gradient(180deg, #F9FAFB 0%, #ffffff 100%) !important;
            position: relative !important;
        }

        /* Decorative separator line at bottom */
        .trusted-employers-section::after {
            content: "" !important;
            position: absolute !important;
            bottom: 0 !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            width: 90% !important;
            max-width: 1200px !important;
            height: 1px !important;
            background: linear-gradient(90deg, transparent 0%, rgba(30, 58, 138, 0.15) 20%, rgba(30, 58, 138, 0.25) 50%, rgba(30, 58, 138, 0.15) 80%, transparent 100%) !important;
        }

        @media (max-width: 767.98px) {
            .trusted-employers-section {
                padding: 2.5rem 0 3rem !important;
            }

            .trusted-employers-section::after {
                width: 95% !important;
            }
        }

        /* Header layout: full-width stacked layout */
        .trusted-employers-section .section-head {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 2.5rem;
            max-width: 100%;
        }

        @media (max-width: 767.98px) {
            .trusted-employers-section .section-head {
                margin-bottom: 2rem;
            }
        }

        .trusted-employers-section .section-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            font-size: .75rem;
            letter-spacing: .8px;
            font-weight: 700;
            color: #1E3A8A;
            text-transform: uppercase;
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.08), rgba(20, 184, 166, 0.08));
            padding: .4rem .85rem;
            border-radius: 50px;
            width: max-content;
            border: 1px solid rgba(30, 58, 138, 0.15);
        }

        .trusted-employers-section .section-eyebrow::before {
            content: "★";
            color: #14B8A6;
            font-size: 1rem;
        }

        .trusted-employers-section .section-heading {
            font-size: 1.85rem;
            font-weight: 800;
            letter-spacing: -.02em;
            margin: 0;
            color: #111827;
            line-height: 1.2;
            width: 100%;
        }

        @media (min-width: 768px) {
            .trusted-employers-section .section-heading {
                font-size: 2.25rem;
            }
        }

        @media (min-width: 992px) {
            .trusted-employers-section .section-heading {
                font-size: 2.5rem;
            }
        }

        .trusted-employers-section .section-sub {
            margin: 0;
            width: 100%;
            max-width: 100%;
            color: #111827;
            opacity: 0.75;
            line-height: 1.65;
            font-size: 1rem;
        }

        @media (max-width: 767.98px) {
            .trusted-employers-section .section-sub {
                font-size: 0.95rem;
            }
        }

        .emp-carousel-wrap {
            padding: 16px 48px;
            position: relative;
        }

        @media (max-width: 767.98px) {
            .emp-carousel-wrap {
                padding: 12px 40px;
            }
        }

        .employer-carousel {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            scroll-padding: 0 16px;
            padding-bottom: 12px;
            scrollbar-width: thin;
            scrollbar-color: rgba(30, 58, 138, 0.2) transparent;
        }

        .employer-carousel::-webkit-scrollbar {
            height: 10px;
        }

        .employer-carousel::-webkit-scrollbar-track {
            background: rgba(30, 58, 138, 0.05);
            border-radius: 10px;
        }

        .employer-carousel::-webkit-scrollbar-thumb {
            background: rgba(30, 58, 138, 0.25);
            border-radius: 10px;
            transition: background 0.2s ease;
        }

        .employer-carousel::-webkit-scrollbar-thumb:hover {
            background: rgba(30, 58, 138, 0.4);
        }

        .emp-card {
            flex: 0 0 auto;
            width: 260px;
            background: #ffffff;
            border: 1px solid rgba(30, 58, 138, 0.12);
            border-radius: 18px;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 8px 24px -12px rgba(30, 58, 138, 0.15), 0 4px 12px -8px rgba(20, 184, 166, 0.08);
            scroll-snap-align: start;
            transition: all .25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .emp-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #1E3A8A, #14B8A6);
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .emp-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -16px rgba(30, 58, 138, 0.25), 0 8px 24px -12px rgba(20, 184, 166, 0.15);
            border-color: rgba(20, 184, 166, 0.3);
        }

        .emp-card:hover::before {
            opacity: 1;
        }

        .emp-card .logo-wrap {
            width: 100%;
            aspect-ratio: 1 / 1;
            border-top-left-radius: 18px;
            border-top-right-radius: 18px;
            overflow: hidden;
            background: linear-gradient(135deg, #F9FAFB 0%, #ffffff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: background 0.25s ease;
        }

        .emp-card:hover .logo-wrap {
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.04) 0%, rgba(20, 184, 166, 0.04) 100%);
        }

        .emp-card .logo-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.3s ease;
        }

        .emp-card:hover .logo-wrap img {
            transform: scale(1.05);
        }

        .emp-card .logo-wrap .emp-initial {
            font-weight: 800;
            font-size: 2.5rem;
            color: #1E3A8A;
            opacity: 0.8;
        }

        .emp-info {
            padding: 1.1rem 1.15rem;
            background: #ffffff;
        }

        .emp-name {
            font-weight: 700;
            font-size: 1.05rem;
            letter-spacing: -.01em;
            line-height: 1.3;
            color: #111827;
            display: block;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 0.5rem;
        }

        .emp-jobs {
            margin-top: 0;
        }

        .emp-jobs .badge {
            font-size: .75rem;
            font-weight: 600;
            padding: .4rem .75rem;
            border-radius: 50px;
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.08), rgba(20, 184, 166, 0.08));
            border: 1px solid rgba(30, 58, 138, 0.15);
            color: #1E3A8A;
            transition: all 0.2s ease;
        }

        .emp-card:hover .emp-jobs .badge {
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.12), rgba(20, 184, 166, 0.12));
            border-color: rgba(20, 184, 166, 0.3);
        }

        .emp-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 3;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 24px -12px rgba(30, 58, 138, 0.3), 0 4px 12px -8px rgba(20, 184, 166, 0.15);
            background: #ffffff;
            border: 1px solid rgba(30, 58, 138, 0.15);
            color: #1E3A8A;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .emp-nav:hover {
            background: linear-gradient(135deg, #1E3A8A, #14B8A6);
            color: #ffffff;
            border-color: transparent;
            transform: translateY(-50%) scale(1.08);
            box-shadow: 0 12px 32px -16px rgba(30, 58, 138, 0.4), 0 6px 16px -10px rgba(20, 184, 166, 0.25);
        }

        .emp-nav:focus-visible {
            outline: 3px solid rgba(250, 204, 21, 0.5);
            outline-offset: 3px;
        }

        .emp-nav i {
            font-size: 1.2rem;
        }

        .emp-nav-prev {
            left: 4px;
        }

        .emp-nav-next {
            right: 4px;
        }

        .emp-dots {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .emp-dots .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(30, 58, 138, 0.2);
            display: inline-block;
            transition: all 0.25s ease;
            text-decoration: none;
        }

        .emp-dots .dot:hover {
            background: rgba(20, 184, 166, 0.4);
            transform: scale(1.2);
        }

        .emp-dots .dot.active {
            background: linear-gradient(135deg, #1E3A8A, #14B8A6);
            width: 28px;
            border-radius: 10px;
        }

        @media (min-width: 768px) {
            .emp-card {
                width: 280px;
            }
        }

        @media (min-width: 992px) {
            .emp-card {
                width: 300px;
            }
        }

        @media (max-width: 576px) {
            .emp-carousel-wrap {
                padding: 10px 36px;
            }

            .emp-nav {
                width: 38px;
                height: 38px;
            }

            .emp-nav i {
                font-size: 1.1rem;
            }
        }
    </style>
    <script>
        (function() {
            const wrap = document.querySelector('.emp-carousel-wrap');
            const scroller = document.getElementById('empCarousel');
            const prev = wrap?.querySelector('.emp-nav-prev');
            const next = wrap?.querySelector('.emp-nav-next');
            if (!wrap || !scroller || !prev || !next) return;

            function getStep() {
                const card = scroller.querySelector('.emp-card');
                return card ? (card.getBoundingClientRect().width + 16) : 280;
            }
            prev.addEventListener('click', function() {
                scroller.scrollBy({
                    left: -getStep() * 1.5,
                    behavior: 'smooth'
                });
            });
            next.addEventListener('click', function() {
                scroller.scrollBy({
                    left: getStep() * 1.5,
                    behavior: 'smooth'
                });
            });
        })();
    </script>
    <script>
        // Make related search chips replace the "What" field and submit the form
        document.addEventListener('DOMContentLoaded', function() {
            const related = document.querySelector('[aria-label="Related searches"]');
            const form = document.querySelector('form.filters-condensed');
            const inputWhat = document.getElementById('filter-what');
            if (!related || !form || !inputWhat) return;
            related.addEventListener('click', function(e) {
                const a = e.target.closest('a.btn');
                if (!a) return;
                e.preventDefault();
                const txt = a.textContent.trim().replace(/\s+\d+$/, '').trim(); // strip count badge if present
                inputWhat.value = txt;
                // Reset pagination if present
                if (form.querySelector('[name="p"]')) {
                    form.querySelector('[name="p"]').value = '1';
                }
                form.submit();
            });
        });
    </script>
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
        .landing-hero,
        .features-section,
        .steps-section,
        .testimonials-section,
        .cta-section {
            display: none !important;
        }

        /* Two-pane independent scroll areas */
        .pane-scroll {
            overflow: auto;
        }

        /* Make job items keyboard-focusable styling */
        #job-list .job-item {
            outline: none;
            display: block;
        }

        #job-list .job-item:focus {
            box-shadow: 0 0 0 .2rem rgba(var(--accent-yellow-rgb), .35);
        }

        /* Add comfortable spacing between job items on the left */
        #job-list .list-group-item {
            margin-bottom: .5rem;
            border-radius: .5rem;
            border: 1px solid rgba(0, 0, 0, .2) !important;
            transition: box-shadow .15s ease, transform .15s ease, border-color .15s ease, background-color .15s ease;
        }

        #job-list .list-group-item:hover {
            border-color: rgba(0, 0, 0, .3) !important;
            box-shadow: 0 6px 18px -12px rgba(var(--primary-blue-rgb), .35), 0 4px 12px -8px rgba(0, 0, 0, .08);
            transform: translateY(-1px);
        }

        #job-list .job-item.active {
            border-color: var(--primary-blue) !important;
            background: #EEF3FA;
        }

        #job-list .list-group-item:last-child {
            margin-bottom: 0;
        }

        .badge-match {
            font-size: .72rem;
            letter-spacing: .2px;
        }

        .applied-badge {
            margin-left: .5rem;
            font-size: .7rem;
        }

        /* Ensure good contrast for list text, especially on active (light) background */
        #job-list .job-item {
            opacity: 1;
        }

        #job-list .job-item h5 {
            color: #0b132a;
            font-weight: 600;
            letter-spacing: .2px;
        }

        #job-list .job-item.active h5 {
            color: #0b132a;
        }

        #job-list .job-item p {
            color: #495057;
        }

        #job-list .job-item .small {
            color: #495057;
        }

        #job-list .job-item .text-muted {
            color: #5c677d !important;
        }

        /* Two-card callouts styling */
        .callouts-duo-section {
            padding: 5rem 0 !important;
            margin-bottom: 0 !important;
            background: #ffffff !important;
            position: relative !important;
        }

        /* Decorative accent at top */
        .callouts-duo-section::before {
            content: "" !important;
            position: absolute !important;
            top: 0 !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            width: 80px !important;
            height: 4px !important;
            background: linear-gradient(90deg, #1E3A8A, #14B8A6) !important;
            border-radius: 4px !important;
        }

        @media (min-width: 768px) {
            .callouts-duo-section {
                padding: 6rem 0 !important;
            }

            .callouts-duo-section::before {
                width: 100px !important;
            }
        }

        @media (min-width: 992px) {
            .callouts-duo-section {
                padding: 7rem 0 !important;
            }

            .callouts-duo-section::before {
                width: 120px !important;
                height: 5px !important;
            }
        }

        /* Removed bottom margin on individual cards since section handles spacing */
        .callouts-duo-section .callout-card {
            margin-bottom: 0;
        }

        .callouts-holder {
            background: transparent;
            border: none;
            border-radius: 0;
            padding: 0;
            box-shadow: none;
        }

        @media (min-width: 768px) {
            .callouts-holder {
                padding: 0;
            }
        }

        a.callout-card,
        .callout-card {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
            min-height: 320px;
            border-radius: 20px;
            overflow: hidden;
            text-decoration: none !important;
            color: inherit !important;
            padding: 2rem;
            padding-right: calc(48% + 2rem) !important;
            border: 1px solid rgba(30, 58, 138, 0.12);
            background-repeat: no-repeat;
            background-position: right 1.5rem center;
            background-size: 48% auto;
            box-shadow: 0 8px 24px -12px rgba(30, 58, 138, 0.15), 0 4px 12px -8px rgba(20, 184, 166, 0.08);
            transition: all .3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .callout-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -16px rgba(30, 58, 138, 0.25), 0 8px 24px -12px rgba(20, 184, 166, 0.15);
            border-color: rgba(20, 184, 166, 0.3);
        }

        /* Variants */
        .callout-card.card-left {
            background: linear-gradient(135deg, #1E3A8A 0%, #14B8A6 100%);
            color: #ffffff;
            border-color: rgba(20, 184, 166, 0.2);
        }

        .callout-card.card-left:hover {
            background: linear-gradient(135deg, #1E3A8A 0%, #0D9488 100%);
            border-color: rgba(20, 184, 166, 0.4);
        }

        .callout-card.card-left .callout-title,
        .callout-card.card-left .callout-sub {
            color: #ffffff;
        }

        .callout-card.card-right {
            background: linear-gradient(135deg, #F9FAFB 0%, #ffffff 100%);
            color: #111827;
            border-color: rgba(30, 58, 138, 0.12);
        }

        .callout-card.card-right:hover {
            background: linear-gradient(135deg, #F9FAFB 0%, #F0F9FF 100%);
            border-color: rgba(30, 58, 138, 0.2);
        }

        .callout-card.card-right .callout-title {
            color: #111827;
        }

        .callout-card.card-right .callout-sub {
            color: #111827;
            opacity: 0.75;
        }

        /* Body (left content) */
        .callout-body {
            position: relative;
            z-index: 2;
            max-width: 52%;
        }

        .callout-title {
            font-weight: 800;
            letter-spacing: -.01em;
            font-size: 1.4rem;
            margin: 0 0 0.75rem;
            line-height: 1.25;
        }

        .callout-sub {
            margin: 0 0 1.25rem;
            font-size: 1rem;
            line-height: 1.6;
        }

        .callout-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.75rem;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px -4px rgba(0, 0, 0, 0.25);
            transition: all 0.25s ease;
            border: none;
        }

        .callout-card.card-left .callout-btn {
            background: #ffffff;
            color: #1E3A8A;
        }

        .callout-card.card-left .callout-btn:hover {
            background: #F9FAFB;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px -6px rgba(0, 0, 0, 0.35);
        }

        .callout-card.card-right .callout-btn {
            background: linear-gradient(135deg, #1E3A8A, #14B8A6);
            color: #ffffff;
        }

        .callout-card.card-right .callout-btn:hover {
            background: linear-gradient(135deg, #1E3A8A, #0D9488);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px -6px rgba(30, 58, 138, 0.5);
        }

        /* Add icon animation */
        .callout-btn i {
            transition: transform 0.25s ease;
            margin-left: 0.5rem;
        }

        .callout-card:hover .callout-btn i {
            transform: translateX(4px);
        }

        @media (min-width: 768px) {
            .callout-card {
                min-height: 360px;
                padding: 2.5rem;
                padding-right: calc(48% + 2.5rem) !important;
                background-position: right 2rem center;
                background-size: 48% auto;
            }

            .callout-title {
                font-size: 1.75rem;
                margin-bottom: 1rem;
            }

            .callout-sub {
                font-size: 1.05rem;
                margin-bottom: 1.5rem;
            }

            .callout-btn {
                padding: 0.85rem 2rem;
                font-size: 1.05rem;
            }
        }

        @media (min-width: 992px) {
            .callout-card {
                min-height: 400px;
                padding: 3rem;
                padding-right: calc(50% + 3rem) !important;
                background-size: 50% auto;
            }

            .callout-title {
                font-size: 2rem;
            }

            .callout-sub {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 767.98px) {
            .callout-card {
                min-height: 280px;
                padding: 1.5rem;
                padding-right: calc(45% + 1.5rem) !important;
                background-size: 45% auto;
            }

            .callout-body {
                max-width: 55%;
            }

            .callout-title {
                font-size: 1.2rem;
            }

            .callout-sub {
                font-size: 0.9rem;
            }

            .callout-btn {
                padding: 0.65rem 1.25rem;
                font-size: 0.95rem;
            }
        }

        /* Sticky header in detail panel */
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 5;
            background: #fff;
            padding-top: .25rem;
            padding-bottom: .25rem;
        }

        .sticky-header+hr {
            margin-top: .5rem;
        }

        /* Fade-in for detail content */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(2px);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        #job-detail-panel .fade-in {
            animation: fadeIn .18s ease-in both;
        }

        /* Skeleton styles */
        @keyframes shimmer {
            0% {
                background-position: -450px 0;
            }

            100% {
                background-position: 450px 0;
            }
        }

        .skeleton-line {
            height: 12px;
            border-radius: 6px;
            background: #e9eef5;
            position: relative;
            overflow: hidden;
        }

        .skeleton-line::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, .7) 50%, rgba(255, 255, 255, 0) 100%);
            transform: translateX(-100%);
            animation: shimmer 1s infinite;
        }

        .skeleton-line.w-50 {
            width: 50%;
        }

        .skeleton-line.w-75 {
            width: 75%;
        }

        .skeleton-line.w-90 {
            width: 90%;
        }

        .skeleton-line.w-100 {
            width: 100%;
        }

        .skeleton-title {
            width: 60%;
            height: 18px;
        }

        .skeleton-sub {
            height: 10px;
        }

        .skeleton-chip-group {
            display: flex;
            gap: .4rem;
        }

        .skeleton-chip {
            display: inline-block;
            height: 18px;
            width: 70px;
            border-radius: 999px;
            background: #e9eef5;
            position: relative;
            overflow: hidden;
        }

        .skeleton-chip::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, .7) 50%, rgba(255, 255, 255, 0) 100%);
            transform: translateX(-100%);
            animation: shimmer 1s infinite;
        }

        .skeleton-btn {
            height: 36px;
            width: 140px;
            border-radius: .375rem;
            background: #e9eef5;
            position: relative;
            overflow: hidden;
        }

        .skeleton-btn::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, .7) 50%, rgba(255, 255, 255, 0) 100%);
            transform: translateX(-100%);
            animation: shimmer 1s infinite;
        }
    </style>
<?php endif; ?>

<?php if ($hasFilters): ?>
    <script>
        (function() {
            // Dynamic height for left/right panes so they scroll without affecting the page
            const container = document.getElementById('two-pane');
            const left = document.getElementById('leftPane');
            const right = document.getElementById('rightPane');
            if (!container || !left || !right) return;

            function updateHeights() {
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('search-active');
        });
    </script>
<?php endif; ?>
<script>
    // Reveal items on scroll for elements tagged with data-reveal (purely visual)
    document.addEventListener('DOMContentLoaded', function() {
        if (!('IntersectionObserver' in window)) {
            document.querySelectorAll('[data-reveal]').forEach(el => el.classList.add('reveal-in'));
            return;
        }
        const io = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    e.target.classList.add('reveal-in');
                    io.unobserve(e.target);
                }
            });
        }, {
            threshold: .12
        });
        document.querySelectorAll('[data-reveal]').forEach(el => io.observe(el));
    });
</script>

<!-- CALLOUTS DUO SECTION (replaces features/steps/testimonials) -->
<section class="callouts-duo-section">
    <div class="container">
        <div class="callouts-holder">
            <div class="row g-3 g-md-4 align-items-stretch">
                <div class="col-md-6 d-flex">
                    <a class="callout-card card-left flex-fill" href="<?php echo rtrim(BASE_URL, '/'); ?>/about" style="
            display:flex; align-items:center; text-decoration:none; color:inherit;
            min-height:320px; padding:22px; padding-right:calc(46% + 28px); border-radius:24px; border:1px solid #e7ecf4;
                        background-color:var(--primary-blue); background-image:url('assets/images/hero/pwd_landingpage.jpg');
            background-repeat:no-repeat; background-position:right 24px center; background-size:46% auto;
            box-shadow:0 20px 44px -28px rgba(2,6,23,.16), 0 12px 30px -26px rgba(2,6,23,.10);
          ">
                        <div class="callout-body">
                            <h3 class="callout-title">All about our mission</h3>
                            <p class="callout-sub">Learn what we do and how we support inclusive hiring.</p>
                            <span class="btn btn-light btn-lg fw-semibold callout-btn">Go to About <i class="bi bi-arrow-right"></i></span>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 d-flex">
                    <a class="callout-card card-right flex-fill" href="support_contact.php" style="
            display:flex; align-items:center; text-decoration:none; color:inherit;
            min-height:320px; padding:22px; padding-right:calc(46% + 28px); border-radius:24px; border:1px solid #e7ecf4;
            background-color:#EEF3FA; background-image:url('assets/images/hero/pwd_landingbottom.jpg');
            background-repeat:no-repeat; background-position:right 24px center; background-size:46% auto;
            box-shadow:0 20px 44px -28px rgba(2,6,23,.16), 0 12px 30px -26px rgba(2,6,23,.10);
          ">
                        <div class="callout-body">
                            <h3 class="callout-title">Questions? We can help</h3>
                            <p class="callout-sub">Reach out and we’ll respond as soon as possible.</p>
                            <span class="btn btn-primary btn-lg fw-semibold callout-btn">Contact us <i class="bi bi-arrow-right"></i></span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Steps section removed -->

<!-- Testimonials section removed -->

<!-- CTA SECTION removed per request -->

<?php include 'includes/footer.php'; ?>