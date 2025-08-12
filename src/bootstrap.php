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

// Database (MySQL)
$dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
$dbPort = (int)($_ENV['DB_PORT'] ?? 3306);
$dbName = $_ENV['DB_DATABASE'] ?? 'insurance_sms';
$dbUser = $_ENV['DB_USERNAME'] ?? 'root';
$dbPass = $_ENV['DB_PASSWORD'] ?? '';
$dbCharset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $dbHost, $dbPort, $dbName, $dbCharset);
$pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Initialize schema (idempotent)
$pdo->exec('CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agency_code VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

$pdo->exec('CREATE TABLE IF NOT EXISTS policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    insurance_number VARCHAR(191) NOT NULL,
    customer_name VARCHAR(191) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    notified TINYINT(1) NOT NULL DEFAULT 0,
    notified_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY unique_policy_cycle (insurance_number, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

$pdo->exec('CREATE TABLE IF NOT EXISTS sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    policy_id INT NULL,
    phone VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    provider_message_id VARCHAR(191) NULL,
    status VARCHAR(50) NOT NULL,
    error TEXT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_sms_policy FOREIGN KEY (policy_id) REFERENCES policies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

// Seed default user if not exists (from env)
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
        $generated = bin2hex(random_bytes(8));
        $passwordHash = password_hash($generated, PASSWORD_DEFAULT);
        error_log('Generated temporary admin password: ' . $generated);
    }
    $stmt = $pdo->prepare('INSERT INTO users (agency_code, password_hash, created_at) VALUES (:code, :hash, :ts)');
    $stmt->execute([
        ':code' => $agencyCode,
        ':hash' => $passwordHash,
        ':ts' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
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