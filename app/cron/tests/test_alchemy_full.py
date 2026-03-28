"""Exercise alchemy_client for high coverage (mocked HTTP / signing)."""
from unittest.mock import MagicMock, patch

import pytest
from eth_account import Account

import alchemy_client


def test_rpc_url_sepolia():
    u = alchemy_client._rpc_url("Sepolia", "abc")
    assert "sepolia" in u.lower()
    assert u.endswith("/abc")


@patch("alchemy_client.requests.post")
def test_get_balance_wei(mock_post):
    mock_post.return_value = MagicMock()
    mock_post.return_value.raise_for_status = MagicMock()
    mock_post.return_value.json.return_value = {"result": "0x10"}
    assert alchemy_client.get_balance_wei("0x0" * 20, "k", "mainnet") == 16


@patch("alchemy_client.requests.post")
def test_get_balance_wei_rpc_error(mock_post):
    mock_post.return_value = MagicMock()
    mock_post.return_value.raise_for_status = MagicMock()
    mock_post.return_value.json.return_value = {"error": "x"}
    with pytest.raises(RuntimeError):
        alchemy_client.get_balance_wei("0x0" * 20, "k", "mainnet")


@patch("alchemy_client.requests.get")
def test_get_eth_usd_price_ok(mock_get):
    mock_get.return_value = MagicMock()
    mock_get.return_value.status_code = 200
    mock_get.return_value.json.return_value = {
        "data": [{"prices": [{"currency": "USD", "price": "2500.5"}]}]
    }
    assert alchemy_client.get_eth_usd_price("k") == 2500.5


@patch("alchemy_client.requests.get")
def test_get_eth_usd_price_bad_status(mock_get):
    mock_get.return_value = MagicMock()
    mock_get.return_value.status_code = 500
    assert alchemy_client.get_eth_usd_price("k") == 0.0


@patch("alchemy_client.requests.get")
def test_get_eth_usd_price_malformed(mock_get):
    mock_get.return_value = MagicMock()
    mock_get.return_value.status_code = 200
    mock_get.return_value.json.return_value = {}
    assert alchemy_client.get_eth_usd_price("k") == 0.0


@patch("alchemy_client.requests.get")
def test_get_eth_usd_price_iteration_raises(mock_get):
    mock_get.return_value = MagicMock()
    mock_get.return_value.status_code = 200
    mock_get.return_value.json.return_value = {"data": [{"prices": None}]}
    assert alchemy_client.get_eth_usd_price("k") == 0.0


@patch("alchemy_client.requests.get")
def test_get_eth_usd_price_no_usd_currency(mock_get):
    mock_get.return_value = MagicMock()
    mock_get.return_value.status_code = 200
    mock_get.return_value.json.return_value = {
        "data": [{"prices": [{"currency": "EUR", "price": "1"}]}]
    }
    assert alchemy_client.get_eth_usd_price("k") == 0.0


@patch("alchemy_client.eth_send_raw_transaction")
@patch("alchemy_client.eth_get_transaction_count")
@patch("alchemy_client.eth_gas_price")
def test_eth_native_transfer_wei(mock_gp, mock_nonce, mock_send):
    mock_gp.return_value = 1_000_000_000
    mock_nonce.return_value = 0
    mock_send.return_value = "0xabc"
    Account.enable_unaudited_hdwallet_features()
    acct = Account.create()
    dest = Account.create().address

    def gb(_addr):
        return 10**17

    h = alchemy_client.eth_native_transfer_wei(acct, dest, 11155111, "key", "sepolia", gb)
    assert h == "0xabc"
    mock_send.assert_called_once()


@patch("alchemy_client.eth_gas_price")
def test_eth_native_transfer_insufficient(mock_gp):
    mock_gp.return_value = 10**12
    acct = Account.create()
    dest = Account.create().address

    def gb(_addr):
        return 1000

    with pytest.raises(ValueError, match="insufficient"):
        alchemy_client.eth_native_transfer_wei(acct, dest, 1, "k", "mainnet", gb)


@patch("alchemy_client.eth_send_raw_transaction")
@patch("alchemy_client.eth_get_transaction_count")
@patch("alchemy_client.eth_gas_price")
def test_eth_native_send_value_wei(mock_gp, mock_nonce, mock_send):
    mock_gp.return_value = 1_000_000_000
    mock_nonce.return_value = 0
    mock_send.return_value = "0xdef"
    acct = Account.create()
    dest = Account.create().address

    def gb(_addr):
        return 10**18

    fee = mock_gp.return_value * 21_000
    val = 10**16
    assert val + fee < gb(None)
    h = alchemy_client.eth_native_send_value_wei(acct, dest, val, 1, "k", "mainnet", gb)
    assert h == "0xdef"


@patch("alchemy_client.eth_gas_price")
def test_eth_native_send_value_bad(mock_gp):
    mock_gp.return_value = 10**12
    acct = Account.create()
    dest = Account.create().address

    def gb(_addr):
        return 10**18

    with pytest.raises(ValueError, match="positive"):
        alchemy_client.eth_native_send_value_wei(acct, dest, 0, 1, "k", "mainnet", gb)
    with pytest.raises(ValueError, match="insufficient"):
        alchemy_client.eth_native_send_value_wei(acct, dest, 10**30, 1, "k", "mainnet", gb)


@patch("alchemy_client._rpc_post")
def test_eth_send_raw_prepends_0x(mock_rpc):
    mock_rpc.return_value = {"result": "0xh"}
    assert alchemy_client.eth_send_raw_transaction("abc", "k", "mainnet") == "0xh"
    mock_rpc.assert_called_once()
    assert mock_rpc.call_args[0][2] == "eth_sendRawTransaction"


@patch("alchemy_client._rpc_post")
def test_eth_gas_price_default_network(mock_rpc):
    mock_rpc.return_value = {"result": "0x9"}
    assert alchemy_client.eth_gas_price("k") == 9


@patch("alchemy_client._rpc_post")
def test_eth_get_transaction_count_default_network(mock_rpc):
    mock_rpc.return_value = {"result": "0x2"}
    assert alchemy_client.eth_get_transaction_count("0x" + "0" * 40, "k") == 2
