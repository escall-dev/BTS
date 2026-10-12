<?php

namespace App\Models;

use App\Core\Model;

class UiSettings extends Model
{
    private const DEFAULTS = [
        'UI_THEME_PRESET' => 'olive',
        'UI_PRIMARY_COLOR' => '#51513D',
        'UI_ACCENT_COLOR' => '#A6A867',
        'UI_LOGO_PATH' => '',
        'UI_APP_TITLE' => 'BrgyTreasurySystem',
    ];

    public static function palettes(): array
    {
        return [
            'olive' => ['label' => 'Olive', 'primary' => '#51513D', 'accent' => '#A6A867'],
            'blue' => ['label' => 'Blue', 'primary' => '#243B5A', 'accent' => '#4F7CAC'],
            'teal' => ['label' => 'Teal', 'primary' => '#244A48', 'accent' => '#4F9D8D'],
            'slate' => ['label' => 'Slate', 'primary' => '#374151', 'accent' => '#94A3B8'],
            'custom' => ['label' => 'Custom', 'primary' => '#51513D', 'accent' => '#A6A867'],
        ];
    }

    public function all(): array
    {
        $stmt = $this->db->query("SELECT input_key, input_value FROM system_inputs WHERE input_key LIKE 'UI\\_%'");
        $settings = self::DEFAULTS;
        foreach ($stmt->fetchAll() as $row) $settings[(string) $row['input_key']] = (string) $row['input_value'];
        $palettes = self::palettes();
        $preset = strtolower(trim((string) ($settings['UI_THEME_PRESET'] ?? 'olive')));
        $settings['UI_THEME_PRESET'] = isset($palettes[$preset]) ? $preset : 'olive';
        foreach (['UI_PRIMARY_COLOR', 'UI_ACCENT_COLOR'] as $key) {
            $settings[$key] = $this->hex((string) ($settings[$key] ?? '')) ?? self::DEFAULTS[$key];
        }
        foreach ($settings as $key => $value) {
            if (preg_match('/^UI_REPORT_CELL_COLOR_[A-Z][A-Z0-9_]*$/', (string) $key) === 1) $settings[$key] = $this->hex((string) $value) ?? '';
        }
        return $settings;
    }

    public function theme(?array $settings = null): array
    {
        $settings ??= $this->all();
        $palettes = self::palettes();
        $preset = (string) ($settings['UI_THEME_PRESET'] ?? 'olive');
        $palette = $palettes[$preset] ?? $palettes['olive'];
        $primary = $preset === 'custom' ? ($this->hex((string) ($settings['UI_PRIMARY_COLOR'] ?? '')) ?? $palettes['olive']['primary']) : $palette['primary'];
        $accent = $preset === 'custom' ? ($this->hex((string) ($settings['UI_ACCENT_COLOR'] ?? '')) ?? $palettes['olive']['accent']) : $palette['accent'];
        $sidebar = $this->shade($primary, -24);
        $sidebarForeground = $this->contrast($sidebar);
        return [
            'primary' => $primary,
            'primary_dark' => $sidebar,
            'accent' => $accent,
            'sidebar_foreground' => $sidebarForeground,
            'sidebar_muted' => $this->alpha($sidebarForeground, 0.66),
            'accent_foreground' => $this->contrast($accent),
        ];
    }

