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
// Allow explicit return parameter (internal only) similar to other pages
$explicitReturn = $_GET['return'] ?? '';
if ($explicitReturn) {
  $sanitizeBack = function($candidate){
    if (!$candidate) return null;
    $parsed = parse_url($candidate);
    if (isset($parsed['scheme']) || isset($parsed['host'])) return null; // block absolute external
    $path = $parsed['path'] ?? '';
    if ($path === '' || str_contains($path,'..')) return null;
    if (!preg_match('~^/?[A-Za-z0-9_./#-]+$~', $path)) return null;
    $safe = ltrim($path,'/');
    if (!empty($parsed['query'])) $safe .= '?' . $parsed['query'];
    if (!empty($parsed['fragment']) && !str_contains($safe,'#')) $safe .= '#' . $parsed['fragment'];
    return $safe;
  };
  if ($tmp = $sanitizeBack($explicitReturn)) {
    $backUrl = $tmp;
  }
}

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
  // Consistent behavior: show flash and redirect to the user's dashboard
  Helpers::flash('error','You do not have permission to access that page.');
  Helpers::redirectToRoleDashboard();
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

<div class="container pt-3 pb-4 fade-up fade-delay-1">
  <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back</a>
</div>

<div class="container pb-5">
  <div class="row g-4">
    <div class="col-lg-5 fade-up fade-delay-2">
      <div class="jsp-summary-card mb-4">
        <div class="jsp-summary-inner p-4">
          <div class="d-flex flex-wrap align-items-start mb-3 w-100 gap-3">
            <div class="jsp-avatar jsp-avatar-lg flex-shrink-0">
              <?php if (!empty($target->profile_picture)): ?>
                <img src="../<?php echo htmlspecialchars($target->profile_picture); ?>" alt="Profile photo of <?php echo htmlspecialchars($target->name ?: 'User'); ?>">
              <?php else: ?>
                <i class="bi bi-person" aria-hidden="true"></i>
              <?php endif; ?>
            </div>
            <div class="flex-grow-1" style="min-width:240px;">
              <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <h1 class="h4 fw-bold mb-0" style="letter-spacing:-.5px;">
                  <?php echo htmlspecialchars($target->name ?: 'Unnamed User'); ?>
                </h1>
                <?php if (!empty($target->pwd_id_status)): ?>
                  <?php $vs=$target->pwd_id_status; $vClass=match($vs){ 'Verified'=>'success','Pending'=>'warning','Rejected'=>'danger', default=>'secondary'}; ?>
                  <span class="badge text-bg-<?php echo $vClass; ?>">PWD: <?php echo htmlspecialchars($vs); ?></span>
                <?php endif; ?>
                <span class="ms-auto d-flex gap-2">
                  <?php if ($resumeSafe): ?><a class="btn btn-outline-accent btn-sm" href="../<?php echo htmlspecialchars($resumeSafe); ?>" target="_blank"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>Resume</a><?php endif; ?>
                  <?php if ($isSelf): ?><a class="btn btn-gradient btn-sm d-inline-flex align-items-center" href="profile_edit.php"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Edit</a><?php endif; ?>
                </span>
              </div>
              <div class="jsp-meta-chips mb-2">
                <span class="jsp-chip"><i class="bi bi-mortarboard" aria-hidden="true"></i><?php echo htmlspecialchars($educationLabel); ?></span>
                <span class="jsp-chip"><i class="bi bi-briefcase" aria-hidden="true"></i><?php echo htmlspecialchars($experienceLabel); ?></span>
                <?php if (!empty($target->region) || !empty($target->province) || !empty($target->city)): ?>
                  <span class="jsp-chip"><i class="bi bi-geo" aria-hidden="true"></i><?php $parts=array_filter([$target->city,$target->province,$target->region]); echo htmlspecialchars(implode(', ',$parts)); ?></span>
                <?php endif; ?>
                <?php if ($videoSafe): ?><span class="jsp-chip"><i class="bi bi-camera-video" aria-hidden="true"></i>Video Intro</span><?php endif; ?>
              </div>
              <?php if (!empty($target->primary_skill_summary)): ?>
                <div class="small text-muted" style="max-width:720px;">"<?php echo nl2br(htmlspecialchars($target->primary_skill_summary)); ?>"</div>
              <?php elseif ($isSelf): ?>
                <div class="small text-muted fst-italic" style="max-width:720px;">Add a professional summary so employers can better understand your strengths. <a href="profile_edit.php" class="text-decoration-none">Add now</a>.</div>
              <?php endif; ?>
            </div>
          </div>
          <h2 class="h6 fw-bold mb-3 text-uppercase small">Profile Details</h2>
      <?php // Skill tags placeholder (replace with actual retrieval if implemented later)
      $skillTags = []; // e.g. $skillTags = User::listSkills($target->user_id);
      ?>
          <?php if (!empty($skillTags)): ?>
            <div class="mb-3">
              <div class="d-flex flex-wrap gap-1">
                <?php foreach ($skillTags as $sk): ?>
                  <span class="badge rounded-pill text-bg-light border" style="font-weight:500; font-size:.65rem; letter-spacing:.5px;"><i class="bi bi-stars me-1" aria-hidden="true"></i><?php echo htmlspecialchars($sk); ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php elseif ($isSelf): ?>
            <div class="mb-3 small text-muted">You have no skill tags yet. <a href="profile_edit.php" class="text-decoration-none">Add skills</a>.</div>
          <?php endif; ?>
          <div class="data-grid">
            <div class="data-item"><div class="label">Email</div><div class="value"><?php echo htmlspecialchars($target->email); ?></div></div>
            <div class="data-item"><div class="label">Disability</div><div class="value"><?php echo htmlspecialchars($disabilityLabel); ?></div></div>
            <?php if (!empty($target->date_of_birth)): ?>
              <div class="data-item"><div class="label">Age</div><div class="value"><?php $dobTs=strtotime($target->date_of_birth); if($dobTs){ $age=(int)date('Y')-(int)date('Y',$dobTs); if(date('md')<date('md',$dobTs))$age--; echo htmlspecialchars($age.' yrs'); } else echo '—'; ?></div></div>
            <?php endif; ?>
            <?php if (!empty($target->phone)): ?><div class="data-item"><div class="label">Phone</div><div class="value"><?php echo htmlspecialchars($target->phone); ?></div></div><?php endif; ?>
            <?php if (!empty($target->disability_type)): ?><div class="data-item"><div class="label">Disability Type</div><div class="value"><?php echo htmlspecialchars($target->disability_type); ?></div></div><?php endif; ?>
            <?php if (!empty($target->disability_severity)): ?><div class="data-item"><div class="label">Severity</div><div class="value"><?php echo htmlspecialchars($target->disability_severity); ?></div></div><?php endif; ?>
            <?php if (!empty($target->assistive_devices)): ?><div class="data-item"><div class="label">Assistive Devices</div><div class="value"><?php echo htmlspecialchars($target->assistive_devices); ?></div></div><?php endif; ?>
            <div class="data-item"><div class="label">Education</div><div class="value"><?php echo htmlspecialchars($educationLabel); ?></div></div>
            <div class="data-item"><div class="label">Experience</div><div class="value"><?php echo htmlspecialchars($experienceLabel); ?></div></div>
            <?php if (!empty($target->gender)): ?><div class="data-item"><div class="label">Gender</div><div class="value"><?php echo htmlspecialchars($target->gender); ?></div></div><?php endif; ?>
            <?php if ($resumeSafe): ?><div class="data-item"><div class="label">Resume</div><div class="value"><a href="../<?php echo htmlspecialchars($resumeSafe); ?>" target="_blank" class="text-decoration-none"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>View</a></div></div><?php endif; ?>
            <?php if ($videoSafe): ?><div class="data-item"><div class="label">Video Intro</div><div class="value"><a href="../<?php echo htmlspecialchars($videoSafe); ?>" target="_blank" class="text-decoration-none"><i class="bi bi-camera-video me-1" aria-hidden="true"></i>Watch</a></div></div><?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <!-- Applications -->
  <div class="section-card applications-table-wrapper fade-up fade-delay-3" role="region" aria-label="Applications list">
        <div class="section-card-header mb-2">
          <div class="d-flex align-items-center"><span class="section-icon me-2"><i class="bi bi-send" aria-hidden="true"></i></span><h2 class="h6 fw-semibold mb-0">Applications</h2></div>
        </div>
        <?php if (!$applications): ?>
          <div class="text-muted small">No applications found or not visible for your role.</div>
        <?php else: ?>
          <div class="table-responsive small">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Job Title</th>
                  <th>Status</th>
                  <th>Match</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($applications as $app): ?>
                  <tr>
                    <td><a href="job_view.php?job_id=<?php echo urlencode($app['job_id']); ?>" class="text-decoration-none"><?php echo htmlspecialchars($app['title']); ?></a></td>
                    <td><span class="badge bg-<?php echo $app['status']==='Approved'?'success':($app['status']==='Declined'?'danger':'secondary'); ?>"><?php echo htmlspecialchars($app['status']); ?></span></td>
                    <td><?php echo htmlspecialchars((float)$app['match_score']); ?>%</td>
                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($app['created_at']))); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- Experience -->
  <div class="section-card p-4 mt-4 fade-up fade-delay-4" role="region" aria-label="Work experience">
        <div class="section-card-header">
          <div class="d-flex align-items-center"><span class="section-icon me-2"><i class="bi bi-briefcase" aria-hidden="true"></i></span><h2 class="h6 fw-semibold mb-0">Work Experience</h2></div>
        </div>
        <?php if (!$experiences): ?>
          <div class="text-muted small">No experience listed.</div>
        <?php else: ?>
          <ul class="bulleted-list mb-0">
            <?php foreach ($experiences as $exp): ?>
              <li>
                <span class="fw-semibold"><?php echo Helpers::sanitizeOutput($exp['position']); ?></span> @ <?php echo Helpers::sanitizeOutput($exp['company']); ?>
                <span class="text-muted">(<?php echo htmlspecialchars(substr($exp['start_date'],0,7)); ?> - <?php echo $exp['is_current'] ? 'Present' : ($exp['end_date'] ? htmlspecialchars(substr($exp['end_date'],0,7)) : '—'); ?>)</span>
                <?php if (!empty($exp['description'])): ?><div class="mt-1 text-muted"><?php echo nl2br(htmlspecialchars($exp['description'])); ?></div><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <!-- Certifications -->
  <div class="section-card p-4 mt-4 fade-up fade-delay-5" role="region" aria-label="Certifications">
        <div class="section-card-header">
          <div class="d-flex align-items-center"><span class="section-icon me-2"><i class="bi bi-patch-check" aria-hidden="true"></i></span><h2 class="h6 fw-semibold mb-0">Certifications</h2></div>
        </div>
        <?php if (!$certs): ?>
          <div class="text-muted small">No certifications listed.</div>
        <?php else: ?>
          <ul class="bulleted-list mb-0">
            <?php foreach ($certs as $ct): ?>
              <li>
                <span class="fw-semibold"><?php echo Helpers::sanitizeOutput($ct['name']); ?></span>
                <?php if ($ct['issuer']): ?><span class="text-muted"> · <?php echo Helpers::sanitizeOutput($ct['issuer']); ?></span><?php endif; ?>
                <?php if ($ct['issued_date']): ?><span class="text-muted"> (<?php echo htmlspecialchars(substr($ct['issued_date'],0,7)); ?>)</span><?php endif; ?>
                <?php if ($ct['credential_id']): ?><div class="text-muted">Credential: <?php echo Helpers::sanitizeOutput($ct['credential_id']); ?></div><?php endif; ?>
                <?php if ($ct['attachment_path']): ?><div><a class="text-decoration-none" href="../<?php echo htmlspecialchars($ct['attachment_path']); ?>" target="_blank"><i class="bi bi-paperclip me-1" aria-hidden="true"></i>Attachment</a></div><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>