<?php

declare(strict_types=1);

/**
 * Referrals: referral link, referred users list, referral earnings.
 */
require_once __DIR__ . '/includes/web_bootstrap.php';

$pageTitle = 'Referrals';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/referrals.php'));
    exit;
}

$siteUrl = Env::get('SITE_URL') ?? '';
$baseUrl = $siteUrl !== '' ? rtrim($siteUrl, '/') : '';

$stmt = $pdo->prepare('SELECT code FROM invite_codes WHERE created_by_user_uuid = ? AND used_at IS NULL LIMIT 1');
$stmt->execute([$currentUser['uuid']]);
$row = $stmt->fetch(\PDO::FETCH_ASSOC);
$referralLink = $row ? $baseUrl . '/register.php?invite=' . urlencode($row['code']) : $baseUrl . '/register.php?invite=' . urlencode($currentUser['username']);

$stmt = $pdo->prepare('SELECT uuid, username, created_at FROM users WHERE inviter_uuid = ? AND deleted_at IS NULL ORDER BY created_at DESC');
$stmt->execute([$currentUser['uuid']]);
$referredUsers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT * FROM referral_payments WHERE user_uuid = ? ORDER BY created_at DESC LIMIT 50');
$stmt->execute([$currentUser['uuid']]);
$earnings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/web_header.php';
?>
<h1>Referrals</h1>
<h2>Your referral link</h2>
<p>Share this link; when someone registers with it, they will be listed as referred by you.</p>
<p><input type="text" readonly value="<?= htmlspecialchars($referralLink) ?>" style="width:100%;max-width:32rem;" onclick="this.select()"></p>

<h2>Referred users</h2>
<?php if (empty($referredUsers)): ?>
    <p>No one has signed up with your referral link yet.</p>
<?php else: ?>
    <ul class="list">
        <?php foreach ($referredUsers as $u): ?>
            <li>
                <a href="/user.php?username=<?= urlencode($u['username']) ?>"><?= htmlspecialchars($u['username']) ?></a>
                <span class="meta">Joined <?= htmlspecialchars($u['created_at'] ?? '') ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<h2>Referral earnings</h2>
<?php if (empty($earnings)): ?>
    <p>No referral earnings yet.</p>
<?php else: ?>
    <ul class="list">
        <?php foreach ($earnings as $e): ?>
            <li>
                Transaction <?= htmlspecialchars($e['transaction_uuid'] ?? '') ?> —
                <?= htmlspecialchars((string) ($e['referral_percent'] ?? '')) ?>% —
                <?= htmlspecialchars((string) ($e['referral_payment_usd'] ?? '0')) ?> USD
                <span class="meta"><?= htmlspecialchars($e['created_at'] ?? '') ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<p><a href="/settings/user.php">User settings</a></p>
<?php require_once __DIR__ . '/includes/web_footer.php';
