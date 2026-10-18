<?php
$trashItems = $trashItems ?? [];
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
    <span class="trash-item-count"><?= count($trashItems) ?> <?= count($trashItems) === 1 ? 'report' : 'reports' ?></span>
</section>

<section class="table-panel trash-panel" aria-label="Deleted reports">
    <?php if ($trashItems === []): ?>
        <div class="trash-empty-state"><span class="trash-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16M10 11v6m4-6v6M5 7l1 14h12l1-14M9 7V4h6v3"/></svg></span><h2>Trash is empty</h2><p>Deleted reports will appear here and can be restored for 30 days.</p><a class="button secondary" href="/BTS/archive">View Archived Reports</a></div>
    <?php else: ?>
        <div class="trash-table-wrap">
            <table class="trash-table">
                <thead><tr><th scope="col">Process</th><th scope="col">Project Title</th><th scope="col">Process Type</th><th scope="col">Deleted</th><th scope="col">Permanent deletion</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($trashItems as $item): ?>
                        <?php
                        $processId = (int) ($item['process_id'] ?? 0);
                        $expiresAt = new \DateTimeImmutable((string) ($item['expires_at'] ?? 'now'), $timezone);
                        $daysLeft = max(1, (int) ceil(($expiresAt->getTimestamp() - time()) / 86400));
                        $status = ($item['completed_at'] ?? null) !== null ? 'Completed' : ((int) ($item['saved_count'] ?? 0) > 0 ? 'In progress' : 'Draft');
                        ?>
                        <tr>
                            <td data-label="Process"><strong class="trash-process-code"><?= $escape($item['process_code'] ?? '') ?></strong><small><?= $status ?></small></td>
                            <td data-label="Project Title" class="trash-project-title"><?= $escape($item['project_title'] ?? 'Not entered yet') ?></td>
                            <td data-label="Process Type"><?= $escape(ucwords(str_replace('-', ' ', (string) ($item['start_type'] ?? '')))) ?></td>
                            <td data-label="Deleted"><?= $escape($formatDate($item['deleted_at'] ?? '')) ?></td>
                            <td data-label="Permanent deletion"><strong><?= $escape($formatDate($item['expires_at'] ?? '')) ?></strong><small class="trash-days-left"><?= $daysLeft ?> <?= $daysLeft === 1 ? 'day' : 'days' ?> left</small></td>
                            <td data-label="Actions">
                                <div class="trash-actions">
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
