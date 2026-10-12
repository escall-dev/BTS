<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\ReportCellColorStyler;
use App\Core\TemplateMapping;
use App\Models\SystemInput;
use App\Models\UiSettings;

class SystemInputController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();
        $this->renderPage([], [], ($_GET['tab'] ?? '') === 'system-ui' ? 'system-ui' : 'report-data', (string) ($_GET['saved'] ?? ''));
    }

    public function save(): void
    {
        $this->requireLogin();
        $section = ($_POST['settings_section'] ?? '') === 'system-ui' ? 'system-ui' : 'report-data';
        if ($section === 'system-ui') {
            $uiModel = new UiSettings();
            $groups = ReportCellColorStyler::discoverGroups(TemplateMapping::templatePath());
            $ui = is_array($_POST['ui'] ?? null) ? $_POST['ui'] : [];
            $errors = $uiModel->save($ui, $_FILES['ui_logo'] ?? null, $groups);
            if ($errors !== []) {
                $this->renderPage($errors, $ui, 'system-ui', '');
                return;
            }
            $this->redirect('/BTS/system-input?tab=system-ui&saved=ui');
        }

        $model = new SystemInput();
        $posted = is_array($_POST['inputs'] ?? null) ? $_POST['inputs'] : [];
        $postedTaxTypes = is_array($_POST['tax_types'] ?? null) ? $_POST['tax_types'] : [];
        $errors = $model->validateTaxTypes($postedTaxTypes);
        if ($errors === []) {
            $errors = $model->saveMany($posted);
        }
        if ($errors !== []) {
            $this->view('system_input/index', $this->pageData($model->allWithPostedValues($posted), $errors, [], 'report-data', '', $postedTaxTypes));
            return;
        }
        $model->saveTaxTypes($postedTaxTypes);
        $this->redirect('/BTS/system-input?tab=report-data&saved=report');
    }

    private function renderPage(array $errors, array $postedUi, string $tab, string $saved): void
    {
        $uiModel = new UiSettings();
        $ui = $uiModel->all();
        if ($postedUi !== []) {
            $ui['UI_THEME_PRESET'] = (string) ($postedUi['theme_preset'] ?? $ui['UI_THEME_PRESET']);
            $ui['UI_PRIMARY_COLOR'] = (string) ($postedUi['primary_color'] ?? $ui['UI_PRIMARY_COLOR']);
            $ui['UI_ACCENT_COLOR'] = (string) ($postedUi['accent_color'] ?? $ui['UI_ACCENT_COLOR']);
            $ui['UI_APP_TITLE'] = (string) ($postedUi['app_title'] ?? $ui['UI_APP_TITLE']);
            foreach ((array) ($postedUi['report_cell_colors'] ?? []) as $group => $color) {
                $ui['UI_REPORT_CELL_COLOR_' . $group] = (string) $color;
            }
        }
        $this->view('system_input/index', $this->pageData((new SystemInput())->all(), $errors, $ui, $tab, $saved));
    }

    private function pageData(array $inputs, array $errors, array $ui, string $tab, string $saved, ?array $taxTypes = null): array
    {
        $colorGroups = $tab === 'system-ui'
            ? ReportCellColorStyler::discoverGroups(TemplateMapping::templatePath())
            : [];

        return [
            'title' => 'System Input',
            'active' => 'system-input',
            'inputs' => $inputs,
            'errors' => $errors,
            'uiSettings' => $ui ?: (new UiSettings())->all(),
            'palettes' => UiSettings::palettes(),
            'colorGroups' => $colorGroups,
            'activeTab' => $tab,
            'savedSection' => $saved,
            'taxTypes' => $taxTypes ?? array_map(static fn (string $name): array => ['name' => $name], (new SystemInput())->taxTypes()),
        ];
    }
}
