<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/sms.php';

$windowDays = (int)($_ENV['EXPIRY_WINDOW_DAYS'] ?? 10);
$duePolicies = find_due_policies($pdo, $windowDays);

$total = 0;
$sent = 0;
$errors = 0;

foreach ($duePolicies as $policy) {
    $total++;
    $msg = build_message($policy['customer_name'], $policy['insurance_number'], $policy['end_date']);
    $res = send_sms_via_infobip($pdo, (int)$policy['id'], $policy['phone'], $msg);
    if ($res['ok']) {
        mark_notified($pdo, (int)$policy['id']);
        $sent++;
    } else {
        $errors++;
    }
}

echo sprintf("Checked %d policies, sent %d, errors %d\n", $total, $sent, $errors);