<?php

declare(strict_types=1);

/**
 * GET /register.php — Registration form. POST — Submit registration. ?invite=CODE optional. CSRF required on POST.
 */
require_once __DIR__ . '/includes/web_bootstrap.php';

$inviteCode = trim((string) ($_GET['invite'] ?? $_POST['invite'] ?? ''));
$inviteRow = null;
if ($inviteCode !== '') {
    $stmt = $pdo->prepare('SELECT id, code, used_at FROM invite_codes WHERE code = ?');
    $stmt->execute([$inviteCode]);
    $inviteRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($inviteRow && $inviteRow['used_at'] !== null) {
        $inviteRow = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $pageTitle = 'Register';
        $error = 'Invalid request. Please try again.';
        require_once __DIR__ . '/includes/web_header.php';
        echo '<p class="alert alert-warning">' . htmlspecialchars($error) . '</p>';
        include __DIR__ . '/includes/form_register.php';
        require_once __DIR__ . '/includes/web_footer.php';
        return;
    }
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $postInvite = trim((string) ($_POST['invite'] ?? ''));
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
    $inviterUuid = null;
    if ($postInvite !== '') {
        $stmt = $pdo->prepare('SELECT id, created_by_user_uuid, used_at FROM invite_codes WHERE code = ?');
        $stmt->execute([$postInvite]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$inv || $inv['used_at'] !== null) {
            $pageTitle = 'Register';
            $error = 'Invalid or already used invite code';
            require_once __DIR__ . '/includes/web_header.php';
            echo '<p class="alert alert-warning">' . htmlspecialchars($error) . '</p>';
            include __DIR__ . '/includes/form_register.php';
            require_once __DIR__ . '/includes/web_footer.php';
            return;
        }
        $inviterUuid = $inv['created_by_user_uuid'];
    }
    $uuid = User::generateUuid();
    try {
        $user = $userRepo->create($uuid, $username, $password, User::ROLE_CUSTOMER, $inviterUuid);
    } catch (\Throwable $e) {
        $pageTitle = 'Register';
        $error = 'Registration failed';
        require_once __DIR__ . '/includes/web_header.php';
        echo '<p class="alert alert-warning">' . htmlspecialchars($error) . '</p>';
        include __DIR__ . '/includes/form_register.php';
        require_once __DIR__ . '/includes/web_footer.php';
        return;
    }
    if ($user !== null && $postInvite !== '') {
        $stmt = $pdo->prepare('UPDATE invite_codes SET used_by_user_uuid = ?, used_at = ? WHERE code = ?');
        $stmt->execute([$uuid, date('Y-m-d H:i:s'), $postInvite]);
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
