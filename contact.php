<?php
/**
 * Contact & Customer Support Page (NovaPhone).
 * Displays flagship store details and saves validated contact inquiries into contact_messages.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

startAppSession();
$user = currentUser();

$errors = [];
$formData = [
    'name'    => $user !== null ? (string) $user['full_name'] : '',
    'email'   => $user !== null ? (string) $user['email'] : '',
    'phone'   => $user !== null ? (string) $user['phone'] : '',
    'subject' => '',
    'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'Your security session expired. Please refresh the page and try again.';
    } else {
        $formData['name']    = trim((string) ($_POST['name'] ?? ''));
        $formData['email']   = strtolower(trim((string) ($_POST['email'] ?? '')));
        $formData['phone']   = trim((string) ($_POST['phone'] ?? ''));
        $formData['subject'] = trim((string) ($_POST['subject'] ?? ''));
        $formData['message'] = trim((string) ($_POST['message'] ?? ''));

        if ($formData['name'] === '') {
            $errors['name'] = 'Full name is required.';
        } elseif (mb_strlen($formData['name']) < 2 || mb_strlen($formData['name']) > 100) {
            $errors['name'] = 'Full name must be between 2 and 100 characters.';
        }

        if ($formData['email'] === '') {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($formData['email']) > 150) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        $normalizedPhone = null;
        if ($formData['phone'] !== '') {
            if (!isValidVnPhone($formData['phone'])) {
                $errors['phone'] = 'Enter a valid 10-digit Vietnamese mobile number (e.g. 0912345678).';
            } else {
                $normalizedPhone = normalizePhone($formData['phone']);
            }
        }

        if ($formData['subject'] === '') {
            $errors['subject'] = 'Subject is required.';
        } elseif (mb_strlen($formData['subject']) < 3 || mb_strlen($formData['subject']) > 150) {
            $errors['subject'] = 'Subject must be between 3 and 150 characters.';
        }

        if ($formData['message'] === '') {
            $errors['message'] = 'Message content is required.';
        } elseif (mb_strlen($formData['message']) < 10 || mb_strlen($formData['message']) > 2000) {
            $errors['message'] = 'Message must be between 10 and 2000 characters.';
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO contact_messages (name, email, phone, subject, message, is_read)
                     VALUES (:name, :email, :phone, :subject, :message, 0)'
                );
                $stmt->execute([
                    ':name'    => $formData['name'],
                    ':email'   => $formData['email'],
                    ':phone'   => $normalizedPhone,
                    ':subject' => $formData['subject'],
                    ':message' => $formData['message'],
                ]);

                setFlash('success', 'Thank you for contacting NovaPhone! Our support team will reply to you shortly.');
                redirect('contact.php');
            } catch (PDOException $e) {
                error_log('Contact message insert failed: ' . $e->getMessage());
                $errors['general'] = 'Unable to send your message right now. Please try again later.';
            }
        }
    }
}

$pageTitle = 'Contact & Support - ' . APP_NAME;
$pageDescription = 'Reach NovaPhone for smartphone purchase consultation, 0% installment inquiries, warranty support, and corporate bulk quotes.';
$activePage = 'contact';
$pageScripts = ['assets/js/contact.js'];

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-banner">
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(BASE_URL) ?>/index.php">Home</a>
            <span aria-hidden="true">/</span>
            <span>Contact &amp; Support</span>
        </nav>
        <h1>Contact <?= e(APP_NAME) ?></h1>
        <p>Questions about 0% installment plans, trade-in quotes, or warranty coverage? Our specialists are ready to help.</p>
    </div>
</section>

<section class="section">
    <div class="container contact-layout">
        <aside class="contact-info-card">
            <h2>Showroom &amp; Support Center</h2>
            <p class="contact-lead">Visit our flagship showroom or contact us directly during business hours.</p>

            <ul class="contact-info-list">
                <li>
                    <strong>Flagship Store</strong>
                    <span><?= e(APP_ADDRESS) ?></span>
                </li>
                <li>
                    <strong>Hanoi Branch</strong>
                    <span>89 Cau Giay, Cau Giay District, Hanoi</span>
                </li>
                <li>
                    <strong>Hotline</strong>
                    <span><?= e(APP_PHONE) ?> (08:00 &ndash; 21:30 Daily)</span>
                </li>
                <li>
                    <strong>Support Email</strong>
                    <span><?= e(APP_EMAIL) ?></span>
                </li>
            </ul>

            <div class="contact-warranty-note">
                <h3>Official Warranty Guarantee</h3>
                <p>All devices include 12-month manufacturer warranty and 30-day hardware exchange across our branches.</p>
            </div>
        </aside>

        <div class="contact-form-card">
            <h2>Send Us a Message</h2>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-error" role="alert">
                    <span><?= e($errors['general']) ?></span>
                </div>
            <?php endif; ?>

            <form id="contact-form" action="<?= e(BASE_URL) ?>/contact.php" method="post" novalidate>
                <?= csrfField() ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="cnt-name">Full Name <span class="required-mark" aria-hidden="true">*</span></label>
                        <input
                            type="text"
                            id="cnt-name"
                            name="name"
                            class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                            value="<?= e($formData['name']) ?>"
                            placeholder="e.g. Pham Quoc Bao"
                            autocomplete="name"
                            required
                            minlength="2"
                            maxlength="100"
                            aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>"
                            aria-describedby="cnt-name-error"
                        >
                        <p class="field-error" id="cnt-name-error"><?= e($errors['name'] ?? '') ?></p>
                    </div>

                    <div class="form-group">
                        <label for="cnt-phone">Phone Number <span class="field-optional">(Optional)</span></label>
                        <input
                            type="tel"
                            id="cnt-phone"
                            name="phone"
                            class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                            value="<?= e($formData['phone']) ?>"
                            placeholder="e.g. 0909112233"
                            autocomplete="tel"
                            maxlength="20"
                            aria-invalid="<?= isset($errors['phone']) ? 'true' : 'false' ?>"
                            aria-describedby="cnt-phone-error"
                        >
                        <p class="field-error" id="cnt-phone-error"><?= e($errors['phone'] ?? '') ?></p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="cnt-email">Email Address <span class="required-mark" aria-hidden="true">*</span></label>
                    <input
                        type="email"
                        id="cnt-email"
                        name="email"
                        class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                        value="<?= e($formData['email']) ?>"
                        placeholder="you@example.com"
                        autocomplete="email"
                        required
                        maxlength="150"
                        aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>"
                        aria-describedby="cnt-email-error"
                    >
                    <p class="field-error" id="cnt-email-error"><?= e($errors['email'] ?? '') ?></p>
                </div>

                <div class="form-group">
                    <label for="cnt-subject">Subject <span class="required-mark" aria-hidden="true">*</span></label>
                    <input
                        type="text"
                        id="cnt-subject"
                        name="subject"
                        class="form-control <?= isset($errors['subject']) ? 'is-invalid' : '' ?>"
                        value="<?= e($formData['subject']) ?>"
                        placeholder="e.g. 0% installment inquiry for iPhone 16 Pro Max"
                        required
                        minlength="3"
                        maxlength="150"
                        aria-invalid="<?= isset($errors['subject']) ? 'true' : 'false' ?>"
                        aria-describedby="cnt-subject-error"
                    >
                    <p class="field-error" id="cnt-subject-error"><?= e($errors['subject'] ?? '') ?></p>
                </div>

                <div class="form-group">
                    <label for="cnt-message">Message <span class="required-mark" aria-hidden="true">*</span></label>
                    <textarea
                        id="cnt-message"
                        name="message"
                        rows="5"
                        class="form-control <?= isset($errors['message']) ? 'is-invalid' : '' ?>"
                        placeholder="Tell us which smartphone model or service you need assistance with..."
                        required
                        minlength="10"
                        maxlength="2000"
                        aria-invalid="<?= isset($errors['message']) ? 'true' : 'false' ?>"
                        aria-describedby="cnt-message-error"
                    ><?= e($formData['message']) ?></textarea>
                    <p class="field-error" id="cnt-message-error"><?= e($errors['message'] ?? '') ?></p>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" id="contact-submit-btn">Send Message</button>
            </form>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

