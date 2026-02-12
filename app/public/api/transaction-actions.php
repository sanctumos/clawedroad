<?php

declare(strict_types=1);

/**
 * POST /api/transaction-actions.php — Request transaction intents (release/cancel/partial_refund).
 * Auth: API key/agent identity OR session (+ CSRF).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/../includes/StatusMachine.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$authHeader = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
$apiKeyHeader = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
$agentHeader = trim((string) ($_SERVER['HTTP_X_AGENT_IDENTITY'] ?? ''));
$tokenQuery = trim((string) ($_GET['token'] ?? ''));
$usingApiAuth = ($authHeader !== '') || ($apiKeyHeader !== '') || ($agentHeader !== '') || ($tokenQuery !== '');

if ($usingApiAuth) {
    $user = requireAgentOrApiKey($agentIdentity, $apiKeyRepo, $pdo, $hooks);
} else {
    $user = requireSession($session);
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF token required']);
        exit;
    }
}

$transactionUuid = trim((string) ($_POST['transaction_uuid'] ?? ''));
$action = trim((string) ($_POST['action'] ?? ''));
if ($transactionUuid === '' || $action === '') {
    http_response_code(400);
    echo json_encode(['error' => 'transaction_uuid and action required']);
    exit;
}

$txStmt = $pdo->prepare('SELECT uuid, buyer_uuid, store_uuid, current_status, dispute_uuid FROM v_current_cumulative_transaction_statuses WHERE uuid = ?');
$txStmt->execute([$transactionUuid]);
$tx = $txStmt->fetch(\PDO::FETCH_ASSOC);
if (!$tx) {
    http_response_code(404);
    echo json_encode(['error' => 'Transaction not found']);
    exit;
}

$actorUuid = (string) ($user['uuid'] ?? '');
$isBuyer = ($tx['buyer_uuid'] ?? '') === $actorUuid;
$isVendor = false;
if (!$isBuyer) {
    $vendorStmt = $pdo->prepare('SELECT 1 FROM store_users WHERE store_uuid = ? AND user_uuid = ? LIMIT 1');
    $vendorStmt->execute([$tx['store_uuid'] ?? '', $actorUuid]);
    $isVendor = (bool) $vendorStmt->fetchColumn();
}
$isStaffOrAdmin = in_array((string) ($user['role'] ?? ''), [User::ROLE_STAFF, User::ROLE_ADMIN], true);

if (!$isBuyer && !$isVendor && !$isStaffOrAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$disputeStatus = 'NONE';
if (!empty($tx['dispute_uuid'])) {
    $disputeStmt = $pdo->prepare('SELECT status FROM disputes WHERE uuid = ? AND deleted_at IS NULL');
    $disputeStmt->execute([$tx['dispute_uuid']]);
    $dispute = $disputeStmt->fetch(\PDO::FETCH_ASSOC);
    $disputeStatus = ($dispute && strtolower((string) ($dispute['status'] ?? '')) === 'resolved') ? 'RESOLVED' : 'OPEN';
}

$paymentStatus = (string) ($tx['current_status'] ?? StatusMachine::STATUS_PENDING);
$releaseAllowed = ($paymentStatus === StatusMachine::STATUS_COMPLETED) && ($disputeStatus === 'NONE') && ($isVendor || $isStaffOrAdmin);
$cancelAllowed = ($paymentStatus === StatusMachine::STATUS_PENDING) && ($disputeStatus === 'NONE') && ($isBuyer || $isStaffOrAdmin);
$partialRefundAllowed = $isStaffOrAdmin && ($disputeStatus === 'OPEN');

$sm = new StatusMachine($pdo);

if ($action === 'release') {
    if (!$releaseAllowed) {
        http_response_code(403);
        echo json_encode(['error' => 'Action not allowed']);
        exit;
    }
    $sm->requestRelease($transactionUuid, $actorUuid);
    echo json_encode(['ok' => true, 'action' => 'release', 'transaction_uuid' => $transactionUuid, 'intent' => StatusMachine::INTENT_RELEASE]);
    exit;
}

if ($action === 'cancel') {
    if (!$cancelAllowed) {
        http_response_code(403);
        echo json_encode(['error' => 'Action not allowed']);
        exit;
    }
    $sm->requestCancel($transactionUuid, $actorUuid);
    echo json_encode(['ok' => true, 'action' => 'cancel', 'transaction_uuid' => $transactionUuid, 'intent' => StatusMachine::INTENT_CANCEL]);
    exit;
}

if ($action === 'partial_refund') {
    if (!$partialRefundAllowed) {
        http_response_code(403);
        echo json_encode(['error' => 'Action not allowed']);
        exit;
    }
    $refundPercent = (float) ($_POST['refund_percent'] ?? 0);
    if ($refundPercent <= 0 || $refundPercent > 100) {
        http_response_code(400);
        echo json_encode(['error' => 'refund_percent must be between 1 and 100']);
        exit;
    }
    $sm->requestPartialRefund($transactionUuid, $refundPercent, $actorUuid);
    echo json_encode([
        'ok' => true,
        'action' => 'partial_refund',
        'transaction_uuid' => $transactionUuid,
        'intent' => StatusMachine::INTENT_PARTIAL_REFUND,
        'refund_percent' => $refundPercent,
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
