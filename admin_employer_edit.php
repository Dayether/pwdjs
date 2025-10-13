<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';
require_once 'classes/User.php';

Helpers::requireRole('admin');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Establish selected employer via POST -> session; avoid exposing in URL
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['user_id'])) {
  $_SESSION['__admin_edit_user_emp'] = (string)$_POST['user_id'];
}
// Fallback to the view session key if present
$targetId = $_SESSION['__admin_edit_user_emp'] ?? ($_SESSION['__admin_view_user_emp'] ?? '');
if ($targetId === '') {
  Helpers::flash('error','Missing employer selection.');
  Helpers::redirect('admin_employers.php');
}

$empUser = User::findById($targetId);
if (!$empUser || ($empUser->role ?? '') !== 'employer') {
  Helpers::flash('error','Employer not found.');
  Helpers::redirect('admin_employers.php');
}

// CSRF
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
function adminEmpCsrf(): string { return $_SESSION['csrf'] ?? ''; }
function verifyAdminEmpCsrf(): void {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        die('Invalid CSRF token.');
    }
}

$errors = [];
$flashMsg = $_SESSION['flash']['msg'] ?? null;
if ($flashMsg) unset($_SESSION['flash']['msg']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__edit_submit'])) {
    verifyAdminEmpCsrf();

    $update = [];
    $original = [
      'name' => $empUser->name,
      'company_name' => $empUser->company_name,
      'business_email' => $empUser->business_email,
      'company_website' => $empUser->company_website,
      'company_phone' => $empUser->company_phone,
      'business_permit_number' => $empUser->business_permit_number,
      'company_owner_name' => $empUser->company_owner_name,
      'contact_person_position' => $empUser->contact_person_position,
      'contact_person_phone' => $empUser->contact_person_phone,
      'employer_doc' => $empUser->employer_doc,
      'profile_picture' => $empUser->profile_picture
    ];

    // Collect + sanitize inputs
    $companyName  = trim($_POST['company_name'] ?? $empUser->company_name);
    $bizEmail     = trim($_POST['business_email'] ?? $empUser->business_email);
    $website      = trim($_POST['company_website'] ?? $empUser->company_website);
    $companyPhone = trim($_POST['company_phone'] ?? $empUser->company_phone);
    $permit       = trim(preg_replace('/\s+/', '', $_POST['business_permit_number'] ?? $empUser->business_permit_number));
    $companyOwner = trim($_POST['company_owner_name'] ?? $empUser->company_owner_name);
    $contactPos   = trim($_POST['contact_person_position'] ?? $empUser->contact_person_position);
    $contactPhone = trim($_POST['contact_person_phone'] ?? $empUser->contact_person_phone);

    // Basic validations
    if ($bizEmail !== '' && !filter_var($bizEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid business email format.';
    }
    if ($website !== '' && !preg_match('~^https?://~i', $website)) {
        $website = 'https://' . $website;
    }
    if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
        $errors[] = 'Invalid company website URL.';
    }
    if ($companyOwner === '') {
      $errors[] = 'Company owner / proprietor name is required.';
    }
    if ($contactPhone !== '' && !preg_match('/^[0-9 +().-]{6,30}$/', $contactPhone)) {
      $errors[] = 'Contact person phone format invalid.';
    }
    if ($companyPhone !== '' && !preg_match('/^[0-9 +().-]{6,30}$/', $companyPhone)) {
      $errors[] = 'Company phone format invalid.';
    }

    $fieldsMap = [
      'name'                   => $companyOwner, // keep display name in sync with owner name
      'company_name'           => $companyName,
      'business_email'         => $bizEmail,
      'company_website'        => $website,
      'company_phone'          => $companyPhone,
      'business_permit_number' => $permit,
      'company_owner_name'     => $companyOwner,
      'contact_person_position'=> $contactPos,
      'contact_person_phone'   => $contactPhone
    ];
    foreach ($fieldsMap as $k => $v) {
        if ((string)$empUser->$k !== (string)$v) {
            $update[$k] = $v;
        }
    }

    // File upload (optional) - business permit / employer doc
    if (!empty($_FILES['employer_doc']['name'])) {
        $mime = $_FILES['employer_doc']['type'];
        $allowed = ['application/pdf','image/jpeg','image/png','image/jpg'];
        if (in_array($mime, $allowed, true)) {
            if (!is_dir(__DIR__.'/uploads/employers')) @mkdir(__DIR__.'/uploads/employers', 0775, true);
            $ext = strtolower(pathinfo($_FILES['employer_doc']['name'], PATHINFO_EXTENSION));
            $fname = 'uploads/employers/' . uniqid('empdoc_') . '.' . $ext;
            if (move_uploaded_file($_FILES['employer_doc']['tmp_name'], __DIR__ . '/' . $fname)) {
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
          if (!is_dir(__DIR__.'/uploads/profile')) @mkdir(__DIR__.'/uploads/profile',0775,true);
          $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
          if (!preg_match('/^(jpe?g|png|gif)$/',$ext)) {
            $errors[] = 'Invalid image extension.';
          } else {
            $pName = 'uploads/profile/' . uniqid('pf_') . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], __DIR__ . '/'.$pName)) {
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
                if (User::updateProfileExtended($empUser->user_id, $update)) {
                    // Build diff for audit log (only fields that changed)
                    $diff = [];
                    foreach ($update as $k=>$v) {
                        $oldVal = $original[$k] ?? null;
                        if ((string)$oldVal !== (string)$v) {
                            $diff[$k] = ['old'=>$oldVal,'new'=>$v];
                            $empUser->$k = $v; // refresh local object
                        }
                    }
                    if ($diff) {
                        User::logProfileUpdate($_SESSION['user_id'], $empUser->user_id, 'employer', $diff);
                    }
                    Helpers::flash('msg','Employer profile updated.');
                    // Keep the current selection and redirect back to view page
                    $_SESSION['__admin_view_user_emp'] = $empUser->user_id;
                    Helpers::redirect('admin_employer_view');
                } else {
                    $errors[] = 'Update failed or no writable fields.';
                }
            } catch (PDOException $e) {
                if ($e->getCode()==='23000') {
                    $errors[] = 'Permit number already in use.';
                } else {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            } catch (Throwable $t) {
                $errors[] = 'Unexpected error.';
            }
        } else {
            Helpers::flash('msg','No changes.');
            $_SESSION['__admin_view_user_emp'] = $empUser->user_id;
            Helpers::redirect('admin_employer_view');
        }
    }
}

