<?php

declare(strict_types=1);

/**
 * POST /api/keys-revoke.php — Revoke an API key (session).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/api_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$user = requireSession($session);
if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token required']);
    exit;
}
$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid key id']);
    exit;
}

if ($apiKeyRepo->revoke($id, $user['uuid'])) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Key not found']);
}
