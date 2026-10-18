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
        <label>
            <span>Status</span>
            <select name="status" data-archive-filter-control>
                <option value="">All statuses</option>
                <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Draft (not started)</option>
                <option value="in_progress" <?= $filters['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
            </select>
            <?php if (isset($filterErrors['status'])): ?><small class="field-error"><?= $escape($filterErrors['status']) ?></small><?php endif; ?>
        </label>
        <label>
            <span>Process Type</span>
            <select name="workflow" data-archive-filter-control>
                <option value="" <?= $filters['workflow'] === '' ? 'selected' : '' ?>>All process types</option>
                <option value="purchase-request" <?= $filters['workflow'] === 'purchase-request' ? 'selected' : '' ?>>Purchase Request</option>
                <option value="disbursement-voucher" <?= $filters['workflow'] === 'disbursement-voucher' ? 'selected' : '' ?>>Disbursement Voucher</option>
            </select>
            <?php if (isset($filterErrors['workflow'])): ?><small class="field-error"><?= $escape($filterErrors['workflow']) ?></small><?php endif; ?>
        </label>
        <label>
            <span>Created From</span>
            <input type="date" name="date_from" value="<?= $escape($filters['date_from']) ?>" data-archive-filter-control>
            <?php if (isset($filterErrors['date_from'])): ?><small class="field-error"><?= $escape($filterErrors['date_from']) ?></small><?php endif; ?>
        </label>
        <label>
            <span>Created To</span>
            <input type="date" name="date_to" value="<?= $escape($filters['date_to']) ?>" data-archive-filter-control>
            <?php if (isset($filterErrors['date_to'])): ?><small class="field-error"><?= $escape($filterErrors['date_to']) ?></small><?php endif; ?>
        </label>
        <label class="archive-process-search">
            <span>Process Number</span>
            <input type="search" name="process_number" value="<?= $escape($filters['process_number']) ?>" placeholder="Search BTS process number" autocomplete="off" data-archive-process-search>
        </label>
        <label class="archive-page-size archive-filter-page-size">
            <span>Reports per page</span>
            <select name="per_page" data-archive-page-size aria-label="Reports per page">
                <?php foreach (['5' => '5', '10' => '10', '15' => '15', '20' => '20', 'all' => 'All'] as $pageSizeValue => $pageSizeLabel): ?>
                    <option value="<?= $escape($pageSizeValue) ?>" <?= (string) $filters['per_page'] === (string) $pageSizeValue ? 'selected' : '' ?>><?= $escape($pageSizeLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
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
                        $processCode = (string) ($archive['process_code'] ?? '');
                        $progressPercent = $totalSteps > 0 ? min(100, (int) round(($savedSteps / $totalSteps) * 100)) : 0;
                        ?>
                        <tr>

                            <td data-label="Process">
                                <strong><?= $escape($processCode) ?></strong>
                                <small><?= $escape($archive['created_at'] ?? '') ?></small>
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
                            <td data-label="Updated"><?= $escape($updatedAt) ?></td>
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
                <span>Showing <?= $firstArchive ?>–<?= $lastArchive ?> of <?= $totalArchives ?></span>
                <div>
                    <?php if ($archivePageCount > 1): ?>
                        <?php if ($archivePage > 1): ?><a class="button secondary" href="<?= $escape($archivePageUrl($archivePage - 1)) ?>" rel="prev">Previous</a><?php endif; ?>
                        <span>Page <?= $archivePage ?> of <?= $archivePageCount ?></span>
                        <?php if ($archivePage < $archivePageCount): ?><a class="button secondary" href="<?= $escape($archivePageUrl($archivePage + 1)) ?>" rel="next">Next</a><?php endif; ?>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout/main.php';
