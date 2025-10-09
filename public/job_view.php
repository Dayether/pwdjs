<?php
/* job_view.php  (v45 – detailed version)
   Features:
   - Detailed job header w/ inline matching lock notice
   - Skill badges split (general vs specific)
   - Accessibility tags
   - Salary range display
   - Edit form with lock overlay for matching criteria after applicants exist (unless admin)
  - Applicant table (progress bar, match score badge; status changes managed on employer_applicants page)
   - View profile links (job seeker & employer) with ?return= param for back button logic
   - Toast notifications (skips blank flashes)
   - Safe back URL resolution
*/

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/Job.php';
require_once '../classes/Skill.php';
require_once '../classes/Taxonomy.php';
require_once '../classes/User.php';
require_once '../classes/Application.php';
require_once '../classes/Matching.php';
require_once '../classes/Skill.php';

$job_id = $_GET['job_id'] ?? '';
$job = Job::findById($job_id);
if (!$job) {
    include '../includes/header.php';
    include '../includes/nav.php';
    echo '<div class="container py-5"><div class="alert alert-danger">Job not found.</div></div>';
    include '../includes/footer.php';
    exit;
}

// Public visibility gate: only Approved moderated jobs are publicly viewable
$viewerRole    = $_SESSION['role'] ?? '';
$currentUserId = $_SESSION['user_id'] ?? null;
$isOwner = $currentUserId && Helpers::isEmployer() && $job->employer_id === $currentUserId;
$isAdmin = ($viewerRole === 'admin');
if (!$isAdmin && !$isOwner && ($job->moderation_status ?? 'Approved') !== 'Approved') {
  include '../includes/header.php';
  include '../includes/nav.php';
  echo '<div class="container py-5"><div class="alert alert-warning">This job is not available. It may be pending review or was rejected.</div></div>';
  include '../includes/footer.php';
  exit;
}

// variables already defined above

$employer = User::findById($job->employer_id);
$employerStatus = $employer ? ($employer->employer_status ?? 'Pending') : 'Unknown';
$isEmployerSuspended = ($employerStatus === 'Suspended');

$canEditBase       = ($isOwner || $isAdmin);
$canEdit           = $canEditBase && !$isEmployerSuspended;
$canViewApplicants = $canEditBase;
$canActOnApplicants= $canViewApplicants && !$isEmployerSuspended;

/* Back URL resolution order:
   1. Explicit ?return= (internal only)
   2. Session stored last_page (set via Helpers::storeLastPage())
   3. HTTP_REFERER (path only, internal)
   4. Role-based fallback (admin -> admin_reports, employer -> employer_dashboard#jobs, otherwise index)
*/
$explicitReturn = $_GET['return'] ?? '';
$sessionLast    = $_SESSION['last_page'] ?? '';
$httpRefRaw     = $_SERVER['HTTP_REFERER'] ?? '';

$roleFallback = $isAdmin ? 'admin_reports.php'
         : ($isOwner ? 'employer_dashboard.php#jobs'
         : 'index.php');
$backUrl = $roleFallback;

// Helper to validate internal path
$sanitizeBack = function($candidate){
  if (!$candidate) return null;
  $parsed = parse_url($candidate);
  if (isset($parsed['scheme']) || isset($parsed['host'])) return null; // external
  $path = $parsed['path'] ?? '';
  if ($path === '' || str_contains($path,'..')) return null;
  if (!preg_match('~^/?[A-Za-z0-9_./#-]+$~', $path)) return null;
  $safe = ltrim($path,'/');
  if (!empty($parsed['query'])) $safe .= '?' . $parsed['query'];
  if (!empty($parsed['fragment']) && !str_contains($safe,'#')) $safe .= '#' . $parsed['fragment'];
  return $safe;
};

if ($explicitReturn && ($tmp = $sanitizeBack($explicitReturn))) {
  $backUrl = $tmp;
} elseif ($sessionLast && ($tmp = $sanitizeBack($sessionLast))) {
  $backUrl = $tmp;
} elseif ($httpRefRaw && ($tmp = $sanitizeBack($httpRefRaw))) {
  $backUrl = $tmp;
}

$loginApplyRedirect = 'login.php?redirect=' . urlencode('job_view.php?job_id=' . $job->job_id);

// Quick Apply handler (post to same page)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'quick_apply') {
  if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'job_seeker') {
    Helpers::redirect($loginApplyRedirect);
  }
  // Optional CSRF check (best effort)
  if (!Helpers::verifyCsrf($_POST['csrf'] ?? '')) {
    Helpers::flash('error','Invalid or expired form. Please try again.');
    Helpers::redirect('job_view.php?job_id=' . urlencode($job->job_id));
  }

  $actor = User::findById($_SESSION['user_id']);
  if (!$actor) {
    Helpers::flash('error','Unable to load your profile. Please re-login.');
    Helpers::redirect('job_view.php?job_id=' . urlencode($job->job_id));
  }
  // Prevent duplicate
  if (Application::userHasApplied($actor->user_id, $job->job_id)) {
    Helpers::flash('info','You have already applied to this job.');
    Helpers::redirect('job_view.php?job_id=' . urlencode($job->job_id));
  }

  // Eligibility check
  $elig = Matching::canApply($actor, $job);
  if (!$elig['ok'] && Matching::hardLock()) {
    // Show top-of-page reasons
    Helpers::flash('warn','You can\'t apply yet. Please review the requirements below.');
    if (!empty($elig['reasons'])) {
      Helpers::flash('error', implode("\n", $elig['reasons']));
    }
    Helpers::redirect('job_view.php?job_id=' . urlencode($job->job_id));
  }

  // Build defaults from profile for quick apply
  $years = (int)($actor->experience ?? 0);
  $userSkillIds = Matching::userSkillIds($actor->user_id);
  $jobSkillIds  = Matching::jobSkillIds($job->job_id);
  $selectedForApp = array_values(array_intersect(array_map('strval',$userSkillIds), array_map('strval',$jobSkillIds)));
  $appEdu = $actor->education_level ?: ($actor->education ?: '');

  $ok = Application::createWithDetails($actor, $job, $years, $selectedForApp, $appEdu);
  if ($ok) {
    Helpers::flash('msg','Application submitted.');
  } else {
    Helpers::flash('error','Submission failed or you already applied.');
  }
  Helpers::redirect('job_view.php?job_id=' . urlencode($job->job_id));
}

