<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

abstract class E2ETestCase extends TestCase
{
    protected static function runRequest(array $request): array
    {
        $tmpDir = sys_get_temp_dir();
        $responseFile = $tmpDir . DIRECTORY_SEPARATOR . 'marketplace_e2e_resp_' . getmypid() . '_' . uniqid('', true) . '.json';
        $requestFile = $tmpDir . DIRECTORY_SEPARATOR . 'marketplace_e2e_req_' . getmypid() . '_' . uniqid('', true) . '.json';
        // Ensure child process uses same app dir for .env (test DB)
        if (!isset($request['app_dir']) && defined('TEST_BASE_DIR')) {
            $absAppDir = realpath(TEST_BASE_DIR);
            if ($absAppDir !== false) {
                $request['app_dir'] = $absAppDir;
            }
        }
        file_put_contents($requestFile, json_encode($request));
        $php = PHP_BINARY;
        $runner = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'run_request.php';
        $cmd = sprintf('"%s" %s %s %s', $php, escapeshellarg($runner), escapeshellarg($responseFile), escapeshellarg($requestFile));
        exec($cmd);
        $content = @file_get_contents($responseFile);
        @unlink($requestFile);
        @unlink($responseFile);
        return is_string($content) ? (json_decode($content, true) ?? ['code' => 0, 'body' => '', 'headers' => []]) : ['code' => 0, 'body' => '', 'headers' => []];
    }

    /** Parse Set-Cookie from response headers. Headers are from headers_list() format: ["Name: Value", ...]. Returns [cookie_name => cookie_value]. */
    protected static function parseCookiesFromResponse(array $res): array
    {
        $headers = $res['headers'] ?? [];
        $cookies = [];
        foreach ($headers as $h) {
            if (stripos($h, 'Set-Cookie:') === 0) {
                $val = trim(substr($h, 11));
                $eq = strpos($val, '=');
                if ($eq !== false) {
                    $name = trim(substr($val, 0, $eq));
                    $rest = substr($val, $eq + 1);
                    $semi = strpos($rest, ';');
                    $cookieVal = $semi !== false ? trim(substr($rest, 0, $semi)) : trim($rest);
                    $cookies[$name] = $cookieVal;
                }
            }
        }
        return $cookies;
    }

    /** Extract CSRF token from HTML body (form input or first 64-char hex value). */
    protected static function extractCsrfFromBody(string $body): string
    {
        if (preg_match('/name=["\']?csrf_token["\']?\s+value=["\']([^"\']+)["\']/', $body, $m)) {
            return $m[1];
        }
        if (preg_match('/value=["\']([a-f0-9]{64})["\']/', $body, $m)) {
            return $m[1];
        }
        return '';
    }

    /** POST login.php with credentials; returns cookies array for use in runRequest(['cookies' => ...]). Returns [] if login did not redirect (302). */
    protected static function loginAs(string $username, string $password): array
    {
        $res = self::runRequest([
            'method' => 'POST',
            'uri' => 'login.php',
            'get' => [],
            'post' => ['username' => $username, 'password' => $password],
            'headers' => [],
        ]);
        if ($res['code'] !== 302) {
            return [];
        }
        $cookies = self::parseCookiesFromResponse($res);
        if ($cookies !== []) {
            return $cookies;
        }
        if (isset($res['session_name'], $res['session_id']) && $res['session_name'] !== '' && $res['session_id'] !== '') {
            return [$res['session_name'] => $res['session_id']];
        }
        return [];
    }
}
