<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';
require_once '../classes/Experience.php';
require_once '../classes/Certification.php';
require_once '../classes/ProfileCompleteness.php';
require_once '../classes/Taxonomy.php';
require_once '../classes/Sensitive.php';

Helpers::requireLogin();
Helpers::requireRole('job_seeker');
$user = User::findById($_SESSION['user_id']);
if (!$user) Helpers::redirect('login.php');

$errors = [];
$flashRedirect = null; // where to redirect after successful sub-action

/* ---------------------------
   EXPERIENCE ADD
---------------------------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form'] ?? '')==='add_experience') {
    $expData = [
        'company'     => trim($_POST['exp_company'] ?? ''),
        'position'    => trim($_POST['exp_position'] ?? ''),
        'start_date'  => $_POST['exp_start_date'] ?? '',
        'end_date'    => $_POST['exp_end_date'] ?? '',
        'is_current'  => isset($_POST['exp_is_current']) ? 1 : 0,
        'description' => trim($_POST['exp_description'] ?? '')
    ];
    if (!$expData['company'] || !$expData['position'] || !$expData['start_date']) {
        $errors[] = 'Company, Position, and Start Date are required for experience.';
    } else {
        if (!Experience::create($user->user_id, $expData)) {
            $errors[] = 'Failed to add experience.';
        } else {
            ProfileCompleteness::compute($user->user_id);
            Helpers::flash('msg','Experience added.');
            $flashRedirect = 'profile_edit.php#tab-employment';
        }
    }
}

/* EXPERIENCE DELETE */
if (isset($_GET['del_exp'])) {
    Experience::delete($user->user_id, (int)$_GET['del_exp']);
    ProfileCompleteness::compute($user->user_id);
    Helpers::flash('msg','Experience removed.');
    Helpers::redirect('profile_edit.php#tab-employment');
}

/* ---------------------------
   CERTIFICATION ADD
---------------------------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form'] ?? '')==='add_cert') {
    $attachmentPath = null;
    if (!empty($_FILES['cert_attachment']['name'])) {
        $ext = strtolower(pathinfo($_FILES['cert_attachment']['name'], PATHINFO_EXTENSION));
        if (!is_dir('../uploads/certs')) @mkdir('../uploads/certs',0775,true);
        $fname = 'uploads/certs/' . uniqid('cert_') . '.' . $ext;
        if (move_uploaded_file($_FILES['cert_attachment']['tmp_name'], '../'.$fname)) {
            $attachmentPath = $fname;
        } else {
            $errors[] = 'Failed to upload certification attachment.';
        }
    }

    $certData = [
        'name'           => trim($_POST['cert_name'] ?? ''),
        'issuer'         => trim($_POST['cert_issuer'] ?? ''),
        'issued_date'    => $_POST['cert_issued_date'] ?? '',
        'expiry_date'    => $_POST['cert_expiry_date'] ?? '',
        'credential_id'  => trim($_POST['cert_credential_id'] ?? ''),
        'attachment_path'=> $attachmentPath
    ];

    if (!$certData['name']) {
        $errors[] = 'Certification name is required.';
    } else {
        if (!Certification::create($user->user_id, $certData)) {
            $errors[] = 'Failed to add certification.';
        } else {
            ProfileCompleteness::compute($user->user_id);
            Helpers::flash('msg','Certification added.');
            $flashRedirect = 'profile_edit.php#tab-employment';
        }
    }
}

/* CERT DELETE */
if (isset($_GET['del_cert'])) {
    Certification::delete($user->user_id, (int)$_GET['del_cert']);
    ProfileCompleteness::compute($user->user_id);
    Helpers::flash('msg','Certification removed.');
    Helpers::redirect('profile_edit.php#tab-employment');
}

