<?php

declare(strict_types=1);

/**
 * Public user profile. GET /user.php?username=… — show profile; 404 if not found.
 */
require_once __DIR__ . '/includes/web_bootstrap.php';

$pageTitle = 'User profile';
$username = trim((string) ($_GET['username'] ?? ''));
if ($username === '') {
    header('Location: /marketplace.php', true, 302);
    exit;
}

$user = $userRepo->findByUsername($username);
if ($user === null) {
    http_response_code(404);
    $pageTitle = 'Not found';
    require_once __DIR__ . '/includes/web_header.php';
    echo '<h1>User not found</h1><p>No user with that username.</p>';
    require_once __DIR__ . '/includes/web_footer.php';
    exit;
}

$storeUuid = null;
$stmt = $pdo->prepare('SELECT store_uuid FROM store_users WHERE user_uuid = ? LIMIT 1');
$stmt->execute([$user['uuid']]);
$row = $stmt->fetch(\PDO::FETCH_ASSOC);
if ($row) {
    $storeUuid = $row['store_uuid'];
}

require_once __DIR__ . '/includes/web_header.php';
?>
<h1><?= htmlspecialchars($user['username']) ?></h1>
<p class="meta">Member since <?= htmlspecialchars($user['created_at'] ?? '') ?></p>
<?php if ($storeUuid): ?>
    <p><a href="/store.php?uuid=<?= urlencode($storeUuid) ?>">View store</a></p>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/web_footer.php';
