<?php
class Helpers {

    /* =========================================================
       (ORIGINAL) Smart ID Generator
       ========================================================= */
    public static function generateSmartId(string $prefix): string {
        $time = strtoupper(base_convert((string)floor(microtime(true)*1000), 10, 32));
        $rand = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
        return $prefix . '_' . $time . $rand;
    }

    /* =========================================================
       (ORIGINAL) sanitizeOutput
       ========================================================= */
    public static function sanitizeOutput(?string $v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }

    /* =========================================================
       (ORIGINAL) requireLogin
       ========================================================= */
    public static function requireLogin(): void {
        if (empty($_SESSION['user_id'])) {
            header('Location: login.php');
            exit;
        }
    }

    /* =========================================================
       (ORIGINAL) Role helpers
       ========================================================= */
    public static function isEmployer(): bool {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'employer';
    }

    public static function isJobSeeker(): bool {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'job_seeker';
    }

    public static function isAdmin(): bool {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    // Enforce specific role with friendly redirect and message
    public static function requireRole(string $role): void {
        if (empty($_SESSION['user_id'])) {
            self::flash('auth','Please log in to continue.');
            header('Location: login.php');
            exit;
        }
        $current = $_SESSION['role'] ?? '';
        if ($current !== $role) {
            self::flash('error','You do not have permission to access that page.');
            self::redirectToRoleDashboard();
        }
    }

    /* =========================================================
       (ORIGINAL) redirect
       ========================================================= */
    public static function redirect(string $url): void {
        header("Location: $url");
        exit;
    }

    /* =========================================================
       (ORIGINAL) flash + getFlashes (unchanged)
       ========================================================= */
    public static function flash(string $key, string $message): void {
        $_SESSION['flash'][$key] = $message;
    }

    public static function getFlashes(): array {
        if (empty($_SESSION['flash'])) return [];
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }

    /* =========================================================
       (ORIGINAL) parseSkillInput
       ========================================================= */
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

    /* =========================================================
       ADDED METHODS (No removals)
       ========================================================= */

    // ADDED: simple login checker
    public static function isLoggedIn(): bool {
        return !empty($_SESSION['user_id']);
    }

    // ADDED: Simple alias for escaping (if ever used)
    public static function e(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    // ADDED: Uniform relative/absolute URL builder
    public static function url(string $path=''): string {
        if ($path === '') return 'index.php';
        if (preg_match('#^https?://#i',$path)) return $path;
        return ltrim($path,'/');
    }

    // ADDED: Support link builder with return + optional subject
    public static function supportLink(?string $subject=null, ?string $currentUri=null): string {
        if ($currentUri===null) {
            $currentUri = $_SERVER['REQUEST_URI'] ?? 'index.php';
        }
        $return = urlencode($currentUri);
        $u = 'support_contact.php?return='.$return;
        if ($subject) {
            $u .= '&subject='.urlencode($subject);
        }
        return self::url($u);
    }

    // ADDED: Back button exclude list
    protected static function backExclude(): array {
        return [
            'support_contact.php'
        ];
    }

    // ADDED: Store last page (session) except excluded pages
    public static function storeLastPage(): void {
        if (empty($_SERVER['REQUEST_URI'])) return;

        $uri = $_SERVER['REQUEST_URI'];
        $parsed = parse_url($uri);
        $path = $parsed['path'] ?? '';
        if ($path === '') return;

        $base = basename($path);
        if (in_array($base, self::backExclude(), true)) return;

        $relative = ltrim($path,'/');

    // Normalize away any absolute BASE_URL or legacy prefixes like 'pwdjs/public/' or 'public/'
        $base = rtrim((string)(defined('BASE_URL') ? BASE_URL : ''), '/');
        if ($base !== '') {
            // If relative accidentally contains full base path, strip it
            $basePath = parse_url($base, PHP_URL_PATH) ?: '';
            $basePath = ltrim($basePath, '/');
            if ($basePath !== '' && stripos($relative, $basePath.'/') === 0) {
                $relative = substr($relative, strlen($basePath)+1);
            }
        }
    foreach (['pwdjsbackup/public/','pwdjs/public/','public/','pwdjsbackup/','pwdjs/'] as $legacy) {
            if (stripos($relative, $legacy) === 0) {
                $relative = substr($relative, strlen($legacy));
                break;
            }
        }

        if (!empty($parsed['query'])) {
            $relative .= '?'.$parsed['query'];
        }

        if (!empty($_SESSION['last_page']) && $_SESSION['last_page'] === $relative) {
            return;
        }
        $_SESSION['last_page'] = $relative;
    }

    // ADDED: Retrieve last page or fallback
    public static function getLastPage(string $fallback='index.php'): string {
        $lp = $_SESSION['last_page'] ?? '';
        if ($lp === '') return $fallback;
        $current = basename($_SERVER['PHP_SELF'] ?? '');
        $lpBase  = basename(parse_url($lp, PHP_URL_PATH) ?? '');
        if ($lpBase === $current) return $fallback; // Prevent loop
        return $lp;
    }

    // ADDED: flash key -> bootstrap type mapper
    public static function mapFlashType(string $key): string {
        return match($key) {
            'error','danger' => 'danger',
            'success','msg'  => 'success',
            'auth','warning' => 'warning',
            default          => 'info'
        };
    }

    // ADDED: Structured flashes
    public static function getStructuredFlashes(): array {
        if (empty($_SESSION['flash'])) return [];
        $out = [];
        foreach ($_SESSION['flash'] as $k => $msg) {
            $out[] = [
                'key'     => $k,
                'type'    => self::mapFlashType($k),
                'message' => $msg
            ];
        }
        unset($_SESSION['flash']);
        return $out;
    }

    // ADDED: Keep simple associative variant
    public static function getFlashesAssoc(): array {
        if (empty($_SESSION['flash'])) return [];
        $out = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $out;
    }

    // ADDED: Safe int fetcher
    public static function int(array $src, string $key, int $default=0): int {
        return isset($src[$key]) && is_numeric($src[$key]) ? (int)$src[$key] : $default;
    }

    // ADDED: CSRF utilities (optional future)
    public static function csrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool {
        return $token && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    // ADDED: Central role-based dashboard redirect
    public static function redirectToRoleDashboard(): void {
        $r = $_SESSION['role'] ?? '';
        if ($r === 'admin') {
            self::redirect('admin_employers.php');
        } elseif ($r === 'employer') {
            self::redirect('employer_dashboard.php');
        } else {
            self::redirect('user_dashboard.php');
        }
    }
}