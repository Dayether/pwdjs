<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';
require_once 'classes/User.php';
require_once 'classes/Job.php';
require_once 'classes/Skill.php';
require_once 'classes/Application.php';
require_once 'classes/Matching.php';

header('Content-Type: application/json; charset=utf-8');

function respond($arr, $code = 200)
{
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'message' => 'Method not allowed'], 405);
}

$action = $_POST['action'] ?? '';
if ($action !== 'quick_apply') {
    respond(['ok' => false, 'message' => 'Invalid action'], 400);
}

$job_id = trim((string)($_POST['job_id'] ?? ''));
if ($job_id === '') {
    respond(['ok' => false, 'message' => 'Missing job_id'], 400);
}

// Auth check
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'job_seeker') {
    $redir = 'login.php?redirect=' . urlencode('job_view.php?job_id=' . $job_id);
    respond(['ok' => false, 'message' => 'Please login to apply.', 'redirect' => $redir], 401);
}

// CSRF
if (!Helpers::verifyCsrf($_POST['csrf'] ?? '')) {
    respond(['ok' => false, 'message' => 'Invalid session. Please refresh and try again.'], 400);
}

$job = Job::findById($job_id);
if (!$job) {
    respond(['ok' => false, 'message' => 'Job not found'], 404);
}

$actor = User::findById($_SESSION['user_id']);
if (!$actor) {
    respond(['ok' => false, 'message' => 'User session invalid. Please re-login.'], 401);
}

// Duplicate
if (Application::userHasApplied($actor->user_id, $job->job_id)) {
    respond(['ok' => false, 'message' => 'You have already applied to this job.']);
}

// Eligibility
$elig = Matching::canApply($actor, $job);
if (!$elig['ok'] && Matching::hardLock()) {
    respond([
        'ok' => false,
        'message' => 'You can\'t apply yet. Please review the requirements.',
        'reasons' => $elig['reasons'] ?? []
    ]);
}

// Build defaults
$years = (int)($actor->experience ?? 0);
$userSkillIds = Matching::userSkillIds($actor->user_id);
$jobSkillIds  = Matching::jobSkillIds($job->job_id);
$selectedForApp = array_values(array_intersect(array_map('strval', $userSkillIds), array_map('strval', $jobSkillIds)));
$appEdu = $actor->education_level ?: ($actor->education ?: '');

$ok = Application::createWithDetails($actor, $job, $years, $selectedForApp, $appEdu);
if ($ok) {
    respond(['ok' => true, 'message' => 'Application submitted.']);
} else {
    respond(['ok' => false, 'message' => 'Submission failed or already applied.']);
}
