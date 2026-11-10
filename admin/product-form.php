<?php
/**
 * Admin Create / Edit Smartphone Form (NovaPhone).
 * Manages general phone info, 5 core specs, secure image upload (finfo MIME + <= 2MB),
 * and dynamic product variants inside an atomic PDO transaction.
 */

require_once __DIR__ . '/../includes/admin_guard.php';

$productId = (int) ($_GET['id'] ?? ($_POST['product_id'] ?? 0));
$isEdit = $productId > 0;

$brands = $pdo->query('SELECT id, name FROM brands ORDER BY id ASC')->fetchAll();

$productData = [
    'brand_id'     => $brands[0]['id'] ?? 0,
    'name'         => '',
    'slug'         => '',
    'short_desc'   => '',
    'description'  => '',
    'screen_spec'  => '',
    'chip_spec'    => '',
    'battery_spec' => '',
    'camera_spec'  => '',
    'os_spec'      => '',
    'promo_label'  => '',
    'is_featured'  => 0,
    'status'       => 'active',
];

$currentPrimaryImage = null;
$variants = [
    [
        'id'             => 0,
        'ram'            => '8GB',
        'storage'        => '256GB',
        'color_name'     => 'Black',
        'color_hex'      => '#1E293B',
        'price'          => '',
        'original_price' => '',
        'stock_qty'      => '10',
        'sku'            => '',
    ],
];
$defaultVariantIndex = 0;

