<?php

declare(strict_types=1);

/**
 * GET /register.php — Registration form. POST /register.php — Submit registration. LEMP: one script per page.
 */
require_once __DIR__ . '/includes/web_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($username === '' || strlen($username) > 16) {
        $pageTitle = 'Register';
        $error = 'Invalid username';
        require_once __DIR__ . '/includes/web_header.php';
        echo '<p class="alert alert-warning">' . htmlspecialchars($error) . '</p>';
        include __DIR__ . '/includes/form_register.php';
        require_once __DIR__ . '/includes/web_footer.php';
        return;
    }
    if (strlen($password) < 8) {
        $pageTitle = 'Register';
        $error = 'Password must be at least 8 characters';
        require_once __DIR__ . '/includes/web_header.php';
        echo '<p class="alert alert-warning">' . htmlspecialchars($error) . '</p>';
        include __DIR__ . '/includes/form_register.php';
        require_once __DIR__ . '/includes/web_footer.php';
        return;
    }
    if ($userRepo->findByUsername($username) !== null) {
        $pageTitle = 'Register';
        $error = 'Username taken';
        require_once __DIR__ . '/includes/web_header.php';
        echo '<p class="alert alert-warning">' . htmlspecialchars($error) . '</p>';
        include __DIR__ . '/includes/form_register.php';
        require_once __DIR__ . '/includes/web_footer.php';
        return;
    }
    $uuid = User::generateUuid();
    try {
        $user = $userRepo->create($uuid, $username, $password, User::ROLE_CUSTOMER, null);
    } catch (\Throwable $e) {
        $pageTitle = 'Register';
        $error = 'Registration failed';
        require_once __DIR__ . '/includes/web_header.php';
        echo '<p class="alert alert-warning">' . htmlspecialchars($error) . '</p>';
        include __DIR__ . '/includes/form_register.php';
        require_once __DIR__ . '/includes/web_footer.php';
        return;
    }
    if ($user !== null) {
        $session->start();
        $session->setUser($user);
    }
    header('Location: /marketplace.php', true, 302);
    return;
}

$pageTitle = 'Register';
require_once __DIR__ . '/includes/web_header.php';
include __DIR__ . '/includes/form_register.php';
require_once __DIR__ . '/includes/web_footer.php';
