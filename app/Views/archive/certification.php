<?php
$certificationForm = $certificationForm ?? [];
$certificationErrors = $certificationErrors ?? [];
ob_start();
?>
<section class="page-header pbc-page-header">
    <div>
        <span class="pbc-report-kicker">Archive / Completed transactions</span>
        <h1>Punong Barangay Certification</h1>
        <p>Create one PDF certification from workflows completed within a selected date range.</p>
    </div>
    <a class="button secondary" href="/BTS/archive">Back to Archived Reports</a>
</section>

<section class="pbc-report-panel" aria-labelledby="pbc-report-title">
    <div class="pbc-report-heading">
        <div>
            <h2 id="pbc-report-title">Certification period</h2>
            <p>The generated report opens as a PDF preview in a new browser tab.</p>
        </div>
        <span class="pbc-report-format">PDF preview</span>
    </div>

    <?php if (!empty($certificationErrors['report'])): ?>
        <p class="notice error"><?= htmlspecialchars($certificationErrors['report']) ?></p>
    <?php endif; ?>

    <form
        class="pbc-report-form"
        method="post"
        action="/BTS/archive/certification/print"
        target="_blank"
        rel="noopener"
    >
        <label>
            <span>Completed from</span>
            <input type="date" name="start_date" value="<?= htmlspecialchars((string) ($certificationForm['start_date'] ?? '')) ?>" required>
            <?php if (!empty($certificationErrors['start_date'])): ?><small class="field-error"><?= htmlspecialchars($certificationErrors['start_date']) ?></small><?php endif; ?>
        </label>
        <label>
            <span>Completed through</span>
            <input type="date" name="end_date" value="<?= htmlspecialchars((string) ($certificationForm['end_date'] ?? '')) ?>" required>
            <?php if (!empty($certificationErrors['end_date'])): ?><small class="field-error"><?= htmlspecialchars($certificationErrors['end_date']) ?></small><?php endif; ?>
        </label>
        <label>
            <span>Certificate date</span>
            <input type="date" name="certificate_date" value="<?= htmlspecialchars((string) ($certificationForm['certificate_date'] ?? '')) ?>" required>
            <?php if (!empty($certificationErrors['certificate_date'])): ?><small class="field-error"><?= htmlspecialchars($certificationErrors['certificate_date']) ?></small><?php endif; ?>
        </label>
        <button type="submit">Generate PDF</button>
    </form>

    <p class="pbc-report-note">Rows are selected by workflow completion date. The printed process date is the date each workflow finished.</p>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout/main.php';
