<?php

declare(strict_types=1);

/**
 * Dispute detail: GET by uuid; POST add claim (dispute_claims + user_uuid); staff POST status (resolve), partial refund (intent). CSRF. AuditLog for dispute_resolve, dispute_partial_refund.
 */
require_once __DIR__ . '/includes/web_bootstrap.php';
require_once __DIR__ . '/includes/StatusMachine.php';
require_once __DIR__ . '/includes/AuditLog.php';

$pageTitle = 'Dispute';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/dispute.php'));
    exit;
}

$disputeUuid = trim((string) ($_GET['uuid'] ?? ''));
if ($disputeUuid === '') {
    header('Location: /payments.php');
    exit;
}

$stmt = $pdo->prepare('SELECT d.*, d.transaction_uuid AS tx_uuid FROM disputes d WHERE d.uuid = ? AND d.deleted_at IS NULL');
$stmt->execute([$disputeUuid]);
$dispute = $stmt->fetch(\PDO::FETCH_ASSOC);
if (!$dispute) {
    http_response_code(404);
    $pageTitle = 'Not found';
    require_once __DIR__ . '/includes/web_header.php';
    echo '<p>Dispute not found.</p>';
    require_once __DIR__ . '/includes/web_footer.php';
    exit;
}

$txUuid = $dispute['tx_uuid'] ?? $dispute['transaction_uuid'] ?? null;
if (!$txUuid) {
    $stmt = $pdo->prepare('SELECT uuid FROM transactions WHERE dispute_uuid = ?');
    $stmt->execute([$disputeUuid]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    $txUuid = $row['uuid'] ?? null;
}

$isBuyer = false;
$isVendor = false;
if ($txUuid) {
    $stmt = $pdo->prepare('SELECT buyer_uuid, store_uuid FROM transactions WHERE uuid = ?');
    $stmt->execute([$txUuid]);
    $tx = $stmt->fetch(\PDO::FETCH_ASSOC);
    if ($tx) {
        $isBuyer = ($tx['buyer_uuid'] ?? '') === $currentUser['uuid'];
        if (!$isBuyer) {
            $check = $pdo->prepare('SELECT 1 FROM store_users WHERE store_uuid = ? AND user_uuid = ?');
            $check->execute([$tx['store_uuid'] ?? '', $currentUser['uuid']]);
            $isVendor = (bool) $check->fetch();
        }
    }
}
$isStaffOrAdmin = in_array($currentUser['role'] ?? '', ['staff', 'admin'], true);
if (!$isBuyer && !$isVendor && !$isStaffOrAdmin) {
    http_response_code(403);
    $pageTitle = 'Forbidden';
    require_once __DIR__ . '/includes/web_header.php';
    echo '<p>You do not have access to this dispute.</p>';
    require_once __DIR__ . '/includes/web_footer.php';
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = trim((string) ($_POST['action'] ?? ''));
        $now = date('Y-m-d H:i:s');
        $actorUuid = $currentUser['uuid'];

        if ($action === 'add_claim') {
            $claimBody = trim((string) ($_POST['claim'] ?? ''));
            if ($claimBody === '') {
                $error = 'Please enter your message.';
            } elseif (strtolower($dispute['status'] ?? '') === 'resolved') {
                $error = 'This dispute is already resolved.';
            } else {
                $pdo->prepare('INSERT INTO dispute_claims (dispute_uuid, claim, status, created_at, user_uuid) VALUES (?, ?, ?, ?, ?)')->execute([$disputeUuid, $claimBody, 'open', $now, $actorUuid]);
                $message = 'Claim added.';
            }
        } elseif ($action === 'resolve' && $isStaffOrAdmin) {
            $pdo->prepare('UPDATE disputes SET status = ?, updated_at = ?, resolver_user_uuid = ? WHERE uuid = ?')->execute(['resolved', $now, $actorUuid, $disputeUuid]);
            AuditLog::write($pdo, $actorUuid, 'dispute_resolve', 'dispute', $disputeUuid, ['transaction_uuid' => $txUuid]);
            $message = 'Dispute resolved.';
            $dispute['status'] = 'resolved';
        } elseif ($action === 'partial_refund' && $isStaffOrAdmin) {
            $refundPercent = (float) ($_POST['refund_percent'] ?? 0);
            if ($refundPercent <= 0 || $refundPercent > 100) {
                $error = 'Refund percent must be between 1 and 100.';
            } elseif (!$txUuid) {
                $error = 'Transaction not linked.';
            } else {
                $sm = new StatusMachine($pdo);
                $sm->requestPartialRefund($txUuid, $refundPercent, $actorUuid);
                AuditLog::write($pdo, $actorUuid, 'dispute_partial_refund', 'dispute', $disputeUuid, ['transaction_uuid' => $txUuid, 'refund_percent' => $refundPercent]);
                $message = 'Partial refund requested.';
            }
        } else {
            $error = 'Action not allowed.';
        }
    }
}

