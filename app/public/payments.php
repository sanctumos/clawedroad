<?php

declare(strict_types=1);

/**
 * My orders — list current transaction statuses. Session required. LEMP: one script per page.
 */
require_once __DIR__ . '/includes/web_bootstrap.php';

$pageTitle = 'My orders';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/payments.php'));
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM v_current_cumulative_transaction_statuses WHERE buyer_uuid = ? ORDER BY updated_at DESC LIMIT 50');
$stmt->execute([$currentUser['uuid']]);
$transactions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/web_header.php';
?>
<h1>My orders</h1>
<ul class="list">
    <?php foreach ($transactions as $row): ?>
        <li>
            <a href="/payment.php?uuid=<?= urlencode($row['uuid']) ?>"><?= htmlspecialchars($row['uuid']) ?></a>
            <div class="meta">Status: <?= htmlspecialchars($row['current_status'] ?? '—') ?> · <?= htmlspecialchars($row['updated_at'] ?? '') ?></div>
        </li>
    <?php endforeach; ?>
</ul>
<?php if (empty($transactions)): ?>
    <p>No orders yet. <a href="/marketplace.php">Browse marketplace</a> to buy.</p>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/web_footer.php';
