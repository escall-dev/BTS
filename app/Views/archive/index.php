<?php
$archives = $archives ?? [];
$filters = $filters ?? ['status' => '', 'workflow' => '', 'date_from' => '', 'date_to' => '', 'process_number' => ''];
$totalArchives = (int) ($totalArchives ?? count($archives));
$archivePerPage = max(1, (int) ($archivePerPage ?? 10));
$archivePage = max(1, (int) ($archivePage ?? 1));
$archivePageCount = max(1, (int) ($archivePageCount ?? 1));
$filterErrors = $filterErrors ?? [];
$deleted = $deleted ?? false;
$hasFilters = count(array_filter($filters, static fn ($key): bool => !in_array($key, ['page', 'per_page'], true) && trim((string) $filters[$key]) !== '', ARRAY_FILTER_USE_KEY)) > 0;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$archiveTimezone = new \DateTimeZone('Asia/Manila');
$formatArchiveDateTime = static function ($value) use ($archiveTimezone): array {
    $value = trim((string) $value);
    if ($value === '') {
        return ['date' => '', 'time' => ''];
    }

    try {
        $dateTime = (new \DateTimeImmutable($value, $archiveTimezone))
            ->setTimezone($archiveTimezone)
            ->format('m-d-Y|h:i A');
        [$date, $time] = explode('|', $dateTime, 2);
        return ['date' => $date, 'time' => $time];
    } catch (\Throwable) {
        return ['date' => $value, 'time' => ''];
    }
};
$formatFilterDate = static function ($value) use ($archiveTimezone): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    try {
        return \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $archiveTimezone)?->format('m/d/Y') ?? $value;
    } catch (\Throwable) {
        return $value;
    }
};
$dateFromLabel = $formatFilterDate($filters['date_from'] ?? '');
$dateToLabel = $formatFilterDate($filters['date_to'] ?? '');
$dateRangeLabel = $dateFromLabel !== '' && $dateToLabel !== ''
    ? $dateFromLabel . ' → ' . $dateToLabel
    : ($dateFromLabel !== '' ? $dateFromLabel . ' → Select end' : ($dateToLabel !== '' ? 'Select start → ' . $dateToLabel : 'Select Start and End Date'));
$archivePageUrl = static function (int $page) use ($filters): string {
    $query = [];
    foreach (['status', 'workflow', 'date_from', 'date_to', 'process_number', 'per_page'] as $key) {
        if (trim((string) ($filters[$key] ?? '')) !== '') {
            $query[$key] = (string) $filters[$key];
        }
    }
    $query['page'] = max(1, $page);
    return '/BTS/archive?' . http_build_query($query);
};
$archivePageItems = [];
$archivePageSizes = ['5' => '5', '10' => '10', '15' => '15', '20' => '20', 'all' => 'All'];
if ($archivePageCount <= 9) {
    $archivePageItems = range(1, $archivePageCount);
} else {
    $archivePageItems[] = 1;
    $windowStart = max(2, $archivePage - 2);
    $windowEnd = min($archivePageCount - 1, $archivePage + 2);
    if ($windowStart > 2) {
        $archivePageItems[] = '…';
    }
    for ($pageNumber = $windowStart; $pageNumber <= $windowEnd; $pageNumber++) {
        $archivePageItems[] = $pageNumber;
    }
    if ($windowEnd < $archivePageCount - 1) {
        $archivePageItems[] = '…';
    }
    $archivePageItems[] = $archivePageCount;
}
ob_start();
?>
<section class="page-header">
    <h1>Archived Reports</h1>
    <p>Saved report processes are listed here. Continue unfinished work or review completed processes.</p>
</section>

<?php if ($deleted): ?>
    <div class="toast success" data-toast>Report process deleted from archive.</div>

<?php endif; ?>

<section class='archive-certification-link'>
    <div>
        <span class='pbc-report-kicker'>Completed transactions</span>
        <h2>Punong Barangay Certification</h2>
        <p>Generate a PDF certification from report processes that have finished.</p>
    </div>
    <a class='button' href='/BTS/archive/certification'>Open certification</a>
</section>

