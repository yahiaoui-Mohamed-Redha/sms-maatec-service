<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function import_policies_from_excel(PDO $pdo, string $filePath): array {
    $ignoreYear = (int)($_ENV['IGNORE_POLICIES_BEFORE_YEAR'] ?? 2021);

    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    $headers = [];
    $rowIndex = 0;
    $inserted = 0;
    $skipped = 0;
    $errors = [];

    foreach ($sheet->getRowIterator() as $row) {
        $rowIndex++;
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        $cells = [];
        foreach ($cellIterator as $cell) {
            $cells[] = trim((string)$cell->getValue());
        }
        if ($rowIndex === 1) {
            $headers = array_map(static fn($h) => strtolower(trim((string)$h)), $cells);
            continue;
        }
        if (!$headers) { break; }
        $rowAssoc = [];
        foreach ($headers as $i => $key) {
            $rowAssoc[$key] = $cells[$i] ?? '';
        }

        // Map expected columns
        $insurance = $rowAssoc['insurance number'] ?? $rowAssoc['insurance_number'] ?? $rowAssoc['number'] ?? '';
        $name = $rowAssoc['customer name'] ?? $rowAssoc['name'] ?? '';
        $phone = $rowAssoc['customer phone number'] ?? $rowAssoc['phone'] ?? '';
        $startDate = $rowAssoc['start date'] ?? $rowAssoc['start'] ?? '';
        $endDate = $rowAssoc['end date'] ?? $rowAssoc['end'] ?? '';

        $start = parse_date($startDate);
        $end = parse_date($endDate);

        if ($insurance === '' || $name === '' || $phone === '' || !$start || !$end) {
            $skipped++;
            $errors[] = "Row {$rowIndex}: missing or invalid fields";
            continue;
        }
        $endYear = (int)substr($end, 0, 4);
        if ($endYear < $ignoreYear) {
            $skipped++;
            continue;
        }
        try {
            upsert_policy($pdo, [
                'insurance_number' => $insurance,
                'customer_name' => $name,
                'phone' => $phone,
                'start_date' => $start,
                'end_date' => $end,
                'notified' => 0,
            ]);
            $inserted++;
        } catch (Throwable $e) {
            $skipped++;
            $errors[] = "Row {$rowIndex}: " . $e->getMessage();
        }
    }

    return compact('inserted', 'skipped', 'errors');
}