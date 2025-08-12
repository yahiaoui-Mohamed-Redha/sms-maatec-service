<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function normalize_phone(string $raw): string {
    $digits = preg_replace('/[^0-9+]/', '', $raw);
    return ltrim($digits);
}

function parse_date(string $dateStr): ?string {
    $dateStr = trim($dateStr);
    if ($dateStr === '') return null;
    try {
        $ts = strtotime($dateStr);
        if ($ts === false) return null;
        return (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d');
    } catch (Throwable $e) {
        return null;
    }
}

function upsert_policy(PDO $pdo, array $data): void {
    $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare('INSERT INTO policies (insurance_number, customer_name, phone, start_date, end_date, notified, created_at, updated_at)
        VALUES (:insurance_number, :customer_name, :phone, :start_date, :end_date, :notified, :created_at, :updated_at)
        ON DUPLICATE KEY UPDATE
            customer_name = VALUES(customer_name),
            phone = VALUES(phone),
            start_date = VALUES(start_date),
            updated_at = VALUES(updated_at)');
    $stmt->execute([
        ':insurance_number' => $data['insurance_number'],
        ':customer_name' => $data['customer_name'],
        ':phone' => normalize_phone($data['phone']),
        ':start_date' => $data['start_date'],
        ':end_date' => $data['end_date'],
        ':notified' => (int)($data['notified'] ?? 0),
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
}

function fetch_policies(PDO $pdo, array $filters = []): array {
    $conditions = [];
    $params = [];

    if (!empty($filters['q'])) {
        $conditions[] = '(insurance_number LIKE :q OR customer_name LIKE :q OR phone LIKE :q)';
        $params[':q'] = '%' . $filters['q'] . '%';
    }
    if (isset($filters['status'])) {
        if ($filters['status'] === 'notified') {
            $conditions[] = 'notified = 1';
        } elseif ($filters['status'] === 'pending') {
            $conditions[] = 'notified = 0';
        }
    }
    if (isset($filters['expiring'])) {
        $days = (int)$filters['expiring'];
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $until = (new DateTimeImmutable('+' . $days . ' days'))->format('Y-m-d');
        $conditions[] = 'DATE(end_date) BETWEEN :from AND :to';
        $params[':from'] = $today;
        $params[':to'] = $until;
    }

    $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
    $sql = 'SELECT * FROM policies ' . $where . ' ORDER BY DATE(end_date) ASC, id DESC LIMIT 1000';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function find_due_policies(PDO $pdo, int $windowDays): array {
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $until = (new DateTimeImmutable('+' . $windowDays . ' days'))->format('Y-m-d');
    $stmt = $pdo->prepare('SELECT * FROM policies WHERE notified = 0 AND DATE(end_date) BETWEEN :from AND :to ORDER BY DATE(end_date) ASC');
    $stmt->execute([':from' => $today, ':to' => $until]);
    return $stmt->fetchAll();
}

function mark_notified(PDO $pdo, int $policyId): void {
    $stmt = $pdo->prepare('UPDATE policies SET notified = 1, notified_at = :ts, updated_at = :ts WHERE id = :id');
    $stmt->execute([':ts' => (new DateTimeImmutable())->format('Y-m-d H:i:s'), ':id' => $policyId]);
}

function log_sms(PDO $pdo, ?int $policyId, string $phone, string $message, string $status, ?string $providerMessageId = null, ?string $error = null): void {
    $stmt = $pdo->prepare('INSERT INTO sms_logs (policy_id, phone, message, status, provider_message_id, error, created_at)
        VALUES (:policy_id, :phone, :message, :status, :provider_message_id, :error, :created_at)');
    $stmt->execute([
        ':policy_id' => $policyId,
        ':phone' => $phone,
        ':message' => $message,
        ':status' => $status,
        ':provider_message_id' => $providerMessageId,
        ':error' => $error,
        ':created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
    ]);
}