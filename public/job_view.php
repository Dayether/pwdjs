<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/Job.php';
require_once '../classes/Skill.php';
require_once '../classes/Taxonomy.php';
require_once '../classes/User.php';
require_once '../classes/Application.php'; // Application handling

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
$isOwner = $currentUserId && Helpers::isEmployer() && $job->employer_id === $currentUserId;
$isAdmin = (($_SESSION['role'] ?? '') === 'admin');

$employer = User::findById($job->employer_id);
$employerStatus = $employer ? ($employer->employer_status ?? 'Pending') : 'Unknown';

// Lock condition: suspended employer => read-only job for everyone (admin included)
$isEmployerSuspended = ($employerStatus === 'Suspended');
// If you also want Pending or Rejected to lock jobs, extend like:
// $isEmployerSuspended = in_array($employerStatus, ['Suspended','Rejected','Pending'], true);

$canEditBase = ($isOwner || $isAdmin);
$canEdit = $canEditBase && !$isEmployerSuspended;              // editing allowed?
$canViewApplicants = $canEditBase;                             // can still view applicants list
$canActOnApplicants = $canViewApplicants && !$isEmployerSuspended; // actions allowed?

// Build safe back URL
$rawReturn = $_GET['return'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
$defaultFallback = $isAdmin ? 'admin_reports.php' : 'index.php';
$backUrl = $defaultFallback;
if ($rawReturn) {
    $parsed = parse_url($rawReturn);
    $isSafe = !(isset($parsed['scheme']) || isset($parsed['host']));
    $path = $parsed['path'] ?? '';
    if ($isSafe && $path !== '' && strpos($path, '..') === false) {
        $backUrl = ltrim($path, '/');
        if (!empty($parsed['query'])) {
            $backUrl .= '?' . $parsed['query'];
        }
    }
}

// Redirect target for guests clicking Apply
$loginApplyRedirect = 'login.php?redirect=' . urlencode('job_view.php?job_id=' . $job->job_id);

// Fetch applicants (viewers with privileges)
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
            JOIN users u ON u.user_id = a.user_id
            WHERE a.job_id = ?
            ORDER BY a.match_score DESC, a.created_at ASC
        ");
        $stmt->execute([$job->job_id]);
        $applicants = $stmt->fetchAll();
    } catch (Throwable $e) {
        $applicants = [];
    }
}

/* General (soft) skills fixed list */
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

// All job skills from mapping
$jobSkillsRows = Skill::getSkillsForJob($job->job_id);
$allSkillNames = array_column($jobSkillsRows, 'name');

$generalLower = array_map('mb_strtolower', $generalSkills);
$generalSelected = [];
$requiredSkills  = [];
foreach ($allSkillNames as $sn) {
    if (in_array(mb_strtolower($sn), $generalLower, true)) {
        $generalSelected[] = $sn;
    } else {
        $requiredSkills[] = $sn;
    }
}

$errors  = [];
$updated = false;

// Attempted edit while locked -> ignore but record message
if (!$canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_mode'] ?? '') === 'edit_job') {
    $errors[] = 'Job is locked (employer suspended). Changes not allowed.';
}

