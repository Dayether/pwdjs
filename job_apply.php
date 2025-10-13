<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';
require_once 'classes/Job.php';
require_once 'classes/Skill.php';
require_once 'classes/User.php';
require_once 'classes/Application.php';
require_once 'classes/Taxonomy.php';
require_once 'classes/Matching.php';

Helpers::requireLogin();
Helpers::requireRole('job_seeker');

$job_id = $_GET['job_id'] ?? '';
$job = Job::findById($job_id);
if (!$job) Helpers::redirect('index.php');

$me = User::findById($_SESSION['user_id']);
$jobSkills = Skill::getSkillsForJob($job_id); // skill_id, name

$eduLevels = Taxonomy::educationLevels();

$errors = [];
$success = false;

// Hard lock pre-check using profile before showing form
$elig = Matching::canApply($me, $job);
if (!$elig['ok'] && Matching::hardLock()) {
  $errors = array_merge($errors, $elig['reasons']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $relevantYears = (int)($_POST['relevant_experience'] ?? 0);
    if ($relevantYears < 0) $relevantYears = 0;

  $applicationEducation = trim($_POST['application_education'] ?? ($me->education_level ?: $me->education ?: ''));
    $selectedSkillIds = $_POST['application_skills'] ?? [];
    if (!is_array($selectedSkillIds)) $selectedSkillIds = [$selectedSkillIds];

  // Re-evaluate after form submission in case candidate indicated more info
  $elig = Matching::canApply($me, $job);
  if (!$elig['ok'] && Matching::hardLock()) {
    $errors = array_merge($errors, $elig['reasons']);
  }

  if (!$errors && Application::createWithDetails($me, $job, $relevantYears, $selectedSkillIds, $applicationEducation)) {
        Helpers::flash('msg','Application submitted.');
        $success = true;
    } else {
    if (!$success && !$errors) {
      $errors[] = 'You have already applied or submission failed.';
    }
    }
}

include 'includes/header.php';
include 'includes/nav.php';
?>
<div class="card border-0 shadow-sm">
  <div class="card-body p-4">
    <h2 class="h5 fw-semibold mb-3"><i class="bi bi-send me-2"></i>Apply for: <?php echo htmlspecialchars($job->title); ?></h2>

    <?php if (isset($elig) && is_array($elig)): ?>
      <div class="mb-3 small">
        <span class="badge bg-secondary">Skill match: <?php echo round(($elig['skill_pct'] ?? 0)*100); ?>%</span>
        <?php if (!empty($elig['score'])): ?>
          <span class="badge bg-info text-dark ms-2">Overall score: <?php echo (int)round($elig['score']); ?>/100</span>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>

    <?php if ($success): ?>
      <div class="alert alert-success">Application submitted successfully.
        <a href="applications.php" class="alert-link">View my applications</a>.
      </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="post" class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Relevant Experience (years)</label>
        <input type="number" min="0" name="relevant_experience" class="form-control" value="<?php echo htmlspecialchars($_POST['relevant_experience'] ?? '0'); ?>">
      </div>

      <div class="col-md-8">
        <label class="form-label">Education Level</label>
        <select name="application_education" class="form-select">
          <option value="">Unspecified</option>
          <?php
            $currentEdu = $_POST['application_education'] ?? ($me->education ?? '');
            foreach ($eduLevels as $lvl):
          ?>
            <option value="<?php echo htmlspecialchars($lvl); ?>" <?php if ($currentEdu === $lvl) echo 'selected'; ?>>
              <?php echo htmlspecialchars($lvl); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12">
        <label class="form-label">Select skills you possess (General + Required)</label>
        <?php if ($jobSkills): ?>
          <div class="row">
            <?php foreach ($jobSkills as $s): ?>
              <div class="col-md-4">
                <div class="form-check">
                  <input class="form-check-input"
                         type="checkbox"
                         name="application_skills[]"
                         value="<?php echo htmlspecialchars($s['skill_id']); ?>"
                         id="appskill_<?php echo htmlspecialchars($s['skill_id']); ?>"
                         <?php if (!empty($_POST['application_skills']) && in_array($s['skill_id'], (array)$_POST['application_skills'])) echo 'checked'; ?>>
                  <label class="form-check-label small" for="appskill_<?php echo htmlspecialchars($s['skill_id']); ?>">
                    <?php echo htmlspecialchars($s['name']); ?>
                  </label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <small class="text-muted">Partial skill selection reduces the skill match portion of the score.</small>
        <?php else: ?>
          <p class="text-muted small mb-0">No specific skills listed by the employer.</p>
        <?php endif; ?>
      </div>

      <div class="col-12 d-grid">
        <button class="btn btn-primary btn-lg"><i class="bi bi-check2-circle me-1"></i>Submit Application</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php include 'includes/footer.php'; ?>