if ($isEdit) {
    $prodStmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
    $prodStmt->execute([':id' => $productId]);
    $existingProd = $prodStmt->fetch();

    if (!$existingProd) {
        setFlash('error', 'Smartphone not found.');
        redirect('admin/products.php');
    }

    $productData = [
        'brand_id'     => (int) $existingProd['brand_id'],
        'name'         => (string) $existingProd['name'],
        'slug'         => (string) $existingProd['slug'],
        'short_desc'   => (string) ($existingProd['short_desc'] ?? ''),
        'description'  => (string) ($existingProd['description'] ?? ''),
        'screen_spec'  => (string) $existingProd['screen_spec'],
        'chip_spec'    => (string) $existingProd['chip_spec'],
        'battery_spec' => (string) $existingProd['battery_spec'],
        'camera_spec'  => (string) $existingProd['camera_spec'],
        'os_spec'      => (string) $existingProd['os_spec'],
        'promo_label'  => (string) ($existingProd['promo_label'] ?? ''),
        'is_featured'  => (int) $existingProd['is_featured'],
        'status'       => (string) $existingProd['status'],
    ];

    $imgStmt = $pdo->prepare(
        'SELECT id, image_path, alt_text FROM product_images
         WHERE product_id = :product_id
         ORDER BY is_primary DESC, sort_order ASC, id ASC LIMIT 1'
    );
    $imgStmt->execute([':product_id' => $productId]);
    $currentPrimaryImage = $imgStmt->fetch() ?: null;

    $varStmt = $pdo->prepare(
        'SELECT id, ram, storage, color_name, color_hex, price, original_price, stock_qty, sku, is_default
         FROM product_variants
         WHERE product_id = :product_id
         ORDER BY is_default DESC, id ASC'
    );
    $varStmt->execute([':product_id' => $productId]);
    $fetchedVars = $varStmt->fetchAll();

    if (!empty($fetchedVars)) {
        $variants = [];
        foreach ($fetchedVars as $idx => $fv) {
            if ((int) $fv['is_default'] === 1) {
                $defaultVariantIndex = $idx;
            }
            $variants[] = [
                'id'             => (int) $fv['id'],
                'ram'            => (string) $fv['ram'],
                'storage'        => (string) $fv['storage'],
                'color_name'     => (string) $fv['color_name'],
                'color_hex'      => (string) $fv['color_hex'],
                'price'          => (string) $fv['price'],
                'original_price' => $fv['original_price'] !== null ? (string) $fv['original_price'] : '',
                'stock_qty'      => (string) $fv['stock_qty'],
                'sku'            => (string) $fv['sku'],
            ];
        }
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'Your security session expired. Please refresh and try again.';
    } else {
        $productData['brand_id']     = (int) ($_POST['brand_id'] ?? 0);
        $productData['name']         = trim((string) ($_POST['name'] ?? ''));
        $productData['slug']         = trim((string) ($_POST['slug'] ?? ''));
        $productData['short_desc']   = trim((string) ($_POST['short_desc'] ?? ''));
        $productData['description']  = trim((string) ($_POST['description'] ?? ''));
        $productData['screen_spec']  = trim((string) ($_POST['screen_spec'] ?? ''));
        $productData['chip_spec']    = trim((string) ($_POST['chip_spec'] ?? ''));
        $productData['battery_spec'] = trim((string) ($_POST['battery_spec'] ?? ''));
        $productData['camera_spec']  = trim((string) ($_POST['camera_spec'] ?? ''));
        $productData['os_spec']      = trim((string) ($_POST['os_spec'] ?? ''));
        $productData['promo_label']  = trim((string) ($_POST['promo_label'] ?? ''));
        $productData['is_featured']  = isset($_POST['is_featured']) ? 1 : 0;
        $productData['status']       = in_array($_POST['status'] ?? 'active', ['active', 'inactive'], true) ? $_POST['status'] : 'active';

        if ($productData['brand_id'] <= 0) {
            $errors['brand_id'] = 'Please select a brand.';
        }
        if ($productData['name'] === '' || mb_strlen($productData['name']) > 160) {
            $errors['name'] = 'Product name is required (max 160 characters).';
        }

        $finalSlug = $productData['slug'] !== '' ? slugify($productData['slug']) : slugify($productData['name']);
        if ($finalSlug === '' || mb_strlen($finalSlug) > 180) {
            $errors['slug'] = 'Please provide a valid URL slug.';
        }
        $productData['slug'] = $finalSlug;

        foreach (['screen_spec' => 'Display', 'chip_spec' => 'Processor', 'battery_spec' => 'Battery', 'camera_spec' => 'Camera', 'os_spec' => 'Operating System'] as $field => $label) {
            if ($productData[$field] === '') {
                $errors[$field] = $label . ' specification is required.';
            }
        }

        // Parse submitted dynamic variant rows
        $vIds      = $_POST['variant_id'] ?? [];
        $vRams     = $_POST['ram'] ?? [];
        $vStorages = $_POST['storage'] ?? [];
        $vColors   = $_POST['color_name'] ?? [];
        $vHexes    = $_POST['color_hex'] ?? [];
        $vPrices   = $_POST['price'] ?? [];
        $vOrigs    = $_POST['original_price'] ?? [];
        $vStocks   = $_POST['stock_qty'] ?? [];
        $vSkus     = $_POST['sku'] ?? [];
        $defaultVariantIndex = max(0, (int) ($_POST['default_variant_index'] ?? 0));

        $submittedVariants = [];
        $seenSkus = [];
        $rowCount = is_array($vSkus) ? count($vSkus) : 0;

        for ($i = 0; $i < $rowCount; $i++) {
            $vId     = (int) ($vIds[$i] ?? 0);
            $ram     = trim((string) ($vRams[$i] ?? ''));
            $storage = trim((string) ($vStorages[$i] ?? ''));
            $color   = trim((string) ($vColors[$i] ?? ''));
            $hex     = strtoupper(trim((string) ($vHexes[$i] ?? '#1E293B')));
            $price   = trim((string) ($vPrices[$i] ?? ''));
            $orig    = trim((string) ($vOrigs[$i] ?? ''));
            $stock   = trim((string) ($vStocks[$i] ?? '0'));
            $sku     = strtoupper(trim((string) ($vSkus[$i] ?? '')));

            if (preg_match('/^#[0-9A-F]{6}$/', $hex) !== 1) {
                $hex = '#1E293B';
            }

            $submittedVariants[] = [
                'id'             => $vId,
                'ram'            => $ram,
                'storage'        => $storage,
                'color_name'     => $color,
                'color_hex'      => $hex,
                'price'          => $price,
                'original_price' => $orig,
                'stock_qty'      => $stock,
                'sku'            => $sku,
            ];

            if ($ram === '' || $storage === '' || $color === '' || $sku === '') {
                $errors['variants'] = 'All variants must specify RAM, Storage, Color, and SKU.';
            } elseif (!is_numeric($price) || (float) $price <= 0) {
                $errors['variants'] = 'Every variant must have a positive selling price.';
            } elseif ($orig !== '' && (!is_numeric($orig) || (float) $orig <= 0)) {
                $errors['variants'] = 'Original price must be empty or a positive number.';
            } elseif (!is_numeric($stock) || (int) $stock < 0) {
                $errors['variants'] = 'Stock quantity must be 0 or greater.';
            } elseif (isset($seenSkus[$sku])) {
                $errors['variants'] = 'Duplicate SKU "' . $sku . '" detected within variants.';
            }
            $seenSkus[$sku] = true;
        }

        if (empty($submittedVariants)) {
            $errors['variants'] = 'At least one product variant is required.';
        } else {
            $variants = $submittedVariants;
            if ($defaultVariantIndex >= count($variants)) {
                $defaultVariantIndex = 0;
            }
        }

        // Optional image upload check
        $uploadedImagePath = null;
        if (isset($_FILES['product_image']) && ($_FILES['product_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploadRes = handleProductImageUpload($_FILES['product_image']);
            if (!$uploadRes['ok']) {
                $errors['product_image'] = $uploadRes['error'];
            } else {
                $uploadedImagePath = $uploadRes['path'];
            }
        }

        if (empty($errors)) {
            try {
                // Verify slug uniqueness
                $slugCheck = $pdo->prepare('SELECT id FROM products WHERE slug = :slug AND id <> :id LIMIT 1');
                $slugCheck->execute([':slug' => $productData['slug'], ':id' => $productId]);
                if ($slugCheck->fetch()) {
                    throw new RuntimeException('Another smartphone already uses the slug "' . $productData['slug'] . '".');
                }

                // Verify each SKU uniqueness against existing variants
                $skuCheckStmt = $pdo->prepare('SELECT id FROM product_variants WHERE sku = :sku AND id <> :id LIMIT 1');
                foreach ($variants as $v) {
                    $skuCheckStmt->execute([':sku' => $v['sku'], ':id' => $v['id']]);
                    if ($skuCheckStmt->fetch()) {
                        throw new RuntimeException('SKU "' . $v['sku'] . '" is already assigned to another variant.');
                    }
                }

                $pdo->beginTransaction();

                if ($isEdit) {
                    $updProd = $pdo->prepare(
                        'UPDATE products SET
                            brand_id = :brand_id, name = :name, slug = :slug,
                            short_desc = :short_desc, description = :description,
                            screen_spec = :screen_spec, chip_spec = :chip_spec,
                            battery_spec = :battery_spec, camera_spec = :camera_spec,
                            os_spec = :os_spec, promo_label = :promo_label,
                            is_featured = :is_featured, status = :status
                         WHERE id = :id'
                    );
                    $updProd->execute([
                        ':brand_id'     => $productData['brand_id'],
                        ':name'         => $productData['name'],
                        ':slug'         => $productData['slug'],
                        ':short_desc'   => $productData['short_desc'] !== '' ? $productData['short_desc'] : null,
                        ':description'  => $productData['description'] !== '' ? $productData['description'] : null,
                        ':screen_spec'  => $productData['screen_spec'],
                        ':chip_spec'    => $productData['chip_spec'],
                        ':battery_spec' => $productData['battery_spec'],
                        ':camera_spec'  => $productData['camera_spec'],
                        ':os_spec'      => $productData['os_spec'],
                        ':promo_label'  => $productData['promo_label'] !== '' ? $productData['promo_label'] : null,
                        ':is_featured'  => $productData['is_featured'],
                        ':status'       => $productData['status'],
                        ':id'           => $productId,
                    ]);
                } else {
                    $insProd = $pdo->prepare(
                        'INSERT INTO products (
                            brand_id, name, slug, short_desc, description,
                            screen_spec, chip_spec, battery_spec, camera_spec, os_spec,
                            is_featured, promo_label, status
                         ) VALUES (
                            :brand_id, :name, :slug, :short_desc, :description,
                            :screen_spec, :chip_spec, :battery_spec, :camera_spec, :os_spec,
                            :is_featured, :promo_label, :status
                         )'
                    );
                    $insProd->execute([
                        ':brand_id'     => $productData['brand_id'],
                        ':name'         => $productData['name'],
                        ':slug'         => $productData['slug'],
                        ':short_desc'   => $productData['short_desc'] !== '' ? $productData['short_desc'] : null,
                        ':description'  => $productData['description'] !== '' ? $productData['description'] : null,
                        ':screen_spec'  => $productData['screen_spec'],
                        ':chip_spec'    => $productData['chip_spec'],
                        ':battery_spec' => $productData['battery_spec'],
                        ':camera_spec'  => $productData['camera_spec'],
                        ':os_spec'      => $productData['os_spec'],
                        ':is_featured'  => $productData['is_featured'],
                        ':promo_label'  => $productData['promo_label'] !== '' ? $productData['promo_label'] : null,
                        ':status'       => $productData['status'],
                    ]);
                    $productId = (int) $pdo->lastInsertId();
                }

                // Sync variants (update existing, insert new, safely delete removed if unused in orders)
                $existingIdsStmt = $pdo->prepare('SELECT id, sku FROM product_variants WHERE product_id = :product_id');
                $existingIdsStmt->execute([':product_id' => $productId]);
                $existingVariantRows = $existingIdsStmt->fetchAll();

                $keptIds = [];
                $updVarStmt = $pdo->prepare(
                    'UPDATE product_variants SET
                        ram = :ram, storage = :storage, color_name = :color_name, color_hex = :color_hex,
                        price = :price, original_price = :original_price, stock_qty = :stock_qty,
                        sku = :sku, is_default = :is_default
                     WHERE id = :id AND product_id = :product_id'
                );
                $insVarStmt = $pdo->prepare(
                    'INSERT INTO product_variants (
                        product_id, ram, storage, color_name, color_hex,
                        price, original_price, stock_qty, sku, is_default
                     ) VALUES (
                        :product_id, :ram, :storage, :color_name, :color_hex,
                        :price, :original_price, :stock_qty, :sku, :is_default
                     )'
                );

                foreach ($variants as $idx => $v) {
                    $isDefault = ($idx === $defaultVariantIndex) ? 1 : 0;
                    $origVal = $v['original_price'] !== '' ? (float) $v['original_price'] : null;

                    if ($v['id'] > 0) {
                        $updVarStmt->execute([
                            ':ram'            => $v['ram'],
                            ':storage'        => $v['storage'],
                            ':color_name'     => $v['color_name'],
                            ':color_hex'      => $v['color_hex'],
                            ':price'          => (float) $v['price'],
                            ':original_price' => $origVal,
                            ':stock_qty'      => (int) $v['stock_qty'],
                            ':sku'            => $v['sku'],
                            ':is_default'     => $isDefault,
                            ':id'             => $v['id'],
                            ':product_id'     => $productId,
                        ]);
                        $keptIds[] = $v['id'];
                    } else {
                        $insVarStmt->execute([
                            ':product_id'     => $productId,
                            ':ram'            => $v['ram'],
                            ':storage'        => $v['storage'],
                            ':color_name'     => $v['color_name'],
                            ':color_hex'      => $v['color_hex'],
                            ':price'          => (float) $v['price'],
                            ':original_price' => $origVal,
                            ':stock_qty'      => (int) $v['stock_qty'],
                            ':sku'            => $v['sku'],
                            ':is_default'     => $isDefault,
                        ]);
                        $keptIds[] = (int) $pdo->lastInsertId();
                    }
                }

                // Remove deleted variants only if not referenced by order_items
                $orderCheckStmt = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE variant_id = :vid');
                $delVarStmt = $pdo->prepare('DELETE FROM product_variants WHERE id = :vid AND product_id = :pid');

                foreach ($existingVariantRows as $exRow) {
                    $exId = (int) $exRow['id'];
                    if (!in_array($exId, $keptIds, true)) {
                        $orderCheckStmt->execute([':vid' => $exId]);
                        if ((int) $orderCheckStmt->fetchColumn() > 0) {
                            throw new RuntimeException('Cannot remove variant "' . $exRow['sku'] . '" because it belongs to existing orders. Set its stock to 0 instead.');
                        }
                        $delVarStmt->execute([':vid' => $exId, ':pid' => $productId]);
                    }
                }

                // Handle primary image upload
                if ($uploadedImagePath !== null) {
                    $altText = trim((string) ($_POST['image_alt'] ?? ''));
                    if ($altText === '') {
                        $altText = $productData['name'] . ' smartphone';
                    }

                    if ($currentPrimaryImage !== null) {
                        $oldPath = (string) $currentPrimaryImage['image_path'];
                        $updImg = $pdo->prepare(
                            'UPDATE product_images SET image_path = :path, alt_text = :alt WHERE id = :id'
                        );
                        $updImg->execute([
                            ':path' => $uploadedImagePath,
                            ':alt'  => $altText,
                            ':id'   => (int) $currentPrimaryImage['id'],
                        ]);

                        // Remove superseded uploaded file
                        if (str_starts_with($oldPath, 'assets/uploads/products/') && is_file(ROOT_PATH . '/' . $oldPath)) {
                            @unlink(ROOT_PATH . '/' . $oldPath);
                        }
                    } else {
                        $insImg = $pdo->prepare(
                            'INSERT INTO product_images (product_id, variant_id, image_path, alt_text, is_primary, sort_order)
                             VALUES (:product_id, NULL, :path, :alt, 1, 1)'
                        );
                        $insImg->execute([
                            ':product_id' => $productId,
                            ':path'       => $uploadedImagePath,
                            ':alt'        => $altText,
                        ]);
                    }
                }

                $pdo->commit();
                setFlash('success', 'Smartphone "' . $productData['name'] . '" saved successfully.');
                redirect('admin/products.php');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($uploadedImagePath !== null && is_file(ROOT_PATH . '/' . $uploadedImagePath)) {
                    @unlink(ROOT_PATH . '/' . $uploadedImagePath);
                }
                $errors['general'] = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Unable to save smartphone right now. Please verify all inputs.';
                if (!$e instanceof RuntimeException) {
                    error_log('Product form save failed: ' . $e->getMessage());
                }
            }
        } elseif ($uploadedImagePath !== null && is_file(ROOT_PATH . '/' . $uploadedImagePath)) {
            @unlink(ROOT_PATH . '/' . $uploadedImagePath);
        }
    }
}