if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_mode'] ?? '') === 'edit_job') {
    // Selected general
    $selectedGeneral = $_POST['required_skills'] ?? [];
    if (!is_array($selectedGeneral)) $selectedGeneral = [$selectedGeneral];
    $selectedGeneral = array_filter(array_map('trim', $selectedGeneral));

    // Required (custom)
    $typedRequiredRaw = trim($_POST['additional_skills'] ?? '');
    $typedRequiredList = $typedRequiredRaw !== '' ? Helpers::parseSkillInput($typedRequiredRaw) : [];

    $merged = [];
    foreach (array_merge($selectedGeneral, $typedRequiredList) as $s) {
        if ($s === '') continue;
        $k = mb_strtolower($s);
        if (!isset($merged[$k])) $merged[$k] = $s;
    }
    $skillsCsv = implode(', ', $merged);

    $tagsSelected = (array)($_POST['accessibility_tags'] ?? []);
    if (!in_array('PWD-Friendly', $tagsSelected, true)) $tagsSelected[] = 'PWD-Friendly';

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $reqExp      = (int)($_POST['required_experience'] ?? 0);
    $reqEduRaw   = trim($_POST['required_education'] ?? '');
    $locCity     = trim($_POST['location_city'] ?? '');
    $locRegion   = trim($_POST['location_region'] ?? '');
    $employment  = $_POST['employment_type'] ?? 'Full time';
    $salary_currency = strtoupper(trim($_POST['salary_currency'] ?? 'PHP'));
    $salary_min  = ($_POST['salary_min'] !== '' ? max(0, (int)$_POST['salary_min']) : null);
    $salary_max  = ($_POST['salary_max'] !== '' ? max(0, (int)$_POST['salary_max']) : null);
    $salary_period = in_array($_POST['salary_period'] ?? 'monthly', ['monthly','yearly','hourly'], true)
        ? $_POST['salary_period'] : 'monthly';

    if ($title === '') $errors[] = 'Title required';
    if ($description === '') $errors[] = 'Description required';
    if ($salary_min !== null && $salary_max !== null && $salary_min > $salary_max) {
        $errors[] = 'Salary min cannot be greater than salary max.';
    }
    if (!in_array($employment, $employmentTypes, true)) $employment = 'Full time';

    $data = [
        'title' => $title,
        'description' => $description,
        'required_skills_input' => $skillsCsv,
        'required_experience' => $reqExp,
        'required_education' => $reqEduRaw,
        'accessibility_tags' => implode(',', array_map('trim',$tagsSelected)),
        'location_city' => $locCity,
        'location_region' => $locRegion,
        'remote_option' => 'Work From Home',
        'employment_type' => $employment,
        'salary_currency' => $salary_currency ?: 'PHP',
        'salary_min' => $salary_min,
        'salary_max' => $salary_max,
        'salary_period' => $salary_period
    ];

    if (!$errors) {
        if (Job::update($job->job_id, $data, $job->employer_id)) {
            $updated = true;
            $job = Job::findById($job->job_id);
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
                        JOIN users u ON u.user_id = a.user_id
                        WHERE a.job_id = ?
                        ORDER BY a.match_score DESC, a.created_at ASC
                    ");
                    $stmt->execute([$job->job_id]);
                    $applicants = $stmt->fetchAll();
                } catch (Throwable $e) { /* ignore */ }
            }
        } else {
            $errors[] = 'Update failed.';
        }
    }
}

$requiredSkillsInputValue = implode(', ', $requiredSkills);
$displaySkills = array_merge($requiredSkills, $generalSelected);

// Toasts
$toasts = [];
if ($updated) {
    $toasts[] = [
        'type' => 'success',
        'icon' => 'bi-check2-circle',
        'message' => ($isAdmin && !$isOwner ? 'Job updated (admin).' : 'Job updated successfully.')
    ];
}
if ($isEmployerSuspended) {
    $toasts[] = [
        'type' => 'warning',
        'icon' => 'bi-lock',
        'message' => 'Employer is suspended. Job is read-only.'
    ];
}
foreach ($errors as $e) {
    $toasts[] = ['type' => 'danger', 'icon' => 'bi-exclamation-triangle', 'message' => htmlspecialchars($e)];
}

include '../includes/header.php';
include '../includes/nav.php';
?>
<style>
#editFormCard { display:none; }
.toast-container { z-index:1080; }
.toast .toast-icon { font-size:1.1rem; }
.badge-readonly { font-size:.7rem; letter-spacing:.5px; }
</style>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3">
  <?php foreach ($toasts as $t): $bg='text-bg-'.$t['type']; ?>
    <div class="toast align-items-center border-0 <?php echo $bg; ?> mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4500">
      <div class="d-flex">
        <div class="toast-body">
          <i class="bi <?php echo $t['icon']; ?> toast-icon me-2"></i><?php echo $t['message']; ?>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Back Button Row -->
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>
</div>

<?php if ($isEmployerSuspended): ?>
  <div class="alert alert-warning py-2 d-flex align-items-center mb-3">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <div>
      Employer account is <strong>Suspended</strong>. This job is locked (view-only). No changes or application actions are allowed.
    </div>
  </div>
