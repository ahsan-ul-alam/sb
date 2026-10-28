-- Run once in phpMyAdmin as root (Import tab, or paste into the SQL tab).
-- Creates the product_hub database and the MySQL user used by .env (DB_USERNAME=admin).

CREATE DATABASE IF NOT EXISTS `product_hub` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'admin'@'localhost' IDENTIFIED BY 'Ahsan2ahsan@';
CREATE USER IF NOT EXISTS 'admin'@'127.0.0.1' IDENTIFIED BY 'Ahsan2ahsan@';

GRANT ALL PRIVILEGES ON `product_hub`.* TO 'admin'@'localhost';
GRANT ALL PRIVILEGES ON `product_hub`.* TO 'admin'@'127.0.0.1';

FLUSH PRIVILEGES;
