<?php

declare(strict_types=1);

/**
 * Store page — show one store; tabs: Items, Reviews, Warnings. ?uuid= & ?tab=items|reviews|warnings
 */
require_once __DIR__ . '/includes/web_bootstrap.php';
require_once __DIR__ . '/includes/AuditLog.php';

$uuid = trim((string) ($_GET['uuid'] ?? ''));
if ($uuid === '') {
    header('Location: /vendors.php');
    exit;
}

$stmt = $pdo->prepare('SELECT uuid, storename, description, created_at FROM stores WHERE uuid = ? AND deleted_at IS NULL');
$stmt->execute([$uuid]);
$store = $stmt->fetch(\PDO::FETCH_ASSOC);
if (!$store) {
    http_response_code(404);
    $pageTitle = 'Not found';
    require_once __DIR__ . '/includes/web_header.php';
    echo '<p>Store not found.</p>';
    require_once __DIR__ . '/includes/web_footer.php';
    exit;
}

$tab = trim((string) ($_GET['tab'] ?? 'items'));
if (!in_array($tab, ['items', 'reviews', 'warnings'], true)) {
    $tab = 'items';
}

$isStaff = $currentUser && in_array($currentUser['role'] ?? '', ['staff', 'admin'], true);
$isStoreOwner = false;
if ($currentUser) {
    $stmt = $pdo->prepare('SELECT 1 FROM store_users WHERE store_uuid = ? AND user_uuid = ? AND role = ?');
    $stmt->execute([$uuid, $currentUser['uuid'], 'owner']);
    $isStoreOwner = (bool) $stmt->fetch();
}

$message = '';
$error = '';

// POST: resolve or ack warning
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tab === 'warnings') {
    if (!$currentUser) {
        $error = 'You must be logged in.';
    } elseif (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = trim((string) ($_POST['action'] ?? ''));
        $warningId = (int) ($_POST['warning_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT id, store_uuid, status FROM store_warnings WHERE id = ? AND store_uuid = ?');
        $stmt->execute([$warningId, $uuid]);
        $warn = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$warn) {
            $error = 'Warning not found.';
        } elseif ($action === 'resolve' && $isStaff) {
            $now = date('Y-m-d H:i:s');
            $pdo->prepare('UPDATE store_warnings SET status = ?, resolved_at = ?, updated_at = ? WHERE id = ?')->execute(['resolved', $now, $now, $warningId]);
            AuditLog::write($pdo, $currentUser['uuid'], 'warning_resolve', 'store_warning', (string) $warningId, ['store_uuid' => $uuid]);
            $message = 'Warning resolved.';
        } elseif ($action === 'ack' && $isStoreOwner) {
            $now = date('Y-m-d H:i:s');
            $pdo->prepare('UPDATE store_warnings SET status = ?, acked_at = ?, updated_at = ? WHERE id = ?')->execute(['acked', $now, $now, $warningId]);
            $message = 'Warning acknowledged.';
        } else {
            $error = 'Action not allowed.';
        }
    }
}

$pageTitle = $store['storename'];
$storeUrl = '/store.php?uuid=' . urlencode($uuid);
require_once __DIR__ . '/includes/web_header.php';
?>
<h1><?= htmlspecialchars($store['storename']) ?></h1>
<?php if (!empty($store['description'])): ?>
    <p><?= nl2br(htmlspecialchars($store['description'])) ?></p>
<?php endif; ?>
<nav style="margin: 1rem 0;">
    <a href="<?= $storeUrl ?>&tab=items" <?= $tab === 'items' ? 'style="font-weight:bold;"' : '' ?>>Items</a>
    | <a href="<?= $storeUrl ?>&tab=reviews" <?= $tab === 'reviews' ? 'style="font-weight:bold;"' : '' ?>>Reviews</a>
    | <a href="<?= $storeUrl ?>&tab=warnings" <?= $tab === 'warnings' ? 'style="font-weight:bold;"' : '' ?>>Warnings</a>
