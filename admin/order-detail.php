<?php
/**
 * Admin Order Detail & Status Management Page (NovaPhone).
 * Displays customer info, shipping details, ordered items, and updates order/payment status.
 * Automatically restores variant stock_qty inside a PDO transaction when an order is cancelled.
 */

require_once __DIR__ . '/../includes/admin_guard.php';

$orderId = (int) ($_GET['id'] ?? ($_POST['order_id'] ?? 0));
if ($orderId <= 0) {
    setFlash('error', 'Invalid order ID.');
    redirect('admin/orders.php');
}

$allowedOrderStatuses   = ['pending', 'confirmed', 'shipping', 'completed', 'cancelled'];
$allowedPaymentStatuses = ['unpaid', 'paid'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Your security session expired. Please try again.');
        redirect('admin/order-detail.php?id=' . $orderId);
    }

    $newOrderStatus   = trim((string) ($_POST['order_status'] ?? ''));
    $newPaymentStatus = trim((string) ($_POST['payment_status'] ?? ''));

    if (!in_array($newOrderStatus, $allowedOrderStatuses, true) || !in_array($newPaymentStatus, $allowedPaymentStatuses, true)) {
        setFlash('error', 'Invalid order or payment status selection.');
        redirect('admin/order-detail.php?id=' . $orderId);
    }

    try {
        $pdo->beginTransaction();

        // Lock order row to ensure atomic status transition & prevent duplicate stock restores
        $lockOrderStmt = $pdo->prepare(
            'SELECT id, order_code, order_status, payment_status FROM orders WHERE id = :id LIMIT 1 FOR UPDATE'
        );
        $lockOrderStmt->execute([':id' => $orderId]);
        $lockedOrder = $lockOrderStmt->fetch();

        if (!$lockedOrder) {
            $pdo->rollBack();
            setFlash('error', 'Order not found.');
            redirect('admin/orders.php');
        }

        $oldStatus = (string) $lockedOrder['order_status'];

        if ($oldStatus === 'cancelled' && $newOrderStatus !== 'cancelled') {
            $pdo->rollBack();
            setFlash('error', 'Cancelled orders cannot be reopened because stock was already restored.');
            redirect('admin/order-detail.php?id=' . $orderId);
        }

        // Restore variant stock_qty once when transitioning into 'cancelled'
        $restoredUnits = 0;
        if ($oldStatus !== 'cancelled' && $newOrderStatus === 'cancelled') {
            $itemsStmt = $pdo->prepare(
                'SELECT variant_id, quantity FROM order_items WHERE order_id = :order_id ORDER BY variant_id ASC'
            );
            $itemsStmt->execute([':order_id' => $orderId]);
            $orderItems = $itemsStmt->fetchAll();

            $lockVariantStmt = $pdo->prepare('SELECT id FROM product_variants WHERE id = :id FOR UPDATE');
            $restoreStockStmt = $pdo->prepare(
                'UPDATE product_variants SET stock_qty = stock_qty + :qty WHERE id = :id'
            );

            foreach ($orderItems as $oi) {
                $vId = (int) $oi['variant_id'];
                $qty = (int) $oi['quantity'];
                $lockVariantStmt->execute([':id' => $vId]);
                $restoreStockStmt->execute([
                    ':qty' => $qty,
                    ':id'  => $vId,
                ]);
                $restoredUnits += $qty;
            }
        }

        $updOrderStmt = $pdo->prepare(
            'UPDATE orders SET order_status = :order_status, payment_status = :payment_status WHERE id = :id'
        );
        $updOrderStmt->execute([
            ':order_status'   => $newOrderStatus,
            ':payment_status' => $newPaymentStatus,
            ':id'             => $orderId,
        ]);

        $pdo->commit();

        if ($restoredUnits > 0) {
            setFlash('success', 'Order ' . $lockedOrder['order_code'] . ' marked as Cancelled and ' . $restoredUnits . ' unit(s) restored to inventory.');
        } else {
            setFlash('success', 'Order ' . $lockedOrder['order_code'] . ' updated successfully.');
        }
        redirect('admin/order-detail.php?id=' . $orderId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Order status update failed: ' . $e->getMessage());
        setFlash('error', 'Unable to update order status right now.');
        redirect('admin/order-detail.php?id=' . $orderId);
    }
}

$orderStmt = $pdo->prepare(
    'SELECT o.*, u.email AS account_email, u.full_name AS account_name
     FROM orders o
     INNER JOIN users u ON u.id = o.user_id
     WHERE o.id = :id
     LIMIT 1'
);
$orderStmt->execute([':id' => $orderId]);
$order = $orderStmt->fetch();