include 'includes/header.php';
?>
<div class="admin-layout">
  <?php include 'includes/admin_sidebar.php'; ?>
  <div class="admin-main">

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

<style>
/* Admin Employer Edit */
.emp-edit-card{background:#ffffff;border:1px solid rgba(13,110,253,.14);border-radius:1.25rem;box-shadow:0 14px 40px -12px rgba(13,110,253,.30),0 6px 18px rgba(0,0,0,.06);padding:1.6rem 1.6rem 2rem;position:relative;overflow:hidden;}
.emp-edit-card::before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 14% 20%,rgba(255,193,7,.22),transparent 60%),radial-gradient(circle at 85% 15%,rgba(102,16,242,.20),transparent 60%);mix-blend-mode:overlay;opacity:.55;pointer-events:none;}
.emp-form-grid{display:grid;grid-template-columns:260px 1fr;gap:2.2rem;align-items:start;}
@media (max-width:991.98px){.emp-form-grid{grid-template-columns:1fr;gap:1.6rem;}}
.avatar-upload-box{position:relative;text-align:center;background:linear-gradient(145deg,#ffffff,#f6faff);border:1px solid #e3e9f1;border-radius:1.15rem;padding:1.4rem 1rem 1.2rem;box-shadow:0 8px 24px -10px rgba(13,110,253,.18),0 4px 12px rgba(0,0,0,.05);} 
.avatar-preview{width:130px;height:130px;margin:0 auto 1rem;border-radius:50%;overflow:hidden;position:relative;display:flex;align-items:center;justify-content:center;background:#f5f7fb;border:3px solid #fff;box-shadow:0 6px 18px -8px rgba(13,110,253,.4);} 
.avatar-preview img{width:100%;height:100%;object-fit:cover;} 
.avatar-preview .fallback{font-size:2.3rem;color:#7a8895;} 
.avatar-upload-box input[type=file]{font-size:.75rem;padding:.55rem .7rem;} 
.small-hint{font-size:.65rem;color:#637280;margin-top:.45rem;font-weight:600;letter-spacing:.6px;text-transform:uppercase;} 
.form-grid-2{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.15rem;} 
.form-grid-3{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.15rem;} 
.input-block{background:#f7f9fb;border:1px solid #dbe3eb;border-radius:.85rem;padding:.75rem .9rem .65rem;position:relative;transition:.2s;} 
.input-block:focus-within{background:#ffffff;border-color:#b5c2d1;box-shadow:0 0 0 3px rgba(13,110,253,.12);} 
.input-block label{font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.75px;color:#5a6875;margin:0 0 .35rem;display:flex;align-items:center;gap:.35rem;} 
.input-block input{background:transparent;border:0;outline:none;width:100%;padding:0;font-size:.9rem;} 
.input-block.disabled{opacity:.7;} 
.divider-soft{height:1px;background:linear-gradient(90deg,#dde3e9,#f7f9fb);margin:1.75rem 0 1.25rem;} 
.edit-actions-row{display:flex;flex-wrap:wrap;gap:.65rem;margin-top:1.4rem;} 
.edit-actions-row .btn-gradient{background:linear-gradient(135deg,var(--primary-blue),var(--primary-purple));border:0;} 
.doc-link a{text-decoration:none;font-weight:600;font-size:.7rem;} 
@media (max-width:575.98px){.emp-edit-card{padding:1.15rem 1.05rem 1.5rem;} .avatar-preview{width:110px;height:110px;}} 
.page-header{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:1rem;}
</style>

<div class="container mt-4">
  <div class="page-header">
    <h1 class="h5 fw-semibold mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Employer Profile</h1>
    <div class="d-flex gap-2">
      <form method="post" action="admin_employer_view" class="d-inline">
        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($empUser->user_id); ?>">
        <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to View</button>
      </form>
      <a class="btn btn-outline-secondary btn-sm" href="admin_employers.php"><i class="bi bi-list"></i> Employers</a>
    </div>
  </div>

  <form method="post" enctype="multipart/form-data" class="emp-edit-card mb-4" id="adminEmpEditForm">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(adminEmpCsrf()); ?>">
    <input type="hidden" name="__edit_submit" value="1">
    <div class="emp-form-grid">
      <div>
        <div class="avatar-upload-box">
          <div class="avatar-preview" id="avatarPreview">
            <?php if (!empty($empUser->profile_picture)): ?>
              <img src="<?php echo htmlspecialchars($empUser->profile_picture); ?>" alt="Profile Picture" id="avatarImg">
            <?php else: ?>
              <div class="fallback"><i class="bi bi-person"></i></div>
            <?php endif; ?>
          </div>
          <input type="file" name="profile_picture" id="profilePictureInput" accept="image/*" class="form-control form-control-sm" aria-label="Upload profile picture">
          <div class="small-hint">JPG/PNG/GIF max 2MB</div>
          <div class="divider-soft"></div>
          <div class="input-block">
            <label><i class="bi bi-file-earmark-arrow-up"></i>Permit Document (PDF/JPG/PNG)</label>
            <input type="file" name="employer_doc" id="permitDocInput" aria-label="Upload business permit">
            <?php if ($empUser->employer_doc): ?>
              <div class="doc-link mt-1"><a href="<?php echo htmlspecialchars($empUser->employer_doc); ?>" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Current file</a></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div>
        <div class="form-grid-2">
          <div class="input-block">
            <label><i class="bi bi-building"></i>Company Name</label>
            <input type="text" name="company_name" value="<?php echo htmlspecialchars($empUser->company_name); ?>" required>
          </div>
          <div class="input-block">
            <label><i class="bi bi-person-vcard"></i>Owner / Proprietor Name</label>
            <input type="text" name="company_owner_name" value="<?php echo htmlspecialchars($empUser->company_owner_name); ?>" required>
          </div>
          <div class="input-block disabled">
            <label><i class="bi bi-person-badge"></i>Display Name (Account)</label>
            <input type="text" id="displayNameMirror" value="<?php echo htmlspecialchars($empUser->name); ?>" disabled>
          </div>
          <div class="input-block">
            <label><i class="bi bi-person-badge-fill"></i>Contact Person Position</label>
            <input type="text" name="contact_person_position" value="<?php echo htmlspecialchars($empUser->contact_person_position); ?>">
          </div>
          <div class="input-block">
            <label><i class="bi bi-envelope"></i>Business Email</label>
            <input type="email" name="business_email" value="<?php echo htmlspecialchars($empUser->business_email); ?>">
          </div>
          <div class="input-block">
            <label><i class="bi bi-link-45deg"></i>Company Website</label>
            <input type="text" name="company_website" value="<?php echo htmlspecialchars($empUser->company_website); ?>" placeholder="https://...">
          </div>
          <div class="input-block">
            <label><i class="bi bi-telephone"></i>Company Phone</label>
            <input type="text" name="company_phone" value="<?php echo htmlspecialchars($empUser->company_phone); ?>">
          </div>
          <div class="input-block">
            <label><i class="bi bi-telephone-inbound"></i>Contact Person Phone</label>
            <input type="text" name="contact_person_phone" value="<?php echo htmlspecialchars($empUser->contact_person_phone); ?>" placeholder="e.g. +63 912 345 6789">
          </div>
          <div class="input-block">
            <label><i class="bi bi-hash"></i>Business Permit #</label>
            <input type="text" name="business_permit_number" value="<?php echo htmlspecialchars($empUser->business_permit_number); ?>">
          </div>
        </div>
        <div class="edit-actions-row">
          <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-save me-1"></i>Save Changes</button>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="cancelToViewBtn">Cancel</button>
        </div>
      </div>
    </div>
  </form>

  <!-- Standalone form for Cancel action (avoid nested form) -->
  <form method="post" action="admin_employer_view" class="d-none" id="cancelToViewForm">
    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($empUser->user_id); ?>">
  </form>

</div><!-- /.container -->

<script>
// Image preview for avatar
document.addEventListener('DOMContentLoaded', function(){
  const input = document.getElementById('profilePictureInput');
  const preview = document.getElementById('avatarPreview');
  if(input && preview){
    input.addEventListener('change', function(){
      const file = this.files && this.files[0];
      if(!file) return;
      if(!file.type.startsWith('image/')) return;
      const img = preview.querySelector('img') || document.createElement('img');
      img.id='avatarImg';
      img.alt='Selected Profile Picture';
      img.style.width='100%';img.style.height='100%';img.style.objectFit='cover';
      const reader = new FileReader();
      reader.onload = e => { img.src = e.target.result; preview.innerHTML=''; preview.appendChild(img); };
      reader.readAsDataURL(file);
    });
  }
});

// Live mirror owner name to Display Name (disabled field)
document.addEventListener('DOMContentLoaded', function(){
  const ownerInput = document.querySelector('input[name="company_owner_name"]');
  const displayMirror = document.getElementById('displayNameMirror');
  if (ownerInput && displayMirror) {
    const sync = ()=> { displayMirror.value = ownerInput.value; };
    ownerInput.addEventListener('input', sync);
  }
  const cancelBtn = document.getElementById('cancelToViewBtn');
  const cancelForm = document.getElementById('cancelToViewForm');
  if (cancelBtn && cancelForm) {
    cancelBtn.addEventListener('click', function(){ cancelForm.submit(); });
  }
});
</script>

<?php include 'includes/footer.php'; ?>
  </div>
</div>
