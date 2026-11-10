<?php
/**
 * Admin Inventory & Stock Management Page (NovaPhone).
 * Allows filtering variants by stock threshold (all, low, out, healthy), brand, or keyword,
 * and updating stock quantities directly with CSRF protection.
 */

require_once __DIR__ . '/../includes/admin_guard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Your security session expired. Please try again.');
        redirect('admin/inventory.php');
    }

    $variantId = (int) ($_POST['variant_id'] ?? 0);
    $newStock  = (int) ($_POST['stock_qty'] ?? -1);
    $returnFilter = trim((string) ($_POST['return_query'] ?? ''));

    if ($variantId <= 0 || $newStock < 0) {
        setFlash('error', 'Stock quantity must be 0 or greater.');
    } else {
        try {
            $updStmt = $pdo->prepare('UPDATE product_variants SET stock_qty = :stock_qty WHERE id = :id');
            $updStmt->execute([
                ':stock_qty' => $newStock,
                ':id'        => $variantId,
            ]);
            setFlash('success', 'Inventory stock updated to ' . $newStock . ' unit(s).');
        } catch (PDOException $e) {
            error_log('Inventory update failed: ' . $e->getMessage());
            setFlash('error', 'Unable to update stock right now.');
        }
    }

    $target = 'admin/inventory.php' . ($returnFilter !== '' ? ('?' . ltrim($returnFilter, '?')) : '');
    redirect(sanitizeRedirectPath($target, 'admin/inventory.php'));
}

$stockFilter = trim((string) ($_GET['stock'] ?? 'all'));
if (!in_array($stockFilter, ['all', 'low', 'out', 'healthy'], true)) {
    $stockFilter = 'all';
}
$brandIdFilter = (int) ($_GET['brand_id'] ?? 0);
$searchQuery   = trim((string) ($_GET['q'] ?? ''));

$whereClauses = ['1=1'];
$params = [];

if ($stockFilter === 'low') {
    $whereClauses[] = 'pv.stock_qty > 0 AND pv.stock_qty <= :low_max';
    $params[':low_max'] = LOW_STOCK_THRESHOLD;
} elseif ($stockFilter === 'out') {
    $whereClauses[] = 'pv.stock_qty <= 0';
} elseif ($stockFilter === 'healthy') {
    $whereClauses[] = 'pv.stock_qty > :healthy_min';
    $params[':healthy_min'] = LOW_STOCK_THRESHOLD;
}

if ($brandIdFilter > 0) {
    $whereClauses[] = 'p.brand_id = :brand_id';
    $params[':brand_id'] = $brandIdFilter;
}

if ($searchQuery !== '') {
    $escaped = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchQuery) . '%';
    $whereClauses[] = '(p.name LIKE :q_name OR pv.sku LIKE :q_sku)';
    $params[':q_name'] = $escaped;
    $params[':q_sku'] = $escaped;
}

$brands = $pdo->query('SELECT id, name FROM brands ORDER BY id ASC')->fetchAll();

$sql = '
    SELECT
        pv.id AS variant_id,
        pv.ram,
        pv.storage,
        pv.color_name,
        pv.color_hex,
        pv.price,
        pv.stock_qty,
        pv.sku,
        p.id AS product_id,
        p.name AS product_name,
        p.status AS product_status,
        b.name AS brand_name
    FROM product_variants pv
    INNER JOIN products p ON p.id = pv.product_id
    INNER JOIN brands b ON b.id = p.brand_id
    WHERE ' . implode(' AND ', $whereClauses) . '
    ORDER BY pv.stock_qty ASC, p.name ASC, pv.id ASC
';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$variants = $stmt->fetchAll();

$currentQueryStr = http_build_query(array_filter([
    'stock'    => $stockFilter !== 'all' ? $stockFilter : null,
    'brand_id' => $brandIdFilter > 0 ? $brandIdFilter : null,
    'q'        => $searchQuery !== '' ? $searchQuery : null,
]));

$adminTitle = 'Inventory Management';
$adminActive = 'inventory';

require_once __DIR__ . '/../includes/admin-header.php';
?>

