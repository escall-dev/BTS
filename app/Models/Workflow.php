<?php

namespace App\Models;

use App\Core\Model;
use App\Core\TemplateMapping;
use PDO;

class Workflow extends Model
{
    public const PURCHASE_START = 'purchase-request';
    public const VOUCHER_START = 'disbursement-voucher';

    public const PURCHASE_HEADER_FIELDS = [
        'PRNUM' => 'P.R. Number',
        'DatePR' => 'P.R. Date',
        'PRPURPOSE' => 'Purpose',
    ];

    public const PURCHASE_ITEM_FIELDS = [
        'INPR' => 'Item Number',
        'PRQTY' => 'Quantity',
        'UOE' => 'Unit of Measurement',
        'PRPART' => 'Particulars',
        'EUC' => 'Estimated Unit Cost',
    ];

    public const SHARED_DATE_META_KEY = '__use_pr_date_for_dates';
    public const ABSTRACT_BIDS_META_KEY = '__abstract_supplier_bids';
    public const RFQ_SUPPLIER_BIDS_META_KEY = '__rfq_supplier_bids';
    public const RFQ_QUOTES_META_KEY = '__rfq_item_quotes';
    public const PURCHASE_ORDER_ITEMS_META_KEY = '__purchase_order_items';
    public const DV_WHTAX_ENABLED_META_KEY = '__dv_whtax_enabled';
    public const DV_PARTICULARS_SUFFIX = 'as per supporting documents attached herein amounting to:';
    public const RIS_PURPOSE_SOURCE_META_KEY = '__ris_purpose_source';

    public const DISBURSEMENT_FUND_OPTIONS = [
        'General Funds',
        'Bank Charges',
        'Communication Equipment',
        'Property, Plant and Equipment',
        'Appropriation for GAD Fund',
        'Appropriation for Sangguniang Kabataan',
        'Appropriation for Development Fund',
        'Appropriation for Barangay DRRM Fund',
        'Appropriation for Senior Citizen Fund',
        'Appropriation for PWD Fund',
        'BCPC Fund',
        'Election Reserved',
        'Discretionary Fund',
    ];

    public const OBLIGATION_SPECIFIC_FUND_GROUPS = [
        'EXPENDITURES PERSONAL SERVICES:' => [
            'Personal Services',
            'Salaries and Wages (Honorarium)',
            'Cash Gift',
            'Year End Bonus',
            'Leave Benefits',
            'Mid Year Bonus',
            'Other Bonuses (SRI, PEI, OTHERS)',
            'Lupon Tagapamayapa',
        ],
        'Maintenance and Other Operating Expenses (MOOE):' => [
            'Travelling Expenses - Local',
            'Training Expenses',
            'Office Supplies / Expenses',
            'Gasoline, Oil & Lubricant',
            'Other Supplies Expenses',
            'Water Expenses',
            'Electricity Expenses',
            'Telephone Expenses - Landline',
            'Telephone Expenses - Mobile',
            'Membership Dues and Contribution',
            'Representation Expenses',
            'Repair & Maintenance - Office Equipment',
            'Repair & Maintenance - Motor Vehicles',
            'Donation',
            'Fidelity Bond Premiums',
            'General Services',
            'Security Services',
        ],
    ];

    public const ABSTRACT_BID_FIELDS = [
        'SUPPNAME' => 'Supplier Name',
        'SUPPADD' => 'Supplier Address',
        'AOP' => 'Amount of Proposal',
    ];

    private const ALL_STEPS = [
        'purchase-request' => 'Purchase Request',
        'bac-reports' => 'BAC Reports',
        'request-for-quotation' => 'Request for Quotation',
        'abstract-of-canvass' => 'Abstract of Canvass',
        'purchase-order' => 'Purchase Order',
        'disbursement-voucher' => 'Disbursement Voucher',
        'obligation-report' => 'Obligation Report',
        'requisition-issue-slip' => 'Requisition and Issue Slip',
        'inspection-acceptance-report' => 'Inspection and Acceptance Report',
    ];

    private const PURCHASE_SEQUENCE = [
        'purchase-request',
        'bac-reports',
        'request-for-quotation',
        'abstract-of-canvass',
        'purchase-order',
        'disbursement-voucher',
        'obligation-report',
        'requisition-issue-slip',
        'inspection-acceptance-report',
    ];

    private const VOUCHER_SEQUENCE = [
        'disbursement-voucher',
        'obligation-report',
        'requisition-issue-slip',
        'inspection-acceptance-report',
    ];

    public static function labels(): array
    {
        return self::ALL_STEPS;
    }

    public static function label(string $step): string
    {
        return self::ALL_STEPS[$step] ?? ucwords(str_replace('-', ' ', $step));
    }

    public static function requiredFields(string $step): array
    {
        if ($step === 'purchase-request') {
            return self::PURCHASE_HEADER_FIELDS;
        }

        if (TemplateMapping::has($step)) {
            return TemplateMapping::transactionFields($step);
        }

        return [
            'reference_number' => 'Reference Number',
            'report_date' => 'Report Date',
            'prepared_by' => 'Prepared By',
        ];
    }

    public function createProcess(string $startType, ?int $userId): int
    {
        $sequence = $this->sequenceFor($startType);
        $processCode = 'BTS-' . date('Ymd-His') . '-' . random_int(100, 999);

        $stmt = $this->db->prepare(
            'INSERT INTO report_processes (process_code, start_type, current_step, status, started_by) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$processCode, $startType, $sequence[0], 'in_progress', $userId]);
        $processId = (int) $this->db->lastInsertId();

        foreach ($sequence as $index => $step) {
            $stepStmt = $this->db->prepare(
                'INSERT INTO report_steps (process_id, step_key, report_type, step_order) VALUES (?, ?, ?, ?)'
            );
            $stepStmt->execute([$processId, $step, self::label($step), $index + 1]);
        }

        $this->upsertArchiveCheckpoint($processId, $processCode, $startType, count($sequence), 'in_progress');

        return $processId;
    }

    public function getProcess(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM report_processes WHERE id = ? AND (status IS NULL OR status <> 'deleted') LIMIT 1");
        $stmt->execute([$id]);
        $process = $stmt->fetch();

        return $process ?: null;
    }

    public function getStep(int $processId, string $step): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM report_steps WHERE process_id = ? AND step_key = ? LIMIT 1');
        $stmt->execute([$processId, $step]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function getSteps(int $processId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM report_steps WHERE process_id = ? ORDER BY step_order');
        $stmt->execute([$processId]);

        return $stmt->fetchAll();
    }


    public function getStepData(int $stepId, bool $includeInternal = false): array
    {
        $stmt = $this->db->prepare(
            'SELECT field_key, field_value
             FROM report_step_data
             WHERE report_step_id = ?
             ORDER BY id'
        );
        $stmt->execute([$stepId]);

        $data = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!$includeInternal && $this->isInternalField($row['field_key'])) {
                continue;
            }

            $data[$row['field_key']] = $row['field_value'];
        }

        return $data;
    }

    public function stepHasValidSavedData(int $processId, array $step): bool
    {
        if ((int) ($step['is_saved'] ?? 0) !== 1) {
            return false;
        }

        if ((string) ($step['step_key'] ?? '') !== 'obligation-report') {
            return true;
        }

        $data = $this->getStepData((int) ($step['id'] ?? 0));
        $obligationDate = trim((string) ($data['OBLIDATE'] ?? ''));
        $specificFund = trim((string) ($data['SPECFUND'] ?? ''));

        if ($obligationDate === '' || $specificFund === '') {
            return false;
        }

        if ($this->obligationUsesGeneralFund($processId)) {
            return in_array($specificFund, self::obligationSpecificFundOptions(), true);
        }

        return true;
    }

    public function getProcessData(int $processId): array
    {
        $stmt = $this->db->prepare(
            'SELECT d.field_key, d.field_value
             FROM report_step_data d
             INNER JOIN report_steps s ON s.id = d.report_step_id
             WHERE s.process_id = ?
             ORDER BY s.step_order, d.id'
        );
        $stmt->execute([$processId]);

        $data = [];
        foreach ($stmt->fetchAll() as $row) {
            if ($this->isInternalField($row['field_key'])) {
                continue;
            }

            $data[$row['field_key']] = $row['field_value'];
        }

        $firstItem = $this->getFirstPurchaseItem($processId);
        if ($firstItem !== null) {
            $data['INPR'] = $firstItem['item_no'];
            $data['PRQTY'] = $firstItem['quantity'];
            $data['UOE'] = $firstItem['unit_of_measurement'];
            $data['PRPART'] = $firstItem['particulars'];
            $data['EUC'] = $firstItem['estimated_unit_cost'];
        }

        return $data;
    }

