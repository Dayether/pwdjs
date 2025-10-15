<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';
require_once 'classes/Job.php';
require_once 'classes/Skill.php';
require_once 'classes/Taxonomy.php';
require_once 'classes/User.php';

Helpers::requireRole('admin');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = Database::getConnection();

// Load employer choices (Approved employers only)
$employers = [];
try {
    $st = $pdo->query("SELECT user_id, company_name, email, name FROM users WHERE role='employer' AND COALESCE(employer_status,'Pending')='Approved' ORDER BY company_name, name");
    $employers = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $employers = [];
}

// Enumerations / lists
$employmentTypes = Taxonomy::employmentTypes();
$accessTags      = Taxonomy::accessibilityTags();
$pwdCats         = Taxonomy::disabilityCategories();
$eduLevels       = Taxonomy::educationLevels();
$generalSkills   = [
    '70+ WPM Typing',
    'Flexible Schedule',
    'Team Player',
    'Professional Attitude',
    'Strong Communication',
    'Adaptable / Quick Learner'
];

$errors = [];
$messages = [];
$duplicateWarning = [];
$duplicatePending = false;
$dupThreshold = 70;  // % similarity on title to warn
$scanLimit   = 12;   // check up to N recent jobs

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate employer selection
    $employer_id = trim($_POST['employer_id'] ?? '');
    if ($employer_id === '') {
        $errors[] = 'Select employer.';
    } else {
        try {
            $chk = $pdo->prepare("SELECT 1 FROM users WHERE user_id=? AND role='employer' AND COALESCE(employer_status,'Pending')='Approved' LIMIT 1");
            $chk->execute([$employer_id]);
            if (!$chk->fetchColumn()) $errors[] = 'Selected employer not found or not approved.';
        } catch (Throwable $e) {
            $errors[] = 'Employer validation failed.';
        }
    }

    // Collect + sanitize
    $title       = trim($_POST['title'] ?? '');
    $employment  = $_POST['employment_type'] ?? '';
    if (!in_array($employment, $employmentTypes, true)) $employment = '';
    $locCity     = trim($_POST['location_city'] ?? '');
    $locRegion   = trim($_POST['location_region'] ?? '');
    $salary_currency = strtoupper(trim($_POST['salary_currency'] ?? ''));
    $salary_min  = ($_POST['salary_min'] !== '' && $_POST['salary_min'] !== null) ? max(0, (int)$_POST['salary_min']) : null;
    $salary_max  = ($_POST['salary_max'] !== '' && $_POST['salary_max'] !== null) ? max(0, (int)$_POST['salary_max']) : null;
    $salary_period = $_POST['salary_period'] ?? '';
    if (!in_array($salary_period, ['monthly', 'yearly', 'hourly'], true)) $salary_period = '';
    $reqExpRaw   = trim((string)($_POST['required_experience'] ?? ''));
    $reqExp      = ($reqExpRaw === '' ? null : max(0, (int)$reqExpRaw));
    $reqEduRaw   = trim($_POST['required_education'] ?? '');
    if (strcasecmp($reqEduRaw, 'Any') === 0) {
        $reqEduRaw = '';
    }
    $description = trim($_POST['description'] ?? '');

    // Skills (checkbox general + comma separated extra)
    $skillsSelected = $_POST['required_skills'] ?? [];
    if (!is_array($skillsSelected)) $skillsSelected = [$skillsSelected];
    $skillsSelected = array_filter(array_map('trim', $skillsSelected));
    $additionalRaw = trim($_POST['additional_skills'] ?? '');
    $extraTokens = $additionalRaw !== '' ? Helpers::parseSkillInput($additionalRaw) : [];
    $merged = [];
    foreach (array_merge($skillsSelected, $extraTokens) as $s) {
        if ($s === '') continue;
        $k = mb_strtolower($s);
        if (!isset($merged[$k])) $merged[$k] = $s;
    }
    $skillsCsv = implode(', ', $merged);
    if ($skillsCsv === '') {
        $errors[] = 'Select at least one skill or add in Additional Skills.';
    }

    // Accessibility tags
    $tagsSelected = (array)($_POST['accessibility_tags'] ?? []);
    $tagsSelected = array_filter(array_map('trim', $tagsSelected));

    if ($title === '') $errors[] = 'Job title required';
    if ($employment === '') $errors[] = 'Employment type is required';
    if ($description === '') $errors[] = 'Description required';
    if ($locCity === '') $errors[] = 'City is required';
    if ($locRegion === '') $errors[] = 'Region / Province is required';
    if ($salary_currency === '') $errors[] = 'Salary currency is required';
    if ($salary_period === '') $errors[] = 'Salary period is required';
    if ($reqExp === null) $errors[] = 'Experience (years) is required';
    if ($salary_min !== null && $salary_max !== null && $salary_min > $salary_max) {
        $errors[] = 'Salary min cannot exceed salary max';
    }
    if (count($tagsSelected) === 0) $errors[] = 'Select at least one Accessibility & Inclusion tag.';

    // Duplicate detection for selected employer
    if (!$errors && $title !== '' && $employer_id !== '') {
        $matches = Job::findSimilarByEmployer($employer_id, ['title' => $title], $dupThreshold, $scanLimit);
        if ($matches) {
            $duplicateWarning = $matches;
            $duplicatePending = empty($_POST['confirm_duplicate']);
        }
    }

    // Handle image upload (optional)
    $job_image = null;
    if (!$errors && !$duplicatePending && !empty($_FILES['job_image']['name'])) {
        $okType = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $mime = $_FILES['job_image']['type'] ?? '';
        if (!in_array($mime, $okType, true)) {
            $errors[] = 'Job image must be JPG, PNG, GIF or WEBP';
        } elseif (($_FILES['job_image']['size'] ?? 0) > 2 * 1024 * 1024) {
            $errors[] = 'Job image too large (max 2MB)';
        } else {
            if (!is_dir(__DIR__ . '/uploads/job_images')) @mkdir(__DIR__ . '/uploads/job_images', 0775, true);
            $ext = pathinfo($_FILES['job_image']['name'], PATHINFO_EXTENSION);
            $fname = 'uploads/job_images/' . uniqid('job_') . '.' . strtolower($ext ?: 'jpg');
            if (move_uploaded_file($_FILES['job_image']['tmp_name'], __DIR__ . '/' . $fname)) {
                $job_image = $fname;
            } else {
                $errors[] = 'Failed to upload image';
            }
        }
    }

    // Require at least one PWD category
    $pwdSelected = isset($_POST['applicable_pwd_types']) ? array_filter(array_map('trim', (array)$_POST['applicable_pwd_types'])) : [];
    if (!$errors && !$duplicatePending && count($pwdSelected) === 0) {
        $errors[] = 'Select at least one Applicable PWD Category.';
    }

    $approveNow = !empty($_POST['approve_now']);

    if (!$errors && !$duplicatePending) {
        $data = [
            'title' => $title,
            'description' => $description,
            'required_experience' => $reqExp,
            'required_education' => $reqEduRaw,
            'required_skills_input' => $skillsCsv,
            'accessibility_tags' => implode(',', $tagsSelected),
            'applicable_pwd_types' => implode(',', $pwdSelected),
            'location_city' => $locCity,
            'location_region' => $locRegion,
            'employment_type' => $employment,
            'salary_currency' => $salary_currency,
            'salary_min' => $salary_min,
            'salary_max' => $salary_max,
            'salary_period' => $salary_period,
            'job_image' => $job_image,
        ];

        $res = Job::createReturnId($data, $employer_id);
        if ($res['ok']) {
            $job_id = $res['job_id'];
            if ($approveNow && $job_id) {
                Job::moderate($job_id, $_SESSION['user_id'], 'approve', 'Approved by admin on creation');
            }
            Helpers::flash('msg', 'Job created' . ($approveNow ? ' and approved' : '') . '.');
            Helpers::redirect('admin_jobs_moderation.php');
        } else {
            $errors[] = 'Creation failed';
        }
    }
}