    public function save(array $ui, ?array $logoFile, array $groups): array
    {
        $preset = strtolower(trim((string) ($ui['theme_preset'] ?? 'olive')));
        $errors = isset(self::palettes()[$preset]) ? [] : ['theme_preset' => 'Select a valid theme palette.'];
        $primary = $this->hex((string) ($ui['primary_color'] ?? ''));
        $accent = $this->hex((string) ($ui['accent_color'] ?? ''));
        $appTitle = trim((string) ($ui['app_title'] ?? ''));
        if (mb_strlen($appTitle) < 2 || mb_strlen($appTitle) > 60) $errors['app_title'] = 'Sidebar title must be between 2 and 60 characters.';
        if ($preset === 'custom' && $primary === null) $errors['primary_color'] = 'Enter a valid six-digit primary color.';
        if ($preset === 'custom' && $accent === null) $errors['accent_color'] = 'Enter a valid six-digit accent color.';

        $colors = [];
        $posted = is_array($ui['report_cell_colors'] ?? null) ? $ui['report_cell_colors'] : [];
        foreach ($groups as $group) {
            if (preg_match('/^[A-Z][A-Z0-9_]*$/', (string) $group) !== 1) continue;
            $raw = trim((string) ($posted[$group] ?? ''));
            $color = $raw === '' ? '' : $this->hex($raw);
            if ($raw !== '' && $color === null) $errors['report_cell_colors.' . $group] = 'Enter a valid six-digit color.';
            $colors[$group] = $color ?? '';
        }

        $upload = $this->validateLogo($logoFile);
        if (isset($upload['error'])) $errors['logo'] = $upload['error'];
        if ($errors !== []) return $errors;

        $current = $this->all();
        $logoPath = (string) ($current['UI_LOGO_PATH'] ?? '');
        if (!empty($upload['tmp_name'])) {
            $directory = dirname(__DIR__, 2) . '/public/uploads/branding';
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) return ['logo' => 'Unable to create the branding upload directory.'];
            $filename = 'system-logo-' . bin2hex(random_bytes(8)) . '.' . $upload['extension'];
            if (!move_uploaded_file($upload['tmp_name'], $directory . '/' . $filename)) return ['logo' => 'Unable to save the uploaded logo.'];
            $logoPath = 'uploads/branding/' . $filename;
        } elseif (!empty($ui['reset_logo'])) {
            $logoPath = '';
        }

        $this->put('UI_THEME_PRESET', 'Theme Preset', $preset);
        $this->put('UI_PRIMARY_COLOR', 'Custom Primary Color', $primary ?? self::DEFAULTS['UI_PRIMARY_COLOR']);
        $this->put('UI_ACCENT_COLOR', 'Custom Accent Color', $accent ?? self::DEFAULTS['UI_ACCENT_COLOR']);
        $this->put('UI_LOGO_PATH', 'System Logo Path', $logoPath);
        $this->put('UI_APP_TITLE', 'Sidebar Title', $appTitle);
        foreach ($colors as $group => $color) $this->put('UI_REPORT_CELL_COLOR_' . $group, ucwords(strtolower(str_replace('_', ' ', $group))) . ' Report Cell Color', $color);
        $this->removeOldLogo((string) ($current['UI_LOGO_PATH'] ?? ''), $logoPath);
        return [];
    }

    private function put(string $key, string $label, string $value): void
    {
        $stmt = $this->db->prepare('INSERT INTO system_inputs (input_key, input_label, input_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE input_label = VALUES(input_label), input_value = VALUES(input_value), updated_at = CURRENT_TIMESTAMP');
        $stmt->execute([$key, $label, $value]);
    }

    private function hex(string $value): ?string
    {
        $value = strtoupper(trim($value));
        return preg_match('/^#[0-9A-F]{6}$/', $value) === 1 ? $value : null;
    }

    private function shade(string $hex, int $amount): string
    {
        $hex = ltrim($hex, '#');
        $parts = [];
        for ($index = 0; $index < 3; $index++) $parts[] = max(0, min(255, hexdec(substr($hex, $index * 2, 2)) + $amount));
        return sprintf('#%02X%02X%02X', $parts[0], $parts[1], $parts[2]);
    }

    private function contrast(string $hex): string
    {
        $hex = ltrim($hex, '#');
        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));
        return ((0.299 * $red) + (0.587 * $green) + (0.114 * $blue)) >= 150 ? '#1B2021' : '#FFFFFF';
    }

    private function alpha(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');
        return sprintf('rgba(%d, %d, %d, %.2F)', hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), $alpha);
    }
    private function validateLogo(?array $file): array
    {
        if ($file === null || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [];
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return ['error' => 'The logo upload did not complete successfully.'];
        if ((int) ($file['size'] ?? 0) > 2097152) return ['error' => 'The logo must be 2 MB or smaller.'];
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) return ['error' => 'The uploaded logo could not be verified.'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime]) || @getimagesize($tmp) === false) return ['error' => 'Upload a valid PNG, JPEG, or WebP image.'];
        return ['tmp_name' => $tmp, 'extension' => $extensions[$mime]];
    }

    private function removeOldLogo(string $oldPath, string $newPath): void
    {
        if ($oldPath === '' || $oldPath === $newPath) return;
        $root = realpath(dirname(__DIR__, 2) . '/public/uploads/branding');
        $old = realpath(dirname(__DIR__, 2) . '/public/' . ltrim(str_replace('\\', '/', $oldPath), '/'));
        if ($root !== false && $old !== false && str_starts_with($old, $root . DIRECTORY_SEPARATOR)) unlink($old);
    }
}