// Precompute matching info if viewer is a job seeker
$matchInfo = null;
if (Helpers::isJobSeeker()) {
  $viewer = User::findById($_SESSION['user_id']);
  if ($viewer) {
    $matchInfo = Matching::canApply($viewer, $job);
  }
}

/* Applicants */
$applicants = [];
if ($canViewApplicants) {
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT a.application_id,
                   a.user_id,
                   a.status,
                   a.match_score,
                   a.relevant_experience,
                   a.application_education,
                   a.created_at,
                   u.name
            FROM applications a
            JOIN users u ON a.user_id = u.user_id
            WHERE a.job_id = ?
            ORDER BY a.match_score DESC, a.created_at ASC
        ");
        $stmt->execute([$job->job_id]);
        $applicants = $stmt->fetchAll();
    } catch (Throwable $e) {
        $applicants = [];
    }
}
$applicantCount   = count($applicants);
$matchingLocked   = ($applicantCount > 0 && !$isAdmin);

/* Soft skills (general) */
$generalSkills = [
    '70+ WPM Typing',
    'Flexible Schedule',
    'Team Player',
    'Professional Attitude',
    'Strong Communication',
    'Adaptable / Quick Learner'
];

$employmentTypes = Taxonomy::employmentTypes();
$accessTags      = Taxonomy::accessibilityTags();
$eduLevels       = Taxonomy::educationLevels();

// Aggregate intended PWD types for this job
$pwdTypes = [];
try {
  $pdoTmp = Database::getConnection();
  $stT = $pdoTmp->prepare("SELECT GROUP_CONCAT(DISTINCT pwd_type ORDER BY pwd_type SEPARATOR ',') FROM job_applicable_pwd_types WHERE job_id = ?");
  $stT->execute([$job->job_id]);
  $agg = (string)$stT->fetchColumn();
  $csv = $agg !== '' ? $agg : (string)($job->applicable_pwd_types ?? '');
  if ($csv !== '') {
    $parts = array_filter(array_map('trim', explode(',', $csv)), fn($v)=>$v!=='');
    $pwdTypes = array_values(array_unique($parts));
  }
} catch (Throwable $e) { $pwdTypes = []; }

/* Split skills */
$jobSkillsRows = Skill::getSkillsForJob($job->job_id);
$allSkillNames = array_column($jobSkillsRows, 'name');

$generalLower     = array_map('mb_strtolower', $generalSkills);
$generalSelected  = [];
$requiredSkills   = [];

foreach ($allSkillNames as $sn) {
    if (in_array(mb_strtolower($sn), $generalLower, true)) {
        $generalSelected[] = $sn;
    } else {
        $requiredSkills[] = $sn;
    }
}

$errors  = [];
$updated = false;
$lockedChangeAttempt = false;

/* Handle Edit POST */
if (!$canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_mode'] ?? '') === 'edit_job') {
    $errors[] = 'Job is locked (employer suspended). Changes not allowed.';
}

