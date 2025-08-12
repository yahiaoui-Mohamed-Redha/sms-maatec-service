<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function attempt_login(PDO $pdo, string $agencyCode, string $password): bool {
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE agency_code = :code LIMIT 1');
    $stmt->execute([':code' => $agencyCode]);
    $user = $stmt->fetch();
    if (!$user) {
        return false;
    }
    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['agency_code'] = $agencyCode;
    $_SESSION['last_active'] = time();
    return true;
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}