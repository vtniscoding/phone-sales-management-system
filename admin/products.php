<?php
/**
 * Admin Smartphone Products Catalog List (NovaPhone).
 * Lists all smartphones with brand, price range, total stock, variant count,
 * search/filter controls, and safe deletion (blocking deletion if order history exists).
 */

require_once __DIR__ . '/../includes/admin_guard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Your security session expired. Please try again.');
        redirect('admin/products.php');
    }

    $action = trim((string) ($_POST['action'] ?? ''));
    $productId = (int) ($_POST['product_id'] ?? 0);

    if ($action === 'delete' && $productId > 0) {
        try {
            $prodStmt = $pdo->prepare('SELECT id, name FROM products WHERE id = :id LIMIT 1');
            $prodStmt->execute([':id' => $productId]);
            $prod = $prodStmt->fetch();

            if (!$prod) {
                setFlash('error', 'Product not found.');
                redirect('admin/products.php');
            }

            // Prevent deleting products referenced by historical order_items
            $orderRefStmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM order_items oi
                 INNER JOIN product_variants pv ON pv.id = oi.variant_id
                 WHERE pv.product_id = :product_id'
            );
            $orderRefStmt->execute([':product_id' => $productId]);
            if ((int) $orderRefStmt->fetchColumn() > 0) {
                setFlash('error', 'Cannot delete "' . $prod['name'] . '" because it appears in existing orders. Edit it and set status to Inactive instead.');
                redirect('admin/products.php');
            }

            // Collect uploaded image files to clean up disk after successful DB delete
            $imgStmt = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id = :product_id');
            $imgStmt->execute([':product_id' => $productId]);
            $imgPaths = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

            $delStmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
            $delStmt->execute([':id' => $productId]);

            foreach ($imgPaths as $relPath) {
                if (is_string($relPath) && str_starts_with($relPath, 'assets/uploads/products/')) {
                    $abs = ROOT_PATH . '/' . $relPath;
                    if (is_file($abs)) {
                        @unlink($abs);
                    }
                }
            }

            setFlash('success', 'Product "' . $prod['name'] . '" deleted successfully.');
            redirect('admin/products.php');
        } catch (PDOException $e) {
            error_log('Admin product delete failed: ' . $e->getMessage());
            setFlash('error', 'Unable to delete product right now.');
            redirect('admin/products.php');
        }
    }
}

$searchQuery = trim((string) ($_GET['q'] ?? ''));
$brandIdFilter = (int) ($_GET['brand_id'] ?? 0);
$statusFilter = trim((string) ($_GET['status'] ?? ''));

$whereClauses = ['1=1'];
$params = [];

if ($searchQuery !== '') {
    $escaped = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchQuery) . '%';
    $whereClauses[] = '(p.name LIKE :q_name OR p.chip_spec LIKE :q_chip OR p.slug LIKE :q_slug)';
    $params[':q_name'] = $escaped;
    $params[':q_chip'] = $escaped;
    $params[':q_slug'] = $escaped;
}
if ($brandIdFilter > 0) {
    $whereClauses[] = 'p.brand_id = :brand_id';
    $params[':brand_id'] = $brandIdFilter;
}
if (in_array($statusFilter, ['active', 'inactive'], true)) {
    $whereClauses[] = 'p.status = :status';
    $params[':status'] = $statusFilter;
} else {
    $statusFilter = '';
}

$brands = $pdo->query('SELECT id, name FROM brands ORDER BY id ASC')->fetchAll();

$sql = '
    SELECT
        p.id,
        p.name,
        p.slug,
        p.is_featured,
        p.promo_label,
        p.status,
        b.name AS brand_name,
        COUNT(DISTINCT pv.id) AS variant_count,
        COALESCE(MIN(pv.price), 0) AS min_price,
        COALESCE(MAX(pv.price), 0) AS max_price,
        COALESCE(SUM(pv.stock_qty), 0) AS total_stock,
        (SELECT pi.image_path
         FROM product_images pi
         WHERE pi.product_id = p.id
         ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
         LIMIT 1) AS primary_image
    FROM products p
    INNER JOIN brands b ON b.id = p.brand_id
    LEFT JOIN product_variants pv ON pv.product_id = p.id
    WHERE ' . implode(' AND ', $whereClauses) . '
    GROUP BY p.id
    ORDER BY p.id DESC
