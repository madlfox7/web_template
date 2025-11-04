<?php
require_once __DIR__.'/../includes/products.php';

$pdo = db();
$u = current_user();
$isAdmin = is_admin();
$errors = [];
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!check_csrf($token)) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $id = (int)($_POST['id'] ?? 0);
            $qty = (int)($_POST['qty'] ?? 0);
            
            if ($id <= 0) {
                $errors[] = 'Invalid product ID.';
            } elseif ($qty <= 0) {
                $errors[] = 'Quantity must be at least 1.';
            } elseif ($qty > 999) {
                $errors[] = 'Quantity cannot exceed 999.';
            } else {
                $p = product_by_id($id);
                if (!$p) {
                    $errors[] = 'Product not found.';
                } elseif (!$p['active']) {
                    $errors[] = 'This product is not available.';
                } elseif ($p['stock'] > 0 && $qty > $p['stock']) {
                    $errors[] = 'Not enough stock available. Only ' . $p['stock'] . ' items in stock.';
                } else {
                    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
                    if (!isset($_SESSION['cart'][$id])) $_SESSION['cart'][$id] = 0;
                    
                    $currentInCart = $_SESSION['cart'][$id];
                    $newTotal = $currentInCart + $qty;
                    
                    if ($p['stock'] > 0 && $newTotal > $p['stock']) {
                        $available = $p['stock'] - $currentInCart;
                        if ($available <= 0) {
                            $errors[] = 'You already have the maximum available quantity in your cart.';
                        } else {
                            $errors[] = 'Cannot add ' . $qty . ' items. Only ' . $available . ' more can be added to cart.';
                        }
                    } else {
                        $_SESSION['cart'][$id] = $newTotal;
                        $flash = 'Added ' . $qty . ' item(s) to cart.';
                    }
                }
            }
        } elseif ($action === 'admin_add_product' && $isAdmin) {
            $name = trim($_POST['name'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $img = trim($_POST['img'] ?? '');
            $stock = (int)($_POST['stock'] ?? 0);
            
            if ($name === '') {
                $errors[] = 'Product name is required.';
            } elseif (strlen($name) > 255) {
                $errors[] = 'Product name cannot exceed 255 characters.';
            } elseif ($price <= 0) {
                $errors[] = 'Product price must be greater than 0.';
            } elseif ($price > 99999.99) {
                $errors[] = 'Product price cannot exceed $99,999.99.';
            } elseif ($stock < 0) {
                $errors[] = 'Stock quantity cannot be negative.';
            } elseif ($stock > 999999) {
                $errors[] = 'Stock quantity cannot exceed 999,999.';
            } elseif ($img !== '' && (strlen($img) > 255 || (!filter_var($img, FILTER_VALIDATE_URL) && substr($img, 0, 1) !== '/'))) {
                $errors[] = 'Image URL must be a valid URL or path starting with /.';
            } else {
                if (add_product($name, $price, $description, $img, $stock)) {
                    $flash = 'Product added successfully.';
                } else {
                    $errors[] = 'Failed to add product. Please try again.';
                }
            }
        } elseif ($action === 'admin_update_product' && $isAdmin) {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $img = trim($_POST['img'] ?? '');
            $stock = (int)($_POST['stock'] ?? 0);
            $active = isset($_POST['active']);
            
            if ($id <= 0) {
                $errors[] = 'Invalid product ID.';
            } elseif ($name === '') {
                $errors[] = 'Product name is required.';
            } elseif (strlen($name) > 255) {
                $errors[] = 'Product name cannot exceed 255 characters.';
            } elseif ($price <= 0) {
                $errors[] = 'Product price must be greater than 0.';
            } elseif ($price > 99999.99) {
                $errors[] = 'Product price cannot exceed $99,999.99.';
            } elseif ($stock < 0) {
                $errors[] = 'Stock quantity cannot be negative.';
            } elseif ($stock > 999999) {
                $errors[] = 'Stock quantity cannot exceed 999,999.';
            } elseif ($img !== '' && (strlen($img) > 255 || (!filter_var($img, FILTER_VALIDATE_URL) && substr($img, 0, 1) !== '/'))) {
                $errors[] = 'Image URL must be a valid URL or path starting with /.';
            } else {
                // Check if product exists before updating
                $existing = product_by_id($id);
                if (!$existing) {
                    $errors[] = 'Product not found.';
                } else {
                    if (update_product($id, $name, $price, $description, $img, $stock, $active)) {
                        $flash = 'Product updated successfully.';
                    } else {
                        $errors[] = 'Failed to update product. Please try again.';
                    }
                }
            }
        } elseif ($action === 'admin_delete_product' && $isAdmin) {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $errors[] = 'Invalid product ID.';
            } else {
                // Check if product exists before deleting
                $existing = product_by_id($id);
                if (!$existing) {
                    $errors[] = 'Product not found.';
                } else {
                    if (delete_product($id)) {
                        $flash = 'Product deleted successfully.';
                    } else {
                        $errors[] = 'Failed to delete product. It may be referenced by existing orders.';
                    }
                }
            }
        }
    }
}

$list = get_products($isAdmin); // Show inactive products only to admins
$token = csrf_token();
?>

