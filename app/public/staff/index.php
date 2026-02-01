<?php

declare(strict_types=1);

/**
 * Staff dashboard — links to sections. Middleware: role in (staff, admin).
 */
require_once __DIR__ . '/../includes/web_bootstrap.php';

$pageTitle = 'Staff';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/staff/index.php'));
    exit;
}
if (!in_array($currentUser['role'] ?? '', ['staff', 'admin'], true)) {
    http_response_code(403);
    $pageTitle = 'Forbidden';
    require_once __DIR__ . '/../includes/web_header.php';
    echo '<p>Staff or admin only.</p>';
    require_once __DIR__ . '/../includes/web_footer.php';
    exit;
}

require_once __DIR__ . '/../includes/web_header.php';
?>
<h1>Staff</h1>
<ul class="list">
    <li><a href="/staff/stores.php">Stores</a></li>
    <li><a href="/staff/tickets.php">Tickets</a></li>
    <li><a href="/staff/disputes.php">Disputes</a></li>
    <li><a href="/staff/warnings.php">Warnings</a></li>
    <li><a href="/staff/deposits.php">Deposits</a></li>
    <li><a href="/staff/stats.php">Stats</a></li>
    <li><a href="/staff/categories.php">Categories</a></li>
</ul>
<?php if (($currentUser['role'] ?? '') === 'admin'): ?>
<p><a href="/admin/index.php">Admin (config, tokens, users)</a></p>
<?php endif; ?>
<p style="margin-top: 1rem;"><a href="/marketplace.php" class="btn">← Marketplace</a></p>
<?php require_once __DIR__ . '/../includes/web_footer.php';
