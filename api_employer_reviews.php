<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';

header('Content-Type: application/json; charset=utf-8');

function respond_json(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(['ok' => false, 'message' => 'Method not allowed'], 405);
}

$action = trim((string)($_POST['action'] ?? ''));
if ($action !== 'submit_review') {
    respond_json(['ok' => false, 'message' => 'Invalid action'], 400);
}

// Auth: job seeker only
if (empty($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'job_seeker')) {
    respond_json(['ok' => false, 'message' => 'Please login as job seeker to write a review.'], 401);
}

// CSRF
if (!Helpers::verifyCsrf($_POST['csrf'] ?? '')) {
    respond_json(['ok' => false, 'message' => 'Invalid session. Please refresh and try again.'], 400);
}

$employerId = trim((string)($_POST['employer_id'] ?? ''));
$rating = (int)($_POST['rating'] ?? 0);
$comment = trim((string)($_POST['comment'] ?? ''));

if ($employerId === '' || $rating < 1 || $rating > 5) {
    respond_json(['ok' => false, 'message' => 'Missing or invalid inputs.'], 400);
}

// Limit comment length to 2000 chars
if (mb_strlen($comment) > 2000) {
    $comment = mb_substr($comment, 0, 2000);
}

try {
    $pdo = Database::getConnection();
    // Validate employer exists and is approved
    $st = $pdo->prepare("SELECT user_id FROM users WHERE user_id=? AND role='employer' AND employer_status='Approved' LIMIT 1");
    $st->execute([$employerId]);
    if (!$st->fetchColumn()) {
        respond_json(['ok' => false, 'message' => 'Employer not found or not approved.'], 404);
    }

    // Prevent duplicate review by same user to same employer (any status)
    $du = $pdo->prepare("SELECT id FROM employer_reviews WHERE employer_id=? AND reviewer_user_id=? LIMIT 1");
    $du->execute([$employerId, $_SESSION['user_id']]);
    if ($du->fetch()) {
        respond_json(['ok' => false, 'message' => 'You have already submitted a review for this employer.'], 400);
    }

    // Insert pending review
    $ins = $pdo->prepare("INSERT INTO employer_reviews (employer_id, reviewer_user_id, rating, comment, status) VALUES (?, ?, ?, ?, 'Pending')");
    $ok = $ins->execute([$employerId, $_SESSION['user_id'], $rating, ($comment !== '' ? $comment : null)]);
    if (!$ok) {
        respond_json(['ok' => false, 'message' => 'Failed to submit review.'], 500);
    }
    respond_json(['ok' => true, 'message' => 'Review submitted for moderation.']);
} catch (Throwable $e) {
    respond_json(['ok' => false, 'message' => 'Server error'], 500);
}
?>
