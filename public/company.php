<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';

$userId = $_GET['user_id'] ?? '';
$employer = $userId ? User::findById($userId) : null;
if (!$employer || $employer->role !== 'employer' || $employer->employer_status !== 'Approved') {
  include '../includes/header.php';
  include '../includes/nav.php';
  echo '<div class="container py-5"><div class="alert alert-danger">Company not found or not approved.</div></div>';
  include '../includes/footer.php';
  exit;
}

$pdo = Database::getConnection();
// Jobs by employer (recent)
$jobs = [];
try {
  $st = $pdo->prepare("SELECT job_id, title, created_at, employment_type, salary_currency, salary_min, salary_max FROM jobs WHERE employer_id=? ORDER BY created_at DESC LIMIT 200");
  $st->execute([$employer->user_id]);
  $jobs = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $jobs = []; }

// Reviews
$reviews = [];
$avgRating = null;
try {
  $st = $pdo->prepare("SELECT rating, comment, created_at FROM employer_reviews WHERE employer_id=? AND status='Approved' ORDER BY created_at DESC LIMIT 100");
  $st->execute([$employer->user_id]);
  $reviews = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $st2 = $pdo->prepare("SELECT AVG(rating) FROM employer_reviews WHERE employer_id=? AND status='Approved'");
  $st2->execute([$employer->user_id]);
  $avgRating = $st2->fetchColumn();
} catch (Throwable $e) {}

include '../includes/header.php';
include '../includes/nav.php';
function stars($n){
  $n = (float)$n;
  $out = '';
  for ($i=1; $i<=5; $i++) {
    $cls = ($i <= $n) ? 'bi-star-fill text-warning' : 'bi-star';
    $out .= '<i class="bi ' . $cls . '"></i>';
  }
  return $out;
}
?>
<div class="container py-4">
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body text-center">
          <div class="mb-2" style="width:96px;height:96px;border-radius:50%;overflow:hidden;margin:0 auto;background:#f5f7fb;display:flex;align-items:center;justify-content:center;">
            <?php if (!empty($employer->profile_picture)): ?>
              <img src="../<?php echo htmlspecialchars($employer->profile_picture); ?>" alt="Logo" style="width:100%;height:100%;object-fit:cover;"/>
            <?php else: ?>
              <i class="bi bi-building" style="font-size:2rem;color:#6c757d" aria-hidden="true"></i>
            <?php endif; ?>
          </div>
          <h1 class="h4 mb-1"><?php echo htmlspecialchars($employer->company_name ?: $employer->name ?: 'Company'); ?></h1>
          <?php if ($avgRating): ?>
            <div class="mb-2"><?php echo stars($avgRating); ?> <span class="small text-muted"><?php echo number_format($avgRating,1); ?>/5</span></div>
          <?php endif; ?>
          <?php if ($employer->company_website): ?><div class="small"><a href="<?php echo htmlspecialchars($employer->company_website); ?>" target="_blank" rel="noopener"><i class="bi bi-globe"></i> Website</a></div><?php endif; ?>
          <?php if ($employer->company_phone): ?><div class="small text-muted"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($employer->company_phone); ?></div><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-about" type="button" role="tab">About</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-jobs" type="button" role="tab">Jobs <span class="badge bg-light text-dark"><?php echo count($jobs); ?></span></button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-reviews" type="button" role="tab">Reviews <span class="badge bg-light text-dark"><?php echo count($reviews); ?></span></button></li>
      </ul>
      <div class="tab-content p-3 border border-top-0 rounded-bottom bg-white shadow-sm">
        <div id="tab-about" class="tab-pane fade show active" role="tabpanel">
          <p class="text-muted">This employer is approved on the platform. Contact details and permit information are kept private.</p>
          <?php if ($employer->city || $employer->region): ?><div class="small"><i class="bi bi-geo"></i> <?php echo htmlspecialchars(trim(($employer->city?:'').($employer->city&&$employer->region?', ':'').($employer->region?:''))); ?></div><?php endif; ?>
        </div>
        <div id="tab-jobs" class="tab-pane fade" role="tabpanel">
          <?php if (!$jobs): ?>
            <div class="alert alert-secondary">No jobs posted yet.</div>
          <?php else: ?>
            <div class="list-group">
              <?php foreach ($jobs as $j): ?>
                <a class="list-group-item list-group-item-action" href="job_view.php?job_id=<?php echo urlencode($j['job_id']); ?>">
                  <div class="d-flex w-100 justify-content-between">
                    <h5 class="mb-1"><?php echo htmlspecialchars($j['title']); ?></h5>
                    <small class="text-muted"><?php echo htmlspecialchars(date('M j, Y', strtotime($j['created_at']))); ?></small>
                  </div>
                  <div class="small text-muted"><?php echo htmlspecialchars($j['employment_type']); ?> · <?php if ($j['salary_min']||$j['salary_max']) echo htmlspecialchars(($j['salary_currency']?:'PHP').' '.number_format($j['salary_min']?:$j['salary_max'])); else echo 'Salary not specified'; ?></div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <div id="tab-reviews" class="tab-pane fade" role="tabpanel">
          <?php if (!$reviews): ?>
            <div class="alert alert-secondary">No reviews yet.</div>
          <?php else: ?>
            <ul class="list-unstyled mb-0">
              <?php foreach ($reviews as $r): ?>
                <li class="mb-3">
                  <div><?php echo stars((int)$r['rating']); ?></div>
                  <?php if (!empty($r['comment'])): ?><div class="small"><?php echo htmlspecialchars($r['comment']); ?></div><?php endif; ?>
                  <div class="text-muted small"><?php echo htmlspecialchars(date('M j, Y', strtotime($r['created_at']))); ?></div>
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
