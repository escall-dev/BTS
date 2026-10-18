<?php
ob_start();

$timezone = new \DateTimeZone('Asia/Manila');
$now = new \DateTimeImmutable('now', $timezone);
$currentHour = (int) $now->format('G');
$greeting = match (true) {
    $currentHour < 12 => 'Good morning',
    $currentHour < 18 => 'Good afternoon',
    default => 'Good evening',
};
$todayLabel = $now->format('l, F j, Y');
$timeLabel = $now->format('h:i:s A');
$userName = (string) ($_SESSION['user']['username'] ?? 'Treasurer');
$stats = $stats ?? ['active' => 0, 'in_progress' => 0, 'ready' => 0, 'completed' => 0];
$workQueue = $workQueue ?? [];
?>

<div class="dashboard-shell">
    <!-- Executive Welcome Hero -->
    <header class="dashboard-hero">
        <div class="dashboard-hero__main">
            <div class="dashboard-hero__badge">
                <span class="dashboard-hero__dot" aria-hidden="true"></span>
                <span>Barangay Sto. Niño Treasury Operations</span>
            </div>
            <h1 class="dashboard-hero__title"><?= htmlspecialchars($greeting) ?>, <?= htmlspecialchars($userName) ?>
            </h1>
            <p class="dashboard-hero__subtitle">Pick up unfinished work or initiate new procurement and disbursement
                reports.</p>
        </div>
        <div class="dashboard-hero__aside">
            <div class="dashboard-hero__clock-card" data-live-clock aria-label="Philippine Standard Time Clock">
                <div class="dashboard-hero__clock-header">
                    <span class="dashboard-hero__clock-badge">Philippine Standard Time (PST)</span>
                    <span class="dashboard-hero__clock-pulse" aria-hidden="true"></span>
                </div>
                <div class="dashboard-hero__clock-body">
                    <svg viewBox="0 0 24 24" class="dashboard-hero__clock-icon" aria-hidden="true">
                        <path
                            d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2ZM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8Zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7Z" />
                    </svg>
                    <div class="dashboard-hero__clock-digits">
                        <span class="dashboard-hero__clock-time"
                            data-clock-time><?= htmlspecialchars($timeLabel) ?></span>
                        <span class="dashboard-hero__clock-date"
                            data-clock-date><?= htmlspecialchars($todayLabel) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- KPI Metrics Row -->
    <section class="dashboard-metrics" aria-label="Workflow Summary Metrics">
        <div class="dashboard-metric-card">
            <div class="dashboard-metric-card__header">
                <div class="dashboard-metric-card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path
                            d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6Zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2Zm0 14H8V4h12v12Zm-10-7h8v1.6H10V9Zm0 3h8v1.6H10V12Zm0-6h8v1.6H10V6Z" />
                    </svg>
                </div>
                <span class="dashboard-metric-card__badge">Live Queue</span>
            </div>
            <div class="dashboard-metric-card__body">
                <div class="dashboard-metric-card__value"><?= (int) ($stats['active'] ?? 0) ?></div>
                <div class="dashboard-metric-card__label">Active Workflows</div>
                <p class="dashboard-metric-card__hint">Unfinished or ongoing reports</p>
            </div>
        </div>

        <div class="dashboard-metric-card">
            <div class="dashboard-metric-card__header">
                <div class="dashboard-metric-card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path
                            d="M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm6 1.5V9h4.5L13 4.5ZM8 13h8v1.7H8V13Zm0 4h8v1.7H8V17Z" />
                    </svg>
                </div>
                <span class="dashboard-metric-card__badge">In Edit</span>
            </div>
            <div class="dashboard-metric-card__body">
                <div class="dashboard-metric-card__value"><?= (int) ($stats['in_progress'] ?? 0) ?></div>
                <div class="dashboard-metric-card__label">In-Progress Sections</div>
                <p class="dashboard-metric-card__hint">Saved steps with pending sections</p>
            </div>
        </div>

        <div class="dashboard-metric-card">
            <div class="dashboard-metric-card__header">
                <div class="dashboard-metric-card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path
                            d="m10 17-5-5 1.41-1.42L10 14.17l7.59-7.59L19 8l-9 9Zm2-15c5.52 0 10 4.48 10 10s-4.48 10-10 10S2 17.52 2 12 6.48 2 12 2Zm0 1.8A8.2 8.2 0 1 0 12 20.2a8.2 8.2 0 0 0 0-16.4Z" />
                    </svg>
                </div>
                <span class="dashboard-metric-card__badge">Ready</span>
            </div>
            <div class="dashboard-metric-card__body">
                <div class="dashboard-metric-card__value"><?= (int) ($stats['ready'] ?? 0) ?></div>
                <div class="dashboard-metric-card__label">Ready for Review</div>
                <p class="dashboard-metric-card__hint">Step saved or ready to finalize</p>
            </div>
        </div>

        <div class="dashboard-metric-card">
            <div class="dashboard-metric-card__header">
                <div class="dashboard-metric-card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path
                            d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3H4V5Zm1 5h14v9a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-9Zm4 3v2h6v-2H9Z" />
                    </svg>
                </div>
                <span class="dashboard-metric-card__badge">Archive</span>
            </div>
            <div class="dashboard-metric-card__body">
                <div class="dashboard-metric-card__value"><?= (int) ($stats['completed'] ?? 0) ?></div>
                <div class="dashboard-metric-card__label">Completed &amp; Archived</div>
                <p class="dashboard-metric-card__hint">Finalized institutional reports</p>
            </div>
        </div>
    </section>

    <!-- Quick Action Launchers -->
    <section class="dashboard-quick-actions" aria-labelledby="quick-actions-title">
        <div class="dashboard-section-header">
            <div>
                <h2 id="quick-actions-title">Quick Actions</h2>
                <p>Start a new standardized financial report workflow with one click.</p>
            </div>
        </div>
        <div class="dashboard-action-grid">
            <div class="dashboard-action-card">
                <div class="dashboard-action-card__content">
                    <div class="dashboard-action-card__top">
                        <span class="dashboard-action-card__badge">9-Step Workflow</span>
                        <div class="dashboard-action-card__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path
                                    d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2ZM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1Zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2Z" />
                            </svg>
                        </div>
                    </div>
                    <h3 class="dashboard-action-card__title">Purchase Request Process</h3>
                    <p class="dashboard-action-card__desc">Complete procurement chain: PR, BAC Resolution, RFQ, Abstract
                        of Canvass, PO, Voucher, ORS, IAR, and RIS.</p>
                </div>
                <div class="dashboard-action-card__footer">
                    <button type="button" class="dashboard-action-card__button" data-start-workflow
                        data-start-action="/BTS/reports/start/purchase-request">
                        <span>Generate Purchase Request</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="dashboard-action-card">
                <div class="dashboard-action-card__content">
                    <div class="dashboard-action-card__top">
                        <span class="dashboard-action-card__badge">4-Step Direct Process</span>
                        <div class="dashboard-action-card__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path
                                    d="M21 7.28V5c0-1.1-.9-2-2-2H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-2.28c.59-.35 1-.98 1-1.72V9c0-.74-.41-1.37-1-1.72ZM20 9v6h-7V9h7ZM5 19V5h14v2h-6c-1.1 0-2 .9-2 2v6c0 1.1.9 2 2 2h6v2H5Zm11-4.5c.83 0 1.5-.67 1.5-1.5s-.67-1.5-1.5-1.5-1.5.67-1.5 1.5.67 1.5 1.5 1.5Z" />
                            </svg>
                        </div>
                    </div>
                    <h3 class="dashboard-action-card__title">Disbursement Voucher Process</h3>
                    <p class="dashboard-action-card__desc">Streamlined expense release: Disbursement Voucher, Obligation
                        Report (ORS), Inspection &amp; Acceptance (IAR), and RIS.</p>
                </div>
                <div class="dashboard-action-card__footer">
                    <button type="button" class="dashboard-action-card__button" data-start-workflow
                        data-start-action="/BTS/reports/start/disbursement-voucher">
                        <span>Generate Disbursement Voucher</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Work Queue Section -->
    <section class="dashboard-work-queue" aria-labelledby="dashboard-work-queue-title">
        <header class="dashboard-work-queue__header">
            <div>
                <h2 id="dashboard-work-queue-title">Continue a Report</h2>
                <p>These reports have unfinished sections. Open any card to resume work seamlessly.</p>
            </div>
            <a href="/BTS/archive" class="dashboard-work-queue__all">
                <span>View all reports</span>
                <span aria-hidden="true">→</span>
            </a>
        </header>

        <?php if ($workQueue === []): ?>
            <div class="dashboard-work-queue__empty">
                <div class="dashboard-work-queue__empty-icon" aria-hidden="true">✓</div>
                <div class="dashboard-work-queue__empty-content">
                    <strong>All Caught Up!</strong>
                    <p>There are no unfinished reports at the moment. You can start a new report using the Quick Actions
                        above.</p>
                </div>
            </div>
        <?php else: ?>
            <ul class="dashboard-work-queue__list">
                <?php foreach ($workQueue as $process): ?>
                    <?php
                    $processId = (int) $process['process_id'];
                    $step = (string) ($process['current_step'] ?? '');
                    $stepLabel = $step !== '' ? \App\Models\Workflow::label($step) : 'Not started';
                    $totalSteps = (int) $process['report_count'];
                    $savedSteps = (int) $process['saved_count'];
                    $progressPercent = $totalSteps > 0 ? min(100, (int) round(($savedSteps / $totalSteps) * 100)) : 0;
                    $ready = (bool) $process['ready_for_next_step'];
                    $allStepsSaved = $totalSteps > 0 && $savedSteps >= $totalSteps;
                    $isDraft = $savedSteps === 0;
                    $activityAt = $isDraft
                        ? (string) ($process['created_at'] ?? '')
                        : (string) ($process['archived_at'] ?? $process['updated_at'] ?? $process['created_at'] ?? '');
                    $activityLabel = $activityAt !== '' ? date('M j, Y', strtotime($activityAt)) : '';
                    $startType = (string) ($process['start_type'] ?? 'purchase-request');
                    $isPurchase = str_contains($startType, 'purchase');
                    ?>
                    <li class="dashboard-work-queue__item">
                        <div class="dashboard-work-queue__card-top">
                            <span class="dashboard-work-queue__type">
                                <span class="dashboard-work-queue__type-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24">
                                        <path
                                            d="M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm6 1.5V9h4.5L13 4.5ZM8 13h8v1.7H8V13Zm0 4h8v1.7H8V17Z" />
                                    </svg>
                                </span>
                                <?= htmlspecialchars(ucwords(str_replace('-', ' ', (string) $process['start_type'])), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <span
                                class="dashboard-work-queue__status<?= $allStepsSaved || $ready ? ' is-ready' : ($isDraft ? ' is-draft' : ' is-needs-work') ?>">
                                <?= $isDraft ? 'Draft' : ($allStepsSaved ? 'Ready to finish' : ($ready ? 'Step saved' : 'Work in progress')) ?>
                            </span>
                        </div>

                        <div class="dashboard-work-queue__heading">
                            <strong class="dashboard-work-queue__project"
                                title="<?= htmlspecialchars((string) $process['project_title'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars((string) $process['project_title'], ENT_QUOTES, 'UTF-8') ?>
                            </strong>
                            <div class="dashboard-work-queue__code">
                                <span>Report no.</span>
                                <code><?= htmlspecialchars((string) $process['process_code'], ENT_QUOTES, 'UTF-8') ?></code>
                            </div>
                        </div>

                        <div class="dashboard-work-queue__details">
                            <div class="dashboard-work-queue__task">
                                <span class="dashboard-work-queue__task-label">Next Action</span>
                                <strong class="dashboard-work-queue__task-value">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17Z" />
                                    </svg>
                                    <?= htmlspecialchars($stepLabel, ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                            </div>
                            <div class="dashboard-work-queue__progress-row">
                                <div class="dashboard-work-queue__progress-info">
                                    <span><?= $savedSteps ?> of <?= $totalSteps ?> sections</span>
                                    <strong><?= $progressPercent ?>%</strong>
                                </div>
                                <div class="dashboard-work-queue__progress" role="progressbar"
                                    aria-label="<?= htmlspecialchars((string) $process['process_code'], ENT_QUOTES, 'UTF-8') ?> report progress"
                                    aria-valuemin="0" aria-valuemax="<?= max(1, $totalSteps) ?>"
                                    aria-valuenow="<?= min($savedSteps, $totalSteps) ?>">
                                    <span style="width: <?= $progressPercent ?>%"></span>
                                </div>
                            </div>
                        </div>

                        <div class="dashboard-work-queue__card-footer">
                            <div class="dashboard-work-queue__meta">
                                <?php if ($activityLabel !== ''): ?>
                                    <svg viewBox="0 0 24 24" class="dashboard-work-queue__time-icon" aria-hidden="true">
                                        <path
                                            d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2ZM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8Zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7Z" />
                                    </svg>
                                    <time
                                        datetime="<?= htmlspecialchars(date('Y-m-d', strtotime($activityAt)), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= $isDraft ? 'Started' : 'Updated' ?>
                                        <?= htmlspecialchars($activityLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </time>
                                <?php endif; ?>
                            </div>
                            <?php if ($step !== ''): ?>
                                <a class="button dashboard-work-queue__button"
                                    href="/BTS/reports/process/<?= $processId ?>/step/<?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?>">
                                    <span><?= $allStepsSaved ? 'Review report' : ($savedSteps === 0 ? 'Start report' : 'Continue report') ?></span>
                                    <span aria-hidden="true">→</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<!-- Start Workflow Confirmation Modal (Integrated with public/assets/js/app.js) -->
<div class="workflow-start-modal" data-start-modal hidden>
    <div class="workflow-start-modal__backdrop" data-start-modal-close></div>
    <section class="workflow-start-modal__panel" role="dialog" aria-modal="true" aria-labelledby="workflow-start-title"
        aria-describedby="workflow-start-description" tabindex="-1">
        <h2 id="workflow-start-title">Start Report Process</h2>
        <p id="workflow-start-description">Once you proceed, this report process will be saved in Archived Reports. You
            can continue it later or delete it there.</p>
        <form method="post" action="/BTS/reports/start/purchase-request" data-start-modal-form>
            <div class="button-row">
                <button type="submit">Proceed</button>
                <button type="button" class="secondary" data-start-modal-close>Cancel</button>
            </div>
        </form>
    </section>
</div>

<noscript>
    <p class="empty-state">Enable JavaScript to start a report process from this page.</p>
</noscript>

<?php
$content = ob_get_clean();
$active = 'dashboard';
require __DIR__ . '/../layout/main.php';
