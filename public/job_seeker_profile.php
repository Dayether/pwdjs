<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';
require_once '../classes/Experience.php';
require_once '../classes/Certification.php';

Helpers::requireLogin();

$viewerId   = $_SESSION['user_id'] ?? null;
$viewerRole = $_SESSION['role'] ?? '';
$userParam  = $_GET['user_id'] ?? '';

if ($userParam === '' && $viewerRole === 'job_seeker') {
    $userParam = $viewerId; // view self by default
}

$target = User::findById($userParam);
$backUrl = $_SERVER['HTTP_REFERER'] ?? 'index.php';

if (!$target) {
    include '../includes/header.php';
    include '../includes/nav.php';
    echo '<div class="container py-5"><div class="alert alert-danger">Profile not found.</div></div>';
    include '../includes/footer.php';
    exit;
}

if ($target->role === 'employer') {
    // This page is only for job seekers; redirect to employer profile
    Helpers::redirect('employer_profile.php?user_id='.urlencode($target->user_id));
    exit;
}

$isSelf = ($viewerRole === 'job_seeker' && $viewerId === $target->user_id);

/* Authorization:
   - Admin always
   - Self (job seeker)
   - Employer only if employer has at least one application from this job seeker
*/
$authorized = false;

if ($viewerRole === 'admin' || $isSelf) {
    $authorized = true;
} elseif ($viewerRole === 'employer') {
    try {
        $pdo = Database::getConnection();
        $chk = $pdo->prepare("
            SELECT 1
            FROM applications a
            JOIN jobs j ON a.job_id = j.job_id
            WHERE a.user_id = ? AND j.employer_id = ?
            LIMIT 1
        ");
        $chk->execute([$target->user_id, $viewerId]);
        if ($chk->fetch()) $authorized = true;
    } catch (Throwable $e) {
        $authorized = false;
    }
}

include '../includes/header.php';
include '../includes/nav.php';

if (!$authorized) {
    echo '<div class="container py-5"><div class="alert alert-warning">You are not authorized to view this job seeker profile.</div></div>';
    include '../includes/footer.php';
    exit;
}

/* Applications visible to viewer */
$applications = [];
// Fetch experience & certifications (always safe once authorized; use user id)
$experiences = Experience::listByUser($target->user_id);
$certs = Certification::listByUser($target->user_id);
try {
    $pdo = Database::getConnection();
    if ($viewerRole === 'admin' || $isSelf) {
        $stmt = $pdo->prepare("
            SELECT a.application_id, a.job_id, a.status, a.created_at, a.match_score, j.title
            FROM applications a
            JOIN jobs j ON a.job_id = j.job_id
            WHERE a.user_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$target->user_id]);
    } elseif ($viewerRole === 'employer') {
        $stmt = $pdo->prepare("
            SELECT a.application_id, a.job_id, a.status, a.created_at, a.match_score, j.title
            FROM applications a
            JOIN jobs j ON a.job_id = j.job_id
            WHERE a.user_id = ? AND j.employer_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$target->user_id, $viewerId]);
    }
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $applications = [];
}

function safeFileLink(?string $path): ?string {
    if (!$path) return null;
    if (strpos($path,'..')!==false) return null;
    return $path;
}
$resumeSafe = safeFileLink($target->resume);
$videoSafe  = safeFileLink($target->video_intro);

$experienceLabel = ($target->experience !== null)
    ? (int)$target->experience . ' year' . ($target->experience == 1 ? '' : 's')
    : '0 years';
$educationLabel  = $target->education_level ?: ($target->education ?: 'Not specified');
$disabilityLabel = $target->disability ?: 'Not specified';

