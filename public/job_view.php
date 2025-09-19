<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/Job.php';
require_once '../classes/Skill.php';
require_once '../classes/Taxonomy.php';
require_once '../classes/User.php';

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

$employer = User::findById($job->employer_id);

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

if ($isOwner && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_mode'] ?? '') === 'edit_job') {
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

            // re-split
            $generalSelected = [];
            $requiredSkills  = [];
            foreach ($allSkillNames as $sn) {
                if (in_array(mb_strtolower($sn), $generalLower, true)) {
                    $generalSelected[] = $sn;
                } else {
                    $requiredSkills[] = $sn;
                }
            }
        } else {
            $errors[] = 'Update failed.';
        }
    }
}

$requiredSkillsInputValue = implode(', ', $requiredSkills);
$displaySkills = array_merge($requiredSkills, $generalSelected); // show both

include '../includes/header.php';
include '../includes/nav.php';
?>
<style>
#editFormCard { display:none; }
</style>
<div class="row">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h1 class="h4 fw-semibold mb-1"><?php echo Helpers::sanitizeOutput($job->title); ?></h1>
            <div class="text-muted small">
              Posted: <?php echo htmlspecialchars(date('M d, Y', strtotime($job->created_at))); ?>
              <?php if ($job->location_city || $job->location_region): ?>
                · Office: <?php echo htmlspecialchars(trim($job->location_city . ', ' . $job->location_region, ', ')); ?>
              <?php endif; ?>
              · <?php echo htmlspecialchars($job->employment_type); ?>
            </div>
          </div>
          <?php if ($isOwner): ?>
            <div class="ms-3">
              <button id="toggleEditBtn" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-pencil-square me-1"></i>Edit Job
              </button>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($updated): ?>
          <div class="alert alert-success mt-3 py-2 mb-3">
            <i class="bi bi-check2-circle me-1"></i>Job updated.
          </div>
        <?php endif; ?>

        <?php foreach ($errors as $e): ?>
          <div class="alert alert-danger mt-3 py-2 mb-3">
            <i class="bi bi-exclamation-triangle me-1"></i><?php echo htmlspecialchars($e); ?>
          </div>
        <?php endforeach; ?>

        <div class="mb-3">
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

        <?php if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'job_seeker'): ?>
          <hr>
          <a class="btn btn-primary btn-lg" href="job_apply.php?job_id=<?php echo urlencode($job->job_id); ?>">
            <i class="bi bi-send me-1"></i>Apply Now
          </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($isOwner): ?>
    <div class="card border-0 shadow-sm mb-4" id="editFormCard">
      <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Job</h5>
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
                <i class="bi bi-save me-1"></i>Save Changes
              </button>
              <button type="button" class="btn btn-outline-secondary" id="cancelEditBtn">Cancel</button>
            </div>
        </form>
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
            <span class="text-muted"><?php echo htmlspecialchars($employer->email); ?></span>
          </div>
          <a class="btn btn-outline-secondary btn-sm" href="employer_dashboard.php">My Jobs</a>
        <?php else: ?>
          <p class="text-muted small mb-0">Employer details not available.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<?php if ($isOwner): ?>
<script>
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
</script>
<?php endif; ?>