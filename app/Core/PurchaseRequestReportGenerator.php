<?php

namespace App\Core;

use App\Models\Workflow;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class PurchaseRequestReportGenerator
{
    private const SOFFICE = 'C:\\Program Files\\LibreOffice\\program\\soffice.com';

    public function generate(Workflow $workflow, array $process, array $step): string
    {
        $this->ensureSpreadsheetAutoload();

        if (!is_file(TemplateMapping::templatePath())) {
            throw new RuntimeException('Template file is missing.');
        }

        $spreadsheet = IOFactory::load(TemplateMapping::templatePath());
        $sheet = $spreadsheet->getSheetByName('Purchase Request');

        if (!$sheet) {
            throw new RuntimeException('Purchase Request sheet was not found in the template.');
        }

        $this->removeOtherSheets($spreadsheet, 'Purchase Request');
        ReportCellColorStyler::apply($sheet);

        $data = $workflow->getTemplateData((int) $process['id'], (int) $step['id']);
        $items = $workflow->getPurchaseItems((int) $process['id']);

        if ($items === []) {
            throw new RuntimeException('No Purchase Request item rows were saved.');
        }

        $this->writeItems($sheet, $items);
        $this->replaceSheetPlaceholders($sheet, $data);
        $printArea = $sheet->getPageSetup()->getPrintArea();
        ReportPrintSettings::apply($sheet, $printArea !== '' ? $printArea : $sheet->calculateWorksheetDimension());

        $outputDir = dirname(__DIR__, 2) . '/storage/generated_reports';
        if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
            throw new RuntimeException('Unable to create generated reports directory.');
        }

        $baseName = $this->safeFilename('Purchase_Request_' . $process['process_code'] . '_' . date('Ymd_His'));
        $xlsxPath = $outputDir . '/' . $baseName . '.xlsx';
        $pdfPath = $outputDir . '/' . $baseName . '.pdf';

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($xlsxPath);

        if (!$this->convertToPdf($xlsxPath, $outputDir, $pdfPath)) {
            $this->writeFallbackPdf($pdfPath, $data, $items);
        }

        if (!is_file($pdfPath)) {
            throw new RuntimeException('PDF was not generated.');
        }

        return $pdfPath;
    }

    private function ensureSpreadsheetAutoload(): void
    {
        if (class_exists(IOFactory::class)) {
            return;
        }

        $localAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $fallbackAutoload = 'C:/xampp/htdocs/FinanceSystem/vendor/autoload.php';

        if (is_file($localAutoload)) {
            require_once $localAutoload;
            return;
        }

        if (is_file($fallbackAutoload)) {
            require_once $fallbackAutoload;
            return;
        }

        throw new RuntimeException('PhpSpreadsheet autoload file was not found.');
    }

    private function removeOtherSheets($spreadsheet, string $keepTitle): void
    {
        for ($index = $spreadsheet->getSheetCount() - 1; $index >= 0; $index--) {
            if ($spreadsheet->getSheet($index)->getTitle() !== $keepTitle) {
                $spreadsheet->removeSheetByIndex($index);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);
    }

    private function writeItems(Worksheet $sheet, array $items): void
    {
        $startRow = 9;
        $styleSourceRow = 9;
        $templateItemRows = 2;
        $totalRowTemplate = 11;
        $itemCount = count($items);

        if ($itemCount < $templateItemRows) {
            $sheet->removeRow($startRow + $itemCount, $templateItemRows - $itemCount);
        } elseif ($itemCount > $templateItemRows) {
            $sheet->insertNewRowBefore($totalRowTemplate, $itemCount - $templateItemRows);
        }

        $totalRow = $startRow + $itemCount;
        $lastItemRow = $totalRow - 1;

        for ($row = $startRow; $row <= $lastItemRow; $row++) {
            $this->copyItemRowFormat($sheet, $styleSourceRow, $row);
            $this->clearItemRowValues($sheet, $row);
        }

        foreach ($items as $index => $item) {
            $row = $startRow + $index;
            $sheet->setCellValue('A' . $row, $item['item_no']);
            $sheet->setCellValue('B' . $row, (float) $item['quantity']);
            $sheet->setCellValue('C' . $row, $item['unit_of_measurement']);
            $sheet->setCellValue('D' . $row, $item['particulars']);
            $sheet->setCellValue('G' . $row, (float) $item['estimated_unit_cost']);
            $sheet->setCellValue('H' . $row, '=IF(OR(B' . $row . '="",G' . $row . '=""),"",B' . $row . '*G' . $row . ')');
        }

        $this->prepareTotalRow($sheet, $totalRow);
        $sheet->setCellValue('A' . $totalRow, 'TOTAL');
        $sheet->setCellValue('H' . $totalRow, '=IF(COUNT(H' . $startRow . ':H' . $lastItemRow . ')=0,"",SUM(H' . $startRow . ':H' . $lastItemRow . '))');
        $this->removeDuplicateTotalRows($sheet, $totalRow);
    }

    private function copyItemRowFormat(Worksheet $sheet, int $sourceRow, int $targetRow): void
    {
        foreach (range('A', 'H') as $column) {
            $sheet->duplicateStyle($sheet->getStyle($column . $sourceRow), $column . $targetRow);
        }

        $sheet->getRowDimension($targetRow)->setRowHeight($sheet->getRowDimension($sourceRow)->getRowHeight());
        $this->resetRowMerges($sheet, $targetRow, 'A', 'H');
        $sheet->mergeCells('D' . $targetRow . ':F' . $targetRow);
    }

    private function prepareTotalRow(Worksheet $sheet, int $totalRow): void
    {
        $this->resetRowMerges($sheet, $totalRow, 'A', 'H');

        foreach (range('A', 'H') as $column) {
            $sheet->setCellValue($column . $totalRow, null);
        }

        $sheet->mergeCells('A' . $totalRow . ':G' . $totalRow);
    }

    private function removeDuplicateTotalRows(Worksheet $sheet, int $totalRow): void
    {
        $nextRow = $totalRow + 1;

        while (strtoupper(trim((string) $sheet->getCell('A' . $nextRow)->getValue())) === 'TOTAL') {
            $sheet->removeRow($nextRow, 1);
        }
    }

    private function clearItemRowValues(Worksheet $sheet, int $row): void
    {
        foreach (['A', 'B', 'C', 'D', 'G', 'H'] as $column) {
            $sheet->setCellValue($column . $row, null);
        }
    }

    private function resetRowMerges(Worksheet $sheet, int $row, string $fromColumn, string $toColumn): void
    {
        foreach ($sheet->getMergeCells() as $range) {
            [$start, $end] = explode(':', $range);
            [$startColumn, $startRow] = $this->splitCellReference($start);
            [$endColumn, $endRow] = $this->splitCellReference($end);

            if ((int) $startRow === $row && (int) $endRow === $row && $startColumn >= $fromColumn && $endColumn <= $toColumn) {
                $sheet->unmergeCells($range);
            }
        }
    }

    private function splitCellReference(string $cell): array
    {
        preg_match('/^([A-Z]+)(\d+)$/', $cell, $matches);
        return [$matches[1], $matches[2]];
    }

    private function replaceSheetPlaceholders(Worksheet $sheet, array $values): void
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();

        for ($row = 1; $row <= $highestRow; $row++) {
            foreach (range('A', $highestColumn) as $column) {
                $cell = $sheet->getCell($column . $row);
                $value = $cell->getValue();

                if (!is_string($value) || $value === '' || str_starts_with($value, '=')) {
                    continue;
                }

                $cell->setValue(TemplateMapping::replacePlaceholders($value, $values));
            }
        }
    }

    private function convertToPdf(string $xlsxPath, string $outputDir, string $expectedPdfPath): bool
    {
        if (!is_file(self::SOFFICE)) {
            return false;
        }

        $profileDir = dirname(__DIR__, 2) . '/storage/libreoffice_profile';
        if (!is_dir($profileDir)) {
            mkdir($profileDir, 0777, true);
        }

        $cmd = '"' . self::SOFFICE . '" --headless --nologo --nodefault --nofirststartwizard --nolockcheck --norestore -env:UserInstallation=file:///' . str_replace('\\', '/', $profileDir) . ' --convert-to pdf --outdir ' . escapeshellarg($outputDir) . ' ' . escapeshellarg($xlsxPath) . ' 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        return $code === 0 && is_file($expectedPdfPath);
    }

    private function writeFallbackPdf(string $pdfPath, array $data, array $items): void
    {
        $page = ReportPrintSettings::pdfPage(ReportPrintSettings::configuredSize());
        $pageWidth = $this->pdfNumber($page['width']);
        $pageHeight = $this->pdfNumber($page['height']);
        $startY = $this->pdfNumber($page['height'] - 46.0);
        $lines = [
            'PURCHASE REQUEST',
            'P.R. No.: ' . ($data['PRNUM'] ?? ''),
            'Date: ' . ($data['DatePR'] ?? ''),
            'Purpose: ' . ($data['PRPURPOSE'] ?? ''),
            '',
            'Item No. | Qty | Unit | Particulars | Estimated Unit Cost | Estimated Amount',
        ];

        $total = 0.0;
        foreach ($items as $item) {
            $qty = (float) $item['quantity'];
            $unitCost = (float) $item['estimated_unit_cost'];
            $amount = $qty * $unitCost;
            $total += $amount;
            $lines[] = implode(' | ', [
                $item['item_no'],
                number_format($qty, 2),
                $item['unit_of_measurement'],
                $item['particulars'],
                number_format($unitCost, 2),
                number_format($amount, 2),
            ]);
        }

        $lines[] = '';
        $lines[] = 'Total: ' . number_format($total, 2);
        $lines[] = '';
        $lines[] = 'Requested by: ' . ($data['BARANGAY_TREASURER'] ?? '');
        $lines[] = 'Approved by: ' . ($data['PUNONG_BARANGAY'] ?? '');

        $content = "BT\n/F1 10 Tf\n36 {$startY} Td\n14 TL\n";
        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $content .= "T*\n";
            }
            $content .= '(' . $this->escapePdfText($this->truncatePdfLine($line)) . ") Tj\n";
        }
        $content .= "ET";

        $objects = [];
        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";
        $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>\nendobj\n";
        $objects[] = "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream\nendobj\n";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

        file_put_contents($pdfPath, $pdf);
    }

    private function pdfNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function escapePdfText(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    private function truncatePdfLine(string $value): string
    {
        return mb_substr($value, 0, 110);
    }

    private function safeFilename(string $value): string
    {
        return trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', $value), '_');
    }
}
