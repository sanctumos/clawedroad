<?php

declare(strict_types=1);

/**
 * Shared header for marketplace web pages. Expects $pageTitle (string), $currentUser (array|null).
 */
$pageTitle = $pageTitle ?? 'Clawed Road';
$currentUser = $currentUser ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> — Clawed Road</title>
    <style>
        :root {
            --cr-header-bg: #1a0f12;
            --cr-header-text: #f0e6e2;
            --cr-header-link: #e8c4b8;
            --cr-header-link-hover: #d4624a;
            --cr-main-bg: #faf6f4;
            --cr-main-text: #1a0f12;
            --cr-link: #b85c38;
            --cr-link-hover: #8b4520;
            --cr-btn-bg: #b85c38;
            --cr-btn-hover: #9a4a28;
            --cr-border: #e8ddd8;
            --cr-meta: #5c4a45;
            --cr-alert-info-bg: #f5ebe8;
            --cr-alert-info-text: #5c3d38;
            --cr-alert-warning-bg: #fdf0e6;
            --cr-alert-warning-text: #7a5c38;
        }
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; margin: 0; padding: 0; line-height: 1.5; background: var(--cr-main-bg); color: var(--cr-main-text); }
        .header { background: var(--cr-header-bg); color: var(--cr-header-text); padding: 0.75rem 1.5rem; display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
        .header a { color: var(--cr-header-link); text-decoration: none; }
        .header a:hover { color: var(--cr-header-link-hover); text-decoration: underline; }
        .header .brand { font-weight: 700; margin-right: 1rem; display: inline-flex; align-items: center; gap: 0.5rem; }
        .header .brand img.logo { height: 1.75rem; width: auto; display: block; vertical-align: middle; }
        .main { max-width: 56rem; margin: 0 auto; padding: 1.5rem; background: #fff; min-height: 60vh; color: var(--cr-main-text); border: 1px solid var(--cr-border); border-radius: 4px; box-shadow: 0 1px 3px rgba(26,15,18,0.06); }
        .list { list-style: none; padding: 0; margin: 0; }
        .list li { padding: 0.75rem; border-bottom: 1px solid var(--cr-border); }
        .list li a { color: var(--cr-link); text-decoration: none; }
        .list li a:hover { color: var(--cr-link-hover); text-decoration: underline; }
        .meta { color: var(--cr-meta); font-size: 0.9rem; margin-top: 0.25rem; }
        .btn { display: inline-block; padding: 0.5rem 1rem; background: var(--cr-btn-bg); color: #fff; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: var(--cr-btn-hover); }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.25rem; font-weight: 500; }
        .form-group input { width: 100%; max-width: 20rem; padding: 0.5rem; border: 1px solid var(--cr-border); border-radius: 4px; }
        .alert { padding: 0.75rem; margin-bottom: 1rem; border-radius: 4px; }
        .alert-info { background: var(--cr-alert-info-bg); color: var(--cr-alert-info-text); }
        .alert-warning { background: var(--cr-alert-warning-bg); color: var(--cr-alert-warning-text); }
    </style>
</head>
<body>
<header class="header">
    <a href="/marketplace.php" class="brand">
        <img src="/clawed-road.svg" alt="" class="logo" width="21" height="28">
        <span>Clawed Road</span>
    </a>
    <a href="/marketplace.php">Marketplace</a>
    <a href="/vendors.php">Vendors</a>
    <?php if ($currentUser): ?>
        <a href="/settings/user.php">Settings</a>
        <a href="/referrals.php">Referrals</a>
        <a href="/payments.php">My orders</a>
        <a href="/support.php">Support</a>
        <?php
        $isVendor = false;
        $myStoreUuid = null;
        if (isset($pdo) && !empty($currentUser['uuid'])) {
            $stmt = $pdo->prepare('SELECT store_uuid FROM store_users WHERE user_uuid = ? LIMIT 1');
            $stmt->execute([$currentUser['uuid']]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $isVendor = true;
                $myStoreUuid = $row['store_uuid'];
            }
        }
        if ($isVendor && $myStoreUuid): ?>
            <a href="/store.php?uuid=<?= urlencode($myStoreUuid) ?>">My store</a>
            <a href="/item/new.php?store_uuid=<?= urlencode($myStoreUuid) ?>">Add item</a>
            <a href="/deposits.php">Deposits</a>
        <?php endif; ?>
        <?php $role = $currentUser['role'] ?? ''; if ($role === 'staff' || $role === 'admin'): ?><a href="/staff/index.php">Staff</a><?php endif; ?>
        <?php if ($role === 'admin'): ?><a href="/admin/index.php">Admin</a><?php endif; ?>
        <a href="/create-store.php">Create store</a>
        <a href="/logout.php">Logout (<?= htmlspecialchars($currentUser['username']) ?>)</a>
    <?php else: ?>
        <a href="/login.php">Login</a>
        <a href="/register.php">Register</a>
    <?php endif; ?>
</header>
<main class="main">
