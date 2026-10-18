<?php
$trashItems = $trashItems ?? [];
$filters = $filters ?? ['recently_deleted' => '', 'progress' => ''];
$hasFilters = $hasFilters ?? false;
$totalTrashItems = $totalTrashItems ?? count($trashItems);
$restored = $restored ?? false;
$purged = $purged ?? false;
$notFound = $notFound ?? false;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$timezone = new \DateTimeZone('Asia/Manila');
$formatDate = static function ($value) use ($timezone): string {
    try {
        return (new \DateTimeImmutable((string) $value, $timezone))->setTimezone($timezone)->format('m-d-Y');
    } catch (\Throwable) {
        return '';
    }
};
$formatDateTime = static function ($value) use ($timezone): array {
    try {
        $date = (new \DateTimeImmutable((string) $value, $timezone))->setTimezone($timezone);
        return [$date->format('m-d-Y'), $date->format('h:i A')];
    } catch (\Throwable) {
        return ['', ''];
    }
};
ob_start();
?>
<section class="page-header trash-page-header">
    <div>
        <h1>Trash</h1>
        <p>Restore deleted reports or remove them permanently.</p>
    </div>
    <a class="button secondary" href="/BTS/archive">Back to Archived Reports</a>
</section>

<?php if ($restored): ?><span data-success-modal data-success-title="Report restored" data-success-message="The report and its saved progress are available again in Archived Reports." data-success-label="Done" data-success-secondary-label="View Archive" data-success-secondary-href="/BTS/archive" hidden></span><?php endif; ?>
<?php if ($purged): ?><span data-success-modal data-success-title="Report permanently deleted" data-success-message="The report and its saved data were permanently removed." data-success-label="Done" data-success-secondary-label="View Archive" data-success-secondary-href="/BTS/archive" hidden></span><?php endif; ?>
<?php if ($notFound): ?><div class="toast error" data-toast>This report is no longer available in Trash.</div><?php endif; ?>

<section class="trash-retention-note" aria-label="Trash retention policy">
    <span class="trash-retention-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 8v4l2.5 1.5M21 12a9 9 0 1 1-2.64-6.36M21 4v5h-5"/></svg></span>
    <div><strong>Reports are kept for 30 days</strong><p>Each report is permanently deleted 30 days after it was moved to Trash. Restore it before its deletion date to keep it.</p></div>
    <span class="trash-item-count"><?= $totalTrashItems ?> <?= $totalTrashItems === 1 ? 'report' : 'reports' ?></span>
</section>

