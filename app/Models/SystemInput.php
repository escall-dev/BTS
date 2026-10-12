<?php

namespace App\Models;

use App\Core\Model;
use App\Core\TemplateMapping;

class SystemInput extends Model
{
    public const DV_TAX_TYPES_KEY = 'DV_TAX_TYPES';

    public function all(): array
    {
        $this->ensureDefaults();
        $stmt = $this->db->query('SELECT * FROM system_inputs ORDER BY input_label');

        return $stmt->fetchAll();
    }

    public function saveMany(array $inputs): array
    {
        $errors = $this->validateCheckNumberRange($inputs);
        $paperSize = $this->postedValueFor($inputs, 'REPORT_PAPER_SIZE', 'Default Paper Size');
        if (!in_array($paperSize, ['A4', 'LETTER', 'LEGAL'], true)) {
            $errors['REPORT_PAPER_SIZE'] = 'Select A4, Short/Letter, or Legal.';
        }
        if ($errors !== []) {
            return $errors;
        }

        foreach ($inputs as $key => $data) {
            $label = trim((string) ($data['label'] ?? ''));
            $value = trim((string) ($data['value'] ?? ''));

            if (($key === 'PR_NUMBER_SERIES' || strcasecmp($label, 'P.R. Number Series') === 0) && $value !== '') {
                $value = $this->normalizePurchaseRequestSeries($value);
            }
            if (($key === 'BAC_RESOLUTION_SERIES' || strcasecmp($label, 'BAC Resolution Number Series') === 0) && $value !== '') {
                $value = $this->normalizePurchaseRequestSeries($value);
            }
            if (($key === 'PO_NUMBER_SERIES' || strcasecmp($label, 'P.O. Number Series') === 0) && $value !== '') {
                $value = $this->normalizePurchaseRequestSeries($value);
            }
            if (($key === 'DV_NUMBER_SERIES' || strcasecmp($label, 'D.V. Number Series') === 0) && $value !== '') {
                $value = $this->normalizePurchaseRequestSeries($value);
            }
            if (($key === 'OR_NUMBER_SERIES' || strcasecmp($label, 'O.R. Number Series') === 0) && $value !== '') {
                $value = $this->normalizePurchaseRequestSeries($value);
            }
            if (($key === 'RIS_NUMBER_SERIES' || strcasecmp($label, 'RIS Number Series') === 0) && $value !== '') {
                $value = $this->normalizePurchaseRequestSeries($value);
            }
            if (($key === 'IAR_NUMBER_SERIES' || strcasecmp($label, 'IAR Number Series') === 0) && $value !== '') {
                $value = $this->normalizePurchaseRequestSeries($value);
            }
            if (($key === 'PBC_NUMBER_SERIES' || strcasecmp($label, 'PBC Number Series') === 0) && $value !== '') {
                $value = $this->normalizePurchaseRequestSeries($value);
            }

            if ($label === '') {
                continue;
            }

            $normalizedKey = ($key !== '' && $key !== 'new')
                ? $key
                : strtoupper(trim(preg_replace('/[^a-z0-9]+/i', '_', $label), '_'));

            if (strcasecmp($label, 'P.R. Number Series') === 0) {
                $normalizedKey = 'PR_NUMBER_SERIES';
            }
            if (strcasecmp($label, 'BAC Resolution Number Series') === 0) {
                $normalizedKey = 'BAC_RESOLUTION_SERIES';
            }
            if (strcasecmp($label, 'P.O. Number Series') === 0) {
                $normalizedKey = 'PO_NUMBER_SERIES';
            }
            if (strcasecmp($label, 'D.V. Number Series') === 0) {
                $normalizedKey = 'DV_NUMBER_SERIES';
            }
            if (strcasecmp($label, 'O.R. Number Series') === 0) {
                $normalizedKey = 'OR_NUMBER_SERIES';
            }
            if (strcasecmp($label, 'RIS Number Series') === 0) {
                $normalizedKey = 'RIS_NUMBER_SERIES';
            }
            if (strcasecmp($label, 'IAR Number Series') === 0) {
                $normalizedKey = 'IAR_NUMBER_SERIES';
            }
            if (strcasecmp($label, 'PBC Number Series') === 0) {
                $normalizedKey = 'PBC_NUMBER_SERIES';
            }
            if (strcasecmp($label, 'Check No. Start') === 0) {
                $normalizedKey = 'CHECKNO_START';
            }
            if (strcasecmp($label, 'Check No. End') === 0) {
                $normalizedKey = 'CHECKNO_END';
            }

            if ($normalizedKey === '') {
                continue;
            }

            $stmt = $this->db->prepare(
                'INSERT INTO system_inputs (input_key, input_label, input_value)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE input_label = VALUES(input_label), input_value = VALUES(input_value), updated_at = CURRENT_TIMESTAMP'
            );
            $stmt->execute([$normalizedKey, $label, $value]);
        }

        return [];
    }

    public function values(): array
    {
        $this->ensureDefaults();
        $stmt = $this->db->query('SELECT input_key, input_value FROM system_inputs');
        $values = [];
        foreach ($stmt->fetchAll() as $row) {
            $values[(string) $row['input_key']] = (string) $row['input_value'];
        }

        return $values;
    }

