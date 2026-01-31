#!/usr/bin/env python3
"""
Cron entrypoint. Run on schedule (e.g. every 1-5 min).
1) Fill escrow addresses; 2) Update PENDING (poll balance -> COMPLETED);
3) Fail old PENDING; 4) (Release/freeze/reconcile/deposits - full in Phase 6.)
"""
import sys
from pathlib import Path

# baseDir = app/ (parent of cron/); .env and db/ are in app/
BASE_DIR = str(Path(__file__).resolve().parent.parent)

def main():
    sys.path.insert(0, str(Path(__file__).resolve().parent))
    from env import load_dotenv, get_required, get
    from db import get_connection
    from escrow import derive_escrow_address
    from tasks import run_fill_escrow, run_update_pending, run_fail_old_pending
    from alchemy_client import get_balance_wei, wei_to_eth

    load_dotenv(BASE_DIR)
    mnemonic = get_required("MNEMONIC")
    api_key = get("ALCHEMY_API_KEY", "")
    network = get("ALCHEMY_NETWORK", "mainnet")

    conn = get_connection(BASE_DIR)
    conn.row_factory = None
    cur = conn.cursor()

    def config_get(key, default=""):
        cur.execute("SELECT value FROM config WHERE key = ?", (key,))
        row = cur.fetchone()
        return row[0] if row else default

    def get_balance_eth(address, chain_id):
        if not api_key:
            return 0.0
        wei = get_balance_wei(address, api_key, network)
        return wei_to_eth(wei)

    run_fill_escrow(conn, mnemonic, derive_escrow_address)
    if api_key:
        run_update_pending(conn, get_balance_eth, tolerance=0.05)
        run_fail_old_pending(conn, config_get)
    conn.close()
    print("Cron run done.")

if __name__ == "__main__":
    main()
