<?php
$archives = $archives ?? [];
$filters = $filters ?? ['status' => '', 'date_from' => '', 'date_to' => '', 'process_number' => ''];
$filterErrors = $filterErrors ?? [];
$deleted = $deleted ?? false;
$hasFilters = count(array_filter($filters, static fn ($value): bool => trim((string) $value) !== '')) > 0;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
ob_start();
?>
<section class="page-header">
    <h1>Archived Reports</h1>
    <p>Saved report processes are listed here. Continue unfinished work or review completed workflows.</p>
</section>

<?php if ($deleted): ?>
    <div class="toast success" data-toast>Report process deleted from archive.</div>

<?php endif; ?>

<section class='archive-certification-link'>
    <div>
        <span class='pbc-report-kicker'>Completed transactions</span>
        <h2>Punong Barangay Certification</h2>
        <p>Generate a PDF certification from workflows that have finished the full process.</p>
    </div>
    <a class='button' href='/BTS/archive/certification'>Open certification</a>
</section>

<section class="archive-filter-panel" aria-labelledby="archive-filter-heading">
    <div class="archive-filter-heading">
        <div>
            <h2 id="archive-filter-heading">Filter reports</h2>
            <p><?= count($archives) ?> <?= count($archives) === 1 ? 'process' : 'processes' ?> found</p>
        </div>
        <?php if ($hasFilters): ?><a class="button secondary" href="/BTS/archive">Clear</a><?php endif; ?>
    </div>
    <form class="archive-filter-form" method="get" action="/BTS/archive" data-archive-filter-form>
        <label>
            <span>Status</span>
            <select name="status" data-archive-filter-control>
                <option value="">All statuses</option>
                <option value="in_progress" <?= $filters['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
            </select>
            <?php if (isset($filterErrors['status'])): ?><small class="field-error"><?= $escape($filterErrors['status']) ?></small><?php endif; ?>
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
        <noscript><button type="submit">Apply filters</button></noscript>
    </form>
</section>

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
                        <th>Workflow</th>
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
                        $updatedAt = (string) ($archive['archived_at'] ?? $archive['updated_at'] ?? $archive['completed_at'] ?? $archive['created_at'] ?? '');
                        ?>
                        <tr>

                            <td>
                                <strong><?= $escape($archive['process_code'] ?? '') ?></strong>
                                <small><?= $escape($archive['created_at'] ?? '') ?></small>
                            </td>
                            <td class="archive-project-title"><?= $escape($archive['project_title'] ?? 'Not entered yet') ?></td>
                            <td><?= $escape(ucwords(str_replace('-', ' ', (string) ($archive['start_type'] ?? '')))) ?></td>
                            <td><?= $escape($currentStepLabel) ?></td>
                            <td>
                                <div class="archive-progress-text"><?= $savedSteps ?> / <?= $totalSteps ?> saved</div>
                                <div class="archive-step-list" aria-label="Workflow step progress">
                                    <?php foreach ($steps as $workflowStep): ?>
                                        <?php
                                        $stepKey = (string) ($workflowStep['step_key'] ?? '');
                                        $stepIsSaved = (int) ($workflowStep['is_saved'] ?? 0) === 1;
                                        $stepIsCurrent = $stepKey === $currentStep;
                                        $stepClass = 'archive-step-pill' . ($stepIsSaved ? ' is-saved' : '') . ($stepIsCurrent ? ' is-current' : '');
                                        ?>
                                        <?php if ($stepIsSaved || $stepIsCurrent): ?>
                                            <a class="<?= $escape($stepClass) ?>" href="/BTS/reports/process/<?= $processId ?>/step/<?= $escape($stepKey) ?>"><?= (int) ($workflowStep['step_order'] ?? 0) ?></a>
                                        <?php else: ?>
                                            <span class="<?= $escape($stepClass) ?>" aria-disabled="true"><?= (int) ($workflowStep['step_order'] ?? 0) ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td><span class="archive-status <?= $isCompleted ? 'is-complete' : 'is-progress' ?>"><?= $escape(ucwords(str_replace('_', ' ', $status))) ?></span></td>
                            <td><?= $escape($updatedAt) ?></td>
                            <td>
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
    <?php endif; ?>
</section>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout/main.php';