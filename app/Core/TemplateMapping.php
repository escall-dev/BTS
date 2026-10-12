<?php

namespace App\Core;

class TemplateMapping
{
    private const TEMPLATE_FILE = 'Procurement_Report_Templates.xlsx';

    private const SYSTEM_FIELDS = [
        'BRGY' => 'Barangay Name',
        'CITY' => 'City',
        'PROVINCE' => 'Province',
        'TELNO' => 'Telephone Number',
        'BARANGAY_TREASURER' => 'Barangay Treasurer',
                'AUTHINSPECT' => 'Authorized Inspector',
'PUNONG_BARANGAY' => 'Punong Barangay',
        'BACMEMBER1' => 'BAC Member 1',
        'BACMEMBER2' => 'BAC Member 2',
        'BACMEMBER3' => 'BAC Member 3',
        'BACSECRETARY' => 'BAC Secretary',
        'BACVICECHAIRMAN' => 'BAC Vice Chairman',
        'AOCCHAIRMAN' => 'AOC Chairman',
        'PR_NUMBER_SERIES' => 'P.R. Number Series',
        'BAC_RESOLUTION_SERIES' => 'BAC Resolution Number Series',
        'PO_NUMBER_SERIES' => 'P.O. Number Series',
        'DV_NUMBER_SERIES' => 'D.V. Number Series',
        'OR_NUMBER_SERIES' => 'O.R. Number Series',
        'RIS_NUMBER_SERIES' => 'RIS Number Series',
        'IAR_NUMBER_SERIES' => 'IAR Number Series',
        'PBC_NUMBER_SERIES' => 'PBC Number Series',
        'PBC_BANK_ACCOUNT' => 'PBC Bank Account Number',
        'PBC_BANK_NAME' => 'PBC Bank Name',
        'PBC_BANK_ADDRESS' => 'PBC Bank Address',
        'CHECKNO_START' => 'Check No. Start',
        'CHECKNO_END' => 'Check No. End',
        'APPROPRIATION_CHAIRPERSON' => 'Appropriation Chairperson',
        'BARANGAY_BOOKKEEPER' => 'Barangay Bookkeeper',
        'CITY_ACCOUNTANT' => 'City/Municipal Accountant',
        'REPORT_PAPER_SIZE' => 'Default Paper Size',
    ];

