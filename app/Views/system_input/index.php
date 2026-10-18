<?php ob_start(); ?>
<section class="page-header">
    <h1>System Input</h1>
    <p>Manage report data and the visual identity of the system.</p>
</section>
<?php
$errors = $errors ?? [];
$inputs = $inputs ?? [];
$uiSettings = $uiSettings ?? [];
$palettes = $palettes ?? [];
$colorGroups = $colorGroups ?? [];
$taxTypes = $taxTypes ?? [];
$activeTab = $activeTab ?? 'report-data';
$inputMap = [];
foreach ($inputs as $input) {
    $key = (string) ($input['input_key'] ?? '');
    if ($key !== '') $inputMap[$key] = $input;
}
$groups = [
    ['anchor' => 'barangay-information', 'title' => 'Barangay Information', 'description' => 'Basic report header details used across procurement documents.', 'keys' => ['BRGY', 'CITY', 'PROVINCE', 'TELNO']],
    ['anchor' => 'officials-signatories', 'title' => 'Officials and Signatories', 'description' => 'Names printed in approval, accounting, and certification signature areas.', 'keys' => ['PUNONG_BARANGAY', 'BARANGAY_TREASURER', 'AUTHINSPECT', 'APPROPRIATION_CHAIRPERSON', 'BARANGAY_BOOKKEEPER', 'CITY_ACCOUNTANT']],
    ['anchor' => 'bac-aoc-people', 'title' => 'BAC / AOC People', 'description' => 'Committee members and canvass chairperson used in BAC and canvass reports.', 'keys' => ['BACMEMBER1', 'BACMEMBER2', 'BACMEMBER3', 'BACSECRETARY', 'BACVICECHAIRMAN', 'AOCCHAIRMAN']],
    ['anchor' => 'report-number-series', 'title' => 'Report Number Series', 'description' => 'Starting values for auto-generated report numbers.', 'keys' => ['PR_NUMBER_SERIES', 'BAC_RESOLUTION_SERIES', 'PO_NUMBER_SERIES', 'DV_NUMBER_SERIES', 'OR_NUMBER_SERIES', 'RIS_NUMBER_SERIES', 'IAR_NUMBER_SERIES', 'PBC_NUMBER_SERIES']],
    ['anchor' => 'transaction-certification', 'title' => 'Completed Transaction Certification', 'description' => 'Bank details printed on the Punong Barangay Certification.', 'keys' => ['PBC_BANK_ACCOUNT', 'PBC_BANK_NAME', 'PBC_BANK_ADDRESS']],
    ['anchor' => 'disbursement-check-range', 'title' => 'Disbursement Check Range', 'description' => 'Available check numbers for Disbursement Voucher assignment.', 'keys' => ['CHECKNO_START', 'CHECKNO_END']],
    ['anchor' => 'report-print-settings', 'title' => 'Report Print Settings', 'description' => 'Choose the paper used by all newly generated reports. Reports keep readable text by fitting to one page wide.', 'keys' => ['REPORT_PAPER_SIZE']],
];
$numberKeys = ['PR_NUMBER_SERIES','BAC_RESOLUTION_SERIES','PO_NUMBER_SERIES','DV_NUMBER_SERIES','OR_NUMBER_SERIES','RIS_NUMBER_SERIES','IAR_NUMBER_SERIES','PBC_NUMBER_SERIES'];
$renderField = static function (string $key) use ($inputMap, $errors, $numberKeys): void {
    $input = $inputMap[$key] ?? ['input_label' => $key, 'input_value' => ''];
    $label = (string) ($input['input_label'] ?? $key);
    $value = (string) ($input['input_value'] ?? '');
    $type = $key === 'TELNO' ? 'tel' : (in_array($key, ['CHECKNO_START','CHECKNO_END'], true) ? 'number' : 'text');
    $helper = in_array($key, $numberKeys, true) ? 'Format: YYYY-MM-###' : '';
    ?>
    <label class="system-input-field">
        <span><?= htmlspecialchars($label) ?></span>
        <input type="hidden" name="inputs[<?= htmlspecialchars($key) ?>][label]" value="<?= htmlspecialchars($label) ?>">
        <?php if ($key === 'REPORT_PAPER_SIZE'): ?>
            <select name="inputs[<?= htmlspecialchars($key) ?>][value]" required>
                <option value="A4" <?= $value === 'A4' ? 'selected' : '' ?>>A4 — 210 × 297 mm</option>
                <option value="LETTER" <?= $value === 'LETTER' ? 'selected' : '' ?>>Short / Letter — 8.5 × 11 in</option>
                <option value="LEGAL" <?= $value === 'LEGAL' ? 'selected' : '' ?>>Legal — 8.5 × 14 in</option>
            </select>
        <?php else: ?>
            <input type="<?= $type ?>" name="inputs[<?= htmlspecialchars($key) ?>][value]" value="<?= htmlspecialchars($value) ?>" <?= $type === 'number' ? 'min="0" step="1" inputmode="numeric"' : '' ?>>
        <?php endif; ?>
        <?php if ($helper): ?><small class="system-input-helper"><?= $helper ?></small><?php endif; ?>
        <?php if (!empty($errors[$key])): ?><small class="field-error"><?= htmlspecialchars($errors[$key]) ?></small><?php endif; ?>
    </label>
    <?php
};
$preset = (string) ($uiSettings['UI_THEME_PRESET'] ?? 'olive');
$primary = (string) ($uiSettings['UI_PRIMARY_COLOR'] ?? '#51513D');
$accent = (string) ($uiSettings['UI_ACCENT_COLOR'] ?? '#A6A867');
$logoPath = (string) ($uiSettings['UI_LOGO_PATH'] ?? '');
$sidebarTitle = (string) ($uiSettings['UI_APP_TITLE'] ?? 'BrgyTreasurySystem');
?>
<?php if (($savedSection ?? '') !== ''): ?>
    <p class="notice success"><?= $savedSection === 'ui' ? 'System appearance saved.' : 'Report data saved.' ?></p>