/* ---------------------------
   MAIN PROFILE UPDATE
---------------------------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form'] ?? '')==='profile') {
    $update = [];

    $update['name']        = trim($_POST['name'] ?? $user->name);
    $update['disability']  = trim($_POST['disability'] ?? $user->disability);

    $update['date_of_birth']        = $_POST['date_of_birth'] ?: null;
    $update['gender']               = $_POST['gender'] ?: null;
    $update['phone']                = trim($_POST['phone'] ?? '');
    $update['region']               = trim($_POST['region'] ?? '');
    $update['province']             = trim($_POST['province'] ?? '');
    $update['city']                 = trim($_POST['city'] ?? '');
    $update['full_address']         = trim($_POST['full_address'] ?? '');
  // Education level: only accept if in taxonomy list (else blank)
  $educationLevels = Taxonomy::educationLevels();
  $inputEdu = trim($_POST['education_level'] ?? '');
  $update['education_level'] = in_array($inputEdu, $educationLevels, true) ? $inputEdu : '';
    $update['primary_skill_summary']= trim($_POST['primary_skill_summary'] ?? '');
  // Disability type (handle __other sentinel)
  $rawDisType = $_POST['disability_type'] ?? '';
  if ($rawDisType === '__other') {
    $rawDisType = trim($_POST['disability_type_other'] ?? '');
  }
  $update['disability_type'] = trim($rawDisType);
  // Enforce: Job seekers must have a specific disability type (no None/empty). If __other used, text is required.
  if (Helpers::isJobSeeker()) {
    if ($update['disability_type'] === '' || strcasecmp($update['disability_type'], 'None') === 0) {
      $errors[] = 'Please select your physical disability (None is not allowed for Job Seeker accounts).';
    }
    if (isset($_POST['disability_type']) && $_POST['disability_type'] === '__other') {
      if ($update['disability_type'] === '' || mb_strlen($update['disability_type']) < 3 || mb_strlen($update['disability_type']) > 120) {
        $errors[] = 'Please provide a valid disability type description (3–120 characters).';
      }
    }
  }
    $update['disability_severity']  = $_POST['disability_severity'] ?: null;
    $update['assistive_devices']    = trim($_POST['assistive_devices'] ?? '');

    // PWD ID: allow setting ONLY if not already stored
    if (!$user->pwd_id_last4) {
      if (isset($_POST['pwd_id_number']) && $_POST['pwd_id_number'] !== '') {
        $raw = preg_replace('/\s+/', '', $_POST['pwd_id_number']);
        if (strlen($raw) >= 4 && strlen($raw) <= 64) {
          $update['pwd_id_last4'] = substr($raw, -4);
          $enc = Sensitive::encrypt($raw);
          if ($enc) {
            $update['pwd_id_number'] = $enc;
          }
        } else {
          $errors[] = 'PWD ID length invalid (must be 4-64 chars).';
        }
      }
    }

    // Resume Upload
    if (!empty($_FILES['resume']['name'])) {
        if ($_FILES['resume']['type'] === 'application/pdf') {
            if (!is_dir('../uploads/resumes')) @mkdir('../uploads/resumes',0775,true);
            $rName = 'uploads/resumes/' . uniqid('res_') . '.pdf';
            if (move_uploaded_file($_FILES['resume']['tmp_name'], '../'.$rName)) {
                $update['resume'] = $rName;
            } else {
                $errors[] = 'Failed to upload resume.';
            }
        } else {
            $errors[] = 'Resume must be a PDF.';
        }
    }

    // Video Upload
    if (!empty($_FILES['video_intro']['name'])) {
        $allowedVid = ['video/mp4','video/webm','video/ogg'];
        if (in_array($_FILES['video_intro']['type'], $allowedVid, true)) {
            if (!is_dir('../uploads/videos')) @mkdir('../uploads/videos',0775,true);
            $ext = strtolower(pathinfo($_FILES['video_intro']['name'], PATHINFO_EXTENSION));
            $vName = 'uploads/videos/' . uniqid('vid_') . '.' . $ext;
            if (move_uploaded_file($_FILES['video_intro']['tmp_name'], '../'.$vName)) {
                $update['video_intro'] = $vName;
            } else {
                $errors[] = 'Failed to upload video.';
            }
        } else {
            $errors[] = 'Invalid video format.';
        }
    }

  // Profile Picture Upload (image)
  if (!empty($_FILES['profile_picture']['name'])) {
    $allowedImg = ['image/jpeg','image/png','image/gif'];
    if (in_array($_FILES['profile_picture']['type'], $allowedImg, true)) {
      if ($_FILES['profile_picture']['size'] <= 2*1024*1024) { // 2MB limit
        if (!is_dir('../uploads/profile')) @mkdir('../uploads/profile',0775,true);
        $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        if (!preg_match('/^(jpe?g|png|gif)$/',$ext)) {
          $errors[] = 'Invalid image extension.';
        } else {
          $pName = 'uploads/profile/' . uniqid('pf_') . '.' . $ext;
          if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], '../'.$pName)) {
            $update['profile_picture'] = $pName;
          } else {
            $errors[] = 'Failed to upload profile picture.';
          }
        }
      } else {
        $errors[] = 'Profile picture too large (max 2MB).';
      }
    } else {
      $errors[] = 'Invalid image format (JPG/PNG/GIF).';
    }
  }

    if (!$errors) {
        if (User::updateProfileExtended($user->user_id, $update)) {
            ProfileCompleteness::compute($user->user_id);
            Helpers::flash('msg','Profile updated.');
            Helpers::redirect('profile_edit.php');
        } else {
            $errors[] = 'Update failed.';
        }
    } else {
        // Para hindi mawala ang nilagay na values sa form kapag may error
        foreach ($update as $k => $v) {
            $user->$k = $v;
        }
    }
}

/* FLASH message retrieval */
$flashMsg = $_SESSION['flash']['msg'] ?? null;
if ($flashMsg) unset($_SESSION['flash']['msg']);

