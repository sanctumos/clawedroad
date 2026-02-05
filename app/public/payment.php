<?php

declare(strict_types=1);

/**
 * Payment page: show order; buttons and POST handlers per State & Permission Matrix (08). CSRF on POST.
 * Access: buyer, vendor (store_users), staff, admin.
 */
require_once __DIR__ . '/includes/web_bootstrap.php';
require_once __DIR__ . '/includes/StatusMachine.php';

$uuid = trim((string) ($_GET['uuid'] ?? ''));
if ($uuid === '') {
    header('Location: /payments.php');
    exit;
}

if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/payment.php?uuid=' . $uuid));
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM v_current_cumulative_transaction_statuses WHERE uuid = ?');
$stmt->execute([$uuid]);
$tx = $stmt->fetch(\PDO::FETCH_ASSOC);
if (!$tx) {
    http_response_code(404);
    $pageTitle = 'Not found';
    require_once __DIR__ . '/includes/web_header.php';
    echo '<p>Order not found.</p>';
    require_once __DIR__ . '/includes/web_footer.php';
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
    require_once __DIR__ . '/includes/web_header.php';
    echo '<p>You do not have access to this order.</p>';
    require_once __DIR__ . '/includes/web_footer.php';
    exit;
}

$paymentStatus = $tx['current_status'] ?? 'PENDING';
$shippingStatus = $tx['current_shipping_status'] ?? 'DISPATCH PENDING';
$disputeStatus = 'NONE';
if (!empty($tx['dispute_uuid'])) {
    $stmt = $pdo->prepare('SELECT status FROM disputes WHERE uuid = ?');
    $stmt->execute([$tx['dispute_uuid']]);
    $d = $stmt->fetch(\PDO::FETCH_ASSOC);
    $disputeStatus = ($d && (strtolower($d['status'] ?? '') === 'resolved')) ? 'RESOLVED' : 'OPEN';
}

// Allowed actions per State & Permission Matrix (README binding)
$allowed = [
    'cancel' => false,
    'mark_shipped' => false,
    'release' => false,
    'confirm_received' => false,
    'open_dispute' => false,
];
if ($paymentStatus === 'PENDING' && $disputeStatus === 'NONE') {
    if ($isBuyer || $isStaffOrAdmin) {
        $allowed['cancel'] = true;
    }
}
if ($paymentStatus === 'COMPLETED' && $disputeStatus === 'NONE') {
    if ($shippingStatus === 'DISPATCH PENDING') {
        if ($isBuyer) {
            $allowed['confirm_received'] = true;
            $allowed['open_dispute'] = true;
        }
        if ($isVendor || $isStaffOrAdmin) {
            $allowed['mark_shipped'] = true;
            $allowed['release'] = true;
            $allowed['open_dispute'] = true;
        }
    } else {
        if ($isBuyer) {
            $allowed['confirm_received'] = true;
            $allowed['open_dispute'] = true;
        }
        if ($isVendor || $isStaffOrAdmin) {
            $allowed['release'] = true;
            $allowed['open_dispute'] = true;
        }
    }
}
if ($paymentStatus === 'COMPLETED' && $disputeStatus === 'NONE' && ($isBuyer || $isVendor)) {
    $allowed['open_dispute'] = true;
}
if ($paymentStatus === 'FROZEN' && $disputeStatus === 'OPEN') {
    $allowed['open_dispute'] = false;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = trim((string) ($_POST['action'] ?? ''));
        $sm = new StatusMachine($pdo);
        $now = date('Y-m-d H:i:s');
        $userUuid = $currentUser['uuid'];

        if ($action === 'cancel' && $allowed['cancel']) {
            $sm->requestCancel($uuid, $userUuid);
            $message = 'Cancel requested.';
            header('Location: /payment.php?uuid=' . urlencode($uuid) . '&msg=cancel');
            exit;
        }
        if ($action === 'mark_shipped' && $allowed['mark_shipped']) {
            $sm->appendShippingStatus($uuid, 'DISPATCHED', 'Marked shipped', $userUuid);
            $message = 'Marked as shipped.';
            header('Location: /payment.php?uuid=' . urlencode($uuid) . '&msg=shipped');
            exit;
        }
        if ($action === 'release' && $allowed['release']) {
            $sm->requestRelease($uuid, $userUuid);
            $message = 'Release requested.';
            header('Location: /payment.php?uuid=' . urlencode($uuid) . '&msg=release');
            exit;
        }
        if ($action === 'confirm_received' && $allowed['confirm_received']) {
            $pdo->prepare('UPDATE transactions SET buyer_confirmed_at = ?, updated_at = ? WHERE uuid = ?')->execute([$now, $now, $uuid]);
            $message = 'Confirmed received.';
            header('Location: /payment.php?uuid=' . urlencode($uuid) . '&msg=confirmed');
            exit;
        }
        $error = 'Action not allowed or invalid.';
    }
}