/* Toast flash */
$rawFlash = $_SESSION['flash'] ?? [];
if (isset($_SESSION['flash'])) unset($_SESSION['flash']);
$toast = null;
foreach (['msg'=>'success','error'=>'danger','warn'=>'warning','info'=>'info'] as $k=>$type) {
    if (!empty($rawFlash[$k])) { $toast=['type'=>$type,'message'=>$rawFlash[$k]]; break; }
}
?>
<style>.toast-container{z-index:1080;}</style>
<?php if ($toast): ?>
<div class="toast-container position-fixed top-0 end-0 p-3">
  <div class="toast text-bg-<?php echo htmlspecialchars($toast['type']); ?> show">
    <div class="d-flex">
      <div class="toast-body"><i class="bi bi-info-circle me-2"></i><?php echo htmlspecialchars($toast['message']); ?></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <?php if ($isSelf): ?>
      <a class="btn btn-outline-primary btn-sm" href="profile_edit.php">
        <i class="bi bi-pencil-square me-1"></i>Edit My Profile
      </a>
    <?php endif; ?>
  </div>

  <div class="row g-4">
    <div class="col-lg-5">
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
          <div class="d-flex align-items-center gap-3 mb-3">
            <div>
              <?php if (!empty($target->profile_picture)): ?>
                <img src="../<?php echo htmlspecialchars($target->profile_picture); ?>" alt="Profile" class="rounded-circle border" style="width:90px;height:90px;object-fit:cover;">
              <?php else: ?>
                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center border" style="width:90px;height:90px;font-size:2rem;color:#888;"><i class="bi bi-person"></i></div>
              <?php endif; ?>
            </div>
            <div class="flex-grow-1">
          <h2 class="h5 fw-semibold mb-3 d-flex flex-wrap align-items-center gap-2">
            <span><i class="bi bi-person-fill me-2"></i><?php echo htmlspecialchars($target->name ?: 'Unnamed User'); ?></span>
            <?php if (!empty($target->pwd_id_status)): ?>
              <?php
                $vs = $target->pwd_id_status;
                $vClass = match($vs){
                  'Verified' => 'success',
                  'Pending'  => 'warning',
                  'Rejected' => 'danger',
                  default    => 'secondary'
                };
              ?>
              <span class="badge text-bg-<?php echo $vClass; ?> small">PWD: <?php echo htmlspecialchars($vs); ?></span>
            <?php endif; ?>
          </h2>
            </div>
          </div>
          <dl class="row small mb-0">
            <dt class="col-5 text-muted">Email</dt><dd class="col-7"><?php echo htmlspecialchars($target->email); ?></dd>
            <dt class="col-5 text-muted">Disability</dt><dd class="col-7"><?php echo htmlspecialchars($disabilityLabel); ?></dd>
            <?php if (!empty($target->date_of_birth)): ?>
              <dt class="col-5 text-muted">Age</dt>
              <dd class="col-7"><?php
                $dobTs = strtotime($target->date_of_birth);
                if ($dobTs) {
                  $age = (int)date('Y') - (int)date('Y',$dobTs);
                  if (date('md') < date('md',$dobTs)) $age--;
                  echo htmlspecialchars($age.' yrs');
                } else echo '—';
              ?></dd>
            <?php endif; ?>
            <?php if (!empty($target->phone)): ?>
              <dt class="col-5 text-muted">Phone</dt>
              <dd class="col-7"><?php echo htmlspecialchars($target->phone); ?></dd>
            <?php endif; ?>

            <?php if (!empty($target->disability_type)): ?>
              <dt class="col-5 text-muted">Disability Type</dt>
              <dd class="col-7"><?php echo htmlspecialchars($target->disability_type); ?></dd>
            <?php endif; ?>

            <?php if (!empty($target->disability_severity)): ?>
              <dt class="col-5 text-muted">Severity</dt>
              <dd class="col-7"><?php echo htmlspecialchars($target->disability_severity); ?></dd>
            <?php endif; ?>

            <?php if (!empty($target->assistive_devices)): ?>
              <dt class="col-5 text-muted">Assistive Devices</dt>
              <dd class="col-7"><?php echo htmlspecialchars($target->assistive_devices); ?></dd>
            <?php endif; ?>

            <dt class="col-5 text-muted">Education</dt>
            <dd class="col-7"><?php echo htmlspecialchars($educationLabel); ?></dd>

            <dt class="col-5 text-muted">Experience</dt>
            <dd class="col-7"><?php echo htmlspecialchars($experienceLabel); ?></dd>

            <?php if (!empty($target->gender)): ?>
              <dt class="col-5 text-muted">Gender</dt>
              <dd class="col-7"><?php echo htmlspecialchars($target->gender); ?></dd>
            <?php endif; ?>

            <?php if (!empty($target->region) || !empty($target->province) || !empty($target->city)): ?>
              <dt class="col-5 text-muted">Location</dt>
              <dd class="col-7">
                <?php
                  $parts = array_filter([$target->city,$target->province,$target->region]);
                  echo htmlspecialchars(implode(', ',$parts));
                ?>
              </dd>
            <?php endif; ?>

            <?php if (!empty($target->primary_skill_summary)): ?>
              <dt class="col-5 text-muted">Summary</dt>
              <dd class="col-7"><?php echo nl2br(htmlspecialchars($target->primary_skill_summary)); ?></dd>
            <?php endif; ?>

            <?php if ($resumeSafe): ?>
              <dt class="col-5 text-muted">Resume</dt>
              <dd class="col-7">
                <a href="../<?php echo htmlspecialchars($resumeSafe); ?>" target="_blank">
                  <i class="bi bi-file-earmark-pdf me-1"></i>View
                </a>
              </dd>
            <?php endif; ?>

            <?php if ($videoSafe): ?>
              <dt class="col-5 text-muted">Video Intro</dt>
              <dd class="col-7">
                <a href="../<?php echo htmlspecialchars($videoSafe); ?>" target="_blank">
                  <i class="bi bi-camera-video me-1"></i>Watch
                </a>
              </dd>
            <?php endif; ?>
          </dl>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <!-- Applications -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
          <h3 class="h6 fw-semibold mb-3"><i class="bi bi-send me-2"></i>Applications</h3>
          <?php if (!$applications): ?>
            <div class="text-muted small">No applications found or not visible for your role.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Job Title</th>
                    <th>Status</th>
                    <th>Match</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody class="small">
                  <?php foreach ($applications as $app): ?>
                    <tr>
                      <td><a href="job_view.php?job_id=<?php echo urlencode($app['job_id']); ?>"><?php echo htmlspecialchars($app['title']); ?></a></td>
                      <td>
                        <span class="badge bg-<?php
                          echo $app['status']==='Approved'?'success':($app['status']==='Declined'?'danger':'secondary');
                        ?>">
                          <?php echo htmlspecialchars($app['status']); ?>
                        </span>
                      </td>
                      <td><?php echo htmlspecialchars((float)$app['match_score']); ?>%</td>
                      <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($app['created_at']))); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Experience -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
          <h3 class="h6 fw-semibold mb-3"><i class="bi bi-briefcase me-2"></i>Work Experience</h3>
          <?php if (!$experiences): ?>
            <div class="text-muted small">No experience listed.</div>
          <?php else: ?>
            <ul class="list-unstyled small mb-0">
              <?php foreach ($experiences as $exp): ?>
                <li class="mb-2">
                  <span class="fw-semibold"><?php echo Helpers::sanitizeOutput($exp['position']); ?></span>
                  @ <?php echo Helpers::sanitizeOutput($exp['company']); ?>
                  <span class="text-muted">
                    (<?php echo htmlspecialchars(substr($exp['start_date'],0,7)); ?> -
                    <?php echo $exp['is_current'] ? 'Present' : ($exp['end_date'] ? htmlspecialchars(substr($exp['end_date'],0,7)) : '—'); ?>)
                  </span>
                  <?php if (!empty($exp['description'])): ?>
                    <div><?php echo nl2br(htmlspecialchars($exp['description'])); ?></div>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>

      <!-- Certifications -->
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <h3 class="h6 fw-semibold mb-3"><i class="bi bi-patch-check me-2"></i>Certifications</h3>
          <?php if (!$certs): ?>
            <div class="text-muted small">No certifications listed.</div>
          <?php else: ?>
            <ul class="list-unstyled small mb-0">
              <?php foreach ($certs as $ct): ?>
                <li class="mb-2">
                  <span class="fw-semibold"><?php echo Helpers::sanitizeOutput($ct['name']); ?></span>
                  <?php if ($ct['issuer']): ?><span class="text-muted">· <?php echo Helpers::sanitizeOutput($ct['issuer']); ?></span><?php endif; ?>
                  <?php if ($ct['issued_date']): ?><span class="text-muted"> (<?php echo htmlspecialchars(substr($ct['issued_date'],0,7)); ?>)</span><?php endif; ?>
                  <?php if ($ct['credential_id']): ?><div class="text-muted">Credential: <?php echo Helpers::sanitizeOutput($ct['credential_id']); ?></div><?php endif; ?>
                  <?php if ($ct['attachment_path']): ?>
                    <div>
                      <a class="text-decoration-none" href="../<?php echo htmlspecialchars($ct['attachment_path']); ?>" target="_blank"><i class="bi bi-paperclip me-1"></i>Attachment</a>
                    </div>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>