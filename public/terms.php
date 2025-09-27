<?php
require_once '../config/config.php';
require_once '../classes/Helpers.php';
include '../includes/header.php';
include '../includes/nav.php';
?>
<style>
/* ===== Terms & Conditions Redesign (aligned to policy page style) ===== */
:root { --terms-accent:#2563eb; --terms-accent-rgb:37 99 235; --terms-gradient:linear-gradient(135deg,#0d6efd 0%,#2563eb 55%,#6366f1 100%); --terms-border:#e2e8f0; --terms-bg-soft:#f5f7fb; --terms-radius-lg:1rem; --terms-radius-md:.85rem; --terms-radius-sm:.6rem; }
@media (prefers-color-scheme: dark){ :root { --terms-border:#1f2937; --terms-bg-soft:#111827; } }

.terms-progress {position:fixed;left:0;top:0;height:2px;width:0;background:#2563eb;z-index:2600;transition:width .15s ease;box-shadow:0 0 0 1px rgba(255,255,255,.15),0 1px 3px rgba(0,0,0,.15);} 

.terms-wrapper {display:grid;gap:2rem;grid-template-columns:1fr;} 
@media (min-width: 992px){ .terms-wrapper {grid-template-columns:250px 1fr;} }

.terms-sidebar {position:sticky;top:90px;align-self:start;max-height:calc(100vh - 100px);overflow:auto;padding-right:4px;} 
.terms-sidebar::-webkit-scrollbar{width:6px;} .terms-sidebar::-webkit-scrollbar-track{background:transparent;} .terms-sidebar::-webkit-scrollbar-thumb{background:rgba(var(--terms-accent-rgb)/.35);border-radius:4px;}

.terms-toc-heading{font-size:.68rem;font-weight:600;text-transform:uppercase;letter-spacing:.75px;margin-bottom:.35rem;color:#000;}
.terms-toc{list-style:none;margin:0;padding:0;}
.terms-toc li+li{margin-top:.12rem;}
.terms-toc a{display:block;padding:.5rem .75rem .5rem 1rem;border-radius:var(--terms-radius-sm);font-size:.78rem;font-weight:500;color:#000;text-decoration:none;line-height:1.1;position:relative;transition:background .15s,color .15s;}
.terms-toc a::before{content:"";position:absolute;left:.4rem;top:50%;translate:0 -50%;width:4px;height:55%;border-radius:3px;background:transparent;transition:background .18s,height .18s;}
.terms-toc a:hover{background:#eff6ff;color:#1d4ed8;}
.terms-toc a.active{color:#111827;font-weight:600;}
.terms-toc a.active::before{background:var(--terms-accent);height:70%;}
.terms-toc a:focus-visible{outline:2px solid #3b82f6;outline-offset:2px;}
@media (prefers-color-scheme: dark){
  .terms-toc a{color:#9ca3af;}
  .terms-toc a:hover{background:#1f2937;color:#fff;}
  .terms-toc a.active{color:#fff;}
  .terms-toc a.active::before{background:#3b82f6;}
}

/* Mobile nav */
.terms-mobile-nav{border:1px solid var(--terms-border);border-radius:.75rem;background:#fff;display:flex;flex-wrap:wrap;gap:.5rem;padding:.75rem 1rem;margin-bottom:1.25rem;}
@media (min-width:992px){.terms-mobile-nav{display:none;}}
.terms-mobile-nav a{flex:1 1 calc(50% - .5rem);font-size:.68rem;text-decoration:none;font-weight:600;padding:.6rem .5rem;border:1px solid var(--terms-border);border-radius:.6rem;text-align:center;color:#1e3a8a;background:#f0f7ff;transition:all .15s;}
.terms-mobile-nav a:hover{background:#eff6ff;color:#1d4ed8;}

.terms-hero{background:var(--terms-gradient);color:#fff;border-radius:var(--terms-radius-lg);padding:2.4rem 2rem 2.2rem;position:relative;overflow:hidden;box-shadow:0 10px 25px -10px rgba(var(--terms-accent-rgb)/.55),0 4px 12px -4px rgba(0,0,0,.25);} 
.terms-hero::before,.terms-hero::after{content:"";position:absolute;border-radius:999px;filter:blur(42px);opacity:.55;mix-blend-mode:overlay;}
.terms-hero::before{width:300px;height:300px;background:radial-gradient(circle at 30% 40%,#fff,transparent 70%);left:-70px;top:-80px;}
.terms-hero::after{width:360px;height:360px;background:radial-gradient(circle at 70% 55%,#fff,transparent 70%);right:-80px;bottom:-120px;}
.terms-hero h1{font-weight:700;letter-spacing:-.5px;}
.terms-badges{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1.1rem;}
.terms-badges .badge{font-size:.63rem;letter-spacing:.75px;font-weight:600;background:rgba(255,255,255,.9);color:#1e3a8a;padding:.45rem .6rem;border-radius:.5rem;}
.terms-badges .badge.dark{background:rgba(31,41,55,.85);color:#f9fafb;}

.terms-content-flow{margin-top:2.1rem;}
.terms-section{margin-bottom:2.75rem;position:relative;padding-top:.25rem;}
.terms-section:not(:first-of-type)::before{content:"";position:absolute;left:0;right:0;top:-1.05rem;height:1px;background:linear-gradient(90deg,rgba(0,0,0,.06),rgba(0,0,0,0));}
@media (prefers-color-scheme: dark){.terms-section:not(:first-of-type)::before{background:linear-gradient(90deg,rgba(255,255,255,.07),rgba(255,255,255,0));}}
.section-anchor{scroll-margin-top:90px;}

.terms-section-title{display:flex;align-items:center;gap:.65rem;margin-bottom:.85rem;}
.terms-section-title .number{flex-shrink:0;width:30px;height:30px;border-radius:9px;background:#2563eb;color:#fff;font-weight:600;display:flex;align-items:center;justify-content:center;font-size:.72rem;box-shadow:0 2px 4px rgba(0,0,0,.15);} 
@media (prefers-color-scheme: dark){.terms-section-title .number{background:#3b82f6;}}
.terms-section:nth-of-type(n+4) .terms-section-title .number{background:#3b82f61a;color:#1d4ed8;box-shadow:none;}
@media (prefers-color-scheme: dark){.terms-section:nth-of-type(n+4) .terms-section-title .number{background:#3b82f61f;color:#60a5fa;}}

.terms-section h2{font-size:1.0rem;font-weight:600;margin:0;}
.terms-anchor-btn{opacity:0;transform:translateY(2px);transition:opacity .15s;display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:6px;background:transparent;color:#2563eb;border:1px solid transparent;}
.terms-section:hover .terms-anchor-btn{opacity:1;}
.terms-anchor-btn:hover{background:#eff6ff;border-color:#bfdbfe;}
@media (prefers-color-scheme: dark){.terms-anchor-btn:hover{background:#1f2937;border-color:#374151;color:#60a5fa;}}
.terms-anchor-btn:focus-visible{outline:2px solid #3b82f6;outline-offset:2px;opacity:1;}

.terms-text{font-size:.78rem;line-height:1.55;color:#000;}
@media (prefers-color-scheme: dark){.terms-text{color:#d1d5db;}}
.terms-text ul{padding-left:1.1rem;margin-bottom:0;}
.terms-text li+li{margin-top:.35rem;}

.terms-footer{text-align:center;font-size:.68rem;color:#6b7280;margin-top:3rem;padding-top:1.15rem;border-top:1px solid var(--terms-border);} 
@media (prefers-color-scheme: dark){.terms-footer{color:#9ca3af;border-color:#1f2937;}}

/* Copy anchor toast */
.terms-toast{position:fixed;bottom:1rem;right:1rem;background:#111827;color:#f9fafb;font-size:.68rem;padding:.55rem .8rem;border-radius:.6rem;box-shadow:0 4px 12px -4px rgba(0,0,0,.5);opacity:0;transform:translateY(6px);pointer-events:none;transition:opacity .2s,transform .2s;z-index:2650;}
.terms-toast.show{opacity:1;transform:translateY(0);}

/* Back to top */
.terms-back-top{position:fixed;right:1.15rem;bottom:1.15rem;background:#2563eb;color:#fff;border:none;border-radius:999px;width:40px;height:40px;display:flex;align-items:center;justify-content:center;font-size:1rem;cursor:pointer;box-shadow:0 4px 14px -4px rgba(0,0,0,.4);opacity:0;pointer-events:none;transition:opacity .25s,transform .25s;transform:translateY(8px);z-index:2620;}
.terms-back-top:hover{background:#1d4ed8;}
.terms-back-top.show{opacity:1;pointer-events:auto;transform:translateY(0);} 
@media (prefers-color-scheme: dark){.terms-back-top{background:#3b82f6;} .terms-back-top:hover{background:#2563eb;}}

/* Fade-in */
.terms-section{--fade-delay:0;} .terms-section.fade-in{opacity:0;transform:translateY(12px);animation:fadeInTerms .55s forwards ease var(--fade-delay);} 
@keyframes fadeInTerms{to{opacity:1;transform:translateY(0);}}

/* Force black text overrides (light mode emphasis) */
.terms-toc-heading, .terms-toc a, .terms-text, .terms-text p, .terms-text li, .terms-section h2 {color:#000 !important;}
</style>

<main id="main-content" class="flex-grow-1 mb-5">
  <div class="terms-progress" id="termsProgress"></div>
  <div class="container py-4 py-lg-5">
    <!-- Mobile nav -->
    <div class="terms-mobile-nav">
      <a href="#acceptance">Accept</a>
      <a href="#roles">Roles</a>
      <a href="#accounts">Security</a>
      <a href="#job-posting">Posting</a>
      <a href="#prohibited">Prohibit</a>
      <a href="#changes">Changes</a>
    </div>

    <div class="terms-wrapper">
      <aside class="terms-sidebar d-none d-lg-block" aria-label="Terms sections">
        <div class="mb-4">
          <div class="terms-toc-heading">Basics</div>
          <ul class="terms-toc">
            <li><a href="#acceptance">1. Acceptance</a></li>
            <li><a href="#roles">2. User Roles</a></li>
            <li><a href="#eligibility">3. Eligibility</a></li>
            <li><a href="#accounts">4. Accounts &amp; Security</a></li>
            <li><a href="#employer-verification">5. Verification</a></li>
          </ul>
        </div>
        <div class="mb-4">
          <div class="terms-toc-heading">Usage</div>
          <ul class="terms-toc">
            <li><a href="#job-posting">6. Job Posting</a></li>
            <li><a href="#applications">7. Applications</a></li>
            <li><a href="#reports">8. Moderation</a></li>
            <li><a href="#prohibited">9. Prohibited</a></li>
            <li><a href="#intellectual-property">10. IP</a></li>
          </ul>
        </div>
        <div>
          <div class="terms-toc-heading">Legal</div>
          <ul class="terms-toc">
            <li><a href="#license">11. License</a></li>
            <li><a href="#disclaimers">12. Disclaimer</a></li>
            <li><a href="#liability">13. Liability</a></li>
            <li><a href="#termination">14. Termination</a></li>
            <li><a href="#changes">15. Changes / Contact</a></li>
          </ul>
        </div>
      </aside>

      <div>
        <header class="terms-hero">
          <h1 class="h3 mb-2">Terms &amp; Conditions</h1>
          <p class="mb-0" style="font-size:.9rem;max-width:760px;line-height:1.5;font-weight:500;">These Terms govern access & use of the PWD Employment & Skills Portal. Creating an account or continuing use means you agree to them.</p>
          <div class="terms-badges">
            <span class="badge">Version 1.0</span>
            <span class="badge dark">Last update: <?php echo date('Y-m-d'); ?></span>
          </div>
        </header>

        <div class="terms-content-flow">
          <!-- Sections transformed to numbered format with anchor buttons -->
          <section id="acceptance" class="terms-section section-anchor fade-in" style="--fade-delay:.05s;">
            <div class="terms-section-title"><div class="number">1</div><h2>Acceptance</h2><button class="terms-anchor-btn" data-anchor="#acceptance" aria-label="Copy link" title="Copy link">🔗</button></div>
            <p class="terms-text mb-0">By accessing the Platform you confirm that you have read, understood, and agree to these Terms and linked policies (including Security & Privacy). If you do not agree, discontinue use immediately.</p>
          </section>
          <section id="roles" class="terms-section section-anchor fade-in" style="--fade-delay:.1s;">
            <div class="terms-section-title"><div class="number">2</div><h2>User Roles</h2><button class="terms-anchor-btn" data-anchor="#roles" aria-label="Copy link" title="Copy link">🔗</button></div>
            <p class="terms-text mb-0">Users may act as: <strong>Job Seeker</strong> (search/apply), <strong>Employer</strong> (post/manage jobs), or <strong>Admin</strong> (moderation & integrity). Each has distinct server-side permissions.</p>
          </section>
          <section id="eligibility" class="terms-section section-anchor fade-in" style="--fade-delay:.15s;">
            <div class="terms-section-title"><div class="number">3</div><h2>Eligibility</h2><button class="terms-anchor-btn" data-anchor="#eligibility" aria-label="Copy link" title="Copy link">🔗</button></div>
            <p class="terms-text mb-0">You must be legally permitted for employment-related interaction in your jurisdiction. Impersonation or spam/fraud accounts will be removed.</p>
          </section>
            <section id="accounts" class="terms-section section-anchor fade-in" style="--fade-delay:.2s;">
            <div class="terms-section-title"><div class="number">4</div><h2>Accounts & Security</h2><button class="terms-anchor-btn" data-anchor="#accounts" aria-label="Copy link" title="Copy link">🔗</button></div>
            <div class="terms-text"><ul>
              <li>Keep credentials confidential; you are responsible for activity under your session.</li>
              <li>We may suspend or restrict accounts suspected of abuse or security violations.</li>
              <li>Optional uploads (resume, video) are user‑initiated; ensure you have the right to share content.</li>
            </ul></div>
          </section>
          <section id="employer-verification" class="terms-section section-anchor fade-in" style="--fade-delay:.25s;">
            <div class="terms-section-title"><div class="number">5</div><h2>Employer Verification</h2><button class="terms-anchor-btn" data-anchor="#employer-verification" aria-label="Copy link" title="Copy link">🔗</button></div>
            <p class="terms-text mb-0">Employers may be asked to upload a business permit / registration document. Status (Pending, Approved, Rejected) influences ability to post or promote jobs. False documents may trigger removal.</p>
          </section>
          <section id="job-posting" class="terms-section section-anchor fade-in" style="--fade-delay:.3s;">
            <div class="terms-section-title"><div class="number">6</div><h2>Job Posting Rules</h2><button class="terms-anchor-btn" data-anchor="#job-posting" aria-label="Copy link" title="Copy link">🔗</button></div>
            <div class="terms-text"><ul>
              <li>Post only legitimate roles with accurate details & location/remote status.</li>
              <li>No discriminatory, offensive, or exploitative content.</li>
              <li>Do not request unnecessary sensitive personal data in descriptions.</li>
              <li>Once applicants exist, certain core requirement fields lock to prevent shifting criteria.</li>
            </ul></div>
          </section>
          <section id="applications" class="terms-section section-anchor fade-in" style="--fade-delay:.35s;">
            <div class="terms-section-title"><div class="number">7</div><h2>Applications & Fairness</h2><button class="terms-anchor-btn" data-anchor="#applications" aria-label="Copy link" title="Copy link">🔗</button></div>
            <p class="terms-text mb-0">Applicants are responsible for accuracy. Employers agree to evaluate in good faith. The Platform does not guarantee hiring. Locked criteria preserve fairness.</p>
          </section>
          <section id="reports" class="terms-section section-anchor fade-in" style="--fade-delay:.4s;">
            <div class="terms-section-title"><div class="number">8</div><h2>Reporting & Moderation</h2><button class="terms-anchor-btn" data-anchor="#reports" aria-label="Copy link" title="Copy link">🔗</button></div>
            <p class="terms-text mb-0">Job seekers may flag suspicious or non‑compliant postings. Admins may edit, remove, or ban to protect integrity. Malicious false reporting is a violation.</p>
          </section>
          <section id="prohibited" class="terms-section section-anchor fade-in" style="--fade-delay:.45s;">
            <div class="terms-section-title"><div class="number">9</div><h2>Prohibited Conduct</h2><button class="terms-anchor-btn" data-anchor="#prohibited" aria-label="Copy link" title="Copy link">🔗</button></div>
            <div class="terms-text"><ul>
              <li>Uploading malware or exploiting vulnerabilities.</li>
              <li>Scraping bulk profile or job data without permission.</li>
              <li>Harassment, discriminatory remarks, or hate speech.</li>
              <li>Misrepresenting affiliation or forging verification docs.</li>
              <li>Circumventing fairness (editing locked fields by hacks).</li>
            </ul></div>
          </section>
          <section id="intellectual-property" class="terms-section section-anchor fade-in" style="--fade-delay:.5s;">
            <div class="terms-section-title"><div class="number">10</div><h2>Intellectual Property</h2><button class="terms-anchor-btn" data-anchor="#intellectual-property" aria-label="Copy link" title="Copy link">🔗</button></div>
            <p class="terms-text mb-0">Platform code, design, and structural components remain property of the Portal / maintainers. User content remains owned by the user with a limited license for service functionality.</p>
          </section>
          <section id="license" class="terms-section section-anchor fade-in" style="--fade-delay:.55s;">
            <div class="terms-section-title"><div class="number">11</div><h2>License to Platform</h2><button class="terms-anchor-btn" data-anchor="#license" aria-label="Copy link" title="Copy link">🔗</button></div>
            <p class="terms-text mb-0">Subject to compliance, you receive a non‑exclusive, revocable license to access & use the Platform for lawful job search or hiring purposes.</p>
          </section>
          <section id="disclaimers" class="terms-section section-anchor fade-in" style="--fade-delay:.6s;">
            <div class="terms-section-title"><div class="number">12</div><h2>Disclaimers</h2><button class="terms-anchor-btn" data-anchor="#disclaimers" aria-label="Copy link" title="Copy link">🔗</button></div>
            <p class="terms-text mb-0">Provided “as is” without warranties of completeness, accuracy, or uninterrupted availability. Not all postings/profiles are vetted in real time.</p>
          </section>
          <section id="liability" class="terms-section section-anchor fade-in" style="--fade-delay:.65s;">
            <div class="terms-section-title"><div class="number">13</div><h2>Limitation of Liability</h2><button class="terms-anchor-btn" data-anchor="#liability" aria-label="Copy link" title="Copy link">🔗</button></div>
            <p class="terms-text mb-0">To the maximum extent allowed by law, maintainers are not liable for lost opportunities or indirect / consequential damages from use or inability to access the service.</p>
          </section>
          <section id="termination" class="terms-section section-anchor fade-in" style="--fade-delay:.7s;">
            <div class="terms-section-title"><div class="number">14</div><h2>Termination</h2><button class="terms-anchor-btn" data-anchor="#termination" aria-label="Copy link" title="Copy link">🔗</button></div>
            <p class="terms-text mb-0">We may suspend or terminate access for policy violations, security risks, illegal activity, or misuse. Some data may persist in backups or compliance logs.</p>
          </section>
          <section id="changes" class="terms-section section-anchor fade-in" style="--fade-delay:.75s;">
            <div class="terms-section-title"><div class="number">15</div><h2>Changes / Contact</h2><button class="terms-anchor-btn" data-anchor="#changes" aria-label="Copy link" title="Copy link">🔗</button></div>
            <p class="terms-text mb-2">We may update these Terms to reflect feature or legal changes. Continued use after updates constitutes acceptance.</p>
            <p class="terms-text mb-0">Questions or concerns: use in‑platform Support (when logged in) or future public contact options.</p>
          </section>
          <div class="terms-footer">&copy; <?php echo date('Y'); ?> PWD Employment &amp; Skills Portal</div>
        </div>
      </div>
    </div>
  </div>
</main>

<button class="terms-back-top" id="termsBackTop" aria-label="Back to top">↑</button>
<div class="terms-toast" id="termsToast" role="status" aria-live="polite">Link copied</div>

<script>
(function(){
  const progress=document.getElementById('termsProgress');
  const sections=[...document.querySelectorAll('.terms-section')];
  const tocLinks=[...document.querySelectorAll('.terms-sidebar a, .terms-mobile-nav a')];
  const toast=document.getElementById('termsToast');
  const backTop=document.getElementById('termsBackTop');

  function updateProgress(){
    const scrollTop=window.scrollY||document.documentElement.scrollTop;
    const docHeight=document.documentElement.scrollHeight-window.innerHeight;
    const pct=docHeight>0?(scrollTop/docHeight)*100:0;progress.style.width=pct+'%';
    if(scrollTop>window.innerHeight*0.6){backTop.classList.add('show');} else {backTop.classList.remove('show');}
  }
  window.addEventListener('scroll',updateProgress,{passive:true});
  updateProgress();

  const observer=new IntersectionObserver(entries=>{
    entries.forEach(entry=>{ if(entry.isIntersecting){ const id=entry.target.id; tocLinks.forEach(a=>{ const match=a.getAttribute('href')==='#'+id; a.classList.toggle('active',match); if(match){a.setAttribute('aria-current','true');} else {a.removeAttribute('aria-current');} }); } });
  },{rootMargin:'-45% 0px -45% 0px',threshold:[0,1]});
  sections.forEach(sec=>observer.observe(sec));

  document.querySelectorAll('.terms-anchor-btn').forEach(btn=>{
    btn.addEventListener('click',()=>{ const anchor=btn.getAttribute('data-anchor'); const url=window.location.origin+window.location.pathname+anchor; try{navigator.clipboard.writeText(url);}catch(e){}; toast.classList.add('show'); clearTimeout(window.__termsToastTimer); window.__termsToastTimer=setTimeout(()=>toast.classList.remove('show'),1800); });
  });

  backTop.addEventListener('click',()=>{window.scrollTo({top:0,behavior:'smooth'});});
})();
</script>

<?php include '../includes/footer.php'; ?>