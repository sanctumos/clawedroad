<?php

declare(strict_types=1);

/**
 * Staff: list store_warnings; add warning. AuditLog warning_create. Middleware: staff/admin.
 */
require_once __DIR__ . '/../includes/web_bootstrap.php';
require_once __DIR__ . '/../includes/AuditLog.php';

$pageTitle = 'Staff — Warnings';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/staff/warnings.php'));
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
        $storeUuid = trim((string) ($_POST['store_uuid'] ?? ''));
        $body = trim((string) ($_POST['message'] ?? ''));
        if ($storeUuid === '' || $body === '') {
            $error = 'Store and message are required.';
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM stores WHERE uuid = ? AND deleted_at IS NULL');
            $stmt->execute([$storeUuid]);
            if (!$stmt->fetch()) {
                $error = 'Store not found.';
            } else {
                $now = date('Y-m-d H:i:s');
                $pdo->prepare('INSERT INTO store_warnings (store_uuid, author_user_uuid, message, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([$storeUuid, $currentUser['uuid'], $body, 'open', $now, $now]);
                $newId = (int) $pdo->lastInsertId();
                AuditLog::write($pdo, $currentUser['uuid'], 'warning_create', 'store_warning', (string) $newId, ['store_uuid' => $storeUuid]);
                $message = 'Warning added.';
            }
        }
    }
}

$stmt = $pdo->query('SELECT w.id, w.store_uuid, w.message, w.status, w.created_at, s.storename, u.username AS author FROM store_warnings w LEFT JOIN stores s ON s.uuid = w.store_uuid LEFT JOIN users u ON u.uuid = w.author_user_uuid AND u.deleted_at IS NULL ORDER BY w.created_at DESC');
$warnings = $stmt->fetchAll(\PDO::FETCH_ASSOC);
$stores = $pdo->query('SELECT uuid, storename FROM stores WHERE deleted_at IS NULL ORDER BY storename')->fetchAll(\PDO::FETCH_ASSOC);
$csrf = $session->getCsrfToken();
require_once __DIR__ . '/../includes/web_header.php';
?>
<h1>Warnings</h1>
<?php if ($message): ?><p class="alert" style="color: green;"><?= htmlspecialchars($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert alert-warning"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<h2>Add warning</h2>
<form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <p><label>Store <select name="store_uuid" required><?php foreach ($stores as $s): ?><option value="<?= htmlspecialchars($s['uuid']) ?>"><?= htmlspecialchars($s['storename']) ?></option><?php endforeach; ?></select></label></p>
    <p><label>Message <textarea name="message" rows="3" required maxlength="5000"></textarea></label></p>
    <p><button type="submit">Add warning</button></p>
</form>
<h2>Recent warnings</h2>
<table style="border-collapse: collapse; width: 100%; max-width: 48rem;">
    <thead>
        <tr style="border-bottom: 2px solid var(--cr-border, #e8ddd8);">
            <th style="text-align: left; padding: 0.5rem;">Store</th>
            <th style="text-align: left; padding: 0.5rem;">Author</th>
            <th style="text-align: left; padding: 0.5rem;">Status</th>
            <th style="text-align: left; padding: 0.5rem;">Created</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($warnings as $w): ?>
        <tr style="border-bottom: 1px solid var(--cr-border, #e8ddd8);">
            <td style="padding: 0.5rem;"><a href="/store.php?uuid=<?= urlencode($w['store_uuid']) ?>&tab=warnings"><?= htmlspecialchars($w['storename'] ?? '') ?></a></td>
            <td style="padding: 0.5rem;"><?= htmlspecialchars($w['author'] ?? '') ?></td>
            <td style="padding: 0.5rem;"><?= htmlspecialchars($w['status']) ?></td>
            <td style="padding: 0.5rem;"><?= htmlspecialchars($w['created_at']) ?></td>
            <td style="padding: 0.5rem;"><a href="/store.php?uuid=<?= urlencode($w['store_uuid']) ?>&tab=warnings">View</a></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<p style="margin-top: 1rem;"><a href="/staff/index.php" class="btn">← Staff</a></p>
<?php require_once __DIR__ . '/../includes/web_footer.php';