<?php endif; ?>
<?php if ($errors !== []): ?><p class="notice error">Please correct the highlighted settings.</p><?php endif; ?>

<nav class="settings-tabs" aria-label="System Input sections">
    <a class="<?= $activeTab === 'report-data' ? 'is-active' : '' ?>" href="/BTS/system-input?tab=report-data" aria-current="<?= $activeTab === 'report-data' ? 'page' : 'false' ?>">Report Data</a>
    <a class="<?= $activeTab === 'system-ui' ? 'is-active' : '' ?>" href="/BTS/system-input?tab=system-ui" aria-current="<?= $activeTab === 'system-ui' ? 'page' : 'false' ?>">System UI</a>
</nav>

<?php if ($activeTab === 'report-data'): ?>
<nav class="settings-section-nav" aria-label="Report Data sections">
    <?php foreach ($groups as $group): ?><a href="#report-section-<?= htmlspecialchars($group['anchor']) ?>"><?= htmlspecialchars($group['title']) ?></a><?php endforeach; ?>
    <a href="#report-section-tax-management">Tax Management</a>
</nav>
<form class="table-panel system-input-form" method="post" action="/BTS/system-input" data-system-input-form>
    <input type="hidden" name="settings_section" value="report-data">
    <div class="system-input-sections">
        <?php foreach ($groups as $group): ?>
            <section class="system-input-section" id="report-section-<?= htmlspecialchars($group['anchor']) ?>">
                <div class="system-input-section-heading"><h2><?= htmlspecialchars($group['title']) ?></h2><p><?= htmlspecialchars($group['description']) ?></p></div>
                <div class="system-input-grid <?= count($group['keys']) === 2 ? 'system-input-grid-compact' : '' ?>">
                    <?php foreach ($group['keys'] as $key) $renderField($key); ?>
                </div>
            </section>
        <?php endforeach; ?>
        <section class="system-input-section tax-management-section" id="report-section-tax-management" data-tax-management>
            <div class="system-input-section-heading">
                <h2>Tax Management</h2>
                <p>Manage the tax type names available in the Disbursement Voucher. Deduction amounts are entered in the voucher.</p>
            </div>
            <div class="tax-type-list" data-tax-type-list>
                <?php foreach ($taxTypes as $index => $taxType): ?>
                    <div class="tax-type-row" data-tax-type-row>
                        <label>
                            <span>Tax Type Name</span>
                            <input type="text" name="tax_types[<?= (int) $index ?>][name]" value="<?= htmlspecialchars((string) ($taxType['name'] ?? '')) ?>" maxlength="80" required>
                            <?php if (!empty($errors['tax_types.' . $index . '.name'])): ?><small class="field-error"><?= htmlspecialchars($errors['tax_types.' . $index . '.name']) ?></small><?php endif; ?>
                        </label>
                        <button type="button" class="secondary compact" data-remove-tax-type>Delete</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="settings-empty-state tax-types-empty-state" data-tax-types-empty <?= $taxTypes !== [] ? 'hidden' : '' ?>>
                <strong>No tax types configured.</strong><span>Disbursement Vouchers will offer only None until a tax type is added.</span>
            </div>
            <button type="button" class="secondary" data-add-tax-type>Add Tax Type</button>
        </section>
    </div>
    <div class="form-actions system-input-actions">
        <p class="system-input-save-status" data-save-status role="status" aria-live="polite">No unsaved changes</p>
        <button type="submit" data-save-button>Save Report Data</button>
    </div>
