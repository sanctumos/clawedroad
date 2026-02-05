<?php

declare(strict_types=1);

/**
 * Admin dashboard — config and accepted tokens. Admin role required. LEMP: one script per page.
 */
require_once __DIR__ . '/../includes/web_bootstrap.php';
require_once __DIR__ . '/../includes/Config.php';

$pageTitle = 'Admin';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/admin/index.php'));
    exit;
}
if (($currentUser['role'] ?? '') !== 'admin') {
    http_response_code(403);
    $pageTitle = 'Forbidden';
    require_once __DIR__ . '/../includes/web_header.php';
    echo '<p>Admin only.</p>';
    require_once __DIR__ . '/../includes/web_footer.php';
    exit;
}

$config = new Config($pdo);
$configKeys = ['pending_duration', 'completed_duration', 'stuck_duration', 'completion_tolerance', 'partial_refund_resolver_percent', 'gold_account_commission', 'silver_account_commission', 'bronze_account_commission', 'free_account_commission'];
$configValues = [];
foreach ($configKeys as $k) {
    $configValues[$k] = $config->get($k);
}
$tokensStmt = $pdo->query('SELECT id, chain_id, symbol, contract_address, created_at FROM accepted_tokens ORDER BY chain_id, symbol');
$tokens = $tokensStmt->fetchAll(\PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/web_header.php';
?>
<h1>Admin</h1>
<section>
    <h2>Config</h2>
    <table style="border-collapse: collapse; width: 100%; max-width: 32rem;">
        <thead>
            <tr style="border-bottom: 2px solid var(--cr-border, #e8ddd8);">
                <th style="text-align: left; padding: 0.5rem;">Key</th>
                <th style="text-align: left; padding: 0.5rem;">Value</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($configValues as $key => $value): ?>
            <tr style="border-bottom: 1px solid var(--cr-border, #e8ddd8);">
                <td style="padding: 0.5rem;"><code><?= htmlspecialchars($key) ?></code></td>
                <td style="padding: 0.5rem;"><?= htmlspecialchars($value ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
<section style="margin-top: 1.5rem;">
    <h2>Accepted tokens</h2>
    <?php if (empty($tokens)): ?>
    <p>No tokens configured.</p>
    <?php else: ?>
    <table style="border-collapse: collapse; width: 100%; max-width: 40rem;">
        <thead>
            <tr style="border-bottom: 2px solid var(--cr-border, #e8ddd8);">
                <th style="text-align: left; padding: 0.5rem;">ID</th>
                <th style="text-align: left; padding: 0.5rem;">Chain</th>
                <th style="text-align: left; padding: 0.5rem;">Symbol</th>
                <th style="text-align: left; padding: 0.5rem;">Contract</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tokens as $t): ?>
            <tr style="border-bottom: 1px solid var(--cr-border, #e8ddd8);">
                <td style="padding: 0.5rem;"><?= (int) $t['id'] ?></td>
                <td style="padding: 0.5rem;"><?= htmlspecialchars((string) ($t['chain_id'] ?? '')) ?></td>
                <td style="padding: 0.5rem;"><?= htmlspecialchars((string) ($t['symbol'] ?? '')) ?></td>
                <td style="padding: 0.5rem;"><code><?= htmlspecialchars($t['contract_address'] ?? '') ?></code></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
<p><a href="/admin/users.php" class="btn">Users</a> <a href="/marketplace.php" class="btn">← Marketplace</a></p>
<?php require_once __DIR__ . '/../includes/web_footer.php'; ?>
