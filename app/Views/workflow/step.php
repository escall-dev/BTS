<?php
$process = $process ?? [];
$step = $step ?? [];
$steps = $steps ?? [];
$data = $data ?? [];
$savedStepData = $savedStepData ?? [];
$fields = $fields ?? [];
$errors = $errors ?? [];
$saved = $saved ?? false;
$printed = $printed ?? false;
$template = $template ?? null;
$templateSheet = $templateSheet ?? null;
$inheritedData = $inheritedData ?? [];
$systemData = $systemData ?? [];
$resolvedData = $resolvedData ?? [];
$purchaseHeaderFields = $purchaseHeaderFields ?? [];
$purchaseItemFields = $purchaseItemFields ?? [];
$purchaseItems = $purchaseItems ?? [];
$abstractSupplierBids = $abstractSupplierBids ?? [];
$selectedAbstractSupplier = $selectedAbstractSupplier ?? '';
$rfqSupplierBids = $rfqSupplierBids ?? [];
$rfqItemRows = $rfqItemRows ?? [];
$purchaseOrderItemRows = $purchaseOrderItemRows ?? [];
$requisitionIssueSlipItemRows = $requisitionIssueSlipItemRows ?? [];
$inspectionAcceptanceItemRows = $inspectionAcceptanceItemRows ?? [];
$disbursementFundOptions = $disbursementFundOptions ?? [];
$disbursementHasPurchaseOrderSource = $disbursementHasPurchaseOrderSource ?? false;
$disbursementTaxMetaKey = $disbursementTaxMetaKey ?? '__dv_whtax_enabled';
$disbursementTaxEnabled = $disbursementTaxEnabled ?? false;
$disbursementTaxTypes = $disbursementTaxTypes ?? [];
$disbursementLowestBidAmount = $disbursementLowestBidAmount ?? '';
$obligationSpecificFundGroups = $obligationSpecificFundGroups ?? [];
$obligationUsesGeneralFund = $obligationUsesGeneralFund ?? false;
$cleared = $cleared ?? false;
$sharedDateEnabled = $sharedDateEnabled ?? false;
$sharedDateValues = $sharedDateValues ?? [];
$dateReadonlyFields = $dateReadonlyFields ?? [];
$bacDateValue = $bacDateValue ?? '';
$bacDateReadonly = $bacDateReadonly ?? false;
$sharedDateMetaKey = '__use_pr_date_for_dates';

$currentStep = (string) ($process['current_step'] ?? '');
$stepKey = (string) ($step['step_key'] ?? '');
$reportType = (string) ($step['report_type'] ?? 'Report Step');
$processCode = (string) ($process['process_code'] ?? '');
$processId = (int) ($process['id'] ?? 0);
$isCompletedWorkflow = ($process['status'] ?? '') === 'completed';
$requestedCompletedEdit = !empty($editCompleted);
$editCompleted = $isCompletedWorkflow && $requestedCompletedEdit;
$isCurrent = $currentStep === $stepKey;
$isSaved = (int) ($step['is_saved'] ?? 0) === 1;
$isPrintable = (int) ($step['is_printable'] ?? 0) === 1;
$stepDataIsValid = $stepDataIsValid ?? $isSaved;
$canPrintReport = $isPrintable && $stepDataIsValid;
$canAdvance = !$isCompletedWorkflow && $isCurrent && $isSaved && $stepDataIsValid;
$isFinal = $stepKey === 'inspection-acceptance-report';
$isPurchaseRequest = $stepKey === 'purchase-request';
$isBacReport = $stepKey === 'bac-reports';
$isAbstractCanvass = $stepKey === 'abstract-of-canvass';
$isRequestForQuotation = $stepKey === 'request-for-quotation';
$isPurchaseOrder = $stepKey === 'purchase-order';
$isDisbursementVoucher = $stepKey === 'disbursement-voucher';
$isObligationReport = $stepKey === 'obligation-report';
$isRequisitionIssueSlip = $stepKey === 'requisition-issue-slip';
$isInspectionAcceptanceReport = $stepKey === 'inspection-acceptance-report';
$hasFields = $fields !== [];
$canEdit = (!$isCompletedWorkflow || $editCompleted) && ($isCurrent || $isSaved);
$showSavedPurchaseDetails = $isPurchaseRequest && $isSaved && $errors === [];
$pageHeaderClass = 'page-header' . ($isPurchaseRequest ? ' pr-page-header' : '');
$reportPanelClass = 'report-panel' . ($isPurchaseRequest ? ' purchase-request-panel' : '') . ($isPurchaseOrder ? ' purchase-order-panel' : '') . ($isDisbursementVoucher ? ' disbursement-voucher-panel' : '') . ($isObligationReport ? ' obligation-report-panel' : '') . ($isRequisitionIssueSlip ? ' requisition-issue-slip-panel' : '') . ($isInspectionAcceptanceReport ? ' inspection-acceptance-panel' : '');
$workflowActionsClass = 'workflow-actions' . ($isPurchaseRequest ? ' pr-workflow-actions' : '');
$printFormAttributes = ' target="_blank" rel="noopener"';
$editQuery = $editCompleted ? '?edit=1' : '';
$clearUrl = '/BTS/reports/process/' . $processId . '/step/' . $stepKey . '/clear' . $editQuery;
$fieldType = static function (string $key): string {
    $lowerKey = strtolower($key);
    if (str_contains($lowerKey, 'date') || str_ends_with($lowerKey, 'date')) {
        return 'date';
    }

    if (preg_match('/(qty|cost|amount|aop|bid)/i', $key)) {
        return 'number';
    }

    return 'text';
};
$fieldStep = static function (string $key): string {
    return preg_match('/(qty|cost|amount|aop|bid)/i', $key) ? ' step="0.01"' : '';
};
$hoverNoteAttributes = static function (string $title, string $body): string {
    return ' class="hover-note-anchor" data-hover-note-title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" data-hover-note-body="' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '"';
};
$printActionNoteTitle = $canPrintReport
    ? 'This report is ready to print.'
    : 'Save this report to unlock printing.';
$printActionNoteBody = $canPrintReport
    ? 'Print opens the generated report in a new tab and records the print action.'
    : 'Unsaved edits stay on this page until the report passes validation.';
$nextActionNoteTitle = $canAdvance
    ? 'Continue to the next report.'
    : 'Save this report to unlock the next step.';
$nextActionNoteBody = $canAdvance
    ? 'This moves the process forward after the saved report is ready.'
    : 'Complete the required fields before continuing.';
$printHoverNoteAttributes = $hoverNoteAttributes($printActionNoteTitle, $printActionNoteBody);
$nextHoverNoteAttributes = $hoverNoteAttributes($nextActionNoteTitle, $nextActionNoteBody);
$fieldValueFor = static function (string $fieldKey) use ($savedStepData, $resolvedData, $errors, $data, $stepKey) {
    if (!empty($errors) && array_key_exists($fieldKey, $data)) {
        return $data[$fieldKey];
    }
    if (
        $stepKey === 'requisition-issue-slip'
        && $fieldKey === 'PURPOSE'
        && (string) ($resolvedData['__ris_purpose_source'] ?? '') === 'pr'
        && array_key_exists($fieldKey, $resolvedData)
    ) {
        return $resolvedData[$fieldKey];
    }
    if (array_key_exists($fieldKey, $savedStepData)) {
        return $savedStepData[$fieldKey];
    }
    if (array_key_exists($fieldKey, $resolvedData)) {
        return $resolvedData[$fieldKey];
    }
    if (array_key_exists($fieldKey, $data)) {
        return $data[$fieldKey];
    }
    return '';
};
$risPurposeUsesPrPurpose = $isRequisitionIssueSlip
    && empty($errors)
    && trim((string) ($data['PURPOSE'] ?? '')) === ''
    && (string) ($resolvedData['__ris_purpose_source'] ?? '') === 'pr'
    && trim((string) ($resolvedData['PURPOSE'] ?? '')) !== '';

ob_start();
?>
<section class="<?= htmlspecialchars($pageHeaderClass) ?>">
    <h1><?= htmlspecialchars($reportType) ?></h1>
    <p>Process <?= htmlspecialchars($processCode) ?></p>
</section>

