<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';
require_once '../classes/Application.php';
require_once '../classes/Job.php';
require_once '../classes/Experience.php';
require_once '../classes/Certification.php';

Helpers::requireLogin();
Helpers::requireRole('job_seeker');

$user = User::findById($_SESSION['user_id']);

// --------------------------------------------------
// Profile Completion Logic
// Add or remove fields here depending on what you require
// --------------------------------------------------
$profileFields = [
  'name'                 => $user->name ?? '',
  'email'                => $user->email ?? '',
  'education_level'      => $user->education_level ?? ($user->education ?? ''),
  'disability'           => $user->disability ?? '',
  'disability_type'      => $user->disability_type ?? '',
  'primary_skill_summary'=> $user->primary_skill_summary ?? '',
  'phone'                => $user->phone ?? '',
  'location'             => ($user->region && $user->province && $user->city) ? 'ok' : '',
  'resume'               => $user->resume ?? '',
  'video_intro'          => $user->video_intro ?? '',
];

$totalFields    = count($profileFields);
$filledFields   = count(array_filter($profileFields, fn($v) => trim((string)$v) !== ''));
$profilePercent = $totalFields ? (int)round(($filledFields / $totalFields) * 100) : 0;
$profileBarClass = 'bg-danger';
if ($profilePercent >= 80)       $profileBarClass = 'bg-success';
elseif ($profilePercent >= 50)   $profileBarClass = 'bg-warning';

$missing = [];
foreach ($profileFields as $k => $v) {
    if (trim((string)$v) === '') {
        $missing[] = ucfirst(str_replace('_', ' ', $k));
    }
}

// --------------------------------------------------
// Fetch user applications
// --------------------------------------------------
$apps = Application::listByUser($user->user_id);  // Should return latest first; adjust if not
// Fetch experience & certifications (limit display later)
$experiences = Experience::listByUser($user->user_id);
$certs = Certification::listByUser($user->user_id);

// Build job status map
$jobStatusMap = [];
try {
    if ($apps) {
        $jobIds = array_values(array_unique(array_map(fn($a) => $a['job_id'], $apps)));
        if ($jobIds) {
            $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT job_id, status FROM jobs WHERE job_id IN ($placeholders)");
            $stmt->execute($jobIds);
            foreach ($stmt->fetchAll() as $row) {
                $jobStatusMap[$row['job_id']] = $row['status'] ?? 'Open';
            }
        }
    }
} catch (Throwable $e) {
    // silently ignore
}

$RECENT_LIMIT = 8;
$recentApps   = array_slice($apps, 0, $RECENT_LIMIT);

// Quick counts
$totalApps     = count($apps);
$approvedCount = count(array_filter($apps, fn($a) => ($a['status'] ?? '') === 'Approved'));
$pendingCount  = count(array_filter($apps, fn($a) => ($a['status'] ?? '') === 'Pending'));

include '../includes/header.php';
include '../includes/nav.php';
?>
<?php if (!empty($_SESSION['flash'])): $flashes = Helpers::getFlashes(); foreach ($flashes as $k=>$m): $t=Helpers::mapFlashType($k); ?>
  <div class="alert alert-<?php echo $t; ?> alert-dismissible fade show auto-dismiss">
    <?php echo htmlspecialchars($m); ?>
    <button class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endforeach; endif; ?>

