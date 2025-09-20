<?php
require_once '../config/config.php';
require_once '../classes/Helpers.php';
include '../includes/header.php';
include '../includes/nav.php';
?>
<style>
.policy-hero {
  background: linear-gradient(135deg,#0d6efd,#6610f2);
  color:#fff;
  border-radius:.75rem;
  padding:2.25rem 1.75rem;
  position:relative;
  overflow:hidden;
}
.policy-hero::after{
  content:'';
  position:absolute;inset:0;
  background:
    radial-gradient(circle at 25% 30%, rgba(255,255,255,.18), transparent 60%),
    radial-gradient(circle at 80% 70%, rgba(255,255,255,.15), transparent 65%);
  mix-blend-mode:overlay;
}
.section-anchor { scroll-margin-top:90px; }
.policy-toc a { text-decoration:none; }
.badge-tag { font-size:.65rem; letter-spacing:.5px; }
</style>

<main id="main-content" class="flex-grow-1 mb-5">
  <div class="container py-4 py-lg-5">

    <div class="policy-hero mb-4 shadow-sm">
      <h1 class="h4 fw-bold mb-2">Security &amp; Privacy</h1>
      <p class="mb-3 mb-lg-2">
        This page explains how the PWD Employment &amp; Skills Portal handles user data, safeguards information, and supports
        inclusive &amp; responsible usage.
      </p>
      <div class="d-flex flex-wrap gap-2 small">
        <span class="badge text-bg-light text-dark badge-tag">Version 1.0</span>
        <span class="badge text-bg-light text-dark badge-tag">Last update: <?php echo date('Y-m-d'); ?></span>
      </div>
    </div>

    <nav class="mb-4">
      <div class="row g-3 policy-toc small">
        <div class="col-md-4">
          <ul class="list-unstyled mb-0">
            <li><a href="#data-we-collect">1. Data We Collect</a></li>
            <li><a href="#how-used">2. How Data Is Used</a></li>
            <li><a href="#lawful-basis">3. Lawful / Legitimate Basis</a></li>
            <li><a href="#user-controls">4. Your Controls &amp; Choices</a></li>
          </ul>
        </div>
        <div class="col-md-4">
          <ul class="list-unstyled mb-0">
            <li><a href="#security">5. Security Measures</a></li>
            <li><a href="#retention">6. Retention</a></li>
            <li><a href="#sharing">7. Limited Sharing</a></li>
            <li><a href="#sensitive">8. Sensitive Information</a></li>
          </ul>
        </div>
        <div class="col-md-4">
          <ul class="list-unstyled mb-0">
            <li><a href="#children">9. Minors</a></li>
            <li><a href="#breach">10. Incident Handling</a></li>
            <li><a href="#changes">11. Changes</a></li>
            <li><a href="#contact">12. Contact / Feedback</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <section id="data-we-collect" class="mb-5 section-anchor">
      <h2 class="h5 fw-semibold mb-3">1. Data We Collect</h2>
      <div class="row g-3">
        <div class="col-md-6">
          <div class="border rounded p-3 h-100">
            <h3 class="h6 fw-semibold">Account &amp; Role Data</h3>
            <ul class="small mb-0 ps-3">
              <li>Name, email, chosen role (job seeker, employer, admin)</li>
              <li>Session identifiers (for login state)</li>
              <li>Employer verification status (Approved / Pending / Rejected)</li>
            </ul>
          </div>
        </div>
        <div class="col-md-6">
          <div class="border rounded p-3 h-100">
            <h3 class="h6 fw-semibold">Profile &amp; Application Data</h3>
            <ul class="small mb-0 ps-3">
              <li>Job seeker: education, experience indicators, optional disability field</li>
              <li>Uploaded documents: resume (PDF), optional video intro</li>
              <li>Employer: company name, description, permit / registration no., optional website &amp; phone</li>
              <li>Uploaded employer verification document (PDF/JPG/PNG/WEBP)</li>
            </ul>
          </div>
        </div>
        <div class="col-md-6">
          <div class="border rounded p-3 h-100">
            <h3 class="h6 fw-semibold">Job Posting Data</h3>
            <ul class="small mb-0 ps-3">
              <li>Title, description, skill requirements, work setup (WFH, part/full time)</li>
              <li>Original location (region/city), salary range, education &amp; experience requirements</li>
              <li>Match‑criteria fields locked after applicants appear (integrity control)</li>
            </ul>
          </div>
        </div>
        <div class="col-md-6">
          <div class="border rounded p-3 h-100">
            <h3 class="h6 fw-semibold">Reports &amp; Moderation</h3>
            <ul class="small mb-0 ps-3">
              <li>User reports of suspicious or non‑compliant jobs (reason + optional details)</li>
              <li>Administrative resolution status &amp; timestamps</li>
            </ul>
          </div>
        </div>
      </div>
    </section>

    <section id="how-used" class="mb-5 section-anchor">
      <h2 class="h5 fw-semibold mb-3">2. How Data Is Used</h2>
      <ul class="small mb-0 ps-3">
        <li>Facilitate job discovery, filtering, and application workflows</li>
        <li>Verify employer legitimacy and reduce fraudulent postings</li>
        <li>Improve fairness via locked job criteria once there are applicants</li>
        <li>Support moderation (reviewing job reports &amp; policy violations)</li>
        <li>Enhance future features (e.g. planned match scoring) in aggregated form</li>
      </ul>
    </section>

    <section id="lawful-basis" class="mb-5 section-anchor">
      <h2 class="h5 fw-semibold mb-3">3. Lawful / Legitimate Basis</h2>
      <p class="small mb-0">
        Data is processed based on: (a) fulfilling the platform’s core service (connecting job seekers &amp; employers),
        (b) legitimate interest in maintaining safety &amp; integrity, and (c) user consent for optional uploads (resume,
        video intro, employer documents).
      </p>
    </section>

    <section id="user-controls" class="mb-5 section-anchor">
      <h2 class="h5 fw-semibold mb-3">4. Your Controls &amp; Choices</h2>
      <ul class="small mb-0 ps-3">
        <li>Edit profile details (except locked application‑critical fields after applications exist)</li>
        <li>Choose whether to upload resume, video intro, or employer verification documents</li>
        <li>Report suspicious jobs (which notifies admins)</li>
        <li>Request removal of optional uploads by deleting or replacing them (future explicit delete UI can be added)</li>
      </ul>
    </section>

    <section id="security" class="mb-5 section-anchor">
      <h2 class="h5 fw-semibold mb-3">5. Security Measures</h2>
      <ul class="small mb-0 ps-3">
        <li>Role‑based access &amp; server‑side permission checks</li>
        <li>Prepared statements to mitigate SQL injection (core DB operations)</li>
        <li>File uploads stored in segmented directories (resumes, videos, employers)</li>
        <li>Restricted accepted MIME types for documents / media</li>
        <li>Integrity logic: job requirement fields locked after applicants appear</li>
        <li>Planned improvements: CSRF tokens, stricter MIME validation, encryption at rest for sensitive docs</li>
      </ul>
    </section>

    <section id="retention" class="mb-5 section-anchor">
      <h2 class="h5 fw-semibold mb-3">6. Retention</h2>
      <p class="small mb-0">
        Core account &amp; job posting data persists while the account or posting remains active. Optional uploads (resume,
        video, employer document) remain until replaced or manually purged during housekeeping. Resolved reports may be
        archived for audit history.
      </p>
    </section>

    <section id="sharing" class="mb-5 section-anchor">
      <h2 class="h5 fw-semibold mb-3">7. Limited Sharing</h2>
      <p class="small mb-0">
        We do not sell user data. Employers see only applicant information necessary for hiring decisions. Admins can
        access data needed for moderation. Aggregated / anonymized metrics may be used to improve platform features.
      </p>
    </section>

    <section id="sensitive" class="mb-5 section-anchor">
      <h2 class="h5 fw-semibold mb-3">8. Sensitive Information</h2>
      <p class="small mb-0">
        Disability information (if provided) is user‑entered descriptive text; the platform does not classify medical
        conditions. Please avoid entering highly sensitive medical records. Do not upload IDs unless explicitly required
        for verification (current system does not request government IDs).
      </p>
    </section>

    <section id="children" class="mb-5 section-anchor">
      <h2 class="h5 fw-semibold mb-3">9. Minors</h2>
      <p class="small mb-0">
        The platform targets professional employment; accounts from users under legal working age should not be created.
      </p>
    </section>

    <section id="breach" class="mb-5 section-anchor">
      <h2 class="h5 fw-semibold mb-3">10. Incident Handling</h2>
      <p class="small mb-0">
        Suspected security or data exposure incidents are prioritized: isolate issue, restrict access, audit logs, and
        notify affected stakeholders where required. Future versions will include automated alerting hooks.
      </p>
    </section>

    <section id="changes" class="mb-5 section-anchor">
      <h2 class="h5 fw-semibold mb-3">11. Changes to This Page</h2>
      <p class="small mb-0">
        Material changes will update the version tag above. Continued use after changes indicates acceptance of the
        revised policy.
      </p>
    </section>

    <section id="contact" class="mb-4 section-anchor">
      <h2 class="h5 fw-semibold mb-3">12. Contact / Feedback</h2>
      <p class="small mb-2">
        For privacy questions or removal requests, use the Support channel (when logged in) or the general feedback
        mechanism planned in future releases.
      </p>
      <ul class="small mb-0 ps-3">
        <li>Request incorrect data correction</li>
        <li>Flag potential misuse of uploaded content</li>
        <li>Suggest additional accessibility protections</li>
      </ul>
    </section>

    <div class="text-center small text-muted">
      &copy; <?php echo date('Y'); ?> PWD Employment &amp; Skills Portal
    </div>
  </div>
</main>

<?php include '../includes/footer.php'; ?>