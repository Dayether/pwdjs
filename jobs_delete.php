<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';
require_once 'classes/Job.php';

Helpers::requireLogin();
Helpers::requireRole('employer');

$job_id = $_GET['job_id'] ?? '';
if ($job_id === '') {
    Helpers::flash('error','Missing job id.');
    Helpers::redirect('employer_dashboard.php#jobs');
}

$job = Job::findById($job_id);
if (!$job || $job->employer_id !== ($_SESSION['user_id'] ?? '')) {
    Helpers::flash('error','Not authorized or job not found.');
    Helpers::redirect('employer_dashboard.php#jobs');
}

if (Job::delete($job_id, $_SESSION['user_id'])) {
    Helpers::flash('msg','Job deleted.');
} else {
    Helpers::flash('error','Delete failed.');
}
Helpers::redirect('employer_dashboard.php#jobs');