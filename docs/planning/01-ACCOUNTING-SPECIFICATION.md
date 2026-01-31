# Accounting Specification (Gating Document)

This document exhaustively describes all money and accounting flows in the current Tochka codebase so they can be re-implemented correctly in PHP + Python. **Treat this as the single source of truth for accounting.**

**Planning decisions (08-PLANNING-DECISIONS-QA.md):** All hardcoded values below are **defaults** (configurable in admin). **Vendor referral** is **roadmap only**. **In-app buyer wallets / “fund from wallet” are not in MVP** (08: buyer sends from external wallet only); treat those sections as **Roadmap**.

---

## 1. Core Concepts

### 1.1 Transaction

- **Identity**: `Transaction.Uuid` (primary key). **Re-implementation (binding):** PHP creates the transaction **without** an escrow address; the escrow address is a separate field filled by **Python (cron)** after creation. Until Python writes it, `escrow_address` is null and the UI shows "Escrow address pending — may take up to 60 seconds." See [05-ARCHITECTURE-LEMP-PYTHON.md](05-ARCHITECTURE-LEMP-PYTHON.md) §8. The escrow address is where the buyer sends funds.
- **Type**: `bitcoin` or `ethereum`. Target: **ethereum** only (and possibly a generic `evm` or per-token type).
- **Parties**: `BuyerUuid`, `StoreUuid` (vendor), optional `DisputeUuid`.
- **Package**: `PackageUuid` → one order (item + quantity + shipping). Price is fixed at creation in crypto (BTC/ETH) via `Package.GetPrice(currency)` and shipping in that currency.

### 1.2 Transaction Status (Payment State)

Status is **append-only**: each change adds a row to `transaction_statuses` with (Time, Amount, Status, Comment, UserUuid, PaymentReceiptUuid).

**Allowed statuses**:

- `NEW` — Initial (no row yet; first row is usually PENDING).
- `PENDING` — Awaiting payment to escrow address. `current_amount` can increase as funds arrive.
- `COMPLETED` — Escrow funded (within 5% of required amount). Ready for vendor to ship and then release.
- `RELEASED` — Funds sent to vendor (+ commission/referral split). Terminal for success path.
- `CANCELLED` — Funds returned to buyer. Terminal.
- `FAILED` — No payment in time or manual fail. Terminal.
- `FROZEN` — Dispute or admin action; funds held.

**State machine (simplified)**:

```
PENDING → COMPLETED → RELEASED
   |           |
   → FAILED    → FROZEN → (dispute resolution → RELEASED or CANCELLED / partial refund)
   |           → CANCELLED (e.g. not dispatched in time)
```

**Critical**: “Current” status and amount are derived from the **latest** row in `transaction_statuses` (see DB views below). Never overwrite; only insert.

### 1.3 Shipping Status

Separate append-only stream: `shipping_statuses` (Time, Status, Comment, UserUuid, TransactionUuid).

- `DISPATCH PENDING` — Default; not yet marked shipped.
- `DISPATCHED` — Vendor marked as dispatched.
- `SHIPPED` — (if used) shipped.

Used for auto-release and auto-freeze rules (e.g. “completed but not dispatched in 72h → freeze”).

### 1.4 Amounts and Currency

- **Transaction amount** (required to complete): Stored on `BitcoinTransaction.Amount` or `EthereumTransaction.Amount` (crypto units: BTC or ETH). Computed at creation as:
  - `itemPackage.GetPrice("BTC")*quantity + shippingPrice` (BTC), or
  - `itemPackage.GetPrice("ETH")*quantity + shippingPrice` (ETH).
- **Current amount paid**: From latest `transaction_statuses.Amount` for that transaction (what’s actually in escrow / paid).
- **Completion rule**: In `UpdateTransactionStatus`, when status is PENDING and `(required - current) <= required*0.05`, status is set to COMPLETED (“Transaction funded”). So 95%+ of required amount completes.

---

## 2. Commission and Account Tiers

### 2.1 Commission Rate (Vendor)

