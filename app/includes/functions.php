<?php
// Minimal helper functions: DB connection, current user, role check
declare(strict_types=1);
session_start();

function env(string $k, $default = null) {
    $v = getenv($k);
    return $v === false ? $default : $v;
}

function db(): ?PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = env('DB_HOST', '127.0.0.1');
    $name = env('DB_NAME', 'app');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');

    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        $pdo = null;
    }
    return $pdo;
}

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $pdo = db();
    if (!$pdo) return null;
    $stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function is_admin(): bool {
    $u = current_user();
    return $u && ($u['role'] ?? '') === 'admin';
}

// CSRF helpers
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function check_csrf(?string $token): bool {
    return !empty($token) && hash_equals($_SESSION['csrf'] ?? '', $token);
}

// Cart helpers (session-based)
function cart_count(): int {
    $c = 0;
    $cart = $_SESSION['cart'] ?? [];
    foreach ($cart as $qid) {
        $c += max(0, (int)$qid);
    }
    return $c;
}
