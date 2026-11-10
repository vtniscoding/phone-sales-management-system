<?php
/**
 * Admin Smartphone Brands Management (NovaPhone).
 * Supports listing, creating, editing, and safely deleting smartphone brands.
 */

require_once __DIR__ . '/../includes/admin_guard.php';

$errors = [];
$editId = (int) ($_GET['edit'] ?? 0);
$editingBrand = null;

if ($editId > 0) {
    $editStmt = $pdo->prepare('SELECT * FROM brands WHERE id = :id LIMIT 1');
    $editStmt->execute([':id' => $editId]);
    $editingBrand = $editStmt->fetch() ?: null;
}

$formData = [
    'id'          => $editingBrand ? (int) $editingBrand['id'] : 0,
    'name'        => $editingBrand ? (string) $editingBrand['name'] : '',
    'slug'        => $editingBrand ? (string) $editingBrand['slug'] : '',
    'logo_path'   => $editingBrand ? (string) ($editingBrand['logo_path'] ?? '') : '',
    'description' => $editingBrand ? (string) ($editingBrand['description'] ?? '') : '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Your security session expired. Please try again.');
        redirect('admin/brands.php');
    }

    $action = trim((string) ($_POST['action'] ?? 'save'));

    if ($action === 'delete') {
        $deleteId = (int) ($_POST['brand_id'] ?? 0);
        try {
            $brandStmt = $pdo->prepare('SELECT id, name FROM brands WHERE id = :id LIMIT 1');
            $brandStmt->execute([':id' => $deleteId]);
            $brand = $brandStmt->fetch();

            if (!$brand) {
                setFlash('error', 'Brand not found.');
                redirect('admin/brands.php');
            }

            $prodCountStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE brand_id = :brand_id');
            $prodCountStmt->execute([':brand_id' => $deleteId]);
            $linkedCount = (int) $prodCountStmt->fetchColumn();

            if ($linkedCount > 0) {
                setFlash('error', 'Cannot delete brand "' . $brand['name'] . '" because ' . $linkedCount . ' smartphone(s) are still assigned to it.');
                redirect('admin/brands.php');
            }

            $delStmt = $pdo->prepare('DELETE FROM brands WHERE id = :id');
            $delStmt->execute([':id' => $deleteId]);
            setFlash('success', 'Brand "' . $brand['name'] . '" deleted successfully.');
            redirect('admin/brands.php');
        } catch (PDOException $e) {
            error_log('Brand delete failed: ' . $e->getMessage());
            setFlash('error', 'Unable to delete this brand right now.');
            redirect('admin/brands.php');
        }
    } elseif ($action === 'save') {
        $formData['id']          = (int) ($_POST['brand_id'] ?? 0);
        $formData['name']        = trim((string) ($_POST['name'] ?? ''));
        $formData['slug']        = trim((string) ($_POST['slug'] ?? ''));
        $formData['logo_path']   = trim((string) ($_POST['logo_path'] ?? ''));
        $formData['description'] = trim((string) ($_POST['description'] ?? ''));

        if ($formData['name'] === '') {
            $errors['name'] = 'Brand name is required.';
        } elseif (mb_strlen($formData['name']) < 2 || mb_strlen($formData['name']) > 80) {
            $errors['name'] = 'Brand name must be between 2 and 80 characters.';
        }

        $finalSlug = $formData['slug'] !== '' ? slugify($formData['slug']) : slugify($formData['name']);
        if ($finalSlug === '') {
            $errors['slug'] = 'A valid alphanumeric slug is required.';
        } elseif (mb_strlen($finalSlug) > 100) {
            $errors['slug'] = 'Slug must not exceed 100 characters.';
        }
        $formData['slug'] = $finalSlug;

        if (mb_strlen($formData['logo_path']) > 255) {
            $errors['logo_path'] = 'Logo path must not exceed 255 characters.';
        }

        if (empty($errors)) {
            try {
                $dupStmt = $pdo->prepare(
                    'SELECT id FROM brands WHERE (name = :name OR slug = :slug) AND id <> :id LIMIT 1'
                );
                $dupStmt->execute([
                    ':name' => $formData['name'],
                    ':slug' => $formData['slug'],
                    ':id'   => $formData['id'],
                ]);

                if ($dupStmt->fetch()) {
                    $errors['general'] = 'Another brand with this name or slug already exists.';
                } else {
                    if ($formData['id'] > 0) {
                        $updStmt = $pdo->prepare(
                            'UPDATE brands
                             SET name = :name, slug = :slug, logo_path = :logo_path, description = :description
                             WHERE id = :id'
                        );
                        $updStmt->execute([
                            ':name'        => $formData['name'],
                            ':slug'        => $formData['slug'],
                            ':logo_path'   => $formData['logo_path'] !== '' ? $formData['logo_path'] : null,
                            ':description' => $formData['description'] !== '' ? $formData['description'] : null,
                            ':id'          => $formData['id'],
                        ]);
                        setFlash('success', 'Brand "' . $formData['name'] . '" updated.');
                    } else {
                        $insStmt = $pdo->prepare(
                            'INSERT INTO brands (name, slug, logo_path, description)
                             VALUES (:name, :slug, :logo_path, :description)'
                        );
                        $insStmt->execute([
                            ':name'        => $formData['name'],
                            ':slug'        => $formData['slug'],
                            ':logo_path'   => $formData['logo_path'] !== '' ? $formData['logo_path'] : null,
                            ':description' => $formData['description'] !== '' ? $formData['description'] : null,
                        ]);
                        setFlash('success', 'Brand "' . $formData['name'] . '" created.');
                    }
                    redirect('admin/brands.php');
                }
            } catch (PDOException $e) {
                error_log('Brand save failed: ' . $e->getMessage());
                $errors['general'] = 'Unable to save brand changes right now.';
            }
        }
    }
}

