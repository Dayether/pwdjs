<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';
require_once 'classes/User.php';

if (session_status() === PHP_SESSION_NONE) session_start();
Helpers::requireLogin();
if (!Helpers::isEmployer()) {
    Helpers::flash('error', 'Access denied.');
    Helpers::redirectToRoleDashboard();
}
Helpers::storeLastPage();

// Parse inputs
$budget_min = isset($_GET['budget_min']) && is_numeric($_GET['budget_min']) ? (int)$_GET['budget_min'] : null;
$budget_max = isset($_GET['budget_max']) && is_numeric($_GET['budget_max']) ? (int)$_GET['budget_max'] : null;
$period = $_GET['period'] ?? 'monthly';
$include_unspecified = isset($_GET['include_unspecified']) && $_GET['include_unspecified'] === '1';
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;

$criteria = [
    'budget_min' => $budget_min,
    'budget_max' => $budget_max,
    'period' => $period,
    'include_unspecified' => $include_unspecified,
    'page' => $page,
    'limit' => 25,
];

$searchPerformed = ($budget_min !== null || $budget_max !== null || isset($_GET['period']) || isset($_GET['include_unspecified']));
$result = $searchPerformed ? User::searchCandidatesBySalary($criteria) : ['results' => [], 'page' => 1, 'limit' => 25, 'has_more' => false, 'total_overlapping' => 0];

