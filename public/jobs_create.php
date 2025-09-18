<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/Job.php';
require_once '../classes/Skill.php';
require_once '../classes/Taxonomy.php';
require_once '../classes/User.php';

Helpers::requireLogin();
if (!Helpers::isEmployer()) Helpers::redirect('index.php');

$eduLevels = Taxonomy::educationLevels();
$allowedSkills = Taxonomy::allowedSkills();
$employmentTypes = Taxonomy::employmentTypes();
$accessTags = Taxonomy::accessibilityTags();

$me = User::findById($_SESSION['user_id']);
$status = $me->employer_status ?: 'Pending';

$errors = [];
if ($status !== 'Approved') {
    $errors[] = 'Your employer account is ' . htmlspecialchars($status) . '. You can post jobs only after an admin approves your account.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors) {
    // Collect standardized skills + custom additions
    $skillsSelected = $_POST['required_skills'] ?? [];
    if (!is_array($skillsSelected)) $skillsSelected = [$skillsSelected];

    $additionalSkills = trim($_POST['additional_skills'] ?? '');
    $extras = [];
    if ($additionalSkills !== '') {
        $extras = array_filter(array_map('trim', explode(',', $additionalSkills)), fn($s) => $s !== '');
    }

    // Merge and normalize unique skill tokens
    $skillsAll = array_unique(array_map('trim', array_merge($skillsSelected, $extras)));
    $skillsCsv = implode(', ', $skillsAll);

    $tagsSelected = (array)($_POST['accessibility_tags'] ?? []);
    if (!in_array('PWD-Friendly', $tagsSelected, true)) $tagsSelected[] = 'PWD-Friendly';

    $data = [
        'title' => trim($_POST['title']),
        'description' => trim($_POST['description']),
        'required_skills_input' => $skillsCsv,
        'required_experience' => (int)($_POST['required_experience'] ?? 0),
        'required_education' => trim($_POST['required_education'] ?? ''),
        'accessibility_tags' => implode(',', array_map('trim', $tagsSelected)),
        // WFH-only enforcement
        'location_city' => trim($_POST['location_city'] ?? ''),
        'location_region' => trim($_POST['location_region'] ?? ''),
        'remote_option' => 'Work From Home',
        'employment_type' => in_array($_POST['employment_type'] ?? '', $employmentTypes, true) ? $_POST['employment_type'] : 'Full time',
        'salary_currency' => strtoupper(trim($_POST['salary_currency'] ?? 'PHP')),
        'salary_min' => ($_POST['salary_min'] !== '') ? max(0, (int)$_POST['salary_min']) : null,
        'salary_max' => ($_POST['salary_max'] !== '') ? max(0, (int)$_POST['salary_max']) : null,
        'salary_period' => in_array($_POST['salary_period'] ?? 'monthly', ['monthly','yearly','hourly'], true) ? $_POST['salary_period'] : 'monthly',
    ];

    if (!$data['title']) $errors[] = 'Title required';
    if (!$data['description']) $errors[] = 'Description required';
    if (($data['salary_min'] !== null && $data['salary_max'] !== null) && $data['salary_min'] > $data['salary_max']) {
        $errors[] = 'Salary min cannot be greater than salary max.';
    }

    if (!$errors) {
        if (Job::create($data, $_SESSION['user_id'])) {
            Helpers::flash('msg','Job created.');
            Helpers::redirect('employer_dashboard.php');
        } else {
            $errors[] = 'Create failed.';
        }
    }
}

include '../includes/header.php';
include '../includes/nav.php';
?>
<style>
/* Bold labels and thicker borders for required inputs/boxes */
label.required, .form-label.required { font-weight: 700; }
.form-control[required], .form-select[required], textarea[required] {
  border-width: 2px !important;
}
.required .asterisk::after {
  content: " *";
  color: #dc3545; /* bootstrap danger */
  font-weight: 700;
}
</style>

