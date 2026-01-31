<?php

declare(strict_types=1);

/**
 * GET /login.php — Login form. POST /login.php — Submit login. LEMP: one script per page.
 */
require_once __DIR__ . '/includes/web_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $pageTitle = 'Login';
        $redirectParam = trim((string) ($_POST['redirect'] ?? ''));
        $redirectParam = ($redirectParam !== '' && $redirectParam[0] === '/' && strpos($redirectParam, '//') === false) ? $redirectParam : '';
        require_once __DIR__ . '/includes/web_header.php';
        echo '<p class="alert alert-warning">Missing username or password.</p>';
        include __DIR__ . '/includes/form_login.php';
        require_once __DIR__ . '/includes/web_footer.php';
        return;
    }
    $user = $userRepo->verifyPassword($username, $password);
    if ($user === null) {
        $pageTitle = 'Login';
        $redirectParam = trim((string) ($_POST['redirect'] ?? ''));
        $redirectParam = ($redirectParam !== '' && $redirectParam[0] === '/' && strpos($redirectParam, '//') === false) ? $redirectParam : '';
        require_once __DIR__ . '/includes/web_header.php';
        echo '<p class="alert alert-warning">Invalid credentials.</p>';
        include __DIR__ . '/includes/form_login.php';
        require_once __DIR__ . '/includes/web_footer.php';
        return;
    }
    $session->start();
    $session->setUser($user);
    $userRepo->updateLastLogin($user['uuid']);
    $redirect = trim((string) ($_POST['redirect'] ?? $_GET['redirect'] ?? ''));
    if ($redirect !== '' && $redirect[0] === '/' && strpos($redirect, '//') === false) {
        header('Location: ' . $redirect, true, 302);
        return;
    }
    header('Location: /marketplace.php', true, 302);
    return;
}

$pageTitle = 'Login';
$redirect = trim((string) ($_GET['redirect'] ?? ''));
$redirectParam = ($redirect !== '' && $redirect[0] === '/' && strpos($redirect, '//') === false) ? $redirect : '';
require_once __DIR__ . '/includes/web_header.php';
include __DIR__ . '/includes/form_login.php';
require_once __DIR__ . '/includes/web_footer.php';
