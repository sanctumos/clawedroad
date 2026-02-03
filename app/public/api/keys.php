<?php

declare(strict_types=1);

/**
 * GET /api/keys.php — List API keys (session).
 * POST /api/keys.php — Create API key (session).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/api_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = requireSession($session);
    $list = $apiKeyRepo->listForUser($user['uuid']);
    echo json_encode(['keys' => $list]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = requireSession($session);
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF token required']);
        exit;
    }
    $name = trim((string) ($_POST['name'] ?? ''));
    $data = $apiKeyRepo->create($user['uuid'], $name);
    echo json_encode(['id' => $data['id'], 'name' => $data['name'], 'key_prefix' => $data['key_prefix'], 'api_key' => $data['api_key'], 'created_at' => $data['created_at']]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
