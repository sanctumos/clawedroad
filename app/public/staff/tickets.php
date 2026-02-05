<?php

declare(strict_types=1);

/**
 * Staff: list all support tickets. Middleware: staff/admin.
 */
require_once __DIR__ . '/../includes/web_bootstrap.php';

$pageTitle = 'Staff — Tickets';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/staff/tickets.php'));
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

$stmt = $pdo->query('SELECT t.id, t.subject, t.status, t.created_at, u.username FROM support_tickets t LEFT JOIN users u ON u.uuid = t.user_uuid AND u.deleted_at IS NULL ORDER BY t.created_at DESC');
$tickets = $stmt->fetchAll(\PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/web_header.php';
?>
<h1>Tickets</h1>
<table style="border-collapse: collapse; width: 100%; max-width: 48rem;">
    <thead>
        <tr style="border-bottom: 2px solid var(--cr-border, #e8ddd8);">
            <th style="text-align: left; padding: 0.5rem;">#</th>
            <th style="text-align: left; padding: 0.5rem;">Subject</th>
            <th style="text-align: left; padding: 0.5rem;">User</th>
            <th style="text-align: left; padding: 0.5rem;">Status</th>
            <th style="text-align: left; padding: 0.5rem;">Created</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($tickets as $t): ?>
        <tr style="border-bottom: 1px solid var(--cr-border, #e8ddd8);">
            <td style="padding: 0.5rem;"><?= (int) $t['id'] ?></td>
            <td style="padding: 0.5rem;"><?= htmlspecialchars($t['subject']) ?></td>
            <td style="padding: 0.5rem;"><?= htmlspecialchars($t['username'] ?? '') ?></td>
            <td style="padding: 0.5rem;"><?= htmlspecialchars($t['status']) ?></td>
            <td style="padding: 0.5rem;"><?= htmlspecialchars($t['created_at']) ?></td>
            <td style="padding: 0.5rem;"><a href="/support/ticket.php?id=<?= (int) $t['id'] ?>">View</a></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<p style="margin-top: 1rem;"><a href="/staff/index.php" class="btn">← Staff</a></p>
<?php require_once __DIR__ . '/../includes/web_footer.php';
