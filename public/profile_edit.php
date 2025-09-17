<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';

Helpers::requireLogin();
$user = User::findById($_SESSION['user_id']);
if (!$user) Helpers::redirect('login.php');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateData = [
        'name' => $_POST['name'],
        'disability' => $_POST['disability'],
        // do not update education here anymore
    ];

    if (!empty($_FILES['resume']['name'])) {
        if ($_FILES['resume']['type'] === 'application/pdf') {
            $resumeName = 'uploads/resumes/' . uniqid('res_') . '.pdf';
            if (move_uploaded_file($_FILES['resume']['tmp_name'], '../' . $resumeName)) {
                $updateData['resume'] = $resumeName;
            } else {
                $errors[] = 'Failed to upload resume.';
            }
        } else {
            $errors[] = 'Resume must be a PDF.';
        }
    }

    if (!empty($_FILES['video_intro']['name'])) {
        $allowed = ['video/mp4','video/webm','video/ogg'];
        if (in_array($_FILES['video_intro']['type'], $allowed)) {
            $ext = pathinfo($_FILES['video_intro']['name'], PATHINFO_EXTENSION);
            $videoName = 'uploads/videos/' . uniqid('vid_') . '.' . $ext;
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
            Helpers::redirect('profile_edit.php');
        } else {
            $errors[] = 'Update failed.';
        }
    }
}

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="row">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body p-4">
        <h2 class="h5 fw-semibold mb-3"><i class="bi bi-person-gear me-2"></i>Edit Profile</h2>
        <?php foreach ($errors as $e): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($e); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endforeach; ?>
        <form method="post" enctype="multipart/form-data" class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Name</label>
            <input name="name" class="form-control form-control-lg" required value="<?php echo Helpers::sanitizeOutput($user->name); ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label">Disability</label>
            <input name="disability" class="form-control form-control-lg" value="<?php echo Helpers::sanitizeOutput($user->disability); ?>">
          </div>
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
  </div>

  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h3 class="h6 fw-semibold mb-2">Your Profile</h3>
        <ul class="list-unstyled small mb-0">
          <li class="mb-1"><i class="bi bi-person me-2 text-muted"></i><?php echo Helpers::sanitizeOutput($user->name); ?></li>
          <li class="mb-1"><i class="bi bi-heart-pulse me-2 text-muted"></i><?php echo Helpers::sanitizeOutput($user->disability ?: 'Not specified'); ?></li>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>