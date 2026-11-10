<?php
/**
 * Global application configuration for NovaPhone (Phone Sales Management System).
 * Defines store metadata, base paths, MySQL PDO credentials, upload limits, and pagination.
 */

// Set local timezone for accurate order timestamps
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Store branding and contact metadata (unaccented Vietnamese address)
define('APP_NAME', 'NovaPhone');
define('APP_SLOGAN', 'Genuine Smartphones - Great Prices Every Day');
define('APP_EMAIL', 'support@novaphone.vn');
define('APP_PHONE', '1900 6868');
define('APP_ADDRESS', '128 Nguyen Van Cu, District 5, Ho Chi Minh City');

// Base URL and filesystem root on XAMPP (matches the folder name inside htdocs)
define('BASE_URL', '/Phone-Sales');
define('ROOT_PATH', dirname(__DIR__));

// MySQL connection settings for XAMPP (localhost:3306, root, no password)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'phone_sales_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Product image upload settings (Admin)
define('UPLOAD_DIR', ROOT_PATH . '/assets/uploads/products/');
define('UPLOAD_URL', BASE_URL . '/assets/uploads/products/');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2 MB limit per image
define('ALLOWED_IMAGE_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
]);

// Business rules and pagination defaults
define('PRODUCTS_PER_PAGE', 8);
define('LOW_STOCK_THRESHOLD', 5); // Trigger low-stock warning on Admin Dashboard when stock_qty <= 5
