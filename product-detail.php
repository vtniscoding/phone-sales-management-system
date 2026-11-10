<?php
/**
 * Smartphone Product Detail Page (NovaPhone).
 * Displays product gallery, interactive variant selector (RAM/Storage/Color/Price/Stock/SKU),
 * full hardware specification table, Add-to-Cart form, and related smartphones.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$idParam = (int) ($_GET['id'] ?? 0);

$product = null;
if ($slug !== '') {
    $stmt = $pdo->prepare(
        "SELECT p.*, b.name AS brand_name, b.slug AS brand_slug
         FROM products p
         INNER JOIN brands b ON b.id = p.brand_id
         WHERE p.slug = :slug AND p.status = 'active'
         LIMIT 1"
    );
    $stmt->execute([':slug' => $slug]);
    $product = $stmt->fetch();
} elseif ($idParam > 0) {
    $stmt = $pdo->prepare(
        "SELECT p.*, b.name AS brand_name, b.slug AS brand_slug
         FROM products p
         INNER JOIN brands b ON b.id = p.brand_id
         WHERE p.id = :id AND p.status = 'active'
         LIMIT 1"
    );
    $stmt->execute([':id' => $idParam]);
    $product = $stmt->fetch();
}

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Smartphone Not Found - ' . APP_NAME;
    $pageDescription = 'The requested smartphone could not be found in the NovaPhone catalog.';
    $activePage = 'products';
    require_once __DIR__ . '/includes/header.php';
    ?>
    <section class="section">
        <div class="container">
            <div class="empty-state">
                <h1>Smartphone Not Found</h1>
                <p>The product you are looking for may have been removed or is currently unavailable.</p>
                <a href="<?= e(BASE_URL) ?>/products.php" class="btn btn-primary">Browse All Smartphones</a>
            </div>
        </div>
    </section>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$productId = (int) $product['id'];

// Load product images ordered with primary image first
$imagesStmt = $pdo->prepare(
    'SELECT id, variant_id, image_path, alt_text, is_primary
     FROM product_images
     WHERE product_id = :product_id
     ORDER BY is_primary DESC, sort_order ASC, id ASC'
);
$imagesStmt->execute([':product_id' => $productId]);
$rawImages = $imagesStmt->fetchAll();

$images = [];
foreach ($rawImages as $img) {
    $images[] = [
        'id'         => (int) $img['id'],
        'variant_id' => $img['variant_id'] !== null ? (int) $img['variant_id'] : null,
        'url'        => resolveImageUrl($img['image_path']),
        'alt'        => $img['alt_text'] !== '' ? $img['alt_text'] : ($product['name'] . ' smartphone'),
    ];
}
if (empty($images)) {
    $images[] = [
        'id'         => 0,
        'variant_id' => null,
        'url'        => BASE_URL . '/assets/images/products/phone-placeholder.svg',
        'alt'        => $product['name'] . ' smartphone',
    ];
}

// Load all variants for this smartphone
$variantsStmt = $pdo->prepare(
    'SELECT id, ram, storage, color_name, color_hex, price, original_price, stock_qty, sku, is_default
     FROM product_variants
     WHERE product_id = :product_id
     ORDER BY is_default DESC, price ASC, id ASC'
);
$variantsStmt->execute([':product_id' => $productId]);
$rawVariants = $variantsStmt->fetchAll();

// Map image URL per variant_id when variant-specific images exist
$variantImageMap = [];
foreach ($images as $img) {
    if ($img['variant_id'] !== null && !isset($variantImageMap[$img['variant_id']])) {
        $variantImageMap[$img['variant_id']] = $img;
    }
}

$variants = [];
foreach ($rawVariants as $v) {
    $vId = (int) $v['id'];
    $price = (float) $v['price'];
    $origPrice = $v['original_price'] !== null ? (float) $v['original_price'] : null;
    $discount = calcDiscountPercent($price, $origPrice);

    $variants[] = [
        'id'             => $vId,
        'ram'            => (string) $v['ram'],
        'storage'        => (string) $v['storage'],
        'color_name'     => (string) $v['color_name'],
        'color_hex'      => (string) $v['color_hex'],
        'price'          => $price,
        'price_formatted'=> formatPrice($price),
        'original_price' => $origPrice,
        'original_formatted' => ($origPrice !== null && $origPrice > $price) ? formatPrice($origPrice) : '',
        'discount_percent' => $discount,
        'stock_qty'      => (int) $v['stock_qty'],
        'sku'            => (string) $v['sku'],
        'is_default'     => (int) $v['is_default'] === 1,
        'image_url'      => $variantImageMap[$vId]['url'] ?? $images[0]['url'],
        'image_alt'      => $variantImageMap[$vId]['alt'] ?? $images[0]['alt'],
    ];
}

// Choose initial selected variant (prefer default variant, or first available)
$selectedVariant = $variants[0] ?? null;
foreach ($variants as $v) {
    if ($v['is_default']) {
        $selectedVariant = $v;
        break;
    }
}

// Load up to 4 related active smartphones from the same brand
$relatedStmt = $pdo->prepare(
    "SELECT
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
       AND p.brand_id = :brand_id
       AND p.id <> :product_id
     ORDER BY p.is_featured DESC, p.id ASC
     LIMIT :limit"
);
$relatedStmt->bindValue(':brand_id', (int) $product['brand_id'], PDO::PARAM_INT);
$relatedStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
$relatedStmt->bindValue(':limit', 4, PDO::PARAM_INT);
$relatedStmt->execute();
$relatedProducts = $relatedStmt->fetchAll();

$pageTitle = $product['name'] . ' Official Price & Specs - ' . APP_NAME;
$pageDescription = $product['name'] . ' (' . $product['chip_spec'] . ', ' . $product['screen_spec'] . ') - ' . ($product['short_desc'] ?: 'Official smartphone at NovaPhone.');
$activePage = 'products';
$pageScripts = ['assets/js/product-detail.js'];

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-banner page-banner-compact">
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(BASE_URL) ?>/index.php">Home</a>
            <span aria-hidden="true">/</span>
            <a href="<?= e(BASE_URL) ?>/products.php">Phones</a>
            <span aria-hidden="true">/</span>
            <a href="<?= e(BASE_URL) ?>/products.php?brand=<?= e(urlencode($product['brand_slug'])) ?>"><?= e($product['brand_name']) ?></a>
            <span aria-hidden="true">/</span>
            <span><?= e($product['name']) ?></span>
        </nav>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="detail-grid">
            <!-- Product Gallery -->
            <div class="detail-gallery">
                <div class="detail-main-image-wrap">
                    <?php if (!empty($product['promo_label'])): ?>
                        <span class="badge badge-promo detail-promo-badge"><?= e($product['promo_label']) ?></span>
                    <?php endif; ?>
                    <img
                        id="detail-main-image"
                        src="<?= e($selectedVariant['image_url'] ?? $images[0]['url']) ?>"
                        alt="<?= e($selectedVariant['image_alt'] ?? $images[0]['alt']) ?>"
                        width="560"
                        height="420"
                    >
                </div>

                <?php if (count($images) > 1): ?>
                    <div class="detail-thumbs" role="list" aria-label="Product gallery thumbnails">
                        <?php foreach ($images as $idx => $img): ?>
                            <button
                                type="button"
                                class="detail-thumb-btn <?= $idx === 0 ? 'is-active' : '' ?>"
                                data-image-src="<?= e($img['url']) ?>"
                                data-image-alt="<?= e($img['alt']) ?>"
                                aria-label="View image <?= e((string) ($idx + 1)) ?>"
                            >
                                <img src="<?= e($img['url']) ?>" alt="<?= e($img['alt']) ?>" width="80" height="60">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Product Purchase Panel -->
            <div class="detail-info">
                <div class="detail-brand-row">
                    <a href="<?= e(BASE_URL) ?>/products.php?brand=<?= e(urlencode($product['brand_slug'])) ?>" class="product-brand-tag">
                        <?= e($product['brand_name']) ?>
                    </a>
                    <?php if ($selectedVariant !== null): ?>
                        <span class="detail-sku">SKU: <strong id="detail-sku"><?= e($selectedVariant['sku']) ?></strong></span>
                    <?php endif; ?>
                </div>

                <h1 class="detail-title"><?= e($product['name']) ?></h1>

                <?php if (!empty($product['short_desc'])): ?>
                    <p class="detail-short-desc"><?= e($product['short_desc']) ?></p>
                <?php endif; ?>

                <?php if ($selectedVariant !== null): ?>
                    <div class="detail-price-box tabular-nums">
                        <span class="detail-price-current" id="detail-price"><?= e($selectedVariant['price_formatted']) ?></span>
                        <span
                            class="detail-price-original <?= $selectedVariant['original_formatted'] === '' ? 'is-hidden' : '' ?>"
                            id="detail-original-price"
                        ><?= e($selectedVariant['original_formatted']) ?></span>
                        <span
                            class="badge badge-discount <?= $selectedVariant['discount_percent'] <= 0 ? 'is-hidden' : '' ?>"
                            id="detail-discount-badge"
                        >-<?= e((string) $selectedVariant['discount_percent']) ?>%</span>
                    </div>

                    <div class="detail-stock-row">
                        <?php
                        $stockQty = $selectedVariant['stock_qty'];
                        if ($stockQty <= 0) {
                            $stockClass = 'badge-out';
                            $stockText = 'Out of Stock';
                        } elseif ($stockQty <= LOW_STOCK_THRESHOLD) {
                            $stockClass = 'badge-low';
                            $stockText = 'Low Stock (' . $stockQty . ' left)';
                        } else {
                            $stockClass = 'badge-stock';
                            $stockText = 'In Stock (' . $stockQty . ' available)';
                        }
                        ?>
                        <span class="badge <?= e($stockClass) ?>" id="detail-stock-badge" data-low-threshold="<?= e((string) LOW_STOCK_THRESHOLD) ?>">
                            <?= e($stockText) ?>
                        </span>
                    </div>

                    <!-- Variant Options -->
                    <div class="variant-section">
                        <h2 class="variant-heading">Select Configuration &amp; Color</h2>
                        <div class="variant-options" id="variant-options" role="radiogroup" aria-label="Phone variants">
                            <?php foreach ($variants as $v): ?>
                                <?php $isSelected = ($v['id'] === $selectedVariant['id']); ?>
                                <button
                                    type="button"
                                    class="variant-btn <?= $isSelected ? 'is-selected' : '' ?> <?= $v['stock_qty'] <= 0 ? 'is-out' : '' ?>"
                                    data-variant-id="<?= e((string) $v['id']) ?>"
                                    aria-pressed="<?= $isSelected ? 'true' : 'false' ?>"
                                >
                                    <span class="variant-color-dot" style="background-color: <?= e($v['color_hex']) ?>;" aria-hidden="true"></span>
                                    <span class="variant-meta">
                                        <strong><?= e($v['ram'] . ' / ' . $v['storage']) ?> &ndash; <?= e($v['color_name']) ?></strong>
                                        <small class="tabular-nums">
                                            <?= e($v['price_formatted']) ?>
                                            <?= $v['stock_qty'] <= 0 ? ' (Out of stock)' : '' ?>
                                        </small>
                                    </span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Add to Cart Form -->
                    <form action="<?= e(BASE_URL) ?>/cart.php" method="post" class="add-to-cart-form" id="add-to-cart-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="variant_id" id="selected-variant-id" value="<?= e((string) $selectedVariant['id']) ?>">

                        <div class="qty-purchase-row">
                            <div class="qty-control">
                                <label for="detail-quantity" class="sr-only">Quantity</label>
                                <button type="button" class="qty-btn" id="qty-decrease" aria-label="Decrease quantity">&minus;</button>
                                <input
                                    type="number"
                                    id="detail-quantity"
                                    name="quantity"
                                    value="<?= $selectedVariant['stock_qty'] > 0 ? '1' : '0' ?>"
                                    min="<?= $selectedVariant['stock_qty'] > 0 ? '1' : '0' ?>"
                                    max="<?= e((string) max(1, $selectedVariant['stock_qty'])) ?>"
                                    <?= $selectedVariant['stock_qty'] <= 0 ? 'disabled' : '' ?>
                                >
                                <button type="button" class="qty-btn" id="qty-increase" aria-label="Increase quantity">+</button>
                            </div>

                            <button
                                type="submit"
                                class="btn btn-primary btn-lg add-cart-submit"
                                id="add-to-cart-btn"
                                <?= $selectedVariant['stock_qty'] <= 0 ? 'disabled' : '' ?>
                            >
                                <?= $selectedVariant['stock_qty'] > 0 ? 'Add to Shopping Cart' : 'Out of Stock' ?>
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <span>No purchase variants are currently configured for this smartphone.</span>
                    </div>
                <?php endif; ?>

                <!-- Warranty & Perks -->
                <ul class="detail-perks">
                    <li>100% genuine official device with 12-month authorized warranty</li>
                    <li>Free 1-to-1 hardware replacement within 30 days</li>
                    <li>0% credit card installment and fast nationwide shipping</li>
                </ul>
            </div>
        </div>

        <!-- Specifications & Description -->
        <div class="detail-specs-layout">
            <section class="detail-panel" aria-labelledby="specs-heading">
                <h2 id="specs-heading">Technical Specifications</h2>
                <table class="specs-table">
                    <tbody>
                        <tr>
                            <th scope="row">Display</th>
                            <td><?= e($product['screen_spec']) ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Processor (Chipset)</th>
                            <td><?= e($product['chip_spec']) ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Camera System</th>
                            <td><?= e($product['camera_spec']) ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Battery &amp; Charging</th>
                            <td><?= e($product['battery_spec']) ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Operating System</th>
                            <td><?= e($product['os_spec']) ?></td>
                        </tr>
                        <?php if ($selectedVariant !== null): ?>
                            <tr>
                                <th scope="row">Selected RAM / Storage</th>
                                <td id="spec-ram-storage"><?= e($selectedVariant['ram'] . ' / ' . $selectedVariant['storage']) ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Selected Color</th>
                                <td id="spec-color"><?= e($selectedVariant['color_name']) ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>

            <section class="detail-panel" aria-labelledby="overview-heading">
                <h2 id="overview-heading">Product Overview</h2>
                <div class="detail-description">
                    <?php if (!empty($product['description'])): ?>
                        <p><?= nl2br(e($product['description'])) ?></p>
                    <?php else: ?>
                        <p><?= e($product['name']) ?> is an official smartphone distributed by <?= e(APP_NAME) ?> with full nationwide warranty coverage.</p>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <!-- Related Smartphones -->
        <?php if (!empty($relatedProducts)): ?>
            <section class="section detail-related" aria-labelledby="related-heading">
                <div class="section-header">
                    <div>
                        <h2 class="section-title" id="related-heading">More from <?= e($product['brand_name']) ?></h2>
                        <p class="section-subtitle">Explore other official models from the same manufacturer.</p>
                    </div>
                    <a href="<?= e(BASE_URL) ?>/products.php?brand=<?= e(urlencode($product['brand_slug'])) ?>" class="btn btn-outline btn-sm">
                        View All <?= e($product['brand_name']) ?>
                    </a>
                </div>

                <div class="product-grid">
                    <?php foreach ($relatedProducts as $product): ?>
                        <?php require __DIR__ . '/includes/product-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($variants)): ?>
    <script type="application/json" id="product-variants-data"><?= json_encode($variants, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

