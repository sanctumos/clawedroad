"""Alchemy JSON-RPC helpers."""
from unittest.mock import MagicMock, patch

import alchemy_client


def test_wei_to_eth():
    assert abs(alchemy_client.wei_to_eth(10**18) - 1.0) < 1e-9


@patch("alchemy_client.requests.post")
def test_rpc_post_error_raises(mock_post):
    mock_post.return_value = MagicMock()
    mock_post.return_value.raise_for_status = MagicMock()
    mock_post.return_value.json.return_value = {"error": {"message": "bad"}}
    try:
        alchemy_client._rpc_post("mainnet", "k", "eth_gasPrice", [])
    except RuntimeError as e:
        assert "bad" in str(e) or "error" in str(e).lower()
    else:
        raise AssertionError("expected RuntimeError")


@patch("alchemy_client.requests.post")
def test_eth_get_transaction_count(mock_post):
    mock_post.return_value = MagicMock()
    mock_post.return_value.raise_for_status = MagicMock()
    mock_post.return_value.json.return_value = {"result": "0x5"}
    assert alchemy_client.eth_get_transaction_count("0x" + "0" * 40, "k", "mainnet") == 5
