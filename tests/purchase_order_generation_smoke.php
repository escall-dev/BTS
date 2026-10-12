<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\ReportPdfGenerator;
use App\Models\Workflow;

final class PurchaseOrderSmokeWorkflow extends Workflow
{
    public array $rows = [];
    public array $data = [];

    public function __construct()
    {
    }

    public function getPurchaseOrderItemRows(int $processId): array
    {
        return $this->rows;
    }

    public function getTemplateData(int $processId, int $stepId): array
    {
        return $this->data;
    }
}

$generator = new ReportPdfGenerator();
$method = new ReflectionMethod($generator, 'mappedSpreadsheet');
$method->setAccessible(true);
$workflow = new PurchaseOrderSmokeWorkflow();
$process = ['id' => 1, 'process_code' => 'PO-SMOKE'];
$step = ['id' => 2, 'step_key' => 'purchase-order', 'report_type' => 'Purchase Order'];
$data = [
    'BRGY' => 'Sample Barangay',
    'CITY' => 'Sample City',
    'PROVINCE' => 'Sample Province',
    'PAYEE' => 'Sample Supplier',
    'PAYEE_ADDRESS' => 'Sample Address',
    'PONUM' => 'PO-001',
    'PODATE' => '2026-08-01',
    'TIN' => '',
    'PODOD' => '2026-08-15',
    'POPOD' => 'Barangay Hall',
    'PODT' => 'Full',
    'POPT' => 'Partial',
    'BID' => '☐',
    'NEG' => '☒',
    'SHOP' => '☐',
    'PUNONG_BARANGAY' => 'Sample Captain',
    'AOCCHAIRMAN' => 'Sample Chairperson',
];
$workflow->data = $data;

foreach ([1, 2, 3] as $itemCount) {
    $workflow->rows = [];
    for ($index = 0; $index < $itemCount; $index++) {
        $workflow->rows[] = [
            'UOE' => $index === 0 ? 'set' : 'pc',
            'PRPART' => 'Item ' . ($index + 1),
            'PRQTY' => '2',
            'POUNITCOST' => '10.00',
            'POAMOUNT' => '20.00',
        ];
    }

    $book = $method->invoke($generator, $workflow, $process, $step, $data);
    $sheet = $book->getActiveSheet();
    $totalRow = 18 + max(0, $itemCount - 2);
    $lastItemRow = 16 + $itemCount - 1;

    if ((string) $sheet->getCell('A16')->getValue() !== 'set') {
        throw new RuntimeException('The first PO unit was not written to the first item row.');
    }
    if ((string) $sheet->getCell('A' . $totalRow)->getValue() !== 'TOTAL') {
        throw new RuntimeException('TOTAL was overwritten for ' . $itemCount . ' item(s).');
    }
    if ((string) $sheet->getCell('H' . $totalRow)->getValue() !== '=SUM(H16:H' . $lastItemRow . ')') {
        throw new RuntimeException('The PO total formula has the wrong item range.');
    }
    if ((string) $sheet->getCell('A8')->getValue() !== 'TIN: ') {
        throw new RuntimeException('TIN was not blank in the generated PO.');
    }
    if (!str_contains((string) $sheet->getCell('E8')->getValue(), '☒ Negotiated')) {
        throw new RuntimeException('The selected Mode of Payment was not marked.');
    }
    foreach (['A11' => 'Barangay Hall', 'F11' => 'Full', 'A12' => '2026-08-15', 'F12' => 'Partial'] as $cell => $expected) {
        if (!str_contains((string) $sheet->getCell($cell)->getValue(), $expected)) {
            throw new RuntimeException('Missing PO delivery value in ' . $cell . '.');
        }
    }
}

$pdfPath = $generator->generate($workflow, $process, $step);
if (!is_file($pdfPath) || filesize($pdfPath) === 0) {
    throw new RuntimeException('The Purchase Order PDF was not generated.');
}

echo 'Purchase Order generation smoke test passed: ' . $pdfPath . PHP_EOL;
