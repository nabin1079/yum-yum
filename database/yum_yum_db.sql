-- ============================================================
-- Yum-Yum database: full schema for a NEW installation.
-- Running this file DROPS and recreates every table.
-- To upgrade an existing database without losing data, run
-- database/migrations/2026-11-15_cod_profiles_security.sql instead.
--
-- On shared hosting the database is usually created in the control
-- panel; in that case remove the CREATE DATABASE and USE lines and
-- import the rest into the database you created.
-- ============================================================

CREATE DATABASE IF NOT EXISTS yum_yum_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE yum_yum_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS contact_messages;
DROP TABLE IF EXISTS auth_attempts;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS food_items;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- users — auth/register.php, auth/login.php, seller/profile.php
-- kitchen_name, area and bio are shown on the public seller page.
-- ------------------------------------------------------------
CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name    VARCHAR(100) NOT NULL,
    last_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,             -- password_hash() output
    role          ENUM('buyer', 'seller') NOT NULL DEFAULT 'buyer',
    phone         VARCHAR(30)  NULL DEFAULT NULL,
    kitchen_name  VARCHAR(120) NULL DEFAULT NULL,
    area          VARCHAR(120) NULL DEFAULT NULL,
    bio           TEXT         NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- password_resets — auth/forgot-password.php, auth/reset-password.php
-- The emailed link carries a public selector (to find the row) and a
-- secret token; only the SHA-256 of the token is stored.
-- ------------------------------------------------------------
CREATE TABLE password_resets (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    selector   CHAR(16)     NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_resets_selector (selector),
    KEY idx_password_resets_user (user_id),
    CONSTRAINT fk_password_resets_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- categories — menu filter bar and seller add/edit dropdown.
-- ------------------------------------------------------------
CREATE TABLE categories (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- food_items — seller/food_add.php, food_edit.php, food_toggle.php
-- image_path holds only the filename inside uploads/food/.
-- A meal that already appears in orders is archived (deleted_at)
-- instead of deleted, so order history keeps its name and picture.
-- ------------------------------------------------------------
CREATE TABLE food_items (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    seller_id    INT UNSIGNED NOT NULL,
    category_id  INT UNSIGNED NOT NULL,
    name         VARCHAR(200) NOT NULL,
    description  TEXT NOT NULL,
    price        DECIMAL(10, 2) NOT NULL,
    image_path   VARCHAR(255) NULL DEFAULT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at   DATETIME NULL DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_food_items_seller (seller_id, created_at),
    KEY idx_food_items_menu (is_available, category_id, created_at),
    CONSTRAINT chk_food_items_price CHECK (price > 0),
    CONSTRAINT fk_food_items_seller
        FOREIGN KEY (seller_id) REFERENCES users (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_food_items_category
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- cart — buyer/cart_add.php, cart_update.php, cart_remove.php
-- The UNIQUE key is required: cart_add.php relies on
-- INSERT ... ON DUPLICATE KEY UPDATE to increment quantity.
-- ------------------------------------------------------------
CREATE TABLE cart (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    buyer_id     INT UNSIGNED NOT NULL,
    food_item_id INT UNSIGNED NOT NULL,
    quantity     INT UNSIGNED NOT NULL DEFAULT 1,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cart_buyer_item (buyer_id, food_item_id),
    KEY idx_cart_food_item (food_item_id),
    CONSTRAINT chk_cart_quantity CHECK (quantity > 0),
    CONSTRAINT fk_cart_buyer
        FOREIGN KEY (buyer_id) REFERENCES users (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cart_food_item
        FOREIGN KEY (food_item_id) REFERENCES food_items (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- orders — buyer/checkout.php, buyer/orders.php, seller/orders.php
-- One order per seller. Payment is cash on delivery: the seller marks
-- the order paid when the cash is received, and the buyer confirms
-- that the food was received.
-- ------------------------------------------------------------
CREATE TABLE orders (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    buyer_id         INT UNSIGNED NOT NULL,
    seller_id        INT UNSIGNED NOT NULL,
    total_amount     DECIMAL(10, 2) NOT NULL,
    status           ENUM('pending', 'confirmed', 'preparing',
                          'out_for_delivery', 'delivered', 'cancelled')
                     NOT NULL DEFAULT 'pending',
    payment_method   ENUM('cod') NOT NULL DEFAULT 'cod',
    payment_status   ENUM('unpaid', 'paid') NOT NULL DEFAULT 'unpaid',
    paid_at          DATETIME NULL DEFAULT NULL,
    received_at      DATETIME NULL DEFAULT NULL,
    cancelled_by     ENUM('buyer', 'seller') NULL DEFAULT NULL,
    delivery_name    VARCHAR(200) NULL DEFAULT NULL,
    delivery_phone   VARCHAR(30)  NULL DEFAULT NULL,
    delivery_address VARCHAR(255) NULL DEFAULT NULL,
    delivery_note    VARCHAR(255) NULL DEFAULT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_orders_buyer (buyer_id, created_at),
    KEY idx_orders_seller (seller_id, created_at),
    KEY idx_orders_status (status),
    CONSTRAINT chk_orders_total CHECK (total_amount >= 0),
    CONSTRAINT fk_orders_buyer
        FOREIGN KEY (buyer_id) REFERENCES users (id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_orders_seller
        FOREIGN KEY (seller_id) REFERENCES users (id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- order_items — price_at_purchase locks the price at checkout.
-- RESTRICT on food_item_id keeps order history intact.
-- ------------------------------------------------------------
CREATE TABLE order_items (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id          INT UNSIGNED NOT NULL,
    food_item_id      INT UNSIGNED NOT NULL,
    quantity          INT UNSIGNED NOT NULL,
    price_at_purchase DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_order_items_order (order_id),
    KEY idx_order_items_food_item (food_item_id),
    CONSTRAINT chk_order_items_quantity CHECK (quantity > 0),
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_order_items_food_item
        FOREIGN KEY (food_item_id) REFERENCES food_items (id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- auth_attempts — rate limiting for login and password reset
-- ------------------------------------------------------------
CREATE TABLE auth_attempts (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    action     VARCHAR(20)  NOT NULL,          -- 'login' or 'reset'
    identifier VARCHAR(255) NOT NULL,          -- the email address entered
    ip         VARCHAR(45)  NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_auth_attempts_identifier (action, identifier, created_at),
    KEY idx_auth_attempts_ip (action, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- contact_messages — contact.php saves every message here and then
-- emails it to the team inbox (CONTACT_TO).
-- ------------------------------------------------------------
CREATE TABLE contact_messages (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NULL DEFAULT NULL,
    name       VARCHAR(200) NOT NULL,
    email      VARCHAR(255) NOT NULL,
    subject    VARCHAR(50)  NOT NULL,
    message    TEXT         NOT NULL,
    ip         VARCHAR(45)  NOT NULL,
    emailed    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contact_messages_created (created_at),
    CONSTRAINT fk_contact_messages_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Seed data: categories (required — sellers must pick one)
-- ------------------------------------------------------------
INSERT INTO categories (name) VALUES
    ('Vegetarian'),
    ('Non-Vegetarian'),
    ('Vegan'),
    ('Rice & Biryani'),
    ('Breads'),
    ('Snacks'),
    ('Desserts'),
    ('Beverages');