<!-- Dashboard Hero -->
<section class="ud-hero">
  <div class="container px-0">
    <h1 class="fade-up fade-delay-2">Welcome, <?php echo Helpers::sanitizeOutput($user->name); ?></h1>
    <p class="ud-sub fade-up fade-delay-3">Track your applications, strengthen your profile, and discover remote-inclusive roles that match your skills.</p>
    <div class="ud-stats-grid fade-up fade-delay-4" aria-label="Application statistics" role="list">
      <div class="ud-stat" role="listitem">
        <div class="ud-stat-label">Applied Jobs</div>
        <div class="ud-stat-value"><?php echo $totalApps; ?></div>
        <div class="ud-pill">Total</div>
      </div>
      <div class="ud-stat" role="listitem">
        <div class="ud-stat-label">Approved</div>
        <div class="ud-stat-value"><?php echo $approvedCount; ?></div>
        <div class="ud-pill text-success" style="background:#e6f8ed; color:#10632c;">Wins</div>
      </div>
      <div class="ud-stat" role="listitem">
        <div class="ud-stat-label">Pending</div>
        <div class="ud-stat-value"><?php echo $pendingCount; ?></div>
        <div class="ud-pill" style="background:#fff6e0; color:#7a4b00;">Open</div>
      </div>
      <div class="ud-stat" role="listitem">
        <div class="ud-stat-label">Profile %</div>
        <div class="ud-stat-value"><?php echo $profilePercent; ?>%</div>
        <div class="ud-pill" style="background:#eef6ff;">Progress</div>
      </div>
    </div>
  </div>
</section>

