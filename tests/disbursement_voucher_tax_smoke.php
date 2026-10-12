<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\ReportPdfGenerator;
use App\Models\SystemInput;
use App\Models\Workflow;

final class SystemInputTaxSmoke extends SystemInput
{
    public function __construct()
    {
    }
}

final class DisbursementVoucherSmokeWorkflow extends Workflow
{
    public array $data = [];

    public function __construct()
    {
    }

    public function getTemplateData(int $processId, int $stepId): array
    {
        return $this->data;
    }
}

$settings = new SystemInputTaxSmoke();
$duplicateErrors = $settings->validateTaxTypes([
    ['name' => 'Expanded Withholding Tax'],
    ['name' => ' expanded withholding tax '],
]);
if (!isset($duplicateErrors['tax_types.1.name'])) {
    throw new RuntimeException('Duplicate tax names were not rejected case-insensitively.');
}
if ($settings->validateTaxTypes([['name' => 'Expanded Withholding Tax'], ['name' => 'VAT']]) !== []) {
    throw new RuntimeException('Valid tax type names were rejected.');
}

$body = Workflow::disbursementParticularsBody(
    'Office supplies ' . Workflow::DV_PARTICULARS_SUFFIX
);
if ($body !== 'Office supplies') {
    throw new RuntimeException('The fixed Particulars suffix was not removed before recomposition.');
}

$generator = new ReportPdfGenerator();
$method = new ReflectionMethod($generator, 'mappedSpreadsheet');
$method->setAccessible(true);
$workflow = new DisbursementVoucherSmokeWorkflow();
$process = ['id' => 1, 'process_code' => 'DV-SMOKE'];
$step = ['id' => 2, 'step_key' => 'disbursement-voucher', 'report_type' => 'Disbursement Voucher'];
$baseData = [
    'DIVNO' => 'DV-001', 'BRGY' => 'Sample Barangay', 'CITY' => 'Sample City',
    'PROVINCE' => 'Sample Province', 'DATET' => '2026-08-01', 'PAYEE' => 'Sample Payee',
    'DDFUND' => 'General Funds', 'PAYEE_ADDRESS' => 'Sample Address', 'TIN' => '123',
    'PARTICULARS' => 'Office supplies' . "\n" . Workflow::DV_PARTICULARS_SUFFIX,
    'GROSS' => '1000.00', 'NETAMOUNT' => '900.00', 'CHECKNO' => '100', 'ORNO' => 'OR-001',
];

$taxed = $method->invoke($generator, $workflow, $process, $step, array_merge($baseData, [
    'DV_TAX_LINE_LABEL' => 'Less: Expanded Withholding Tax',
    'WHTAX' => '100.00',
]));
$taxedSheet = $taxed->getActiveSheet();
if ((string) $taxedSheet->getCell('A9')->getValue() !== 'Less: Expanded Withholding Tax'
    || (float) $taxedSheet->getCell('I9')->getValue() !== 100.0) {
    throw new RuntimeException('Selected tax type and deduction did not render in the DV template: '
        . (string) $taxedSheet->getCell('A9')->getValue() . ' / ' . (string) $taxedSheet->getCell('I9')->getValue());
}
if (substr_count((string) $taxedSheet->getCell('A6')->getValue(), Workflow::DV_PARTICULARS_SUFFIX) !== 1) {
    throw new RuntimeException('The fixed Particulars suffix did not render exactly once.');
}

$none = $method->invoke($generator, $workflow, $process, $step, array_merge($baseData, [
    'DV_TAX_LINE_LABEL' => '', 'WHTAX' => '', 'NETAMOUNT' => '1000.00',
]));
$noneSheet = $none->getActiveSheet();
if ((string) $noneSheet->getCell('A9')->getValue() !== '' || (string) $noneSheet->getCell('I9')->getValue() !== '') {
    throw new RuntimeException('None displayed a tax label or deduction in the DV template.');
}

$workflow->data = array_merge($baseData, [
    'DV_TAX_LINE_LABEL' => 'Less: Expanded Withholding Tax',
    'WHTAX' => '100.00',
]);
$pdfPath = $generator->generate($workflow, $process, $step);
if (!is_file($pdfPath) || filesize($pdfPath) === 0) {
    throw new RuntimeException('The Disbursement Voucher PDF was not generated.');
}

echo 'Disbursement Voucher tax smoke test passed: ' . $pdfPath . PHP_EOL;
