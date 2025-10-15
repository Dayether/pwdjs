<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';
require_once 'classes/Report.php';
require_once 'classes/Job.php';

Helpers::requireLogin();
Helpers::requireRole('job_seeker');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helpers::redirect('index.php');
}

$job_id = trim($_POST['job_id'] ?? '');
$reason = trim($_POST['reason'] ?? '');
$details = trim($_POST['details'] ?? '');

$job = Job::findById($job_id);
if (!$job) {
    Helpers::flash('error', 'Job not found.');
    Helpers::redirect('index.php');
}

$errors = [];
if ($reason === '') $errors[] = 'Reason is required.';
if (strlen($reason) > 120) $errors[] = 'Reason too long.';
if (strlen($details) > 2000) $errors[] = 'Details too long (max 2000).';

if ($errors) {
    Helpers::flash('error', implode(' ', $errors));
    Helpers::redirect('job_view.php?job_id=' . urlencode($job_id));
}

if (Report::create($job_id, $_SESSION['user_id'], $reason, $details)) {
    Helpers::flash('msg', 'Thank you. Your report has been submitted.');
} else {
    Helpers::flash('error', 'Failed to submit report.');
}
Helpers::redirect('job_view.php?job_id=' . urlencode($job_id));
