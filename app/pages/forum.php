<?php
// Forum with threads and posts; users can create threads, reply, and edit/delete own posts within N minutes. Admin has full privileges.

$pdo = db();
$errors = [];
$flash = '';
$user = current_user();
$isAdmin = is_admin();
$EDIT_WINDOW_MIN = (int)(getenv('EDIT_WINDOW_MINUTES') ?: 10);
$now = new DateTimeImmutable('now');

$token = csrf_token();

// Helpers
function can_edit_post(array $post, bool $isAdmin, ?array $user, int $windowMin, DateTimeImmutable $now): bool {
    // Admins cannot edit posts, only hide/delete
    if ($isAdmin) return false;
    if (!$user) return false;
    if ((int)$post['user_id'] !== (int)$user['id']) return false;
    try { $created = new DateTimeImmutable($post['created_at']); } catch (Throwable $e) { return false; }
    $diffMin = (int)floor(($now->getTimestamp() - $created->getTimestamp()) / 60);
    return $diffMin <= $windowMin;
}

// Routing by query param
$threadId = isset($_GET['thread']) ? (int)$_GET['thread'] : 0;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if (!check_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        try {
            if ($act === 'create_thread') {
                if (!$user) throw new Exception('Login required.');
                $title = trim((string)($_POST['title'] ?? ''));
                $content = trim((string)($_POST['content'] ?? ''));
                if ($title === '' || $content === '') throw new Exception('Title and content are required.');
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO threads (user_id, title) VALUES (?, ?)')->execute([(int)$user['id'], $title]);
                $tid = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO posts (thread_id, user_id, content) VALUES (?, ?, ?)')->execute([$tid, (int)$user['id'], $content]);
                $pdo->commit();
                $threadId = $tid; // stay on new thread view below
                $flash = 'Thread created.';
            } elseif ($act === 'reply' && $threadId > 0) {
                if (!$user) throw new Exception('Login required.');
                $content = trim((string)($_POST['content'] ?? ''));
                if ($content === '') throw new Exception('Message cannot be empty.');
                $pdo->prepare('INSERT INTO posts (thread_id, user_id, content) VALUES (?, ?, ?)')->execute([$threadId, (int)$user['id'], $content]);
                $flash = 'Reply posted.';
            } elseif ($act === 'edit_post') {
                $pid = (int)($_POST['post_id'] ?? 0);
                $content = trim((string)($_POST['content'] ?? ''));
                if ($pid <= 0 || $content === '') throw new Exception('Invalid edit request.');
                $stmt = $pdo->prepare('SELECT id, thread_id, user_id, content, created_at FROM posts WHERE id = ?');
                $stmt->execute([$pid]);
                $post = $stmt->fetch();
                if (!$post) throw new Exception('Post not found.');
                if (!can_edit_post($post, $isAdmin, $user, $EDIT_WINDOW_MIN, $now)) throw new Exception('Not allowed to edit.');
                $pdo->prepare('UPDATE posts SET content = ?, updated_at = NOW() WHERE id = ?')->execute([$content, $pid]);
                $threadId = (int)$post['thread_id'];
                $flash = 'Post updated.';
            } elseif ($act === 'delete_post') {
                $pid = (int)($_POST['post_id'] ?? 0);
                if ($pid <= 0) throw new Exception('Invalid delete request.');
                $row = $pdo->prepare('SELECT id, thread_id, user_id, created_at FROM posts WHERE id = ?');
                $row->execute([$pid]);
                $post = $row->fetch();
                if (!$post) throw new Exception('Post not found.');
                if (!can_edit_post($post, $isAdmin, $user, $EDIT_WINDOW_MIN, $now)) throw new Exception('Not allowed to delete.');
                $pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$pid]);
                $threadId = (int)$post['thread_id'];
                $flash = 'Post deleted.';
            } elseif ($act === 'delete_thread') {
                $tid = (int)($_POST['thread_id'] ?? 0);
                if ($tid <= 0) throw new Exception('Invalid thread.');
                if (!$isAdmin) throw new Exception('Admin only.');
                $pdo->prepare('DELETE FROM threads WHERE id = ?')->execute([$tid]);
                $threadId = 0;
                $flash = 'Thread deleted.';
            } elseif ($act === 'admin_hide_post') {
                $pid = (int)($_POST['post_id'] ?? 0);
                if ($pid <= 0) throw new Exception('Invalid post.');
                if (!$isAdmin) throw new Exception('Admin only.');
                $pdo->prepare('UPDATE posts SET hidden = 1 WHERE id = ?')->execute([$pid]);
                $flash = 'Post hidden by admin.';
            } elseif ($act === 'admin_show_post') {
                $pid = (int)($_POST['post_id'] ?? 0);
                if ($pid <= 0) throw new Exception('Invalid post.');
                if (!$isAdmin) throw new Exception('Admin only.');
                $pdo->prepare('UPDATE posts SET hidden = 0 WHERE id = ?')->execute([$pid]);
                $flash = 'Post shown by admin.';
            } elseif ($act === 'admin_delete_post') {
                $pid = (int)($_POST['post_id'] ?? 0);
                if ($pid <= 0) throw new Exception('Invalid post.');
                if (!$isAdmin) throw new Exception('Admin only.');
                $pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$pid]);
                $flash = 'Post deleted by admin.';
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage() ?: 'Operation failed.';
        }
    }
}

