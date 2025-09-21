<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';
require_once '../classes/Experience.php';
require_once '../classes/Certification.php';
require_once '../classes/ProfileCompleteness.php';

Helpers::requireLogin();
$user = User::findById($_SESSION['user_id']);
if (!$user) Helpers::redirect('login.php');

$errors = [];

/* Experience Add/Delete */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='add_experience') {
    $expData = [
        'company' => trim($_POST['exp_company'] ?? ''),
        'position'=> trim($_POST['exp_position'] ?? ''),
        'start_date'=> $_POST['exp_start_date'] ?? '',
        'end_date'=> $_POST['exp_end_date'] ?? '',
        'is_current'=> isset($_POST['exp_is_current']) ? 1 : 0,
        'description'=> trim($_POST['exp_description'] ?? '')
    ];
    if (!Experience::create($user->user_id, $expData)) {
        $errors[] = 'Failed to add experience.';
    } else {
        ProfileCompleteness::compute($user->user_id);
        Helpers::flash('msg','Experience added.');
        Helpers::redirect('profile_edit.php#tab-employment');
    }
}
if (isset($_GET['del_exp'])) {
    Experience::delete($user->user_id, (int)$_GET['del_exp']);
    ProfileCompleteness::compute($user->user_id);
    Helpers::flash('msg','Experience removed.');
    Helpers::redirect('profile_edit.php#tab-employment');
}

/* Certification Add/Delete */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='add_cert') {
    $attachmentPath = null;
    if (!empty($_FILES['cert_attachment']['name'])) {
        $ext = strtolower(pathinfo($_FILES['cert_attachment']['name'], PATHINFO_EXTENSION));
        if (!is_dir('../uploads/certs')) @mkdir('../uploads/certs',0775,true);
        $fname = 'uploads/certs/' . uniqid('cert_') . '.' . $ext;
        if (move_uploaded_file($_FILES['cert_attachment']['tmp_name'], '../'.$fname)) {
            $attachmentPath = $fname;
        }
    }
    $certData = [
        'name' => trim($_POST['cert_name'] ?? ''),
        'issuer' => trim($_POST['cert_issuer'] ?? ''),
        'issued_date' => $_POST['cert_issued_date'] ?? '',
        'expiry_date' => $_POST['cert_expiry_date'] ?? '',
        'credential_id' => trim($_POST['cert_credential_id'] ?? ''),
        'attachment_path' => $attachmentPath
    ];
    if (!Certification::create($user->user_id, $certData)) {
        $errors[] = 'Failed to add certification.';
    } else {
        ProfileCompleteness::compute($user->user_id);
        Helpers::flash('msg','Certification added.');
        Helpers::redirect('profile_edit.php#tab-employment');
    }
}
if (isset($_GET['del_cert'])) {
    Certification::delete($user->user_id, (int)$_GET['del_cert']);
    ProfileCompleteness::compute($user->user_id);
    Helpers::flash('msg','Certification removed.');
    Helpers::redirect('profile_edit.php#tab-employment');
}

/* Main Profile Update */
if ($_SERVER['REQUEST_METHOD']==='POST' && empty($_POST['action'])) {
    $update = [];
    $update['name'] = trim($_POST['name'] ?? $user->name);
    $update['disability'] = trim($_POST['disability'] ?? $user->disability);

    $update['date_of_birth'] = $_POST['date_of_birth'] ?: null;
    $update['gender'] = $_POST['gender'] ?: null;
    $update['phone'] = trim($_POST['phone'] ?? '');
    $update['region'] = trim($_POST['region'] ?? '');
    $update['province'] = trim($_POST['province'] ?? '');
    $update['city'] = trim($_POST['city'] ?? '');
    $update['full_address'] = trim($_POST['full_address'] ?? '');
    $update['education_level'] = trim($_POST['education_level'] ?? '');
    $update['primary_skill_summary'] = trim($_POST['primary_skill_summary'] ?? '');
    $update['disability_type'] = trim($_POST['disability_type'] ?? '');
    $update['disability_severity'] = $_POST['disability_severity'] ?: null;
    $update['assistive_devices'] = trim($_POST['assistive_devices'] ?? '');

    if (!empty($_POST['pwd_id_number'])) {
        $raw = preg_replace('/\s+/', '', $_POST['pwd_id_number']);
        if (strlen($raw) >= 4) {
            $update['pwd_id_last4'] = substr($raw, -4);
            // TODO: encryption
            $update['pwd_id_number'] = $raw;
        }
    }

    // Resume
    if (!empty($_FILES['resume']['name'])) {
        if ($_FILES['resume']['type'] === 'application/pdf') {
            if (!is_dir('../uploads/resumes')) @mkdir('../uploads/resumes',0775,true);
            $rName = 'uploads/resumes/' . uniqid('res_') . '.pdf';
            if (move_uploaded_file($_FILES['resume']['tmp_name'], '../'.$rName)) {
                $update['resume'] = $rName;
            } else $errors[]='Failed to upload resume.';
        } else $errors[]='Resume must be a PDF.';
    }

    // Video
    if (!empty($_FILES['video_intro']['name'])) {
        $allowedVid = ['video/mp4','video/webm','video/ogg'];
        if (in_array($_FILES['video_intro']['type'],$allowedVid,true)) {
            if (!is_dir('../uploads/videos')) @mkdir('../uploads/videos',0775,true);
            $ext = strtolower(pathinfo($_FILES['video_intro']['name'], PATHINFO_EXTENSION));
            $vName = 'uploads/videos/' . uniqid('vid_') . '.' . $ext;
            if (move_uploaded_file($_FILES['video_intro']['tmp_name'], '../'.$vName)) {
                $update['video_intro'] = $vName;
            } else $errors[]='Failed to upload video.';
        } else $errors[]='Invalid video format.';
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
        foreach ($update as $k=>$v) $user->$k = $v;
    }
}