<section class="archive-filter-panel trash-filter-panel" aria-labelledby="trash-filter-heading">
    <div class="archive-filter-heading">
        <div>
            <h2 id="trash-filter-heading">Filter deleted reports</h2>
            <p><?= count($trashItems) ?> of <?= $totalTrashItems ?> <?= $totalTrashItems === 1 ? 'report' : 'reports' ?></p>
        </div>
        <?php if ($hasFilters): ?><a class="button secondary" href="/BTS/trash">Clear</a><?php endif; ?>
    </div>
    <form class="archive-filter-form trash-filter-form" method="get" action="/BTS/trash" data-archive-filter-form>
        <div class="archive-filter-select trash-filter-select" data-archive-custom-select>
            <span id="trash-recent-filter-label">Recently deleted</span>
            <select class="archive-native-filter-select" name="recently_deleted" data-archive-filter-control data-archive-native-select aria-hidden="true" tabindex="-1" hidden>
                <option value="" <?= $filters['recently_deleted'] === '' ? 'selected' : '' ?>>Any time</option>
                <option value="24-hours" <?= $filters['recently_deleted'] === '24-hours' ? 'selected' : '' ?>>Last 24 hours</option>
                <option value="7-days" <?= $filters['recently_deleted'] === '7-days' ? 'selected' : '' ?>>Last 7 days</option>
                <option value="30-days" <?= $filters['recently_deleted'] === '30-days' ? 'selected' : '' ?>>Last 30 days</option>
            </select>
            <button class="archive-filter-select-trigger" type="button" aria-labelledby="trash-recent-filter-label trash-recent-filter-value" aria-haspopup="listbox" aria-expanded="false" aria-controls="trash-recent-filter-options" data-archive-select-trigger>
                <span id="trash-recent-filter-value" data-archive-select-value><?= $filters['recently_deleted'] === '' ? 'Any time' : ($filters['recently_deleted'] === '24-hours' ? 'Last 24 hours' : ($filters['recently_deleted'] === '7-days' ? 'Last 7 days' : 'Last 30 days')) ?></span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
            </button>
            <div class="archive-filter-select-menu" id="trash-recent-filter-options" role="listbox" aria-labelledby="trash-recent-filter-label" hidden data-archive-select-menu>
                <div role="option" tabindex="-1" aria-selected="<?= $filters['recently_deleted'] === '' ? 'true' : 'false' ?>" data-archive-select-option data-value="">Any time</div>
                <div role="option" tabindex="-1" aria-selected="<?= $filters['recently_deleted'] === '24-hours' ? 'true' : 'false' ?>" data-archive-select-option data-value="24-hours">Last 24 hours</div>
                <div role="option" tabindex="-1" aria-selected="<?= $filters['recently_deleted'] === '7-days' ? 'true' : 'false' ?>" data-archive-select-option data-value="7-days">Last 7 days</div>
                <div role="option" tabindex="-1" aria-selected="<?= $filters['recently_deleted'] === '30-days' ? 'true' : 'false' ?>" data-archive-select-option data-value="30-days">Last 30 days</div>
            </div>
        </div>
        <div class="archive-filter-select trash-filter-select" data-archive-custom-select>
            <span id="trash-progress-filter-label">Progress when deleted</span>
            <select class="archive-native-filter-select" name="progress" data-archive-filter-control data-archive-native-select aria-hidden="true" tabindex="-1" hidden>
                <option value="" <?= $filters['progress'] === '' ? 'selected' : '' ?>>Any progress</option>
                <option value="none" <?= $filters['progress'] === 'none' ? 'selected' : '' ?>>No steps saved</option>
                <option value="partial" <?= $filters['progress'] === 'partial' ? 'selected' : '' ?>>In progress</option>
                <option value="complete" <?= $filters['progress'] === 'complete' ? 'selected' : '' ?>>All steps saved</option>
            </select>
            <button class="archive-filter-select-trigger" type="button" aria-labelledby="trash-progress-filter-label trash-progress-filter-value" aria-haspopup="listbox" aria-expanded="false" aria-controls="trash-progress-filter-options" data-archive-select-trigger>
                <span id="trash-progress-filter-value" data-archive-select-value><?= $filters['progress'] === '' ? 'Any progress' : ($filters['progress'] === 'none' ? 'No steps saved' : ($filters['progress'] === 'partial' ? 'In progress' : 'All steps saved')) ?></span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
            </button>
            <div class="archive-filter-select-menu" id="trash-progress-filter-options" role="listbox" aria-labelledby="trash-progress-filter-label" hidden data-archive-select-menu>
                <div role="option" tabindex="-1" aria-selected="<?= $filters['progress'] === '' ? 'true' : 'false' ?>" data-archive-select-option data-value="">Any progress</div>
                <div role="option" tabindex="-1" aria-selected="<?= $filters['progress'] === 'none' ? 'true' : 'false' ?>" data-archive-select-option data-value="none">No steps saved</div>
                <div role="option" tabindex="-1" aria-selected="<?= $filters['progress'] === 'partial' ? 'true' : 'false' ?>" data-archive-select-option data-value="partial">In progress</div>
                <div role="option" tabindex="-1" aria-selected="<?= $filters['progress'] === 'complete' ? 'true' : 'false' ?>" data-archive-select-option data-value="complete">All steps saved</div>
            </div>
        </div>
        <noscript><button type="submit">Apply filters</button></noscript>
    </form>
</section>