Commission is a **percent of the transaction** taken from the escrow on **Release**. Stored conceptually on `Transaction` via `Store`:

- `Transaction.CommissionPercent()`:
  - Gold: `GoldAccountCommission`
  - Silver: `SilverAccountCommission`
  - Bronze: `BronzeAccountCommission`
  - Default: `FreeAccountCommission`

From `settings.json.example`: e.g. 0.02 / 0.05 / 0.1 / 0.2 (2%–20%).

### 2.2 Referral Percent (From Commission)

Out of the **commission** (not out of full tx amount):

- **Buyer inviter**: Gets `commission * inviterPercent`. Inviter percent depends on inviter’s account tier (Gold/Silver/Bronze/Free) or, for mobile app purchases, `AndroidDeveloperCommission` / `AndroidDeveloperUsername`.
- **Vendor inviter**: Same idea; `vendorInviter` from store’s inviter. (Ethereum path has a TODO: vendor referral not fully wired in code but structure exists.)

Referral is paid **at Release** to inviter’s wallet (Bitcoin or Ethereum address), and a `ReferralPayment` record is created (TransactionUuid, UserUuid, ReferralPercent, ReferralPaymentBTC/ETH, ReferralPaymentUSD, IsBuyerReferral).

---

## 3. Release Flow (Escrow → Payouts)

### 3.1 Bitcoin Release (Reference Only; You Will Drop BTC)

- **From**: Escrow address = `Transaction.Uuid`.
- **To** (percent split):
  - Vendor: `(1 - commission)`.
  - Buyer inviter (if any): `commission * inviterPercent`.
  - Vendor inviter (if any): `commission * inviterPercent`.
  - (Multisig: no actual move; just set status RELEASED.)
- **API**: `apis.SendBTCFromSingleWalletWithPercentSplit(addressFrom, payments)`.
- **Receipt**: `CreateBTCPaymentReceipt(result)`; then `SetTransactionStatus("RELEASED", currentAmount, comment, userUuid, &receipt)` and save `ReferralPayment` rows.

### 3.2 Ethereum Release (Template for EVM)

- **From**: Escrow address = `Transaction.Uuid`.
- **To** (percent split):
  - Vendor: `(1 - commission)`.
  - Buyer inviter (if any): `commission * inviterPercent`.
  - **Commission wallet**: The remainder so that percents sum to 1.0. `MARKETPLACE_SETTINGS.EthereumCommissionWallet` gets `commisionPercent = 1.0 - sum(payments)`.
- **API**: `apis.SendETH(addressFrom, payments)` (PaymentGate). Target: replace with Alchemy (or internal Python) sending ETH/token to same split.
- **Receipt**: `CreateETHPaymentReceipt(results)`; then `SetTransactionStatus("RELEASED", ...)` and save buyer (and optionally vendor) `ReferralPayment`.

**Invariant**: Sum of all payout percents = 1.0. No double-spend: status is set to RELEASED only once (check `t.Status[len(t.Status)-1].Status != "RELEASED"` before paying).

---

## 4. Cancel Flow (Refund to Buyer)

- **From**: Escrow address.
- **To**: Buyer’s refund address. **Reference (v1)**: e.g. `FindRecentEthereumWallet().PublicKey` (in-app wallet). **MVP (08)**: Buyer-provided refund address (e.g. at checkout or on profile); no in-app buyer wallet.
- **Percent**: 1.0 to buyer.
- **After**: `SetTransactionStatus("CANCELLED", currentAmount, comment, userUuid, &receipt)`.

**Invariant**: Only one CANCELLED status per transaction (idempotency check before sending).

---

## 5. Dispute Partial Refund

Used when staff resolves a dispute with a split (e.g. 60% vendor, 40% buyer).

- **From**: Escrow address.
- **To** (percent split):
  - Vendor: `(1 - refundPercent) - 0.05`
  - Buyer: `refundPercent - 0.05`
  - Resolver (staff): `0.10` (10%)
