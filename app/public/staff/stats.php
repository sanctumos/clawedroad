<?php

declare(strict_types=1);

/**
 * Staff: simple stats (counts). Middleware: staff/admin.
 */
require_once __DIR__ . '/../includes/web_bootstrap.php';

$pageTitle = 'Staff — Stats';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/staff/stats.php'));
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

$usersCount = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL')->fetchColumn();
$storesCount = (int) $pdo->query('SELECT COUNT(*) FROM stores WHERE deleted_at IS NULL')->fetchColumn();
$itemsCount = (int) $pdo->query('SELECT COUNT(*) FROM items WHERE deleted_at IS NULL')->fetchColumn();
$transactionsCount = (int) $pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn();
$ticketsCount = (int) $pdo->query('SELECT COUNT(*) FROM support_tickets')->fetchColumn();
$disputesCount = (int) $pdo->query('SELECT COUNT(*) FROM disputes WHERE deleted_at IS NULL')->fetchColumn();
$warningsCount = (int) $pdo->query('SELECT COUNT(*) FROM store_warnings')->fetchColumn();

require_once __DIR__ . '/../includes/web_header.php';
?>
<h1>Stats</h1>
<table style="border-collapse: collapse; width: 100%; max-width: 24rem;">
    <tr style="border-bottom: 1px solid var(--cr-border, #e8ddd8);"><td style="padding: 0.5rem;">Users</td><td style="padding: 0.5rem;"><?= $usersCount ?></td></tr>
    <tr style="border-bottom: 1px solid var(--cr-border, #e8ddd8);"><td style="padding: 0.5rem;">Stores</td><td style="padding: 0.5rem;"><?= $storesCount ?></td></tr>
    <tr style="border-bottom: 1px solid var(--cr-border, #e8ddd8);"><td style="padding: 0.5rem;">Items</td><td style="padding: 0.5rem;"><?= $itemsCount ?></td></tr>
    <tr style="border-bottom: 1px solid var(--cr-border, #e8ddd8);"><td style="padding: 0.5rem;">Transactions</td><td style="padding: 0.5rem;"><?= $transactionsCount ?></td></tr>
    <tr style="border-bottom: 1px solid var(--cr-border, #e8ddd8);"><td style="padding: 0.5rem;">Support tickets</td><td style="padding: 0.5rem;"><?= $ticketsCount ?></td></tr>
    <tr style="border-bottom: 1px solid var(--cr-border, #e8ddd8);"><td style="padding: 0.5rem;">Disputes</td><td style="padding: 0.5rem;"><?= $disputesCount ?></td></tr>
    <tr style="border-bottom: 1px solid var(--cr-border, #e8ddd8);"><td style="padding: 0.5rem;">Store warnings</td><td style="padding: 0.5rem;"><?= $warningsCount ?></td></tr>
</table>
<p style="margin-top: 1rem;"><a href="/staff/index.php" class="btn">← Staff</a></p>
<?php require_once __DIR__ . '/../includes/web_footer.php';
