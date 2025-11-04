<?php
require __DIR__ . '/../app/includes/password.php';

$dbFile = __DIR__ . '/../db/users.db';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(401);
        echo "invalid credentials";
        exit;
    }

    if (verify_password($password, $user['password_hash'])) {
        // rehash if algorithm or options changed
        if (needs_rehash($user['password_hash'])) {
            $newHash = hash_password($password);
            $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $upd->execute([$newHash, $user['id']]);
        }
        echo "login ok";
    } else {
        http_response_code(401);
        echo "invalid credentials";
    }
    exit;
}
?>
<!doctype html>
<meta charset="utf-8">
<title>Login</title>
<form method="post">
  <label>Username <input name="username" required></label><br>
  <label>Password <input name="password" type="password" required></label><br>
  <button type="submit">Login</button>
</form>