if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_mode'] ?? '') === 'edit_job') {
    $selectedGeneral = $_POST['required_skills'] ?? [];
    if (!is_array($selectedGeneral)) $selectedGeneral = [$selectedGeneral];
    $selectedGeneral = array_filter(array_map('trim', $selectedGeneral));

    $typedRequiredRaw  = trim($_POST['additional_skills'] ?? '');
    $typedRequiredList = $typedRequiredRaw !== '' ? Helpers::parseSkillInput($typedRequiredRaw) : [];

    $merged = [];
    foreach (array_merge($selectedGeneral, $typedRequiredList) as $s) {
        if ($s === '') continue;
        $k = mb_strtolower($s);
        if (!isset($merged[$k])) $merged[$k] = $s;
    }
    $skillsCsvSubmitted = implode(', ', $merged);

  $tagsSelected = (array)($_POST['accessibility_tags'] ?? []);

    $title            = trim($_POST['title'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $reqExpSubmitted  = (int)($_POST['required_experience'] ?? 0);
    $reqEduSubmitted  = trim($_POST['required_education'] ?? '');
    $locCity          = trim($_POST['location_city'] ?? '');
    $locRegion        = trim($_POST['location_region'] ?? '');
    $employment       = $_POST['employment_type'] ?? 'Full time';
    $salary_currency  = strtoupper(trim($_POST['salary_currency'] ?? 'PHP'));
    $salary_min       = ($_POST['salary_min'] !== '' ? max(0, (int)$_POST['salary_min']) : null);
    $salary_max       = ($_POST['salary_max'] !== '' ? max(0, (int)$_POST['salary_max']) : null);
    $salary_period    = in_array($_POST['salary_period'] ?? 'monthly', ['monthly','yearly','hourly'], true)
                        ? $_POST['salary_period'] : 'monthly';

    if ($title === '')        $errors[] = 'Title required';
    if ($description === '')  $errors[] = 'Description required';
    if ($salary_min !== null && $salary_max !== null && $salary_min > $salary_max) {
        $errors[] = 'Salary min cannot be greater than salary max.';
    }
    if (!in_array($employment, $employmentTypes, true)) $employment = 'Full time';

    /* Matching lock check */
    if ($matchingLocked) {
        $originalSkillsCsv   = $job->required_skills_input ?? '';
        $originalSkillsNorm  = preg_replace('/\s+/', ' ', mb_strtolower(trim($originalSkillsCsv)));
        $submittedSkillsNorm = preg_replace('/\s+/', ' ', mb_strtolower(trim($skillsCsvSubmitted)));

        if ($originalSkillsNorm !== $submittedSkillsNorm ||
            $job->required_experience != $reqExpSubmitted ||
            (string)($job->required_education ?? '') !== (string)$reqEduSubmitted) {
            $lockedChangeAttempt = true;
        }

        $skillsCsvFinal = $originalSkillsCsv;
        $reqExpFinal    = $job->required_experience;
        $reqEduFinal    = $job->required_education ?? '';
    } else {
        $skillsCsvFinal = $skillsCsvSubmitted;
        $reqExpFinal    = $reqExpSubmitted;
        $reqEduFinal    = $reqEduSubmitted;
    }

    $data = [
        'title'                 => $title,
        'description'           => $description,
        'required_skills_input' => $skillsCsvFinal,
        'required_experience'   => $reqExpFinal,
        'required_education'    => $reqEduFinal,
        'accessibility_tags'    => implode(',', array_map('trim',$tagsSelected)),
        'location_city'         => $locCity,
        'location_region'       => $locRegion,
        'remote_option'         => 'Work From Home',
        'employment_type'       => $employment,
        'salary_currency'       => $salary_currency ?: 'PHP',
        'salary_min'            => $salary_min,
        'salary_max'            => $salary_max,
        'salary_period'         => $salary_period
    ];

    if (!$errors) {
        if (Job::update($job->job_id, $data, $job->employer_id)) {
            $updated = true;
            $job = Job::findById($job->job_id);

            /* Rebuild skill splits */
            $jobSkillsRows = Skill::getSkillsForJob($job->job_id);
            $allSkillNames = array_column($jobSkillsRows, 'name');
            $generalSelected = [];
            $requiredSkills  = [];
            foreach ($allSkillNames as $sn) {
                if (in_array(mb_strtolower($sn), $generalLower, true)) {
                    $generalSelected[] = $sn;
                } else {
                    $requiredSkills[] = $sn;
                }
            }

            if ($canViewApplicants) {
                try {
                    $stmt = $pdo->prepare("
                        SELECT a.application_id,
                               a.user_id,
                               a.status,
                               a.match_score,
                               a.relevant_experience,
                               a.application_education,
                               a.created_at,
                               u.name
                        FROM applications a
                        JOIN users u ON a.user_id = u.user_id
                        WHERE a.job_id = ?
                        ORDER BY a.match_score DESC, a.created_at ASC
                    ");
                    $stmt->execute([$job->job_id]);
                    $applicants = $stmt->fetchAll();
                    $applicantCount = count($applicants);
                    $matchingLocked = ($applicantCount > 0 && !$isAdmin);
                } catch (Throwable $e) {}
            }
        } else {
            $errors[] = 'Update failed.';
        }
    }
}

/* Rebuild display arrays for form */
$requiredSkillsInputValue = implode(', ', $requiredSkills);
$displaySkills            = array_merge($requiredSkills, $generalSelected);

/* Flash consumption -> toasts (skip empty) */
$rawFlash = $_SESSION['flash'] ?? [];
if (isset($_SESSION['flash'])) unset($_SESSION['flash']);
$fMsgs = [
    'success' => (isset($rawFlash['msg'])   && trim($rawFlash['msg'])   !== '') ? $rawFlash['msg']   : null,
    'danger'  => (isset($rawFlash['error']) && trim($rawFlash['error']) !== '') ? $rawFlash['error'] : null,
    'info'    => (isset($rawFlash['info'])  && trim($rawFlash['info'])  !== '') ? $rawFlash['info']  : null,
    'warning' => (isset($rawFlash['warn'])  && trim($rawFlash['warn'])  !== '') ? $rawFlash['warn']  : null,
];

$toasts = [];
foreach ($fMsgs as $type => $msg) {
    if ($msg) {
        $icon = $type==='success' ? 'bi-check-circle'
              : ($type==='danger' ? 'bi-exclamation-triangle'
              : ($type==='warning' ? 'bi-exclamation-triangle' : 'bi-info-circle'));
        $toasts[] = ['type'=>$type,'icon'=>$icon,'message'=>htmlspecialchars($msg)];
    }
}
if ($updated) {
    $toasts[] = [
        'type'=>'success',
        'icon'=>'bi-check2-circle',
        'message'=>($isAdmin && !$isOwner) ? 'Job updated (admin).' : 'Job updated successfully.'
    ];
}
if ($isEmployerSuspended) {
    $toasts[] = [
        'type'=>'warning',
        'icon'=>'bi-lock',
        'message'=>'Employer is suspended. Job is read-only.'
    ];
}
if ($lockedChangeAttempt) {
    $toasts[] = [
        'type'=>'info',
        'icon'=>'bi-info-circle',
        'message'=>'Matching criteria locked (existing applicants). Changes ignored.'
    ];
}
foreach ($errors as $e) {
    if (trim($e) !== '') {
        $toasts[] = ['type'=>'danger','icon'=>'bi-exclamation-triangle','message'=>htmlspecialchars($e)];
    }
}
$toasts = array_values(array_filter($toasts, fn($t)=>isset($t['message']) && trim($t['message'])!==''));

include '../includes/header.php';
include '../includes/nav.php';
?>
<style>
.toast-container { z-index:1080; }
.toast .toast-icon { font-size:1.1rem; }
.badge-readonly { font-size:.7rem; letter-spacing:.5px; }
.field-locked-overlay { position: relative; }
.field-locked-overlay::after {
  content: 'Locked (has applicants)';
  position: absolute; top:-0.55rem; right:.5rem;
  background:#ffc107; color:#212529; font-size:.65rem;
  padding:2px 6px; border-radius:3px; letter-spacing:.5px; font-weight:600;
}
/* Job image styles */
.job-hero {
  width: 100%;
  max-height: 260px;
  object-fit: cover;
  border-radius: .5rem;
  border: 1px solid rgba(0,0,0,.05);
}
.pwd-tags{display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.35rem}
.pwd-tag{display:inline-flex;align-items:center;padding:.25rem .55rem;border-radius:999px;font-size:.7rem;font-weight:700;letter-spacing:.03em;color:#0d6efd;background:#e7f1ff;border:1px solid #b6d3ff}
</style>

<!-- Toasts -->
<div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3">
  <?php foreach ($toasts as $t): $bg='text-bg-'.$t['type']; ?>
    <div class="toast align-items-center border-0 <?php echo $bg; ?> mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4300">
      <div class="d-flex">
        <div class="toast-body">
          <i class="bi <?php echo $t['icon']; ?> toast-icon me-2"></i><?php echo $t['message']; ?>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="container pt-3">
  <div class="d-flex justify-content-between align-items-center mb-3 fade-up fade-delay-1">
    <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-outline-secondary btn-sm" aria-label="Go back" id="jobViewBackBtn">
      <i class="bi bi-arrow-left me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Back</span>
    </a>
  </div>
</div>

<?php if ($isEmployerSuspended): ?>
  <div class="alert alert-warning py-2 d-flex align-items-center mb-3">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <div>Employer account is <strong>Suspended</strong>. This job is locked (view-only).</div>
  </div>
<?php endif; ?>

<div class="container pb-5">
<div class="row g-4">
  <!-- LEFT: Job content + applicants -->
  <div class="col-lg-8 fade-up fade-delay-2">
    <!-- Job Overview Card -->
    <div class="card border-0 shadow-sm mb-3 fade-up fade-delay-2 jobv-card">
      <div class="card-body p-4 jobv-card-inner">
        <div class="job-hero-wrap mb-4 fade-up fade-delay-2">
          <?php if (!empty($job->job_image)): ?>
            <img src="../<?php echo htmlspecialchars($job->job_image); ?>" alt="Image for job <?php echo htmlspecialchars($job->title); ?>">
          <?php else: ?>
            <div class="job-hero-placeholder">
              <i class="bi bi-card-image me-2" aria-hidden="true"></i>
              <span><?php echo htmlspecialchars(strtoupper(substr($job->title,0,1))); ?></span>
            </div>
          <?php endif; ?>
        </div>
        <div class="d-flex justify-content-between align-items-start flex-wrap">
          <div class="me-3">
            <h1 class="h4 fw-bold mb-1 jobv-title gradient-text" style="letter-spacing:-.5px;">
              <?php echo Helpers::sanitizeOutput($job->title); ?>
              <?php if ($isAdmin && !$isOwner): ?>
                <span class="badge text-bg-danger align-middle ms-1">Admin</span>
              <?php endif; ?>
              <?php if ($isEmployerSuspended): ?>
                <span class="badge text-bg-warning align-middle ms-1 badge-readonly">READ-ONLY</span>
              <?php endif; ?>
            </h1>
            <div class="text-muted small">
              Posted: <?php echo htmlspecialchars(date('M d, Y', strtotime($job->created_at))); ?>
              <?php if ($job->location_city || $job->location_region): ?>
                · Office: <?php echo htmlspecialchars(trim($job->location_city . ', ' . $job->location_region, ', ')); ?>
              <?php endif; ?>
              · <?php echo htmlspecialchars($job->employment_type); ?>
            </div>
            <?php if ($pwdTypes): ?>
              <div class="pwd-tags" aria-label="Intended PWD types">
                <?php foreach ($pwdTypes as $pt): ?>
                  <span class="pwd-tag"><?php echo htmlspecialchars($pt); ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php if ($matchingLocked): ?>
              <div class="small mt-1 text-info">
                <i class="bi bi-shield-lock me-1"></i>
                Matching criteria locked (<?php echo $applicantCount; ?> applicant<?php echo $applicantCount>1?'s':''; ?>).
              </div>
            <?php endif; ?>
          </div>
          <?php if (Helpers::isJobSeeker()): ?>
            <div class="mt-2 mt-lg-0 d-flex gap-2">
              <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#reportJobModal">
                <i class="bi bi-flag me-1"></i>Report Job
              </button>
            </div>
          <?php endif; ?>
        </div>

        <!-- Required Skills -->
        <div class="mb-4 mt-3">
          <h2 class="h6 fw-semibold mb-2 text-uppercase small tracking-wide"><i class="bi bi-list-check me-1"></i>Required Skills</h2>
          <?php if ($displaySkills): ?>
            <?php foreach ($displaySkills as $sn): ?>
              <span class="badge text-bg-secondary me-1 mb-1"><?php echo htmlspecialchars($sn); ?></span>
            <?php endforeach; ?>
          <?php else: ?>
            <span class="text-muted small">None specified.</span>
          <?php endif; ?>
        </div>

        <!-- Accessibility -->
        <?php
          $accTags = array_filter(array_map('trim', explode(',', $job->accessibility_tags ?? '')));
          if ($accTags):
        ?>
          <div class="mb-4">
            <h2 class="h6 fw-semibold mb-2 text-uppercase small tracking-wide"><i class="bi bi-universal-access me-1"></i>Accessibility</h2>
            <?php foreach ($accTags as $t): ?>
              <span class="badge text-bg-info me-1 mb-1"><?php echo htmlspecialchars($t); ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Description -->
        <div class="mb-4">
          <h2 class="h6 fw-semibold mb-2 text-uppercase small tracking-wide"><i class="bi bi-file-text me-1"></i>Description</h2>
          <div class="text-body">
            <?php echo nl2br(Helpers::sanitizeOutput($job->description)); ?>
          </div>
        </div>

        <?php if ($matchInfo && isset($matchInfo['score'])): ?>
          <div class="alert alert-<?php echo ($matchInfo['ok'] ? 'success' : 'warning'); ?> d-flex align-items-center">
            <i class="bi <?php echo $matchInfo['ok'] ? 'bi-emoji-smile' : 'bi-info-circle'; ?> me-2"></i>
            <div>
              Match score: <strong><?php echo number_format((float)$matchInfo['score'],2); ?></strong>
              <?php if (!$matchInfo['ok'] && !empty($matchInfo['reasons'])): ?>
                <div class="small mt-1">
                  <?php foreach ($matchInfo['reasons'] as $r): ?>
                    <div>• <?php echo htmlspecialchars($r); ?></div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <?php
                // Build breakdown
                $viewerObj = isset($viewer) ? $viewer : User::findById($_SESSION['user_id']);
                $bd = $viewerObj ? Matching::breakdown($viewerObj, $job) : null;
                $missingNames = [];
                if ($bd && !empty($bd['missing_skill_ids'])) {
                  try {
                    $all = Skill::getSkillsForJob($job->job_id); // id+name
                    $map = [];
                    foreach ($all as $row) { $map[(string)$row['skill_id']] = $row['name']; }
                    foreach ($bd['missing_skill_ids'] as $ms) {
                      $missingNames[] = $map[(string)$ms] ?? ('#'.$ms);
                    }
                  } catch (Throwable $e) { /* ignore */ }
                }
              ?>
              <?php if ($bd): ?>
                <div class="mt-2">
                  <a class="small" data-bs-toggle="collapse" href="#scoreBreakdown" role="button" aria-expanded="false" aria-controls="scoreBreakdown">
                    Why this score?
                  </a>
                  <div class="collapse mt-2" id="scoreBreakdown">
                    <div class="small">
                      <div>Experience: <strong><?php echo number_format($bd['exp'],2); ?>/40</strong> (You: <?php echo (int)$bd['user_years']; ?> yr<?php echo ((int)$bd['user_years']===1?'':'s'); ?>; Req: <?php echo (int)$bd['job_required_years']; ?>)</div>
                      <div>Skills: <strong><?php echo number_format($bd['skills'],2); ?>/40</strong>
                        <?php if ($missingNames): ?>
                          <div class="text-muted">Missing: <?php echo htmlspecialchars(implode(', ', $missingNames)); ?></div>
                        <?php endif; ?>
                      </div>
                      <div>Education: <strong><?php echo number_format($bd['edu'],2); ?>/20</strong> (You: <?php echo htmlspecialchars($bd['user_education'] ?: '—'); ?>; Req: <?php echo htmlspecialchars($bd['job_required_education'] ?: '—'); ?>)</div>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Compensation -->
        <div class="mb-4">
          <h2 class="h6 fw-semibold mb-2 text-uppercase small tracking-wide"><i class="bi bi-cash-stack me-1"></i>Compensation</h2>
          <?php
            if ($job->salary_min !== null || $job->salary_max !== null) {
              $range = '';
              if ($job->salary_min !== null && $job->salary_max !== null) {
                $range = number_format($job->salary_min) . ' - ' . number_format($job->salary_max);
              } elseif ($job->salary_min !== null) {
                $range = number_format($job->salary_min) . '+';
              } elseif ($job->salary_max !== null) {
                $range = 'Up to ' . number_format($job->salary_max);
              }
              echo '<span class="badge text-bg-success">' . htmlspecialchars($job->salary_currency) . ' ' . $range . ' / ' . htmlspecialchars($job->salary_period) . '</span>';
            } else {
              echo '<span class="text-muted small">Not specified</span>';
            }
          ?>
        </div>

        <!-- Apply Buttons -->
        <?php if (!empty($_SESSION['user_id']) && $viewerRole === 'job_seeker' && !$isEmployerSuspended): ?>
          <?php
            $alreadyApplied = false;
            try {
              $pdoTmp = Database::getConnection();
              $chk = $pdoTmp->prepare("SELECT 1 FROM applications WHERE user_id=? AND job_id=? LIMIT 1");
              $chk->execute([$_SESSION['user_id'], $job->job_id]);
              $alreadyApplied = (bool)$chk->fetchColumn();
            } catch (Throwable $e) { $alreadyApplied = false; }
          ?>
          <hr>
          <?php if ($alreadyApplied): ?>
            <div class="alert alert-info mb-0">
              <i class="bi bi-check2-circle me-1"></i>You have already applied to this job.
            </div>
          <?php elseif ($matchInfo && !$matchInfo['ok'] && Matching::hardLock()): ?>
            <div class="alert alert-warning mb-2">
              <i class="bi bi-shield-exclamation me-1"></i>
              You can't apply to this job because your profile doesn't meet the minimum requirements.
              <?php if (!empty($matchInfo['reasons'])): ?>
                <div class="small mt-1">
                  <?php foreach ($matchInfo['reasons'] as $r): ?><div>• <?php echo htmlspecialchars($r); ?></div><?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
            <a class="btn btn-secondary btn-lg disabled" tabindex="-1" aria-disabled="true">
              <i class="bi bi-lock me-1"></i>Apply Disabled
            </a>
          <?php else: ?>
            <button id="applyNowBtn" class="btn btn-primary btn-lg" type="button" data-job-id="<?php echo htmlspecialchars($job->job_id); ?>">
              <i class="bi bi-send me-1"></i>Apply Now
            </button>
          <?php endif; ?>
        <?php elseif (!empty($_SESSION['user_id']) && $viewerRole === 'job_seeker' && $isEmployerSuspended): ?>
          <hr>
          <button class="btn btn-secondary btn-lg" disabled>
            <i class="bi bi-lock me-1"></i>Applications Closed
          </button>
        <?php endif; ?>

        <?php if (empty($_SESSION['user_id']) && !$isEmployerSuspended): ?>
          <hr>
            <a id="guestApplyBtn" class="btn btn-primary btn-lg" href="<?php echo htmlspecialchars($loginApplyRedirect); ?>">
              <i class="bi bi-send me-1"></i>Apply Now
            </a>
            <div class="small text-muted mt-2">
              Create an account or login to complete your application.
            </div>
        <?php elseif (empty($_SESSION['user_id']) && $isEmployerSuspended): ?>
          <hr>
          <button class="btn btn-secondary btn-lg" disabled>
            <i class="bi bi-lock me-1"></i>Applications Closed
          </button>
        <?php endif; ?>

      </div>
    </div>


    <!-- Applicants -->
    <?php if ($canViewApplicants): ?>
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
          <h3 class="h6 fw-semibold mb-3">
            <i class="bi bi-people-fill me-2"></i>Applicants (<?php echo number_format(count($applicants)); ?>)
            <?php if ($isAdmin && !$isOwner): ?>
              <span class="badge text-bg-danger ms-1">Admin View</span>
            <?php endif; ?>
            <?php if ($isEmployerSuspended): ?>
              <span class="badge text-bg-warning ms-1">Locked</span>
            <?php endif; ?>
          </h3>
          <?php if (!$applicants): ?>
            <div class="text-muted small">No applicants yet.</div>
          <?php else: ?>
            <?php if ($isOwner && !$isEmployerSuspended): ?>
              <div class="d-flex justify-content-end mb-2">
                <a class="btn btn-outline-primary btn-sm" href="employer_applicants.php?job_id=<?php echo urlencode($job->job_id); ?>">
                  <i class="bi bi-people"></i> Manage Applicants
                </a>
              </div>
            <?php endif; ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light">
                  <tr>
                    <th style="min-width:160px;">Applicant</th>
                    <th>Match</th>
                    <th>Relevant Exp (yrs)</th>
                    <th>Education</th>
                    <th>Status</th>
                    <th>Applied</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($applicants as $a):
                    $match   = (float)$a['match_score'];
                    $status  = $a['status'];
                    $badgeCls= $status === 'Pending' ? 'secondary' : ($status === 'Approved' ? 'success' : 'danger');
                    $edu     = $a['application_education'] ?: '—';
                  ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?php echo Helpers::sanitizeOutput($a['name']); ?></div>
                        <div class="small text-muted">
                          <a class="text-decoration-none"
                             href="job_seeker_profile.php?user_id=<?php echo urlencode($a['user_id']); ?>&return=<?php echo urlencode('job_view.php?job_id='.$job->job_id); ?>">
                            View profile
                          </a>
                        </div>
                      </td>
                      <td>
                        <div class="d-flex align-items-center" style="min-width:90px;">
                          <div class="progress flex-grow-1 me-2" style="height:6px;">
                            <div class="progress-bar bg-primary"
                                 role="progressbar"
                                 style="width: <?php echo max(0,min(100,$match)); ?>%;"
                                 aria-valuenow="<?php echo (int)$match; ?>"
                                 aria-valuemin="0" aria-valuemax="100"></div>
                          </div>
                          <span class="badge text-bg-primary"><?php echo number_format($match,2); ?></span>
                        </div>
                      </td>
                      <td><?php echo (int)$a['relevant_experience']; ?></td>
                      <td><?php echo Helpers::sanitizeOutput($edu); ?></td>
                      <td><span class="badge text-bg-<?php echo $badgeCls; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                      <td><span class="small text-muted"><?php echo date('M j, Y', strtotime($a['created_at'])); ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- RIGHT: Employer Card -->
  <div class="col-lg-4">
      <div class="card border-0 shadow-sm fade-up fade-delay-4 jobv-card">
      <div class="card-body p-4 jobv-card-inner">
        <h2 class="h6 fw-bold mb-3 text-uppercase small tracking-wide"><i class="bi bi-building me-1"></i>Employer</h2>
        <?php if ($employer): ?>
          <div class="small mb-2">
            <strong><?php echo htmlspecialchars($employer->company_name ?: $employer->name); ?></strong><br>
            <span class="text-muted"><?php echo htmlspecialchars($employer->email); ?></span><br>
            <span class="badge
              <?php
                echo $employerStatus==='Approved' ? 'text-bg-success'
                     : ($employerStatus==='Suspended' ? 'text-bg-warning'
                     : ($employerStatus==='Rejected' ? 'text-bg-danger' : 'text-bg-secondary'));
              ?> mt-2">
              <?php echo htmlspecialchars($employerStatus); ?>
            </span>
          </div>

          <?php if ($viewerRole === 'job_seeker' && !$isOwner): ?>
            <a class="btn btn-outline-primary btn-sm mb-2"
               href="employer_profile.php?user_id=<?php echo urlencode($employer->user_id); ?>&return=<?php echo urlencode('job_view.php?job_id='.$job->job_id); ?>">
               <i class="bi bi-eye me-1"></i>View Profile
            </a>
          <?php endif; ?>

          <?php if ($isOwner && !$isEmployerSuspended): ?>
            <a class="btn btn-outline-secondary btn-sm" href="employer_dashboard.php">My Jobs</a>
          <?php endif; ?>

          <?php if ($isAdmin && !$isOwner && $isEmployerSuspended): ?>
            <div class="mt-2 small text-muted">
              Viewing suspended employer's job (read-only).
            </div>
          <?php elseif ($isAdmin && !$isOwner): ?>
            <div class="mt-2 small text-muted">
              You are viewing this job as <span class="fw-semibold text-danger">Admin</span>.
            </div>
          <?php endif; ?>
        <?php else: ?>
          <p class="text-muted small mb-0">Employer details not available.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
 </div> <!-- /.row -->
</div> <!-- /.container -->

<style>
/* Job View Enhanced Styles */
.jobv-card { border-radius:1rem; overflow:hidden; position:relative; background:#ffffff; border:1px solid #d5e2f2; box-shadow:0 10px 28px -14px rgba(13,110,253,.30),0 6px 18px -8px rgba(0,0,0,.08); }
.jobv-card::before { content:""; position:absolute; inset:0; pointer-events:none; background:linear-gradient(180deg,rgba(255,255,255,.0) 0%,rgba(13,110,253,.04) 120%); }
.jobv-card-inner { position:relative; z-index:1; }
.jobv-title { background:linear-gradient(90deg,#0d6efd,#6636ff); -webkit-background-clip:text; background-clip:text; color:transparent; }
.tracking-wide { letter-spacing:.5px; }
.jobv-card h2.h6 { font-size:.7rem; letter-spacing:.8px; opacity:.78; font-weight:700; }
.jobv-card .badge { font-weight:500; }
/* Hero */
.job-hero-wrap { position:relative; border-radius:.85rem; overflow:hidden; background:linear-gradient(135deg,#d6e7ff,#eef5ff); border:1px solid #c7d9ee; }
.job-hero-wrap img { display:block; width:100%; height:240px; object-fit:cover; }
.job-hero-placeholder { height:240px; display:flex; align-items:center; justify-content:center; font-size:3rem; font-weight:600; color:#fff; background:linear-gradient(135deg,#0d6efd,#6636ff); letter-spacing:2px; }
.job-hero-placeholder i { font-size:2.4rem; }
@media (min-width:992px){ .jobv-title { font-size:1.6rem; } .job-hero-wrap img,.job-hero-placeholder{ height:260px; } }
</style>
<?php include '../includes/footer.php'; ?>
<!-- Apply Confirm Modal (Job View) -->
<?php if (!empty($_SESSION['user_id']) && $viewerRole === 'job_seeker' && !$isEmployerSuspended && (!$alreadyApplied) && !($matchInfo && !$matchInfo['ok'] && Matching::hardLock())): ?>
<div class="modal fade" id="jobViewApplyConfirm" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title"><i class="bi bi-send me-2"></i>Apply to this job?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">You're about to apply to:</div>
        <div class="fw-semibold"><?php echo Helpers::sanitizeOutput($job->title); ?></div>
        <div class="small text-muted mt-2">We'll use your current profile details. You can update your profile anytime.</div>
        <div class="alert alert-warning small mt-3 d-none" id="jvApplyWarn"></div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">No</button>
        <button type="button" class="btn btn-primary" id="jvApplyYes"><i class="bi bi-check2 me-1"></i>Yes, apply</button>
      </div>
    </div>
  </div>
  </div>
<?php endif; ?>
<!-- Report Job Modal -->
<?php if (Helpers::isJobSeeker()): ?>
<div class="modal fade" id="reportJobModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
  <form method="post" action="job_report_submit" class="needs-validation" novalidate>
        <input type="hidden" name="job_id" value="<?php echo htmlspecialchars($job->job_id); ?>">
        <div class="modal-header py-2">
          <h5 class="modal-title"><i class="bi bi-flag me-2"></i>Report Job</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Reason <span class="text-danger">*</span></label>
            <select name="reason" class="form-select form-select-sm" required>
              <option value="">Select a reason</option>
              <?php
                $reasons = ['Spam/Scam','Fake or Misleading','Discriminatory','Inappropriate Content','Other'];
                foreach ($reasons as $r) {
                  echo '<option value="'.htmlspecialchars($r).'">'.htmlspecialchars($r).'</option>';
                }
              ?>
            </select>
            <div class="invalid-feedback">Please choose a reason.</div>
          </div>
          <div class="mb-2">
            <label class="form-label">Details (optional)</label>
            <textarea name="details" class="form-control form-control-sm" rows="4" maxlength="2000" placeholder="Provide additional context (optional)"></textarea>
            <div class="form-text">Max 2000 characters.</div>
          </div>
          <div class="alert alert-info small py-2 mb-0"><i class="bi bi-shield-exclamation me-1"></i> Misuse of reports may lead to account review.</div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-danger btn-sm"><i class="bi bi-flag me-1"></i>Submit Report</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
(()=>{
  const modal=document.getElementById('reportJobModal');
  if(modal){
    modal.addEventListener('shown.bs.modal',()=>{
      const sel=modal.querySelector('select[name=reason]'); if(sel) sel.focus();
    });
    // Bootstrap validation
    const form=modal.querySelector('form');
    form.addEventListener('submit',e=>{
      if(!form.checkValidity()){
        e.preventDefault(); e.stopPropagation();
      }
      form.classList.add('was-validated');
    });
  }
})();
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Show toasts
  if (window.bootstrap) {
    document.querySelectorAll('#toastContainer .toast').forEach(function(el){
      (new bootstrap.Toast(el)).show();
    });
  }

  // Guest apply flow
  const guestBtn = document.getElementById('guestApplyBtn');
  if (guestBtn) {
    guestBtn.addEventListener('click', function(e){
      e.preventDefault();
      showDynamicToast('You need to login to apply for this job.', 'warning', 1800);
      setTimeout(()=>{ window.location.href = this.href; }, 1700);
    });
  }

  // Job View Apply flow (confirmation + API)
  const applyBtn = document.getElementById('applyNowBtn');
  if (applyBtn) {
    const jobId = applyBtn.getAttribute('data-job-id');
    const csrf = <?php echo json_encode(Helpers::csrfToken()); ?>;
    function doQuickApply(btnEl, yesBtnEl){
      let origYes = null, origBtn = null;
      if (yesBtnEl) { origYes = yesBtnEl.innerHTML; yesBtnEl.disabled = true; yesBtnEl.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…'; }
      if (btnEl) { origBtn = btnEl.innerHTML; btnEl.disabled = true; btnEl.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…'; }
      (async () => {
        try {
          const fd = new FormData(); fd.append('action','quick_apply'); fd.append('job_id', jobId); fd.append('csrf', csrf);
          // Use extensionless path to avoid any servers that canonicalize .php URLs
          const r = await fetch('api_apply', { method:'POST', body: fd, credentials:'same-origin' });
          let data = null;
          try { data = await r.json(); } catch(_) { data = null; }
          // Handle redirect even if HTTP status is non-2xx
          if (data && data.redirect) { window.location.href = data.redirect; return; }
          if (!data) throw new Error('Network error');
          if (data.ok) {
            showDynamicToast(data.message || 'Application submitted.', 'success', 3800);
            if (btnEl) { btnEl.classList.remove('btn-primary'); btnEl.classList.add('btn-success'); btnEl.disabled = true; btnEl.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Applied'; }
          } else {
            showDynamicToast(data.message || 'Unable to apply.', 'warning', 3800);
            if (data.reasons && data.reasons.length) {
              const warn = document.getElementById('jvApplyWarn');
              if (warn) { warn.textContent = data.reasons.join('\n'); warn.classList.remove('d-none'); }
            }
          }
        } catch (e) {
          showDynamicToast('Something went wrong. Please try again.', 'danger', 3800);
        } finally {
          if (yesBtnEl) { yesBtnEl.disabled = false; yesBtnEl.innerHTML = origYes || '<i class="bi bi-check2 me-1"></i>Yes, apply'; }
          if (btnEl && !btnEl.classList.contains('btn-success')) { btnEl.disabled = false; btnEl.innerHTML = origBtn || '<i class="bi bi-send me-1"></i>Apply Now'; }
        }
      })();
    }

    function openConfirm(){
      const modalEl = document.getElementById('jobViewApplyConfirm');
      if (window.bootstrap && modalEl) {
        const m = new bootstrap.Modal(modalEl); m.show();
        const yes = document.getElementById('jvApplyYes');
        if (yes) {
          const handler = () => { doQuickApply(applyBtn, yes); bootstrap.Modal.getInstance(modalEl)?.hide(); yes.removeEventListener('click', handler); };
          yes.addEventListener('click', handler);
        }
      } else {
        if (confirm('Apply to this job?')) { doQuickApply(applyBtn, null); }
      }
    }

    applyBtn.addEventListener('click', openConfirm);

    // If navigated with ?apply=1, auto-open confirmation for smoother flow from outside Apply
    try {
      const usp = new URLSearchParams(window.location.search);
      if (usp.get('apply') === '1') {
        // Delay slightly to ensure Bootstrap modal is ready
        setTimeout(openConfirm, 150);
      }
    } catch (e) { /* no-op */ }
  }
});

/* Dynamic toast helper */
function showDynamicToast(message, type='info', delay=4000) {
  if (!message || !message.trim()) return;
  const container = document.getElementById('toastContainer') || createToastContainer();
  const iconMap = {
    success: 'bi-check-circle', danger: 'bi-x-circle',
    warning: 'bi-exclamation-triangle', info: 'bi-info-circle',
    secondary: 'bi-info-circle', lock: 'bi-lock'
  };
  const icon = iconMap[type] || iconMap.info;
  const toastEl = document.createElement('div');
  toastEl.className = 'toast align-items-center text-bg-' + type + ' border-0 mb-2';
  toastEl.setAttribute('role','alert');
  toastEl.setAttribute('aria-live','assertive');
  toastEl.setAttribute('aria-atomic','true');
  toastEl.innerHTML = `
    <div class="d-flex">
      <div class="toast-body"><i class="bi ${icon} me-2"></i>${message}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>`;
  container.appendChild(toastEl);
  if (window.bootstrap) (new bootstrap.Toast(toastEl,{delay})).show();
}

function createToastContainer() {
  const c = document.createElement('div');
  c.id = 'toastContainer';
  c.className = 'toast-container position-fixed top-0 end-0 p-3';
  c.style.zIndex = 1080;
  document.body.appendChild(c);
  return c;
}

</script>
</script>