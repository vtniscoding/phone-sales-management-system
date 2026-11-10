<?php
/**
 * Smartphone Catalog Page (NovaPhone).
 * Supports keyword search, multi-criteria filtering (brand, price range, RAM, storage, promotions),
 * sorting, active filter chips, and URL-preserved pagination via PDO Prepared Statements.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Read and sanitize GET query parameters
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$brandSlug   = trim((string) ($_GET['brand'] ?? ''));
$priceRange  = trim((string) ($_GET['price'] ?? ''));
$ramFilter   = trim((string) ($_GET['ram'] ?? ''));
$storageFilter = trim((string) ($_GET['storage'] ?? ''));
$promoOnly   = ($_GET['promo'] ?? '') === '1';
$sortKey     = trim((string) ($_GET['sort'] ?? 'featured'));
$currentPage = max(1, (int) ($_GET['page'] ?? 1));

$allowedSortOptions = [
    'featured'   => 'Featured Picks',
    'price_asc'  => 'Price: Low to High',
    'price_desc' => 'Price: High to Low',
    'discount'   => 'Biggest Discounts',
    'newest'     => 'Newest Arrivals',
];
if (!array_key_exists($sortKey, $allowedSortOptions)) {
    $sortKey = 'featured';
}

$priceRangeOptions = [
    'under-5m' => ['label' => 'Under 5M VND', 'min' => null, 'max' => 4999999],
    '5m-10m'   => ['label' => '5M - 10M VND', 'min' => 5000000, 'max' => 10000000],
    '10m-20m'  => ['label' => '10M - 20M VND', 'min' => 10000001, 'max' => 20000000],
    'over-20m' => ['label' => 'Over 20M VND', 'min' => 20000001, 'max' => null],
];
if ($priceRange !== '' && !array_key_exists($priceRange, $priceRangeOptions)) {
    $priceRange = '';
}

// Fetch filter metadata (brands, RAM options, storage options)
$brandsStmt = $pdo->query('SELECT id, name, slug FROM brands ORDER BY id ASC');
$brands = $brandsStmt->fetchAll();

$brandNameMap = [];
foreach ($brands as $b) {
    $brandNameMap[$b['slug']] = $b['name'];
}
if ($brandSlug !== '' && !isset($brandNameMap[$brandSlug])) {
    $brandSlug = '';
}

$ramOptions = $pdo->query('SELECT DISTINCT ram FROM product_variants ORDER BY ram ASC')->fetchAll(PDO::FETCH_COLUMN);
usort($ramOptions, static fn(string $a, string $b): int => parseCapacityMb($a) <=> parseCapacityMb($b));

$storageOptions = $pdo->query('SELECT DISTINCT storage FROM product_variants ORDER BY storage ASC')->fetchAll(PDO::FETCH_COLUMN);
usort($storageOptions, static fn(string $a, string $b): int => parseCapacityMb($a) <=> parseCapacityMb($b));

// Build dynamic WHERE clauses with unique named parameters (ATTR_EMULATE_PREPARES = false)
$whereClauses = ["p.status = 'active'"];
$params = [];

if ($searchQuery !== '') {
    $escapedLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchQuery) . '%';
    $whereClauses[] = '(p.name LIKE :q_name OR p.short_desc LIKE :q_desc OR p.chip_spec LIKE :q_chip OR b.name LIKE :q_brand)';
    $params[':q_name']  = $escapedLike;
    $params[':q_desc']  = $escapedLike;
    $params[':q_chip']  = $escapedLike;
    $params[':q_brand'] = $escapedLike;
}

if ($brandSlug !== '') {
    $whereClauses[] = 'b.slug = :brand_slug';
    $params[':brand_slug'] = $brandSlug;
}

if ($promoOnly) {
    $whereClauses[] = '(p.promo_label IS NOT NULL OR EXISTS (
        SELECT 1 FROM product_variants pv_promo
        WHERE pv_promo.product_id = p.id
          AND pv_promo.original_price IS NOT NULL
          AND pv_promo.original_price > pv_promo.price
    ))';
}

// Variant-level filters (price range, RAM, storage) combined inside one EXISTS subquery
$variantConditions = ['pv_f.product_id = p.id'];
if ($priceRange !== '') {
    $range = $priceRangeOptions[$priceRange];
    if ($range['min'] !== null) {
        $variantConditions[] = 'pv_f.price >= :min_price';
        $params[':min_price'] = $range['min'];
    }
    if ($range['max'] !== null) {
        $variantConditions[] = 'pv_f.price <= :max_price';
        $params[':max_price'] = $range['max'];
    }
}
if ($ramFilter !== '' && in_array($ramFilter, $ramOptions, true)) {
    $variantConditions[] = 'pv_f.ram = :ram_filter';
    $params[':ram_filter'] = $ramFilter;
} else {
    $ramFilter = '';
}
if ($storageFilter !== '' && in_array($storageFilter, $storageOptions, true)) {
    $variantConditions[] = 'pv_f.storage = :storage_filter';
    $params[':storage_filter'] = $storageFilter;
} else {
    $storageFilter = '';
}

if (count($variantConditions) > 1) {
    $whereClauses[] = 'EXISTS (SELECT 1 FROM product_variants pv_f WHERE ' . implode(' AND ', $variantConditions) . ')';
}

$whereSql = implode(' AND ', $whereClauses);

// Representative variant join (picks default or lowest-price variant per product)
$fromJoinSql = '
    FROM products p
    INNER JOIN brands b ON b.id = p.brand_id
    INNER JOIN product_variants pv ON pv.id = (
        SELECT pv_pick.id
        FROM product_variants pv_pick
        WHERE pv_pick.product_id = p.id
        ORDER BY pv_pick.is_default DESC, pv_pick.price ASC, pv_pick.id ASC
        LIMIT 1
    )
';

// Count matching products for pagination
$countStmt = $pdo->prepare('SELECT COUNT(*) ' . $fromJoinSql . ' WHERE ' . $whereSql);
$countStmt->execute($params);
$totalProducts = (int) $countStmt->fetchColumn();

$perPage = PRODUCTS_PER_PAGE;
$totalPages = max(1, (int) ceil($totalProducts / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $perPage;

// Whitelisted ORDER BY clause
$orderBySql = match ($sortKey) {
    'price_asc'  => 'pv.price ASC, p.id ASC',
    'price_desc' => 'pv.price DESC, p.id ASC',
    'discount'   => 'CASE WHEN pv.original_price IS NOT NULL AND pv.original_price > pv.price THEN ((pv.original_price - pv.price) / pv.original_price) ELSE 0 END DESC, p.id ASC',
    'newest'     => 'p.created_at DESC, p.id DESC',
    default      => 'p.is_featured DESC, p.id ASC',
};

$selectSql = "
    SELECT
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
    {$fromJoinSql}
    WHERE {$whereSql}
    ORDER BY {$orderBySql}
    LIMIT :limit OFFSET :offset
";

$productsStmt = $pdo->prepare($selectSql);
foreach ($params as $key => $val) {
    $productsStmt->bindValue($key, $val);
}
$productsStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$productsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$productsStmt->execute();
$products = $productsStmt->fetchAll();

// Build active filter chips for quick removal
$activeFilters = [];
if ($searchQuery !== '') {
    $activeFilters[] = ['label' => 'Keyword: "' . $searchQuery . '"', 'url' => buildQueryUrl('products.php', [], ['q', 'page'])];
}
if ($brandSlug !== '') {
    $activeFilters[] = ['label' => 'Brand: ' . $brandNameMap[$brandSlug], 'url' => buildQueryUrl('products.php', [], ['brand', 'page'])];
}
if ($priceRange !== '') {
    $activeFilters[] = ['label' => 'Price: ' . $priceRangeOptions[$priceRange]['label'], 'url' => buildQueryUrl('products.php', [], ['price', 'page'])];
}
if ($ramFilter !== '') {
    $activeFilters[] = ['label' => 'RAM: ' . $ramFilter, 'url' => buildQueryUrl('products.php', [], ['ram', 'page'])];
}
if ($storageFilter !== '') {
    $activeFilters[] = ['label' => 'Storage: ' . $storageFilter, 'url' => buildQueryUrl('products.php', [], ['storage', 'page'])];
}
if ($promoOnly) {
    $activeFilters[] = ['label' => 'Hot Deals Only', 'url' => buildQueryUrl('products.php', [], ['promo', 'page'])];
}

// Dynamic SEO heading & meta tags
$headingTitle = $promoOnly ? 'Hot Smartphone Deals' : 'All Official Smartphones';
if ($brandSlug !== '') {
    $headingTitle = $brandNameMap[$brandSlug] . ($promoOnly ? ' Deals' : ' Smartphones');
}
$pageTitle = $headingTitle . ' - ' . APP_NAME;
$pageDescription = 'Browse and filter official smartphones by brand, price range, RAM, and storage with 0% installment at NovaPhone.';
$activePage = $promoOnly ? 'deals' : 'products';

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-banner">
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(BASE_URL) ?>/index.php">Home</a>
            <span aria-hidden="true">/</span>
            <span><?= e($headingTitle) ?></span>
        </nav>
        <h1><?= e($headingTitle) ?></h1>
        <p>Filter by brand, budget, RAM, or storage to find your ideal official smartphone.</p>
    </div>
</section>

<section class="section">
    <div class="container catalog-layout">
        <!-- Filter Sidebar -->
        <aside class="filter-sidebar" aria-label="Product filters">
            <div class="filter-card">
                <div class="filter-card-header">
                    <h2>Filter Phones</h2>
                    <?php if (!empty($activeFilters)): ?>
                        <a href="<?= e(BASE_URL) ?>/products.php" class="filter-reset-link">Reset All</a>
                    <?php endif; ?>
                </div>

                <form action="<?= e(BASE_URL) ?>/products.php" method="get" class="filter-form">
                    <div class="form-group">
                        <label for="filter-q">Keyword</label>
                        <input
                            type="search"
                            id="filter-q"
                            name="q"
                            class="form-control"
                            value="<?= e($searchQuery) ?>"
                            placeholder="Model name, chip..."
                        >
                    </div>

                    <div class="form-group">
                        <label for="filter-brand">Brand</label>
                        <select id="filter-brand" name="brand" class="form-control">
                            <option value="">All Brands</option>
                            <?php foreach ($brands as $b): ?>
                                <option value="<?= e($b['slug']) ?>" <?= $brandSlug === $b['slug'] ? 'selected' : '' ?>>
                                    <?= e($b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="filter-price">Price Range</label>
                        <select id="filter-price" name="price" class="form-control">
                            <option value="">Any Price</option>
                            <?php foreach ($priceRangeOptions as $key => $opt): ?>
                                <option value="<?= e($key) ?>" <?= $priceRange === $key ? 'selected' : '' ?>>
                                    <?= e($opt['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="filter-ram">RAM</label>
                        <select id="filter-ram" name="ram" class="form-control">
                            <option value="">Any RAM</option>
                            <?php foreach ($ramOptions as $ram): ?>
                                <option value="<?= e($ram) ?>" <?= $ramFilter === $ram ? 'selected' : '' ?>>
                                    <?= e($ram) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="filter-storage">Internal Storage</label>
                        <select id="filter-storage" name="storage" class="form-control">
                            <option value="">Any Storage</option>
                            <?php foreach ($storageOptions as $storage): ?>
                                <option value="<?= e($storage) ?>" <?= $storageFilter === $storage ? 'selected' : '' ?>>
                                    <?= e($storage) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group filter-checkbox-row">
                        <label class="checkbox-label">
                            <input type="checkbox" name="promo" value="1" <?= $promoOnly ? 'checked' : '' ?>>
                            <span>Promotions &amp; Discounts Only</span>
                        </label>
                    </div>

                    <?php if ($sortKey !== 'featured'): ?>
                        <input type="hidden" name="sort" value="<?= e($sortKey) ?>">
                    <?php endif; ?>

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary btn-block">Apply Filters</button>
                        <?php if (!empty($activeFilters)): ?>
                            <a href="<?= e(BASE_URL) ?>/products.php" class="btn btn-outline btn-block">Clear Filters</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </aside>

        <!-- Catalog Main Results -->
        <div class="catalog-main">
            <div class="catalog-toolbar">
                <p class="catalog-count">
                    <?php if ($totalProducts > 0): ?>
                        Showing <strong><?= e((string) ($offset + 1)) ?></strong>&ndash;<strong><?= e((string) min($offset + $perPage, $totalProducts)) ?></strong>
                        of <strong><?= e((string) $totalProducts) ?></strong> smartphones
                    <?php else: ?>
                        <strong>0</strong> smartphones matched your criteria
                    <?php endif; ?>
                </p>

                <form action="<?= e(BASE_URL) ?>/products.php" method="get" class="sort-form">
                    <?php if ($searchQuery !== ''): ?><input type="hidden" name="q" value="<?= e($searchQuery) ?>"><?php endif; ?>
                    <?php if ($brandSlug !== ''): ?><input type="hidden" name="brand" value="<?= e($brandSlug) ?>"><?php endif; ?>
                    <?php if ($priceRange !== ''): ?><input type="hidden" name="price" value="<?= e($priceRange) ?>"><?php endif; ?>
                    <?php if ($ramFilter !== ''): ?><input type="hidden" name="ram" value="<?= e($ramFilter) ?>"><?php endif; ?>
                    <?php if ($storageFilter !== ''): ?><input type="hidden" name="storage" value="<?= e($storageFilter) ?>"><?php endif; ?>
                    <?php if ($promoOnly): ?><input type="hidden" name="promo" value="1"><?php endif; ?>

                    <label for="sort-select">Sort by:</label>
                    <select id="sort-select" name="sort" class="form-control sort-select" onchange="this.form.submit()">
                        <?php foreach ($allowedSortOptions as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $sortKey === $key ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if (!empty($activeFilters)): ?>
                <div class="active-filter-chips" aria-label="Active filters">
                    <?php foreach ($activeFilters as $chip): ?>
                        <a href="<?= e($chip['url']) ?>" class="filter-chip" title="Remove filter">
                            <span><?= e($chip['label']) ?></span>
                            <span aria-hidden="true">&times;</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($products)): ?>
                <div class="product-grid catalog-grid">
                    <?php foreach ($products as $product): ?>
                        <?php require __DIR__ . '/includes/product-card.php'; ?>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="Catalog pagination">
                        <?php if ($currentPage > 1): ?>
                            <a href="<?= e(buildQueryUrl('products.php', ['page' => $currentPage - 1])) ?>" class="page-link">&laquo; Prev</a>
                        <?php else: ?>
                            <span class="page-link is-disabled" aria-disabled="true">&laquo; Prev</span>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <?php if ($p === $currentPage): ?>
                                <span class="page-link is-active" aria-current="page"><?= e((string) $p) ?></span>
                            <?php else: ?>
                                <a href="<?= e(buildQueryUrl('products.php', ['page' => $p])) ?>" class="page-link"><?= e((string) $p) ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($currentPage < $totalPages): ?>
                            <a href="<?= e(buildQueryUrl('products.php', ['page' => $currentPage + 1])) ?>" class="page-link">Next &raquo;</a>
                        <?php else: ?>
                            <span class="page-link is-disabled" aria-disabled="true">Next &raquo;</span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No Smartphones Found</h3>
                    <p>Try clearing some filters or searching for another model such as iPhone, Galaxy, Xiaomi, OPPO, or realme.</p>
                    <a href="<?= e(BASE_URL) ?>/products.php" class="btn btn-primary">View All Smartphones</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

