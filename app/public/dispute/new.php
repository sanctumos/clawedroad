<?php

declare(strict_types=1);

/**
 * Start dispute: form (reason, message); POST create dispute, link to transaction, append FROZEN, insert first claim. CSRF.
 */
require_once __DIR__ . '/../includes/web_bootstrap.php';
require_once __DIR__ . '/../includes/StatusMachine.php';

$pageTitle = 'Open dispute';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/dispute/new.php'));
    exit;
}

$transactionUuid = trim((string) ($_GET['transaction_uuid'] ?? ''));
if ($transactionUuid === '') {
    header('Location: /payments.php');
    exit;
}

$stmt = $pdo->prepare('SELECT t.uuid, t.store_uuid, t.buyer_uuid, t.dispute_uuid, v.current_status FROM transactions t JOIN v_current_cumulative_transaction_statuses v ON v.uuid = t.uuid WHERE t.uuid = ?');
$stmt->execute([$transactionUuid]);
$tx = $stmt->fetch(\PDO::FETCH_ASSOC);
if (!$tx) {
    http_response_code(404);
    $pageTitle = 'Not found';
    require_once __DIR__ . '/../includes/web_header.php';
    echo '<p>Order not found.</p>';
    require_once __DIR__ . '/../includes/web_footer.php';
    exit;
}

if (!empty($tx['dispute_uuid'])) {
    header('Location: /dispute.php?uuid=' . urlencode($tx['dispute_uuid']));
    exit;
}

$isBuyer = ($tx['buyer_uuid'] ?? '') === $currentUser['uuid'];
$isVendor = false;
if (!$isBuyer) {
    $check = $pdo->prepare('SELECT 1 FROM store_users WHERE store_uuid = ? AND user_uuid = ?');
    $check->execute([$tx['store_uuid'] ?? '', $currentUser['uuid']]);
    $isVendor = (bool) $check->fetch();
}
$isStaffOrAdmin = in_array($currentUser['role'] ?? '', ['staff', 'admin'], true);
if (!$isBuyer && !$isVendor && !$isStaffOrAdmin) {
    http_response_code(403);
    $pageTitle = 'Forbidden';
    require_once __DIR__ . '/../includes/web_header.php';
    echo '<p>You do not have access to open a dispute for this order.</p>';
    require_once __DIR__ . '/../includes/web_footer.php';
    exit;
}

$paymentStatus = $tx['current_status'] ?? '';
if (!in_array($paymentStatus, ['COMPLETED', 'FROZEN'], true)) {
    require_once __DIR__ . '/../includes/web_header.php';
    echo '<p>Disputes can only be opened for completed or frozen orders.</p>';
    echo '<p><a href="/payment.php?uuid=' . urlencode($transactionUuid) . '">← Back to order</a></p>';
    require_once __DIR__ . '/../includes/web_footer.php';
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request. Please try again.';
    } else {
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        if ($message === '') {
            $error = 'Please provide a message.';
        } else {
            $disputeUuid = User::generateUuid();
            $now = date('Y-m-d H:i:s');
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('INSERT INTO disputes (uuid, status, created_at, transaction_uuid) VALUES (?, ?, ?, ?)');
                $stmt->execute([$disputeUuid, 'open', $now, $transactionUuid]);
                $pdo->prepare('UPDATE transactions SET dispute_uuid = ?, updated_at = ? WHERE uuid = ?')->execute([$disputeUuid, $now, $transactionUuid]);
                $sm = new StatusMachine($pdo);
                $sm->appendTransactionStatus($transactionUuid, 0, StatusMachine::STATUS_FROZEN, 'Dispute opened', $currentUser['uuid']);
                $claimBody = $reason !== '' ? $reason . "\n\n" . $message : $message;
                $pdo->prepare('INSERT INTO dispute_claims (dispute_uuid, claim, status, created_at, user_uuid) VALUES (?, ?, ?, ?, ?)')->execute([$disputeUuid, $claimBody, 'open', $now, $currentUser['uuid']]);
                $pdo->commit();
                header('Location: /dispute.php?uuid=' . urlencode($disputeUuid));
                exit;
            } catch (\Throwable $e) {
                $pdo->rollBack();
                $error = 'Could not create dispute. Please try again.';
            }
        }
    }
}

$csrf = $session->getCsrfToken();
require_once __DIR__ . '/../includes/web_header.php';
?>
<h1>Open dispute</h1>
<p>Order: <a href="/payment.php?uuid=<?= urlencode($transactionUuid) ?>"><?= htmlspecialchars(substr($transactionUuid, 0, 8)) ?>…</a></p>
<?php if ($error): ?><p class="alert alert-warning"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <p><label>Reason (optional) <input type="text" name="reason" value="<?= htmlspecialchars($_POST['reason'] ?? '') ?>" maxlength="200"></label></p>
    <p><label>Message <textarea name="message" rows="4" required maxlength="10000"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea></label></p>
    <p><button type="submit">Open dispute</button></p>
</form>
<p style="margin-top: 1rem;"><a href="/payment.php?uuid=<?= urlencode($transactionUuid) ?>" class="btn">← Back to order</a></p>
<?php require_once __DIR__ . '/../includes/web_footer.php';