$adminTitle = $isEdit ? ('Edit: ' . $productData['name']) : 'Add New Smartphone';
$adminActive = 'products';

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-form-topbar">
    <a href="<?= e(BASE_URL) ?>/admin/products.php" class="btn btn-outline btn-sm">&laquo; Back to Products</a>
    <?php if ($isEdit): ?>
        <a href="<?= e(BASE_URL) ?>/product-detail.php?slug=<?= e(urlencode($productData['slug'])) ?>" class="btn btn-ghost btn-sm" target="_blank">Preview Storefront Page</a>
    <?php endif; ?>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-error" role="alert">
        <span><?= e($errors['general']) ?></span>
    </div>
<?php endif; ?>

<form action="<?= e(BASE_URL) ?>/admin/product-form.php<?= $isEdit ? '?id=' . e((string) $productId) : '' ?>" method="post" enctype="multipart/form-data" id="admin-product-form">
    <?= csrfField() ?>
    <input type="hidden" name="product_id" value="<?= e((string) $productId) ?>">

    <div class="admin-form-grid">
        <!-- General Info & Specs -->
        <section class="admin-card">
            <h2>General Information &amp; Specifications</h2>

            <div class="form-grid">
                <div class="form-group">
                    <label for="prod-brand">Brand <span class="required-mark">*</span></label>
                    <select id="prod-brand" name="brand_id" class="form-control" required>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?= e((string) $b['id']) ?>" <?= (int) $productData['brand_id'] === (int) $b['id'] ? 'selected' : '' ?>>
                                <?= e($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="prod-status">Visibility Status</label>
                    <select id="prod-status" name="status" class="form-control">
                        <option value="active" <?= $productData['status'] === 'active' ? 'selected' : '' ?>>Active (Visible)</option>
                        <option value="inactive" <?= $productData['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Hidden)</option>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="prod-name">Smartphone Name <span class="required-mark">*</span></label>
                    <input type="text" id="prod-name" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= e($productData['name']) ?>" required maxlength="160">
                    <p class="field-error"><?= e($errors['name'] ?? '') ?></p>
                </div>

                <div class="form-group">
                    <label for="prod-slug">URL Slug <span class="field-optional">(Auto if blank)</span></label>
                    <input type="text" id="prod-slug" name="slug" class="form-control <?= isset($errors['slug']) ? 'is-invalid' : '' ?>" value="<?= e($productData['slug']) ?>" maxlength="180">
                    <p class="field-error"><?= e($errors['slug'] ?? '') ?></p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="prod-promo">Promo Badge <span class="field-optional">(Optional)</span></label>
                    <input type="text" id="prod-promo" name="promo_label" class="form-control" value="<?= e($productData['promo_label']) ?>" placeholder="e.g. 0% Installment" maxlength="60">
                </div>

                <div class="form-group admin-checkbox-align">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_featured" value="1" <?= (int) $productData['is_featured'] === 1 ? 'checked' : '' ?>>
                        <span>Feature on Homepage</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="prod-short-desc">Short Summary</label>
                <input type="text" id="prod-short-desc" name="short_desc" class="form-control" value="<?= e($productData['short_desc']) ?>" maxlength="255">
            </div>

            <div class="form-group">
                <label for="prod-desc">Full Description</label>
                <textarea id="prod-desc" name="description" rows="4" class="form-control"><?= e($productData['description']) ?></textarea>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="spec-screen">Display <span class="required-mark">*</span></label>
                    <input type="text" id="spec-screen" name="screen_spec" class="form-control <?= isset($errors['screen_spec']) ? 'is-invalid' : '' ?>" value="<?= e($productData['screen_spec']) ?>" placeholder='e.g. 6.7" OLED 120Hz' required maxlength="150">
                    <p class="field-error"><?= e($errors['screen_spec'] ?? '') ?></p>
                </div>
                <div class="form-group">
                    <label for="spec-chip">Processor / Chipset <span class="required-mark">*</span></label>
                    <input type="text" id="spec-chip" name="chip_spec" class="form-control <?= isset($errors['chip_spec']) ? 'is-invalid' : '' ?>" value="<?= e($productData['chip_spec']) ?>" placeholder="e.g. Snapdragon 8 Elite" required maxlength="120">
                    <p class="field-error"><?= e($errors['chip_spec'] ?? '') ?></p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="spec-battery">Battery &amp; Charging <span class="required-mark">*</span></label>
                    <input type="text" id="spec-battery" name="battery_spec" class="form-control <?= isset($errors['battery_spec']) ? 'is-invalid' : '' ?>" value="<?= e($productData['battery_spec']) ?>" placeholder="e.g. 5000 mAh, 45W fast charge" required maxlength="100">
                    <p class="field-error"><?= e($errors['battery_spec'] ?? '') ?></p>
                </div>
                <div class="form-group">
                    <label for="spec-os">Operating System <span class="required-mark">*</span></label>
                    <input type="text" id="spec-os" name="os_spec" class="form-control <?= isset($errors['os_spec']) ? 'is-invalid' : '' ?>" value="<?= e($productData['os_spec']) ?>" placeholder="e.g. Android 15" required maxlength="80">
                    <p class="field-error"><?= e($errors['os_spec'] ?? '') ?></p>
                </div>
            </div>

            <div class="form-group">
                <label for="spec-camera">Camera System <span class="required-mark">*</span></label>
                <input type="text" id="spec-camera" name="camera_spec" class="form-control <?= isset($errors['camera_spec']) ? 'is-invalid' : '' ?>" value="<?= e($productData['camera_spec']) ?>" placeholder="e.g. 50MP Main + 12MP Ultra Wide" required maxlength="150">
                <p class="field-error"><?= e($errors['camera_spec'] ?? '') ?></p>
            </div>
        </section>

        <!-- Image Upload -->
        <section class="admin-card">
            <h2>Primary Product Image</h2>
            <div class="admin-image-preview-wrap">
                <img
                    id="product-image-preview"
                    src="<?= e(resolveImageUrl($currentPrimaryImage['image_path'] ?? null)) ?>"
                    alt="<?= e($currentPrimaryImage['alt_text'] ?? 'Product preview') ?>"
                    width="280"
                    height="210"
                >
            </div>

            <div class="form-group">
                <label for="product-image-input">Upload New Image <span class="field-optional">(JPG, PNG, WebP &le; 2MB)</span></label>
                <input
                    type="file"
                    id="product-image-input"
                    name="product_image"
                    class="form-control <?= isset($errors['product_image']) ? 'is-invalid' : '' ?>"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                >
                <p class="field-error" id="product-image-error"><?= e($errors['product_image'] ?? '') ?></p>
            </div>

            <div class="form-group">
                <label for="product-image-alt">Image SEO Alt Text</label>
                <input
                    type="text"
                    id="product-image-alt"
                    name="image_alt"
                    class="form-control"
                    value="<?= e($currentPrimaryImage['alt_text'] ?? '') ?>"
                    placeholder="e.g. iPhone 16 Pro Max Desert Titanium smartphone"
                    maxlength="180"
                >
            </div>
        </section>
    </div>

    <!-- Variants Editor -->
    <section class="admin-card admin-variants-card">
        <div class="admin-card-header">
            <div>
                <h2>Configurations &amp; Variants (<?= e((string) count($variants)) ?>)</h2>
                <p>Define RAM, Storage, Color, Price, Stock, and unique SKU for each purchasable option</p>
            </div>
            <button type="button" class="btn btn-outline btn-sm" id="add-variant-btn">+ Add Variant</button>
        </div>

        <?php if (!empty($errors['variants'])): ?>
            <div class="alert alert-error" role="alert">
                <span><?= e($errors['variants']) ?></span>
            </div>
        <?php endif; ?>

        <div id="variants-container" class="variants-list">
            <?php foreach ($variants as $idx => $v): ?>
                <div class="variant-editor-row" data-variant-row>
                    <input type="hidden" name="variant_id[]" value="<?= e((string) $v['id']) ?>">
                    <div class="variant-row-grid">
                        <div class="form-group">
                            <label>RAM</label>
                            <input type="text" name="ram[]" class="form-control" value="<?= e($v['ram']) ?>" placeholder="8GB" required maxlength="20">
                        </div>
                        <div class="form-group">
                            <label>Storage</label>
                            <input type="text" name="storage[]" class="form-control" value="<?= e($v['storage']) ?>" placeholder="256GB" required maxlength="20">
                        </div>
                        <div class="form-group">
                            <label>Color Name</label>
                            <input type="text" name="color_name[]" class="form-control" value="<?= e($v['color_name']) ?>" placeholder="Titanium Gray" required maxlength="50">
                        </div>
                        <div class="form-group">
                            <label>Swatch</label>
                            <input type="color" name="color_hex[]" class="form-control form-control-color" value="<?= e($v['color_hex']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Price (VND)</label>
                            <input type="number" name="price[]" class="form-control" value="<?= e($v['price']) ?>" min="1000" step="1000" required>
                        </div>
                        <div class="form-group">
                            <label>Original Price</label>
                            <input type="number" name="original_price[]" class="form-control" value="<?= e($v['original_price']) ?>" min="1000" step="1000" placeholder="Optional">
                        </div>
                        <div class="form-group">
                            <label>Stock Qty</label>
                            <input type="number" name="stock_qty[]" class="form-control" value="<?= e($v['stock_qty']) ?>" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>SKU</label>
                            <input type="text" name="sku[]" class="form-control" value="<?= e($v['sku']) ?>" placeholder="IP16PM-256-DES" required maxlength="60">
                        </div>
                    </div>

                    <div class="variant-row-controls">
                        <label class="radio-inline">
                            <input type="radio" name="default_variant_index" value="<?= e((string) $idx) ?>" <?= $idx === $defaultVariantIndex ? 'checked' : '' ?>>
                            <span>Default Variant</span>
                        </label>
                        <button type="button" class="btn btn-danger-outline btn-sm" data-remove-variant>Remove</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="admin-form-submit">
            <a href="<?= e(BASE_URL) ?>/admin/products.php" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary btn-lg">Save Smartphone</button>
        </div>
    </section>
</form>

<template id="variant-row-template">
    <div class="variant-editor-row" data-variant-row>
        <input type="hidden" name="variant_id[]" value="0">
        <div class="variant-row-grid">
            <div class="form-group">
                <label>RAM</label>
                <input type="text" name="ram[]" class="form-control" placeholder="8GB" required maxlength="20">
            </div>
            <div class="form-group">
                <label>Storage</label>
                <input type="text" name="storage[]" class="form-control" placeholder="256GB" required maxlength="20">
            </div>
            <div class="form-group">
                <label>Color Name</label>
                <input type="text" name="color_name[]" class="form-control" placeholder="Black" required maxlength="50">
            </div>
            <div class="form-group">
                <label>Swatch</label>
                <input type="color" name="color_hex[]" class="form-control form-control-color" value="#1E293B">
            </div>
            <div class="form-group">
                <label>Price (VND)</label>
                <input type="number" name="price[]" class="form-control" min="1000" step="1000" required>
            </div>
            <div class="form-group">
                <label>Original Price</label>
                <input type="number" name="original_price[]" class="form-control" min="1000" step="1000" placeholder="Optional">
            </div>
            <div class="form-group">
                <label>Stock Qty</label>
                <input type="number" name="stock_qty[]" class="form-control" value="10" min="0" required>
            </div>
            <div class="form-group">
                <label>SKU</label>
                <input type="text" name="sku[]" class="form-control" placeholder="SKU-CODE" required maxlength="60">
            </div>
        </div>
        <div class="variant-row-controls">
            <label class="radio-inline">
                <input type="radio" name="default_variant_index" value="0">
                <span>Default Variant</span>
            </label>
            <button type="button" class="btn btn-danger-outline btn-sm" data-remove-variant>Remove</button>
        </div>
    </div>
</template>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

