<?php

require dirname(__DIR__) . '/app/Core/CompletedTransactionCertificationGenerator.php';

use App\Core\CompletedTransactionCertificationGenerator;

$count = max(1, (int) ($argv[1] ?? 6));
$rows = [];
for ($index = 1; $index <= $count; $index++) {
    $rows[] = [
        'check_no' => (string) (717872 + $index),
        'completed_at' => '2026-07-18 17:30:00',
        'payee' => 'Sample Payee ' . $index,
        'amount' => (string) (1000 * $index),
        'purpose' => 'Fallback voucher particulars ' . $index,
        'pr_purpose' => 'Sample purchase purpose ' . $index,
    ];
}

$settings = [
    'PBC_BANK_ACCOUNT' => '2482-1002-12',
    'PBC_BANK_NAME' => 'Landbank of the Philippines',
    'PBC_BANK_ADDRESS' => 'San Pedro City',
    'BRGY' => 'Sta. Nina',
    'PUNONG_BARANGAY' => 'HON. SAMPLE PUNONG BARANGAY',
    'BARANGAY_TREASURER' => 'SAMPLE TREASURER',
];

$result = (new CompletedTransactionCertificationGenerator())->generate(
    $rows,
    $settings,
    '2026-07-TEST-' . $count,
    '2026-07-01'
);

if (!is_file($result['pdf']) || filesize($result['pdf']) === 0) {
    throw new RuntimeException('PDF smoke output was not created.');
}
if (glob(dirname($result['pdf']) . '/PBC_2026-07-TEST-' . $count . '*.xlsx') !== []) {
    throw new RuntimeException('Temporary Excel output was not removed.');
}

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
