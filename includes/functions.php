<?php
/**
 * Shared helper functions for NovaPhone (escaping, formatting, sessions, flash messages, cart count).
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Starts PHP session with secure cookie flags if not already active.
 */
function startAppSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL . '/',
        'httponly' => true,      // Prevent JavaScript from reading session cookie (XSS defense)
        'samesite' => 'Lax',     // Mitigate cross-site request forgery on top-level navigation
    ]);

    session_start();
}

/**
 * Escapes text before rendering inside HTML to prevent XSS attacks.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Formats a numeric price in Vietnamese Dong (VND) using English convention.
 */
function formatPrice(float|int|string|null $amount): string
{
    if ($amount === null || $amount === '') {
        return '0 VND';
    }
    return number_format((float) $amount, 0, '.', ',') . ' VND';
}

/**
 * Calculates integer discount percentage when original price is higher than current price.
 */
function calcDiscountPercent(?float $price, ?float $originalPrice): int
{
    if ($price === null || $originalPrice === null || $originalPrice <= $price || $originalPrice <= 0) {
        return 0;
    }
    return (int) round((($originalPrice - $price) / $originalPrice) * 100);
}

/**
 * Stores a one-time flash notification message in session.
 * Supported types: 'success', 'error', 'warning', 'info'.
 */