<div class="card border-0 shadow-sm">
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h2 class="h5 fw-semibold mb-0"><i class="bi bi-plus-lg me-2"></i>Create Job</h2>
      <span class="badge <?php echo $status==='Approved'?'text-bg-success':($status==='Pending'?'text-bg-warning':'text-bg-danger'); ?>">
        Employer Status: <?php echo htmlspecialchars($status); ?>
      </span>
    </div>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($e); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endforeach; ?>

    <?php if ($status !== 'Approved'): ?>
      <div class="alert alert-warning">
        Once your employer account is approved by an admin, you’ll be able to create job posts here.
        If you believe this is a mistake, please <a class="alert-link" href="support.php">contact support</a>.
      </div>
    <?php else: ?>
      <div class="alert alert-info">
        This platform is Work From Home only. Your job will be published as 100% remote.
      </div>
      <form method="post" class="row g-3">
        <div class="col-12">
          <label class="form-label required"><span class="asterisk">Title</span></label>
          <input name="title" class="form-control form-control-lg" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" placeholder="e.g., Junior Software Developer">
        </div>

        <div class="col-md-6">
          <label class="form-label">Employment type</label>
          <select name="employment_type" class="form-select">
            <?php foreach ($employmentTypes as $t): ?>
              <option value="<?php echo htmlspecialchars($t); ?>" <?php if (($_POST['employment_type'] ?? '') === $t) echo 'selected'; ?>><?php echo htmlspecialchars($t); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Original office location (optional)</label>
          <div class="d-flex gap-2">
            <input name="location_city" class="form-control" placeholder="City" value="<?php echo htmlspecialchars($_POST['location_city'] ?? ''); ?>">
            <input name="location_region" class="form-control" placeholder="Region/Province" value="<?php echo htmlspecialchars($_POST['location_region'] ?? ''); ?>">
          </div>
          <small class="text-muted">Shown to applicants for context even if WFH.</small>
        </div>

        <div class="col-md-4">
          <label class="form-label">Salary currency</label>
          <input name="salary_currency" class="form-control" value="<?php echo htmlspecialchars($_POST['salary_currency'] ?? 'PHP'); ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Salary min</label>
          <input name="salary_min" type="number" min="0" class="form-control" placeholder="e.g., 25000" value="<?php echo htmlspecialchars($_POST['salary_min'] ?? ''); ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Salary max</label>
          <input name="salary_max" type="number" min="0" class="form-control" placeholder="e.g., 35000" value="<?php echo htmlspecialchars($_POST['salary_max'] ?? ''); ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Salary period</label>
          <select name="salary_period" class="form-select">
            <?php foreach (['monthly','yearly','hourly'] as $p): ?>
              <option value="<?php echo $p; ?>" <?php if (($_POST['salary_period'] ?? 'monthly') === $p) echo 'selected'; ?>>
                <?php echo ucfirst($p); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-8">
          <label class="form-label">Required Skills (choose any)</label>
          <select name="required_skills[]" class="form-select" multiple size="8">
            <?php foreach ($allowedSkills as $s): ?>
              <option value="<?php echo htmlspecialchars($s); ?>" <?php if (!empty($_POST['required_skills']) && in_array($s, (array)$_POST['required_skills'])) echo 'selected'; ?>>
                <?php echo htmlspecialchars($s); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted d-block">Use standardized skills for better matching.</small>

          <label class="form-label mt-3">Additional skills (comma-separated)</label>
          <input name="additional_skills" class="form-control" placeholder="e.g., Excel, QuickBooks, Zendesk" value="<?php echo htmlspecialchars($_POST['additional_skills'] ?? ''); ?>">
          <small class="text-muted">Add any job-specific skills not in the list. We’ll include these in matching.</small>
        </div>

        <div class="col-md-4">
          <label class="form-label">Experience (years)</label>
          <input name="required_experience" type="number" min="0" class="form-control" value="<?php echo htmlspecialchars($_POST['required_experience'] ?? '0'); ?>">
          <label class="form-label mt-3">Education Requirement</label>
          <select name="required_education" class="form-select">
            <option value="">Any</option>
            <?php foreach ($eduLevels as $lvl): ?>
              <option value="<?php echo htmlspecialchars($lvl); ?>" <?php if (($_POST['required_education'] ?? '') === $lvl) echo 'selected'; ?>>
                <?php echo htmlspecialchars($lvl); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label d-block">Accessibility Tags</label>
          <?php foreach ($accessTags as $tag): ?>
            <?php
              $isPosted = isset($_POST['accessibility_tags']);
              $checked = $isPosted ? in_array($tag, (array)$_POST['accessibility_tags']) : ($tag === 'PWD-Friendly');
            ?>
            <div class="form-check form-check-inline">
              <input class="form-check-input" name="accessibility_tags[]" type="checkbox" value="<?php echo $tag; ?>" <?php echo $checked ? 'checked' : ''; ?>>
              <label class="form-check-label"><?php echo $tag; ?></label>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="col-12">
          <label class="form-label required"><span class="asterisk">Description</span></label>
          <textarea name="description" class="form-control" rows="8" required placeholder="Job summary, responsibilities, qualifications. Include accommodations for PWD applicants."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>

        <div class="col-12 d-grid">
          <button class="btn btn-primary btn-lg"><i class="bi bi-check2-circle me-1"></i>Create</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php include '../includes/footer.php'; ?>