<?php

declare(strict_types=1);

/**
 * Book — create a transaction (purchase) for a package. GET: form. POST: create transaction, redirect to payment. LEMP: one script per page. ?package_uuid=
 */
require_once __DIR__ . '/includes/web_bootstrap.php';

$packageUuid = trim((string) ($_GET['package_uuid'] ?? $_POST['package_uuid'] ?? ''));
if ($packageUuid === '') {
    header('Location: /marketplace.php');
    exit;
}

if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/book.php?package_uuid=' . $packageUuid));
    exit;
}

$stmt = $pdo->prepare('SELECT p.uuid, p.name, p.item_uuid, p.store_uuid, i.name AS item_name FROM packages p JOIN items i ON i.uuid = p.item_uuid AND i.deleted_at IS NULL WHERE p.uuid = ? AND p.deleted_at IS NULL');
$stmt->execute([$packageUuid]);
$package = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$package) {
    http_response_code(404);
    $pageTitle = 'Not found';
    require_once __DIR__ . '/includes/web_header.php';
    echo '<p>Package not found.</p>';
    require_once __DIR__ . '/includes/web_footer.php';
    exit;
}

$pageTitle = 'Buy: ' . $package['item_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $refundAddress = trim((string) ($_POST['refund_address'] ?? ''));
    $requiredAmount = (float) ($_POST['required_amount'] ?? 0);
    $chainId = (int) ($_POST['chain_id'] ?? 1);
    $currency = trim((string) ($_POST['currency'] ?? 'ETH'));
    $txUuid = User::generateUuid();
    $now = date('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO transactions (uuid, type, description, package_uuid, store_uuid, buyer_uuid, refund_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$txUuid, 'evm', '', $packageUuid, $package['store_uuid'], $currentUser['uuid'], $refundAddress ?: null, $now]);
    $pdo->prepare('INSERT INTO evm_transactions (uuid, amount, chain_id, currency, created_at) VALUES (?, ?, ?, ?, ?)')->execute([$txUuid, $requiredAmount, $chainId, $currency, $now]);
    header('Location: /payment.php?uuid=' . urlencode($txUuid));
    exit;
}

require_once __DIR__ . '/includes/web_header.php';
?>
<h1>Buy: <?= htmlspecialchars($package['item_name']) ?></h1>
<p>Package: <?= htmlspecialchars($package['name'] ?: $packageUuid) ?></p>
<form method="post" action="/book.php">
    <input type="hidden" name="package_uuid" value="<?= htmlspecialchars($packageUuid) ?>">
    <div class="form-group">
        <label>Refund address (EVM, optional)</label>
        <input type="text" name="refund_address" placeholder="0x...">
    </div>
    <div class="form-group">
        <label>Amount</label>
        <input type="number" name="required_amount" step="any" min="0" value="0" required>
    </div>
    <div class="form-group">
        <label>Chain ID</label>
        <input type="number" name="chain_id" value="1">
    </div>
    <div class="form-group">
        <label>Currency</label>
        <input type="text" name="currency" value="ETH">
    </div>
    <button type="submit" class="btn">Create order</button>
</form>
<p><a href="/item.php?uuid=<?= urlencode($package['item_uuid']) ?>">← Back to item</a></p>
<?php require_once __DIR__ . '/includes/web_footer.php';
