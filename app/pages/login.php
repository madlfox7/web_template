<?php
// Login page: authenticate user by username or email and password.
// Uses db() from includes/functions.php

$errors = [];
$success = null;
// Determine where to redirect after login
$return = (string)($_POST['return'] ?? $_GET['return'] ?? '');
if ($return === '') {
  // fallback to referer path if same host and relative, else home
  $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
  if ($ref !== '') {
    $urlPath = parse_url($ref, PHP_URL_PATH);
    $urlQuery = parse_url($ref, PHP_URL_QUERY);
    $ret = $urlPath ?: '/';
    if ($urlQuery) $ret .= '?' . $urlQuery;
    $return = $ret;
  }
}
// sanitize: only allow same-site relative paths
if ($return === '' || $return[0] !== '/') $return = '/?page=home';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ident = trim((string)($_POST['ident'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($ident === '') $errors[] = 'Username or email is required.';
    if ($password === '') $errors[] = 'Password is required.';

    if (empty($errors)) {
        $pdo = db();
        if (!$pdo) {
            $errors[] = 'Database connection failed.';
        } else {
            // find by username or email
            $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([$ident, $ident]);
            $row = $stmt->fetch();
            if (!$row) {
                $errors[] = 'Invalid credentials.';
            } else {
                if (password_verify($password, $row['password_hash'])) {
                    // success
                    $_SESSION['user_id'] = (int)$row['id'];
                    $success = 'Login successful.';
                } else {
                    $errors[] = 'Invalid credentials.';
                }
            }
        }
    }
}

?>

<div class="card">
  <h2>Login</h2>

  <?php if ($success): ?>
    <div class="flash" style="border-color:#86efac;background:#ecfdf5;color:#064e3b"><?= htmlspecialchars($success) ?></div>
    <p><a class="btn" href="<?= htmlspecialchars($return) ?>">Continue</a></p>
    <script>
      // client-side redirect because headers already sent by layout
      setTimeout(function(){ window.location.href = <?= json_encode($return) ?>; }, 400);
    </script>
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
        <div>Username or Email</div>
        <input class="input" type="text" name="ident" value="<?= isset($ident) ? htmlspecialchars($ident) : '' ?>" required>
      </label>

      <label>
        <div>Password</div>
        <input class="input" type="password" name="password" required>
      </label>

      <input type="hidden" name="return" value="<?= htmlspecialchars($return) ?>">

      <div>
        <button class="btn" type="submit">Login</button>
        <a class="btn secondary" href="/?page=register&return=<?= urlencode($return) ?>">Register</a>
        <a class="btn secondary" href="/?page=forgot&return=<?= urlencode($return) ?>">Forgot password?</a>
      </div>
    </form>

  <?php endif; ?>
</div>