$brands = $pdo->query(
    'SELECT b.*, COUNT(p.id) AS product_count
     FROM brands b
     LEFT JOIN products p ON p.brand_id = b.id
     GROUP BY b.id
     ORDER BY b.id ASC'
)->fetchAll();

$adminTitle = 'Manage Brands';
$adminActive = 'brands';

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-split-grid">
    <!-- Add / Edit Brand Form -->
    <section class="admin-card">
        <div class="admin-card-header">
            <h2><?= $formData['id'] > 0 ? 'Edit Brand' : 'Add New Brand' ?></h2>
            <?php if ($formData['id'] > 0): ?>
                <a href="<?= e(BASE_URL) ?>/admin/brands.php" class="btn btn-outline btn-sm">Cancel Edit</a>
            <?php endif; ?>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-error" role="alert">
                <span><?= e($errors['general']) ?></span>
            </div>
        <?php endif; ?>

        <form action="<?= e(BASE_URL) ?>/admin/brands.php" method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="brand_id" value="<?= e((string) $formData['id']) ?>">

            <div class="form-group">
                <label for="brand-name">Brand Name <span class="required-mark" aria-hidden="true">*</span></label>
                <input
                    type="text"
                    id="brand-name"
                    name="name"
                    class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                    value="<?= e($formData['name']) ?>"
                    placeholder="e.g. Samsung"
                    required
                    maxlength="80"
                >
                <p class="field-error"><?= e($errors['name'] ?? '') ?></p>
            </div>

            <div class="form-group">
                <label for="brand-slug">URL Slug <span class="field-optional">(Auto-generated if blank)</span></label>
                <input
                    type="text"
                    id="brand-slug"
                    name="slug"
                    class="form-control <?= isset($errors['slug']) ? 'is-invalid' : '' ?>"
                    value="<?= e($formData['slug']) ?>"
                    placeholder="e.g. samsung"
                    maxlength="100"
                >
                <p class="field-error"><?= e($errors['slug'] ?? '') ?></p>
            </div>

            <div class="form-group">
                <label for="brand-logo">Logo Relative Path <span class="field-optional">(Optional)</span></label>
                <input
                    type="text"
                    id="brand-logo"
                    name="logo_path"
                    class="form-control <?= isset($errors['logo_path']) ? 'is-invalid' : '' ?>"
                    value="<?= e($formData['logo_path']) ?>"
                    placeholder="assets/images/brands/samsung.svg"
                    maxlength="255"
                >
                <p class="field-error"><?= e($errors['logo_path'] ?? '') ?></p>
            </div>

            <div class="form-group">
                <label for="brand-desc">Description</label>
                <textarea
                    id="brand-desc"
                    name="description"
                    rows="3"
                    class="form-control"
                    placeholder="Short brand overview..."
                ><?= e($formData['description']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <?= $formData['id'] > 0 ? 'Update Brand' : 'Create Brand' ?>
            </button>
        </form>
    </section>

    <!-- Brands List Table -->
    <section class="admin-card">
        <div class="admin-card-header">
            <h2>Smartphone Brands (<?= e((string) count($brands)) ?>)</h2>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Logo</th>
                        <th>Brand &amp; Slug</th>
                        <th>Products</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody class="tabular-nums">
                    <?php foreach ($brands as $b): ?>
                        <tr>
                            <td>
                                <img
                                    src="<?= e(resolveImageUrl($b['logo_path'], 'assets/images/products/phone-placeholder.svg')) ?>"
                                    alt="<?= e($b['name']) ?>"
                                    class="admin-brand-thumb"
                                    width="72"
                                    height="26"
                                >
                            </td>
                            <td>
                                <strong><?= e($b['name']) ?></strong>
                                <div class="table-subtext">/<?= e($b['slug']) ?></div>
                            </td>
                            <td><?= e((string) $b['product_count']) ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= e(BASE_URL) ?>/admin/brands.php?edit=<?= e((string) $b['id']) ?>" class="btn btn-outline btn-sm">Edit</a>
                                    <form action="<?= e(BASE_URL) ?>/admin/brands.php" method="post" class="inline-form" data-confirm-delete="Delete brand <?= e($b['name']) ?>?">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="brand_id" value="<?= e((string) $b['id']) ?>">
                                        <button type="submit" class="btn btn-danger-outline btn-sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

