<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\ReportPdfGenerator;
use App\Models\Workflow;

final class RfqSmokeWorkflow extends Workflow
{
    public function __construct()
    {
    }

    public function getTemplateData(int $processId, int $stepId): array
    {
        return [
            'BRGY' => 'Sample Barangay',
            'CITY' => 'Sample City',
            'PROVINCE' => 'Sample Province',
            'BARANGAY_TREASURER' => 'Sample Treasurer',
            'SUPPNAME' => 'SHOULD NOT LEAK',
            'SUPPADD' => 'SHOULD NOT LEAK',
            'AOP' => '999999.99',
        ];
    }

    public function getRfqSupplierBids(int $stepId): array
    {
        return [
            ['SUPPNAME' => 'Supplier / Alpha', 'SUPPADD' => 'Alpha Address', 'AOP' => '101.25'],
            ['SUPPNAME' => 'Supplier / Alpha', 'SUPPADD' => 'Beta Address', 'AOP' => '202.50'],
        ];
    }

    public function getRfqItemRows(int $processId, int $stepId, ?array $quoteRows = null): array
    {
        return [
            ['PRQTY' => '2', 'UOE' => 'box', 'PRPART' => 'Bond paper'],
            ['PRQTY' => '3', 'UOE' => 'pc', 'PRPART' => 'Ink cartridge'],
        ];
    }
}

$workflow = new RfqSmokeWorkflow();
$generator = new ReportPdfGenerator();
$method = new ReflectionMethod($generator, 'requestForQuotationSpreadsheet');
$method->setAccessible(true);
$process = ['id' => 1, 'process_code' => 'RFQ-SMOKE'];
$step = ['id' => 2, 'step_key' => 'request-for-quotation', 'report_type' => 'Request for Quotation'];
$data = $workflow->getTemplateData(1, 2);
$spreadsheet = $method->invoke($generator, $workflow, $process, $step, $data);

if ($spreadsheet->getSheetCount() !== 2) {
    throw new RuntimeException('Expected exactly two supplier RFQ sheets.');
}

$expected = [
    ['Supplier / Alpha', 'Alpha Address'],
    ['Supplier / Alpha', 'Beta Address'],
];
$titles = [];
foreach ($spreadsheet->getAllSheets() as $index => $sheet) {
    $titles[] = $sheet->getTitle();
    if ($sheet->getCell('A8')->getValue() !== $expected[$index][0]) {
        throw new RuntimeException('Supplier name was not isolated on RFQ sheet ' . ($index + 1) . '.');
    }
    if ($sheet->getCell('A9')->getValue() !== $expected[$index][1]) {
        throw new RuntimeException('Supplier address was not isolated on RFQ sheet ' . ($index + 1) . '.');
    }
    if ((string) $sheet->getCell('A23')->getValue() !== '2' || (string) $sheet->getCell('C24')->getValue() !== 'Ink cartridge') {
        throw new RuntimeException('Inherited PR items were not copied to every RFQ sheet.');
    }
    foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
        $value = $sheet->getCell($coordinate)->getValue();
        if (is_string($value) && (str_contains($value, '999999.99') || str_contains($value, '101.25') || str_contains($value, '202.50'))) {
            throw new RuntimeException('A proposal amount leaked into a generated RFQ.');
        }
    }
}

if (count(array_unique($titles)) !== 2 || max(array_map('mb_strlen', $titles)) > 31) {
    throw new RuntimeException('RFQ worksheet titles were not safe and unique.');
}

$pdfPath = $generator->generate($workflow, $process, $step);
if (!is_file($pdfPath) || filesize($pdfPath) === 0) {
    throw new RuntimeException('Combined RFQ PDF was not generated.');
}

echo 'RFQ generation smoke test passed: ' . $pdfPath . PHP_EOL;
