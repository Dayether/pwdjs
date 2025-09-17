<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/User.php';
require_once '../classes/Job.php';
require_once '../classes/Application.php';
require_once '../classes/Skill.php';
require_once '../classes/Taxonomy.php';

Helpers::requireLogin();

// Hard gate: only job seekers can access this page
if (!Helpers::isJobSeeker()) {
    Helpers::flash('msg', 'Only job seekers can apply for jobs.');
    Helpers::redirect('index.php');
}

$job_id = $_GET['job_id'] ?? ($_POST['job_id'] ?? '');
$job = Job::findById($job_id);
if (!$job) Helpers::redirect('index.php');

$user = User::findById($_SESSION['user_id']);
$jobSkills = Skill::getSkillsForJob($job->job_id);
$eduLevels = Taxonomy::educationLevels();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $relevantYears = max(0, (int)($_POST['relevant_experience'] ?? 0));
    $selectedSkillIds = $_POST['skills'] ?? [];
    if (!is_array($selectedSkillIds)) $selectedSkillIds = [$selectedSkillIds];
    $applicationEducation = $_POST['application_education'] ?? '';

    if (Application::createWithDetails($user, $job, $relevantYears, $selectedSkillIds, $applicationEducation)) {
        Helpers::flash('msg','Application submitted.');
        Helpers::redirect('job_view.php?job_id=' . urlencode($job_id));
    } else {
        $errors[] = 'You may have already applied for this job or an error occurred.';
    }
}

include '../includes/header.php';
include '../includes/nav.php';

$postedYears = $_POST['relevant_experience'] ?? '';
$postedSkills = isset($_POST['skills']) ? (array)$_POST['skills'] : [];
$postedEdu = $_POST['application_education'] ?? '';
?>
<div class="row justify-content-center">
  <div class="col-lg-10 col-xl-8">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h2 class="h5 fw-semibold mb-3"><i class="bi bi-send me-2"></i>Apply to: <?php echo Helpers::sanitizeOutput($job->title); ?></h2>
        <?php foreach ($errors as $e): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($e); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endforeach; ?>

        <form method="post" class="row g-3">
          <input type="hidden" name="job_id" value="<?php echo htmlspecialchars($job->job_id); ?>">

          <div class="col-md-4">
            <label class="form-label">Relevant Experience (years)</label>
            <input name="relevant_experience" type="number" min="0" class="form-control" value="<?php echo htmlspecialchars($postedYears); ?>" placeholder="e.g., 2">
            <small class="text-muted">Experience specific to this role.</small>
          </div>

          <div class="col-md-4">
            <label class="form-label">Your Education for this application</label>
            <select name="application_education" class="form-select">
              <option value="">Not specified</option>
              <?php foreach ($eduLevels as $lvl): ?>
                <option value="<?php echo htmlspecialchars($lvl); ?>" <?php if ($postedEdu === $lvl) echo 'selected'; ?>>
                  <?php echo htmlspecialchars($lvl); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Education is considered but experience and skills weigh more.</small>
          </div>

          <div class="col-12">
            <label class="form-label">Select the skills you have for this job</label>
            <?php if ($jobSkills): ?>
              <div class="row row-cols-1 row-cols-md-2 g-2">
                <?php foreach ($jobSkills as $s): ?>
                  <div class="col">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="skills[]" id="skill_<?php echo htmlspecialchars($s['skill_id']); ?>" value="<?php echo htmlspecialchars($s['skill_id']); ?>" <?php if (in_array($s['skill_id'], $postedSkills)) echo 'checked'; ?>>
                      <label class="form-check-label" for="skill_<?php echo htmlspecialchars($s['skill_id']); ?>">
                        <?php echo Helpers::sanitizeOutput($s['name']); ?>
                      </label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <small class="text-muted d-block mt-1">These options come from the employer’s requirements.</small>
            <?php else: ?>
              <div class="alert alert-secondary">This job has no specific skill requirements. You can submit based on your experience and education.</div>
            <?php endif; ?>
          </div>

          <div class="col-12 d-grid">
            <button class="btn btn-success btn-lg"><i class="bi bi-check2-circle me-1"></i>Submit Application</button>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>