// View rendering
if ($threadId > 0) {
    // Thread view
    $th = $pdo->prepare('SELECT t.id, t.title, t.user_id, t.created_at, u.username FROM threads t LEFT JOIN users u ON u.id = t.user_id WHERE t.id = ?');
    $th->execute([$threadId]);
    $thread = $th->fetch();
    $posts = [];
    if ($thread) {
        // Always fetch all posts, but we'll handle display logic in the template
        $ps = $pdo->prepare("SELECT p.id, p.thread_id, p.user_id, p.content, p.created_at, p.updated_at, p.hidden, u.username FROM posts p LEFT JOIN users u ON u.id = p.user_id WHERE p.thread_id = ? ORDER BY p.created_at ASC");
        $ps->execute([$threadId]);
        $posts = $ps->fetchAll();
    }
    ?>
    <div class="card">
      <a href="/?page=forum" class="btn secondary" style="float:right">Back</a>
      <h2><?= htmlspecialchars($thread['title'] ?? 'Thread') ?></h2>
      <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
      <?php if ($errors): ?>
        <div class="flash" style="border-color:#fca5a5;background:#fff1f2;color:#7f1d1d">
          <ul style="margin:0;padding-left:1.2rem"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <?php if ($thread): ?>
        <?php if ($isAdmin): ?>
          <form method="post" style="margin:.5rem 0" onsubmit="return confirm('Delete this thread?');">
            <input type="hidden" name="csrf" value="<?= $token ?>">
            <input type="hidden" name="action" value="delete_thread">
            <input type="hidden" name="thread_id" value="<?= (int)$thread['id'] ?>">
            <button class="btn secondary" type="submit">Delete thread</button>
          </form>
        <?php endif; ?>

        <?php foreach ($posts as $p): ?>
          <div style="padding:8px;border-bottom:1px solid #eee<?= $isAdmin && $p['hidden'] ? ';background:#fff5f5;border-left:3px solid #ef4444' : '' ?>">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap">
              <div>
                <strong><?= htmlspecialchars($p['username'] ?? 'anon') ?></strong>
                <small style="opacity:.7">• <?= htmlspecialchars($p['created_at']) ?><?= $p['updated_at'] ? ' (edited)' : '' ?></small>
                <?php if ($isAdmin && $p['hidden']): ?>
                  <span style="color:#ef4444;font-weight:bold;margin-left:8px">[HIDDEN BY ADMIN]</span>
                <?php endif; ?>
              </div>
              <div style="display:flex;gap:8px;align-items:center">
                <?php if (can_edit_post($p, $isAdmin, $user, $EDIT_WINDOW_MIN, $now)): ?>
                  <details>
                    <summary class="btn secondary" style="display:inline-block;cursor:pointer">Edit/Delete</summary>
                    <div style="margin-top:.5rem;background:#f9f9f9;padding:8px;border-radius:4px">
                      <form method="post" class="row" style="margin-bottom:.5rem">
                        <textarea class="input" name="content" rows="3"><?= htmlspecialchars($p['content']) ?></textarea>
                        <input type="hidden" name="csrf" value="<?= $token ?>">
                        <input type="hidden" name="action" value="edit_post">
                        <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
                        <button class="btn" type="submit">Save</button>
                      </form>
                      <form method="post" onsubmit="return confirm('Delete this post?');">
                        <input type="hidden" name="csrf" value="<?= $token ?>">
                        <input type="hidden" name="action" value="delete_post">
                        <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
                        <button class="btn secondary" type="submit">Delete</button>
                      </form>
                    </div>
                  </details>
                <?php endif; ?>
                
                <?php if ($isAdmin): ?>
                  <?php if ($p['hidden']): ?>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="csrf" value="<?= $token ?>">
                      <input type="hidden" name="action" value="admin_show_post">
                      <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
                      <button class="btn" type="submit" style="background:#16a34a;color:white;font-size:12px;padding:4px 8px">Show</button>
                    </form>
                  <?php else: ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('Hide this post from public view?');">
                      <input type="hidden" name="csrf" value="<?= $token ?>">
                      <input type="hidden" name="action" value="admin_hide_post">
                      <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
                      <button class="btn secondary" type="submit" style="font-size:12px;padding:4px 8px">Hide</button>
                    </form>
                  <?php endif; ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('PERMANENTLY delete this post?');">
                    <input type="hidden" name="csrf" value="<?= $token ?>">
                    <input type="hidden" name="action" value="admin_delete_post">
                    <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
                    <button class="btn" type="submit" style="background:#dc2626;color:white;font-size:12px;padding:4px 8px">Delete</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
            <?php if ($p['hidden']): ?>
              <?php if ($isAdmin): ?>
                <p style="margin:6px 0 0;opacity:0.6"><?= nl2br(htmlspecialchars($p['content'])) ?></p>
              <?php else: ?>
                <p style="margin:6px 0 0;color:#666;font-style:italic;background:#f9f9f9;padding:8px;border-radius:4px">This post has been hidden by an administrator.</p>
              <?php endif; ?>
            <?php else: ?>
              <p style="margin:6px 0 0"><?= nl2br(htmlspecialchars($p['content'])) ?></p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <?php if ($user): ?>
          <form method="post" class="row" style="margin-top:1rem">
            <textarea class="input" name="content" rows="3" placeholder="Write a reply..."></textarea>
            <input type="hidden" name="csrf" value="<?= $token ?>">
            <input type="hidden" name="action" value="reply">
            <button class="btn" type="submit">Reply</button>
          </form>
        <?php else: ?>
          <?php $ret = urlencode($_SERVER['REQUEST_URI'] ?? '/?page=forum'); ?>
          <p style="margin-top:1rem"><a class="btn" href="/?page=login&return=<?= $ret ?>">Login</a> to reply.</p>
        <?php endif; ?>
      <?php else: ?>
        <p>Thread not found.</p>
      <?php endif; ?>
    </div>
    <?php
    return;
}

