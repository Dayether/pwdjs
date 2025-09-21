<?php
require_once __DIR__ . '/Database.php';

class Certification {

    public static function listByUser(string $userId): array {
        $pdo = Database::getConnection();
        $q = $pdo->prepare("SELECT * FROM user_certifications WHERE user_id=? ORDER BY issued_date DESC, created_at DESC");
        $q->execute([$userId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(string $userId, array $data): bool {
        if (empty($data['name'])) return false;
        $pdo = Database::getConnection();
        $sql = "INSERT INTO user_certifications (user_id, name, issuer, issued_date, expiry_date, credential_id, attachment_path)
                VALUES (?,?,?,?,?,?,?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $userId,
            trim($data['name']),
            $data['issuer'] ?: null,
            $data['issued_date'] ?: null,
            $data['expiry_date'] ?: null,
            $data['credential_id'] ?: null,
            $data['attachment_path'] ?: null
        ]);
    }

    public static function delete(string $userId, int $id): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM user_certifications WHERE id=? AND user_id=?");
        return $stmt->execute([$id, $userId]);
    }
}