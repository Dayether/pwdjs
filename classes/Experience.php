<?php
require_once __DIR__ . '/Database.php';

class Experience {

    public static function listByUser(string $userId): array {
        $pdo = Database::getConnection();
        $q = $pdo->prepare("SELECT * FROM user_experiences WHERE user_id=? ORDER BY start_date DESC, created_at DESC");
        $q->execute([$userId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(string $userId, array $data): bool {
        if (empty($data['company']) || empty($data['position']) || empty($data['start_date'])) return false;
        $pdo = Database::getConnection();
        $isCurrent = !empty($data['is_current']) ? 1 : 0;
        $sql = "INSERT INTO user_experiences (user_id, company, position, start_date, end_date, is_current, description)
                VALUES (?,?,?,?,?,?,?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $userId,
            trim($data['company']),
            trim($data['position']),
            $data['start_date'],
            $isCurrent ? null : ($data['end_date'] ?: null),
            $isCurrent,
            $data['description'] ?: null
        ]);
    }

    public static function delete(string $userId, int $id): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM user_experiences WHERE id=? AND user_id=?");
        return $stmt->execute([$id, $userId]);
    }
}