    private const MAPPINGS = [
        'purchase-request' => [
            'sheet' => 'Purchase Request',
            'transaction_fields' => [
                'PRNUM' => 'P.R. Number',
                'DatePR' => 'P.R. Date',
                'INPR' => 'Item Number',
                'PRQTY' => 'Quantity',
                'UOE' => 'Unit of Measurement',
                'PRPART' => 'Particulars',
                'EUC' => 'Estimated Unit Cost',
                'PRPURPOSE' => 'Purpose',
            ],
            'system_fields' => ['BARANGAY_TREASURER', 'PUNONG_BARANGAY'],
            'formulas' => ['H9', 'H10', 'H11'],
        ],
        'bac-reports' => [
            'sheet' => 'BAC Resolution',
            'transaction_fields' => [
                'PROJECTITLE' => 'Project Title',
                'BACRNUM' => 'BAC Resolution Number',
                'BACDAY' => 'BAC Day',
                'BACMONTH' => 'BAC Month',
                'BACYEAR' => 'BAC Year',
                'BACPLACE' => 'BAC Place',
            ],
            'inherited_fields' => ['PRNUM'],
            'system_fields' => ['BRGY', 'CITY', 'PROVINCE', 'TELNO', 'BARANGAY_TREASURER', 'BACMEMBER1', 'BACMEMBER2', 'BACMEMBER3', 'BACSECRETARY', 'BACVICECHAIRMAN'],
            'supports_brace_placeholders' => true,
        ],
        'abstract-of-canvass' => [
            'sheet' => 'Abstract of Canvass',
            'transaction_fields' => [
                'SUPPNAME' => 'Supplier Name',
                'AOP' => 'Amount of Proposal',
                'SUPPAOP' => 'Supplier Amounts of Proposal',
                'LBSUPPNAME' => 'Lowest Bid Supplier Name',
                'LOWESTBIDAMOUNT' => 'Lowest Bid Amount',
                'LOWBIDSUPPNAME' => 'Lowest Bid Supplier Name',
                'LOWBIDSUPPAOP' => 'Lowest Bid Amount',
                'LOWBIDAOP' => 'Lowest Bid Amount',
            ],
            'system_fields' => ['BRGY', 'CITY', 'PROVINCE', 'TELNO', 'BACMEMBER1', 'BACMEMBER2', 'BACMEMBER3', 'BACSECRETARY', 'BACVICECHAIRMAN', 'AOCCHAIRMAN'],
        ],
        'request-for-quotation' => [
            'sheet' => 'Request for Quotation',
            'transaction_fields' => [
                'SUPPNAME' => 'Supplier Name',
                'SUPPADD' => 'Supplier Address',
            ],
            'inherited_fields' => ['PRQTY', 'UOE', 'PRPART'],
            'system_fields' => ['BRGY', 'CITY', 'PROVINCE', 'BARANGAY_TREASURER'],
            'formulas' => ['H23:H34'],
        ],
        'purchase-order' => [
            'sheet' => 'Purchase Order',
            'transaction_fields' => [
                'PONUM' => 'P.O. Number',
                'PODATE' => 'P.O. Date',
                'PAYEE' => 'Supplier',
                'PAYEE_ADDRESS' => 'Address',
                'PODOD' => 'Date of Delivery',
                'POPOD' => 'Place of Delivery',
                'PODT' => 'Delivery Term',
                'POPT' => 'Payment Term',
                'PO_MODE_OF_PAYMENT' => 'Mode of Payment',
            ],
            'inherited_fields' => ['UOE', 'PRPART', 'PRQTY'],
            'system_fields' => ['BRGY', 'CITY', 'PROVINCE', 'PUNONG_BARANGAY', 'AOCCHAIRMAN'],
        ],
        'disbursement-voucher' => [
            'sheet' => 'Disbursement Voucher',
            'transaction_fields' => [
                'DIVNO' => 'D.V. Number',
                'DATET' => 'Date',
                'PAYEE' => 'Payee',
                'DDFUND' => 'Fund',
                'PAYEE_ADDRESS' => 'Address',
                'TIN' => 'TIN',
                'PARTICULARS' => 'Particulars',
                'GROSS' => 'Gross Amount',
                'DV_TAX_TYPE' => 'Tax Type',
                'WHTAX' => 'Withholding Tax',
                'NETAMOUNT' => 'Net Amount',
                'CHECKNO' => 'Check No.',
                'ORNO' => 'O.R. Number',
            ],
            'system_fields' => ['BRGY', 'CITY', 'PROVINCE', 'APPROPRIATION_CHAIRPERSON', 'BARANGAY_TREASURER', 'PUNONG_BARANGAY', 'BARANGAY_BOOKKEEPER', 'CITY_ACCOUNTANT'],
        ],
        'obligation-report' => [
            'sheet' => 'Obligation Report',
            'transaction_fields' => [
                'OBLIDATE' => 'Obligation Date',
                'SPECFUND' => 'Specific Fund',
            ],
            'inherited_fields' => ['PAYEE', 'PAYEE_ADDRESS', 'DDFUND', 'NETAMOUNT', 'ORNO', 'PARTICULARS'],
            'system_fields' => ['BRGY', 'CITY', 'PROVINCE', 'PUNONG_BARANGAY', 'AOCCHAIRMAN'],
        ],
        'requisition-issue-slip' => [
            'sheet' => 'Requisition & Issue Slip',
            'transaction_fields' => [
                'RAINO' => 'RIS No.',
                'RAIDATE' => 'RIS Date',
                'PURPOSE' => 'Purpose',
            ],
            'inherited_fields' => ['UOE', 'PRPART', 'PRQTY'],
            'system_fields' => ['BRGY', 'CITY', 'PROVINCE'],
        ],
        'inspection-acceptance-report' => [
            'sheet' => 'Inspection & Acceptance',
            'transaction_fields' => [
                'IAS' => 'IAR No.',
                'IASDATE' => 'IAR Date',
            ],
            'inherited_fields' => ['UOE', 'PRPART', 'PRQTY', 'RAINO', 'RAIDATE', 'PAYEE'],
            'system_fields' => ['BRGY', 'CITY', 'PROVINCE', 'AUTHINSPECT', 'BARANGAY_TREASURER'],
        ],
    ];

    public static function templatePath(): string
    {
        return dirname(__DIR__, 2) . '/storage/templates/' . self::TEMPLATE_FILE;
    }

    public static function has(string $step): bool
    {
        return isset(self::MAPPINGS[$step]);
    }

    public static function get(string $step): ?array
    {
        return self::MAPPINGS[$step] ?? null;
    }

    public static function sheetFor(string $step): ?string
    {
        return self::MAPPINGS[$step]['sheet'] ?? null;
    }

    public static function transactionFields(string $step): array
    {
        return self::MAPPINGS[$step]['transaction_fields'] ?? [];
    }

    public static function inheritedFields(string $step): array
    {
        return self::MAPPINGS[$step]['inherited_fields'] ?? [];
    }

    public static function systemFields(string $step): array
    {
        return self::MAPPINGS[$step]['system_fields'] ?? [];
    }

    public static function systemFieldDefinitions(): array
    {
        return self::SYSTEM_FIELDS;
    }

    public static function supportsBracePlaceholders(string $step): bool
    {
        return (bool) (self::MAPPINGS[$step]['supports_brace_placeholders'] ?? false);
    }

    public static function replacePlaceholders(string $text, array $values, bool $supportBraceSyntax = false): string
    {
        foreach ($values as $key => $value) {
            $replacement = (string) $value;
            $text = str_replace('${' . $key . '}', $replacement, $text);

            if ($supportBraceSyntax) {
                $text = str_replace('{' . $key . '}', $replacement, $text);
            }
        }

        return $text;
    }
}


