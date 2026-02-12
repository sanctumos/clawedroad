<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap: sets up test .env in app/, loads classes, initializes test DB.
 * Uses app/ as base so E2E includes resolve correctly. Overwrites app/.env for test run.
 */
$appDir = dirname(__DIR__);
define('TEST_BASE_DIR', $appDir);

// Use a shared session save path for E2E tests to work properly across subprocesses
$testSessionPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpunit_e2e_sessions';
if (!is_dir($testSessionPath)) {
    mkdir($testSessionPath, 0755, true);
}
ini_set('session.save_path', $testSessionPath);
// Clean up old sessions from previous test runs at bootstrap
$oldSessions = glob($testSessionPath . DIRECTORY_SEPARATOR . 'sess_*');
if (is_array($oldSessions)) {
    foreach ($oldSessions as $file) {
        @unlink($file);
    }
}

// Backup existing .env and write test .env (restored in shutdown)
$envPath = $appDir . DIRECTORY_SEPARATOR . '.env';
$envBackup = $envPath . '.backup.' . getmypid();
if (file_exists($envPath)) {
    copy($envPath, $envBackup);
}
$testDbPath = $appDir . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'test.sqlite';
$testDbDir = dirname($testDbPath);
if (!is_dir($testDbDir)) {
    mkdir($testDbDir, 0755, true);
}
$envContent = <<<ENV
DB_DRIVER=sqlite
DB_DSN=sqlite:db/test.sqlite
SITE_URL=http://test.example.com
SITE_NAME=Test Marketplace
SESSION_SALT=test-session-salt
COOKIE_ENCRYPTION_SALT=test-cookie-salt
CSRF_SALT=test-csrf-salt
ADMIN_USERNAME=admin
ADMIN_PASSWORD=admin
AGENT_IDENTITY_VERIFY_URL=test
ENV;
file_put_contents($envPath, $envContent);
register_shutdown_function(static function () use ($envPath, $envBackup): void {
    if (file_exists($envBackup)) {
        copy($envBackup, $envPath);
        unlink($envBackup);
    }
});

$inc = $appDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR;

// Load app bootstrap first (loads Env, Db, Session, User, ApiKey, Config and inits with app/ baseDir)
require $inc . 'bootstrap.php';
// Load remaining classes not loaded by bootstrap
require_once $inc . 'Config.php';
require_once $inc . 'Schema.php';
require_once $inc . 'Views.php';
require_once $inc . 'StatusMachine.php';
require_once $inc . 'api_helpers.php';
require_once $inc . 'SkillGenerator.php';

// Run schema and views on test DB (bootstrap already inited with app/ and our test .env)
$pdo = Db::pdo();
$schema = new Schema($pdo, true);
$schema->run();
$views = new Views($pdo, true);
$views->run();
$config = new Config($pdo);
$config->seedDefaults();
$adminUsername = Env::get('ADMIN_USERNAME');
$adminPassword = Env::get('ADMIN_PASSWORD') ?? 'admin';
if ($adminUsername !== null && $adminUsername !== '') {
    $userRepo = new User($pdo);
    $existing = $userRepo->findByUsername($adminUsername);
    if ($existing === null) {
        $userRepo->create(User::generateUuid(), $adminUsername, $adminPassword, User::ROLE_ADMIN, null);
    } else {
        $pdo->prepare('UPDATE users SET role = ? WHERE uuid = ?')->execute([User::ROLE_ADMIN, $existing['uuid']]);
        if ($adminPassword !== '') {
            $hash = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare('UPDATE users SET passphrase_hash = ? WHERE uuid = ?')->execute([$hash, $existing['uuid']]);
        }
    }
    // E2E: seed a non-admin customer for tests that need "logged-in customer gets 403 on staff/admin"
    $customerUsername = 'e2e_customer';
    $customerPassword = 'password123';
    $cust = $userRepo->findByUsername($customerUsername);
    if ($cust === null) {
        $userRepo->create(User::generateUuid(), $customerUsername, $customerPassword, 'customer', null);
    } else {
        $hash = password_hash($customerPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare('UPDATE users SET passphrase_hash = ?, role = ? WHERE uuid = ?')->execute([$hash, 'customer', $cust['uuid']]);
    }
    $cust = $userRepo->findByUsername($customerUsername);
    if ($cust !== null && !defined('E2E_PACKAGE_UUID')) {
        // E2E: one store, one item, one package for transaction/book flow tests (issue #22)
        $storeUuid = $pdo->query("SELECT store_uuid FROM store_users WHERE user_uuid = " . $pdo->quote($cust['uuid']) . " LIMIT 1")->fetchColumn();
        if ($storeUuid === false) {
            $storeUuid = User::generateUuid();
            $now = date('Y-m-d H:i:s');
            // storename has a DB CHECK constraint: 1..16 chars (see Schema.php).
            // Keep this deterministic-length to avoid test bootstrap failures.
            $storename = 'e2e' . substr(bin2hex(random_bytes(8)), 0, 13); // 3 + 13 = 16 chars
            $pdo->prepare('INSERT INTO stores (uuid, storename, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')->execute([$storeUuid, $storename, 'E2E store', $now, $now]);
            $pdo->prepare('INSERT INTO store_users (store_uuid, user_uuid, role) VALUES (?, ?, ?)')->execute([$storeUuid, $cust['uuid'], 'owner']);
        }
        $packageUuid = $pdo->query("SELECT uuid FROM packages WHERE store_uuid = " . $pdo->quote($storeUuid) . " AND (deleted_at IS NULL OR deleted_at = '') LIMIT 1")->fetchColumn();
        if ($packageUuid === false) {
            $now = date('Y-m-d H:i:s');
            $itemUuid = User::generateUuid();
            $pdo->prepare('INSERT INTO items (uuid, name, description, store_uuid, created_at) VALUES (?, ?, ?, ?, ?)')->execute([$itemUuid, 'E2E Item', 'For E2E transaction tests', $storeUuid, $now]);
            $packageUuid = User::generateUuid();
            $pdo->prepare('INSERT INTO packages (uuid, item_uuid, store_uuid, name, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([$packageUuid, $itemUuid, $storeUuid, 'E2E Package', $now, $now]);
        }
        define('E2E_PACKAGE_UUID', $packageUuid);
    }
}
