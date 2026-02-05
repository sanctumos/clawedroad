<?php

declare(strict_types=1);

/**
 * Current-status views per 01 §10: current = latest row in transaction_statuses.
 * Portable SQL (SQLite / MariaDB). EVM-only (no bitcoin).
 */
final class Views
{
    private \PDO $pdo;
    private bool $sqlite;

    public function __construct(\PDO $pdo, bool $sqlite)
    {
        $this->pdo = $pdo;
        $this->sqlite = $sqlite;
    }

    public function run(): void
    {
        $this->dropViews();
        $this->createVTransactionStatuses();
        $this->createVShippingStatuses();
        $this->createVCurrentTransactionStatuses();
        $this->createVCurrentEvmTransactionStatuses();
        $this->createVCurrentCumulativeTransactionStatuses();
    }

    private function exec(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    private function dropViews(): void
    {
        $views = [
            'v_current_cumulative_transaction_statuses',
            'v_current_evm_transaction_statuses',
            'v_current_transaction_statuses',
            'v_shipping_statuses',
            'v_transaction_statuses',
        ];
        foreach ($views as $v) {
            // DROP VIEW IF EXISTS works on both SQLite and MariaDB
            $this->exec("DROP VIEW IF EXISTS {$v}");
        }
    }

    /** v_transaction_statuses: min/max time, min/max amount, min/max status per transaction */
    private function createVTransactionStatuses(): void
    {
        $this->exec(<<<'SQL'
        CREATE VIEW v_transaction_statuses AS
        SELECT
            ts.transaction_uuid,
            ts.max_timestamp,
            ts.min_timestamp,
            ts1.amount AS min_amount,
            ts2.amount AS max_amount,
            ts1.status AS min_status,
            ts2.status AS max_status
        FROM (
            SELECT transaction_uuid, MAX(time) AS max_timestamp, MIN(time) AS min_timestamp
            FROM transaction_statuses
            GROUP BY transaction_uuid
        ) ts
        JOIN transaction_statuses ts1 ON ts1.transaction_uuid = ts.transaction_uuid AND ts1.time = ts.min_timestamp
        JOIN transaction_statuses ts2 ON ts2.transaction_uuid = ts.transaction_uuid AND ts2.time = ts.max_timestamp
        SQL);
    }

    /** v_shipping_statuses: latest shipping status per transaction (one row per tx) */
    private function createVShippingStatuses(): void
    {
        $this->exec(<<<'SQL'
        CREATE VIEW v_shipping_statuses AS
        SELECT ss.transaction_uuid, ss.time AS max_timestamp, ss.status AS max_status
        FROM shipping_statuses ss
        INNER JOIN (
            SELECT transaction_uuid, MAX(time) AS max_timestamp
            FROM shipping_statuses
            GROUP BY transaction_uuid
        ) m ON ss.transaction_uuid = m.transaction_uuid AND ss.time = m.max_timestamp
        SQL);
    }

    /** v_current_transaction_statuses: one row per transaction with current status/amount/shipping */
    private function createVCurrentTransactionStatuses(): void
    {
        $this->exec(<<<'SQL'
        CREATE VIEW v_current_transaction_statuses AS
        SELECT
            t.uuid,
            t.description,
            t.type,
            t.package_uuid,
            t.store_uuid,
            t.buyer_uuid,
            t.dispute_uuid,
            vts.max_status AS current_status,
            vts.max_amount AS current_amount,
            vts.max_timestamp AS updated_at,
            vts.min_timestamp AS created_at,
            COALESCE(vss.max_status, 'DISPATCH PENDING') AS current_shipping_status,
            0 AS number_of_messages,
            s.storename AS storename,
            u2.username AS buyer_username
        FROM transactions t
        INNER JOIN v_transaction_statuses vts ON t.uuid = vts.transaction_uuid
        INNER JOIN stores s ON s.uuid = t.store_uuid
        INNER JOIN users u2 ON u2.uuid = t.buyer_uuid
        LEFT JOIN v_shipping_statuses vss ON t.uuid = vss.transaction_uuid
        SQL);
    }

    /** v_current_evm_transaction_statuses: current status + evm_transactions (amount, escrow_address, chain_id, currency) */
    private function createVCurrentEvmTransactionStatuses(): void
    {
        $this->exec(<<<'SQL'
        CREATE VIEW v_current_evm_transaction_statuses AS
        SELECT vcts.*, e.amount AS required_amount, e.escrow_address, e.chain_id, e.currency
        FROM v_current_transaction_statuses vcts
        JOIN evm_transactions e ON vcts.uuid = e.uuid
        WHERE vcts.type = 'evm'
        SQL);
    }

    /** v_current_cumulative_transaction_statuses: EVM-only listing (all transactions with current status) */
    private function createVCurrentCumulativeTransactionStatuses(): void
    {
        $this->exec(<<<'SQL'
        CREATE VIEW v_current_cumulative_transaction_statuses AS
        SELECT uuid, type, description, current_amount, current_status, current_shipping_status,
               number_of_messages, required_amount, escrow_address, chain_id, currency,
               buyer_username, storename, dispute_uuid, package_uuid, store_uuid, buyer_uuid,
               updated_at, created_at
        FROM v_current_evm_transaction_statuses
        SQL);
    }
}
