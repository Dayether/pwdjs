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
$pwdCats         = Taxonomy::disabilityCategories();
$eduLevels       = Taxonomy::educationLevels();

$errors = [];

// Determine if job already has applicants (lock matching criteria fields if so)
$pdo = Database::getConnection();
$applicantCount = 0;
try {
  $stApp = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE job_id = ?");
  $stApp->execute([$job_id]);
  $applicantCount = (int)$stApp->fetchColumn();
} catch (Throwable $e) { $applicantCount = 0; }
$matchingLocked = ($applicantCount > 0); // employer edit page (no admin override here)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Predefined + additional skills (only if not locked)
    if (!$matchingLocked) {
      $skillsSelected = $_POST['required_skills'] ?? [];
      if (!is_array($skillsSelected)) $skillsSelected = [$skillsSelected];
      $skillsSelected = array_filter(array_map('trim', $skillsSelected));
      $additionalSkillsRaw = trim($_POST['additional_skills'] ?? '');
      $extraTokens = $additionalSkillsRaw !== '' ? Helpers::parseSkillInput($additionalSkillsRaw) : [];
      $merged = [];
      foreach (array_merge($skillsSelected, $extraTokens) as $s) {
          if ($s === '') continue;
          $k = mb_strtolower($s);
          if (!isset($merged[$k])) $merged[$k] = $s;
      }
      $skillsCsv = implode(', ', $merged);
    } else {
      // Keep original required skills if locked
      $skillsCsv = $job->required_skills_input ?? '';
    }

    // Accessibility tags
  $tagsSelected = (array)($_POST['accessibility_tags'] ?? []);

    // Other fields (unchanged structure)
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $reqExpRaw   = (int)($_POST['required_experience'] ?? 0);
    $reqEduRawIn = trim($_POST['required_education'] ?? '');
    if ($matchingLocked) {
      $reqExp    = (int)$job->required_experience;
      $reqEduRaw = (string)($job->required_education ?? '');
    } else {
      $reqExp    = $reqExpRaw;
      $reqEduRaw = $reqEduRawIn;
    }
    $locCity     = trim($_POST['location_city'] ?? '');
    $locRegion   = trim($_POST['location_region'] ?? '');
    $employment  = $_POST['employment_type'] ?? 'Full time';
    $salary_currency = strtoupper(trim($_POST['salary_currency'] ?? 'PHP'));
    $salary_min  = ($_POST['salary_min'] !== '') ? max(0, (int)$_POST['salary_min']) : null;
    $salary_max  = ($_POST['salary_max'] !== '') ? max(0, (int)$_POST['salary_max']) : null;
    $salary_period = in_array($_POST['salary_period'] ?? 'monthly', ['monthly','yearly','hourly'], true)
        ? $_POST['salary_period'] : 'monthly';
  $job_image = $job->job_image; // default keep existing

    if ($title === '') $errors[] = 'Title required';
    if ($description === '') $errors[] = 'Description required';
    if ($salary_min !== null && $salary_max !== null && $salary_min > $salary_max) {
        $errors[] = 'Salary min cannot be greater than salary max.';
    }
    if (!in_array($employment, $employmentTypes, true)) $employment = 'Full time';

  // Optional: image upload
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
        $job_image = $fname;
      } else {
        $errors[] = 'Failed to upload job image.';
      }
    }
  }

  $data = [
        'title' => $title,
        'description' => $description,
        'required_skills_input' => $skillsCsv,
  'required_experience' => $reqExp,
  'required_education' => $reqEduRaw,
    'accessibility_tags' => implode(',', array_map('trim',$tagsSelected)),
    'applicable_pwd_types' => isset($_POST['applicable_pwd_types']) ? implode(',', array_map('trim',(array)$_POST['applicable_pwd_types'])) : ($job->applicable_pwd_types ?? null),
        'location_city' => $locCity,
        'location_region' => $locRegion,
        'remote_option' => 'Work From Home',
        'employment_type' => $employment,
        'salary_currency' => $salary_currency ?: 'PHP',
        'salary_min' => $salary_min,
        'salary_max' => $salary_max,
    'salary_period' => $salary_period,
    'job_image'     => $job_image
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

// Split existing skills between general (from fixed list) + custom/additional
$rawTokens = array_filter(array_map('trim', explode(',', $job->required_skills_input ?? '')));
$generalLower = array_map('mb_strtolower', $generalSkills);
$selectedGeneral = [];
$additionalSkills = [];
foreach ($rawTokens as $tok) {
  if ($tok === '') continue;
  if (in_array(mb_strtolower($tok), $generalLower, true)) {
    $selectedGeneral[] = $tok;
  } else {
    $additionalSkills[] = $tok;
  }
}
$additionalSkillsCsv = implode(', ', $additionalSkills);

// If locked, disable editing in UI
$lockedAttr = $matchingLocked ? 'disabled' : '';

// Determine a safe back URL (prefer stored last page if available)
$backUrl = 'employer_dashboard.php#jobs';
if (!empty($_SESSION['last_page'])) {
  $candidate = $_SESSION['last_page'];
  if (preg_match('~^/?[A-Za-z0-9_./#-]+$~', $candidate) && !str_contains($candidate,'..')) {
    $backUrl = $candidate;
  }
} elseif (!empty($_SERVER['HTTP_REFERER'])) {
  $ref = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
  if ($ref && preg_match('~^/?[A-Za-z0-9_./-]+$~', $ref)) {
    $backUrl = ltrim($ref,'/');
  }
}

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="card border-0 shadow-sm">
  <div class="card-body p-4">
    <?php $editCsrf = Helpers::csrfToken(); ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-outline-secondary btn-sm" id="dynamicBackBtn" aria-label="Go back"><i class="bi bi-arrow-left"></i></a>
        <h2 class="h5 fw-semibold mb-0 d-flex align-items-center gap-2"><i class="bi bi-pencil-square"></i><span>Edit Job</span></h2>
      </div>
      <?php if (isset($job->status)): $st = $job->status; ?>
        <div class="d-flex align-items-center gap-2">
          <span id="statusPill" class="badge rounded-pill text-uppercase fw-semibold bg-light text-dark border" style="letter-spacing:.6px;">
            <?php echo htmlspecialchars($st); ?>
          </span>
          <div class="btn-group">
            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Change status" id="statusDropdownBtn">Status</button>
            <ul class="dropdown-menu dropdown-menu-end" id="statusMenu" data-job-id="<?php echo htmlspecialchars($job->job_id); ?>">
              <?php foreach(['Open','Suspended','Closed'] as $opt): if ($opt === $st) continue; ?>
                <li><a class="dropdown-item status-change" href="jobs_status.php?ajax=1&job_id=<?php echo urlencode($job->job_id); ?>&to=<?php echo urlencode($opt); ?>&csrf=<?php echo urlencode($editCsrf); ?>" data-status="<?php echo htmlspecialchars($opt); ?>"><?php echo $opt === 'Open' ? 'Set Open' : ($opt==='Suspended'?'Suspend':'Close'); ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>

    <?php if ($msg = ($_SESSION['flash']['msg'] ?? null)): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

  <form method="post" class="row g-3" enctype="multipart/form-data" novalidate>
      <div class="col-12">
        <fieldset class="border rounded p-3 pt-2">
          <legend class="float-none w-auto px-2 small fw-semibold mb-1">Title</legend>
          <label for="jobTitle" class="form-label fw-semibold">Role Title <span class="text-danger">*</span></label>
          <input id="jobTitle" name="title" class="form-control" required value="<?php echo htmlspecialchars($job->title); ?>" aria-describedby="titleHelp">
          <div id="titleHelp" class="form-text">Use a clear, specific role name (e.g., “Frontend Accessibility Engineer”).</div>
        </fieldset>
      </div>

      <div class="col-md-6">
        <fieldset class="border rounded p-3 pt-2 h-100">
          <legend class="float-none w-auto px-2 small fw-semibold mb-1">Employment Type</legend>
          <label class="form-label">Select type</label>
          <select name="employment_type" class="form-select">
            <?php foreach ($employmentTypes as $t): ?>
              <option value="<?php echo htmlspecialchars($t); ?>" <?php if ($job->employment_type === $t) echo 'selected'; ?>>
                <?php echo htmlspecialchars($t); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </fieldset>
      </div>

      <div class="col-md-6">
        <fieldset class="border rounded p-3 pt-2 h-100">
          <legend class="float-none w-auto px-2 small fw-semibold mb-1">Location (Optional)</legend>
          <label class="form-label">Office / Base City</label>
          <div class="d-flex gap-2 flex-wrap" aria-describedby="locHelp">
            <input name="location_city" class="form-control" placeholder="City" value="<?php echo htmlspecialchars($job->location_city); ?>" aria-label="City">
            <input name="location_region" class="form-control" placeholder="Region/Province" value="<?php echo htmlspecialchars($job->location_region); ?>" aria-label="Region or Province">
          </div>
          <div id="locHelp" class="form-text">Leave blank for fully remote roles.</div>
        </fieldset>
      </div>

      <div class="col-md-6">
        <fieldset class="border rounded p-3 pt-2 h-100">
          <legend class="float-none w-auto px-2 small fw-semibold mb-1">Job Image (Optional)</legend>
          <div id="imagePreview" class="border rounded p-2 d-flex align-items-center gap-3 mb-2" style="min-height:82px; background:#f8fafc;">
            <?php if (!empty($job->job_image)): ?>
              <img id="jobImageTag" src="../<?php echo htmlspecialchars($job->job_image); ?>" alt="Current job image" style="max-height:70px; border-radius:6px;">
            <?php else: ?>
              <div id="jobImagePlaceholder" class="text-muted small">No image uploaded.</div>
            <?php endif; ?>
          </div>
          <input id="jobImage" type="file" name="job_image" class="form-control" accept="image/*" aria-describedby="jobImageHelp">
          <div id="jobImageHelp" class="form-text">JPG / PNG / GIF / WEBP up to 2MB. Choosing a new file replaces the current image.</div>
        </fieldset>
      </div>

      <div class="col-12">
        <fieldset class="border rounded p-3 pt-2">
          <legend class="float-none w-auto px-2 small fw-semibold mb-1">Compensation</legend>
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Currency</label>
              <input name="salary_currency" class="form-control" value="<?php echo htmlspecialchars($job->salary_currency); ?>" aria-label="Salary currency">
            </div>
            <div class="col-md-3">
              <label class="form-label">Min</label>
              <input name="salary_min" type="number" min="0" class="form-control" value="<?php echo htmlspecialchars($job->salary_min ?? ''); ?>" aria-label="Minimum salary">
            </div>
            <div class="col-md-3">
              <label class="form-label">Max</label>
              <input name="salary_max" type="number" min="0" class="form-control" value="<?php echo htmlspecialchars($job->salary_max ?? ''); ?>" aria-label="Maximum salary">
            </div>
            <div class="col-md-3">
              <label class="form-label">Period</label>
              <select name="salary_period" class="form-select" aria-label="Salary period">
                <?php foreach (['monthly','yearly','hourly'] as $p): ?>
                  <option value="<?php echo $p; ?>" <?php if ($job->salary_period === $p) echo 'selected'; ?>><?php echo ucfirst($p); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-text mt-2">Provide both min & max for transparency (optional but improves candidate trust).</div>
        </fieldset>
      </div>

      <div class="col-12">
        <fieldset class="border rounded p-3 pt-2" style="--bs-border-color:#e3e8ee;">
          <legend class="float-none w-auto px-2 small fw-semibold mb-1">Skills &amp; Qualifications<?php if($matchingLocked): ?> <span class="badge text-bg-warning ms-1 align-middle">Locked (has applicants)</span><?php endif; ?></legend>
          <div class="row g-3 align-items-start">
            <div class="col-md-3">
              <label class="form-label small mb-1">Experience (years)</label>
              <input name="required_experience" type="number" min="0" class="form-control" value="<?php echo htmlspecialchars($job->required_experience); ?>" aria-label="Required experience in years" <?php echo $lockedAttr; ?>>
            </div>
            <div class="col-md-3">
              <label class="form-label small mb-1">Education Requirement</label>
              <select name="required_education" class="form-select" aria-label="Education requirement" <?php echo $lockedAttr; ?>>
                <option value="">Any</option>
                <?php foreach ($eduLevels as $lvl): ?>
                  <option value="<?php echo htmlspecialchars($lvl); ?>" <?php if (($job->required_education ?? '') === $lvl) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($lvl); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label for="additionalSkills" class="form-label small mb-1">Additional Skills (comma separated)</label>
              <input id="additionalSkills" type="text" name="additional_skills" class="form-control" value="<?php echo htmlspecialchars($additionalSkillsCsv); ?>" aria-describedby="addSkillsHelp" placeholder="e.g., PHP, Laravel, Data Entry" <?php echo $lockedAttr; ?>>
              <div id="addSkillsHelp" class="form-text">General soft skills below; add technical or role-specific skills here. Automatically deduplicated.</div>
            </div>
            <div class="col-12">
              <div class="row" id="skillsGroup" aria-describedby="skillsHelp">
                <?php foreach ($generalSkills as $skill):
                  $checked = in_array($skill, $selectedGeneral, true) ? 'checked' : ''; ?>
                  <div class="col-6 col-md-4 col-lg-3 mb-2">
                    <div class="form-check small">
                      <input class="form-check-input" type="checkbox" id="skill_<?php echo md5($skill); ?>" name="required_skills[]" value="<?php echo htmlspecialchars($skill); ?>" <?php echo $checked; ?> <?php echo $lockedAttr; ?>>
                      <label class="form-check-label" for="skill_<?php echo md5($skill); ?>"><?php echo htmlspecialchars($skill); ?></label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <div id="skillsHelp" class="form-text mt-0">
                <?php if($matchingLocked): ?>Matching criteria locked because there are existing applicants (<?php echo $applicantCount; ?>). These fields can no longer be changed.<?php else: ?>Tick all general / soft capabilities that apply.<?php endif; ?>
              </div>
            </div>
          </div>
        </fieldset>
      </div>

      <div class="col-12">
        <fieldset class="border rounded p-3 pt-2" style="--bs-border-color: #e3e8ee;">
          <legend class="float-none w-auto px-2 small fw-semibold mb-1">Accessibility Tags</legend>
          <?php
            $currentTags = array_filter(array_map('trim', explode(',', $job->accessibility_tags ?? '')));
          ?>
          <div class="d-flex flex-wrap gap-3" id="accessGroup" aria-describedby="accessHelp">
            <?php foreach ($accessTags as $tag): ?>
              <div class="form-check form-check-inline m-0">
                <input class="form-check-input" name="accessibility_tags[]" type="checkbox"
                       id="access_<?php echo md5($tag); ?>"
                       value="<?php echo htmlspecialchars($tag); ?>"
                       <?php echo in_array($tag, $currentTags, true) ? 'checked' : ''; ?>>
                <label class="form-check-label small" for="access_<?php echo md5($tag); ?>"><?php echo htmlspecialchars($tag); ?></label>
              </div>
            <?php endforeach; ?>
          </div>
          <div id="accessHelp" class="form-text mt-1">Highlight inclusive or assistive practices relevant to this role.</div>
        </fieldset>
      </div>

      <div class="col-12">
        <fieldset class="border rounded p-3 pt-2" style="--bs-border-color: #e3e8ee;">
          <legend class="float-none w-auto px-2 small fw-semibold mb-1">Applicable PWD Categories</legend>
          <?php $curCats = array_filter(array_map('trim', explode(',', $job->applicable_pwd_types ?? ''))); ?>
          <div class="d-flex flex-wrap gap-3" aria-describedby="pwdCatHelp">
            <?php foreach ($pwdCats as $pcat): $isSel = in_array($pcat, $curCats, true); ?>
              <div class="form-check form-check-inline m-0">
                <input class="form-check-input" type="checkbox" name="applicable_pwd_types[]" id="pwdcat_<?php echo md5($pcat); ?>" value="<?php echo htmlspecialchars($pcat); ?>" <?php echo $isSel ? 'checked' : ''; ?>>
                <label class="form-check-label small" for="pwdcat_<?php echo md5($pcat); ?>"><?php echo htmlspecialchars($pcat); ?></label>
              </div>
            <?php endforeach; ?>
          </div>
          <div id="pwdCatHelp" class="form-text mt-1">Select specific PWD categories this job is intended for. Leave empty if open to all PWDs.</div>
        </fieldset>
      </div>

      <div class="col-12">
        <fieldset class="border rounded p-3 pt-2">
          <legend class="float-none w-auto px-2 small fw-semibold mb-1">Role Description</legend>
          <label for="jobDesc" class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
          <div id="descHint" class="form-text mb-1">Outline mission, key responsibilities, impact, & inclusive culture notes. Avoid internal-only jargon.</div>
          <textarea id="jobDesc" name="description" class="form-control" rows="8" required aria-describedby="descHint"><?php echo htmlspecialchars($job->description); ?></textarea>
        </fieldset>
      </div>

      <div class="col-12 d-grid mt-2">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
      </div>
    </form>
    <div class="visually-hidden" id="editLive" aria-live="polite"></div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
(function(){
  const imgInput = document.getElementById('jobImage');
  const previewWrap = document.getElementById('imagePreview');
  if(imgInput){
    imgInput.addEventListener('change', function(){
      if(!this.files || !this.files[0]) return;
      const f = this.files[0];
      if(!/^image\//.test(f.type)) return;
      const url = URL.createObjectURL(f);
      let tag = document.getElementById('jobImageTag');
      const placeholder=document.getElementById('jobImagePlaceholder');
      if(!tag){
        tag=document.createElement('img');
        tag.id='jobImageTag';
        tag.style.maxHeight='70px';
        tag.style.borderRadius='6px';
        previewWrap.innerHTML='';
        previewWrap.appendChild(tag);
      }
      if(placeholder) placeholder.remove();
      tag.src=url;
      tag.alt='Selected job image preview';
    });
  }

  // Status change AJAX inline
  const statusMenu = document.getElementById('statusMenu');
  const pill = document.getElementById('statusPill');
  const live = document.getElementById('editLive');
  function rebuildMenu(current){
    const opts=['Open','Suspended','Closed'];
    statusMenu.innerHTML='';
    opts.filter(o=>o!==current).forEach(o=>{
      const li=document.createElement('li');
      const a=document.createElement('a');
      a.className='dropdown-item status-change';
      a.href=`jobs_status.php?ajax=1&job_id=${statusMenu.dataset.jobId}&to=${encodeURIComponent(o)}&csrf=<?php echo urlencode($editCsrf); ?>`;
      a.dataset.status=o;
      a.textContent = o==='Open'?'Set Open':(o==='Suspended'?'Suspend':'Close');
      li.appendChild(a); statusMenu.appendChild(li);
    });
  }
  document.addEventListener('click', function(e){
    const link = e.target.closest('.status-change');
    if(!link) return;
    if(!link.href.includes('jobs_status.php')) return;
    e.preventDefault();
    const url = new URL(link.href, window.location.origin);
    fetch(url.toString(), {credentials:'same-origin'})
      .then(r=>r.json())
      .then(data=>{
        if(!data.ok) throw new Error(data.error||'Failed');
        const st=data.current;
        if(pill){ pill.textContent=st; }
        rebuildMenu(st);
        live.textContent = 'Status updated to ' + st;
      })
      .catch(err=>{ live.textContent = 'Status update failed: ' + err.message; });
  });

})();
</script>