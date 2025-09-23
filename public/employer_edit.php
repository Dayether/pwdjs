<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';

Helpers::requireLogin();
if (($_SESSION['role'] ?? '') !== 'employer') {
    Helpers::redirect('index.php');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$user = User::findById($_SESSION['user_id']);
if (!$user) Helpers::redirect('login.php');

/* --- CSRF helpers (skip if you already implemented in Helpers) --- */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
function csrfToken(): string {
    return $_SESSION['csrf'] ?? '';
}
function verifyCsrf(): void {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        die('Invalid CSRF token.');
    }
}

$errors = [];
$flashMsg = $_SESSION['flash']['msg'] ?? null;
if ($flashMsg) unset($_SESSION['flash']['msg']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $update = [];

    // Collect + sanitize inputs
    $companyName  = trim($_POST['company_name'] ?? $user->company_name);
    $bizEmail     = trim($_POST['business_email'] ?? $user->business_email);
    $website      = trim($_POST['company_website'] ?? $user->company_website);
    $companyPhone = trim($_POST['company_phone'] ?? $user->company_phone);
    $permit       = trim(preg_replace('/\s+/', '', $_POST['business_permit_number'] ?? $user->business_permit_number));

    // Basic validations
    if ($bizEmail !== '' && !filter_var($bizEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid business email format.';
    }

    if ($website !== '' && !preg_match('~^https?://~i', $website)) {
        // Auto prepend https:// if user forgot
        $website = 'https://' . $website;
    }
    if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
        $errors[] = 'Invalid company website URL.';
    }

    // Prepare update array only if changed
    $fieldsMap = [
        'company_name'            => $companyName,
        'business_email'          => $bizEmail,
        'company_website'         => $website,
        'company_phone'           => $companyPhone,
        'business_permit_number'  => $permit
    ];
    foreach ($fieldsMap as $k => $v) {
        if ((string)$user->$k !== (string)$v) {
            $update[$k] = $v;
        }
    }

    // File upload (optional)
    if (!empty($_FILES['employer_doc']['name'])) {
        $mime = $_FILES['employer_doc']['type'];
        $allowed = ['application/pdf','image/jpeg','image/png','image/jpg'];
        if (in_array($mime, $allowed, true)) {
            if (!is_dir('../uploads/employers')) @mkdir('../uploads/employers', 0775, true);
            $ext = strtolower(pathinfo($_FILES['employer_doc']['name'], PATHINFO_EXTENSION));
            $fname = 'uploads/employers/' . uniqid('empdoc_') . '.' . $ext;
            if (move_uploaded_file($_FILES['employer_doc']['tmp_name'], '../' . $fname)) {
                $update['employer_doc'] = $fname;
            } else {
                $errors[] = 'Failed to upload document.';
            }
        } else {
            $errors[] = 'Invalid document format (PDF/JPG/PNG only).';
        }
    }

  // Profile Picture upload (employer)
  if (!empty($_FILES['profile_picture']['name'])) {
    $allowedImg = ['image/jpeg','image/png','image/gif'];
    if (in_array($_FILES['profile_picture']['type'], $allowedImg, true)) {
      if ($_FILES['profile_picture']['size'] <= 2*1024*1024) {
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
        if ($update) {
            try {
                if (User::updateProfileExtended($user->user_id, $update)) {
                    foreach ($update as $k=>$v) $user->$k = $v; // refresh local object
                    Helpers::flash('msg','Employer profile updated.');
                    Helpers::redirect('employer_profile.php');
                } else {
                    $errors[] = 'Update failed or no writable fields.';
                }
            } catch (PDOException $e) {
                if ($e->getCode()==='23000') {
                    // Likely unique constraint on permit
                    $errors[] = 'Permit number already in use.';
                } else {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            } catch (Throwable $t) {
                $errors[] = 'Unexpected error.';
            }
        } else {
            Helpers::flash('msg','No changes.');
            Helpers::redirect('employer_profile.php');
        }
    }
}

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
  <h1 class="h5 fw-semibold mb-2 mb-lg-0">
    <i class="bi bi-building me-2"></i>Edit Employer Profile
  </h1>
  <a class="btn btn-outline-secondary btn-sm" href="employer_profile.php">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
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
  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrfToken()); ?>">
  <div class="card-body p-4">
    <div class="row g-3">
      <div class="col-md-3 text-center">
        <label class="form-label d-block">Profile Picture</label>
        <div class="mb-2">
          <?php if (!empty($user->profile_picture)): ?>
            <img src="../<?php echo htmlspecialchars($user->profile_picture); ?>" alt="Profile" class="rounded-circle border" style="width:100px;height:100px;object-fit:cover;">
          <?php else: ?>
            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center border" style="width:100px;height:100px;font-size:2rem;color:#888;"><i class="bi bi-person"></i></div>
          <?php endif; ?>
        </div>
        <input type="file" name="profile_picture" accept="image/*" class="form-control form-control-sm">
        <div class="form-text">JPG/PNG/GIF max 2MB</div>
      </div>
      <div class="col-md-9">
      <div class="col-md-6">
        <label class="form-label">Company Name</label>
        <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($user->company_name); ?>" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Display Name (Account Name)</label>
        <input type="text" disabled class="form-control" value="<?php echo htmlspecialchars($user->name); ?>">
        <div class="form-text">Separate from company name (locked for now).</div>
      </div>
      <div class="col-md-6">
        <label class="form-label">Business Email</label>
        <input type="email" name="business_email" class="form-control" value="<?php echo htmlspecialchars($user->business_email); ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Company Website</label>
        <input type="text" name="company_website" class="form-control" value="<?php echo htmlspecialchars($user->company_website); ?>" placeholder="https://...">
      </div>
      <div class="col-md-4">
        <label class="form-label">Company Phone</label>
        <input type="text" name="company_phone" class="form-control" value="<?php echo htmlspecialchars($user->company_phone); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Business Permit #</label>
        <input type="text" name="business_permit_number" class="form-control" value="<?php echo htmlspecialchars($user->business_permit_number); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Upload Document (PDF/JPG/PNG)</label>
        <input type="file" name="employer_doc" class="form-control">
        <?php if ($user->employer_doc): ?>
          <div class="form-text">
            <a href="../<?php echo htmlspecialchars($user->employer_doc); ?>" target="_blank">Current file</a>
          </div>
        <?php endif; ?>
      </div>
      </div><!-- /.col-md-9 -->
    </div>

    <div class="mt-4 d-flex gap-2">
      <button class="btn btn-primary">
        <i class="bi bi-save me-1"></i>Save Changes
      </button>
      <a href="employer_profile.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </div>
</form>

<?php include '../includes/footer.php'; ?>