    public function taxTypes(): array
    {
        $stmt = $this->db->prepare('SELECT input_value FROM system_inputs WHERE input_key = ? LIMIT 1');
        $stmt->execute([self::DV_TAX_TYPES_KEY]);
        $decoded = json_decode((string) ($stmt->fetchColumn() ?: '[]'), true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($name): string => trim((string) $name), $decoded), static fn (string $name): bool => $name !== ''));
    }

    public function validateTaxTypes(array $rows): array
    {
        $errors = [];
        $seen = [];
        foreach ($rows as $index => $row) {
            $name = is_array($row) ? trim((string) ($row['name'] ?? '')) : '';
            if ($name === '') {
                $errors['tax_types.' . $index . '.name'] = 'Tax type name is required.';
                continue;
            }
            if (mb_strlen($name) > 80) {
                $errors['tax_types.' . $index . '.name'] = 'Tax type name must not exceed 80 characters.';
                continue;
            }
            $identity = mb_strtolower($name);
            if (isset($seen[$identity])) {
                $errors['tax_types.' . $index . '.name'] = 'Tax type names must be unique.';
            }
            $seen[$identity] = true;
        }

        return $errors;
    }

    public function saveTaxTypes(array $rows): void
    {
        $names = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }

        $stmt = $this->db->prepare(
            'INSERT INTO system_inputs (input_key, input_label, input_value) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE input_label = VALUES(input_label), input_value = VALUES(input_value), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([self::DV_TAX_TYPES_KEY, 'DV Tax Types', json_encode(array_values($names), JSON_UNESCAPED_UNICODE)]);
    }

    public function reservePbcNumber(string $certificateDate): string
    {
        $monthPrefix = date('Y-m-', strtotime($certificateDate));
        $this->ensureDefaults();
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT input_value FROM system_inputs WHERE input_key = \'PBC_NUMBER_SERIES\' FOR UPDATE');
            $stmt->execute();
            $configured = trim((string) $stmt->fetchColumn());
            $number = 1;
            if (preg_match('/^(\\d{4}-\\d{2})-(\\d+)$/', $configured, $matches) === 1 && $matches[1] . '-' === $monthPrefix) {
                $number = max(1, (int) $matches[2]);
            }

            $reserved = $monthPrefix . sprintf('%03d', $number);
            $next = $monthPrefix . sprintf('%03d', $number + 1);
            $update = $this->db->prepare('UPDATE system_inputs SET input_value = ?, updated_at = CURRENT_TIMESTAMP WHERE input_key = \'PBC_NUMBER_SERIES\'');
            $update->execute([$next]);
            $this->db->commit();

            return $reserved;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function allWithPostedValues(array $postedInputs): array
    {
        $inputs = $this->all();
        foreach ($inputs as &$input) {
            $key = (string) ($input['input_key'] ?? '');
            if (isset($postedInputs[$key]) && is_array($postedInputs[$key])) {
                $input['input_label'] = (string) ($postedInputs[$key]['label'] ?? $input['input_label']);
                $input['input_value'] = (string) ($postedInputs[$key]['value'] ?? $input['input_value']);
            }
        }
        unset($input);

        return $inputs;
    }

    private function validateCheckNumberRange(array $inputs): array
    {
        $start = $this->postedValueFor($inputs, 'CHECKNO_START', 'Check No. Start');
        $end = $this->postedValueFor($inputs, 'CHECKNO_END', 'Check No. End');
        if ($start === '' && $end === '') {
            return [];
        }

        $errors = [];
        if ($start === '' || !ctype_digit($start)) {
            $errors['CHECKNO_START'] = 'Check No. Start must be a whole number.';
        }
        if ($end === '' || !ctype_digit($end)) {
            $errors['CHECKNO_END'] = 'Check No. End must be a whole number.';
        }
        if ($errors === [] && (int) $start > (int) $end) {
            $errors['CHECKNO_END'] = 'Check No. End must be greater than or equal to Check No. Start.';
        }

        return $errors;
    }

    private function postedValueFor(array $inputs, string $key, string $label): string
    {
        foreach ($inputs as $inputKey => $data) {
            if (!is_array($data)) {
                continue;
            }
            $inputLabel = trim((string) ($data['label'] ?? ''));
            if ((string) $inputKey === $key || strcasecmp($inputLabel, $label) === 0) {
                return trim((string) ($data['value'] ?? ''));
            }
        }

        return '';
    }

    private function normalizePurchaseRequestSeries(string $value): string
    {
        $clean = trim($value);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d+)$/', $clean, $matches) === 1) {
            return sprintf('%04d-%02d-%03d', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        if (preg_match('/^(\d{4})[\/-](\d{1,2})[\/-](\d+)$/', $clean, $matches) === 1) {
            return sprintf('%04d-%02d-%03d', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        return $clean;
    }

    private function ensureDefaults(): void
    {
        $definitions = TemplateMapping::systemFieldDefinitions();
        if ($definitions === []) {
            return;
        }

        $rows = [];
        $parameters = [];
        foreach ($definitions as $key => $label) {
            $rows[] = '(?, ?, ?)';
            array_push($parameters, $key, $label, $key === 'REPORT_PAPER_SIZE' ? 'A4' : '');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO system_inputs (input_key, input_label, input_value) VALUES '
            . implode(', ', $rows)
            . ' ON DUPLICATE KEY UPDATE input_label = input_label'
        );
        $stmt->execute($parameters);
    }
}

