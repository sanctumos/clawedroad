<?php

declare(strict_types=1);

/**
 * Item edit: GET form (name, description), POST save. POST action=delete sets items.deleted_at. Store membership required. CSRF on POST.
 */
require_once __DIR__ . '/../includes/web_bootstrap.php';

$pageTitle = 'Edit item';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/item/edit.php'));
    exit;
}

$itemUuid = trim((string) ($_GET['uuid'] ?? ''));
if ($itemUuid === '') {
    header('Location: /marketplace.php');
    exit;
}

$stmt = $pdo->prepare('SELECT i.uuid, i.name, i.description, i.store_uuid, s.storename FROM items i JOIN stores s ON s.uuid = i.store_uuid AND s.deleted_at IS NULL WHERE i.uuid = ? AND i.deleted_at IS NULL');
$stmt->execute([$itemUuid]);
$item = $stmt->fetch(\PDO::FETCH_ASSOC);

if (!$item) {
    http_response_code(404);
    $pageTitle = 'Not found';
    require_once __DIR__ . '/../includes/web_header.php';
    echo '<p>Item not found.</p>';
    require_once __DIR__ . '/../includes/web_footer.php';
    exit;
}

// Store membership: user must be in store_users for this item's store
$stmt = $pdo->prepare('SELECT 1 FROM store_users WHERE store_uuid = ? AND user_uuid = ?');
$stmt->execute([$item['store_uuid'], $currentUser['uuid']]);
if (!$stmt->fetch()) {
    http_response_code(403);
    $pageTitle = 'Forbidden';
    require_once __DIR__ . '/../includes/web_header.php';
    echo '<p>You do not have access to edit this item.</p>';
    require_once __DIR__ . '/../includes/web_footer.php';
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request. Please try again.';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $now = date('Y-m-d H:i:s');
        $pdo->prepare('UPDATE items SET deleted_at = ?, updated_at = ? WHERE uuid = ?')->execute([$now, $now, $itemUuid]);
        $success = 'Item deleted.';
        header('Location: /store.php?uuid=' . urlencode($item['store_uuid']) . '&deleted=1');
        exit;
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        if ($name === '') {
            $error = 'Name is required.';
        } else {
            $now = date('Y-m-d H:i:s');
            $pdo->prepare('UPDATE items SET name = ?, description = ?, updated_at = ? WHERE uuid = ?')->execute([$name, $description, $now, $itemUuid]);
            $success = 'Item updated.';
            $item['name'] = $name;
            $item['description'] = $description;
        }
    }
}

$csrf = $session->getCsrfToken();
require_once __DIR__ . '/../includes/web_header.php';
?>
<h1>Edit item</h1>
<p>Store: <a href="/store.php?uuid=<?= urlencode($item['store_uuid']) ?>"><?= htmlspecialchars($item['storename']) ?></a></p>
<?php if ($error): ?><p class="alert alert-warning"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($success): ?><p class="alert" style="color: green;"><?= htmlspecialchars($success) ?></p><?php endif; ?>
<form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <p><label>Name <input type="text" name="name" value="<?= htmlspecialchars($item['name']) ?>" required maxlength="255"></label></p>
    <p><label>Description <textarea name="description" rows="4" maxlength="10000"><?= htmlspecialchars($item['description'] ?? '') ?></textarea></label></p>
    <p><button type="submit">Save</button></p>
</form>
<hr>
<form method="post" onsubmit="return confirm('Delete this item?');">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="action" value="delete">
    <p><button type="submit" class="btn" style="color: #c00;">Delete item</button></p>
</form>
<p style="margin-top: 1rem;"><a href="/item.php?uuid=<?= urlencode($itemUuid) ?>" class="btn">← View item</a> <a href="/store.php?uuid=<?= urlencode($item['store_uuid']) ?>" class="btn">Store</a></p>
<?php require_once __DIR__ . '/../includes/web_footer.php';
