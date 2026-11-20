"""Loopback simulator evidence must survive an intentionally lost response."""
from __future__ import annotations

import http.client
import json
import os
import sqlite3
import subprocess
import sys
import tempfile
import time
import unittest
from contextlib import closing
from pathlib import Path

SIMULATOR = Path(__file__).resolve().parents[3] / "tests/Distribution/fixtures/scenarios/support/provider/simulator.py"


class ScenarioProviderTest(unittest.TestCase):
    def test_committed_effect_survives_lost_ack_and_delayed_lookup(self) -> None:
        with tempfile.TemporaryDirectory(prefix="corex-provider-simulator-") as temporary:
            root = Path(temporary)
            barriers = root / "barriers"
            barriers.mkdir()
            simulator = subprocess.Popen(
                [sys.executable, str(SIMULATOR)],
                stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True,
                env={**os.environ, "COREX_SCENARIO_BARRIERS": str(barriers)},
            )
            try:
                marker = barriers / "provider-ready.reached"
                deadline = time.monotonic() + 5
                while not marker.is_file() and simulator.poll() is None and time.monotonic() < deadline:
                    time.sleep(0.025)
                self.assertTrue(marker.is_file(), "Simulator did not announce its loopback port")
                bound = json.loads(marker.read_text())
                self.assertEqual(simulator.pid, bound["pid"])
                route = "/effects/notifications/immutable%3Aeffect"
                with closing(http.client.HTTPConnection("127.0.0.1", bound["port"], timeout=5)) as connection:
                    connection.request("POST", route, body=b'{"fixture":"secret omitted from journal"}', headers={
                        "X-Scenario-Clock": "100", "X-Scenario-Response": "drop", "X-Scenario-Lookup-Delay": "10",
                        "X-Scenario-Barrier": "provider-committed",
                    })
                    with self.assertRaises(http.client.RemoteDisconnected):
                        connection.getresponse()
                committed = json.loads((barriers / "provider-committed.reached").read_text())
                self.assertEqual(simulator.pid, committed["pid"])
                with closing(sqlite3.connect(root / "provider-journal.sqlite")) as journal:
                    attempts = journal.execute("SELECT count(*) FROM requests").fetchone()[0]
                    ref = journal.execute("SELECT provider_ref FROM effects").fetchone()[0]
                self.assertEqual(1, attempts)
                self.assertEqual(committed["reference"], ref)

                with closing(http.client.HTTPConnection("127.0.0.1", bound["port"], timeout=5)) as connection:
                    connection.request("GET", route, headers={"X-Scenario-Clock": "109"})
                    response = connection.getresponse()
                    self.assertEqual(404, response.status)
                    self.assertEqual({"status": "missing"}, json.load(response))
                    connection.request("GET", route, headers={"X-Scenario-Clock": "110"})
                    response = connection.getresponse()
                    self.assertEqual(200, response.status)
                    self.assertEqual({"status": "confirmed", "reference": ref}, json.load(response))
                (barriers / "provider-ready.release").touch()
                stdout, stderr = simulator.communicate(timeout=5)
                self.assertEqual(0, simulator.returncode, stderr)
                self.assertEqual({"requests": 1, "effects": 1, "lookups": 2}, json.loads(stdout)["after"])
            finally:
                if simulator.poll() is None:
                    simulator.kill()
                    simulator.communicate(timeout=5)
