<?php

declare(strict_types=1);

/**
 * Add review: buyer only when transaction status = RELEASED; one per transaction. Score 1–5. CSRF on POST.
 */
require_once __DIR__ . '/../includes/web_bootstrap.php';

$pageTitle = 'Add review';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/review/add.php'));
    exit;
}

$transactionUuid = trim((string) ($_GET['transaction_uuid'] ?? $_POST['transaction_uuid'] ?? ''));
if ($transactionUuid === '') {
    header('Location: /payments.php');
    exit;
}

$stmt = $pdo->prepare('SELECT t.uuid, t.store_uuid, t.buyer_uuid, v.current_status FROM transactions t JOIN v_current_cumulative_transaction_statuses v ON v.uuid = t.uuid WHERE t.uuid = ?');
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

$isBuyer = ($tx['buyer_uuid'] ?? '') === $currentUser['uuid'];
$isReleased = ($tx['current_status'] ?? '') === 'RELEASED';
if (!$isBuyer || !$isReleased) {
    http_response_code(403);
    $pageTitle = 'Forbidden';
    require_once __DIR__ . '/../includes/web_header.php';
    echo '<p>Only the buyer can add a review after the order is released.</p>';
    require_once __DIR__ . '/../includes/web_footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT 1 FROM reviews WHERE transaction_uuid = ? LIMIT 1');
$stmt->execute([$transactionUuid]);
if ($stmt->fetch()) {
    header('Location: /payment.php?uuid=' . urlencode($transactionUuid));
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request. Please try again.';
    } else {
        $score = (int) ($_POST['score'] ?? 0);
        $comment = trim((string) ($_POST['comment'] ?? ''));
        if ($score < 1 || $score > 5) {
            $error = 'Score must be between 1 and 5.';
        } else {
            $storeUuid = $tx['store_uuid'];
            $raterUuid = $currentUser['uuid'];
            $now = date('Y-m-d H:i:s');
            try {
                $pdo->prepare('INSERT INTO reviews (transaction_uuid, store_uuid, rater_user_uuid, score, comment, created_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([$transactionUuid, $storeUuid, $raterUuid, $score, $comment, $now]);
                $success = 'Review submitted.';
                header('Location: /payment.php?uuid=' . urlencode($transactionUuid) . '&reviewed=1');
                exit;
            } catch (\PDOException $e) {
                if (strpos($e->getMessage(), 'UNIQUE') !== false || strpos($e->getMessage(), 'unique') !== false) {
                    $error = 'You have already reviewed this order.';
                } else {
                    $error = 'Could not save review. Please try again.';
                }
            }
        }
    }
}

$csrf = $session->getCsrfToken();
require_once __DIR__ . '/../includes/web_header.php';
?>
<h1>Add review</h1>
<p>Order: <a href="/payment.php?uuid=<?= urlencode($transactionUuid) ?>"><?= htmlspecialchars(substr($transactionUuid, 0, 8)) ?>…</a></p>
<?php if ($error): ?><p class="alert alert-warning"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="transaction_uuid" value="<?= htmlspecialchars($transactionUuid) ?>">
    <p>
        <label>Score (1–5) <select name="score" required>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3" selected>3</option>
            <option value="4">4</option>
            <option value="5">5</option>
        </select></label>
    </p>
    <p>
        <label>Comment (optional) <textarea name="comment" rows="4" maxlength="5000"><?= htmlspecialchars($_POST['comment'] ?? '') ?></textarea></label>
    </p>
    <p><button type="submit">Submit review</button></p>
</form>
<p style="margin-top: 1rem;"><a href="/payment.php?uuid=<?= urlencode($transactionUuid) ?>" class="btn">← Back to order</a></p>
<?php require_once __DIR__ . '/../includes/web_footer.php';
