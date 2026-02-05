<?php

declare(strict_types=1);

/**
 * Deposits list for current user's stores (via store_users). Optional link to add. Auth + store membership.
 */
require_once __DIR__ . '/includes/web_bootstrap.php';

$pageTitle = 'Deposits';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/deposits.php'));
    exit;
}

$stmt = $pdo->prepare('SELECT d.*, s.storename FROM deposits d JOIN store_users su ON d.store_uuid = su.store_uuid JOIN stores s ON s.uuid = d.store_uuid WHERE su.user_uuid = ? AND d.deleted_at IS NULL AND s.deleted_at IS NULL ORDER BY d.created_at DESC');
$stmt->execute([$currentUser['uuid']]);
$deposits = $stmt->fetchAll(\PDO::FETCH_ASSOC);

// Check if user is owner of any store (for withdraw links)
$stmt = $pdo->prepare('SELECT store_uuid FROM store_users WHERE user_uuid = ? AND role = ?');
$stmt->execute([$currentUser['uuid'], 'owner']);
$ownedStoreUuids = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'store_uuid');

require_once __DIR__ . '/includes/web_header.php';
?>
<h1>Deposits</h1>
<p><a href="/deposits/add.php" class="btn">Add deposit</a></p>
<?php if (empty($deposits)): ?>
    <p>No deposits yet. <a href="/deposits/add.php">Add a deposit</a> for one of your stores.</p>
<?php else: ?>
<table style="border-collapse: collapse; width: 100%; max-width: 48rem;">
    <thead>
        <tr style="border-bottom: 2px solid var(--cr-border, #e8ddd8);">
            <th style="text-align: left; padding: 0.5rem;">Store</th>
            <th style="text-align: left; padding: 0.5rem;">Currency / Crypto</th>
            <th style="text-align: left; padding: 0.5rem;">Address</th>
            <th style="text-align: right; padding: 0.5rem;">Balance</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($deposits as $d): ?>
        <tr style="border-bottom: 1px solid var(--cr-border, #e8ddd8);">
            <td style="padding: 0.5rem;"><a href="/store.php?uuid=<?= urlencode($d['store_uuid']) ?>"><?= htmlspecialchars($d['storename']) ?></a></td>
            <td style="padding: 0.5rem;"><?= htmlspecialchars($d['currency'] ?? '') ?> / <?= htmlspecialchars($d['crypto'] ?? '') ?></td>
            <td style="padding: 0.5rem;"><code><?= $d['address'] ? htmlspecialchars(mb_substr($d['address'], 0, 12)) . '…' : '—' ?></code></td>
            <td style="padding: 0.5rem; text-align: right;"><?= htmlspecialchars((string) ($d['crypto_value'] ?? '0')) ?></td>
            <td style="padding: 0.5rem;">
                <?php if (in_array($d['store_uuid'], $ownedStoreUuids, true)): ?>
                    <a href="/deposits/withdraw.php?uuid=<?= urlencode($d['uuid']) ?>">Withdraw</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<p style="margin-top: 1rem;"><a href="/marketplace.php" class="btn">← Marketplace</a></p>
<?php require_once __DIR__ . '/includes/web_footer.php';
