<?php
/**
 * Shared storefront layout header (SEO meta tags, announcement bar, responsive navbar, flash alerts).
 * Expected optional variables before require:
 *   $pageTitle (string), $pageDescription (string), $activePage (string), $searchQuery (string)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';

startAppSession();

$pageTitle = $pageTitle ?? (APP_NAME . ' - ' . APP_SLOGAN);
$pageDescription = $pageDescription ?? 'Shop official smartphones from Apple, Samsung, Xiaomi, OPPO, and realme at NovaPhone with 0% installment and fast delivery.';
$activePage = $activePage ?? '';
$searchQuery = $searchQuery ?? ($_GET['q'] ?? '');

$user = currentUser();
$cartCount = getCartItemCount($pdo);
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/main.css">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <!-- Top announcement bar -->
    <div class="topbar">
        <div class="container topbar-inner">
            <div class="topbar-left">
                <span>100% Genuine Official Warranty</span>
                <span class="topbar-sep" aria-hidden="true">|</span>
                <span>0% Installment Available</span>
            </div>
            <div class="topbar-right">
                <span>Hotline: <strong><?= e(APP_PHONE) ?></strong></span>
                <span class="topbar-sep" aria-hidden="true">|</span>
                <span><?= e(APP_ADDRESS) ?></span>
            </div>
        </div>
    </div>

    <!-- Main sticky navigation header -->
    <header class="site-header">
        <div class="container header-inner">
            <a href="<?= e(BASE_URL) ?>/index.php" class="brand-logo" aria-label="<?= e(APP_NAME) ?> Home">
                <img
                    src="<?= e(BASE_URL) ?>/assets/images/logo/logo.svg"
                    alt="<?= e(APP_NAME) ?>"
                    class="brand-logo-img"
                    width="150"
                    height="49"
                >
            </a>

            <!-- Storefront keyword search -->
            <form class="header-search" action="<?= e(BASE_URL) ?>/products.php" method="get" role="search">
                <label class="sr-only" for="header-search-input">Search smartphones</label>
                <input
                    id="header-search-input"
                    type="search"
                    name="q"
                    value="<?= e($searchQuery) ?>"
                    placeholder="Search iPhone, Galaxy S25, Xiaomi, chip..."
                    autocomplete="off"
                >
                <button type="submit" aria-label="Search">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/>
                        <path d="M20 20L16.65 16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </form>

            <!-- Primary navigation links -->
            <nav class="main-nav" id="primary-nav" aria-label="Main navigation">
                <ul class="nav-list">
                    <li>
                        <a href="<?= e(BASE_URL) ?>/index.php" class="nav-link <?= $activePage === 'home' ? 'is-active' : '' ?>">Home</a>
                    </li>
                    <li>
                        <a href="<?= e(BASE_URL) ?>/products.php" class="nav-link <?= $activePage === 'products' ? 'is-active' : '' ?>">Phones</a>
                    </li>
                    <li>
                        <a href="<?= e(BASE_URL) ?>/products.php?promo=1" class="nav-link <?= $activePage === 'deals' ? 'is-active' : '' ?>">Deals</a>
                    </li>
                    <li>
                        <a href="<?= e(BASE_URL) ?>/contact.php" class="nav-link <?= $activePage === 'contact' ? 'is-active' : '' ?>">Contact</a>
                    </li>
                </ul>

                <div class="nav-actions">
                    <?php if ($user !== null): ?>
                        <?php if (($user['role'] ?? '') === 'admin'): ?>
                            <a href="<?= e(BASE_URL) ?>/admin/index.php" class="btn btn-outline btn-sm">Admin Panel</a>
                        <?php endif; ?>
                        <a href="<?= e(BASE_URL) ?>/profile.php" class="btn btn-ghost btn-sm <?= $activePage === 'profile' ? 'is-active' : '' ?>">
                            <?= e($user['full_name']) ?>
                        </a>
                        <form action="<?= e(BASE_URL) ?>/logout.php" method="post" class="inline-form">
                            <?= csrfField() ?>
                            <button type="submit" class="btn btn-outline btn-sm">Logout</button>
                        </form>
                    <?php else: ?>
                        <a href="<?= e(BASE_URL) ?>/login.php" class="btn btn-ghost btn-sm <?= $activePage === 'login' ? 'is-active' : '' ?>">Login</a>
                        <a href="<?= e(BASE_URL) ?>/register.php" class="btn btn-primary btn-sm <?= $activePage === 'register' ? 'is-active' : '' ?>">Register</a>
                    <?php endif; ?>
                </div>
            </nav>

            <!-- Cart shortcut + mobile menu button -->
            <div class="header-utilities">
                <a href="<?= e(BASE_URL) ?>/cart.php" class="cart-button <?= $activePage === 'cart' ? 'is-active' : '' ?>" aria-label="Shopping Cart">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6 6H21L19.5 14.5H8L6 6Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        <path d="M6 6L5 3H2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="9.5" cy="19" r="1.5" fill="currentColor"/>
                        <circle cx="17.5" cy="19" r="1.5" fill="currentColor"/>
                    </svg>
                    <span class="cart-label">Cart</span>
                    <span class="cart-badge" id="header-cart-count"><?= e((string) $cartCount) ?></span>
                </a>

                <button
                    type="button"
                    class="menu-toggle"
                    id="mobile-menu-toggle"
                    aria-label="Toggle navigation menu"
                    aria-expanded="false"
                    aria-controls="primary-nav"
                >
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M4 6H20M4 12H20M4 18H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
        </div>
    </header>

    <?php if ($flash !== null): ?>
        <div class="container flash-container">
            <div class="alert alert-<?= e($flash['type']) ?>" role="alert">
                <span><?= e($flash['message']) ?></span>
                <button type="button" class="alert-close" aria-label="Dismiss message">&times;</button>
            </div>
        </div>
    <?php endif; ?>

    <main id="main-content">