<?php endif; ?>

<div class="row">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body p-4">
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
          </div>
          <?php if ($canEdit): ?>
            <div>
              <button id="toggleEditBtn" class="btn btn-outline-secondary btn-sm mt-2 mt-lg-0">
                <i class="bi bi-pencil-square me-1"></i>Edit Job
              </button>
            </div>
          <?php endif; ?>
        </div>

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

        <div class="mb-3">
          <h6 class="fw-semibold mb-2"><i class="bi bi-file-text me-1"></i>Description</h6>
          <div class="text-body">
            <?php echo nl2br(Helpers::sanitizeOutput($job->description)); ?>
          </div>
        </div>

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

        <?php if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'job_seeker' && !$isEmployerSuspended): ?>
          <hr>
          <a class="btn btn-primary btn-lg" href="job_apply.php?job_id=<?php echo urlencode($job->job_id); ?>">
            <i class="bi bi-send me-1"></i>Apply Now
          </a>
        <?php elseif (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'job_seeker' && $isEmployerSuspended): ?>
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

    <?php if ($canEdit): ?>
    <!-- Edit form only if allowed -->
    <div class="card border-0 shadow-sm mb-4" id="editFormCard">
      <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0">
          <i class="bi bi-pencil-square me-2"></i>
          Edit Job
          <?php if ($isAdmin && !$isOwner): ?>
            <span class="badge text-bg-danger ms-1">Admin Override</span>
          <?php endif; ?>
        </h5>
        <button type="button" id="closeEditBtn" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="card-body">
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
              <input name="location_city" class="form-control" placeholder="City" value="<?php echo htmlspecialchars($job->location_city); ?>">
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
                <option value="<?php echo $p; ?>" <?php if ($job->salary_period === $p) echo 'selected'; ?>><?php echo ucfirst($p); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-8">
            <label class="form-label">General Skills</label>
            <div class="row">
              <?php foreach ($generalSkills as $gs):
                $checked = in_array($gs, $generalSelected, true) ? 'checked' : ''; ?>
                <div class="col-sm-6 col-lg-4">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox"
                           id="genskill_<?php echo md5($gs); ?>"
                           name="required_skills[]"
                           value="<?php echo htmlspecialchars($gs); ?>"
                           <?php echo $checked; ?>>
                    <label class="form-check-label small" for="genskill_<?php echo md5($gs); ?>">
                      <?php echo htmlspecialchars($gs); ?>
                    </label>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <small class="text-muted d-block mt-1">Select general / soft capabilities.</small>

            <label class="form-label mt-3">Required Skills (comma separated)</label>
            <input type="text" name="additional_skills" class="form-control"
                   value="<?php echo htmlspecialchars($requiredSkillsInputValue); ?>"
                   placeholder="e.g., Calendar Management, Data Entry, Customer Support">
            <small class="text-muted">Specific technical or role-focused requirements.</small>
          </div>

          <div class="col-md-4">
            <label class="form-label">Experience (years)</label>
            <input name="required_experience" type="number" min="0" class="form-control" value="<?php echo htmlspecialchars($job->required_experience); ?>">
            <label class="form-label mt-3">Education Requirement</label>
            <select name="required_education" class="form-select">
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
              if (!in_array('PWD-Friendly', $currentTags, true)) $currentTags[] = 'PWD-Friendly';
            ?>
            <?php foreach ($accessTags as $tag): ?>
              <div class="form-check form-check-inline">
                <input class="form-check-input" name="accessibility_tags[]" type="checkbox"
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

    <?php if ($canViewApplicants): ?>
    <!-- Applicants Section (read-only if suspended) -->
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
                  <th style="width:130px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($applicants as $a):
                  $match = (float)$a['match_score'];
                  $status = $a['status'];
                  $badgeCls = $status === 'Pending' ? 'secondary' : ($status === 'Approved' ? 'success' : 'danger');
                  $edu = $a['application_education'] ?: '—';
                ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?php echo Helpers::sanitizeOutput($a['name']); ?></div>
                      <div class="small text-muted">
                        <a class="text-decoration-none"
                           href="job_seeker_profile.php?user_id=<?php echo urlencode($a['user_id']); ?>" target="_blank">
                          View profile <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                      </div>
                    </td>
                    <td>
                      <div class="d-flex align-items-center" style="min-width:90px;">
                        <div class="progress flex-grow-1 me-2" style="height:6px;">
                          <div class="progress-bar bg-primary"
                               role="progressbar"
                               style="width: <?php echo max(0,min(100,$match)); ?>%;"
                               aria-valuenow="<?php echo (int)$match; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <span class="badge text-bg-primary"><?php echo number_format($match,2); ?></span>
                      </div>
                    </td>
                    <td><?php echo (int)$a['relevant_experience']; ?></td>
                    <td><?php echo Helpers::sanitizeOutput($edu); ?></td>
                    <td>
                      <span class="badge text-bg-<?php echo $badgeCls; ?>">
                        <?php echo htmlspecialchars($status); ?>
                      </span>
                    </td>
                    <td>
                      <span class="small text-muted">
                        <?php echo date('M j, Y', strtotime($a['created_at'])); ?>
                      </span>
                    </td>
                    <td>
                      <?php if ($canActOnApplicants): ?>
                        <div class="btn-group btn-group-sm" role="group">
                          <?php if ($status !== 'Approved'): ?>
                            <a class="btn btn-outline-success"
                               href="applications.php?action=approve&application_id=<?php echo urlencode($a['application_id']); ?>"
                               onclick="return confirm('Approve this application?');"
                               title="Approve">
                              <i class="bi bi-check2-circle"></i>
                            </a>
                          <?php endif; ?>
                          <?php if ($status !== 'Declined'): ?>
                            <a class="btn btn-outline-danger"
                               href="applications.php?action=decline&application_id=<?php echo urlencode($a['application_id']); ?>"
                               onclick="return confirm('Decline this application?');"
                               title="Decline">
                              <i class="bi bi-x-circle"></i>
                            </a>
                          <?php endif; ?>
                        </div>
                      <?php else: ?>
                        <span class="text-muted small">Locked</span>
                      <?php endif; ?>
                    </td>
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
                echo $employerStatus==='Approved'?'text-bg-success':
                     ($employerStatus==='Suspended'?'text-bg-warning':
                     ($employerStatus==='Rejected'?'text-bg-danger':'text-bg-secondary'));
              ?>
            mt-2"><?php echo htmlspecialchars($employerStatus); ?></span>
          </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
  if (window.bootstrap) {
    document.querySelectorAll('#toastContainer .toast').forEach(function(el){
      const t = new bootstrap.Toast(el);
      t.show();
    });
  }

  const guestBtn = document.getElementById('guestApplyBtn');
  if (guestBtn) {
    guestBtn.addEventListener('click', function(e){
      e.preventDefault();
      showDynamicToast('You need to login to apply for this job.', 'warning', 1800);
      setTimeout(()=>{ window.location.href = this.href; }, 1700);
    });
  }
});

function showDynamicToast(message, type='info', delay=4000) {
  const container = document.getElementById('toastContainer') || createToastContainer();
  const iconMap = {
    success: 'bi-check-circle',
    danger: 'bi-x-circle',
    warning: 'bi-exclamation-triangle',
    info: 'bi-info-circle',
    secondary: 'bi-info-circle',
    lock: 'bi-lock'
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
  if (window.bootstrap) {
    const t = new bootstrap.Toast(toastEl, {delay: delay});
    t.show();
  }
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
(function(){
  const btn = document.getElementById('toggleEditBtn');
  const card = document.getElementById('editFormCard');
  const closeBtn = document.getElementById('closeEditBtn');
  const cancelBtn = document.getElementById('cancelEditBtn');
  function showEdit() { card.style.display='block'; window.scrollTo({top:card.offsetTop-60,behavior:'smooth'}); }
  function hideEdit() { card.style.display='none'; }
  btn?.addEventListener('click', e => { e.preventDefault(); card.style.display==='block'?hideEdit():showEdit(); });
  closeBtn?.addEventListener('click', hideEdit);
  cancelBtn?.addEventListener('click', hideEdit);
  <?php if ($errors): ?>showEdit();<?php endif; ?>
})();
<?php endif; ?>
</script>