<div class="row g-4 align-items-start">
  <!-- Left Column -->
  <div class="col-lg-7">
    <div class="profile-progress-card mb-4">
      <div class="profile-progress-inner p-4">
        <div class="d-flex flex-wrap gap-2 mb-3">
          <a class="btn btn-gradient" href="index.php"><i class="bi bi-search me-1" aria-hidden="true"></i><span>Find Jobs</span></a>
          <a class="btn btn-outline-secondary" href="profile_edit.php"><i class="bi bi-person-lines-fill me-1" aria-hidden="true"></i><span>Update Profile</span></a>
          <a class="btn btn-outline-accent" href="applications.php"><i class="bi bi-list-check me-1" aria-hidden="true"></i><span>Applications</span></a>
        </div>
        <div class="mb-2 d-flex justify-content-between align-items-center">
          <div class="small fw-semibold">Profile Completion</div>
          <div class="small text-muted"><?php echo $filledFields . '/' . $totalFields; ?> (<?php echo $profilePercent; ?>%)</div>
        </div>
        <div class="progress" style="height:10px;">
          <div class="progress-bar <?php echo $profileBarClass; ?>" style="width:<?php echo $profilePercent; ?>%" role="progressbar" aria-valuenow="<?php echo $profilePercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <?php if ($profilePercent < 100): ?>
          <div class="small mt-2 text-muted">
            Improve visibility by completing missing fields.
            <?php if ($missing): ?><br><span class="text-danger">Missing:</span> <?php echo htmlspecialchars(implode(', ', $missing)); ?><?php endif; ?>
          </div>
        <?php else: ?>
          <div class="small mt-2 text-success">Great! Your profile is fully complete.</div>
        <?php endif; ?>

        <hr class="my-4">
        <div class="row g-3 mb-1">
          <div class="col-md-6">
            <div class="panel-card p-3 h-100">
              <div class="text-muted small mb-1">Education</div>
              <div class="fw-semibold"><?php echo Helpers::sanitizeOutput(($user->education_level ?: $user->education) ?: 'Not specified'); ?></div>
            </div>
          </div>
            <div class="col-md-6">
              <div class="panel-card p-3 h-100">
                <div class="text-muted small mb-1">Disability</div>
                <div class="fw-semibold"><?php echo Helpers::sanitizeOutput($user->disability ?: 'Not specified'); ?></div>
              </div>
            </div>
        </div>

        <!-- Experience -->
        <div class="panel-card mt-4 p-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h3 class="h6 fw-semibold mb-0"><span class="panel-heading-icon"><i class="bi bi-briefcase" aria-hidden="true"></i></span>Recent Experience</h3>
            <a href="profile_edit.php#employment-section" class="mini-link">Manage</a>
          </div>
          <?php if (!$experiences): ?>
            <div class="small text-muted">No experience added yet.</div>
          <?php else: ?>
            <ul class="list-unstyled small mb-0 exp-list">
              <?php foreach (array_slice($experiences,0,3) as $exp): ?>
                <li class="mb-1">
                  <span class="fw-semibold"><?php echo Helpers::sanitizeOutput($exp['position']); ?></span> @ <?php echo Helpers::sanitizeOutput($exp['company']); ?>
                  <span class="text-muted">(<?php echo htmlspecialchars(substr($exp['start_date'],0,7)); ?> - <?php echo $exp['is_current'] ? 'Present' : ($exp['end_date'] ? htmlspecialchars(substr($exp['end_date'],0,7)) : '—'); ?>)</span>
                </li>
              <?php endforeach; ?>
            </ul>
            <?php if (count($experiences) > 3): ?><div class="small mt-1"><a href="profile_edit.php#employment-section">View all (<?php echo count($experiences); ?>)</a></div><?php endif; ?>
          <?php endif; ?>
        </div>

        <!-- Certifications -->
        <div class="panel-card mt-4 p-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h3 class="h6 fw-semibold mb-0"><span class="panel-heading-icon"><i class="bi bi-patch-check" aria-hidden="true"></i></span>Certifications</h3>
            <a href="profile_edit.php#employment-section" class="mini-link">Manage</a>
          </div>
          <?php if (!$certs): ?>
            <div class="small text-muted">No certifications yet.</div>
          <?php else: ?>
            <ul class="list-unstyled small mb-0 cert-list">
              <?php foreach (array_slice($certs,0,4) as $ct): ?>
                <li class="mb-1">
                  <span class="fw-semibold"><?php echo Helpers::sanitizeOutput($ct['name']); ?></span>
                  <?php if ($ct['issuer']): ?><span class="text-muted"> · <?php echo Helpers::sanitizeOutput($ct['issuer']); ?></span><?php endif; ?>
                  <?php if ($ct['issued_date']): ?><span class="text-muted"> (<?php echo htmlspecialchars(substr($ct['issued_date'],0,7)); ?>)</span><?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
            <?php if (count($certs) > 4): ?><div class="small mt-1"><a href="profile_edit.php#employment-section">View all (<?php echo count($certs); ?>)</a></div><?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Right Column -->
  <div class="col-lg-5" id="applications">
    <div class="apps-table-wrapper h-100 d-flex flex-column">
      <div class="d-flex align-items-center mb-3">
        <span class="panel-heading-icon me-2"><i class="bi bi-list-check" aria-hidden="true"></i></span>
        <h3 class="h6 fw-semibold mb-0">My Recent Applications</h3>
      </div>
      <?php if (!$recentApps): ?>
        <div class="alert alert-secondary small mb-0">You have not applied to any jobs yet.</div>
      <?php else: ?>
        <div class="table-responsive small flex-grow-1">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Job</th>
                <th class="text-center">Match</th>
                <th>Status</th>
                <th class="text-nowrap">Applied</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentApps as $a): ?>
                <?php
                  $status = $a['status'] ?? 'Pending';
                  $badgeClass = match ($status) {
                    'Approved' => 'success',
                    'Declined' => 'danger',
                    'Pending'  => 'warning',
                    default    => 'secondary'
                  };
                  $jobState = $jobStatusMap[$a['job_id']] ?? 'Open';
                  $jobStateBadge = $jobState !== 'Open'
                    ? '<span class="badge bg-secondary ms-1">'.htmlspecialchars($jobState).'</span>' : '';
                  $matchScore = isset($a['match_score']) && $a['match_score'] !== null
                    ? (int)$a['match_score'].'%' : '—';
                ?>
                <tr>
                  <td class="small">
                    <a class="text-decoration-none" href="job_view.php?job_id=<?php echo urlencode($a['job_id']); ?>">
                      <?php echo Helpers::sanitizeOutput($a['title']); ?>
                    </a>
                    <?php echo $jobStateBadge; ?>
                  </td>
                  <td class="text-center small"><?php echo $matchScore; ?></td>
                  <td class="small">
                    <span class="badge text-bg-<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                  </td>
                  <td class="small text-nowrap"><?php echo htmlspecialchars(date('M d', strtotime($a['created_at']))); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($totalApps > $RECENT_LIMIT): ?>
          <div class="mt-3 text-end">
            <a class="mini-link" href="applications.php">View all (<?php echo $totalApps; ?>)</a>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>