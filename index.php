<?php
/**
 * Storefront Homepage (NovaPhone).
 * Displays hero slider, smartphone brands, featured products, hot promotions, and trust highlights.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = APP_NAME . ' - Official Smartphones & Best Deals';
$pageDescription = 'Explore flagship and mid-range smartphones from Apple, Samsung, Xiaomi, OPPO, and realme with official warranty and 0% installment at NovaPhone.';
$activePage = 'home';

// Load all brands for the brand strip
$brandsStmt = $pdo->query('SELECT id, name, slug, logo_path FROM brands ORDER BY id ASC');
$brands = $brandsStmt->fetchAll();

// Shared product projection selecting the default (or lowest-price) variant and primary image
$baseProductSelect = "
    SELECT
        p.id,
        p.name,
        p.slug,
        p.screen_spec,
        p.chip_spec,
        p.promo_label,
        b.name AS brand_name,
        b.slug AS brand_slug,
        pv.price AS display_price,
        pv.original_price AS display_original_price,
        (SELECT COALESCE(SUM(pv_sum.stock_qty), 0)
         FROM product_variants pv_sum
         WHERE pv_sum.product_id = p.id) AS total_stock,
        (SELECT pi.image_path
         FROM product_images pi
         WHERE pi.product_id = p.id
         ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
         LIMIT 1) AS primary_image,
        (SELECT pi.alt_text
         FROM product_images pi
         WHERE pi.product_id = p.id
         ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
         LIMIT 1) AS primary_alt
    FROM products p
    INNER JOIN brands b ON b.id = p.brand_id
    INNER JOIN product_variants pv ON pv.id = (
        SELECT pv_pick.id
        FROM product_variants pv_pick
        WHERE pv_pick.product_id = p.id
        ORDER BY pv_pick.is_default DESC, pv_pick.price ASC
        LIMIT 1
    )
    WHERE p.status = 'active'
";

// Fetch featured smartphones (up to 8)
$featuredStmt = $pdo->prepare($baseProductSelect . ' AND p.is_featured = 1 ORDER BY p.id ASC LIMIT :limit');
$featuredStmt->bindValue(':limit', 8, PDO::PARAM_INT);
$featuredStmt->execute();
$featuredProducts = $featuredStmt->fetchAll();

// Fetch discounted promotion smartphones (up to 4 highest percentage discounts)
$promoStmt = $pdo->prepare(
    $baseProductSelect . '
    AND pv.original_price IS NOT NULL
    AND pv.original_price > pv.price
    ORDER BY ((pv.original_price - pv.price) / pv.original_price) DESC, p.id ASC
    LIMIT :limit'
);
$promoStmt->bindValue(':limit', 4, PDO::PARAM_INT);
$promoStmt->execute();
$promoProducts = $promoStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner Slider -->
<section class="hero-section" aria-label="Featured promotions">
    <div class="container">
        <div class="hero-slider" id="hero-slider">
            <div class="hero-slides">
                <article class="hero-slide">
                    <div class="hero-content">
                        <span class="hero-badge">Flagship 2025/2026</span>
                        <h1>iPhone 16 Pro Max Titanium Power</h1>
                        <p>Experience the 6.9-inch Super Retina XDR 120Hz display, A18 Pro chip, and 0% installment plans with official 12-month VN/A warranty.</p>
                        <div class="hero-cta-group">
                            <a href="<?= e(BASE_URL) ?>/product-detail.php?slug=iphone-16-pro-max" class="btn hero-btn-light btn-lg">Explore iPhone 16 Pro Max</a>
                            <a href="<?= e(BASE_URL) ?>/products.php?brand=apple" class="btn hero-btn-ghost btn-lg">Shop All Apple</a>
                        </div>
                    </div>
                    <div class="hero-visual">
                        <div class="hero-spec-card">
                            <span class="badge badge-promo">0% Installment</span>
                            <div class="spec-price tabular-nums">33,990,000 VND</div>
                            <ul class="hero-spec-list">
                                <li>Chip: Apple A18 Pro (3nm)</li>
                                <li>Display: 6.9" OLED 120Hz</li>
                                <li>Camera: Triple 48MP + 5x Telephoto</li>
                                <li>Storage: 256GB / 512GB / 1TB</li>
                            </ul>
                        </div>
                    </div>
                </article>

                <article class="hero-slide">
                    <div class="hero-content">
                        <span class="hero-badge">Galaxy AI Flagship</span>
                        <h2>Samsung Galaxy S25 Ultra</h2>
                        <p>Built-in S Pen precision, 200MP quad camera system, and Snapdragon 8 Elite performance with exclusive launch savings up to 3M VND.</p>
                        <div class="hero-cta-group">
                            <a href="<?= e(BASE_URL) ?>/product-detail.php?slug=samsung-galaxy-s25-ultra" class="btn hero-btn-light btn-lg">View Galaxy S25 Ultra</a>
                            <a href="<?= e(BASE_URL) ?>/products.php?brand=samsung" class="btn hero-btn-ghost btn-lg">Shop All Samsung</a>
                        </div>
                    </div>
                    <div class="hero-visual">
                        <div class="hero-spec-card">
                            <span class="badge badge-discount">Save 3M VND</span>
                            <div class="spec-price tabular-nums">31,990,000 VND</div>
                            <ul class="hero-spec-list">
                                <li>Chip: Snapdragon 8 Elite</li>
                                <li>Display: 6.9" Dynamic AMOLED 2X</li>
                                <li>Camera: 200MP Main Sensor</li>
                                <li>Battery: 5000 mAh, 45W Charge</li>
                            </ul>
                        </div>
                    </div>
                </article>

                <article class="hero-slide">
                    <div class="hero-content">
                        <span class="hero-badge">Leica Night Optics</span>
                        <h2>Xiaomi 14T Pro &amp; 14 Ultra Series</h2>
                        <p>Co-engineered Leica optics, 144Hz AMOLED clarity, and 120W HyperCharge power built for creators and gamers.</p>
                        <div class="hero-cta-group">
                            <a href="<?= e(BASE_URL) ?>/product-detail.php?slug=xiaomi-14t-pro" class="btn hero-btn-light btn-lg">View Xiaomi 14T Pro</a>
                            <a href="<?= e(BASE_URL) ?>/products.php?brand=xiaomi" class="btn hero-btn-ghost btn-lg">Shop All Xiaomi</a>
                        </div>
                    </div>
                    <div class="hero-visual">
                        <div class="hero-spec-card">
                            <span class="badge badge-promo">120W HyperCharge</span>
                            <div class="spec-price tabular-nums">15,490,000 VND</div>
                            <ul class="hero-spec-list">
                                <li>Chip: Dimensity 9300+</li>
                                <li>Display: 6.67" 144Hz 1.5K AMOLED</li>
                                <li>Camera: 50MP Leica Optics</li>
                                <li>Memory: 12GB RAM / Up to 512GB</li>
                            </ul>
                        </div>
                    </div>
                </article>
            </div>

            <button type="button" class="hero-control hero-prev" aria-label="Previous slide">&#10094;</button>
            <button type="button" class="hero-control hero-next" aria-label="Next slide">&#10095;</button>

            <div class="hero-dots" role="tablist" aria-label="Slide selector">
                <button type="button" class="hero-dot is-active" aria-label="Slide 1" aria-selected="true"></button>
                <button type="button" class="hero-dot" aria-label="Slide 2" aria-selected="false"></button>
                <button type="button" class="hero-dot" aria-label="Slide 3" aria-selected="false"></button>
            </div>
        </div>
    </div>
</section>

<!-- Shop by Brand -->
<section class="section" aria-labelledby="brands-heading">
    <div class="container">
        <div class="section-header">
            <div>
                <h2 class="section-title" id="brands-heading">Browse by Brand</h2>
                <p class="section-subtitle">Official smartphones from leading global manufacturers.</p>
            </div>
            <a href="<?= e(BASE_URL) ?>/products.php" class="btn btn-outline btn-sm">View All Phones</a>
        </div>

        <div class="brand-grid">
            <?php foreach ($brands as $brand): ?>
                <a href="<?= e(BASE_URL) ?>/products.php?brand=<?= e(urlencode($brand['slug'])) ?>" class="brand-card">
                    <img
                        src="<?= e(resolveImageUrl($brand['logo_path'], 'assets/images/products/phone-placeholder.svg')) ?>"
                        alt="<?= e($brand['name']) ?> official smartphones"
                        width="120"
                        height="36"
                    >
                    <span><?= e($brand['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Featured Smartphones -->
<section class="section" aria-labelledby="featured-heading">
    <div class="container">
        <div class="section-header">
            <div>
                <h2 class="section-title" id="featured-heading">Featured Smartphones</h2>
                <p class="section-subtitle">Top-rated flagship and mid-range phones picked by NovaPhone.</p>
            </div>
            <a href="<?= e(BASE_URL) ?>/products.php" class="btn btn-outline btn-sm">Explore Catalog</a>
        </div>

        <?php if (!empty($featuredProducts)): ?>
            <div class="product-grid">
                <?php foreach ($featuredProducts as $product): ?>
                    <?php require __DIR__ . '/includes/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>No featured smartphones are currently available.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Hot Promotions -->
<section class="section" aria-labelledby="deals-heading">
    <div class="container">
        <div class="section-header">
            <div>
                <h2 class="section-title" id="deals-heading">Hot Deals &amp; Promotions</h2>
                <p class="section-subtitle">Limited-time price drops on best-selling models.</p>
            </div>
            <a href="<?= e(BASE_URL) ?>/products.php?promo=1" class="btn btn-primary btn-sm">See All Deals</a>
        </div>

        <?php if (!empty($promoProducts)): ?>
            <div class="product-grid">
                <?php foreach ($promoProducts as $product): ?>
                    <?php require __DIR__ . '/includes/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>New promotional offers will be announced soon.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Store Trust Highlights -->
<section class="section" aria-labelledby="trust-heading">
    <div class="container">
        <div class="section-header">
            <div>
                <h2 class="section-title" id="trust-heading">Why Buy at <?= e(APP_NAME) ?>?</h2>
                <p class="section-subtitle">Transparent service designed for peace of mind.</p>
            </div>
        </div>

        <div class="trust-grid">
            <div class="trust-item">
                <h3>100% Genuine Products</h3>
                <p>Sealed devices with full official manufacturer warranty across Vietnam.</p>
            </div>
            <div class="trust-item">
                <h3>30-Day Exchange</h3>
                <p>Free 1-to-1 replacement within 30 days for any hardware defect.</p>
            </div>
            <div class="trust-item">
                <h3>0% Installment Plans</h3>
                <p>Flexible monthly payments via credit cards or partner financing.</p>
            </div>
            <div class="trust-item">
                <h3>Fast Local Delivery</h3>
                <p>Same-day courier in Ho Chi Minh City and Hanoi, tracked nationwide.</p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

