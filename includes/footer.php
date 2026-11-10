<?php
/**
 * Shared storefront layout footer and global client-side script loading.
 * Optional variable before require:
 *   $pageScripts (array of relative JS paths inside assets/js/)
 */
$pageScripts = $pageScripts ?? [];
?>
    </main>

    <footer class="site-footer">
        <div class="container footer-grid">
            <div class="footer-col">
                <div class="footer-brand">
                    <a href="<?= e(BASE_URL) ?>/index.php" class="footer-logo-link" aria-label="<?= e(APP_NAME) ?> Home">
                        <img
                            src="<?= e(BASE_URL) ?>/assets/images/logo/logo.svg"
                            alt="<?= e(APP_NAME) ?>"
                            class="footer-logo-img"
                            width="150"
                            height="49"
                        >
                    </a>
                    <span><?= e(APP_SLOGAN) ?></span>
                </div>
                <p class="footer-desc">
                    Authorized smartphone retailer providing official warranty coverage, transparent pricing, and fast nationwide shipping.
                </p>
                <ul class="footer-contact">
                    <li><strong>Address:</strong> <?= e(APP_ADDRESS) ?></li>
                    <li><strong>Hotline:</strong> <?= e(APP_PHONE) ?> (08:00 - 21:30)</li>
                    <li><strong>Email:</strong> <?= e(APP_EMAIL) ?></li>
                </ul>
            </div>

            <div class="footer-col">
                <h3>Shop</h3>
                <ul class="footer-links">
                    <li><a href="<?= e(BASE_URL) ?>/index.php">Home</a></li>
                    <li><a href="<?= e(BASE_URL) ?>/products.php">All Smartphones</a></li>
                    <li><a href="<?= e(BASE_URL) ?>/products.php?promo=1">Hot Promotions</a></li>
                    <li><a href="<?= e(BASE_URL) ?>/contact.php">Contact &amp; Support</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h3>Brands</h3>
                <ul class="footer-links">
                    <li><a href="<?= e(BASE_URL) ?>/products.php?brand=apple">Apple iPhone</a></li>
                    <li><a href="<?= e(BASE_URL) ?>/products.php?brand=samsung">Samsung Galaxy</a></li>
                    <li><a href="<?= e(BASE_URL) ?>/products.php?brand=xiaomi">Xiaomi &amp; Redmi</a></li>
                    <li><a href="<?= e(BASE_URL) ?>/products.php?brand=oppo">OPPO Find &amp; Reno</a></li>
                    <li><a href="<?= e(BASE_URL) ?>/products.php?brand=realme">realme Smartphones</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h3>Customer Care</h3>
                <ul class="footer-links">
                    <li><a href="<?= e(BASE_URL) ?>/cart.php">Shopping Cart</a></li>
                    <li><a href="<?= e(BASE_URL) ?>/profile.php">Order Tracking &amp; Profile</a></li>
                    <li><a href="<?= e(BASE_URL) ?>/login.php">Sign In</a></li>
                    <li><a href="<?= e(BASE_URL) ?>/register.php">Create Account</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="container footer-bottom-inner">
                <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> Store. All rights reserved.</p>
                <p>Built with Vanilla HTML, CSS, JavaScript, PHP &amp; MySQL PDO.</p>
            </div>
        </div>
    </footer>

    <script src="<?= e(BASE_URL) ?>/assets/js/main.js" defer></script>
    <?php foreach ($pageScripts as $scriptPath): ?>
        <script src="<?= e(BASE_URL . '/' . ltrim($scriptPath, '/')) ?>" defer></script>
    <?php endforeach; ?>
</body>
</html>

