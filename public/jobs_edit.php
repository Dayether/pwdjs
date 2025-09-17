<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/Job.php';
require_once '../classes/Taxonomy.php';
require_once '../classes/Skill.php';
require_once '../classes/User.php';

Helpers::requireLogin();
if (!Helpers::isEmployer()) Helpers::redirect('index.php');

$employmentTypes = Taxonomy::employmentTypes();
$eduLevels = Taxonomy::educationLevels();
$allowedSkills = Taxonomy::allowedSkills();

$job_id = $_GET['job_id'] ?? '';
$job = Job::findById($job_id);
if (!$job || $job->employer_id !== $_SESSION['user_id']) {
  Helpers::redirect('employer_dashboard.php');
}

$errors = [];
if (isset($_GET['delete']) && $_GET['delete'] == '1') {
  if (Job::delete($job_id, $_SESSION['user_id'])) {
    Helpers::flash('msg','Job deleted.');
  }
  Helpers::redirect('employer_dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Read POST safely
  $title       = trim($_POST['title'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $reqExp      = (int)($_POST['required_experience'] ?? 0);
  $reqEduRaw   = trim($_POST['required_education'] ?? '');
  $employment  = $_POST['employment_type'] ?? 'Full time';
  $locCity     = trim($_POST['location_city'] ?? '');
  $locRegion   = trim($_POST['location_region'] ?? '');
  $cur         = strtoupper(trim($_POST['salary_currency'] ?? 'PHP'));
  $smin        = (isset($_POST['salary_min']) && $_POST['salary_min'] !== '') ? max(0, (int)$_POST['salary_min']) : null;
  $smax        = (isset($_POST['salary_max']) && $_POST['salary_max'] !== '') ? max(0, (int)$_POST['salary_max']) : null;
  $period      = $_POST['salary_period'] ?? 'monthly';

  $skillsSelected = $_POST['required_skills'] ?? [];
  if (!is_array($skillsSelected)) $skillsSelected = [$skillsSelected];
  $skillsCsv = implode(', ', array_map('trim', $skillsSelected));

  $tagsSelected = (array)($_POST['accessibility_tags'] ?? []);
  if (!in_array('PWD-Friendly', $tagsSelected, true)) $tagsSelected[] = 'PWD-Friendly';

  // Validate
  if ($title === '') $errors[] = 'Title required';
  if ($description === '') $errors[] = 'Description required';
  if ($smin !== null && $smax !== null && $smin > $smax) {
    $errors[] = 'Salary min cannot be greater than salary max.';
  }
  if (!in_array($employment, $employmentTypes, true)) $employment = 'Full time';
  if (!in_array($period, ['monthly','yearly','hourly'], true)) $period = 'monthly';

  // Payload
  $data = [
    'title' => $title,
    'description' => $description,
    'required_skills_input' => $skillsCsv,
    'required_experience' => $reqExp,
    'required_education' => $reqEduRaw,
    'accessibility_tags' => implode(',', array_map('trim', $tagsSelected)),
    'location_city' => $locCity,
    'location_region' => $locRegion,
    'remote_option' => 'Work From Home',
    'employment_type' => $employment,
    'salary_currency' => $cur ?: 'PHP',
    'salary_min' => $smin,
    'salary_max' => $smax,
    'salary_period' => $period,
  ];

  if (!$errors) {
    // Correct order: (job_id, data, employer_id)
    if (Job::update($job_id, $data, $_SESSION['user_id'])) {
      Helpers::flash('msg','Job updated.');
      Helpers::redirect('jobs_edit.php?job_id=' . urlencode($job_id));
    } else {
      $errors[] = 'Update failed.';
    }
  }
}

include '../includes/header.php';
include '../includes/nav.php';

// Form defaults from DB (null-safe)
$employmentType = $job->employment_type ?? 'Full time';
$city  = $job->location_city   ?? '';
$region= $job->location_region ?? '';
$cur   = $job->salary_currency ?? 'PHP';
$smin  = property_exists($job, 'salary_min') ? $job->salary_min : null;
$smax  = property_exists($job, 'salary_max') ? $job->salary_max : null;
$period= $job->salary_period   ?? 'monthly';

$selectedSkills = array_map('trim', explode(',', $job->required_skills_input ?? ''));
$selectedTags = array_filter(array_map('trim', explode(',', $job->accessibility_tags ?? '')));
if (!in_array('PWD-Friendly', $selectedTags, true)) $selectedTags[] = 'PWD-Friendly';
?>
<div class="card border-0 shadow-sm">
  <div class="card-body p-4">
    <h2 class="h5 fw-semibold mb-3"><i class="bi bi-pencil-square me-2"></i>Edit Job</h2>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($e); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endforeach; ?>

    <form id="job-edit" method="post" class="row g-3">
      <div class="col-12">
        <label class="form-label">Title</label>
        <input name="title" class="form-control form-control-lg" required value="<?php echo Helpers::sanitizeOutput($job->title); ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Employment type</label>
        <select name="employment_type" class="form-select">
          <?php foreach ($employmentTypes as $t): ?>
            <option value="<?php echo htmlspecialchars($t); ?>" <?php if ($employmentType === $t) echo 'selected'; ?>><?php echo htmlspecialchars($t); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Original office location (optional)</label>
        <div class="d-flex gap-2">
          <input name="location_city" class="form-control" value="<?php echo Helpers::sanitizeOutput($city); ?>" placeholder="City">
          <input name="location_region" class="form-control" value="<?php echo Helpers::sanitizeOutput($region); ?>" placeholder="Region/Province">
        </div>
      </div>

      <div class="col-md-4">
        <label class="form-label">Salary currency</label>
        <input name="salary_currency" class="form-control" value="<?php echo Helpers::sanitizeOutput($cur); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Salary min</label>
        <input name="salary_min" type="number" min="0" class="form-control" value="<?php echo htmlspecialchars($smin ?? ''); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Salary max</label>
        <input name="salary_max" type="number" min="0" class="form-control" value="<?php echo htmlspecialchars($smax ?? ''); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Salary period</label>
        <select name="salary_period" class="form-select">
          <?php foreach (['monthly','yearly','hourly'] as $p): ?>
            <option value="<?php echo $p; ?>" <?php if (($period ?? 'monthly') === $p) echo 'selected'; ?>>
              <?php echo ucfirst($p); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-8">
        <label class="form-label">Required Skills</label>
        <select name="required_skills[]" class="form-select" multiple size="8">
          <?php foreach ($allowedSkills as $s): ?>
            <option value="<?php echo htmlspecialchars($s); ?>" <?php if (in_array($s, $selectedSkills, true)) echo 'selected'; ?>>
              <?php echo htmlspecialchars($s); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">Experience (years)</label>
        <input name="required_experience" type="number" min="0" class="form-control" value="<?php echo htmlspecialchars($job->required_experience ?? 0); ?>">
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
        <?php foreach (Taxonomy::accessibilityTags() as $tag): ?>
          <div class="form-check form-check-inline">
            <input class="form-check-input" name="accessibility_tags[]" type="checkbox" value="<?php echo $tag; ?>" <?php if (in_array($tag, $selectedTags, true)) echo 'checked'; ?>>
            <label class="form-check-label"><?php echo $tag; ?></label>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="col-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="8" required><?php echo Helpers::sanitizeOutput($job->description); ?></textarea>
      </div>

      <div class="col-12 d-flex gap-2">
        <button type="submit" form="job-edit" class="btn btn-primary">
          <i class="bi bi-check2-circle me-1"></i>Save
        </button>
        <a class="btn btn-outline-secondary" href="employer_dashboard.php">Back</a>
        <a class="btn btn-outline-danger ms-auto" href="jobs_edit.php?delete=1&job_id=<?php echo urlencode($job->job_id); ?>" onclick="return confirm('Delete job?')"><i class="bi bi-trash me-1"></i>Delete</a>
      </div>
    </form>
  </div>
</div>
<?php include '../includes/footer.php'; ?>