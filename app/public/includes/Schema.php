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
        $this->createPasswordResetTokens();
        $this->createRecoveryRateLimit();
        $this->createLoginRateLimit();
        $this->createRegistrationRateLimit();
        $this->createInviteCodes();
        $this->createReviews();
        $this->createStoreWarnings();
        $this->createSupportTickets();
        $this->createSupportTicketMessages();
        $this->createPrivateMessages();
        $this->createDepositWithdrawIntents();
        $this->createAuditLog();
        $this->addV25Columns();
        $this->createConfig();
        $this->createApiKeys();
        $this->createApiKeyRequests();
        $this->createAgentIdentities();
        $this->createAgentRequests();
        $this->createHooks();
        $this->createHookEvents();
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

    /**
     * Creates stores table.
     * Note: storename is limited to 16 characters (enforced by CHECK constraint and PHP code).
     */
    private function createStores(): void
    {
        $this->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS stores (
            uuid TEXT PRIMARY KEY,
            storename TEXT NOT NULL UNIQUE CHECK (LENGTH(storename) >= 1 AND LENGTH(storename) <= 16),
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
            address TEXT,
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
            // Prefix index covers full key length: 32 bytes × 2 = 64 hex chars (see ApiKey::KEY_HEX_LENGTH)
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

    private function createPasswordResetTokens(): void
    {
        $pk = $this->pk();
        $this->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id {$pk},
            user_uuid TEXT NOT NULL,
            token TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY (user_uuid) REFERENCES users(uuid)
        )");
        // Index DDL is portable between SQLite and MariaDB
        $this->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_password_reset_tokens_token ON password_reset_tokens(token)');
        $this->exec('CREATE INDEX IF NOT EXISTS idx_password_reset_tokens_user ON password_reset_tokens(user_uuid)');
    }

    private function createRecoveryRateLimit(): void
    {
        $this->exec('CREATE TABLE IF NOT EXISTS recovery_rate_limit (
            id ' . $this->pk() . ',
            ip_hash TEXT NOT NULL,
            requested_at TEXT NOT NULL
        )');
        // Index DDL is portable between SQLite and MariaDB
        $this->exec('CREATE INDEX IF NOT EXISTS idx_recovery_rate_limit_ip ON recovery_rate_limit(ip_hash)');
        $this->exec('CREATE INDEX IF NOT EXISTS idx_recovery_rate_limit_at ON recovery_rate_limit(requested_at)');
    }

    private function createLoginRateLimit(): void
    {
        $this->exec('CREATE TABLE IF NOT EXISTS login_rate_limit (
            id ' . $this->pk() . ',
            ip_hash TEXT NOT NULL,
            attempted_at TEXT NOT NULL
        )');
        // Index DDL is portable between SQLite and MariaDB
        $this->exec('CREATE INDEX IF NOT EXISTS idx_login_rate_limit_ip ON login_rate_limit(ip_hash)');
        $this->exec('CREATE INDEX IF NOT EXISTS idx_login_rate_limit_at ON login_rate_limit(attempted_at)');
    }

    private function createRegistrationRateLimit(): void
    {
        $this->exec('CREATE TABLE IF NOT EXISTS registration_rate_limit (
            id ' . $this->pk() . ',
            ip_hash TEXT NOT NULL,
            created_at TEXT NOT NULL
        )');
        // Index DDL is portable between SQLite and MariaDB
        $this->exec('CREATE INDEX IF NOT EXISTS idx_registration_rate_limit_ip ON registration_rate_limit(ip_hash)');
        $this->exec('CREATE INDEX IF NOT EXISTS idx_registration_rate_limit_at ON registration_rate_limit(created_at)');
    }

    private function createAgentIdentities(): void
    {
        $pk = $this->pk();
        $this->exec("CREATE TABLE IF NOT EXISTS agent_identities (
            id {$pk},
            agent_id TEXT NOT NULL,
            agent_name TEXT,
            provider TEXT NOT NULL,
            user_uuid TEXT NOT NULL,
            first_verified_at TEXT NOT NULL,
            last_verified_at TEXT NOT NULL,
            FOREIGN KEY (user_uuid) REFERENCES users(uuid)
        )");
        // Index DDL is portable between SQLite and MariaDB
        $this->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_agent_identities_agent_id ON agent_identities(agent_id)');
        $this->exec('CREATE INDEX IF NOT EXISTS idx_agent_identities_user ON agent_identities(user_uuid)');
    }

    private function createAgentRequests(): void
    {
        $pk = $this->pk();
        $this->exec("CREATE TABLE IF NOT EXISTS agent_requests (
            id {$pk},
            agent_id TEXT NOT NULL,
            requested_at TEXT NOT NULL
        )");
        // Index DDL is portable between SQLite and MariaDB
        $this->exec('CREATE INDEX IF NOT EXISTS idx_agent_requests_agent ON agent_requests(agent_id)');
        $this->exec('CREATE INDEX IF NOT EXISTS idx_agent_requests_at ON agent_requests(requested_at)');
    }

    private function createHooks(): void
    {
        $pk = $this->pk();
        $this->exec("CREATE TABLE IF NOT EXISTS hooks (
            id {$pk},
            event_name TEXT NOT NULL,
            webhook_url TEXT,
            enabled INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL
        )");
        // Index DDL is portable between SQLite and MariaDB
        $this->exec('CREATE INDEX IF NOT EXISTS idx_hooks_event ON hooks(event_name)');
    }

    private function createHookEvents(): void
    {
        $pk = $this->pk();
        $this->exec("CREATE TABLE IF NOT EXISTS hook_events (
            id {$pk},
            hook_id INTEGER,
            event_name TEXT NOT NULL,
            payload TEXT,
            fired_at TEXT NOT NULL,
            FOREIGN KEY (hook_id) REFERENCES hooks(id)
        )");
        // Index DDL is portable between SQLite and MariaDB
        $this->exec('CREATE INDEX IF NOT EXISTS idx_hook_events_event ON hook_events(event_name)');
        $this->exec('CREATE INDEX IF NOT EXISTS idx_hook_events_hook ON hook_events(hook_id)');
    }

    private function createInviteCodes(): void
    {
        $pk = $this->pk();
        $this->exec("CREATE TABLE IF NOT EXISTS invite_codes (
            id {$pk},
            code TEXT NOT NULL,
            created_by_user_uuid TEXT,
            used_by_user_uuid TEXT,
            used_at TEXT,
            created_at TEXT NOT NULL,
            FOREIGN KEY (created_by_user_uuid) REFERENCES users(uuid),
            FOREIGN KEY (used_by_user_uuid) REFERENCES users(uuid)
        )");
        // Index DDL is portable between SQLite and MariaDB
        $this->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_invite_codes_code ON invite_codes(code)');
    }

    private function createReviews(): void
    {
        $pk = $this->pk();
        // UNIQUE constraint is portable between SQLite and MariaDB
        $this->exec("CREATE TABLE IF NOT EXISTS reviews (
            id {$pk},
            transaction_uuid TEXT NOT NULL UNIQUE,
            store_uuid TEXT NOT NULL,
            rater_user_uuid TEXT NOT NULL,
            score INTEGER NOT NULL,
            comment TEXT,
            created_at TEXT NOT NULL,
            FOREIGN KEY (transaction_uuid) REFERENCES transactions(uuid),
            FOREIGN KEY (store_uuid) REFERENCES stores(uuid),
            FOREIGN KEY (rater_user_uuid) REFERENCES users(uuid)
        )");
        // Index DDL is portable between SQLite and MariaDB
        $this->exec('CREATE INDEX IF NOT EXISTS idx_reviews_store ON reviews(store_uuid)');
    }

    private function createStoreWarnings(): void
    {
        $pk = $this->pk();
        $this->exec("CREATE TABLE IF NOT EXISTS store_warnings (
            id {$pk},
            store_uuid TEXT NOT NULL,
            author_user_uuid TEXT NOT NULL,
            message TEXT NOT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT,
            resolved_at TEXT,
            acked_at TEXT,
            FOREIGN KEY (store_uuid) REFERENCES stores(uuid),
            FOREIGN KEY (author_user_uuid) REFERENCES users(uuid)
        )");
        // Index DDL is portable between SQLite and MariaDB
        $this->exec('CREATE INDEX IF NOT EXISTS idx_store_warnings_store ON store_warnings(store_uuid)');
    }

    private function createSupportTickets(): void
    {
        $pk = $this->pk();
        $this->exec("CREATE TABLE IF NOT EXISTS support_tickets (
            id {$pk},
            user_uuid TEXT NOT NULL,
            subject TEXT NOT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT,
            FOREIGN KEY (user_uuid) REFERENCES users(uuid)
        )");
        // Index DDL is portable between SQLite and MariaDB
        $this->exec('CREATE INDEX IF NOT EXISTS idx_support_tickets_user ON support_tickets(user_uuid)');
    }

    private function createSupportTicketMessages(): void
    {
        $pk = $this->pk();
        $this->exec("CREATE TABLE IF NOT EXISTS support_ticket_messages (
            id {$pk},
            ticket_id INTEGER NOT NULL,
            user_uuid TEXT NOT NULL,
            body TEXT NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY (ticket_id) REFERENCES support_tickets(id),
            FOREIGN KEY (user_uuid) REFERENCES users(uuid)
        )");
        // Index DDL is portable between SQLite and MariaDB
        $this->exec('CREATE INDEX IF NOT EXISTS idx_support_ticket_messages_ticket ON support_ticket_messages(ticket_id, created_at)');
    }

    private function createPrivateMessages(): void
    {
        $pk = $this->pk();
        $this->exec("CREATE TABLE IF NOT EXISTS private_messages (
            id {$pk},
            from_user_uuid TEXT NOT NULL,
            to_user_uuid TEXT NOT NULL,
            body TEXT NOT NULL,
            read_at TEXT,
            created_at TEXT NOT NULL,
            FOREIGN KEY (from_user_uuid) REFERENCES users(uuid),
            FOREIGN KEY (to_user_uuid) REFERENCES users(uuid)
        )");
        // Index DDL is portable between SQLite and MariaDB
        $this->exec('CREATE INDEX IF NOT EXISTS idx_private_messages_from ON private_messages(from_user_uuid, created_at)');
        $this->exec('CREATE INDEX IF NOT EXISTS idx_private_messages_to ON private_messages(to_user_uuid, created_at)');
    }

    private function createDepositWithdrawIntents(): void
    {
        $pk = $this->pk();
        $this->exec("CREATE TABLE IF NOT EXISTS deposit_withdraw_intents (
            id {$pk},
            deposit_uuid TEXT NOT NULL,
            to_address TEXT NOT NULL,
            requested_at TEXT NOT NULL,
            requested_by_user_uuid TEXT NOT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY (deposit_uuid) REFERENCES deposits(uuid),
            FOREIGN KEY (requested_by_user_uuid) REFERENCES users(uuid)
        )");
        // Index DDL is portable between SQLite and MariaDB
        $this->exec('CREATE INDEX IF NOT EXISTS idx_deposit_withdraw_intents_deposit ON deposit_withdraw_intents(deposit_uuid)');
        $this->exec('CREATE INDEX IF NOT EXISTS idx_deposit_withdraw_intents_status ON deposit_withdraw_intents(status)');
    }

    private function createAuditLog(): void
    {
        $pk = $this->pk();
        $this->exec("CREATE TABLE IF NOT EXISTS audit_log (
            id {$pk},
            actor_user_uuid TEXT NOT NULL,
            action_type TEXT NOT NULL,
            target_type TEXT NOT NULL,
            target_id TEXT NOT NULL,
            metadata TEXT,
            created_at TEXT NOT NULL,
            FOREIGN KEY (actor_user_uuid) REFERENCES users(uuid)
        )");
        // Index DDL is portable between SQLite and MariaDB
        $this->exec('CREATE INDEX IF NOT EXISTS idx_audit_log_actor ON audit_log(actor_user_uuid)');
        $this->exec('CREATE INDEX IF NOT EXISTS idx_audit_log_target ON audit_log(target_type, target_id)');
    }

    private function addV25Columns(): void
    {
        $this->addColumnIfMissing('stores', 'withdraw_address', 'TEXT');
        $this->addColumnIfMissing('transactions', 'buyer_confirmed_at', 'TEXT');
        $this->addColumnIfMissing('disputes', 'transaction_uuid', 'TEXT');
        $this->addColumnIfMissing('dispute_claims', 'user_uuid', 'TEXT');
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        if ($this->sqlite) {
            $stmt = $this->pdo->query("PRAGMA table_info({$table})");
            $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($columns as $col) {
                if (strcasecmp($col['name'], $column) === 0) {
                    return;
                }
            }
            $this->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        } else {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM {$table} LIKE " . $this->pdo->quote($column));
            if ($stmt->fetch() !== false) {
                return;
            }
            $this->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }
}
