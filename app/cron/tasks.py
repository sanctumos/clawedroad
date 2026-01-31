"""
Cron tasks per 01 §9: update PENDING (poll balance -> COMPLETED), fail old PENDING,
release old COMPLETED, freeze stuck, cancel not-dispatched, reconcile, deposit withdraw.
Uses config for durations; Alchemy for balance/price.
"""
from datetime import datetime, timedelta
import re

def _parse_duration(s: str) -> timedelta:
    """Parse '24h', '336h', '720h' into timedelta."""
    if not s:
        return timedelta(hours=24)
    m = re.match(r"^(\d+)(h|m|d)$", s.strip().lower())
    if not m:
        return timedelta(hours=24)
    n, unit = int(m.group(1)), m.group(2)
    if unit == "h":
        return timedelta(hours=n)
    if unit == "m":
        return timedelta(minutes=n)
    if unit == "d":
        return timedelta(days=n)
    return timedelta(hours=24)

def run_fill_escrow(conn, mnemonic, escrow_derive):
    """Fill escrow_address for evm_transactions where NULL; insert first PENDING status."""
    cur = conn.cursor()
    cur.execute(
        "SELECT uuid FROM evm_transactions WHERE escrow_address IS NULL OR escrow_address = ''"
    )
    rows = cur.fetchall()
    now = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
    for (tx_uuid,) in rows:
        address = escrow_derive(mnemonic, tx_uuid)
        cur.execute(
            "UPDATE evm_transactions SET escrow_address = ?, updated_at = ? WHERE uuid = ?",
            (address, now, tx_uuid),
        )
        cur.execute(
            """INSERT INTO transaction_statuses (transaction_uuid, time, amount, status, comment, created_at)
               VALUES (?, ?, 0, 'PENDING', 'Escrow address created', ?)""",
            (tx_uuid, now, now),
        )
    conn.commit()

def run_update_pending(conn, get_balance_eth, tolerance=0.05):
    """
    For each PENDING tx with escrow_address, get balance; if current >= (1-tolerance)*required, insert COMPLETED.
    """
    cur = conn.cursor()
    cur.execute("""
        SELECT v.uuid, v.escrow_address, v.required_amount, v.current_amount, v.chain_id
        FROM v_current_evm_transaction_statuses v
        WHERE v.current_status = 'PENDING' AND v.escrow_address IS NOT NULL AND v.escrow_address != ''
    """)
    rows = cur.fetchall()
    now = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
    for row in rows:
        tx_uuid, escrow_address, required, current_amt, chain_id = row
        if not required or float(required) <= 0:
            continue
        balance_eth = get_balance_eth(escrow_address, chain_id)
        if balance_eth >= float(required) * (1 - tolerance):
            cur.execute(
                """INSERT INTO transaction_statuses (transaction_uuid, time, amount, status, comment, created_at)
                   VALUES (?, ?, ?, 'COMPLETED', 'Transaction funded', ?)""",
                (tx_uuid, now, balance_eth, now),
            )
    conn.commit()

def run_fail_old_pending(conn, config_get):
    """PENDING older than pending_duration -> insert FAILED."""
    duration = _parse_duration(config_get("pending_duration", "24h"))
    cutoff = (datetime.utcnow() - duration).strftime("%Y-%m-%d %H:%M:%S")
    cur = conn.cursor()
    cur.execute("""
        SELECT v.uuid FROM v_current_evm_transaction_statuses v
        WHERE v.current_status = 'PENDING' AND v.created_at < ?
    """, (cutoff,))
    rows = cur.fetchall()
    now = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
    for (tx_uuid,) in rows:
        cur.execute(
            """INSERT INTO transaction_statuses (transaction_uuid, time, amount, status, comment, created_at)
               VALUES (?, ?, 0, 'FAILED', 'Pending timeout', ?)""",
            (tx_uuid, now, now),
        )
    conn.commit()
