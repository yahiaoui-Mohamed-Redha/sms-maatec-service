<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';

use Infobip\Configuration;
use Infobip\Api\SmsApi;
use Infobip\Model\SmsDestination;
use Infobip\Model\SmsTextualMessage;
use Infobip\Model\SmsAdvancedTextualRequest;

function build_message(string $customerName, string $insuranceNumber, string $endDate): string {
    $dateFmt = (new DateTimeImmutable($endDate))->format('Y-m-d');
    return "Hello {$customerName}, your insurance {$insuranceNumber} will expire on {$dateFmt}. Please renew soon.";
}

function send_sms_via_infobip(PDO $pdo, ?int $policyId, string $phone, string $message): array {
    $baseUrl = trim((string)($_ENV['INFOBIP_BASE_URL'] ?? ''));
    $apiKey = trim((string)($_ENV['INFOBIP_API_KEY'] ?? ''));
    if ($baseUrl === '' || $apiKey === '') {
        $error = 'Infobip credentials are not configured';
        log_sms($pdo, $policyId, $phone, $message, 'error', null, $error);
        return ['ok' => false, 'error' => $error];
    }

    $configuration = new Configuration(host: $baseUrl, apiKey: $apiKey);
    $api = new SmsApi(config: $configuration);

    $destination = new SmsDestination(to: $phone);
    $smsMessage = new SmsTextualMessage(destinations: [$destination], text: $message);
    $request = new SmsAdvancedTextualRequest(messages: [$smsMessage]);

    try {
        $response = $api->sendSmsMessage($request);
        $providerId = method_exists($response, 'getBulkId') ? (string)$response->getBulkId() : null;
        log_sms($pdo, $policyId, $phone, $message, 'sent', $providerId, null);
        return ['ok' => true, 'provider_id' => $providerId];
    } catch (Throwable $e) {
        $error = $e->getMessage();
        log_sms($pdo, $policyId, $phone, $message, 'error', null, $error);
        return ['ok' => false, 'error' => $error];
    }
}