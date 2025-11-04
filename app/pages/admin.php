<?php
// Admin UI for managing users and forum messages (approve/deny/delete)
// Requires admin role

if (!is_admin()) {
    http_response_code(403);
    echo '<div class="card"><h2>Forbidden</h2><p>Admin access only.</p></div>';
    return;
}

$pdo = db();
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf'] ?? '';
    if (!check_csrf($token)) {
        $flash = 'Invalid CSRF token.';
    } else {
        try {
            if ($action === 'approve_message' && !empty($_POST['id'])) {
                $stmt = $pdo->prepare('UPDATE messages SET status = "approved" WHERE id = ?');
                $stmt->execute([(int)$_POST['id']]);
                $flash = 'Message approved.';
            } elseif ($action === 'deny_message' && !empty($_POST['id'])) {
                $stmt = $pdo->prepare('UPDATE messages SET status = "denied" WHERE id = ?');
                $stmt->execute([(int)$_POST['id']]);
                $flash = 'Message denied.';
            } elseif ($action === 'delete_message' && !empty($_POST['id'])) {
                $stmt = $pdo->prepare('DELETE FROM messages WHERE id = ?');
                $stmt->execute([(int)$_POST['id']]);
                $flash = 'Message deleted.';
            } elseif ($action === 'make_admin' && !empty($_POST['id'])) {
                $stmt = $pdo->prepare('UPDATE users SET role = "admin" WHERE id = ?');
                $stmt->execute([(int)$_POST['id']]);
                $flash = 'User promoted to admin.';
            } elseif ($action === 'revoke_admin' && !empty($_POST['id'])) {
                $uid = (int)$_POST['id'];
                if ($uid === (int)($_SESSION['user_id'] ?? 0)) {
                    $flash = 'Cannot revoke your own admin.';
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET role = "user" WHERE id = ?');
                    $stmt->execute([$uid]);
                    $flash = 'Admin role revoked.';
                }
            } elseif ($action === 'delete_user' && !empty($_POST['id'])) {
                $uid = (int)$_POST['id'];
                if ($uid === (int)($_SESSION['user_id'] ?? 0)) {
                    $flash = 'Cannot delete yourself.';
                } else {
                    $pdo->beginTransaction();
                    $pdo->prepare('DELETE FROM messages WHERE user_id = ?')->execute([$uid]);
                    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
                    $pdo->commit();
                    $flash = 'User and their messages removed.';
                }
            } elseif ($action === 'delete_thread' && !empty($_POST['thread_id'])) {
                $thread_id = (int)$_POST['thread_id'];
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM posts WHERE thread_id = ?')->execute([$thread_id]);
                $pdo->prepare('DELETE FROM threads WHERE id = ?')->execute([$thread_id]);
                $pdo->commit();
                $flash = 'Thread and all its posts deleted.';
            } elseif ($action === 'delete_post' && !empty($_POST['post_id'])) {
                $post_id = (int)$_POST['post_id'];
                $stmt = $pdo->prepare('DELETE FROM posts WHERE id = ?');
                $stmt->execute([$post_id]);
                $flash = 'Post deleted.';
            } elseif ($action === 'hide_post' && !empty($_POST['post_id'])) {
                $post_id = (int)$_POST['post_id'];
                $stmt = $pdo->prepare('UPDATE posts SET hidden = 1 WHERE id = ?');
                $stmt->execute([$post_id]);
                $flash = 'Post hidden.';
            } elseif ($action === 'show_post' && !empty($_POST['post_id'])) {
                $post_id = (int)$_POST['post_id'];
                $stmt = $pdo->prepare('UPDATE posts SET hidden = 0 WHERE id = ?');
                $stmt->execute([$post_id]);
                $flash = 'Post shown.';
            }
        } catch (Throwable $e) {
            $flash = 'Operation failed.';
        }
    }
}

// Fetch metrics
$threads = null;
try {
    $row = $pdo->query("SHOW STATUS LIKE 'Threads_connected'")->fetch();
    $threads = $row['Value'] ?? null;
} catch (Throwable $e) {}

// Fetch messages and users
try {
    $messages = $pdo->query('SELECT m.id, m.user_id, m.content, m.status, m.created_at, u.username FROM messages m LEFT JOIN users u ON u.id = m.user_id ORDER BY m.created_at DESC LIMIT 200')->fetchAll();
} catch (Throwable $e) {
    $messages = [];
}
$users = $pdo->query('SELECT id, username, email, role FROM users ORDER BY id DESC LIMIT 200')->fetchAll();
$token = csrf_token();
?>

