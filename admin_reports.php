<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Helpers.php';
require_once 'classes/Report.php';
require_once 'classes/Job.php';

Helpers::requireLogin();
if (($_SESSION['role'] ?? '') !== 'admin') Helpers::redirect('index.php');

// Actions
if (isset($_GET['action'], $_GET['report_id'])) {
    $action   = $_GET['action'];
    $report_id = $_GET['report_id'];
    $job_id   = $_GET['job_id'] ?? '';

    if ($action === 'resolve') {
        Report::resolve($report_id);
        Helpers::flash('msg', 'Report resolved.');
    } elseif ($action === 'delete_job' && $job_id) {
        if (Job::adminDelete($job_id)) {
            // Optionally mark the report resolved after deletion
            Report::resolve($report_id);
            Helpers::flash('msg', 'Job deleted and report resolved.');
        } else {
            Helpers::flash('msg', 'Failed to delete job.');
        }
    }
    Helpers::redirect('admin_reports.php');
}

$open = Report::listOpen();

// Build reason counts
$reasonCounts = ['total' => count($open)];
foreach ($open as $__r) {
    $rs = trim($__r['reason'] ?? 'Other');
    if ($rs === '') $rs = 'Other';
    $reasonCounts[$rs] = ($reasonCounts[$rs] ?? 0) + 1;
}
// Sort reasons by count desc (excluding total)
$sortedReasons = [];
foreach ($reasonCounts as $k => $v) {
    if ($k === 'total') continue;
    $sortedReasons[] = ['reason' => $k, 'count' => $v];
}
usort($sortedReasons, function ($a, $b) {
    return $b['count'] <=> $a['count'];
});

