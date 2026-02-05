<?php

declare(strict_types=1);

/**
 * Staff dispute list. Staff and admin both use this. Middleware: role in (staff, admin).
 */
require_once __DIR__ . '/../includes/web_bootstrap.php';

$pageTitle = 'Staff — Disputes';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/staff/disputes.php'));
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

$stmt = $pdo->query('SELECT d.uuid, d.status, d.created_at, d.transaction_uuid FROM disputes d WHERE d.deleted_at IS NULL ORDER BY d.created_at DESC');
$disputes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/web_header.php';
?>
<h1>Disputes</h1>
<?php if (empty($disputes)): ?>
    <p>No disputes.</p>
<?php else: ?>
<table style="border-collapse: collapse; width: 100%; max-width: 48rem;">
    <thead>
        <tr style="border-bottom: 2px solid var(--cr-border, #e8ddd8);">
            <th style="text-align: left; padding: 0.5rem;">Dispute</th>
            <th style="text-align: left; padding: 0.5rem;">Status</th>
            <th style="text-align: left; padding: 0.5rem;">Created</th>
            <th style="text-align: left; padding: 0.5rem;">Transaction</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($disputes as $d): ?>
        <tr style="border-bottom: 1px solid var(--cr-border, #e8ddd8);">
            <td style="padding: 0.5rem;"><code><?= htmlspecialchars(substr($d['uuid'], 0, 8)) ?>…</code></td>
            <td style="padding: 0.5rem;"><?= htmlspecialchars($d['status'] ?? '') ?></td>
            <td style="padding: 0.5rem;"><?= htmlspecialchars($d['created_at'] ?? '') ?></td>
            <td style="padding: 0.5rem;"><?= $d['transaction_uuid'] ? '<a href="/payment.php?uuid=' . urlencode($d['transaction_uuid']) . '">' . htmlspecialchars(substr($d['transaction_uuid'], 0, 8)) . '…</a>' : '—' ?></td>
            <td style="padding: 0.5rem;"><a href="/dispute.php?uuid=<?= urlencode($d['uuid']) ?>">View</a></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<p style="margin-top: 1rem;"><a href="/staff/index.php" class="btn">← Staff</a></p>
<?php require_once __DIR__ . '/../includes/web_footer.php';
