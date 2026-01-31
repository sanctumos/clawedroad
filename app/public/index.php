<?php

declare(strict_types=1);

/**
 * Web entry point. Document root = public/ (only public/ and db/ sync to LEMP).
 */
$baseDir = dirname(__DIR__);
$inc = __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR;
require $inc . 'Env.php';
require $inc . 'Db.php';
require $inc . 'Session.php';
require $inc . 'User.php';
require $inc . 'Router.php';
require $inc . 'ApiKey.php';

Env::load($baseDir);
Db::init($baseDir);

$pdo = Db::pdo();
$session = new Session($baseDir);
$userRepo = new User($pdo);
$apiKeyRepo = new ApiKey($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$router = new Router('', $method);

function getApiKeyFromRequest(): ?string {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', trim($auth), $m)) {
        return trim($m[1]);
    }
    $key = $_SERVER['HTTP_X_API_KEY'] ?? null;
    if ($key !== null && $key !== '') {
        return $key;
    }
    return isset($_GET['token']) ? (string) $_GET['token'] : null;
}

$router->get('/', function () {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'OK';
});

$router->get('/login', function () use ($session) {
    $session->start();
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body><form method="post" action="/login">';
    echo 'Username: <input name="username" type="text"><br>';
    echo 'Password: <input name="password" type="password"><br>';
    echo '<button type="submit">Login</button></form></body></html>';
});

$router->post('/login', function () use ($session, $userRepo) {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        http_response_code(400);
        echo 'Missing username or password';
        return;
    }
    $user = $userRepo->verifyPassword($username, $password);
    if ($user === null) {
        http_response_code(401);
        echo 'Invalid credentials';
        return;
    }
    $session->setUser($user);
    $userRepo->updateLastLogin($user['uuid']);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Logged in as ' . $user['username'];
});

$router->get('/register', function () {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body><form method="post" action="/register">';
    echo 'Username: <input name="username" type="text"><br>';
    echo 'Password: <input name="password" type="password"><br>';
    echo '<button type="submit">Register</button></form></body></html>';
});

$router->post('/register', function () use ($session, $userRepo, $pdo) {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($username === '' || strlen($username) > 16) {
        http_response_code(400);
        echo 'Invalid username';
        return;
    }
    if (strlen($password) < 8) {
        http_response_code(400);
        echo 'Password must be at least 8 characters';
        return;
    }
    if ($userRepo->findByUsername($username) !== null) {
        http_response_code(409);
        echo 'Username taken';
        return;
    }
    $uuid = User::generateUuid();
    try {
        $user = $userRepo->create($uuid, $username, $password, User::ROLE_CUSTOMER, null);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo 'Registration failed';
        return;
    }
    if ($user !== null) {
        $session->setUser($user);
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Registered as ' . $username;
});

$router->get('/logout', function () use ($session) {
    $session->destroy();
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Logged out';
});

$router->get('/api/auth/user', function () use ($apiKeyRepo) {
    $key = getApiKeyFromRequest();
    if ($key === null) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Missing API key']);
        return;
    }
    $user = $apiKeyRepo->validate($key);
    if ($user === null) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid API key']);
        return;
    }
    if (!$apiKeyRepo->checkRateLimit((int) $user['api_key_id'])) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Rate limit exceeded']);
        return;
    }
    $apiKeyRepo->recordRequest((int) $user['api_key_id']);
    header('Content-Type: application/json');
    echo json_encode(['username' => $user['username'], 'role' => $user['role'], 'user_uuid' => $user['user_uuid']]);
});

$router->post('/api/keys', function () use ($session, $apiKeyRepo) {
    $session->start();
    $user = $session->getUser();
    if ($user === null) {
        http_response_code(401);
        echo 'Login required';
        return;
    }
    $name = trim((string) ($_POST['name'] ?? ''));
    $data = $apiKeyRepo->create($user['uuid'], $name);
    header('Content-Type: application/json');
    echo json_encode(['id' => $data['id'], 'name' => $data['name'], 'key_prefix' => $data['key_prefix'], 'api_key' => $data['api_key'], 'created_at' => $data['created_at']]);
});

$router->get('/api/keys', function () use ($session, $apiKeyRepo) {
    $session->start();
    $user = $session->getUser();
    if ($user === null) {
        http_response_code(401);
        echo 'Login required';
        return;
    }
    $list = $apiKeyRepo->listForUser($user['uuid']);
    header('Content-Type: application/json');
    echo json_encode(['keys' => $list]);
});

