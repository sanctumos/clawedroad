<form method="post" action="/register.php">
    <div class="form-group">
        <label for="reg-username">Username</label>
        <input id="reg-username" type="text" name="username" maxlength="16" required autocomplete="username">
    </div>
    <div class="form-group">
        <label for="reg-password">Password (min 8 characters)</label>
        <input id="reg-password" type="password" name="password" required minlength="8" autocomplete="new-password">
    </div>
    <button type="submit" class="btn">Register</button>
</form>
<p><a href="/login.php">Login</a> · <a href="/marketplace.php">Marketplace</a></p>
