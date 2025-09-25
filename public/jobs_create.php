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

/* General (soft) skills fixed list */
$generalSkills = [
    '70+ WPM Typing',
    'Flexible Schedule',
    'Team Player',
    'Professional Attitude',
    'Strong Communication',
    'Adaptable / Quick Learner'
];

$eduLevels       = Taxonomy::educationLevels();
$employmentTypes = Taxonomy::employmentTypes();
$accessTags      = Taxonomy::accessibilityTags();

$me     = User::findById($_SESSION['user_id']);
$status = $me->employer_status ?: 'Pending';

$errors = [];
$duplicateWarning = [];
$dupThreshold = 85.0;   // % similarity threshold
$scanLimit    = 25;     // how many recent jobs to scan
$duplicatePending = false; // flag if we found duplicates and waiting for confirmation

if ($status !== 'Approved') {
    $errors[] = 'Your employer account is ' . htmlspecialchars($status) . '. You can post jobs only after an admin approves your account.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors) {

    $selectedGeneral = $_POST['required_skills'] ?? [];
    if (!is_array($selectedGeneral)) $selectedGeneral = [$selectedGeneral];
    $selectedGeneral = array_filter(array_map('trim', $selectedGeneral));

    $requiredCustomRaw  = trim($_POST['additional_skills'] ?? '');
    $requiredCustomList = $requiredCustomRaw !== '' ? Helpers::parseSkillInput($requiredCustomRaw) : [];

    $merged = [];
    foreach (array_merge($selectedGeneral, $requiredCustomList) as $s) {
        if ($s === '') continue;
        $k = mb_strtolower($s);
        if (!isset($merged[$k])) $merged[$k] = $s;
    }
    $skillsCsv = implode(', ', $merged);

  $tagsSelected = (array)($_POST['accessibility_tags'] ?? []);

  $data = [
        'title'                 => trim($_POST['title']),
        'description'           => trim($_POST['description']),
        'required_skills_input' => $skillsCsv,
        'required_experience'   => (int)($_POST['required_experience'] ?? 0),
        'required_education'    => trim($_POST['required_education'] ?? ''),
        'accessibility_tags'    => implode(',', array_map('trim', $tagsSelected)),
        'location_city'         => trim($_POST['location_city'] ?? ''),
        'location_region'       => trim($_POST['location_region'] ?? ''),
        'remote_option'         => 'Work From Home',
        'employment_type'       => in_array($_POST['employment_type'] ?? '', $employmentTypes, true) ? $_POST['employment_type'] : 'Full time',
        'salary_currency'       => strtoupper(trim($_POST['salary_currency'] ?? 'PHP')),
        'salary_min'            => ($_POST['salary_min'] !== '') ? max(0, (int)$_POST['salary_min']) : null,
        'salary_max'            => ($_POST['salary_max'] !== '') ? max(0, (int)$_POST['salary_max']) : null,
    'salary_period'         => in_array($_POST['salary_period'] ?? 'monthly', ['monthly','yearly','hourly'], true) ? $_POST['salary_period'] : 'monthly',
    'job_image'             => null,
    ];

  // Handle optional image upload
  if (!empty($_FILES['job_image']['name'])) {
    $okType = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
    $mime = $_FILES['job_image']['type'] ?? '';
    if (!in_array($mime, $okType, true)) {
      $errors[] = 'Job image must be JPG, PNG, GIF, or WEBP.';
    } elseif ($_FILES['job_image']['size'] > 2*1024*1024) {
      $errors[] = 'Job image too large (max 2MB).';
    } else {
      if (!is_dir('../uploads/job_images')) @mkdir('../uploads/job_images', 0775, true);
      $ext = pathinfo($_FILES['job_image']['name'], PATHINFO_EXTENSION);
      $fname = 'uploads/job_images/'.uniqid('job_').'.'.strtolower($ext ?: 'jpg');
      if (move_uploaded_file($_FILES['job_image']['tmp_name'], '../'.$fname)) {
        $data['job_image'] = $fname;
      } else {
        $errors[] = 'Failed to upload job image.';
      }
    }
  }

    if (!$data['title'])       $errors[] = 'Title required';
    if (!$data['description']) $errors[] = 'Description required';
    if (($data['salary_min'] !== null && $data['salary_max'] !== null) && $data['salary_min'] > $data['salary_max']) {
        $errors[] = 'Salary min cannot be greater than salary max.';
    }

    // DUPLICATE CHECK (only if no validation errors yet and not previously confirmed)
    if (!$errors && !isset($_POST['confirm_duplicate'])) {
        $similarJobs = Job::findSimilarByEmployer($_SESSION['user_id'], $data, $dupThreshold, $scanLimit);
        if ($similarJobs) {
            $duplicatePending = true;
            $duplicateWarning = $similarJobs;
        }
    }

    if (!$errors && !$duplicatePending) {
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

    <?php if ($duplicatePending && $duplicateWarning): ?>
      <div class="alert alert-warning border-2">
        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-octagon me-1"></i>Possible duplicate jobs detected</div>
        <p class="small mb-2">
          We found existing job(s) you posted that are very similar (≥ <?php echo (int)$dupThreshold; ?>% title match).
          Review them below. If you still want to create this new job, click "Confirm & Create" again.
        </p>
        <ul class="small mb-2">
          <?php foreach ($duplicateWarning as $d): ?>
            <li>
              <a href="job_view.php?job_id=<?php echo urlencode($d['job_id']); ?>" target="_blank">
                <?php echo htmlspecialchars($d['title']); ?>
              </a>
              (<?php echo $d['percent']; ?>% match
              <?php if ($d['exact_match']) echo ' · exact'; ?>,
              <?php echo htmlspecialchars(date('M d, Y', strtotime($d['created_at']))); ?>,
              status: <?php echo htmlspecialchars($d['status']); ?>)
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="small text-muted">
          This is a safeguard to avoid accidental duplicate postings.
        </div>
      </div>
    <?php endif; ?>

    <?php if ($status !== 'Approved'): ?>
      <div class="alert alert-warning">
        Once your employer account is approved you may post jobs.
      </div>
    <?php else: ?>
  <form method="post" class="row g-3" enctype="multipart/form-data">
        <?php if ($duplicatePending): ?>
          <input type="hidden" name="confirm_duplicate" value="1">
        <?php endif; ?>

        <div class="col-12">
          <label class="form-label fw-semibold">Title<span class="text-danger">*</span></label>
          <input name="title" class="form-control form-control-lg" required
                 value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Employment type</label>
          <select name="employment_type" class="form-select">
            <?php foreach ($employmentTypes as $t): ?>
              <option value="<?php echo htmlspecialchars($t); ?>" <?php if (($_POST['employment_type'] ?? '') === $t) echo 'selected'; ?>>
                <?php echo htmlspecialchars($t); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Office Location (optional)</label>
          <div class="d-flex gap-2">
            <input name="location_city" class="form-control" placeholder="City"
                   value="<?php echo htmlspecialchars($_POST['location_city'] ?? ''); ?>">
            <input name="location_region" class="form-control" placeholder="Region/Province"
                   value="<?php echo htmlspecialchars($_POST['location_region'] ?? ''); ?>">
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Job Image (optional)</label>
          <input type="file" name="job_image" class="form-control" accept="image/*">
          <div class="form-text">JPG/PNG/GIF/WEBP up to 2MB. Shown on listings.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Salary currency</label>
          <input name="salary_currency" class="form-control"
                 value="<?php echo htmlspecialchars($_POST['salary_currency'] ?? 'PHP'); ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Salary min</label>
          <input name="salary_min" type="number" min="0" class="form-control"
                 value="<?php echo htmlspecialchars($_POST['salary_min'] ?? ''); ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Salary max</label>
          <input name="salary_max" type="number" min="0" class="form-control"
                 value="<?php echo htmlspecialchars($_POST['salary_max'] ?? ''); ?>">
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
          <label class="form-label">General Skills</label>
          <div class="row">
            <?php foreach ($generalSkills as $gs):
              $checked = (!empty($_POST['required_skills']) && in_array($gs, (array)$_POST['required_skills'], true)) ? 'checked' : ''; ?>
              <div class="col-sm-6 col-lg-4">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox"
                         id="genskill_<?php echo md5($gs); ?>"
                         name="required_skills[]"
                         value="<?php echo htmlspecialchars($gs); ?>" <?php echo $checked; ?>>
                  <label class="form-check-label small" for="genskill_<?php echo md5($gs); ?>">
                    <?php echo htmlspecialchars($gs); ?>
                  </label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <small class="text-muted d-block mt-1">Select general / soft capabilities.</small>

          <label class="form-label mt-3">Required Skills (comma separated)</label>
          <input name="additional_skills" class="form-control"
                 placeholder="e.g., Calendar Management, Data Entry, Customer Support"
                 value="<?php echo htmlspecialchars($_POST['additional_skills'] ?? ''); ?>">
          <small class="text-muted">Specific technical or role-focused requirements.</small>
        </div>

        <div class="col-md-4">
          <label class="form-label">Experience (years)</label>
          <input name="required_experience" type="number" min="0" class="form-control"
                 value="<?php echo htmlspecialchars($_POST['required_experience'] ?? '0'); ?>">
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
          <?php foreach ($accessTags as $tag):
            $isPosted = isset($_POST['accessibility_tags']);
            $checked = $isPosted ? in_array($tag, (array)$_POST['accessibility_tags'], true) : false;
          ?>
            <div class="form-check form-check-inline">
              <input class="form-check-input" name="accessibility_tags[]" type="checkbox"
                     value="<?php echo htmlspecialchars($tag); ?>" <?php echo $checked ? 'checked' : ''; ?>>
              <label class="form-check-label"><?php echo htmlspecialchars($tag); ?></label>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="col-12">
          <label class="form-label fw-semibold">Description<span class="text-danger">*</span></label>
          <textarea name="description" class="form-control" rows="8" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>

        <div class="col-12 d-grid">
          <button class="btn btn-primary btn-lg">
            <i class="bi bi-check2-circle me-1"></i>
            <?php echo $duplicatePending ? 'Confirm & Create' : 'Create'; ?>
          </button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php include '../includes/footer.php'; ?>