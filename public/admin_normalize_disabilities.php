<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/Taxonomy.php';

// Auth: admin only
if (!Helpers::isLoggedIn() || ($_SESSION['role'] ?? '') !== 'admin') {
  header('HTTP/1.1 403 Forbidden');
  echo 'Forbidden';
  exit;
}

$pdo = Database::getConnection();
$action = $_GET['action'] ?? 'dry-run'; // dry-run | apply | last
// Enforce POST + CSRF for apply
if ($action === 'apply') {
  $methodOk = ($_SERVER['REQUEST_METHOD'] === 'POST');
  $csrfOk = isset($_POST['csrf'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf']);
  if (!$methodOk || !$csrfOk) {
    // degrade to dry-run without error to be safe
    $action = 'dry-run';
  }
}

function canonLabel(?string $s): ?string {
  $c = Taxonomy::canonicalizeDisability($s ?? '');
  if ($c === null) return null; // unknown
  if ($c === '') return '';     // intentionally blank
  return $c;
}

function canonCsv(?string $csv): string {
  $csv = trim((string)$csv);
  if ($csv === '') return '';
  $parts = array_filter(array_map('trim', explode(',', $csv)));
  $seen = [];
  $out = [];
  foreach ($parts as $p) {
    $c = canonLabel($p);
    if ($c === null || $c === '') continue;
    $k = mb_strtolower($c);
    if (!isset($seen[$k])) { $seen[$k] = true; $out[] = $c; }
  }
  return implode(',', $out);
}

$report = [
  'users' => ['scanned'=>0,'updated'=>0,'examples'=>[]],
  'jobs'  => ['scanned'=>0,'updated'=>0,'examples'=>[]],
];

// If requesting last run summary
if ($action === 'last') {
  try {
    $st = $pdo->query("SELECT task, actor_user_id, mode, users_scanned, users_updated, jobs_scanned, jobs_updated, details, created_at FROM admin_tasks_log WHERE task='normalize_disabilities' ORDER BY created_at DESC LIMIT 1");
    $last = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  } catch (Throwable $e) { $last = null; }
  header('Content-Type: application/json');
  echo json_encode(['mode'=>'last','last'=>$last], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

// Users: normalize disability + disability_type
$uStmt = $pdo->query("SELECT user_id, role, disability, disability_type FROM users");
$users = $uStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($users as $u) {
  $report['users']['scanned']++;
  $orig1 = $u['disability'];
  $orig2 = $u['disability_type'];
  $new1 = canonLabel($orig1);
  $new2 = canonLabel($orig2);
  // Prefer the more specific disability_type if available; otherwise use general
  $final1 = $new1; // general field
  $final2 = $new2; // specific field

  $changed = false;
  $vals = [];
  if ($final1 !== null) {
    // store blank as NULL for consistency
    $store = ($final1 === '') ? null : $final1;
    if ($store !== ($orig1 === '' ? null : $orig1)) { $changed = true; $vals['disability'] = $store; }
  }
  if ($final2 !== null) {
    $store2 = ($final2 === '') ? null : $final2;
    if ($store2 !== ($orig2 === '' ? null : $orig2)) { $changed = true; $vals['disability_type'] = $store2; }
  }
  if ($changed) {
    if ($action === 'apply') {
      $set = [];$params=[];
      foreach ($vals as $k=>$v) { $set[] = "$k = ?"; $params[] = $v; }
      $params[] = $u['user_id'];
      $pdo->prepare("UPDATE users SET ".implode(',', $set)." WHERE user_id = ?")->execute($params);
    }
    $report['users']['updated']++;
    if (count($report['users']['examples']) < 5) {
      $report['users']['examples'][] = [
        'user_id'=>$u['user_id'],
        'from'=>['disability'=>$orig1,'disability_type'=>$orig2],
        'to'=>['disability'=>$vals['disability'] ?? $orig1,'disability_type'=>$vals['disability_type'] ?? $orig2]
      ];
    }
  }
}

// Jobs: normalize applicable_pwd_types CSV
$jStmt = $pdo->query("SELECT job_id, applicable_pwd_types FROM jobs");
$jobs = $jStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($jobs as $j) {
  $report['jobs']['scanned']++;
  $orig = $j['applicable_pwd_types'];
  $norm = canonCsv($orig);
  // Store NULL if empty
  $store = ($norm === '') ? null : $norm;
  // Compare normalized vs original (treat '' and NULL as equal)
  $origCmp = ($orig === '' ? null : $orig);
  if ($store !== $origCmp) {
    if ($action === 'apply') {
      $pdo->prepare("UPDATE jobs SET applicable_pwd_types = ? WHERE job_id = ?")->execute([$store, $j['job_id']]);
    }
    $report['jobs']['updated']++;
    if (count($report['jobs']['examples']) < 5) {
      $report['jobs']['examples'][] = [
        'job_id'=>$j['job_id'],
        'from'=>$orig,
        'to'=>$store
      ];
    }
  }
}

// Write audit log on apply
if ($action === 'apply') {
  try {
    $det = json_encode(['examples'=>$report], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $st = $pdo->prepare("INSERT INTO admin_tasks_log (task, actor_user_id, mode, users_scanned, users_updated, jobs_scanned, jobs_updated, details) VALUES (?,?,?,?,?,?,?,?)");
    $st->execute([
      'normalize_disabilities',
      $_SESSION['user_id'] ?? 'unknown',
      'apply',
      $report['users']['scanned'] ?? 0,
      $report['users']['updated'] ?? 0,
      $report['jobs']['scanned'] ?? 0,
      $report['jobs']['updated'] ?? 0,
      $det
    ]);
  } catch (Throwable $e) { /* ignore logging errors */ }
}

header('Content-Type: application/json');
echo json_encode([
  'mode' => $action,
  'categories' => Taxonomy::disabilityCategories(),
  'report' => $report
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