<div class="card">
  <h2>Admin Panel</h2>
  <?php if ($threads !== null): ?>
    <p style="margin:.25rem 0;color:#555">DB connections: <strong><?= (int)$threads ?></strong></p>
  <?php endif; ?>

  <?php if ($flash): ?>
    <div class="flash"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <h3>Threads</h3>
  <?php
    try {
      $threads = $pdo->query('SELECT t.id, t.title, t.created_at, u.username FROM threads t LEFT JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC LIMIT 100')->fetchAll();
    } catch (Throwable $e) { $threads = []; }
  ?>
  <?php if (!$threads): ?>
    <p>No threads.</p>
  <?php else: ?>
    <?php foreach ($threads as $t): ?>
      <div style="padding:8px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap">
        <div>
          <strong>#<?= (int)$t['id'] ?></strong>
          <a href="/?page=forum&thread=<?= (int)$t['id'] ?>" style="font-weight:600;text-decoration:none"><?= htmlspecialchars($t['title']) ?></a>
          <small style="opacity:.7">by <?= htmlspecialchars($t['username'] ?? 'anon') ?> • <?= htmlspecialchars($t['created_at']) ?></small>
        </div>
        <form method="post" onsubmit="return confirm('Delete thread and its posts?');">
          <input type="hidden" name="csrf" value="<?= $token ?>">
          <input type="hidden" name="action" value="delete_thread">
          <input type="hidden" name="thread_id" value="<?= (int)$t['id'] ?>">
          <button class="btn secondary" type="submit">Delete thread</button>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <h3 style="margin-top:1rem">Recent posts</h3>
  <?php
    try {
      $posts = $pdo->query('SELECT p.id, p.thread_id, p.content, p.created_at, p.hidden, u.username, t.title FROM posts p LEFT JOIN users u ON u.id = p.user_id LEFT JOIN threads t ON t.id = p.thread_id ORDER BY p.created_at DESC LIMIT 200')->fetchAll();
    } catch (Throwable $e) { $posts = []; }
  ?>
  <?php foreach ($posts as $p): ?>
    <div style="padding:8px;border-bottom:1px solid #eee<?= $p['hidden'] ? ';background:#fff5f5;border-left:3px solid #ef4444' : '' ?>">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap">
        <div>
          <strong><?= htmlspecialchars($p['username'] ?? 'anon') ?></strong>
          <small style="opacity:.7">• <?= htmlspecialchars($p['created_at']) ?></small>
          <span>in <a href="/?page=forum&thread=<?= (int)$p['thread_id'] ?>" style="text-decoration:none"><?= htmlspecialchars($p['title'] ?? ('#'.(int)$p['thread_id'])) ?></a></span>
          <?php if ($p['hidden']): ?>
            <span style="color:#ef4444;font-weight:bold;margin-left:8px">[HIDDEN]</span>
          <?php endif; ?>
        </div>
        <div style="display:flex;gap:4px">
          <?php if ($p['hidden']): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Show this post?');">
              <input type="hidden" name="csrf" value="<?= $token ?>">
              <input type="hidden" name="action" value="show_post">
              <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
              <button class="btn" type="submit" style="font-size:12px;padding:4px 8px">Show</button>
            </form>
          <?php else: ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Hide this post?');">
              <input type="hidden" name="csrf" value="<?= $token ?>">
              <input type="hidden" name="action" value="hide_post">
              <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
              <button class="btn secondary" type="submit" style="font-size:12px;padding:4px 8px">Hide</button>
            </form>
          <?php endif; ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this post permanently?');">
            <input type="hidden" name="csrf" value="<?= $token ?>">
            <input type="hidden" name="action" value="delete_post">
            <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
            <button class="btn secondary" type="submit" style="font-size:12px;padding:4px 8px">Delete</button>
          </form>
        </div>
      </div>
      <p style="margin:6px 0 0<?= $p['hidden'] ? ';opacity:0.6' : '' ?>"><?= nl2br(htmlspecialchars($p['content'])) ?></p>
    </div>
  <?php endforeach; ?>

  <h3 style="margin-top:1rem">Users</h3>
  <?php foreach ($users as $u): ?>
    <div style="padding:8px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap">
      <div>
        <strong><?= htmlspecialchars($u['username']) ?></strong>
        <small style="opacity:.7">(<?= htmlspecialchars($u['email']) ?>)</small>
        — <?= htmlspecialchars($u['role']) ?>
      </div>
      <div>
        <?php if ($u['role'] !== 'admin'): ?>
          <form method="post" style="display:inline;margin-right:6px">
            <input type="hidden" name="csrf" value="<?= $token ?>">
            <input type="hidden" name="action" value="make_admin">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button class="btn" type="submit">Make admin</button>
          </form>
        <?php else: ?>
          <form method="post" style="display:inline;margin-right:6px" onsubmit="return confirm('Revoke admin role?');">
            <input type="hidden" name="csrf" value="<?= $token ?>">
            <input type="hidden" name="action" value="revoke_admin">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button class="btn secondary" type="submit">Revoke admin</button>
          </form>
        <?php endif; ?>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete user and their messages?');">
          <input type="hidden" name="csrf" value="<?= $token ?>">
          <input type="hidden" name="action" value="delete_user">
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <button class="btn secondary" type="submit">Delete user</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
