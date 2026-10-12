<?php

namespace App\Core;

use App\Models\UiSettings;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportCellColorStyler
{
    private const PATTERN = '/[$][{]CELL_COLOR:([A-Z][A-Z0-9_]*)[}]/';
    private const CACHE_FILE = 'report-cell-color-groups.json';

    public static function discoverGroups(string $path): array
    {
        if (!is_file($path)) return [];
        $signature = self::templateSignature($path);
        $cached = self::readCache($signature);
        if ($cached !== null) return $cached;

        self::autoload();
        try {
            $book = IOFactory::load($path);
            $groups = [];
            foreach ($book->getAllSheets() as $sheet) {
                foreach (self::marked($sheet) as $item) foreach ($item[2][1] as $group) $groups[$group] = true;
            }
            $book->disconnectWorksheets();
            $groups = array_keys($groups);
            sort($groups, SORT_STRING);
            self::writeCache($signature, $groups);
            return $groups;
        } catch (\Throwable) {
            return [];
        }
    }

    private static function templateSignature(string $path): array
    {
        return [
            'path' => realpath($path) ?: $path,
            'modified_at' => filemtime($path) ?: 0,
            'size' => filesize($path) ?: 0,
        ];
    }

    private static function readCache(array $signature): ?array
    {
        $file = self::cachePath();
        if (!is_file($file)) return null;
        $decoded = json_decode((string) @file_get_contents($file), true);
        if (!is_array($decoded) || ($decoded['template'] ?? null) !== $signature) return null;
        $groups = $decoded['groups'] ?? null;
        if (!is_array($groups)) return null;
        foreach ($groups as $group) {
            if (!is_string($group) || preg_match('/^[A-Z][A-Z0-9_]*$/', $group) !== 1) return null;
        }
        return array_values($groups);
    }

    private static function writeCache(array $signature, array $groups): void
    {
        $directory = dirname(self::cachePath());
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) return;
        $json = json_encode(['template' => $signature, 'groups' => array_values($groups)], JSON_UNESCAPED_SLASHES);
        if ($json === false) return;

        $temporary = @tempnam($directory, 'cell-colors-');
        if ($temporary === false) return;
        if (@file_put_contents($temporary, $json, LOCK_EX) === false || !@rename($temporary, self::cachePath())) {
            @unlink($temporary);
            @file_put_contents(self::cachePath(), $json, LOCK_EX);
        }
    }

    private static function cachePath(): string
    {
        return dirname(__DIR__, 2) . '/storage/cache/' . self::CACHE_FILE;
    }
    public static function apply(Worksheet $sheet, ?array $settings = null): void
    {
        $settings ??= (new UiSettings())->all();
        foreach (self::marked($sheet) as [$coordinate, $value, $matches]) {
            $clean = trim((string) preg_replace(self::PATTERN, '', $value));
            $sheet->getCell($coordinate)->setValue((string) preg_replace('/[ \t]{2,}/', ' ', $clean));
            foreach (array_unique($matches[1]) as $group) {
                $color = self::color((string) ($settings['UI_REPORT_CELL_COLOR_' . $group] ?? ''));
                if ($color === null) continue;
                $range = self::mergeFor($sheet, $coordinate) ?? $coordinate;
                $style = $sheet->getStyle($range);
                $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF' . substr($color, 1));
                $style->getFont()->getColor()->setARGB(self::contrast($color));
            }
        }
    }

    private static function marked(Worksheet $sheet): array
    {
        $result = [];
        foreach ($sheet->getRowIterator() as $row) {
            $cells = $row->getCellIterator();
            $cells->setIterateOnlyExistingCells(true);
            foreach ($cells as $cell) {
                $value = $cell->getValue();
                if (!is_string($value) || $value === '' || str_starts_with($value, '=')) continue;
                if (preg_match_all(self::PATTERN, $value, $matches) > 0) $result[] = [$cell->getCoordinate(), $value, $matches];
            }
        }
        return $result;
    }

    private static function mergeFor(Worksheet $sheet, string $coordinate): ?string
    {
        [$letters, $row] = Coordinate::coordinateFromString($coordinate);
        $column = Coordinate::columnIndexFromString($letters);
        foreach ($sheet->getMergeCells() as $range) {
            [$start, $end] = Coordinate::rangeBoundaries($range);
            if ($column >= $start[0] && $column <= $end[0] && (int) $row >= $start[1] && (int) $row <= $end[1]) return $range;
        }
        return null;
    }

    private static function color(string $value): ?string
    {
        $value = strtoupper(trim($value));
        return preg_match('/^#[0-9A-F]{6}$/', $value) === 1 ? $value : null;
    }

    private static function contrast(string $hex): string
    {
        $r = hexdec(substr($hex, 1, 2)); $g = hexdec(substr($hex, 3, 2)); $b = hexdec(substr($hex, 5, 2));
        return ((0.299 * $r) + (0.587 * $g) + (0.114 * $b)) >= 150 ? 'FF1B2021' : 'FFFFFFFF';
    }

    private static function autoload(): void
    {
        if (!class_exists(\App\Core\Model::class, false)) {
            require_once dirname(__DIR__) . '/Core/Model.php';
        }
        if (class_exists(IOFactory::class)) return;
        foreach ([dirname(__DIR__, 2) . '/vendor/autoload.php', 'C:/xampp/htdocs/FinanceSystem/vendor/autoload.php'] as $file) {
            if (is_file($file)) { require_once $file; return; }
        }
    }
}