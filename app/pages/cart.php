<?php
require_once __DIR__.'/../includes/products.php';

$isAdmin = is_admin();
$errors = [];
$flash = '';

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
$cart = &$_SESSION['cart'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'update') {
            $hasChanges = false;
            foreach (($_POST['qty'] ?? []) as $id => $qty) {
                $id = (int)$id; 
                $qty = (int)$qty;
                
                if ($id <= 0) {
                    $errors[] = 'Invalid product ID.';
                    continue;
                }
                
                if ($qty < 0) {
                    $errors[] = 'Quantity cannot be negative.';
                    continue;
                }
                
                if ($qty > 999) {
                    $errors[] = 'Quantity cannot exceed 999.';
                    continue;
                }
                
                // Check product exists and is active
                $product = product_by_id($id);
                if (!$product) {
                    $errors[] = 'Product with ID ' . $id . ' not found.';
                    unset($cart[$id]); // Remove invalid products
                    $hasChanges = true;
                    continue;
                }
                
                if (!$product['active']) {
                    $errors[] = $product['name'] . ' is no longer available and was removed from cart.';
                    unset($cart[$id]);
                    $hasChanges = true;
                    continue;
                }
                
                // Check stock if product has stock tracking
                if ($product['stock'] > 0 && $qty > $product['stock']) {
                    $errors[] = $product['name'] . ': Only ' . $product['stock'] . ' items available. Quantity adjusted.';
                    $cart[$id] = $product['stock'];
                    $hasChanges = true;
                    continue;
                }
                
                if ($qty === 0) {
                    unset($cart[$id]);
                    $hasChanges = true;
                } else {
                    $cart[$id] = $qty;
                    $hasChanges = true;
                }
            }
            
            if ($hasChanges && empty($errors)) {
                $flash = 'Cart updated successfully.';
            } elseif ($hasChanges) {
                $flash = 'Cart updated with some changes noted above.';
            }
        } elseif ($action === 'remove') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $errors[] = 'Invalid product ID.';
            } elseif (!isset($cart[$id])) {
                $errors[] = 'Item not found in cart.';
            } else {
                unset($cart[$id]);
                $flash = 'Item removed from cart.';
            }
        } elseif ($action === 'clear') {
            $cart = [];
            $flash = 'Cart cleared.';
        } elseif ($action === 'admin_add_item' && $isAdmin) {
            $id = (int)($_POST['product_id'] ?? 0);
            $qty = (int)($_POST['qty'] ?? 0);
            
            if ($id <= 0) {
                $errors[] = 'Please select a valid product.';
            } elseif ($qty <= 0) {
                $errors[] = 'Quantity must be at least 1.';
            } elseif ($qty > 999) {
                $errors[] = 'Quantity cannot exceed 999.';
            } else {
                $p = product_by_id($id);
                if (!$p) {
                    $errors[] = 'Selected product not found.';
                } elseif (!$p['active']) {
                    $errors[] = 'Selected product is not active.';
                } else {
                    if (!isset($cart[$id])) $cart[$id] = 0;
                    $currentInCart = $cart[$id];
                    $newTotal = $currentInCart + $qty;
                    
                    // Check stock limits for admins too (optional check)
                    if ($p['stock'] > 0 && $newTotal > $p['stock']) {
                        $available = $p['stock'] - $currentInCart;
                        if ($available <= 0) {
                            $errors[] = 'Maximum available quantity already in cart.';
                        } else {
                            $cart[$id] = $p['stock'];
                            $flash = 'Added maximum available quantity (' . $available . ') to cart.';
                        }
                    } else {
                        $cart[$id] = $newTotal;
                        $flash = 'Item added to cart by admin.';
                    }
                }
            }
        } elseif ($action === 'admin_set_qty' && $isAdmin) {
            $id = (int)($_POST['id'] ?? 0);
            $qty = (int)($_POST['new_qty'] ?? 0);
            
            if ($id <= 0) {
                $errors[] = 'Invalid product ID.';
            } elseif ($qty < 0) {
                $errors[] = 'Quantity cannot be negative.';
            } elseif ($qty > 999) {
                $errors[] = 'Quantity cannot exceed 999.';
            } else {
                // Check if product exists
                $product = product_by_id($id);
                if (!$product) {
                    $errors[] = 'Product not found. Removing from cart.';
                    unset($cart[$id]);
                } elseif (!$product['active']) {
                    $errors[] = $product['name'] . ' is no longer available. Removing from cart.';
                    unset($cart[$id]);
                } elseif ($product['stock'] > 0 && $qty > $product['stock']) {
                    $errors[] = 'Only ' . $product['stock'] . ' items available. Quantity set to maximum.';
                    $cart[$id] = $product['stock'];
                } else {
                    if ($qty === 0) {
                        unset($cart[$id]);
                        $flash = 'Item removed by admin.';
                    } else {
                        $cart[$id] = $qty;
                        $flash = 'Quantity updated by admin.';
                    }
                }
            }
        }
    }
}

