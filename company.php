<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';
require_once 'classes/User.php';

$userId = $_GET['user_id'] ?? '';
$employer = $userId ? User::findById($userId) : null;
if (!$employer || $employer->role !== 'employer' || $employer->employer_status !== 'Approved') {
    include 'includes/header.php';
    include 'includes/nav.php';
    echo '<div class="container py-5"><div class="alert alert-danger">Company not found or not approved.</div></div>';
    include 'includes/footer.php';
    exit;
}

$pdo = Database::getConnection();
// Jobs by employer (recent)
$jobs = [];
try {
    $st = $pdo->prepare("SELECT j.job_id, j.title, j.created_at, j.employment_type, j.salary_currency, j.salary_min, j.salary_max, COALESCE(jt.pwd_types, j.applicable_pwd_types) AS pwd_types
                        FROM jobs j
                        LEFT JOIN (
                          SELECT job_id, GROUP_CONCAT(DISTINCT pwd_type ORDER BY pwd_type SEPARATOR ',') AS pwd_types
                          FROM job_applicable_pwd_types
                          GROUP BY job_id
                        ) jt ON jt.job_id = j.job_id
                        WHERE j.employer_id=? AND j.moderation_status='Approved'
                        ORDER BY j.created_at DESC LIMIT 200");
    $st->execute([$employer->user_id]);
    $jobs = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $jobs = [];
}

// Reviews
$reviews = [];
$avgRating = null;
try {
    // Join to users to show reviewer name; coerce join to a common collation to avoid mix issues
    $st = $pdo->prepare("SELECT r.rating, r.comment, r.created_at, ru.name AS reviewer_name
                         FROM employer_reviews r
                         LEFT JOIN users ru ON ru.user_id = r.reviewer_user_id COLLATE utf8mb4_general_ci
                         WHERE r.employer_id=? AND r.status='Approved'
                         ORDER BY r.created_at DESC LIMIT 100");
    $st->execute([$employer->user_id]);
    $reviews = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $st2 = $pdo->prepare("SELECT AVG(rating) FROM employer_reviews WHERE employer_id=? AND status='Approved'");
    $st2->execute([$employer->user_id]);
    $avgRating = $st2->fetchColumn();
} catch (Throwable $e) {
}

include 'includes/header.php';
include 'includes/nav.php';
function stars($n)
{
    $n = (float)$n;
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $cls = ($i <= $n) ? 'bi-star-fill text-warning' : 'bi-star';
        $out .= '<i class="bi ' . $cls . '"></i>';
    }
    return $out;
}
?>
<div class="container py-4">
    <?php
    $jobsCount = is_array($jobs) ? count($jobs) : 0;
    $lastPosted = $jobsCount ? ($jobs[0]['created_at'] ?? null) : null; // jobs are DESC
    $memberSince = $employer->created_at ? date('M Y', strtotime($employer->created_at)) : null;
    $isApproved = ($employer->employer_status === 'Approved');
    $defaultTab = ($jobsCount > 0) ? 'jobs' : 'about';
    ?>
    <div class="card company-hero shadow-sm mb-3">
        <div class="card-body d-flex align-items-center gap-3 flex-wrap">
            <div class="company-logo-lg" aria-hidden="true">
                <div class="logo-wrap">
                    <?php if (!empty($employer->profile_picture)): ?>
                        <img src="<?php echo htmlspecialchars($employer->profile_picture); ?>" alt="<?php echo htmlspecialchars($employer->company_name ?: 'Company'); ?> logo" />
                    <?php else: ?>
                        <i class="bi bi-building" aria-hidden="true"></i>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex-grow-1 min-w-0">
                <h1 class="h4 mb-1 text-truncate">
                    <?php echo htmlspecialchars($employer->company_name ?: $employer->name ?: 'Company'); ?>
                    <?php if ($isApproved): ?>
                        <span class="align-middle ms-1" title="Approved employer" aria-label="Approved employer"><i class="bi bi-patch-check-fill text-success"></i></span>
                    <?php endif; ?>
                </h1>
                <div class="d-flex flex-wrap align-items-center gap-2 small text-muted">
                    <?php if ($avgRating): ?>
                        <span class="d-inline-flex align-items-center gap-1"><?php echo stars($avgRating); ?> <span><?php echo number_format($avgRating, 1); ?>/5</span></span>
                        <span aria-hidden="true">·</span>
                    <?php endif; ?>
                    <span><?php echo (int)$jobsCount; ?> job<?php echo $jobsCount === 1 ? '' : 's'; ?></span>
                    <?php if ($lastPosted): ?><span aria-hidden="true">·</span><span>Last posted <?php echo htmlspecialchars(date('M j, Y', strtotime($lastPosted))); ?></span><?php endif; ?>
                    <?php if ($memberSince): ?><span aria-hidden="true">·</span><span>Member since <?php echo htmlspecialchars($memberSince); ?></span><?php endif; ?>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-3 small mt-1">
                    <?php if ($employer->company_website): ?><span><i class="bi bi-globe me-1" aria-hidden="true"></i><a href="<?php echo htmlspecialchars($employer->company_website); ?>" target="_blank" rel="noopener">Website</a></span><?php endif; ?>
                    <?php if ($employer->company_phone): ?><span class="text-muted"><i class="bi bi-telephone me-1" aria-hidden="true"></i><?php echo htmlspecialchars($employer->company_phone); ?></span><?php endif; ?>
                    <?php if ($employer->city || $employer->region): ?><span class="text-muted"><i class="bi bi-geo me-1" aria-hidden="true"></i><?php echo htmlspecialchars(trim(($employer->city ?: '') . ($employer->city && $employer->region ? ', ' : '') . ($employer->region ?: ''))); ?></span><?php endif; ?>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 ms-auto">
                <a href="#" class="btn btn-primary btn-sm" id="btnViewJobs"><i class="bi bi-briefcase me-1"></i>View jobs</a>
                <a href="#" class="btn btn-outline-secondary btn-sm" id="btnViewReviews"><i class="bi bi-chat-left-text me-1"></i>Reviews</a>
            </div>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="small text-uppercase text-muted fw-semibold mb-2">Company details</div>
                    <ul class="list-unstyled small mb-0">
                        <li class="mb-1"><i class="bi bi-patch-check-fill text-success me-1" aria-hidden="true"></i><?php echo $isApproved ? 'Approved employer' : 'Employer'; ?></li>
                        <?php if ($memberSince): ?><li class="mb-1"><i class="bi bi-calendar3 me-1" aria-hidden="true"></i>Member since <?php echo htmlspecialchars($memberSince); ?></li><?php endif; ?>
                        <?php if ($employer->company_website): ?><li class="mb-1"><i class="bi bi-globe me-1" aria-hidden="true"></i><a href="<?php echo htmlspecialchars($employer->company_website); ?>" target="_blank" rel="noopener">Website</a></li><?php endif; ?>
                        <?php if ($employer->company_phone): ?><li class="mb-1 text-muted"><i class="bi bi-telephone me-1" aria-hidden="true"></i><?php echo htmlspecialchars($employer->company_phone); ?></li><?php endif; ?>
                        <?php if ($employer->city || $employer->region): ?><li class="mb-1 text-muted"><i class="bi bi-geo me-1" aria-hidden="true"></i><?php echo htmlspecialchars(trim(($employer->city ?: '') . ($employer->city && $employer->region ? ', ' : '') . ($employer->region ?: ''))); ?></li><?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item" role="presentation"><button class="nav-link <?php echo $defaultTab === 'about' ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#tab-about" type="button" role="tab">About</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link <?php echo $defaultTab === 'jobs' ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#tab-jobs" type="button" role="tab">Jobs <span class="badge bg-light text-dark"><?php echo count($jobs); ?></span></button></li>
                <li class="nav-item" role="presentation"><button class="nav-link <?php echo $defaultTab === 'reviews' ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#tab-reviews" type="button" role="tab">Reviews <span class="badge bg-light text-dark"><?php echo count($reviews); ?></span></button></li>
            </ul>
            <div class="tab-content p-3 border border-top-0 rounded-bottom bg-white shadow-sm">
                <div id="tab-about" class="tab-pane fade <?php echo $defaultTab === 'about' ? 'show active' : ''; ?>" role="tabpanel">
                    <p class="text-muted">This employer is approved on the platform. Contact details and permit information are kept private.</p>
                    <?php if ($employer->city || $employer->region): ?><div class="small"><i class="bi bi-geo"></i> <?php echo htmlspecialchars(trim(($employer->city ?: '') . ($employer->city && $employer->region ? ', ' : '') . ($employer->region ?: ''))); ?></div><?php endif; ?>
                </div>
                <div id="tab-jobs" class="tab-pane fade <?php echo $defaultTab === 'jobs' ? 'show active' : ''; ?>" role="tabpanel">
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
                                    <div class="small text-muted"><?php echo htmlspecialchars($j['employment_type']); ?> · <?php if ($j['salary_min'] || $j['salary_max']) echo htmlspecialchars(($j['salary_currency'] ?: 'PHP') . ' ' . number_format($j['salary_min'] ?: $j['salary_max']));
                                                                                                                            else echo 'Salary not specified'; ?></div>
                                    <?php if (!empty($j['pwd_types'])):
                                        $parts = array_values(array_unique(array_filter(array_map('trim', explode(',', $j['pwd_types'])))));
                                    ?>
                                        <div class="mt-1">
                                            <?php foreach ($parts as $pt): ?>
                                                <span class="badge bg-primary-subtle text-primary-emphasis border me-1 mb-1"><?php echo htmlspecialchars($pt); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div id="tab-reviews" class="tab-pane fade <?php echo $defaultTab === 'reviews' ? 'show active' : ''; ?>" role="tabpanel">
                    <?php
                    $canReview = Helpers::isLoggedIn() && Helpers::isJobSeeker();
                    if ($canReview): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title h6 mb-3">Write a review</h5>
                                <form id="reviewForm" class="row g-3" method="post">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(Helpers::csrfToken()); ?>">
                                    <input type="hidden" name="action" value="submit_review">
                                    <input type="hidden" name="employer_id" value="<?php echo htmlspecialchars($employer->user_id); ?>">
                                    <div class="col-md-4">
                                        <label class="form-label">Rating</label>
                                        <select name="rating" class="form-select" required>
                                            <option value="">Select…</option>
                                            <?php for ($i=5; $i>=1; $i--): ?>
                                                <option value="<?php echo $i; ?>"><?php echo $i; ?> star<?php echo $i>1?'s':''; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Comment <span class="text-muted small">(optional)</span></label>
                                        <textarea name="comment" class="form-control" rows="4" maxlength="2000" placeholder="Share your experience working with this employer (max 2000 characters)"></textarea>
                                    </div>
                                    <div class="col-12 d-flex justify-content-end gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-send me-1"></i>Submit review
                                        </button>
                                    </div>
                                    <div id="reviewMsg" class="mt-2" role="status" aria-live="polite"></div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!$reviews): ?>
                        <div class="alert alert-secondary">No reviews yet.</div>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($reviews as $r): ?>
                                <li class="mb-3">
                                    <div><?php echo stars((int)$r['rating']); ?></div>
                                    <?php if (!empty($r['comment'])): ?><div class="small"><?php echo htmlspecialchars($r['comment']); ?></div><?php endif; ?>
                                    <div class="text-muted small">
                                        <?php if (!empty($r['reviewer_name'])): ?>
                                            <span class="me-1 fw-semibold"><?php echo htmlspecialchars($r['reviewer_name']); ?></span>
                                            <span aria-hidden="true">·</span>
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars(date('M j, Y', strtotime($r['created_at']))); ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
    .company-hero .company-logo-lg .logo-wrap {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        overflow: hidden;
        background: #f5f7fb;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .company-hero .company-logo-lg img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .company-hero .bi-building {
        font-size: 2.25rem;
        color: #6c757d;
    }

    @media (max-width: 576px) {
        .company-hero .company-logo-lg .logo-wrap {
            width: 96px;
            height: 96px;
        }
    }
