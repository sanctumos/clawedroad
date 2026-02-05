<?php

declare(strict_types=1);

/**
 * GET /api/transactions.php — List transactions (API key or session).
 * POST /api/transactions.php — Create transaction (session).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/api_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = requireAgentOrApiKey($agentIdentity, $apiKeyRepo, $pdo, $hooks);
    $userUuid = $user['uuid'];

    // Get stores the user belongs to
    $storeStmt = $pdo->prepare('SELECT store_uuid FROM store_users WHERE user_uuid = ?');
    $storeStmt->execute([$userUuid]);
    $userStoreUuids = array_column($storeStmt->fetchAll(\PDO::FETCH_ASSOC), 'store_uuid');

    // Query transactions where user is buyer OR store is user's store
    $params = [$userUuid];
    $storeFilter = '';
    if (!empty($userStoreUuids)) {
        $placeholders = implode(',', array_fill(0, count($userStoreUuids), '?'));
        $storeFilter = " OR store_uuid IN ($placeholders)";
        $params = array_merge($params, $userStoreUuids);
    }

    $stmt = $pdo->prepare("SELECT * FROM v_current_cumulative_transaction_statuses WHERE buyer_uuid = ?$storeFilter ORDER BY updated_at DESC LIMIT 100");
    $stmt->execute($params);
    echo json_encode(['transactions' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = requireSession($session);
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF token required']);
        exit;
    }
    $packageUuid = trim((string) ($_POST['package_uuid'] ?? ''));
    $refundAddress = trim((string) ($_POST['refund_address'] ?? ''));
    $requiredAmount = (float) ($_POST['required_amount'] ?? 0);
    $chainId = (int) ($_POST['chain_id'] ?? 1);
    $currency = trim((string) ($_POST['currency'] ?? 'ETH'));
    if ($packageUuid === '') {
        http_response_code(400);
        echo json_encode(['error' => 'package_uuid required']);
        exit;
    }
    $storeStmt = $pdo->prepare('SELECT store_uuid FROM packages WHERE uuid = ? AND deleted_at IS NULL');
    $storeStmt->execute([$packageUuid]);
    $storeRow = $storeStmt->fetch(\PDO::FETCH_ASSOC);
    if (!$storeRow) {
        http_response_code(404);
        echo json_encode(['error' => 'Package not found']);
        exit;
    }
    $txUuid = User::generateUuid();
    $now = date('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO transactions (uuid, type, description, package_uuid, store_uuid, buyer_uuid, refund_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$txUuid, 'evm', '', $packageUuid, $storeRow['store_uuid'], $user['uuid'], $refundAddress ?: null, $now]);
    $pdo->prepare('INSERT INTO evm_transactions (uuid, amount, chain_id, currency, created_at) VALUES (?, ?, ?, ?, ?)')->execute([$txUuid, $requiredAmount, $chainId, $currency, $now]);
    $agentStmt = $pdo->prepare('SELECT agent_id FROM agent_identities WHERE user_uuid = ? LIMIT 1');
    $agentStmt->execute([$user['uuid']]);
    $agentRow = $agentStmt->fetch(\PDO::FETCH_ASSOC);
    if ($agentRow && !empty($agentRow['agent_id'])) {
        $hooks->fire('transaction_created_by_agent', ['transaction_uuid' => $txUuid, 'agent_id' => $agentRow['agent_id']]);
    }
    echo json_encode(['ok' => true, 'uuid' => $txUuid, 'escrow_address_pending' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
