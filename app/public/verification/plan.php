<?php

declare(strict_types=1);

/**
 * Verification plan: tier info (gold/silver/bronze). Static info page; no purchase in v2.5.
 */
require_once __DIR__ . '/../includes/web_bootstrap.php';

$pageTitle = 'Verification plan';
require_once __DIR__ . '/../includes/web_header.php';
?>
<h1>Verification plan</h1>
<p>Verification tiers (gold, silver, bronze) are planned for a future release. For now, vendorship is governed by the <a href="/verification/agreement.php">vendorship agreement</a>.</p>
<p><a href="/verification/agreement.php">Vendorship agreement</a> · <a href="/settings/user.php">Settings</a></p>
<?php require_once __DIR__ . '/../includes/web_footer.php';
