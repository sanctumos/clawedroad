# Codebase Inventory and Cross-Reference

## 1. Entry Points

| File | Purpose |
|------|---------|
| `main.go` | CLI: `server`, `sync-models`, `sync-views`, `update-deposits`, `user` (role), `index`, `import-metro`, `staff-stats`, `maintain-transactions`. |
| `server.go` | Runs crons (transactions, wallets, stats, SERP, messageboard, currency) and webserver (gocraft/web). |
| `commandline.go` | Implements sync-models, sync-views, update-deposits, user role, index, import-metro, staff-stats, maintain-transactions. |

## 2. Module Layout

### 2.1 `modules/marketplace/`

**Models (data + DB)**  
- `models.go` — DB init, SyncModels, SyncDatabaseViews; auto-migrate all entities.  
- `models_transaction.go` — Transaction, TransactionStatus, ShippingStatus; status machine; CreateTransactionForPackage; Find*Transaction*.  
- `models_transaction_cc_bitcoin.go` — BitcoinTransaction; UpdateTransactionStatus, Release, Cancel, PartialRefund (BTC).  
- `models_transaction_cc_ethereum.go` — EthereumTransaction; same (ETH).  
- `models_transaction_db_view.go` — CurrentTransactionStatus, views setup (v_transaction_statuses, v_current_*, vm_*), Refresh*MaterializedView.  
- `models_receipt.go` — PaymentReceipt; CreateBTCPaymentReceipt, CreateETHPaymentReceipt.  
- `models_referral.go` — ReferralPayment.  
- `models_deposit.go` — Deposit, DepositHistory.  
- `models_wallet_bitcoin.go` — UserBitcoinWallet, Balance, Action; CreateBitcoinWallet; balance views.  
- `models_wallet_ethereum.go` — UserEthereumWallet, Balance, Action; CreateEthereumWallet; balance views.  
- `models_user.go` — User (incl. Pgp, Bitcoin, Ethereum, 2FA); FindUserByUuid, CheckPassphrase, Iniviter, etc.  
- `models_store.go` — Store (incl. PGP, verification); StoreUser.  
- `models_api.go` — APISession; FindAPISessionByToken, CreateAPISession.  
- `models_package.go` — Package, PackagePrice; GetPrice(currency).  
- `models_currency.go` — CRYPTO/FIAT lists, UpdateCurrencyRates, GetCurrencyRate.  
- `models_*.go` — Item, Dispute, Message, Thread, Support, Reservation, etc. (see project layout).  

**Tasks (cron)**  
- `tasks_transaction.go` — TaskUpdatePendingTransactions, TaskFailOldPendingTransactions, TaskReleaseOldCompletedTransactions, TaskFreezeStuckCompletedTransactions, CancelCompletedAndNotDispatchedTransactions, TaskFinalizeReleasedAndCancelledTransactionsWithNonZeroAmount, etc.; StartTransactionsCron.  
- `tasks_wallet.go` — TaskUpdateRecentBitcoinWallets, TaskUpdateAllBitcoinWallets, same for Ethereum; StartWalletsCron.  
- `tasks_currency.go` — UpdateCurrencyRates; StartCurrencyCron.  
- `tasks_*.go` — SERP, messageboard, stats.  

**Commands**  
- `commands_deposits.go` — CommandUpdateDeposits (used by `update-deposits`).  

**Middleware**  
- `middleware_auth.go` — AuthMiddleware (session or ?token=), BotCheckMiddleware, AdminMiddleware, StaffMiddleware.  
- `middleware_wallet.go` — BitcoinWalletMiddleware, EthereumWalletMiddleware, WalletsMiddleware.  
- `middleware_*.go` — Security, rate limit, transaction, dispute, store, etc.  

