<?php

declare(strict_types=1);

/**
 * Vendorship agreement: show agreement text; POST re-agree (set vendorship_agreed_at for user's stores). CSRF required.
 */
require_once __DIR__ . '/../includes/web_bootstrap.php';

$pageTitle = 'Vendorship agreement';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/verification/agreement.php'));
    exit;
}

$stmt = $pdo->prepare('SELECT store_uuid FROM store_users WHERE user_uuid = ? AND role = ?');
$stmt->execute([$currentUser['uuid'], 'owner']);
$ownedStores = $stmt->fetchAll(PDO::FETCH_COLUMN);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request. Please try again.';
    } elseif (empty($ownedStores)) {
        $error = 'You do not own a store. Create a store first.';
    } else {
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare('UPDATE stores SET vendorship_agreed_at = ? WHERE uuid = ?');
        foreach ($ownedStores as $storeUuid) {
            $stmt->execute([$now, $storeUuid]);
        }
        $success = 'You have agreed to the vendorship terms.';
    }
}

require_once __DIR__ . '/../includes/web_header.php';
?>
<h1>Vendorship agreement</h1>
<?php if ($error): ?>
    <p class="alert alert-warning"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>
<?php if ($success): ?>
    <p class="alert alert-info"><?= htmlspecialchars($success) ?></p>
<?php endif; ?>
<p>By selling on this marketplace you agree to the vendorship terms: accurate listings, timely shipping, and resolution of disputes in good faith.</p>
<?php if (!empty($ownedStores)): ?>
<form method="post" action="/verification/agreement.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($session->getCsrfToken()) ?>">
    <button type="submit" class="btn">I agree</button>
</form>
<?php else: ?>
    <p><a href="/create-store.php">Create a store</a> first to agree to vendorship terms.</p>
<?php endif; ?>
<p><a href="/verification/plan.php">Verification plan (tiers)</a> · <a href="/settings/user.php">Settings</a></p>
<?php require_once __DIR__ . '/../includes/web_footer.php';
