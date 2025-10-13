<?php
require_once __DIR__ . '/../config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PWD Employment & Skills Portal</title>
<meta name="description" content="Connect PWD job seekers with inclusive employers. Post jobs, search, apply, and manage applications.">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php $BASE = rtrim(BASE_URL, '/'); ?>
<base href="<?php echo htmlspecialchars($BASE); ?>/">
<link href="<?php echo $BASE; ?>/assets/styles.css?v=20250926b" rel="stylesheet">
<?php
  // Dynamic favicon detection (prefers root favicon files; falls back to existing logo)
  $__favCandidates = [
    $BASE.'/favicon.ico' => __DIR__.'/../favicon.ico',
    $BASE.'/favicon.png' => __DIR__.'/../favicon.png',
    $BASE.'/assets/favicon.png' => __DIR__.'/../assets/favicon.png',
    $BASE.'/assets/images/hero/logo.png' => __DIR__.'/../assets/images/hero/logo.png', // fallback to logo
  ];
  $__favHref = null;
  foreach ($__favCandidates as $href=>$disk) {
    if (is_file($disk)) { $__favHref = $href; break; }
  }
  if ($__favHref) {
    $ext = pathinfo(parse_url($__favHref, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
    $type = ($ext==='ico') ? 'image/x-icon' : (($ext==='svg') ? 'image/svg+xml' : 'image/png');
    echo '<link rel="icon" href="'.htmlspecialchars($__favHref).'" type="'.$type.'">';
  }
?>
<style>
  /* Sticky footer layout helpers */
  .page-wrapper {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }
  main.flex-grow-1 {
    flex: 1 0 auto;
    padding-bottom: 1.25rem; /* default safe space above footer */
  }
  body {
    font-family: 'Inter', system-ui, -apple-system, "Segoe UI", Roboto, Ubuntu, sans-serif;
  }
  /* Optional consistent small shadow already used */
  .shadow-sm { box-shadow:0 .125rem .25rem rgba(0,0,0,.075)!important; }

  /* Prevent flicker if very short content */
  main.flex-grow-1:empty {
    min-height: 40vh;
  }
</style>
<?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
<style>
  /* Remove bottom gap for admin pages (no footer shown) */
  main.flex-grow-1 { padding-bottom: 0 !important; }
  .admin-main { padding-bottom: 1.25rem !important; }
  /* Ensure full height stretch */
  .admin-layout { min-height: 100vh; }
</style>
<?php endif; ?>
</head>
<body>
<div class="page-wrapper">
  <!-- All page content (nav + page body) lives inside <main>. Footer closes outside. -->
  <main class="flex-grow-1">
  <!-- Removed duplicate custom confirmation script; using single Bootstrap-based handler in footer -->