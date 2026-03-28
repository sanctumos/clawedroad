"""Tests for transaction intent processing and helpers."""
from unittest.mock import MagicMock

import pytest

from tasks import _valid_evm_address, run_process_transaction_intents


@pytest.mark.parametrize(
    "addr,ok",
    [
        ("0x" + "a" * 40, True),
        ("0x" + "A" * 40, True),
        ("0x123", False),
        ("", False),
        (None, False),
    ],
)
def test_valid_evm_address(addr, ok):
    assert _valid_evm_address(addr) is ok


def test_run_process_transaction_intents_no_api_key():
    conn = MagicMock()
    run_process_transaction_intents(
        conn,
        "mnemonic",
        "",
        "mainnet",
        MagicMock(),
        MagicMock(),
        MagicMock(),
        MagicMock(),
    )
    conn.cursor.assert_not_called()


def test_run_process_transaction_intents_no_pending():
    conn = MagicMock()
    cur = MagicMock()
    cur.fetchall.return_value = []
    conn.cursor.return_value = cur
    run_process_transaction_intents(
        conn,
        "mnemonic",
        "alchemy-key",
        "mainnet",
        lambda m, u: MagicMock(address="0x" + "1" * 40),
        MagicMock(),
        MagicMock(),
        MagicMock(),
    )
    cur.execute.assert_called_once()
