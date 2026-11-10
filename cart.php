<?php
/**
 * Shopping Cart Page (NovaPhone).
 * Supports both guest session carts and logged-in user carts with CSRF-protected
 * add, update, remove, and clear actions plus real-time stock bounds checking.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

startAppSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Your security session expired. Please refresh and try again.');
        redirect('cart.php');
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    try {
        if ($action === 'add') {
            $variantId = (int) ($_POST['variant_id'] ?? 0);
            $quantity  = max(1, (int) ($_POST['quantity'] ?? 1));

            if ($variantId <= 0) {
                setFlash('error', 'Please select a valid smartphone configuration.');
                redirect('products.php');
            }

            $variantStmt = $pdo->prepare(
                "SELECT pv.id, pv.stock_qty, p.name AS product_name, p.slug AS product_slug
                 FROM product_variants pv
                 INNER JOIN products p ON p.id = pv.product_id
                 WHERE pv.id = :variant_id AND p.status = 'active'
                 LIMIT 1"
            );
            $variantStmt->execute([':variant_id' => $variantId]);
            $variant = $variantStmt->fetch();

            if (!$variant) {
                setFlash('error', 'The selected smartphone is no longer available.');
                redirect('products.php');
            }

            $stockQty = (int) $variant['stock_qty'];
            if ($stockQty <= 0) {
                setFlash('error', 'This configuration is currently out of stock.');
                redirect('product-detail.php?slug=' . urlencode($variant['product_slug']));
            }

            $cartId = getOrCreateCartId($pdo, true);
            if ($cartId === null) {
                setFlash('error', 'Unable to initialize your shopping cart.');
                redirect('products.php');
            }

            $existingStmt = $pdo->prepare(
                'SELECT id, quantity FROM cart_items WHERE cart_id = :cart_id AND variant_id = :variant_id LIMIT 1'
            );
            $existingStmt->execute([
                ':cart_id'    => $cartId,
                ':variant_id' => $variantId,
            ]);
            $existing = $existingStmt->fetch();

            if ($existing) {
                $existingQty = (int) $existing['quantity'];
                if ($existingQty >= $stockQty) {
                    setFlash('warning', 'Maximum available stock (' . $stockQty . ') for ' . $variant['product_name'] . ' is already in your cart.');
                    redirect('cart.php');
                }
                $newQty = min($stockQty, $existingQty + $quantity);
                $updStmt = $pdo->prepare('UPDATE cart_items SET quantity = :quantity WHERE id = :id');
                $updStmt->execute([
                    ':quantity' => $newQty,
                    ':id'       => (int) $existing['id'],
                ]);
                if ($existingQty + $quantity > $stockQty) {
                    setFlash('warning', 'Quantity adjusted to maximum available stock (' . $stockQty . ') for ' . $variant['product_name'] . '.');
                } else {
                    setFlash('success', 'Updated ' . $variant['product_name'] . ' quantity in your cart.');
                }
            } else {
                $insertQty = min($stockQty, $quantity);
                $insStmt = $pdo->prepare(
                    'INSERT INTO cart_items (cart_id, variant_id, quantity) VALUES (:cart_id, :variant_id, :quantity)'
                );
                $insStmt->execute([
                    ':cart_id'    => $cartId,
                    ':variant_id' => $variantId,
                    ':quantity'   => $insertQty,
                ]);
                if ($quantity > $stockQty) {
                    setFlash('warning', 'Added maximum available stock (' . $stockQty . ') of ' . $variant['product_name'] . ' to your cart.');
                } else {
                    setFlash('success', 'Added ' . $variant['product_name'] . ' to your shopping cart.');
                }
            }

            redirect('cart.php');
        }

        $cartId = getOrCreateCartId($pdo, false);
        if ($cartId === null) {
            redirect('cart.php');
        }

        if ($action === 'update') {
            $cartItemId = (int) ($_POST['cart_item_id'] ?? 0);
            $quantity   = (int) ($_POST['quantity'] ?? 1);

            $itemStmt = $pdo->prepare(
                'SELECT ci.id, pv.stock_qty, p.name AS product_name
                 FROM cart_items ci
                 INNER JOIN product_variants pv ON pv.id = ci.variant_id
                 INNER JOIN products p ON p.id = pv.product_id
                 WHERE ci.id = :id AND ci.cart_id = :cart_id
                 LIMIT 1'
            );
            $itemStmt->execute([
                ':id'      => $cartItemId,
                ':cart_id' => $cartId,
            ]);
            $item = $itemStmt->fetch();

            if (!$item) {
                setFlash('error', 'Cart item not found.');
                redirect('cart.php');
            }

            if ($quantity <= 0) {
                $delStmt = $pdo->prepare('DELETE FROM cart_items WHERE id = :id AND cart_id = :cart_id');
                $delStmt->execute([':id' => $cartItemId, ':cart_id' => $cartId]);
                setFlash('info', 'Removed ' . $item['product_name'] . ' from your cart.');
                redirect('cart.php');
            }

            $stockQty = (int) $item['stock_qty'];
            if ($stockQty <= 0) {
                setFlash('error', $item['product_name'] . ' is currently out of stock. Please remove it before checkout.');
                redirect('cart.php');
            }

            $cappedQty = min($stockQty, $quantity);
            $updStmt = $pdo->prepare('UPDATE cart_items SET quantity = :quantity WHERE id = :id AND cart_id = :cart_id');
            $updStmt->execute([
                ':quantity' => $cappedQty,
                ':id'       => $cartItemId,
                ':cart_id'  => $cartId,
            ]);

            if ($quantity > $stockQty) {
                setFlash('warning', 'Only ' . $stockQty . ' unit(s) of ' . $item['product_name'] . ' are currently in stock.');
            } else {
                setFlash('success', 'Cart quantity updated.');
            }
            redirect('cart.php');
        }

        if ($action === 'remove') {
            $cartItemId = (int) ($_POST['cart_item_id'] ?? 0);
            $delStmt = $pdo->prepare('DELETE FROM cart_items WHERE id = :id AND cart_id = :cart_id');
            $delStmt->execute([':id' => $cartItemId, ':cart_id' => $cartId]);
            setFlash('info', 'Item removed from your shopping cart.');
            redirect('cart.php');
        }

        if ($action === 'clear') {
            $delStmt = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id');
            $delStmt->execute([':cart_id' => $cartId]);
            setFlash('info', 'Your shopping cart has been cleared.');
            redirect('cart.php');
        }
    } catch (PDOException $e) {
        error_log('Cart operation failed: ' . $e->getMessage());
        setFlash('error', 'Unable to update your shopping cart right now.');
        redirect('cart.php');
    }
}

$cartId = getOrCreateCartId($pdo, false);
$cartItems = getCartItems($pdo, $cartId);

$totalUnits = 0;
$subtotal = 0.0;
$hasBlockingIssue = false;

foreach ($cartItems as &$item) {
    $qty = (int) $item['quantity'];
    $stock = (int) $item['stock_qty'];
    $price = (float) $item['price'];
    $lineTotal = $price * $qty;

    $item['line_total'] = $lineTotal;
    $item['is_unavailable'] = ($item['product_status'] !== 'active') || $stock <= 0 || $qty > $stock;
    if ($item['is_unavailable']) {
        $hasBlockingIssue = true;
    }

    $totalUnits += $qty;
    $subtotal += $lineTotal;
}
unset($item);

$pageTitle = 'Shopping Cart - ' . APP_NAME;
$pageDescription = 'Review selected smartphones, adjust quantities, and proceed to checkout at NovaPhone.';
$activePage = 'cart';
$pageScripts = ['assets/js/cart.js'];

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-banner page-banner-compact">
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(BASE_URL) ?>/index.php">Home</a>
            <span aria-hidden="true">/</span>
            <span>Shopping Cart</span>
        </nav>
        <h1>Your Shopping Cart</h1>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (empty($cartItems)): ?>
            <div class="empty-state">
                <h2>Your Shopping Cart Is Empty</h2>
                <p>Explore our official smartphones from Apple, Samsung, Xiaomi, OPPO, and realme to find your next device.</p>
                <a href="<?= e(BASE_URL) ?>/products.php" class="btn btn-primary">Browse Smartphones</a>
            </div>
        <?php else: ?>
            <?php if ($hasBlockingIssue): ?>
                <div class="alert alert-warning" role="alert">
                    <span>Some items in your cart exceed current stock or are unavailable. Please adjust quantities or remove unavailable items before checking out.</span>
                </div>
            <?php endif; ?>

            <div class="cart-layout">
                <div class="cart-items-panel">
                    <div class="cart-panel-header">
                        <h2>Cart Items (<?= e((string) $totalUnits) ?>)</h2>
                        <form action="<?= e(BASE_URL) ?>/cart.php" method="post">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="btn btn-ghost btn-sm" data-confirm-clear="true">Clear Cart</button>
                        </form>
                    </div>

                    <div class="cart-list">
                        <?php foreach ($cartItems as $item): ?>
                            <?php
                            $detailUrl = BASE_URL . '/product-detail.php?slug=' . urlencode($item['product_slug']);
                            $imgUrl = resolveImageUrl($item['image_path']);
                            $imgAlt = $item['alt_text'] ?: ($item['product_name'] . ' smartphone');
                            $stock = (int) $item['stock_qty'];
                            $qty = (int) $item['quantity'];
                            ?>
                            <article class="cart-item-row">
                                <a href="<?= e($detailUrl) ?>" class="cart-item-thumb">
                                    <img src="<?= e($imgUrl) ?>" alt="<?= e($imgAlt) ?>" width="96" height="72">
                                </a>

                                <div class="cart-item-info">
                                    <div class="product-brand-tag"><?= e($item['brand_name']) ?></div>
                                    <h3 class="cart-item-title">
                                        <a href="<?= e($detailUrl) ?>"><?= e($item['product_name']) ?></a>
                                    </h3>
                                    <p class="cart-item-variant">
                                        <span class="variant-color-dot" style="background-color: <?= e($item['color_hex']) ?>;" aria-hidden="true"></span>
                                        <span><?= e($item['ram'] . ' / ' . $item['storage'] . ' - ' . $item['color_name']) ?></span>
                                        <span class="cart-item-sku">SKU: <?= e($item['sku']) ?></span>
                                    </p>

                                    <?php if ($item['product_status'] !== 'active' || $stock <= 0): ?>
                                        <span class="badge badge-out">Out of Stock &mdash; Remove to continue</span>
                                    <?php elseif ($qty > $stock): ?>
                                        <span class="badge badge-low">Only <?= e((string) $stock) ?> unit(s) left in stock</span>
                                    <?php elseif ($stock <= LOW_STOCK_THRESHOLD): ?>
                                        <span class="badge badge-low">Low Stock (<?= e((string) $stock) ?> left)</span>
                                    <?php endif; ?>
                                </div>

                                <div class="cart-item-price tabular-nums">
                                    <span class="cart-unit-price"><?= e(formatPrice((float) $item['price'])) ?></span>
                                    <?php if ($item['original_price'] !== null && (float) $item['original_price'] > (float) $item['price']): ?>
                                        <small class="price-original"><?= e(formatPrice((float) $item['original_price'])) ?></small>
                                    <?php endif; ?>
                                </div>

                                <div class="cart-item-qty">
                                    <form action="<?= e(BASE_URL) ?>/cart.php" method="post" class="cart-qty-form">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="cart_item_id" value="<?= e((string) $item['cart_item_id']) ?>">
                                        <div class="qty-control qty-control-sm">
                                            <button type="button" class="qty-btn" data-qty-step="-1" aria-label="Decrease quantity">&minus;</button>
                                            <input
                                                type="number"
                                                name="quantity"
                                                value="<?= e((string) $qty) ?>"
                                                min="1"
                                                max="<?= e((string) max(1, $stock)) ?>"
                                                aria-label="Quantity for <?= e($item['product_name']) ?>"
                                            >
                                            <button type="button" class="qty-btn" data-qty-step="1" aria-label="Increase quantity">+</button>
                                        </div>
                                        <button type="submit" class="btn btn-outline btn-sm">Update</button>
                                    </form>
                                </div>

                                <div class="cart-item-subtotal tabular-nums">
                                    <strong><?= e(formatPrice($item['line_total'])) ?></strong>
                                    <form action="<?= e(BASE_URL) ?>/cart.php" method="post">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="cart_item_id" value="<?= e((string) $item['cart_item_id']) ?>">
                                        <button type="submit" class="cart-remove-btn">Remove</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>

                <aside class="cart-summary-card" aria-label="Order summary">
                    <h2>Order Summary</h2>
                    <dl class="summary-rows tabular-nums">
                        <div class="summary-row">
                            <dt>Items (<?= e((string) $totalUnits) ?>)</dt>
                            <dd><?= e(formatPrice($subtotal)) ?></dd>
                        </div>
                        <div class="summary-row">
                            <dt>Nationwide Shipping</dt>
                            <dd class="summary-free">Free</dd>
                        </div>
                        <div class="summary-row summary-total">
                            <dt>Estimated Total</dt>
                            <dd><?= e(formatPrice($subtotal)) ?></dd>
                        </div>
                    </dl>

                    <?php if ($hasBlockingIssue): ?>
                        <button type="button" class="btn btn-primary btn-lg btn-block" disabled>
                            Resolve Stock Issues First
                        </button>
                    <?php else: ?>
                        <a href="<?= e(BASE_URL) ?>/checkout.php" class="btn btn-primary btn-lg btn-block">
                            Proceed to Checkout
                        </a>
                    <?php endif; ?>

                    <a href="<?= e(BASE_URL) ?>/products.php" class="btn btn-outline btn-block summary-continue-btn">
                        Continue Shopping
                    </a>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

