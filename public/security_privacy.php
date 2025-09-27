<?php
require_once '../config/config.php';
require_once '../classes/Helpers.php';
include '../includes/header.php';
include '../includes/nav.php';
?>
<style>
/* ===== Security & Privacy Layout Redesign ===== */
:root {
  --policy-accent: #4f46e5; /* indigo-600 */
  --policy-accent-rgb: 79 70 229;
  --policy-bg-soft: #f5f7fb;
  --policy-border: #e2e8f0;
  --policy-gradient: linear-gradient(135deg,#0d6efd 0%,#6610f2 55%,#8b5cf6 100%);
  --policy-radius-lg: 1rem;
  --policy-radius-md: .9rem;
  --policy-radius-sm: .65rem;
  --policy-text-light: #000000; /* hard black for maximum contrast per user request */
  --policy-text-muted: #6b7280;
}

@media (prefers-color-scheme: dark) {
  :root {
    --policy-bg-soft:#111827;
    --policy-border:#1f2937;
  }
}

/* Progress bar refined */
.policy-progress-bar {position:fixed;left:0;top:0;height:2px;width:0;z-index:2500;background:#4f46e5;transition:width .15s ease;box-shadow:0 0 0 1px rgba(255,255,255,.15),0 1px 3px rgba(0,0,0,.15);} /* refined */

.policy-wrapper {display:grid;gap:2rem;grid-template-columns:1fr;}
@media (min-width: 992px){
  .policy-wrapper {grid-template-columns:260px 1fr;align-items:start;}
}

.policy-sidebar {position:sticky;top:90px;align-self:start;max-height:calc(100vh - 100px);overflow:auto;padding-right:4px;}
.policy-sidebar::-webkit-scrollbar{width:6px;}
.policy-sidebar::-webkit-scrollbar-track{background:transparent;}
.policy-sidebar::-webkit-scrollbar-thumb{background:rgba(var(--policy-accent-rgb)/.35);border-radius:4px;}

.policy-toc-heading{font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:#000;margin-bottom:.35rem;}
.policy-toc {list-style:none;padding-left:0;margin:0;}
.policy-toc li+li{margin-top:.15rem;}
.policy-toc a{display:block;padding:.5rem .75rem .5rem 1rem;border-radius:var(--policy-radius-sm);font-size:.8rem;font-weight:500;color:#000;text-decoration:none;position:relative;line-height:1.15;background:transparent;transition:background .15s,color .15s;}
.policy-toc a::before{content:"";position:absolute;left:.4rem;top:50%;translate:0 -50%;width:4px;height:55%;border-radius:3px;background:transparent;transition:background .18s,height .18s;}
.policy-toc a:hover{background:#eef2ff;color:#1e3a8a;}
.policy-toc a.active{background:transparent;color:#111827;font-weight:600;}
.policy-toc a.active::before{background:var(--policy-accent);height:70%;}
.policy-toc a:focus-visible{outline:2px solid #6366f1;outline-offset:2px;}
@media (prefers-color-scheme: dark){
  .policy-toc a{color:#9ca3af;}
  .policy-toc a:hover{background:#1f2937;color:#fff;}
  .policy-toc a.active{color:#fff;}
  .policy-toc a.active::before{background:#6366f1;}
}

.policy-mobile-nav {border:1px solid var(--policy-border);border-radius:.75rem;background:#fff;display:flex;flex-wrap:wrap;gap:.5rem;padding:.75rem 1rem;margin-bottom:1.25rem;}
@media (min-width:992px){.policy-mobile-nav{display:none;}}
.policy-mobile-nav a{flex:1 1 calc(50% - .5rem);font-size:.7rem;text-decoration:none;font-weight:600;padding:.65rem .5rem;border:1px solid var(--policy-border);border-radius:.65rem;text-align:center;color:#374151;background:#f9fafb;transition:all .15s;} 
.policy-mobile-nav a:hover{background:#eef2ff;color:#1e3a8a;}

.policy-hero {background:var(--policy-gradient);color:#fff;border-radius:var(--policy-radius-lg);padding:2.55rem 2rem 2.2rem;position:relative;overflow:hidden;box-shadow:0 10px 25px -10px rgba(var(--policy-accent-rgb)/.55),0 4px 12px -4px rgba(0,0,0,.25);}
.policy-hero::before,.policy-hero::after{content:"";position:absolute;border-radius:999px;filter:blur(40px);opacity:.55;mix-blend-mode:overlay;}
.policy-hero::before{width:320px;height:320px;background:radial-gradient(circle at 30% 40%,#fff,transparent 70%);left:-60px;top:-80px;}
.policy-hero::after{width:360px;height:360px;background:radial-gradient(circle at 70% 60%,#fff,transparent 70%);right:-80px;bottom:-120px;}
.policy-hero h1{font-weight:700;letter-spacing:-.5px;}
.policy-badges{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1.1rem;}
.policy-badges .badge{font-size:.65rem;letter-spacing:.75px;font-weight:600;background:rgba(255,255,255,.9);color:#312e81;padding:.45rem .65rem;border-radius:.5rem;}
.policy-badges .badge.dark{background:rgba(31,41,55,.85);color:#f9fafb;}

.policy-content-flow{margin-top:2.1rem;}
.policy-section{margin-bottom:3rem;position:relative;padding-top:.25rem;}
.policy-section:not(:first-of-type)::before{content:"";position:absolute;left:0;right:0;top:-1.15rem;height:1px;background:linear-gradient(90deg,rgba(0,0,0,.05),rgba(0,0,0,0));}
@media (prefers-color-scheme: dark){.policy-section:not(:first-of-type)::before{background:linear-gradient(90deg,rgba(255,255,255,.07),rgba(255,255,255,0));}}
.policy-section:last-of-type{margin-bottom:2rem;}
.section-anchor{scroll-margin-top:90px;}

.policy-section-title{display:flex;align-items:center;gap:.65rem;margin-bottom:.85rem;}
.policy-section-title .number{flex-shrink:0;width:32px;height:32px;border-radius:10px;background:#4f46e5;color:#fff;font-weight:600;display:flex;align-items:center;justify-content:center;font-size:.75rem;box-shadow:0 2px 4px rgba(0,0,0,.15);} 
@media (prefers-color-scheme: dark){.policy-section-title .number{background:#6366f1;color:#fff;}}
/* lighter badges for later sections */
.policy-section:nth-of-type(n+3) .policy-section-title .number{background:#6366f11a;color:#4f46e5;box-shadow:none;}
@media (prefers-color-scheme: dark){.policy-section:nth-of-type(n+3) .policy-section-title .number{background:#6366f11f;color:#818cf8;}}

.policy-section h2{font-size:1.02rem;font-weight:600;margin:0;}
.anchor-link-btn{opacity:0;transform:translateY(2px);transition:opacity .15s;display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:6px;background:transparent;color:#6366f1;border:1px solid transparent;}
.policy-section:hover .anchor-link-btn{opacity:1;}
.anchor-link-btn:hover{background:#eef2ff;border-color:#c7d2fe;}
@media (prefers-color-scheme: dark){.anchor-link-btn:hover{background:#1f2937;border-color:#374151;color:#818cf8;}}
.anchor-link-btn:focus-visible{outline:2px solid #6366f1;outline-offset:2px;opacity:1;}

.policy-card-grid{display:grid;gap:1rem;grid-template-columns:1fr;}
@media (min-width:768px){.policy-card-grid{grid-template-columns:repeat(2,1fr);} }
.policy-card{background:#fff;border:1px solid var(--policy-border);border-radius:var(--policy-radius-md);padding:1.05rem 1.05rem 1rem;position:relative;display:flex;flex-direction:column;gap:.4rem;box-shadow:0 2px 4px rgba(0,0,0,.04);} 
@media (prefers-color-scheme: dark){.policy-card{background:#111827;border-color:#1f2937;box-shadow:0 2px 6px -2px rgba(0,0,0,.6);} }
.policy-card h3{font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.75px;margin:0;color:#4338ca;}
@media (prefers-color-scheme: dark){.policy-card h3{color:#818cf8;}}
.policy-card ul{margin:0;padding-left:1.1rem;font-size:.75rem;color:#000;} 
@media (prefers-color-scheme: dark){.policy-card ul{color:#d1d5db;}}
.policy-card ul li+li{margin-top:.45rem;}

.policy-text{font-size:.78rem;line-height:1.55;color:#000;} 
@media (prefers-color-scheme: dark){.policy-text{color:#d1d5db;}}
.policy-text ul{padding-left:1.15rem;margin-bottom:0;}
.policy-text li+li{margin-top:.35rem;}

.policy-footer{text-align:center;font-size:.7rem;color:var(--policy-text-muted);margin-top:3rem;padding-top:1.25rem;border-top:1px solid var(--policy-border);} 
@media (prefers-color-scheme: dark){.policy-footer{color:#9ca3af;border-color:#1f2937;}}

/* Copy anchor toast */
.policy-toast{position:fixed;bottom:1rem;right:1rem;background:#111827;color:#f9fafb;font-size:.7rem;padding:.6rem .85rem;border-radius:.65rem;box-shadow:0 4px 12px -4px rgba(0,0,0,.5);opacity:0;transform:translateY(6px);pointer-events:none;transition:opacity .2s,transform .2s;z-index:2600;}
.policy-toast.show{opacity:1;transform:translateY(0);}

/* Back to top button */
.policy-back-top{position:fixed;right:1.15rem;bottom:1.15rem;background:#4f46e5;color:#fff;border:none;border-radius:999px;width:42px;height:42px;display:flex;align-items:center;justify-content:center;font-size:1.05rem;cursor:pointer;box-shadow:0 4px 14px -4px rgba(0,0,0,.4);opacity:0;pointer-events:none;transition:opacity .25s,transform .25s;transform:translateY(8px);z-index:2550;}
.policy-back-top:hover{background:#4338ca;}
.policy-back-top.show{opacity:1;pointer-events:auto;transform:translateY(0);} 
@media (prefers-color-scheme: dark){.policy-back-top{background:#6366f1;} .policy-back-top:hover{background:#4f46e5;}}

/* Subtle fade reveal */
.policy-section{--fade-delay:0;} .policy-section.fade-in{opacity:0;transform:translateY(12px);animation:fadeIn .55s forwards ease var(--fade-delay);} 
@keyframes fadeIn{to{opacity:1;transform:translateY(0);}}
/* ===== Forced Black Text Overrides (user insists all content text be #000) ===== */
/* These rules come last to override earlier dark-mode color adjustments. */
 .policy-toc-heading,
 .policy-toc a,
 .policy-card ul,
 .policy-card ul li,
 .policy-text,
 .policy-text p,
 .policy-text li,
 .policy-section h2,
 .policy-card h3,
 .policy-section p { color:#000 !important; }
/* If later you want dark mode legibility back, remove the !important lines above
  and rely on the original @media (prefers-color-scheme: dark) blocks. */

/* Light blue background for cards in Section 1 (Data We Collect) */
#data-we-collect .policy-card { 
  background:#e0f2fe; /* light blue */
  border-color:#bae6fd; 
  box-shadow:0 2px 4px rgba(0,0,0,.05);
}
#data-we-collect .policy-card h3 { 
  color:#0f3b66 !important; /* darker blue heading for contrast */
}
#data-we-collect .policy-card ul { color:#000 !important; }
</style>

<main id="main-content" class="flex-grow-1 mb-5">
  <div class="policy-progress-bar" id="policyProgress"></div>
  <div class="container py-4 py-lg-5">
    <!-- Mobile quick nav -->
    <div class="policy-mobile-nav">
      <a href="#data-we-collect">Data</a>
      <a href="#how-used">Use</a>
      <a href="#security">Security</a>
      <a href="#retention">Retention</a>
      <a href="#sharing">Sharing</a>
      <a href="#contact">Contact</a>
    </div>

    <div class="policy-wrapper">
      <!-- Sidebar TOC (desktop) -->
      <aside class="policy-sidebar d-none d-lg-block" aria-label="Policy sections">
        <div class="policy-toc-section mb-4">
          <div class="policy-toc-heading">Overview</div>
          <ul class="policy-toc">
            <li><a href="#data-we-collect">1. Data We Collect</a></li>
            <li><a href="#how-used">2. How Data Is Used</a></li>
            <li><a href="#lawful-basis">3. Lawful Basis</a></li>
            <li><a href="#user-controls">4. Controls & Choices</a></li>
          </ul>
        </div>
        <div class="policy-toc-section mb-4">
          <div class="policy-toc-heading">Safeguards</div>
          <ul class="policy-toc">
            <li><a href="#security">5. Security Measures</a></li>
            <li><a href="#retention">6. Retention</a></li>
            <li><a href="#sharing">7. Limited Sharing</a></li>
            <li><a href="#sensitive">8. Sensitive Info</a></li>
          </ul>
        </div>
        <div class="policy-toc-section">
          <div class="policy-toc-heading">Other</div>
          <ul class="policy-toc">
            <li><a href="#children">9. Minors</a></li>
            <li><a href="#breach">10. Incident Handling</a></li>
            <li><a href="#changes">11. Changes</a></li>
            <li><a href="#contact">12. Contact</a></li>
          </ul>
        </div>
      </aside>

      <!-- Main content -->
      <div>
        <header class="policy-hero">
          <h1 class="h3 mb-2">Security &amp; Privacy</h1>
          <p class="mb-0" style="font-size:.9rem;max-width:760px;line-height:1.5;font-weight:500;">
            How the PWD Employment &amp; Skills Portal handles user data, safeguards information, and supports inclusive &amp; responsible usage.
          </p>
          <div class="policy-badges">
            <span class="badge">Version 1.0</span>
            <span class="badge dark">Last update: <?php echo date('Y-m-d'); ?></span>
          </div>
        </header>

        <div class="policy-content-flow">
          <!-- 1 Data We Collect -->
          <section id="data-we-collect" class="policy-section section-anchor fade-in" style="--fade-delay:.05s;">
            <div class="policy-section-title">
              <div class="number">1</div>
              <h2>Data We Collect</h2>
              <button class="anchor-link-btn" data-anchor="#data-we-collect" aria-label="Copy link to section" title="Copy link">🔗</button>
            </div>
            <div class="policy-card-grid">
              <div class="policy-card">
                <h3>Account & Role</h3>
                <ul>
                  <li>Name, email, chosen role (job seeker, employer, admin)</li>
                  <li>Session identifiers (for login state)</li>
                  <li>Employer verification status (Approved / Pending / Rejected)</li>
                </ul>
              </div>
              <div class="policy-card">
                <h3>Profile & Application</h3>
                <ul>
                  <li>Job seeker: education, experience indicators, optional disability field</li>
                  <li>Uploaded documents: resume (PDF), optional video intro</li>
                  <li>Employer: company name, description, permit / registration no., optional website & phone</li>
                  <li>Uploaded employer verification document (PDF/JPG/PNG/WEBP)</li>
                </ul>
              </div>
              <div class="policy-card">
                <h3>Job Posting</h3>
                <ul>
                  <li>Title, description, skill requirements, work setup (WFH, part/full time)</li>
                  <li>Original location (region/city), salary range, education & experience requirements</li>
                  <li>Match‑criteria fields locked after applicants appear (integrity control)</li>
                </ul>
              </div>
              <div class="policy-card">
                <h3>Reports & Moderation</h3>
                <ul>
                  <li>User reports of suspicious or non‑compliant jobs (reason + optional details)</li>
                  <li>Administrative resolution status & timestamps</li>
                </ul>
              </div>
            </div>
          </section>

          <!-- 2 How Data Is Used -->
          <section id="how-used" class="policy-section section-anchor fade-in" style="--fade-delay:.1s;">
            <div class="policy-section-title">
              <div class="number">2</div>
              <h2>How Data Is Used</h2>
              <button class="anchor-link-btn" data-anchor="#how-used" aria-label="Copy link to section" title="Copy link">🔗</button>
            </div>
            <div class="policy-text">
              <ul>
                <li>Facilitate job discovery, filtering, and application workflows</li>
                <li>Verify employer legitimacy and reduce fraudulent postings</li>
                <li>Improve fairness via locked job criteria once there are applicants</li>
                <li>Support moderation (reviewing job reports & policy violations)</li>
                <li>Enhance future features (e.g. planned match scoring) in aggregated form</li>
              </ul>
            </div>
          </section>

          <!-- 3 Lawful Basis -->
          <section id="lawful-basis" class="policy-section section-anchor fade-in" style="--fade-delay:.15s;">
            <div class="policy-section-title">
              <div class="number">3</div>
              <h2>Lawful / Legitimate Basis</h2>
              <button class="anchor-link-btn" data-anchor="#lawful-basis" aria-label="Copy link to section" title="Copy link">🔗</button>
            </div>
            <p class="policy-text mb-0">
              Data is processed based on: (a) fulfilling the platform’s core service (connecting job seekers & employers), (b) legitimate interest in maintaining safety & integrity, and (c) user consent for optional uploads (resume, video intro, employer documents).
            </p>
          </section>

          <!-- 4 Controls -->
          <section id="user-controls" class="policy-section section-anchor fade-in" style="--fade-delay:.2s;">
            <div class="policy-section-title">
              <div class="number">4</div>
              <h2>Your Controls & Choices</h2>
              <button class="anchor-link-btn" data-anchor="#user-controls" aria-label="Copy link to section" title="Copy link">🔗</button>
            </div>
            <div class="policy-text">
              <ul>
                <li>Edit profile details (except locked application‑critical fields after applications exist)</li>
                <li>Choose whether to upload resume, video intro, or employer verification documents</li>
                <li>Report suspicious jobs (which notifies admins)</li>
                <li>Request removal of optional uploads by deleting or replacing them (future explicit delete UI can be added)</li>
              </ul>
            </div>
          </section>

          <!-- 5 Security Measures -->
          <section id="security" class="policy-section section-anchor fade-in" style="--fade-delay:.25s;">
            <div class="policy-section-title">
              <div class="number">5</div>
              <h2>Security Measures</h2>
              <button class="anchor-link-btn" data-anchor="#security" aria-label="Copy link to section" title="Copy link">🔗</button>
            </div>
            <div class="policy-text">
              <ul>
                <li>Role‑based access & server‑side permission checks</li>
                <li>Prepared statements to mitigate SQL injection (core DB operations)</li>
                <li>File uploads stored in segmented directories (resumes, videos, employers)</li>
                <li>Restricted accepted MIME types for documents / media</li>
                <li>Integrity logic: job requirement fields locked after applicants appear</li>
                <li>Planned improvements: CSRF tokens, stricter MIME validation, encryption at rest for sensitive docs</li>
              </ul>
            </div>
          </section>

          <!-- 6 Retention -->
          <section id="retention" class="policy-section section-anchor fade-in" style="--fade-delay:.3s;">
            <div class="policy-section-title">
              <div class="number">6</div>
              <h2>Retention</h2>
              <button class="anchor-link-btn" data-anchor="#retention" aria-label="Copy link to section" title="Copy link">🔗</button>
            </div>
            <p class="policy-text mb-0">Core account & job posting data persists while the account or posting remains active. Optional uploads (resume, video, employer document) remain until replaced or manually purged during housekeeping. Resolved reports may be archived for audit history.</p>
          </section>

          <!-- 7 Sharing -->
          <section id="sharing" class="policy-section section-anchor fade-in" style="--fade-delay:.35s;">
            <div class="policy-section-title">
              <div class="number">7</div>
              <h2>Limited Sharing</h2>
              <button class="anchor-link-btn" data-anchor="#sharing" aria-label="Copy link to section" title="Copy link">🔗</button>
            </div>
            <p class="policy-text mb-0">We do not sell user data. Employers see only applicant information necessary for hiring decisions. Admins can access data needed for moderation. Aggregated / anonymized metrics may be used to improve platform features.</p>
          </section>

            <!-- 8 Sensitive Info -->
          <section id="sensitive" class="policy-section section-anchor fade-in" style="--fade-delay:.4s;">
            <div class="policy-section-title">
              <div class="number">8</div>
              <h2>Sensitive Information</h2>
              <button class="anchor-link-btn" data-anchor="#sensitive" aria-label="Copy link to section" title="Copy link">🔗</button>
            </div>
            <p class="policy-text mb-0">Disability information (if provided) is user‑entered descriptive text; the platform does not classify medical conditions. Please avoid entering highly sensitive medical records. Do not upload IDs unless explicitly required for verification (current system does not request government IDs).</p>
          </section>

          <!-- 9 Minors -->
          <section id="children" class="policy-section section-anchor fade-in" style="--fade-delay:.45s;">
            <div class="policy-section-title">
              <div class="number">9</div>
              <h2>Minors</h2>
              <button class="anchor-link-btn" data-anchor="#children" aria-label="Copy link to section" title="Copy link">🔗</button>
            </div>
            <p class="policy-text mb-0">The platform targets professional employment; accounts from users under legal working age should not be created.</p>
          </section>

          <!-- 10 Incident Handling -->
          <section id="breach" class="policy-section section-anchor fade-in" style="--fade-delay:.5s;">
            <div class="policy-section-title">
              <div class="number">10</div>
              <h2>Incident Handling</h2>
              <button class="anchor-link-btn" data-anchor="#breach" aria-label="Copy link to section" title="Copy link">🔗</button>
            </div>
            <p class="policy-text mb-0">Suspected security or data exposure incidents are prioritized: isolate issue, restrict access, audit logs, and notify affected stakeholders where required. Future versions will include automated alerting hooks.</p>
          </section>

          <!-- 11 Changes -->
          <section id="changes" class="policy-section section-anchor fade-in" style="--fade-delay:.55s;">
            <div class="policy-section-title">
              <div class="number">11</div>
              <h2>Changes to This Page</h2>
              <button class="anchor-link-btn" data-anchor="#changes" aria-label="Copy link to section" title="Copy link">🔗</button>
            </div>
            <p class="policy-text mb-0">Material changes will update the version tag above. Continued use after changes indicates acceptance of the revised policy.</p>
          </section>

          <!-- 12 Contact -->
          <section id="contact" class="policy-section section-anchor fade-in" style="--fade-delay:.6s;">
            <div class="policy-section-title">
              <div class="number">12</div>
              <h2>Contact / Feedback</h2>
              <button class="anchor-link-btn" data-anchor="#contact" aria-label="Copy link to section" title="Copy link">🔗</button>
            </div>
            <p class="policy-text mb-2">For privacy questions or removal requests, use the Support channel (when logged in) or the general feedback mechanism planned in future releases.</p>
            <div class="policy-text">
              <ul>
                <li>Request incorrect data correction</li>
                <li>Flag potential misuse of uploaded content</li>
                <li>Suggest additional accessibility protections</li>
              </ul>
            </div>
          </section>

          <div class="policy-footer">&copy; <?php echo date('Y'); ?> PWD Employment &amp; Skills Portal</div>
        </div>
      </div>
    </div>
  </div>
</main>

<button class="policy-back-top" id="policyBackTop" aria-label="Back to top">↑</button>

<div class="policy-toast" id="policyToast" role="status" aria-live="polite">Link copied</div>

<script>
// ===== Scroll progress, active TOC highlighting & utilities =====
(function(){
  const progress = document.getElementById('policyProgress');
  const sections = Array.from(document.querySelectorAll('.policy-section'));
  const tocLinks = Array.from(document.querySelectorAll('.policy-sidebar a, .policy-mobile-nav a'));
  const toast = document.getElementById('policyToast');
  const backTop = document.getElementById('policyBackTop');

  function updateProgress(){
    const scrollTop = window.scrollY || document.documentElement.scrollTop;
    const docHeight = document.documentElement.scrollHeight - window.innerHeight;
    const pct = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
    progress.style.width = pct + '%';
    // toggle back-to-top
    if(scrollTop > window.innerHeight * 0.6){
      backTop.classList.add('show');
    } else {
      backTop.classList.remove('show');
    }
  }
  window.addEventListener('scroll', updateProgress, {passive:true});
  updateProgress();

  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if(entry.isIntersecting){
        const id = entry.target.id;
        tocLinks.forEach(a => {
          const match = a.getAttribute('href') === '#' + id;
            a.classList.toggle('active', match);
            if(match){
              a.setAttribute('aria-current','true');
            } else {
              a.removeAttribute('aria-current');
            }
        });
      }
    });
  },{rootMargin:'-45% 0px -45% 0px', threshold: [0,1]});
  sections.forEach(sec => observer.observe(sec));

  // Anchor copy buttons
  document.querySelectorAll('.anchor-link-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const anchor = btn.getAttribute('data-anchor');
      const url = window.location.origin + window.location.pathname + anchor;
      try { navigator.clipboard.writeText(url); } catch(e) {}
      toast.classList.add('show');
      clearTimeout(window.__policyToastTimer);
      window.__policyToastTimer = setTimeout(()=> toast.classList.remove('show'), 1800);
    });
  });

  // Back to top
  backTop.addEventListener('click', () => {
    window.scrollTo({top:0,behavior:'smooth'});
  });
})();
</script>

<?php include '../includes/footer.php'; ?>