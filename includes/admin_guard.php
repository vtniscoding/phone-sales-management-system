<?php
/**
 * Admin Role-Based Access Control Guard (NovaPhone).
 * Must be included at the top of every /admin/* page to block unauthenticated guests
 * and non-admin customers (403 Forbidden).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';

startAppSession();
$adminUser = currentUser();

if ($adminUser === null) {
    setFlash('warning', 'Please sign in with an administrator account to access the Admin Panel.');
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $safeTarget = sanitizeRedirectPath($requestUri, '');
    if ($safeTarget !== '' && str_starts_with($safeTarget, 'admin/')) {
        redirect('login.php?redirect=' . urlencode($safeTarget));
    }
    redirect('login.php');
}

if (($adminUser['role'] ?? '') !== 'admin') {
    http_response_code(403);
    $pageTitle = '403 Forbidden - ' . APP_NAME;
    $pageDescription = 'Administrator access required.';
    $activePage = '';
    require __DIR__ . '/header.php';
    ?>
    <section class="section">
        <div class="container">
            <div class="empty-state">
                <h1>403 &ndash; Access Denied</h1>
                <p>You do not have administrator permissions to view this area.</p>
                <a href="<?= e(BASE_URL) ?>/index.php" class="btn btn-primary">Return to Storefront</a>
            </div>
        </div>
    </section>
    <?php
    require __DIR__ . '/footer.php';
    exit;
}

