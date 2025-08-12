<?php

declare(strict_types=1);

use Dotenv\Dotenv;

// Composer autoload
require_once __DIR__ . '/../vendor/autoload.php';

// Base paths
const BASE_PATH = __DIR__ . '/..';
$storagePath = BASE_PATH . '/storage';
if (!is_dir($storagePath)) {
    mkdir($storagePath, 0775, true);
}

// Load environment
if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
}

// Session
$sessionName = $_ENV['SESSION_NAME'] ?? 'insure_session';
session_name($sessionName);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'cookie_samesite' => 'Lax',
    ]);
}

// CSRF helpers
function get_csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function verify_csrf_token(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['_csrf'] ?? '';
        if (!hash_equals($_SESSION['_csrf'] ?? '', $token)) {
            http_response_code(400);
            exit('Invalid CSRF token');
        }
    }
}

// HTML escape helper
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Database (SQLite)
$dbPath = $_ENV['DB_PATH'] ?? (BASE_PATH . '/storage/app.db');
$pdo = new PDO('sqlite:' . $dbPath, options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Initialize schema
$pdo->exec('CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    agency_code TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS policies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    insurance_number TEXT NOT NULL,
    customer_name TEXT NOT NULL,
    phone TEXT NOT NULL,
    start_date TEXT NOT NULL,
    end_date TEXT NOT NULL,
    notified INTEGER NOT NULL DEFAULT 0,
    notified_at TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(insurance_number, end_date)
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS sms_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    policy_id INTEGER NULL,
    phone TEXT NOT NULL,
    message TEXT NOT NULL,
    provider_message_id TEXT NULL,
    status TEXT NOT NULL,
    error TEXT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY(policy_id) REFERENCES policies(id)
)');

// Seed default user if not exists
$agencyCode = trim((string)($_ENV['AGENCY_CODE'] ?? ''));
$existingUser = null;
if ($agencyCode !== '') {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE agency_code = :code');
    $stmt->execute([':code' => $agencyCode]);
    $existingUser = $stmt->fetch();
}

if (!$existingUser && $agencyCode !== '') {
    $passwordHash = trim((string)($_ENV['AGENCY_PASSWORD_HASH'] ?? ''));
    $plain = trim((string)($_ENV['AGENCY_PASSWORD'] ?? ''));
    if ($passwordHash === '' && $plain !== '') {
        $passwordHash = password_hash($plain, PASSWORD_DEFAULT);
    }
    if ($passwordHash === '') {
        // Generate a random password if none provided
        $generated = bin2hex(random_bytes(8));
        $passwordHash = password_hash($generated, PASSWORD_DEFAULT);
        error_log('Generated temporary admin password: ' . $generated);
    }
    $stmt = $pdo->prepare('INSERT INTO users (agency_code, password_hash, created_at) VALUES (:code, :hash, :ts)');
    $stmt->execute([
        ':code' => $agencyCode,
        ':hash' => $passwordHash,
        ':ts' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
    ]);
}

// Auth helpers
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}