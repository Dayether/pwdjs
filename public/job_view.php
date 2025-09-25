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

$job_id = $_GET['job_id'] ?? '';
$job = Job::findById($job_id);
if (!$job) {
    include '../includes/header.php';
    include '../includes/nav.php';
    echo '<div class="container py-5"><div class="alert alert-danger">Job not found.</div></div>';
    include '../includes/footer.php';
    exit;
}

$currentUserId = $_SESSION['user_id'] ?? null;
$viewerRole    = $_SESSION['role'] ?? '';
$isOwner = $currentUserId && Helpers::isEmployer() && $job->employer_id === $currentUserId;
$isAdmin = ($viewerRole === 'admin');

$employer = User::findById($job->employer_id);
$employerStatus = $employer ? ($employer->employer_status ?? 'Pending') : 'Unknown';
$isEmployerSuspended = ($employerStatus === 'Suspended');

$canEditBase       = ($isOwner || $isAdmin);
$canEdit           = $canEditBase && !$isEmployerSuspended;
$canViewApplicants = $canEditBase;
$canActOnApplicants= $canViewApplicants && !$isEmployerSuspended;

/* Back URL: allow ?return= or HTTP_REFERER but keep internal only */
$rawReturn = $_GET['return'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
$defaultFallback = $isAdmin ? 'admin_reports.php' : 'index.php';
$backUrl = $defaultFallback;
if ($rawReturn) {
    $parsed = parse_url($rawReturn);
    $internal = !(isset($parsed['scheme']) || isset($parsed['host']));
    $path = $parsed['path'] ?? '';
    if ($internal && $path !== '' && strpos($path, '..') === false) {
        $backUrl = ltrim($path, '/');
        if (!empty($parsed['query'])) {
            $backUrl .= '?' . $parsed['query'];
        }
    }
}

$loginApplyRedirect = 'login.php?redirect=' . urlencode('job_view.php?job_id=' . $job->job_id);

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
#editFormCard { display:none; }
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

<div class="d-flex justify-content-between align-items-center mb-3">
  <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
</div>

<?php if ($isEmployerSuspended): ?>
  <div class="alert alert-warning py-2 d-flex align-items-center mb-3">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <div>Employer account is <strong>Suspended</strong>. This job is locked (view-only).</div>
  </div>
<?php endif; ?>

<div class="row">
  <!-- LEFT: Job content + applicants -->
  <div class="col-lg-8">
    <!-- Job Overview Card -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body p-4">
        <?php if (!empty($job->job_image)): ?>
          <div class="mb-3">
            <img class="job-hero" src="../<?php echo htmlspecialchars($job->job_image); ?>" alt="Job image">
          </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between align-items-start flex-wrap">
          <div class="me-3">
            <h1 class="h4 fw-semibold mb-1">
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
            <?php if ($matchingLocked): ?>
              <div class="small mt-1 text-info">
                <i class="bi bi-shield-lock me-1"></i>
                Matching criteria locked (<?php echo $applicantCount; ?> applicant<?php echo $applicantCount>1?'s':''; ?>).
              </div>
            <?php endif; ?>
          </div>
          <?php if ($canEdit): ?>
            <div>
              <button id="toggleEditBtn" class="btn btn-outline-secondary btn-sm mt-2 mt-lg-0">
                <i class="bi bi-pencil-square me-1"></i>Edit Job
              </button>
            </div>
          <?php elseif (Helpers::isJobSeeker()): ?>
            <div class="mt-2 mt-lg-0 d-flex gap-2">
              <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#reportJobModal">
                <i class="bi bi-flag me-1"></i>Report Job
              </button>
            </div>
          <?php endif; ?>
        </div>

        <!-- Required Skills -->
        <div class="mb-3 mt-3">
          <h6 class="fw-semibold mb-2"><i class="bi bi-list-check me-1"></i>Required Skills</h6>
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
          <div class="mb-3">
            <h6 class="fw-semibold mb-2"><i class="bi bi-universal-access me-1"></i>Accessibility</h6>
            <?php foreach ($accTags as $t): ?>
              <span class="badge text-bg-info me-1 mb-1"><?php echo htmlspecialchars($t); ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Description -->
        <div class="mb-3">
          <h6 class="fw-semibold mb-2"><i class="bi bi-file-text me-1"></i>Description</h6>
          <div class="text-body">
            <?php echo nl2br(Helpers::sanitizeOutput($job->description)); ?>
          </div>
        </div>

        <!-- Compensation -->
        <div class="mb-3">
          <h6 class="fw-semibold mb-2"><i class="bi bi-cash-stack me-1"></i>Compensation</h6>
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
          <?php else: ?>
            <a class="btn btn-primary btn-lg" href="job_apply.php?job_id=<?php echo urlencode($job->job_id); ?>">
              <i class="bi bi-send me-1"></i>Apply Now
            </a>
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

    <!-- Edit Form Card -->
    <?php if ($canEdit): ?>
      <div class="card border-0 shadow-sm mb-4" id="editFormCard">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
          <h5 class="mb-0">
            <i class="bi bi-pencil-square me-2"></i>Edit Job
            <?php if ($isAdmin && !$isOwner): ?>
              <span class="badge text-bg-danger ms-1">Admin Override</span>
            <?php endif; ?>
            <?php if ($matchingLocked && !$isAdmin): ?>
              <span class="badge text-bg-warning ms-1">Matching Criteria Locked</span>
            <?php endif; ?>
          </h5>
          <button type="button" id="closeEditBtn" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="card-body">
          <?php if ($matchingLocked && !$isAdmin): ?>
            <div class="alert alert-info py-2 small mb-3">
              <i class="bi bi-info-circle me-1"></i>
              This job already has <?php echo $applicantCount; ?> applicant<?php echo $applicantCount>1?'s':''; ?>. Core matching fields are locked.
            </div>
          <?php endif; ?>
          <form method="post" class="row g-3">
            <input type="hidden" name="form_mode" value="edit_job">
            <div class="col-12">
              <label class="form-label fw-semibold">Title</label>
              <input name="title" class="form-control" required value="<?php echo htmlspecialchars($job->title); ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label">Employment type</label>
              <select name="employment_type" class="form-select">
                <?php foreach ($employmentTypes as $t): ?>
                  <option value="<?php echo htmlspecialchars($t); ?>" <?php if ($job->employment_type === $t) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($t); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Office Location (optional)</label>
              <div class="d-flex gap-2">
                <input name="location_city"   class="form-control" placeholder="City"            value="<?php echo htmlspecialchars($job->location_city); ?>">
                <input name="location_region" class="form-control" placeholder="Region/Province" value="<?php echo htmlspecialchars($job->location_region); ?>">
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label">Salary currency</label>
              <input name="salary_currency" class="form-control" value="<?php echo htmlspecialchars($job->salary_currency); ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Salary min</label>
              <input name="salary_min" type="number" min="0" class="form-control" value="<?php echo htmlspecialchars($job->salary_min ?? ''); ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Salary max</label>
              <input name="salary_max" type="number" min="0" class="form-control" value="<?php echo htmlspecialchars($job->salary_max ?? ''); ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Salary period</label>
              <select name="salary_period" class="form-select">
                <?php foreach (['monthly','yearly','hourly'] as $p): ?>
                  <option value="<?php echo $p; ?>" <?php if ($job->salary_period === $p) echo 'selected'; ?>>
                    <?php echo ucfirst($p); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-8 <?php echo ($matchingLocked && !$isAdmin)?'field-locked-overlay':''; ?>">
              <label class="form-label">General Skills</label>
              <div class="row">
                <?php foreach ($generalSkills as $gs):
                  $checked = in_array($gs, $generalSelected, true) ? 'checked' : ''; ?>
                  <div class="col-sm-6 col-lg-4">
                    <div class="form-check">
                      <input class="form-check-input"
                             type="checkbox"
                             id="genskill_<?php echo md5($gs); ?>"
                             name="required_skills[]"
                             value="<?php echo htmlspecialchars($gs); ?>"
                             <?php echo $checked; ?>
                             <?php echo ($matchingLocked && !$isAdmin)?'disabled':''; ?>>
                      <label class="form-check-label small" for="genskill_<?php echo md5($gs); ?>">
                        <?php echo htmlspecialchars($gs); ?>
                      </label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <small class="text-muted d-block mt-1">Select general / soft capabilities.</small>

              <label class="form-label mt-3">Required Skills (comma separated)</label>
              <input
                 type="text"
                 name="additional_skills"
                 class="form-control"
                 value="<?php echo htmlspecialchars($requiredSkillsInputValue); ?>"
                 placeholder="e.g., Calendar Management, Data Entry"
                 <?php echo ($matchingLocked && !$isAdmin)?'disabled':''; ?>>
              <small class="text-muted">Specific technical or role-focused requirements.</small>
            </div>

            <div class="col-md-4 <?php echo ($matchingLocked && !$isAdmin)?'field-locked-overlay':''; ?>">
              <label class="form-label">Experience (years)</label>
              <input name="required_experience"
                     type="number"
                     min="0"
                     class="form-control"
                     value="<?php echo htmlspecialchars($job->required_experience); ?>"
                     <?php echo ($matchingLocked && !$isAdmin)?'disabled':''; ?>>
              <label class="form-label mt-3">Education Requirement</label>
              <select name="required_education"
                      class="form-select"
                      <?php echo ($matchingLocked && !$isAdmin)?'disabled':''; ?>>
                <option value="">Any</option>
                <?php foreach ($eduLevels as $lvl): ?>
                  <option value="<?php echo htmlspecialchars($lvl); ?>" <?php if (($job->required_education ?? '') === $lvl) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($lvl); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label d-block">Accessibility Tags</label>
              <?php
                $currentTags = array_filter(array_map('trim', explode(',', $job->accessibility_tags ?? '')));
              ?>
              <?php foreach ($accessTags as $tag): ?>
                <div class="form-check form-check-inline">
                  <input class="form-check-input"
                         name="accessibility_tags[]"
                         type="checkbox"
                         value="<?php echo htmlspecialchars($tag); ?>"
                         <?php echo in_array($tag, $currentTags, true) ? 'checked' : ''; ?>>
                  <label class="form-check-label"><?php echo htmlspecialchars($tag); ?></label>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">Description</label>
              <textarea name="description" class="form-control" rows="7" required><?php echo htmlspecialchars($job->description); ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
              <button class="btn btn-primary">
                <i class="bi bi-save me-1"></i><?php echo $isAdmin && !$isOwner ? 'Save (Admin)' : 'Save Changes'; ?>
              </button>
              <button type="button" class="btn btn-outline-secondary" id="cancelEditBtn">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    <?php endif; ?>

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
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h5 class="fw-semibold mb-3"><i class="bi bi-building me-1"></i>Employer</h5>
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
</div>

<?php include '../includes/footer.php'; ?>
<!-- Report Job Modal -->
<?php if (Helpers::isJobSeeker()): ?>
<div class="modal fade" id="reportJobModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="job_report_submit.php" class="needs-validation" novalidate>
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

<?php if ($canEdit): ?>
(function() {
  const btn      = document.getElementById('toggleEditBtn');
  const card     = document.getElementById('editFormCard');
  const closeBtn = document.getElementById('closeEditBtn');
  const cancelBtn= document.getElementById('cancelEditBtn');

  function showEdit() {
    card.style.display='block';
    window.scrollTo({top:card.offsetTop - 60, behavior:'smooth'});
  }
  function hideEdit() { card.style.display='none'; }

  btn?.addEventListener('click', e => {
    e.preventDefault();
    (card.style.display === 'block') ? hideEdit() : showEdit();
  });
  closeBtn?.addEventListener('click', hideEdit);
  cancelBtn?.addEventListener('click', hideEdit);

  <?php if ($errors): ?>showEdit();<?php endif; ?>
})();
<?php endif; ?>
</script>