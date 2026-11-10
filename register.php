<?php
/**
 * Customer Registration Page (NovaPhone).
 * Validates user input on both client (auth.js) and server (PHP), enforces CSRF protection,
 * hashes passwords with bcrypt via password_hash(), and inserts new customer accounts safely.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

startAppSession();

// Redirect authenticated users away from the registration page
$existingUser = currentUser();
if ($existingUser !== null) {
    if (($existingUser['role'] ?? '') === 'admin' && is_file(ROOT_PATH . '/admin/index.php')) {
        redirect('admin/index.php');
    }
    redirect('index.php');
}

$errors = [];
$formData = [
    'full_name' => '',
    'email'     => '',
    'phone'     => '',
    'address'   => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'Your security session expired. Please refresh the page and try again.';
    } else {
        $formData['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
        $formData['email']     = strtolower(trim((string) ($_POST['email'] ?? '')));
        $formData['phone']     = trim((string) ($_POST['phone'] ?? ''));
        $formData['address']   = trim((string) ($_POST['address'] ?? ''));
        $password              = (string) ($_POST['password'] ?? '');
        $confirmPassword       = (string) ($_POST['confirm_password'] ?? '');

        // Validate full name
        if ($formData['full_name'] === '') {
            $errors['full_name'] = 'Full name is required.';
        } elseif (mb_strlen($formData['full_name']) < 2 || mb_strlen($formData['full_name']) > 100) {
            $errors['full_name'] = 'Full name must be between 2 and 100 characters.';
        }

        // Validate email format
        if ($formData['email'] === '') {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($formData['email']) > 150) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        // Validate Vietnamese mobile phone number
        $normalizedPhone = normalizePhone($formData['phone']);
        if ($formData['phone'] === '') {
            $errors['phone'] = 'Phone number is required.';
        } elseif (!isValidVnPhone($formData['phone'])) {
            $errors['phone'] = 'Enter a valid 10-digit Vietnamese mobile number (e.g. 0912345678).';
        }

        // Validate optional shipping address length
        if (mb_strlen($formData['address']) > 255) {
            $errors['address'] = 'Address must not exceed 255 characters.';
        }

        // Validate password strength & confirmation match
        $passwordError = validatePasswordStrength($password);
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        } elseif ($passwordError !== null) {
            $errors['password'] = $passwordError;
        }

        if ($confirmPassword === '') {
            $errors['confirm_password'] = 'Please confirm your password.';
        } elseif ($password !== $confirmPassword) {
            $errors['confirm_password'] = 'Password confirmation does not match.';
        }

        // Check duplicate email and insert new customer account
        if (empty($errors)) {
            try {
                $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $checkStmt->execute([':email' => $formData['email']]);

                if ($checkStmt->fetch()) {
                    $errors['email'] = 'An account with this email address already exists.';
                } else {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                    $insertStmt = $pdo->prepare(
                        'INSERT INTO users (full_name, email, phone, password_hash, address, role)
                         VALUES (:full_name, :email, :phone, :password_hash, :address, :role)'
                    );
                    $insertStmt->execute([
                        ':full_name'     => $formData['full_name'],
                        ':email'         => $formData['email'],
                        ':phone'         => $normalizedPhone,
                        ':password_hash' => $passwordHash,
                        ':address'       => $formData['address'] !== '' ? $formData['address'] : null,
                        ':role'          => 'customer',
                    ]);

                    setFlash('success', 'Registration successful! Please sign in with your new account.');
                    redirect('login.php');
                }
            } catch (PDOException $e) {
                error_log('Registration failed: ' . $e->getMessage());
                $errors['general'] = 'Unable to create your account right now. Please try again later.';
            }
        }
    }
}

$pageTitle = 'Create Account - ' . APP_NAME;
$pageDescription = 'Register a NovaPhone customer account to save your shipping address, track smartphone orders, and check out faster.';
$activePage = 'register';
$pageScripts = ['assets/js/auth.js'];

require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
    <div class="container">
        <div class="auth-layout">
            <aside class="auth-aside">
                <span class="hero-badge">NovaPhone Member</span>
                <h1>Join NovaPhone Today</h1>
                <p>Create your free customer account for faster checkout, instant order tracking, and exclusive member deals on official smartphones.</p>
                <ul class="auth-benefits">
                    <li>Track order status from confirmation to delivery</li>
                    <li>Save default shipping details for 1-click checkout</li>
                    <li>100% official warranty on Apple, Samsung, Xiaomi, OPPO &amp; realme</li>
                </ul>
            </aside>

            <div class="auth-card">
                <div class="auth-card-header">
                    <h2>Create Customer Account</h2>
                    <p>Already have an account? <a href="<?= e(BASE_URL) ?>/login.php">Sign in here</a></p>
                </div>

                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-error" role="alert">
                        <span><?= e($errors['general']) ?></span>
                    </div>
                <?php endif; ?>

                <form id="register-form" action="<?= e(BASE_URL) ?>/register.php" method="post" novalidate data-auth-form="register">
                    <?= csrfField() ?>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="reg-full-name">Full Name <span class="required-mark" aria-hidden="true">*</span></label>
                            <input
                                type="text"
                                id="reg-full-name"
                                name="full_name"
                                class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                                value="<?= e($formData['full_name']) ?>"
                                placeholder="e.g. Nguyen Van An"
                                autocomplete="name"
                                required
                                minlength="2"
                                maxlength="100"
                                aria-invalid="<?= isset($errors['full_name']) ? 'true' : 'false' ?>"
                                aria-describedby="reg-full-name-error"
                            >
                            <p class="field-error" id="reg-full-name-error"><?= e($errors['full_name'] ?? '') ?></p>
                        </div>

                        <div class="form-group">
                            <label for="reg-phone">Phone Number <span class="required-mark" aria-hidden="true">*</span></label>
                            <input
                                type="tel"
                                id="reg-phone"
                                name="phone"
                                class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                                value="<?= e($formData['phone']) ?>"
                                placeholder="e.g. 0912345678"
                                autocomplete="tel"
                                required
                                maxlength="20"
                                aria-invalid="<?= isset($errors['phone']) ? 'true' : 'false' ?>"
                                aria-describedby="reg-phone-error"
                            >
                            <p class="field-error" id="reg-phone-error"><?= e($errors['phone'] ?? '') ?></p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="reg-email">Email Address <span class="required-mark" aria-hidden="true">*</span></label>
                        <input
                            type="email"
                            id="reg-email"
                            name="email"
                            class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                            value="<?= e($formData['email']) ?>"
                            placeholder="you@example.com"
                            autocomplete="email"
                            required
                            maxlength="150"
                            aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>"
                            aria-describedby="reg-email-error"
                        >
                        <p class="field-error" id="reg-email-error"><?= e($errors['email'] ?? '') ?></p>
                    </div>

                    <div class="form-group">
                        <label for="reg-address">Shipping Address <span class="field-optional">(Optional)</span></label>
                        <input
                            type="text"
                            id="reg-address"
                            name="address"
                            class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>"
                            value="<?= e($formData['address']) ?>"
                            placeholder="128 Nguyen Van Cu, District 5, Ho Chi Minh City"
                            autocomplete="street-address"
                            maxlength="255"
                            aria-invalid="<?= isset($errors['address']) ? 'true' : 'false' ?>"
                            aria-describedby="reg-address-error"
                        >
                        <p class="field-error" id="reg-address-error"><?= e($errors['address'] ?? '') ?></p>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="reg-password">Password <span class="required-mark" aria-hidden="true">*</span></label>
                            <div class="input-password-wrap">
                                <input
                                    type="password"
                                    id="reg-password"
                                    name="password"
                                    class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                    placeholder="Minimum 8 characters"
                                    autocomplete="new-password"
                                    required
                                    minlength="8"
                                    aria-invalid="<?= isset($errors['password']) ? 'true' : 'false' ?>"
                                    aria-describedby="reg-password-error password-rules-list"
                                >
                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-toggle-password="reg-password"
                                    aria-label="Show password"
                                    aria-pressed="false"
                                >Show</button>
                            </div>
                            <p class="field-error" id="reg-password-error"><?= e($errors['password'] ?? '') ?></p>
                        </div>

                        <div class="form-group">
                            <label for="reg-confirm-password">Confirm Password <span class="required-mark" aria-hidden="true">*</span></label>
                            <div class="input-password-wrap">
                                <input
                                    type="password"
                                    id="reg-confirm-password"
                                    name="confirm_password"
                                    class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                                    placeholder="Re-enter password"
                                    autocomplete="new-password"
                                    required
                                    minlength="8"
                                    aria-invalid="<?= isset($errors['confirm_password']) ? 'true' : 'false' ?>"
                                    aria-describedby="reg-confirm-password-error"
                                >
                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-toggle-password="reg-confirm-password"
                                    aria-label="Show password"
                                    aria-pressed="false"
                                >Show</button>
                            </div>
                            <p class="field-error" id="reg-confirm-password-error"><?= e($errors['confirm_password'] ?? '') ?></p>
                        </div>
                    </div>

                    <ul class="password-rules" id="password-rules-list" aria-live="polite">
                        <li data-rule="length">At least 8 characters</li>
                        <li data-rule="upper">1 uppercase letter (A-Z)</li>
                        <li data-rule="lower">1 lowercase letter (a-z)</li>
                        <li data-rule="digit">1 number (0-9)</li>
                    </ul>

                    <button type="submit" class="btn btn-primary btn-lg btn-block" id="register-submit-btn">
                        Create Account
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

