CREATE DATABASE IF NOT EXISTS matet_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE matet_inventory;
CREATE TABLE IF NOT EXISTS users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  user_name VARCHAR(80) NOT NULL UNIQUE,
  user_pw VARCHAR(255) NOT NULL,
  role ENUM('owner','assistant') NOT NULL DEFAULT 'assistant',
  active TINYINT(1) NOT NULL DEFAULT 1
);
CREATE TABLE IF NOT EXISTS categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT
);
CREATE TABLE IF NOT EXISTS suppliers (
  supplier_id INT AUTO_INCREMENT PRIMARY KEY,
  supplier_name VARCHAR(150) NOT NULL,
  contact_num VARCHAR(40),
  address TEXT
);
CREATE TABLE IF NOT EXISTS products (
  product_id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  supplier_id INT NOT NULL,
  product_name VARCHAR(160) NOT NULL,
  selling_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  product_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  product_discount DECIMAL(5,2) NOT NULL DEFAULT 0,
  product_quantity INT NOT NULL DEFAULT 0,
  description TEXT,
  reorder_level INT NOT NULL DEFAULT 5,
  image_url VARCHAR(500),
  active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY(category_id) REFERENCES categories(category_id),
  FOREIGN KEY(supplier_id) REFERENCES suppliers(supplier_id),
  CHECK(product_quantity >= 0)
);
CREATE TABLE IF NOT EXISTS transactions (
  transaction_id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  transaction_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  total_discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  FOREIGN KEY(admin_id) REFERENCES users(user_id)
);
CREATE TABLE IF NOT EXISTS product_transactions (
  product_transaction_id INT AUTO_INCREMENT PRIMARY KEY,
  transaction_id INT NOT NULL,
  product_id INT NOT NULL,
  selling_price DECIMAL(12,2) NOT NULL,
  product_quantity INT NOT NULL,
  product_cost DECIMAL(12,2) NOT NULL,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  subamount DECIMAL(12,2) NOT NULL,
  FOREIGN KEY(transaction_id) REFERENCES transactions(transaction_id),
  FOREIGN KEY(product_id) REFERENCES products(product_id)
);
CREATE TABLE IF NOT EXISTS deliveries (
  delivery_id INT AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT NOT NULL,
  admin_id INT NOT NULL,
  date_received DATETIME NULL,
  delivery_date DATE NOT NULL,
  delivery_status ENUM('delivering','received','cancelled') NOT NULL DEFAULT 'delivering',
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  FOREIGN KEY(supplier_id) REFERENCES suppliers(supplier_id),
  FOREIGN KEY(admin_id) REFERENCES users(user_id)
);
CREATE TABLE IF NOT EXISTS delivery_details (
  delivery_details_id INT AUTO_INCREMENT PRIMARY KEY,
  delivery_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity_received INT NOT NULL,
  unit_cost DECIMAL(12,2) NOT NULL,
  discount_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  subtotal DECIMAL(12,2) NOT NULL,
  FOREIGN KEY(delivery_id) REFERENCES deliveries(delivery_id),
  FOREIGN KEY(product_id) REFERENCES products(product_id)
);
CREATE TABLE IF NOT EXISTS audit_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  action VARCHAR(100) NOT NULL,
  details TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(user_id) REFERENCES users(user_id)
);
INSERT IGNORE INTO categories(category_id,category_name) VALUES
  (1,'Tires & Wheels'),
  (2,'Engine Parts'),
  (3,'Oils & Lubricants'),
  (4,'Chains & Sprockets'),
  (5,'Brake Parts'),
  (6,'Accessories'),
  (7,'Others');
INSERT INTO suppliers(supplier_name,contact_num,address)
  SELECT 'General Supplier','',''
  WHERE NOT EXISTS(SELECT 1 FROM suppliers);
