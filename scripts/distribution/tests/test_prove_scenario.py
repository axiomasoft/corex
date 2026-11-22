"""Process and barrier contracts for the installed scenario runner."""
from __future__ import annotations

import json
import os
import signal
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

SCRIPTS = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(SCRIPTS))
import prove_scenario as runner


class ScenarioProcessTest(unittest.TestCase):
    def test_host_components_are_exact_committed_subtrees_with_unique_targets(self) -> None:
        bulk = "bulk-transfer/host/app/BulkTransfer"
        fields = "field-search/host/app/FieldSearch"
        received = runner.host_components({"id": "transport", "host_components": [bulk, fields]})
        self.assertEqual(
            [(runner.FIXTURES.relative_to(runner.ROOT) / bulk, Path("app/BulkTransfer")),
             (runner.FIXTURES.relative_to(runner.ROOT) / fields, Path("app/FieldSearch"))],
            list(received),
        )
        for invalid in ("../" + bulk, "bulk-transfer/host", bulk + "/../Other", "support/host/app/Missing"):
            with self.subTest(component=invalid), self.assertRaises(runner.ScenarioError):
                runner.host_components({"id": "transport", "host_components": [invalid]})
        with self.assertRaises(runner.ScenarioError):
            runner.host_components({"id": "transport", "host_components": [bulk, bulk]})
        with self.assertRaises(runner.ScenarioError):
            runner.host_components({"id": "bulk-transfer", "host_components": [bulk]})

    def test_meilisearch_requires_the_expected_loopback_service(self) -> None:
        with patch.dict(os.environ, {"MEILISEARCH_HOST": "http://127.0.0.1:17700", "MEILISEARCH_MASTER_KEY": "fixture-key"}):
            with patch.object(runner, "meilisearch_request", return_value={"pkgVersion": "1.15.0", "commitSha": "ab"}):
                with self.assertRaisesRegex(runner.ScenarioError, "Meilisearch 1.16.0"):
                    runner.meilisearch_environment("1.16.0")
            with patch.object(runner, "meilisearch_request", return_value={"pkgVersion": "1.16.0", "commitSha": "cd"}):
                first, receipt = runner.meilisearch_environment("1.16.0")
                second, _ = runner.meilisearch_environment("1.16.0")
                self.assertEqual(first["COREX_SCENARIO_MEILI_NAMESPACE"], receipt["namespace"])
                self.assertNotEqual(first["COREX_SCENARIO_MEILI_NAMESPACE"], second["COREX_SCENARIO_MEILI_NAMESPACE"])
                self.assertNotIn("fixture-key", json.dumps(receipt))

        with patch.dict(os.environ, {"MEILISEARCH_HOST": "https://example.test:7700", "MEILISEARCH_MASTER_KEY": "fixture-key"}):
            with self.assertRaisesRegex(runner.ScenarioError, "loopback test service"):
                runner.meilisearch_environment("1.16.0")

    def test_meilisearch_cleanup_rejects_unproven_uid(self) -> None:
        with tempfile.TemporaryDirectory() as temporary:
            ledger = Path(temporary) / "meilisearch-targets.ndjson"
            parts = ["corex.search.uid.v2", "d", "b", "1", "epoch", "a", "incarnation", "workspace", "w", "e",
                     "meilisearch", "competing-namespace", "2", "allocation"]
            ledger.write_text(json.dumps({"uid": "another-index", "parts": parts}) + "\n")
            with self.assertRaisesRegex(runner.ScenarioError, "outside this scenario"):
                runner.staged_meilisearch_targets(ledger, "expected-namespace")
            with self.assertRaisesRegex(runner.ScenarioError, "noncanonical"):
                runner.staged_meilisearch_targets(ledger, "competing-namespace")

    def test_terminate_records_live_owner_and_pretermination_witness(self) -> None:
        with tempfile.TemporaryDirectory(prefix="corex-scenario-process-contract-") as temporary:
            root = Path(temporary)
            (root / "worker.py").write_text('''
import json, os, time
from pathlib import Path
pid = os.getpid()
report = {
    "pid": pid, "assertions": 1, "backend": "process-contract-only",
    "connection": {"session": pid}, "refs": ["claimed"], "before": {}, "after": {"claim": "committed"},
}
print(json.dumps(report), flush=True)
barrier = Path(os.environ["COREX_SCENARIO_BARRIERS"])
temp = barrier / "marker.tmp"
temp.write_text(json.dumps({"pid": pid}))
temp.rename(barrier / "claimed.reached")
while True:
    time.sleep(0.025)
''', encoding="utf-8")
            spec = {
                "processes": {"worker": [sys.executable, "{fixture}/worker.py"]},
                "actions": [
                    {"action": "start", "name": "worker"},
                    {"action": "await", "name": "claimed", "process": "worker"},
                    {"action": "terminate", "name": "worker", "barrier": "claimed"},
                ],
                "timeout_seconds": 2,
            }
            trace = {}
            witnesses = runner.run_actions(spec, root, root, os.environ.copy(), root, trace)
            self.assertEqual(trace["worker"]["exit_code"], -signal.SIGKILL)
            self.assertEqual(witnesses["worker"]["pid"], trace["worker"]["pid"])
            self.assertEqual(witnesses["worker"]["after"], {"claim": "committed"})

    def test_cannot_replace_the_provisioned_database_name_for_one_worker(self) -> None:
        with tempfile.TemporaryDirectory(prefix="corex-scenario-env-contract-") as temporary:
            root = Path(temporary)
            key = "COREX_SCENARIO_DB_CONTROL"
            spec = {
                "processes": {"worker": [sys.executable, "-c", "raise AssertionError('should never start')"]},
                "actions": [{"action": "start", "name": "worker", "environment": {key: "different"}}],
            }
            with self.assertRaisesRegex(runner.ScenarioError, "invalid process-specific scenario environment"):
                runner.run_actions(spec, root, root, {key: "allocated"}, root, {})

    def test_provider_sidecar_does_not_relax_postgres_worker_witness(self) -> None:
        report = '''
import json, os, sys
backend = sys.argv[1]
print(json.dumps({
    "pid": os.getpid(), "assertions": 1, "backend": backend,
    "connection": {"session": os.getpid(), "database": "allocated_test"},
    "refs": [], "before": {}, "after": {},
}))
'''
        for worker_backend in ("PostgreSQL 16.14", "SQLite 3"):
            with self.subTest(backend=worker_backend), tempfile.TemporaryDirectory() as temporary:
                root = Path(temporary)
                spec = {
                    "processes": {
                        "provider": [sys.executable, "-c", report, "SQLite 3"],
                        "worker": [sys.executable, "-c", report, worker_backend],
                    },
                    "auxiliary_processes": ["provider"],
                    "distinct_connections": True,
                    "actions": [{"action": action, "name": name} for name in ("provider", "worker") for action in ("start", "complete")],
                }
                env = {**os.environ, "COREX_SCENARIO_PG_VERSION": "160014", "COREX_PG_DATABASES": "allocated_test"}
                if worker_backend.startswith("PostgreSQL "):
                    witnesses = runner.run_actions(spec, root, root, env, root, {})
                    self.assertEqual("SQLite 3", witnesses["provider"]["backend"])
                else:
                    with self.assertRaisesRegex(runner.ScenarioError, "did not report a provisioned PostgreSQL session"):
                        runner.run_actions(spec, root, root, env, root, {})
