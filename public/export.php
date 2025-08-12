<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_login();

$policies = fetch_policies($pdo, []);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="policies.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Insurance Number', 'Customer Name', 'Phone', 'Start Date', 'End Date', 'Notified', 'Notified At']);
foreach ($policies as $p) {
    fputcsv($out, [
        $p['insurance_number'],
        $p['customer_name'],
        $p['phone'],
        $p['start_date'],
        $p['end_date'],
        $p['notified'],
        $p['notified_at'],
    ]);
}
fclose($out);
exit;