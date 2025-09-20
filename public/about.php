<?php
require_once '../config/config.php';
require_once '../classes/Helpers.php';
require_once '../classes/Database.php'; // if later you want stats
include '../includes/header.php';
include '../includes/nav.php';

/* (Optional) Basic stats – safe fallback if tables exist */
$pdo = null;
$totalJobs = $activeWFH = $approvedEmployers = $jobSeekers = null;
try {
  $pdo = Database::getConnection();
  $totalJobs = (int)$pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
  $activeWFH = (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE remote_option='Work From Home'")->fetchColumn();
  $approvedEmployers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='employer' AND employer_status='Approved'")->fetchColumn();
  $jobSeekers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='job_seeker'")->fetchColumn();
} catch(Throwable $e) {
  // silent
}

?>
<style>
.about-hero {
  background: linear-gradient(135deg,#0d6efd 0%,#6610f2 100%);
  color:#fff;
  border-radius:.75rem;
  position:relative;
  overflow:hidden;
}
.about-hero::after {
  content:'';
  position:absolute;
  inset:0;
  background:
    radial-gradient(circle at 30% 20%, rgba(255,255,255,.18), transparent 60%),
    radial-gradient(circle at 80% 70%, rgba(255,255,255,.15), transparent 65%);
  mix-blend-mode:overlay;
}
.section-anchor {
  scroll-margin-top:90px;
}
.feature-icon {
  font-size:1.5rem;
  width:2.5rem;
  height:2.5rem;
  display:flex;
  align-items:center;
  justify-content:center;
  border-radius:.5rem;
  background:#f1f5f9;
  color:#0d6efd;
}
.badge-pill-sm {
  font-size:.65rem;
  letter-spacing:.5px;
  font-weight:600;
}
.timeline-dot {
  width:12px;height:12px;border:2px solid var(--bs-primary);border-radius:50%;background:#fff;
  position:relative;top:2px;
}
.timeline-line {
  position:absolute;left:5px;top:14px;width:2px;background:var(--bs-primary);opacity:.35;
}
</style>

<main id="main-content" class="flex-grow-1 mb-5">
  <div class="container py-4 py-lg-5">

    <!-- HERO -->
    <div class="about-hero p-4 p-lg-5 mb-4 shadow-sm">
      <div class="row align-items-center">
        <div class="col-lg-7 position-relative">
          <h1 class="h2 fw-bold mb-3">About the PWD Employment & Skills Portal</h1>
          <p class="lead mb-3">
            A focused platform connecting Persons with Disabilities (PWDs) to inclusive, remote‑friendly job opportunities and
            helping employers build truly accessible teams.
          </p>
          <div class="d-flex flex-wrap gap-2">
            <a href="#mission" class="btn btn-light btn-sm"><i class="bi bi-bullseye me-1"></i>Mission</a>
            <a href="#features" class="btn btn-outline-light btn-sm"><i class="bi bi-stars me-1"></i>Features</a>
            <a href="#accessibility" class="btn btn-outline-light btn-sm"><i class="bi bi-universal-access me-1"></i>Accessibility</a>
            <a href="#roadmap" class="btn btn-outline-light btn-sm"><i class="bi bi-map me-1"></i>Roadmap</a>
          </div>
        </div>
        <div class="col-lg-5 mt-4 mt-lg-0">
          <div class="row g-3">
            <div class="col-6">
              <div class="bg-white bg-opacity-75 rounded shadow-sm p-3 h-100 text-center">
                <div class="fw-bold fs-5 mb-0"><?php echo $totalJobs!==null?number_format($totalJobs):'—'; ?></div>
                <div class="small text-muted">Total Jobs</div>
              </div>
            </div>
            <div class="col-6">
              <div class="bg-white bg-opacity-75 rounded shadow-sm p-3 h-100 text-center">
                <div class="fw-bold fs-5 mb-0"><?php echo $activeWFH!==null?number_format($activeWFH):'—'; ?></div>
                <div class="small text-muted">WFH Posts</div>
              </div>
            </div>
            <div class="col-6">
              <div class="bg-white bg-opacity-75 rounded shadow-sm p-3 h-100 text-center">
                <div class="fw-bold fs-5 mb-0"><?php echo $approvedEmployers!==null?number_format($approvedEmployers):'—'; ?></div>
                <div class="small text-muted">Approved Employers</div>
              </div>
            </div>
            <div class="col-6">
              <div class="bg-white bg-opacity-75 rounded shadow-sm p-3 h-100 text-center">
                <div class="fw-bold fs-5 mb-0"><?php echo $jobSeekers!==null?number_format($jobSeekers):'—'; ?></div>
                <div class="small text-muted">PWD Job Seekers</div>
              </div>
            </div>
          </div>
          <div class="text-white-50 small mt-3">
            
          </div>
        </div>
      </div>
    </div>

    <!-- Mission -->
    <section id="mission" class="mb-5 section-anchor">
      <h2 class="h4 fw-semibold mb-3"><i class="bi bi-bullseye me-2 text-primary"></i>Our Mission</h2>
      <p class="mb-2">
        Empower PWD professionals by removing traditional barriers—geography, bias, and inaccessible hiring processes—while
        enabling employers to discover skilled, motivated talent in a structured, data‑assisted way.
      </p>
      <p class="mb-0">
        Sa madaling salita: <em>Mas mabilis at mas patas na hiring para sa PWD community.</em>
      </p>
    </section>

    <!-- Why Built -->
    <section class="mb-5">
      <h2 class="h5 fw-semibold mb-3"><i class="bi bi-question-circle me-2 text-primary"></i>Bakit namin ginawa ito?</h2>
      <ul class="small mb-0 ps-3">
        <li>Maraming PWD candidates na highly skilled pero hindi umaabot sa interview dahil sa filtering bias.</li>
        <li>Kulang ang structured data sa accessibility needs vs job requirements.</li>
        <li>WFH at flexible roles are increasing — perfect opportunity to widen inclusion.</li>
        <li>Employers need lightweight tools to verify & manage inclusive hiring without heavy HR software.</li>
      </ul>
    </section>

    <!-- Features -->
    <section id="features" class="mb-5 section-anchor">
      <h2 class="h4 fw-semibold mb-3"><i class="bi bi-stars me-2 text-primary"></i>Key Features</h2>
      <div class="row g-3">
        <div class="col-md-4">
          <div class="border rounded h-100 p-3">
            <div class="feature-icon mb-2"><i class="bi bi-person-vcard"></i></div>
            <h3 class="h6 fw-semibold">Rich Candidate Profiles</h3>
            <p class="small mb-2">Skills, experience, education, accessibility tags, optional resume & video intro.</p>
            <span class="badge rounded-pill text-bg-primary badge-pill-sm">PWD Focused</span>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded h-100 p-3">
            <div class="feature-icon mb-2"><i class="bi bi-diagram-3"></i></div>
            <h3 class="h6 fw-semibold">Matching Criteria Lock</h3>
            <p class="small mb-2">Once applicants exist, core fields (skills/exp/education) lock to preserve fairness.</p>
            <span class="badge rounded-pill text-bg-warning badge-pill-sm">Integrity</span>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded h-100 p-3">
            <div class="feature-icon mb-2"><i class="bi bi-building-check"></i></div>
            <h3 class="h6 fw-semibold">Employer Verification</h3>
            <p class="small mb-2">Document upload & status control to reduce fake or exploitative listings.</p>
            <span class="badge rounded-pill text-bg-success badge-pill-sm">Trust</span>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded h-100 p-3">
            <div class="feature-icon mb-2"><i class="bi bi-clipboard2-data"></i></div>
            <h3 class="h6 fw-semibold">Structured Filtering</h3>
            <p class="small mb-2">Education, max experience, region/city, min pay, accessibility tags.</p>
            <span class="badge rounded-pill text-bg-secondary badge-pill-sm">Efficiency</span>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded h-100 p-3">
            <div class="feature-icon mb-2"><i class="bi bi-speedometer2"></i></div>
            <h3 class="h6 fw-semibold">Match Scoring (Planned)</h3>
            <p class="small mb-2">Weighted evaluation of required vs general skills & experience.</p>
            <span class="badge rounded-pill text-bg-info badge-pill-sm">Upcoming</span>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded h-100 p-3">
            <div class="feature-icon mb-2"><i class="bi bi-lock"></i></div>
            <h3 class="h6 fw-semibold">Data Privacy Respect</h3>
            <p class="small mb-2">Only essential fields stored; optional uploads; future consent controls.</p>
            <span class="badge rounded-pill text-bg-dark badge-pill-sm">Privacy</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Accessibility -->
    <section id="accessibility" class="mb-5 section-anchor">
      <h2 class="h4 fw-semibold mb-3"><i class="bi bi-universal-access me-2 text-primary"></i>Accessibility Commitments</h2>
      <div class="row g-3">
        <div class="col-md-6">
          <ul class="small mb-0 ps-3">
            <li>Keyboard navigable primary forms & buttons.</li>
            <li>Semantic HTML structure for assistive technologies.</li>
            <li>High‑contrast accent colors; consistent icon + text labeling.</li>
            <li>Descriptive links (e.g., “View profile” vs generic “Click”).</li>
            <li>Locking of job criteria to reduce mid‑process shifting (fairness).</li>
          </ul>
        </div>
        <div class="col-md-6">
          <ul class="small mb-0 ps-3">
            <li>Planned: WCAG contrast audit pass.</li>
            <li>Planned: User setting for larger base font size.</li>
            <li>Planned: Structured disability & accommodation preference fields.</li>
            <li>Planned: Alternative text enforcement for uploaded employer logos.</li>
          </ul>
        </div>
      </div>
    </section>

    <!-- Roadmap -->
    <section id="roadmap" class="mb-5 section-anchor">
      <h2 class="h4 fw-semibold mb-3"><i class="bi bi-map me-2 text-primary"></i>Roadmap (Preview)</h2>
      <div class="position-relative ps-4">
        <div class="timeline-line" style="height:100%;"></div>
        <div class="mb-4">
          <div class="timeline-dot"></div>
          <h3 class="h6 fw-semibold ms-3">Phase 1 (Current)</h3>
          <ul class="small ms-3 mb-2">
            <li>Core job posting & application flow</li>
            <li>Employer verification & status gating</li>
            <li>Basic filtering (education, exp, region, pay)</li>
          </ul>
        </div>
        <div class="mb-4">
          <div class="timeline-dot"></div>
          <h3 class="h6 fw-semibold ms-3">Phase 2</h3>
          <ul class="small ms-3 mb-2">
            <li>Weighted match scoring UI (progress bars in listings)</li>
            <li>In‑place applicant moderation (AJAX approve/decline)</li>
            <li>Profile field for accommodation preferences</li>
          </ul>
        </div>
        <div class="mb-4">
          <div class="timeline-dot"></div>
          <h3 class="h6 fw-semibold ms-3">Phase 3</h3>
          <ul class="small ms-3 mb-2">
            <li>Email or in‑app notification triggers</li>
            <li>Analytics: hires per skill, time‑to‑fill</li>
            <li>Admin reporting dashboard refinement</li>
          </ul>
        </div>
        <div class="mb-2">
          <div class="timeline-dot"></div>
          <h3 class="h6 fw-semibold ms-3">Phase 4+</h3>
          <ul class="small ms-3 mb-0">
            <li>Accessibility compliance checklist for each job</li>
            <li>AI skill suggestion & resume parsing (privacy‑safe)</li>
            <li>Multi‑language interface</li>
          </ul>
        </div>
      </div>
      <div class="small text-muted mt-2">Sequence subject to change based on user feedback.</div>
    </section>

    <!-- Contact / Feedback -->
    <section id="contact" class="mb-5 section-anchor">
      <h2 class="h4 fw-semibold mb-3"><i class="bi bi-chat-dots me-2 text-primary"></i>Contact & Feedback</h2>
      <p class="small mb-2">
        This portal is evolving. Kung may suggestion (feature, accessibility improvement, bug, refinement),
        please reach out via the support / feedback channel inside the system (or future support form).
      </p>
      <ul class="small ps-3 mb-0">
        <li>Feature idea: Weighted skill matching improvements</li>
        <li>Report: Inaccurate job or suspicious employer</li>
        <li>Accessibility: Screen reader or contrast issue</li>
      </ul>
    </section>

    <!-- Notes -->
    <section class="mb-4">
      <h2 class="h6 fw-semibold mb-2"><i class="bi bi-exclamation-circle me-2 text-primary"></i>Disclaimer</h2>
      <p class="small mb-0">
        All data provided by employers and applicants are self‑reported. Always exercise standard diligence.
        The platform aims to assist, not replace, responsible hiring judgment.
      </p>
    </section>

    <div class="text-center small text-muted">
      &copy; <?php echo date('Y'); ?> PWD Employment & Skills Portal · Inclusive opportunities start here.
    </div>

  </div>
</main>

<?php include '../includes/footer.php'; ?>