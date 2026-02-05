<?php

declare(strict_types=1);

/**
 * Runs a single endpoint script with given request params. Writes response to file (for E2E tests).
 * Usage: php run_request.php <response_file> <request_file>
 * Request file contains JSON: {"method":"GET","uri":"api/stores.php","get":{},"post":{},"headers":{}}
 */
if (php_sapi_name() !== 'cli' || $argc < 3) {
    fwrite(STDERR, "Usage: php run_request.php <response_file> <request_file>\n");
    exit(1);
}

// Use a test-specific session directory to avoid race conditions in parallel tests
$testSessionPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpunit_e2e_sessions';
if (!is_dir($testSessionPath)) {
    @mkdir($testSessionPath, 0755, true);
}
ini_set('session.save_path', $testSessionPath);

$responseFile = $argv[1];
$requestFile = $argv[2];
$requestJson = file_get_contents($requestFile);
if ($requestJson === false) {
    fwrite(STDERR, "Cannot read request file\n");
    exit(1);
}
$request = json_decode($requestJson, true);
if (!is_array($request)) {
    fwrite(STDERR, "Invalid JSON\n");
    exit(1);
}

// So the app loads .env from the same path as the test runner (test DB)
if (!empty($request['app_dir'])) {
    putenv('MARKETPLACE_APP_DIR=' . $request['app_dir']);
    // Vary REMOTE_ADDR per request so login/recovery/registration rate limits don't block E2E (unless overridden)
    if (isset($request['remote_addr']) && $request['remote_addr'] !== '') {
        $_SERVER['REMOTE_ADDR'] = (string) $request['remote_addr'];
    } elseif (!isset($_SERVER['REMOTE_ADDR']) || $_SERVER['REMOTE_ADDR'] === '') {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.' . (abs(crc32($requestFile)) % 254 + 1);
    }
}

$_SERVER['REQUEST_METHOD'] = $request['method'] ?? 'GET';
$_GET = $request['get'] ?? [];
$_POST = $request['post'] ?? [];
$_SERVER['HTTP_AUTHORIZATION'] = $request['headers']['Authorization'] ?? $request['headers']['authorization'] ?? '';
$_SERVER['HTTP_X_API_KEY'] = $request['headers']['X-Api-Key'] ?? '';
$_SERVER['HTTP_X_AGENT_IDENTITY'] = $request['headers']['X-Agent-Identity'] ?? '';
$_COOKIE = isset($request['cookies']) && is_array($request['cookies']) ? $request['cookies'] : [];

$appDir = dirname(__DIR__);
chdir($appDir . DIRECTORY_SEPARATOR . 'public');

ob_start();

register_shutdown_function(static function () use ($responseFile): void {
    $body = ob_get_clean();
    if ($body === false) {
        $body = '';
    }
    $code = http_response_code();
    if ($code === false) {
        $code = 200;
    }
    $out = [
        'code' => $code,
        'body' => $body,
        'headers' => headers_list(),
    ];
    if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
        $out['session_name'] = session_name();
        $out['session_id'] = session_id();
    }
    file_put_contents($responseFile, json_encode($out));
});

$uri = $request['uri'] ?? 'index.php';
if (strpos($uri, '..') !== false) {
    file_put_contents($responseFile, json_encode(['code' => 400, 'body' => 'Invalid URI', 'headers' => []]));
    exit(0);
}

require $uri;
