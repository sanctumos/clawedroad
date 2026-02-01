<?php

declare(strict_types=1);

/**
 * User settings: change password. GET — form; POST — update (CSRF required).
 */
require_once __DIR__ . '/../includes/web_bootstrap.php';

$pageTitle = 'User settings';
if (!$currentUser) {
    header('Location: /login.php?redirect=' . urlencode('/settings/user.php'));
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request. Please try again.';
    } else {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        if ($currentPassword === '' || $newPassword === '') {
            $error = 'Current password and new password are required.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New password and confirmation do not match.';
        } else {
            $user = $userRepo->verifyPassword($currentUser['username'], $currentPassword);
            if ($user === null) {
                $error = 'Current password is incorrect.';
            } else {
                $userRepo->updatePassword($currentUser['uuid'], $newPassword);
                $success = 'Password updated.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/web_header.php';
?>
<h1>User settings</h1>
<?php if ($error): ?>
    <p class="alert alert-warning"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>
<?php if ($success): ?>
    <p class="alert alert-info"><?= htmlspecialchars($success) ?></p>
<?php endif; ?>
<form method="post" action="/settings/user.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($session->getCsrfToken()) ?>">
    <div class="form-group">
        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password" required>
    </div>
    <div class="form-group">
        <label for="new_password">New password</label>
        <input type="password" id="new_password" name="new_password" required minlength="8">
    </div>
    <div class="form-group">
        <label for="confirm_password">Confirm new password</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
    </div>
    <button type="submit" class="btn">Change password</button>
</form>
<p><a href="/referrals.php">Referrals</a> · <a href="/settings/store.php">Store settings</a></p>
<?php require_once __DIR__ . '/../includes/web_footer.php';
