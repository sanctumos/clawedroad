<?php

declare(strict_types=1);

/**
 * Support ticket thread: GET by id; POST add message (user) or reply + set status (staff). Rate limit 20 messages/hour per ticket. CSRF. AuditLog for ticket_status_change.
 */
require_once __DIR__ . '/../includes/web_bootstrap.php';
require_once __DIR__ . '/../includes/AuditLog.php';

$pageTitle = 'Ticket';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/support/ticket.php'));
    exit;
}

$ticketId = (int) ($_GET['id'] ?? 0);
if ($ticketId <= 0) {
    header('Location: /support.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, user_uuid, subject, status, created_at, updated_at FROM support_tickets WHERE id = ?');
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch(\PDO::FETCH_ASSOC);
if (!$ticket) {
    http_response_code(404);
    $pageTitle = 'Not found';
    require_once __DIR__ . '/../includes/web_header.php';
    echo '<p>Ticket not found.</p>';
    require_once __DIR__ . '/../includes/web_footer.php';
    exit;
}

$isOwner = ($ticket['user_uuid'] ?? '') === $currentUser['uuid'];
$isStaffOrAdmin = in_array($currentUser['role'] ?? '', ['staff', 'admin'], true);
if (!$isOwner && !$isStaffOrAdmin) {
    http_response_code(403);
    $pageTitle = 'Forbidden';
    require_once __DIR__ . '/../includes/web_header.php';
    echo '<p>You do not have access to this ticket.</p>';
    require_once __DIR__ . '/../includes/web_footer.php';
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = trim((string) ($_POST['action'] ?? ''));
        $now = date('Y-m-d H:i:s');
        $actorUuid = $currentUser['uuid'];

        if ($action === 'reply') {
            $body = trim((string) ($_POST['body'] ?? ''));
            if (strlen($body) > 10000) {
                $error = 'Message must be at most 10,000 characters.';
            } else {
                $cutoff = date('Y-m-d H:i:s', time() - 3600);
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM support_ticket_messages WHERE ticket_id = ? AND created_at >= ?');
                $stmt->execute([$ticketId, $cutoff]);
                if ((int) $stmt->fetchColumn() >= 20) {
                    $error = 'Rate limit: maximum 20 messages per hour for this ticket. Please try again later.';
                } elseif ($body === '') {
                    $error = 'Message is required.';
                } else {
                    $pdo->prepare('INSERT INTO support_ticket_messages (ticket_id, user_uuid, body, created_at) VALUES (?, ?, ?, ?)')->execute([$ticketId, $actorUuid, $body, $now]);
                    $pdo->prepare('UPDATE support_tickets SET updated_at = ? WHERE id = ?')->execute([$now, $ticketId]);
                    $message = 'Message sent.';
                }
            }
        } elseif ($action === 'set_status' && $isStaffOrAdmin) {
            $newStatus = trim((string) ($_POST['status'] ?? ''));
            if (!in_array($newStatus, ['open', 'closed', 'pending'], true)) {
                $newStatus = 'open';
            }
            $oldStatus = $ticket['status'] ?? '';
            $pdo->prepare('UPDATE support_tickets SET status = ?, updated_at = ? WHERE id = ?')->execute([$newStatus, $now, $ticketId]);
            AuditLog::write($pdo, $actorUuid, 'ticket_status_change', 'support_ticket', (string) $ticketId, ['old_status' => $oldStatus, 'new_status' => $newStatus]);
            $message = 'Status updated.';
            $ticket['status'] = $newStatus;
            $ticket['updated_at'] = $now;
        } else {
            $error = 'Invalid action.';
        }
    }
}

$stmt = $pdo->prepare('SELECT m.id, m.body, m.created_at, m.user_uuid, u.username FROM support_ticket_messages m LEFT JOIN users u ON u.uuid = m.user_uuid AND u.deleted_at IS NULL WHERE m.ticket_id = ? ORDER BY m.created_at ASC');
$stmt->execute([$ticketId]);
$messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$csrf = $session->getCsrfToken();
$pageTitle = 'Ticket #' . $ticketId;
require_once __DIR__ . '/../includes/web_header.php';
?>
<h1>Ticket #<?= (int) $ticketId ?></h1>
<p><strong><?= htmlspecialchars($ticket['subject']) ?></strong> — <?= htmlspecialchars($ticket['status']) ?> — <?= htmlspecialchars($ticket['created_at']) ?></p>
<?php if ($message): ?><p class="alert" style="color: green;"><?= htmlspecialchars($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert alert-warning"><?= htmlspecialchars($error) ?></p><?php endif; ?>

<h2>Messages</h2>
<ul class="list">
    <?php foreach ($messages as $m): ?>
    <li>
        <strong><?= htmlspecialchars($m['username'] ?? 'Unknown') ?></strong> — <?= htmlspecialchars($m['created_at']) ?>
        <div><?= nl2br(htmlspecialchars($m['body'] ?? '')) ?></div>
    </li>
    <?php endforeach; ?>
</ul>

<section style="margin-top: 1rem;">
    <h3>Reply</h3>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="reply">
        <p><label>Message <textarea name="body" rows="4" required maxlength="10000"></textarea></label></p>
        <p><button type="submit">Send</button></p>
    </form>
</section>

<?php if ($isStaffOrAdmin): ?>
<section style="margin-top: 1rem;">
    <h3>Set status (staff)</h3>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="set_status">
        <label>Status <select name="status">
            <option value="open" <?= ($ticket['status'] ?? '') === 'open' ? 'selected' : '' ?>>open</option>
            <option value="pending" <?= ($ticket['status'] ?? '') === 'pending' ? 'selected' : '' ?>>pending</option>
            <option value="closed" <?= ($ticket['status'] ?? '') === 'closed' ? 'selected' : '' ?>>closed</option>
        </select></label>
        <button type="submit">Update</button>
    </form>
</section>
<?php endif; ?>

<p style="margin-top: 1.5rem;"><a href="/support.php" class="btn">← Tickets</a></p>
<?php require_once __DIR__ . '/../includes/web_footer.php';
