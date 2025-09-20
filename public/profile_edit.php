<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';

Helpers::requireLogin();
$user = User::findById($_SESSION['user_id']);
if (!$user) Helpers::redirect('login.php');

$errors = [];
$justPosted = ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($justPosted) {
    $updateData = [
        'name'       => $_POST['name'],
        // Reuse disability column (job seeker: disability; employer: description)
        'disability' => $_POST['disability'] ?? null,
    ];

    if ($user->role === 'employer') {
        if (isset($_POST['company_name'])) {
            $updateData['company_name'] = trim($_POST['company_name']);
        }
        if (isset($_POST['business_email'])) {
            $bizEmail = trim($_POST['business_email']);
            if ($bizEmail === '' || filter_var($bizEmail, FILTER_VALIDATE_EMAIL)) {
                $updateData['business_email'] = $bizEmail;
            } else {
                $errors[] = 'Please enter a valid business email address.';
            }
        }
        if (isset($_POST['company_website'])) {
            $cweb = trim($_POST['company_website']);
            if ($cweb === '' || filter_var($cweb, FILTER_VALIDATE_URL)) {
                $updateData['company_website'] = $cweb;
            } else {
                $errors[] = 'Please enter a valid company website URL (including http:// or https://).';
            }
        }
        if (isset($_POST['company_phone'])) {
            $updateData['company_phone'] = trim($_POST['company_phone']);
        }
        if (isset($_POST['business_permit_number'])) {
            $updateData['business_permit_number'] = trim($_POST['business_permit_number']);
        }

        // Employer verification doc
        if (!empty($_FILES['employer_doc']['name'])) {
            $allowed = [
                'application/pdf' => 'pdf',
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];
            $mime = $_FILES['employer_doc']['type'] ?? '';
            if (isset($allowed[$mime])) {
                $ext = $allowed[$mime];
                $docName = 'uploads/employers/' . uniqid('empdoc_') . '.' . $ext;
                if (!is_dir('../uploads/employers')) {
                    @mkdir('../uploads/employers', 0775, true);
                }
                if (move_uploaded_file($_FILES['employer_doc']['tmp_name'], '../' . $docName)) {
                    $updateData['employer_doc'] = $docName;
                } else {
                    $errors[] = 'Failed to upload employer document.';
                }
            } else {
                $errors[] = 'Employer document must be a PDF or image (JPG/PNG/WEBP).';
            }
        }
    }

    // Resume (PDF)
    if (!empty($_FILES['resume']['name'])) {
        if ($_FILES['resume']['type'] === 'application/pdf') {
            $resumeName = 'uploads/resumes/' . uniqid('res_') . '.pdf';
            if (!is_dir('../uploads/resumes')) {
                @mkdir('../uploads/resumes', 0775, true);
            }
            if (move_uploaded_file($_FILES['resume']['tmp_name'], '../' . $resumeName)) {
                $updateData['resume'] = $resumeName;
            } else {
                $errors[] = 'Failed to upload resume.';
            }
        } else {
            $errors[] = 'Resume must be a PDF.';
        }
    }

    // Video intro
    if (!empty($_FILES['video_intro']['name'])) {
        $allowedVid = ['video/mp4','video/webm','video/ogg'];
        if (in_array($_FILES['video_intro']['type'], $allowedVid, true)) {
            $ext = pathinfo($_FILES['video_intro']['name'], PATHINFO_EXTENSION);
            $videoName = 'uploads/videos/' . uniqid('vid_') . '.' . $ext;
            if (!is_dir('../uploads/videos')) {
                @mkdir('../uploads/videos', 0775, true);
            }
            if (move_uploaded_file($_FILES['video_intro']['tmp_name'], '../' . $videoName)) {
                $updateData['video_intro'] = $videoName;
            } else {
                $errors[] = 'Failed to upload video.';
            }
        } else {
            $errors[] = 'Invalid video format.';
        }
    }

    if (!$errors) {
        if (User::updateProfile($user->user_id, $updateData)) {
            Helpers::flash('msg','Profile updated.');
            // refresh to clear POST & keep open view mode
            Helpers::redirect('profile_edit.php');
        } else {
            $errors[] = 'Update failed.';
        }
    } else {
        // Keep user object unsynced; they will see attempted values still
        $user->name        = $updateData['name'];
        $user->disability  = $updateData['disability'];
        if ($user->role === 'employer') {
            foreach (['company_name','business_email','company_website','company_phone','business_permit_number'] as $fld) {
                if (isset($updateData[$fld])) $user->$fld = $updateData[$fld];
            }
        }
    }
}

