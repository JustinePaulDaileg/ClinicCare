-- ============================================================
-- PayMongo Integration Tables
-- Run this against your cliniccares database
-- ============================================================

-- Stores every PayMongo payment link/transaction tied to a billing invoice
CREATE TABLE IF NOT EXISTS `paymongo_transactions` (
    `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `billing_id`           INT UNSIGNED NOT NULL,
    `paymongo_link_id`     VARCHAR(100)  NOT NULL COMMENT 'PayMongo link resource ID (link_xxx)',
    `paymongo_payment_id`  VARCHAR(100)  DEFAULT NULL COMMENT 'PayMongo payment resource ID once paid',
    `reference_number`     VARCHAR(100)  DEFAULT NULL COMMENT 'PayMongo reference number shown to customer',
    `amount`               DECIMAL(12,2) NOT NULL,
    `currency`             CHAR(3)       NOT NULL DEFAULT 'PHP',
    `status`               VARCHAR(50)   NOT NULL DEFAULT 'pending'
                           COMMENT 'pending | awaiting_payment_method | paid | cancelled | unpaid',
    `checkout_url`         TEXT          DEFAULT NULL,
    `description`          TEXT          DEFAULT NULL,
    `created_by`           INT UNSIGNED  DEFAULT NULL COMMENT 'user_id who triggered the payment',
    `paid_at`              DATETIME      DEFAULT NULL,
    `created_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_billing_id`        (`billing_id`),
    INDEX `idx_paymongo_link_id`  (`paymongo_link_id`),
    INDEX `idx_reference_number`  (`reference_number`),
    INDEX `idx_status`            (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stores raw webhook payloads for debugging / audit
CREATE TABLE IF NOT EXISTS `paymongo_webhook_logs` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_type`  VARCHAR(100) DEFAULT NULL,
    `payload`     LONGTEXT     DEFAULT NULL,
    `received_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_event_type` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optionally add 'paymongo' to payment_method ENUM if your billing table uses ENUM
-- (Skip if payment_method is already VARCHAR)
-- ALTER TABLE `billing` MODIFY COLUMN `payment_method` 
--   ENUM('cash','gcash','paymaya','card','bank_transfer','insurance','paymongo')
--   DEFAULT NULL;
