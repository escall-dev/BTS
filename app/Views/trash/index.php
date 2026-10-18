<?php
$trashItems = $trashItems ?? [];
$filters = $filters ?? ['recently_deleted' => '', 'progress' => '', 'workflow' => '', 'process_number' => '', 'per_page' => '10', 'page' => 1];
$hasFilters = $hasFilters ?? false;
$totalTrashItems = $totalTrashItems ?? count($trashItems);
$trashPerPage = max(1, (int) ($trashPerPage ?? 10));
$trashPage = max(1, (int) ($trashPage ?? 1));
$trashPageCount = max(1, (int) ($trashPageCount ?? 1));
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
$trashPageSizes = ['5' => '5', '10' => '10', '15' => '15', '20' => '20', 'all' => 'All'];
$trashPageUrl = static function (int $page) use ($filters): string {
    $query = [];
    foreach (['recently_deleted', 'progress', 'workflow', 'process_number', 'per_page'] as $key) {
        if (trim((string) ($filters[$key] ?? '')) !== '') {
            $query[$key] = (string) $filters[$key];
        }
    }
    $query['page'] = max(1, $page);
    return '/BTS/trash?' . http_build_query($query);
};
$trashPageItems = [];
if ($trashPageCount <= 9) {
    $trashPageItems = range(1, $trashPageCount);
} else {
    $trashPageItems[] = 1;
    $windowStart = max(2, $trashPage - 2);
    $windowEnd = min($trashPageCount - 1, $trashPage + 2);
    if ($windowStart > 2) $trashPageItems[] = '…';
    for ($pageNumber = $windowStart; $pageNumber <= $windowEnd; $pageNumber++) $trashPageItems[] = $pageNumber;
    if ($windowEnd < $trashPageCount - 1) $trashPageItems[] = '…';
    $trashPageItems[] = $trashPageCount;
}
ob_start();
?>
<section class="page-header trash-page-header">
    <div>
        <h1>Trash</h1>
        <p>Restore deleted reports or remove them permanently.</p>
    </div>
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
    <form class="archive-filter-form trash-filter-form" method="get" action="/BTS/trash" data-archive-filter-form data-suggestions-url="/BTS/trash/suggestions">
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
        <div class="archive-filter-select archive-type-filter trash-filter-select" data-archive-custom-select>
            <span id="trash-type-filter-label">Process Type</span>
            <select class="archive-native-filter-select" name="workflow" data-archive-filter-control data-archive-native-select aria-hidden="true" tabindex="-1" hidden>
                <option value="" <?= $filters['workflow'] === '' ? 'selected' : '' ?>>All process types</option>
                <option value="purchase-request" <?= $filters['workflow'] === 'purchase-request' ? 'selected' : '' ?>>Purchase Request</option>
                <option value="disbursement-voucher" <?= $filters['workflow'] === 'disbursement-voucher' ? 'selected' : '' ?>>Disbursement Voucher</option>
            </select>
            <button class="archive-filter-select-trigger" type="button" aria-labelledby="trash-type-filter-label trash-type-filter-value" aria-haspopup="listbox" aria-expanded="false" aria-controls="trash-type-filter-options" data-archive-select-trigger>
                <span id="trash-type-filter-value" data-archive-select-value><?= $filters['workflow'] === 'purchase-request' ? 'Purchase Request' : ($filters['workflow'] === 'disbursement-voucher' ? 'Disbursement Voucher' : 'All process types') ?></span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
            </button>
            <div class="archive-filter-select-menu" id="trash-type-filter-options" role="listbox" aria-labelledby="trash-type-filter-label" hidden data-archive-select-menu>
                <div role="option" tabindex="-1" aria-selected="<?= $filters['workflow'] === '' ? 'true' : 'false' ?>" data-archive-select-option data-value="">All process types</div>
                <div role="option" tabindex="-1" aria-selected="<?= $filters['workflow'] === 'purchase-request' ? 'true' : 'false' ?>" data-archive-select-option data-value="purchase-request">Purchase Request</div>
                <div role="option" tabindex="-1" aria-selected="<?= $filters['workflow'] === 'disbursement-voucher' ? 'true' : 'false' ?>" data-archive-select-option data-value="disbursement-voucher">Disbursement Voucher</div>
            </div>
        </div>
        <div class="archive-filter-select archive-page-size archive-filter-page-size trash-filter-select" data-archive-custom-select>
            <span id="trash-page-size-label">Per page</span>
            <select class="archive-native-filter-select" name="per_page" data-archive-page-size data-archive-native-select aria-hidden="true" tabindex="-1" hidden>
                <?php foreach ($trashPageSizes as $pageSizeValue => $pageSizeLabel): ?>
                    <option value="<?= $escape($pageSizeValue) ?>" <?= (string) $filters['per_page'] === (string) $pageSizeValue ? 'selected' : '' ?>><?= $escape($pageSizeLabel) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="archive-filter-select-trigger" type="button" aria-labelledby="trash-page-size-label trash-page-size-value" aria-haspopup="listbox" aria-expanded="false" aria-controls="trash-page-size-options" data-archive-select-trigger>
                <span id="trash-page-size-value" data-archive-select-value><?= $escape($trashPageSizes[(string) $filters['per_page']] ?? '10') ?></span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
            </button>
            <div class="archive-filter-select-menu" id="trash-page-size-options" role="listbox" aria-labelledby="trash-page-size-label" hidden data-archive-select-menu>
                <?php foreach ($trashPageSizes as $pageSizeValue => $pageSizeLabel): ?>
                    <div role="option" tabindex="-1" aria-selected="<?= (string) $filters['per_page'] === (string) $pageSizeValue ? 'true' : 'false' ?>" data-archive-select-option data-value="<?= $escape($pageSizeValue) ?>"><?= $escape($pageSizeLabel) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="archive-search-control" data-archive-search-control>
            <input id="trash-process-search" type="search" name="process_number" value="<?= $escape($filters['process_number']) ?>" placeholder="Process number or project title" aria-label="Search by process number or project title in Trash" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="trash-search-suggestions" data-archive-process-search>
            <button class="archive-search-toggle" type="submit" aria-label="Search Trash by process number or project title" data-archive-search-toggle>
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 5 5"></path></svg>
            </button>
            <div class="archive-search-suggestions" id="trash-search-suggestions" role="listbox" aria-label="Trash search suggestions" hidden data-archive-search-suggestions></div>
        </div>
        <noscript><button type="submit">Apply filters</button></noscript>
    </form>