**Views (HTTP handlers)**  
- `views_auth.go` — Login, register, recover, PGP login/setup, vendorship agreement (PGP sign).  
- `views_api.go` — All /api/* handlers (serp, deals, categories, transactions, wallet, settings, PGP setup, item CMS, support, messages, disputes, verification, staff).  
- `views_transaction.go`, `views_wallet*.go`, `views_deposit.go`, etc.  

**Router**  
- `router.go` — ConfigureRouter: middleware stack, /api/* routes, logged-in routes, staff routes.  

**Context / session**  
- `context.go` — Context struct (ViewUser, ViewUserStore, APISession, etc.).  
- `session.go` — session store.  
- `settings.go` — MARKETPLACE_SETTINGS, regexps.  

**Localization**  
- `localization.go` — Strings (incl. PGP, login).  

### 2.2 `modules/apis/`

- `payments_bitcoin.go` — GenerateBTCAddress, GetAmountOnBTCAddress, SendBTC*, EstimateBTCFee* (PaymentGate HTTP).  
- `payments_ethereum.go` — GenerateETHAddress, GetAmountOnETHAddress, SendETH (PaymentGate HTTP).  
- `currency.go` — GetCurrencyRates (external API).  
- `settings.go` — APPLICATION_SETTINGS (PaymentGate, etc.).  
- `mattermost.go` — Notifications (optional).  

### 2.3 `modules/settings/`

- `settings.go` — MarketplaceSettings (pending/completed/stuck duration, commission, referral, EthereumCommissionWallet, PaymentGate, etc.); load from settings.json.  

### 2.4 `modules/util/`

- `pgp.go` — ValidatePGPPublicKey, EncryptText, CheckSignature, Fingerprint (openpgp).  
- `password.go` — PasswordHashV1.  
- `context.go`, `logging.go`, `network.go`, etc.  

## 3. Where Accounting Lives

- **Status machine / amounts**: `models_transaction.go` (SetTransactionStatus, CurrentAmountPaid, TransactionAmount, Release, Cancel, PartialRefund, Complete, Fail, Freeze, MakePending).  
- **Release/Cancel/PartialRefund (BTC)**: `models_transaction_cc_bitcoin.go`.  
- **Release/Cancel/PartialRefund (ETH)**: `models_transaction_cc_ethereum.go`.  
- **Receipts**: `models_receipt.go`.  
- **Referral**: `models_referral.go`; created in BTC/ETH Release paths.  
- **Deposits**: `models_deposit.go` (Update, Withdraw, DepositHistory).  
- **User wallet → escrow**: `models_transaction.go` FundFromUserWallets; wallet send in `models_wallet_*.go`. **Reference only; out of MVP** (08: buyer sends from external wallet only).  
- **Cron**: `tasks_transaction.go`, `tasks_wallet.go`.  
- **Views (current status)**: `models_transaction_db_view.go` (setupTransactionStatusesView, v_current_*, vm_*).  

## 4. Where Auth / API Keys Live

- **Session / token auth**: `middleware_auth.go` (session or ?token=; FindAPISessionByToken).  
- **APISession**: `models_api.go`.  
- **Password**: `models_user.go` CheckPassphrase; `util.PasswordHashV1`.  
- **PGP login/setup**: `views_auth.go`, `views_api.go` (settings pgp/step1, step2); `util/pgp.go`.  

## 5. Where Dark-Web / PGP Lives

- See **02-DARK-WEB-STRIP-OUT.md** (files and what to strip).  
- Summary: `util/pgp.go`; User/Store Pgp fields and validation; views_auth.go (LoginPGP, SetupPGPViaDecryption*); views_api.go (ViewAPISetupPGPViaDecryption*); router (pgp/step1, step2); messageboard PGP detection; localization; vendorship agreement signing.  

## 6. Where EVM / Payments Live

- **Ethereum**: `modules/apis/payments_ethereum.go`; `models_transaction_cc_ethereum.go`; `models_wallet_ethereum.go`; `models_deposit.go` (ETH branch); views/tasks for ETH.  
- **Bitcoin**: Same pattern under *bitcoin*; to be removed for EVM-only.  

## 7. Config and Data

- **settings.json.example** — Duration, commission, referral, PaymentGate, EthereumCommissionWallet, etc.  
- **data/i18n/*.json** — Strings (incl. PGP, login).  
- **dumps/** — cities.sql, countries.sql, metro JSON.  

## 8. Templates and Static

- **templates/** — Amber templates (auth, board, deposit, dispute, item, package, transaction, wallet, staff, etc.).  
- **static/** — Static help pages.  
- **public/** — Assets.  

Use this inventory to map current Go modules to PHP (web + API) and Python (crypto + cron) when re-implementing.