</form>
<?php else: ?>
<nav class="settings-section-nav" aria-label="System UI sections">
    <a href="#system-ui-section-theme">Theme Palette</a>
    <a href="#system-ui-section-report-colors">Generated Report Cell Colors</a>
    <a href="#system-ui-section-logo">System Logo</a>
</nav>
<form class="table-panel system-ui-form" method="post" action="/BTS/system-input" enctype="multipart/form-data" data-ui-settings-form data-system-input-form>
    <input type="hidden" name="settings_section" value="system-ui">
    <section class="system-input-section" id="system-ui-section-theme">
        <div class="system-input-section-heading"><h2>Theme Palette</h2><p>Choose a preset or create a custom primary and accent combination.</p></div>
        <div class="palette-grid">
            <?php foreach ($palettes as $key => $palette): ?>
                <label class="palette-option">
                    <input type="radio" name="ui[theme_preset]" value="<?= htmlspecialchars($key) ?>" <?= $preset === $key ? 'checked' : '' ?> data-theme-preset>
                    <span class="palette-swatches"><i style="--swatch:<?= htmlspecialchars($palette['primary']) ?>"></i><i style="--swatch:<?= htmlspecialchars($palette['accent']) ?>"></i></span>
                    <span class="palette-option-copy"><strong><?= htmlspecialchars($palette['label']) ?></strong><small><?= $key === 'custom' ? 'Choose your colors' : htmlspecialchars($palette['primary'] . ' / ' . $palette['accent']) ?></small></span>
                    <span class="palette-check" aria-hidden="true">&#10003;</span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($errors['theme_preset'])): ?><small class="field-error"><?= htmlspecialchars($errors['theme_preset']) ?></small><?php endif; ?>
        <div class="system-ui-custom-colors" data-custom-colors>
            <div class="custom-colors-heading"><div><strong>Custom colors</strong><small>Used only when the Custom palette is selected.</small></div><span data-custom-status>Custom palette</span></div>
            <div class="system-input-grid">
                <label class="system-input-field color-setting-field"><span>Primary Color</span><span class="color-control"><input type="color" name="ui[primary_color]" value="<?= htmlspecialchars($primary) ?>" data-ui-primary><output data-primary-hex><?= htmlspecialchars(strtoupper($primary)) ?></output></span><?php if (!empty($errors['primary_color'])): ?><small class="field-error"><?= htmlspecialchars($errors['primary_color']) ?></small><?php endif; ?></label>
                <label class="system-input-field color-setting-field"><span>Accent Color</span><span class="color-control"><input type="color" name="ui[accent_color]" value="<?= htmlspecialchars($accent) ?>" data-ui-accent><output data-accent-hex><?= htmlspecialchars(strtoupper($accent)) ?></output></span><?php if (!empty($errors['accent_color'])): ?><small class="field-error"><?= htmlspecialchars($errors['accent_color']) ?></small><?php endif; ?></label>
            </div>
        </div>
        <div class="theme-preview" data-theme-preview style="--preview-primary:<?= htmlspecialchars($primary) ?>;--preview-accent:<?= htmlspecialchars($accent) ?>">
            <div class="theme-preview-heading"><div><strong>Live preview</strong><small>See how the palette feels across the system.</small></div><span>Preview only</span></div>
            <div class="theme-preview-window">
                <aside><span class="theme-preview-brand"><i></i>BTS</span><span class="is-active">Dashboard</span><span>Reports</span><span>Archive</span></aside>
                <div class="theme-preview-content"><span class="theme-preview-bar"></span><div><strong>Page content</strong><small>Cards, actions, and active states</small><button type="button">Primary action</button></div></div>
            </div>
        </div>
    </section>

    <section class="system-input-section report-cell-colors-section" id="system-ui-section-report-colors">
        <div class="system-input-section-heading"><h2>Generated Report Cell Colors</h2><p>Choose the background used by each marker group in Procurement_Report_Templates.xlsx. Text contrast is adjusted automatically.</p></div>
        <?php if ($colorGroups === []): ?>
            <div class="settings-empty-state"><strong>No color markers found.</strong><span>Add a marker such as <code>&#36;{CELL_COLOR:ITEM_HEADER}</code> to a template cell.</span></div>
        <?php else: ?>
            <div class="report-color-grid">
                <?php foreach ($colorGroups as $group): $key = 'UI_REPORT_CELL_COLOR_' . $group; $value = (string) ($uiSettings[$key] ?? ''); $color = $value !== '' ? $value : '#F7F5EA'; ?>
                    <label class="report-color-card">
                        <span class="report-color-card-heading"><strong><?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $group)))) ?></strong><small>Report cell background</small></span>
                        <span class="report-color-control"><input type="color" name="ui[report_cell_colors][<?= htmlspecialchars($group) ?>]" value="<?= htmlspecialchars($color) ?>" data-report-color><output data-report-color-hex><?= htmlspecialchars(strtoupper($color)) ?></output></span>
                        <code>&#36;{CELL_COLOR:<?= htmlspecialchars($group) ?>}</code>
                        <?php if (!empty($errors['report_cell_colors.' . $group])): ?><small class="field-error"><?= htmlspecialchars($errors['report_cell_colors.' . $group]) ?></small><?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <section class="system-input-section" id="system-ui-section-logo">
        <div class="system-input-section-heading"><h2>System Logo</h2><p>PNG, JPEG, or WebP. Maximum file size: 2 MB.</p></div>
        <div class="logo-setting-row">
            <div class="logo-preview">
                <?php if ($logoPath !== ''): ?><img src="/BTS/<?= htmlspecialchars($logoPath) ?>" alt="Current system logo"><?php else: ?><span class="brand-mark" aria-hidden="true"><span></span><span></span></span><?php endif; ?>
            </div>
            <label>Upload New Logo<input type="file" name="ui_logo" accept="image/png,image/jpeg,image/webp"></label>
            <label class="sidebar-title-setting">Sidebar Title<input type="text" name="ui[app_title]" value="<?= htmlspecialchars($sidebarTitle) ?>" minlength="2" maxlength="60" required><small>Displayed below the logo in the sidebar.</small><?php if (!empty($errors['app_title'])): ?><small class="field-error"><?= htmlspecialchars($errors['app_title']) ?></small><?php endif; ?></label>
            <?php if ($logoPath !== ''): ?><label class="check-option"><input type="checkbox" name="ui[reset_logo]" value="1"> Restore default logo</label><?php endif; ?>
        </div>
        <?php if (!empty($errors['logo'])): ?><small class="field-error"><?= htmlspecialchars($errors['logo']) ?></small><?php endif; ?>
    </section>

    <div class="form-actions system-input-actions">
        <p class="system-input-save-status" data-save-status role="status" aria-live="polite">No unsaved changes</p>
        <button type="submit" data-save-button>Save System UI</button>
    </div>
</form>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../layout/main.php'; ?>
