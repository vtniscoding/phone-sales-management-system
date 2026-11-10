<?php
/**
 * Account Logout Endpoint (NovaPhone).
 * Processes CSRF-protected POST logout requests (and provides a confirmation fallback for direct GET visits),
 * clears authenticated session state, regenerates the session ID, and redirects cleanly.
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

startAppSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Invalid logout request. Please try again.');
        redirect('index.php');
    }

    // Clear authenticated user state and rotate session ID
    unset($_SESSION['user']);
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    setFlash('info', 'You have been signed out successfully.');
    redirect('login.php');
}

// If an unauthenticated visitor hits /logout.php via GET, send them straight to login
if (currentUser() === null) {
    redirect('login.php');
}

$pageTitle = 'Sign Out - ' . APP_NAME;
$pageDescription = 'Confirm signing out of your NovaPhone account.';
$activePage = '';

require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
    <div class="container">
        <div class="auth-card auth-card-narrow">
            <div class="auth-card-header">
                <h1>Sign Out</h1>
                <p>Are you sure you want to sign out of your NovaPhone account?</p>
            </div>
            <form action="<?= e(BASE_URL) ?>/logout.php" method="post" class="logout-confirm-actions">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-primary">Yes, Sign Out</button>
                <a href="<?= e(BASE_URL) ?>/index.php" class="btn btn-outline">Cancel</a>
            </form>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

