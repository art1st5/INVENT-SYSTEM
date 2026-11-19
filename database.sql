-- =============================================================================
-- database.sql — Database schema for Matet Inventory System
--
-- PURPOSE:
--   This file creates the entire database structure from scratch.
--   Run it once in phpMyAdmin (Import tab) or in the MySQL command line.
--   It is safe to run multiple times — all CREATE statements use IF NOT EXISTS.
--
-- STRUCTURE OVERVIEW:
--   users              — Staff accounts (owner and assistant roles)
--   categories         — Product categories (e.g. Engine Parts, Brakes)
--   suppliers          — Supplier contact information
--   products           — Inventory items with pricing and stock levels
--   transactions       — Header record for each cash sale
--   product_transactions — Line items linking products to a sale transaction
--   deliveries         — Header record for each stock delivery from a supplier
--   delivery_details   — Line items linking products to a delivery
--   audit_logs         — Activity trail: who did what and when
-- =============================================================================

-- Create the database if it does not already exist.
-- utf8mb4 supports full Unicode including emoji and special characters.
CREATE DATABASE IF NOT EXISTS matet_inventory
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE matet_inventory;

-- =============================================================================
-- TABLE: users
-- Stores staff accounts. Each person who logs in has one row here.
--
-- role:   'owner'     — full access including user management and audit log
--         'assistant' — can record sales and deliveries but cannot manage users
-- active: set to 0 to disable an account without deleting it
-- user_pw: passwords are stored as bcrypt hashes (never plain text)
-- =============================================================================
CREATE TABLE IF NOT EXISTS users (
    user_id   INT AUTO_INCREMENT PRIMARY KEY,
    user_name VARCHAR(80)                                   NOT NULL UNIQUE,
    user_pw   VARCHAR(255)                                  NOT NULL,
    role      ENUM('owner', 'assistant', 'employee')      NOT NULL DEFAULT 'employee',
    active    TINYINT(1)                                    NOT NULL DEFAULT 1
);

-- =============================================================================
-- TABLE: categories
-- Product categories used to group inventory items.
-- Examples: Tires & Wheels, Engine Parts, Accessories
-- =============================================================================
CREATE TABLE IF NOT EXISTS categories (
    category_id   INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE, -- e.g. "Engine Parts"
    description   TEXT                          -- optional longer description
);

-- =============================================================================
-- TABLE: suppliers
-- Companies or individuals who supply products to the shop.
-- Each product is linked to one supplier.
-- =============================================================================
CREATE TABLE IF NOT EXISTS suppliers (
    supplier_id   INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(150) NOT NULL,
    contact_num   VARCHAR(40),  -- phone or mobile number
    address       TEXT          -- physical or mailing address
);

