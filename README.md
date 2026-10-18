# Barangay Treasury System (BTS)

A robust, native PHP web application engineered for Barangay Treasury operations, managing end-to-end procurement workflows, disbursement vouchers, financial report generation, and official transaction certifications.

---

## 📋 Overview

The **Barangay Treasury System (BTS)** streamlines and automates local government unit (LGU) barangay treasury processes in accordance with standard Philippine barangay financial and procurement regulations. Built on a clean, custom MVC architecture, it coordinates transactions from initial requisition through bidding, canvassing, purchasing, disbursement, and archive certification.

---

## ✨ Key Features

### 🔄 Multi-Stage Financial & Procurement Workflows
- **Purchase Request Workflow (Full Procurement Pipeline)**:
  1. **Purchase Request (PR)**: Itemized supply/service requisition with estimated costs.
  2. **BAC Resolution**: Bids and Awards Committee approval and resolution series tracking.
  3. **Request for Quotation (RFQ)**: Multi-supplier canvassing and price proposal collation.
  4. **Abstract of Canvass (AOC)**: Comparative price analysis and supplier award determination.
  5. **Purchase Order (PO)**: Formal order generation with delivery terms and payment conditions.
  6. **Disbursement Voucher (DV)**: Automated voucher calculation, tax deductions (VAT/expanded tax), and net amount due.
  7. **Obligation Request and Status (ORS)**: Fund allotment and appropriation certification.
  8. **Inspection and Acceptance Report (IAR)**: Delivery verification and inspection sign-off.
  9. **Requisition and Issue Slip (RIS)**: Stock issuance to requesting barangay offices.
- **Direct Disbursement Voucher Workflow**:
  - Accelerated pipeline for non-procurement or direct expenditures (DV → ORS → IAR → RIS).

### 📄 Automated Report & Document Engine
- **Excel & PDF Generation**: Generates official formatted documents from templates (`storage/templates/Procurement_Report_Templates.xlsx`) using **PhpSpreadsheet** and automated **LibreOffice CLI** conversion for print-ready PDFs.
- **Post-Audit / PBC Certification**: Generates official Completed Transaction Certifications with bank account details, check number series, payees, and amounts.
- **Tax & Deduction Calculation**: Built-in computation for standard government withholding taxes.

### ⚙️ System Input & Parameters Management
- Centralized management of official barangay metadata:
  - Barangay Name, City, Province, and contact information.
  - Signatories: Punong Barangay, Barangay Treasurer, BAC Chairperson/Members/Secretary, Barangay Bookkeeper.
  - Number series trackers for PR, BAC, PO, and DV documents.
  - Bank details (Bank Name, Account Number, Check Number ranges).

### 🗄️ Archives & Audit Trail
- Historical transaction repository with search, status filtering, and re-export capabilities.
- Prevents tampering with completed processes while allowing workflow progression.
- Deleted report processes move to Trash and can be restored for 30 days; expired items are permanently removed during authenticated app activity.

### 🔐 Login Security
- Apply `database/migrations/20261018_add_login_attempt_lockouts.sql` once to enable escalating login lockouts (3, 6, 12 minutes, up to 24 hours).

### 🗑️ Trash Retention
- Apply `database/migrations/20261018_add_trash_retention.sql` once to add the deletion timestamp and backfill existing deleted reports.
- The app checks for expired reports at least hourly during authenticated use and whenever Archived Reports or Trash is opened.
- `php tools/purge_trash.php` can also be scheduled with Windows Task Scheduler for cleanup while the app is idle.

---

## 🛠️ Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | PHP 8.2+ |
| **Architecture** | Custom Native MVC (Clean PSR-4 Autoloading) |
| **Database** | MySQL / MariaDB |
| **Dependencies** | Composer, PhpOffice/PhpSpreadsheet |
| **Document Processing** | PhpSpreadsheet, LibreOffice (Headless CLI PDF conversion) |
| **Frontend** | Vanilla HTML5, CSS3, JavaScript |
| **Server Environment** | Apache (XAMPP on Windows) |

---

## 📁 Directory Structure

```text
BTS/
├── app/
│   ├── Controllers/          # Request handlers (Workflow, Auth, Archives, System Inputs)
│   ├── Core/                 # Core framework (Router, Controller, Model, Report Generators)
│   ├── Models/               # Data access and business logic
│   └── Views/                # Layouts and view templates
├── config/                   # Configuration files (app.php, database.php)
├── database/                 # Database migrations / schema scripts
├── public/                   # Web root (index.php, static CSS/JS/images)
│   └── assets/               # Stylesheets, scripts, brand logos
├── routes/                   # URL routing declarations (routes/web.php)
├── storage/
│   ├── cache/                # Application cache
│   ├── generated_reports/    # Output PDFs and spreadsheets (runtime)
│   └── templates/            # Protected Excel master templates
├── tests/                    # Integration and smoke test scripts
└── tools/                    # Technical specifications, UI rules, and developer notes
```

---

## 🚀 Getting Started

### Prerequisites
- **PHP** >= 8.2 (Required extensions: `pdo_mysql`, `gd`, `zip`, `xml`, `mbstring`)
- **MySQL** / **MariaDB** (via XAMPP)
- **Composer** (v2.x)
- **LibreOffice** (optional, required for automatic Excel-to-PDF conversion)

### Installation

1. **Clone the repository** to your web server root (e.g. `C:\xampp\htdocs\BTS`):
   ```bash
   git clone https://github.com/escall-dev/BTS.git
   cd BTS
   ```

2. **Install Composer dependencies**:
   ```bash
   composer install
   ```

3. **Database Configuration**:
   - Verify connection settings in `config/database.php`:
     ```php
     return [
         'host' => '127.0.0.1',
         'database' => 'brgy_treasury_system',
         'username' => 'root',
         'password' => '',
         'charset' => 'utf8mb4',
     ];
     ```

4. **Web Server Setup**:
   - Ensure the Apache rewrite module (`mod_rewrite`) is enabled.
   - Point your browser to `http://localhost/BTS/`.

---

## 🔑 Default Credentials

| Field | Value |
|---|---|
| **Username** | `treasurer` |
| **Password** | `admin123` |
| **Role** | Treasurer |

---

## 🧪 Testing & Verification

Run the included smoke tests to verify report generation and calculation logic:

```bash
# Test Disbursement Voucher tax calculation and PDF output
php tests/disbursement_voucher_tax_smoke.php

# Test Completed Transaction (PBC) certification generation
php tests/pbc_certification_smoke.php

# Test Purchase Order generation
php tests/purchase_order_generation_smoke.php

# Test Request for Quotation (RFQ) generation
php tests/rfq_generation_smoke.php
```

---

## 📄 License

Proprietary — Developed for Barangay Treasury Operations.
