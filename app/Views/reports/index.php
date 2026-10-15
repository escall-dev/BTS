<?php ob_start(); ?>
<section class="report-generation-workspace">
    <header class="workspace-heading">
        <h1>Report Generation</h1>
        <p>Select the report process to begin.</p>
    </header>

    <div class="action-grid report-start-grid">
        <button type="button" class="action-card" data-start-workflow data-start-action="/BTS/reports/start/purchase-request">
            <span>Generate Purchase Request</span>
            <small>Purchase Request, BAC, RFQ, Canvass, Purchase Order, Voucher, ORS, IAR, and RIS.</small>
        </button>
        <button type="button" class="action-card" data-start-workflow data-start-action="/BTS/reports/start/disbursement-voucher">
            <span>Generate Disbursement Voucher</span>
            <small>Voucher, ORS, IAR, and RIS as a separate process.</small>
        </button>
    </div>
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
    <p class="empty-state">Enable JavaScript to start a report process from this page.</p>
</noscript>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout/main.php';