// Threads list view
$hiddenFilter = $isAdmin ? '' : ' AND (p.hidden = 0 OR p.hidden IS NULL)';
$threads = $pdo->query("SELECT t.id, t.title, t.created_at, u.username, (
  SELECT COUNT(*) FROM posts p WHERE p.thread_id = t.id{$hiddenFilter}
) AS posts_count, (
  SELECT MAX(created_at) FROM posts p WHERE p.thread_id = t.id{$hiddenFilter}
) AS last_at FROM threads t LEFT JOIN users u ON u.id = t.user_id ORDER BY COALESCE(last_at, t.created_at) DESC, t.id DESC LIMIT 100")->fetchAll();
?>

<div class="card">
  <h2>Forum</h2>
  <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($errors): ?>
    <div class="flash" style="border-color:#fca5a5;background:#fff1f2;color:#7f1d1d"><ul style="margin:0;padding-left:1.2rem"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <?php if ($user): ?>
    <details style="margin-bottom:1rem">
      <summary class="btn">Create new thread</summary>
      <form method="post" class="row" style="margin-top:.75rem">
        <label>
          <div>Title</div>
          <input class="input" type="text" name="title" required>
        </label>
        <label>
          <div>Message</div>
          <textarea class="input" name="content" rows="3" required></textarea>
        </label>
        <input type="hidden" name="csrf" value="<?= $token ?>">
        <input type="hidden" name="action" value="create_thread">
        <div><button class="btn" type="submit">Create thread</button></div>
      </form>
    </details>
  <?php else: ?>
    <?php $ret = urlencode($_SERVER['REQUEST_URI'] ?? '/?page=forum'); ?>
    <p><a class="btn" href="/?page=login&return=<?= $ret ?>">Login</a> to start a new thread or reply.</p>
  <?php endif; ?>

  <h3>Recent threads</h3>
  <?php if (!$threads): ?>
    <p>No threads yet.</p>
  <?php else: ?>
    <?php foreach ($threads as $t): ?>
      <div style="padding:8px;border-bottom:1px solid #eee">
        <a href="/?page=forum&thread=<?= (int)$t['id'] ?>" style="font-weight:600;text-decoration:none"><?= htmlspecialchars($t['title']) ?></a>
        <div style="color:#666">
          by <?= htmlspecialchars($t['username'] ?? 'anon') ?> • <?= htmlspecialchars($t['created_at']) ?> • <?= (int)$t['posts_count'] ?> posts
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
