<?php
require __DIR__ . '/../app/includes/password.php';

$dbFile = __DIR__ . '/../db/users.db';
if (!is_dir(dirname($dbFile))) mkdir(dirname($dbFile), 0755, true);

$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL
)");

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($username === '' || $password === '') {
        http_response_code(400);
        echo "username and password required";
        exit;
    }

    $hash = hash_password($password);
    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, datetime("now"))');
    try {
        $stmt->execute([$username, $hash]);
        echo "registered";
    } catch (PDOException $e) {
        http_response_code(400);
        echo "error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES);
    }
    exit;
}
?>
<!doctype html>
<meta charset="utf-8">
<title>Register</title>
<form method="post">
  <label>Username <input name="username" required></label><br>
  <label>Password <input name="password" type="password" required></label><br>
  <button type="submit">Register</button>
</form>
