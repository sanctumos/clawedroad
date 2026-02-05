<?php

declare(strict_types=1);

/**
 * Vendors — list stores. LEMP: one script per page.
 */
require_once __DIR__ . '/includes/web_bootstrap.php';

$pageTitle = 'Vendors';
$stmt = $pdo->query('SELECT uuid, storename, description, created_at FROM stores WHERE deleted_at IS NULL ORDER BY storename');
$stores = $stmt->fetchAll(\PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/web_header.php';
?>
<h1>Vendors</h1>
<p>Browse stores.</p>
<ul class="list">
    <?php foreach ($stores as $row): ?>
        <li>
            <a href="/store.php?uuid=<?= urlencode($row['uuid']) ?>"><?= htmlspecialchars($row['storename']) ?></a>
            <?php if (!empty($row['description'])): ?>
                <div class="meta"><?= htmlspecialchars(mb_substr($row['description'], 0, 120)) ?><?= mb_strlen($row['description']) > 120 ? '…' : '' ?></div>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
<?php if (empty($stores)): ?>
    <p>No stores yet. Register and create a store via <a href="/api/keys.php">API</a> or future vendor UI.</p>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/web_footer.php';