if ($flashRedirect && !$errors) {
    Helpers::redirect($flashRedirect);
}

/* Lists */
$experiences = Experience::listByUser($user->user_id);
$certs       = Certification::listByUser($user->user_id);

include '../includes/header.php';
include '../includes/nav.php';
?>
<style>
  .nav-tabs .nav-link { font-size:.85rem; }
  textarea { resize:vertical; }
  .exp-item:hover { background:#f8f9fa; }
  .age-badge { font-size:.7rem; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap mb-3 fade-up fade-delay-1" style="padding-left:.25rem;">
  <h1 class="h4 fw-bold mb-2 mb-lg-0" style="letter-spacing:-.5px; margin-left:4px;">
    Edit Profile
  </h1>
  <a class="btn btn-outline-secondary btn-sm" href="user_dashboard.php"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<?php if ($flashMsg): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-1"></i><?php echo htmlspecialchars($flashMsg); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle me-1"></i><?php echo htmlspecialchars($e); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="pe-shell mb-4 fade-up fade-delay-2" id="profileEditForm">
  <input type="hidden" name="form" value="profile">
  <!-- LEFT COLUMN -->
  <div class="pe-card">
    <div class="pe-card-inner">
      <h2 class="pe-head"><span class="icon"><i class="bi bi-person"></i></span>Basic Information</h2>
      <div class="d-flex flex-wrap gap-4 align-items-start mb-3">
        <div class="avatar-upload-zone" id="avatarZone">
          <div class="preview" id="avatarPreview">
            <?php if (!empty($user->profile_picture)): ?>
              <img src="../<?php echo htmlspecialchars($user->profile_picture); ?>" alt="Current profile picture">
            <?php else: ?>
              <i class="bi bi-person"></i>
            <?php endif; ?>
          </div>
          <label class="avatar-upload-trigger" for="profilePictureInput"><i class="bi bi-upload"></i>Upload</label>
          <input type="file" id="profilePictureInput" name="profile_picture" accept="image/*">
          <div class="form-text mt-1">JPG/PNG/GIF max 2MB</div>
        </div>
        <div class="flex-grow-1" style="min-width:260px;">
          <div class="row g-3">
            <div class="col-sm-8">
              <label class="form-label">Full Name</label>
              <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user->name); ?>" required>
            </div>
            <div class="col-sm-4">
              <label class="form-label">Gender</label>
              <select name="gender" class="form-select">
                <option value="">Select</option>
                <?php foreach (['Male','Female','Non-binary','Prefer not to say'] as $g): ?>
                  <option value="<?php echo $g; ?>" <?php if ($user->gender===$g) echo 'selected'; ?>><?php echo $g; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-5">
              <label class="form-label">Date of Birth</label>
              <div class="d-flex align-items-center gap-2"><input type="date" name="date_of_birth" id="dobInput" class="form-control" value="<?php echo htmlspecialchars($user->date_of_birth); ?>"><span id="ageBadge" class="badge text-bg-secondary age-badge"></span></div>
            </div>
            <div class="col-sm-7">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user->phone); ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Region</label>
              <input type="text" name="region" class="form-control" value="<?php echo htmlspecialchars($user->region); ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Province</label>
              <input type="text" name="province" class="form-control" value="<?php echo htmlspecialchars($user->province); ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">City</label>
              <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($user->city); ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Full Address</label>
              <input type="text" name="full_address" class="form-control" value="<?php echo htmlspecialchars($user->full_address); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Educational Attainment</label>
              <select name="education_level" class="form-select">
                <option value="">Select</option>
                <?php foreach (Taxonomy::educationLevels() as $lvl): ?>
                  <option value="<?php echo htmlspecialchars($lvl); ?>" <?php if ($user->education_level===$lvl) echo 'selected'; ?>><?php echo htmlspecialchars($lvl); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label d-flex justify-content-between align-items-center">Primary Skill Summary / Bio <span class="char-counter" id="summaryCounter">0 / 600</span></label>
              <textarea name="primary_skill_summary" id="summaryInput" class="form-control" rows="4" maxlength="600" aria-describedby="summaryHelp"><?php echo htmlspecialchars($user->primary_skill_summary); ?></textarea>
              <div id="summaryHelp" class="form-text">Highlight strengths, roles, tools, and impact (max 600 characters).</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT COLUMN -->
  <div class="pe-card">
    <div class="pe-card-inner">
      <h2 class="pe-head"><span class="icon"><i class="bi bi-activity"></i></span>Disability & Support</h2>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Disability (short desc)</label>
          <input type="text" name="disability" class="form-control" value="<?php echo htmlspecialchars($user->disability); ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Disability Type</label>
          <?php
            $disabilityTypes = [
              'Spinal Cord Injury',
              'Musculoskeletal Condition (e.g., cerebral palsy, muscular dystrophy)',
              'Amputee (lower limb)',
              'Neurological Condition (e.g., multiple sclerosis, spina bifida)',
              'Other Physical Disability'
            ];
            $curDisType = $user->disability_type;
            $isOther = $curDisType && !in_array($curDisType,$disabilityTypes,true);
          ?>
          <select name="disability_type" id="disabilityTypeSelect" class="form-select mb-2">
            <option value="">Select</option>
            <?php foreach($disabilityTypes as $dt): ?>
              <option value="<?php echo $dt==='Other Physical Disability'?'__other':htmlspecialchars($dt); ?>" <?php if(($dt!=='Other Physical Disability' && $curDisType===$dt) || ($dt==='Other Physical Disability' && $isOther)) echo 'selected'; ?>><?php echo htmlspecialchars($dt); ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="disability_type_other" id="disabilityTypeOther" class="form-control" placeholder="Specify other type" value="<?php echo $isOther?htmlspecialchars($curDisType):''; ?>" style="display: <?php echo $isOther?'block':'none'; ?>;">
        </div>
        <div class="col-md-4">
          <label class="form-label">Severity</label>
          <select name="disability_severity" class="form-select">
            <option value="">Select</option>
            <?php foreach (['Mild','Moderate','Severe'] as $sev): ?>
              <option value="<?php echo $sev; ?>" <?php if ($user->disability_severity===$sev) echo 'selected'; ?>><?php echo $sev; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-8">
          <label class="form-label">Assistive Devices (comma separated)</label>
          <input type="text" name="assistive_devices" class="form-control" value="<?php echo htmlspecialchars($user->assistive_devices); ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">PWD ID Number</label>
          <?php if ($user->pwd_id_last4): ?>
            <div class="form-control bg-light" tabindex="-1" style="cursor:not-allowed;">****<?php echo htmlspecialchars($user->pwd_id_last4); ?> (locked)</div>
            <div class="form-text">Cannot be changed after saving.</div>
          <?php else: ?>
            <input type="text" name="pwd_id_number" class="form-control" placeholder="Enter your PWD ID for verification" autocomplete="off">
            <div class="form-text">Encrypted & locked after saving.</div>
          <?php endif; ?>
        </div>
      </div>
      <div class="mini-divider"></div>
      <h2 class="pe-head" style="margin-top:.25rem;"><span class="icon"><i class="bi bi-folder2-open"></i></span>Documents</h2>
      <div class="row g-3">
        <div class="col-md-6">
          <div class="file-block" data-target="resumeInput" id="resumeBlock">
            <span class="fb-icon"><i class="bi bi-file-earmark-text"></i></span>
            <span class="fb-label">Resume (PDF)</span>
            <small>Click or drop PDF here</small>
            <input type="file" id="resumeInput" name="resume" accept="application/pdf">
            <div class="file-feedback" id="resumeFeedback"><?php if ($user->resume): ?>Current: <a href="../<?php echo htmlspecialchars($user->resume); ?>" target="_blank">View</a><?php endif; ?></div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="file-block" data-target="videoInput" id="videoBlock">
            <span class="fb-icon"><i class="bi bi-camera-video"></i></span>
            <span class="fb-label">Video Intro</span>
            <small>MP4/WebM/Ogg</small>
            <input type="file" id="videoInput" name="video_intro" accept="video/*">
            <div class="file-feedback" id="videoFeedback"><?php if ($user->video_intro): ?>Current: <a href="../<?php echo htmlspecialchars($user->video_intro); ?>" target="_blank">View</a><?php endif; ?></div>
          </div>
        </div>
      </div>
      <div class="sticky-save pe-actions">
        <button class="btn btn-gradient"><i class="bi bi-save me-1" aria-hidden="true"></i>Save Changes</button>
        <a href="user_dashboard.php" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </div>
  </div>