    public function getSystemInputData(): array
    {
        $stmt = $this->db->query('SELECT input_key, input_value FROM system_inputs');

        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function getTemplateData(int $processId, int $stepId): array
    {
        $stepKey = $this->stepKeyForTemplateData($processId, $stepId);
        $systemData = $this->getSystemInputData();
        $data = array_merge(
            $systemData,
            $this->getProcessData($processId),
            $this->getStepData($stepId, true),
            $this->getSharedDateValues($processId, $stepKey)
        );

        if ($stepKey === 'purchase-request' && trim((string) ($data['PRNUM'] ?? '')) === '') {
            $data['PRNUM'] = $this->nextPurchaseRequestNumber((string) ($systemData['PR_NUMBER_SERIES'] ?? ''));
        }
        if ($stepKey === 'bac-reports' && trim((string) ($data['BACRNUM'] ?? '')) === '') {
            $data['BACRNUM'] = $this->nextBacResolutionNumber((string) ($systemData['BAC_RESOLUTION_SERIES'] ?? ''));
        }
        if ($stepKey === 'purchase-order') {
            if (trim((string) ($data['PONUM'] ?? '')) === '') {
                $data['PONUM'] = $this->nextPurchaseOrderNumber((string) ($systemData['PO_NUMBER_SERIES'] ?? ''));
            }

            $supplierDefaults = $this->purchaseOrderSupplierDefaults($processId, $data);
            if (trim((string) ($data['PAYEE'] ?? '')) === '') {
                $data['PAYEE'] = $supplierDefaults['PAYEE'];
            }
            if (trim((string) ($data['PAYEE_ADDRESS'] ?? '')) === '') {
                $data['PAYEE_ADDRESS'] = $supplierDefaults['PAYEE_ADDRESS'];
            }

            $paymentMode = trim((string) ($data['PO_MODE_OF_PAYMENT'] ?? ''));
            $data['TIN'] = '';
            $data['BID'] = $paymentMode === 'BID' ? '☒' : '☐';
            $data['NEG'] = $paymentMode === 'NEG' ? '☒' : '☐';
            $data['SHOP'] = $paymentMode === 'SHOP' ? '☒' : '☐';
        }
        if ($stepKey === 'abstract-of-canvass') {
            $data = array_merge($data, $this->abstractCanvassTemplateData($processId, $stepId, $data));
        }
        if ($stepKey === 'disbursement-voucher') {
            $data = array_merge($data, $this->disbursementVoucherTemplateData($processId, $stepId, $data));
        }
        if ($stepKey === 'obligation-report') {
            $data = array_merge($data, $this->obligationReportTemplateData($processId, $data));
        }
        if ($stepKey === 'inspection-acceptance-report' && trim((string) ($data['IAS'] ?? '')) === '') {
            $data['IAS'] = $this->nextIarNumber((string) ($systemData['IAR_NUMBER_SERIES'] ?? ''));
        }
        if ($stepKey === 'requisition-issue-slip') {
            if (trim((string) ($data['RAINO'] ?? '')) === '') {
                $data['RAINO'] = $this->nextRisNumber((string) ($systemData['RIS_NUMBER_SERIES'] ?? ''));
            }

            $prPurpose = trim((string) ($data['PRPURPOSE'] ?? ''));
            $risPurposeSource = trim((string) ($data[self::RIS_PURPOSE_SOURCE_META_KEY] ?? ''));
            $legacyShouldFollowPrPurpose = $risPurposeSource === '' && $prPurpose !== '';

            if ($prPurpose !== '' && ($risPurposeSource === 'pr' || $legacyShouldFollowPrPurpose)) {
                $data['PURPOSE'] = $prPurpose;
                $data[self::RIS_PURPOSE_SOURCE_META_KEY] = 'pr';
            } elseif (trim((string) ($data['PURPOSE'] ?? '')) === '') {
                if (trim((string) ($data['RISPURPOSE'] ?? '')) !== '') {
                    $data['PURPOSE'] = trim((string) $data['RISPURPOSE']);
                } elseif ($prPurpose !== '') {
                    $data['PURPOSE'] = $prPurpose;
                    $data[self::RIS_PURPOSE_SOURCE_META_KEY] = 'pr';
                }
            }
        }

        return $this->withDefaultDateValues($stepKey, $data);
    }

    private function withDefaultDateValues(string $stepKey, array $data): array
    {
        foreach (self::requiredFields($stepKey) as $key => $_label) {
            if ($this->isCalendarDateField((string) $key) && trim((string) ($data[$key] ?? '')) === '') {
                $data[$key] = date('Y-m-d');
            }
        }

        return $data;
    }

    private function isCalendarDateField(string $key): bool
    {
        $upper = strtoupper($key);

        return $key === 'DatePR'
            || $key === 'DATET'
            || $key === 'report_date'
            || str_contains($upper, 'DATE');
    }

    private function abstractCanvassTemplateData(int $processId, int $stepId, array $data): array
    {
        $bids = $this->getSupplierBidsForAbstract($processId, $stepId);
        $validBids = array_values(array_filter($bids, static fn (array $bid): bool => trim((string) ($bid['SUPPNAME'] ?? '')) !== '' && trim((string) ($bid['AOP'] ?? '')) !== ''));
        if ($validBids === []) {
            return [];
        }

        $selectedSupplier = trim((string) ($data['LBSUPPNAME'] ?? $data['LOWBIDSUPPNAME'] ?? ''));
        $selectedBid = $selectedSupplier !== '' ? $this->abstractBidBySupplier($validBids, $selectedSupplier) : null;
        if ($selectedBid === null) {
            $selectedBid = $this->lowestAbstractBid(array_values(array_filter($validBids, static fn (array $bid): bool => is_numeric($bid['AOP']))));
        }
        if ($selectedBid === null) {
            $selectedBid = $validBids[0];
        }

        $supplierNames = array_map(static fn (array $bid): string => trim((string) ($bid['SUPPNAME'] ?? '')), $validBids);
        $proposalAmounts = array_map(static fn (array $bid): string => trim((string) ($bid['AOP'] ?? '')), $validBids);
        $selectedAmount = trim((string) ($selectedBid['AOP'] ?? ''));

        return [
            'SUPPNAME' => implode("\n", $supplierNames),
            'SUPPAOP' => implode("\n", $proposalAmounts),
            'AOP' => implode("\n", $proposalAmounts),
            'LBSUPPNAME' => trim((string) ($selectedBid['SUPPNAME'] ?? '')),
            'LOWBIDSUPPNAME' => trim((string) ($selectedBid['SUPPNAME'] ?? '')),
            'LOWESTBIDAMOUNT' => $selectedAmount,
            'LOWBIDSUPPAOP' => $selectedAmount,
            'LOWBIDAOP' => $selectedAmount,
        ];
    }

    private function nextPurchaseRequestNumber(string $series): string
    {
        $parsed = $this->parsePurchaseRequestNumber($series);
        if ($parsed === null) {
            return '';
        }

        [$year, $month, $seedNumber] = $parsed;
        $prefix = sprintf('%04d-%02d-', $year, $month);
        $nextNumber = $seedNumber;

        $stmt = $this->db->prepare(
            "SELECT d.field_value
             FROM report_step_data d
             INNER JOIN report_steps s ON s.id = d.report_step_id
             WHERE d.field_key = 'PRNUM'
               AND s.step_key = 'purchase-request'
               AND d.field_value LIKE ?"
        );
        $stmt->execute([$prefix . '%']);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
            $existing = $this->parsePurchaseRequestNumber((string) $value);
            if ($existing === null || $existing[0] !== $year || $existing[1] !== $month) {
                continue;
            }

            $nextNumber = max($nextNumber, $existing[2] + 1);
        }

        return sprintf('%s%03d', $prefix, $nextNumber);
    }

