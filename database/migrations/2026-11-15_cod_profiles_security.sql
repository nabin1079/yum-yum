-- ============================================================
-- Upgrade an EXISTING yum_yum_db to the cash-on-delivery,
-- seller-profile and security release. Keeps all existing data.
-- Safe to run more than once.
--
--   mysql -u root yum_yum_db < database/migrations/2026-11-15_cod_profiles_security.sql
--   (or import it in phpMyAdmin with yum_yum_db selected)
-- ============================================================

-- Seller profile and contact fields
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS phone        VARCHAR(30)  NULL DEFAULT NULL AFTER role,
    ADD COLUMN IF NOT EXISTS kitchen_name VARCHAR(120) NULL DEFAULT NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS area         VARCHAR(120) NULL DEFAULT NULL AFTER kitchen_name,
    ADD COLUMN IF NOT EXISTS bio          TEXT         NULL AFTER area;

-- Archive instead of delete for meals that have orders
ALTER TABLE food_items
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL AFTER is_available;

-- New reset-token format (selector + SHA-256 token). Old tokens are
-- short-lived, so the table is simply recreated.
DROP TABLE IF EXISTS password_resets;
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

-- Cash on delivery, one seller per order, delivery details
ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS seller_id        INT UNSIGNED NULL AFTER buyer_id,
    ADD COLUMN IF NOT EXISTS payment_method   ENUM('cod') NOT NULL DEFAULT 'cod' AFTER status,
    ADD COLUMN IF NOT EXISTS payment_status   ENUM('unpaid', 'paid') NOT NULL DEFAULT 'unpaid' AFTER payment_method,
    ADD COLUMN IF NOT EXISTS paid_at          DATETIME NULL DEFAULT NULL AFTER payment_status,
    ADD COLUMN IF NOT EXISTS received_at      DATETIME NULL DEFAULT NULL AFTER paid_at,
    ADD COLUMN IF NOT EXISTS cancelled_by     ENUM('buyer', 'seller') NULL DEFAULT NULL AFTER received_at,
    ADD COLUMN IF NOT EXISTS delivery_name    VARCHAR(200) NULL DEFAULT NULL AFTER cancelled_by,
    ADD COLUMN IF NOT EXISTS delivery_phone   VARCHAR(30)  NULL DEFAULT NULL AFTER delivery_name,
    ADD COLUMN IF NOT EXISTS delivery_address VARCHAR(255) NULL DEFAULT NULL AFTER delivery_phone,
    ADD COLUMN IF NOT EXISTS delivery_note    VARCHAR(255) NULL DEFAULT NULL AFTER delivery_address;

-- Fill seller_id for existing orders from their items
UPDATE orders o
SET o.seller_id = (
    SELECT MIN(fi.seller_id)
    FROM order_items oi
    JOIN food_items fi ON fi.id = oi.food_item_id
    WHERE oi.order_id = o.id
)
WHERE o.seller_id IS NULL;

-- Orders with no items cannot be assigned to a seller; remove them.
DELETE FROM orders WHERE seller_id IS NULL;

ALTER TABLE orders MODIFY seller_id INT UNSIGNED NOT NULL;

-- Existing delivered orders count as paid and received
UPDATE orders
SET payment_status = 'paid',
    paid_at = COALESCE(paid_at, updated_at),
    received_at = COALESCE(received_at, updated_at)
WHERE status = 'delivered' AND payment_status = 'unpaid';

ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_orders_seller (seller_id, created_at);

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
              AND CONSTRAINT_NAME = 'fk_orders_seller');
SET @sql := IF(@fk = 0,
    'ALTER TABLE orders ADD CONSTRAINT fk_orders_seller FOREIGN KEY (seller_id) REFERENCES users (id) ON DELETE RESTRICT',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Rate limiting
CREATE TABLE IF NOT EXISTS auth_attempts (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    action     VARCHAR(20)  NOT NULL,
    identifier VARCHAR(255) NOT NULL,
    ip         VARCHAR(45)  NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_auth_attempts_identifier (action, identifier, created_at),
    KEY idx_auth_attempts_ip (action, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contact form messages
CREATE TABLE IF NOT EXISTS contact_messages (
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
