<?php
require_once '../config/config.php';
require_once '../classes/Helpers.php';
include '../includes/header.php';
include '../includes/nav.php';
?>
<style>
.terms-hero {
  background: linear-gradient(135deg,#0d6efd,#0b5ed7);
  color:#fff;
  padding:2.25rem 1.75rem;
  border-radius:.75rem;
  position:relative;
  overflow:hidden;
}
.terms-hero::after {
  content:'';
  position:absolute;inset:0;
  background:
    radial-gradient(circle at 25% 30%, rgba(255,255,255,.15), transparent 60%),
    radial-gradient(circle at 80% 70%, rgba(255,255,255,.12), transparent 65%);
  mix-blend-mode:overlay;
}
.section-anchor { scroll-margin-top:90px; }
.terms-toc a { text-decoration:none; }
.badge-tag { font-size:.65rem; letter-spacing:.5px; }
</style>

<main id="main-content" class="flex-grow-1 mb-5">
  <div class="container py-4 py-lg-5">
    <div class="terms-hero mb-4 shadow-sm">
      <h1 class="h4 fw-bold mb-2">Terms &amp; Conditions</h1>
      <p class="mb-3 mb-lg-2">
        These Terms govern your access to and use of the PWD Employment &amp; Skills Portal (the “Platform”).
        By creating an account or using any feature you agree to these Terms.
      </p>
      <div class="d-flex flex-wrap gap-2 small">
        <span class="badge text-bg-light text-dark badge-tag">Version 1.0</span>
        <span class="badge text-bg-light text-dark badge-tag">Last update: <?php echo date('Y-m-d'); ?></span>
      </div>
    </div>

    <nav class="mb-4">
      <div class="row g-3 terms-toc small">
        <div class="col-md-4">
          <ul class="list-unstyled mb-0">
            <li><a href="#acceptance">1. Acceptance</a></li>
            <li><a href="#roles">2. User Roles</a></li>
            <li><a href="#eligibility">3. Eligibility</a></li>
            <li><a href="#accounts">4. Accounts &amp; Security</a></li>
            <li><a href="#employer-verification">5. Employer Verification</a></li>
          </ul>
        </div>
        <div class="col-md-4">
          <ul class="list-unstyled mb-0">
            <li><a href="#job-posting">6. Job Posting Rules</a></li>
            <li><a href="#applications">7. Applications &amp; Fairness</a></li>
            <li><a href="#reports">8. Reporting &amp; Moderation</a></li>
            <li><a href="#prohibited">9. Prohibited Conduct</a></li>
            <li><a href="#intellectual-property">10. Intellectual Property</a></li>
          </ul>
        </div>
        <div class="col-md-4">
          <ul class="list-unstyled mb-0">
            <li><a href="#license">11. License to Platform</a></li>
            <li><a href="#disclaimers">12. Disclaimers</a></li>
            <li><a href="#liability">13. Limitation of Liability</a></li>
            <li><a href="#termination">14. Termination</a></li>
            <li><a href="#changes">15. Changes / Contact</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <section id="acceptance" class="mb-4 section-anchor">
      <h2 class="h6 fw-semibold mb-2">1. Acceptance</h2>
      <p class="small mb-0">
        By accessing the Platform you confirm that you have read, understood, and agree to these Terms and any linked policies
        (including the Security &amp; Privacy page). If you do not agree, discontinue use immediately.
      </p>
    </section>

    <section id="roles" class="mb-4 section-anchor">
      <h2 class="h6 fw-semibold mb-2">2. User Roles</h2>
      <p class="small mb-2">
        Users may act as: <strong>Job Seeker</strong> (search/apply), <strong>Employer</strong> (post and manage jobs),
        or <strong>Admin</strong> (moderation and platform integrity). Each role has distinct permissions enforced server‑side.
      </p>
    </section>

    <section id="eligibility" class="mb-4 section-anchor">
      <h2 class="h6 fw-semibold mb-2">3. Eligibility</h2>
      <p class="small mb-0">
        You must be legally permitted to enter into employment‑related interactions in your jurisdiction. Accounts impersonating
        other entities or created for spam/fraud will be removed.
      </p>
    </section>

    <section id="accounts" class="mb-4 section-anchor">
      <h2 class="h6 fw-semibold mb-2">4. Accounts &amp; Security</h2>
      <ul class="small mb-0 ps-3">
        <li>Keep credentials confidential; you are responsible for activity under your session.</li>
        <li>We may suspend or restrict accounts suspected of abuse or security violations.</li>
        <li>Optional uploads (resume, video) are user‑initiated; ensure you have the right to share that content.</li>
      </ul>
    </section>

    <section id="employer-verification" class="mb-4 section-anchor">
      <h2 class="h6 fw-semibold mb-2">5. Employer Verification</h2>
      <p class="small mb-2">
        Employers may be asked to upload a business permit / registration document. Verification status (Pending, Approved, Rejected)
        influences ability to post or promote jobs. Submitting false documents may lead to immediate removal.
      </p>
    </section>

    <section id="job-posting" class="mb-4 section-anchor">
      <h2 class="h6 fw-semibold mb-2">6. Job Posting Rules</h2>
      <ul class="small mb-0 ps-3">
        <li>Post only legitimate roles with accurate titles, descriptions, location/remote status, and compensation ranges where applicable.</li>
        <li>No discriminatory, offensive, or exploitative content.</li>
        <li>Do not request unnecessary sensitive personal data from applicants in the job description.</li>
        <li>Once applicants exist, certain criteria (core requirements) lock to prevent moving goalposts.</li>
      </ul>
    </section>

    <section id="applications" class="mb-4 section-anchor">
      <h2 class="h6 fw-semibold mb-2">7. Applications &amp; Fairness</h2>
      <p class="small mb-0">
        Job seekers are responsible for the accuracy of submitted information. Employers agree to evaluate applicants in
        good faith. The Platform does not guarantee hiring outcomes. Locked criteria help preserve fairness across all applicants.
      </p>
    </section>

    <section id="reports" class="mb-4 section-anchor">
      <h2 class="h6 fw-semibold mb-2">8. Reporting &amp; Moderation</h2>
      <p class="small mb-0">
        Job seekers may flag suspicious or non‑compliant postings. Admins may remove or edit content, or ban accounts, at discretion
        to protect integrity. False, malicious reporting can itself be a violation.
      </p>
    </section>

    <section id="prohibited" class="mb-4 section-anchor">
      <h2 class="h6 fw-semibold mb-2">9. Prohibited Conduct</h2>
      <ul class="small mb-0 ps-3">
        <li>Uploading malware or attempting to exploit vulnerabilities</li>
        <li>Scraping bulk profile or job data without permission</li>
        <li>Harassment, discriminatory remarks, or hate speech</li>
        <li>Misrepresenting affiliation or forging verification docs</li>
        <li>Circumventing application fairness (editing locked fields by hacks)</li>
      </ul>
    </section>

    <section id="intellectual-property" class="mb-4 section-anchor">
      <h2 class="h6 fw-semibold mb-2">10. Intellectual Property</h2>
      <p class="small mb-0">
        Platform code, design, and structural components remain the property of the Portal or its maintainers. User‑submitted
        content remains owned by the user, who grants the Platform a limited license to display and process it for the
        service’s functionality.
      </p>
    </section>

    <section id="license" class="mb-4 section-anchor">
      <h2 class="h6 fw-semibold mb-2">11. License to Platform</h2>
      <p class="small mb-0">
        Subject to compliance with these Terms, you receive a non‑exclusive, revocable license to access and use the Platform
        for lawful job search or hiring purposes.
      </p>
    </section>

    <section id="disclaimers" class="mb-4 section-anchor">
      <h2 class="h6 fw-semibold mb-2">12. Disclaimers</h2>
      <p class="small mb-0">
        The Platform is provided “as is” without warranties of completeness, accuracy, or uninterrupted availability.
        We do not vet every posting or profile in real time.
      </p>
    </section>

    <section id="liability" class="mb-4 section-anchor">
      <h2 class="h6 fw-semibold mb-2">13. Limitation of Liability</h2>
      <p class="small mb-0">
        To the maximum extent permitted by law, the Platform and its maintainers are not liable for lost opportunities,
        indirect, incidental, or consequential damages arising from use, reliance, or inability to access the service.
      </p>
    </section>

    <section id="termination" class="mb-4 section-anchor">
      <h2 class="h6 fw-semibold mb-2">14. Termination</h2>
      <p class="small mb-0">
        We may suspend or terminate access for policy violations, security risks, illegal activity, or misuse. Users may
        discontinue use at any time; some data may persist in backups or compliance records.
      </p>
    </section>

    <section id="changes" class="mb-5 section-anchor">
      <h2 class="h6 fw-semibold mb-2">15. Changes / Contact</h2>
      <p class="small mb-2">
        We may update these Terms to reflect feature changes or legal requirements. Continued use after updates constitutes acceptance.
      </p>
      <p class="small mb-0">
        For questions about these Terms or to raise concerns, use the in‑platform Support option (when logged in) or submit feedback
        through future public contact channels.
      </p>
    </section>

    <div class="text-center small text-muted">
      &copy; <?php echo date('Y'); ?> PWD Employment &amp; Skills Portal
    </div>
  </div>
</main>

<?php include '../includes/footer.php'; ?>