</nav>
<?php if ($message): ?><p class="alert" style="color: green;"><?= htmlspecialchars($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert alert-warning"><?= htmlspecialchars($error) ?></p><?php endif; ?>

<?php if ($tab === 'items'): ?>
<h2>Items</h2>
<?php
$stmt = $pdo->prepare('SELECT uuid, name, description, created_at FROM items WHERE store_uuid = ? AND deleted_at IS NULL ORDER BY created_at DESC');
$stmt->execute([$uuid]);
$items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
?>
<ul class="list">
    <?php foreach ($items as $row): ?>
        <li>
            <a href="/item.php?uuid=<?= urlencode($row['uuid']) ?>"><?= htmlspecialchars($row['name']) ?></a>
            <?php if (!empty($row['description'])): ?>
                <div class="meta"><?= htmlspecialchars(mb_substr($row['description'], 0, 100)) ?><?= mb_strlen($row['description']) > 100 ? '…' : '' ?></div>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
<?php if (empty($items)): ?>
    <p>No items in this store yet.</p>
<?php endif; ?>

<?php elseif ($tab === 'reviews'): ?>
<h2>Reviews</h2>
<?php
$stmt = $pdo->prepare('SELECT r.id, r.transaction_uuid, r.score, r.comment, r.created_at, u.username AS rater_username FROM reviews r JOIN users u ON u.uuid = r.rater_user_uuid AND u.deleted_at IS NULL WHERE r.store_uuid = ? ORDER BY r.created_at DESC');
$stmt->execute([$uuid]);
$reviews = $stmt->fetchAll(\PDO::FETCH_ASSOC);
?>
<?php if (empty($reviews)): ?>
    <p>No reviews yet.</p>
<?php else: ?>
<ul class="list">
    <?php foreach ($reviews as $r): ?>
        <li>
            <strong><?= htmlspecialchars($r['rater_username']) ?></strong> — <?= (int) $r['score'] ?> / 5
            <?php if (!empty($r['comment'])): ?>
                <div><?= nl2br(htmlspecialchars($r['comment'])) ?></div>
            <?php endif; ?>
            <span class="meta"><?= htmlspecialchars($r['created_at']) ?></span>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<?php elseif ($tab === 'warnings'): ?>
<h2>Warnings</h2>
<?php
$stmt = $pdo->prepare('SELECT w.id, w.message, w.status, w.created_at, w.resolved_at, w.acked_at, u.username AS author_username FROM store_warnings w JOIN users u ON u.uuid = w.author_user_uuid AND u.deleted_at IS NULL WHERE w.store_uuid = ? ORDER BY w.created_at DESC');
$stmt->execute([$uuid]);
$warnings = $stmt->fetchAll(\PDO::FETCH_ASSOC);
$csrf = $session->getCsrfToken();
?>
<?php if (empty($warnings)): ?>
    <p>No warnings.</p>
<?php else: ?>
<ul class="list">
    <?php foreach ($warnings as $w): ?>
        <li>
            <strong><?= htmlspecialchars($w['author_username']) ?></strong> — <?= htmlspecialchars($w['status']) ?>
            <div><?= nl2br(htmlspecialchars($w['message'])) ?></div>
            <span class="meta"><?= htmlspecialchars($w['created_at']) ?></span>
            <?php if ($w['status'] === 'open'): ?>
                <?php if ($isStaff): ?>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="resolve">
                    <input type="hidden" name="warning_id" value="<?= (int) $w['id'] ?>">
                    <button type="submit">Resolve</button>
                </form>
                <?php endif; ?>
                <?php if ($isStoreOwner): ?>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="ack">
                    <input type="hidden" name="warning_id" value="<?= (int) $w['id'] ?>">
                    <button type="submit">Acknowledge</button>
                </form>
                <?php endif; ?>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
<?php endif; ?>

<p style="margin-top: 1rem;"><a href="/vendors.php">← Back to vendors</a></p>
<?php require_once __DIR__ . '/includes/web_footer.php';