/* Flash messages */
$flashMsg = $_SESSION['flash']['msg'] ?? null;
if (isset($_SESSION['flash']['msg'])) unset($_SESSION['flash']['msg']);

include '../includes/header.php';
include '../includes/nav.php';
?>
<style>
/* Simple separation & toggle animations */
#editFormCard { display:none; }
.fade-slide {
  animation: fadeSlide .25s ease;
}
@keyframes fadeSlide {
  from { opacity:0; transform:translateY(-4px); }
  to { opacity:1; transform:translateY(0); }
}
.profile-overview-item i { width:18px; }
.badge-inline { font-size:.65rem; letter-spacing:.5px; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
  <h1 class="h5 fw-semibold mb-2 mb-lg-0">
    <i class="bi bi-person-badge me-2"></i>Profile
  </h1>
  <div class="d-flex gap-2">
    <button id="toggleEditBtn" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-pencil-square me-1"></i>Edit Profile
    </button>
  </div>
</div>

<?php if ($flashMsg): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check2-circle me-2"></i><?php echo htmlspecialchars($flashMsg); ?>
    <button class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($e); ?>
    <button class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endforeach; ?>

<!-- PROFILE OVERVIEW CARD -->
<div class="card border-0 shadow-sm mb-4" id="overviewCard">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between">
      <h2 class="h6 fw-semibold mb-3"><i class="bi bi-person-lines-fill me-2"></i>Overview</h2>
      <span class="badge text-bg-secondary badge-inline align-self-start"><?php echo htmlspecialchars(ucfirst($user->role)); ?></span>
    </div>
    <div class="row g-4">
      <div class="col-md-6">
        <ul class="list-unstyled mb-0 small">
          <li class="mb-2 profile-overview-item">
            <i class="bi bi-person text-muted me-2"></i><strong>Name:</strong>
            <span class="ms-1"><?php echo Helpers::sanitizeOutput($user->name); ?></span>
          </li>
          <?php if ($user->role === 'employer'): ?>
            <li class="mb-2 profile-overview-item">
              <i class="bi bi-card-text text-muted me-2"></i><strong>Description:</strong>
              <span class="ms-1"><?php echo Helpers::sanitizeOutput($user->disability ?: '(none)'); ?></span>
            </li>
          <?php else: ?>
            <li class="mb-2 profile-overview-item">
              <i class="bi bi-heart-pulse text-muted me-2"></i><strong>Disability:</strong>
              <span class="ms-1"><?php echo Helpers::sanitizeOutput($user->disability ?: 'Not specified'); ?></span>
            </li>
          <?php endif; ?>
          <?php if ($user->role === 'employer'): ?>
            <li class="mb-2 profile-overview-item">
              <i class="bi bi-building text-muted me-2"></i><strong>Company:</strong>
              <span class="ms-1"><?php echo Helpers::sanitizeOutput($user->company_name ?: '(none)'); ?></span>
            </li>
          <?php endif; ?>
          <?php if ($user->role === 'employer' && $user->business_email): ?>
            <li class="mb-2 profile-overview-item">
              <i class="bi bi-envelope-paper text-muted me-2"></i><strong>Business Email:</strong>
              <span class="ms-1"><?php echo Helpers::sanitizeOutput($user->business_email); ?></span>
            </li>
          <?php endif; ?>
          <?php if ($user->role === 'employer' && $user->company_phone): ?>
            <li class="mb-2 profile-overview-item">
              <i class="bi bi-telephone text-muted me-2"></i><strong>Phone:</strong>
              <span class="ms-1"><?php echo Helpers::sanitizeOutput($user->company_phone); ?></span>
            </li>
          <?php endif; ?>
          <?php if ($user->role === 'employer'): ?>
            <li class="mb-2 profile-overview-item">
              <i class="bi bi-file-earmark-text text-muted me-2"></i><strong>Permit #:</strong>
              <span class="ms-1"><?php echo Helpers::sanitizeOutput($user->business_permit_number ?: '(none)'); ?></span>
            </li>
          <?php endif; ?>
        </ul>
      </div>
      <div class="col-md-6">
        <ul class="list-unstyled mb-0 small">
          <?php if ($user->role === 'employer' && $user->company_website): ?>
            <li class="mb-2 profile-overview-item">
              <i class="bi bi-globe text-muted me-2"></i><strong>Website:</strong>
              <span class="ms-1">
                <a target="_blank" href="<?php echo htmlspecialchars($user->company_website); ?>">Visit site</a>
              </span>
            </li>
          <?php endif; ?>
          <?php if ($user->resume): ?>
            <li class="mb-2 profile-overview-item">
              <i class="bi bi-file-earmark-pdf text-muted me-2"></i><strong>Resume:</strong>
              <span class="ms-1"><a target="_blank" href="../<?php echo htmlspecialchars($user->resume); ?>">View PDF</a></span>
            </li>
          <?php endif; ?>
          <?php if ($user->video_intro): ?>
            <li class="mb-2 profile-overview-item">
              <i class="bi bi-camera-video text-muted me-2"></i><strong>Video Intro:</strong>
              <span class="ms-1"><a target="_blank" href="../<?php echo htmlspecialchars($user->video_intro); ?>">Watch</a></span>
            </li>
          <?php endif; ?>
          <?php if ($user->role === 'employer' && $user->employer_doc): ?>
            <li class="mb-2 profile-overview-item">
              <i class="bi bi-paperclip text-muted me-2"></i><strong>Verification Doc:</strong>
              <span class="ms-1"><a target="_blank" href="../<?php echo htmlspecialchars($user->employer_doc); ?>">Open</a></span>
            </li>
          <?php endif; ?>
          
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- EDIT FORM CARD (hidden initially) -->
<div class="card border-0 shadow-sm mb-5" id="editFormCard">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h2 class="h6 fw-semibold mb-0">
        <i class="bi bi-pencil-square me-2"></i>Edit Profile
        <span class="badge text-bg-secondary ms-2"><?php echo htmlspecialchars(ucfirst($user->role)); ?></span>
      </h2>
      <button class="btn btn-sm btn-outline-secondary" id="cancelEditBtn">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <form method="post" enctype="multipart/form-data" class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Name</label>
        <input name="name" class="form-control form-control-lg" required value="<?php echo Helpers::sanitizeOutput($user->name); ?>">
      </div>

      <?php if ($user->role === 'employer'): ?>
        <div class="col-md-6">
          <label class="form-label">Company Description (optional)</label>
          <textarea name="disability" class="form-control form-control-lg" rows="2" placeholder="Short company blurb"><?php echo Helpers::sanitizeOutput($user->disability); ?></textarea>
          <small class="text-muted">Stored internally in disability field.</small>
        </div>
      <?php else: ?>
        <div class="col-md-6">
          <label class="form-label">Disability</label>
            <input name="disability" class="form-control form-control-lg" value="<?php echo Helpers::sanitizeOutput($user->disability); ?>" placeholder="e.g., Visual impairment (optional)">
        </div>
      <?php endif; ?>

      <?php if ($user->role === 'employer'): ?>
        <div class="col-12">
          <hr class="my-2">
          <h3 class="h6 fw-semibold mb-2"><i class="bi bi-building me-2"></i>Employer Details</h3>
        </div>

        <div class="col-md-6">
          <label class="form-label">Company Name</label>
          <input name="company_name" class="form-control" value="<?php echo Helpers::sanitizeOutput($user->company_name); ?>" placeholder="Your company name">
        </div>
        <div class="col-md-6">
          <label class="form-label">Business Email (optional)</label>
          <input type="email" name="business_email" class="form-control" value="<?php echo Helpers::sanitizeOutput($user->business_email); ?>" placeholder="hr@company.com">
        </div>

        <div class="col-md-6">
          <label class="form-label">Company website (optional)</label>
          <input type="url" name="company_website" class="form-control" value="<?php echo Helpers::sanitizeOutput($user->company_website); ?>" placeholder="https://example.com">
        </div>
        <div class="col-md-6">
          <label class="form-label">Company phone (optional)</label>
          <input name="company_phone" class="form-control" value="<?php echo Helpers::sanitizeOutput($user->company_phone); ?>" placeholder="+63 900 000 0000">
        </div>

        <div class="col-md-6">
          <label class="form-label">Business Permit / Registration No. (optional)</label>
          <input name="business_permit_number" class="form-control" value="<?php echo Helpers::sanitizeOutput($user->business_permit_number); ?>" placeholder="SEC/DTI/Mayor’s permit">
        </div>
        <div class="col-md-6">
          <label class="form-label">Verification Document (PDF/JPG/PNG/WEBP)</label>
          <input type="file" name="employer_doc" class="form-control" accept=".pdf,image/*">
          <?php if (!empty($user->employer_doc)): ?>
            <small>Current: <a target="_blank" href="../<?php echo htmlspecialchars($user->employer_doc); ?>">View document</a></small>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="col-md-6">
        <label class="form-label">Resume (PDF)</label>
        <input type="file" name="resume" class="form-control" accept="application/pdf">
        <?php if ($user->resume): ?>
          <small>Current: <a target="_blank" href="../<?php echo htmlspecialchars($user->resume); ?>">View</a></small>
        <?php endif; ?>
      </div>
      <div class="col-md-6">
        <label class="form-label">Video Intro (mp4/webm/ogg)</label>
        <input type="file" name="video_intro" class="form-control" accept="video/*">
        <?php if ($user->video_intro): ?>
          <small>Current: <a target="_blank" href="../<?php echo htmlspecialchars($user->video_intro); ?>">Watch</a></small>
        <?php endif; ?>
      </div>

      <div class="col-12 d-grid">
        <button class="btn btn-primary btn-lg"><i class="bi bi-save me-1"></i>Save Changes</button>
      </div>
    </form>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
(function(){
  const toggleBtn = document.getElementById('toggleEditBtn');
  const cancelBtn = document.getElementById('cancelEditBtn');
  const editCard  = document.getElementById('editFormCard');
  const overview  = document.getElementById('overviewCard');

  function showEdit(){
    editCard.style.display = 'block';
    editCard.classList.add('fade-slide');
    toggleBtn.innerHTML = '<i class="bi bi-x-lg me-1"></i>Close Edit';
    toggleBtn.classList.remove('btn-outline-primary');
    toggleBtn.classList.add('btn-outline-secondary');
    window.scrollTo({top: editCard.offsetTop - 60, behavior:'smooth'});
  }
  function hideEdit(){
    editCard.style.display = 'none';
    toggleBtn.innerHTML = '<i class="bi bi-pencil-square me-1"></i>Edit Profile';
    toggleBtn.classList.remove('btn-outline-secondary');
    toggleBtn.classList.add('btn-outline-primary');
  }
  toggleBtn?.addEventListener('click', (e)=>{
    e.preventDefault();
    (editCard.style.display === 'block') ? hideEdit() : showEdit();
  });
  cancelBtn?.addEventListener('click', (e)=>{
    e.preventDefault();
    hideEdit();
  });

  // Auto open if there are validation errors or ?edit=1
  const hasErrors = <?php echo json_encode(!empty($errors)); ?>;
  const urlParams = new URLSearchParams(window.location.search);
  if (hasErrors || urlParams.get('edit') === '1') {
    showEdit();
  }
})();
</script>