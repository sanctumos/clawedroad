<?php

declare(strict_types=1);

/**
 * GET /api/auth-user.php — Return current user for API key. LEMP: one script per endpoint.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/api_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$user = requireAgentOrApiKey($agentIdentity, $apiKeyRepo, $pdo, $hooks);
// All auth functions now return 'uuid' consistently
echo json_encode(['username' => $user['username'], 'role' => $user['role'], 'user_uuid' => $user['uuid']]);
