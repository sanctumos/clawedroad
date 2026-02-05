<?php

declare(strict_types=1);

/**
 * Item page — show one item and its packages; link to buy. LEMP: one script per page. ?uuid=
 */
require_once __DIR__ . '/includes/web_bootstrap.php';

$uuid = trim((string) ($_GET['uuid'] ?? ''));
if ($uuid === '') {
    header('Location: /marketplace.php');
    exit;
}

$stmt = $pdo->prepare('SELECT i.uuid, i.name, i.description, i.store_uuid, i.created_at, s.storename FROM items i JOIN stores s ON s.uuid = i.store_uuid AND s.deleted_at IS NULL WHERE i.uuid = ? AND i.deleted_at IS NULL');
$stmt->execute([$uuid]);
$item = $stmt->fetch(\PDO::FETCH_ASSOC);
if (!$item) {
    http_response_code(404);
    $pageTitle = 'Not found';
    require_once __DIR__ . '/includes/web_header.php';
    echo '<p>Item not found.</p>';
    require_once __DIR__ . '/includes/web_footer.php';
    exit;
}

$pageTitle = $item['name'];
$stmt = $pdo->prepare('SELECT uuid, name, description, type, created_at FROM packages WHERE item_uuid = ? AND deleted_at IS NULL ORDER BY created_at');
$stmt->execute([$uuid]);
$packages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/web_header.php';
?>
<h1><?= htmlspecialchars($item['name']) ?></h1>
<div class="meta">Store: <a href="/store.php?uuid=<?= urlencode($item['store_uuid']) ?>"><?= htmlspecialchars($item['storename']) ?></a></div>
<?php if (!empty($item['description'])): ?>
    <p><?= nl2br(htmlspecialchars($item['description'])) ?></p>
<?php endif; ?>

<h2>Packages</h2>
<ul class="list">
    <?php foreach ($packages as $row): ?>
        <li>
            <strong><?= htmlspecialchars($row['name'] ?: 'Package') ?></strong>
            <?php if (!empty($row['description'])): ?>
                <div class="meta"><?= htmlspecialchars(mb_substr($row['description'], 0, 100)) ?><?= mb_strlen($row['description']) > 100 ? '…' : '' ?></div>
            <?php endif; ?>
            <?php if ($currentUser): ?>
                <a href="/book.php?package_uuid=<?= urlencode($row['uuid']) ?>" class="btn">Buy</a>
            <?php else: ?>
                <a href="/login.php">Login to buy</a>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
<?php if (empty($packages)): ?>
    <p>No packages for this item yet.</p>
<?php endif; ?>
<p><a href="/store.php?uuid=<?= urlencode($item['store_uuid']) ?>">← Back to store</a></p>
<?php require_once __DIR__ . '/includes/web_footer.php';
