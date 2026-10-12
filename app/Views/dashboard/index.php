<?php ob_start(); ?>
<section class="page-header">
    <h1>Dashboard</h1>
    <p>Start a treasury report workflow.</p>
</section>

<section class="action-grid">
    <button type="button" class="action-card" data-start-workflow data-start-action="/BTS/reports/start/purchase-request">
        <span>Generate Purchase Request</span>
        <small>Runs the full Purchase Request to RIS workflow.</small>
    </button>
    <button type="button" class="action-card" data-start-workflow data-start-action="/BTS/reports/start/disbursement-voucher">
        <span>Generate Disbursement Voucher</span>
        <small>Starts directly at voucher processing.</small>
    </button>
</section>
<div class="workflow-start-modal" data-start-modal hidden>
    <div class="workflow-start-modal__backdrop" data-start-modal-close></div>
    <section class="workflow-start-modal__panel" role="dialog" aria-modal="true" aria-labelledby="workflow-start-title">
        <h2 id="workflow-start-title">Start Report Process</h2>
        <p>Once you proceed, this report process will be saved in Archived Reports. You can continue it later or delete it there.</p>
        <form method="post" action="/BTS/reports/start/purchase-request" data-start-modal-form>
            <div class="button-row">
                <button type="submit">Proceed</button>
                <button type="button" class="secondary" data-start-modal-close>Cancel</button>
            </div>
        </form>
    </section>
</div><noscript>
    <p class="empty-state">Enable JavaScript to start a report workflow from this page.</p>
</noscript>
<?php
$content = ob_get_clean();
$active = 'dashboard';
require __DIR__ . '/../layout/main.php';