include 'includes/header.php';
?>
<div class="admin-layout">
    <?php include 'includes/admin_sidebar.php'; ?>
    <div class="admin-main">
        <style>
            .reports-topbar {
                display: flex;
                flex-direction: column;
                gap: .9rem;
                margin-bottom: 1.15rem
            }

            .reason-chips {
                display: flex;
                flex-wrap: wrap;
                gap: .55rem
            }

            .reason-chip {
                --bd: rgba(255, 255, 255, .08);
                display: inline-flex;
                align-items: center;
                gap: .45rem;
                font-size: .62rem;
                font-weight: 600;
                letter-spacing: .07em;
                text-transform: uppercase;
                padding: .5rem .7rem;
                border: 1px solid var(--bd);
                background: #162335;
                color: #c3d2e6;
                border-radius: 8px;
                cursor: pointer;
                transition: .25s
            }

            .reason-chip:hover {
                background: #1f3856;
                color: #fff
            }

            .reason-chip.active {
                background: linear-gradient(135deg, #1f4d89, #163657);
                color: #fff;
                border-color: #3e74c4;
                box-shadow: 0 4px 12px -6px rgba(0, 0, 0, .6)
            }

            .reason-chip .count {
                background: rgba(255, 255, 255, .1);
                padding: .2rem .5rem;
                border-radius: 5px;
                font-size: .58rem;
                font-weight: 600
            }

            .filters-bar {
                display: flex;
                flex-wrap: wrap;
                gap: .6rem;
                align-items: center
            }

            .filters-bar .search-box {
                flex: 1 1 260px;
                position: relative
            }

            .filters-bar .search-box input {
                background: #101b2b;
                border: 1px solid #233246;
                color: #dbe6f5;
                padding: .6rem .75rem .6rem 2rem;
                font-size: .78rem;
                border-radius: 9px;
                width: 100%
            }

            .filters-bar .search-box i {
                position: absolute;
                left: .65rem;
                top: 50%;
                transform: translateY(-50%);
                color: #6c7c91;
                font-size: .9rem
            }

            .filters-bar select {
                background: #101b2b;
                border: 1px solid #233246;
                color: #dbe6f5;
                font-size: .72rem;
                padding: .55rem .65rem;
                border-radius: 8px
            }

            .filters-bar button.reset-btn {
                background: #182739;
                border: 1px solid #23384f;
                color: #9fb4cc;
                font-size: .7rem;
                padding: .55rem .75rem;
                border-radius: 8px;
                font-weight: 600;
                letter-spacing: .5px
            }

            .filters-bar button.reset-btn:hover {
                background: #213249;
                color: #fff
            }

            .reports-wrapper {
                position: relative;
                border: 1px solid rgba(255, 255, 255, .06);
                background: #0f1827;
                border-radius: 16px;
                overflow: hidden;
                box-shadow: 0 6px 22px -12px rgba(0, 0, 0, .65)
            }

            table.reports-table {
                margin: 0;
                border-collapse: separate;
                border-spacing: 0;
                width: 100%
            }

            table.reports-table thead th {
                background: #142134;
                color: #ced8e6;
                font-size: .63rem;
                font-weight: 600;
                letter-spacing: .09em;
                text-transform: uppercase;
                padding: .75rem .85rem;
                border-bottom: 1px solid #1f2e45;
                position: sticky;
                top: 0;
                z-index: 2;
                cursor: pointer
            }

            table.reports-table tbody td {
                padding: .85rem .9rem;
                vertical-align: top;
                font-size: .72rem;
                color: #d2dbe7;
                border-bottom: 1px solid #132031
            }

            table.reports-table tbody tr:last-child td {
                border-bottom: none
            }

            table.reports-table tbody tr {
                transition: .2s
            }

            table.reports-table tbody tr:hover {
                background: rgba(255, 255, 255, .04)
            }

            .reason-badge {
                display: inline-flex;
                align-items: center;
                gap: .35rem;
                font-size: .58rem;
                font-weight: 600;
                letter-spacing: .05em;
                padding: .4rem .55rem;
                border-radius: 6px;
                text-transform: uppercase;
                background: #233247;
                color: #d5e4f7;
                border: 1px solid #2f4867
            }

            .actions-bar a.action-btn {
                background: #132840;
                border: 1px solid #1f3a57;
                color: #c4d8ef;
                font-size: .6rem;
                font-weight: 600;
                padding: .4rem .55rem;
                border-radius: 6px;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: .3rem;
                transition: .25s
            }

            .actions-bar a.action-btn:hover {
                background: #1c3a57;
                color: #fff;
                border-color: #2f5a87
            }

            .empty-state {
                padding: 2.3rem 1rem;
                text-align: center;
                color: #7f8fa1;
                font-size: .8rem
            }

            .details-cell {
                max-width: 400px;
                line-height: 1.25
            }

            .fade-in {
                animation: fadeIn .5s ease both
            }

            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(6px)
                }

                to {
                    opacity: 1;
                    transform: translateY(0)
                }
            }

            @media (max-width:880px) {
                .reports-wrapper {
                    border: none;
                    background: transparent;
                    box-shadow: none
                }

                table.reports-table,
                table.reports-table thead,
                table.reports-table tbody,
                table.reports-table th,
                table.reports-table td,
                table.reports-table tr {
                    display: block
                }

                table.reports-table thead {
                    display: none
                }

                table.reports-table tbody tr {
                    background: #132133;
                    border: 1px solid #1f3147;
                    border-radius: 12px;
                    margin-bottom: .85rem;
                    padding: .75rem .9rem
                }

                table.reports-table tbody td {
                    border: none;
                    padding: .35rem 0;
                    font-size: .7rem
                }

                table.reports-table tbody td.actions {
                    margin-top: .55rem
                }
            }

            .reports-wrapper {
                margin-left: -.5rem;
                margin-right: -.5rem
            }

            @media (min-width:1200px) {
                .reports-wrapper {
                    margin-left: -1rem;
                    margin-right: -1rem
                }
            }
        </style>

        <div class="reports-topbar fade-in">
            <div class="reason-chips" id="reasonChips">
                <div class="reason-chip active" data-filter="">Total <span class="count"><?php echo $reasonCounts['total']; ?></span></div>
                <?php foreach ($sortedReasons as $__sr): ?>
                    <div class="reason-chip" data-filter="<?php echo htmlspecialchars($__sr['reason']); ?>"><?php echo htmlspecialchars($__sr['reason']); ?> <span class="count"><?php echo $__sr['count']; ?></span></div>
                <?php endforeach; ?>
            </div>
            <div class="filters-bar">
                <div class="search-box"><i class="bi bi-search"></i><input type="text" id="reportSearch" placeholder="Search job, reporter or details..."></div>
                <select id="reasonSelect">
                    <option value="">All Reasons</option>
                    <?php foreach ($sortedReasons as $__sr): ?>
                        <option value="<?php echo htmlspecialchars($__sr['reason']); ?>"><?php echo htmlspecialchars($__sr['reason']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="reset-btn" id="resetReports"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
            </div>
        </div>

        <div class="reports-wrapper fade-in" id="reportsWrapper">
            <table class="reports-table" id="reportsTable">
                <thead>
                    <tr>
                        <th data-sort="job">Job</th>
                        <th data-sort="reporter">Reporter</th>
                        <th data-sort="reason">Reason</th>
                        <th data-sort="details">Details</th>
                        <th data-sort="created">Created</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($open as $r): ?>
                        <tr data-reason="<?php echo htmlspecialchars($r['reason'] ?? ''); ?>">
                            <td style="font-size:.72rem;font-weight:600;color:#fff"><?php echo htmlspecialchars($r['job_title'] ?? ($r['job_id'] ?? '—')); ?></td>
                            <td style="font-size:.7rem;color:#9fb3c9"><?php echo htmlspecialchars($r['reporter_name'] ?? '—'); ?></td>
                            <td style="font-size:.65rem"><span class="reason-badge"><?php echo htmlspecialchars($r['reason'] ?? '—'); ?></span></td>
                            <td class="details-cell" style="font-size:.65rem;color:#d6e1ef"><?php echo htmlspecialchars($r['details'] ?? '—'); ?></td>
                            <td style="font-size:.65rem;color:#c2cfdd"><?php echo htmlspecialchars(date('M j, Y', strtotime($r['created_at'] ?? 'now'))); ?></td>
                            <td class="actions" style="text-align:right">
                                <div class="actions-bar" style="display:flex;gap:.35rem;justify-content:flex-end;flex-wrap:wrap">
                                    <a class="action-btn" href="job_view.php?job_id=<?php echo urlencode($r['job_id'] ?? ''); ?>" target="_blank"><i class="bi bi-box-arrow-up-right"></i>Job</a>
                                    <a class="action-btn" href="admin_reports.php?action=resolve&report_id=<?php echo urlencode($r['report_id']); ?>"><i class="bi bi-check2-circle"></i>Resolve</a>
                                    <?php if (!empty($r['job_id'])): ?>
                                        <a class="action-btn" data-confirm-title="Delete Job" data-confirm="Delete this job and resolve the report?" data-confirm-yes="Delete" data-confirm-no="Cancel" href="admin_reports.php?action=delete_job&report_id=<?php echo urlencode($r['report_id']); ?>&job_id=<?php echo urlencode($r['job_id']); ?>"><i class="bi bi-trash"></i>Delete</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$open): ?>
                        <tr>
                            <td colspan="6" class="empty-state">No open reports.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
    <script>
        const rSearch = document.getElementById('reportSearch');
        const rSelect = document.getElementById('reasonSelect');
        const rChips = document.getElementById('reasonChips');
        const rReset = document.getElementById('resetReports');
        const rRows = [...document.querySelectorAll('#reportsTable tbody tr')];

        function applyReportFilters() {
            const q = (rSearch.value || '').toLowerCase().trim();
            const reason = rSelect.value || '';
            rRows.forEach(row => {
                const rr = row.getAttribute('data-reason') || '';
                const text = row.innerText.toLowerCase();
                const okReason = !reason || rr === reason;
                const okSearch = !q || text.includes(q);
                row.style.display = (okReason && okSearch) ? '' : 'none';
            });
        }
        rSearch?.addEventListener('input', applyReportFilters);
        rSelect?.addEventListener('change', () => {
            applyReportFilters();
            syncReasonChip();
        });
        rChips?.addEventListener('click', e => {
            const c = e.target.closest('.reason-chip');
            if (!c) return;
            [...rChips.querySelectorAll('.reason-chip')].forEach(x => x.classList.remove('active'));
            c.classList.add('active');
            rSelect.value = c.getAttribute('data-filter') || '';
            applyReportFilters();
        });
        rReset?.addEventListener('click', () => {
            rSearch.value = '';
            rSelect.value = '';
            [...rChips.querySelectorAll('.reason-chip')].forEach(x => x.classList.remove('active'));
            rChips.querySelector('[data-filter=""]')?.classList.add('active');
            applyReportFilters();
        });

        function syncReasonChip() {
            const v = rSelect.value || '';
            [...rChips.querySelectorAll('.reason-chip')].forEach(ch => {
                ch.classList.toggle('active', (ch.getAttribute('data-filter') || '') === v);
            });
        }

        // Column sorting
        document.querySelectorAll('#reportsTable thead th[data-sort]').forEach(th => {
            th.addEventListener('click', () => {
                const key = th.getAttribute('data-sort');
                const tbody = th.closest('table').querySelector('tbody');
                const dir = th.getAttribute('data-dir') === 'asc' ? 'desc' : 'asc';
                th.setAttribute('data-dir', dir);
                const f = dir === 'asc' ? 1 : -1;
                const arr = [...tbody.querySelectorAll('tr')];
                arr.sort((a, b) => {
                    const get = (row) => {
                        switch (key) {
                            case 'job':
                                return row.children[0].innerText.toLowerCase();
                            case 'reporter':
                                return row.children[1].innerText.toLowerCase();
                            case 'reason':
                                return row.children[2].innerText.toLowerCase();
                            case 'details':
                                return row.children[3].innerText.toLowerCase();
                            case 'created':
                                return row.children[4].innerText.toLowerCase();
                            default:
                                return row.innerText.toLowerCase();
                        }
                    };
                    return get(a).localeCompare(get(b)) * f;
                });
                arr.forEach(tr => tbody.appendChild(tr));
            });
        });
        applyReportFilters();
    </script>