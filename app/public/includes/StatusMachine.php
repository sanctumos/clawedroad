<?php

declare(strict_types=1);

/**
 * Append-only status machine per 01 §11. PHP writes status rows and intent for Python.
 * PHP does NOT sign or send; Python cron performs chain actions and writes RELEASED/CANCELLED receipts.
 */
final class StatusMachine
{
    private \PDO $pdo;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_RELEASED = 'RELEASED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_FROZEN = 'FROZEN';

    public const INTENT_RELEASE = 'RELEASE';
    public const INTENT_CANCEL = 'CANCEL';
    public const INTENT_PARTIAL_REFUND = 'PARTIAL_REFUND';

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Append a transaction_status row (append-only; never update past rows). */
    public function appendTransactionStatus(
        string $transactionUuid,
        float $amount,
        string $status,
        string $comment = '',
        ?string $userUuid = null,
        ?string $paymentReceiptUuid = null
    ): void {
        $now = $this->now();
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO transaction_statuses (transaction_uuid, time, amount, status, comment, user_uuid, payment_receipt_uuid, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        SQL);
        $stmt->execute([
            $transactionUuid,
            $now,
            $amount,
            $status,
            $comment,
            $userUuid,
            $paymentReceiptUuid,
            $now,
        ]);
    }

    /** Append a shipping_status row. */
    public function appendShippingStatus(
        string $transactionUuid,
        string $status,
        string $comment = '',
        ?string $userUuid = null
    ): void {
        $now = $this->now();
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO shipping_statuses (transaction_uuid, time, status, comment, user_uuid, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
        SQL);
        $stmt->execute([$transactionUuid, $now, $status, $comment, $userUuid, $now]);
    }

    /** Request release (PHP writes intent; Python cron performs payout and sets RELEASED). */
    public function requestRelease(string $transactionUuid, ?string $userUuid = null): void
    {
        $this->insertIntent($transactionUuid, self::INTENT_RELEASE, null, $userUuid);
    }

    /** Request cancel (PHP writes intent; Python cron refunds and sets CANCELLED). */
    public function requestCancel(string $transactionUuid, ?string $userUuid = null): void
    {
        $this->insertIntent($transactionUuid, self::INTENT_CANCEL, null, $userUuid);
    }

    /** Request partial refund (params: refund_percent). */
    public function requestPartialRefund(string $transactionUuid, float $refundPercent, ?string $userUuid = null): void
    {
        $params = json_encode(['refund_percent' => $refundPercent]);
        $this->insertIntent($transactionUuid, self::INTENT_PARTIAL_REFUND, $params, $userUuid);
    }

    private function insertIntent(string $transactionUuid, string $action, ?string $params, ?string $userUuid): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO transaction_intents (transaction_uuid, action, params, requested_at, requested_by_user_uuid, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', ?)
        SQL);
        $stmt->execute([$transactionUuid, $action, $params, $now, $userUuid, $now]);
    }

    /** Get current status row for a transaction from view. */
    public function getCurrentStatus(string $transactionUuid): ?array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT * FROM v_current_cumulative_transaction_statuses WHERE uuid = ?
        SQL);
        $stmt->execute([$transactionUuid]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Get pending intents for Python cron. */
    public function getPendingIntents(string $action = null): array
    {
        $sql = "SELECT * FROM transaction_intents WHERE status = 'pending'";
        $params = [];
        if ($action !== null) {
            $sql .= ' AND action = ?';
            $params[] = $action;
        }
        $sql .= ' ORDER BY requested_at ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Mark intent completed/failed (called by Python after chain action). */
    public function updateIntentStatus(int $intentId, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE transaction_intents SET status = ? WHERE id = ?');
        $stmt->execute([$status, $intentId]);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
