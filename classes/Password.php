<?php
require_once __DIR__ . '/Database.php';

class PasswordHelper {
    private static function tokenFor(string $plain): string {
        $secret = getenv('APP_ENC_KEY') ?: (defined('APP_ENC_KEY') ? (string)constant('APP_ENC_KEY') : '');
        if (strlen($secret) < 32) {
            $secret = str_pad($secret, 32, '0');
        } else {
            $secret = substr($secret, 0, 32);
        }
        return hash_hmac('sha256', $plain, $secret);
    }

    public static function generateUniquePassword(int $length = 8, int $maxTries = 50): string {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $n = strlen($alphabet);
        $pdo = Database::getConnection();
        for ($i=0; $i<$maxTries; $i++) {
            $plain = '';
            for ($j=0; $j<$length; $j++) {
                $plain .= $alphabet[random_int(0, $n-1)];
            }
            $token = self::tokenFor($plain);
            $stmt = $pdo->prepare("SELECT 1 FROM used_password_tokens WHERE token=? LIMIT 1");
            $stmt->execute([$token]);
            if (!$stmt->fetchColumn()) {
                return $plain;
            }
        }
        throw new RuntimeException('Failed to generate unique password after multiple attempts');
    }

    public static function assignInitialPasswordIfMissing(string $userId): ?string {
        $pdo = Database::getConnection();
        $row = $pdo->prepare("SELECT password FROM users WHERE user_id=? LIMIT 1");
        $row->execute([$userId]);
        $cur = $row->fetchColumn();
        if ($cur && $cur !== '') {
            return null; // already has a password
        }
        $plain = self::generateUniquePassword(8);
        $hash = password_hash($plain, PASSWORD_DEFAULT);
        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare("UPDATE users SET password=? WHERE user_id=?");
            $upd->execute([$hash, $userId]);
            $token = self::tokenFor($plain);
            $ins = $pdo->prepare("INSERT INTO used_password_tokens (token, user_id) VALUES (?, ?)");
            $ins->execute([$token, $userId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $plain;
    }
}
