<?php

declare(strict_types=1);

/**
 * Staff: list all deposits. Middleware: staff/admin.
 */
require_once __DIR__ . '/../includes/web_bootstrap.php';

$pageTitle = 'Staff — Deposits';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/staff/deposits.php'));
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

$stmt = $pdo->query('SELECT d.uuid, d.store_uuid, d.currency, d.crypto, d.address, d.crypto_value, d.created_at, s.storename FROM deposits d LEFT JOIN stores s ON s.uuid = d.store_uuid AND s.deleted_at IS NULL WHERE d.deleted_at IS NULL ORDER BY d.created_at DESC');
$deposits = $stmt->fetchAll(\PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/web_header.php';
?>
<h1>Deposits</h1>
<table style="border-collapse: collapse; width: 100%; max-width: 48rem;">
    <thead>
        <tr style="border-bottom: 2px solid var(--cr-border, #e8ddd8);">
            <th style="text-align: left; padding: 0.5rem;">Store</th>
            <th style="text-align: left; padding: 0.5rem;">Currency / Crypto</th>
            <th style="text-align: left; padding: 0.5rem;">Address</th>
            <th style="text-align: right; padding: 0.5rem;">Balance</th>
            <th style="text-align: left; padding: 0.5rem;">Created</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($deposits as $d): ?>
        <tr style="border-bottom: 1px solid var(--cr-border, #e8ddd8);">
            <td style="padding: 0.5rem;"><a href="/store.php?uuid=<?= urlencode($d['store_uuid']) ?>"><?= htmlspecialchars($d['storename'] ?? '') ?></a></td>
            <td style="padding: 0.5rem;"><?= htmlspecialchars($d['currency'] ?? '') ?> / <?= htmlspecialchars($d['crypto'] ?? '') ?></td>
            <td style="padding: 0.5rem;"><code><?= $d['address'] ? htmlspecialchars(mb_substr($d['address'], 0, 12)) . '…' : '—' ?></code></td>
            <td style="padding: 0.5rem; text-align: right;"><?= htmlspecialchars((string) ($d['crypto_value'] ?? '0')) ?></td>
            <td style="padding: 0.5rem;"><?= htmlspecialchars($d['created_at'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<p style="margin-top: 1rem;"><a href="/staff/index.php" class="btn">← Staff</a></p>
<?php require_once __DIR__ . '/../includes/web_footer.php';
