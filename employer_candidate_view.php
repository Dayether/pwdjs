<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';
require_once 'classes/User.php';
require_once 'classes/ProfileCompleteness.php';
require_once 'classes/Experience.php';
require_once 'classes/Certification.php';
require_once 'classes/Skill.php';

if (session_status() === PHP_SESSION_NONE) session_start();
Helpers::requireLogin();
if (!Helpers::isEmployer()) { Helpers::flash('error','Access denied.'); Helpers::redirectToRoleDashboard(); }
Helpers::storeLastPage();

$userId = $_GET['user_id'] ?? '';
if ($userId === '') { Helpers::redirect('employer_candidates.php'); }

$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT user_id,name,email,education,education_level,experience,primary_skill_summary,expected_salary_currency,expected_salary_min,expected_salary_max,expected_salary_period,preferred_work_setup,preferred_location,interests,accessibility_preferences,profile_picture,video_intro,region,province,city,resume FROM users WHERE user_id=? AND role='job_seeker' LIMIT 1");
$stmt->execute([$userId]);
$cand = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$cand) { Helpers::flash('error','Candidate not found.'); Helpers::redirect('employer_candidates.php'); }

// Compute profile completeness if possible
$score = ProfileCompleteness::ensure($cand['user_id'], 1440);

// Load ancillary entities (safe sets only)
$experiences = [];$certs=[];$skills=[];
try { $experiences = Experience::listByUser($cand['user_id']); } catch (Throwable $e) {}
try { $certs = Certification::listByUser($cand['user_id']); } catch (Throwable $e) {}
try { $skills = Skill::getSkillsForUser($cand['user_id']); } catch (Throwable $e) {}
// Defensive: coerce non-array results
if (!is_array($experiences)) $experiences=[]; if (!is_array($certs)) $certs=[]; if (!is_array($skills)) $skills=[];
// If Skill::getSkillsForUser returned a flat string (unlikely but defensive), wrap to array
if (is_string($skills)) { $skills = [$skills]; }
if (!is_array($skills)) { $skills = []; }
// Normalize skills so template can safely access ['name']
if (!empty($skills)) {
  $normalized = [];
  foreach ($skills as $sk) {
    if (is_array($sk)) {
      if (isset($sk['name'])) { $normalized[] = ['name'=>$sk['name']]; continue; }
      // If array but different keys e.g. ['skill_name']
      if (isset($sk['skill_name'])) { $normalized[] = ['name'=>$sk['skill_name']]; continue; }
      // Fallback: first scalar value
      $first = null; foreach ($sk as $v){ if (is_scalar($v)){ $first=$v; break; } }
      if ($first!==null) { $normalized[] = ['name'=>$first]; }
    } elseif (is_string($sk)) {
      $normalized[] = ['name'=>$sk];
    }
  }
  $skills = $normalized;
}
// Ensure every element now is array with 'name'
foreach ($skills as $idx=>$sk) {
  if (!is_array($sk) || !isset($sk['name'])) { unset($skills[$idx]); }
}

// Sanitized resource links
$resumeLink = (!empty($cand['resume']) && strpos($cand['resume'],'..')===false) ? htmlspecialchars($cand['resume']) : null;
$videoLink  = (!empty($cand['video_intro']) && strpos($cand['video_intro'],'..')===false) ? htmlspecialchars($cand['video_intro']) : null;

include 'includes/header.php';
include 'includes/nav.php';
$backUrl = Helpers::getLastPage('employer_candidates.php');

$salParts=[];
if (!empty($cand['expected_salary_min'])) $salParts[] = number_format((int)$cand['expected_salary_min']);
if (!empty($cand['expected_salary_max'])) $salParts[] = number_format((int)$cand['expected_salary_max']);
$salRange = implode(' - ', $salParts);
$salaryDisp = $salRange ? (($cand['expected_salary_currency'] ?: 'PHP').' '.$salRange.' / '.ucfirst($cand['expected_salary_period'] ?: 'monthly')) : 'Unspecified';
?>
<div class="container pt-3 pb-4">
  <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back</a>
