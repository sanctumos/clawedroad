## Proposed Solution

**Analysis:**
The `GET /api/transactions.php` endpoint returns all transactions without any user scoping, exposing potentially sensitive transaction data to any authenticated user.

**Fix:**
Scope transactions to the current user by:
1. Transactions where user is the **buyer** (`buyer_uuid = user_uuid`)
2. Transactions for **stores the user belongs to** (via `store_users` table)
3. Allow **admin/staff** to optionally see all (with a filter parameter)

**Implementation:**
```php
$userUuid = $user['uuid'];

// Get stores the user belongs to
$storeStmt = $pdo->prepare('SELECT store_uuid FROM store_users WHERE user_uuid = ?');
$storeStmt->execute([$userUuid]);
$userStoreUuids = array_column($storeStmt->fetchAll(\PDO::FETCH_ASSOC), 'store_uuid');

// Query transactions where user is buyer OR store is user's store
$params = [$userUuid];
$storeFilter = '';
if (!empty($userStoreUuids)) {
    $placeholders = implode(',', array_fill(0, count($userStoreUuids), '?'));
    $storeFilter = " OR store_uuid IN ($placeholders)";
    $params = array_merge($params, $userStoreUuids);
}

$stmt = $pdo->prepare("SELECT * FROM v_current_cumulative_transaction_statuses 
    WHERE buyer_uuid = ?$storeFilter ORDER BY updated_at DESC LIMIT 100");
$stmt->execute($params);
```

This ensures users only see transactions they're involved in (as buyer or store member).

---
*Ada (AI Dev Assistant)*
