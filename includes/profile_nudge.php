<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/../classes/Helpers.php';
require_once __DIR__.'/../classes/ProfileCompleteness.php';

// Only for logged-in job seekers
if (!Helpers::isJobSeeker()) return;

// Dismiss handling (session only for now)
if (isset($_GET['dismiss_profile_nudge'])) {
    $_SESSION['dismiss_profile_nudge'] = true;
    $clean = $_SERVER['REQUEST_URI'] ?? 'index.php';
    // Remove the query parameter for a clean URL
    $clean = preg_replace('/([&?])dismiss_profile_nudge=1&?/', '$1', $clean);
    $clean = rtrim($clean, '?&');
    header("Location: $clean");
    exit;
}
if (!empty($_SESSION['dismiss_profile_nudge'])) return;

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) return;

$score = ProfileCompleteness::ensure($userId, 1440);
if ($score >= 60) return; // threshold where we stop nudging

$level = $score < 30 ? 'low' : 'mid';

// Determine quick checklist suggestions (top 2 missing)
$missing = [];
try {
    $pdoN = Database::getConnection();
    $stN = $pdoN->prepare("SELECT resume, video_intro, primary_skill_summary, education, education_level, expected_salary_min, expected_salary_max FROM users WHERE user_id=? LIMIT 1");
    $stN->execute([$userId]);
    $urow = $stN->fetch(PDO::FETCH_ASSOC) ?: [];
    if (empty($urow['resume'])) $missing[] = 'Upload a resume';
    if (empty($urow['primary_skill_summary'])) $missing[] = 'Add a skill summary';
    if (empty($urow['education']) && empty($urow['education_level'])) $missing[] = 'Add education';
    if (empty($urow['video_intro'])) $missing[] = 'Add a video intro';
    if (empty($urow['expected_salary_min']) && empty($urow['expected_salary_max'])) $missing[] = 'Set expected salary';
} catch (Throwable $e) {}
$missing = array_slice($missing, 0, 2);
?>
<style>
.profile-nudge {background:linear-gradient(105deg,#ffffff,#f5f9ff);border:1px solid #dbe6ff;border-radius:.85rem;padding:1rem 1.1rem;box-shadow:0 4px 14px -6px rgba(0,38,89,.15);margin:1rem auto 1.25rem;position:relative;overflow:hidden}
.profile-nudge[data-level='low']{border-color:#ffdfc8;background:linear-gradient(105deg,#fff,#fff7f1)}
.profile-nudge[data-level='low'] strong{color:#b34700}
.profile-nudge .nudge-progress{position:relative;width:64px;height:64px;border-radius:50%;background:conic-gradient(#0d6efd var(--pct),#e2ecf8 var(--pct));display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:600;color:#0d3a66}
.profile-nudge[data-level='low'] .nudge-progress{background:conic-gradient(#ff8c37 var(--pct),#ffe7d4 var(--pct));color:#7d2e00}
.profile-nudge .nudge-progress span{position:relative;z-index:2}
.profile-nudge .nudge-progress::after{content:'';position:absolute;inset:6px;border-radius:50%;background:#fff;box-shadow:inset 0 0 0 1px rgba(0,0,0,.06)}
.profile-nudge .close-nudge{position:absolute;top:6px;right:8px;border:0;background:transparent;color:#6b7b90;font-size:1rem;line-height:1;padding:.25rem;border-radius:.35rem}
.profile-nudge .close-nudge:hover{background:rgba(0,0,0,.06);color:#102b46}
.profile-nudge ul.missing{margin:.5rem 0 0;padding:0;list-style:none;display:flex;flex-wrap:wrap;gap:.5rem}
.profile-nudge ul.missing li{background:#eef4ff;border:1px solid #d0e2ff;color:#234f84;font-size:.7rem;font-weight:600;letter-spacing:.5px;padding:.35rem .55rem;border-radius:40px;text-transform:uppercase}
.profile-nudge[data-level='low'] ul.missing li{background:#fff3e8;border-color:#ffd9bc;color:#8b3b00}
@media (max-width:575.98px){.profile-nudge{padding:.85rem}.profile-nudge .nudge-progress{width:56px;height:56px}}
</style>
<div class="profile-nudge" data-level="<?php echo htmlspecialchars($level); ?>" role="region" aria-label="Profile completeness reminder">
  <form method="get" style="position:absolute;top:0;right:0;">
    <?php foreach($_GET as $k=>$v){ if($k==='dismiss_profile_nudge') continue; echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars(is_array($v)?'':$v).'">'; } ?>
    <button type="submit" name="dismiss_profile_nudge" value="1" class="close-nudge" aria-label="Dismiss profile completeness reminder">&times;</button>
  </form>
  <div class="d-flex align-items-center gap-3">
    <div class="nudge-progress" style="--pct:<?php echo max(0,min(100,(int)$score)); ?>%;" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int)$score; ?>"><span><?php echo (int)$score; ?>%</span></div>
    <div class="flex-grow-1">
      <strong><?php echo $score < 30 ? 'Let\'s build your profile' : 'Boost your match quality'; ?></strong>
      <p class="small mb-2 text-muted">Complete your profile to appear in more relevant searches & improve job match scoring.</p>
      <a href="profile_edit.php" class="btn btn-sm btn-primary">Improve Profile</a>
      <?php if($missing): ?>
        <ul class="missing" aria-label="Suggested next steps">
          <?php foreach($missing as $m): ?><li><?php echo htmlspecialchars($m); ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>