- So: 0.90 to parties, 0.10 to resolver; the 0.05 each is likely fee/tolerance (document in re-implementation).
- **After**: Status set to `CANCELLED` (partial refund is still a “close” of escrow).

**Invariant**: `(1 - refundPercent) - 0.05 + (refundPercent - 0.05) + 0.1 = 1.0`. **Resolver** is **always staff**; staff user must have an EVM address for the 10% payout (08).

---

## 6. Payment Receipts

- Every on-chain send (release, cancel, partial refund) creates a **PaymentReceipt**: Uuid (= tx hash), Type (bitcoin/ethereum), SerializedData (JSON of API result), Version.
- **TransactionStatus** can link to a receipt: `PaymentReceiptUuid`. Used for audit and display.

**Re-implementation**: Store EVM tx hash and optional receipt payload; same idea.

---

## 7. Deposits (Vendor Insurance/Deposit)

- **Deposit**: StoreUuid, Currency, FiatValue, Crypto (BTC/ETH), CryptoValue, CurrencyRate, Address (crypto address).
- **DepositHistory**: Append-only (Action, Value, DepositUuid). Used to track “Deposit withdrawn” etc.
- **Update**: For each deposit, check balance on `Address` (BTC/ETH API). If balance 0, create history “Deposit withdrawn”.
- **Withdraw**: Send from deposit address to store admin’s wallet (100%). Separate from escrow.

Deposits are **not** part of the main escrow accounting; they are vendor-side balances. **Vendor deposits are in MVP** (08). For EVM-only: ETH (and optionally configured tokens); balance and withdraw in Python via Alchemy.

---

## 8. User Wallets (In-App) — **Roadmap, Not MVP (08)**

- **Reference (v1)**: UserBitcoinWallet / UserEthereumWallet; `Transaction.FundFromUserWallets(user)` sends from user wallet to escrow, then sets PENDING. Balance from chain, stored in Balance tables.
- **MVP (08)**: **Buyer sends from external wallet only.** No in-app buyer wallets; no “fund from user wallet.” We show escrow address (and optional QR); buyer pays from their own wallet (MetaMask, etc.). Do **not** implement FundFromUserWallets or buyer hot wallets in MVP.
- **Cancel/refund in MVP**: Refund to buyer on Cancel/partial refund uses a **buyer-provided refund address** (e.g. at checkout or on profile), not an in-app wallet.
- **Roadmap**: In-app user wallets and “fund from wallet” can be added later; wallet balance views (10.3) are then relevant.

---

## 9. Cron / Background Tasks (Accounting-Relevant)

- **TaskUpdatePendingTransactions**: For each PENDING tx, refresh status and call `UpdateTransactionStatus()` (poll escrow balance; if enough, set COMPLETED).
- **TaskFailOldPendingTransactions**: PENDING older than `pending_duration` → Fail (set FAILED).
- **TaskReleaseOldCompletedTransactions**: COMPLETED older than `completed_duration` → Release (auto-release).
- **TaskFreezeStuckCompletedTransactions**: COMPLETED older than `stuck_duration` → Freeze.
- **CancelCompletedAndNotDispatchedTransactions**: COMPLETED + DISPATCH PENDING and created ≥ 72h ago → Freeze (“not dispatched in 3 days”).
- **TaskUpdateBalancesOrRecentlyReleasedAndCancelledTransactions** / **TaskFinalizeReleasedAndCancelledTransactionsWithNonZeroAmount**: Reconcile status with chain (update amount/status from chain for recently released/cancelled).

**Re-implementation**: Same logic in Python cron (scheduled run; polls Alchemy; updates DB). Durations from config (e.g. `pending_duration`, `completed_duration`, `stuck_duration`).

---

## 10. Database Views (Current)

These views are **critical** for queries; re-implement equivalent in MariaDB/Postgres.

### 10.1 Transaction status aggregation

