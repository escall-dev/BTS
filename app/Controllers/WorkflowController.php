<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\PurchaseRequestReportGenerator;
use App\Core\ReportPdfGenerator;
use App\Core\TemplateMapping;
use App\Models\SystemInput;
use App\Models\Workflow;

class WorkflowController extends Controller
{
    public function startPurchaseRequest(): void
    {
        $this->redirect('/BTS/reports');
    }

    public function startDisbursementVoucher(): void
    {
        $this->redirect('/BTS/reports');
    }

    public function startWorkflow(string $type): void
    {
        $this->start($type);
    }

    public function showStep(string $id, string $step): void
    {
        $this->requireLogin();

        $workflow = new Workflow();
        $process = $workflow->getProcess((int) $id);
        $reportStep = $workflow->getStep((int) $id, $step);

        if (!$process || !$reportStep) {
            http_response_code(404);
            echo 'Workflow step not found';
            return;
        }

        if (!$this->canOpenStep($process, $reportStep)) {
            $this->redirect('/BTS/reports/process/' . (int) $id . '/step/' . $process['current_step']);
        }

        $this->renderStep($workflow, $process, $reportStep, [
            'printed' => ($_GET['printed'] ?? '') === '1',
            'saved' => ($_GET['saved'] ?? '') === '1',
            'cleared' => ($_GET['cleared'] ?? '') === '1',
        ]);
    }

    public function saveStep(string $id, string $step): void
    {
        $this->requireLogin();

        $workflow = new Workflow();
        $process = $workflow->getProcess((int) $id);
        $reportStep = $workflow->getStep((int) $id, $step);

        if (!$process || !$reportStep) {
            http_response_code(404);
            echo 'Workflow step not found';
            return;
        }

        if (!$this->canOpenStep($process, $reportStep)) {
            $this->redirect('/BTS/reports/process/' . (int) $id . '/step/' . $process['current_step']);
        }

        $errors = $workflow->saveStepData((int) $reportStep['id'], $step, $_POST, (int) $process['id']);

        if ($errors !== []) {
            $overrides = [
                'data' => $this->scalarFormData($_POST),
                'errors' => $errors,
                'printed' => false,
                'saved' => false,
            ];

            if ($step === 'purchase-request') {
                $overrides['purchaseItems'] = $workflow->normalizePurchaseItems($_POST);
                $overrides['sharedDateEnabled'] = !empty($_POST[Workflow::SHARED_DATE_META_KEY]);
            }

            if ($step === 'abstract-of-canvass') {
                $overrides['abstractSupplierBids'] = $workflow->getSupplierBidsForAbstract((int) $process['id'], (int) $reportStep['id'], $_POST);
                $overrides['selectedAbstractSupplier'] = trim((string) ($_POST['LBSUPPNAME'] ?? ''));
            }

            if ($step === 'request-for-quotation') {
                $overrides['rfqSupplierBids'] = $workflow->normalizeRfqSupplierBids($_POST);
                $overrides['rfqItemRows'] = $workflow->getRfqItemRows((int) $process['id'], (int) $reportStep['id']);
            }

            if ($step === 'purchase-order') {
                $postedPaymentModes = $_POST['PO_MODE_OF_PAYMENT'] ?? [];
                if (is_array($postedPaymentModes) && count($postedPaymentModes) === 1 && is_scalar($postedPaymentModes[0])) {
                    $overrides['data']['PO_MODE_OF_PAYMENT'] = trim((string) $postedPaymentModes[0]);
                }
                $purchaseOrderItemRows = $workflow->getPurchaseOrderItemRows((int) $process['id']);
                $postedPoItems = $workflow->normalizePurchaseOrderItems($_POST);
                foreach ($purchaseOrderItemRows as $index => &$row) {
                    $row['POUNITCOST'] = $postedPoItems[$index]['POUNITCOST'] ?? '';
                    $quantity = trim((string) ($row['PRQTY'] ?? ''));
                    $unitCost = trim((string) $row['POUNITCOST']);
                    $row['POAMOUNT'] = ($quantity !== '' && $unitCost !== '' && is_numeric($quantity) && is_numeric($unitCost))
                        ? number_format((float) $quantity * (float) $unitCost, 2, '.', '')
                        : '';
                }
                unset($row);
                $overrides['purchaseOrderItemRows'] = $purchaseOrderItemRows;
            }

            if ($step === 'disbursement-voucher') {
                $overrides['disbursementTaxEnabled'] = trim((string) ($_POST['DV_TAX_TYPE'] ?? '')) !== '';
            }


            if ($step === 'inspection-acceptance-report') {
                $overrides['inspectionAcceptanceItemRows'] = $workflow->getInspectionAcceptanceItemRows((int) $process['id']);
            }

            $reportStep = $workflow->getStep((int) $id, $step) ?: $reportStep;
            $this->renderStep($workflow, $process, $reportStep, $overrides);
            return;
        }

        $workflow->touchArchiveCheckpoint((int) $process['id']);
        $process = $workflow->getProcess((int) $id);
        $reportStep = $workflow->getStep((int) $id, $step);
        $overrides = [
            'data' => $workflow->getStepData((int) $reportStep['id']),
            'saved' => true,
            'printed' => false,
        ];

        if ($step === 'purchase-request') {
            $overrides['purchaseItems'] = $workflow->getPurchaseItems((int) $process['id']);
        }

        if ($step === 'abstract-of-canvass') {
            $overrides['abstractSupplierBids'] = $workflow->getSupplierBidsForAbstract((int) $process['id'], (int) $reportStep['id']);
            $overrides['selectedAbstractSupplier'] = (string) ($overrides['data']['LBSUPPNAME'] ?? '');
        }

        if ($step === 'request-for-quotation') {
            $overrides['rfqSupplierBids'] = $workflow->getRfqSupplierBids((int) $reportStep['id']);
            $overrides['rfqItemRows'] = $workflow->getRfqItemRows((int) $process['id'], (int) $reportStep['id']);
        }

        $this->renderStep($workflow, $process, $reportStep, $overrides);
    }

