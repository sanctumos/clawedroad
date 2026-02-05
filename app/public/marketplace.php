<?php

declare(strict_types=1);

/**
 * Marketplace — list items (SERP). LEMP: one script per page.
 */
require_once __DIR__ . '/includes/web_bootstrap.php';

$pageTitle = 'Marketplace';
$stmt = $pdo->query("SELECT i.uuid, i.name, i.description, i.store_uuid, i.created_at, s.storename FROM items i JOIN stores s ON s.uuid = i.store_uuid AND s.deleted_at IS NULL WHERE i.deleted_at IS NULL ORDER BY i.created_at DESC LIMIT 100");
$items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/web_header.php';
?>
<h1>Marketplace</h1>
<p>Browse items from all stores.</p>
<p><a href="/api/skill.php">Agent skill (for AI agents)</a></p>
<ul class="list">
    <?php foreach ($items as $row): ?>
        <li>
            <a href="/item.php?uuid=<?= urlencode($row['uuid']) ?>"><?= htmlspecialchars($row['name']) ?></a>
            <div class="meta"><?= htmlspecialchars($row['storename']) ?> · <?= htmlspecialchars($row['uuid']) ?></div>
        </li>
    <?php endforeach; ?>
</ul>
<?php if (empty($items)): ?>
    <p>No items yet. <a href="/vendors.php">Browse vendors</a> or create a store and add items (via API or future vendor UI).</p>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/web_footer.php';
