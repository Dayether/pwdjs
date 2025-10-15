<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';

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

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'job_seeker') {
    respond(['ok' => false, 'message' => 'Please login as job seeker.'], 401);
}

if (!Helpers::verifyCsrf($_POST['csrf'] ?? '')) {
    respond(['ok' => false, 'message' => 'Invalid session. Please refresh and try again.'], 400);
}

$job_id = trim((string)($_POST['job_id'] ?? ''));
if ($job_id === '') {
    respond(['ok' => false, 'message' => 'Missing job_id'], 400);
}

try {
    $pdo = Database::getConnection();
    // Only allow cancel if status is still Pending
    $stmt = $pdo->prepare("SELECT application_id, status FROM applications WHERE user_id=? AND job_id=? LIMIT 1");
    $stmt->execute([$_SESSION['user_id'], $job_id]);
    $row = $stmt->fetch();
    if (!$row) respond(['ok' => false, 'message' => 'No application found.'], 404);
    if (strcasecmp($row['status'], 'Pending') !== 0) {
        respond(['ok' => false, 'message' => 'Application can no longer be cancelled.'], 400);
    }
    $del = $pdo->prepare("DELETE FROM applications WHERE application_id = ? LIMIT 1");
    $ok = $del->execute([$row['application_id']]);
    if ($ok) respond(['ok' => true, 'message' => 'Application cancelled.']);
    respond(['ok' => false, 'message' => 'Failed to cancel application.']);
} catch (Throwable $e) {
    respond(['ok' => false, 'message' => 'Server error.'], 500);
}
