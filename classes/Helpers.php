<?php
class Helpers
{
    /* =========================
       BASIC SANITIZE / ROLE
       ========================= */
    public static function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
    public static function sanitizeOutput(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
    public static function isEmployer(): bool { return (($_SESSION['role'] ?? '') === 'employer'); }
    public static function isAdmin(): bool { return (($_SESSION['role'] ?? '') === 'admin'); }

    /* =========================
       URL + REDIRECT (simple)
       ========================= */
    public static function url(string $path=''): string {
        if ($path === '') return 'index.php';
        if (preg_match('#^https?://#i',$path)) return $path;
        return ltrim($path,'/');
    }
    public static function redirect(string $path): void {
        if (!preg_match('#^https?://#i',$path)) {
            $path = self::url($path);
        }
        header('Location: '.$path);
        exit;
    }

    /* =========================
       AUTH
       ========================= */
    public static function requireLogin(): void {
        if (empty($_SESSION['user_id'])) {
            self::flash('auth','You must be logged in to access that page.');
            self::redirect('login.php');
        }
    }

    /* =========================
       FLASH
       ========================= */
    public static function flash(string $key,string $message): void {
        $_SESSION['flash'][$key]=$message;
    }
    public static function getFlashes(): array {
        if (empty($_SESSION['flash'])) return [];
        $out=[];
        foreach($_SESSION['flash'] as $k=>$v){
            $out[]=[
                'type'=>self::mapFlashType($k),
                'message'=>$v
            ];
        }
        unset($_SESSION['flash']);
        return $out;
    }
    protected static function mapFlashType(string $k): string {
        return match($k){
            'error','danger'=>'danger',
            'success'=>'success',
            'auth'=>'warning',
            default=>'info'
        };
    }

    /* =========================
       SUPPORT LINK
       ========================= */
    public static function supportLink(?string $subject=null, ?string $currentUri=null): string {
        if ($currentUri===null) $currentUri = $_SERVER['REQUEST_URI'] ?? 'index.php';
        $return = urlencode($currentUri);
        $u='support_contact.php?return='.$return;
        if ($subject) $u.='&subject='.urlencode($subject);
        return self::url($u);
    }

    /* =========================
       SKILL PARSER
       ========================= */
    public static function parseSkillInput(string $raw): array {
        $parts = preg_split('/[,;\n]+/',$raw);
        $skills=[];
        foreach($parts as $p){
            $t=trim($p);
            if($t!=='' && !in_array($t,$skills,true)){
                $skills[]=$t;
            }
        }
        return $skills;
    }

    /* =========================
       BACK HISTORY (session)
       storeLastPage(): i-store ang kasalukuyang page (relative) maliban kung NASA mismong page na ayaw mo i-store (exclude list)
       getLastPage(): kunin ang last_page or fallback
       ========================= */
    protected static function backExclude(): array {
        return [
            'support_contact.php'
        ];
    }
    public static function storeLastPage(): void {
        if (empty($_SERVER['REQUEST_URI'])) return;
        $path = $_SERVER['REQUEST_URI'];
        $parsed = parse_url($path);
        $p = $parsed['path'] ?? '';
        if ($p==='') return;
        $base = basename($p);
        if (in_array($base, self::backExclude(), true)) return;

        $rel = ltrim($p,'/');
        // Optional strip for deployments with /pwdjs/public/ prefix
        $marker='public/';
        $pos=strpos($rel,$marker);
        if($pos!==false){
            $rel=substr($rel,$pos+strlen($marker));
        }
        if (!empty($parsed['query'])) $rel.='?'.$parsed['query'];

        if (!empty($_SESSION['last_page']) && $_SESSION['last_page']===$rel) return;
        $_SESSION['last_page']=$rel;
    }
    public static function getLastPage(string $fallback='index.php'): string {
        $lp=$_SESSION['last_page'] ?? '';
        if ($lp==='') return $fallback;
        $current=basename($_SERVER['PHP_SELF'] ?? '');
        $lpBase=basename(parse_url($lp,PHP_URL_PATH)??'');
        if ($lpBase===$current) return $fallback;
        return $lp;
    }
}