"""
Cron tasks per 01 §9: update PENDING (poll balance -> COMPLETED), fail old PENDING,
release old COMPLETED, freeze stuck, cancel not-dispatched, reconcile, deposit withdraw.
Uses config for durations; Alchemy for balance/price.
"""
from datetime import datetime, timedelta
import json
import re
import traceback
import uuid
from typing import Optional

from alchemy_client import eth_gas_price

_ADDR_RE = re.compile(r"^0x[0-9a-fA-F]{40}$")

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


def run_fill_deposit_address(conn, mnemonic, derive_deposit_address):
    """Fill deposits.address for rows where address IS NULL. v2.5 Vendor CMS."""
    cur = conn.cursor()
    cur.execute("SELECT uuid FROM deposits WHERE (address IS NULL OR address = '') AND deleted_at IS NULL")
    rows = cur.fetchall()
    now = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
    for (deposit_uuid,) in rows:
        address = derive_deposit_address(mnemonic, deposit_uuid)
        cur.execute(
            "UPDATE deposits SET address = ?, updated_at = ? WHERE uuid = ?",
            (address, now, deposit_uuid),
        )
    conn.commit()


def run_update_deposit_balances(conn, get_balance_eth):
    """Update deposits.crypto_value from chain balance for deposits that have an address. v2.5."""
    cur = conn.cursor()
    cur.execute("""
        SELECT d.uuid, d.address, d.crypto
        FROM deposits d
        WHERE d.address IS NOT NULL AND d.address != '' AND d.deleted_at IS NULL
    """)
    deposits = cur.fetchall()
    now = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
    for deposit_uuid, address, crypto in deposits:
        cur.execute("SELECT chain_id FROM accepted_tokens WHERE symbol = ? LIMIT 1", (crypto,))
        row = cur.fetchone()
        if not row:
            continue
        chain_id = row[0]
        try:
            balance = get_balance_eth(address, chain_id)
        except Exception:
            continue
        cur.execute(
            "UPDATE deposits SET crypto_value = ?, updated_at = ? WHERE uuid = ?",
            (balance, now, deposit_uuid),
        )
    conn.commit()


def run_process_withdraw_intents(conn):
    """
    Process deposit_withdraw_intents with status 'pending'.
    Stub: mark as completed and insert deposit_history (withdrawal). Real implementation
    would sign and send tx from deposit address to to_address.
    """
    cur = conn.cursor()
    cur.execute("""
        SELECT id, deposit_uuid, to_address, requested_at, requested_by_user_uuid
        FROM deposit_withdraw_intents
        WHERE status = 'pending'
    """)
    rows = cur.fetchall()
    now = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
    for row in rows:
        intent_id, deposit_uuid, to_address, requested_at, requested_by = row
        cur.execute("SELECT crypto_value FROM deposits WHERE uuid = ?", (deposit_uuid,))
        dep = cur.fetchone()
        if not dep:
            cur.execute("UPDATE deposit_withdraw_intents SET status = 'failed' WHERE id = ?", (intent_id,))
            continue
        amount = float(dep[0] or 0)
        if amount <= 0:
            cur.execute("UPDATE deposit_withdraw_intents SET status = 'failed' WHERE id = ?", (intent_id,))
            continue
        # Stub: record withdrawal in history and mark intent completed. Real impl would send tx.
        history_uuid = uuid.uuid4().hex
        cur.execute(
            """INSERT INTO deposit_history (uuid, deposit_uuid, action, value, created_at)
               VALUES (?, ?, 'withdraw', ?, ?)""",
            (history_uuid, deposit_uuid, -amount, now),
        )
        cur.execute("UPDATE deposits SET crypto_value = 0, updated_at = ? WHERE uuid = ?", (now, deposit_uuid))
        cur.execute("UPDATE deposit_withdraw_intents SET status = 'completed' WHERE id = ?", (intent_id,))
    conn.commit()


def _valid_evm_address(addr: Optional[str]) -> bool:
    return bool(addr and _ADDR_RE.match(addr.strip()))


def _append_transaction_status(cur, tx_uuid: str, amount: float, status: str, comment: str) -> None:
    now = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
    cur.execute(
        """INSERT INTO transaction_statuses (transaction_uuid, time, amount, status, comment, created_at)
           VALUES (?, ?, ?, ?, ?, ?)""",
        (tx_uuid, now, amount, status, comment, now),
    )


def _fail_intent(cur, conn, intent_id: int, msg: str) -> None:
    cur.execute("UPDATE transaction_intents SET status = 'failed' WHERE id = ?", (intent_id,))
    conn.commit()


def _complete_intent(cur, conn, intent_id: int) -> None:
    cur.execute("UPDATE transaction_intents SET status = 'completed' WHERE id = ?", (intent_id,))
    conn.commit()


