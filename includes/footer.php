    </main><!-- /main content area -->

    <?php if (($_SESSION['role'] ?? '') !== 'admin'): ?>
        <style>
            /* Footer CTA + Footer styles scoped to footer to avoid conflicts */
            .footer-cta-wrap {
                position: relative;
                z-index: 2;
            }

            .footer-cta {
                background: linear-gradient(135deg, var(--primary-blue), var(--primary-purple));
                color: #fff;
                border-radius: 1rem;
                padding: 1.25rem 1.25rem 1.25rem 1.35rem;
                box-shadow: 0 18px 36px -18px rgba(2, 6, 23, .28), 0 10px 26px -18px rgba(2, 6, 23, .18);
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                border: 1px solid rgba(255, 255, 255, .15);
            }

            .footer-cta h3 {
                margin: 0;
                font-weight: 800;
                letter-spacing: .2px;
            }

            .footer-cta p {
                margin: .25rem 0 0;
                opacity: .9;
            }

            .footer-cta .cta-text {
                display: grid;
                gap: .15rem;
            }

            .footer-cta .cta-actions {
                display: flex;
                gap: .6rem;
                align-items: center;
            }

            .footer-cta .btn-cta {
                background-color: #111827;
                /* near-black from palette */
                color: #fff;
                border: 0;
                border-radius: .65rem;
                padding: .7rem 1rem;
                font-weight: 700;
                box-shadow: 0 10px 22px -14px rgba(0, 0, 0, .35);
            }

            .footer-cta .btn-cta:focus {
                outline: none;
                box-shadow: 0 0 0 .2rem rgba(var(--accent-yellow-rgb), .4);
            }

            .footer-cta .btn-cta:hover {
                filter: brightness(1.05);
            }

            .footer-cta .btn-cta-outline {
                background: transparent;
                color: #fff;
                border: 2px solid rgba(255, 255, 255, .8);
                border-radius: .65rem;
                padding: .66rem 1rem;
                font-weight: 700;
            }

            .footer-cta .btn-cta-outline:hover {
                background: rgba(255, 255, 255, .12);
            }

            footer.footer-themed {
                background: linear-gradient(180deg, rgba(var(--primary-blue-rgb), 1) 0%, rgba(var(--primary-blue-rgb), .96) 100%);
                color: #fff;
                padding-bottom: 2.5rem !important;
                /* override py-5 bottom padding */
            }

            footer.footer-themed .footer-link {
                color: rgba(255, 255, 255, .85);
                text-decoration: none;
            }

            footer.footer-themed .footer-link:hover,
            footer.footer-themed .footer-link:focus {
                color: #fff;
                text-decoration: underline;
            }

            footer.footer-themed .footer-title {
                font-weight: 700;
                letter-spacing: .2px;
                color: #fff;
            }

            footer.footer-themed .footer-sub {
                color: rgba(255, 255, 255, .75);
            }

            /* Justify brand description text and add spacing toward next column */
            footer.footer-themed .footer-brand .footer-sub {
                text-align: justify;
                text-justify: inter-word;
            }

            /* spacing handled via grid offset on column 2 at md+ */

            footer.footer-themed .social a {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 36px;
                height: 36px;
                border-radius: 999px;
                background: rgba(var(--secondary-teal-rgb), .18);
                color: #fff;
            }

            footer.footer-themed .social a:hover {
                background: rgba(var(--secondary-teal-rgb), .3);
            }

            footer.footer-themed .divider {
                height: 1px;
                background: rgba(255, 255, 255, .14);
                /* Increased vertical spacing between sections */
                margin: 5rem 0 2.5rem;
                border-radius: 1rem;
            }

            @media (max-width: 575.98px) {
                .footer-cta {
                    flex-direction: column;
                    align-items: stretch;
                }
            }
        </style>

        <footer class="footer footer-themed py-5 mt-4 mt-md-5">
            <div class="container">
                <div class="row g-4 align-items-start">
                    <div class="col-12 col-md-4 footer-brand">
                        <div class="mb-2 footer-title">PWD Employment &amp; Skills Portal</div>
                        <div class="footer-sub small">&copy; <?php echo date('Y'); ?>. <strong>Inclusive opportunities for PWD professionals.</strong><br>Connecting skilled individuals with inclusive employers to build a workforce that values diversity, accessibility, and equal opportunity.</div>
                    </div>
                    <div class="col-6 col-md-2">
                        <div class="footer-title small mb-2">For Employers</div>
                        <ul class="list-unstyled small mb-0">
                            <?php if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'employer'): ?>
                                <li class="mb-1"><a class="footer-link" href="jobs_create.php">Post a job</a></li>
                            <?php else: ?>
                                <li class="mb-1"><a class="footer-link" href="employer_dashboard.php">Employer portal</a></li>
                            <?php endif; ?>
                            <li class="mb-1"><a class="footer-link" href="employer_candidates.php">Find candidates</a></li>
                        </ul>
                    </div>
                    <div class="col-6 col-md-2 offset-md-1">
                        <div class="footer-title small mb-2">Company</div>
                        <ul class="list-unstyled small mb-0">
                            <li class="mb-1"><a class="footer-link" href="about.php">About us</a></li>
                            <li class="mb-1"><a class="footer-link" href="support_contact.php">Contact</a></li>
                        </ul>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="footer-title small mb-2">Further information</div>
                        <ul class="list-unstyled small mb-0">
                            <li class="mb-1"><a class="footer-link" href="terms.php">Terms &amp; Conditions</a></li>
                            <li class="mb-1"><a class="footer-link" href="security_privacy.php">Security &amp; Privacy</a></li>
                        </ul>
                    </div>

                </div>
                <div class="divider"></div>
                <div class="d-flex flex-wrap align-items-center justify-content-between small">
                    <div>
                        <span class="me-2">&copy; <?php echo date('Y'); ?> PWD Employment &amp; Skills Portal</span>
                        <span class="text-white-50">All rights reserved.</span>
                    </div>
                    <div class="d-flex gap-3">
                        <?php if (!empty($_SESSION['user_id'])): ?>
                            <a class="footer-link d-inline-flex align-items-center" href="support_contact.php">
                                <i class="bi bi-life-preserver me-1"></i>Support
                            </a>
                        <?php endif; ?>
                        <a class="footer-link" href="security_privacy.php">Security &amp; Privacy</a>
                        <a class="footer-link" href="terms.php">Terms &amp; Conditions</a>
                    </div>
                </div>
            </div>
        </footer>
    <?php endif; ?>

    <!-- Global confirmation modal (kept) -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmModalLabel">Please confirm</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" id="confirmModalMessage">Are you sure?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="confirmModalNo">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmModalYes">Yes</button>
                </div>
            </div>
        </div>
    </div>

    </div><!-- /page-wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php $BASE = rtrim(BASE_URL, '/'); ?>
    <script src="<?php echo $BASE; ?>/assets/theme.js?v=20250926a"></script>
    <script>
        /* Auto-dismiss flash alerts */
        document.querySelectorAll('.alert.auto-dismiss').forEach(el => {
            setTimeout(() => {
                try {
                    bootstrap.Alert.getOrCreateInstance(el).close();
                } catch (e) {}
            }, 4000);
        });

        /* Global confirmation handler */
        (function() {
            const modalEl = document.getElementById('confirmModal');
            if (!modalEl) return;
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            let targetHref = null;

            document.addEventListener('click', function(e) {
                const link = e.target.closest('a[data-confirm]');
                if (!link) return;
                e.preventDefault();
                targetHref = link.getAttribute('href');

                modalEl.querySelector('#confirmModalLabel').textContent =
                    link.getAttribute('data-confirm-title') || 'Confirm';
                modalEl.querySelector('#confirmModalMessage').textContent =
                    link.getAttribute('data-confirm') || 'Are you sure?';
                modalEl.querySelector('#confirmModalYes').textContent =
                    link.getAttribute('data-confirm-yes') || 'Yes';
                modalEl.querySelector('#confirmModalNo').textContent =
                    link.getAttribute('data-confirm-no') || 'Cancel';
                modal.show();
            });

            modalEl.querySelector('#confirmModalYes').addEventListener('click', () => {
                if (targetHref) window.location.href = targetHref;
            });
        })();
    </script>
    </body>

    </html>