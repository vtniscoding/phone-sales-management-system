<?php
/**
 * Admin Contact Messages Inbox (NovaPhone).
 * Lists customer inquiries from contact_messages with filter tabs,
 * mark-read/unread toggling, and CSRF-protected deletion.
 */

require_once __DIR__ . '/../includes/admin_guard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Your security session expired. Please try again.');
        redirect('admin/messages.php');
    }

    $action = trim((string) ($_POST['action'] ?? ''));
    $msgId  = (int) ($_POST['message_id'] ?? 0);
    $returnStatus = trim((string) ($_POST['return_status'] ?? 'all'));

    if ($msgId > 0) {
        try {
            if ($action === 'mark_read') {
                $stmt = $pdo->prepare('UPDATE contact_messages SET is_read = 1 WHERE id = :id');
                $stmt->execute([':id' => $msgId]);
                setFlash('success', 'Message marked as read.');
            } elseif ($action === 'mark_unread') {
                $stmt = $pdo->prepare('UPDATE contact_messages SET is_read = 0 WHERE id = :id');
                $stmt->execute([':id' => $msgId]);
                setFlash('info', 'Message marked as unread.');
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare('DELETE FROM contact_messages WHERE id = :id');
                $stmt->execute([':id' => $msgId]);
                setFlash('success', 'Contact message deleted.');
            }
        } catch (PDOException $e) {
            error_log('Admin message action failed: ' . $e->getMessage());
            setFlash('error', 'Unable to update message status right now.');
        }
    }

    $target = in_array($returnStatus, ['unread', 'read'], true)
        ? ('admin/messages.php?status=' . $returnStatus)
        : 'admin/messages.php';
    redirect($target);
}

$statusFilter = trim((string) ($_GET['status'] ?? 'all'));
if (!in_array($statusFilter, ['all', 'unread', 'read'], true)) {
    $statusFilter = 'all';
}

$whereSql = match ($statusFilter) {
    'unread' => 'is_read = 0',
    'read'   => 'is_read = 1',
    default  => '1=1',
};

$messages = $pdo->query(
    "SELECT id, name, email, phone, subject, message, is_read, created_at
     FROM contact_messages
     WHERE {$whereSql}
     ORDER BY is_read ASC, created_at DESC, id DESC"
)->fetchAll();

$adminTitle = 'Contact Messages';
$adminActive = 'messages';

require_once __DIR__ . '/../includes/admin-header.php';
?>

<section class="admin-card">
    <div class="admin-card-header">
        <div>
            <h2>Customer Inquiries (<?= e((string) count($messages)) ?>)</h2>
            <p>Review messages submitted via the storefront Contact page</p>
        </div>
        <div class="admin-tab-pills">
            <a href="<?= e(BASE_URL) ?>/admin/messages.php" class="btn btn-sm <?= $statusFilter === 'all' ? 'btn-primary' : 'btn-outline' ?>">All</a>
            <a href="<?= e(BASE_URL) ?>/admin/messages.php?status=unread" class="btn btn-sm <?= $statusFilter === 'unread' ? 'btn-primary' : 'btn-outline' ?>">Unread</a>
            <a href="<?= e(BASE_URL) ?>/admin/messages.php?status=read" class="btn btn-sm <?= $statusFilter === 'read' ? 'btn-primary' : 'btn-outline' ?>">Read</a>
        </div>
    </div>

    <?php if (empty($messages)): ?>
        <div class="empty-state">
            <p>No contact messages found in this view.</p>
        </div>
    <?php else: ?>
        <div class="messages-stack">
            <?php foreach ($messages as $msg): ?>
                <?php $isUnread = (int) $msg['is_read'] === 0; ?>
                <article class="message-card <?= $isUnread ? 'message-unread' : '' ?>">
                    <div class="message-card-head">
                        <div>
                            <div class="message-badges">
                                <?php if ($isUnread): ?>
                                    <span class="badge badge-promo">Unread</span>
                                <?php else: ?>
                                    <span class="badge badge-stock">Read</span>
                                <?php endif; ?>
                                <span class="table-subtext"><?= e(date('M d, Y H:i', strtotime((string) $msg['created_at']))) ?></span>
                            </div>
                            <h3><?= e($msg['subject']) ?></h3>
                            <p class="message-sender">
                                <strong><?= e($msg['name']) ?></strong> &bull;
                                <a href="mailto:<?= e($msg['email']) ?>" class="table-link"><?= e($msg['email']) ?></a>
                                <?php if (!empty($msg['phone'])): ?>
                                    &bull; <?= e($msg['phone']) ?>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="table-actions">
                            <form action="<?= e(BASE_URL) ?>/admin/messages.php" method="post" class="inline-form">
                                <?= csrfField() ?>
                                <input type="hidden" name="message_id" value="<?= e((string) $msg['id']) ?>">
                                <input type="hidden" name="return_status" value="<?= e($statusFilter) ?>">
                                <?php if ($isUnread): ?>
                                    <input type="hidden" name="action" value="mark_read">
                                    <button type="submit" class="btn btn-outline btn-sm">Mark Read</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="mark_unread">
                                    <button type="submit" class="btn btn-outline btn-sm">Mark Unread</button>
                                <?php endif; ?>
                            </form>

                            <form action="<?= e(BASE_URL) ?>/admin/messages.php" method="post" class="inline-form" data-confirm-delete="Delete this inquiry from <?= e($msg['name']) ?>?">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="message_id" value="<?= e((string) $msg['id']) ?>">
                                <input type="hidden" name="return_status" value="<?= e($statusFilter) ?>">
                                <button type="submit" class="btn btn-danger-outline btn-sm">Delete</button>
                            </form>
                        </div>
                    </div>

                    <p class="message-body"><?= nl2br(e($msg['message'])) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