</form>

<div class="pe-card mb-4 fade-up fade-delay-3" id="employment-section">
  <div class="pe-card-inner">
    <h2 class="pe-head"><span class="icon"><i class="bi bi-briefcase"></i></span>Work Experience</h2>

    <?php if ($experiences): ?>
      <ul class="list-modern mb-3">
        <?php foreach ($experiences as $exp): ?>
          <li class="d-flex justify-content-between gap-3">
            <div class="flex-grow-1">
              <div class="fw-semibold mb-1"><?php echo htmlspecialchars($exp['position']); ?> <span class="text-muted">@ <?php echo htmlspecialchars($exp['company']); ?></span></div>
              <div class="small text-muted mb-1">
                <?php $sd = htmlspecialchars($exp['start_date']); $ed = $exp['is_current'] ? 'Present' : ($exp['end_date'] ?: '—'); echo $sd . ' - ' . htmlspecialchars($ed); ?>
              </div>
              <?php if (!empty($exp['description'])): ?><div class="small text-muted"><?php echo nl2br(htmlspecialchars($exp['description'])); ?></div><?php endif; ?>
            </div>
            <div class="list-modern-actions">
              <a class="btn btn-sm btn-outline-danger" href="profile_edit.php?del_exp=<?php echo (int)$exp['id']; ?>" onclick="return confirm('Delete this experience?')"><i class="bi bi-trash"></i></a>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?><div class="text-muted small mb-3">No experience added yet.</div><?php endif; ?>

    <div class="add-inline-form mb-4">
      <h3>Add Experience</h3>
      <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="form" value="add_experience">
        <div class="col-md-4 col-sm-6">
          <input type="text" name="exp_company" class="form-control form-control-sm" placeholder="Company *" required>
        </div>
        <div class="col-md-4 col-sm-6">
          <input type="text" name="exp_position" class="form-control form-control-sm" placeholder="Position *" required>
        </div>
        <div class="col-md-2">
          <input type="date" name="exp_start_date" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-2">
          <input type="date" name="exp_end_date" class="form-control form-control-sm">
        </div>
        <div class="col-12 d-flex align-items-center gap-2">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="exp_is_current" id="exp_is_current">
            <label class="form-check-label small" for="exp_is_current">Current</label>
          </div>
          <input type="text" name="exp_description" class="form-control form-control-sm" placeholder="Short description">
          <button class="btn btn-sm btn-gradient" title="Add Experience"><i class="bi bi-plus-lg"></i></button>
        </div>
      </form>
    </div>

    <h2 class="pe-head"><span class="icon"><i class="bi bi-patch-check"></i></span>Certifications</h2>
    <?php if ($certs): ?>
      <ul class="list-modern mb-3">
        <?php foreach ($certs as $ct): ?>
          <li class="d-flex justify-content-between gap-3">
            <div class="flex-grow-1">
              <div class="fw-semibold mb-1"><?php echo htmlspecialchars($ct['name']); ?><?php if ($ct['issuer']): ?> <span class="text-muted">· <?php echo htmlspecialchars($ct['issuer']); ?></span><?php endif; ?></div>
              <div class="small text-muted mb-1">Issued: <?php echo $ct['issued_date']?htmlspecialchars($ct['issued_date']):'—'; ?> | Expiry: <?php echo $ct['expiry_date']?htmlspecialchars($ct['expiry_date']):'None'; ?></div>
              <?php if ($ct['credential_id']): ?><div class="small text-muted">Credential: <?php echo htmlspecialchars($ct['credential_id']); ?></div><?php endif; ?>
              <?php if ($ct['attachment_path']): ?><div class="small"><a class="text-decoration-none" href="../<?php echo htmlspecialchars($ct['attachment_path']); ?>" target="_blank"><i class="bi bi-paperclip me-1"></i>Attachment</a></div><?php endif; ?>
            </div>
            <div class="list-modern-actions">
              <a class="btn btn-sm btn-outline-danger" href="profile_edit.php?del_cert=<?php echo (int)$ct['id']; ?>" onclick="return confirm('Delete this certification?')"><i class="bi bi-trash"></i></a>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?><div class="text-muted small mb-3">No certifications added yet.</div><?php endif; ?>

    <div class="add-inline-form">
      <h3>Add Certification</h3>
      <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
        <input type="hidden" name="form" value="add_cert">
        <div class="col-md-4 col-sm-6">
          <input type="text" name="cert_name" class="form-control form-control-sm" placeholder="Name *" required>
        </div>
        <div class="col-md-3">
          <input type="text" name="cert_issuer" class="form-control form-control-sm" placeholder="Issuer">
        </div>
        <div class="col-md-2">
          <input type="date" name="cert_issued_date" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
          <input type="date" name="cert_expiry_date" class="form-control form-control-sm">
        </div>
        <div class="col-md-3">
          <input type="text" name="cert_credential_id" class="form-control form-control-sm" placeholder="Credential ID">
        </div>
        <div class="col-md-3">
          <input type="file" name="cert_attachment" class="form-control form-control-sm">
        </div>
        <div class="col-md-2 d-grid">
          <button class="btn btn-sm btn-gradient"><i class="bi bi-plus"></i> Add</button>
        </div>
      </form>
    </div>

  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