def run_process_transaction_intents(
    conn,
    mnemonic: str,
    api_key: str,
    network: str,
    derive_escrow_account_fn,
    get_balance_wei_fn,
    eth_native_transfer_wei_fn,
    eth_native_send_value_wei_fn,
) -> None:
    """
    Process pending transaction_intents (RELEASE, CANCEL, PARTIAL_REFUND): sign native ETH
    transfers from HD escrow and broadcast via Alchemy. Requires ALCHEMY_API_KEY; MVP is native ETH only.
    """
    if not api_key:
        return

    cur = conn.cursor()
    cur.execute(
        "SELECT id, transaction_uuid, action, params FROM transaction_intents WHERE status = 'pending' ORDER BY requested_at ASC"
    )
    rows = cur.fetchall()

    for intent_id, tx_uuid, action, params_json in rows:
        try:
            cur.execute(
                "SELECT current_status FROM v_current_cumulative_transaction_statuses WHERE uuid = ?",
                (tx_uuid,),
            )
            st = cur.fetchone()
            pay_status = (st[0] if st else None) or ""

            cur.execute(
                "SELECT escrow_address, chain_id, currency FROM evm_transactions WHERE uuid = ?",
                (tx_uuid,),
            )
            evm = cur.fetchone()
            if not evm or not evm[0]:
                _fail_intent(cur, conn, intent_id)
                continue
            escrow_address, chain_id, currency = evm[0], int(evm[1]), (evm[2] or "ETH").upper()
            if currency != "ETH":
                _fail_intent(cur, conn, intent_id)
                continue

            cur.execute(
                """
                SELECT t.refund_address, s.withdraw_address, t.dispute_uuid
                FROM transactions t
                JOIN stores s ON s.uuid = t.store_uuid
                WHERE t.uuid = ?
                """,
                (tx_uuid,),
            )
            row = cur.fetchone()
            if not row:
                _fail_intent(cur, conn, intent_id)
                continue
            refund_address, withdraw_address, dispute_uuid = row[0], row[1], row[2]

            acct = derive_escrow_account_fn(mnemonic, tx_uuid)
            if acct.address.lower() != (escrow_address or "").lower():
                _fail_intent(cur, conn, intent_id)
                continue

            def balance_of(addr: str) -> int:
                return get_balance_wei_fn(addr, api_key, network)

            if action == "RELEASE":
                if pay_status != "COMPLETED":
                    _fail_intent(cur, conn, intent_id)
                    continue
                if not _valid_evm_address(withdraw_address):
                    _fail_intent(cur, conn, intent_id)
                    continue
                tx_hash = eth_native_transfer_wei_fn(
                    acct, withdraw_address.strip(), chain_id, api_key, network, balance_of
                )
                _append_transaction_status(
                    cur, tx_uuid, 0.0, "RELEASED", f"Released to vendor (tx {tx_hash})"
                )
                _complete_intent(cur, conn, intent_id)

            elif action == "CANCEL":
                if pay_status != "PENDING":
                    _fail_intent(cur, conn, intent_id)
                    continue
                if not _valid_evm_address(refund_address):
                    _fail_intent(cur, conn, intent_id)
                    continue
                tx_hash = eth_native_transfer_wei_fn(
                    acct, refund_address.strip(), chain_id, api_key, network, balance_of
                )
                _append_transaction_status(
                    cur, tx_uuid, 0.0, "CANCELLED", f"Refunded buyer (tx {tx_hash})"
                )
                _complete_intent(cur, conn, intent_id)

            elif action == "PARTIAL_REFUND":
                # Dispute flow freezes the transaction (FROZEN) while dispute is open.
                if pay_status != "FROZEN":
                    _fail_intent(cur, conn, intent_id)
                    continue
                if not dispute_uuid:
                    _fail_intent(cur, conn, intent_id)
                    continue
                cur.execute("SELECT status FROM disputes WHERE uuid = ?", (dispute_uuid,))
                drow = cur.fetchone()
                dstatus = (drow[0] or "").lower() if drow else ""
                if dstatus != "open":
                    _fail_intent(cur, conn, intent_id)
                    continue
                try:
                    params = json.loads(params_json or "{}")
                    pct = float(params.get("refund_percent", 0))
                except (json.JSONDecodeError, TypeError, ValueError):
                    _fail_intent(cur, conn, intent_id)
                    continue
                if pct <= 0 or pct > 100:
                    _fail_intent(cur, conn, intent_id)
                    continue
                if not _valid_evm_address(refund_address) or not _valid_evm_address(withdraw_address):
                    _fail_intent(cur, conn, intent_id)
                    continue

                gas_price = eth_gas_price(api_key, network)
                fee = gas_price * 21_000
                bal = balance_of(acct.address)
                total_sendable = bal - 2 * fee
                if total_sendable <= 0:
                    _fail_intent(cur, conn, intent_id)
                    continue
                buyer_wei = int(total_sendable * pct / 100.0)
                vendor_wei = total_sendable - buyer_wei
                if buyer_wei <= 0 or vendor_wei <= 0:
                    _fail_intent(cur, conn, intent_id)
                    continue

                h1 = eth_native_send_value_wei_fn(
                    acct,
                    refund_address.strip(),
                    buyer_wei,
                    chain_id,
                    api_key,
                    network,
                    balance_of,
                )
                h2 = eth_native_send_value_wei_fn(
                    acct,
                    withdraw_address.strip(),
                    vendor_wei,
                    chain_id,
                    api_key,
                    network,
                    balance_of,
                )
                _append_transaction_status(
                    cur,
                    tx_uuid,
                    0.0,
                    "RELEASED",
                    f"Partial refund: buyer tx {h1}, vendor tx {h2}",
                )
                _complete_intent(cur, conn, intent_id)
            else:
                _fail_intent(cur, conn, intent_id)
        except Exception:
            traceback.print_exc()
            _fail_intent(cur, conn, intent_id)
