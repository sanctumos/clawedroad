<?php

declare(strict_types=1);

/**
 * Portable schema (SQLite / MariaDB). Run once to create tables.
 * Per 01 and 08: transactions, transaction_statuses, shipping_statuses,
 * payment_receipts, referral_payments, evm_transactions, deposits, users,
 * stores, packages, items, disputes, config; no Postgres-only features.
 */
final class Schema
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
        $this->createUsers();
        $this->createStores();
        $this->createStoreUsers();
        $this->createItemCategories();
        $this->createItems();
        $this->createPackages();
        $this->createPackagePrices();
        $this->createPaymentReceipts();
        $this->createTransactions();
        $this->createEvmTransactions();
        $this->createTransactionStatuses();
        $this->createShippingStatuses();
        $this->createReferralPayments();
        $this->createDeposits();
        $this->createDepositHistory();
        $this->createDisputes();
        $this->createDisputeClaims();
        $this->createTransactionIntents();
        $this->createConfig();
        $this->createApiKeys();
        $this->createApiKeyRequests();
        $this->createAcceptedTokens();
    }

    private function createApiKeyRequests(): void
    {
        $pk = $this->pk();
        $this->exec("CREATE TABLE IF NOT EXISTS api_key_requests (
            id {$pk},
            api_key_id INTEGER NOT NULL,
            requested_at TEXT NOT NULL,
            FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
        )");
        if (!$this->sqlite) {
            $this->exec('CREATE INDEX IF NOT EXISTS idx_api_key_requests_key ON api_key_requests(api_key_id)');
            $this->exec('CREATE INDEX IF NOT EXISTS idx_api_key_requests_at ON api_key_requests(requested_at)');
        }
    }

    private function createTransactionIntents(): void
    {
        $pk = $this->pk();
        $this->exec("CREATE TABLE IF NOT EXISTS transaction_intents (
            id {$pk},
            transaction_uuid TEXT NOT NULL,
            action TEXT NOT NULL,
            params TEXT,
            requested_at TEXT NOT NULL,
            requested_by_user_uuid TEXT,
            status TEXT NOT NULL DEFAULT 'pending',
            created_at TEXT,
            FOREIGN KEY (transaction_uuid) REFERENCES transactions(uuid)
        )");
        if (!$this->sqlite) {
            $this->exec('CREATE INDEX IF NOT EXISTS idx_intents_tx ON transaction_intents(transaction_uuid)');
            $this->exec('CREATE INDEX IF NOT EXISTS idx_intents_status ON transaction_intents(status)');
        }
    }

    private function pk(): string
    {
        return $this->sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    }

    private function ts(): string
    {
        return $this->sqlite
            ? "DEFAULT (datetime('current_timestamp'))"
            : 'DEFAULT CURRENT_TIMESTAMP';
    }

    private function exec(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    private function createUsers(): void
    {
        $this->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS users (
            uuid TEXT PRIMARY KEY,
            username TEXT NOT NULL UNIQUE,
            passphrase_hash TEXT NOT NULL,
            role TEXT NOT NULL,
            inviter_uuid TEXT,
            refund_address_evm TEXT,
            resolver_evm_address TEXT,
            banned INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT,
            deleted_at TEXT
        )
        SQL);
        if (!$this->sqlite) {
            $this->exec('CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)');
            $this->exec('CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)');
            $this->exec('CREATE INDEX IF NOT EXISTS idx_users_inviter ON users(inviter_uuid)');
        }
    }

    private function createStores(): void
    {
        $this->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS stores (
            uuid TEXT PRIMARY KEY,
            storename TEXT NOT NULL UNIQUE,
            description TEXT,
            vendorship_agreed_at TEXT,
            is_gold INTEGER NOT NULL DEFAULT 0,
            is_silver INTEGER NOT NULL DEFAULT 0,
            is_bronze INTEGER NOT NULL DEFAULT 0,
            is_free INTEGER NOT NULL DEFAULT 1,
            is_suspended INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT,
            deleted_at TEXT
        )
        SQL);
        if (!$this->sqlite) {
            $this->exec('CREATE INDEX IF NOT EXISTS idx_stores_storename ON stores(storename)');
        }
    }

    private function createStoreUsers(): void
    {
        $this->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS store_users (
            store_uuid TEXT NOT NULL,
            user_uuid TEXT NOT NULL,
            role TEXT NOT NULL,
            PRIMARY KEY (store_uuid, user_uuid, role),
            FOREIGN KEY (store_uuid) REFERENCES stores(uuid),
            FOREIGN KEY (user_uuid) REFERENCES users(uuid)
        )
        SQL);
    }

    private function createItemCategories(): void
    {
        $pk = $this->pk();
        $this->exec("CREATE TABLE IF NOT EXISTS item_categories (
            id {$pk},
            name_en TEXT NOT NULL,
            parent_id INTEGER,
            FOREIGN KEY (parent_id) REFERENCES item_categories(id)
        )");
    }

    private function createItems(): void
    {
        $this->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS items (
            uuid TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            description TEXT,
            store_uuid TEXT NOT NULL,
            category_id INTEGER,
            created_at TEXT NOT NULL,
            updated_at TEXT,
            deleted_at TEXT,
            FOREIGN KEY (store_uuid) REFERENCES stores(uuid),
            FOREIGN KEY (category_id) REFERENCES item_categories(id)
        )
        SQL);
        if (!$this->sqlite) {
            $this->exec('CREATE INDEX IF NOT EXISTS idx_items_store ON items(store_uuid)');
        }
    }

    private function createPackages(): void
    {
        $this->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS packages (
            uuid TEXT PRIMARY KEY,
            item_uuid TEXT NOT NULL,
            store_uuid TEXT NOT NULL,
            name TEXT,
            description TEXT,
            type TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT,
            deleted_at TEXT,
            FOREIGN KEY (item_uuid) REFERENCES items(uuid),
            FOREIGN KEY (store_uuid) REFERENCES stores(uuid)
        )
        SQL);
        if (!$this->sqlite) {
            $this->exec('CREATE INDEX IF NOT EXISTS idx_packages_item ON packages(item_uuid)');
            $this->exec('CREATE INDEX IF NOT EXISTS idx_packages_store ON packages(store_uuid)');
        }
    }

    private function createPackagePrices(): void
    {
        $this->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS package_prices (
            uuid TEXT PRIMARY KEY,
            package_uuid TEXT NOT NULL,
            currency TEXT NOT NULL,
            price_usd REAL NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY (package_uuid) REFERENCES packages(uuid)
        )
        SQL);
    }

    private function createTransactions(): void
    {
        $this->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS transactions (
            uuid TEXT PRIMARY KEY,
            type TEXT NOT NULL,
            description TEXT,
            package_uuid TEXT NOT NULL,
            store_uuid TEXT NOT NULL,
            buyer_uuid TEXT NOT NULL,
            dispute_uuid TEXT,
            refund_address TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT,
            FOREIGN KEY (package_uuid) REFERENCES packages(uuid),
            FOREIGN KEY (store_uuid) REFERENCES stores(uuid),
            FOREIGN KEY (buyer_uuid) REFERENCES users(uuid)
        )
        SQL);
        if (!$this->sqlite) {
            $this->exec('CREATE INDEX IF NOT EXISTS idx_transactions_store ON transactions(store_uuid)');
            $this->exec('CREATE INDEX IF NOT EXISTS idx_transactions_buyer ON transactions(buyer_uuid)');
            $this->exec('CREATE INDEX IF NOT EXISTS idx_transactions_dispute ON transactions(dispute_uuid)');
        }
    }

    private function createEvmTransactions(): void
    {
        $this->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS evm_transactions (
            uuid TEXT PRIMARY KEY,
            escrow_address TEXT,
            amount REAL NOT NULL,
            chain_id INTEGER NOT NULL,
            currency TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT,
            FOREIGN KEY (uuid) REFERENCES transactions(uuid)
        )
        SQL);
    }

    private function createTransactionStatuses(): void
    {
        $pk = $this->pk();
        $this->exec("CREATE TABLE IF NOT EXISTS transaction_statuses (
            id {$pk},
            transaction_uuid TEXT NOT NULL,
            time TEXT NOT NULL,
            amount REAL NOT NULL,
            status TEXT NOT NULL,
            comment TEXT,
            user_uuid TEXT,
            payment_receipt_uuid TEXT,
            created_at TEXT,
            FOREIGN KEY (transaction_uuid) REFERENCES transactions(uuid),
            FOREIGN KEY (payment_receipt_uuid) REFERENCES payment_receipts(uuid)
        )");
        if (!$this->sqlite) {
            $this->exec('CREATE INDEX IF NOT EXISTS idx_tx_statuses_tx ON transaction_statuses(transaction_uuid)');
            $this->exec('CREATE INDEX IF NOT EXISTS idx_tx_statuses_status ON transaction_statuses(status)');
        }
    }

    private function createShippingStatuses(): void
    {
        $pk = $this->pk();
        $this->exec("CREATE TABLE IF NOT EXISTS shipping_statuses (
            id {$pk},
            transaction_uuid TEXT NOT NULL,
            time TEXT NOT NULL,
            status TEXT NOT NULL,
            comment TEXT,
            user_uuid TEXT,
            created_at TEXT,
            FOREIGN KEY (transaction_uuid) REFERENCES transactions(uuid)
        )");
        if (!$this->sqlite) {
            $this->exec('CREATE INDEX IF NOT EXISTS idx_shipping_statuses_tx ON shipping_statuses(transaction_uuid)');
        }
    }

    private function createPaymentReceipts(): void
    {
        $this->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS payment_receipts (
            uuid TEXT PRIMARY KEY,
            type TEXT NOT NULL,
            serialized_data TEXT NOT NULL,
            version INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL
        )
        SQL);
    }

    private function createReferralPayments(): void
    {
        $this->exec('CREATE TABLE IF NOT EXISTS referral_payments (
            id ' . $this->pk() . ',
            transaction_uuid TEXT NOT NULL,
            user_uuid TEXT NOT NULL,
            referral_percent REAL NOT NULL,
            referral_payment_eth REAL NOT NULL DEFAULT 0,
            referral_payment_usd REAL NOT NULL DEFAULT 0,
            is_buyer_referral INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT,
            FOREIGN KEY (transaction_uuid) REFERENCES transactions(uuid),
            FOREIGN KEY (user_uuid) REFERENCES users(uuid)
        )');
        if (!$this->sqlite) {
            $this->exec('CREATE INDEX IF NOT EXISTS idx_referral_tx ON referral_payments(transaction_uuid)');
        }
    }

    private function createDeposits(): void
    {
        $this->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS deposits (
            uuid TEXT PRIMARY KEY,
            store_uuid TEXT NOT NULL,
            currency TEXT NOT NULL,
            crypto TEXT NOT NULL,
            address TEXT NOT NULL,
            crypto_value REAL NOT NULL,
            fiat_value REAL NOT NULL,
            currency_rate REAL NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT,
            deleted_at TEXT,
            FOREIGN KEY (store_uuid) REFERENCES stores(uuid)
        )
        SQL);
        if (!$this->sqlite) {
            $this->exec('CREATE INDEX IF NOT EXISTS idx_deposits_store ON deposits(store_uuid)');
        }
    }

    private function createDepositHistory(): void
    {
        $this->exec('CREATE TABLE IF NOT EXISTS deposit_history (
            uuid TEXT PRIMARY KEY,
            deposit_uuid TEXT NOT NULL,
            action TEXT NOT NULL,
            value REAL NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY (deposit_uuid) REFERENCES deposits(uuid)
        )');
    }

    private function createDisputes(): void
    {
        $this->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS disputes (
            uuid TEXT PRIMARY KEY,
            status TEXT NOT NULL,
            resolver_user_uuid TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT,
            deleted_at TEXT,
            FOREIGN KEY (resolver_user_uuid) REFERENCES users(uuid)
        )
        SQL);
        if (!$this->sqlite) {
            $this->exec('CREATE INDEX IF NOT EXISTS idx_disputes_status ON disputes(status)');
        }
    }

    private function createDisputeClaims(): void
    {
        $this->exec('CREATE TABLE IF NOT EXISTS dispute_claims (
            id ' . $this->pk() . ',
            dispute_uuid TEXT NOT NULL,
            claim TEXT NOT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT,
            FOREIGN KEY (dispute_uuid) REFERENCES disputes(uuid)
        )');
    }

    private function createConfig(): void
    {
        $this->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS config (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )
        SQL);
    }

    private function createApiKeys(): void
    {
        $this->exec('CREATE TABLE IF NOT EXISTS api_keys (
            id ' . $this->pk() . ',
            user_uuid TEXT NOT NULL,
            name TEXT,
            api_key TEXT NOT NULL,
            key_prefix TEXT NOT NULL,
            created_at TEXT NOT NULL,
            last_used_at TEXT,
            expires_at TEXT,
            FOREIGN KEY (user_uuid) REFERENCES users(uuid)
        )');
        if (!$this->sqlite) {
            $this->exec('CREATE INDEX IF NOT EXISTS idx_api_keys_user ON api_keys(user_uuid)');
            $this->exec('CREATE INDEX IF NOT EXISTS idx_api_keys_key ON api_keys(api_key(64))');
        }
    }

    private function createAcceptedTokens(): void
    {
        $pk = $this->pk();
        $unique = $this->sqlite ? ', UNIQUE(chain_id, contract_address)' : ', UNIQUE KEY uq_chain_contract (chain_id, contract_address)';
        $this->exec("CREATE TABLE IF NOT EXISTS accepted_tokens (
            id {$pk},
            chain_id INTEGER NOT NULL,
            symbol TEXT NOT NULL,
            contract_address TEXT,
            created_at TEXT NOT NULL {$unique}
        )");
    }
}
