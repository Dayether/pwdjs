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
       ===============  ADDED METHODS (NO REMOVALS)  ============
       Below are all new helpers added WITHOUT deleting any
       existing original code above.
       ========================================================= */

    // ADDED: Simple alias for escaping (sometimes code uses e())
    public static function e(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    // ADDED: Admin role checker
    public static function isAdmin(): bool {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    // ADDED: Uniform relative URL builder
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

        // Optional stripping of deployment prefix (adjust if needed)
        $marker = 'public/';
        $pos = strpos($relative, $marker);
        if ($pos !== false) {
            $relative = substr($relative, $pos + strlen($marker));
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
        if ($lpBase === $current) return $fallback;
        return $lp;
    }

    // ADDED: Optional flash -> bootstrap type mapper (not used by original, but safe)
    public static function mapFlashType(string $key): string {
        return match($key) {
            'error','danger' => 'danger',
            'success'        => 'success',
            'auth','warning' => 'warning',
            default          => 'info'
        };
    }

    // ADDED: Helper to get structured flashes if you later want icon/types
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

    public static function getFlashesAssoc(): array {
    if (empty($_SESSION['flash'])) return [];
    $out = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $out;
}
}