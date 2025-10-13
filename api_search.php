<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Search.php';
require_once 'classes/Helpers.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
if ($action === 'suggest') {
    $q = trim($_GET['q'] ?? '');
    $suggestions = Search::suggest($q, 8);
    echo json_encode(['suggestions' => $suggestions]);
    exit;
}

if ($action === 'history') {
    if (!Helpers::isLoggedIn() || !Helpers::isJobSeeker()) {
        echo json_encode(['history' => []]);
        exit;
    }
    $userId = $_SESSION['user_id'];
    $history = Search::getHistory($userId, 8);
    echo json_encode(['history' => $history]);
    exit;
}

if ($action === 'clear_history') {
    if (!Helpers::isLoggedIn() || !Helpers::isJobSeeker()) {
        echo json_encode(['ok' => false]);
        exit;
    }
    try {
        $pdo = Database::getConnection();
        $st = $pdo->prepare("DELETE FROM search_history WHERE user_id=?");
        $ok = $st->execute([$_SESSION['user_id']]);
        echo json_encode(['ok' => (bool)$ok]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
?>