-- =============================================================================
-- TABLE: products
-- The core inventory table. Each row is one product/SKU.
--
-- selling_price:    the price charged to customers
-- product_cost:     what the shop paid for it (used to calculate profit)
-- product_discount: percentage discount applied at the time of sale
-- product_quantity: current units in stock (decreases on sale, increases on delivery)
-- reorder_level:    minimum quantity before the dashboard shows a "low stock" alert
-- active:           0 = archived (hidden from sales/deliveries), 1 = active
--
-- FOREIGN KEYS enforce referential integrity:
--   - You cannot assign a category_id that does not exist in categories
--   - You cannot assign a supplier_id that does not exist in suppliers
-- CHECK constraint ensures stock can never go below zero.
-- =============================================================================
CREATE TABLE IF NOT EXISTS products (
    product_id       INT AUTO_INCREMENT PRIMARY KEY,
    category_id      INT            NOT NULL,
    supplier_id      INT            NOT NULL,
    product_name     VARCHAR(160)   NOT NULL,
    selling_price    DECIMAL(12, 2) NOT NULL DEFAULT 0,
    product_cost     DECIMAL(12, 2) NOT NULL DEFAULT 0,
    product_discount DECIMAL(5, 2)  NOT NULL DEFAULT 0,  -- e.g. 10.00 = 10%
    product_quantity INT            NOT NULL DEFAULT 0,
    description      TEXT,
    reorder_level    INT            NOT NULL DEFAULT 5,
    image_url        VARCHAR(500),                        -- optional product photo URL
    active           TINYINT(1)     NOT NULL DEFAULT 1,
    FOREIGN KEY (category_id) REFERENCES categories (category_id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers (supplier_id),
    CHECK (product_quantity >= 0)                        -- prevent negative stock
);

-- =============================================================================
-- TABLE: transactions
-- One row per cash sale session. A sale may cover one product at a time.
-- The admin_id records which staff member processed the sale.
-- =============================================================================
CREATE TABLE IF NOT EXISTS transactions (
    transaction_id   INT AUTO_INCREMENT PRIMARY KEY,
    admin_id         INT            NULL,                                        -- NULL if the staff account was deleted
    transaction_date DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_discount   DECIMAL(12, 2) NOT NULL DEFAULT 0,
    total_amount     DECIMAL(12, 2) NOT NULL DEFAULT 0,
    FOREIGN KEY (admin_id) REFERENCES users (user_id) ON DELETE SET NULL
);

-- =============================================================================
-- TABLE: product_transactions
-- Line-item detail for each transaction. Links a product to a sale.
-- Prices and costs are recorded HERE at the time of sale so that
-- later changes to the product's price do not alter historical profit data.
-- =============================================================================
CREATE TABLE IF NOT EXISTS product_transactions (
    product_transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id         INT            NOT NULL,
    product_id             INT            NOT NULL,
    selling_price          DECIMAL(12, 2) NOT NULL, -- price at time of sale (snapshot)
    product_quantity       INT            NOT NULL,
    product_cost           DECIMAL(12, 2) NOT NULL, -- cost at time of sale (snapshot)
    discount_amount        DECIMAL(12, 2) NOT NULL DEFAULT 0,
    subamount              DECIMAL(12, 2) NOT NULL, -- (selling_price × qty) − discount
    FOREIGN KEY (transaction_id) REFERENCES transactions (transaction_id),
    FOREIGN KEY (product_id)     REFERENCES products (product_id)
);

-- =============================================================================
-- TABLE: deliveries
-- One row per supplier delivery. Tracks whether stock has arrived or is pending.
-- delivery_status:
--   'delivering' — ordered but not yet received at the shop
--   'received'   — stock has arrived and been counted into inventory
--   'cancelled'  — delivery was cancelled
-- =============================================================================
CREATE TABLE IF NOT EXISTS deliveries (
    delivery_id     INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id     INT                                       NULL,              -- optional; NULL if not specified
    admin_id        INT                                       NULL,              -- NULL if the staff account was deleted
    date_received   DATETIME                                  NULL,     -- NULL until marked as received
    delivery_date   DATE                                      NOT NULL,
    delivery_status ENUM('delivering', 'received', 'cancelled') NOT NULL DEFAULT 'delivering',
    total_amount    DECIMAL(12, 2)                            NOT NULL DEFAULT 0,
    FOREIGN KEY (supplier_id) REFERENCES suppliers (supplier_id),
    FOREIGN KEY (admin_id)    REFERENCES users (user_id) ON DELETE SET NULL
);

-- =============================================================================
-- TABLE: delivery_details
-- Line-item detail for each delivery. Links a product to a delivery record.
-- unit_cost is recorded here so the product's stored cost can be updated
-- each time new stock arrives at a potentially different price.
-- =============================================================================
CREATE TABLE IF NOT EXISTS delivery_details (
    delivery_details_id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_id         INT            NOT NULL,
    product_id          INT            NOT NULL,
    quantity_received   INT            NOT NULL,
    unit_cost           DECIMAL(12, 2) NOT NULL,  -- cost per unit on this delivery
    discount_price      DECIMAL(12, 2) NOT NULL DEFAULT 0,
    subtotal            DECIMAL(12, 2) NOT NULL,  -- unit_cost × quantity_received
    FOREIGN KEY (delivery_id) REFERENCES deliveries (delivery_id),
    FOREIGN KEY (product_id)  REFERENCES products (product_id)
);

-- =============================================================================
-- TABLE: audit_logs
-- Records every important action performed in the system.
-- Provides a complete, tamper-evident history of who did what and when.
-- Only the owner role can view this log (page=audit in index.php).
-- =============================================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    log_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NULL,                   -- NULL if the staff account was deleted
    action     VARCHAR(100) NOT NULL,
    details    TEXT,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE SET NULL
);

-- =============================================================================
-- SEED DATA — Default records inserted once on first setup
-- =============================================================================

-- Default product categories for a motorcycle parts shop.
-- INSERT IGNORE skips the row if the category_id already exists
-- so running this file twice does not create duplicates.
INSERT IGNORE INTO categories (category_id, category_name) VALUES
    (1, 'Tires & Wheels'),
    (2, 'Engine Parts'),
    (3, 'Oils & Lubricants'),
    (4, 'Chains & Sprockets'),
    (5, 'Brake Parts'),
    (6, 'Accessories'),
    (7, 'Others');  -- catch-all for products that do not fit a specific category

-- Insert a placeholder "General Supplier" only if no suppliers exist yet.
-- This ensures the suppliers table is never empty when the first product is added.
INSERT INTO suppliers (supplier_name, contact_num, address)
    SELECT 'General Supplier', '', ''
    WHERE NOT EXISTS (SELECT 1 FROM suppliers);
