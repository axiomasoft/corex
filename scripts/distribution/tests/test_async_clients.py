"""External bulk and webhook clients."""
from __future__ import annotations

import importlib.util
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
SCRIPT = ROOT / "scripts/distribution/verify_async_clients.py"
SPEC = importlib.util.spec_from_file_location("verify_async_clients", SCRIPT)
assert SPEC and SPEC.loader
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)


class AsyncClientTest(unittest.TestCase):
    def test_clients_do_not_import_corex_or_graphql(self) -> None:
        self.assertEqual(MODULE.source_forbidden(MODULE.BULK_CLIENT), [])
        self.assertEqual(MODULE.source_forbidden(MODULE.WEBHOOK_CLIENT), [])

    def test_opaque_bulk_path_stays_local(self) -> None:
        path = MODULE.BULK_PATH + "/bulk-1?track=opaque"
        self.assertEqual(MODULE.BULK.forward_path("http://127.0.0.1:9", path), "http://127.0.0.1:9" + path)
        with self.assertRaises(SystemExit):
            MODULE.BULK.forward_path("http://127.0.0.1:9", "http://example.test/result")

    def test_signature_uses_exact_bytes_and_unpublished_topic_is_absent(self) -> None:
        contract = MODULE.WEBHOOK.load_snapshots(MODULE.SNAPSHOTS)
        self.assertNotIn(MODULE.TOPIC, contract["topics"])
        signature = MODULE.WEBHOOK.sign(MODULE.SECRET, contract["webhooks"]["rotation"], "delivery-1", "1700000000", MODULE.BODY)
        self.assertTrue(MODULE.WEBHOOK.signature_matches(MODULE.SECRET, contract["webhooks"]["rotation"], "delivery-1", "1700000000", MODULE.BODY, signature))
        self.assertFalse(MODULE.WEBHOOK.signature_matches(MODULE.SECRET, contract["webhooks"]["rotation"], "delivery-1", "1700000000", MODULE.BODY + b" ", signature))


if __name__ == "__main__":
    unittest.main()
