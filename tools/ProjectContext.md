# BrgyTreasurySystem - AI Context & Baseline

## Purpose
This document is the baseline context for the `BrgyTreasurySystem` project in `C:\xampp\htdocs\BTS`. The older `FinanceSystem` project is only a structural reference; this project uses a new title, live database, and treasury-focused workflow.

## Project Overview
`BrgyTreasurySystem` is a PHP-based barangay treasury web application built on a custom MVC architecture. The current version implements the workflow shell for report generation plus purchase-side Excel template mapping for the first four procurement reports.

## Tech Stack
- Backend: PHP 8.2+
- Architecture: Custom native MVC
- Database: MySQL/MariaDB through local XAMPP/phpMyAdmin
- Dependency management: Composer
- Frontend: HTML, CSS, JavaScript in `public/assets/`
- Document generation: PhpSpreadsheet is reserved for Excel/report workflows

## Directory Structure
- `app/Core/` contains the router, base controller, base model helpers, and template mapping.
- `app/Controllers/` contains request handlers.
- `app/Models/` contains database access and workflow logic.
- `app/Views/` contains templates and the main sidebar layout.
- `config/` contains application and database configuration.
- `public/` is the web root and contains public assets only.
- `routes/` contains URL-to-controller mappings.
- `storage/templates/` contains protected source Excel templates.
- `storage/generated_reports/` is reserved for generated documents.
- `tools/` is reserved for local maintenance scripts.

## Current Baseline
- App title: `BrgyTreasurySystem`
- Base URL path: `/BTS`
- Live database name: `brgy_treasury_system`
- Default user: `treasurer` / `admin123`
- No local schema file is kept in `database/`; schema changes are applied directly to local MySQL/phpMyAdmin.
- Protected template file: `storage/templates/Procurement_Report_Templates.xlsx`

## Implemented Features
- Login/logout and authenticated dashboard.
- Sidebar navigation: Dashboard, Report Generation, Archived Reports, System Input.
- Dashboard and Report Generation start buttons for Purchase Request and Disbursement Voucher.
- Purchase Request workflow: PR, BAC Reports, RFQ, Abstract of Canvass, Purchase Order, DV, ORS, IAR, RIS.
- Direct Disbursement Voucher workflow: DV, ORS, IAR, RIS.
- Save/Print/Next/Finish gating with placeholder previews.
- Saved reports can be reopened for correction; future reports stay locked.
- Finish archives the workflow process.
- System Input placeholder storage with default reusable report fields.

## Purchase-Side Template Mapping
Implemented in `App\Core\TemplateMapping` for these sheets only:
- `purchase-request` -> `Purchase Request`
- `bac-reports` -> `BAC Resolution`
- `request-for-quotation` -> `Request for Quotation`
- `abstract-of-canvass` -> `Abstract of Canvass`

Placeholder rules:
- Standard placeholders use `${FIELD}`.
- BAC Resolution also supports `{FIELD}` for current `{BACRNUM}` and `{BACMONTH}` cells.
- Purchase Request values can be inherited by later steps, especially PR item data used by RFQ. RFQ supplier proposals feed the Abstract of Canvass recommended supplier selection.
- Reusable general values are stored in `system_inputs`.
- Transaction-specific values are stored in `report_step_data`.

## Live Database Tables
- `users`
- `report_processes`
- `report_steps`
- `report_step_data`
- `archives`
- `system_inputs`

## Development Guidelines
- Follow the custom MVC structure; do not introduce Laravel/Symfony conventions.
- Add every new endpoint to `routes/web.php`.
- Keep source templates under `storage/templates/`, not `public/`.
- Keep generated reports under `storage/generated_reports/`.
- Use Composer autoloading under the `App\` namespace, with the current fallback autoloader supporting the scaffold before `vendor/` exists.
- Voucher-side templates are incomplete and intentionally not mapped yet.