<div class="card">
  <h2>Shop</h2>
  <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($errors): ?>
    <div class="flash" style="border-color:#fca5a5;background:#fff1f2;color:#7f1d1d">
      <ul style="margin:0;padding-left:1.2rem"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <?php if ($isAdmin): ?>
    <details style="margin-bottom:1rem">
      <summary class="btn" style="background:#dc2626;color:white">Add New Product (Admin)</summary>
      <form method="post" class="row" style="margin-top:.75rem;background:#fef2f2;padding:1rem;border-radius:8px">
        <input type="hidden" name="csrf" value="<?= $token ?>">
        <input type="hidden" name="action" value="admin_add_product">
        
        <label>
          <div>Product Name *</div>
          <input class="input" type="text" name="name" required>
        </label>
        
        <label>
          <div>Price * ($)</div>
          <input class="input" type="number" step="0.01" min="0.01" max="99999.99" name="price" required>
        </label>
        
        <label>
          <div>Description</div>
          <textarea class="input" name="description" rows="2" maxlength="1000"></textarea>
        </label>
        
        <label>
          <div>Image URL</div>
          <input class="input" type="text" name="img" placeholder="/img/product.png" maxlength="255">
        </label>
        
        <label>
          <div>Stock Quantity</div>
          <input class="input" type="number" min="0" max="999999" name="stock" value="0">
        </label>
        
        <div>
          <button class="btn" type="submit">Add Product</button>
        </div>
      </form>
    </details>
  <?php endif; ?>

  <div class="grid">
  <?php foreach ($list as $p): ?>
    <div class="card<?= !$p['active'] ? ' inactive' : '' ?>" style="<?= !$p['active'] ? 'opacity:0.6;border:2px dashed #ccc' : '' ?>">
      <?php if ($isAdmin && !$p['active']): ?>
        <div style="background:#fef2f2;color:#dc2626;padding:4px 8px;font-size:12px;border-radius:4px;margin-bottom:8px;text-align:center">INACTIVE</div>
      <?php endif; ?>
      
      <div style="font-weight:600; font-size:1.1rem; margin-bottom:.25rem;"><?= htmlspecialchars($p['name']) ?></div>
      <div style="color:#666; margin-bottom:.5rem;">$<?= number_format($p['price'], 2) ?></div>
      <?php if ($p['description']): ?>
        <div style="color:#888; font-size:0.9rem; margin-bottom:.5rem;"><?= htmlspecialchars($p['description']) ?></div>
      <?php endif; ?>
      <?php if ($isAdmin): ?>
        <div style="color:#666; font-size:0.8rem; margin-bottom:.5rem;">Stock: <?= (int)$p['stock'] ?></div>
      <?php endif; ?>
      
      <?php if ($p['active']): ?>
        <form method="post" style="display:flex; gap:.5rem; align-items:center;">
          <input type="hidden" name="csrf" value="<?= $token ?>">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <input class="input" style="max-width:100px" type="number" min="1" max="<?= $p['stock'] > 0 ? $p['stock'] : 999 ?>" name="qty" value="1" required>
          <button class="btn" type="submit">Add to cart</button>
        </form>
      <?php endif; ?>
      
      <?php if ($isAdmin): ?>
        <details style="margin-top:.5rem">
          <summary class="btn secondary" style="font-size:12px;padding:4px 8px">Edit Product</summary>
          <form method="post" style="margin-top:.5rem;background:#f9f9f9;padding:8px;border-radius:4px">
            <input type="hidden" name="csrf" value="<?= $token ?>">
            <input type="hidden" name="action" value="admin_update_product">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            
            <div style="margin-bottom:.5rem">
              <label style="font-size:12px">Name:</label>
              <input class="input" type="text" name="name" value="<?= htmlspecialchars($p['name']) ?>" required maxlength="255" style="font-size:12px">
            </div>
            
            <div style="margin-bottom:.5rem">
              <label style="font-size:12px">Price:</label>
              <input class="input" type="number" step="0.01" min="0.01" max="99999.99" name="price" value="<?= $p['price'] ?>" required style="font-size:12px">
            </div>
            
            <div style="margin-bottom:.5rem">
              <label style="font-size:12px">Description:</label>
              <textarea class="input" name="description" rows="2" maxlength="1000" style="font-size:12px"><?= htmlspecialchars($p['description'] ?? '') ?></textarea>
            </div>
            
            <div style="margin-bottom:.5rem">
              <label style="font-size:12px">Stock:</label>
              <input class="input" type="number" min="0" max="999999" name="stock" value="<?= (int)$p['stock'] ?>" style="font-size:12px">
            </div>
            
            <div style="margin-bottom:.5rem">
              <label style="font-size:12px">
                <input type="checkbox" name="active" <?= $p['active'] ? 'checked' : '' ?>>
                Active
              </label>
            </div>
            
            <div style="display:flex;gap:4px">
              <button class="btn" type="submit" style="font-size:12px;padding:4px 8px">Update</button>
              <button class="btn secondary" type="button" onclick="this.parentElement.parentElement.parentElement.parentElement.open=false" style="font-size:12px;padding:4px 8px">Cancel</button>
            </div>
          </form>
          
          <form method="post" style="margin-top:.5rem" onsubmit="return confirm('Permanently delete this product?');">
            <input type="hidden" name="csrf" value="<?= $token ?>">
            <input type="hidden" name="action" value="admin_delete_product">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button class="btn" type="submit" style="background:#dc2626;color:white;font-size:12px;padding:4px 8px">Delete Product</button>
          </form>
        </details>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>
</div>
