<?php

declare(strict_types=1);

/**
 * PMs: list conversations (distinct peers, last message); thread by peer; POST send. Pagination 50; rate limit 10/min per user; body ≤ 10_000. CSRF on POST.
 */
require_once __DIR__ . '/includes/web_bootstrap.php';

$pageTitle = 'Messages';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/messages.php'));
    exit;
}

$me = $currentUser['uuid'];
$perPage = 50;
$peerParam = trim((string) ($_GET['username'] ?? $_GET['peer'] ?? ''));
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request. Please try again.';
    } else {
        $body = trim((string) ($_POST['body'] ?? ''));
        $toUsername = trim((string) ($_POST['to_username'] ?? ''));
        if (strlen($body) > 10000) {
            $error = 'Message must be at most 10,000 characters.';
        } elseif ($body === '' || $toUsername === '') {
            $error = 'Recipient and message are required.';
        } else {
            $cutoff = date('Y-m-d H:i:s', time() - 60);
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM private_messages WHERE from_user_uuid = ? AND created_at >= ?');
            $stmt->execute([$me, $cutoff]);
            if ((int) $stmt->fetchColumn() >= 10) {
                $error = 'Rate limit: maximum 10 messages per minute. Please try again in a moment.';
            } else {
                $toUser = $userRepo->findByUsername($toUsername);
                if (!$toUser || $toUser['uuid'] === $me) {
                    $error = 'Recipient not found or invalid.';
                } else {
                    $now = date('Y-m-d H:i:s');
                    $pdo->prepare('INSERT INTO private_messages (from_user_uuid, to_user_uuid, body, read_at, created_at) VALUES (?, ?, ?, NULL, ?)')->execute([$me, $toUser['uuid'], $body, $now]);
                    $message = 'Message sent.';
                    $peerParam = $toUsername;
                }
            }
        }
    }
}

if ($peerParam !== '') {
    $peerUser = $userRepo->findByUsername($peerParam);
    if (!$peerUser) {
        $peerUser = $userRepo->findByUuid($peerParam);
    }
    if (!$peerUser || $peerUser['uuid'] === $me) {
        $peerParam = '';
    }
}

