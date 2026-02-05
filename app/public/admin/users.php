<?php

declare(strict_types=1);

/**
 * Admin users: list users (paginated); user detail + POST actions ban, set role staff, grant seller. Admin-only. CSRF on POST.
 */
require_once __DIR__ . '/../includes/web_bootstrap.php';
require_once __DIR__ . '/../includes/AuditLog.php';

$pageTitle = 'Admin — Users';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/admin/users.php'));
    exit;
}
if (($currentUser['role'] ?? '') !== 'admin') {
    http_response_code(403);
    $pageTitle = 'Forbidden';
    require_once __DIR__ . '/../includes/web_header.php';
    echo '<p>Admin only.</p>';
    require_once __DIR__ . '/../includes/web_footer.php';
    exit;
}

$perPage = 50;
$targetUuid = isset($_GET['uuid']) ? trim((string) $_GET['uuid']) : null;
$targetUsername = isset($_GET['username']) ? trim((string) $_GET['username']) : null;
$detailUser = null;
$postSubjectUuid = null;
$message = '';
$error = '';

// POST: apply action (ban, staff, seller)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = trim((string) ($_POST['action'] ?? ''));
        $subjectUuid = trim((string) ($_POST['user_uuid'] ?? ''));
        if ($subjectUuid === '' || $action === '') {
            $error = 'Missing action or user.';
        } else {
            $subject = $userRepo->findByUuid($subjectUuid);
            if (!$subject) {
                $error = 'User not found.';
            } else {
                $postSubjectUuid = $subjectUuid;
                $actorUuid = $currentUser['uuid'];
                if ($action === 'ban') {
                    $pdo->prepare('UPDATE users SET banned = 1, updated_at = ? WHERE uuid = ?')->execute([date('Y-m-d H:i:s'), $subjectUuid]);
                    AuditLog::write($pdo, $actorUuid, 'user_ban', 'user', $subjectUuid, ['username' => $subject['username']]);
                    $message = 'User banned.';
                } elseif ($action === 'staff') {
                    $pdo->prepare('UPDATE users SET role = ?, updated_at = ? WHERE uuid = ?')->execute([User::ROLE_STAFF, date('Y-m-d H:i:s'), $subjectUuid]);
                    AuditLog::write($pdo, $actorUuid, 'grant_staff', 'user', $subjectUuid, ['username' => $subject['username']]);
                    $message = 'Role set to staff.';
                } elseif ($action === 'seller') {
                    $stmt = $pdo->prepare('SELECT 1 FROM store_users WHERE user_uuid = ? LIMIT 1');
                    $stmt->execute([$subjectUuid]);
                    if ($stmt->fetch()) {
                        $message = 'User already has a store.';
                    } else {
                        $storeUuid = User::generateUuid();
                        $now = date('Y-m-d H:i:s');
                        $storename = $subject['username'];
                        $stmt = $pdo->prepare('SELECT 1 FROM stores WHERE storename = ?');
                        $stmt->execute([$storename]);
                        if ($stmt->fetch()) {
                            $storename = $subject['username'] . '_' . substr($storeUuid, 0, 8);
                        }
                        $pdo->prepare('INSERT INTO stores (uuid, storename, description, is_free, created_at) VALUES (?, ?, ?, 1, ?)')->execute([$storeUuid, $storename, '', $now]);
                        $pdo->prepare('INSERT INTO store_users (store_uuid, user_uuid, role) VALUES (?, ?, ?)')->execute([$storeUuid, $subjectUuid, 'owner']);
                        AuditLog::write($pdo, $actorUuid, 'grant_seller', 'user', $subjectUuid, ['username' => $subject['username'], 'store_uuid' => $storeUuid]);
                        $message = 'Seller granted; store created.';
                    }
                } else {
                    $error = 'Unknown action.';
                }
            }
        }
    }
}

// Resolve detail user by uuid or username (or POST target so we show detail after action)
if ($postSubjectUuid !== null && $postSubjectUuid !== '') {
    $detailUser = $userRepo->findByUuid($postSubjectUuid);
}
if ($detailUser === null && $targetUuid !== null && $targetUuid !== '') {
    $detailUser = $userRepo->findByUuid($targetUuid);
}
if ($detailUser === null && $targetUsername !== null && $targetUsername !== '') {
    $detailUser = $userRepo->findByUsername($targetUsername);
}

