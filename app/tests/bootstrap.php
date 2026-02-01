<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap: sets up test .env in app/, loads classes, initializes test DB.
 * Uses app/ as base so E2E includes resolve correctly. Overwrites app/.env for test run.
 */
$appDir = dirname(__DIR__);
define('TEST_BASE_DIR', $appDir);

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
ENV;
file_put_contents($envPath, $envContent);
register_shutdown_function(static function () use ($envPath, $envBackup): void {
    if (file_exists($envBackup)) {
        copy($envBackup, $envPath);
        unlink($envBackup);
    }
});

$inc = $appDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR;

// Load app bootstrap first (loads Env, Db, Session, User, ApiKey and inits with app/ baseDir)
require $inc . 'bootstrap.php';
// Load remaining classes not loaded by bootstrap
require $inc . 'Config.php';
require $inc . 'Schema.php';
require $inc . 'Views.php';
require $inc . 'StatusMachine.php';
require $inc . 'api_helpers.php';

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
}
