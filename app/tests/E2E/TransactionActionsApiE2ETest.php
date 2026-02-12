<?php

declare(strict_types=1);

final class TransactionActionsApiE2ETest extends E2ETestCase
{
    public function testPostTransactionActionsWithoutAuthReturns401(): void
    {
        $res = self::runRequest([
            'method' => 'POST',
            'uri' => 'api/transaction-actions.php',
            'get' => [],
            'post' => [
                'transaction_uuid' => User::generateUuid(),
                'action' => 'release',
            ],
            'headers' => [],
        ]);

        $this->assertSame(401, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertSame('Login required', $data['error'] ?? '');
    }

    public function testPostTransactionActionsSessionWithoutCsrfReturns403(): void
    {
        $txUuid = $this->seedTransaction(StatusMachine::STATUS_PENDING);
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);

        $res = self::runRequest([
            'method' => 'POST',
            'uri' => 'api/transaction-actions.php',
            'get' => [],
            'post' => [
                'transaction_uuid' => $txUuid,
                'action' => 'cancel',
            ],
            'headers' => [],
            'cookies' => $cookies,
        ]);

        $this->assertSame(403, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertSame('CSRF token required', $data['error'] ?? '');
    }

    public function testBuyerCannotRequestReleaseViaApi(): void
    {
        $txUuid = $this->seedTransaction(StatusMachine::STATUS_COMPLETED);
        $cookies = self::loginAs('e2e_customer', 'password123');
        $this->assertNotEmpty($cookies);
        $csrf = $this->csrfForCookies($cookies);
        $this->assertNotSame('', $csrf);

        $res = self::runRequest([
            'method' => 'POST',
            'uri' => 'api/transaction-actions.php',
            'get' => [],
            'post' => [
                'transaction_uuid' => $txUuid,
                'action' => 'release',
                'csrf_token' => $csrf,
            ],
            'headers' => [],
            'cookies' => $cookies,
        ]);

        $this->assertSame(403, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertSame('Action not allowed', $data['error'] ?? '');
    }

    public function testPostTransactionActionsWithApiKeyReleaseWritesIntent(): void
    {
        $txUuid = $this->seedTransaction(StatusMachine::STATUS_COMPLETED);
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $apiKey = $this->createApiKey($cookies, 'tx-actions-release');
        $this->assertNotSame('', $apiKey);

        $res = self::runRequest([
            'method' => 'POST',
            'uri' => 'api/transaction-actions.php',
            'get' => [],
            'post' => [
                'transaction_uuid' => $txUuid,
                'action' => 'release',
            ],
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
            ],
        ]);

        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertTrue($data['ok'] ?? false);
        $this->assertSame('RELEASE', $data['intent'] ?? '');

        $intent = $this->latestIntentForTransaction($txUuid, 'RELEASE');
        $this->assertNotNull($intent);
        $this->assertSame('pending', $intent['status']);
    }

    public function testPostTransactionActionsCancelWithSessionWritesIntent(): void
    {
        $txUuid = $this->seedTransaction(StatusMachine::STATUS_PENDING);
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $csrf = $this->csrfForCookies($cookies);
        $this->assertNotSame('', $csrf);

        $res = self::runRequest([
            'method' => 'POST',
            'uri' => 'api/transaction-actions.php',
            'get' => [],
            'post' => [
                'transaction_uuid' => $txUuid,
                'action' => 'cancel',
                'csrf_token' => $csrf,
            ],
            'headers' => [],
            'cookies' => $cookies,
        ]);

        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertTrue($data['ok'] ?? false);
        $this->assertSame('CANCEL', $data['intent'] ?? '');

        $intent = $this->latestIntentForTransaction($txUuid, 'CANCEL');
        $this->assertNotNull($intent);
        $this->assertSame('pending', $intent['status']);
    }

    public function testPostTransactionActionsPartialRefundWithOpenDisputeWritesIntent(): void
    {
        $txUuid = $this->seedTransaction(StatusMachine::STATUS_FROZEN, true);
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $csrf = $this->csrfForCookies($cookies);
        $this->assertNotSame('', $csrf);

        $res = self::runRequest([
            'method' => 'POST',
            'uri' => 'api/transaction-actions.php',
            'get' => [],
            'post' => [
                'transaction_uuid' => $txUuid,
                'action' => 'partial_refund',
                'refund_percent' => '25',
                'csrf_token' => $csrf,
            ],
            'headers' => [],
            'cookies' => $cookies,
        ]);

        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertTrue($data['ok'] ?? false);
        $this->assertSame('PARTIAL_REFUND', $data['intent'] ?? '');
        $this->assertSame(25.0, (float) ($data['refund_percent'] ?? 0));

        $intent = $this->latestIntentForTransaction($txUuid, 'PARTIAL_REFUND');
        $this->assertNotNull($intent);
        $params = json_decode((string) ($intent['params'] ?? ''), true);
        $this->assertSame(25.0, (float) ($params['refund_percent'] ?? 0));
    }

    private function createApiKey(array $cookies, string $name): string
    {
        $csrf = $this->csrfForCookies($cookies);
        if ($csrf === '') {
            return '';
        }
        $keyRes = self::runRequest([
            'method' => 'POST',
            'uri' => 'api/keys.php',
            'get' => [],
            'post' => [
                'name' => $name,
                'csrf_token' => $csrf,
            ],
            'headers' => [],
            'cookies' => $cookies,
        ]);
        if ($keyRes['code'] !== 200) {
            return '';
        }
        $keyData = json_decode($keyRes['body'], true);
        return (string) ($keyData['api_key'] ?? '');
    }

    private function csrfForCookies(array $cookies): string
    {
        $pageRes = self::runRequest([
            'method' => 'GET',
            'uri' => 'register.php',
            'get' => [],
            'post' => [],
            'headers' => [],
            'cookies' => $cookies,
        ]);
        return self::extractCsrfFromBody((string) ($pageRes['body'] ?? ''));
    }

    private function seedTransaction(string $status, bool $withOpenDispute = false): string
    {
        $pdo = Db::pdo();
        $now = date('Y-m-d H:i:s');
        $buyerUuid = $this->userUuidByUsername('e2e_customer');
        $storeUuid = $this->storeUuidForUser($buyerUuid);
        $packageUuid = $this->ensurePackageForStore($storeUuid);

        $txUuid = User::generateUuid();
        $pdo->prepare('INSERT INTO transactions (uuid, type, description, package_uuid, store_uuid, buyer_uuid, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$txUuid, 'evm', '', $packageUuid, $storeUuid, $buyerUuid, $now]);
        $pdo->prepare('INSERT INTO evm_transactions (uuid, amount, chain_id, currency, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$txUuid, 0.1, 1, 'ETH', $now]);
        $pdo->prepare('INSERT INTO transaction_statuses (transaction_uuid, time, amount, status, comment, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$txUuid, $now, 0.0, $status, 'Seed status', $now]);

        if ($withOpenDispute) {
            $disputeUuid = User::generateUuid();
            $pdo->prepare('INSERT INTO disputes (uuid, status, created_at) VALUES (?, ?, ?)')->execute([$disputeUuid, 'open', $now]);
            $pdo->prepare('UPDATE transactions SET dispute_uuid = ?, updated_at = ? WHERE uuid = ?')->execute([$disputeUuid, $now, $txUuid]);
        }

        return $txUuid;
    }

    private function userUuidByUsername(string $username): string
    {
        $stmt = Db::pdo()->prepare('SELECT uuid FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        return (string) $stmt->fetchColumn();
    }

    private function storeUuidForUser(string $userUuid): string
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT store_uuid FROM store_users WHERE user_uuid = ? LIMIT 1');
        $stmt->execute([$userUuid]);
        $storeUuid = (string) ($stmt->fetchColumn() ?: '');
        if ($storeUuid !== '') {
            return $storeUuid;
        }

        $storeUuid = User::generateUuid();
        $now = date('Y-m-d H:i:s');
        $storename = 'e2e' . substr($storeUuid, 0, 8);
        $pdo->prepare('INSERT INTO stores (uuid, storename, description, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$storeUuid, $storename, 'E2E', $now]);
        $pdo->prepare('INSERT INTO store_users (store_uuid, user_uuid, role) VALUES (?, ?, ?)')
            ->execute([$storeUuid, $userUuid, 'owner']);
        return $storeUuid;
    }

    private function ensurePackageForStore(string $storeUuid): string
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare("SELECT uuid FROM packages WHERE store_uuid = ? AND (deleted_at IS NULL OR deleted_at = '') LIMIT 1");
        $stmt->execute([$storeUuid]);
        $packageUuid = (string) ($stmt->fetchColumn() ?: '');
        if ($packageUuid !== '') {
            return $packageUuid;
        }

        $now = date('Y-m-d H:i:s');
        $itemUuid = User::generateUuid();
        $pdo->prepare('INSERT INTO items (uuid, name, description, store_uuid, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$itemUuid, 'E2E Tx Item', '', $storeUuid, $now]);
        $packageUuid = User::generateUuid();
        $pdo->prepare('INSERT INTO packages (uuid, item_uuid, store_uuid, name, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$packageUuid, $itemUuid, $storeUuid, 'E2E Tx Package', $now]);

        return $packageUuid;
    }

    private function latestIntentForTransaction(string $transactionUuid, string $action): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM transaction_intents WHERE transaction_uuid = ? AND action = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$transactionUuid, $action]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