include 'includes/header.php';
?>
<style>
    /* Create Job header visibility boost */
    .admin-main .admin-page-header {
        background:
            radial-gradient(1200px 150px at 30% -20%, rgba(96, 165, 250, .18), transparent),
            linear-gradient(180deg, rgba(15, 23, 42, .95), rgba(15, 23, 42, .65));
        border: 1px solid rgba(255, 255, 255, .08);
        border-radius: 14px;
        padding: .9rem 1rem .85rem;
    }

    .admin-main .admin-page-header .page-title {
        color: #f5faff;
        font-size: 1.5rem;
        font-weight: 700;
        text-shadow: 0 1px 2px rgba(0, 0, 0, .5)
    }

    .admin-main .admin-page-header .page-title i {
        color: #60a5fa;
        filter: drop-shadow(0 1px 6px rgba(96, 165, 250, .35))
    }

    .admin-main .admin-page-header .page-sub {
        color: #c7d7ef;
        opacity: .95;
        font-size: .85rem;
        margin: 0
    }

    .admin-main .admin-page-header .btn-outline-light {
        background: rgba(255, 255, 255, .06);
        border-color: rgba(255, 255, 255, .35);
        color: #eaf2ff
    }

    @media (prefers-color-scheme: light) {
        .admin-main .admin-page-header {
            background: linear-gradient(180deg, #e8eef9, #dae6fb);
            border-color: #cbd5e1
        }

        .admin-main .admin-page-header .page-title {
            color: #0f172a;
            text-shadow: none
        }

        .admin-main .admin-page-header .page-sub {
            color: #334155
        }
    }
</style>
<div class="admin-layout">
    <?php include 'includes/admin_sidebar.php'; ?>
    <div class="admin-main">
        <div class="admin-page-header mb-3">
            <div class="page-title-block">
                <h1 class="page-title"><i class="bi bi-briefcase"></i><span>Create Job (Admin)</span></h1>
                <p class="page-sub">Post a job on behalf of an approved employer. Optionally approve it immediately.</p>
            </div>
            <div class="page-actions">
                <a href="admin_jobs_moderation.php" class="btn btn-sm btn-outline-light"><i class="bi bi-clipboard-check me-1"></i>Moderation</a>
            </div>
        </div>

        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-1"></i><?php echo htmlspecialchars($e); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endforeach; ?>

        <?php if ($duplicatePending && $duplicateWarning): ?>
            <div class="alert alert-warning border-2">
                <div class="fw-semibold mb-1"><i class="bi bi-exclamation-octagon me-1"></i>Possible duplicate jobs detected</div>
                <ul class="small mb-2">
                    <?php foreach ($duplicateWarning as $d): ?>
                        <li>
                            <a href="job_view.php?job_id=<?php echo urlencode($d['job_id']); ?>" target="_blank"><?php echo htmlspecialchars($d['title']); ?></a>
                            (<?php echo $d['percent']; ?>% match<?php if ($d['exact_match']) echo ' · exact'; ?>,
                            <?php echo htmlspecialchars(date('M d, Y', strtotime($d['created_at']))); ?>,
                            status: <?php echo htmlspecialchars($d['status']); ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="small text-muted">This is a safeguard to avoid accidental duplicates.</div>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="job-create-form" id="adminJobCreateForm">
            <style>
                /* Card + layout */
                .job-create-form .card {
                    background: #0f1827;
                    border: 1px solid rgba(255, 255, 255, .08);
                    border-radius: 14px
                }

                .job-create-form .card-body {
                    padding: 1rem 1rem
                }

                /* Labels */
                .job-create-form .form-label {
                    color: #eaf2ff;
                    font-weight: 600;
                    font-size: .82rem
                }

                /* Dark inputs/selects */
                .job-create-form .form-control,
                .job-create-form .form-select,
                .job-create-form textarea {
                    background: #101b2b;
                    border: 1px solid #233246;
                    color: #dbe6f5;
                    border-radius: 9px
                }

                .job-create-form .form-control::placeholder {
                    color: #95a5bb;
                    opacity: 1
                }

                .job-create-form .form-control:focus,
                .job-create-form .form-select:focus,
                .job-create-form textarea:focus {
                    border-color: #2f4f77;
                    outline: none;
                    box-shadow: none
                }

                .job-create-form .form-check-input {
                    background: #101b2b;
                    border: 1px solid #233246
                }

                .job-create-form .form-check-input:checked {
                    background-color: #1f4d89;
                    border-color: #1f4d89
                }

                /* Helper text & validation */
                .job-create-form .small,
                .job-create-form .text-muted {
                    color: #eaf2ff !important;
                    opacity: .95
                }

                .job-create-form .form-hint {
                    color: #9fb4cc
                }

                .job-create-form .invalid-msg {
                    color: #ffd8d8;
                    background: rgba(154, 32, 32, .2);
                    border: 1px solid rgba(154, 32, 32, .35);
                    border-radius: 8px;
                    padding: .4rem .55rem;
                    margin-top: .5rem;
                    font-size: .75rem
                }

                /* Sections */
                .section-title {
                    margin: .4rem 0 .5rem;
                    font-size: .78rem;
                    letter-spacing: .08em;
                    text-transform: uppercase;
                    color: #9fb4cc;
                    border-top: 1px solid #1f2e45;
                    padding-top: .75rem
                }

                /* Chips/tags */
                .skill-chip,
                .acc-tag {
                    cursor: pointer;
                    user-select: none;
                    display: inline-flex;
                    align-items: center;
                    gap: .5rem;
                    padding: .45rem .7rem;
                    border-radius: 10px;
                    border: 1px solid #30445f;
                    background: #122033;
                    color: #cfe3ff;
                    font-size: .78rem;
                }

                .skill-chip input[type=checkbox],
                .acc-tag input[type=checkbox] {
                    margin-right: .5rem;
                }

                .acc-tag.selected {
                    background: rgba(37, 99, 235, .15);
                    border-color: #2c5db8;
                    color: #e4f0ff;
                }

                .skill-chip.is-checked {
                    background: rgba(37, 99, 235, .15);
                    border-color: #2c5db8;
                    color: #e4f0ff;
                }

                /* Image preview */
                .preview-box {
                    margin-top: .5rem;
                    display: none
                }

                .preview-box img {
                    max-width: 260px;
                    height: auto;
                    border-radius: 10px;
                    border: 1px solid #1f2e45;
                    box-shadow: 0 4px 12px -8px rgba(0, 0, 0, .6)
                }
            </style>
            <?php if ($duplicatePending): ?><input type="hidden" name="confirm_duplicate" value="1"><?php endif; ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Employer *</label>
                            <select name="employer_id" class="form-select" required>
                                <option value="" disabled selected>Select employer</option>
                                <?php foreach ($employers as $e): $label = trim(($e['company_name'] ?: $e['name']) . ' · ' . $e['email']); ?>
                                    <option value="<?php echo htmlspecialchars($e['user_id']); ?>" <?php if (($_POST['employer_id'] ?? '') === $e['user_id']) echo 'selected'; ?>><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="approveNow" name="approve_now" <?php echo !empty($_POST['approve_now']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="approveNow">Approve immediately</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="section-title">Job Basics</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Job Title *</label>
                            <input name="title" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" class="form-control" maxlength="100">
                            <div class="form-hint small"><span id="titleCount">0</span>/100</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Employment Type *</label>
                            <select name="employment_type" class="form-select" required>
                                <option value="" disabled <?php if (empty($_POST['employment_type'])) echo 'selected'; ?>>Select employment type</option>
                                <?php foreach ($employmentTypes as $t): ?>
                                    <option value="<?php echo htmlspecialchars($t); ?>" <?php if (($_POST['employment_type'] ?? '') === $t) echo 'selected'; ?>><?php echo htmlspecialchars($t); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="section-title">Location & Compensation</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">City *</label>
                            <input name="location_city" required value="<?php echo htmlspecialchars($_POST['location_city'] ?? ''); ?>" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Region / Province *</label>
                            <input name="location_region" required value="<?php echo htmlspecialchars($_POST['location_region'] ?? ''); ?>" class="form-control">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Salary Currency *</label>
                            <input name="salary_currency" required value="<?php echo htmlspecialchars($_POST['salary_currency'] ?? ''); ?>" placeholder="PHP" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Salary Min</label>
                            <input name="salary_min" type="number" min="0" value="<?php echo htmlspecialchars($_POST['salary_min'] ?? ''); ?>" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Salary Max</label>
                            <input name="salary_max" type="number" min="0" value="<?php echo htmlspecialchars($_POST['salary_max'] ?? ''); ?>" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Salary Period *</label>
                            <select name="salary_period" class="form-select" required>
                                <option value="" disabled <?php if (empty($_POST['salary_period'])) echo 'selected'; ?>>Select period</option>
                                <?php foreach (['monthly', 'yearly', 'hourly'] as $p): ?>
                                    <option value="<?php echo $p; ?>" <?php if (($_POST['salary_period'] ?? '') === $p) echo 'selected'; ?>><?php echo ucfirst($p); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="section-title">Requirements</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Experience (years) *</label>
                            <input name="required_experience" type="number" min="0" required value="<?php echo htmlspecialchars($_POST['required_experience'] ?? ''); ?>" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Education Requirement *</label>
                            <select name="required_education" class="form-select" required>
                                <option value="" disabled <?php if (($_POST['required_education'] ?? '') === '') echo 'selected'; ?>>Select education requirement</option>
                                <option value="Any" <?php if (($_POST['required_education'] ?? '') === 'Any') echo 'selected'; ?>>Any</option>
                                <?php foreach ($eduLevels as $lvl): ?>
                                    <option value="<?php echo htmlspecialchars($lvl); ?>" <?php if (($_POST['required_education'] ?? '') === $lvl) echo 'selected'; ?>><?php echo htmlspecialchars($lvl); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Job Image (optional)</label>
                            <input type="file" name="job_image" accept="image/*" class="form-control">
                            <div class="small text-muted">PNG / JPG / GIF / WEBP up to 2MB</div>
                            <div id="jobImagePreview" class="preview-box"></div>
                        </div>
                        <div class="col-12">
                            <div class="section-title">Skills</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Additional Skills (comma separated)</label>
                            <input name="additional_skills" value="<?php echo htmlspecialchars($_POST['additional_skills'] ?? ''); ?>" class="form-control">
                        </div>

                        <div class="col-12">
                            <div class="small text-muted mb-1">General / Soft Skills (at least 1 required)</div>
                            <div class="row g-2">
                                <?php foreach ($generalSkills as $gs): $checked = (!empty($_POST['required_skills']) && in_array($gs, (array)$_POST['required_skills'], true)) ? 'checked' : ''; ?>
                                    <div class="col-sm-6 col-lg-4">
                                        <label class="skill-chip d-inline-flex align-items-center gap-2">
                                            <input type="checkbox" name="required_skills[]" value="<?php echo htmlspecialchars($gs); ?>" <?php echo $checked; ?>>
                                            <span><?php echo htmlspecialchars($gs); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="invalid-msg d-none" data-for-group="skills">Please select at least one skill or add skills.</div>
                        </div>
                        <div class="col-12">
                            <div class="section-title">Applicable PWD Categories</div>
                        </div>
                        <div class="col-12">
                            <div class="small text-muted mb-1">Applicable PWD Categories (at least 1 required)</div>
                            <div class="d-flex flex-wrap gap-2" data-required-group="pwdcats">
                                <?php foreach ($pwdCats as $pcat):
                                    $isPosted = isset($_POST['applicable_pwd_types']);
                                    $checked = $isPosted ? in_array($pcat, (array)$_POST['applicable_pwd_types'], true) : false; ?>
                                    <label class="acc-tag <?php echo $checked ? 'selected' : ''; ?>">
                                        <input type="checkbox" name="applicable_pwd_types[]" value="<?php echo htmlspecialchars($pcat); ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                        <span><i class="bi bi-person-check me-1"></i><?php echo htmlspecialchars($pcat); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-hint small">Select at least one PWD category this job is intended for.</div>
                            <div class="invalid-msg d-none" data-for-group="pwdcats">Please select at least one Applicable PWD Category.</div>
                        </div>
                        <div class="col-12">
                            <div class="section-title">Accessibility & Inclusion</div>
                        </div>
                        <div class="col-12">
                            <div class="small text-muted mb-1">Accessibility & Inclusion (at least 1 required)</div>
                            <div class="d-flex flex-wrap gap-2" data-required-group="accesstags">
                                <?php foreach ($accessTags as $tag):
                                    $isPosted = isset($_POST['accessibility_tags']);
                                    $checked = $isPosted ? in_array($tag, (array)$_POST['accessibility_tags'], true) : false; ?>
                                    <label class="acc-tag <?php echo $checked ? 'selected' : ''; ?>">
                                        <input type="checkbox" name="accessibility_tags[]" value="<?php echo htmlspecialchars($tag); ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                        <span><i class="bi bi-universal-access me-1"></i><?php echo htmlspecialchars($tag); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="invalid-msg d-none" data-for-group="accesstags">Please select at least one Accessibility & Inclusion tag.</div>
                        </div>
                        <div class="col-12">
                            <div class="section-title">Role Description</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Role Description *</label>
                            <textarea name="description" required rows="8" class="form-control" maxlength="3000"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            <div class="form-hint small"><span id="descCount">0</span>/3000</div>
                        </div>
                    </div>

                    <div class="mt-4 d-flex flex-wrap gap-2 align-items-center">
                        <button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i><?php echo $duplicatePending ? 'Confirm & Create' : 'Create Job'; ?></button>
                        <a href="admin_jobs_moderation.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Cancel</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
    (function() {
        // Required-group validation helper (skills, pwdcats, accesstags)
        const form = document.getElementById('adminJobCreateForm');
        if (!form) return;
        // Toggle visual state for skill chips
        form.querySelectorAll('.skill-chip input[type=checkbox]').forEach(function(cb) {
            function sync() {
                const lab = cb.closest('.skill-chip');
                if (!lab) return;
                lab.classList.toggle('is-checked', cb.checked);
            }
            cb.addEventListener('change', sync);
            sync();
        });
        // Toggle visual state for accessibility/pwd tags
        form.querySelectorAll('.acc-tag input[type=checkbox]').forEach(function(cb) {
            function sync() {
                const lab = cb.closest('.acc-tag');
                if (!lab) return;
                lab.classList.toggle('selected', cb.checked);
            }
            cb.addEventListener('change', sync);
            sync();
        });

        function groupSatisfied(attr) {
            const wrap = form.querySelector('[data-required-group="' + attr + '"]');
            if (!wrap) return true;
            const checked = wrap.querySelector('input[type=checkbox]:checked');
            return !!checked;
        }
        form.addEventListener('submit', function(e) {
            let ok = true;
            ['skills', 'pwdcats', 'accesstags'].forEach(function(attr) {
                const show = !groupSatisfied(attr);
                const msg = form.querySelector('.invalid-msg[data-for-group="' + attr + '"]');
                if (msg) msg.classList.toggle('d-none', !show);
                if (show) ok = false;
            });
            if (!ok) e.preventDefault();
        });

        // Title and description character counters
        const titleInput = form.querySelector('input[name="title"]');
        const titleCount = document.getElementById('titleCount');
        const descInput = form.querySelector('textarea[name="description"]');
        const descCount = document.getElementById('descCount');

        function bindCounter(input, outEl, max) {
            if (!input || !outEl) return;
            const update = () => {
                outEl.textContent = String((input.value || '').length);
            };
            input.addEventListener('input', update);
            update();
        }
        bindCounter(titleInput, titleCount, 100);
        bindCounter(descInput, descCount, 3000);

        // Image preview
        const fileInput = form.querySelector('input[name="job_image"]');
        const previewBox = document.getElementById('jobImagePreview');
        if (fileInput && previewBox) {
            fileInput.addEventListener('change', function() {
                const f = fileInput.files && fileInput.files[0];
                if (!f) {
                    previewBox.style.display = 'none';
                    previewBox.innerHTML = '';
                    return;
                }
                if (!/^image\//.test(f.type)) {
                    previewBox.style.display = 'none';
                    previewBox.innerHTML = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewBox.innerHTML = '<img alt="Preview" src="' + (e.target?.result || '') + '">';
                    previewBox.style.display = 'block';
                };
                reader.readAsDataURL(f);
            });
        }
    })();
</script>