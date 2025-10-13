<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';
require_once 'classes/Job.php';

if (session_status() === PHP_SESSION_NONE) session_start();
Helpers::requireLogin();
Helpers::requireRole('employer');

$redirect = 'employer_dashboard.php#jobs';
$isAjax = (isset($_GET['ajax']) && $_GET['ajax']=='1') || (isset($_POST['ajax']) && $_POST['ajax']=='1');

$job_id = $_GET['job_id'] ?? $_POST['job_id'] ?? '';
$target = $_GET['to'] ?? $_POST['to'] ?? '';
$token  = $_GET['csrf'] ?? $_POST['csrf'] ?? '';

function jsonOut($arr){
    header('Content-Type: application/json');
    echo json_encode($arr);
    exit;
}

$allowed = ['Open','Suspended','Closed'];
if (!in_array($target,$allowed,true)) {
    if ($isAjax) jsonOut(['ok'=>false,'error'=>'Invalid status']);
    Helpers::flash('error','Invalid status.');
    Helpers::redirect($redirect);
}
if (!$job_id) {
    if ($isAjax) jsonOut(['ok'=>false,'error'=>'Missing job id']);
    Helpers::flash('error','Missing job id.');
    Helpers::redirect($redirect);
}
if (!Helpers::verifyCsrf($token)) {
    if ($isAjax) jsonOut(['ok'=>false,'error'=>'Security token invalid']);
    Helpers::flash('error','Security token invalid.');
    Helpers::redirect($redirect);
}

try {
    $pdo = Database::getConnection();
    // Ensure ownership
    $stmt = $pdo->prepare('SELECT job_id,status FROM jobs WHERE job_id=? AND employer_id=? LIMIT 1');
    $stmt->execute([$job_id, $_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        if ($isAjax) jsonOut(['ok'=>false,'error'=>'Job not found']);
        Helpers::flash('error','Job not found.');
        Helpers::redirect($redirect);
    }
    $prev = $row['status'];
    if (Job::setStatus($job_id,$target)) {
        if ($isAjax) jsonOut(['ok'=>true,'job_id'=>$job_id,'previous'=>$prev,'current'=>$target]);
        Helpers::flash('success','Status updated to '.$target.'.');
    } else {
        if ($isAjax) jsonOut(['ok'=>false,'error'=>'Failed to update status']);
        Helpers::flash('error','Failed to update status.');
    }
} catch (Throwable $e) {
    if ($isAjax) jsonOut(['ok'=>false,'error'=>'Unexpected error']);
    Helpers::flash('error','Unexpected error.');
}
Helpers::redirect($redirect);
