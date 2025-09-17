<?php
class Helpers {
    public static function generateSmartId(string $prefix): string {
        $time = strtoupper(base_convert((string)floor(microtime(true)*1000), 10, 32));
        $rand = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
        return $prefix . '_' . $time . $rand;
    }

    public static function sanitizeOutput(?string $v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }

    public static function requireLogin(): void {
        if (empty($_SESSION['user_id'])) {
            header('Location: login.php');
            exit;
        }
    }

    public static function isEmployer(): bool {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'employer';
    }

    public static function isJobSeeker(): bool {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'job_seeker';
    }

    public static function redirect(string $url): void {
        header("Location: $url");
        exit;
    }

    public static function flash(string $key, string $message): void {
        $_SESSION['flash'][$key] = $message;
    }

    public static function getFlashes(): array {
        if (empty($_SESSION['flash'])) return [];
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }

    public static function parseSkillInput(string $input): array {
        $parts = array_filter(array_map('trim', explode(',', $input)));
        $unique = [];
        foreach ($parts as $p) {
            if ($p !== '' && !in_array(strtolower($p), array_map('strtolower',$unique))) {
                $unique[] = $p;
            }
        }
        return $unique;
    }
}