    public function clearStep(string $id, string $step): void
    {
        $this->requireLogin();

        $workflow = new Workflow();
        $process = $workflow->getProcess((int) $id);
        $reportStep = $workflow->getStep((int) $id, $step);

        if (!$process || !$reportStep || !$this->canOpenStep($process, $reportStep)) {
            $target = $process['current_step'] ?? $step;
            $this->redirect('/BTS/reports/process/' . (int) $id . '/step/' . $target);
        }

        $workflow->clearStepData((int) $process['id'], (int) $reportStep['id'], $step);

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (str_contains($accept, 'application/json') || strtolower($requestedWith) === 'fetch') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            return;
        }

        $this->redirect('/BTS/reports/process/' . (int) $id . '/step/' . $step . '?cleared=1');
    }
    public function printStep(string $id, string $step): void
    {
        $this->requireLogin();

        $workflow = new Workflow();
        $process = $workflow->getProcess((int) $id);
        $reportStep = $workflow->getStep((int) $id, $step);

        if (!$process || !$reportStep || !$this->canOpenStep($process, $reportStep) || (int) $reportStep['is_printable'] !== 1 || !$workflow->stepHasValidSavedData((int) $process['id'], $reportStep)) {
            $target = $process['current_step'] ?? $step;
            $this->redirect('/BTS/reports/process/' . (int) $id . '/step/' . $target);
        }

        try {
            $generator = $step === 'purchase-request'
                ? new PurchaseRequestReportGenerator()
                : new ReportPdfGenerator();
            $pdfPath = $generator->generate($workflow, $process, $reportStep);
            $workflow->markPrinted((int) $reportStep['id']);
            $this->downloadFile($pdfPath, basename($pdfPath), 'application/pdf', true);
        } catch (\Throwable $exception) {
            $this->renderStep($workflow, $process, $reportStep, [
                'errors' => ['print' => 'PDF generation failed: ' . $exception->getMessage()],
                'printed' => false,
                'saved' => false,
            ]);
        }
    }

    public function next(string $id): void
    {
        $this->requireLogin();

        $workflow = new Workflow();
        $process = $workflow->getProcess((int) $id);

        if (!$process) {
            http_response_code(404);
            echo 'Workflow process not found';
            return;
        }

        $step = $workflow->getStep((int) $id, $process['current_step']);

        if (!$step || !$workflow->stepHasValidSavedData((int) $id, $step)) {
            $this->redirect('/BTS/reports/process/' . (int) $id . '/step/' . $process['current_step']);
        }

        $nextStep = $workflow->advance((int) $id);

        if (!$nextStep) {
            $this->redirect('/BTS/reports/process/' . (int) $id . '/step/' . $process['current_step']);
        }

        $this->redirect('/BTS/reports/process/' . (int) $id . '/step/' . $nextStep);
    }

    public function finish(string $id): void
    {
        $this->requireLogin();

        $workflow = new Workflow();
        $process = $workflow->getProcess((int) $id);

        if (!$process || $process['current_step'] !== 'inspection-acceptance-report') {
            $this->redirect('/BTS/reports');
        }

        $step = $workflow->getStep((int) $id, 'inspection-acceptance-report');

        if (!$step || !$workflow->stepHasValidSavedData((int) $id, $step)) {
            $this->redirect('/BTS/reports/process/' . (int) $id . '/step/inspection-acceptance-report');
        }

        $workflow->finish((int) $id);
        $this->redirect('/BTS/reports/process/' . (int) $id . '/complete');
    }

    public function complete(string $id): void
    {
        $this->requireLogin();

        $workflow = new Workflow();
        $process = $workflow->getProcess((int) $id);

        if (!$process) {
            http_response_code(404);
            echo 'Workflow process not found';
            return;
        }

        $this->view('workflow/complete', [
            'title' => 'Workflow Complete',
            'active' => 'reports',
            'process' => $process,
        ]);
    }

    private function start(string $startType): void
    {
        $this->requireLogin();

        if (!in_array($startType, [Workflow::PURCHASE_START, Workflow::VOUCHER_START], true)) {
            http_response_code(404);
            echo 'Workflow start type not found';
            return;
        }

        $workflow = new Workflow();
        $processId = $workflow->createProcess($startType, $_SESSION['user']['id'] ?? null);
        $process = $workflow->getProcess($processId);

        $this->redirect('/BTS/reports/process/' . $processId . '/step/' . $process['current_step']);
    }

    private function renderStep(Workflow $workflow, array $process, array $reportStep, array $overrides = []): void
    {
        $step = $reportStep['step_key'];
        $savedStepData = $workflow->getStepData((int) $reportStep['id']);
        $savedStepDataWithInternal = $workflow->getStepData((int) $reportStep['id'], true);
        $data = $overrides['data'] ?? $savedStepData;

        $purchaseItems = $overrides['purchaseItems'] ?? [];
        if ($step === 'purchase-request' && $purchaseItems === []) {
            $purchaseItems = $workflow->getPurchaseItems((int) $process['id']);
        }
        if ($step === 'purchase-request' && $purchaseItems === []) {
            $purchaseItems = [[
                'INPR' => '',
                'PRQTY' => '',
                'UOE' => '',
                'PRPART' => '',
                'EUC' => '',
            ]];
        }

        $abstractSupplierBids = $overrides['abstractSupplierBids'] ?? [];
        if ($step === 'abstract-of-canvass' && $abstractSupplierBids === []) {
            $abstractSupplierBids = $workflow->getSupplierBidsForAbstract((int) $process['id'], (int) $reportStep['id']);
        }

        $rfqSupplierBids = $overrides['rfqSupplierBids'] ?? [];
        if ($step === 'request-for-quotation' && $rfqSupplierBids === []) {
            $rfqSupplierBids = $workflow->getRfqSupplierBids((int) $reportStep['id']);
        }
        if ($step === 'request-for-quotation' && $rfqSupplierBids === []) {
            $rfqSupplierBids = [[
                'SUPPNAME' => '',
                'SUPPADD' => '',
                'AOP' => '',
            ]];
        }

        $rfqItemRows = $overrides['rfqItemRows'] ?? [];
        if ($step === 'request-for-quotation' && $rfqItemRows === []) {
            $rfqItemRows = $workflow->getRfqItemRows((int) $process['id'], (int) $reportStep['id']);
        }

        $purchaseOrderItemRows = $overrides['purchaseOrderItemRows'] ?? [];
        if ($step === 'purchase-order' && $purchaseOrderItemRows === []) {
            $purchaseOrderItemRows = $workflow->getPurchaseOrderItemRows((int) $process['id']);
        }

        $requisitionIssueSlipItemRows = $overrides['requisitionIssueSlipItemRows'] ?? [];
        if ($step === 'requisition-issue-slip' && $requisitionIssueSlipItemRows === []) {
            $requisitionIssueSlipItemRows = $workflow->getRequisitionIssueSlipItemRows((int) $process['id']);
        }

        $inspectionAcceptanceItemRows = $overrides['inspectionAcceptanceItemRows'] ?? [];
        if ($step === 'inspection-acceptance-report' && $inspectionAcceptanceItemRows === []) {
            $inspectionAcceptanceItemRows = $workflow->getInspectionAcceptanceItemRows((int) $process['id']);
        }

        $bacDateValue = $step === 'bac-reports'
            ? $workflow->getBacCalendarDate((int) $process['id'], (int) $reportStep['id'], is_array($data) ? $data : [])
            : '';

        $this->view('workflow/step', array_merge([
            'title' => Workflow::label($step),
            'active' => 'reports',
            'process' => $process,
            'step' => $reportStep,
            'steps' => $workflow->getSteps((int) $process['id']),
            'data' => $data,
            'savedStepData' => $savedStepData,
            'fields' => Workflow::requiredFields($step),
            'labels' => Workflow::labels(),
            'template' => TemplateMapping::get($step),
            'templateSheet' => TemplateMapping::sheetFor($step),
            'inheritedData' => $workflow->getInheritedData((int) $process['id'], $step),
            'systemData' => $workflow->getSystemDataForStep($step),
            'resolvedData' => $workflow->getTemplateData((int) $process['id'], (int) $reportStep['id']),
            'purchaseHeaderFields' => Workflow::PURCHASE_HEADER_FIELDS,
            'purchaseItemFields' => Workflow::PURCHASE_ITEM_FIELDS,
            'purchaseItems' => $purchaseItems,
            'abstractSupplierBids' => $abstractSupplierBids,
            'selectedAbstractSupplier' => $overrides['selectedAbstractSupplier'] ?? (string) ($data['LBSUPPNAME'] ?? ''),
            'rfqSupplierBids' => $rfqSupplierBids,
            'rfqItemRows' => $rfqItemRows,
            'purchaseOrderItemRows' => $purchaseOrderItemRows,
            'requisitionIssueSlipItemRows' => $requisitionIssueSlipItemRows,
            'inspectionAcceptanceItemRows' => $inspectionAcceptanceItemRows,
            'disbursementFundOptions' => Workflow::DISBURSEMENT_FUND_OPTIONS,
            'disbursementHasPurchaseOrderSource' => $workflow->hasDisbursementVoucherPurchaseOrderSource((int) $process['id']),
            'disbursementTaxMetaKey' => Workflow::DV_WHTAX_ENABLED_META_KEY,
            'disbursementTaxEnabled' => $overrides['disbursementTaxEnabled'] ?? (($savedStepDataWithInternal[Workflow::DV_WHTAX_ENABLED_META_KEY] ?? '') === '1'),
            'disbursementTaxTypes' => (new SystemInput())->taxTypes(),
            'disbursementLowestBidAmount' => $workflow->disbursementLowestBidAmount((int) $process['id']),
            'obligationSpecificFundGroups' => Workflow::OBLIGATION_SPECIFIC_FUND_GROUPS,
            'obligationUsesGeneralFund' => $workflow->obligationUsesGeneralFund((int) $process['id']),
            'stepDataIsValid' => $workflow->stepHasValidSavedData((int) $process['id'], $reportStep),
            'printed' => false,
            'saved' => false,
            'cleared' => false,
            'sharedDateEnabled' => $workflow->isSharedDateEnabled((int) $process['id']),
            'sharedDateValues' => $workflow->getSharedDateValues((int) $process['id'], $step),
            'dateReadonlyFields' => $workflow->dateReadonlyFields((int) $process['id'], $step),
            'bacDateValue' => $bacDateValue,
            'bacDateReadonly' => $workflow->dateReadonlyFields((int) $process['id'], $step) !== [],
        ], $overrides));
    }

    private function canOpenStep(array $process, array $step): bool
    {
        return $process['current_step'] === $step['step_key'] || (int) $step['is_saved'] === 1;
    }

    private function scalarFormData(array $input): array
    {
        $clean = [];

        foreach ($input as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $clean[$key] = (string) $value;
            }
        }

        return $clean;
    }

    private function downloadFile(string $path, string $filename, string $contentType, bool $inline = false): void
    {
        if (!is_file($path)) {
            http_response_code(404);
            echo 'Generated file not found';
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $disposition = $inline ? 'inline' : 'attachment';
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($path);
        exit;
    }
}





