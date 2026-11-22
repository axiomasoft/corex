"""P17.3 external REST and MCP client contract."""
from __future__ import annotations

import importlib.util
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
SCRIPT = ROOT / "scripts/distribution/verify_api_clients.py"
SPEC = importlib.util.spec_from_file_location("verify_api_clients", SCRIPT)
assert SPEC and SPEC.loader
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)


class ApiClientTest(unittest.TestCase):
    def test_committed_snapshots_match_the_manifest(self) -> None:
        contract = MODULE.REST.load_snapshots(MODULE.SNAPSHOTS)
        self.assertEqual("v2", contract["manifest"]["major"])
        self.assertIn("operations.json", contract["digests"])
        self.assertEqual(contract["digests"]["operations.json"], contract["manifest"]["files"]["operations.json"])

    def test_unexpected_field_and_opaque_cursor_are_closed(self) -> None:
        self.assertEqual(MODULE.REST.unexpected_fields({"data": {}, "meta": {}, "extra": 1}), ["extra"])
        self.assertEqual(MODULE.REST.forward_cursor("http://127.0.0.1:9", MODULE.CURSOR), "http://127.0.0.1:9" + MODULE.CURSOR)
        with self.assertRaises(SystemExit):
            MODULE.REST.forward_cursor("http://127.0.0.1:9", "http://example.test/next")

    def test_clients_do_not_import_corex_or_graphql(self) -> None:
        self.assertEqual(MODULE.source_forbidden(MODULE.REST_CLIENT), [])
        self.assertEqual(MODULE.source_forbidden(MODULE.MCP_CLIENT), [])

    def test_raw_cursor_is_not_decoded(self) -> None:
        self.assertEqual(MODULE.raw_query_value(f"cursor={MODULE.CURSOR_TOKEN}", "cursor"), MODULE.CURSOR_TOKEN)
        self.assertIsNone(MODULE.raw_query_value("", "cursor"))


if __name__ == "__main__":
    unittest.main()