<?php if (!empty($saved)): ?>
    <div class="toast success" data-toast>Report step saved. Print and next actions are now available.</div>
<?php endif; ?>
<?php if (!empty($printed)): ?>
    <div class="toast success" data-toast>Print action recorded.</div>
<?php endif; ?>
<?php if (!empty($cleared)): ?>
    <div class="toast success" data-toast>Report fields cleared.</div>
<?php endif; ?>
<?php if (!empty($errors['print'])): ?>
    <div class="toast error" data-toast><?= htmlspecialchars($errors['print']) ?></div>
<?php endif; ?>
<?php if ($isCompletedWorkflow && !$editCompleted): ?>
    <p class="notice completed-review-notice">This process is complete and is open in read-only review mode. <a href="/BTS/reports/process/<?= $processId ?>/step/<?= htmlspecialchars($stepKey) ?>?edit=1">Edit this completed report</a></p>
<?php elseif ($isCompletedWorkflow && $editCompleted): ?>
    <p class="notice completed-review-notice is-editing">You are editing a completed process. Changes will update the saved report. <a href="/BTS/reports/process/<?= $processId ?>/step/<?= htmlspecialchars($stepKey) ?>">Return to read-only review</a></p>
<?php elseif (!$isCurrent && $isSaved): ?>
    <p class="notice">You are reviewing a completed report. You may update and save it again, then return to the current step from the list.</p>
<?php endif; ?>

