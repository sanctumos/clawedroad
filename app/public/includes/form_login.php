<?php
$redirectParam = $redirectParam ?? '';
?>
<form method="post" action="/login.php">
    <?php if ($redirectParam !== ''): ?><input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectParam) ?>"><?php endif; ?>
    <div class="form-group">
        <label for="login-username">Username</label>
        <input id="login-username" type="text" name="username" required autocomplete="username">
    </div>
    <div class="form-group">
        <label for="login-password">Password</label>
        <input id="login-password" type="password" name="password" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn">Login</button>
</form>
<p><a href="/register.php">Register</a> · <a href="/marketplace.php">Marketplace</a></p>
