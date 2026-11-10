<?php
/**
 * Checkout & Order Placement Page (NovaPhone).
 * Requires authenticated user session, validates shipping/payment inputs,
 * locks variant stock rows inside a PDO transaction (SELECT ... FOR UPDATE),
 * creates orders & order_items, decrements stock_qty atomically, and empties the cart.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

$user = requireLogin('checkout.php');
$userId = (int) $user['id'];

// Mode 1: Display Order Confirmation Receipt after successful checkout (?code=NP...)
$placedOrderCode = trim((string) ($_GET['code'] ?? ''));
if ($placedOrderCode !== '') {
    $orderStmt = $pdo->prepare(
        'SELECT * FROM orders WHERE order_code = :order_code AND user_id = :user_id LIMIT 1'
    );
    $orderStmt->execute([
        ':order_code' => $placedOrderCode,
        ':user_id'    => $userId,
    ]);
    $placedOrder = $orderStmt->fetch();

    if (!$placedOrder) {
        setFlash('error', 'Order receipt not found.');
        redirect('index.php');
    }

    $itemsStmt = $pdo->prepare(
        'SELECT product_name, variant_label, unit_price, quantity, subtotal
         FROM order_items
         WHERE order_id = :order_id
         ORDER BY id ASC'
    );
    $itemsStmt->execute([':order_id' => (int) $placedOrder['id']]);
    $placedItems = $itemsStmt->fetchAll();

    $pageTitle = 'Order Confirmed (' . $placedOrder['order_code'] . ') - ' . APP_NAME;
    $pageDescription = 'Your smartphone order at NovaPhone has been placed successfully.';
    $activePage = 'cart';

    require_once __DIR__ . '/includes/header.php';
    ?>
    <section class="page-banner page-banner-compact">
        <div class="container">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="<?= e(BASE_URL) ?>/index.php">Home</a>
                <span aria-hidden="true">/</span>
                <span>Order Confirmation</span>
            </nav>
            <h1>Order Placed Successfully</h1>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="receipt-card">
                <div class="receipt-header">
                    <span class="badge badge-stock">Order Confirmed</span>
                    <h2>Thank you, <?= e($placedOrder['customer_name']) ?>!</h2>
                    <p>Order Code: <strong class="receipt-code"><?= e($placedOrder['order_code']) ?></strong></p>
                </div>

                <?php if ($placedOrder['payment_method'] === 'bank_transfer'): ?>
                    <div class="bank-transfer-box">
                        <h3>Simulated Bank Transfer Instructions</h3>
                        <p>Please transfer <strong class="tabular-nums"><?= e(formatPrice((float) $placedOrder['total_amount'])) ?></strong> to the account below:</p>
                        <ul>
                            <li><strong>Bank:</strong> Vietcombank &ndash; Ho Chi Minh City Branch</li>
                            <li><strong>Account Number:</strong> 0071 000 886868</li>
                            <li><strong>Account Name:</strong> NOVAPHONE TECH STORE</li>
                            <li><strong>Transfer Reference:</strong> <?= e($placedOrder['order_code']) ?></li>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="receipt-meta-grid">
                    <div>
                        <h3>Shipping Details</h3>
                        <p><strong>Recipient:</strong> <?= e($placedOrder['customer_name']) ?></p>
                        <p><strong>Phone:</strong> <?= e($placedOrder['customer_phone']) ?></p>
                        <p><strong>Address:</strong> <?= e($placedOrder['shipping_address']) ?></p>
                        <?php if (!empty($placedOrder['note'])): ?>
                            <p><strong>Order Note:</strong> <?= e($placedOrder['note']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3>Order Status &amp; Payment</h3>
                        <p><strong>Order Date:</strong> <?= e($placedOrder['created_at']) ?></p>
                        <p><strong>Payment Method:</strong> <?= $placedOrder['payment_method'] === 'bank_transfer' ? 'Bank Transfer (Simulated)' : 'Cash on Delivery (COD)' ?></p>
                        <p><strong>Payment Status:</strong> <?= e(ucfirst($placedOrder['payment_status'])) ?></p>
                        <p><strong>Order Status:</strong> <?= e(ucfirst($placedOrder['order_status'])) ?></p>
                    </div>
                </div>

                <div class="receipt-items-wrap">
                    <h3>Ordered Smartphones</h3>
                    <table class="receipt-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Unit Price</th>
                                <th>Qty</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="tabular-nums">
                            <?php foreach ($placedItems as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($item['product_name']) ?></strong>
                                        <div class="receipt-variant"><?= e($item['variant_label']) ?></div>
                                    </td>
                                    <td><?= e(formatPrice((float) $item['unit_price'])) ?></td>
                                    <td><?= e((string) $item['quantity']) ?></td>
                                    <td><strong><?= e(formatPrice((float) $item['subtotal'])) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="tabular-nums">
                            <tr>
                                <td colspan="3"><strong>Total Amount</strong></td>
                                <td><strong class="receipt-total"><?= e(formatPrice((float) $placedOrder['total_amount'])) ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="receipt-actions">
                    <a href="<?= e(BASE_URL) ?>/products.php" class="btn btn-primary">Continue Shopping</a>
                    <?php if (is_file(ROOT_PATH . '/profile.php')): ?>
                        <a href="<?= e(BASE_URL) ?>/profile.php" class="btn btn-outline">View My Orders</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Mode 2: Active Checkout Form
$cartId = getOrCreateCartId($pdo, false);
$cartItems = getCartItems($pdo, $cartId);

$errors = [];
$formData = [
    'customer_name'    => (string) $user['full_name'],
    'customer_phone'   => (string) $user['phone'],
    'shipping_address' => (string) ($user['address'] ?? ''),
    'payment_method'   => 'cod',
    'note'             => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'Your security session expired. Please refresh the page and try again.';
    } else {
        $formData['customer_name']    = trim((string) ($_POST['customer_name'] ?? ''));
        $formData['customer_phone']   = trim((string) ($_POST['customer_phone'] ?? ''));
        $formData['shipping_address'] = trim((string) ($_POST['shipping_address'] ?? ''));
        $formData['payment_method']   = trim((string) ($_POST['payment_method'] ?? 'cod'));
        $formData['note']             = trim((string) ($_POST['note'] ?? ''));

        if ($formData['customer_name'] === '') {
            $errors['customer_name'] = 'Recipient full name is required.';
        } elseif (mb_strlen($formData['customer_name']) < 2 || mb_strlen($formData['customer_name']) > 100) {
            $errors['customer_name'] = 'Recipient full name must be between 2 and 100 characters.';
        }

        $normalizedPhone = normalizePhone($formData['customer_phone']);
        if ($formData['customer_phone'] === '') {
            $errors['customer_phone'] = 'Contact phone number is required.';
        } elseif (!isValidVnPhone($formData['customer_phone'])) {
            $errors['customer_phone'] = 'Enter a valid 10-digit Vietnamese mobile number (e.g. 0912345678).';
        }

        if ($formData['shipping_address'] === '') {
            $errors['shipping_address'] = 'Shipping address is required.';
        } elseif (mb_strlen($formData['shipping_address']) < 5 || mb_strlen($formData['shipping_address']) > 255) {
            $errors['shipping_address'] = 'Shipping address must be between 5 and 255 characters.';
        }

        if (!in_array($formData['payment_method'], ['cod', 'bank_transfer'], true)) {
            $errors['payment_method'] = 'Please select a valid payment method.';
        }

        if (mb_strlen($formData['note']) > 255) {
            $errors['note'] = 'Order note must not exceed 255 characters.';
        }

        if ($cartId === null || empty($cartItems)) {
            $errors['general'] = 'Your shopping cart is empty. Please add a smartphone before checking out.';
        }

        if (empty($errors)) {
            try {
                // Begin atomic transaction and lock cart + product_variant rows in deterministic order
                $pdo->beginTransaction();

                $lockedStmt = $pdo->prepare(
                    'SELECT
                        ci.id AS cart_item_id,
                        ci.variant_id,
                        ci.quantity,
                        pv.ram,
                        pv.storage,
                        pv.color_name,
                        pv.price,
                        pv.stock_qty,
                        p.name AS product_name,
                        p.status AS product_status
                     FROM cart_items ci
                     INNER JOIN product_variants pv ON pv.id = ci.variant_id
                     INNER JOIN products p ON p.id = pv.product_id
                     WHERE ci.cart_id = :cart_id
                     ORDER BY ci.variant_id ASC
                     FOR UPDATE'
                );
                $lockedStmt->execute([':cart_id' => $cartId]);
                $lockedItems = $lockedStmt->fetchAll();

                if (empty($lockedItems)) {
                    $pdo->rollBack();
                    $errors['general'] = 'Your shopping cart is empty.';
                } else {
                    $stockProblem = null;
                    $orderTotal = 0.0;
                    $preparedItems = [];

                    foreach ($lockedItems as $row) {
                        $qty = (int) $row['quantity'];
                        $stockQty = (int) $row['stock_qty'];

                        if ($row['product_status'] !== 'active') {
                            $stockProblem = $row['product_name'] . ' is no longer available for purchase.';
                            break;
                        }
                        if ($stockQty < $qty || $qty <= 0) {
                            $stockProblem = 'Insufficient stock for ' . $row['product_name'] . ' (' . $row['ram'] . '/' . $row['storage'] . ' - ' . $row['color_name'] . '). Available: ' . $stockQty . '.';
                            break;
                        }

                        $unitPrice = (float) $row['price'];
                        $lineSubtotal = $unitPrice * $qty;
                        $orderTotal += $lineSubtotal;

                        $preparedItems[] = [
                            'variant_id'    => (int) $row['variant_id'],
                            'product_name'  => (string) $row['product_name'],
                            'variant_label' => $row['ram'] . ' / ' . $row['storage'] . ' - ' . $row['color_name'],
                            'unit_price'    => $unitPrice,
                            'quantity'      => $qty,
                            'subtotal'      => $lineSubtotal,
                        ];
                    }

                    if ($stockProblem !== null) {
                        $pdo->rollBack();
                        $errors['general'] = $stockProblem;
                    } else {
                        // Generate a unique order code (e.g., NP20261110A1B2)
                        $orderCode = 'NP' . date('Ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
                        $codeCheckStmt = $pdo->prepare('SELECT id FROM orders WHERE order_code = :code LIMIT 1');
                        $codeCheckStmt->execute([':code' => $orderCode]);
                        if ($codeCheckStmt->fetch()) {
                            $orderCode = 'NP' . date('YmdHis') . strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));
                        }

                        $insertOrderStmt = $pdo->prepare(
                            'INSERT INTO orders (
                                order_code, user_id, customer_name, customer_phone,
                                shipping_address, payment_method, payment_status,
                                order_status, total_amount, note
                             ) VALUES (
                                :order_code, :user_id, :customer_name, :customer_phone,
                                :shipping_address, :payment_method, :payment_status,
                                :order_status, :total_amount, :note
                             )'
                        );
                        $insertOrderStmt->execute([
                            ':order_code'       => $orderCode,
                            ':user_id'          => $userId,
                            ':customer_name'    => $formData['customer_name'],
                            ':customer_phone'   => $normalizedPhone,
                            ':shipping_address' => $formData['shipping_address'],
                            ':payment_method'   => $formData['payment_method'],
                            ':payment_status'   => 'unpaid',
                            ':order_status'     => 'pending',
                            ':total_amount'     => $orderTotal,
                            ':note'             => $formData['note'] !== '' ? $formData['note'] : null,
                        ]);

                        $orderId = (int) $pdo->lastInsertId();

                        $insertItemStmt = $pdo->prepare(
                            'INSERT INTO order_items (
                                order_id, variant_id, product_name, variant_label,
                                unit_price, quantity, subtotal
                             ) VALUES (
                                :order_id, :variant_id, :product_name, :variant_label,
                                :unit_price, :quantity, :subtotal
                             )'
                        );

                        $decrementStockStmt = $pdo->prepare(
                            'UPDATE product_variants
                             SET stock_qty = stock_qty - :qty_sub
                             WHERE id = :variant_id AND stock_qty >= :qty_min'
                        );

                        foreach ($preparedItems as $pItem) {
                            $insertItemStmt->execute([
                                ':order_id'      => $orderId,
                                ':variant_id'    => $pItem['variant_id'],
                                ':product_name'  => $pItem['product_name'],
                                ':variant_label' => $pItem['variant_label'],
                                ':unit_price'    => $pItem['unit_price'],
                                ':quantity'      => $pItem['quantity'],
                                ':subtotal'      => $pItem['subtotal'],
                            ]);

                            $decrementStockStmt->execute([
                                ':qty_sub'    => $pItem['quantity'],
                                ':variant_id' => $pItem['variant_id'],
                                ':qty_min'    => $pItem['quantity'],
                            ]);

                            if ($decrementStockStmt->rowCount() !== 1) {
                                throw new RuntimeException('Concurrent stock deduction conflict for variant #' . $pItem['variant_id']);
                            }
                        }

                        // Empty the customer's cart after committing all order items
                        $clearCartStmt = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id');
                        $clearCartStmt->execute([':cart_id' => $cartId]);

                        $pdo->commit();

                        setFlash('success', 'Your order ' . $orderCode . ' has been placed!');
                        redirect('checkout.php?code=' . urlencode($orderCode));
                    }
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Checkout transaction failed: ' . $e->getMessage());
                $errors['general'] = 'Unable to place your order due to a stock or server error. Please review your cart and try again.';
                $cartItems = getCartItems($pdo, $cartId);
            }
        }
    }
}

$checkoutTotal = 0.0;
foreach ($cartItems as $item) {
    $checkoutTotal += (float) $item['price'] * (int) $item['quantity'];
}

$pageTitle = 'Checkout - ' . APP_NAME;
$pageDescription = 'Enter shipping details and confirm your smartphone order at NovaPhone.';
$activePage = 'cart';
$pageScripts = ['assets/js/checkout.js'];

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-banner page-banner-compact">
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(BASE_URL) ?>/index.php">Home</a>
            <span aria-hidden="true">/</span>
            <a href="<?= e(BASE_URL) ?>/cart.php">Shopping Cart</a>
            <span aria-hidden="true">/</span>
            <span>Checkout</span>
        </nav>
        <h1>Checkout &amp; Delivery</h1>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (empty($cartItems)): ?>
            <div class="empty-state">
                <h2>No Items Ready for Checkout</h2>
                <p>Your shopping cart is currently empty. Add a smartphone first before proceeding to checkout.</p>
                <a href="<?= e(BASE_URL) ?>/products.php" class="btn btn-primary">Explore Smartphones</a>
            </div>
        <?php else: ?>
            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-error" role="alert">
                    <span><?= e($errors['general']) ?></span>
                </div>
            <?php endif; ?>

            <div class="checkout-layout">
                <div class="checkout-form-card">
                    <h2>Shipping &amp; Payment Details</h2>
                    <form id="checkout-form" action="<?= e(BASE_URL) ?>/checkout.php" method="post" novalidate>
                        <?= csrfField() ?>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="chk-name">Recipient Full Name <span class="required-mark" aria-hidden="true">*</span></label>
                                <input
                                    type="text"
                                    id="chk-name"
                                    name="customer_name"
                                    class="form-control <?= isset($errors['customer_name']) ? 'is-invalid' : '' ?>"
                                    value="<?= e($formData['customer_name']) ?>"
                                    placeholder="e.g. Tran Minh Anh"
                                    autocomplete="name"
                                    required
                                    maxlength="100"
                                    aria-invalid="<?= isset($errors['customer_name']) ? 'true' : 'false' ?>"
                                    aria-describedby="chk-name-error"
                                >
                                <p class="field-error" id="chk-name-error"><?= e($errors['customer_name'] ?? '') ?></p>
                            </div>

                            <div class="form-group">
                                <label for="chk-phone">Contact Phone <span class="required-mark" aria-hidden="true">*</span></label>
                                <input
                                    type="tel"
                                    id="chk-phone"
                                    name="customer_phone"
                                    class="form-control <?= isset($errors['customer_phone']) ? 'is-invalid' : '' ?>"
                                    value="<?= e($formData['customer_phone']) ?>"
                                    placeholder="e.g. 0912345678"
                                    autocomplete="tel"
                                    required
                                    maxlength="20"
                                    aria-invalid="<?= isset($errors['customer_phone']) ? 'true' : 'false' ?>"
                                    aria-describedby="chk-phone-error"
                                >
                                <p class="field-error" id="chk-phone-error"><?= e($errors['customer_phone'] ?? '') ?></p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="chk-address">Delivery Address <span class="required-mark" aria-hidden="true">*</span></label>
                            <input
                                type="text"
                                id="chk-address"
                                name="shipping_address"
                                class="form-control <?= isset($errors['shipping_address']) ? 'is-invalid' : '' ?>"
                                value="<?= e($formData['shipping_address']) ?>"
                                placeholder="128 Nguyen Van Cu, District 5, Ho Chi Minh City"
                                autocomplete="street-address"
                                required
                                maxlength="255"
                                aria-invalid="<?= isset($errors['shipping_address']) ? 'true' : 'false' ?>"
                                aria-describedby="chk-address-error"
                            >
                            <p class="field-error" id="chk-address-error"><?= e($errors['shipping_address'] ?? '') ?></p>
                        </div>

                        <div class="form-group">
                            <span class="field-group-label">Payment Method <span class="required-mark" aria-hidden="true">*</span></span>
                            <div class="payment-options">
                                <label class="payment-option">
                                    <input
                                        type="radio"
                                        name="payment_method"
                                        value="cod"
                                        <?= $formData['payment_method'] === 'cod' ? 'checked' : '' ?>
                                    >
                                    <span>
                                        <strong>Cash on Delivery (COD)</strong>
                                        <small>Inspect your sealed device upon arrival and pay the courier.</small>
                                    </span>
                                </label>

                                <label class="payment-option">
                                    <input
                                        type="radio"
                                        name="payment_method"
                                        value="bank_transfer"
                                        <?= $formData['payment_method'] === 'bank_transfer' ? 'checked' : '' ?>
                                    >
                                    <span>
                                        <strong>Bank Transfer (Simulated)</strong>
                                        <small>Receive NovaPhone bank transfer details immediately after placing the order.</small>
                                    </span>
                                </label>
                            </div>
                            <p class="field-error" id="chk-payment-error"><?= e($errors['payment_method'] ?? '') ?></p>

                            <div class="bank-transfer-box <?= $formData['payment_method'] === 'bank_transfer' ? '' : 'is-hidden' ?>" id="bank-transfer-preview">
                                <h3>NovaPhone Bank Account (Simulated)</h3>
                                <p>Vietcombank (Ho Chi Minh City Branch) &bull; Account: <strong>0071 000 886868</strong> &bull; Holder: <strong>NOVAPHONE TECH STORE</strong></p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="chk-note">Order Note <span class="field-optional">(Optional)</span></label>
                            <textarea
                                id="chk-note"
                                name="note"
                                rows="3"
                                class="form-control <?= isset($errors['note']) ? 'is-invalid' : '' ?>"
                                placeholder="Delivery time preference, call before arrival, or VAT invoice note..."
                                maxlength="255"
                                aria-invalid="<?= isset($errors['note']) ? 'true' : 'false' ?>"
                                aria-describedby="chk-note-error"
                            ><?= e($formData['note']) ?></textarea>
                            <p class="field-error" id="chk-note-error"><?= e($errors['note'] ?? '') ?></p>
                        </div>

                        <div class="checkout-submit-row">
                            <a href="<?= e(BASE_URL) ?>/cart.php" class="btn btn-outline">Back to Cart</a>
                            <button type="submit" class="btn btn-primary btn-lg" id="checkout-submit-btn">
                                Confirm &amp; Place Order
                            </button>
                        </div>
                    </form>
                </div>

                <aside class="cart-summary-card" aria-label="Checkout summary">
                    <h2>Order Items (<?= e((string) count($cartItems)) ?>)</h2>
                    <ul class="checkout-mini-list">
                        <?php foreach ($cartItems as $item): ?>
                            <?php $lineSubtotal = (float) $item['price'] * (int) $item['quantity']; ?>
                            <li class="checkout-mini-item">
                                <div>
                                    <strong><?= e($item['product_name']) ?> &times; <?= e((string) $item['quantity']) ?></strong>
                                    <small><?= e($item['ram'] . ' / ' . $item['storage'] . ' - ' . $item['color_name']) ?></small>
                                </div>
                                <span class="tabular-nums"><?= e(formatPrice($lineSubtotal)) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <dl class="summary-rows tabular-nums">
                        <div class="summary-row">
                            <dt>Shipping Fee</dt>
                            <dd class="summary-free">Free</dd>
                        </div>
                        <div class="summary-row summary-total">
                            <dt>Total Payable</dt>
                            <dd><?= e(formatPrice($checkoutTotal)) ?></dd>
                        </div>
                    </dl>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