- **v_transaction_statuses**: Min/max (time, amount, status) per transaction.
- **v_current_transaction_statuses**: Per transaction: uuid, description, type, package_uuid, store_uuid, buyer_uuid, dispute_uuid, **current_status** (max_status), **current_amount** (max_amount), updated_at, created_at, **current_shipping_status**, number_of_messages, storename, buyer_username.
- **v_current_bitcoin_transaction_statuses** / **v_current_ethereum_transaction_statuses**: Join with bitcoin_transactions / ethereum_transactions (amount, etc.).
- **v_current_cummulative_transaction_statuses**: UNION of BTC and ETH current views (for “all transactions” listing).

All “find by status” and “current status” logic use these views so that **current** = latest row in `transaction_statuses` / `shipping_statuses`.

### 10.2 Materialized views

- **vm_shipping_statuses**: Materialized copy of v_shipping_statuses (refreshed periodically).
- **vm_thread_counts**: Thread counts for transactions (messages).

### 10.3 Wallet balance views (Roadmap, not MVP)

- **v_user_bitcoin_wallet_balances** / **v_user_ethereum_wallet_balances**: Per user, sum of latest balance per wallet. **Not needed in MVP** (08: no in-app buyer wallets). Implement when/if “fund from wallet” is added.

---

## 11. Invariants (Checklist for Re-Implementation)

1. **Single current status**: Current payment state = latest row in `transaction_statuses`. No updates to past rows.
2. **No double release**: Before sending release payout, check last status ≠ RELEASED; then set RELEASED once with receipt.
3. **No double cancel**: Same for CANCELLED.
4. **Percents sum to 1.0**: On Release (vendor + referral + commission) and on Partial Refund (vendor + buyer + resolver).
5. **Completion threshold**: COMPLETED when paid amount ≥ 95% of required (or exact rule from code).
6. **Receipt per send**: Every on-chain send has a PaymentReceipt (hash + payload) and optionally linked to TransactionStatus.
7. **Referral only on release**: ReferralPayment rows created only when status is set to RELEASED and inviter exists.

---

## 12. Config (Accounting-Relevant)

**Per 08-PLANNING-DECISIONS-QA.md:** These values are **defaults**; admin panel must allow configuring them (stored in config/settings table).

From `settings.json.example` / `MarketplaceSettings` (reference):

- **pending_duration**: e.g. "24h" — fail PENDING after this.
- **completed_duration**: e.g. "336h" — auto-release COMPLETED after this.
- **stuck_duration**: e.g. "720h" — freeze COMPLETED after this.
- **Gold/Silver/Bronze/FreeAccountCommission**: Vendor commission.
- **Gold/Silver/Bronze/FreeAccountReferralPercent**: Referral share of commission.
- **EthereumCommissionWallet**: Address receiving remainder on ETH release.
- **AndroidDeveloperUsername** / **AndroidDeveloperCommission**: For mobile-app buyer referral.

---

## 13. Files to Mirror Logic From (Go)

- `modules/marketplace/models_transaction.go` — Status, amounts, Release/Cancel/Fail/Freeze. **Omit FundFromUserWallets in MVP** (08: buyer external-wallet-only).
- `modules/marketplace/models_transaction_cc_bitcoin.go` — Release/Cancel/PartialRefund (BTC); use as reference for percent splits.
- `modules/marketplace/models_transaction_cc_ethereum.go` — Release/Cancel/PartialRefund (ETH); template for EVM.
- `modules/marketplace/models_receipt.go` — PaymentReceipt creation.
- `modules/marketplace/models_referral.go` — ReferralPayment.
- `modules/marketplace/models_deposit.go` — Deposit/DepositHistory.
- `modules/marketplace/tasks_transaction.go` — All cron tasks above.
- `modules/marketplace/models_transaction_db_view.go` — View definitions (setupTransactionStatusesView, etc.) and queries using v_current_cummulative_transaction_statuses.

Once this spec is agreed and re-implemented in PHP (persistence + API) and Python (cron logic), accounting is gated and safe to run in production. **Vendor referral** is on the roadmap, not MVP (see 08).
