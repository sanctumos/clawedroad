<?php

declare(strict_types=1);

/**
 * Support: list my tickets. 50 per page.
 */
require_once __DIR__ . '/includes/web_bootstrap.php';

$pageTitle = 'Support';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/support.php'));
    exit;
}

$perPage = 50;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM support_tickets WHERE user_uuid = ?');
$countStmt->execute([$currentUser['uuid']]);
$total = (int) $countStmt->fetchColumn();
$totalPages = (int) ceil($total / $perPage);

$stmt = $pdo->prepare('SELECT id, subject, status, created_at, updated_at FROM support_tickets WHERE user_uuid = ? ORDER BY created_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset);
$stmt->execute([$currentUser['uuid']]);
$tickets = $stmt->fetchAll(\PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/web_header.php';
?>
<h1>Support tickets</h1>
<p><a href="/support/new.php" class="btn">New ticket</a></p>
<?php if (empty($tickets)): ?>
    <p>No tickets yet.</p>
<?php else: ?>
<table style="border-collapse: collapse; width: 100%; max-width: 40rem;">
    <thead>
        <tr style="border-bottom: 2px solid var(--cr-border, #e8ddd8);">
            <th style="text-align: left; padding: 0.5rem;">Subject</th>
            <th style="text-align: left; padding: 0.5rem;">Status</th>
            <th style="text-align: left; padding: 0.5rem;">Created</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($tickets as $t): ?>
        <tr style="border-bottom: 1px solid var(--cr-border, #e8ddd8);">
            <td style="padding: 0.5rem;"><?= htmlspecialchars($t['subject']) ?></td>
            <td style="padding: 0.5rem;"><?= htmlspecialchars($t['status']) ?></td>
            <td style="padding: 0.5rem;"><?= htmlspecialchars($t['created_at']) ?></td>
            <td style="padding: 0.5rem;"><a href="/support/ticket.php?id=<?= (int) $t['id'] ?>">View</a></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php if ($totalPages > 1): ?>
<p style="margin-top: 1rem;">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i === $page): ?><strong><?= $i ?></strong><?php else: ?><a href="/support.php?page=<?= $i ?>"><?= $i ?></a><?php endif; ?>
        <?= $i < $totalPages ? ' ' : '' ?>
    <?php endfor; ?>
</p>
<?php endif; ?>
<?php endif; ?>
<p style="margin-top: 1rem;"><a href="/marketplace.php" class="btn">← Marketplace</a></p>
<?php require_once __DIR__ . '/includes/web_footer.php';
