<?php
/**
 * Admin Dashboard Overview (NovaPhone).
 * Displays store KPI cards, low-stock variant alerts (<= LOW_STOCK_THRESHOLD),
 * and recent customer orders.
 */

require_once __DIR__ . '/../includes/admin_guard.php';

// KPI metrics
$totalOrders = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$totalRevenue = (float) $pdo->query(
    "SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status <> 'cancelled'"
)->fetchColumn();
$totalCustomers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$lowStockStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM product_variants WHERE stock_qty <= :threshold'
);
$lowStockStmt->execute([':threshold' => LOW_STOCK_THRESHOLD]);
$lowStockCount = (int) $lowStockStmt->fetchColumn();

// Low-stock variants list
$lowStockListStmt = $pdo->prepare(
    'SELECT
        pv.id AS variant_id,
        pv.sku,
        pv.ram,
        pv.storage,
        pv.color_name,
        pv.stock_qty,
        p.id AS product_id,
        p.name AS product_name,
        b.name AS brand_name
     FROM product_variants pv
     INNER JOIN products p ON p.id = pv.product_id
     INNER JOIN brands b ON b.id = p.brand_id
     WHERE pv.stock_qty <= :threshold
     ORDER BY pv.stock_qty ASC, p.name ASC
     LIMIT 10'
);
$lowStockListStmt->execute([':threshold' => LOW_STOCK_THRESHOLD]);
$lowStockItems = $lowStockListStmt->fetchAll();

// Latest 6 orders
$latestOrdersStmt = $pdo->query(
    'SELECT id, order_code, customer_name, customer_phone, payment_method,
            payment_status, order_status, total_amount, created_at
     FROM orders
     ORDER BY created_at DESC, id DESC
     LIMIT 6'
);
$latestOrders = $latestOrdersStmt->fetchAll();

$adminTitle = 'Dashboard Overview';
$adminActive = 'dashboard';

require_once __DIR__ . '/../includes/admin-header.php';
?>

<section class="admin-kpi-grid" aria-label="Store performance summary">
    <article class="kpi-card">
        <span class="kpi-label">Total Orders</span>
        <strong class="kpi-value tabular-nums"><?= e((string) $totalOrders) ?></strong>
        <span class="kpi-sub">All recorded storefront orders</span>
    </article>

    <article class="kpi-card">
        <span class="kpi-label">Active Revenue</span>
        <strong class="kpi-value tabular-nums"><?= e(formatPrice($totalRevenue)) ?></strong>
        <span class="kpi-sub">Excluding cancelled orders</span>
    </article>

    <article class="kpi-card">
        <span class="kpi-label">Customers</span>
        <strong class="kpi-value tabular-nums"><?= e((string) $totalCustomers) ?></strong>
        <span class="kpi-sub">Registered customer accounts</span>
    </article>

    <article class="kpi-card <?= $lowStockCount > 0 ? 'kpi-card-alert' : '' ?>">
        <span class="kpi-label">Low Stock SKUs</span>
        <strong class="kpi-value tabular-nums"><?= e((string) $lowStockCount) ?></strong>
        <span class="kpi-sub">Variants with stock &le; <?= e((string) LOW_STOCK_THRESHOLD) ?></span>
    </article>
</section>

<div class="admin-dashboard-grid">
    <!-- Low Stock Alerts -->
    <section class="admin-card" aria-labelledby="low-stock-heading">
        <div class="admin-card-header">
            <div>
                <h2 id="low-stock-heading">Low Stock Alerts</h2>
                <p>Variants requiring restock (&le; <?= e((string) LOW_STOCK_THRESHOLD) ?> units)</p>
            </div>
            <a href="<?= e(BASE_URL) ?>/admin/inventory.php" class="btn btn-outline btn-sm">Open Inventory</a>
        </div>

        <?php if (empty($lowStockItems)): ?>
            <div class="empty-state">
                <p>All smartphone variants are currently well stocked.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Smartphone</th>
                            <th>Variant &amp; SKU</th>
                            <th>Stock</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="tabular-nums">
                        <?php foreach ($lowStockItems as $item): ?>
                            <tr>
                                <td>
                                    <strong><?= e($item['product_name']) ?></strong>
                                    <div class="table-subtext"><?= e($item['brand_name']) ?></div>
                                </td>
                                <td>
                                    <div><?= e($item['ram'] . ' / ' . $item['storage'] . ' - ' . $item['color_name']) ?></div>
                                    <div class="table-subtext">SKU: <?= e($item['sku']) ?></div>
                                </td>
                                <td>
                                    <?php if ((int) $item['stock_qty'] <= 0): ?>
                                        <span class="badge badge-out">Out (0)</span>
                                    <?php else: ?>
                                        <span class="badge badge-low"><?= e((string) $item['stock_qty']) ?> left</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= e(BASE_URL) ?>/admin/product-form.php?id=<?= e((string) $item['product_id']) ?>" class="btn btn-outline btn-sm">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- Latest Orders -->
    <section class="admin-card" aria-labelledby="recent-orders-heading">
        <div class="admin-card-header">
            <div>
                <h2 id="recent-orders-heading">Recent Orders</h2>
                <p>Latest customer checkouts</p>
            </div>
            <a href="<?= e(BASE_URL) ?>/admin/orders.php" class="btn btn-outline btn-sm">All Orders</a>
        </div>

        <?php if (empty($latestOrders)): ?>
            <div class="empty-state">
                <p>No orders have been placed yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody class="tabular-nums">
                        <?php foreach ($latestOrders as $order): ?>
                            <?php $orderMeta = getOrderStatusMeta((string) $order['order_status']); ?>
                            <tr>
                                <td>
                                    <a href="<?= e(BASE_URL) ?>/admin/order-detail.php?id=<?= e((string) $order['id']) ?>" class="table-link">
                                        <strong><?= e($order['order_code']) ?></strong>
                                    </a>
                                    <div class="table-subtext"><?= e(date('M d, H:i', strtotime((string) $order['created_at']))) ?></div>
                                </td>
                                <td>
                                    <div><?= e($order['customer_name']) ?></div>
                                    <div class="table-subtext"><?= e($order['customer_phone']) ?></div>
                                </td>
                                <td><strong><?= e(formatPrice((float) $order['total_amount'])) ?></strong></td>
                                <td><span class="badge <?= e($orderMeta['class']) ?>"><?= e($orderMeta['label']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

