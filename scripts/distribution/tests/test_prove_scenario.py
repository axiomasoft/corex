"""Process and barrier contracts for the installed scenario runner."""
from __future__ import annotations

import os
import signal
import sys
import tempfile
import unittest
from pathlib import Path

SCRIPTS = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(SCRIPTS))
import prove_scenario as runner


class ScenarioProcessTest(unittest.TestCase):
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
