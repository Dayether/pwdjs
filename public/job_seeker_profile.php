<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';
require_once '../classes/Job.php';
require_once '../classes/Application.php';

Helpers::requireLogin();

$viewerId   = $_SESSION['user_id'] ?? null;
$viewerRole = $_SESSION['role'] ?? '';
$targetId   = $_GET['user_id'] ?? '';

$jobSeeker = $targetId ? User::findById($targetId) : null;

/* Compute back URL (supports ?return=, else referer, else fallback by role) */
function computeBackUrl(string $viewerRole): string {
    $raw = $_GET['return'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    $fallback = $viewerRole === 'employer' ? 'employer_dashboard.php'
              : ($viewerRole === 'admin' ? 'admin_reports.php' : 'user_dashboard.php');
    if ($raw === '') return $fallback;
    $parsed = parse_url($raw);
    if (isset($parsed['scheme']) || isset($parsed['host'])) return $fallback;
    $path = ltrim($parsed['path'] ?? '', '/');
    if ($path === '' || strpos($path, '..') !== false) return $fallback;
    $final = $path;
    if (!empty($parsed['query'])) $final .= '?' . $parsed['query'];
    return $final;
}
$backUrl = computeBackUrl($viewerRole);

include '../includes/header.php';
include '../includes/nav.php';

if (!$jobSeeker || $jobSeeker->role !== 'job_seeker') {
    echo '<div class="container py-5"><div class="alert alert-danger">Job seeker profile not found.</div></div>';
    include '../includes/footer.php';
    exit;
}

$isSelf = ($viewerRole === 'job_seeker' && $viewerId === $jobSeeker->user_id);

/* Authorization
   - Admin: all
   - Self: own
   - Employer: only if may application sa isa man sa jobs niya
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
        $chk->execute([$jobSeeker->user_id, $viewerId]);
        if ($chk->fetch()) $authorized = true;
    } catch (Throwable $e) {
        $authorized = false;
    }
}

if (!$authorized) {
    echo '<div class="container py-5"><div class="alert alert-warning">You are not authorized to view this profile.</div></div>';
    include '../includes/footer.php';
    exit;
}

/* Fetch applications
   - Admin / Self: all
   - Employer: only to employer's jobs
*/
$applications = [];
try {
    $pdo = Database::getConnection();
    if ($viewerRole === 'admin' || $isSelf) {
        $stmt = $pdo->prepare("
            SELECT a.application_id, a.job_id, a.status, a.created_at, a.match_score,
                   j.title
            FROM applications a
            JOIN jobs j ON a.job_id = j.job_id
            WHERE a.user_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$jobSeeker->user_id]);
    } elseif ($viewerRole === 'employer') {
        $stmt = $pdo->prepare("
            SELECT a.application_id, a.job_id, a.status, a.created_at, a.match_score,
                   j.title
            FROM applications a
            JOIN jobs j ON a.job_id = j.job_id
            WHERE a.user_id = ? AND j.employer_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$jobSeeker->user_id, $viewerId]);
    }
    $applications = $stmt->fetchAll();
} catch (Throwable $e) {
    $applications = [];
}

$resumePath = $jobSeeker->resume;
$videoPath  = $jobSeeker->video_intro;

function safeFileLink(?string $path): ?string {
    if (!$path) return null;
    if (strpos($path, '..') !== false) return null;
    return $path;
}
$resumeSafe = safeFileLink($resumePath);
$videoSafe  = safeFileLink($videoPath);

$experienceLabel = ($jobSeeker->experience !== null) ? (int)$jobSeeker->experience . ' year' . ($jobSeeker->experience == 1 ? '' : 's') : '0 years';
$educationLabel  = $jobSeeker->education ?: 'Not specified';
$disabilityLabel = $jobSeeker->disability ?: 'Not specified';

