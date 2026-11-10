<?php
/**
 * Localhost helper script to generate valid bcrypt hashes via PHP password_hash().
 * Restricted to loopback addresses for local XAMPP development only.
 * URL: http://localhost/Phone-Sales/database/init_test_passwords.php
 */

$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remoteAddr, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Access denied: This setup script can only be run from localhost.');
}

require_once dirname(__DIR__) . '/config/db.php';

// Test accounts seeded in database/seed.sql
$testAccounts = [
    'admin@novaphone.vn'  => 'Admin@123',
    'khach1@novaphone.vn' => 'Customer@123',
    'khach2@novaphone.vn' => 'Customer@123',
];

try {
    $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE email = :email');
    $updated = [];

    foreach ($testAccounts as $email => $plainPassword) {
        // Generate secure bcrypt password hash using PHP native password_hash()
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $stmt->execute([
            ':hash'  => $hash,
            ':email' => $email,
        ]);
        $updated[] = [
            'email' => $email,
            'pass'  => $plainPassword,
            'rows'  => $stmt->rowCount(),
        ];
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<h2>PDO connection & test account passwords initialized successfully!</h2>';
    echo '<ul>';
    foreach ($updated as $item) {
        echo '<li><strong>' . htmlspecialchars($item['email'], ENT_QUOTES, 'UTF-8') . '</strong> — Password: <code>' . htmlspecialchars($item['pass'], ENT_QUOTES, 'UTF-8') . '</code> (Updated)</li>';
    }
    echo '</ul>';
    echo '<p>You can use these credentials to test authentication in Stage 3.</p>';
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Failed to initialize test passwords: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
