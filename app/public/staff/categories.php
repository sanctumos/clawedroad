<?php

declare(strict_types=1);

/**
 * Staff: CRUD item_categories. Middleware: staff/admin. CSRF on POST.
 */
require_once __DIR__ . '/../includes/web_bootstrap.php';

$pageTitle = 'Staff — Categories';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/staff/categories.php'));
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

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request.';
    } else {
        $action = trim((string) ($_POST['action'] ?? ''));
        if ($action === 'create') {
            $name = trim((string) ($_POST['name_en'] ?? ''));
            $parentId = ($_POST['parent_id'] ?? '') === '' ? null : (int) $_POST['parent_id'];
            if ($name === '') {
                $error = 'Name is required.';
            } else {
                $pdo->prepare('INSERT INTO item_categories (name_en, parent_id) VALUES (?, ?)')->execute([$name, $parentId]);
                $message = 'Category created.';
            }
        } elseif ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name_en'] ?? ''));
            $parentId = ($_POST['parent_id'] ?? '') === '' ? null : (int) $_POST['parent_id'];
            if ($id <= 0 || $name === '') {
                $error = 'Invalid category or name.';
            } else {
                $pdo->prepare('UPDATE item_categories SET name_en = ?, parent_id = ? WHERE id = ?')->execute([$name, $parentId, $id]);
                $message = 'Category updated.';
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                $error = 'Invalid category.';
            } else {
                $pdo->prepare('UPDATE item_categories SET parent_id = NULL WHERE parent_id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM item_categories WHERE id = ?')->execute([$id]);
                $message = 'Category deleted.';
            }
        }
    }
}

$categories = $pdo->query('SELECT id, name_en, parent_id FROM item_categories ORDER BY name_en')->fetchAll(\PDO::FETCH_ASSOC);
$csrf = $session->getCsrfToken();
require_once __DIR__ . '/../includes/web_header.php';
?>
<h1>Item categories</h1>
<?php if ($message): ?><p class="alert" style="color: green;"><?= htmlspecialchars($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert alert-warning"><?= htmlspecialchars($error) ?></p><?php endif; ?>

<h2>Add category</h2>
<form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="action" value="create">
    <p><label>Name <input type="text" name="name_en" required maxlength="200"></label></p>
    <p><label>Parent <select name="parent_id"><option value="">— None —</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name_en']) ?></option><?php endforeach; ?></select></label></p>
    <p><button type="submit">Create</button></p>
</form>

<h2>Categories</h2>
<table style="border-collapse: collapse; width: 100%; max-width: 32rem;">
    <thead>
        <tr style="border-bottom: 2px solid var(--cr-border, #e8ddd8);">
            <th style="text-align: left; padding: 0.5rem;">ID</th>
            <th style="text-align: left; padding: 0.5rem;">Name</th>
            <th style="text-align: left; padding: 0.5rem;">Parent</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($categories as $c): ?>
        <?php
        $parentName = '';
        if (!empty($c['parent_id'])) {
            foreach ($categories as $p) {
                if ((int) $p['id'] === (int) $c['parent_id']) {
                    $parentName = $p['name_en'];
                    break;
                }
            }
        }
        ?>
        <tr style="border-bottom: 1px solid var(--cr-border, #e8ddd8);">
            <td style="padding: 0.5rem;"><?= (int) $c['id'] ?></td>
            <td style="padding: 0.5rem;"><?= htmlspecialchars($c['name_en']) ?></td>
            <td style="padding: 0.5rem;"><?= htmlspecialchars($parentName) ?></td>
            <td style="padding: 0.5rem;">
                <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <input type="text" name="name_en" value="<?= htmlspecialchars($c['name_en']) ?>" size="20">
                    <select name="parent_id"><option value="">— None —</option><?php foreach ($categories as $p): if ((int) $p['id'] !== (int) $c['id']): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === (int) $c['parent_id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name_en']) ?></option><?php endif; endforeach; ?></select>
                    <button type="submit">Update</button>
                </form>
                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this category?');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <button type="submit">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<p style="margin-top: 1rem;"><a href="/staff/index.php" class="btn">← Staff</a></p>
<?php require_once __DIR__ . '/../includes/web_footer.php';
