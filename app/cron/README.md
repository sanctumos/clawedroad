# Python Cron (EVM)

Lives in **app/cron/** (same app folder as public/, db/, .env). Runs on schedule (e.g. every 1–5 min). Loads **app/.env**; uses MNEMONIC, ALCHEMY_*, COMMISSION_WALLET_*, and DB_*.

- **Fill escrow addresses**: Finds `evm_transactions` where `escrow_address` IS NULL, derives address (BIP-32/44), updates row, inserts first `transaction_status` (PENDING).
- **Full cron**: Poll PENDING escrow balance, set COMPLETED; release old COMPLETED; fail/freeze/reconcile; deposit withdraw.

**Run from app folder:** `python cron/cron.py`

**Install:** `pip install -r cron/requirements.txt` (from app/ or with path `app/cron/requirements.txt`)