    private function parsePurchaseRequestNumber(string $value): ?array
    {
        $clean = trim($value);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d+)$/', $clean, $matches) !== 1) {
            return null;
        }

        return [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
    }

    private function nextBacResolutionNumber(string $series): string
    {
        $parsed = $this->parsePurchaseRequestNumber($series);
        if ($parsed === null) {
            return trim($series);
        }

        [$year, $month, $seedNumber] = $parsed;
        $prefix = sprintf('%04d-%02d-', $year, $month);
        $nextNumber = $seedNumber;

        $stmt = $this->db->prepare(
            "SELECT d.field_value
             FROM report_step_data d
             INNER JOIN report_steps s ON s.id = d.report_step_id
             WHERE d.field_key = 'BACRNUM'
               AND s.step_key = 'bac-reports'
               AND d.field_value LIKE ?"
        );
        $stmt->execute([$prefix . '%']);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
            $existing = $this->parsePurchaseRequestNumber((string) $value);
            if ($existing === null || $existing[0] !== $year || $existing[1] !== $month) {
                continue;
            }

            $nextNumber = max($nextNumber, $existing[2] + 1);
        }

        return sprintf('%s%03d', $prefix, $nextNumber);
    }

    private function nextPurchaseOrderNumber(string $series): string
    {
        $parsed = $this->parsePurchaseRequestNumber($series);
        if ($parsed === null) {
            return trim($series);
        }

        [$year, $month, $seedNumber] = $parsed;
        $prefix = sprintf('%04d-%02d-', $year, $month);
        $nextNumber = $seedNumber;

        $stmt = $this->db->prepare(
            "SELECT d.field_value
             FROM report_step_data d
             INNER JOIN report_steps s ON s.id = d.report_step_id
             WHERE d.field_key = 'PONUM'
               AND s.step_key = 'purchase-order'
               AND d.field_value LIKE ?"
        );
        $stmt->execute([$prefix . '%']);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
            $existing = $this->parsePurchaseRequestNumber((string) $value);
            if ($existing === null || $existing[0] !== $year || $existing[1] !== $month) {
                continue;
            }

            $nextNumber = max($nextNumber, $existing[2] + 1);
        }

        return sprintf('%s%03d', $prefix, $nextNumber);
    }

    private function nextDisbursementVoucherNumber(string $series): string
    {
        $parsed = $this->parsePurchaseRequestNumber($series);
        if ($parsed === null) {
            return trim($series);
        }

        [$year, $month, $seedNumber] = $parsed;
        $prefix = sprintf('%04d-%02d-', $year, $month);
        $nextNumber = $seedNumber;

        $stmt = $this->db->prepare(
            "SELECT d.field_value
             FROM report_step_data d
             INNER JOIN report_steps s ON s.id = d.report_step_id
             WHERE d.field_key = 'DIVNO'
               AND s.step_key = 'disbursement-voucher'
               AND d.field_value LIKE ?"
        );
        $stmt->execute([$prefix . '%']);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
            $existing = $this->parsePurchaseRequestNumber((string) $value);
            if ($existing === null || $existing[0] !== $year || $existing[1] !== $month) {
                continue;
            }

            $nextNumber = max($nextNumber, $existing[2] + 1);
        }

        return sprintf('%s%03d', $prefix, $nextNumber);
    }

    private function nextOrNumber(string $series): string
    {
        $parsed = $this->parsePurchaseRequestNumber($series);
        if ($parsed === null) {
            return trim($series);
        }

        [$year, $month, $seedNumber] = $parsed;
        $prefix = sprintf('%04d-%02d-', $year, $month);
        $nextNumber = $seedNumber;

        $stmt = $this->db->prepare(
            "SELECT d.field_value
             FROM report_step_data d
             INNER JOIN report_steps s ON s.id = d.report_step_id
             WHERE d.field_key = 'ORNO'
               AND s.step_key = 'disbursement-voucher'
               AND d.field_value LIKE ?"
        );
        $stmt->execute([$prefix . '%']);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
            $existing = $this->parsePurchaseRequestNumber((string) $value);
            if ($existing === null || $existing[0] !== $year || $existing[1] !== $month) {
                continue;
            }

            $nextNumber = max($nextNumber, $existing[2] + 1);
        }

        return sprintf('%s%03d', $prefix, $nextNumber);
    }

    private function nextRisNumber(string $series): string
    {
        $parsed = $this->parsePurchaseRequestNumber($series);
        if ($parsed === null) {
            return trim($series);
        }

        [$year, $month, $seedNumber] = $parsed;
        $prefix = sprintf('%04d-%02d-', $year, $month);
        $nextNumber = $seedNumber;

        $stmt = $this->db->prepare(
            "SELECT d.field_value
             FROM report_step_data d
             INNER JOIN report_steps s ON s.id = d.report_step_id
             WHERE d.field_key = 'RAINO'
               AND s.step_key = 'requisition-issue-slip'
               AND d.field_value LIKE ?"
        );
        $stmt->execute([$prefix . '%']);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
            $existing = $this->parsePurchaseRequestNumber((string) $value);
            if ($existing === null || $existing[0] !== $year || $existing[1] !== $month) {
                continue;
            }

            $nextNumber = max($nextNumber, $existing[2] + 1);
        }

        return sprintf('%s%03d', $prefix, $nextNumber);
    }
    private function nextIarNumber(string $series): string
    {
        $parsed = $this->parsePurchaseRequestNumber($series);
        if ($parsed === null) {
            return trim($series);
        }

        [$year, $month, $seedNumber] = $parsed;
        $prefix = sprintf('%04d-%02d-', $year, $month);
        $nextNumber = $seedNumber;

        $stmt = $this->db->prepare(
            "SELECT d.field_value
             FROM report_step_data d
             INNER JOIN report_steps s ON s.id = d.report_step_id
             WHERE d.field_key = 'IAS'
               AND s.step_key = 'inspection-acceptance-report'
               AND d.field_value LIKE ?"
        );
        $stmt->execute([$prefix . '%']);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
            $existing = $this->parsePurchaseRequestNumber((string) $value);
            if ($existing === null || $existing[0] !== $year || $existing[1] !== $month) {
                continue;
            }

            $nextNumber = max($nextNumber, $existing[2] + 1);
        }

        return sprintf('%s%03d', $prefix, $nextNumber);
    }
    private function purchaseOrderSupplierDefaults(int $processId, array $data): array
    {
        $abstractStep = $this->getStep($processId, 'abstract-of-canvass');
        $abstractData = $abstractStep ? $this->getStepData((int) $abstractStep['id']) : [];
        $rfqStep = $this->getStep($processId, 'request-for-quotation');
        $rfqBids = $rfqStep ? $this->getRfqSupplierBids((int) $rfqStep['id']) : [];
        $abstractBids = $abstractStep ? $this->getAbstractSupplierBids((int) $abstractStep['id']) : [];
        $bids = $rfqBids !== [] ? $rfqBids : $abstractBids;

        $preferredSupplier = trim((string) (
            $data['LBSUPPNAME']
            ?? $data['LOWBIDSUPPNAME']
            ?? $abstractData['LBSUPPNAME']
            ?? $abstractData['LOWBIDSUPPNAME']
            ?? ''
        ));

        $selectedBid = null;
        if ($preferredSupplier !== '') {
            foreach ($bids as $bid) {
                if (strcasecmp(trim((string) ($bid['SUPPNAME'] ?? '')), $preferredSupplier) === 0) {
                    $selectedBid = $bid;
                    break;
                }
            }
        }

        if ($selectedBid === null && $bids !== []) {
            $sortedBids = $this->sortSupplierBidsByAmount($bids);
            $selectedBid = $sortedBids[0] ?? $bids[0];
        }

        $supplierName = trim((string) ($selectedBid['SUPPNAME'] ?? $preferredSupplier));
        $supplierAddress = trim((string) ($selectedBid['SUPPADD'] ?? $selectedBid['LBADD'] ?? $data['SUPPADD'] ?? $data['LBADD'] ?? ''));

        return [
            'PAYEE' => $supplierName,
            'PAYEE_ADDRESS' => $supplierAddress,
        ];
    }

    public function hasDisbursementVoucherPurchaseOrderSource(int $processId): bool
    {
        $purchaseOrderStep = $this->getStep($processId, 'purchase-order');

        return $purchaseOrderStep !== null;
    }

    private function disbursementVoucherTemplateData(int $processId, int $stepId, array $data): array
    {
        $resolved = [];
        $particularsBody = self::disbursementParticularsBody((string) ($data['PARTICULARS'] ?? ''));
        if ($particularsBody !== '') {
            $resolved['PARTICULARS'] = $particularsBody . ' ' . self::DV_PARTICULARS_SUFFIX;
        }
        if (trim((string) ($data['DIVNO'] ?? '')) === '') {
            $resolved['DIVNO'] = $this->nextDisbursementVoucherNumber((string) ($data['DV_NUMBER_SERIES'] ?? ''));
        }
        if (trim((string) ($data['ORNO'] ?? '')) === '') {
            $resolved['ORNO'] = $this->nextOrNumber((string) ($data['OR_NUMBER_SERIES'] ?? ''));
        }

        if ($this->hasDisbursementVoucherPurchaseOrderSource($processId)) {
            $poData = $this->purchaseOrderData($processId);
            foreach (['PAYEE', 'PAYEE_ADDRESS', 'TIN'] as $key) {
                if (trim((string) ($data[$key] ?? '')) === '' && trim((string) ($poData[$key] ?? '')) !== '') {
                    $resolved[$key] = trim((string) $poData[$key]);
                }
            }

            $gross = $this->purchaseOrderGross($processId);
            if ($gross !== '') {
                $resolved['GROSS'] = $gross;
            }
        }

        if (trim((string) ($data['CHECKNO'] ?? '')) === '') {
            $resolved['CHECKNO'] = $this->nextCheckNumber($stepId);
        }

        $gross = (string) ($resolved['GROSS'] ?? $data['GROSS'] ?? '');
        $taxType = trim((string) ($data['DV_TAX_TYPE'] ?? ''));
        if ($taxType === '' && (string) ($data[self::DV_WHTAX_ENABLED_META_KEY] ?? '') === '1') {
            $taxType = 'Withholding Tax';
            $resolved['DV_TAX_TYPE'] = $taxType;
        }
        $resolved['DV_TAX_LINE_LABEL'] = $taxType !== '' ? 'Less: ' . $taxType : '';
        $tax = trim((string) ($data['WHTAX'] ?? ''));
        if ($tax === '') {
            $tax = '0';
            $resolved['WHTAX'] = '0';
        }
        if ($gross !== '' && is_numeric($gross) && is_numeric($tax)) {
            $resolved['NETAMOUNT'] = $this->formatMoney((float) $gross - (float) $tax);
        }
        if ($taxType === '') {
            $resolved['WHTAX'] = '';
        }

        return $resolved;
    }

    public static function disbursementParticularsBody(string $value): string
    {
        $body = trim($value);
        $suffix = preg_quote(self::DV_PARTICULARS_SUFFIX, '/');
        $body = preg_replace('/\s*' . $suffix . '\s*$/iu', '', $body) ?? $body;

        return trim($body);
    }

    public function disbursementLowestBidAmount(int $processId): string
    {
        $step = $this->getStep($processId, 'abstract-of-canvass');
        if (!$step) {
            return '';
        }

        $data = $this->getStepData((int) $step['id']);
        return trim((string) ($data['LOWESTBIDAMOUNT'] ?? $data['LOWBIDSUPPAOP'] ?? ''));
    }

    private function configuredDisbursementTaxTypes(): array
    {
        $json = (string) ($this->getSystemInputData()['DV_TAX_TYPES'] ?? '[]');
        $types = json_decode($json, true);
        if (!is_array($types)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($type): string => trim((string) $type), $types), static fn (string $type): bool => $type !== ''));
    }

    private function purchaseOrderData(int $processId): array
    {
        $step = $this->getStep($processId, 'purchase-order');
        if (!$step) {
            return [];
        }

        return $this->getStepData((int) $step['id']);
    }

    private function purchaseOrderGross(int $processId): string
    {
        $total = 0.0;
        $hasAmount = false;
        foreach ($this->getPurchaseOrderItemRows($processId) as $row) {
            $amount = trim((string) ($row['POAMOUNT'] ?? ''));
            if ($amount === '' || !is_numeric($amount)) {
                continue;
            }

            $total += (float) $amount;
            $hasAmount = true;
        }

        return $hasAmount ? $this->formatMoney($total) : '';
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function configuredCheckNumberRange(): ?array
    {
        $systemData = $this->getSystemInputData();
        $start = trim((string) ($systemData['CHECKNO_START'] ?? ''));
        $end = trim((string) ($systemData['CHECKNO_END'] ?? ''));
        if ($start === '' || $end === '' || !ctype_digit($start) || !ctype_digit($end)) {
            return null;
        }

        $startNumber = (int) $start;
        $endNumber = (int) $end;
        if ($startNumber > $endNumber) {
            return null;
        }

        return [$startNumber, $endNumber];
    }

    private function nextCheckNumber(?int $ignoreStepId = null): string
    {
        $range = $this->configuredCheckNumberRange();
        if ($range === null) {
            return '';
        }

        [$start, $end] = $range;
        $used = $this->usedCheckNumbers($ignoreStepId);
        for ($number = $start; $number <= $end; $number++) {
            if (!isset($used[(string) $number])) {
                return (string) $number;
            }
        }

        return '';
    }

    private function usedCheckNumbers(?int $ignoreStepId = null): array
    {
        $sql = "SELECT d.field_value
                FROM report_step_data d
                INNER JOIN report_steps s ON s.id = d.report_step_id
                WHERE d.field_key = 'CHECKNO'
                  AND s.step_key = 'disbursement-voucher'";
        if ($ignoreStepId !== null) {
            $sql .= ' AND d.report_step_id <> ?';
            $params[] = $ignoreStepId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $used = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
            $clean = trim((string) $value);
            if ($clean !== '') {
                $used[$clean] = true;
            }
        }

        return $used;
    }
    public static function obligationSpecificFundOptions(): array
    {
        return array_values(array_merge(...array_values(self::OBLIGATION_SPECIFIC_FUND_GROUPS)));
    }

    public function obligationUsesGeneralFund(int $processId): bool
    {
        return strcasecmp(trim((string) ($this->getProcessData($processId)['DDFUND'] ?? '')), 'General Funds') === 0;
    }

    private function obligationReportTemplateData(int $processId, array $data): array
    {
        $resolved = [];
        $processData = $this->getProcessData($processId);

        foreach (['PAYEE', 'PAYEE_ADDRESS', 'DDFUND', 'NETAMOUNT', 'ORNO', 'PARTICULARS'] as $key) {
            if (trim((string) ($data[$key] ?? '')) === '' && trim((string) ($processData[$key] ?? '')) !== '') {
                $resolved[$key] = trim((string) $processData[$key]);
            }
        }

        return $resolved;
    }
    public function getInheritedData(int $processId, string $step): array
    {
        $processData = $this->getProcessData($processId);
        $inherited = [];

        foreach (TemplateMapping::inheritedFields($step) as $field) {
            if (array_key_exists($field, $processData)) {
                $inherited[$field] = $processData[$field];
            }
        }

        return $inherited;
    }

    public function getSystemDataForStep(string $step): array
    {
        $systemData = $this->getSystemInputData();
        $values = [];

        foreach (TemplateMapping::systemFields($step) as $field) {
            $values[$field] = $systemData[$field] ?? '';
        }

        return $values;
    }

    public function getPurchaseItems(int $processId): array
    {
        $stmt = $this->db->prepare(
            "SELECT item_no, quantity, unit_of_measurement, particulars, estimated_unit_cost
             FROM report_items
             WHERE process_id = ? AND step_key = 'purchase-request'
             ORDER BY row_order, id"
        );
        $stmt->execute([$processId]);

        return $stmt->fetchAll();
    }

    public function normalizePurchaseItems(array $input): array
    {
        $items = $input['items'] ?? [];
        $normalized = [];

        if (!is_array($items)) {
            return $normalized;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $normalized[] = [
                'INPR' => (string) (count($normalized) + 1),
                'PRQTY' => trim((string) ($item['PRQTY'] ?? '')),
                'UOE' => trim((string) ($item['UOE'] ?? '')),
                'PRPART' => trim((string) ($item['PRPART'] ?? '')),
                'EUC' => trim((string) ($item['EUC'] ?? '')),
            ];
        }

        return $normalized;
    }
    public function normalizeAbstractSupplierBids(array $input): array
    {
        $rows = $input['supplier_bids'] ?? [];
        $normalized = [];

        if (!is_array($rows)) {
            return $normalized;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized[] = [
                'SUPPNAME' => trim((string) ($row['SUPPNAME'] ?? '')),
                'SUPPADD' => trim((string) ($row['SUPPADD'] ?? $row['LBADD'] ?? '')),
                'AOP' => trim((string) ($row['AOP'] ?? '')),
            ];
        }

        return $normalized;
    }

    public function normalizeRfqSupplierBids(array $input): array
    {
        $rows = $input['supplier_bids'] ?? [];
        $normalized = [];

        if (!is_array($rows)) {
            return $normalized;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized[] = [
                'SUPPNAME' => trim((string) ($row['SUPPNAME'] ?? '')),
                'SUPPADD' => trim((string) ($row['SUPPADD'] ?? $row['LBADD'] ?? '')),
            ];
        }

        return $normalized;
    }

    public function getAbstractSupplierBids(int $stepId): array
    {
        $data = $this->getStepData($stepId, true);
        $json = (string) ($data[self::ABSTRACT_BIDS_META_KEY] ?? '');
        $rows = $json !== '' ? json_decode($json, true) : [];

        if (!is_array($rows) || $rows === []) {
            if (($data['SUPPNAME'] ?? '') !== '' || ($data['SUPPADD'] ?? $data['LBADD'] ?? '') !== '' || ($data['AOP'] ?? '') !== '') {
                return [[
                    'SUPPNAME' => (string) ($data['SUPPNAME'] ?? ''),
                    'SUPPADD' => (string) ($data['SUPPADD'] ?? $data['LBADD'] ?? ''),
                    'AOP' => (string) ($data['AOP'] ?? ''),
                ]];
            }

            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized[] = [
                'SUPPNAME' => trim((string) ($row['SUPPNAME'] ?? '')),
                'SUPPADD' => trim((string) ($row['SUPPADD'] ?? $row['LBADD'] ?? '')),
                'AOP' => trim((string) ($row['AOP'] ?? '')),
            ];
        }

        return $normalized;
    }

    public function getRfqSupplierBids(int $stepId): array
    {
        $data = $this->getStepData($stepId, true);
        $json = (string) ($data[self::RFQ_SUPPLIER_BIDS_META_KEY] ?? '');
        $rows = $json !== '' ? json_decode($json, true) : [];

        if (!is_array($rows) || $rows === []) {
            if (($data['SUPPNAME'] ?? '') !== '' || ($data['SUPPADD'] ?? $data['LBADD'] ?? '') !== '' || ($data['AOP'] ?? '') !== '') {
                return [[
                    'SUPPNAME' => (string) ($data['SUPPNAME'] ?? ''),
                    'SUPPADD' => (string) ($data['SUPPADD'] ?? $data['LBADD'] ?? ''),
                    'AOP' => (string) ($data['AOP'] ?? ''),
                ]];
            }

            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized[] = [
                'SUPPNAME' => trim((string) ($row['SUPPNAME'] ?? '')),
                'SUPPADD' => trim((string) ($row['SUPPADD'] ?? $row['LBADD'] ?? '')),
                'AOP' => trim((string) ($row['AOP'] ?? '')),
            ];
        }

        return $normalized;
    }

    public function getSupplierBidsForAbstract(int $processId, int $abstractStepId, ?array $input = null): array
    {
        $rfqStep = $this->getStep($processId, 'request-for-quotation');
        $rfqBids = $rfqStep ? $this->getRfqSupplierBids((int) $rfqStep['id']) : [];
        $savedAbstractBids = $this->getAbstractSupplierBids($abstractStepId);
        $postedRows = is_array($input['supplier_bids'] ?? null) ? $input['supplier_bids'] : [];

        if ($rfqBids === []) {
            return $savedAbstractBids;
        }

        $amountsBySupplier = [];
        foreach ($savedAbstractBids as $bid) {
            $key = $this->supplierBidIdentity($bid);
            if ($key !== '') {
                $amountsBySupplier[$key] = trim((string) ($bid['AOP'] ?? ''));
            }
        }

        $bids = [];
        foreach ($rfqBids as $index => $rfqBid) {
            $identity = $this->supplierBidIdentity($rfqBid);
            $postedAmount = is_array($postedRows[$index] ?? null)
                ? trim((string) ($postedRows[$index]['AOP'] ?? ''))
                : null;
            $amount = $postedAmount
                ?? ($amountsBySupplier[$identity] ?? trim((string) ($rfqBid['AOP'] ?? '')));

            $bids[] = [
                'SUPPNAME' => trim((string) ($rfqBid['SUPPNAME'] ?? '')),
                'SUPPADD' => trim((string) ($rfqBid['SUPPADD'] ?? $rfqBid['LBADD'] ?? '')),
                'AOP' => $amount,
            ];
        }

        return $bids;
    }

    private function supplierBidIdentity(array $bid): string
    {
        $name = strtolower(trim((string) ($bid['SUPPNAME'] ?? '')));
        $address = strtolower(trim((string) ($bid['SUPPADD'] ?? $bid['LBADD'] ?? '')));

        return $name === '' && $address === '' ? '' : $name . "\n" . $address;
    }
    public function normalizeRfqQuoteRows(array $input): array
    {
        $rows = $input['rfq_items'] ?? [];
        $normalized = [];

        if (!is_array($rows)) {
            return $normalized;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized[] = [
                'ABSTRACTAOPDIVIDEPRQTY' => trim((string) ($row['ABSTRACTAOPDIVIDEPRQTY'] ?? '')),
            ];
        }

        return $normalized;
    }

    public function getRfqQuoteRows(int $stepId): array
    {
        $data = $this->getStepData($stepId, true);
        $json = (string) ($data[self::RFQ_QUOTES_META_KEY] ?? '');
        $rows = $json !== '' ? json_decode($json, true) : [];

        if (!is_array($rows) || $rows === []) {
            if (($data['ABSTRACTAOPDIVIDEPRQTY'] ?? '') !== '') {
                return [[
                    'ABSTRACTAOPDIVIDEPRQTY' => (string) $data['ABSTRACTAOPDIVIDEPRQTY'],
                ]];
            }

            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized[] = [
                'ABSTRACTAOPDIVIDEPRQTY' => trim((string) ($row['ABSTRACTAOPDIVIDEPRQTY'] ?? '')),
            ];
        }

        return $normalized;
    }

    public function getRfqItemRows(int $processId, int $stepId, ?array $quoteRows = null): array
    {
        $purchaseItems = $this->getPurchaseItems($processId);
        $quotes = $quoteRows ?? $this->getRfqQuoteRows($stepId);
        $rows = [];

        foreach ($purchaseItems as $index => $item) {
            $rows[] = [
                'PRQTY' => (string) ($item['quantity'] ?? ''),
                'UOE' => (string) ($item['unit_of_measurement'] ?? ''),
                'PRPART' => (string) ($item['particulars'] ?? ''),
                'ABSTRACTAOPDIVIDEPRQTY' => (string) ($quotes[$index]['ABSTRACTAOPDIVIDEPRQTY'] ?? ''),
            ];
        }

        return $rows;
    }

    public function normalizePurchaseOrderItems(array $input): array
    {
        $rows = $input['po_items'] ?? [];
        $normalized = [];

        if (!is_array($rows)) {
            return $normalized;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized[] = [
                'POUNITCOST' => trim((string) ($row['POUNITCOST'] ?? '')),
            ];
        }

        return $normalized;
    }

    public function getPurchaseOrderItemRows(int $processId): array
    {
        $purchaseItems = $this->getPurchaseItems($processId);
        $savedRows = $this->purchaseOrderSavedItemRows($processId);
        $rows = [];

        foreach ($purchaseItems as $index => $item) {
            $unitCost = (string) ($savedRows[$index]['POUNITCOST'] ?? '');
            $quantity = (string) ($item['quantity'] ?? '');

            $rows[] = [
                'UOE' => (string) ($item['unit_of_measurement'] ?? ''),
                'PRPART' => (string) ($item['particulars'] ?? ''),
                'PRQTY' => $quantity,
                'POUNITCOST' => $unitCost,
                'POAMOUNT' => $this->purchaseOrderAmount($quantity, $unitCost),
            ];
        }

        return $rows;
    }

    private function purchaseOrderSavedItemRows(int $processId): array
    {
        $step = $this->getStep($processId, 'purchase-order');
        if (!$step) {
            return [];
        }

        $data = $this->getStepData((int) $step['id'], true);
        $json = (string) ($data[self::PURCHASE_ORDER_ITEMS_META_KEY] ?? '');
        $rows = $json !== '' ? json_decode($json, true) : [];
        if (!is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized[] = [
                'POUNITCOST' => trim((string) ($row['POUNITCOST'] ?? '')),
            ];
        }

        return $normalized;
    }

    private function purchaseOrderAmount(string $quantity, string $unitCost): string
    {
        $quantity = trim($quantity);
        $unitCost = trim($unitCost);
        if ($quantity === '' || $unitCost === '' || !is_numeric($quantity) || !is_numeric($unitCost)) {
            return '';
        }

        return number_format((float) $quantity * (float) $unitCost, 2, '.', '');
    }

    public function getRequisitionIssueSlipItemRows(int $processId, ?array $sourceRows = null): array
    {
        $rows = [];

        foreach ($this->getPurchaseItems($processId) as $item) {
            $rows[] = [
                'UOE' => (string) ($item['unit_of_measurement'] ?? ''),
                'PRPART' => (string) ($item['particulars'] ?? ''),
                'PRQTY' => (string) ($item['quantity'] ?? ''),
            ];
        }

        return $rows;
    }

    public function getInspectionAcceptanceItemRows(int $processId): array
    {
        $rows = [];

        foreach ($this->getPurchaseItems($processId) as $item) {
            $rows[] = [
                'UOE' => (string) ($item['unit_of_measurement'] ?? ''),
                'PRPART' => (string) ($item['particulars'] ?? ''),
                'PRQTY' => (string) ($item['quantity'] ?? ''),
            ];
        }

        return $rows;
    }
    public function saveStepData(int $stepId, string $step, array $input, ?int $processId = null): array
    {
        if ($step === 'purchase-request') {
            if ($processId === null) {
                return ['form' => 'Purchase Request process was not found.'];
            }

            return $this->savePurchaseRequest((int) $processId, $stepId, $input);
        }

        if ($step === 'abstract-of-canvass') {
            if ($processId === null) {
                return ['form' => 'Abstract of Canvass process was not found.'];
            }

            return $this->saveAbstractOfCanvass((int) $processId, $stepId, $input);
        }

        if ($step === 'request-for-quotation') {
            if ($processId === null) {
                return ['form' => 'Request for Quotation process was not found.'];
            }

            return $this->saveRequestForQuotation((int) $processId, $stepId, $input);
        }

        if ($step === 'bac-reports') {
            if ($processId === null) {
                return ['form' => 'BAC Reports process was not found.'];
            }

            return $this->saveBacReports((int) $processId, $stepId, $input);
        }

        if ($step === 'purchase-order') {
            if ($processId === null) {
                return ['form' => 'Purchase Order process was not found.'];
            }

            return $this->savePurchaseOrder((int) $processId, $stepId, $input);
        }

        if ($step === 'disbursement-voucher') {
            if ($processId === null) {
                return ['form' => 'Disbursement Voucher process was not found.'];
            }

            return $this->saveDisbursementVoucher((int) $processId, $stepId, $input);
        }

        if ($step === 'obligation-report') {
            if ($processId === null) {
                return ['form' => 'Obligation Report process was not found.'];
            }

            return $this->saveObligationReport((int) $processId, $stepId, $input);
        }

        if ($step === 'requisition-issue-slip') {
            if ($processId === null) {
                return ['form' => 'Requisition and Issue Slip process was not found.'];
            }

            return $this->saveRequisitionIssueSlip((int) $processId, $stepId, $input);
        }

        if ($step === 'inspection-acceptance-report') {
            if ($processId === null) {
                return ['form' => 'Inspection and Acceptance Report process was not found.'];
            }

            return $this->saveInspectionAcceptanceReport((int) $processId, $stepId, $input);
        }

        $errors = [];
        $fields = self::requiredFields($step);
        $sharedDateValues = $processId !== null ? $this->getSharedDateValues((int) $processId, $step) : [];
        $readonlyDateFields = $processId !== null ? $this->dateReadonlyFields((int) $processId, $step) : [];

        foreach ($fields as $key => $label) {
            $value = in_array($key, $readonlyDateFields, true)
                ? (string) ($sharedDateValues[$key] ?? '')
                : trim((string) ($input[$key] ?? ''));

            if ($value === '') {
                $errors[$key] = $label . ' is required.';
                continue;
            }

            $this->upsertStepData($stepId, $key, $value);
        }

        if ($errors === []) {
            $this->markStepSaved($stepId, 'template://' . (TemplateMapping::sheetFor($step) ?? $step));
        }

        return $errors;
    }

    public function markPrinted(int $stepId): void
    {
        $stmt = $this->db->prepare('UPDATE report_steps SET printed_at = CURRENT_TIMESTAMP WHERE id = ? AND is_printable = 1');
        $stmt->execute([$stepId]);
    }

    public function advance(int $processId): ?string
    {
        $process = $this->getProcess($processId);

        if (!$process || ($process['status'] ?? '') === 'completed') {
            return null;
        }

        $steps = $this->getSteps($processId);
        $sequence = array_map(static fn (array $step): string => (string) $step['step_key'], $steps);
        if ($sequence === []) {
            $sequence = $this->sequenceFor($process['start_type']);
        }
        $currentIndex = array_search($process['current_step'], $sequence, true);

        if ($currentIndex === false || !isset($sequence[$currentIndex + 1])) {
            return null;
        }

        $nextStep = $sequence[$currentIndex + 1];

        $stmt = $this->db->prepare('UPDATE report_processes SET current_step = ? WHERE id = ?');
        $stmt->execute([$nextStep, $processId]);
        $this->touchArchiveCheckpoint($processId);

        return $nextStep;
    }

    public function finish(int $processId): void
    {
        $process = $this->getProcess($processId);

        if (!$process || ($process['status'] ?? '') === 'completed') {
            return;
        }

        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM report_steps WHERE process_id = ?');
        $countStmt->execute([$processId]);
        $reportCount = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "UPDATE report_processes SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE id = ?"
        );
        $stmt->execute([$processId]);

        $this->upsertArchiveCheckpoint($processId, $process['process_code'], $process['start_type'], $reportCount, 'completed');
    }

    public function archiveCount(array $filters = []): int
    {
        [$conditions, $parameters] = $this->archiveConditions($filters);
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM report_processes p
             LEFT JOIN archives a ON a.process_id = p.id
             WHERE " . implode("\n               AND ", $conditions)
        );
        $stmt->execute($parameters);

        return (int) $stmt->fetchColumn();
    }

    public function archives(array $filters = [], int $limit = 10, int $offset = 0): array
    {
        [$conditions, $parameters] = $this->archiveConditions($filters);
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $stmt = $this->db->prepare(
            "SELECT
                p.id AS process_id,
                p.process_code,
                p.start_type,
                p.current_step,
                p.status AS process_status,
                p.created_at,
                p.updated_at,
                p.completed_at,
                a.report_count,
                a.status AS archive_status,
                a.archived_at,
                (SELECT bac_data.field_value
                 FROM report_steps bac_step
                 INNER JOIN report_step_data bac_data ON bac_data.report_step_id = bac_step.id
                 WHERE bac_step.process_id = p.id
                   AND bac_step.step_key = 'bac-reports'
                   AND bac_data.field_key = 'PROJECTITLE'
                 LIMIT 1) AS bac_project_title,
                (SELECT pr_data.field_value
                 FROM report_steps pr_step
                 INNER JOIN report_step_data pr_data ON pr_data.report_step_id = pr_step.id
                 WHERE pr_step.process_id = p.id
                   AND pr_step.step_key = 'purchase-request'
                   AND pr_data.field_key = 'PRPURPOSE'
                 LIMIT 1) AS purchase_request_purpose
             FROM report_processes p
             LEFT JOIN archives a ON a.process_id = p.id
             WHERE " . implode("\n               AND ", $conditions) . "
             ORDER BY COALESCE(a.archived_at, p.updated_at, p.completed_at, p.created_at) DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($parameters);
        $archives = $stmt->fetchAll();

        foreach ($archives as &$archive) {
            $steps = $this->getSteps((int) $archive['process_id']);
            $archive['steps'] = $steps;
            $archive['report_count'] = (int) ($archive['report_count'] ?: count($steps));
            $archive['saved_count'] = count(array_filter($steps, static fn (array $step): bool => (int) ($step['is_saved'] ?? 0) === 1));
            $archive['status'] = (string) ($archive['archive_status'] ?: $archive['process_status'] ?: 'in_progress');
            if ($archive['status'] === 'in_progress' && $archive['saved_count'] === 0) {
                $archive['status'] = 'draft';
            }
            $projectTitle = trim((string) ($archive['bac_project_title'] ?? ''));
            $purchasePurpose = trim((string) ($archive['purchase_request_purpose'] ?? ''));
            $archive['project_title'] = $projectTitle !== '' ? $projectTitle : ($purchasePurpose !== '' ? $purchasePurpose : 'Not entered yet');
        }
        unset($archive);

        return $archives;
    }

    public function archiveSuggestions(array $filters, string $query, int $limit = 4): array
    {
        $filters['process_number'] = '';
        [$conditions, $parameters] = $this->archiveConditions($filters);
        $where = implode("\n               AND ", $conditions);
        $limit = max(1, min(4, $limit));
        $sql = "SELECT candidate.value
                FROM (
                    SELECT p.process_code AS value
                    FROM report_processes p
                    LEFT JOIN archives a ON a.process_id = p.id
                    WHERE {$where}
                    UNION
                    SELECT COALESCE(
                        NULLIF((SELECT bac_data.field_value
                                FROM report_steps bac_step
                                INNER JOIN report_step_data bac_data ON bac_data.report_step_id = bac_step.id
                                WHERE bac_step.process_id = p.id
                                  AND bac_step.step_key = 'bac-reports'
                                  AND bac_data.field_key = 'PROJECTITLE'
                                LIMIT 1), ''),
                        NULLIF((SELECT pr_data.field_value
                                FROM report_steps pr_step
                                INNER JOIN report_step_data pr_data ON pr_data.report_step_id = pr_step.id
                                WHERE pr_step.process_id = p.id
                                  AND pr_step.step_key = 'purchase-request'
                                  AND pr_data.field_key = 'PRPURPOSE'
                                LIMIT 1), ''),
                        'Not entered yet'
                    ) AS value
                    FROM report_processes p
                    LEFT JOIN archives a ON a.process_id = p.id
                    WHERE {$where}
                ) candidate
                WHERE candidate.value <> ''
                  AND LOWER(LEFT(candidate.value, CHAR_LENGTH(?))) = LOWER(?)
                ORDER BY candidate.value
                LIMIT {$limit}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([...$parameters, ...$parameters, $query, $query]);

        return array_values(array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN)));
    }

    private function archiveConditions(array $filters): array
    {
        $conditions = [
            "(p.status IS NULL OR p.status <> 'deleted')",
            "(a.status IS NULL OR a.status <> 'deleted')",
        ];
        $parameters = [];
        $status = (string) ($filters['status'] ?? '');
        $savedStepExists = "EXISTS (SELECT 1 FROM report_steps saved_step WHERE saved_step.process_id = p.id AND saved_step.is_saved = 1)";

        if ($status === 'completed') {
            $conditions[] = "COALESCE(a.status, p.status, 'in_progress') = 'completed'";
        } elseif ($status === 'draft') {
            $conditions[] = "COALESCE(a.status, p.status, 'in_progress') = 'in_progress'";
            $conditions[] = "NOT {$savedStepExists}";
        } elseif ($status === 'in_progress') {
            $conditions[] = "COALESCE(a.status, p.status, 'in_progress') = 'in_progress'";
            $conditions[] = $savedStepExists;
        }

        $workflowType = (string) ($filters['workflow'] ?? '');
        if ($workflowType !== '') {
            $conditions[] = 'p.start_type = ?';
            $parameters[] = $workflowType;
        }

        $dateFrom = (string) ($filters['date_from'] ?? '');
        if ($dateFrom !== '') {
            $conditions[] = 'p.created_at >= ?';
            $parameters[] = $dateFrom . ' 00:00:00';
        }

        $dateTo = (string) ($filters['date_to'] ?? '');
        if ($dateTo !== '') {
            $conditions[] = 'p.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
            $parameters[] = $dateTo . ' 00:00:00';
        }

        $processNumber = trim((string) ($filters['process_number'] ?? ''));
        if ($processNumber !== '') {
            $conditions[] = "(
                LOWER(p.process_code) LIKE ?
                OR EXISTS (
                    SELECT 1
                    FROM report_steps title_step
                    INNER JOIN report_step_data title_data ON title_data.report_step_id = title_step.id
                    WHERE title_step.process_id = p.id
                      AND title_step.step_key = 'bac-reports'
                      AND title_data.field_key = 'PROJECTITLE'
                      AND LOWER(title_data.field_value) LIKE ?
                )
                OR EXISTS (
                    SELECT 1
                    FROM report_steps purpose_step
                    INNER JOIN report_step_data purpose_data ON purpose_data.report_step_id = purpose_step.id
                    WHERE purpose_step.process_id = p.id
                      AND purpose_step.step_key = 'purchase-request'
                      AND purpose_data.field_key = 'PRPURPOSE'
                      AND LOWER(purpose_data.field_value) LIKE ?
                )
            )";
            $searchTerm = '%' . strtolower($processNumber) . '%';
            array_push($parameters, $searchTerm, $searchTerm, $searchTerm);
        }

        return [$conditions, $parameters];
    }

    public function completedTransactions(string $startDate, string $endDate): array
    {
        $sql = <<<'SQL'
SELECT
    p.id AS process_id,
    p.process_code,
    p.start_type,
    p.completed_at,
    MAX(CASE WHEN d.field_key = 'CHECKNO' THEN d.field_value END) AS check_no,
    MAX(CASE WHEN d.field_key = 'DATET' THEN d.field_value END) AS transaction_date,
    MAX(CASE WHEN d.field_key = 'PAYEE' THEN d.field_value END) AS payee,
    MAX(CASE WHEN d.field_key = 'NETAMOUNT' THEN d.field_value END) AS amount,
    MAX(CASE WHEN d.field_key = 'PARTICULARS' THEN d.field_value END) AS purpose,
    MAX(prd.field_value) AS pr_purpose
FROM report_processes p
INNER JOIN report_steps s
    ON s.process_id = p.id
   AND s.step_key = 'disbursement-voucher'
LEFT JOIN report_step_data d ON d.report_step_id = s.id
LEFT JOIN report_steps prs
    ON prs.process_id = p.id
   AND prs.step_key = 'purchase-request'
LEFT JOIN report_step_data prd
    ON prd.report_step_id = prs.id
   AND prd.field_key = 'PRPURPOSE'
WHERE p.status = 'completed'
  AND p.completed_at >= ?
  AND p.completed_at < DATE_ADD(?, INTERVAL 1 DAY)
GROUP BY p.id, p.process_code, p.start_type, p.completed_at
ORDER BY COALESCE(NULLIF(MAX(CASE WHEN d.field_key = 'DATET' THEN d.field_value END), ''), p.completed_at), p.id
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$startDate, $endDate]);

        return $stmt->fetchAll();
    }

    public function touchArchiveCheckpoint(int $processId): void
    {
        $stmt = $this->db->prepare('UPDATE archives SET archived_at = CURRENT_TIMESTAMP WHERE process_id = ? AND status <> ?');
        $stmt->execute([$processId, 'deleted']);
    }

    public function softDeleteProcess(int $processId): bool
    {
        $process = $this->getProcess($processId);
        if (!$process) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $processStmt = $this->db->prepare("UPDATE report_processes SET deleted_from_status = status, status = 'deleted', deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
            $processStmt->execute([$processId]);

            $countStmt = $this->db->prepare('SELECT COUNT(*) FROM report_steps WHERE process_id = ?');
            $countStmt->execute([$processId]);
            $reportCount = (int) $countStmt->fetchColumn();
            $this->upsertArchiveCheckpoint($processId, $process['process_code'], $process['start_type'], $reportCount, 'deleted');

            $this->db->commit();
            return true;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function trashItems(array $filters = [], int $limit = 10, int $offset = 0): array
    {
        [$conditions, $parameters] = $this->trashConditions($filters);
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        $stmt = $this->db->prepare(<<<'SQL'
SELECT
    p.id AS process_id,
    p.process_code,
    p.start_type,
    p.created_at,
    p.deleted_at,
    DATE_ADD(p.deleted_at, INTERVAL 30 DAY) AS expires_at,
    p.completed_at,
    COALESCE(step_progress.saved_count, 0) AS saved_count,
    COALESCE(step_progress.total_count, 0) AS total_count,
    COALESCE(
        NULLIF((SELECT bac_data.field_value
                FROM report_steps bac_step
                INNER JOIN report_step_data bac_data ON bac_data.report_step_id = bac_step.id
                WHERE bac_step.process_id = p.id
                  AND bac_step.step_key = 'bac-reports'
                  AND bac_data.field_key = 'PROJECTITLE'
                LIMIT 1), ''),
        NULLIF((SELECT pr_data.field_value
                FROM report_steps pr_step
                INNER JOIN report_step_data pr_data ON pr_data.report_step_id = pr_step.id
                WHERE pr_step.process_id = p.id
                  AND pr_step.step_key = 'purchase-request'
                  AND pr_data.field_key = 'PRPURPOSE'
                LIMIT 1), ''),
        'Not entered yet'
    ) AS project_title
FROM report_processes p
LEFT JOIN (
    SELECT process_id, COUNT(*) AS total_count, SUM(CASE WHEN is_saved = 1 THEN 1 ELSE 0 END) AS saved_count
    FROM report_steps
    GROUP BY process_id
) step_progress ON step_progress.process_id = p.id
WHERE p.status = 'deleted'
  AND p.deleted_at IS NOT NULL
SQL . (count($conditions) > 2 ? " AND " . implode("\n  AND ", array_slice($conditions, 2)) : '') . "\nORDER BY p.deleted_at DESC, p.id DESC\nLIMIT {$limit} OFFSET {$offset}");

        $stmt->execute($parameters);
        return $stmt->fetchAll();
    }

    public function trashCount(array $filters = []): int
    {
        [$conditions, $parameters] = $this->trashConditions($filters);
        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM report_processes p
            LEFT JOIN (
                SELECT process_id, COUNT(*) AS total_count, SUM(CASE WHEN is_saved = 1 THEN 1 ELSE 0 END) AS saved_count
                FROM report_steps
                GROUP BY process_id
            ) step_progress ON step_progress.process_id = p.id
            WHERE " . implode("\n              AND ", $conditions));
        $stmt->execute($parameters);
        return (int) $stmt->fetchColumn();
    }

    public function trashSuggestions(array $filters, string $query, int $limit = 4): array
    {
        [$conditions, $parameters] = $this->trashConditions($filters, false);
        $where = implode("\n  AND ", $conditions);
        $limit = max(1, min(4, $limit));
        $from = "FROM report_processes p
            LEFT JOIN (
                SELECT process_id, COUNT(*) AS total_count, SUM(CASE WHEN is_saved = 1 THEN 1 ELSE 0 END) AS saved_count
                FROM report_steps
                GROUP BY process_id
            ) step_progress ON step_progress.process_id = p.id";
        $projectTitle = "COALESCE(
            NULLIF((SELECT bac_data.field_value FROM report_steps bac_step
                    INNER JOIN report_step_data bac_data ON bac_data.report_step_id = bac_step.id
                    WHERE bac_step.process_id = p.id AND bac_step.step_key = 'bac-reports'
                      AND bac_data.field_key = 'PROJECTITLE' LIMIT 1), ''),
            NULLIF((SELECT pr_data.field_value FROM report_steps pr_step
                    INNER JOIN report_step_data pr_data ON pr_data.report_step_id = pr_step.id
                    WHERE pr_step.process_id = p.id AND pr_step.step_key = 'purchase-request'
                      AND pr_data.field_key = 'PRPURPOSE' LIMIT 1), ''),
            'Not entered yet'
        )";
        $stmt = $this->db->prepare("SELECT candidate.value
            FROM (
                SELECT p.process_code AS value {$from} WHERE {$where}
                UNION
                SELECT {$projectTitle} AS value {$from} WHERE {$where}
            ) candidate
            WHERE candidate.value <> ''
              AND LOWER(LEFT(candidate.value, CHAR_LENGTH(?))) = LOWER(?)
            ORDER BY candidate.value
            LIMIT {$limit}");
        $stmt->execute([...$parameters, ...$parameters, $query, $query]);
        return array_values(array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN)));
    }

    private function trashConditions(array $filters, bool $includeSearch = true): array
    {
        $conditions = ["p.status = 'deleted'", 'p.deleted_at IS NOT NULL'];
        $parameters = [];
        $intervals = ['24-hours' => '1 DAY', '7-days' => '7 DAY', '30-days' => '30 DAY'];
        $recentlyDeleted = (string) ($filters['recently_deleted'] ?? '');
        if (isset($intervals[$recentlyDeleted])) {
            $conditions[] = 'p.deleted_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL ' . $intervals[$recentlyDeleted] . ')';
        }

        $workflow = (string) ($filters['workflow'] ?? '');
        if (in_array($workflow, [self::PURCHASE_START, self::VOUCHER_START], true)) {
            $conditions[] = 'p.start_type = ?';
            $parameters[] = $workflow;
        }

        $progress = (string) ($filters['progress'] ?? '');
        if ($progress === 'none') {
            $conditions[] = 'COALESCE(step_progress.saved_count, 0) = 0';
        } elseif ($progress === 'partial') {
            $conditions[] = 'COALESCE(step_progress.saved_count, 0) > 0 AND step_progress.saved_count < step_progress.total_count';
        } elseif ($progress === 'complete') {
            $conditions[] = 'step_progress.total_count > 0 AND step_progress.saved_count >= step_progress.total_count';
        }

        $processNumber = $includeSearch ? trim((string) ($filters['process_number'] ?? '')) : '';
        if ($processNumber !== '') {
            $conditions[] = "(
                LOWER(p.process_code) LIKE ?
                OR EXISTS (
                    SELECT 1 FROM report_steps title_step
                    INNER JOIN report_step_data title_data ON title_data.report_step_id = title_step.id
                    WHERE title_step.process_id = p.id AND title_step.step_key = 'bac-reports'
                      AND title_data.field_key = 'PROJECTITLE' AND LOWER(title_data.field_value) LIKE ?
                )
                OR EXISTS (
                    SELECT 1 FROM report_steps purpose_step
                    INNER JOIN report_step_data purpose_data ON purpose_data.report_step_id = purpose_step.id
                    WHERE purpose_step.process_id = p.id AND purpose_step.step_key = 'purchase-request'
                      AND purpose_data.field_key = 'PRPURPOSE' AND LOWER(purpose_data.field_value) LIKE ?
                )
            )";
            $searchTerm = '%' . strtolower($processNumber) . '%';
            array_push($parameters, $searchTerm, $searchTerm, $searchTerm);
        }

        return [$conditions, $parameters];
    }

    public function restoreDeletedProcess(int $processId): bool
    {
        $this->db->beginTransaction();
        try {
            $processStmt = $this->db->prepare("SELECT id, deleted_from_status FROM report_processes WHERE id = ? AND status = 'deleted' AND deleted_at > DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 DAY) FOR UPDATE");
            $processStmt->execute([$processId]);
            $process = $processStmt->fetch();
            if (!$process) {
                $this->db->commit();
                return false;
            }

            $status = in_array($process['deleted_from_status'], ['in_progress', 'completed'], true) ? $process['deleted_from_status'] : 'in_progress';
            $restore = $this->db->prepare("UPDATE report_processes SET status = ?, deleted_at = NULL, deleted_from_status = NULL WHERE id = ? AND status = 'deleted'");
            $restore->execute([$status, $processId]);
            $archive = $this->db->prepare("UPDATE archives SET status = ?, archived_at = CURRENT_TIMESTAMP WHERE process_id = ? AND status = 'deleted'");
            $archive->execute([$status, $processId]);
            $this->db->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function permanentlyDeleteProcess(int $processId): bool
    {
        $this->db->beginTransaction();
        try {
            $processStmt = $this->db->prepare("SELECT id FROM report_processes WHERE id = ? AND status = 'deleted' FOR UPDATE");
            $processStmt->execute([$processId]);
            if (!$processStmt->fetchColumn()) {
                $this->db->commit();
                return false;
            }

            $this->deleteProcessRows($processId);
            $this->db->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function purgeExpiredTrash(): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->query("SELECT id FROM report_processes WHERE status = 'deleted' AND deleted_at IS NOT NULL AND deleted_at <= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 DAY) FOR UPDATE");
            $processIds = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
            foreach ($processIds as $processId) {
                $this->deleteProcessRows($processId);
            }
            $this->db->commit();
            return count($processIds);
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    private function deleteProcessRows(int $processId): void
    {
        $items = $this->db->prepare('DELETE FROM report_items WHERE process_id = ?');
        $items->execute([$processId]);

        $stepData = $this->db->prepare('DELETE d FROM report_step_data AS d INNER JOIN report_steps AS s ON s.id = d.report_step_id WHERE s.process_id = ?');
        $stepData->execute([$processId]);

        $archive = $this->db->prepare('DELETE FROM archives WHERE process_id = ?');
        $archive->execute([$processId]);
        $steps = $this->db->prepare('DELETE FROM report_steps WHERE process_id = ?');
        $steps->execute([$processId]);
        $process = $this->db->prepare("DELETE FROM report_processes WHERE id = ? AND status = 'deleted'");
        $process->execute([$processId]);
    }

    private function upsertArchiveCheckpoint(int $processId, string $processCode, string $startType, int $reportCount, string $status): void
    {
        $archiveStmt = $this->db->prepare(
            'INSERT INTO archives (process_id, process_code, start_type, report_count, status)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE report_count = VALUES(report_count), status = VALUES(status), archived_at = CURRENT_TIMESTAMP'
        );
        $archiveStmt->execute([$processId, $processCode, $startType, $reportCount, $status]);
    }

    public function clearStepData(int $processId, int $stepId, string $step): void
    {
        $this->db->beginTransaction();

        try {
            $this->clearStepDataRows($stepId);

            if ($step === 'purchase-request') {
                $deleteItems = $this->db->prepare("DELETE FROM report_items WHERE process_id = ? AND step_key = 'purchase-request'");
                $deleteItems->execute([$processId]);
            }

            $this->markStepDraft($stepId);
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function isSharedDateEnabled(int $processId): bool
    {
        $source = $this->sharedDateSource($processId);

        return $source !== null && (string) ($source['data'][self::SHARED_DATE_META_KEY] ?? '') === '1';
    }

    public function dateReadonlyFields(int $processId, string $step): array
    {
        if ($step !== 'bac-reports' || !$this->isSharedDateEnabled($processId)) {
            return [];
        }

        return array_keys($this->getSharedDateValues($processId, $step));
    }

    public function getSharedDateValues(int $processId, ?string $step = null): array
    {
        $targetStep = $step ?? 'bac-reports';
        if ($targetStep !== 'bac-reports') {
            return [];
        }

        $date = $this->inheritedBacDate($processId);
        if ($date === '') {
            return [];
        }

        return $this->bacDateParts($date);
    }

    public function getBacCalendarDate(int $processId, int $stepId, array $data = []): string
    {
        if (array_key_exists('BACDATE', $data) && trim((string) $data['BACDATE']) !== '') {
            return trim((string) $data['BACDATE']);
        }

        $inheritedDate = $this->inheritedBacDate($processId);
        if ($inheritedDate !== '') {
            return $inheritedDate;
        }

        $source = $data !== [] ? $data : $this->getStepData($stepId);
        $dateFromParts = $this->calendarDateFromBacParts(
            (string) ($source['BACDAY'] ?? ''),
            (string) ($source['BACMONTH'] ?? ''),
            (string) ($source['BACYEAR'] ?? '')
        );

        return $dateFromParts !== '' ? $dateFromParts : date('Y-m-d');
    }

    private function stepKeyForTemplateData(int $processId, int $stepId): string
    {
        $stmt = $this->db->prepare('SELECT step_key FROM report_steps WHERE process_id = ? AND id = ? LIMIT 1');
        $stmt->execute([$processId, $stepId]);

        return (string) ($stmt->fetchColumn() ?: '');
    }

    private function isInternalField(string $key): bool
    {
        return str_starts_with($key, '__');
    }

    private function setSharedDateEnabled(int $stepId, bool $enabled): void
    {
        $this->upsertStepData($stepId, self::SHARED_DATE_META_KEY, $enabled ? '1' : '0');
    }

    private function inheritedBacDate(int $processId): string
    {
        $source = $this->sharedDateSource($processId);
        if ($source === null || (string) ($source['data'][self::SHARED_DATE_META_KEY] ?? '') !== '1') {
            return '';
        }

        $date = (string) ($source['data']['DatePR'] ?? '');

        return $this->isCalendarDate($date) ? $date : '';
    }

    private function isCalendarDate(string $date): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year);
    }

    private function bacDateParts(string $date): array
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return [];
        }

        $day = (int) date('j', $timestamp);

        return [
            'BACDAY' => $this->ordinalDay($day),
            'BACMONTH' => date('F', $timestamp),
            'BACYEAR' => date('Y', $timestamp),
        ];
    }

    private function ordinalDay(int $day): string
    {
        if ($day % 100 >= 11 && $day % 100 <= 13) {
            return $day . 'th';
        }

        return $day . match ($day % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }

    private function calendarDateFromBacParts(string $day, string $month, string $year): string
    {
        $numericDay = (int) preg_replace('/\D+/', '', $day);
        $numericYear = (int) preg_replace('/\D+/', '', $year);
        if ($numericDay <= 0 || $numericYear <= 0 || trim($month) === '') {
            return '';
        }

        if (is_numeric($month)) {
            $monthNumber = (int) $month;
        } else {
            $monthTimestamp = strtotime('1 ' . $month . ' 2000');
            if ($monthTimestamp === false) {
                return '';
            }
            $monthNumber = (int) date('n', $monthTimestamp);
        }

        if (!checkdate($monthNumber, $numericDay, $numericYear)) {
            return '';
        }

        return sprintf('%04d-%02d-%02d', $numericYear, $monthNumber, $numericDay);
    }

    private function sharedDateSource(int $processId): ?array
    {
        $step = $this->getStep($processId, 'purchase-request');
        if (!$step) {
            return null;
        }

        return [
            'step' => $step,
            'data' => $this->getStepData((int) $step['id'], true),
        ];
    }


    private function isDateDerivedField(string $key): bool
    {
        $upper = strtoupper($key);

        return $key === 'DatePR'
            || $key === 'report_date'
            || str_contains($upper, 'DATE')
            || str_ends_with($upper, 'DAY')
            || str_ends_with($upper, 'MONTH')
            || str_ends_with($upper, 'YEAR');
    }

    private function sharedDateValueForKey(string $key, string $date): string
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }

        $upper = strtoupper($key);
        if (str_ends_with($upper, 'DAY')) {
            return $key === 'BACDAY'
                ? $this->ordinalDay((int) date('j', $timestamp))
                : date('j', $timestamp);
        }
        if (str_ends_with($upper, 'MONTH')) {
            return date('F', $timestamp);
        }
        if (str_ends_with($upper, 'YEAR')) {
            return date('Y', $timestamp);
        }

        return date('Y-m-d', $timestamp);
    }

    private function clearStepDataRows(int $stepId): void
    {
        $stmt = $this->db->prepare('DELETE FROM report_step_data WHERE report_step_id = ?');
        $stmt->execute([$stepId]);
    }

    private function saveBacReports(int $processId, int $stepId, array $input): array
    {
        $errors = [];
        $fields = self::requiredFields('bac-reports');
        $date = $this->inheritedBacDate($processId);

        if ($date === '') {
            $date = trim((string) ($input['BACDATE'] ?? ''));
            if (!$this->isCalendarDate($date)) {
                $errors['BACDATE'] = 'BAC Date is required.';
            }
        }

        foreach ($fields as $key => $label) {
            if (in_array($key, ['BACDAY', 'BACMONTH', 'BACYEAR'], true)) {
                continue;
            }

            $value = trim((string) ($input[$key] ?? ''));
            if ($value === '') {
                $errors[$key] = $label . ' is required.';
                continue;
            }

            $this->upsertStepData($stepId, $key, $value);
        }

        if ($errors !== []) {
            if (!$this->isStepSaved($stepId)) {
                $this->markStepDraft($stepId);
            }
            return $errors;
        }

        foreach ($this->bacDateParts($date) as $key => $value) {
            $this->upsertStepData($stepId, $key, $value);
        }
        $this->markStepSaved($stepId, 'template://BAC Resolution');

        return [];
    }

    private function saveAbstractOfCanvass(int $processId, int $stepId, array $input): array
    {
        $errors = [];
        $bids = $this->getSupplierBidsForAbstract($processId, $stepId, $input);

        if ($bids === []) {
            $errors['supplier_bids'] = 'Save supplier proposals in the Request for Quotation step before completing the Abstract of Canvass.';
        }

        foreach ($bids as $index => $bid) {
            if ($bid['AOP'] === '') {
                $errors['supplier_bids'][$index]['AOP'] = 'Amount of Proposal is required.';
            } elseif (!is_numeric($bid['AOP'])) {
                $errors['supplier_bids'][$index]['AOP'] = 'Amount of Proposal must be numeric.';
            }
        }

        $validBids = array_values(array_filter($bids, static fn (array $bid): bool => $bid['SUPPNAME'] !== '' && $bid['AOP'] !== '' && is_numeric($bid['AOP'])));
        $lowestBid = $this->lowestAbstractBid($validBids);
        $selectedSupplier = trim((string) ($input['LBSUPPNAME'] ?? ''));

        if ($selectedSupplier === '' && $lowestBid !== null) {
            $selectedSupplier = $lowestBid['SUPPNAME'];
        }

        $selectedBid = $this->abstractBidBySupplier($validBids, $selectedSupplier);
        if ($selectedSupplier === '' || $selectedBid === null) {
            $errors['LBSUPPNAME'] = 'Choose a supplier from the saved RFQ proposal rows.';
        }

        if ($errors !== []) {
            if (!$this->isStepSaved($stepId)) {
                $this->markStepDraft($stepId);
            }
            return $errors;
        }

        $this->upsertStepData($stepId, self::ABSTRACT_BIDS_META_KEY, json_encode($bids, JSON_UNESCAPED_UNICODE));
        $this->upsertStepData($stepId, 'LBSUPPNAME', $selectedBid['SUPPNAME']);
        $this->upsertStepData($stepId, 'LOWESTBIDAMOUNT', $selectedBid['AOP']);
        $this->upsertStepData($stepId, 'LOWBIDSUPPNAME', $selectedBid['SUPPNAME']);
        $this->upsertStepData($stepId, 'LOWBIDSUPPAOP', $selectedBid['AOP']);
        $this->markStepSaved($stepId, 'template://Abstract of Canvass');

        return [];
    }

    private function lowestAbstractBid(array $bids): ?array
    {
        $lowest = null;
        foreach ($bids as $bid) {
            if ($lowest === null || (float) $bid['AOP'] < (float) $lowest['AOP']) {
                $lowest = $bid;
            }
        }

        return $lowest;
    }

    public function sortSupplierBidsByAmount(array $bids): array
    {
        $rows = array_values(array_filter($bids, static fn (array $bid): bool => trim((string) ($bid['SUPPNAME'] ?? '')) !== '' || trim((string) ($bid['AOP'] ?? '')) !== ''));

        usort($rows, static function (array $left, array $right): int {
            $leftAmount = trim((string) ($left['AOP'] ?? ''));
            $rightAmount = trim((string) ($right['AOP'] ?? ''));
            $leftNumeric = $leftAmount !== '' && is_numeric($leftAmount);
            $rightNumeric = $rightAmount !== '' && is_numeric($rightAmount);

            if ($leftNumeric && $rightNumeric) {
                return (float) $leftAmount <=> (float) $rightAmount;
            }
            if ($leftNumeric) {
                return -1;
            }
            if ($rightNumeric) {
                return 1;
            }

            return strcasecmp((string) ($left['SUPPNAME'] ?? ''), (string) ($right['SUPPNAME'] ?? ''));
        });

        return $rows;
    }

    private function abstractBidBySupplier(array $bids, string $supplier): ?array
    {
        foreach ($bids as $bid) {
            if ($bid['SUPPNAME'] === $supplier) {
                return $bid;
            }
        }

        return null;
    }

    private function saveRequestForQuotation(int $processId, int $stepId, array $input): array
    {
        $errors = [];
        $purchaseItems = $this->getPurchaseItems($processId);
        $bids = $this->normalizeRfqSupplierBids($input);

        if ($bids === []) {
            $errors['supplier_bids'] = 'At least one supplier proposal row is required.';
        }

        if ($purchaseItems === []) {
            $errors['rfq_items'] = 'Save Purchase Request items before completing the Request for Quotation.';
        }

        foreach ($bids as $index => $bid) {
            foreach (['SUPPNAME' => 'Supplier Name', 'SUPPADD' => 'Supplier Address'] as $key => $label) {
                if ($bid[$key] === '') {
                    $errors['supplier_bids'][$index][$key] = $label . ' is required.';
                }
            }
        }

        if ($errors !== []) {
            if (!$this->isStepSaved($stepId)) {
                $this->markStepDraft($stepId);
            }
            return $errors;
        }

        $firstBid = $bids[0];

        $this->upsertStepData($stepId, self::RFQ_SUPPLIER_BIDS_META_KEY, json_encode($bids, JSON_UNESCAPED_UNICODE));
        $this->upsertStepData($stepId, 'SUPPNAME', $firstBid['SUPPNAME']);
        $this->upsertStepData($stepId, 'SUPPADD', $firstBid['SUPPADD']);
        $this->upsertStepData($stepId, 'LBADD', $firstBid['SUPPADD']);
        $this->upsertStepData($stepId, 'AOP', '');
        $this->markStepSaved($stepId, 'template://Request for Quotation');

        return [];
    }

    private function saveObligationReport(int $processId, int $stepId, array $input): array
    {
        $errors = [];
        $values = [];
        $labels = self::requiredFields('obligation-report');

        foreach ($labels as $key => $label) {
            $value = trim((string) ($input[$key] ?? ''));
            $values[$key] = $value;
            if ($value === '') {
                $errors[$key] = $label . ' is required.';
            }
        }

        $specificFund = trim((string) ($values['SPECFUND'] ?? ''));
        if ($this->obligationUsesGeneralFund($processId)) {
            if ($specificFund === '') {
                $errors['SPECFUND'] = 'Select a specific fund.';
            } elseif (!in_array($specificFund, self::obligationSpecificFundOptions(), true)) {
                $errors['SPECFUND'] = 'Select a valid specific fund.';
            }
        } elseif ($specificFund === '') {
            $errors['SPECFUND'] = 'Specific Fund is required.';
        }

        if ($errors !== []) {
            $this->markStepDraft($stepId);
            return $errors;
        }

        foreach ($values as $key => $value) {
            $this->upsertStepData($stepId, $key, $value);
        }
        $this->markStepSaved($stepId, 'template://Obligation Report');

        return [];
    }

    private function saveRequisitionIssueSlip(int $processId, int $stepId, array $input): array
    {
        $errors = [];
        $values = [];
        $labels = self::requiredFields('requisition-issue-slip');

        foreach ($labels as $key => $label) {
            $value = trim((string) ($input[$key] ?? ''));
            $values[$key] = $value;
            if ($value === '') {
                $errors[$key] = $label . ' is required.';
            }
        }

        if ($errors !== []) {
            if (!$this->isStepSaved($stepId)) {
                $this->markStepDraft($stepId);
            }
            return $errors;
        }

        foreach ($values as $key => $value) {
            $this->upsertStepData($stepId, $key, $value);
        }

        $prPurpose = trim((string) ($this->getProcessData($processId)['PRPURPOSE'] ?? ''));
        $purposeSource = $prPurpose !== '' && trim((string) ($values['PURPOSE'] ?? '')) === $prPurpose
            ? 'pr'
            : 'custom';
        $this->upsertStepData($stepId, self::RIS_PURPOSE_SOURCE_META_KEY, $purposeSource);
        $this->markStepSaved($stepId, 'template://Requisition & Issue Slip');

        return [];
    }

    private function saveInspectionAcceptanceReport(int $processId, int $stepId, array $input): array
    {
        $errors = [];
        $values = [];

        foreach (self::requiredFields('inspection-acceptance-report') as $key => $label) {
            $value = trim((string) ($input[$key] ?? ''));
            $values[$key] = $value;
            if ($value === '') {
                $errors[$key] = $label . ' is required.';
            }
        }

        if ($errors !== []) {
            if (!$this->isStepSaved($stepId)) {
                $this->markStepDraft($stepId);
            }
            return $errors;
        }

        foreach ($values as $key => $value) {
            $this->upsertStepData($stepId, $key, $value);
        }
        $this->markStepSaved($stepId, 'template://Inspection & Acceptance');

        return [];
    }
    private function saveDisbursementVoucher(int $processId, int $stepId, array $input): array
    {
        $errors = [];
        $values = [];
        $requiredTextFields = ['DIVNO', 'DATET', 'PAYEE', 'DDFUND', 'PAYEE_ADDRESS', 'TIN', 'PARTICULARS', 'ORNO'];
        $labels = self::requiredFields('disbursement-voucher');

        foreach ($requiredTextFields as $key) {
            $value = trim((string) ($input[$key] ?? ''));
            $values[$key] = $value;
            if ($value === '') {
                $errors[$key] = ($labels[$key] ?? $key) . ' is required.';
            }
        }

        if (($values['PARTICULARS'] ?? '') !== '') {
            $body = self::disbursementParticularsBody($values['PARTICULARS']);
            if ($body === '') {
                $errors['PARTICULARS'] = 'Particulars is required.';
            } else {
                $values['PARTICULARS'] = $body . ' ' . self::DV_PARTICULARS_SUFFIX;
            }
        }

        if (($values['DDFUND'] ?? '') !== '' && !in_array($values['DDFUND'], self::DISBURSEMENT_FUND_OPTIONS, true)) {
            $errors['DDFUND'] = 'Select a valid fund.';
        }

        if ($this->hasDisbursementVoucherPurchaseOrderSource($processId)) {
            $gross = $this->purchaseOrderGross($processId);
            if ($gross === '') {
                $errors['GROSS'] = 'Save Purchase Order item amounts before completing the Disbursement Voucher.';
            }
            $values['GROSS'] = $gross;
        } else {
            $gross = trim((string) ($input['GROSS'] ?? ''));
            $values['GROSS'] = $gross;
            if ($gross === '') {
                $errors['GROSS'] = 'Gross Amount is required.';
            } elseif (!is_numeric($gross)) {
                $errors['GROSS'] = 'Gross Amount must be numeric.';
            }
        }

        $taxType = trim((string) ($input['DV_TAX_TYPE'] ?? ''));
        $taxTypes = $this->configuredDisbursementTaxTypes();
        if ($taxType !== '' && !in_array($taxType, $taxTypes, true)) {
            $errors['DV_TAX_TYPE'] = 'Select an available tax type or None.';
        }
        $taxEnabled = $taxType !== '';
        $tax = $taxEnabled ? trim((string) ($input['WHTAX'] ?? '')) : '0';
        if ($taxEnabled && $tax === '') {
            $errors['WHTAX'] = 'Withholding Tax is required when enabled.';
        } elseif ($tax !== '' && !is_numeric($tax)) {
            $errors['WHTAX'] = 'Withholding Tax must be numeric.';
        }

        if (!empty($values['GROSS']) && is_numeric($values['GROSS']) && $tax !== '' && is_numeric($tax)) {
            if ((float) $tax > (float) $values['GROSS']) {
                $errors['WHTAX'] = 'Withholding Tax cannot exceed Gross Amount.';
            }
            $values['GROSS'] = $this->formatMoney((float) $values['GROSS']);
            $values['WHTAX'] = $this->formatMoney((float) $tax);
            $values['NETAMOUNT'] = $this->formatMoney((float) $values['GROSS'] - (float) $values['WHTAX']);
        } else {
            $values['WHTAX'] = $tax;
            $values['NETAMOUNT'] = '';
        }
        $values['DV_TAX_TYPE'] = $taxType;

        $checkNo = trim((string) ($input['CHECKNO'] ?? ''));
        if ($checkNo === '') {
            $checkNo = $this->nextCheckNumber($stepId);
        }
        $range = $this->configuredCheckNumberRange();
        if ($range === null) {
            $errors['CHECKNO'] = 'Set a valid Check No. Start and End range in System Input.';
        } elseif ($checkNo === '') {
            $errors['CHECKNO'] = 'No available check number remains in the configured range.';
        } elseif (!ctype_digit($checkNo)) {
            $errors['CHECKNO'] = 'Check No. must be a whole number.';
        } else {
            [$start, $end] = $range;
            $checkNumber = (int) $checkNo;
            if ($checkNumber < $start || $checkNumber > $end) {
                $errors['CHECKNO'] = 'Check No. must be within the configured System Input range.';
            } elseif (isset($this->usedCheckNumbers($stepId)[$checkNo])) {
                $errors['CHECKNO'] = 'Check No. is already used by another Disbursement Voucher.';
            }
        }
        $values['CHECKNO'] = $checkNo;

        if ($errors !== []) {
            if (!$this->isStepSaved($stepId)) {
                $this->markStepDraft($stepId);
            }
            return $errors;
        }

        foreach ($values as $key => $value) {
            $this->upsertStepData($stepId, $key, $value);
        }
        $this->upsertStepData($stepId, self::DV_WHTAX_ENABLED_META_KEY, $taxEnabled ? '1' : '0');
        $this->markStepSaved($stepId, 'template://Disbursement Voucher');

        return [];
    }
    private function savePurchaseOrder(int $processId, int $stepId, array $input): array
    {
        $errors = [];
        $header = [];
        $postedPaymentModes = $input['PO_MODE_OF_PAYMENT'] ?? [];
        if (!is_array($postedPaymentModes)) {
            $postedPaymentModes = $postedPaymentModes === '' ? [] : [$postedPaymentModes];
        }

        foreach (self::requiredFields('purchase-order') as $key => $label) {
            $rawValue = $key === 'PO_MODE_OF_PAYMENT'
                ? (count($postedPaymentModes) === 1 ? $postedPaymentModes[0] : '')
                : ($input[$key] ?? '');
            $value = is_scalar($rawValue) ? trim((string) $rawValue) : '';
            $header[$key] = $value;

            if ($value === '') {
                $errors[$key] = $label . ' is required.';
            }
        }

        foreach (['PODT' => 'Delivery Term', 'POPT' => 'Payment Term'] as $key => $label) {
            if (($header[$key] ?? '') !== '' && !in_array($header[$key], ['Full', 'Partial'], true)) {
                $errors[$key] = $label . ' must be Full or Partial.';
            }
        }

        if (count($postedPaymentModes) !== 1) {
            $errors['PO_MODE_OF_PAYMENT'] = 'Choose exactly one Mode of Payment.';
        } elseif (($header['PO_MODE_OF_PAYMENT'] ?? '') !== '' && !in_array($header['PO_MODE_OF_PAYMENT'], ['BID', 'NEG', 'SHOP'], true)) {
            $errors['PO_MODE_OF_PAYMENT'] = 'Choose Bidding, Negotiated, or Shopping.';
        }

        $purchaseItems = $this->getPurchaseItems($processId);
        $poItems = $this->normalizePurchaseOrderItems($input);

        if ($purchaseItems === []) {
            $errors['po_items'] = 'Save Purchase Request items before completing the Purchase Order.';
        }

        foreach ($purchaseItems as $index => $_item) {
            $row = $poItems[$index] ?? ['POUNITCOST' => ''];

            if ($row['POUNITCOST'] === '') {
                $errors['po_items'][$index]['POUNITCOST'] = 'Unit Cost is required.';
            } elseif (!is_numeric($row['POUNITCOST'])) {
                $errors['po_items'][$index]['POUNITCOST'] = 'Unit Cost must be numeric.';
            }
        }

        if ($errors !== []) {
            if (!$this->isStepSaved($stepId)) {
                $this->markStepDraft($stepId);
            }
            return $errors;
        }

        foreach ($header as $key => $value) {
            $this->upsertStepData($stepId, $key, $value);
        }

        $savedRows = [];
        foreach ($purchaseItems as $index => $item) {
            $unitCost = $poItems[$index]['POUNITCOST'] ?? '';
            $savedRows[] = [
                'POUNITCOST' => $unitCost,
                'POAMOUNT' => $this->purchaseOrderAmount((string) ($item['quantity'] ?? ''), $unitCost),
            ];
        }

        $this->upsertStepData($stepId, self::PURCHASE_ORDER_ITEMS_META_KEY, json_encode($savedRows, JSON_UNESCAPED_UNICODE));
        $this->markStepSaved($stepId, 'template://Purchase Order');

        return [];
    }

    private function savePurchaseRequest(int $processId, int $stepId, array $input): array
    {
        $errors = [];
        $header = [];

        foreach (self::PURCHASE_HEADER_FIELDS as $key => $label) {
            $value = trim((string) ($input[$key] ?? ''));
            $header[$key] = $value;

            if ($value === '') {
                $errors[$key] = $label . ' is required.';
            }
        }

        $items = $this->normalizePurchaseItems($input);
        if ($items === []) {
            $errors['items'] = 'At least one purchase item row is required.';
        }

        foreach ($items as $index => $item) {
            foreach (self::PURCHASE_ITEM_FIELDS as $key => $label) {
                if ($item[$key] === '') {
                    $errors['items'][$index][$key] = $label . ' is required.';
                }
            }

            if ($item['PRQTY'] !== '' && !is_numeric($item['PRQTY'])) {
                $errors['items'][$index]['PRQTY'] = 'Quantity must be numeric.';
            }

            if ($item['EUC'] !== '' && !is_numeric($item['EUC'])) {
                $errors['items'][$index]['EUC'] = 'Estimated Unit Cost must be numeric.';
            }
        }

        if ($errors !== []) {
            if (!$this->isStepSaved($stepId)) {
                $this->markStepDraft($stepId);
            }
            return $errors;
        }

        foreach ($header as $key => $value) {
            $this->upsertStepData($stepId, $key, $value);
        }

        $this->setSharedDateEnabled($stepId, !empty($input[self::SHARED_DATE_META_KEY]));
        $this->replacePurchaseItems($processId, $stepId, $items);
        $this->markStepSaved($stepId, 'template://Purchase Request');

        return [];
    }

    private function replacePurchaseItems(int $processId, int $stepId, array $items): void
    {
        $this->db->beginTransaction();

        try {
            $delete = $this->db->prepare("DELETE FROM report_items WHERE process_id = ? AND step_key = 'purchase-request'");
            $delete->execute([$processId]);

            $insert = $this->db->prepare(
                'INSERT INTO report_items
                    (process_id, report_step_id, step_key, item_no, quantity, unit_of_measurement, particulars, estimated_unit_cost, row_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            foreach ($items as $index => $item) {
                $insert->execute([
                    $processId,
                    $stepId,
                    'purchase-request',
                    $item['INPR'],
                    $item['PRQTY'],
                    $item['UOE'],
                    $item['PRPART'],
                    $item['EUC'],
                    $index + 1,
                ]);
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function getFirstPurchaseItem(int $processId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM report_items
             WHERE process_id = ? AND step_key = 'purchase-request'
             ORDER BY row_order, id
             LIMIT 1"
        );
        $stmt->execute([$processId]);
        $item = $stmt->fetch();

        return $item ?: null;
    }

    private function upsertStepData(int $stepId, string $key, string $value): void
    {
        $exists = $this->db->prepare(
            'SELECT id FROM report_step_data WHERE report_step_id = ? AND field_key = ? LIMIT 1'
        );
        $exists->execute([$stepId, $key]);
        $existingId = $exists->fetchColumn();

        if ($existingId !== false) {
            $update = $this->db->prepare(
                'UPDATE report_step_data
                 SET field_value = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?'
            );
            $update->execute([$value, $existingId]);
            return;
        }

        $insert = $this->db->prepare(
            'INSERT INTO report_step_data (report_step_id, field_key, field_value)
             VALUES (?, ?, ?)'
        );
        $insert->execute([$stepId, $key, $value]);
    }

    private function markStepSaved(int $stepId, string $previewPath): void
    {
        $stmt = $this->db->prepare(
            'UPDATE report_steps SET is_saved = 1, is_printable = 1, preview_path = ? WHERE id = ?'
        );
        $stmt->execute([$previewPath, $stepId]);
    }

    private function isStepSaved(int $stepId): bool
    {
        $stmt = $this->db->prepare('SELECT is_saved FROM report_steps WHERE id = ? LIMIT 1');
        $stmt->execute([$stepId]);

        return (int) $stmt->fetchColumn() === 1;
    }

    private function markStepDraft(int $stepId): void
    {
        $stmt = $this->db->prepare('UPDATE report_steps SET is_saved = 0, is_printable = 0 WHERE id = ?');
        $stmt->execute([$stepId]);
    }

    private function sequenceFor(string $startType): array
    {
        return $startType === self::PURCHASE_START ? self::PURCHASE_SEQUENCE : self::VOUCHER_SEQUENCE;
    }
}












