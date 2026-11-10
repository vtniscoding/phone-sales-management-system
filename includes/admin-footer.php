<?php
/**
 * Shared Admin Panel Layout Footer (NovaPhone).
 */
$adminScripts = $adminScripts ?? [];
?>
            </main>
        </div>
    </div>

    <script src="<?= e(BASE_URL) ?>/assets/js/main.js" defer></script>
    <script src="<?= e(BASE_URL) ?>/assets/js/admin.js" defer></script>
    <?php foreach ($adminScripts as $scriptPath): ?>
        <script src="<?= e(BASE_URL . '/' . ltrim($scriptPath, '/')) ?>" defer></script>
    <?php endforeach; ?>
</body>
</html>

