<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';
require_once 'classes/Job.php';
require_once 'classes/Skill.php';
require_once 'classes/Taxonomy.php';
require_once 'classes/User.php';

Helpers::requireLogin();
Helpers::requireRole('employer');

// Employer status for gating creation
$user = User::findById($_SESSION['user_id']);
$status = $user ? ($user->employer_status ?? 'Pending') : 'Pending';

// Enumerations / lists
$employmentTypes = Taxonomy::employmentTypes();
$accessTags      = Taxonomy::accessibilityTags();
$pwdCats         = Taxonomy::disabilityCategories();
$eduLevels       = Taxonomy::educationLevels();
$generalSkills   = [
    '70+ WPM Typing',
    'Flexible Schedule',
    'Team Player',
    'Professional Attitude',
    'Strong Communication',
    'Adaptable / Quick Learner'
];

$errors = [];
$duplicateWarning = [];
$duplicatePending = false;
$dupThreshold = 70;   // % similarity on title to warn
$scanLimit   = 12;    // check up to N recent jobs

// Employer job stats (mirror user dashboard style for visual alignment)
$myJobs = Job::listByEmployer($_SESSION['user_id']);
$totalJobs = count($myJobs);
$openJobs = count(array_filter($myJobs, fn($j)=>($j['status'] ?? '')==='Open'));
$suspendedJobs = count(array_filter($myJobs, fn($j)=>($j['status'] ?? '')==='Suspended'));
$closedJobs = count(array_filter($myJobs, fn($j)=>($j['status'] ?? '')==='Closed'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect + sanitize
  $title       = trim($_POST['title'] ?? '');
  $employment  = $_POST['employment_type'] ?? '';
  if (!in_array($employment, $employmentTypes, true)) $employment = '';
    $locCity     = trim($_POST['location_city'] ?? '');
    $locRegion   = trim($_POST['location_region'] ?? '');
  $salary_currency = strtoupper(trim($_POST['salary_currency'] ?? ''));
    $salary_min  = ($_POST['salary_min'] !== '' && $_POST['salary_min'] !== null) ? max(0,(int)$_POST['salary_min']) : null;
    $salary_max  = ($_POST['salary_max'] !== '' && $_POST['salary_max'] !== null) ? max(0,(int)$_POST['salary_max']) : null;
  $salary_period = $_POST['salary_period'] ?? '';
  if (!in_array($salary_period, ['monthly','yearly','hourly'], true)) $salary_period = '';
  $reqExpRaw   = trim((string)($_POST['required_experience'] ?? ''));
  $reqExp      = ($reqExpRaw === '' ? null : max(0,(int)$reqExpRaw));
  $reqEduRaw   = trim($_POST['required_education'] ?? '');
  if (strcasecmp($reqEduRaw, 'Any') === 0) { $reqEduRaw = ''; }
    $description = trim($_POST['description'] ?? '');

  // Skills (checkbox general + comma separated extra)
    $skillsSelected = $_POST['required_skills'] ?? [];
    if (!is_array($skillsSelected)) $skillsSelected = [$skillsSelected];
    $skillsSelected = array_filter(array_map('trim',$skillsSelected));
    $additionalRaw = trim($_POST['additional_skills'] ?? '');
    $extraTokens = $additionalRaw !== '' ? Helpers::parseSkillInput($additionalRaw) : [];
    $merged = [];
    foreach (array_merge($skillsSelected,$extraTokens) as $s) {
        if ($s==='') continue; $k = mb_strtolower($s); if (!isset($merged[$k])) $merged[$k]=$s; }
    $skillsCsv = implode(', ', $merged);
  if ($skillsCsv === '') {
    $errors[] = 'Select at least one skill or add in Additional Skills.';
  }

    // Accessibility tags
  $tagsSelected = (array)($_POST['accessibility_tags'] ?? []);
  $tagsSelected = array_filter(array_map('trim',$tagsSelected));

  if ($title === '') $errors[] = 'Job title required';
  if ($employment === '') $errors[] = 'Employment type is required';
  if ($description === '') $errors[] = 'Description required';
  if ($locCity === '') $errors[] = 'City is required';
  if ($locRegion === '') $errors[] = 'Region / Province is required';
  if ($salary_currency === '') $errors[] = 'Salary currency is required';
  if ($salary_period === '') $errors[] = 'Salary period is required';
  if ($reqExp === null) $errors[] = 'Experience (years) is required';
    if ($salary_min !== null && $salary_max !== null && $salary_min > $salary_max) {
        $errors[] = 'Salary min cannot exceed salary max';
    }
  if (count($tagsSelected) === 0) $errors[] = 'Select at least one Accessibility & Inclusion tag.';

    // Duplicate detection (title similarity against recent employer jobs)
    if (!$errors && $title !== '') {
        $existing = Job::listByEmployer($_SESSION['user_id']);
        $count = 0;
        foreach ($existing as $row) {
            if ($count >= $scanLimit) break; $count++;
            $pct = 0; similar_text(mb_strtolower($title), mb_strtolower($row['title']), $pct);
            $pct = round($pct,1);
            if ($pct >= $dupThreshold) {
                $duplicateWarning[] = [
                    'job_id' => $row['job_id'],
                    'title' => $row['title'],
                    'percent' => $pct,
                    'exact_match' => (mb_strtolower($row['title']) === mb_strtolower($title)),
                    'created_at' => $row['created_at'],
                    'status' => $row['status']
                ];
            }
        }
        if ($duplicateWarning && empty($_POST['confirm_duplicate'])) {
            $duplicatePending = true; // show warning first round
        }
    }

    // Handle image upload (optional)
    $job_image = null;
    if (!$errors && !$duplicatePending && !empty($_FILES['job_image']['name'])) {
        $okType = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
        $mime = $_FILES['job_image']['type'] ?? '';
        if (!in_array($mime,$okType,true)) {
            $errors[] = 'Job image must be JPG, PNG, GIF or WEBP';
        } elseif ($_FILES['job_image']['size'] > 2*1024*1024) {
            $errors[] = 'Job image too large (max 2MB)';
        } else {
            if (!is_dir(__DIR__.'/uploads/job_images')) @mkdir(__DIR__.'/uploads/job_images', 0775, true);
            $ext = pathinfo($_FILES['job_image']['name'], PATHINFO_EXTENSION);
            $fname = 'uploads/job_images/'.uniqid('job_').'.'.strtolower($ext ?: 'jpg');
      if (move_uploaded_file($_FILES['job_image']['tmp_name'], __DIR__ . '/'.$fname)) {
                $job_image = $fname;
            } else {
                $errors[] = 'Failed to upload image';
            }
        }
    }

  // Require at least one PWD category
  $pwdSelected = isset($_POST['applicable_pwd_types']) ? array_filter(array_map('trim',(array)$_POST['applicable_pwd_types'])) : [];
  if (!$errors && !$duplicatePending && count($pwdSelected) === 0) {
    $errors[] = 'Select at least one Applicable PWD Category.';
  }

  if (!$errors && !$duplicatePending) {
    $data = [
            'title' => $title,
            'description' => $description,
            'required_experience' => $reqExp,
            'required_education' => $reqEduRaw,
            'required_skills_input' => $skillsCsv,
      'accessibility_tags' => implode(',', $tagsSelected),
      'applicable_pwd_types' => implode(',', $pwdSelected),
            'location_city' => $locCity,
            'location_region' => $locRegion,
            'employment_type' => $employment,
            'salary_currency' => $salary_currency,
            'salary_min' => $salary_min,
            'salary_max' => $salary_max,
            'salary_period' => $salary_period,
            'job_image' => $job_image,
        ];
    if (Job::create($data, $_SESSION['user_id'])) {
      Helpers::flash('msg','Job submitted and is now awaiting admin review. You\'ll receive an email once it\'s approved or if changes are needed.');
      Helpers::redirect('employer_dashboard.php');
        } else {
            $errors[] = 'Creation failed';
        }
    }
}

include 'includes/header.php';
include 'includes/nav.php';
?>
<div class="job-create-compact py-4 py-md-5">
  <div class="container">
    <style>
      .fieldset-plain{border:0;padding:0;margin:0 0 1.25rem 0;min-width:0;}
      .fieldset-plain legend{margin-bottom:.5rem;font-size:.875rem;font-weight:600;text-transform:uppercase;color:var(--bs-secondary-color,#6c757d);}
      .create-progress-bar[role=progressbar]{position:relative;}
      .create-progress-bar[role=progressbar] .cpb-fill{display:block;height:100%;}
      .file-input-stack{display:flex;flex-direction:column;}
      .file-input-stack .file-label{font-size:.7rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;margin-bottom:.25rem;color:#5c6772;}
      .file-input-stack .plain-file{padding:.45rem .65rem;}
      /* Force select wrapper to show as filled so label does not collide with first option */
      .input-floating-label.select-filled { background:#fff; }
      .input-floating-label.select-filled > label { opacity:.85; }
      .input-floating-label.force-top {background:#fff !important;}
      /* Pin the label above the control and avoid overlap with placeholder */
      .input-floating-label.force-top > label {
        position:absolute;
        top:-0.55rem;
        left:.75rem;
        background:#fff;
        padding:0 .25rem;
        font-size:.78rem;
        line-height:1;
        opacity:.85;
        transform:none;
      }
      .input-floating-label.force-top select.form-select,
      .input-floating-label.force-top input.form-control,
      .input-floating-label.force-top textarea.form-control {
        /* extra space is not strictly required since label sits above border, but keep consistent */
        padding-top:.6rem;
      }
      .group-hint {
        display:flex; align-items:center; gap:.4rem; margin:.25rem 0 .5rem; color:#6c757d;
      }
      .group-hint .bi { color:#6c757d; }
      .invalid-msg { color:#dc3545; font-size:.85rem; margin-top:.25rem; }
      .invalid-msg.d-none { display:none; }
    </style>
    <div class="jc-compact-card">
      <div class="jccc-head">
        <div class="jccc-title-row">
          <h2 class="jccc-title mb-0"><i class="bi bi-card-text me-2"></i>Create Job Posting</h2>
          <div class="jccc-status status-<?php echo strtolower($status); ?>"><i class="bi bi-building-check me-1"></i><?php echo htmlspecialchars($status); ?> Employer</div>
        </div>
        <p class="jccc-sub mb-2">Please fill out the form to publish a new job opportunity.</p>
        <div class="small text-muted d-flex flex-wrap align-items-center gap-2 mb-3">
          <span class="hint-item"><i class="bi bi-bullseye me-1"></i>Be specific</span>
          <span class="hint-item"><i class="bi bi-cash-coin me-1"></i>Show salary</span>
          <span class="hint-item"><i class="bi bi-universal-access me-1"></i>Tag support</span>
        </div>
        <!-- Job stats removed as requested -->
      </div>
      <div class="jccc-body">
        <div class="progress-create-wrapper mb-3">
          <div class="create-progress-bar" id="jobProgressBar" role="progressbar" aria-label="Form completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span class="cpb-fill" style="width:0%"></span></div>
          <div class="cpb-meta small"><span id="cpbPercent">0%</span> complete</div>
        </div>
        <div class="fsc-body pt-0" id="core">
      <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($e); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endforeach; ?>

      <?php if ($duplicatePending && $duplicateWarning): ?>
        <div class="alert alert-warning border-2">
          <div class="fw-semibold mb-1"><i class="bi bi-exclamation-octagon me-1"></i>Possible duplicate jobs detected</div>
          <p class="small mb-2">We found existing job(s) you posted that are very similar (≥ <?php echo (int)$dupThreshold; ?>% title match). Review them below. If you still want to create this new job, click "Confirm &amp; Create" again.</p>
          <ul class="small mb-2">
            <?php foreach ($duplicateWarning as $d): ?>
              <li>
                <a href="job_view.php?job_id=<?php echo urlencode($d['job_id']); ?>" target="_blank">
                  <?php echo htmlspecialchars($d['title']); ?>
                </a>
                (<?php echo $d['percent']; ?>% match<?php if ($d['exact_match']) echo ' · exact'; ?>,
                <?php echo htmlspecialchars(date('M d, Y', strtotime($d['created_at']))); ?>,
                status: <?php echo htmlspecialchars($d['status']); ?>)
              </li>
            <?php endforeach; ?>
          </ul>
          <div class="small text-muted">This is a safeguard to avoid accidental duplicate postings.</div>
        </div>
      <?php endif; ?>

      <?php if ($status !== 'Approved'): ?>
        <div class="alert alert-warning mb-0">Once your employer account is approved you may post jobs.</div>
      <?php else: ?>
      <form method="post" enctype="multipart/form-data" class="job-create-form" id="jobCreateForm">
        <?php if ($duplicatePending): ?><input type="hidden" name="confirm_duplicate" value="1"><?php endif; ?>

        <div class="grid-2 gap first-group">
          <div class="input-floating-label big">
            <label>Job Title *</label>
            <input name="title" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" class="form-control">
          </div>
          <div class="input-floating-label force-top">
            <label>Employment Type *</label>
            <select name="employment_type" class="form-select" required>
              <option value="" disabled <?php if (empty($_POST['employment_type'])) echo 'selected'; ?>>Select employment type</option>
              <?php foreach ($employmentTypes as $t): ?>
                <option value="<?php echo htmlspecialchars($t); ?>" <?php if (($_POST['employment_type'] ?? '') === $t) echo 'selected'; ?>><?php echo htmlspecialchars($t); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="section-divider" aria-hidden="true"></div>
        <h5 class="section-head" id="loccomp"><i class="bi bi-geo-alt me-1"></i>Location & Compensation</h5>
        <div class="grid-2 gap">
          <div class="input-floating-label">
            <label>City *</label>
            <input name="location_city" required value="<?php echo htmlspecialchars($_POST['location_city'] ?? ''); ?>" class="form-control" aria-describedby="hintCity">
          </div>
          <div class="input-floating-label">
            <label>Region / Province *</label>
            <input name="location_region" required value="<?php echo htmlspecialchars($_POST['location_region'] ?? ''); ?>" class="form-control">
          </div>
        </div>

        <div class="grid-3 gap mt-3">
          <div class="input-floating-label force-top">
            <label>Salary Currency *</label>
            <input name="salary_currency" required value="<?php echo htmlspecialchars($_POST['salary_currency'] ?? ''); ?>" placeholder="PHP" class="form-control">
          </div>
          <div class="input-floating-label">
            <label>Salary Min</label>
            <input name="salary_min" type="number" min="0" value="<?php echo htmlspecialchars($_POST['salary_min'] ?? ''); ?>" class="form-control">
          </div>
          <div class="input-floating-label">
            <label>Salary Max</label>
            <input name="salary_max" type="number" min="0" value="<?php echo htmlspecialchars($_POST['salary_max'] ?? ''); ?>" class="form-control">
          </div>
        </div>

        <div class="grid-2 gap mt-3">
          <div class="input-floating-label force-top">
            <label>Salary Period *</label>
            <select name="salary_period" class="form-select" required>
              <option value="" disabled <?php if (empty($_POST['salary_period'])) echo 'selected'; ?>>Select period</option>
              <?php foreach (['monthly','yearly','hourly'] as $p): ?>
                <option value="<?php echo $p; ?>" <?php if (($_POST['salary_period'] ?? '') === $p) echo 'selected'; ?>><?php echo ucfirst($p); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group file-input-stack">
            <label for="job_image" class="file-label">Job Image (optional)</label>
            <input type="file" id="job_image" name="job_image" accept="image/*" class="form-control plain-file">
            <div class="small text-muted mt-1">PNG / JPG / GIF / WEBP up to 2MB</div>
          </div>
        </div>
        <p class="form-hint small mt-2 mb-0">Leave salary blank if confidential. Providing a range increases applicant trust.</p>

        <div class="section-divider" aria-hidden="true"></div>
        <h5 class="section-head" id="skills"><i class="bi bi-stars me-1"></i>Skills &amp; Qualifications</h5>
        <div class="grid-3 gap">
          <div class="input-floating-label">
            <label>Experience (years) *</label>
            <input name="required_experience" type="number" min="0" required value="<?php echo htmlspecialchars($_POST['required_experience'] ?? ''); ?>" class="form-control">
          </div>
          <div class="input-floating-label force-top">
            <label>Education Requirement *</label>
            <select name="required_education" class="form-select" aria-describedby="eduHelp" required>
              <option value="" disabled <?php if (($_POST['required_education'] ?? '') === '') echo 'selected'; ?>>Select education requirement</option>
              <option value="Any" <?php if (($_POST['required_education'] ?? '') === 'Any') echo 'selected'; ?>>Any</option>
              <?php foreach ($eduLevels as $lvl): ?>
                <option value="<?php echo htmlspecialchars($lvl); ?>" <?php if (($_POST['required_education'] ?? '') === $lvl) echo 'selected'; ?>><?php echo htmlspecialchars($lvl); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="input-floating-label">
            <label>Additional Skills (comma separated)</label>
            <input name="additional_skills" value="<?php echo htmlspecialchars($_POST['additional_skills'] ?? ''); ?>" class="form-control" aria-describedby="addSkillsHint">
          </div>
        </div>
  <div id="eduHelp" class="form-hint small mt-n1 mb-2">Select "Any" if all education levels are accepted.</div>
  <fieldset class="mt-3 fieldset-plain" aria-labelledby="skills">
          <legend class="visually-hidden">General / Soft Skills (at least 1 required)</legend>
          <div class="group-hint small" id="skillsHint">
            <i class="bi bi-info-circle" data-bs-toggle="tooltip" title="To help matching and quality of applicants, at least one skill is required."></i>
            <span>At least one skill is required.</span>
          </div>
          <div class="row g-2 gen-skills-grid" data-required-group="skills">
            <?php foreach ($generalSkills as $gs):
              $checked = (!empty($_POST['required_skills']) && in_array($gs, (array)$_POST['required_skills'], true)) ? 'checked' : ''; ?>
              <div class="col-sm-6 col-lg-4">
                <label class="skill-chip">
                  <input type="checkbox" name="required_skills[]" value="<?php echo htmlspecialchars($gs); ?>" <?php echo $checked; ?>>
                  <span><?php echo htmlspecialchars($gs); ?></span>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="invalid-msg d-none" data-for-group="skills">Please select at least one skill or add skills in the text field.</div>
        </fieldset>

        <fieldset class="fieldset-plain" aria-labelledby="access">
          <legend class="visually-hidden">Applicable PWD Categories (at least 1 required)</legend>
          <div class="group-hint small" id="pwdHint">
            <i class="bi bi-info-circle" data-bs-toggle="tooltip" title="We require at least one target PWD category so job seekers can discover the right opportunities."></i>
            <span>Select at least one PWD category for this job.</span>
          </div>
          <div class="tags-flex mb-3" data-required-group="pwdcats">
            <?php foreach ($pwdCats as $pcat):
              $isPosted = isset($_POST['applicable_pwd_types']);
              $checked = $isPosted ? in_array($pcat, (array)$_POST['applicable_pwd_types'], true) : false; ?>
              <label class="acc-tag <?php echo $checked ? 'selected' : ''; ?>">
                <input type="checkbox" name="applicable_pwd_types[]" value="<?php echo htmlspecialchars($pcat); ?>" <?php echo $checked ? 'checked' : ''; ?>>
                <span><i class="bi bi-person-check me-1"></i><?php echo htmlspecialchars($pcat); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="form-hint small">Select at least one PWD category this job is intended for.</div>
          <div class="invalid-msg d-none" data-for-group="pwdcats">Please select at least one Applicable PWD Category.</div>
        </fieldset>

        <div class="section-divider" aria-hidden="true"></div>
        <h5 class="section-head" id="access"><i class="bi bi-universal-access me-1"></i>Accessibility &amp; Inclusion</h5>
        <fieldset class="fieldset-plain" aria-labelledby="access">
          <legend class="visually-hidden">Accessibility & Inclusion Tags (at least 1 required)</legend>
          <div class="group-hint small" id="accessHint">
            <i class="bi bi-info-circle" data-bs-toggle="tooltip" title="At least one tag helps set expectations around inclusion and accommodations."></i>
            <span>Select at least one Accessibility & Inclusion tag.</span>
          </div>
          <div class="tags-flex mb-3" data-required-group="accesstags">
            <?php foreach ($accessTags as $tag):
              $isPosted = isset($_POST['accessibility_tags']);
              $checked = $isPosted ? in_array($tag, (array)$_POST['accessibility_tags'], true) : false; ?>
              <label class="acc-tag <?php echo $checked ? 'selected' : ''; ?>">
                <input type="checkbox" name="accessibility_tags[]" value="<?php echo htmlspecialchars($tag); ?>" <?php echo $checked ? 'checked' : ''; ?>>
                <span><i class="bi bi-universal-access me-1"></i><?php echo htmlspecialchars($tag); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="invalid-msg d-none" data-for-group="accesstags">Please select at least one Accessibility & Inclusion tag.</div>
        </fieldset>

        <div class="section-divider" aria-hidden="true"></div>
        <h5 class="section-head" id="desc"><i class="bi bi-journal-text me-1"></i>Description &amp; Publish</h5>
        <div class="input-floating-label textarea">
          <label>Role Description *</label>
          <textarea name="description" required rows="10" class="form-control" aria-describedby="descHint"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>
        <div id="descHint" class="form-hint small mt-1">Outline responsibilities, tools used, schedule, success metrics, team structure, growth path.</div>
        <div class="mt-4 d-flex flex-wrap gap-2 align-items-center">
          <button class="btn btn-gradient px-4"><i class="bi bi-check2-circle me-1"></i><?php echo $duplicatePending ? 'Confirm &amp; Create' : 'Create Job'; ?></button>
          <a href="employer_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Cancel</a>
        </div>
      </form>
      <?php endif; ?>
        </div><!-- /#core -->
      </div><!-- /.jccc-body -->
    </div><!-- /.jc-compact-card -->
  </div><!-- /.container -->
</div><!-- /.job-create-compact -->
<?php include 'includes/footer.php'; ?>
<script>
// Enhance chip & tag selection visuals + ensure floating labels react
(function(){
  function syncFloating(el){
    var wrap = el.closest('.input-floating-label');
    if(!wrap) return;
    if(el.value.trim()!=='' || el === document.activeElement){
      wrap.classList.add('filled');
    } else {
      wrap.classList.remove('filled');
    }
  }
  document.querySelectorAll('.input-floating-label input, .input-floating-label textarea, .input-floating-label select').forEach(function(inp){
    ['input','change','blur','focus'].forEach(function(evt){ inp.addEventListener(evt,function(){syncFloating(inp);}); });
    // initial
    syncFloating(inp);
  });
  // For select elements: only mark filled when a non-empty option is selected
  document.querySelectorAll('.input-floating-label select').forEach(function(sel){
    function sync(){
      var wrap = sel.closest('.input-floating-label');
      if(!wrap) return;
      if((sel.value||'').trim() !== '') wrap.classList.add('filled','select-filled');
      else wrap.classList.remove('filled','select-filled');
    }
    sel.addEventListener('change', sync);
    sync();
  });

  // Skill chips: toggle selected style when checkbox changes
  document.querySelectorAll('.skill-chip input[type=checkbox]').forEach(function(cb){
    cb.addEventListener('change', function(){
      var lab = cb.closest('.skill-chip');
      if(!lab) return;
      if(cb.checked) lab.classList.add('is-checked'); else lab.classList.remove('is-checked');
    });
    if(cb.checked) cb.dispatchEvent(new Event('change'));
  });

  // Accessibility tags
  document.querySelectorAll('.acc-tag input[type=checkbox]').forEach(function(cb){
    cb.addEventListener('change', function(){
      var tag = cb.closest('.acc-tag');
      if(!tag) return;
      tag.classList.toggle('selected', cb.checked);
    });
    if(cb.checked) cb.dispatchEvent(new Event('change'));
  });

    // Removed sidebar navigation logic since form is now a single container
    // Progress calculation for required fields
    (function(){
      var form = document.getElementById('jobCreateForm');
      if(!form) return;
      var bar = document.getElementById('jobProgressBar');
      var percentEl = document.getElementById('cpbPercent');
      // Bootstrap tooltips
      if (window.bootstrap) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el){
          try { new bootstrap.Tooltip(el); } catch(e) {}
        });
      }
      function showGroupError(groupAttr, show){
        var msg = form.querySelector('.invalid-msg[data-for-group="'+groupAttr+'"]');
        if(!msg) return;
        msg.classList.toggle('d-none', !show);
      }
      function groupSatisfied(groupAttr){
        var wrap = form.querySelector('[data-required-group="'+groupAttr+'"]');
        if(!wrap) return true;
        var anyChecked = !!wrap.querySelector('input[type=checkbox]:checked');
        return anyChecked;
      }
      function reqFields(){
        // Only count visible required inputs/textareas (not hidden duplicate confirm input)
        return Array.from(form.querySelectorAll('[required]')).filter(function(el){ return !el.disabled && el.type !== 'hidden'; });
      }
      function filled(el){
        if(!el) return false; var val = (el.value||'').trim(); return val.length>0; }
      function update(){
        var fields = reqFields();
        var done = fields.filter(filled).length;
        // Add virtual required groups for skills, pwdcats, and accessibility tags
        var virtualReqs = 3; // skills + pwdcats + accesstags
        var virtualDone = 0;
        if(groupSatisfied('skills')) virtualDone++;
        if(groupSatisfied('pwdcats')) virtualDone++;
        if(groupSatisfied('accesstags')) virtualDone++;
        // Toggle inline errors as user interacts
        showGroupError('skills', !groupSatisfied('skills'));
        showGroupError('pwdcats', !groupSatisfied('pwdcats'));
        showGroupError('accesstags', !groupSatisfied('accesstags'));
        var total = fields.length + virtualReqs;
        var pct = total? Math.round(((done + virtualDone)/total)*100):0;
        if(bar){
          var fill = bar.querySelector('.cpb-fill'); if(fill){ fill.style.width=pct+'%'; }
          bar.setAttribute('aria-valuenow', pct);
          bar.setAttribute('aria-valuetext', pct+' percent complete');
        }
        if(percentEl){ percentEl.textContent = pct+'%'; }
      }
      form.addEventListener('input', update);
      form.addEventListener('change', update);
      form.addEventListener('submit', function(e){
        var missing = (!groupSatisfied('skills') || !groupSatisfied('pwdcats') || !groupSatisfied('accesstags'));
        if(missing){ e.preventDefault(); update(); return false; }
      });
      update();
    })();
})();
</script>