$flashMsg = $_SESSION['flash']['msg'] ?? null;
if ($flashMsg) unset($_SESSION['flash']['msg']);

$experiences = Experience::listByUser($user->user_id);
$certs = Certification::listByUser($user->user_id);

include '../includes/header.php';
include '../includes/nav.php';
?>
<style>
.nav-tabs .nav-link { font-size:.85rem; }
textarea { resize:vertical; }
.exp-item:hover { background:#f8f9fa; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
  <h1 class="h5 fw-semibold mb-2 mb-lg-0">
    <i class="bi bi-person-badge me-2"></i>Edit Profile
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

<form method="post" enctype="multipart/form-data" class="card border-0 shadow-sm mb-4">
  <div class="card-body p-4">
    <ul class="nav nav-tabs mb-3" role="tablist">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-basic" type="button">Basic Info</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-disability" type="button">Disability</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-employment" type="button">Employment & Skills</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-docs" type="button">Documents</button></li>
    </ul>

    <div class="tab-content">
      <!-- BASIC -->
      <div class="tab-pane fade show active" id="tab-basic">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user->name); ?>" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Date of Birth</label>
            <input type="date" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($user->date_of_birth); ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Gender</label>
            <select name="gender" class="form-select">
              <option value="">Select</option>
              <?php foreach (['Male','Female','Non-binary','Prefer not to say'] as $g): ?>
                <option value="<?php echo $g; ?>" <?php if($user->gender===$g) echo 'selected'; ?>><?php echo $g; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
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
          <div class="col-md-6">
            <label class="form-label">City</label>
            <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($user->city); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Full Address</label>
            <input type="text" name="full_address" class="form-control" value="<?php echo htmlspecialchars($user->full_address); ?>">
          </div>
        </div>
      </div>

      <!-- DISABILITY -->
      <div class="tab-pane fade" id="tab-disability">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Disability (short desc)</label>
            <input type="text" name="disability" class="form-control" value="<?php echo htmlspecialchars($user->disability); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Disability Type</label>
            <input type="text" name="disability_type" class="form-control" value="<?php echo htmlspecialchars($user->disability_type); ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Severity</label>
            <select name="disability_severity" class="form-select">
              <option value="">Select</option>
              <?php foreach (['Mild','Moderate','Severe'] as $sev): ?>
                <option value="<?php echo $sev; ?>" <?php if($user->disability_severity===$sev) echo 'selected'; ?>><?php echo $sev; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label">Assistive Devices (comma separated)</label>
            <input type="text" name="assistive_devices" class="form-control" value="<?php echo htmlspecialchars($user->assistive_devices); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">PWD ID Number (optional)</label>
            <input type="text" name="pwd_id_number" class="form-control" placeholder="Enter or leave blank">
            <?php if ($user->pwd_id_last4): ?>
              <div class="form-text">Stored ending in ****<?php echo htmlspecialchars($user->pwd_id_last4); ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- EMPLOYMENT & SKILLS -->
      <div class="tab-pane fade" id="tab-employment">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Education Level</label>
            <input type="text" name="education_level" class="form-control" value="<?php echo htmlspecialchars($user->education_level); ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Primary Skill Summary / Bio</label>
            <textarea name="primary_skill_summary" class="form-control" rows="4"><?php echo htmlspecialchars($user->primary_skill_summary); ?></textarea>
          </div>

          <div class="col-12"><hr></div>

          <div class="col-12">
            <h6 class="fw-semibold mb-2"><i class="bi bi-briefcase me-1"></i>Work Experience</h6>
            <?php if ($experiences): ?>
              <ul class="list-group mb-3">
                <?php foreach ($experiences as $exp): ?>
                  <li class="list-group-item d-flex justify-content-between align-items-start exp-item">
                    <div>
                      <div class="fw-semibold"><?php echo htmlspecialchars($exp['position']); ?> @ <?php echo htmlspecialchars($exp['company']); ?></div>
                      <div class="small text-muted">
                        <?php
                          $sd = htmlspecialchars($exp['start_date']);
                          $ed = $exp['is_current'] ? 'Present' : ($exp['end_date'] ?: '—');
                          echo $sd . ' - ' . htmlspecialchars($ed);
                        ?>
                      </div>
                      <?php if (!empty($exp['description'])): ?>
                        <div class="small mt-1"><?php echo nl2br(htmlspecialchars($exp['description'])); ?></div>
                      <?php endif; ?>
                    </div>
                    <a class="btn btn-sm btn-outline-danger"
                       href="profile_edit.php?del_exp=<?php echo (int)$exp['id']; ?>"
                       onclick="return confirm('Delete this experience?')">
                      <i class="bi bi-trash"></i>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="text-muted small mb-3">No experience added yet.</div>
            <?php endif; ?>

            <div class="border rounded p-3 mb-4">
              <h6 class="small fw-semibold text-uppercase mb-2">Add Experience</h6>
              <form method="post" class="row g-2">
                <input type="hidden" name="action" value="add_experience">
                <div class="col-md-4">
                  <input type="text" name="exp_company" class="form-control form-control-sm" placeholder="Company" required>
                </div>
                <div class="col-md-4">
                  <input type="text" name="exp_position" class="form-control form-control-sm" placeholder="Position" required>
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
                  <button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i></button>
                </div>
              </form>
            </div>

            <h6 class="fw-semibold mb-2"><i class="bi bi-patch-check me-1"></i>Certifications</h6>
            <?php if ($certs): ?>
              <ul class="list-group mb-3">
                <?php foreach ($certs as $ct): ?>
                  <li class="list-group-item d-flex justify-content-between align-items-start">
                    <div>
                      <div class="fw-semibold"><?php echo htmlspecialchars($ct['name']); ?></div>
                      <div class="small text-muted">
                        <?php
                          $issuer = $ct['issuer'] ?: 'Unknown issuer';
                          $issued = $ct['issued_date'] ?: '—';
                          $expiry = $ct['expiry_date'] ?: 'No expiry';
                          echo htmlspecialchars($issuer) . ' | ' . htmlspecialchars($issued) . ' - ' . htmlspecialchars($expiry);
                        ?>
                      </div>
                      <?php if ($ct['credential_id']): ?>
                        <div class="small">Credential: <?php echo htmlspecialchars($ct['credential_id']); ?></div>
                      <?php endif; ?>
                      <?php if ($ct['attachment_path']): ?>
                        <div class="small">
                          <a href="../<?php echo htmlspecialchars($ct['attachment_path']); ?>" target="_blank">
                            <i class="bi bi-paperclip"></i>Attachment
                          </a>
                        </div>
                      <?php endif; ?>
                    </div>
                    <a class="btn btn-sm btn-outline-danger"
                       href="profile_edit.php?del_cert=<?php echo (int)$ct['id']; ?>"
                       onclick="return confirm('Delete this certification?')">
                      <i class="bi bi-trash"></i>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="text-muted small mb-3">No certifications added yet.</div>
            <?php endif; ?>

            <div class="border rounded p-3">
              <h6 class="small fw-semibold text-uppercase mb-2">Add Certification</h6>
              <form method="post" enctype="multipart/form-data" class="row g-2">
                <input type="hidden" name="action" value="add_cert">
                <div class="col-md-4">
                  <input type="text" name="cert_name" class="form-control form-control-sm" placeholder="Name" required>
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
                  <button class="btn btn-sm btn-primary"><i class="bi bi-plus"></i> Add</button>
                </div>
              </form>
            </div>

          </div>
        </div>
      </div>

      <!-- DOCUMENTS -->
      <div class="tab-pane fade" id="tab-docs">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Resume (PDF)</label>
            <input type="file" name="resume" accept="application/pdf" class="form-control">
            <?php if ($user->resume): ?>
              <div class="form-text"><a href="../<?php echo htmlspecialchars($user->resume); ?>" target="_blank">Current resume</a></div>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label">Video Intro (MP4/WebM/Ogg)</label>
            <input type="file" name="video_intro" accept="video/*" class="form-control">
            <?php if ($user->video_intro): ?>
              <div class="form-text"><a href="../<?php echo htmlspecialchars($user->video_intro); ?>" target="_blank">Current video</a></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-4 d-flex gap-2">
      <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
      <a href="user_dashboard.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </div>
</form>

<?php include '../includes/footer.php'; ?>