';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$adminTitle = 'Manage Smartphones';
$adminActive = 'products';

require_once __DIR__ . '/../includes/admin-header.php';
?>

<section class="admin-card">
    <div class="admin-card-header">
        <div>
            <h2>Smartphones Catalog (<?= e((string) count($products)) ?>)</h2>
            <p>Manage general details, specs, variants, pricing, and images</p>
        </div>
        <a href="<?= e(BASE_URL) ?>/admin/product-form.php" class="btn btn-primary">+ Add Smartphone</a>
    </div>

    <form action="<?= e(BASE_URL) ?>/admin/products.php" method="get" class="admin-filter-bar">
        <input
            type="search"
            name="q"
            class="form-control"
            value="<?= e($searchQuery) ?>"
            placeholder="Search name, chip, slug..."
        >
        <select name="brand_id" class="form-control">
            <option value="">All Brands</option>
            <?php foreach ($brands as $b): ?>
                <option value="<?= e((string) $b['id']) ?>" <?= $brandIdFilter === (int) $b['id'] ? 'selected' : '' ?>>
                    <?= e($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="form-control">
            <option value="">All Statuses</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <button type="submit" class="btn btn-outline">Filter</button>
        <?php if ($searchQuery !== '' || $brandIdFilter > 0 || $statusFilter !== ''): ?>
            <a href="<?= e(BASE_URL) ?>/admin/products.php" class="btn btn-ghost">Reset</a>
        <?php endif; ?>
    </form>

    <?php if (empty($products)): ?>
        <div class="empty-state">
            <p>No smartphones match your filter criteria.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Phone</th>
                        <th>Brand</th>
                        <th>Price Range</th>
                        <th>Variants</th>
                        <th>Total Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody class="tabular-nums">
                    <?php foreach ($products as $prod): ?>
                        <?php
                        $minP = (float) $prod['min_price'];
                        $maxP = (float) $prod['max_price'];
                        $stock = (int) $prod['total_stock'];
                        ?>
                        <tr>
                            <td>
                                <div class="admin-product-cell">
                                    <img
                                        src="<?= e(resolveImageUrl($prod['primary_image'])) ?>"
                                        alt="<?= e($prod['name']) ?>"
                                        class="admin-product-thumb"
                                        width="56"
                                        height="42"
                                    >
                                    <div>
                                        <strong><?= e($prod['name']) ?></strong>
                                        <div class="table-subtext">
                                            /<?= e($prod['slug']) ?>
                                            <?php if ((int) $prod['is_featured'] === 1): ?>
                                                &bull; <span class="text-primary">Featured</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td><?= e($prod['brand_name']) ?></td>
                            <td>
                                <?php if ($minP === $maxP): ?>
                                    <strong><?= e(formatPrice($minP)) ?></strong>
                                <?php else: ?>
                                    <strong><?= e(formatPrice($minP)) ?></strong>
                                    <div class="table-subtext">to <?= e(formatPrice($maxP)) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e((string) $prod['variant_count']) ?></td>
                            <td>
                                <?php if ($stock <= 0): ?>
                                    <span class="badge badge-out">0</span>
                                <?php elseif ($stock <= LOW_STOCK_THRESHOLD): ?>
                                    <span class="badge badge-low"><?= e((string) $stock) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-stock"><?= e((string) $stock) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($prod['status'] === 'active'): ?>
                                    <span class="badge badge-stock">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-out">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= e(BASE_URL) ?>/admin/product-form.php?id=<?= e((string) $prod['id']) ?>" class="btn btn-outline btn-sm">Edit</a>
                                    <form action="<?= e(BASE_URL) ?>/admin/products.php" method="post" class="inline-form" data-confirm-delete="Delete <?= e($prod['name']) ?> and all its variants?">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="product_id" value="<?= e((string) $prod['id']) ?>">
                                        <button type="submit" class="btn btn-danger-outline btn-sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