/* Flash (optional) */
$rawFlash = $_SESSION['flash'] ?? [];
if (isset($_SESSION['flash'])) unset($_SESSION['flash']);
$toast = null;
foreach (['msg'=>'success','error'=>'danger','warn'=>'warning','info'=>'info'] as $k=>$type) {
    if (!empty($rawFlash[$k])) {
        $toast = ['type'=>$type,'message'=>$rawFlash[$k]];
        break;
    }
}
?>
<style>
.toast-container{z-index:1080;}
</style>
<?php if ($toast): ?>
<div class="toast-container position-fixed top-0 end-0 p-3">
  <div class="toast text-bg-<?php echo htmlspecialchars($toast['type']); ?> show" role="alert" aria-live="assertive">
    <div class="d-flex">
      <div class="toast-body">
        <i class="bi bi-info-circle me-2"></i><?php echo htmlspecialchars($toast['message']); ?>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="container py-4" id="main-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
      </a>
    </div>
    <div>
      <?php if ($isSelf): ?>
        <a class="btn btn-outline-primary btn-sm" href="profile_edit.php">
          <i class="bi bi-pencil-square me-1"></i>Edit My Profile
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="row">
    <div class="col-md-5 mb-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <h2 class="h5 fw-semibold mb-3">
            <i class="bi bi-person-fill me-2"></i><?php echo htmlspecialchars($jobSeeker->name); ?>
          </h2>
          <dl class="row small mb-0">
            <dt class="col-5 text-muted">Email</dt>
            <dd class="col-7"><?php echo htmlspecialchars($jobSeeker->email); ?></dd>

            <dt class="col-5 text-muted">Disability</dt>
            <dd class="col-7"><?php echo htmlspecialchars($disabilityLabel); ?></dd>

            <dt class="col-5 text-muted">Education</dt>
            <dd class="col-7"><?php echo htmlspecialchars($educationLabel); ?></dd>

            <dt class="col-5 text-muted">Experience</dt>
            <dd class="col-7"><?php echo htmlspecialchars($experienceLabel); ?></dd>

            <?php if ($resumeSafe): ?>
              <dt class="col-5 text-muted">Resume</dt>
              <dd class="col-7">
                <a class="text-decoration-none" href="<?php echo htmlspecialchars($resumeSafe); ?>" target="_blank">
                  <i class="bi bi-file-earmark-pdf me-1"></i>View / Download
                </a>
              </dd>
            <?php endif; ?>
            <?php if ($videoSafe): ?>
              <dt class="col-5 text-muted">Video Intro</dt>
              <dd class="col-7">
                <a class="text-decoration-none" href="<?php echo htmlspecialchars($videoSafe); ?>" target="_blank">
                  <i class="bi bi-camera-video me-1"></i>Play
                </a>
              </dd>
            <?php endif; ?>
          </dl>

          <?php if ($videoSafe && preg_match('/\\.(mp4|webm|ogg)$/i', $videoSafe)): ?>
            <div class="mt-3">
              <video style="max-width:100%;border-radius:6px;" controls preload="metadata">
                <source src="<?php echo htmlspecialchars($videoSafe); ?>">
              </video>
            </div>
          <?php endif; ?>

          <?php if (!$resumeSafe && !$videoSafe): ?>
            <div class="mt-3 small text-muted">
              No resume or video intro uploaded.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-md-7">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <h3 class="h6 fw-semibold mb-3">
            <i class="bi bi-briefcase me-2"></i>
            <?php echo ($viewerRole === 'employer') ? 'Applications to Your Jobs' : 'Applications'; ?>
            (<?php echo count($applications); ?>)
          </h3>

          <?php if (!$applications): ?>
            <div class="text-muted small">No applications found.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Job Title</th>
                    <th>Status</th>
                    <th>Match</th>
                    <th>Applied</th>
                    <?php if ($viewerRole === 'employer' || $viewerRole === 'admin'): ?>
                      <th style="width:140px;">Actions</th>
                    <?php endif; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($applications as $ap):
                      $st = $ap['status'];
                      $badge = $st==='Pending'?'secondary':($st==='Approved'?'success':($st==='Declined'?'danger':'secondary'));
                      $match = (float)$ap['match_score'];
                  ?>
                    <tr>
                      <td>
                        <a class="text-decoration-none fw-semibold"
                           href="job_view.php?job_id=<?php echo urlencode($ap['job_id']); ?>&return=<?php echo urlencode('job_seeker_profile.php?user_id='.$jobSeeker->user_id); ?>">
                          <?php echo htmlspecialchars($ap['title']); ?>
                        </a>
                      </td>
                      <td><span class="badge text-bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($st); ?></span></td>
                      <td>
                        <div class="d-flex align-items-center" style="min-width:90px;">
                          <div class="progress flex-grow-1 me-2" style="height:6px;">
                            <div class="progress-bar bg-primary" style="width: <?php echo max(0,min(100,$match)); ?>%;"></div>
                          </div>
                          <span class="badge text-bg-primary"><?php echo number_format($match,2); ?></span>
                        </div>
                      </td>
                      <td><span class="small text-muted"><?php echo date('M j, Y', strtotime($ap['created_at'])); ?></span></td>

                      <?php if ($viewerRole === 'employer' || $viewerRole === 'admin'): ?>
                        <td>
                          <div class="btn-group btn-group-sm" role="group">
                            <?php if ($st !== 'Approved'): ?>
                              <a class="btn btn-outline-success"
                                 href="applications.php?action=approve&application_id=<?php echo urlencode($ap['application_id']); ?>"
                                 onclick="return confirm('Approve this application?');"
                                 title="Approve">
                                <i class="bi bi-check2-circle"></i>
                              </a>
                            <?php endif; ?>
                            <?php if ($st !== 'Declined'): ?>
                              <a class="btn btn-outline-danger"
                                 href="applications.php?action=decline&application_id=<?php echo urlencode($ap['application_id']); ?>"
                                 onclick="return confirm('Decline this application?');"
                                 title="Decline">
                                <i class="bi bi-x-circle"></i>
                              </a>
                            <?php endif; ?>
                          </div>
                        </td>
                      <?php endif; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

          <?php if ($viewerRole === 'employer'): ?>
            <div class="small text-muted mt-2">
              You only see this job seeker&apos;s applications to your own jobs.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>