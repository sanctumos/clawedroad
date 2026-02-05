<?php

declare(strict_types=1);

/**
 * Staff: list stores. Optional ?uuid= for detail. Middleware: staff/admin.
 */
require_once __DIR__ . '/../includes/web_bootstrap.php';

$pageTitle = 'Staff — Stores';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/staff/stores.php'));
    exit;
}
if (!in_array($currentUser['role'] ?? '', ['staff', 'admin'], true)) {
    http_response_code(403);
    $pageTitle = 'Forbidden';
    require_once __DIR__ . '/../includes/web_header.php';
    echo '<p>Staff or admin only.</p>';
    require_once __DIR__ . '/../includes/web_footer.php';
    exit;
}

$stmt = $pdo->query('SELECT uuid, storename, description, is_suspended, created_at FROM stores WHERE deleted_at IS NULL ORDER BY storename');
$stores = $stmt->fetchAll(\PDO::FETCH_ASSOC);
require_once __DIR__ . '/../includes/web_header.php';
?>
<h1>Stores</h1>
<table style="border-collapse: collapse; width: 100%; max-width: 48rem;">
    <thead>
        <tr style="border-bottom: 2px solid var(--cr-border, #e8ddd8);">
            <th style="text-align: left; padding: 0.5rem;">Store</th>
            <th style="text-align: left; padding: 0.5rem;">Suspended</th>
            <th style="text-align: left; padding: 0.5rem;">Created</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($stores as $s): ?>
        <tr style="border-bottom: 1px solid var(--cr-border, #e8ddd8);">
            <td style="padding: 0.5rem;"><a href="/store.php?uuid=<?= urlencode($s['uuid']) ?>"><?= htmlspecialchars($s['storename']) ?></a></td>
            <td style="padding: 0.5rem;"><?= !empty($s['is_suspended']) ? 'Yes' : 'No' ?></td>
            <td style="padding: 0.5rem;"><?= htmlspecialchars($s['created_at'] ?? '') ?></td>
            <td style="padding: 0.5rem;"><a href="/store.php?uuid=<?= urlencode($s['uuid']) ?>&tab=warnings">Warnings</a></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<p style="margin-top: 1rem;"><a href="/staff/index.php" class="btn">← Staff</a></p>
<?php require_once __DIR__ . '/../includes/web_footer.php';
