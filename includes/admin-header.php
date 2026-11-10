<?php
/**
 * Shared Admin Panel Layout Header & Sidebar (NovaPhone).
 * Expected variables before require:
 *   $adminTitle (string), $adminActive (string), $adminUser (array from admin_guard.php)
 */

$adminTitle = $adminTitle ?? ('Admin Panel - ' . APP_NAME);
$adminActive = $adminActive ?? '';
$flash = getFlash();

// Quick badge counts for sidebar awareness
$unreadMsgCount = 0;
$pendingOrderCount = 0;
try {
    $unreadMsgCount = (int) $pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
    $pendingOrderCount = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();
} catch (PDOException $e) {
    error_log('Admin sidebar count query failed: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($adminTitle) ?> &ndash; <?= e(APP_NAME) ?> Admin</title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/main.css">
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/admin.css">
</head>
<body class="admin-body">
    <div class="admin-shell">
        <aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
            <div class="admin-sidebar-brand">
                <a href="<?= e(BASE_URL) ?>/admin/index.php" class="footer-logo-link" aria-label="<?= e(APP_NAME) ?> Admin Home">
                    <img src="<?= e(BASE_URL) ?>/assets/images/logo/logo.svg" alt="<?= e(APP_NAME) ?>" class="footer-logo-img" width="140" height="46">
                </a>
                <span class="admin-portal-tag">Admin Portal</span>
            </div>

            <nav class="admin-nav">
                <a href="<?= e(BASE_URL) ?>/admin/index.php" class="admin-nav-link <?= $adminActive === 'dashboard' ? 'is-active' : '' ?>">
                    <span>Dashboard</span>
                </a>
                <a href="<?= e(BASE_URL) ?>/admin/brands.php" class="admin-nav-link <?= $adminActive === 'brands' ? 'is-active' : '' ?>">
                    <span>Brands</span>
                </a>
                <a href="<?= e(BASE_URL) ?>/admin/products.php" class="admin-nav-link <?= $adminActive === 'products' ? 'is-active' : '' ?>">
                    <span>Products &amp; Variants</span>
                </a>
                <a href="<?= e(BASE_URL) ?>/admin/inventory.php" class="admin-nav-link <?= $adminActive === 'inventory' ? 'is-active' : '' ?>">
                    <span>Inventory</span>
                </a>
                <a href="<?= e(BASE_URL) ?>/admin/orders.php" class="admin-nav-link <?= $adminActive === 'orders' ? 'is-active' : '' ?>">
                    <span>Orders</span>
                    <?php if ($pendingOrderCount > 0): ?>
                        <span class="admin-nav-pill"><?= e((string) $pendingOrderCount) ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= e(BASE_URL) ?>/admin/customers.php" class="admin-nav-link <?= $adminActive === 'customers' ? 'is-active' : '' ?>">
                    <span>Customers</span>
                </a>
                <a href="<?= e(BASE_URL) ?>/admin/messages.php" class="admin-nav-link <?= $adminActive === 'messages' ? 'is-active' : '' ?>">
                    <span>Messages</span>
                    <?php if ($unreadMsgCount > 0): ?>
                        <span class="admin-nav-pill"><?= e((string) $unreadMsgCount) ?></span>
                    <?php endif; ?>
                </a>
            </nav>

            <div class="admin-sidebar-footer">
                <a href="<?= e(BASE_URL) ?>/index.php" class="btn btn-outline btn-sm btn-block">View Storefront</a>
                <form action="<?= e(BASE_URL) ?>/logout.php" method="post">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-ghost btn-sm btn-block admin-logout-btn">Sign Out</button>
                </form>
            </div>
        </aside>

        <div class="admin-workspace">
            <header class="admin-topbar">
                <div class="admin-topbar-left">
                    <button
                        type="button"
                        class="menu-toggle admin-menu-toggle"
                        id="admin-sidebar-toggle"
                        aria-label="Toggle admin sidebar"
                        aria-expanded="false"
                        aria-controls="admin-sidebar"
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 6H20M4 12H20M4 18H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                    <h1 class="admin-page-title"><?= e($adminTitle) ?></h1>
                </div>

                <div class="admin-topbar-user">
                    <span class="badge badge-promo">Admin</span>
                    <strong><?= e($adminUser['full_name']) ?></strong>
                </div>
            </header>

            <main class="admin-main">
                <?php if ($flash !== null): ?>
                    <div class="alert alert-<?= e($flash['type']) ?>" role="alert">
                        <span><?= e($flash['message']) ?></span>
                        <button type="button" class="alert-close" aria-label="Dismiss message">&times;</button>
                    </div>
                <?php endif; ?>

