<?php

namespace App\Core;

use App\Models\SystemInput;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportPrintSettings
{
    public const KEY = 'REPORT_PAPER_SIZE';
    public const DEFAULT_SIZE = 'A4';

    public static function normalize(?string $value): string
    {
        $value = strtoupper(trim((string) $value));
        return in_array($value, ['A4', 'LETTER', 'LEGAL'], true) ? $value : self::DEFAULT_SIZE;
    }

    public static function configuredSize(?array $settings = null): string
    {
        $settings ??= (new SystemInput())->values();
        return self::normalize($settings[self::KEY] ?? null);
    }

    public static function apply(Worksheet $sheet, string $printArea, ?array $settings = null): void
    {
        $sheet->getPageSetup()
            ->setPrintArea($printArea)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(self::spreadsheetPaperSize(self::configuredSize($settings)));

        $sheet->getPageMargins()->setTop(0.5)->setRight(0.5)->setBottom(0.5)->setLeft(0.5);
        $sheet->getPageSetup()->setHorizontalCentered(true);
    }

    public static function pdfPage(string $size): array
    {
        return match (self::normalize($size)) {
            'LETTER' => ['width' => 612.0, 'height' => 792.0],
            'LEGAL' => ['width' => 612.0, 'height' => 1008.0],
            default => ['width' => 595.28, 'height' => 841.89],
        };
    }

    private static function spreadsheetPaperSize(string $size): int
    {
        return match ($size) {
            'LETTER' => PageSetup::PAPERSIZE_LETTER,
            'LEGAL' => PageSetup::PAPERSIZE_LEGAL,
            default => PageSetup::PAPERSIZE_A4,
        };
    }
}
