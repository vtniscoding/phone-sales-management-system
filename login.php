<?php
/**
 * Account Login Page (NovaPhone).
 * Authenticates both Customer and Admin accounts via password_verify(),
 * regenerates the session ID on login, merges guest cart items, and safely redirects.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

startAppSession();

// Redirect already-authenticated users to their appropriate destination
$existingUser = currentUser();
if ($existingUser !== null) {
    if (($existingUser['role'] ?? '') === 'admin' && is_file(ROOT_PATH . '/admin/index.php')) {
        redirect('admin/index.php');
    }
    redirect('index.php');
}

$redirectParam = sanitizeRedirectPath($_GET['redirect'] ?? ($_POST['redirect'] ?? ''), '');
$errors = [];
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'Your security session expired. Please refresh the page and try again.';
    } else {
        $emailValue = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password   = (string) ($_POST['password'] ?? '');

        if ($emailValue === '') {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if ($password === '') {
            $errors['password'] = 'Password is required.';
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT id, full_name, email, phone, password_hash, address, role
                     FROM users
                     WHERE email = :email
                     LIMIT 1'
                );
                $stmt->execute([':email' => $emailValue]);
                $userRow = $stmt->fetch();

                if (!$userRow || !password_verify($password, (string) $userRow['password_hash'])) {
                    $errors['general'] = 'Invalid email address or password.';
                } else {
                    // Capture guest session ID before regenerating to merge temporary cart items
                    $guestSessionId = session_id();

                    // Regenerate session ID to prevent session fixation attacks
                    session_regenerate_id(true);

                    // Refresh CSRF token for the newly regenerated authenticated session
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    // Store safe user profile data in session (never store password_hash)
                    $_SESSION['user'] = [
                        'id'        => (int) $userRow['id'],
                        'full_name' => (string) $userRow['full_name'],
                        'email'     => (string) $userRow['email'],
                        'phone'     => (string) $userRow['phone'],
                        'address'   => (string) ($userRow['address'] ?? ''),
                        'role'      => (string) $userRow['role'],
                    ];

                    // Merge any temporary guest cart items into the customer's saved cart
                    mergeGuestCartToUser($pdo, $guestSessionId, (int) $userRow['id']);

                    setFlash('success', 'Welcome back, ' . $userRow['full_name'] . '!');

                    if ($redirectParam !== '' && !str_starts_with($redirectParam, 'admin/')) {
                        redirect($redirectParam);
                    }

                    if ($userRow['role'] === 'admin' && is_file(ROOT_PATH . '/admin/index.php')) {
                        redirect('admin/index.php');
                    }

                    redirect('index.php');
                }
            } catch (PDOException $e) {
                error_log('Login failed: ' . $e->getMessage());
                $errors['general'] = 'Unable to sign in right now. Please try again later.';
            }
        }
    }
}

$pageTitle = 'Sign In - ' . APP_NAME;
$pageDescription = 'Sign in to your NovaPhone account to manage your shopping cart, place orders, and view order history.';
$activePage = 'login';
$pageScripts = ['assets/js/auth.js'];

require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
    <div class="container">
        <div class="auth-layout">
            <aside class="auth-aside">
                <span class="hero-badge">Welcome Back</span>
                <h1>Sign In to NovaPhone</h1>
                <p>Access your saved smartphone cart, checkout smoothly, and review your warranty &amp; order history anytime.</p>
                <ul class="auth-benefits">
                    <li>Automatic guest cart sync upon sign-in</li>
                    <li>Instant order tracking and status updates</li>
                    <li>Protected with bcrypt hashing &amp; CSRF verification</li>
                </ul>
            </aside>

            <div class="auth-card">
                <div class="auth-card-header">
                    <h2>Sign In</h2>
                    <p>New to NovaPhone? <a href="<?= e(BASE_URL) ?>/register.php">Create an account</a></p>
                </div>

                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-error" role="alert">
                        <span><?= e($errors['general']) ?></span>
                    </div>
                <?php endif; ?>

                <form id="login-form" action="<?= e(BASE_URL) ?>/login.php" method="post" novalidate data-auth-form="login">
                    <?= csrfField() ?>
                    <?php if ($redirectParam !== ''): ?>
                        <input type="hidden" name="redirect" value="<?= e($redirectParam) ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="login-email">Email Address <span class="required-mark" aria-hidden="true">*</span></label>
                        <input
                            type="email"
                            id="login-email"
                            name="email"
                            class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                            value="<?= e($emailValue) ?>"
                            placeholder="you@example.com"
                            autocomplete="email"
                            required
                            maxlength="150"
                            aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>"
                            aria-describedby="login-email-error"
                        >
                        <p class="field-error" id="login-email-error"><?= e($errors['email'] ?? '') ?></p>
                    </div>

                    <div class="form-group">
                        <label for="login-password">Password <span class="required-mark" aria-hidden="true">*</span></label>
                        <div class="input-password-wrap">
                            <input
                                type="password"
                                id="login-password"
                                name="password"
                                class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                                aria-invalid="<?= isset($errors['password']) ? 'true' : 'false' ?>"
                                aria-describedby="login-password-error"
                            >
                            <button
                                type="button"
                                class="password-toggle"
                                data-toggle-password="login-password"
                                aria-label="Show password"
                                aria-pressed="false"
                            >Show</button>
                        </div>
                        <p class="field-error" id="login-password-error"><?= e($errors['password'] ?? '') ?></p>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg btn-block" id="login-submit-btn">
                        Sign In
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