<div class="workflow-layout">
    <aside class="step-list" aria-label="Report process steps">
        <?php foreach ($steps as $workflowStep): ?>
            <?php
            $stepClass = [];
            $stepIsCurrent = $workflowStep['step_key'] === $currentStep;
            $stepIsViewing = $workflowStep['step_key'] === $stepKey;
            $stepIsSaved = (int) $workflowStep['is_saved'] === 1;
            $stepCanOpen = $stepIsCurrent || $stepIsSaved;

            if ($stepIsViewing) {
                $stepClass[] = 'is-viewing';
            }

            if ($stepIsCurrent) {
                $stepClass[] = 'is-current';
            }
            if ($stepIsSaved) {
                $stepClass[] = 'is-complete';
            }
            if (!$stepCanOpen) {
                $stepClass[] = 'is-locked';
            }
            ?>
            <?php if ($stepCanOpen): ?>
                <a class="<?= htmlspecialchars(implode(' ', $stepClass)) ?>" href="/BTS/reports/process/<?= $processId ?>/step/<?= htmlspecialchars($workflowStep['step_key']) ?>">
                    <span><?= (int) $workflowStep['step_order'] ?></span>
                    <?= htmlspecialchars($workflowStep['report_type']) ?>
                </a>
            <?php else: ?>
                <div class="<?= htmlspecialchars(implode(' ', $stepClass)) ?>" aria-disabled="true">
                    <span><?= (int) $workflowStep['step_order'] ?></span>
                    <?= htmlspecialchars($workflowStep['report_type']) ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </aside>

    <section class="<?= htmlspecialchars($reportPanelClass) ?>">
        <?php if ($template === null && !$isPurchaseRequest && !$isBacReport && !$isAbstractCanvass && !$isRequestForQuotation && !$isPurchaseOrder && !$isDisbursementVoucher && !$isObligationReport): ?>
            <div class="preview-box">
                <div>
                    <strong><?= htmlspecialchars($reportType) ?> Preview</strong>
                    <?php if ($templateSheet): ?>
                        <p>Mapped to Excel sheet: <strong><?= htmlspecialchars($templateSheet) ?></strong></p>
                    <?php else: ?>
                        <p>Template preview placeholder. Exact report fields will be mapped from the provided templates later.</p>
                    <?php endif; ?>
                </div>
                <dl class="preview-data">
                    <div>
                        <dt>Status</dt>
                        <dd><?= $isSaved ? 'Saved' : 'Draft' ?></dd>
                    </div>
                    <div>
                        <dt>Printable</dt>
                        <dd><?= $isPrintable ? 'Yes' : 'No' ?></dd>
                    </div>
                    <?php if ($templateSheet): ?>
                        <div>
                            <dt>Template Sheet</dt>
                            <dd><?= htmlspecialchars($templateSheet) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($data as $key => $value): ?>
                        <?php if (!is_scalar($value) && $value !== null) { continue; } ?>
                        <div>
                            <dt><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) $key))) ?></dt>
                            <dd><?= htmlspecialchars((string) $value) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </div>
        <?php endif; ?>

        <?php if (!$isPurchaseRequest && !$isBacReport && !$isRequisitionIssueSlip && !$isInspectionAcceptanceReport && $inheritedData !== []): ?>
            <div class="mapping-flags" aria-label="Report data status">
                <span class="saved-details-note inherited-data-note">
                    <?= $isBacReport ? 'Using inherited P.R. data' : 'Using inherited report data' ?>
                </span>
            </div>
        <?php endif; ?>

        <?php if ($isPurchaseRequest): ?>
            <form class="form-stack purchase-request-form" method="post" action="/BTS/reports/process/<?= $processId ?>/step/purchase-request/save<?= $editQuery ?>" data-pr-form data-clear-url="<?= htmlspecialchars($clearUrl) ?>">
                <section class="form-section pr-form-section pr-details-section">
                    <div class="section-heading-row pr-section-heading procurement-section-header">
                        <h2>Purchase Request Details</h2>
                        <?php if ($showSavedPurchaseDetails): ?>
                            <span class="saved-details-note">Current saved details for printing</span>
                        <?php endif; ?>
                    </div>
                    <div class="form-grid pr-details-grid">
                        <?php foreach ($purchaseHeaderFields as $key => $label): ?>
                            <?php $fieldValue = array_key_exists($key, $data) ? $data[$key] : (empty($errors) ? ($resolvedData[$key] ?? '') : ''); ?>
                            <?php if ($key === 'DatePR'): ?>
                                <div class="pr-date-field">
                                    <div class="pr-date-heading">
                                        <div class="field-heading-text">
                                            <span><?= htmlspecialchars($label) ?></span>
                                        </div>
                                        <label
                                            class="toggle-control pr-date-toggle"
                                            data-tooltip="When on, this P.R. Date fills BAC Reports date fields only."
                                            aria-label="Use this P.R. Date for BAC Reports date fields"
                                        >
                                            <input
                                                type="checkbox"
                                                name="<?= htmlspecialchars($sharedDateMetaKey) ?>"
                                                value="1"
                                                <?= $sharedDateEnabled ? 'checked' : '' ?>
                                                <?= $canEdit ? '' : 'disabled' ?>
                                            >
                                            <span class="toggle-switch" aria-hidden="true"></span>
                                        </label>
                                    </div>
                                    <input
                                        type="<?= $fieldType((string) $key) ?>"
                                        name="<?= htmlspecialchars($key) ?>"
                                        value="<?= htmlspecialchars(is_scalar($fieldValue) || $fieldValue === null ? (string) $fieldValue : '') ?>"
                                        <?= $canEdit ? '' : 'disabled' ?>
                                        required
                                    >
                                    <?php if (!empty($errors[$key])): ?>
                                        <small class="field-error"><?= htmlspecialchars($errors[$key]) ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($key === 'PRPURPOSE'): ?>
                                <label class="pr-purpose-field">
                                    <?= htmlspecialchars($label) ?>
                                    <textarea
                                        name="<?= htmlspecialchars($key) ?>"
                                        <?= $canEdit ? '' : 'disabled' ?>
                                        required
                                    ><?= htmlspecialchars(is_scalar($fieldValue) || $fieldValue === null ? (string) $fieldValue : '') ?></textarea>
                                    <?php if (!empty($errors[$key])): ?>
                                        <small class="field-error"><?= htmlspecialchars($errors[$key]) ?></small>
                                    <?php endif; ?>
                                </label>
                            <?php else: ?>
            
                    <label>
                                    <?= htmlspecialchars($label) ?>
                                    <input
                                        type="<?= $fieldType((string) $key) ?>"
                                        name="<?= htmlspecialchars($key) ?>"
                                        value="<?= htmlspecialchars(is_scalar($fieldValue) || $fieldValue === null ? (string) $fieldValue : '') ?>"
                                        <?= $canEdit ? '' : 'disabled' ?>
                                        required
                                    >
                                    <?php if (!empty($errors[$key])): ?>
                                        <small class="field-error"><?= htmlspecialchars($errors[$key]) ?></small>
                                    <?php endif; ?>
                                </label>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="form-section pr-form-section pr-items-section">
                    <div class="section-heading-row pr-section-heading procurement-section-header">
                        <h2>Requisition Items</h2>
                        <?php if (!empty($errors['items']) && is_string($errors['items'])): ?>
                            <small class="field-error"><?= htmlspecialchars($errors['items']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="pr-items-table-wrap">
                        <table class="pr-items-table">
                            <thead>
                                <tr>
                                    <th>Item Number</th>
                                    <th>Quantity</th>
                                    <th>Unit</th>
                                    <th>Particulars</th>
                                    <th>Estimated Unit Cost</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-pr-items>
                                <?php foreach ($purchaseItems as $index => $item): ?>
                                    <tr data-pr-row>
                                        <?php foreach ($purchaseItemFields as $key => $label): ?>
                                            <?php
                                            $dbKey = match ($key) {
                                                'INPR' => 'item_no',
                                                'PRQTY' => 'quantity',
                                                'UOE' => 'unit_of_measurement',
                                                'PRPART' => 'particulars',
                                                'EUC' => 'estimated_unit_cost',
                                                default => $key,
                                            };
                                            $itemValue = $key === 'INPR' ? (string) ($index + 1) : ($item[$key] ?? $item[$dbKey] ?? '');
                                            if ($key === 'PRQTY' && is_scalar($itemValue)) {
                                                $quantityValue = trim((string) $itemValue);
                                                $itemValue = preg_match('/^-?\d+\.\d+$/', $quantityValue) === 1
                                                    ? rtrim(rtrim($quantityValue, '0'), '.')
                                                    : $quantityValue;
                                            }
                                            ?>
                                            <td>
                                                <input
                                                    type="<?= $fieldType($key) ?>"
                                                    name="items[<?= (int) $index ?>][<?= htmlspecialchars($key) ?>]"
                                                    value="<?= htmlspecialchars((string) $itemValue) ?>"
                                                    <?= $fieldStep($key) ?>
                                                    <?= $key === 'INPR' ? 'readonly data-pr-item-number' : '' ?>
                                                    <?= $canEdit ? '' : 'disabled' ?>
                                                    required
                                                >
                                                <?php if (!empty($errors['items'][$index][$key])): ?>
                                                    <small class="field-error"><?= htmlspecialchars($errors['items'][$index][$key]) ?></small>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td>
                                            <button type="button" class="secondary compact" data-remove-pr-row <?= $canEdit ? '' : 'disabled' ?>>Remove</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="secondary pr-add-item-button" data-add-pr-row <?= $canEdit ? '' : 'disabled' ?>>Add Item Row</button>
                </section>

                <div class="form-actions report-form-actions">
                    <button type="submit" class="pr-save-button" <?= $canEdit ? '' : 'disabled' ?>>Save Purchase Request</button>
                    <button type="submit" class="secondary" formaction="<?= htmlspecialchars($clearUrl) ?>" formnovalidate data-clear-step <?= $canEdit ? '' : 'disabled' ?>>Clear Fields</button>
                </div>
            </form>
        <?php elseif ($isAbstractCanvass): ?>
            <?php
            $abstractBidRows = [];
            $validAbstractBids = [];
            foreach ($abstractSupplierBids as $index => $bid) {
                $supplier = trim((string) ($bid['SUPPNAME'] ?? ''));
                $address = trim((string) ($bid['SUPPADD'] ?? $bid['LBADD'] ?? ''));
                $amount = trim((string) ($bid['AOP'] ?? ''));
                if ($supplier !== '') {
                    $abstractBidRows[] = ['index' => $index, 'SUPPNAME' => $supplier, 'SUPPADD' => $address, 'AOP' => $amount];
                }
                if ($supplier !== '' && $amount !== '' && is_numeric($amount)) {
                    $validAbstractBids[] = ['SUPPNAME' => $supplier, 'SUPPADD' => $address, 'AOP' => $amount];
                }
            }
            usort($validAbstractBids, static fn (array $left, array $right): int => (float) $left['AOP'] <=> (float) $right['AOP']);
            if ($selectedAbstractSupplier === '' && $validAbstractBids !== []) {
                $selectedAbstractSupplier = $validAbstractBids[0]['SUPPNAME'];
            }
            $selectedAbstractAmount = '';
            foreach ($validAbstractBids as $bid) {
                if ($bid['SUPPNAME'] === $selectedAbstractSupplier) {
                    $selectedAbstractAmount = $bid['AOP'];
                    break;
                }
            }
            ?>
            <form class="form-stack abstract-canvass-form" method="post" action="/BTS/reports/process/<?= $processId ?>/step/<?= htmlspecialchars($stepKey) ?>/save<?= $editQuery ?>" data-clear-url="<?= htmlspecialchars($clearUrl) ?>" data-abstract-form>
                <div class="section-heading-row report-form-heading procurement-section-header">
                    <h2>Report Fields</h2>
                    <?php if ($isSaved && $errors === []): ?>
                        <span class="saved-details-note">Current saved data for printing</span>
                    <?php endif; ?>
                </div>

                <section class="form-section abstract-selection-section">
                    <div class="section-heading-row pr-section-heading procurement-section-header">
                        <h2>Recommended Supplier</h2>
                    </div>
                    <div class="form-grid two-columns abstract-recommendation-grid">
                        <label>
                            Lowest Bid Supplier Name
                            <select name="LBSUPPNAME" data-abstract-selected-supplier <?= $canEdit ? '' : 'disabled' ?> required>
                                <option value="">Select supplier</option>
                                <?php foreach ($validAbstractBids as $bid): ?>
                                    <option value="<?= htmlspecialchars($bid['SUPPNAME']) ?>" data-bid-amount="<?= htmlspecialchars($bid['AOP']) ?>" <?= $bid['SUPPNAME'] === $selectedAbstractSupplier ? 'selected' : '' ?>><?= htmlspecialchars($bid['SUPPNAME']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($errors['LBSUPPNAME'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['LBSUPPNAME']) ?></small>
                            <?php endif; ?>
                        </label>

                        <label>
                            Lowest Bid Amount
                            <input
                                type="number"
                                step="0.01"
                                name="LOWESTBIDAMOUNT"
                                value="<?= htmlspecialchars($selectedAbstractAmount) ?>"
                                data-abstract-selected-amount
                                readonly
                                required
                            >
                        </label>
                    </div>
                </section>

                <section class="form-section abstract-supplier-section">
                    <div class="section-heading-row pr-section-heading procurement-section-header">
                        <h2>Supplier Proposals</h2>
                        <?php if (!empty($errors['supplier_bids']) && is_string($errors['supplier_bids'])): ?>
                            <small class="field-error"><?= htmlspecialchars($errors['supplier_bids']) ?></small>
                        <?php endif; ?>
                    </div>
                    <?php if ($abstractBidRows === []): ?>
                        <div class="notice">No saved RFQ suppliers are available yet.</div>
                    <?php else: ?>
                        <div class="abstract-bids-table-wrap">
                            <table class="abstract-bids-table">
                                <thead>
                                    <tr>
                                        <th>Supplier Name</th>
                                        <th>Address</th>
                                        <th>Amount of Proposal</th>
                                    </tr>
                                </thead>
                                <tbody data-abstract-bids>
                                    <?php foreach ($abstractBidRows as $bid): ?>
                                        <tr data-abstract-row>
                                            <td><?= htmlspecialchars($bid['SUPPNAME']) ?></td>
                                            <td><?= htmlspecialchars($bid['SUPPADD']) ?></td>
                                            <td>
                                                <input type="hidden" value="<?= htmlspecialchars($bid['SUPPNAME']) ?>" data-abstract-supplier-name>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    name="supplier_bids[<?= (int) $bid['index'] ?>][AOP]"
                                                    value="<?= htmlspecialchars($bid['AOP']) ?>"
                                                    data-abstract-bid-amount
                                                    <?= $canEdit ? '' : 'disabled' ?>
                                                    required
                                                >
                                                <?php if (!empty($errors['supplier_bids'][$bid['index']]['AOP'])): ?>
                                                    <small class="field-error"><?= htmlspecialchars($errors['supplier_bids'][$bid['index']]['AOP']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <div class="form-actions report-form-actions">
                    <button type="submit" <?= $canEdit ? '' : 'disabled' ?>>Save Report</button>
                    <button type="submit" class="secondary" formaction="<?= htmlspecialchars($clearUrl) ?>" formnovalidate data-clear-step <?= $canEdit ? '' : 'disabled' ?>>Clear Fields</button>
                </div>
            </form>
        <?php elseif ($isRequestForQuotation): ?>
            <form class="form-stack request-quotation-form" method="post" action="/BTS/reports/process/<?= $processId ?>/step/<?= htmlspecialchars($stepKey) ?>/save<?= $editQuery ?>" data-clear-url="<?= htmlspecialchars($clearUrl) ?>" data-abstract-form>
                <div class="section-heading-row report-form-heading procurement-section-header">
                    <h2>Report Fields</h2>
                    <?php if ($isSaved && $errors === []): ?>
                        <span class="saved-details-note">Current saved data for printing</span>
                    <?php endif; ?>
                </div>

                <section class="form-section rfq-items-section">
                    <div class="section-heading-row pr-section-heading procurement-section-header">
                        <h2>Inherited P.R. Items</h2>
                        <?php if (!empty($errors['rfq_items']) && is_string($errors['rfq_items'])): ?>
                            <small class="field-error"><?= htmlspecialchars($errors['rfq_items']) ?></small>
                        <?php endif; ?>
                    </div>
                    <?php if ($rfqItemRows === []): ?>
                        <div class="notice">No Purchase Request items are available for this RFQ.</div>
                    <?php else: ?>
                        <div class="rfq-items-table-wrap">
                            <table class="rfq-items-table">
                                <thead>
                                    <tr>
                                        <th>Quantity</th>
                                        <th>Unit</th>
                                        <th>Particulars</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rfqItemRows as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string) ($row['PRQTY'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string) ($row['UOE'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string) ($row['PRPART'] ?? '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="form-section abstract-supplier-section rfq-supplier-section">
                    <div class="section-heading-row pr-section-heading procurement-section-header">
                        <h2>Supplier Proposals</h2>
                        <?php if (!empty($errors['supplier_bids']) && is_string($errors['supplier_bids'])): ?>
                            <small class="field-error"><?= htmlspecialchars($errors['supplier_bids']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="abstract-bids-table-wrap">
                        <table class="abstract-bids-table rfq-supplier-table">
                            <thead>
                                <tr>
                                    <th>Supplier Name</th>
                                    <th>Address</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-abstract-bids>
                                <?php foreach ($rfqSupplierBids as $index => $bid): ?>
                                    <tr data-abstract-row>
                                        <td>
                                            <input
                                                type="text"
                                                name="supplier_bids[<?= (int) $index ?>][SUPPNAME]"
                                                value="<?= htmlspecialchars((string) ($bid['SUPPNAME'] ?? '')) ?>"
                                                data-abstract-supplier-name
                                                <?= $canEdit ? '' : 'disabled' ?>
                                                required
                                            >
                                            <?php if (!empty($errors['supplier_bids'][$index]['SUPPNAME'])): ?>
                                                <small class="field-error"><?= htmlspecialchars($errors['supplier_bids'][$index]['SUPPNAME']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <input
                                                type="text"
                                                name="supplier_bids[<?= (int) $index ?>][SUPPADD]"
                                                value="<?= htmlspecialchars((string) ($bid['SUPPADD'] ?? $bid['LBADD'] ?? '')) ?>"
                                                <?= $canEdit ? '' : 'disabled' ?>
                                                required
                                            >
                                            <?php if (!empty($errors['supplier_bids'][$index]['SUPPADD'])): ?>
                                                <small class="field-error"><?= htmlspecialchars($errors['supplier_bids'][$index]['SUPPADD']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="secondary compact" data-remove-abstract-row <?= $canEdit ? '' : 'disabled' ?>>Remove</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="secondary" data-add-abstract-row <?= $canEdit ? '' : 'disabled' ?>>Add Supplier Row</button>
                </section>

                <div class="form-actions report-form-actions">
                    <button type="submit" <?= $canEdit ? '' : 'disabled' ?>>Save Report</button>
                    <button type="submit" class="secondary" formaction="<?= htmlspecialchars($clearUrl) ?>" formnovalidate data-clear-step <?= $canEdit ? '' : 'disabled' ?>>Clear Fields</button>
                </div>
            </form>
        <?php elseif ($isPurchaseOrder): ?>
            <form class="form-stack purchase-order-form" data-po-form method="post" action="/BTS/reports/process/<?= $processId ?>/step/<?= htmlspecialchars($stepKey) ?>/save<?= $editQuery ?>" data-clear-url="<?= htmlspecialchars($clearUrl) ?>">
                <div class="section-heading-row report-form-heading procurement-section-header">
                    <h2>Purchase Order Details</h2>
                    <?php if ($isSaved && $errors === []): ?>
                        <span class="saved-details-note">Current saved data for printing</span>
                    <?php endif; ?>
                </div>

                <section class="form-section purchase-order-details-section">
                    <div class="form-grid two-columns purchase-order-grid">
                        <?php
                        $poRenderField = static function (string $fieldKey, string $fieldLabel, string $fieldClass = '', ?string $inputType = null, string $sourceNote = '') use ($fieldValueFor, $fieldType, $fieldStep, $canEdit, $errors): void {
                            $fieldValue = $fieldValueFor($fieldKey);
                            $classAttribute = $fieldClass !== '' ? ' class="' . htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') . '"' : '';
                            ?>
                            <label<?= $classAttribute ?>>
                                <span class="field-label-row">
                                    <span class="field-label-text">
                                        <span><?= htmlspecialchars($fieldLabel) ?></span>
                                        <?php if ($sourceNote !== ''): ?>
                                            <span class="po-field-source-note"><?= htmlspecialchars($sourceNote) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                                <input
                                    type="<?= htmlspecialchars($inputType ?? $fieldType($fieldKey)) ?>"
                                    name="<?= htmlspecialchars($fieldKey) ?>"
                                    value="<?= htmlspecialchars(is_scalar($fieldValue) || $fieldValue === null ? (string) $fieldValue : '') ?>"
                                    <?= $fieldStep($fieldKey) ?>
                                    <?= $canEdit ? '' : 'disabled' ?>
                                    required
                                >
                                <?php if (!empty($errors[$fieldKey])): ?>
                                    <small class="field-error"><?= htmlspecialchars($errors[$fieldKey]) ?></small>
                                <?php endif; ?>
                            </label>
                            <?php
                        };
                        ?>
                        <?php $poRenderField('PAYEE', $fields['PAYEE'] ?? 'Supplier', 'po-source-field', null, 'From canvass'); ?>
                        <?php $poRenderField('PONUM', $fields['PONUM'] ?? 'P.O. Number', 'po-number-field', null, 'Auto-numbered'); ?>
                        <?php $poRenderField('PAYEE_ADDRESS', $fields['PAYEE_ADDRESS'] ?? 'Address', 'po-source-field', null, 'From canvass'); ?>
                        <?php $poRenderField('PODATE', $fields['PODATE'] ?? 'P.O. Date'); ?>
                        <?php $poRenderField('PODOD', $fields['PODOD'] ?? 'Date of Delivery', '', 'date'); ?>
                        <?php $poRenderField('POPOD', $fields['POPOD'] ?? 'Place of Delivery'); ?>
                        <?php foreach (['PODT' => 'Delivery Term', 'POPT' => 'Payment Term'] as $termKey => $termLabel): ?>
                            <label>
                                <span class="field-label-row"><span class="field-label-text"><span><?= htmlspecialchars($fields[$termKey] ?? $termLabel) ?></span></span></span>
                                <select name="<?= htmlspecialchars($termKey) ?>" <?= $canEdit ? '' : 'disabled' ?> required>
                                    <option value="">Select <?= htmlspecialchars(strtolower($termLabel)) ?></option>
                                    <?php foreach (['Full', 'Partial'] as $termOption): ?>
                                        <option value="<?= $termOption ?>" <?= (string) $fieldValueFor($termKey) === $termOption ? 'selected' : '' ?>><?= $termOption ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!empty($errors[$termKey])): ?><small class="field-error"><?= htmlspecialchars($errors[$termKey]) ?></small><?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="form-section purchase-order-payment-mode-section">
                    <div class="section-heading-row pr-section-heading procurement-section-header">
                        <h2>Mode of Payment</h2>
                        <?php if (!empty($errors['PO_MODE_OF_PAYMENT'])): ?>
                            <small class="field-error"><?= htmlspecialchars($errors['PO_MODE_OF_PAYMENT']) ?></small>
                        <?php endif; ?>
                    </div>
                    <?php $selectedPaymentMode = (string) $fieldValueFor('PO_MODE_OF_PAYMENT'); ?>
                    <div class="po-payment-mode-options" role="group" aria-label="Mode of Payment">
                        <?php foreach (['BID' => 'Bidding', 'NEG' => 'Negotiated', 'SHOP' => 'Shopping'] as $modeValue => $modeLabel): ?>
                            <label class="po-payment-mode-option">
                                <input
                                    type="checkbox"
                                    name="PO_MODE_OF_PAYMENT[]"
                                    value="<?= $modeValue ?>"
                                    data-po-payment-mode
                                    <?= $selectedPaymentMode === $modeValue ? 'checked' : '' ?>
                                    <?= $canEdit ? '' : 'disabled' ?>
                                >
                                <span><?= $modeLabel ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="form-section purchase-order-items-section">
                    <div class="section-heading-row pr-section-heading procurement-section-header">
                        <div class="po-inherited-heading-copy">
                            <h2>Inherited P.R. Items</h2>
                            <span>Quantity, unit, and particulars are carried over from the Purchase Request.</span>
                        </div>
                        <span class="po-inherited-badge">From Purchase Request</span>
                    </div>
                    <?php if ($purchaseOrderItemRows === []): ?>
                        <div class="notice">No Purchase Request items are available for this Purchase Order.</div>
                    <?php else: ?>
                        <div class="po-items-table-wrap">
                            <table class="po-items-table">
                                <thead>
                                    <tr>
                                        <th>Unit</th>
                                        <th>Particulars</th>
                                        <th>Quantity</th>
                                        <th>Unit Cost</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($purchaseOrderItemRows as $index => $row): ?>
                                        <tr data-po-row data-po-quantity="<?= htmlspecialchars((string) ($row['PRQTY'] ?? '')) ?>">
                                            <td><?= htmlspecialchars((string) ($row['UOE'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string) ($row['PRPART'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string) ($row['PRQTY'] ?? '')) ?></td>
                                            <td>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    name="po_items[<?= (int) $index ?>][POUNITCOST]"
                                                    value="<?= htmlspecialchars((string) ($row['POUNITCOST'] ?? '')) ?>"
                                                    data-po-unit-cost
                                                    <?= $canEdit ? '' : 'disabled' ?>
                                                    required
                                                >
                                                <?php if (!empty($errors['po_items'][$index]['POUNITCOST'])): ?>
                                                    <small class="field-error"><?= htmlspecialchars($errors['po_items'][$index]['POUNITCOST']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    name="po_items[<?= (int) $index ?>][POAMOUNT]"
                                                    value="<?= htmlspecialchars((string) ($row['POAMOUNT'] ?? '')) ?>"
                                                    data-po-amount
                                                    readonly
                                                >
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <div class="form-actions report-form-actions">
                    <button type="submit" <?= $canEdit ? '' : 'disabled' ?>>Save Purchase Order</button>
                    <button type="submit" class="secondary" formaction="<?= htmlspecialchars($clearUrl) ?>" formnovalidate data-clear-step <?= $canEdit ? '' : 'disabled' ?>>Clear Fields</button>
                </div>
            </form>
        <?php elseif ($isDisbursementVoucher): ?>
            <?php
            $dvTaxEnabled = !empty($errors)
                ? !empty($data[$disbursementTaxMetaKey])
                : (bool) $disbursementTaxEnabled;
            $dvSelectedTaxType = trim((string) $fieldValueFor('DV_TAX_TYPE'));
            if ($dvSelectedTaxType === '' && $dvTaxEnabled) {
                $dvSelectedTaxType = 'Withholding Tax';
            }
            $dvParticularsBody = \App\Models\Workflow::disbursementParticularsBody((string) $fieldValueFor('PARTICULARS'));
            $dvGrossValue = str_replace(',', '', trim((string) $fieldValueFor('GROSS')));
            $dvLowestBidValue = str_replace(',', '', trim((string) $disbursementLowestBidAmount));
            $dvGrossMismatch = $dvGrossValue !== '' && $dvLowestBidValue !== '' && is_numeric($dvGrossValue) && is_numeric($dvLowestBidValue)
                && abs((float) $dvGrossValue - (float) $dvLowestBidValue) >= 0.005;
            $dvRenderInput = static function (string $fieldKey, string $fieldLabel, string $type = 'text', string $extraAttributes = '', string $fieldClass = '') use ($fieldValueFor, $canEdit, $errors): void {
                $fieldValue = $fieldValueFor($fieldKey);
                $classAttribute = $fieldClass !== '' ? ' class="' . htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') . '"' : '';
                ?>
                <label<?= $classAttribute ?>>
                    <span class="field-label-row">
                        <span class="field-label-text"><span><?= htmlspecialchars($fieldLabel) ?></span></span>
                    </span>
                    <input
                        type="<?= htmlspecialchars($type) ?>"
                        name="<?= htmlspecialchars($fieldKey) ?>"
                        value="<?= htmlspecialchars(is_scalar($fieldValue) || $fieldValue === null ? (string) $fieldValue : '') ?>"
                        <?= $extraAttributes ?>
                        <?= $canEdit ? '' : 'disabled' ?>
                        required
                    >
                    <?php if (!empty($errors[$fieldKey])): ?>
                        <small class="field-error"><?= htmlspecialchars($errors[$fieldKey]) ?></small>
                    <?php endif; ?>
                </label>
                <?php
            };
            ?>
            <form class="form-stack disbursement-voucher-form" data-dv-form method="post" action="/BTS/reports/process/<?= $processId ?>/step/<?= htmlspecialchars($stepKey) ?>/save<?= $editQuery ?>" data-clear-url="<?= htmlspecialchars($clearUrl) ?>">
                <div class="section-heading-row report-form-heading procurement-section-header">
                    <h2>Disbursement Voucher Details</h2>
                    <?php if ($isSaved && $errors === []): ?>
                        <span class="saved-details-note">Current saved data for printing</span>
                    <?php endif; ?>
                </div>

                <section class="form-section disbursement-voucher-details-section">
                    <div class="form-grid two-columns disbursement-voucher-grid">
                        <?php $dvRenderInput('DIVNO', $fields['DIVNO'] ?? 'D.V. Number'); ?>
                        <?php $dvRenderInput('ORNO', $fields['ORNO'] ?? 'O.R. Number'); ?>
                        <?php $dvRenderInput('DATET', $fields['DATET'] ?? 'Date', 'date'); ?>
                        <?php $dvRenderInput('PAYEE', $fields['PAYEE'] ?? 'Payee'); ?>
                        <label>
                            <span class="field-label-row">
                                <span class="field-label-text"><span><?= htmlspecialchars($fields['DDFUND'] ?? 'Fund') ?></span></span>
                            </span>
                            <?php $selectedFund = (string) $fieldValueFor('DDFUND'); ?>
                            <select name="DDFUND" <?= $canEdit ? '' : 'disabled' ?> required>
                                <option value="">Select fund</option>
                                <?php foreach ($disbursementFundOptions as $fundOption): ?>
                                    <option value="<?= htmlspecialchars($fundOption) ?>" <?= $selectedFund === $fundOption ? 'selected' : '' ?>><?= htmlspecialchars($fundOption) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($errors['DDFUND'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['DDFUND']) ?></small>
                            <?php endif; ?>
                        </label>
                        <?php $dvRenderInput('PAYEE_ADDRESS', $fields['PAYEE_ADDRESS'] ?? 'Address'); ?>
                        <?php $dvRenderInput('TIN', $fields['TIN'] ?? 'TIN'); ?>
                        <label class="disbursement-voucher-full-field">
                            <span class="field-label-row">
                                <span class="field-label-text"><span><?= htmlspecialchars($fields['PARTICULARS'] ?? 'Particulars') ?></span></span>
                            </span>
                            <textarea name="PARTICULARS" rows="5" <?= $canEdit ? '' : 'disabled' ?> required><?= htmlspecialchars($dvParticularsBody) ?></textarea>
                            <span class="dv-particulars-suffix"><strong>Automatically appended:</strong> <?= htmlspecialchars(\App\Models\Workflow::DV_PARTICULARS_SUFFIX) ?></span>
                            <?php if (!empty($errors['PARTICULARS'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['PARTICULARS']) ?></small>
                            <?php endif; ?>
                        </label>
                    </div>
                </section>

                <section class="form-section disbursement-voucher-amounts-section">
                    <div class="form-grid two-columns disbursement-voucher-grid">
                        <label>
                            <span class="field-label-row">
                                <span class="field-label-text"><span><?= htmlspecialchars($fields['GROSS'] ?? 'Gross Amount') ?></span></span>
                                <?php if ($disbursementHasPurchaseOrderSource): ?>
                                    <span class="saved-details-note field-status-note">From Purchase Order</span>
                                <?php endif; ?>
                            </span>
                            <input
                                type="number"
                                step="0.01"
                                name="GROSS"
                                value="<?= htmlspecialchars((string) $fieldValueFor('GROSS')) ?>"
                                data-dv-gross
                                <?= $disbursementHasPurchaseOrderSource ? 'readonly' : '' ?>
                                <?= $canEdit ? '' : 'disabled' ?>
                                required
                            >
                            <?php if (!empty($errors['GROSS'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['GROSS']) ?></small>
                            <?php endif; ?>
                            <?php if ($dvGrossMismatch): ?>
                                <small class="notice dv-gross-bid-notice">The Gross Amount must be equal to the Lowest Bid Amount.</small>
                            <?php elseif ($dvLowestBidValue !== '' && is_numeric($dvLowestBidValue)): ?>
                                <small class="field-status-note">Lowest Bid: <?= htmlspecialchars(number_format((float) $dvLowestBidValue, 2)) ?></small>
                            <?php endif; ?>
                        </label>
                        <label>
                            <span class="field-label-row">
                                <span class="field-label-text"><span><?= htmlspecialchars($fields['CHECKNO'] ?? 'Check No.') ?></span></span>
                            </span>
                            <input
                                type="number"
                                step="1"
                                name="CHECKNO"
                                value="<?= htmlspecialchars((string) $fieldValueFor('CHECKNO')) ?>"
                                readonly
                                <?= $canEdit ? '' : 'disabled' ?>
                                required
                            >
                            <?php if (!empty($errors['CHECKNO'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['CHECKNO']) ?></small>
                            <?php endif; ?>
                        </label>
                        <label class="disbursement-tax-toggle-field">
                            <span class="field-label-row">
                                <span class="field-label-text"><span><?= htmlspecialchars($fields['DV_TAX_TYPE'] ?? 'Tax Type') ?></span></span>
                            </span>
                            <select name="DV_TAX_TYPE" data-dv-tax-type <?= $canEdit ? '' : 'disabled' ?>>
                                <option value="">None</option>
                                <?php if ($dvSelectedTaxType !== '' && !in_array($dvSelectedTaxType, $disbursementTaxTypes, true)): ?>
                                    <option value="<?= htmlspecialchars($dvSelectedTaxType) ?>" selected><?= htmlspecialchars($dvSelectedTaxType) ?> (retired)</option>
                                <?php endif; ?>
                                <?php foreach ($disbursementTaxTypes as $taxType): ?>
                                    <option value="<?= htmlspecialchars($taxType) ?>" <?= $dvSelectedTaxType === $taxType ? 'selected' : '' ?>><?= htmlspecialchars($taxType) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($errors['DV_TAX_TYPE'])): ?><small class="field-error"><?= htmlspecialchars($errors['DV_TAX_TYPE']) ?></small><?php endif; ?>
                            <span class="field-label-row dv-tax-amount-label"><span class="field-label-text"><span>Tax Deduction Amount</span></span></span>
                            <input
                                type="number"
                                step="0.01"
                                name="WHTAX"
                                value="<?= htmlspecialchars((string) $fieldValueFor('WHTAX')) ?>"
                                data-dv-tax-input
                                <?= $dvSelectedTaxType !== '' ? '' : 'disabled' ?>
                                <?= $canEdit ? '' : 'disabled' ?>
                            >
                            <?php if (!empty($errors['WHTAX'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['WHTAX']) ?></small>
                            <?php endif; ?>
                        </label>
                        <label>
                            <span class="field-label-row">
                                <span class="field-label-text"><span><?= htmlspecialchars($fields['NETAMOUNT'] ?? 'Net Amount') ?></span></span>
                            </span>
                            <input
                                type="number"
                                step="0.01"
                                name="NETAMOUNT"
                                value="<?= htmlspecialchars((string) $fieldValueFor('NETAMOUNT')) ?>"
                                data-dv-net
                                readonly
                            >
                            <?php if (!empty($errors['NETAMOUNT'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['NETAMOUNT']) ?></small>
                            <?php endif; ?>
                        </label>
                    </div>
                </section>

                <div class="form-actions report-form-actions">
                    <button type="submit" <?= $canEdit ? '' : 'disabled' ?>>Save Disbursement Voucher</button>
                    <button type="submit" class="secondary" formaction="<?= htmlspecialchars($clearUrl) ?>" formnovalidate data-clear-step <?= $canEdit ? '' : 'disabled' ?>>Clear Fields</button>
                </div>
            </form>
        <?php elseif ($isObligationReport): ?>
            <form class="form-stack obligation-report-form" method="post" action="/BTS/reports/process/<?= $processId ?>/step/<?= htmlspecialchars($stepKey) ?>/save<?= $editQuery ?>" data-clear-url="<?= htmlspecialchars($clearUrl) ?>">
                <div class="section-heading-row report-form-heading procurement-section-header">
                    <h2>Obligation Report Details</h2>
                    <?php if ($isSaved && $errors === []): ?>
                        <span class="saved-details-note">Current saved data for printing</span>
                    <?php endif; ?>
                </div>

                <section class="form-section obligation-report-details-section">
                    <div class="form-grid two-columns obligation-report-grid">
                        <label>
                            <span class="field-label-row">
                                <span class="field-label-text"><span><?= htmlspecialchars($fields['OBLIDATE'] ?? 'Obligation Date') ?></span></span>
                            </span>
                            <input
                                type="date"
                                name="OBLIDATE"
                                value="<?= htmlspecialchars((string) $fieldValueFor('OBLIDATE')) ?>"
                                <?= $canEdit ? '' : 'disabled' ?>
                                required
                            >
                            <?php if (!empty($errors['OBLIDATE'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['OBLIDATE']) ?></small>
                            <?php endif; ?>
                        </label>
                        <label>
                            <span class="field-label-row">
                                <span class="field-label-text"><span>Fund</span></span>
                                <span class="saved-details-note field-status-note">Inherited</span>
                            </span>
                            <input type="text" value="<?= htmlspecialchars((string) $fieldValueFor('DDFUND')) ?>" disabled>
                        </label>
                        <label>
                            <span class="field-label-row">
                                <span class="field-label-text"><span>Payee</span></span>
                                <span class="saved-details-note field-status-note">Inherited</span>
                            </span>
                            <input type="text" value="<?= htmlspecialchars((string) $fieldValueFor('PAYEE')) ?>" disabled>
                        </label>
                        <label>
                            <span class="field-label-row">
                                <span class="field-label-text"><span>Address</span></span>
                                <span class="saved-details-note field-status-note">Inherited</span>
                            </span>
                            <input type="text" value="<?= htmlspecialchars((string) $fieldValueFor('PAYEE_ADDRESS')) ?>" disabled>
                        </label>
                        <label>
                            <span class="field-label-row">
                                <span class="field-label-text"><span>O.R. Number</span></span>
                                <span class="saved-details-note field-status-note">Inherited</span>
                            </span>
                            <input type="text" value="<?= htmlspecialchars((string) $fieldValueFor('ORNO')) ?>" disabled>
                        </label>
                        <label>
                            <span class="field-label-row">
                                <span class="field-label-text"><span>Net Amount</span></span>
                                <span class="saved-details-note field-status-note">Inherited</span>
                            </span>
                            <input type="text" value="<?= htmlspecialchars((string) $fieldValueFor('NETAMOUNT')) ?>" disabled>
                        </label>
                        <label>
                            <span class="field-label-row">
                                <span class="field-label-text"><span><?= htmlspecialchars($fields['SPECFUND'] ?? 'Specific Fund') ?></span></span>
                            </span>
                            <?php $selectedSpecificFund = (string) $fieldValueFor('SPECFUND'); ?>
                            <?php if ($obligationUsesGeneralFund): ?>
                                <select name="SPECFUND" <?= $canEdit ? '' : 'disabled' ?> required>
                                    <option value="">Select specific fund</option>
                                    <?php foreach ($obligationSpecificFundGroups as $groupLabel => $groupOptions): ?>
                                        <optgroup label="<?= htmlspecialchars((string) $groupLabel) ?>">
                                            <?php foreach ($groupOptions as $specificFundOption): ?>
                                                <option value="<?= htmlspecialchars($specificFundOption) ?>" <?= $selectedSpecificFund === $specificFundOption ? 'selected' : '' ?>><?= htmlspecialchars($specificFundOption) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input
                                    type="text"
                                    name="SPECFUND"
                                    value="<?= htmlspecialchars($selectedSpecificFund) ?>"
                                    <?= $canEdit ? '' : 'disabled' ?>
                                    required
                                >
                            <?php endif; ?>
                            <?php if (!empty($errors['SPECFUND'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['SPECFUND']) ?></small>
                            <?php endif; ?>
                        </label>
                        <label class="obligation-report-full-field">
                            <span class="field-label-row">
                                <span class="field-label-text"><span>Particulars</span></span>
                                <span class="saved-details-note field-status-note">Inherited</span>
                            </span>
                            <textarea rows="5" readonly><?= htmlspecialchars((string) $fieldValueFor('PARTICULARS')) ?></textarea>
                        </label>
                    </div>
                </section>

                <div class="form-actions report-form-actions">
                    <button type="submit" <?= $canEdit ? '' : 'disabled' ?>>Save Obligation Report</button>
                    <button type="submit" class="secondary" formaction="<?= htmlspecialchars($clearUrl) ?>" formnovalidate data-clear-step <?= $canEdit ? '' : 'disabled' ?>>Clear Fields</button>
                </div>
            </form>
        <?php elseif ($isRequisitionIssueSlip): ?>
            <form class="form-stack requisition-issue-slip-form" method="post" action="/BTS/reports/process/<?= $processId ?>/step/<?= htmlspecialchars($stepKey) ?>/save<?= $editQuery ?>" data-clear-url="<?= htmlspecialchars($clearUrl) ?>">
                <div class="section-heading-row report-form-heading procurement-section-header">
                    <h2>Requisition and Issue Slip Details</h2>
                    <?php if ($isSaved && $errors === []): ?>
                        <span class="saved-details-note">Current saved data for printing</span>
                    <?php endif; ?>
                </div>

                <section class="form-section requisition-issue-slip-details-section">
                    <div class="form-grid two-columns">
                        <label>
                            <span class="field-label-row">
                                <span class="field-label-text"><span><?= htmlspecialchars($fields['RAINO'] ?? 'RIS No.') ?></span></span>
                            </span>
                            <input
                                type="text"
                                name="RAINO"
                                value="<?= htmlspecialchars((string) $fieldValueFor('RAINO')) ?>"
                                <?= $canEdit ? '' : 'disabled' ?>
                                required
                            >
                            <?php if (!empty($errors['RAINO'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['RAINO']) ?></small>
                            <?php endif; ?>
                        </label>
                        <label>
                            <span class="field-label-row">
                                <span class="field-label-text"><span><?= htmlspecialchars($fields['RAIDATE'] ?? 'RIS Date') ?></span></span>
                            </span>
                            <input
                                type="date"
                                name="RAIDATE"
                                value="<?= htmlspecialchars((string) $fieldValueFor('RAIDATE')) ?>"
                                <?= $canEdit ? '' : 'disabled' ?>
                                required
                            >
                            <?php if (!empty($errors['RAIDATE'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['RAIDATE']) ?></small>
                            <?php endif; ?>
                        </label>
                        <label class="obligation-report-full-field <?= $risPurposeUsesPrPurpose ? 'inherited-field-box' : '' ?>">
                            <span class="field-label-row">
                                <span class="field-label-text"><span><?= htmlspecialchars($fields['PURPOSE'] ?? 'Purpose') ?></span></span>
                            </span>
                            <textarea name="PURPOSE" rows="4" class="<?= $risPurposeUsesPrPurpose ? 'inherited-field-control' : '' ?>" <?= $canEdit ? '' : 'disabled' ?> required><?= htmlspecialchars((string) $fieldValueFor('PURPOSE')) ?></textarea>
                            <?php if (!empty($errors['PURPOSE'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['PURPOSE']) ?></small>
                            <?php endif; ?>
                        </label>
                    </div>
                </section>

                <section class="form-section requisition-issue-slip-items-section">
                    <div class="section-heading-row pr-section-heading procurement-section-header">
                        <h2>Item Issuance</h2>
                    </div>
                    <?php if ($requisitionIssueSlipItemRows === []): ?>
                        <div class="notice">No Purchase Request items are available for this slip.</div>
                    <?php else: ?>
                        <div class="po-items-table-wrap inherited-table-wrap">
                            <table class="po-items-table inherited-data-table">
                                <thead>
                                    <tr>
                                        <th>Unit</th>
                                        <th>Particulars</th>
                                        <th>Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requisitionIssueSlipItemRows as $index => $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string) ($row['UOE'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string) ($row['PRPART'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string) ($row['PRQTY'] ?? '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <div class="form-actions report-form-actions">
                    <button type="submit" <?= $canEdit ? '' : 'disabled' ?>>Save Requisition and Issue Slip</button>
                    <button type="submit" class="secondary" formaction="<?= htmlspecialchars($clearUrl) ?>" formnovalidate data-clear-step <?= $canEdit ? '' : 'disabled' ?>>Clear Fields</button>
                </div>
            </form>
        <?php elseif ($isInspectionAcceptanceReport): ?>
            <form class="form-stack inspection-acceptance-form" method="post" action="/BTS/reports/process/<?= $processId ?>/step/<?= htmlspecialchars($stepKey) ?>/save<?= $editQuery ?>" data-clear-url="<?= htmlspecialchars($clearUrl) ?>">
                <div class="section-heading-row report-form-heading procurement-section-header">
                    <h2>Inspection and Acceptance Details</h2>
                    <?php if ($isSaved && $errors === []): ?>
                        <span class="saved-details-note">Current saved data for printing</span>
                    <?php endif; ?>
                </div>

                <section class="form-section inspection-acceptance-details-section">
                    <div class="form-grid two-columns">
                        <label>
                            <span class="field-label-row">
                                <span class="field-label-text"><span><?= htmlspecialchars($fields['IAS'] ?? 'IAR No.') ?></span></span>
                            </span>
                            <input
                                type="text"
                                name="IAS"
                                value="<?= htmlspecialchars((string) $fieldValueFor('IAS')) ?>"
                                <?= $canEdit ? '' : 'disabled' ?>
                                required
                            >
                            <?php if (!empty($errors['IAS'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['IAS']) ?></small>
                            <?php endif; ?>
                        </label>
                        <label>
                            <span class="field-label-row">
                                <span class="field-label-text"><span><?= htmlspecialchars($fields['IASDATE'] ?? 'IAR Date') ?></span></span>
                            </span>
                            <input
                                type="date"
                                name="IASDATE"
                                value="<?= htmlspecialchars((string) $fieldValueFor('IASDATE')) ?>"
                                <?= $canEdit ? '' : 'disabled' ?>
                                required
                            >
                            <?php if (!empty($errors['IASDATE'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['IASDATE']) ?></small>
                            <?php endif; ?>
                        </label>
                    </div>
                </section>

                <section class="form-section inspection-acceptance-items-section">
                    <div class="section-heading-row pr-section-heading procurement-section-header">
                        <h2>Inspected Items</h2>
                    </div>
                    <?php if ($inspectionAcceptanceItemRows === []): ?>
                        <div class="notice">No Purchase Request items are available for this report.</div>
                    <?php else: ?>
                        <div class="po-items-table-wrap">
                            <table class="po-items-table">
                                <thead>
                                    <tr>
                                        <th>Unit</th>
                                        <th>Particulars</th>
                                        <th>Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inspectionAcceptanceItemRows as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string) ($row['UOE'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string) ($row['PRPART'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string) ($row['PRQTY'] ?? '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <div class="form-actions report-form-actions">
                    <button type="submit" <?= $canEdit ? '' : 'disabled' ?>>Save Inspection and Acceptance Report</button>
                    <button type="submit" class="secondary" formaction="<?= htmlspecialchars($clearUrl) ?>" formnovalidate data-clear-step <?= $canEdit ? '' : 'disabled' ?>>Clear Fields</button>
                </div>
            </form>
        <?php elseif ($hasFields): ?>
            <form class="form-stack" method="post" action="/BTS/reports/process/<?= $processId ?>/step/<?= htmlspecialchars($stepKey) ?>/save<?= $editQuery ?>" data-clear-url="<?= htmlspecialchars($clearUrl) ?>">
                <div class="section-heading-row report-form-heading procurement-section-header">
                    <h2>Report Fields</h2>
                    <?php if ($isSaved && $errors === []): ?>
                        <span class="saved-details-note">Current saved data for printing</span>
                    <?php endif; ?>
                </div>
                <?php if ($isBacReport): ?>
                    <?php
                    $bacFieldValue = static function (string $fieldKey) use ($savedStepData, $resolvedData, $errors, $data) {
                        if (!empty($errors) && array_key_exists($fieldKey, $data)) {
                            return $data[$fieldKey];
                        }
                        if (array_key_exists($fieldKey, $savedStepData)) {
                            return $savedStepData[$fieldKey];
                        }
                        if (array_key_exists($fieldKey, $resolvedData)) {
                            return $resolvedData[$fieldKey];
                        }
                        if (array_key_exists($fieldKey, $data)) {
                            return $data[$fieldKey];
                        }
                        return '';
                    };
                    $bacRenderField = static function (string $fieldKey, string $fieldLabel, string $fieldClass = '') use ($bacFieldValue, $fieldType, $fieldStep, $canEdit, $errors): void {
                        $fieldValue = $bacFieldValue($fieldKey);
                        $classAttribute = $fieldClass !== '' ? ' class="' . htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') . '"' : '';
                        ?>
                        <label<?= $classAttribute ?>>
                            <span class="field-label-row">
                                <span class="field-label-text">
                                    <span><?= htmlspecialchars($fieldLabel) ?></span>
                                </span>
                            </span>
                            <input
                                type="<?= $fieldType($fieldKey) ?>"
                                name="<?= htmlspecialchars($fieldKey) ?>"
                                value="<?= htmlspecialchars(is_scalar($fieldValue) || $fieldValue === null ? (string) $fieldValue : '') ?>"
                                <?= $fieldStep($fieldKey) ?>
                                <?= $canEdit ? '' : 'disabled' ?>
                                required
                            >
                            <?php if (!empty($errors[$fieldKey])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors[$fieldKey]) ?></small>
                            <?php endif; ?>
                        </label>
                        <?php
                    };
                    ?>
                    <div class="form-grid two-columns bac-report-grid">
                        <label class="bac-date-field">
                            <span class="field-label-row">
                                <span class="field-label-text">
                                    <span>BAC Date</span>
                                </span>
                                <?php if ($bacDateReadonly): ?>
                                    <span class="saved-details-note field-status-note">Using P.R. Date</span>
                                <?php endif; ?>
                            </span>
                            <input
                                type="date"
                                name="BACDATE"
                                value="<?= htmlspecialchars((string) $bacDateValue) ?>"
                                <?= $bacDateReadonly ? 'readonly' : '' ?>
                                <?= $canEdit ? '' : 'disabled' ?>
                                required
                            >
                            <?php if (!empty($errors['BACDATE'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['BACDATE']) ?></small>
                            <?php endif; ?>
                        </label>
                        <?php $bacRenderField('BACRNUM', $fields['BACRNUM'] ?? 'BAC Resolution Number'); ?>
                        <?php $bacRenderField('PROJECTITLE', $fields['PROJECTITLE'] ?? 'Project Title', 'bac-full-width-field'); ?>
                        <?php $bacRenderField('BACPLACE', $fields['BACPLACE'] ?? 'BAC Place', 'bac-full-width-field'); ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($fields as $key => $label): ?>
                        <?php
                        $isDateReadonly = in_array((string) $key, $dateReadonlyFields, true);
                        if ($isDateReadonly) {
                            $fieldValue = $sharedDateValues[$key] ?? '';
                        } elseif (array_key_exists((string) $key, $savedStepData)) {
                            $fieldValue = $savedStepData[(string) $key];
                        } elseif (!empty($errors) && array_key_exists((string) $key, $data)) {
                            $fieldValue = $data[(string) $key];
                        } elseif (array_key_exists((string) $key, $resolvedData)) {
                            $fieldValue = $resolvedData[(string) $key];
                        } else {
                            $fieldValue = '';
                        }
                        ?>
                        <?php
                        $fieldStatusNote = '';
                        if ($isDateReadonly) {
                            $fieldStatusNote = 'Using P.R. Date';
                        } elseif (array_key_exists((string) $key, $inheritedData)) {
                            $fieldStatusNote = 'Inherited';
                        }
                        ?>
                        <label>
                            <span class="field-label-row">
                                <span class="field-label-text">
                                    <span><?= htmlspecialchars($label) ?></span>
                                </span>
                                <?php if ($fieldStatusNote !== ''): ?>
                                    <span class="saved-details-note field-status-note"><?= htmlspecialchars($fieldStatusNote) ?></span>
                                <?php endif; ?>
                            </span>
                            <input
                                type="<?= $fieldType((string) $key) ?>"
                                name="<?= htmlspecialchars($key) ?>"
                                value="<?= htmlspecialchars(is_scalar($fieldValue) || $fieldValue === null ? (string) $fieldValue : '') ?>"
                                <?= $fieldStep((string) $key) ?>
                                <?= $isDateReadonly ? 'readonly' : '' ?>
                                <?= $canEdit ? '' : 'disabled' ?>
                                required
                            >
                            <?php if (!empty($errors[$key])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors[$key]) ?></small>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class="form-actions report-form-actions">
                    <button type="submit" <?= $canEdit ? '' : 'disabled' ?>>Save Report</button>
                    <button type="submit" class="secondary" formaction="<?= htmlspecialchars($clearUrl) ?>" formnovalidate data-clear-step <?= $canEdit ? '' : 'disabled' ?>>Clear Fields</button>
                </div>
            </form>
        <?php else: ?>
            <div class="notice">No manual fields are required for this step yet.</div>
        <?php endif; ?>

        <div class="<?= htmlspecialchars($workflowActionsClass) ?>">
            <form<?= $printHoverNoteAttributes ?> method="post" action="/BTS/reports/process/<?= $processId ?>/step/<?= htmlspecialchars($stepKey) ?>/print"<?= $printFormAttributes ?>>
                <button type="submit" class="secondary" <?= $canPrintReport ? '' : 'disabled' ?>><?= $isPurchaseRequest ? 'Print Purchase Request' : 'Print' ?></button>
            </form>

            <?php if ($isCompletedWorkflow): ?>
                <a class="button secondary" href="/BTS/archive">Back to Archived Reports</a>
            <?php elseif ($isFinal): ?>
                <form method="post" action="/BTS/reports/process/<?= $processId ?>/finish">
                    <button type="submit" <?= $canAdvance ? '' : 'disabled' ?>>Finish</button>
                </form>
            <?php else: ?>
                <form<?= $nextHoverNoteAttributes ?> method="post" action="/BTS/reports/process/<?= $processId ?>/next">
                    <button type="submit" <?= $canAdvance ? '' : 'disabled' ?>><?= $isPurchaseRequest ? 'Continue to BAC Reports' : 'Next Step' ?></button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout/main.php';












