-- ══════════════════════════════════════════════
--  NOT NOTHING — Database Schema
--  Run this in your AlwaysData phpMyAdmin or
--  MySQL admin panel.
-- ══════════════════════════════════════════════

-- 1. Orders Table
CREATE TABLE IF NOT EXISTS `orders` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `paypal_order_id` VARCHAR(64)     NOT NULL,
  `payer_name`      VARCHAR(150)    DEFAULT '',
  `payer_email`     VARCHAR(150)    DEFAULT '',
  `buyer_email`     VARCHAR(150)    DEFAULT '',
  `format`          ENUM('ebook','paperback','hardcover') NOT NULL,
  `amount`          DECIMAL(8,2)    NOT NULL,
  `status`          VARCHAR(32)     NOT NULL DEFAULT 'COMPLETED',
  `created_at`      DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_paypal_order` (`paypal_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Admins Table
CREATE TABLE IF NOT EXISTS `admins` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `username`        VARCHAR(50)     NOT NULL UNIQUE,
  `password_hash`   VARCHAR(255)    NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default login: admin / admin123
INSERT INTO `admins` (`username`, `password_hash`) 
VALUES ('admin', '$2y$10$zYf.GgkIdtzkMCpRrkKBceffurKAqBLsovk2QRQtDpohTZ.2YQRLi')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- 3. Page Views (Analytics) Table
CREATE TABLE IF NOT EXISTS `page_views` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address`  VARCHAR(45)  NOT NULL,
  `country`     VARCHAR(100) DEFAULT 'Unknown',
  `visit_date`  DATE         NOT NULL,
  `created_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ip_date` (`ip_address`, `visit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