include 'includes/header.php';
include 'includes/nav.php';
?>
<div class="container py-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h5 fw-semibold mb-0"><i class="bi bi-people me-2"></i>Find Candidates by Expected Salary</h1>
        <a class="btn btn-sm btn-outline-secondary" href="<?php echo Helpers::getLastPage('employer_dashboard.php'); ?>"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form class="row g-3 align-items-end" method="get" action="employer_candidates.php" aria-label="Candidate salary filter form">
                <div class="col-6 col-md-3">
                    <label for="budget_min" class="form-label small fw-semibold">Budget Min</label>
                    <input type="number" min="0" class="form-control form-control-sm" name="budget_min" id="budget_min" value="<?php echo htmlspecialchars($budget_min ?? ''); ?>" placeholder="e.g. 15000">
                </div>
                <div class="col-6 col-md-3">
                    <label for="budget_max" class="form-label small fw-semibold">Budget Max</label>
                    <input type="number" min="0" class="form-control form-control-sm" name="budget_max" id="budget_max" value="<?php echo htmlspecialchars($budget_max ?? ''); ?>" placeholder="e.g. 30000">
                </div>
                <div class="col-6 col-md-2">
                    <label for="period" class="form-label small fw-semibold">Period</label>
                    <select class="form-select form-select-sm" id="period" name="period">
                        <?php $periods = ['monthly' => 'Monthly', 'yearly' => 'Yearly', 'hourly' => 'Hourly'];
                        foreach ($periods as $k => $lbl): ?>
                            <option value="<?php echo $k; ?>" <?php echo $period === $k ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2 form-check mt-4 pt-2 ms-2">
                    <input type="checkbox" class="form-check-input" id="include_unspecified" name="include_unspecified" value="1" <?php echo $include_unspecified ? 'checked' : ''; ?>>
                    <label class="form-check-label small" for="include_unspecified">Include unspecified</label>
                </div>
                <div class="col-12 col-md-2 text-md-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>Search</button>
                </div>
            </form>
            <div class="small text-muted mt-2">Show candidates whose expected salary range overlaps your budget. Overlap rule: candidate_min ≤ budget_max AND candidate_max ≥ budget_min.</div>
        </div>
    </div>

    <?php if ($searchPerformed): ?>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="small fw-semibold">
                <?php echo (int)$result['total_overlapping']; ?> overlapping candidate(s)
                <?php if ($include_unspecified): ?> + unspecified (first page only)<?php endif; ?>
            </div>
            <?php if ($result['total_overlapping'] > 0): ?>
                <div class="small text-muted">Page <?php echo (int)$result['page']; ?><?php if ($result['has_more']) echo ' (more available)'; ?></div>
            <?php endif; ?>
        </div>

        <?php if (!$result['results']): ?>
            <div class="alert alert-secondary">No candidates matched your criteria.</div>
        <?php else: ?>
            <div class="row g-3" id="candidateResults" role="list">
                <?php foreach ($result['results'] as $cand): ?>
                    <?php
                    $cur = $cand['expected_salary_currency'] ?: 'PHP';
                    $min = $cand['expected_salary_min'];
                    $max = $cand['expected_salary_max'];
                    $per = $cand['expected_salary_period'] ?: $period;
                    $salaryText = 'Unspecified';
                    if ($min !== null || $max !== null) {
                        if ($min !== null && $max !== null && $min != $max) {
                            $salaryText = $cur . ' ' . number_format($min) . ' – ' . number_format($max) . ' / ' . ucfirst($per);
                        } else {
                            $one = ($min ?? $max);
                            $salaryText = $cur . ' ' . number_format((int)$one) . ' / ' . ucfirst($per);
                        }
                    }
                    ?>
                    <div class="col-12 col-md-6 col-lg-4" role="listitem">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <?php if (!empty($cand['profile_picture'])): ?>
                                        <img src="<?php echo htmlspecialchars($cand['profile_picture']); ?>" alt="Profile" class="rounded-circle border" style="width:48px;height:48px;object-fit:cover;">
                                    <?php else: ?>
                                        <span class="rounded-circle bg-light d-inline-flex justify-content-center align-items-center" style="width:48px;height:48px;">
                                            <i class="bi bi-person" style="font-size:1.2rem;"></i>
                                        </span>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-semibold text-truncate" title="<?php echo Helpers::sanitizeOutput($cand['name']); ?>"><?php echo Helpers::sanitizeOutput($cand['name']); ?></div>
                                        <div class="small text-muted" title="Expected salary"><?php echo htmlspecialchars($salaryText); ?></div>
                                    </div>
                                </div>
                                <div class="small flex-grow-1 mb-2" style="min-height:48px;">
                                    <?php echo Helpers::sanitizeOutput(mb_strimwidth($cand['primary_skill_summary'] ?? 'No summary provided.', 0, 120, '…')); ?>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-auto pt-2">
                                    <span class="badge text-bg-light border small">Exp: <?php echo (int)($cand['experience'] ?? 0); ?> yr(s)</span>
                                    <a class="btn btn-sm btn-outline-primary" href="employer_candidate_view.php?user_id=<?php echo urlencode($cand['user_id']); ?>">View</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php
            // Pagination controls (overlapping only)
            $prevPage = $result['page'] > 1 ? $result['page'] - 1 : null;
            $nextPage = $result['has_more'] ? $result['page'] + 1 : null;
            $baseParams = $_GET; // preserve current query
            ?>
            <nav class="mt-3" aria-label="Pagination">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo $prevPage ? '' : 'disabled'; ?>">
                        <?php if ($prevPage): $baseParams['page'] = $prevPage; ?>
                            <a class="page-link" href="?<?php echo http_build_query($baseParams); ?>" aria-label="Previous">&laquo;</a>
                        <?php else: ?>
                            <span class="page-link" aria-hidden="true">&laquo;</span>
                        <?php endif; ?>
                    </li>
                    <li class="page-item disabled"><span class="page-link">Page <?php echo (int)$result['page']; ?></span></li>
                    <li class="page-item <?php echo $nextPage ? '' : 'disabled'; ?>">
                        <?php if ($nextPage): $baseParams['page'] = $nextPage; ?>
                            <a class="page-link" href="?<?php echo http_build_query($baseParams); ?>" aria-label="Next">&raquo;</a>
                        <?php else: ?>
                            <span class="page-link" aria-hidden="true">&raquo;</span>
                        <?php endif; ?>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info">Enter a budget range and click Search to find matching candidates. You can leave one side blank to search from 0 up or for an exact value enter the same number in both.</div>
    <?php endif; ?>
</div>

<style>
    #candidateResults .card {
        transition: box-shadow .25s ease, transform .25s ease;
    }

    #candidateResults .card:hover {
        box-shadow: 0 6px 20px -4px rgba(0, 0, 0, .25);
        transform: translateY(-2px);
    }
</style>

<?php include 'includes/footer.php'; ?>