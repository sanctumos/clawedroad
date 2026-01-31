<?php

declare(strict_types=1);

/**
 * GET /api/items.php — List items (public). Optional ?store_uuid=
 * POST /api/items.php — Create item (session).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/api_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $storeUuid = $_GET['store_uuid'] ?? '';
    $sql = 'SELECT uuid, name, description, store_uuid, category_id, created_at FROM items WHERE deleted_at IS NULL';
    $params = [];
    if ($storeUuid !== '') {
        $sql .= ' AND store_uuid = ?';
        $params[] = $storeUuid;
    }
    $sql .= ' ORDER BY created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['items' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = requireSession($session);
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $storeUuid = trim((string) ($_POST['store_uuid'] ?? ''));
    if ($name === '' || $storeUuid === '') {
        http_response_code(400);
        echo json_encode(['error' => 'name and store_uuid required']);
        exit;
    }
    $uuid = User::generateUuid();
    $now = date('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO items (uuid, name, description, store_uuid, created_at) VALUES (?, ?, ?, ?, ?)')->execute([$uuid, $name, $description, $storeUuid, $now]);
    echo json_encode(['ok' => true, 'uuid' => $uuid]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