<section class="archive-filter-panel" aria-labelledby="archive-filter-heading">
    <div class="archive-filter-heading">
        <div>
            <h2 id="archive-filter-heading">Filter reports</h2>
            <p><?= $totalArchives ?> <?= $totalArchives === 1 ? 'process' : 'processes' ?> found</p>
        </div>
        <?php if ($hasFilters): ?><a class="button secondary" href="/BTS/archive">Clear</a><?php endif; ?>
    </div>
    <form class="archive-filter-form" method="get" action="/BTS/archive" data-archive-filter-form>
        <div class="archive-filter-select archive-status-filter" data-archive-custom-select>
            <span id="archive-status-filter-label">Status</span>
            <select class="archive-native-filter-select" name="status" data-archive-filter-control data-archive-native-select aria-hidden="true" tabindex="-1" hidden>
                <option value="">All statuses</option>
                <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Draft (not started)</option>
                <option value="in_progress" <?= $filters['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
            </select>
            <button class="archive-filter-select-trigger" type="button" aria-labelledby="archive-status-filter-label archive-status-filter-value" aria-haspopup="listbox" aria-expanded="false" aria-controls="archive-status-filter-options" data-archive-select-trigger>
                <span id="archive-status-filter-value" data-archive-select-value>All statuses</span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
            </button>
            <div class="archive-filter-select-menu" id="archive-status-filter-options" role="listbox" aria-labelledby="archive-status-filter-label" hidden data-archive-select-menu>
                <div role="option" tabindex="-1" aria-selected="<?= $filters['status'] === '' ? 'true' : 'false' ?>" data-archive-select-option data-value="">All statuses</div>
                <div role="option" tabindex="-1" aria-selected="<?= $filters['status'] === 'draft' ? 'true' : 'false' ?>" data-archive-select-option data-value="draft">Draft (not started)</div>
                <div role="option" tabindex="-1" aria-selected="<?= $filters['status'] === 'in_progress' ? 'true' : 'false' ?>" data-archive-select-option data-value="in_progress">In Progress</div>
                <div role="option" tabindex="-1" aria-selected="<?= $filters['status'] === 'completed' ? 'true' : 'false' ?>" data-archive-select-option data-value="completed">Completed</div>
            </div>
            <?php if (isset($filterErrors['status'])): ?><small class="field-error"><?= $escape($filterErrors['status']) ?></small><?php endif; ?>
        </div>
        <div class="archive-filter-select archive-type-filter" data-archive-custom-select>
            <span id="archive-type-filter-label">Process Type</span>
            <select class="archive-native-filter-select" name="workflow" data-archive-filter-control data-archive-native-select aria-hidden="true" tabindex="-1" hidden>
                <option value="" <?= $filters['workflow'] === '' ? 'selected' : '' ?>>All process types</option>
                <option value="purchase-request" <?= $filters['workflow'] === 'purchase-request' ? 'selected' : '' ?>>Purchase Request</option>
                <option value="disbursement-voucher" <?= $filters['workflow'] === 'disbursement-voucher' ? 'selected' : '' ?>>Disbursement Voucher</option>
            </select>
            <button class="archive-filter-select-trigger" type="button" aria-labelledby="archive-type-filter-label archive-type-filter-value" aria-haspopup="listbox" aria-expanded="false" aria-controls="archive-type-filter-options" data-archive-select-trigger>
                <span id="archive-type-filter-value" data-archive-select-value>All process types</span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
            </button>
            <div class="archive-filter-select-menu" id="archive-type-filter-options" role="listbox" aria-labelledby="archive-type-filter-label" hidden data-archive-select-menu>
                <div role="option" tabindex="-1" aria-selected="<?= $filters['workflow'] === '' ? 'true' : 'false' ?>" data-archive-select-option data-value="">All process types</div>
                <div role="option" tabindex="-1" aria-selected="<?= $filters['workflow'] === 'purchase-request' ? 'true' : 'false' ?>" data-archive-select-option data-value="purchase-request">Purchase Request</div>
                <div role="option" tabindex="-1" aria-selected="<?= $filters['workflow'] === 'disbursement-voucher' ? 'true' : 'false' ?>" data-archive-select-option data-value="disbursement-voucher">Disbursement Voucher</div>
            </div>
            <?php if (isset($filterErrors['workflow'])): ?><small class="field-error"><?= $escape($filterErrors['workflow']) ?></small><?php endif; ?>
        </div>
        <div class="archive-date-range" data-date-range-picker>
            <span class="archive-filter-label">Select Start and End Date</span>
            <input type="hidden" name="date_from" value="<?= $escape($filters['date_from']) ?>" data-date-range-from>
            <input type="hidden" name="date_to" value="<?= $escape($filters['date_to']) ?>" data-date-range-to>
            <div class="archive-date-range-control<?= $dateFromLabel !== '' || $dateToLabel !== '' ? ' has-clear' : '' ?>">
                <button class="archive-date-range-trigger" type="button" aria-haspopup="dialog" aria-expanded="false" aria-controls="archive-date-range-popover" data-date-range-trigger>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="16" rx="2"></rect><path d="M7.5 3v4M16.5 3v4M3.5 9.5h17"></path></svg>
                    <span data-date-range-label><?= $escape($dateRangeLabel) ?></span>
                </button>
                <button class="archive-date-range-clear" type="button" aria-label="Clear selected date range" title="Clear dates" data-date-range-clear <?= $dateFromLabel !== '' || $dateToLabel !== '' ? '' : 'hidden' ?>>×</button>
            </div>
            <div class="archive-date-range-popover" id="archive-date-range-popover" role="dialog" aria-label="Select Start and End Date" hidden data-date-range-popover>
                <div class="archive-calendar-months">
                    <?php for ($calendarIndex = 0; $calendarIndex < 2; $calendarIndex++): ?>
                        <section class="archive-calendar-month" data-calendar-month="<?= $calendarIndex ?>">
                            <header><button type="button" aria-label="Previous month" data-calendar-previous <?= $calendarIndex === 1 ? 'hidden' : '' ?>>‹</button><strong data-calendar-title></strong><button type="button" aria-label="Next month" data-calendar-next <?= $calendarIndex === 0 ? 'hidden' : '' ?>>›</button></header>
                            <div class="archive-calendar-weekdays" aria-hidden="true"><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span><span>Su</span></div>
                            <div class="archive-calendar-days" data-calendar-days></div>
                        </section>
                    <?php endfor; ?>
                </div>
                <footer class="archive-date-range-footer">
                    <span class="archive-date-range-preview" aria-live="polite" data-date-range-preview>Select start and end dates</span>
                    <button class="secondary" type="button" data-date-range-cancel>Cancel</button>
                    <button type="button" data-date-range-apply>Apply dates</button>
                </footer>
            </div>
            <?php if (isset($filterErrors['date_from'])): ?><small class="field-error"><?= $escape($filterErrors['date_from']) ?></small><?php endif; ?>
            <?php if (isset($filterErrors['date_to'])): ?><small class="field-error"><?= $escape($filterErrors['date_to']) ?></small><?php endif; ?>
        </div>
        <div class="archive-filter-select archive-page-size archive-filter-page-size" data-archive-custom-select>
            <span id="archive-page-size-label">Per page</span>
            <select class="archive-native-filter-select" name="per_page" data-archive-page-size data-archive-native-select aria-hidden="true" tabindex="-1" hidden>
                <?php foreach ($archivePageSizes as $pageSizeValue => $pageSizeLabel): ?>
                    <option value="<?= $escape($pageSizeValue) ?>" <?= (string) $filters['per_page'] === (string) $pageSizeValue ? 'selected' : '' ?>><?= $escape($pageSizeLabel) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="archive-filter-select-trigger" type="button" aria-labelledby="archive-page-size-label archive-page-size-value" aria-haspopup="listbox" aria-expanded="false" aria-controls="archive-page-size-options" data-archive-select-trigger>
                <span id="archive-page-size-value" data-archive-select-value><?= $escape($archivePageSizes[(string) $filters['per_page']] ?? '10') ?></span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
            </button>
            <div class="archive-filter-select-menu" id="archive-page-size-options" role="listbox" aria-labelledby="archive-page-size-label" hidden data-archive-select-menu>
                <?php foreach ($archivePageSizes as $pageSizeValue => $pageSizeLabel): ?>
                    <div role="option" tabindex="-1" aria-selected="<?= (string) $filters['per_page'] === (string) $pageSizeValue ? 'true' : 'false' ?>" data-archive-select-option data-value="<?= $escape($pageSizeValue) ?>"><?= $escape($pageSizeLabel) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="archive-search-control" data-archive-search-control>
            <input id="archive-process-search" type="search" name="process_number" value="<?= $escape($filters['process_number']) ?>" placeholder="Process number" aria-label="Search by process number" autocomplete="off" data-archive-process-search>
            <button class="archive-search-toggle" type="submit" aria-label="Search by process number" data-archive-search-toggle>
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 5 5"></path></svg>
            </button>
        </div>
        <noscript><button type="submit">Apply filters</button></noscript>
    </form>
</section>

<div class="archive-results" data-archive-results aria-live="polite" aria-busy="false">
<section class="table-panel archive-panel" data-archive-panel>
    <?php if ($archives === []): ?>
        <p class="empty-state"><?= $hasFilters || $filterErrors !== [] ? 'No report processes match these filters.' : 'No saved report process has been archived yet.' ?></p>
    <?php else: ?>

        <div class="archive-table-scroll">
            <table class="archive-table">
                <thead>
                    <tr>

                        <th>Process</th>
                        <th>Project Title</th>
                        <th>Process Type</th>
                        <th>Current Step</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th>Actions</th>

                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($archives as $archive): ?>
                        <?php
                        $processId = (int) ($archive['process_id'] ?? 0);
                        $steps = is_array($archive['steps'] ?? null) ? $archive['steps'] : [];
                        $currentStep = (string) ($archive['current_step'] ?? '');
                        $currentStepLabel = $currentStep !== '' ? \App\Models\Workflow::label($currentStep) : 'Not started';
                        $totalSteps = (int) ($archive['report_count'] ?? count($steps));
                        $savedSteps = (int) ($archive['saved_count'] ?? 0);
                        $status = (string) ($archive['status'] ?? 'in_progress');
                        $isCompleted = $status === 'completed';
                        $isDraft = $status === 'draft';
                        $updatedAt = (string) ($archive['archived_at'] ?? $archive['updated_at'] ?? $archive['completed_at'] ?? $archive['created_at'] ?? '');
                        $createdAtParts = $formatArchiveDateTime($archive['created_at'] ?? '');
                        $updatedAtParts = $formatArchiveDateTime($updatedAt);
                        $processCode = (string) ($archive['process_code'] ?? '');
                        $progressPercent = $totalSteps > 0 ? min(100, (int) round(($savedSteps / $totalSteps) * 100)) : 0;
                        ?>
                        <tr>

                            <td data-label="Process">
                                <a class="archive-process-code archive-process-code-link" href="/BTS/reports/process/<?= $processId ?>/step/<?= $escape($currentStep) ?>" aria-label="Open process <?= $escape($processCode) ?>" title="Open process <?= $escape($processCode) ?>">
                                    <?= $escape($processCode) ?>
                                </a>
                                <small class="archive-datetime"><span><?= $escape($createdAtParts['date']) ?></span><?php if ($createdAtParts['time'] !== ''): ?><span><?= $escape($createdAtParts['time']) ?></span><?php endif; ?></small>
                            </td>
                            <td class="archive-project-title" data-label="Project Title"><?= $escape($archive['project_title'] ?? 'Not entered yet') ?></td>
                            <td data-label="Process Type"><?= $escape(ucwords(str_replace('-', ' ', (string) ($archive['start_type'] ?? '')))) ?></td>
                            <td data-label="Current Step"><?= $escape($currentStepLabel) ?></td>
                            <td data-label="Progress">
                                <div class="archive-progress-text"><?= $savedSteps ?> / <?= $totalSteps ?> saved</div>
                                <div class="archive-progress-track" role="progressbar" aria-label="Report progress for <?= $escape($processCode) ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $progressPercent ?>">
                                    <span style="width: <?= $progressPercent ?>%"></span>
                                </div>
                                <div class="archive-step-list" aria-label="Report process step progress">
                                    <?php foreach ($steps as $workflowStep): ?>
                                        <?php
                                        $stepKey = (string) ($workflowStep['step_key'] ?? '');
                                        $stepOrder = (int) ($workflowStep['step_order'] ?? 0);
                                        $stepLabel = \App\Models\Workflow::label($stepKey);
                                        $stepIsSaved = (int) ($workflowStep['is_saved'] ?? 0) === 1;
                                        $stepIsCurrent = $stepKey === $currentStep;
                                        $stepClass = 'archive-step-pill' . ($stepIsSaved ? ' is-saved' : '') . ($stepIsCurrent ? ' is-current' : '');
                                        $stepAriaState = $stepIsCurrent && $stepIsSaved ? 'current and saved' : ($stepIsCurrent ? 'current' : ($stepIsSaved ? 'saved' : 'not saved'));
                                        ?>
                                        <?php if ($stepIsSaved || $stepIsCurrent): ?>
                                            <a class="<?= $escape($stepClass) ?>" href="/BTS/reports/process/<?= $processId ?>/step/<?= $escape($stepKey) ?>" aria-label="Step <?= $stepOrder ?>: <?= $escape($stepLabel) ?>, <?= $escape($stepAriaState) ?>" title="Step <?= $stepOrder ?>: <?= $escape($stepLabel) ?><?= $stepIsCurrent ? ' — current' : '' ?><?= $stepIsSaved ? ' — saved' : '' ?>"<?= $stepIsCurrent ? ' aria-current="step"' : '' ?>><?= $stepOrder ?></a>
                                        <?php else: ?>
                                            <span class="<?= $escape($stepClass) ?>" aria-label="Step <?= $stepOrder ?>: <?= $escape($stepLabel) ?>, not saved" aria-disabled="true" title="Step <?= $stepOrder ?>: <?= $escape($stepLabel) ?> — not saved"><?= $stepOrder ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td data-label="Status"><span class="archive-status <?= $isCompleted ? 'is-complete' : ($isDraft ? 'is-draft' : 'is-progress') ?>"><?= $escape($isDraft ? 'Draft' : ucwords(str_replace('_', ' ', $status))) ?></span></td>
                            <td data-label="Updated"><time class="archive-datetime"><span><?= $escape($updatedAtParts['date']) ?></span><?php if ($updatedAtParts['time'] !== ''): ?><span><?= $escape($updatedAtParts['time']) ?></span><?php endif; ?></time></td>
                            <td data-label="Actions">
                                <div class="archive-actions">
                                    <a class="button secondary" href="/BTS/reports/process/<?= $processId ?>/step/<?= $escape($currentStep) ?>"><?= $isCompleted ? 'Review' : 'Continue' ?></a>
                                    <form method="post" action="/BTS/archive/<?= $processId ?>/delete" data-confirm-delete="Delete this saved report process from Archived Reports?">
                                        <button type="submit" class="secondary danger-button">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalArchives > 0): ?>
            <?php $firstArchive = (($archivePage - 1) * $archivePerPage) + 1; $lastArchive = min($firstArchive + count($archives) - 1, $totalArchives); ?>
            <nav class="archive-pagination" aria-label="Archive pages">
                <?php if ($archivePageCount > 1): ?>
                    <div class="archive-pagination-controls">
                        <?php if ($archivePage > 1): ?>
                            <a class="archive-page-link archive-page-direction" href="<?= $escape($archivePageUrl($archivePage - 1)) ?>" rel="prev"><span aria-hidden="true">‹</span> Back</a>
                        <?php else: ?>
                            <span class="archive-page-link archive-page-direction is-disabled" aria-disabled="true"><span aria-hidden="true">‹</span> Back</span>
                        <?php endif; ?>
                        <div class="archive-page-numbers" aria-label="Page numbers">
                            <?php foreach ($archivePageItems as $pageItem): ?>
                                <?php if ($pageItem === '…'): ?>
                                    <span class="archive-page-ellipsis" aria-hidden="true">…</span>
                                <?php elseif ($pageItem === $archivePage): ?>
                                    <span class="archive-page-link is-current" aria-current="page" aria-label="Page <?= $pageItem ?>"><?= $pageItem ?></span>
                                <?php else: ?>
                                    <a class="archive-page-link" href="<?= $escape($archivePageUrl($pageItem)) ?>" aria-label="Go to page <?= $pageItem ?>"><?= $pageItem ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($archivePage < $archivePageCount): ?>
                            <a class="archive-page-link archive-page-direction" href="<?= $escape($archivePageUrl($archivePage + 1)) ?>" rel="next">Next <span aria-hidden="true">›</span></a>
                        <?php else: ?>
                            <span class="archive-page-link archive-page-direction is-disabled" aria-disabled="true">Next <span aria-hidden="true">›</span></span>
                        <?php endif; ?>
                        <form class="archive-page-jump" method="get" action="/BTS/archive" data-archive-page-jump>
                            <?php foreach (['status', 'workflow', 'date_from', 'date_to', 'per_page', 'process_number'] as $key): ?>
                                <input type="hidden" name="<?= $escape($key) ?>" value="<?= $escape($filters[$key] ?? '') ?>">
                            <?php endforeach; ?>
                            <label for="archive-page-number">Page</label>
                            <input id="archive-page-number" type="number" name="page" min="1" max="<?= $archivePageCount ?>" value="<?= $archivePage ?>" required aria-label="Page number">
                            <button type="submit">Go</button>
                        </form>
                    </div>
                <?php endif; ?>
                <span class="archive-pagination-summary">Showing <?= $firstArchive ?>–<?= $lastArchive ?> of <?= $totalArchives ?></span>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout/main.php';
