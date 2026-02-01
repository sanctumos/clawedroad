<?php

declare(strict_types=1);

/**
 * New support ticket: GET form, POST create ticket + first message. CSRF. Rate limit 5/hour per user. Body max 10_000 chars.
 */
require_once __DIR__ . '/../includes/web_bootstrap.php';

$pageTitle = 'New ticket';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/support/new.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request. Please try again.';
    } else {
        $cutoff = date('Y-m-d H:i:s', time() - 3600);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM support_tickets WHERE user_uuid = ? AND created_at >= ?');
        $stmt->execute([$currentUser['uuid'], $cutoff]);
        if ((int) $stmt->fetchColumn() >= 5) {
            $error = 'Rate limit: maximum 5 new tickets per hour. Please try again later.';
        } else {
            $subject = trim((string) ($_POST['subject'] ?? ''));
            $body = trim((string) ($_POST['body'] ?? ''));
            if ($subject === '') {
                $error = 'Subject is required.';
            } elseif (strlen($body) > 10000) {
                $error = 'Message must be at most 10,000 characters.';
            } else {
                $now = date('Y-m-d H:i:s');
                $pdo->prepare('INSERT INTO support_tickets (user_uuid, subject, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')->execute([$currentUser['uuid'], $subject, 'open', $now, $now]);
                $ticketId = (int) $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO support_ticket_messages (ticket_id, user_uuid, body, created_at) VALUES (?, ?, ?, ?)')->execute([$ticketId, $currentUser['uuid'], $body, $now]);
                header('Location: /support/ticket.php?id=' . $ticketId);
                exit;
            }
        }
    }
}

$csrf = $session->getCsrfToken();
require_once __DIR__ . '/../includes/web_header.php';
?>
<h1>New ticket</h1>
<?php if ($error): ?><p class="alert alert-warning"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <p><label>Subject <input type="text" name="subject" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" required maxlength="500"></label></p>
    <p><label>Message <textarea name="body" rows="6" required maxlength="10000"><?= htmlspecialchars($_POST['body'] ?? '') ?></textarea></label></p>
    <p><button type="submit">Create ticket</button></p>
</form>
<p style="margin-top: 1rem;"><a href="/support.php" class="btn">← Tickets</a></p>
<?php require_once __DIR__ . '/../includes/web_footer.php';
