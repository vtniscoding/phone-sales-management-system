<?php
/**
 * Admin Customers Directory (NovaPhone).
 * Lists registered customer accounts along with total order count,
 * active spend (excluding cancelled orders), and latest order timestamp.
 */

require_once __DIR__ . '/../includes/admin_guard.php';

$searchQuery = trim((string) ($_GET['q'] ?? ''));

$whereClauses = ["u.role = 'customer'"];
$params = [];

if ($searchQuery !== '') {
    $escaped = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchQuery) . '%';
    $whereClauses[] = '(u.full_name LIKE :q_name OR u.email LIKE :q_email OR u.phone LIKE :q_phone)';
    $params[':q_name']  = $escaped;
    $params[':q_email'] = $escaped;
    $params[':q_phone'] = $escaped;
}

$sql = "
    SELECT
        u.id,
        u.full_name,
        u.email,
        u.phone,
        u.address,
        u.created_at,
        COUNT(o.id) AS total_orders,
        COALESCE(SUM(CASE WHEN o.order_status <> 'cancelled' THEN o.total_amount ELSE 0 END), 0) AS total_spent,
        MAX(o.created_at) AS last_order_at
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id
    WHERE " . implode(' AND ', $whereClauses) . '
    GROUP BY u.id
    ORDER BY total_spent DESC, u.created_at DESC
';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$adminTitle = 'Customers Directory';
$adminActive = 'customers';

require_once __DIR__ . '/../includes/admin-header.php';
?>

<section class="admin-card">
    <div class="admin-card-header">
        <div>
            <h2>Registered Customers (<?= e((string) count($customers)) ?>)</h2>
            <p>View customer contact details, order volume, and cumulative spend</p>
        </div>
    </div>

    <form action="<?= e(BASE_URL) ?>/admin/customers.php" method="get" class="admin-filter-bar">
        <input
            type="search"
            name="q"
            class="form-control"
            value="<?= e($searchQuery) ?>"
            placeholder="Search customer name, email, or phone..."
        >
        <button type="submit" class="btn btn-outline">Search</button>
        <?php if ($searchQuery !== ''): ?>
            <a href="<?= e(BASE_URL) ?>/admin/customers.php" class="btn btn-ghost">Reset</a>
        <?php endif; ?>
    </form>

    <?php if (empty($customers)): ?>
        <div class="empty-state">
            <p>No customer accounts matched your search.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Default Address</th>
                        <th>Orders</th>
                        <th>Active Spend</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody class="tabular-nums">
                    <?php foreach ($customers as $cust): ?>
                        <tr>
                            <td>
                                <strong><?= e($cust['full_name']) ?></strong>
                                <div class="table-subtext">ID #<?= e((string) $cust['id']) ?></div>
                            </td>
                            <td>
                                <div><?= e($cust['email']) ?></div>
                                <div class="table-subtext"><?= e($cust['phone']) ?></div>
                            </td>
                            <td><?= e($cust['address'] ?: 'Not specified') ?></td>
                            <td>
                                <strong><?= e((string) $cust['total_orders']) ?></strong>
                                <?php if (!empty($cust['last_order_at'])): ?>
                                    <div class="table-subtext">Last: <?= e(date('M d, Y', strtotime((string) $cust['last_order_at']))) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= e(formatPrice((float) $cust['total_spent'])) ?></strong></td>
                            <td><?= e(date('M d, Y', strtotime((string) $cust['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

