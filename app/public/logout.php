<?php

declare(strict_types=1);

/**
 * GET /logout.php — Destroy session and redirect. LEMP: one script per page.
 */
$baseDir = dirname(__DIR__);
$inc = __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR;
require $inc . 'Env.php';
require $inc . 'Db.php';
require $inc . 'Session.php';

Env::load($baseDir);
Db::init($baseDir);

$session = new Session($baseDir);
$session->destroy();

header('Location: /marketplace.php', true, 302);
exit;