<section class="admin-card">
    <div class="admin-card-header">
        <div>
            <h2>SKU Stock Levels (<?= e((string) count($variants)) ?>)</h2>
            <p>Monitor low-stock alerts (&le; <?= e((string) LOW_STOCK_THRESHOLD) ?>) and update inventory quantities directly</p>
        </div>
        <div class="admin-tab-pills">
            <a href="<?= e(BASE_URL) ?>/admin/inventory.php" class="btn btn-sm <?= $stockFilter === 'all' ? 'btn-primary' : 'btn-outline' ?>">All SKUs</a>
            <a href="<?= e(BASE_URL) ?>/admin/inventory.php?stock=low" class="btn btn-sm <?= $stockFilter === 'low' ? 'btn-primary' : 'btn-outline' ?>">Low Stock</a>
            <a href="<?= e(BASE_URL) ?>/admin/inventory.php?stock=out" class="btn btn-sm <?= $stockFilter === 'out' ? 'btn-primary' : 'btn-outline' ?>">Out of Stock</a>
            <a href="<?= e(BASE_URL) ?>/admin/inventory.php?stock=healthy" class="btn btn-sm <?= $stockFilter === 'healthy' ? 'btn-primary' : 'btn-outline' ?>">In Stock (&gt;<?= e((string) LOW_STOCK_THRESHOLD) ?>)</a>
        </div>
    </div>

    <form action="<?= e(BASE_URL) ?>/admin/inventory.php" method="get" class="admin-filter-bar">
        <input type="hidden" name="stock" value="<?= e($stockFilter) ?>">
        <input
            type="search"
            name="q"
            class="form-control"
            value="<?= e($searchQuery) ?>"
            placeholder="Search smartphone name or SKU..."
        >
        <select name="brand_id" class="form-control">
            <option value="">All Brands</option>
            <?php foreach ($brands as $b): ?>
                <option value="<?= e((string) $b['id']) ?>" <?= $brandIdFilter === (int) $b['id'] ? 'selected' : '' ?>>
                    <?= e($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline">Apply</button>
        <?php if ($stockFilter !== 'all' || $brandIdFilter > 0 || $searchQuery !== ''): ?>
            <a href="<?= e(BASE_URL) ?>/admin/inventory.php" class="btn btn-ghost">Reset</a>
        <?php endif; ?>
    </form>

    <?php if (empty($variants)): ?>
        <div class="empty-state">
            <p>No inventory variants match the selected filter.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Smartphone &amp; Brand</th>
                        <th>Configuration</th>
                        <th>SKU</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Update Stock</th>
                    </tr>
                </thead>
                <tbody class="tabular-nums">
                    <?php foreach ($variants as $v): ?>
                        <?php $qty = (int) $v['stock_qty']; ?>
                        <tr>
                            <td>
                                <a href="<?= e(BASE_URL) ?>/admin/product-form.php?id=<?= e((string) $v['product_id']) ?>" class="table-link">
                                    <strong><?= e($v['product_name']) ?></strong>
                                </a>
                                <div class="table-subtext"><?= e($v['brand_name']) ?></div>
                            </td>
                            <td>
                                <span class="variant-color-dot inline-dot" style="background-color: <?= e($v['color_hex']) ?>;" aria-hidden="true"></span>
                                <?= e($v['ram'] . ' / ' . $v['storage'] . ' - ' . $v['color_name']) ?>
                            </td>
                            <td><code><?= e($v['sku']) ?></code></td>
                            <td><?= e(formatPrice((float) $v['price'])) ?></td>
                            <td>
                                <?php if ($qty <= 0): ?>
                                    <span class="badge badge-out">Out of Stock</span>
                                <?php elseif ($qty <= LOW_STOCK_THRESHOLD): ?>
                                    <span class="badge badge-low">Low (<?= e((string) $qty) ?>)</span>
                                <?php else: ?>
                                    <span class="badge badge-stock">Healthy (<?= e((string) $qty) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form action="<?= e(BASE_URL) ?>/admin/inventory.php" method="post" class="inline-stock-form">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="variant_id" value="<?= e((string) $v['variant_id']) ?>">
                                    <input type="hidden" name="return_query" value="<?= e($currentQueryStr) ?>">
                                    <input
                                        type="number"
                                        name="stock_qty"
                                        class="form-control stock-qty-input"
                                        value="<?= e((string) $qty) ?>"
                                        min="0"
                                        required
                                        aria-label="Stock quantity for <?= e($v['sku']) ?>"
                                    >
                                    <button type="submit" class="btn btn-outline btn-sm">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