(function(){
  const dobInput = document.getElementById('dobInput');
  const ageBadge = document.getElementById('ageBadge');
  const summaryInput = document.getElementById('summaryInput');
  const summaryCounter = document.getElementById('summaryCounter');
  const disabilityTypeSelect = document.getElementById('disabilityTypeSelect');
  const disabilityTypeOther = document.getElementById('disabilityTypeOther');
  const avatarInput = document.getElementById('profilePictureInput');
  const avatarPreview = document.getElementById('avatarPreview');
  const avatarZone = document.getElementById('avatarZone');
  const resumeBlock = document.getElementById('resumeBlock');
  const videoBlock = document.getElementById('videoBlock');
  const resumeInput = document.getElementById('resumeInput');
  const videoInput = document.getElementById('videoInput');
  const resumeFeedback = document.getElementById('resumeFeedback');
  const videoFeedback = document.getElementById('videoFeedback');

  function calcAge() {
    if (!dobInput || !ageBadge || !dobInput.value) { ageBadge && (ageBadge.textContent=''); return; }
    const dob = new Date(dobInput.value);
    if (isNaN(dob.getTime())) { ageBadge.textContent=''; return; }
    const diff = Date.now() - dob.getTime();
    const ageDate = new Date(diff);
    const age = Math.abs(ageDate.getUTCFullYear() - 1970);
    ageBadge.textContent = age + ' yrs';
  }
  dobInput && dobInput.addEventListener('change', calcAge);
  calcAge();

  function updateCounter(){
    if(!summaryInput || !summaryCounter) return;
    const val = summaryInput.value.trim();
    summaryCounter.textContent = val.length + ' / 600';
    summaryCounter.classList.toggle('text-danger', val.length > 600);
  }
  summaryInput && ['input','change'].forEach(ev=>summaryInput.addEventListener(ev, updateCounter));
  updateCounter();

  disabilityTypeSelect && disabilityTypeSelect.addEventListener('change', function(){
    if (this.value === '__other') {
      disabilityTypeOther.style.display='block';
      disabilityTypeOther.focus();
    } else {
      disabilityTypeOther.style.display='none';
      disabilityTypeOther.value='';
    }
  });

  // Avatar preview & drag highlight
  function handleAvatarFile(file){
    if(!file || !file.type.match(/^image\//)) return;
    const reader = new FileReader();
    reader.onload = e => {
      avatarPreview.innerHTML = '<img src="'+e.target.result+'" alt="New profile preview">';
    };
    reader.readAsDataURL(file);
  }
  avatarInput && avatarInput.addEventListener('change', e=>{
    handleAvatarFile(e.target.files[0]);
  });
  ['dragenter','dragover'].forEach(ev=>{
    avatarZone && avatarZone.addEventListener(ev, e=>{e.preventDefault(); avatarZone.classList.add('dragging');});
  });
  ['dragleave','drop'].forEach(ev=>{
    avatarZone && avatarZone.addEventListener(ev, e=>{e.preventDefault(); avatarZone.classList.remove('dragging');});
  });
  avatarZone && avatarZone.addEventListener('drop', e=>{
    if(e.dataTransfer.files && e.dataTransfer.files[0]){
      avatarInput.files = e.dataTransfer.files;
      handleAvatarFile(e.dataTransfer.files[0]);
    }
  });

  function wireFileBlock(block, input, feedback){
    if(!block || !input) return;
    block.addEventListener('click', () => input.click());
    input.addEventListener('change', ()=>{
      if(!input.files[0]) return;
      feedback && (feedback.innerHTML = 'Selected: <strong>'+ input.files[0].name +'</strong>');
    });
    ['dragenter','dragover'].forEach(ev=>block.addEventListener(ev, e=>{e.preventDefault(); block.classList.add('dragging');}));
    ['dragleave','drop'].forEach(ev=>block.addEventListener(ev, e=>{e.preventDefault(); block.classList.remove('dragging');}));
    block.addEventListener('drop', e=>{
      if(e.dataTransfer.files && e.dataTransfer.files[0]){
        input.files = e.dataTransfer.files;
        const f = e.dataTransfer.files[0];
        feedback && (feedback.innerHTML = 'Selected: <strong>'+ f.name +'</strong>');
      }
    });
  }
  wireFileBlock(resumeBlock, resumeInput, resumeFeedback);
  wireFileBlock(videoBlock, videoInput, videoFeedback);
})();
</script>