if (!$order) {
    setFlash('error', 'Order not found.');
    redirect('admin/orders.php');
}

$itemsStmt = $pdo->prepare(
    'SELECT oi.*, pv.sku
     FROM order_items oi
     LEFT JOIN product_variants pv ON pv.id = oi.variant_id
     WHERE oi.order_id = :order_id
     ORDER BY oi.id ASC'
);
$itemsStmt->execute([':order_id' => $orderId]);
$items = $itemsStmt->fetchAll();

$orderMeta = getOrderStatusMeta((string) $order['order_status']);
$payMeta   = getPaymentStatusMeta((string) $order['payment_status']);

$adminTitle = 'Order #' . $order['order_code'];
$adminActive = 'orders';

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-form-topbar">
    <a href="<?= e(BASE_URL) ?>/admin/orders.php" class="btn btn-outline btn-sm">&laquo; Back to Orders</a>
    <div class="order-inspect-badges">
        <span class="badge <?= e($orderMeta['class']) ?>"><?= e($orderMeta['label']) ?></span>
        <span class="badge <?= e($payMeta['class']) ?>"><?= e($payMeta['label']) ?></span>
    </div>
</div>

<div class="admin-split-grid">
    <!-- Status Update Card -->
    <section class="admin-card">
        <div class="admin-card-header">
            <h2>Update Order Status</h2>
        </div>

        <form action="<?= e(BASE_URL) ?>/admin/order-detail.php?id=<?= e((string) $orderId) ?>" method="post">
            <?= csrfField() ?>
            <input type="hidden" name="order_id" value="<?= e((string) $orderId) ?>">

            <div class="form-group">
                <label for="order-status-select">Order Lifecycle Status</label>
                <select
                    id="order-status-select"
                    name="order_status"
                    class="form-control"
                    <?= $order['order_status'] === 'cancelled' ? 'disabled' : '' ?>
                >
                    <?php foreach ($allowedOrderStatuses as $st): ?>
                        <option value="<?= e($st) ?>" <?= $order['order_status'] === $st ? 'selected' : '' ?>>
                            <?= e(ucfirst($st)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($order['order_status'] === 'cancelled'): ?>
                    <input type="hidden" name="order_status" value="cancelled">
                    <p class="table-subtext">Cancelled orders are locked after stock restoration.</p>
                <?php else: ?>
                    <p class="table-subtext">Changing status to <strong>Cancelled</strong> automatically restores item quantities to inventory.</p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="payment-status-select">Payment Status</label>
                <select id="payment-status-select" name="payment_status" class="form-control">
                    <option value="unpaid" <?= $order['payment_status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                    <option value="paid" <?= $order['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Save Order Update</button>
        </form>

        <hr class="admin-divider">

        <div class="order-customer-summary">
            <h3>Customer &amp; Delivery</h3>
            <p><strong>Account:</strong> <?= e($order['account_name']) ?> (<?= e($order['account_email']) ?>)</p>
            <p><strong>Recipient:</strong> <?= e($order['customer_name']) ?></p>
            <p><strong>Phone:</strong> <?= e($order['customer_phone']) ?></p>
            <p><strong>Address:</strong> <?= e($order['shipping_address']) ?></p>
            <p><strong>Payment Method:</strong> <?= $order['payment_method'] === 'bank_transfer' ? 'Bank Transfer (Simulated)' : 'Cash on Delivery (COD)' ?></p>
            <p><strong>Placed On:</strong> <?= e($order['created_at']) ?></p>
            <?php if (!empty($order['note'])): ?>
                <p><strong>Customer Note:</strong> <?= e($order['note']) ?></p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Ordered Items Table -->
    <section class="admin-card">
        <div class="admin-card-header">
            <h2>Ordered Smartphones (<?= e((string) count($items)) ?>)</h2>
        </div>

        <div class="table-responsive">
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th>Product &amp; Configuration</th>
                        <th>SKU</th>
                        <th>Unit Price</th>
                        <th>Qty</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody class="tabular-nums">
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <strong><?= e($item['product_name']) ?></strong>
                                <div class="receipt-variant"><?= e($item['variant_label']) ?></div>
                            </td>
                            <td><code><?= e($item['sku'] ?? 'N/A') ?></code></td>
                            <td><?= e(formatPrice((float) $item['unit_price'])) ?></td>
                            <td><?= e((string) $item['quantity']) ?></td>
                            <td><strong><?= e(formatPrice((float) $item['subtotal'])) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="tabular-nums">
                    <tr>
                        <td colspan="4"><strong>Total Order Amount</strong></td>
                        <td><strong class="receipt-total"><?= e(formatPrice((float) $order['total_amount'])) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

