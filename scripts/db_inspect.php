<?php
// Inspect users table and verify stored values look like password hashes (Argon2/bcrypt)
try {
    $dbFile = __DIR__ . '/../db/users.db';
    if (!file_exists($dbFile)) {
        echo "DB not found at {$dbFile}. No users yet.\n";
        exit;
    }
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $rows = $pdo->query('SELECT id, username, password_hash, created_at FROM users LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        echo "No users found.\n";
        exit;
    }
    foreach ($rows as $r) {
        $id = $r['id'];
        $user = $r['username'];
        $hash = $r['password_hash'];
        $created = $r['created_at'];

        // detect algorithm by hash format
        $alg = 'unknown';
        if (strpos($hash, '$argon2') === 0) $alg = 'argon2';
        elseif (strpos($hash, '$2y$') === 0 || strpos($hash, '$2b$') === 0 || strpos($hash, '$2a$') === 0) $alg = 'bcrypt';
        else $alg = 'php-default';

        $looks_like_hash = (strlen($hash) > 20) ? 'yes' : 'no';

        echo sprintf("%s | id=%s user=%s created=%s alg=%s len=%d looks_like_hash=%s\n", date('c'), $id, $user, $created, $alg, strlen($hash), $looks_like_hash);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
