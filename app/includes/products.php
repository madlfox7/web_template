<?php
// Products catalog using database

function get_products(bool $includeInactive = false): array {
    $pdo = db();
    if (!$pdo) return [];
    
    $sql = "SELECT id, name, price, description, img, stock, active FROM products";
    if (!$includeInactive) {
        $sql .= " WHERE active = 1";
    }
    $sql .= " ORDER BY name";
    
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function product_by_id(int $id): ?array {
    $pdo = db();
    if (!$pdo) return null;
    
    try {
        $stmt = $pdo->prepare('SELECT id, name, price, description, img, stock, active FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        return $product ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function demo_products(): array {
    // Backward compatibility - use database products
    $products = get_products();
    $result = [];
    foreach ($products as $p) {
        $result[$p['id']] = $p;
    }
    return $result;
}

function add_product(string $name, float $price, string $description = '', string $img = '', int $stock = 0): bool {
    $pdo = db();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare('INSERT INTO products (name, price, description, img, stock) VALUES (?, ?, ?, ?, ?)');
        return $stmt->execute([$name, $price, $description, $img, $stock]);
    } catch (Throwable $e) {
        return false;
    }
}

function update_product(int $id, string $name, float $price, string $description = '', string $img = '', int $stock = 0, bool $active = true): bool {
    $pdo = db();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare('UPDATE products SET name = ?, price = ?, description = ?, img = ?, stock = ?, active = ? WHERE id = ?');
        return $stmt->execute([$name, $price, $description, $img, $stock, $active ? 1 : 0, $id]);
    } catch (Throwable $e) {
        return false;
    }
}

function delete_product(int $id): bool {
    $pdo = db();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
        return $stmt->execute([$id]);
    } catch (Throwable $e) {
        return false;
    }
}
