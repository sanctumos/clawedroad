<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers \StatusMachine
 */
final class StatusMachineTest extends TestCase
{
    private PDO $pdo;
    private StatusMachine $sm;
    private string $txUuid;
    private string $storeUuid;
    private string $buyerUuid;
    private string $packageUuid;

    protected function setUp(): void
    {
        $this->pdo = Db::pdo();
        $this->sm = new StatusMachine($this->pdo);
        $this->txUuid = User::generateUuid();
        $this->storeUuid = User::generateUuid();
        $this->buyerUuid = User::generateUuid();
        $this->packageUuid = User::generateUuid();
        $now = date('Y-m-d H:i:s');
        $this->pdo->prepare('INSERT INTO users (uuid, username, passphrase_hash, role, banned, created_at) VALUES (?, ?, ?, ?, 0, ?)')->execute([$this->buyerUuid, 'buyer_' . bin2hex(random_bytes(4)), password_hash('x', PASSWORD_BCRYPT), 'customer', $now]);
        $this->pdo->prepare('INSERT INTO stores (uuid, storename, description, is_free, created_at) VALUES (?, ?, ?, 1, ?)')->execute([$this->storeUuid, 'store_' . bin2hex(random_bytes(4)), '', $now]);
        $this->pdo->prepare('INSERT INTO packages (uuid, item_uuid, store_uuid, created_at) VALUES (?, ?, ?, ?)')->execute([$this->packageUuid, User::generateUuid(), $this->storeUuid, $now]);
        $this->pdo->prepare('INSERT INTO transactions (uuid, type, description, package_uuid, store_uuid, buyer_uuid, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$this->txUuid, 'evm', '', $this->packageUuid, $this->storeUuid, $this->buyerUuid, $now]);
        $this->pdo->prepare('INSERT INTO evm_transactions (uuid, escrow_address, amount, chain_id, currency, created_at) VALUES (?, ?, ?, 1, ?, ?)')->execute([$this->txUuid, '0x123', 0.1, 'ETH', $now]);
        $this->pdo->prepare('INSERT INTO transaction_statuses (transaction_uuid, time, amount, status, comment, created_at) VALUES (?, ?, 0, ?, ?, ?)')->execute([$this->txUuid, $now, StatusMachine::STATUS_PENDING, 'Created', $now]);
    }

    public function testAppendTransactionStatus(): void
    {
        $this->sm->appendTransactionStatus($this->txUuid, 0.1, StatusMachine::STATUS_COMPLETED, 'Funded', $this->buyerUuid, null);
        $row = $this->pdo->prepare('SELECT * FROM transaction_statuses WHERE transaction_uuid = ? ORDER BY id DESC LIMIT 1');
        $row->execute([$this->txUuid]);
        $r = $row->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame(StatusMachine::STATUS_COMPLETED, $r['status']);
        $this->assertSame('0.1', (string) $r['amount']);
        $this->assertSame('Funded', $r['comment']);
    }

    public function testAppendShippingStatus(): void
    {
        $this->sm->appendShippingStatus($this->txUuid, 'DISPATCHED', 'Shipped', $this->buyerUuid);
        $row = $this->pdo->prepare('SELECT * FROM shipping_statuses WHERE transaction_uuid = ?');
        $row->execute([$this->txUuid]);
        $r = $row->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('DISPATCHED', $r['status']);
        $this->assertSame('Shipped', $r['comment']);
    }

    public function testRequestReleaseInsertsIntent(): void
    {
        $this->sm->requestRelease($this->txUuid, $this->buyerUuid);
        $row = $this->pdo->prepare("SELECT * FROM transaction_intents WHERE transaction_uuid = ? AND action = 'RELEASE'");
        $row->execute([$this->txUuid]);
        $r = $row->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotNull($r);
        $this->assertSame('pending', $r['status']);
    }

    public function testRequestCancelInsertsIntent(): void
    {
        $this->sm->requestCancel($this->txUuid, $this->buyerUuid);
        $row = $this->pdo->prepare("SELECT * FROM transaction_intents WHERE transaction_uuid = ? AND action = 'CANCEL'");
        $row->execute([$this->txUuid]);
        $this->assertNotNull($row->fetch());
    }

    public function testRequestPartialRefundInsertsIntentWithParams(): void
    {
        $this->sm->requestPartialRefund($this->txUuid, 0.5, $this->buyerUuid);
        $row = $this->pdo->prepare("SELECT * FROM transaction_intents WHERE transaction_uuid = ? AND action = 'PARTIAL_REFUND'");
        $row->execute([$this->txUuid]);
        $r = $row->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotNull($r);
        $params = json_decode($r['params'], true);
        $this->assertSame(0.5, $params['refund_percent']);
    }

    public function testGetCurrentStatusReturnsRow(): void
    {
        $this->sm->appendTransactionStatus($this->txUuid, 0.0, StatusMachine::STATUS_PENDING, '', null, null);
        $row = $this->sm->getCurrentStatus($this->txUuid);
        $this->assertNotNull($row);
        $this->assertSame($this->txUuid, $row['uuid']);
    }

    public function testGetCurrentStatusReturnsNullWhenNoStatus(): void
    {
        $orphanUuid = User::generateUuid();
        $this->pdo->prepare('INSERT INTO transactions (uuid, type, description, package_uuid, store_uuid, buyer_uuid, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$orphanUuid, 'evm', '', $this->packageUuid, $this->storeUuid, $this->buyerUuid, date('Y-m-d H:i:s')]);
        $this->pdo->prepare('INSERT INTO evm_transactions (uuid, escrow_address, amount, chain_id, currency, created_at) VALUES (?, ?, ?, 1, ?, ?)')->execute([$orphanUuid, '0x', 0.1, 'ETH', date('Y-m-d H:i:s')]);
        $row = $this->sm->getCurrentStatus($orphanUuid);
        $this->assertNull($row);
    }

    public function testGetPendingIntentsWithAction(): void
    {
        $this->sm->requestRelease($this->txUuid, null);
        $intents = $this->sm->getPendingIntents(StatusMachine::INTENT_RELEASE);
        $this->assertNotEmpty($intents);
    }

    public function testGetPendingIntentsWithoutAction(): void
    {
        $this->sm->requestRelease($this->txUuid, null);
        $intents = $this->sm->getPendingIntents(null);
        $this->assertNotEmpty($intents);
    }

    public function testUpdateIntentStatus(): void
    {
        $this->sm->requestRelease($this->txUuid, null);
        $stmt = $this->pdo->prepare("SELECT id FROM transaction_intents WHERE transaction_uuid = ? AND action = 'RELEASE'");
        $stmt->execute([$this->txUuid]);
        $id = (int) $stmt->fetchColumn();
        $this->sm->updateIntentStatus($id, 'completed');
        $status = $this->pdo->query("SELECT status FROM transaction_intents WHERE id = $id")->fetchColumn();
        $this->assertSame('completed', $status);
    }

    public function testStatusConstants(): void
    {
        $this->assertSame('PENDING', StatusMachine::STATUS_PENDING);
        $this->assertSame('COMPLETED', StatusMachine::STATUS_COMPLETED);
        $this->assertSame('RELEASED', StatusMachine::STATUS_RELEASED);
        $this->assertSame('FAILED', StatusMachine::STATUS_FAILED);
        $this->assertSame('CANCELLED', StatusMachine::STATUS_CANCELLED);
        $this->assertSame('FROZEN', StatusMachine::STATUS_FROZEN);
        $this->assertSame('RELEASE', StatusMachine::INTENT_RELEASE);
        $this->assertSame('CANCEL', StatusMachine::INTENT_CANCEL);
        $this->assertSame('PARTIAL_REFUND', StatusMachine::INTENT_PARTIAL_REFUND);
    }
}