</div>
<div class="container pb-5">
  <div class="row g-4">
    <div class="col-lg-5">
      <div class="jsp-summary-card mb-4">
        <div class="jsp-summary-inner p-4">
          <div class="d-flex flex-wrap align-items-start mb-3 gap-3">
            <div class="jsp-avatar jsp-avatar-lg flex-shrink-0">
              <?php if (!empty($cand['profile_picture'])): ?>
                <img src="<?php echo htmlspecialchars($cand['profile_picture']); ?>" alt="Profile photo of <?php echo htmlspecialchars($cand['name'] ?: 'Candidate'); ?>">
              <?php else: ?>
                <i class="bi bi-person" aria-hidden="true"></i>
              <?php endif; ?>
            </div>
            <div class="flex-grow-1" style="min-width:240px;">
              <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <h1 class="h5 fw-bold mb-0" style="letter-spacing:-.5px;">
                  <?php echo htmlspecialchars($cand['name'] ?: 'Unnamed Candidate'); ?>
                </h1>
                <?php if ($score !== null): ?>
                  <span class="badge text-bg-info">Completeness <?php echo (int)$score; ?>%</span>
                <?php endif; ?>
              </div>
              <div class="jsp-meta-chips mb-2">
                <?php
                  $educationLabel = $cand['education_level'] ?: ($cand['education'] ?: 'Education N/A');
                  $experienceLabel = ($cand['experience'] !== null) ? (int)$cand['experience'] . ' yr'.($cand['experience']==1?'':'s') : '0 yrs';
                  $locParts = array_filter([$cand['city'] ?? '', $cand['province'] ?? '', $cand['region'] ?? '']);
                ?>
                <span class="jsp-chip"><i class="bi bi-mortarboard" aria-hidden="true"></i><?php echo htmlspecialchars($educationLabel); ?></span>
                <span class="jsp-chip"><i class="bi bi-briefcase" aria-hidden="true"></i><?php echo htmlspecialchars($experienceLabel); ?></span>
                <?php if ($locParts): ?><span class="jsp-chip"><i class="bi bi-geo" aria-hidden="true"></i><?php echo htmlspecialchars(implode(', ',$locParts)); ?></span><?php endif; ?>
                <?php if (!empty($cand['preferred_work_setup'])): ?><span class="jsp-chip"><i class="bi bi-building-check" aria-hidden="true"></i><?php echo htmlspecialchars($cand['preferred_work_setup']); ?></span><?php endif; ?>
                <?php if (!empty($cand['preferred_location'])): ?><span class="jsp-chip"><i class="bi bi-geo-alt" aria-hidden="true"></i><?php echo htmlspecialchars($cand['preferred_location']); ?></span><?php endif; ?>
                <?php if ($videoLink): ?><span class="jsp-chip"><i class="bi bi-camera-video" aria-hidden="true"></i>Video Intro</span><?php endif; ?>
              </div>
              <?php if (!empty($cand['primary_skill_summary'])): ?>
                <div class="small text-muted" style="max-width:720px;">"<?php echo nl2br(htmlspecialchars($cand['primary_skill_summary'])); ?>"</div>
              <?php else: ?>
                <div class="small text-muted fst-italic">No professional summary provided.</div>
              <?php endif; ?>
            </div>
          </div>
          <h2 class="h6 fw-bold mb-3 text-uppercase small">Profile Details</h2>
          <?php if (!empty($skills)): ?>
            <div class="mb-3">
              <div class="d-flex flex-wrap gap-1">
                          <?php foreach ($skills as $sk): ?>
                            <?php
                              $label = null;
                              if (is_array($sk) && isset($sk['name'])) { $label = $sk['name']; }
                              elseif (is_array($sk)) {
                                // fallback first scalar
                                foreach ($sk as $vv){ if (is_scalar($vv)){ $label=$vv; break; } }
                              } elseif (is_string($sk)) { $label = $sk; }
                              if ($label === null) continue; // skip invalid element
                            ?>
                            <span class="badge rounded-pill text-bg-light border" style="font-weight:500;font-size:.65rem;letter-spacing:.5px;"><i class="bi bi-stars me-1" aria-hidden="true"></i><?php echo htmlspecialchars($label); ?></span>
                          <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
          <div class="data-grid">
            <?php if (!empty($cand['email'])): ?><div class="data-item"><div class="label">Email</div><div class="value"><?php echo htmlspecialchars($cand['email']); ?></div></div><?php endif; ?>
            <div class="data-item"><div class="label">Education</div><div class="value"><?php echo htmlspecialchars($educationLabel); ?></div></div>
            <div class="data-item"><div class="label">Experience</div><div class="value"><?php echo htmlspecialchars($experienceLabel); ?></div></div>
            <?php if ($salaryDisp && $salaryDisp!=='Unspecified'): ?><div class="data-item"><div class="label">Expected Salary</div><div class="value"><?php echo htmlspecialchars($salaryDisp); ?></div></div><?php endif; ?>
            <?php if (!empty($cand['interests'])): ?><div class="data-item"><div class="label">Interests</div><div class="value"><?php echo nl2br(htmlspecialchars($cand['interests'])); ?></div></div><?php endif; ?>
            <?php if (!empty($cand['accessibility_preferences'])): ?><div class="data-item"><div class="label">Accessibility</div><div class="value"><?php echo htmlspecialchars($cand['accessibility_preferences']); ?></div></div><?php endif; ?>
            <?php if (!empty($cand['preferred_work_setup'])): ?><div class="data-item"><div class="label">Work Setup</div><div class="value"><?php echo htmlspecialchars($cand['preferred_work_setup']); ?></div></div><?php endif; ?>
            <?php if (!empty($cand['preferred_location'])): ?><div class="data-item"><div class="label">Preferred Location</div><div class="value"><?php echo htmlspecialchars($cand['preferred_location']); ?></div></div><?php endif; ?>
            <?php if ($resumeLink): ?><div class="data-item"><div class="label">Resume</div><div class="value"><a class="text-decoration-none" target="_blank" href="<?php echo $resumeLink; ?>"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>View</a></div></div><?php endif; ?>
            <?php if ($videoLink): ?><div class="data-item"><div class="label">Video Intro</div><div class="value"><a class="text-decoration-none" target="_blank" href="<?php echo $videoLink; ?>"><i class="bi bi-camera-video me-1" aria-hidden="true"></i>Watch</a></div></div><?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-7">
      <div class="section-card p-4 fade-up" role="region" aria-label="Work experience">
        <div class="section-card-header mb-2 d-flex align-items-center"><span class="section-icon me-2"><i class="bi bi-briefcase" aria-hidden="true"></i></span><h2 class="h6 fw-semibold mb-0">Work Experience</h2></div>
        <?php if (!$experiences): ?>
          <div class="text-muted small">No experience listed.</div>
        <?php else: ?>
          <ul class="bulleted-list mb-0">
            <?php foreach ($experiences as $exp): if(!is_array($exp)) continue; ?>
              <li>
                <span class="fw-semibold"><?php echo Helpers::sanitizeOutput($exp['position']); ?></span> @ <?php echo Helpers::sanitizeOutput($exp['company']); ?>
                <span class="text-muted">(<?php echo htmlspecialchars(substr($exp['start_date'],0,7)); ?> - <?php echo $exp['is_current'] ? 'Present' : ($exp['end_date'] ? htmlspecialchars(substr($exp['end_date'],0,7)) : '—'); ?>)</span>
                <?php if (!empty($exp['description'])): ?><div class="mt-1 text-muted small"><?php echo nl2br(htmlspecialchars($exp['description'])); ?></div><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
      <div class="section-card p-4 mt-4 fade-up" role="region" aria-label="Certifications">
        <div class="section-card-header mb-2 d-flex align-items-center"><span class="section-icon me-2"><i class="bi bi-patch-check" aria-hidden="true"></i></span><h2 class="h6 fw-semibold mb-0">Certifications</h2></div>
        <?php if (!$certs): ?>
          <div class="text-muted small">No certifications listed.</div>
        <?php else: ?>
          <ul class="bulleted-list mb-0">
            <?php foreach ($certs as $ct): if(!is_array($ct)) continue; ?>
              <li>
                <span class="fw-semibold"><?php echo Helpers::sanitizeOutput($ct['name']); ?></span>
                <?php if ($ct['issuer']): ?><span class="text-muted"> · <?php echo Helpers::sanitizeOutput($ct['issuer']); ?></span><?php endif; ?>
                <?php if ($ct['issued_date']): ?><span class="text-muted"> (<?php echo htmlspecialchars(substr($ct['issued_date'],0,7)); ?>)</span><?php endif; ?>
                <?php if ($ct['credential_id']): ?><div class="text-muted small">Credential: <?php echo Helpers::sanitizeOutput($ct['credential_id']); ?></div><?php endif; ?>
                <?php if ($ct['attachment_path']): ?><div><a class="text-decoration-none small" href="<?php echo htmlspecialchars($ct['attachment_path']); ?>" target="_blank"><i class="bi bi-paperclip me-1" aria-hidden="true"></i>Attachment</a></div><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