if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
    if ($msg === 'cancel') {
        $message = 'Cancel requested.';
    } elseif ($msg === 'shipped') {
        $message = 'Marked as shipped.';
    } elseif ($msg === 'release') {
        $message = 'Release requested.';
    } elseif ($msg === 'confirmed') {
        $message = 'Confirmed received.';
    }
}

// Can buyer add review? (RELEASED, buyer, one per tx)
$canAddReview = false;
$hasReview = false;
if ($paymentStatus === 'RELEASED' && $isBuyer) {
    $stmt = $pdo->prepare('SELECT 1 FROM reviews WHERE transaction_uuid = ? LIMIT 1');
    $stmt->execute([$uuid]);
    $hasReview = (bool) $stmt->fetch();
    $canAddReview = !$hasReview;
}

$pageTitle = 'Order ' . substr($uuid, 0, 8) . '…';
$csrf = $session->getCsrfToken();
require_once __DIR__ . '/includes/web_header.php';
?>
<h1>Order</h1>
<?php if ($message): ?><p class="alert" style="color: green;"><?= htmlspecialchars($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert alert-warning"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<dl style="display: grid; grid-template-columns: auto 1fr; gap: 0.25rem 1rem;">
    <dt>UUID</dt><dd><code><?= htmlspecialchars($uuid) ?></code></dd>
    <dt>Status</dt><dd><?= htmlspecialchars($paymentStatus) ?></dd>
    <dt>Shipping</dt><dd><?= htmlspecialchars($shippingStatus) ?></dd>
    <dt>Updated</dt><dd><?= htmlspecialchars($tx['updated_at'] ?? '—') ?></dd>
    <?php if (!empty($tx['escrow_address'])): ?>
        <dt>Escrow address</dt><dd><code><?= htmlspecialchars($tx['escrow_address']) ?></code></dd>
        <dt></dt><dd class="alert alert-info">Send payment to this address. Amount and currency are set when the order was created.</dd>
    <?php else: ?>
        <dt>Escrow</dt><dd class="alert alert-warning">Escrow address pending (cron will fill it shortly).</dd>
    <?php endif; ?>
</dl>

<section style="margin-top: 1rem;">
    <h2>Actions</h2>
    <?php if ($allowed['cancel']): ?>
    <form method="post" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="cancel">
        <button type="submit">Cancel order</button>
    </form>
    <?php endif; ?>
    <?php if ($allowed['mark_shipped']): ?>
    <form method="post" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="mark_shipped">
        <button type="submit">Mark shipped</button>
    </form>
    <?php endif; ?>
    <?php if ($allowed['release']): ?>
    <form method="post" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="release">
        <button type="submit">Release</button>
    </form>
    <?php endif; ?>
    <?php if ($allowed['confirm_received']): ?>
    <form method="post" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="confirm_received">
        <button type="submit">Confirm received</button>
    </form>
    <?php endif; ?>
    <?php if ($allowed['open_dispute']): ?>
    <p><a href="/dispute/new.php?transaction_uuid=<?= urlencode($uuid) ?>" class="btn">Open dispute</a></p>
    <?php endif; ?>
    <?php if ($canAddReview): ?>
    <p><a href="/review/add.php?transaction_uuid=<?= urlencode($uuid) ?>" class="btn">Add review</a></p>
    <?php elseif ($hasReview && $isBuyer): ?>
    <p class="meta">You have already reviewed this order.</p>
    <?php endif; ?>
</section>

<p style="margin-top: 1rem;"><a href="/payments.php">← My orders</a></p>
<?php require_once __DIR__ . '/includes/web_footer.php';
