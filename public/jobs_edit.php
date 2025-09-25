<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/Job.php';
require_once '../classes/Skill.php';
require_once '../classes/Taxonomy.php';
require_once '../classes/User.php';

Helpers::requireLogin();
Helpers::requireRole('employer');

$job_id = $_GET['job_id'] ?? '';
$job = Job::findById($job_id);
if (!$job || $job->employer_id !== $_SESSION['user_id']) {
    Helpers::redirect('employer_dashboard.php');
}

$allowedSkills   = Taxonomy::allowedSkills();
$employmentTypes = Taxonomy::employmentTypes();
$accessTags      = Taxonomy::accessibilityTags();
$eduLevels       = Taxonomy::educationLevels();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Predefined skills
    $skillsSelected = $_POST['required_skills'] ?? [];
    if (!is_array($skillsSelected)) $skillsSelected = [$skillsSelected];
    $skillsSelected = array_filter(array_map('trim', $skillsSelected));

    // Custom skills (comma separated)
    $additionalSkillsRaw = trim($_POST['additional_skills'] ?? '');
    $extraTokens = $additionalSkillsRaw !== '' ? Helpers::parseSkillInput($additionalSkillsRaw) : [];

    // Merge (case-insensitive unique)
    $merged = [];
    foreach (array_merge($skillsSelected, $extraTokens) as $s) {
        if ($s === '') continue;
        $k = mb_strtolower($s);
        if (!isset($merged[$k])) $merged[$k] = $s;
    }
    $skillsCsv = implode(', ', $merged);

    // Accessibility tags
  $tagsSelected = (array)($_POST['accessibility_tags'] ?? []);

    // Other fields (unchanged structure)
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $reqExp      = (int)($_POST['required_experience'] ?? 0);
    $reqEduRaw   = trim($_POST['required_education'] ?? '');
    $locCity     = trim($_POST['location_city'] ?? '');
    $locRegion   = trim($_POST['location_region'] ?? '');
    $employment  = $_POST['employment_type'] ?? 'Full time';
    $salary_currency = strtoupper(trim($_POST['salary_currency'] ?? 'PHP'));
    $salary_min  = ($_POST['salary_min'] !== '') ? max(0, (int)$_POST['salary_min']) : null;
    $salary_max  = ($_POST['salary_max'] !== '') ? max(0, (int)$_POST['salary_max']) : null;
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
        if (Job::update($job_id, $data, $_SESSION['user_id'])) {
            Helpers::flash('msg','Job updated.');
            Helpers::redirect('jobs_edit.php?job_id=' . urlencode($job_id));
        } else {
            $errors[] = 'Update failed.';
        }
    }
}

$job = Job::findById($job_id);

// Split existing skills between predefined + custom
$rawTokens = array_filter(array_map('trim', explode(',', $job->required_skills_input ?? '')));
$allowedLower = array_map('mb_strtolower', $allowedSkills);
$selectedAllowed = [];
$customSkills = [];
foreach ($rawTokens as $tok) {
    if ($tok === '') continue;
    if (in_array(mb_strtolower($tok), $allowedLower, true)) {
        $selectedAllowed[] = $tok;
    } else {
        $customSkills[] = $tok;
    }
}
$customSkillsCsv = implode(', ', $customSkills);

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="card border-0 shadow-sm">
  <div class="card-body p-4">
    <h2 class="h5 fw-semibold mb-3"><i class="bi bi-pencil-square me-2"></i>Edit Job</h2>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>

    <?php if ($msg = ($_SESSION['flash']['msg'] ?? null)): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
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
        <label class="form-label">Required Skills (predefined)</label>
        <div class="row">
          <?php foreach ($allowedSkills as $skill):
            $checked = in_array($skill, $selectedAllowed, true) ? 'checked' : ''; ?>
            <div class="col-md-4">
              <div class="form-check">
                <input class="form-check-input" type="checkbox"
                       id="skill_<?php echo md5($skill); ?>"
                       name="required_skills[]"
                       value="<?php echo htmlspecialchars($skill); ?>"
                       <?php echo $checked; ?>>
                <label class="form-check-label small" for="skill_<?php echo md5($skill); ?>">
                  <?php echo htmlspecialchars($skill); ?>
                </label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <small class="text-muted d-block mt-1">Check any that apply.</small>

        <label class="form-label mt-3">Custom / Additional Skills (comma separated)</label>
        <input type="text" name="additional_skills" class="form-control"
               value="<?php echo htmlspecialchars($customSkillsCsv); ?>"
               placeholder="e.g., Figma, Accessibility Auditing">
        <small class="text-muted">These will also show to applicants.</small>
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
        <textarea name="description" class="form-control" rows="8" required><?php echo htmlspecialchars($job->description); ?></textarea>
      </div>

      <div class="col-12 d-grid">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php include '../includes/footer.php'; ?>