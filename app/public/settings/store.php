<?php

declare(strict_types=1);

/**
 * Store settings: storename, description, vendorship re-agree, withdraw_address (owner only). CSRF required.
 */
require_once __DIR__ . '/../includes/web_bootstrap.php';

$pageTitle = 'Store settings';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/settings/store.php'));
    exit;
}

$stmt = $pdo->prepare('SELECT store_uuid FROM store_users WHERE user_uuid = ? AND role = ? LIMIT 1');
$stmt->execute([$currentUser['uuid'], 'owner']);
$row = $stmt->fetch(\PDO::FETCH_ASSOC);
if (!$row) {
    require_once __DIR__ . '/../includes/web_header.php';
    echo '<h1>Store settings</h1><p>You do not own a store. <a href="/create-store.php">Create a store</a> first.</p>';
    require_once __DIR__ . '/../includes/web_footer.php';
    exit;
}

$storeUuid = $row['store_uuid'];
$stmt = $pdo->prepare('SELECT uuid, storename, description, vendorship_agreed_at, withdraw_address FROM stores WHERE uuid = ? AND deleted_at IS NULL');
$stmt->execute([$storeUuid]);
$store = $stmt->fetch(\PDO::FETCH_ASSOC);
if (!$store) {
    require_once __DIR__ . '/../includes/web_header.php';
    echo '<h1>Store not found</h1>';
    require_once __DIR__ . '/../includes/web_footer.php';
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request. Please try again.';
    } else {
        $storename = trim((string) ($_POST['storename'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $withdrawAddress = trim((string) ($_POST['withdraw_address'] ?? ''));
        $agreeVendorship = isset($_POST['vendorship_agree']);

        if ($storename === '') {
            $error = 'Store name is required.';
        } else {
            if ($withdrawAddress !== '' && !preg_match('/^0x[a-fA-F0-9]{40}$/', $withdrawAddress)) {
                $error = 'Withdraw address must be a valid EVM address (0x followed by 40 hex characters).';
            } else {
                $stmt = $pdo->prepare('UPDATE stores SET storename = ?, description = ?, updated_at = ? WHERE uuid = ?');
                $stmt->execute([$storename, $description, date('Y-m-d H:i:s'), $storeUuid]);

                $stmt = $pdo->prepare('UPDATE stores SET withdraw_address = ? WHERE uuid = ?');
                $stmt->execute([$withdrawAddress === '' ? null : $withdrawAddress, $storeUuid]);

                if ($agreeVendorship) {
                    $stmt = $pdo->prepare('UPDATE stores SET vendorship_agreed_at = ? WHERE uuid = ?');
                    $stmt->execute([date('Y-m-d H:i:s'), $storeUuid]);
                }

                $success = 'Store settings saved.';
                $store['storename'] = $storename;
                $store['description'] = $description;
                $store['withdraw_address'] = $withdrawAddress === '' ? null : $withdrawAddress;
                if ($agreeVendorship) {
                    $store['vendorship_agreed_at'] = date('Y-m-d H:i:s');
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/web_header.php';
?>
<h1>Store settings</h1>
<?php if ($error): ?>
    <p class="alert alert-warning"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>
<?php if ($success): ?>
    <p class="alert alert-info"><?= htmlspecialchars($success) ?></p>
<?php endif; ?>
<form method="post" action="/settings/store.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($session->getCsrfToken()) ?>">
    <div class="form-group">
        <label for="storename">Store name</label>
        <input type="text" id="storename" name="storename" value="<?= htmlspecialchars($store['storename'] ?? '') ?>" required>
    </div>
    <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4" style="width:100%;max-width:30rem;"><?= htmlspecialchars($store['description'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
        <label for="withdraw_address">Withdraw address (EVM, 0x…)</label>
        <input type="text" id="withdraw_address" name="withdraw_address" value="<?= htmlspecialchars($store['withdraw_address'] ?? '') ?>" placeholder="0x...">
        <span class="meta">Required for deposit withdrawals. One address per store.</span>
    </div>
    <div class="form-group">
        <label><input type="checkbox" name="vendorship_agree" value="1"> I agree to the vendorship terms (re-agree)</label>
    </div>
    <button type="submit" class="btn">Save</button>
</form>
<p><a href="/settings/user.php">User settings</a> · <a href="/store.php?uuid=<?= urlencode($storeUuid) ?>">View store</a></p>
<?php require_once __DIR__ . '/../includes/web_footer.php';
