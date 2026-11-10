<?php
/**
 * MySQL database connection using PDO.
 * Shared across all storefront and admin pages.
 */

require_once __DIR__ . '/config.php';

/**
 * Returns a single shared PDO instance per HTTP request.
 *
 * @return PDO
 */
function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // Build DSN with explicit host, port, database name, and utf8mb4 encoding
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    // Enforce strict error handling, associative arrays, and native prepared statements
    $options = [
        // Throw PDOException on database errors so transactions can roll back safely
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        // Return associative arrays by default for clean, readable templates
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Disable emulated prepares so MySQL executes real prepared statements (SQLi defense)
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Log detailed error on the server without exposing credentials to the browser
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Unable to connect to the database. Please verify MySQL is running in XAMPP and check config/config.php.');
    }
}

// Expose $pdo directly for scripts that require this file
$pdo = getDBConnection();
