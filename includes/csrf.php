<?php
/**
 * CSRF (Cross-Site Request Forgery) protection helpers for all POST forms.
 */

require_once __DIR__ . '/functions.php';

/**
 * Returns the active CSRF token from session, generating one if missing.
 */
function generateCsrfToken(): string
{
    startAppSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Outputs a hidden form input containing the current CSRF token.
 */
function csrfField(): string
{
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Verifies submitted CSRF token in constant time using hash_equals().
 */
function verifyCsrfToken(?string $submittedToken): bool
{
    startAppSession();
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if ($sessionToken === '' || $submittedToken === null || $submittedToken === '') {
        return false;
    }
    return hash_equals($sessionToken, $submittedToken);
}

