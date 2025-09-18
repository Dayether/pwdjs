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
        'disability' => $_POST['disability'] ?? null,
    ];

    // If employer, accept employer details
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

        // Employer verification document (optional upload)
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

    // Resume upload (PDF)
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

    // Video intro upload
    if (!empty($_FILES['video_intro']['name'])) {
        $allowed = ['video/mp4','video/webm','video/ogg'];
        if (in_array($_FILES['video_intro']['type'], $allowed, true)) {
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

          <?php if ($user->role === 'employer'): ?>
            <div class="col-12">
              <hr>
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
              <input name="business_permit_number" class="form-control" value="<?php echo Helpers::sanitizeOutput($user->business_permit_number); ?>" placeholder="e.g., SEC/DTI/Mayor’s permit">
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
  </div>

  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h3 class="h6 fw-semibold mb-2">Your Profile</h3>
        <ul class="list-unstyled small mb-0">
          <li class="mb-1"><i class="bi bi-person me-2 text-muted"></i><?php echo Helpers::sanitizeOutput($user->name); ?></li>
          <li class="mb-1"><i class="bi bi-heart-pulse me-2 text-muted"></i><?php echo Helpers::sanitizeOutput($user->disability ?: 'Not specified'); ?></li>

          <?php if ($user->role === 'employer'): ?>
            <li class="mb-1"><i class="bi bi-building me-2 text-muted"></i><?php echo Helpers::sanitizeOutput($user->company_name ?: '(no company)'); ?></li>
            <li class="mb-1"><i class="bi bi-envelope-paper me-2 text-muted"></i><?php echo Helpers::sanitizeOutput($user->business_email ?: '(no business email)'); ?></li>
            <?php if (!empty($user->company_website)): ?>
              <li class="mb-1"><i class="bi bi-globe me-2 text-muted"></i><a target="_blank" href="<?php echo htmlspecialchars($user->company_website); ?>">Website</a></li>
            <?php endif; ?>
            <?php if (!empty($user->company_phone)): ?>
              <li class="mb-1"><i class="bi bi-telephone me-2 text-muted"></i><?php echo Helpers::sanitizeOutput($user->company_phone); ?></li>
            <?php endif; ?>
            <li class="mb-1"><i class="bi bi-file-earmark-text me-2 text-muted"></i>Permit No.: <?php echo Helpers::sanitizeOutput($user->business_permit_number ?: '(none)'); ?></li>
            <?php if (!empty($user->employer_doc)): ?>
              <li class="mb-1"><i class="bi bi-paperclip me-2 text-muted"></i><a target="_blank" href="../<?php echo htmlspecialchars($user->employer_doc); ?>">View verification</a></li>
            <?php endif; ?>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>