<section class="table-panel trash-panel" aria-label="Deleted reports">
    <?php if ($trashItems === []): ?>
        <div class="trash-empty-state"><span class="trash-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16M10 11v6m4-6v6M5 7l1 14h12l1-14M9 7V4h6v3"/></svg></span><h2><?= $hasFilters ? 'No matching reports' : 'Trash is empty' ?></h2><p><?= $hasFilters ? 'Try changing or clearing the selected filters.' : 'Deleted reports will appear here and can be restored for 30 days.' ?></p><?php if ($hasFilters): ?><a class="button secondary" href="/BTS/trash">Clear filters</a><?php else: ?><a class="button secondary" href="/BTS/archive">View Archived Reports</a><?php endif; ?></div>
    <?php else: ?>
        <div class="trash-table-wrap">
            <table class="archive-table trash-archive-table">
                <thead><tr><th scope="col">Process</th><th scope="col">Project Title</th><th scope="col">Process Type</th><th scope="col">Deleted</th><th scope="col">Progress when deleted</th><th scope="col">Permanent deletion</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($trashItems as $item): ?>
                        <?php
                        $processId = (int) ($item['process_id'] ?? 0);
                        $expiresAt = new \DateTimeImmutable((string) ($item['expires_at'] ?? 'now'), $timezone);
                        $daysLeft = max(1, (int) ceil(($expiresAt->getTimestamp() - time()) / 86400));
                        $status = ($item['completed_at'] ?? null) !== null ? 'Completed' : ((int) ($item['saved_count'] ?? 0) > 0 ? 'In progress' : 'Draft');
                        [$deletedDate, $deletedTime] = $formatDateTime($item['deleted_at'] ?? '');
                        $savedSteps = (int) ($item['saved_count'] ?? 0);
                        $totalSteps = (int) ($item['total_count'] ?? 0);
                        $progressPercent = $totalSteps > 0 ? min(100, (int) round(($savedSteps / $totalSteps) * 100)) : 0;
                        $processCode = (string) ($item['process_code'] ?? '');
                        ?>
                        <tr>
                            <td data-label="Process"><strong class="archive-process-code"><span class="trash-process-code"><?= $escape($processCode) ?></span></strong><small><?= $status ?></small></td>
                            <td data-label="Project Title" class="archive-project-title"><?= $escape($item['project_title'] ?? 'Not entered yet') ?></td>
                            <td data-label="Process Type"><?= $escape(ucwords(str_replace('-', ' ', (string) ($item['start_type'] ?? '')))) ?></td>
                            <td data-label="Deleted"><time class="archive-datetime"><span><?= $escape($deletedDate) ?></span><?php if ($deletedTime !== ''): ?><span><?= $escape($deletedTime) ?></span><?php endif; ?></time></td>
                            <td data-label="Progress when deleted">
                                <div class="archive-progress-text"><?= $savedSteps ?> / <?= $totalSteps ?> saved</div>
                                <div class="archive-progress-track" role="progressbar" aria-label="Saved report steps" aria-valuemin="0" aria-valuemax="<?= max(1, $totalSteps) ?>" aria-valuenow="<?= $savedSteps ?>"><span style="width: <?= $progressPercent ?>%"></span></div>
                            </td>
                            <td data-label="Permanent deletion"><div class="archive-datetime"><span class="trash-expiry-date"><?= $escape($formatDate($item['expires_at'] ?? '')) ?></span><span class="trash-days-left"><?= $daysLeft ?> <?= $daysLeft === 1 ? 'day' : 'days' ?> left</span></div></td>
                            <td data-label="Actions">
                                <div class="archive-actions trash-actions">
                                    <form method="post" action="/BTS/trash/<?= $processId ?>/restore" data-confirm-submit="Restore this report to Archived Reports?" data-confirm-title="Restore this report?" data-confirm-note="Its saved progress will be available again in Archived Reports." data-confirm-label="Restore report">
                                        <button type="submit" class="secondary">Restore</button>
                                    </form>
                                    <form method="post" action="/BTS/trash/<?= $processId ?>/delete" data-confirm-delete="true" data-confirm-title="Delete this report permanently?" data-confirm-message="This report and all of its saved report data will be permanently deleted." data-confirm-note="This cannot be undone." data-confirm-label="Delete permanently" data-confirm-danger="true">
                                        <button type="submit" class="secondary danger-button">Delete permanently</button>
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
<?php $content = ob_get_clean(); require __DIR__ . '/../layout/main.php'; ?>
