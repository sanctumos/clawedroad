<?php

declare(strict_types=1);

/**
 * GET /api/stores.php — List stores (public).
 * POST /api/stores.php — Create store (session).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/api_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query('SELECT uuid, storename, description, vendorship_agreed_at, created_at FROM stores WHERE deleted_at IS NULL ORDER BY storename');
    echo json_encode(['stores' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = requireSession($session);
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF token required']);
        exit;
    }
    $storename = trim((string) ($_POST['storename'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $agree = isset($_POST['vendorship_agree']) && $_POST['vendorship_agree'] === '1';
    if ($storename === '' || strlen($storename) > 16) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid storename']);
        exit;
    }
    $uuid = User::generateUuid();
    $now = date('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO stores (uuid, storename, description, vendorship_agreed_at, is_free, created_at) VALUES (?, ?, ?, ?, 1, ?)')->execute([$uuid, $storename, $description, $agree ? $now : null, $now]);
    $pdo->prepare('INSERT INTO store_users (store_uuid, user_uuid, role) VALUES (?, ?, ?)')->execute([$uuid, $user['uuid'], 'owner']);
    echo json_encode(['ok' => true, 'uuid' => $uuid]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
