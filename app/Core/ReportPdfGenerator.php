<?php

namespace App\Core;

use App\Models\Workflow;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class ReportPdfGenerator
{
    private const SOFFICE = 'C:\\Program Files\\LibreOffice\\program\\soffice.com';

    public function generate(Workflow $workflow, array $process, array $step): string
    {
        $this->ensureSpreadsheetAutoload();

        $stepKey = (string) ($step['step_key'] ?? '');
        $data = $workflow->getTemplateData((int) $process['id'], (int) $step['id']);

        $spreadsheet = $stepKey === 'request-for-quotation'
            ? $this->requestForQuotationSpreadsheet($workflow, $process, $step, $data)
            : (TemplateMapping::has($stepKey)
                ? $this->mappedSpreadsheet($workflow, $process, $step, $data)
                : $this->summarySpreadsheet((string) ($step['report_type'] ?? Workflow::label($stepKey)), $process, $data));

        $outputDir = dirname(__DIR__, 2) . '/storage/generated_reports';
        if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
            throw new RuntimeException('Unable to create generated reports directory.');
        }

        $baseName = $this->safeFilename(str_replace('-', '_', $stepKey) . '_' . ($process['process_code'] ?? 'process') . '_' . date('Ymd_His'));
        $xlsxPath = $outputDir . '/' . $baseName . '.xlsx';
        $pdfPath = $outputDir . '/' . $baseName . '.pdf';

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($xlsxPath);

        if (!$this->convertToPdf($xlsxPath, $outputDir, $pdfPath)) {
            $this->writeFallbackPdf($pdfPath, (string) ($step['report_type'] ?? Workflow::label($stepKey)), $data);
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

    private function mappedSpreadsheet(Workflow $workflow, array $process, array $step, array $data): Spreadsheet
    {
        if (!is_file(TemplateMapping::templatePath())) {
            throw new RuntimeException('Template file is missing.');
        }

        $stepKey = (string) ($step['step_key'] ?? '');
        $sheetTitle = TemplateMapping::sheetFor($stepKey);
        $spreadsheet = IOFactory::load(TemplateMapping::templatePath());
        $sheet = $sheetTitle ? $spreadsheet->getSheetByName($sheetTitle) : null;

        if (!$sheet || !$sheetTitle) {
            throw new RuntimeException('Mapped template sheet was not found.');
        }

        $this->removeOtherSheets($spreadsheet, $sheetTitle);
        ReportCellColorStyler::apply($sheet);

        if ($stepKey === 'abstract-of-canvass') {
            $this->writeAbstractSupplierPlaceholderRows($sheet, $workflow->getSupplierBidsForAbstract((int) $process['id'], (int) $step['id']));
        }
        if ($stepKey === 'purchase-order') {
            $this->writePurchaseOrderItemRows($sheet, $workflow->getPurchaseOrderItemRows((int) $process['id']));
        }
        if ($stepKey === 'requisition-issue-slip') {
            $this->writeRequisitionIssueSlipRows($sheet, $data, $workflow->getRequisitionIssueSlipItemRows((int) $process['id']));
        }
        if ($stepKey === 'inspection-acceptance-report') {
            $this->writeInspectionAcceptanceRows($sheet, $workflow->getInspectionAcceptanceItemRows((int) $process['id']));
        }

        $this->replaceSheetPlaceholders($sheet, $data, TemplateMapping::supportsBracePlaceholders($stepKey));
        $this->preparePrintLayout($sheet, $stepKey, $data);

        return $spreadsheet;
    }

    private function requestForQuotationSpreadsheet(Workflow $workflow, array $process, array $step, array $data): Spreadsheet
    {
        if (!is_file(TemplateMapping::templatePath())) {
            throw new RuntimeException('Template file is missing.');
        }

        $sheetTitle = TemplateMapping::sheetFor('request-for-quotation');
        $spreadsheet = IOFactory::load(TemplateMapping::templatePath());
        $sourceSheet = $sheetTitle ? $spreadsheet->getSheetByName($sheetTitle) : null;
        $supplierBids = $workflow->getRfqSupplierBids((int) $step['id']);

        if (!$sourceSheet || !$sheetTitle) {
            throw new RuntimeException('Mapped Request for Quotation template sheet was not found.');
        }
        if ($supplierBids === []) {
            throw new RuntimeException('No RFQ suppliers were saved.');
        }

        $this->removeOtherSheets($spreadsheet, $sheetTitle);
        $templateSheet = clone $sourceSheet;
        $sheets = [$sourceSheet];
        for ($index = 1, $count = count($supplierBids); $index < $count; $index++) {
            $sheet = clone $templateSheet;
            $sheet->setTitle($this->rfqSheetTitle($supplierBids[$index], $index, $spreadsheet));
            $spreadsheet->addSheet($sheet);
            $sheets[] = $sheet;
        }

        $rows = $workflow->getRfqItemRows((int) $process['id'], (int) $step['id']);
        foreach ($sheets as $index => $sheet) {
            $supplier = $supplierBids[$index];
            if ($index === 0) {
                $sheet->setTitle($this->rfqSheetTitle($supplier, $index, $spreadsheet, $sheetTitle));
            }

            ReportCellColorStyler::apply($sheet);
            $this->writeRequestForQuotationRows($sheet, $rows, $supplier);
            $supplierData = array_merge($data, [
                'SUPPNAME' => trim((string) ($supplier['SUPPNAME'] ?? '')),
                'SUPPADD' => trim((string) ($supplier['SUPPADD'] ?? $supplier['LBADD'] ?? '')),
                'AOP' => '',
            ]);
            $this->replaceSheetPlaceholders($sheet, $supplierData, TemplateMapping::supportsBracePlaceholders('request-for-quotation'));
            $this->preparePrintLayout($sheet, 'request-for-quotation', $supplierData);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function rfqSheetTitle(array $supplier, int $index, Spreadsheet $spreadsheet, ?string $currentTitle = null): string
    {
        $name = trim((string) ($supplier['SUPPNAME'] ?? ''));
        $name = preg_replace('/[\\\\\/?*\[\]:]+/', ' ', $name) ?? '';
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        $base = mb_substr('RFQ ' . ($index + 1) . ($name !== '' ? ' ' . $name : ''), 0, 31);
        $title = $base !== '' ? $base : 'RFQ ' . ($index + 1);
        $suffix = 2;

        while (($existing = $spreadsheet->getSheetByName($title)) !== null && $title !== $currentTitle) {
            $ending = ' ' . $suffix++;
            $title = mb_substr($base, 0, 31 - mb_strlen($ending)) . $ending;
        }

        return $title;
    }

    private function removeOtherSheets(Spreadsheet $spreadsheet, string $keepTitle): void
    {
        for ($index = $spreadsheet->getSheetCount() - 1; $index >= 0; $index--) {
            if ($spreadsheet->getSheet($index)->getTitle() !== $keepTitle) {
                $spreadsheet->removeSheetByIndex($index);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);
    }

    private function writeAbstractSupplierPlaceholderRows(Worksheet $sheet, array $bids): void
    {
        $supplierNames = [];
        $amounts = [];
        foreach ($bids as $bid) {
            $supplier = trim((string) ($bid['SUPPNAME'] ?? ''));
            $amount = trim((string) ($bid['AOP'] ?? ''));
            if ($supplier !== '') {
                $supplierNames[] = $supplier;
            }
            if ($amount !== '') {
                $amounts[] = $amount;
            }
        }

        if ($supplierNames !== []) {
            $sheet->setCellValue('B15', implode("\n", $supplierNames));
        }
        if ($amounts !== []) {
            $sheet->setCellValue('F15', implode("\n", $amounts));
        }
    }

    private function writeRequestForQuotationRows(Worksheet $sheet, array $rows, array $supplier): void
    {
        $startRow = 23;
        foreach ($rows as $index => $row) {
            $rowNumber = $startRow + $index;
            $this->copyPreviousRowStyleIfNeeded($sheet, 23, $rowNumber, 'A', 'H');
            $sheet->setCellValue('A' . $rowNumber, trim((string) ($row['PRQTY'] ?? '')));
            $sheet->setCellValue('B' . $rowNumber, trim((string) ($row['UOE'] ?? '')));
            $sheet->setCellValue('C' . $rowNumber, trim((string) ($row['PRPART'] ?? '')));
        }

        $sheet->setCellValue('A8', trim((string) ($supplier['SUPPNAME'] ?? '')));
        $sheet->setCellValue('A9', trim((string) ($supplier['SUPPADD'] ?? $supplier['LBADD'] ?? '')));
    }

    private function writePurchaseOrderItemRows(Worksheet $sheet, array $rows): void
    {
        $startRow = 16;
        $templateRows = 2;
        $totalTemplateRow = 18;
        $additionalRows = max(0, count($rows) - $templateRows);
        $sourceRow = 17;
        $sourceStyle = $sheet->getStyle('A' . $sourceRow . ':H' . $sourceRow);
        $sourceHeight = $sheet->getRowDimension($sourceRow)->getRowHeight();
        $sourceMergedRanges = $this->mergedRangesForRow($sheet, $sourceRow);

        if ($additionalRows > 0) {
            $sheet->insertNewRowBefore($totalTemplateRow, $additionalRows);
        }

        foreach ($rows as $index => $row) {
            $rowNumber = $startRow + $index;
            if ($rowNumber >= $totalTemplateRow) {
                $sheet->duplicateStyle($sourceStyle, 'A' . $rowNumber . ':H' . $rowNumber);
                $sheet->getRowDimension($rowNumber)->setRowHeight($sourceHeight);
                $this->copyRowSetup($sheet, $sourceMergedRanges, $rowNumber);
            }
            $this->ensureMergedRange($sheet, 'B' . $rowNumber . ':D' . $rowNumber);
            $this->ensureMergedRange($sheet, 'F' . $rowNumber . ':G' . $rowNumber);
            $sheet->setCellValue('A' . $rowNumber, trim((string) ($row['UOE'] ?? '')));
            $sheet->setCellValue('B' . $rowNumber, trim((string) ($row['PRPART'] ?? '')));
            $sheet->setCellValue('E' . $rowNumber, trim((string) ($row['PRQTY'] ?? '')));
            $sheet->setCellValue('F' . $rowNumber, trim((string) ($row['POUNITCOST'] ?? '')));
            $sheet->setCellValue('H' . $rowNumber, trim((string) ($row['POAMOUNT'] ?? '')));
        }

        $totalRow = $totalTemplateRow + $additionalRows;
        $lastItemRow = $startRow + count($rows) - 1;
        for ($rowNumber = max($startRow, $lastItemRow + 1); $rowNumber < $totalRow; $rowNumber++) {
            foreach (['A', 'B', 'E', 'F', 'H'] as $column) {
                $sheet->setCellValue($column . $rowNumber, '');
            }
        }

        $sheet->setCellValue('A' . $totalRow, 'TOTAL');
        $sheet->setCellValue('H' . $totalRow, $rows === [] ? '' : '=SUM(H' . $startRow . ':H' . $lastItemRow . ')');
    }

    private function writeRequisitionIssueSlipRows(Worksheet $sheet, array $data, array $rows): void
    {
        if ($rows === []) {
            foreach (['A', 'B', 'E', 'G'] as $column) {
                $sheet->setCellValue($column . '11', '');
            }
            return;
        }

        $startRow = 11;
        $templateRows = 1;
        $additionalRows = max(0, count($rows) - $templateRows);
        $sourceStyle = $sheet->getStyle('A' . $startRow . ':H' . $startRow);
        $sourceHeight = $sheet->getRowDimension($startRow)->getRowHeight();
        $sourceMergedRanges = $this->mergedRangesForRow($sheet, $startRow);

        if ($additionalRows > 0) {
            $sheet->insertNewRowBefore($startRow + $templateRows, $additionalRows);
        }

        foreach ($rows as $index => $row) {
            $rowNumber = $startRow + $index;
            $sheet->duplicateStyle($sourceStyle, 'A' . $rowNumber . ':H' . $rowNumber);
            $sheet->getRowDimension($rowNumber)->setRowHeight($sourceHeight);
            $this->copyRowSetup($sheet, $sourceMergedRanges, $rowNumber);
            $this->ensureMergedRange($sheet, 'B' . $rowNumber . ':D' . $rowNumber);
            $this->ensureMergedRange($sheet, 'E' . $rowNumber . ':F' . $rowNumber);
            $this->ensureMergedRange($sheet, 'G' . $rowNumber . ':H' . $rowNumber);
            $sheet->getStyle('A' . $rowNumber . ':H' . $rowNumber)->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
            $sheet->getStyle('A' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('E' . $rowNumber . ':F' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $quantity = trim((string) ($row['PRQTY'] ?? ''));
            $sheet->setCellValue('A' . $rowNumber, trim((string) ($row['UOE'] ?? '')));
            $sheet->setCellValue('B' . $rowNumber, trim((string) ($row['PRPART'] ?? '')));
            $sheet->setCellValue('E' . $rowNumber, is_numeric($quantity) ? (float) $quantity : $quantity);
            $sheet->setCellValue('G' . $rowNumber, '');
        }
    }

    private function writeInspectionAcceptanceRows(Worksheet $sheet, array $rows): void
    {
        if ($rows === []) {
            $sheet->setCellValue('A10', '');
            $sheet->setCellValue('B10', '');
            $sheet->setCellValue('F10', '');
            return;
        }

        $startRow = 10;
        $templateRows = 2;
        $count = count($rows);
        $additionalRows = max(0, $count - $templateRows);
        $sourceStyle = $sheet->getStyle('A' . $startRow . ':H' . $startRow);
        $sourceHeight = $sheet->getRowDimension($startRow)->getRowHeight();
        $sourceMergedRanges = $this->mergedRangesForRow($sheet, $startRow);

        if ($additionalRows > 0) {
            $sheet->insertNewRowBefore($startRow + $templateRows, $additionalRows);
        }

        foreach ($rows as $index => $row) {
            $rowNumber = $startRow + $index;
            $sheet->duplicateStyle($sourceStyle, 'A' . $rowNumber . ':H' . $rowNumber);
            $sheet->getRowDimension($rowNumber)->setRowHeight($sourceHeight);
            $this->copyRowSetup($sheet, $sourceMergedRanges, $rowNumber);
            $this->ensureMergedRange($sheet, 'B' . $rowNumber . ':E' . $rowNumber);
            $this->ensureMergedRange($sheet, 'F' . $rowNumber . ':G' . $rowNumber);
            $sheet->getStyle('A' . $rowNumber . ':H' . $rowNumber)->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
            $sheet->getStyle('A' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $rowNumber . ':E' . $rowNumber)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setIndent(1);
            $sheet->getStyle('F' . $rowNumber . ':G' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $quantity = trim((string) ($row['PRQTY'] ?? ''));
            $sheet->setCellValue('A' . $rowNumber, trim((string) ($row['UOE'] ?? '')));
            $sheet->setCellValue('B' . $rowNumber, trim((string) ($row['PRPART'] ?? '')));
            $sheet->setCellValue('F' . $rowNumber, is_numeric($quantity) ? (float) $quantity : $quantity);
        }
    }

    private function copyPreviousRowStyleIfNeeded(Worksheet $sheet, int $sourceRow, int $targetRow, string $fromColumn, string $toColumn): void
    {
        if ($targetRow === $sourceRow) {
            return;
        }

        $sheet->duplicateStyle($sheet->getStyle($fromColumn . $sourceRow . ':' . $toColumn . $sourceRow), $fromColumn . $targetRow . ':' . $toColumn . $targetRow);
        $sheet->getRowDimension($targetRow)->setRowHeight($sheet->getRowDimension($sourceRow)->getRowHeight());
    }

    private function mergedRangesForRow(Worksheet $sheet, int $row): array
    {
        $ranges = [];
        foreach ($sheet->getMergeCells() as $range) {
            [$start, $end] = explode(':', $range);
            [, $startRow] = $this->splitCellReference($start);
            [, $endRow] = $this->splitCellReference($end);
            if ((int) $startRow === $row && (int) $endRow === $row) {
                $ranges[] = $range;
            }
        }

        return $ranges;
    }

    private function copyRowSetup(Worksheet $sheet, array $sourceMergedRanges, int $targetRow): void
    {
        foreach ($sourceMergedRanges as $range) {
            [$start, $end] = explode(':', $range);
            [$startColumn] = $this->splitCellReference($start);
            [$endColumn] = $this->splitCellReference($end);
            $this->ensureMergedRange($sheet, $startColumn . $targetRow . ':' . $endColumn . $targetRow);
        }
    }

    private function ensureMergedRange(Worksheet $sheet, string $range): void
    {
        if (!in_array($range, $sheet->getMergeCells(), true)) {
            $sheet->mergeCells($range);
        }
    }

    private function splitCellReference(string $cell): array
    {
        preg_match('/^([A-Z]+)(\d+)$/', $cell, $matches);
        return [$matches[1], $matches[2]];
    }

    private function replaceSheetPlaceholders(Worksheet $sheet, array $values, bool $supportBraceSyntax): void
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

                $cell->setValue(TemplateMapping::replacePlaceholders($value, $values, $supportBraceSyntax));
            }
        }
    }

    private function preparePrintLayout(Worksheet $sheet, string $stepKey, array $data): void
    {
        if ($stepKey === 'obligation-report') {
            $sheet->setCellValue('B9', (string) ($data['PAYEE'] ?? ''));
            $sheet->setCellValue('E44', (string) ($data['AOCCHAIRMAN'] ?? ''));
            $this->setPrintArea($sheet, 'A1:G48');
            return;
        }

        if ($stepKey === 'disbursement-voucher') {
            $this->setPrintArea($sheet, 'A1:I41');
            return;
        }

        if ($stepKey === 'requisition-issue-slip') {
            $lastRow = max(24, $sheet->getHighestDataRow());
            $this->setPrintArea($sheet, 'A1:H' . $lastRow);
            return;
        }

        if ($stepKey === 'inspection-acceptance-report') {
            $this->setPrintArea($sheet, 'A1:H22');
            return;
        }

        $this->setPrintArea($sheet, $sheet->calculateWorksheetDimension());
    }

    private function setPrintArea(Worksheet $sheet, string $area): void
    {
        ReportPrintSettings::apply($sheet, $area);
    }

    private function summarySpreadsheet(string $reportType, array $process, array $data): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($reportType, 0, 31));
        $sheet->setCellValue('A1', $reportType);
        $sheet->setCellValue('A2', 'Process: ' . ($process['process_code'] ?? ''));

        $row = 4;
        foreach ($data as $key => $value) {
            if (str_starts_with((string) $key, '__')) {
                continue;
            }
            $sheet->setCellValue('A' . $row, (string) $key);
            $sheet->setCellValue('B' . $row, (string) $value);
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(60);
        $this->setPrintArea($sheet, 'A1:B' . max(4, $row));

        return $spreadsheet;
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

    private function writeFallbackPdf(string $pdfPath, string $title, array $data): void
    {
        $page = ReportPrintSettings::pdfPage(ReportPrintSettings::configuredSize());
        $pageWidth = $this->pdfNumber($page['width']);
        $pageHeight = $this->pdfNumber($page['height']);
        $startY = $this->pdfNumber($page['height'] - 46.0);
        $lines = [$title, ''];
        foreach ($data as $key => $value) {
            if (str_starts_with((string) $key, '__')) {
                continue;
            }
            $lines[] = $key . ': ' . (string) $value;
        }

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
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        file_put_contents($pdfPath, $pdf);
    }

    private function pdfNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function truncatePdfLine(string $line): string
    {
        return mb_substr($line, 0, 96);
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function safeFilename(string $name): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $name) ?? 'report';
        return trim($safe, '_') ?: 'report';
    }
}
