<?php

declare(strict_types=1);

/**
 * Create store (become a vendor). Session required. LEMP: one script per page.
 */
require_once __DIR__ . '/includes/web_bootstrap.php';

$pageTitle = 'Create store';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/create-store.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $pageTitle = 'Create store';
        $error = 'Invalid request. Please try again.';
        require_once __DIR__ . '/includes/web_header.php';
        echo '<p class="alert alert-warning">' . htmlspecialchars($error) . '</p>';
        include __DIR__ . '/includes/form_create_store.php';
        require_once __DIR__ . '/includes/web_footer.php';
        return;
    }
    $storename = trim((string) ($_POST['storename'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $agree = isset($_POST['vendorship_agree']) && $_POST['vendorship_agree'] === '1';
    if ($storename === '' || strlen($storename) > 16) {
        $pageTitle = 'Create store';
        $error = 'Store name must be 1–16 characters';
        require_once __DIR__ . '/includes/web_header.php';
        echo '<p class="alert alert-warning">' . htmlspecialchars($error) . '</p>';
        include __DIR__ . '/includes/form_create_store.php';
        require_once __DIR__ . '/includes/web_footer.php';
        return;
    }
    $uuid = User::generateUuid();
    $now = date('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO stores (uuid, storename, description, vendorship_agreed_at, is_free, created_at) VALUES (?, ?, ?, ?, 1, ?)')->execute([$uuid, $storename, $description, $agree ? $now : null, $now]);
    $pdo->prepare('INSERT INTO store_users (store_uuid, user_uuid, role) VALUES (?, ?, ?)')->execute([$uuid, $currentUser['uuid'], 'owner']);
    header('Location: /store.php?uuid=' . urlencode($uuid), true, 302);
    exit;
}

require_once __DIR__ . '/includes/web_header.php';
include __DIR__ . '/includes/form_create_store.php';
require_once __DIR__ . '/includes/web_footer.php';