$allProducts = get_products(); // For admin dropdown
$products = [];
foreach ($allProducts as $p) {
    $products[$p['id']] = $p;
}
$items = [];
$subtotal = 0.0;
foreach ($cart as $id => $qty) {
    $p = $products[$id] ?? null;
    if (!$p) continue;
    $line = $p;
    $line['qty'] = $qty;
    $line['total'] = $qty * (float)$p['price'];
    $subtotal += $line['total'];
    $items[] = $line;
}
$token = csrf_token();
?>

<div class="card">
  <h2>Your cart</h2>
  <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($errors): ?>
    <div class="flash" style="border-color:#fca5a5;background:#fff1f2;color:#7f1d1d"><ul style="margin:0;padding-left:1.2rem"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <?php if ($isAdmin): ?>
    <details style="margin-bottom:1rem">
      <summary class="btn" style="background:#dc2626;color:white">Add Item to Cart (Admin)</summary>
      <form method="post" style="margin-top:.75rem;background:#fef2f2;padding:1rem;border-radius:8px;display:flex;gap:.5rem;align-items:end;flex-wrap:wrap">
        <input type="hidden" name="csrf" value="<?= $token ?>">
        <input type="hidden" name="action" value="admin_add_item">
        
        <label style="min-width:200px">
          <div style="font-size:12px;margin-bottom:4px">Product</div>
          <select class="input" name="product_id" required style="font-size:12px">
            <option value="">Select a product...</option>
            <?php foreach ($allProducts as $ap): ?>
              <option value="<?= (int)$ap['id'] ?>"><?= htmlspecialchars($ap['name']) ?> - $<?= number_format($ap['price'], 2) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        
        <label style="min-width:80px">
          <div style="font-size:12px;margin-bottom:4px">Quantity</div>
          <input class="input" type="number" min="1" max="999" name="qty" value="1" required style="font-size:12px">
        </label>
        
        <button class="btn" type="submit" style="font-size:12px;padding:8px 12px">Add to Cart</button>
      </form>
    </details>
  <?php endif; ?>

  <?php if (!$items): ?>
    <p>Your cart is empty. <a class="btn" href="/?page=shop">Go shopping</a></p>
  <?php else: ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= $token ?>">
      <input type="hidden" name="action" value="update">
      <table class="table">
        <thead>
          <tr><th>Item</th><th>Price</th><th>Qty</th><th>Total</th><th></th><?php if ($isAdmin): ?><th>Admin</th><?php endif; ?></tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td><?= htmlspecialchars($it['name']) ?></td>
              <td>$<?= number_format($it['price'], 2) ?></td>
              <td style="max-width:120px"><input class="input" type="number" min="0" name="qty[<?= (int)$it['id'] ?>]" value="<?= (int)$it['qty'] ?>"></td>
              <td>$<?= number_format($it['total'], 2) ?></td>
              <td>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= $token ?>">
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                  <button class="btn secondary" type="submit" style="font-size:12px;padding:4px 8px">Remove</button>
                </form>
              </td>
              <?php if ($isAdmin): ?>
                <td>
                  <form method="post" style="display:inline-flex;gap:4px;align-items:center">
                    <input type="hidden" name="csrf" value="<?= $token ?>">
                    <input type="hidden" name="action" value="admin_set_qty">
                    <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                    <input class="input" type="number" min="0" max="999" name="new_qty" value="<?= (int)$it['qty'] ?>" style="width:60px;font-size:12px">
                    <button class="btn" type="submit" style="background:#dc2626;color:white;font-size:10px;padding:2px 6px">Set</button>
                  </form>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1rem;gap:.5rem;flex-wrap:wrap">
        <div style="font-weight:600">Subtotal: $<?= number_format($subtotal, 2) ?></div>
        <div>
          <button class="btn" type="submit">Update cart</button>
          <form method="post" style="display:inline" onsubmit="return confirm('Clear entire cart?');">
            <input type="hidden" name="csrf" value="<?= $token ?>">
            <input type="hidden" name="action" value="clear">
            <button class="btn secondary" type="submit">Clear cart</button>
          </form>
          <a class="btn" href="/?page=shop">Continue shopping</a>
        </div>
      </div>
    </form>
  <?php endif; ?>
</div>