</style>
<script>
    (function() {
        const jobsBtn = document.getElementById('btnViewJobs');
        const revsBtn = document.getElementById('btnViewReviews');

        function showTab(target) {
            try {
                const btn = document.querySelector('[data-bs-target="' + target + '"]');
                if (btn && window.bootstrap) {
                    window.bootstrap.Tab.getOrCreateInstance(btn).show();
                    document.querySelector(target)?.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                } else if (btn) {
                    btn.click();
                    document.querySelector(target)?.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            } catch (_) {}
        }
        jobsBtn?.addEventListener('click', function(e) {
            e.preventDefault();
            showTab('#tab-jobs');
        });
        revsBtn?.addEventListener('click', function(e) {
            e.preventDefault();
            showTab('#tab-reviews');
        });
    })();
</script>
<script>
// Review form handler
(function(){
    const form = document.getElementById('reviewForm');
    if (!form) return;
    const msg = document.getElementById('reviewMsg');
    form.addEventListener('submit', async function(e){
        e.preventDefault();
        msg.className = '';
        msg.textContent = '';
        const fd = new FormData(form);
        try {
            const res = await fetch('api_employer_reviews.php', {
                method: 'POST',
                body: fd
            });
            const data = await res.json().catch(()=>({ok:false,message:'Unexpected response'}));
            if (data.ok) {
                msg.className = 'alert alert-success py-2 px-3';
                msg.textContent = data.message || 'Review submitted.';
                form.reset();
            } else {
                msg.className = 'alert alert-warning py-2 px-3';
                msg.textContent = data.message || 'Unable to submit review.';
            }
        } catch (err) {
            msg.className = 'alert alert-danger py-2 px-3';
            msg.textContent = 'Network error. Please try again.';
        }
    });
})();
</script>
<?php include 'includes/footer.php'; ?>