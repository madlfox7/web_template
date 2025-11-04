<?php
// Registration page: show form and handle POST to create a new user.
// Relies on app/includes/functions.php for db() and session.

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');

    if ($username === '') $errors[] = 'Username is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if ($password === '' || strlen($password) < 6) $errors[] = 'Password is required (min 6 chars).';
    if ($password !== $password2) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $pdo = db();
        if (!$pdo) {
            $errors[] = 'Database connection failed.';
        } else {
            // check for existing username/email
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $errors[] = 'Username or email already in use.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $ins = $pdo->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)');
                    $ins->execute([$username, $email, $hash, 'user']);
                    $id = (int)$pdo->lastInsertId();
                    // log in user by id
                    $_SESSION['user_id'] = $id;
                    $success = 'Registration successful — you are now logged in.';
                } catch (Exception $e) {
                    $errors[] = 'Failed to create user: ' . $e->getMessage();
                }
            }
        }
    }
}

?>

<div class="card">
  <h2>Register</h2>

  <?php if ($success): ?>
    <div class="flash" style="border-color:#86efac;background:#ecfdf5;color:#064e3b"><?= htmlspecialchars($success) ?></div>
    <p><a class="btn" href="/?page=home">Go to home</a></p>
  <?php else: ?>

    <?php if (!empty($errors)): ?>
      <div class="flash" style="border-color:#fca5a5;background:#fff1f2;color:#7f1d1d">
        <ul style="margin:0;padding-left:1.2rem">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" class="row" style="max-width:520px">
      <label>
        <div>Username</div>
        <input class="input" type="text" name="username" value="<?= isset($username) ? htmlspecialchars($username) : '' ?>" required>
      </label>

      <label>
        <div>Email</div>
        <input class="input" type="email" name="email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" required>
      </label>

      <label>
        <div>Password</div>
        <input class="input" type="password" name="password" required>
      </label>

      <label>
        <div>Confirm password</div>
        <input class="input" type="password" name="password2" required>
      </label>

      <div>
        <button class="btn" type="submit">Register</button>
        <a class="btn secondary" href="/?page=login">Have an account? Login</a>
      </div>
    </form>

  <?php endif; ?>
</div>