if ($detailUser !== null) {
    // Detail view: show user and action forms
    $stmt = $pdo->prepare('SELECT su.store_uuid, s.storename FROM store_users su JOIN stores s ON s.uuid = su.store_uuid WHERE su.user_uuid = ? AND su.role = ?');
    $stmt->execute([$detailUser['uuid'], 'owner']);
    $stores = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $csrf = $session->getCsrfToken();
    require_once __DIR__ . '/../includes/web_header.php';
    ?>
    <h1>User: <?= htmlspecialchars($detailUser['username']) ?></h1>
    <?php if ($message): ?><p class="alert" style="color: green;"><?= htmlspecialchars($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="alert alert-warning"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <table style="border-collapse: collapse;">
        <tr><td style="padding: 0.5rem;">UUID</td><td><code><?= htmlspecialchars($detailUser['uuid']) ?></code></td></tr>
        <tr><td style="padding: 0.5rem;">Username</td><td><?= htmlspecialchars($detailUser['username']) ?></td></tr>
        <tr><td style="padding: 0.5rem;">Role</td><td><?= htmlspecialchars($detailUser['role']) ?></td></tr>
        <tr><td style="padding: 0.5rem;">Banned</td><td><?= !empty($detailUser['banned']) ? 'Yes' : 'No' ?></td></tr>
        <tr><td style="padding: 0.5rem;">Created</td><td><?= htmlspecialchars($detailUser['created_at'] ?? '') ?></td></tr>
    </table>
    <?php if (!empty($stores)): ?>
    <p><strong>Stores:</strong> <?php foreach ($stores as $s): ?><a href="/store.php?uuid=<?= urlencode($s['store_uuid']) ?>"><?= htmlspecialchars($s['storename']) ?></a> <?php endforeach; ?></p>
    <?php endif; ?>
    <section style="margin-top: 1rem;">
        <h2>Actions</h2>
        <form method="post" style="display: inline-block; margin-right: 0.5rem;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="ban">
            <input type="hidden" name="user_uuid" value="<?= htmlspecialchars($detailUser['uuid']) ?>">
            <button type="submit" name="submit" value="1">Ban user</button>
        </form>
        <form method="post" style="display: inline-block; margin-right: 0.5rem;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="staff">
            <input type="hidden" name="user_uuid" value="<?= htmlspecialchars($detailUser['uuid']) ?>">
            <button type="submit" name="submit" value="1">Set role to staff</button>
        </form>
        <form method="post" style="display: inline-block;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="seller">
            <input type="hidden" name="user_uuid" value="<?= htmlspecialchars($detailUser['uuid']) ?>">
            <button type="submit" name="submit" value="1">Grant seller (create store)</button>
        </form>
    </section>
    <p style="margin-top: 1.5rem;"><a href="/admin/users.php" class="btn">← Back to user list</a></p>
    <?php
    require_once __DIR__ . '/../includes/web_footer.php';
    exit;
}

// List view: paginated users
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$countStmt = $pdo->query('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL');
$total = (int) $countStmt->fetchColumn();
$totalPages = (int) ceil($total / $perPage);
$stmt = $pdo->prepare('SELECT uuid, username, role, banned, created_at FROM users WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT ? OFFSET ?');
$stmt->execute([$perPage, $offset]);
$users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/web_header.php';
?>
<h1>Users</h1>
<?php if ($error): ?><p class="alert alert-warning"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($message): ?><p class="alert" style="color: green;"><?= htmlspecialchars($message) ?></p><?php endif; ?>
<table style="border-collapse: collapse; width: 100%; max-width: 40rem;">
    <thead>
        <tr style="border-bottom: 2px solid var(--cr-border, #e8ddd8);">
            <th style="text-align: left; padding: 0.5rem;">Username</th>
            <th style="text-align: left; padding: 0.5rem;">Role</th>
            <th style="text-align: left; padding: 0.5rem;">Banned</th>
            <th style="text-align: left; padding: 0.5rem;">Created</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $u): ?>
        <tr style="border-bottom: 1px solid var(--cr-border, #e8ddd8);">
            <td style="padding: 0.5rem;"><?= htmlspecialchars($u['username']) ?></td>
            <td style="padding: 0.5rem;"><?= htmlspecialchars($u['role']) ?></td>
            <td style="padding: 0.5rem;"><?= !empty($u['banned']) ? 'Yes' : 'No' ?></td>
            <td style="padding: 0.5rem;"><?= htmlspecialchars($u['created_at'] ?? '') ?></td>
            <td style="padding: 0.5rem;"><a href="/admin/users.php?uuid=<?= urlencode($u['uuid']) ?>">View</a></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php if ($totalPages > 1): ?>
<p style="margin-top: 1rem;">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i === $page): ?><strong><?= $i ?></strong><?php else: ?><a href="/admin/users.php?page=<?= $i ?>"><?= $i ?></a><?php endif; ?>
        <?= $i < $totalPages ? ' ' : '' ?>
    <?php endfor; ?>
</p>
<?php endif; ?>
<p style="margin-top: 1.5rem;"><a href="/admin/index.php" class="btn">← Admin</a></p>
<?php require_once __DIR__ . '/../includes/web_footer.php';
