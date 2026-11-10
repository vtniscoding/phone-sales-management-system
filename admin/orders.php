<?php
/**
 * Admin Orders Management List (NovaPhone).
 * Supports filtering orders by lifecycle status, payment status, and search query,
 * with pagination and quick inspection links.
 */

require_once __DIR__ . '/../includes/admin_guard.php';

$statusFilter  = trim((string) ($_GET['status'] ?? ''));
$paymentFilter = trim((string) ($_GET['payment'] ?? ''));
$searchQuery   = trim((string) ($_GET['q'] ?? ''));
$currentPage   = max(1, (int) ($_GET['page'] ?? 1));
$perPage       = 10;

$allowedStatuses = ['pending', 'confirmed', 'shipping', 'completed', 'cancelled'];
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}
if ($paymentFilter !== '' && !in_array($paymentFilter, ['paid', 'unpaid'], true)) {
    $paymentFilter = '';
}

$whereClauses = ['1=1'];
$params = [];

if ($statusFilter !== '') {
    $whereClauses[] = 'o.order_status = :order_status';
    $params[':order_status'] = $statusFilter;
}
if ($paymentFilter !== '') {
    $whereClauses[] = 'o.payment_status = :payment_status';
    $params[':payment_status'] = $paymentFilter;
}
if ($searchQuery !== '') {
    $escaped = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchQuery) . '%';
    $whereClauses[] = '(o.order_code LIKE :q_code OR o.customer_name LIKE :q_name OR o.customer_phone LIKE :q_phone)';
    $params[':q_code']  = $escaped;
    $params[':q_name']  = $escaped;
    $params[':q_phone'] = $escaped;
}

$whereSql = implode(' AND ', $whereClauses);

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM orders o WHERE ' . $whereSql);
$countStmt->execute($params);
$totalOrders = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalOrders / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $perPage;

$sql = "
    SELECT
        o.id,
        o.order_code,
        o.customer_name,
        o.customer_phone,
        o.payment_method,
        o.payment_status,
        o.order_status,
        o.total_amount,
        o.created_at,
        (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS total_items
    FROM orders o
    WHERE {$whereSql}
    ORDER BY o.created_at DESC, o.id DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();

$adminTitle = 'Manage Orders';
$adminActive = 'orders';

require_once __DIR__ . '/../includes/admin-header.php';
?>

<section class="admin-card">
    <div class="admin-card-header">
        <div>
            <h2>Customer Orders (<?= e((string) $totalOrders) ?>)</h2>
            <p>Review order items, verify payments, and update delivery statuses</p>
        </div>
        <div class="admin-tab-pills">
            <a href="<?= e(BASE_URL) ?>/admin/orders.php" class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-outline' ?>">All</a>
            <?php foreach ($allowedStatuses as $st): ?>
                <a href="<?= e(BASE_URL) ?>/admin/orders.php?status=<?= e($st) ?>" class="btn btn-sm <?= $statusFilter === $st ? 'btn-primary' : 'btn-outline' ?>">
                    <?= e(ucfirst($st)) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <form action="<?= e(BASE_URL) ?>/admin/orders.php" method="get" class="admin-filter-bar">
        <?php if ($statusFilter !== ''): ?>
            <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
        <?php endif; ?>
        <input
            type="search"
            name="q"
            class="form-control"
            value="<?= e($searchQuery) ?>"
            placeholder="Search order code, customer name, or phone..."
        >
        <select name="payment" class="form-control">
            <option value="">All Payment Statuses</option>
            <option value="unpaid" <?= $paymentFilter === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
            <option value="paid" <?= $paymentFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
        </select>
        <button type="submit" class="btn btn-outline">Filter</button>
        <?php if ($statusFilter !== '' || $paymentFilter !== '' || $searchQuery !== ''): ?>
            <a href="<?= e(BASE_URL) ?>/admin/orders.php" class="btn btn-ghost">Reset</a>
        <?php endif; ?>
    </form>

    <?php if (empty($orders)): ?>
        <div class="empty-state">
            <p>No orders found for the current filters.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order Code</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Order Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody class="tabular-nums">
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $orderMeta = getOrderStatusMeta((string) $order['order_status']);
                        $payMeta = getPaymentStatusMeta((string) $order['payment_status']);
                        ?>
                        <tr>
                            <td>
                                <strong><?= e($order['order_code']) ?></strong>
                                <div class="table-subtext"><?= e(date('M d, Y H:i', strtotime((string) $order['created_at']))) ?></div>
                            </td>
                            <td>
                                <div><?= e($order['customer_name']) ?></div>
                                <div class="table-subtext"><?= e($order['customer_phone']) ?></div>
                            </td>
                            <td><?= e((string) $order['total_items']) ?> unit(s)</td>
                            <td><strong><?= e(formatPrice((float) $order['total_amount'])) ?></strong></td>
                            <td>
                                <div><?= $order['payment_method'] === 'bank_transfer' ? 'Bank Transfer' : 'COD' ?></div>
                                <span class="badge <?= e($payMeta['class']) ?>"><?= e($payMeta['label']) ?></span>
                            </td>
                            <td><span class="badge <?= e($orderMeta['class']) ?>"><?= e($orderMeta['label']) ?></span></td>
                            <td>
                                <a href="<?= e(BASE_URL) ?>/admin/order-detail.php?id=<?= e((string) $order['id']) ?>" class="btn btn-outline btn-sm">
                                    Inspect
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Orders pagination">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php if ($p === $currentPage): ?>
                        <span class="page-link is-active"><?= e((string) $p) ?></span>
                    <?php else: ?>
                        <a href="<?= e(buildQueryUrl('admin/orders.php', ['page' => $p])) ?>" class="page-link"><?= e((string) $p) ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

