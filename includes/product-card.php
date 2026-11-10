<?php
/**
 * Reusable storefront product card component.
 * Expects $product associative array with brand, variant price/stock, and primary image fields.
 */

$price = isset($product['display_price']) ? (float) $product['display_price'] : 0.0;
$originalPrice = isset($product['display_original_price']) && $product['display_original_price'] !== null
    ? (float) $product['display_original_price']
    : null;
$discountPercent = calcDiscountPercent($price, $originalPrice);
$totalStock = (int) ($product['total_stock'] ?? 0);
$imageUrl = resolveImageUrl($product['primary_image'] ?? null);
$imageAlt = $product['primary_alt'] ?: ($product['name'] . ' smartphone');
$detailUrl = BASE_URL . '/product-detail.php?slug=' . urlencode($product['slug']);
?>
<article class="product-card">
    <a href="<?= e($detailUrl) ?>" class="product-thumb-wrap">
        <div class="product-badges">
            <?php if (!empty($product['promo_label'])): ?>
                <span class="badge badge-promo"><?= e($product['promo_label']) ?></span>
            <?php else: ?>
                <span></span>
            <?php endif; ?>

            <?php if ($discountPercent > 0): ?>
                <span class="badge badge-discount">-<?= e((string) $discountPercent) ?>%</span>
            <?php endif; ?>
        </div>
        <img
            src="<?= e($imageUrl) ?>"
            alt="<?= e($imageAlt) ?>"
            width="280"
            height="210"
            loading="lazy"
        >
    </a>

    <div class="product-brand-tag"><?= e($product['brand_name']) ?></div>
    <h3 class="product-title">
        <a href="<?= e($detailUrl) ?>"><?= e($product['name']) ?></a>
    </h3>
    <p class="product-specs-mini">
        <?= e($product['chip_spec']) ?> &bull; <?= e($product['screen_spec']) ?>
    </p>

    <div class="product-price-block">
        <div class="price-row tabular-nums">
            <span class="price-current"><?= e(formatPrice($price)) ?></span>
            <?php if ($originalPrice !== null && $originalPrice > $price): ?>
                <span class="price-original"><?= e(formatPrice($originalPrice)) ?></span>
            <?php endif; ?>
        </div>

        <div class="product-card-footer">
            <?php if ($totalStock > 0): ?>
                <span class="badge badge-stock">In Stock (<?= e((string) $totalStock) ?>)</span>
            <?php else: ?>
                <span class="badge badge-out">Out of Stock</span>
            <?php endif; ?>
            <a href="<?= e($detailUrl) ?>" class="btn btn-outline btn-sm">View Details</a>
        </div>
    </div>
</article>

