<?php

declare(strict_types=1);

/**
 * Withdraw request: GET + POST. to_address = stores.withdraw_address (no user input). Owner-only. Reject if withdraw_address null. CSRF on POST.
 */
require_once __DIR__ . '/../includes/web_bootstrap.php';

$pageTitle = 'Withdraw';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/deposits/withdraw.php'));
    exit;
}

$depositUuid = trim((string) ($_GET['uuid'] ?? ''));
if ($depositUuid === '') {
    header('Location: /deposits.php');
    exit;
}

$stmt = $pdo->prepare('SELECT d.*, s.storename, s.withdraw_address FROM deposits d JOIN stores s ON s.uuid = d.store_uuid WHERE d.uuid = ? AND d.deleted_at IS NULL AND s.deleted_at IS NULL');
$stmt->execute([$depositUuid]);
$deposit = $stmt->fetch(\PDO::FETCH_ASSOC);

if (!$deposit) {
    http_response_code(404);
    $pageTitle = 'Not found';
    require_once __DIR__ . '/../includes/web_header.php';
    echo '<p>Deposit not found.</p>';
    require_once __DIR__ . '/../includes/web_footer.php';
    exit;
}

// Owner-only: user must have store_users (store_uuid, user_uuid, role = 'owner')
$stmt = $pdo->prepare('SELECT 1 FROM store_users WHERE store_uuid = ? AND user_uuid = ? AND role = ?');
$stmt->execute([$deposit['store_uuid'], $currentUser['uuid'], 'owner']);
if (!$stmt->fetch()) {
    http_response_code(403);
    $pageTitle = 'Forbidden';
    require_once __DIR__ . '/../includes/web_header.php';
    echo '<p>Only the store owner can request a withdrawal.</p>';
    require_once __DIR__ . '/../includes/web_footer.php';
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request. Please try again.';
    } else {
        $withdrawAddress = $deposit['withdraw_address'] ?? null;
        if ($withdrawAddress === null || $withdrawAddress === '') {
            $error = 'Set withdraw address in store settings first.';
        } else {
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare('INSERT INTO deposit_withdraw_intents (deposit_uuid, to_address, requested_at, requested_by_user_uuid, status, created_at) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$depositUuid, $withdrawAddress, $now, $currentUser['uuid'], 'pending', $now]);
            $success = 'Withdrawal requested. It will be processed by the system.';
            header('Location: /deposits.php?withdraw=1');
            exit;
        }
    }
}

// Re-fetch withdraw_address in case we're showing form
$stmt = $pdo->prepare('SELECT withdraw_address FROM stores WHERE uuid = ?');
$stmt->execute([$deposit['store_uuid']]);
$store = $stmt->fetch(\PDO::FETCH_ASSOC);
$withdrawAddress = $store['withdraw_address'] ?? null;

$csrf = $session->getCsrfToken();
require_once __DIR__ . '/../includes/web_header.php';
?>
<h1>Withdraw</h1>
<p>Store: <a href="/store.php?uuid=<?= urlencode($deposit['store_uuid']) ?>"><?= htmlspecialchars($deposit['storename']) ?></a></p>
<p>Deposit: <?= htmlspecialchars($deposit['currency'] ?? '') ?> / <?= htmlspecialchars($deposit['crypto'] ?? '') ?> — Balance: <?= htmlspecialchars((string) ($deposit['crypto_value'] ?? '0')) ?></p>
<?php if ($error): ?><p class="alert alert-warning"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($withdrawAddress === null || $withdrawAddress === ''): ?>
    <p class="alert alert-warning">Set your withdraw address in <a href="/settings/store.php">store settings</a> first. Withdrawals go only to that address.</p>
<?php else: ?>
    <p>Withdraw to: <code><?= htmlspecialchars($withdrawAddress) ?></code> (set in store settings)</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <p><button type="submit">Request withdrawal</button></p>
    </form>
<?php endif; ?>
<p style="margin-top: 1rem;"><a href="/deposits.php" class="btn">← Deposits</a></p>
<?php require_once __DIR__ . '/../includes/web_footer.php';
