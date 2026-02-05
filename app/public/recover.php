<?php

declare(strict_types=1);

/**
 * Password recovery. Step 1: request reset (username); token shown in UI (no email in v2.5).
 * Step 2: GET/POST with token to set new password. CSRF on POST. Rate limit 5/hr per IP.
 */
require_once __DIR__ . '/includes/web_bootstrap.php';

$pageTitle = 'Recover password';
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));

function checkRecoveryRateLimit(PDO $pdo, string $ipHash): bool
{
    $cutoff = date('Y-m-d H:i:s', time() - 3600);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM recovery_rate_limit WHERE ip_hash = ? AND requested_at > ?');
    $stmt->execute([$ipHash, $cutoff]);
    return (int) $stmt->fetchColumn() >= 5;
}

function recordRecoveryAttempt(PDO $pdo, string $ipHash): void
{
    $stmt = $pdo->prepare('INSERT INTO recovery_rate_limit (ip_hash, requested_at) VALUES (?, ?)');
    $stmt->execute([$ipHash, date('Y-m-d H:i:s')]);
}

$ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '0');

// Optional: expire old password reset tokens on step-1 load (no separate cron needed)
if ($token === '') {
    $pdo->prepare('DELETE FROM password_reset_tokens WHERE expires_at < ?')->execute([date('Y-m-d H:i:s')]);
}

if ($token !== '') {
    $stmt = $pdo->prepare('SELECT id, user_uuid, expires_at FROM password_reset_tokens WHERE token = ?');
    $stmt->execute([$token]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$row || (strtotime($row['expires_at']) < time())) {
        $pageTitle = 'Invalid or expired link';
        require_once __DIR__ . '/includes/web_header.php';
        echo '<h1>Invalid or expired link</h1><p>This reset link is invalid or has expired. <a href="/recover.php">Request a new one</a>.</p>';
        require_once __DIR__ . '/includes/web_footer.php';
        exit;
    }

    $userId = $row['user_uuid'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
            $pageTitle = 'Set new password';
            require_once __DIR__ . '/includes/web_header.php';
            echo '<p class="alert alert-warning">Invalid request. Please try again.</p>';
            echo '<form method="post" action="/recover.php"><input type="hidden" name="token" value="' . htmlspecialchars($token) . '"><input type="hidden" name="csrf_token" value="' . htmlspecialchars($session->getCsrfToken()) . '"><div class="form-group"><label for="new_password">New password</label><input type="password" id="new_password" name="new_password" required minlength="8"></div><div class="form-group"><label for="confirm_password">Confirm password</label><input type="password" id="confirm_password" name="confirm_password" required minlength="8"></div><button type="submit" class="btn">Set password</button></form>';
            require_once __DIR__ . '/includes/web_footer.php';
            exit;
        }
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        if (strlen($newPassword) < 8 || $newPassword !== $confirmPassword) {
            $pageTitle = 'Set new password';
            require_once __DIR__ . '/includes/web_header.php';
            echo '<p class="alert alert-warning">Passwords must match and be at least 8 characters.</p>';
            echo '<form method="post" action="/recover.php"><input type="hidden" name="token" value="' . htmlspecialchars($token) . '"><input type="hidden" name="csrf_token" value="' . htmlspecialchars($session->getCsrfToken()) . '"><div class="form-group"><label for="new_password">New password</label><input type="password" id="new_password" name="new_password" required minlength="8"></div><div class="form-group"><label for="confirm_password">Confirm password</label><input type="password" id="confirm_password" name="confirm_password" required minlength="8"></div><button type="submit" class="btn">Set password</button></form>';
            require_once __DIR__ . '/includes/web_footer.php';
            exit;
        }
        $userRepo->updatePassword($userId, $newPassword);
        $pdo->prepare('DELETE FROM password_reset_tokens WHERE token = ?')->execute([$token]);
        header('Location: /login.php?recovered=1', true, 302);
        exit;
    }

    require_once __DIR__ . '/includes/web_header.php';
    ?>
    <h1>Set new password</h1>
    <form method="post" action="/recover.php">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($session->getCsrfToken()) ?>">
        <div class="form-group">
            <label for="new_password">New password</label>
            <input type="password" id="new_password" name="new_password" required minlength="8">
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm password</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
        </div>
        <button type="submit" class="btn">Set password</button>
    </form>
    <?php
    require_once __DIR__ . '/includes/web_footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        require_once __DIR__ . '/includes/web_header.php';
        echo '<p class="alert alert-warning">Invalid request. Please try again.</p>';
        echo '<form method="post" action="/recover.php"><input type="hidden" name="csrf_token" value="' . htmlspecialchars($session->getCsrfToken()) . '"><div class="form-group"><label for="username">Username</label><input type="text" id="username" name="username" required></div><button type="submit" class="btn">Send reset link</button></form>';
        require_once __DIR__ . '/includes/web_footer.php';
        exit;
    }

    if (checkRecoveryRateLimit($pdo, $ipHash)) {
        http_response_code(429);
        require_once __DIR__ . '/includes/web_header.php';
        echo '<p class="alert alert-warning">Too many requests. If that user exists, a reset link was sent. Please try again later.</p>';
        require_once __DIR__ . '/includes/web_footer.php';
        exit;
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $user = $username !== '' ? $userRepo->findByUsername($username) : null;

    if ($user !== null && empty($user['banned'])) {
        $tokenValue = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);
        $stmt = $pdo->prepare('INSERT INTO password_reset_tokens (user_uuid, token, expires_at, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$user['uuid'], $tokenValue, $expiresAt, date('Y-m-d H:i:s')]);
        recordRecoveryAttempt($pdo, $ipHash);

        $siteUrl = Env::get('SITE_URL') ?? '';
        $base = $siteUrl !== '' ? rtrim($siteUrl, '/') : '';
        $resetLink = $base . '/recover.php?token=' . urlencode($tokenValue);
        require_once __DIR__ . '/includes/web_header.php';
        echo '<h1>Reset link</h1>';
        echo '<p>If that user exists, a reset link was sent. Copy and open this link (it expires in 1 hour):</p>';
        echo '<p><input type="text" readonly value="' . htmlspecialchars($resetLink) . '" style="width:100%;max-width:32rem;" onclick="this.select()"></p>';
        echo '<p><a href="/login.php">Back to login</a></p>';
        require_once __DIR__ . '/includes/web_footer.php';
        exit;
    }

    recordRecoveryAttempt($pdo, $ipHash);
    require_once __DIR__ . '/includes/web_header.php';
    echo '<h1>Reset link</h1>';
    echo '<p>If that user exists, a reset link was sent. Copy and open the link in the box below (it expires in 1 hour). Check your username and try again if needed.</p>';
    echo '<form method="post" action="/recover.php"><input type="hidden" name="csrf_token" value="' . htmlspecialchars($session->getCsrfToken()) . '"><div class="form-group"><label for="username">Username</label><input type="text" id="username" name="username" required></div><button type="submit" class="btn">Send reset link</button></form>';
    require_once __DIR__ . '/includes/web_footer.php';
    exit;
}

require_once __DIR__ . '/includes/web_header.php';
?>
<h1>Recover password</h1>
<p>Enter your username. A reset link will be shown on the next page (no email in v2.5).</p>
<form method="post" action="/recover.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($session->getCsrfToken()) ?>">
    <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required>
    </div>
    <button type="submit" class="btn">Send reset link</button>
</form>
<p><a href="/login.php">Back to login</a></p>
<?php require_once __DIR__ . '/includes/web_footer.php';