$router->post('/api/keys/revoke', function () use ($session, $apiKeyRepo) {
    $session->start();
    $user = $session->getUser();
    if ($user === null) {
        http_response_code(401);
        echo 'Login required';
        return;
    }
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo 'Invalid key id';
        return;
    }
    if ($apiKeyRepo->revoke($id, $user['uuid'])) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    } else {
        http_response_code(404);
        echo 'Key not found';
    }
});

require $inc . 'Config.php';
$config = new Config($pdo);

$router->get('/admin/config', function () use ($session, $config) {
    $session->start();
    $user = $session->getUser();
    if ($user === null || ($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo 'Admin only';
        return;
    }
    $keys = ['pending_duration', 'completed_duration', 'stuck_duration', 'completion_tolerance', 'partial_refund_resolver_percent', 'gold_account_commission', 'silver_account_commission', 'bronze_account_commission', 'free_account_commission'];
    $out = [];
    foreach ($keys as $k) {
        $out[$k] = $config->get($k);
    }
    header('Content-Type: application/json');
    echo json_encode($out);
});

$router->post('/admin/config', function () use ($session, $config) {
    $session->start();
    $user = $session->getUser();
    if ($user === null || ($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo 'Admin only';
        return;
    }
    $allowed = ['pending_duration', 'completed_duration', 'stuck_duration', 'completion_tolerance', 'partial_refund_resolver_percent', 'gold_account_commission', 'silver_account_commission', 'bronze_account_commission', 'free_account_commission'];
    foreach ($allowed as $k) {
        if (isset($_POST[$k])) {
            $config->set($k, (string) $_POST[$k]);
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
});

$router->get('/admin/tokens', function () use ($session, $pdo) {
    $session->start();
    $user = $session->getUser();
    if ($user === null || ($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo 'Admin only';
        return;
    }
    $stmt = $pdo->query('SELECT id, chain_id, symbol, contract_address, created_at FROM accepted_tokens ORDER BY chain_id, symbol');
    header('Content-Type: application/json');
    echo json_encode(['tokens' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
});

$router->post('/admin/tokens', function () use ($session, $pdo) {
    $session->start();
    $user = $session->getUser();
    if ($user === null || ($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo 'Admin only';
        return;
    }
    $chainId = (int) ($_POST['chain_id'] ?? 0);
    $symbol = trim((string) ($_POST['symbol'] ?? ''));
    $contractAddress = trim((string) ($_POST['contract_address'] ?? ''));
    if ($chainId <= 0 || $symbol === '') {
        http_response_code(400);
        echo 'chain_id and symbol required';
        return;
    }
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare('INSERT INTO accepted_tokens (chain_id, symbol, contract_address, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$chainId, $symbol, $contractAddress ?: null, $now]);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
});

$router->post('/admin/tokens/remove', function () use ($session, $pdo) {
    $session->start();
    $user = $session->getUser();
    if ($user === null || ($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo 'Admin only';
        return;
    }
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo 'Invalid id';
        return;
    }
    $pdo->prepare('DELETE FROM accepted_tokens WHERE id = ?')->execute([$id]);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
});

require $inc . 'StatusMachine.php';
$statusMachine = new StatusMachine($pdo);

$router->get('/api/stores', function () use ($pdo) {
    $stmt = $pdo->query('SELECT uuid, storename, description, vendorship_agreed_at, created_at FROM stores WHERE deleted_at IS NULL ORDER BY storename');
    header('Content-Type: application/json');
    echo json_encode(['stores' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
});

$router->post('/api/stores', function () use ($session, $pdo, $userRepo) {
    $session->start();
    $user = $session->getUser();
    if ($user === null) {
        http_response_code(401);
        echo 'Login required';
        return;
    }
    $storename = trim((string) ($_POST['storename'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $agree = isset($_POST['vendorship_agree']) && $_POST['vendorship_agree'] === '1';
    if ($storename === '' || strlen($storename) > 16) {
        http_response_code(400);
        echo 'Invalid storename';
        return;
    }
    $uuid = $userRepo->generateUuid();
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare('INSERT INTO stores (uuid, storename, description, vendorship_agreed_at, is_free, created_at) VALUES (?, ?, ?, ?, 1, ?)');
    $stmt->execute([$uuid, $storename, $description, $agree ? $now : null, $now]);
    $pdo->prepare('INSERT INTO store_users (store_uuid, user_uuid, role) VALUES (?, ?, ?)')->execute([$uuid, $user['uuid'], 'owner']);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'uuid' => $uuid]);
});

$router->get('/api/items', function () use ($pdo) {
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
    header('Content-Type: application/json');
    echo json_encode(['items' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
});

$router->post('/api/items', function () use ($session, $pdo, $userRepo) {
    $session->start();
    $user = $session->getUser();
    if ($user === null) {
        http_response_code(401);
        echo 'Login required';
        return;
    }
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $storeUuid = trim((string) ($_POST['store_uuid'] ?? ''));
    if ($name === '' || $storeUuid === '') {
        http_response_code(400);
        echo 'name and store_uuid required';
        return;
    }
    $uuid = $userRepo->generateUuid();
    $now = date('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO items (uuid, name, description, store_uuid, created_at) VALUES (?, ?, ?, ?, ?)')->execute([$uuid, $name, $description, $storeUuid, $now]);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'uuid' => $uuid]);
});

$router->get('/api/transactions', function () use ($session, $pdo, $apiKeyRepo) {
    $key = getApiKeyFromRequest();
    if ($key === null) {
        $session->start();
        $user = $session->getUser();
        if ($user === null) {
            http_response_code(401);
            echo 'API key or login required';
            return;
        }
    } else {
        $user = $apiKeyRepo->validate($key);
        if ($user === null) {
            http_response_code(401);
            echo 'Invalid API key';
            return;
        }
        if (!$apiKeyRepo->checkRateLimit((int) $user['api_key_id'])) {
            http_response_code(429);
            echo 'Rate limit exceeded';
            return;
        }
        $apiKeyRepo->recordRequest((int) $user['api_key_id']);
    }
    $stmt = $pdo->query('SELECT * FROM v_current_cumulative_transaction_statuses LIMIT 100');
    header('Content-Type: application/json');
    echo json_encode(['transactions' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
});

$router->post('/api/transactions', function () use ($session, $pdo, $userRepo, $statusMachine) {
    $session->start();
    $user = $session->getUser();
    if ($user === null) {
        http_response_code(401);
        echo 'Login required';
        return;
    }
    $packageUuid = trim((string) ($_POST['package_uuid'] ?? ''));
    $refundAddress = trim((string) ($_POST['refund_address'] ?? ''));
    $requiredAmount = (float) ($_POST['required_amount'] ?? 0);
    $chainId = (int) ($_POST['chain_id'] ?? 1);
    $currency = trim((string) ($_POST['currency'] ?? 'ETH'));
    if ($packageUuid === '') {
        http_response_code(400);
        echo 'package_uuid required';
        return;
    }
    $store = $pdo->prepare('SELECT store_uuid FROM packages WHERE uuid = ? AND deleted_at IS NULL');
    $store->execute([$packageUuid]);
    $storeRow = $store->fetch(\PDO::FETCH_ASSOC);
    if (!$storeRow) {
        http_response_code(404);
        echo 'Package not found';
        return;
    }
    $txUuid = $userRepo->generateUuid();
    $now = date('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO transactions (uuid, type, description, package_uuid, store_uuid, buyer_uuid, refund_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$txUuid, 'evm', '', $packageUuid, $storeRow['store_uuid'], $user['uuid'], $refundAddress ?: null, $now]);
    $pdo->prepare('INSERT INTO evm_transactions (uuid, amount, chain_id, currency, created_at) VALUES (?, ?, ?, ?, ?)')->execute([$txUuid, $requiredAmount, $chainId, $currency, $now]);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'uuid' => $txUuid, 'escrow_address_pending' => true]);
});

$router->get('/api/deposits', function () use ($session, $pdo) {
    $session->start();
    $user = $session->getUser();
    if ($user === null) {
        http_response_code(401);
        echo 'Login required';
        return;
    }
    $stmt = $pdo->prepare('SELECT d.* FROM deposits d JOIN store_users su ON d.store_uuid = su.store_uuid WHERE su.user_uuid = ? AND d.deleted_at IS NULL');
    $stmt->execute([$user['uuid']]);
    header('Content-Type: application/json');
    echo json_encode(['deposits' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
});

$router->get('/api/disputes', function () use ($session, $pdo) {
    $session->start();
    $user = $session->getUser();
    if ($user === null) {
        http_response_code(401);
        echo 'Login required';
        return;
    }
    $stmt = $pdo->query('SELECT uuid, status, resolver_user_uuid, created_at FROM disputes WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 50');
    header('Content-Type: application/json');
    echo json_encode(['disputes' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
});

$router->run();