$stmt = $pdo->prepare('SELECT c.id, c.claim, c.status, c.created_at, c.user_uuid, u.username FROM dispute_claims c LEFT JOIN users u ON u.uuid = c.user_uuid AND u.deleted_at IS NULL WHERE c.dispute_uuid = ? ORDER BY c.created_at ASC');
$stmt->execute([$disputeUuid]);
$claims = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$csrf = $session->getCsrfToken();
$pageTitle = 'Dispute ' . substr($disputeUuid, 0, 8) . '…';
require_once __DIR__ . '/includes/web_header.php';
?>
<h1>Dispute</h1>
<?php if ($message): ?><p class="alert" style="color: green;"><?= htmlspecialchars($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert alert-warning"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<dl style="display: grid; grid-template-columns: auto 1fr; gap: 0.25rem 1rem;">
    <dt>Status</dt><dd><?= htmlspecialchars($dispute['status'] ?? '') ?></dd>
    <dt>Created</dt><dd><?= htmlspecialchars($dispute['created_at'] ?? '') ?></dd>
    <?php if ($txUuid): ?>
    <dt>Order</dt><dd><a href="/payment.php?uuid=<?= urlencode($txUuid) ?>"><?= htmlspecialchars(substr($txUuid, 0, 8)) ?>…</a></dd>
    <?php endif; ?>
</dl>

<h2>Claims</h2>
<ul class="list">
    <?php foreach ($claims as $c): ?>
    <li>
        <strong><?= htmlspecialchars($c['username'] ?? 'Unknown') ?></strong> — <?= htmlspecialchars($c['created_at'] ?? '') ?>
        <div><?= nl2br(htmlspecialchars($c['claim'] ?? '')) ?></div>
    </li>
    <?php endforeach; ?>
</ul>

<?php if (strtolower($dispute['status'] ?? '') !== 'resolved'): ?>
<section style="margin-top: 1rem;">
    <h3>Add claim</h3>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="add_claim">
        <p><label>Message <textarea name="claim" rows="4" required maxlength="10000"></textarea></label></p>
        <p><button type="submit">Add claim</button></p>
    </form>
</section>
<?php endif; ?>

<?php if ($isStaffOrAdmin && strtolower($dispute['status'] ?? '') !== 'resolved'): ?>
<section style="margin-top: 1rem;">
    <h3>Staff actions</h3>
    <form method="post" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="resolve">
        <button type="submit">Resolve dispute</button>
    </form>
    <form method="post" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="partial_refund">
        <label>Partial refund % <input type="number" name="refund_percent" min="1" max="100" value="50" style="width:4rem;"></label>
        <button type="submit">Request partial refund</button>
    </form>
</section>
<?php endif; ?>

<p style="margin-top: 1.5rem;">
    <?php if ($txUuid): ?><a href="/payment.php?uuid=<?= urlencode($txUuid) ?>" class="btn">← Order</a> <?php endif; ?>
    <?php if ($isStaffOrAdmin): ?><a href="/staff/disputes.php" class="btn">Disputes list</a><?php endif; ?>
    <a href="/payments.php" class="btn">My orders</a>
</p>
<?php require_once __DIR__ . '/includes/web_footer.php';
