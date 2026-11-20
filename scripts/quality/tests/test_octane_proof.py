"""Receipt integrity tests; no package source patches or mutation runners."""
from __future__ import annotations

import copy
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
import xml.etree.ElementTree as ET

spec = importlib.util.spec_from_file_location("octane_proof", Path(__file__).parents[1] / "octane-proof.py")
proof = importlib.util.module_from_spec(spec)
spec.loader.exec_module(proof)


class OctaneReceiptTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        self.directory = self.root / "evidence"
        self.directory.mkdir()
        self.consumer = self.root / proof.CONSUMER
        for path in ["composer.json", "composer.lock", "packages/core/composer.json", "packages/core/src/Example.php",
                     "packages/core/tests/Release/OctaneLifecycleTest.php", "scripts/quality/octane-proof.py",
                     str(proof.CONSUMER / "composer.json"), str(proof.CONSUMER / "phpunit.xml"),
                     str(proof.CONSUMER / "LifecycleServiceProvider.php"),
                     str(proof.CONSUMER / "vendor/axiomasoft/corex/composer.json"),
                     str(proof.CONSUMER / "vendor/axiomasoft/corex/src/Example.php"),
                     str(proof.CONSUMER / "vendor/laravel/octane/src/Worker.php"),
                     str(proof.CONSUMER / "vendor/laravel/octane/config/octane.php")]:
            target = self.root / path
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_text("fixture\n")
        lock = {"packages": [
            {"name": "laravel/framework", "version": "v13.12.0"},
            {"name": "laravel/octane", "version": "v2.17.4", "source": {"reference": "source-ref"}},
        ]}
        (self.consumer / proof.CONSUMER_LOCK).write_text(json.dumps(lock))
        self.receipt = {"schema": "corex-octane-source-proof/v1",
                        "source_hashes": proof.source_hashes(self.root),
                        "consumer_lock_sha256": proof.sha256(self.consumer / proof.CONSUMER_LOCK),
                        "octane_hashes": proof.octane_hashes(self.consumer),
                        "installed_core_hashes": proof.core_hashes(self.consumer / "vendor/axiomasoft/corex"),
                        "cells": []}
        for version in ["8.4", "8.5"]:
            junit = self.directory / (version + ".xml")
            suite = ET.Element("testsuite")
            for name in proof.CASES:
                ET.SubElement(suite, "testcase", name=name, **{"class": "OctaneLifecycleTest", "assertions": "10"})
            ET.ElementTree(suite).write(junit)
            cell = {"cell": version, "executed": True, "exit_code": 0,
                    "runtime": {"php": version + ".1", "laravel": "v13.12.0", "octane": "v2.17.4",
                                "octane_ref": "source-ref", "implementation": proof.IMPLEMENTATION,
                                "worker_file": str(self.consumer / "vendor/laravel/octane/src/Worker.php"),
                                "core_path": str(self.consumer / "vendor/axiomasoft/corex")},
                    "raw_junit": junit.name, "raw_junit_sha256": proof.sha256(junit), "cases": {}}
            witness = self.directory / (version + "-runtime.json")
            witness.write_text(json.dumps(cell["runtime"]))
            log = self.directory / (version + "-worker.log")
            log.write_text("fixture worker log\n")
            cell.update({"runtime_witness": witness.name, "runtime_witness_sha256": proof.sha256(witness),
                         "raw_log": log.name, "raw_log_sha256": proof.sha256(log)})
            for name, (terminated, errors, statuses, messages) in proof.CASES.items():
                tenants = [*proof.TENANTS, proof.TENANTS[0]] if name == "test_failed_request_cleanup" else proof.TENANTS
                owners = ["lifecycle-tenant-" + tenant[-1] for tenant in tenants]
                counts = {"RequestReceived": len(tenants), "RequestHandled": len(tenants), "RequestTerminated": terminated,
                          "WorkerErrorOccurred": errors, "WorkerStarting": 2 if name == "test_worker_restart" else 1,
                          "WorkerStopping": 2 if name == "test_worker_restart" else 1}
                trace = {"case": name, "executed": True, "implementation": proof.IMPLEMENTATION,
                         "execution_scope": "real-php-worker-handler", "pid": 123, "handles": len(tenants),
                         "events": {proof.EVENT_PREFIX + key: value for key, value in counts.items()},
                         "tenants": tenants, "markers": tenants, "connections": owners, "bindings": owners,
                         "principals": ["fixture-user:" + tenant for tenant in tenants],
                         "statuses": statuses, "errors": messages}
                path = self.directory / (version + "-" + name + ".json")
                path.write_text(json.dumps(trace))
                cell["cases"][name] = {"path": path.name, "sha256": proof.sha256(path), "assertions": 10}
            self.receipt["cells"].append(cell)

    def validate(self, receipt=None):
        proof.validate_receipt(receipt or self.receipt, self.root, self.directory, ["8.4", "8.5"])

    def test_complete_receipt_is_accepted(self):
        self.validate()

    def test_missing_handler_and_incomplete_matrix_are_rejected(self):
        for missing in ["matrix", "case", "file"]:
            with self.subTest(missing=missing):
                receipt = copy.deepcopy(self.receipt)
                if missing == "matrix":
                    receipt["cells"].pop()
                elif missing == "case":
                    receipt["cells"][0]["cases"].pop(next(iter(proof.CASES)))
                else:
                    receipt["cells"][0]["cases"][next(iter(proof.CASES))]["path"] = "absent.json"
                with self.assertRaises((proof.ProofError, OSError)):
                    self.validate(receipt)

    def test_unexecuted_wrong_and_modeled_handlers_are_rejected(self):
        for key, value in [("executed", False), ("implementation", "FakeWorker"),
                           ("execution_scope", "modeled-reset"), ("handles", 0), ("pid", 0),
                           ("case", "wrong-test"), ("tenants", [proof.TENANTS[0]] * 2)]:
            with self.subTest(key=key):
                self.reject_trace_change(key, value)

    def reject_trace_change(self, key, value, case="test_tenant_request_isolation"):
        receipt = copy.deepcopy(self.receipt)
        artifact = receipt["cells"][0]["cases"][case]
        path = self.directory / artifact["path"]
        original = path.read_bytes()
        try:
            trace = json.loads(original)
            trace[key] = value
            path.write_text(json.dumps(trace))
            # Rehash to prove semantic rejection, not just an integrity mismatch.
            artifact["sha256"] = proof.sha256(path)
            with self.assertRaises(proof.ProofError):
                self.validate(receipt)
        finally:
            path.write_bytes(original)

    def test_cleanup_events_failure_outcomes_and_process_witness_are_checked(self):
        self.reject_trace_change("events", {})
        self.reject_trace_change("statuses", [200, 200], "test_failed_request_cleanup")
        self.reject_trace_change("errors", [], "test_worker_error_cleanup")
        self.reject_trace_change("pid", 456)
        for key in ["markers", "connections", "bindings", "principals"]:
            with self.subTest(key=key):
                self.reject_trace_change(key, [], "test_failed_request_cleanup")

    def test_skipped_failed_missing_duplicate_and_empty_junit_cases_are_rejected(self):
        for defect in ["skipped", "failure", "error", "missing", "duplicate", "no-assertions", "wrong-class"]:
            with self.subTest(defect=defect):
                receipt = copy.deepcopy(self.receipt)
                cell = receipt["cells"][0]
                path = self.directory / cell["raw_junit"]
                original = path.read_bytes()
                try:
                    tree = ET.parse(path)
                    suite, case = tree.getroot(), tree.getroot()[0]
                    if defect in ("skipped", "failure", "error"):
                        ET.SubElement(case, defect)
                    elif defect == "missing":
                        suite.remove(case)
                    elif defect == "duplicate":
                        suite.append(copy.deepcopy(case))
                    elif defect == "no-assertions":
                        case.set("assertions", "0")
                    else:
                        case.set("class", "FakeTest")
                    tree.write(path)
                    cell["raw_junit_sha256"] = proof.sha256(path)
                    with self.assertRaises(proof.ProofError):
                        self.validate(receipt)
                finally:
                    path.write_bytes(original)

    def test_lock_source_and_raw_artifact_hashes_are_checked(self):
        for key in ["consumer_lock_sha256", "source_hashes", "octane_hashes"]:
            with self.subTest(key=key):
                receipt = copy.deepcopy(self.receipt)
                receipt[key] = "wrong"
                with self.assertRaises(proof.ProofError):
                    self.validate(receipt)
        for key in ["raw_junit_sha256", "raw_junit", "raw_log_sha256", "runtime_witness_sha256"]:
            receipt = copy.deepcopy(self.receipt)
            receipt["cells"][0][key] = "wrong"
            with self.assertRaises(proof.ProofError):
                self.validate(receipt)
        (self.root / "packages/core/src/Example.php").write_text("changed\n")
        with self.assertRaises(proof.ProofError):
            self.validate()

    def test_changed_installed_core_is_rejected(self):
        (self.consumer / "vendor/axiomasoft/corex/src/Example.php").write_text("different installed source\n")
        with self.assertRaises(proof.ProofError):
            self.validate()

    def test_wrong_runtime_lock_reference_worker_path_and_matrix_are_rejected(self):
        for key, value in [("php", "8.3.1"), ("laravel", "v12.1.0"), ("octane_ref", "stale"),
                           ("worker_file", "/fake/Worker.php"), ("core_path", "/wrong/core"),
                           ("implementation", "FakeWorker")]:
            with self.subTest(key=key):
                receipt = copy.deepcopy(self.receipt)
                receipt["cells"][0]["runtime"][key] = value
                with self.assertRaises(proof.ProofError):
                    self.validate(receipt)
        for key, value in [("executed", False), ("exit_code", 1), ("cell", "8.3")]:
            receipt = copy.deepcopy(self.receipt)
            receipt["cells"][0][key] = value
            with self.assertRaises(proof.ProofError):
                self.validate(receipt)

    def test_trace_integrity_and_evidence_path_escape_are_rejected(self):
        receipt = copy.deepcopy(self.receipt)
        artifact = receipt["cells"][0]["cases"][next(iter(proof.CASES))]
        artifact["sha256"] = "wrong"
        with self.assertRaises(proof.ProofError):
            self.validate(receipt)
        artifact["path"] = "../composer.lock"
        with self.assertRaises(proof.ProofError):
            self.validate(receipt)


if __name__ == "__main__":
    unittest.main()
