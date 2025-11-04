<?php
require_once __DIR__.'/../includes/functions.php';
$u = current_user();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>MiniShop+Forum</title>
  <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
<header class="topbar">
  <div class="brand">MiniShop+Forum</div>
  <nav>
    <a href="/?page=home">Home</a>
    <a href="/?page=forum">Forum</a>
    <a href="/?page=shop">Shop</a>
    <a href="/?page=cart">Cart<?php if (!empty($_SESSION['cart'])) { $cnt = cart_count(); echo $cnt ? ' ('.$cnt.')' : ''; } ?></a>
    <?php if ($u): ?>
      <span class="welcome">Hello, <?= htmlspecialchars($u['username']) ?></span>
      <?php if (is_admin()): ?><a href="/?page=admin">Admin</a><?php endif; ?>
      <a href="/?page=logout">Logout</a>
    <?php else: ?>
      <?php $return = urlencode($_SERVER['REQUEST_URI'] ?? '/'); ?>
      <a href="/?page=login&return=<?= $return ?>">Login</a>
      <a href="/?page=register&return=<?= $return ?>">Register</a>
    <?php endif; ?>
  </nav>
</header>

<main class="container">
  <?php
    // как и раньше — фронт-контроллер подгружает страницы
    $page = $_GET['page'] ?? 'home';
    $file = __DIR__.'/../pages/'.basename($page).'.php';
    if (is_file($file)) require $file;
    else { http_response_code(404); echo '<h1>404</h1><p>Page not found.</p>'; }
  ?>
</main>

<footer class="footer">© <?= date('Y') ?> MiniShop+Forum</footer>
<script src="/js/app.js"></script>
</body>
</html>
