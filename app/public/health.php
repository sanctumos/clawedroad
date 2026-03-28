<?php

declare(strict_types=1);

/**
 * Liveness / health: plain text OK. Optional DB ping — 503 if DB unreachable.
 */
header('Content-Type: text/plain; charset=UTF-8');

try {
    require_once __DIR__ . '/includes/bootstrap.php';
    Db::pdo()->query('SELECT 1');
} catch (Throwable $e) {
    http_response_code(503);
    echo 'DB_UNAVAILABLE';
    exit;
}

http_response_code(200);
echo 'OK';
