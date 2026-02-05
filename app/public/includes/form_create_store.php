<form method="post" action="/create-store.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($session->getCsrfToken()) ?>">
    <div class="form-group">
        <label for="storename">Store name (1–16 chars)</label>
        <input id="storename" type="text" name="storename" maxlength="16" required>
    </div>
    <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4" style="width: 100%; max-width: 24rem;"></textarea>
    </div>
    <div class="form-group">
        <label>
            <input type="checkbox" name="vendorship_agree" value="1">
            I agree to the vendorship agreement
        </label>
    </div>
    <button type="submit" class="btn">Create store</button>
</form>
<p><a href="/vendors.php">← Vendors</a> · <a href="/marketplace.php">Marketplace</a></p>
