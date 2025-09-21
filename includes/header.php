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
<link href="<?php echo BASE_URL; ?>/assets/styles.css" rel="stylesheet">
<style>
  /* Sticky footer layout helpers */
  .page-wrapper {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }
  main.flex-grow-1 {
    flex: 1 0 auto;
    padding-bottom: 1.25rem; /* safe space above footer */
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
</head>
<body class="bg-body-tertiary">
<div class="page-wrapper">
  <!-- All page content (nav + page body) lives inside <main>. Footer closes outside. -->
  <main class="flex-grow-1">