function setFlash(string $type, string $message): void
{
    startAppSession();
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Retrieves and clears the one-time flash notification from session.
 */
function getFlash(): ?array
{
    startAppSession();
    if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Redirects to an application-relative path and terminates execution.
 */
function redirect(string $path): never
{
    $target = str_starts_with($path, 'http') ? $path : BASE_URL . '/' . ltrim($path, '/');
    header('Location: ' . $target);
    exit;
}

/**
 * Resolves a product or brand image path safely, falling back to a local SVG placeholder
 * when the physical image file has not been generated or uploaded yet.
 */
function resolveImageUrl(?string $relativePath, string $fallback = 'assets/images/products/phone-placeholder.svg'): string
{
    $cleanPath = ltrim((string) ($relativePath ?? ''), '/');
    if ($cleanPath !== '' && is_file(ROOT_PATH . '/' . $cleanPath)) {
        return BASE_URL . '/' . $cleanPath;
    }
    return BASE_URL . '/' . ltrim($fallback, '/');
}

/**
 * Returns the currently logged-in user array from session, or null for guests.
 */
function currentUser(): ?array
{
    startAppSession();
    return $_SESSION['user'] ?? null;
}

/**
 * Counts total item quantities in the active user's or guest session's shopping cart.
 */
function getCartItemCount(PDO $pdo): int
{
    startAppSession();
    $user = currentUser();

    try {
        if ($user !== null) {
            $sql = 'SELECT COALESCE(SUM(ci.quantity), 0) AS total_qty
                    FROM carts c
                    INNER JOIN cart_items ci ON ci.cart_id = c.id
                    WHERE c.user_id = :user_id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':user_id' => (int) $user['id']]);
        } else {
            $sessionId = session_id();
            $sql = 'SELECT COALESCE(SUM(ci.quantity), 0) AS total_qty
                    FROM carts c
                    INNER JOIN cart_items ci ON ci.cart_id = c.id
                    WHERE c.session_id = :session_id AND c.user_id IS NULL';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':session_id' => $sessionId]);
        }

        $row = $stmt->fetch();
        return (int) ($row['total_qty'] ?? 0);
    } catch (PDOException $e) {
        error_log('Failed to count cart items: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Normalizes a phone number string by removing spaces, dots, and dashes.
 */
function normalizePhone(string $phone): string
{
    $cleaned = preg_replace('/[\s.\-()]+/', '', trim($phone));
    if ($cleaned === null) {
        return trim($phone);
    }
    if (str_starts_with($cleaned, '+84')) {
        $cleaned = '0' . substr($cleaned, 3);
    }
    return $cleaned;
}

/**
 * Validates a Vietnamese mobile phone number (10 digits starting with 03/05/07/08/09).
 */
function isValidVnPhone(string $phone): bool
{
    $normalized = normalizePhone($phone);
    return preg_match('/^0(3|5|7|8|9)[0-9]{8}$/', $normalized) === 1;
}

/**
 * Validates password complexity and returns an English error message or null when valid.
 */
function validatePasswordStrength(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must include at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must include at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must include at least one number.';
    }
    return null;
}

/**
 * Ensures redirect targets remain internal relative paths (prevents Open Redirect attacks).
 */
function sanitizeRedirectPath(?string $path, string $default = 'index.php'): string
{
    $candidate = trim((string) ($path ?? ''));
    if (
        $candidate === ''
        || str_contains($candidate, '://')
        || str_starts_with($candidate, '//')
        || str_contains($candidate, '\\')
        || str_contains($candidate, "\r")
        || str_contains($candidate, "\n")
    ) {
        return $default;
    }

    $cleaned = ltrim($candidate, '/');
    // Strip duplicated base folder prefix if present
    $basePrefix = ltrim(BASE_URL, '/') . '/';
    if (str_starts_with($cleaned, $basePrefix)) {
        $cleaned = substr($cleaned, strlen($basePrefix));
    }

    // Allow only safe local PHP paths with optional query string
    if (preg_match('/^[a-zA-Z0-9_\/-]+\.php(\?[a-zA-Z0-9_=&%+\-\.]*)?$/', $cleaned) !== 1) {
        return $default;
    }

    return $cleaned;
}

/**
 * Merges any guest session cart items into the authenticated user's persistent cart.
 * Caps combined quantities at each variant's available stock_qty.
 */
function mergeGuestCartToUser(PDO $pdo, string $guestSessionId, int $userId): void
{
    if ($guestSessionId === '' || $userId <= 0) {
        return;
    }

    try {
        $guestCartStmt = $pdo->prepare(
            'SELECT id FROM carts WHERE session_id = :session_id AND user_id IS NULL LIMIT 1'
        );
        $guestCartStmt->execute([':session_id' => $guestSessionId]);
        $guestCart = $guestCartStmt->fetch();

        if (!$guestCart) {
            return;
        }

        $guestCartId = (int) $guestCart['id'];
        $guestItemsStmt = $pdo->prepare(
            'SELECT ci.variant_id, ci.quantity, pv.stock_qty
             FROM cart_items ci
             INNER JOIN product_variants pv ON pv.id = ci.variant_id
             WHERE ci.cart_id = :cart_id'
        );
        $guestItemsStmt->execute([':cart_id' => $guestCartId]);
        $guestItems = $guestItemsStmt->fetchAll();

        $pdo->beginTransaction();

        if (!empty($guestItems)) {
            $userCartStmt = $pdo->prepare('SELECT id FROM carts WHERE user_id = :user_id LIMIT 1');
            $userCartStmt->execute([':user_id' => $userId]);
            $userCart = $userCartStmt->fetch();

            if ($userCart) {
                $userCartId = (int) $userCart['id'];
            } else {
                $insertCartStmt = $pdo->prepare('INSERT INTO carts (user_id, session_id) VALUES (:user_id, NULL)');
                $insertCartStmt->execute([':user_id' => $userId]);
                $userCartId = (int) $pdo->lastInsertId();
            }

            $existingItemStmt = $pdo->prepare(
                'SELECT id, quantity FROM cart_items WHERE cart_id = :cart_id AND variant_id = :variant_id LIMIT 1'
            );
            $updateItemStmt = $pdo->prepare(
                'UPDATE cart_items SET quantity = :quantity WHERE id = :id'
            );
            $insertItemStmt = $pdo->prepare(
                'INSERT INTO cart_items (cart_id, variant_id, quantity) VALUES (:cart_id, :variant_id, :quantity)'
            );

            foreach ($guestItems as $item) {
                $variantId = (int) $item['variant_id'];
                $stockQty = (int) $item['stock_qty'];
                if ($stockQty <= 0) {
                    continue;
                }

                $existingItemStmt->execute([
                    ':cart_id'    => $userCartId,
                    ':variant_id' => $variantId,
                ]);
                $existing = $existingItemStmt->fetch();

                if ($existing) {
                    $mergedQty = min($stockQty, (int) $existing['quantity'] + (int) $item['quantity']);
                    $updateItemStmt->execute([
                        ':quantity' => $mergedQty,
                        ':id'       => (int) $existing['id'],
                    ]);
                } else {
                    $insertQty = min($stockQty, (int) $item['quantity']);
                    $insertItemStmt->execute([
                        ':cart_id'    => $userCartId,
                        ':variant_id' => $variantId,
                        ':quantity'   => $insertQty,
                    ]);
                }
            }
        }

        // Remove temporary guest cart after merging
        $deleteGuestCartStmt = $pdo->prepare('DELETE FROM carts WHERE id = :id');
        $deleteGuestCartStmt->execute([':id' => $guestCartId]);

        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Guest cart merge failed: ' . $e->getMessage());
    }
}

/**
 * Requires an authenticated user session, redirecting guests to login.php.
 */
function requireLogin(?string $redirectAfterLogin = null): array
{
    $user = currentUser();
    if ($user !== null) {
        return $user;
    }

    setFlash('warning', 'Please sign in to access that page.');
    $target = 'login.php';
    if ($redirectAfterLogin !== null && $redirectAfterLogin !== '') {
        $safeRedirect = sanitizeRedirectPath($redirectAfterLogin, '');
        if ($safeRedirect !== '') {
            $target .= '?redirect=' . urlencode($safeRedirect);
        }
    }
    redirect($target);
}

/**
 * Builds a clean application URL preserving current $_GET query parameters with overrides.
 */
function buildQueryUrl(string $path, array $overrides = [], array $removeKeys = []): string
{
    $params = $_GET;
    foreach ($removeKeys as $key) {
        unset($params[$key]);
    }
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    $base = BASE_URL . '/' . ltrim($path, '/');
    if (empty($params)) {
        return $base;
    }
    return $base . '?' . http_build_query($params);
}

/**
 * Converts storage/RAM labels such as '8GB' or '1TB' into megabytes for numeric sorting.
 */
function parseCapacityMb(string $label): int
{
    if (preg_match('/^(\d+)\s*(TB|GB|MB)$/i', trim($label), $m) !== 1) {
        return 0;
    }
    $num = (int) $m[1];
    $unit = strtoupper($m[2]);
    return match ($unit) {
        'TB' => $num * 1024 * 1024,
        'GB' => $num * 1024,
        default => $num,
    };
}

/**
 * Finds the active cart ID for the current logged-in user or guest session,
 * optionally creating one when missing.
 */
function getOrCreateCartId(PDO $pdo, bool $createIfMissing = true): ?int
{
    startAppSession();
    $user = currentUser();

    if ($user !== null) {
        $stmt = $pdo->prepare('SELECT id FROM carts WHERE user_id = :user_id LIMIT 1');
        $stmt->execute([':user_id' => (int) $user['id']]);
        $row = $stmt->fetch();
        if ($row) {
            return (int) $row['id'];
        }
        if (!$createIfMissing) {
            return null;
        }
        $ins = $pdo->prepare('INSERT INTO carts (user_id, session_id) VALUES (:user_id, NULL)');
        $ins->execute([':user_id' => (int) $user['id']]);
        return (int) $pdo->lastInsertId();
    }

    $sessionId = session_id();
    if ($sessionId === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id FROM carts WHERE session_id = :session_id AND user_id IS NULL LIMIT 1');
    $stmt->execute([':session_id' => $sessionId]);
    $row = $stmt->fetch();
    if ($row) {
        return (int) $row['id'];
    }
    if (!$createIfMissing) {
        return null;
    }

    $ins = $pdo->prepare('INSERT INTO carts (user_id, session_id) VALUES (NULL, :session_id)');
    $ins->execute([':session_id' => $sessionId]);
    return (int) $pdo->lastInsertId();
}

/**
 * Fetches detailed cart line items joined with variant, product, brand, and primary image info.
 */
function getCartItems(PDO $pdo, ?int $cartId): array
{
    if ($cartId === null || $cartId <= 0) {
        return [];
    }

    $sql = "
        SELECT
            ci.id AS cart_item_id,
            ci.quantity,
            pv.id AS variant_id,
            pv.ram,
            pv.storage,
            pv.color_name,
            pv.color_hex,
            pv.price,
            pv.original_price,
            pv.stock_qty,
            pv.sku,
            p.id AS product_id,
            p.name AS product_name,
            p.slug AS product_slug,
            p.status AS product_status,
            b.name AS brand_name,
            COALESCE(
                (SELECT pi_v.image_path FROM product_images pi_v
                 WHERE pi_v.variant_id = pv.id
                 ORDER BY pi_v.is_primary DESC, pi_v.sort_order ASC, pi_v.id ASC LIMIT 1),
                (SELECT pi_p.image_path FROM product_images pi_p
                 WHERE pi_p.product_id = p.id
                 ORDER BY pi_p.is_primary DESC, pi_p.sort_order ASC, pi_p.id ASC LIMIT 1)
            ) AS image_path,
            (SELECT pi_a.alt_text FROM product_images pi_a
             WHERE pi_a.product_id = p.id
             ORDER BY pi_a.is_primary DESC, pi_a.sort_order ASC, pi_a.id ASC LIMIT 1) AS alt_text
        FROM cart_items ci
        INNER JOIN product_variants pv ON pv.id = ci.variant_id
        INNER JOIN products p ON p.id = pv.product_id
        INNER JOIN brands b ON b.id = p.brand_id
        WHERE ci.cart_id = :cart_id
        ORDER BY ci.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cart_id' => $cartId]);
    return $stmt->fetchAll();
}

/**
 * Returns English label and CSS badge class for an order lifecycle status.
 */
function getOrderStatusMeta(string $status): array
{
    return match ($status) {
        'pending'   => ['label' => 'Pending',   'class' => 'badge-status-pending'],
        'confirmed' => ['label' => 'Confirmed', 'class' => 'badge-status-confirmed'],
        'shipping'  => ['label' => 'Shipping',  'class' => 'badge-status-shipping'],
        'completed' => ['label' => 'Completed', 'class' => 'badge-status-completed'],
        'cancelled' => ['label' => 'Cancelled', 'class' => 'badge-status-cancelled'],
        default     => ['label' => ucfirst($status), 'class' => 'badge-status-pending'],
    };
}

/**
 * Returns English label and CSS badge class for a payment status.
 */
function getPaymentStatusMeta(string $status): array
{
    return match ($status) {
        'paid'   => ['label' => 'Paid',   'class' => 'badge-stock'],
        'unpaid' => ['label' => 'Unpaid', 'class' => 'badge-low'],
        default  => ['label' => ucfirst($status), 'class' => 'badge-low'],
    };
}

/**
 * Generates a lowercase URL-safe slug from an arbitrary product or brand name.
 */
function slugify(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = str_replace('+', '-plus-', $slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-');
}

/**
 * Validates and stores an uploaded product image safely (MIME check via finfo, <= 2MB, random filename).
 * Returns ['ok' => bool, 'path' => ?string, 'error' => ?string].
 */
function handleProductImageUpload(array $file): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'path' => null, 'error' => 'Invalid file upload payload.'];
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'path' => null, 'error' => 'No image file was uploaded.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => null, 'error' => 'Image upload failed (error code ' . (int) $file['error'] . ').'];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > MAX_UPLOAD_SIZE) {
        return ['ok' => false, 'path' => null, 'error' => 'Image file size must be greater than 0 and at most 2 MB.'];
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return ['ok' => false, 'path' => null, 'error' => 'Uploaded file could not be verified.'];
    }

    // Inspect actual file contents with finfo rather than trusting browser-supplied extension/type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $tmpPath) : false;
    if ($finfo) {
        finfo_close($finfo);
    }

    if (!is_string($mimeType) || !array_key_exists($mimeType, ALLOWED_IMAGE_TYPES)) {
        return ['ok' => false, 'path' => null, 'error' => 'Only JPG, PNG, and WebP images are allowed.'];
    }

    if (@getimagesize($tmpPath) === false) {
        return ['ok' => false, 'path' => null, 'error' => 'Uploaded file is not a valid image.'];
    }

    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
        return ['ok' => false, 'path' => null, 'error' => 'Unable to prepare image upload directory.'];
    }

    $ext = ALLOWED_IMAGE_TYPES[$mimeType];
    $filename = 'phone-' . bin2hex(random_bytes(10)) . '.' . $ext;
    $destPath = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpPath, $destPath)) {
        return ['ok' => false, 'path' => null, 'error' => 'Failed to save uploaded image on server.'];
    }

    return [
        'ok'    => true,
        'path'  => 'assets/uploads/products/' . $filename,
        'error' => null,
    ];
}

