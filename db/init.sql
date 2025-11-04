-- Enhanced database schema with better indexing and constraints

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  email VARCHAR(120) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('user','admin') DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_login TIMESTAMP NULL,
  is_active TINYINT(1) DEFAULT 1,
  
  INDEX idx_users_email (email),
  INDEX idx_users_username (username),
  INDEX idx_users_role (role),
  INDEX idx_users_active (is_active)
);

-- Seed a default admin user (username: admin, password: AdminPass123!)
INSERT IGNORE INTO users (username, email, password_hash, role) VALUES (
  'admin',
  'admin@example.com',
  '$2y$10$OC.0bDKbkyWXs.6tpcCojemu8dee0tlodXHiyAn5XT3olLr2dtox6',
  'admin'
);

-- Forum messages table (legacy - consider deprecating)
CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  content TEXT NOT NULL,
  status ENUM('pending','approved','denied') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_messages_user (user_id),
  INDEX idx_messages_status (status),
  INDEX idx_messages_created (created_at)
);

-- Forum threads and posts (enhanced)
CREATE TABLE IF NOT EXISTS threads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT,
  is_pinned TINYINT(1) DEFAULT 0,
  is_locked TINYINT(1) DEFAULT 0,
  view_count INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_threads_user (user_id),
  INDEX idx_threads_created (created_at),
  INDEX idx_threads_updated (updated_at),
  INDEX idx_threads_pinned (is_pinned),
  FULLTEXT idx_threads_search (title, description)
);

CREATE TABLE IF NOT EXISTS posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  thread_id INT NOT NULL,
  user_id INT NULL,
  content TEXT NOT NULL,
  hidden TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  
  FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_posts_thread (thread_id),
  INDEX idx_posts_user (user_id),
  INDEX idx_posts_created (created_at),
  INDEX idx_posts_hidden (hidden),
  FULLTEXT idx_posts_search (content)
);

-- Products table for shop functionality (enhanced)
CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) UNIQUE,
  description TEXT,
  price DECIMAL(10,2) NOT NULL,
  cost_price DECIMAL(10,2) NULL,
  img VARCHAR(255),
  stock INT DEFAULT 0,
  active TINYINT(1) DEFAULT 1,
  featured TINYINT(1) DEFAULT 0,
  weight DECIMAL(8,2) NULL,
  sku VARCHAR(100) UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_products_active (active),
  INDEX idx_products_featured (featured),
  INDEX idx_products_price (price),
  INDEX idx_products_stock (stock),
  INDEX idx_products_sku (sku),
  FULLTEXT idx_products_search (name, description)
);

-- Categories for products
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) UNIQUE,
  description TEXT,
  parent_id INT NULL,
  sort_order INT DEFAULT 0,
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
  INDEX idx_categories_parent (parent_id),
  INDEX idx_categories_active (active),
  INDEX idx_categories_sort (sort_order)
);

-- Product-Category relationship
CREATE TABLE IF NOT EXISTS product_categories (
  product_id INT NOT NULL,
  category_id INT NOT NULL,
  
  PRIMARY KEY (product_id, category_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Orders system
CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
  total_amount DECIMAL(10,2) NOT NULL,
  shipping_address TEXT,
  billing_address TEXT,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_orders_user (user_id),
  INDEX idx_orders_status (status),
  INDEX idx_orders_created (created_at)
);

-- Order items
CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
  INDEX idx_order_items_order (order_id),
  INDEX idx_order_items_product (product_id)
);

-- User sessions (optional - for better session management)
CREATE TABLE IF NOT EXISTS user_sessions (
  id VARCHAR(128) PRIMARY KEY,
  user_id INT NOT NULL,
  ip_address VARCHAR(45),
  user_agent TEXT,
  payload TEXT,
  last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_sessions_user (user_id),
  INDEX idx_sessions_activity (last_activity)
);

-- Insert demo products with enhanced data
INSERT IGNORE INTO products (id, name, slug, description, price, img, stock, featured, sku) VALUES 
(1, 'Premium T-Shirt', 'premium-t-shirt', 'High-quality cotton t-shirt perfect for everyday wear', 19.99, '/img/tshirt.png', 100, 1, 'TSH-001'),
(2, 'Coffee Mug', 'coffee-mug', 'Ceramic coffee mug with ergonomic handle', 9.99, '/img/mug.png', 50, 1, 'MUG-001'),
(3, 'Sticker Pack', 'sticker-pack', 'Collection of high-quality vinyl stickers', 3.99, '/img/stickers.png', 200, 0, 'STK-001'),
(4, 'Premium Hoodie', 'premium-hoodie', 'Comfortable hoodie with premium materials', 39.00, '/img/hoodie.png', 25, 1, 'HOD-001');

-- Insert demo categories
INSERT IGNORE INTO categories (id, name, slug, description) VALUES
(1, 'Clothing', 'clothing', 'Apparel and fashion items'),
(2, 'Accessories', 'accessories', 'Various accessories and add-ons'),
(3, 'Home & Office', 'home-office', 'Items for home and office use');

-- Link products to categories
INSERT IGNORE INTO product_categories (product_id, category_id) VALUES
(1, 1), -- T-Shirt -> Clothing
(4, 1), -- Hoodie -> Clothing
(2, 3), -- Mug -> Home & Office
(3, 2); -- Stickers -> Accessories