</section>

<div class="archive-results" data-archive-results aria-live="polite" aria-busy="false">
<section class="table-panel archive-panel trash-panel" aria-label="Deleted reports">
    <?php if ($trashItems === []): ?>
        <div class="trash-empty-state"><span class="trash-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16M10 11v6m4-6v6M5 7l1 14h12l1-14M9 7V4h6v3"/></svg></span><h2><?= $hasFilters ? 'No matching reports' : 'Trash is empty' ?></h2><p><?= $hasFilters ? 'Try changing or clearing the selected filters.' : 'Deleted reports will appear here and can be restored for 30 days.' ?></p><?php if ($hasFilters): ?><a class="button secondary" href="/BTS/trash">Clear filters</a><?php endif; ?></div>
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
        <?php if ($totalTrashItems > 0): ?>
            <?php $firstTrashItem = (($trashPage - 1) * $trashPerPage) + 1; $lastTrashItem = min($firstTrashItem + count($trashItems) - 1, $totalTrashItems); ?>
            <nav class="archive-pagination" aria-label="Trash pages">
                <?php if ($trashPageCount > 1): ?>
                    <div class="archive-pagination-controls">
                        <?php if ($trashPage > 1): ?><a class="archive-page-link archive-page-direction" href="<?= $escape($trashPageUrl($trashPage - 1)) ?>" rel="prev"><span aria-hidden="true">‹</span> Back</a><?php else: ?><span class="archive-page-link archive-page-direction is-disabled" aria-disabled="true"><span aria-hidden="true">‹</span> Back</span><?php endif; ?>
                        <div class="archive-page-numbers" aria-label="Page numbers">
                            <?php foreach ($trashPageItems as $pageItem): ?>
                                <?php if ($pageItem === '…'): ?><span class="archive-page-ellipsis" aria-hidden="true">…</span>
                                <?php elseif ($pageItem === $trashPage): ?><span class="archive-page-link is-current" aria-current="page" aria-label="Page <?= $pageItem ?>"><?= $pageItem ?></span>
                                <?php else: ?><a class="archive-page-link" href="<?= $escape($trashPageUrl($pageItem)) ?>" aria-label="Go to page <?= $pageItem ?>"><?= $pageItem ?></a><?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($trashPage < $trashPageCount): ?><a class="archive-page-link archive-page-direction" href="<?= $escape($trashPageUrl($trashPage + 1)) ?>" rel="next">Next <span aria-hidden="true">›</span></a><?php else: ?><span class="archive-page-link archive-page-direction is-disabled" aria-disabled="true">Next <span aria-hidden="true">›</span></span><?php endif; ?>
                        <form class="archive-page-jump" method="get" action="/BTS/trash" data-archive-page-jump>
                            <?php foreach (['recently_deleted', 'progress', 'workflow', 'per_page', 'process_number'] as $key): ?><input type="hidden" name="<?= $escape($key) ?>" value="<?= $escape($filters[$key] ?? '') ?>"><?php endforeach; ?>
                            <label for="trash-page-number">Page</label>
                            <input id="trash-page-number" type="number" name="page" min="1" max="<?= $trashPageCount ?>" value="<?= $trashPage ?>" required aria-label="Page number">
                            <button type="submit">Go</button>
                        </form>
                    </div>
                <?php endif; ?>
                <span class="archive-pagination-summary">Showing <?= $firstTrashItem ?>–<?= $lastTrashItem ?> of <?= $totalTrashItems ?></span>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layout/main.php'; ?>
