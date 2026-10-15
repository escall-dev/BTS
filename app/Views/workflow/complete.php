<?php ob_start(); ?>
<section class="complete-panel">
    <h1>Report Process Complete</h1>
    <p>Process <?= htmlspecialchars($process['process_code']) ?> has been saved to Archived Reports.</p>
    <div class="button-row">
        <a class="button" href="/BTS/archive">Go to Archived Reports</a>
        <a class="button secondary" href="/BTS/reports">Generate Another Report</a>
    </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout/main.php';