if ($peerParam !== '' && isset($peerUser)) {
    $peerUuid = $peerUser['uuid'];
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare(<<<'SQL'
        SELECT id, from_user_uuid, to_user_uuid, body, read_at, created_at
        FROM private_messages
        WHERE (from_user_uuid = ? AND to_user_uuid = ?) OR (from_user_uuid = ? AND to_user_uuid = ?)
        ORDER BY created_at DESC
        LIMIT 50 OFFSET ?
    SQL);
    $stmt->execute([$me, $peerUuid, $peerUuid, $me, $offset]);
    $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM private_messages WHERE (from_user_uuid = ? AND to_user_uuid = ?) OR (from_user_uuid = ? AND to_user_uuid = ?)');
    $stmt->execute([$me, $peerUuid, $peerUuid, $me]);
    $totalMessages = (int) $stmt->fetchColumn();
    $totalPages = (int) ceil($totalMessages / $perPage);
    $pdo->prepare('UPDATE private_messages SET read_at = ? WHERE to_user_uuid = ? AND from_user_uuid = ? AND read_at IS NULL')->execute([date('Y-m-d H:i:s'), $me, $peerUuid]);
    $csrf = $session->getCsrfToken();
    $pageTitle = 'Message: ' . $peerUser['username'];
    require_once __DIR__ . '/includes/web_header.php';
    ?>
    <h1>Conversation with <?= htmlspecialchars($peerUser['username']) ?></h1>
    <?php if ($message): ?><p class="alert" style="color: green;"><?= htmlspecialchars($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="alert alert-warning"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <ul class="list" style="list-style: none; padding: 0;">
        <?php foreach (array_reverse($messages) as $m): ?>
        <li style="margin-bottom: 0.5rem;">
            <strong><?= ($m['from_user_uuid'] === $me ? 'You' : htmlspecialchars($peerUser['username'])) ?></strong> — <?= htmlspecialchars($m['created_at']) ?>
            <div><?= nl2br(htmlspecialchars($m['body'] ?? '')) ?></div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($totalPages > 1): ?>
    <p><?php for ($i = 1; $i <= $totalPages; $i++): ?><?php if ($i === $page): ?><strong><?= $i ?></strong><?php else: ?><a href="/messages.php?username=<?= urlencode($peerUser['username']) ?>&page=<?= $i ?>"><?= $i ?></a><?php endif; ?> <?php endfor; ?></p>
    <?php endif; ?>
    <form method="post" style="margin-top: 1rem;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="to_username" value="<?= htmlspecialchars($peerUser['username']) ?>">
        <p><label>Message <textarea name="body" rows="4" required maxlength="10000"></textarea></label></p>
        <p><button type="submit">Send</button></p>
    </form>
    <p style="margin-top: 1rem;"><a href="/messages.php" class="btn">← Conversations</a></p>
    <?php
    require_once __DIR__ . '/includes/web_footer.php';
    exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$stmt = $pdo->prepare(<<<'SQL'
    SELECT peer_uuid, last_at FROM (
        SELECT CASE WHEN from_user_uuid = ? THEN to_user_uuid ELSE from_user_uuid END AS peer_uuid, MAX(created_at) AS last_at
        FROM private_messages WHERE from_user_uuid = ? OR to_user_uuid = ?
        GROUP BY peer_uuid
    ) ORDER BY last_at DESC LIMIT 50 OFFSET ?
SQL);
$stmt->execute([$me, $me, $me, $offset]);
$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
$stmt = $pdo->prepare('SELECT from_user_uuid, COUNT(*) AS unread FROM private_messages WHERE to_user_uuid = ? AND read_at IS NULL GROUP BY from_user_uuid');
$stmt->execute([$me]);
$unreadByPeer = [];
while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    $unreadByPeer[$row['from_user_uuid']] = (int) $row['unread'];
}
$countStmt = $pdo->prepare(<<<'SQL'
    SELECT COUNT(*) FROM (SELECT 1 FROM private_messages WHERE from_user_uuid = ? OR to_user_uuid = ? GROUP BY CASE WHEN from_user_uuid = ? THEN to_user_uuid ELSE from_user_uuid END)
SQL);
$countStmt->execute([$me, $me, $me]);
$totalConvos = (int) $countStmt->fetchColumn();
$totalPages = (int) ceil($totalConvos / $perPage);
$conversations = [];
foreach ($rows as $r) {
    $peerUuid = $r['peer_uuid'];
    $peer = $userRepo->findByUuid($peerUuid);
    $conversations[] = [
        'peer_uuid' => $peerUuid,
        'peer_username' => $peer['username'] ?? 'Unknown',
        'last_at' => $r['last_at'],
        'unread' => $unreadByPeer[$peerUuid] ?? 0,
    ];
}
require_once __DIR__ . '/includes/web_header.php';
?>
<h1>Messages</h1>
<?php if (empty($conversations)): ?>
    <p>No conversations yet. Start one by visiting a user's profile and sending a message, or use the form below.</p>
<?php else: ?>
<ul class="list">
    <?php foreach ($conversations as $c): ?>
    <li>
        <a href="/messages.php?username=<?= urlencode($c['peer_username']) ?>"><?= htmlspecialchars($c['peer_username']) ?></a>
        <?php if (!empty($c['unread'])): ?><span class="meta">(<?= (int) $c['unread'] ?> unread)</span><?php endif; ?>
        — <?= htmlspecialchars($c['last_at']) ?>
    </li>
    <?php endforeach; ?>
</ul>
<?php if ($totalPages > 1): ?>
<p><?php for ($i = 1; $i <= $totalPages; $i++): ?><?php if ($i === $page): ?><strong><?= $i ?></strong><?php else: ?><a href="/messages.php?page=<?= $i ?>"><?= $i ?></a><?php endif; ?> <?php endfor; ?></p>
<?php endif; ?>
<?php endif; ?>
<p style="margin-top: 1rem;"><strong>Start conversation</strong></p>
<form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($session->getCsrfToken()) ?>">
    <p><label>To (username) <input type="text" name="to_username" value="<?= htmlspecialchars($_POST['to_username'] ?? '') ?>" required></label></p>
    <p><label>Message <textarea name="body" rows="4" required maxlength="10000"><?= htmlspecialchars($_POST['body'] ?? '') ?></textarea></label></p>
    <p><button type="submit">Send</button></p>
</form>
<?php if ($error): ?><p class="alert alert-warning"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<p style="margin-top: 1rem;"><a href="/marketplace.php" class="btn">← Marketplace</a></p>
<?php require_once __DIR__ . '/includes/web_footer.php';
