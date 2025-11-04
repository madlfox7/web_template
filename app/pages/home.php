<?php
// Enhanced homepage with better UX and statistics
$pdo = db();
$u = current_user();

// Get statistics
$stats = [
    'users' => 0,
    'threads' => 0,
    'posts' => 0,
    'products' => 0
];

if ($pdo) {
    try {
        $stats['users'] = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $stats['threads'] = $pdo->query('SELECT COUNT(*) FROM threads')->fetchColumn();
        $stats['posts'] = $pdo->query('SELECT COUNT(*) FROM posts WHERE hidden = 0 OR hidden IS NULL')->fetchColumn();
        $stats['products'] = $pdo->query('SELECT COUNT(*) FROM products WHERE active = 1')->fetchColumn();
    } catch (Exception $e) {
        // Silent fail for stats
    }
}

// Get recent activity
$recentThreads = [];
$recentProducts = [];
if ($pdo) {
    try {
        $stmt = $pdo->query('SELECT t.id, t.title, t.created_at, u.username FROM threads t LEFT JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC LIMIT 3');
        $recentThreads = $stmt->fetchAll();
        
        $stmt = $pdo->query('SELECT id, name, price, img FROM products WHERE active = 1 ORDER BY created_at DESC LIMIT 4');
        $recentProducts = $stmt->fetchAll();
    } catch (Exception $e) {
        // Silent fail
    }
}
?>

<div class="fade-in">
  <div class="card text-center" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border: none;">
    <h1 style="margin: 0 0 1rem 0; font-size: 2.5rem; color: white;">Welcome to MiniShop+Forum</h1>
    <p style="font-size: 1.2rem; opacity: 0.9; margin: 0; color: white;">Your one-stop destination for community discussions and shopping</p>
  </div>

  <?php if (!$u): ?>
    <div class="card">
      <h2>Get Started</h2>
      <p>Join our community to participate in discussions and shop for amazing products!</p>
      <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
        <a href="/?page=register" class="btn">Create Account</a>
        <a href="/?page=login" class="btn secondary">Sign In</a>
        <a href="/?page=forum" class="btn secondary">Browse Forum</a>
        <a href="/?page=shop" class="btn secondary">View Products</a>
      </div>
    </div>
  <?php endif; ?>

  <!-- Statistics -->
  <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    <div class="card text-center">
      <div style="font-size: 2rem; font-weight: bold; color: var(--primary);"><?= number_format($stats['users']) ?></div>
      <div style="color: var(--secondary);">Community Members</div>
    </div>
    <div class="card text-center">
      <div style="font-size: 2rem; font-weight: bold; color: var(--success);"><?= number_format($stats['threads']) ?></div>
      <div style="color: var(--secondary);">Forum Threads</div>
    </div>
    <div class="card text-center">
      <div style="font-size: 2rem; font-weight: bold; color: var(--warning);"><?= number_format($stats['posts']) ?></div>
      <div style="color: var(--secondary);">Forum Posts</div>
    </div>
    <div class="card text-center">
      <div style="font-size: 2rem; font-weight: bold; color: var(--danger);"><?= number_format($stats['products']) ?></div>
      <div style="color: var(--secondary);">Products Available</div>
    </div>
  </div>

  <div class="grid">
    <!-- Recent Forum Activity -->
    <div class="card">
      <h3 style="margin-top: 0; display: flex; align-items: center; gap: 0.5rem;">
        <span>💬</span> Latest Forum Discussions
      </h3>
      <?php if ($recentThreads): ?>
        <?php foreach ($recentThreads as $thread): ?>
          <div style="padding: 0.75rem 0; border-bottom: 1px solid var(--border); last-child:border-bottom: none;">
            <a href="/?page=forum&thread=<?= $thread['id'] ?>" style="text-decoration: none; font-weight: 600; color: var(--primary);">
              <?= htmlspecialchars($thread['title']) ?>
            </a>
            <div style="font-size: 0.9rem; color: var(--secondary); margin-top: 0.25rem;">
              by <?= htmlspecialchars($thread['username'] ?? 'Anonymous') ?> • <?= date('M j, Y', strtotime($thread['created_at'])) ?>
            </div>
          </div>
        <?php endforeach; ?>
        <div style="margin-top: 1rem;">
          <a href="/?page=forum" class="btn secondary">View All Discussions</a>
        </div>
      <?php else: ?>
        <p style="color: var(--secondary);">No discussions yet. <a href="/?page=forum">Start the first one!</a></p>
      <?php endif; ?>
    </div>

    <!-- Featured Products -->
    <div class="card">
      <h3 style="margin-top: 0; display: flex; align-items: center; gap: 0.5rem;">
        <span>🛍️</span> Featured Products
      </h3>
      <?php if ($recentProducts): ?>
        <div style="display: grid; gap: 1rem;">
          <?php foreach ($recentProducts as $product): ?>
            <div style="display: flex; align-items: center; gap: 1rem; padding: 0.5rem 0;">
              <?php if ($product['img']): ?>
                <div style="width: 50px; height: 50px; background: var(--light); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; color: var(--secondary);">
                  📦
                </div>
              <?php endif; ?>
              <div style="flex: 1;">
                <div style="font-weight: 600;"><?= htmlspecialchars($product['name']) ?></div>
                <div style="color: var(--primary); font-weight: 600;">$<?= number_format($product['price'], 2) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="margin-top: 1rem;">
          <a href="/?page=shop" class="btn secondary">Browse All Products</a>
        </div>
      <?php else: ?>
        <p style="color: var(--secondary);">No products available yet.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Features Section -->
  <div class="card">
    <h3>What You Can Do Here</h3>
    <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
      <div style="text-align: center; padding: 1rem;">
        <div style="font-size: 3rem; margin-bottom: 0.5rem;">💬</div>
        <h4 style="margin: 0.5rem 0;">Join Discussions</h4>
        <p style="color: var(--secondary); margin: 0;">Participate in community forums, create threads, and engage with other members.</p>
      </div>
      <div style="text-align: center; padding: 1rem;">
        <div style="font-size: 3rem; margin-bottom: 0.5rem;">🛒</div>
        <h4 style="margin: 0.5rem 0;">Shop Products</h4>
        <p style="color: var(--secondary); margin: 0;">Browse and purchase from our curated selection of quality products.</p>
      </div>
      <div style="text-align: center; padding: 1rem;">
        <div style="font-size: 3rem; margin-bottom: 0.5rem;">👥</div>
        <h4 style="margin: 0.5rem 0;">Build Community</h4>
        <p style="color: var(--secondary); margin: 0;">Connect with like-minded individuals and grow together as a community.</p>
      </div>
    </div>
  </div>

  <?php if ($pdo): ?>
    <div class="flash success">
      <strong>✅ System Status:</strong> All systems operational and connected to database.
    </div>
  <?php else: ?>
    <div class="flash error">
      <strong>⚠️ System Status:</strong> Database connection unavailable. Some features may be limited.
    </div>
  <?php endif; ?>
</div>
