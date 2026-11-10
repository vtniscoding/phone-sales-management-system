<?php
/**
 * Customer Profile, Password Management & Order History Page (NovaPhone).
 * Requires authenticated session, supports updating profile defaults, changing password
 * with current-password verification, and inspecting past order items & statuses.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

$sessionUser = requireLogin('profile.php');
$userId = (int) $sessionUser['id'];

$stmt = $pdo->prepare(
    'SELECT id, full_name, email, phone, address, role, created_at FROM users WHERE id = :id LIMIT 1'
);
$stmt->execute([':id' => $userId]);
$userRecord = $stmt->fetch();

if (!$userRecord) {
    unset($_SESSION['user']);
    setFlash('error', 'Your account session could not be verified. Please sign in again.');
    redirect('login.php');
}

$profileErrors = [];
$passwordErrors = [];
$profileForm = [
    'full_name' => (string) $userRecord['full_name'],
    'phone'     => (string) $userRecord['phone'],
    'address'   => (string) ($userRecord['address'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Your security session expired. Please refresh and try again.');
        redirect('profile.php');
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'update_profile') {
        $profileForm['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
        $profileForm['phone']     = trim((string) ($_POST['phone'] ?? ''));
        $profileForm['address']   = trim((string) ($_POST['address'] ?? ''));

        if ($profileForm['full_name'] === '') {
            $profileErrors['full_name'] = 'Full name is required.';
        } elseif (mb_strlen($profileForm['full_name']) < 2 || mb_strlen($profileForm['full_name']) > 100) {
            $profileErrors['full_name'] = 'Full name must be between 2 and 100 characters.';
        }

        $normalizedPhone = normalizePhone($profileForm['phone']);
        if ($profileForm['phone'] === '') {
            $profileErrors['phone'] = 'Phone number is required.';
        } elseif (!isValidVnPhone($profileForm['phone'])) {
            $profileErrors['phone'] = 'Enter a valid 10-digit Vietnamese mobile number (e.g. 0912345678).';
        }

        if (mb_strlen($profileForm['address']) > 255) {
            $profileErrors['address'] = 'Address must not exceed 255 characters.';
        }

        if (empty($profileErrors)) {
            try {
                $updStmt = $pdo->prepare(
                    'UPDATE users SET full_name = :full_name, phone = :phone, address = :address WHERE id = :id'
                );
                $updStmt->execute([
                    ':full_name' => $profileForm['full_name'],
                    ':phone'     => $normalizedPhone,
                    ':address'   => $profileForm['address'] !== '' ? $profileForm['address'] : null,
                    ':id'        => $userId,
                ]);

                // Sync updated profile values into active session
                $_SESSION['user']['full_name'] = $profileForm['full_name'];
                $_SESSION['user']['phone']     = $normalizedPhone;
                $_SESSION['user']['address']   = $profileForm['address'];

                setFlash('success', 'Your profile details have been updated.');
                redirect('profile.php');
            } catch (PDOException $e) {
                error_log('Profile update failed: ' . $e->getMessage());
                $profileErrors['general'] = 'Unable to update your profile right now.';
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword     = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '') {
            $passwordErrors['current_password'] = 'Current password is required.';
        }

        $strengthErr = validatePasswordStrength($newPassword);
        if ($newPassword === '') {
            $passwordErrors['new_password'] = 'New password is required.';
        } elseif ($strengthErr !== null) {
            $passwordErrors['new_password'] = $strengthErr;
        } elseif ($newPassword === $currentPassword) {
            $passwordErrors['new_password'] = 'New password must be different from your current password.';
        }

        if ($confirmPassword === '') {
            $passwordErrors['confirm_password'] = 'Please confirm your new password.';
        } elseif ($newPassword !== $confirmPassword) {
            $passwordErrors['confirm_password'] = 'Password confirmation does not match.';
        }

        if (empty($passwordErrors)) {
            try {
                $hashStmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
                $hashStmt->execute([':id' => $userId]);
                $hashRow = $hashStmt->fetch();

                if (!$hashRow || !password_verify($currentPassword, (string) $hashRow['password_hash'])) {
                    $passwordErrors['current_password'] = 'Current password is incorrect.';
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updPassStmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
                    $updPassStmt->execute([
                        ':password_hash' => $newHash,
                        ':id'            => $userId,
                    ]);

                    setFlash('success', 'Your password has been changed successfully.');
                    redirect('profile.php');
                }
            } catch (PDOException $e) {
                error_log('Password change failed: ' . $e->getMessage());
                $passwordErrors['general'] = 'Unable to change your password right now.';
            }
        }
    }
}

// Load customer's order history
$ordersStmt = $pdo->prepare(
    'SELECT id, order_code, customer_name, customer_phone, shipping_address,
            payment_method, payment_status, order_status, total_amount, note, created_at
     FROM orders
     WHERE user_id = :user_id
     ORDER BY created_at DESC, id DESC'
);
$ordersStmt->execute([':user_id' => $userId]);
$orders = $ordersStmt->fetchAll();

// Inspect specific order details when ?order=NP... is provided
$selectedOrder = null;
$selectedOrderItems = [];
$inspectCode = trim((string) ($_GET['order'] ?? ''));

if ($inspectCode !== '') {
    foreach ($orders as $orderRow) {
        if ($orderRow['order_code'] === $inspectCode) {
            $selectedOrder = $orderRow;
            break;
        }
    }
    if ($selectedOrder !== null) {
        $itemsStmt = $pdo->prepare(
            'SELECT product_name, variant_label, unit_price, quantity, subtotal
             FROM order_items
             WHERE order_id = :order_id
             ORDER BY id ASC'
        );
        $itemsStmt->execute([':order_id' => (int) $selectedOrder['id']]);
        $selectedOrderItems = $itemsStmt->fetchAll();
    }
}

$pageTitle = 'My Account & Orders - ' . APP_NAME;
$pageDescription = 'Manage your NovaPhone profile, default shipping address, password, and smartphone order history.';
$activePage = 'profile';
$pageScripts = ['assets/js/profile.js'];

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-banner page-banner-compact">
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(BASE_URL) ?>/index.php">Home</a>
            <span aria-hidden="true">/</span>
            <span>My Profile &amp; Orders</span>
        </nav>
        <h1>My Account</h1>
        <p>Member since <?= e(date('M d, Y', strtotime((string) $userRecord['created_at']))) ?> &bull; <?= e($userRecord['email']) ?></p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="profile-grid">
            <!-- Profile Details Form -->
            <div class="profile-card">
                <h2>Profile &amp; Delivery Defaults</h2>

                <?php if (!empty($profileErrors['general'])): ?>
                    <div class="alert alert-error" role="alert">
                        <span><?= e($profileErrors['general']) ?></span>
                    </div>
                <?php endif; ?>

                <form id="profile-form" action="<?= e(BASE_URL) ?>/profile.php" method="post" novalidate>
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-group">
                        <label for="prof-email">Email Address</label>
                        <input
                            type="email"
                            id="prof-email"
                            class="form-control"
                            value="<?= e($userRecord['email']) ?>"
                            disabled
                        >
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="prof-name">Full Name <span class="required-mark" aria-hidden="true">*</span></label>
                            <input
                                type="text"
                                id="prof-name"
                                name="full_name"
                                class="form-control <?= isset($profileErrors['full_name']) ? 'is-invalid' : '' ?>"
                                value="<?= e($profileForm['full_name']) ?>"
                                autocomplete="name"
                                required
                                minlength="2"
                                maxlength="100"
                                aria-invalid="<?= isset($profileErrors['full_name']) ? 'true' : 'false' ?>"
                                aria-describedby="prof-name-error"
                            >
                            <p class="field-error" id="prof-name-error"><?= e($profileErrors['full_name'] ?? '') ?></p>
                        </div>

                        <div class="form-group">
                            <label for="prof-phone">Phone Number <span class="required-mark" aria-hidden="true">*</span></label>
                            <input
                                type="tel"
                                id="prof-phone"
                                name="phone"
                                class="form-control <?= isset($profileErrors['phone']) ? 'is-invalid' : '' ?>"
                                value="<?= e($profileForm['phone']) ?>"
                                autocomplete="tel"
                                required
                                maxlength="20"
                                aria-invalid="<?= isset($profileErrors['phone']) ? 'true' : 'false' ?>"
                                aria-describedby="prof-phone-error"
                            >
                            <p class="field-error" id="prof-phone-error"><?= e($profileErrors['phone'] ?? '') ?></p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="prof-address">Default Shipping Address</label>
                        <input
                            type="text"
                            id="prof-address"
                            name="address"
                            class="form-control <?= isset($profileErrors['address']) ? 'is-invalid' : '' ?>"
                            value="<?= e($profileForm['address']) ?>"
                            placeholder="128 Nguyen Van Cu, District 5, Ho Chi Minh City"
                            autocomplete="street-address"
                            maxlength="255"
                            aria-invalid="<?= isset($profileErrors['address']) ? 'true' : 'false' ?>"
                            aria-describedby="prof-address-error"
                        >
                        <p class="field-error" id="prof-address-error"><?= e($profileErrors['address'] ?? '') ?></p>
                    </div>

                    <button type="submit" class="btn btn-primary" id="profile-submit-btn">Save Profile Changes</button>
                </form>
            </div>

            <!-- Change Password Form -->
            <div class="profile-card">
                <h2>Change Password</h2>

                <?php if (!empty($passwordErrors['general'])): ?>
                    <div class="alert alert-error" role="alert">
                        <span><?= e($passwordErrors['general']) ?></span>
                    </div>
                <?php endif; ?>

                <form id="password-form" action="<?= e(BASE_URL) ?>/profile.php" method="post" novalidate>
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label for="pwd-current">Current Password <span class="required-mark" aria-hidden="true">*</span></label>
                        <div class="input-password-wrap">
                            <input
                                type="password"
                                id="pwd-current"
                                name="current_password"
                                class="form-control <?= isset($passwordErrors['current_password']) ? 'is-invalid' : '' ?>"
                                placeholder="Enter current password"
                                autocomplete="current-password"
                                required
                                aria-invalid="<?= isset($passwordErrors['current_password']) ? 'true' : 'false' ?>"
                                aria-describedby="pwd-current-error"
                            >
                            <button
                                type="button"
                                class="password-toggle"
                                data-toggle-password="pwd-current"
                                aria-label="Show password"
                                aria-pressed="false"
                            >Show</button>
                        </div>
                        <p class="field-error" id="pwd-current-error"><?= e($passwordErrors['current_password'] ?? '') ?></p>
                    </div>

                    <div class="form-group">
                        <label for="pwd-new">New Password <span class="required-mark" aria-hidden="true">*</span></label>
                        <div class="input-password-wrap">
                            <input
                                type="password"
                                id="pwd-new"
                                name="new_password"
                                class="form-control <?= isset($passwordErrors['new_password']) ? 'is-invalid' : '' ?>"
                                placeholder="Minimum 8 characters"
                                autocomplete="new-password"
                                required
                                minlength="8"
                                aria-invalid="<?= isset($passwordErrors['new_password']) ? 'true' : 'false' ?>"
                                aria-describedby="pwd-new-error password-rules-list"
                            >
                            <button
                                type="button"
                                class="password-toggle"
                                data-toggle-password="pwd-new"
                                aria-label="Show password"
                                aria-pressed="false"
                            >Show</button>
                        </div>
                        <p class="field-error" id="pwd-new-error"><?= e($passwordErrors['new_password'] ?? '') ?></p>
                    </div>

                    <div class="form-group">
                        <label for="pwd-confirm">Confirm New Password <span class="required-mark" aria-hidden="true">*</span></label>
                        <div class="input-password-wrap">
                            <input
                                type="password"
                                id="pwd-confirm"
                                name="confirm_password"
                                class="form-control <?= isset($passwordErrors['confirm_password']) ? 'is-invalid' : '' ?>"
                                placeholder="Re-enter new password"
                                autocomplete="new-password"
                                required
                                minlength="8"
                                aria-invalid="<?= isset($passwordErrors['confirm_password']) ? 'true' : 'false' ?>"
                                aria-describedby="pwd-confirm-error"
                            >
                            <button
                                type="button"
                                class="password-toggle"
                                data-toggle-password="pwd-confirm"
                                aria-label="Show password"
                                aria-pressed="false"
                            >Show</button>
                        </div>
                        <p class="field-error" id="pwd-confirm-error"><?= e($passwordErrors['confirm_password'] ?? '') ?></p>
                    </div>

                    <ul class="password-rules" id="password-rules-list" aria-live="polite">
                        <li data-rule="length">At least 8 characters</li>
                        <li data-rule="upper">1 uppercase letter (A-Z)</li>
                        <li data-rule="lower">1 lowercase letter (a-z)</li>
                        <li data-rule="digit">1 number (0-9)</li>
                    </ul>

                    <button type="submit" class="btn btn-outline" id="password-submit-btn">Update Password</button>
                </form>
            </div>
        </div>

        <!-- Order History -->
        <div class="orders-history-card" id="orders-section">
            <div class="cart-panel-header">
                <h2>Order History (<?= e((string) count($orders)) ?>)</h2>
                <a href="<?= e(BASE_URL) ?>/products.php" class="btn btn-outline btn-sm">Shop More Phones</a>
            </div>

            <?php if ($selectedOrder !== null): ?>
                <?php
                $selOrderStatus = getOrderStatusMeta((string) $selectedOrder['order_status']);
                $selPayStatus = getPaymentStatusMeta((string) $selectedOrder['payment_status']);
                ?>
                <div class="order-inspect-box" id="order-detail-box">
                    <div class="order-inspect-head">
                        <div>
                            <h3>Order #<?= e($selectedOrder['order_code']) ?></h3>
                            <p>Placed on <?= e($selectedOrder['created_at']) ?></p>
                        </div>
                        <div class="order-inspect-badges">
                            <span class="badge <?= e($selOrderStatus['class']) ?>"><?= e($selOrderStatus['label']) ?></span>
                            <span class="badge <?= e($selPayStatus['class']) ?>"><?= e($selPayStatus['label']) ?></span>
                            <a href="<?= e(BASE_URL) ?>/profile.php#orders-section" class="btn btn-ghost btn-sm">Close</a>
                        </div>
                    </div>

                    <div class="receipt-meta-grid">
                        <div>
                            <p><strong>Recipient:</strong> <?= e($selectedOrder['customer_name']) ?></p>
                            <p><strong>Phone:</strong> <?= e($selectedOrder['customer_phone']) ?></p>
                            <p><strong>Address:</strong> <?= e($selectedOrder['shipping_address']) ?></p>
                        </div>
                        <div>
                            <p><strong>Payment Method:</strong> <?= $selectedOrder['payment_method'] === 'bank_transfer' ? 'Bank Transfer (Simulated)' : 'Cash on Delivery (COD)' ?></p>
                            <?php if (!empty($selectedOrder['note'])): ?>
                                <p><strong>Note:</strong> <?= e($selectedOrder['note']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="receipt-items-wrap">
                        <table class="receipt-table">
                            <thead>
                                <tr>
                                    <th>Smartphone</th>
                                    <th>Unit Price</th>
                                    <th>Qty</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="tabular-nums">
                                <?php foreach ($selectedOrderItems as $oItem): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($oItem['product_name']) ?></strong>
                                            <div class="receipt-variant"><?= e($oItem['variant_label']) ?></div>
                                        </td>
                                        <td><?= e(formatPrice((float) $oItem['unit_price'])) ?></td>
                                        <td><?= e((string) $oItem['quantity']) ?></td>
                                        <td><strong><?= e(formatPrice((float) $oItem['subtotal'])) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="tabular-nums">
                                <tr>
                                    <td colspan="3"><strong>Order Total</strong></td>
                                    <td><strong class="receipt-total"><?= e(formatPrice((float) $selectedOrder['total_amount'])) ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <p>You have not placed any orders yet.</p>
                    <a href="<?= e(BASE_URL) ?>/products.php" class="btn btn-primary">Explore Smartphones</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order Code</th>
                                <th>Date</th>
                                <th>Payment</th>
                                <th>Total</th>
                                <th>Status</th>
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
                                    <td><strong><?= e($order['order_code']) ?></strong></td>
                                    <td><?= e(date('M d, Y H:i', strtotime((string) $order['created_at']))) ?></td>
                                    <td>
                                        <?= $order['payment_method'] === 'bank_transfer' ? 'Bank Transfer' : 'COD' ?>
                                        <span class="badge <?= e($payMeta['class']) ?>"><?= e($payMeta['label']) ?></span>
                                    </td>
                                    <td><strong><?= e(formatPrice((float) $order['total_amount'])) ?></strong></td>
                                    <td><span class="badge <?= e($orderMeta['class']) ?>"><?= e($orderMeta['label']) ?></span></td>
                                    <td>
                                        <a href="<?= e(BASE_URL) ?>/profile.php?order=<?= e(urlencode($order['order_code'])) ?>#order-detail-box" class="btn btn-outline btn-sm">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

