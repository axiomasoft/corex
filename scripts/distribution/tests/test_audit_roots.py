"""Hermetic Composer source inventory, execution and evidence failure controls."""
from __future__ import annotations

import importlib.util
import json
import subprocess
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

SCRIPT = Path(__file__).resolve().parents[1] / "audit-roots.py"
SPEC = importlib.util.spec_from_file_location("audit_roots", SCRIPT)
assert SPEC and SPEC.loader
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)


class Composer:
    """Record tool calls and model only their filesystem/JSON protocol, without network."""

    def __init__(self):
        self.calls = []
        self.fail = None
        self.audit_output = {"advisories": [], "abandoned": []}
        self.timeout = False
        self.omit_lock = False

    def __call__(self, command, cwd, environment, timeout):
        self.calls.append((command, cwd, dict(environment), timeout))
        role = command[4]
        if self.timeout:
            raise subprocess.TimeoutExpired(command, timeout)
        if role == "update" and not self.omit_lock:
            manifest = MODULE.read_json(cwd / "composer.json")
            MODULE.write_json(cwd / "composer.lock", {"content-hash": MODULE.content_hash(manifest), "packages": [{"name": "example/stable", "version": "1.2.3"}], "packages-dev": []})
        stdout = json.dumps(self.audit_output) if role == "audit" else f"real mocked {role} output\n"
        if role == "--version":
            stdout = "Composer version 2.8.3 (hermetic fixture)\n"
        return subprocess.CompletedProcess(command, 2 if self.fail == role else 0, stdout, "planted command failure" if self.fail == role else "")


class AuditRootsTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix="corex-audit-test-")
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name) / "source"
        self.evidence = Path(self.temporary.name) / "evidence"
        self.root.mkdir()
        self.runner = Composer()
        self.manifest("composer.json", "example/root", locked=True)

    def manifest(self, name, package, *, locked=False, **extra):
        path = self.root / name
        data = {"name": package, "description": "Source audit fixture", "license": "MIT", "require": {"example/stable": "^1.2"}, "minimum-stability": "stable", **extra}
        MODULE.write_json(path, data)
        if locked:
            MODULE.write_json(path.with_name("composer.lock"), {"content-hash": MODULE.content_hash(data), "packages": [{"name": "example/stable", "version": "1.2.3"}], "packages-dev": []})
        return path

    def run_audit(self, environment=None):
        return MODULE.audit(self.root, self.evidence, runner=self.runner, environment=environment or {}, timeout=7)

    def assert_red(self, receipt, diagnostic):
        self.assertEqual("RED", receipt["status"])
        self.assertIn(diagnostic, "\n".join(receipt["errors"]))

    def test_all_source_roots_are_audited_with_actual_outputs_and_hashes(self):
        self.manifest("packages/core/composer.json", "example/core", locked=True)
        self.manifest("products/app/composer.json", "example/app", locked=True)
        self.manifest("packages/core/tests/fixtures/worker/composer.json", "example/worker", locked=True)
        self.manifest("tests/fixtures/consumer/composer.json", "example/consumer", locked=True)
        receipt = self.run_audit()
        self.assertEqual("GREEN", receipt["status"], receipt)
        self.assertEqual(5, len(receipt["roots"]))
        MODULE.verify_evidence(self.root, self.evidence)
        for row in receipt["roots"]:
            directory = self.evidence / row["directory"]
            proof = MODULE.read_json(directory / "receipt.json")
            self.assertEqual(["validate", "audit"], [command["role"] for command in proof["commands"]])
            self.assertEqual(MODULE.digest(directory / "composer.lock"), proof["lock_sha256"])
            output = MODULE.read_json(directory / "validate.json")
            self.assertIn("real mocked validate output", output["stdout"])
            self.assertEqual(0, output["exit"])
        for command, _, environment, timeout in self.runner.calls:
            self.assertEqual(["composer", "--no-interaction", "--no-plugins", "--no-scripts"], command[:4])
            self.assertNotIn("--no-audit", command)
            self.assertEqual("fail", environment["COMPOSER_AUDIT_ABANDONED"])
            self.assertEqual(7, timeout)

    def test_missing_root_manifest_is_red(self):
        (self.root / "composer.json").unlink()
        self.assert_red(self.run_audit(), "missing source root manifest: composer.json")
        self.assertFalse(self.runner.calls)

    def test_execution_log_is_not_source_but_adjacent_composer_root_is_audited(self):
        log = self.root / ".codex/state/session.tools.jsonl"
        log.parent.mkdir(parents=True)
        log.write_text('{"event": 1}\n', encoding="utf-8")
        manifest = self.manifest(".codex/tooling/composer.json", "example/tooling", locked=True)
        receipt = self.run_audit()
        self.assertEqual("GREEN", receipt["status"], receipt)
        self.assertEqual(2, len(receipt["roots"]))
        log.write_text('{"event": 2}\n', encoding="utf-8")
        MODULE.verify_evidence(self.root, self.evidence)
        manifest.write_text(manifest.read_text(encoding="utf-8") + "\n", encoding="utf-8")
        with self.assertRaisesRegex(MODULE.AuditError, "changed roots|source changed"):
            MODULE.verify_evidence(self.root, self.evidence)

    def test_missing_package_manifest_is_red(self):
        path = self.root / "packages/missing/src/Worker.php"
        path.parent.mkdir(parents=True)
        path.write_text("<?php", encoding="utf-8")
        self.assert_red(self.run_audit(), "packages/missing/composer.json")

    def test_deleted_tracked_standalone_root_is_red(self):
        with patch.object(MODULE, "tracked_manifests", return_value={"tests/fixtures/deleted/composer.json"}):
            self.assert_red(self.run_audit(), "tests/fixtures/deleted/composer.json")

    def test_missing_root_receipt_is_red_even_with_green_summary(self):
        self.assertEqual("GREEN", self.run_audit()["status"])
        index = MODULE.read_json(self.evidence / "index.json")
        index["roots"] = []
        MODULE.write_json(self.evidence / "index.json", index)
        with self.assertRaisesRegex(MODULE.AuditError, "missing root audit"):
            MODULE.verify_evidence(self.root, self.evidence)

    def test_missing_audit_output_is_red(self):
        receipt = self.run_audit()
        directory = self.evidence / receipt["roots"][0]["directory"]
        (directory / "audit.json").unlink()
        with self.assertRaisesRegex(MODULE.AuditError, "missing/changed command evidence"):
            MODULE.verify_evidence(self.root, self.evidence)

    def test_missing_audit_call_is_red(self):
        receipt = self.run_audit()
        directory = self.evidence / receipt["roots"][0]["directory"]
        proof = MODULE.read_json(directory / "receipt.json")
        proof["commands"] = [row for row in proof["commands"] if row["role"] != "audit"]
        with self.assertRaisesRegex(MODULE.AuditError, "missing/duplicate audit"):
            MODULE.verify_commands(self.root, proof, directory)

    def test_insecure_no_audit_environment_is_red(self):
        self.assert_red(self.run_audit({"COMPOSER_NO_AUDIT": "1"}), "COMPOSER_NO_AUDIT")
        self.assertFalse(self.runner.calls)

    def test_insecure_manifest_bypasses_are_red(self):
        for config in ({"audit": {"ignore": ["GHSA-hidden"]}}, {"audit": {"abandoned": "ignore"}}, {"audit": {"block-insecure": False}}, {"secure-http": False}, {"disable-tls": True}, {"lock": False}, {"policy": {"advisories": {"audit": False}}}):
            with self.subTest(config=config):
                self.manifest("composer.json", "example/root", locked=True, config=config)
                self.assert_red(self.run_audit(), "insecure")

    def test_failed_validate_or_audit_command_is_red(self):
        for role in ("validate", "audit"):
            with self.subTest(role=role):
                self.runner.fail = role
                self.assert_red(self.run_audit(), f"{role} failed")

    def test_audit_advisory_abandoned_or_ignore_is_red_even_exit_zero(self):
        for output in ({"advisories": {"bad/pkg": [{"id": "GHSA-bad"}]}, "abandoned": []}, {"advisories": [], "abandoned": {"bad/pkg": None}}, {"advisories": [], "abandoned": [], "ignored-advisories": [{"id": "GHSA-hidden"}]}, {"advisories": "", "abandoned": []}, {}):
            with self.subTest(output=output):
                self.runner.audit_output = output
                self.assert_red(self.run_audit(), "audit")

    def test_lock_mismatch_is_red_and_not_repaired(self):
        path = self.root / "composer.json"
        manifest = MODULE.read_json(path)
        manifest["require"]["example/stable"] = "^2.0"
        MODULE.write_json(path, manifest)
        before = MODULE.digest(path.with_name("composer.lock"))
        self.assert_red(self.run_audit(), "content-hash mismatch")
        self.assertEqual(before, MODULE.digest(path.with_name("composer.lock")))
        self.assertEqual(["--version"], [command[4] for command, *_ in self.runner.calls])

    def test_changed_lock_evidence_is_red(self):
        receipt = self.run_audit()
        lock = self.evidence / receipt["roots"][0]["directory"] / "composer.lock"
        lock.write_text("{}", encoding="utf-8")
        with self.assertRaisesRegex(MODULE.AuditError, "lock/manifest evidence changed"):
            MODULE.verify_evidence(self.root, self.evidence)

    def test_new_source_root_or_changed_source_invalidates_evidence(self):
        self.assertEqual("GREEN", self.run_audit()["status"])
        self.manifest("tests/fixtures/new/composer.json", "example/new")
        with self.assertRaisesRegex(MODULE.AuditError, "missing or changed roots"):
            MODULE.verify_evidence(self.root, self.evidence)

    def test_changed_source_inventory_or_manifest_copy_is_red(self):
        receipt = self.run_audit()
        directory = self.evidence / receipt["roots"][0]["directory"]
        (directory / "source-composer.json").write_text("{}", encoding="utf-8")
        with self.assertRaisesRegex(MODULE.AuditError, "source manifest evidence changed"):
            MODULE.verify_evidence(self.root, self.evidence)
        self.run_audit()
        (self.evidence / "source.json").write_text("{}", encoding="utf-8")
        with self.assertRaisesRegex(MODULE.AuditError, "source inventory evidence changed"):
            MODULE.verify_evidence(self.root, self.evidence)

    def test_untracked_symlink_manifest_cannot_disappear_from_inventory(self):
        directory = self.root / "tests/fixtures/linked"
        directory.mkdir(parents=True)
        (directory / "composer.json").symlink_to(self.root / "composer.json")
        self.assert_red(self.run_audit(), "missing source root manifest: tests/fixtures/linked/composer.json")

    def test_clean_harness_solves_in_isolated_copy_with_precise_inherited_repositories(self):
        parent = self.manifest("composer.json", "example/root", locked=True, repositories=[{"type": "path", "url": "packages/*"}])
        self.manifest("packages/core/composer.json", "example/core", locked=True)
        source = self.manifest("tests/fixtures/worker/composer.json", "example/worker")
        before = MODULE.digest(source)
        receipt = self.run_audit()
        self.assertEqual("GREEN", receipt["status"], receipt)
        self.assertEqual(before, MODULE.digest(source))
        self.assertFalse(source.with_name("composer.lock").exists())
        self.assertFalse((source.parent / "vendor").exists())
        row = next(row for row in receipt["roots"] if row["manifest"] == str(source.relative_to(self.root)))
        directory = self.evidence / row["directory"]
        proof = MODULE.read_json(directory / "receipt.json")
        self.assertEqual("isolated-solve", proof["lock_kind"])
        self.assertEqual(["validate-source", "solve", "validate", "audit"], [command["role"] for command in proof["commands"]])
        effective = MODULE.read_json(directory / "composer.json")
        self.assertEqual(MODULE.read_json(source)["require"], effective["require"])
        self.assertEqual(str(self.root / "packages/*"), effective["repositories"][0]["url"])
        self.assertNotIn("versions", effective["repositories"][0]["options"])
        self.assertEqual("packages/core/composer.json", proof["repository_bindings"][0]["manifest"])
        self.assertEqual(str(parent.relative_to(self.root)), proof["repository_bindings"][0]["owner"])

    def test_isolated_solve_pins_the_reviewed_toolchain_and_verifies_those_exact_arguments(self):
        root = self.manifest("composer.json", "example/root", locked=True, **{"require-dev": {"phpstan/phpstan": "^2.1"}})
        lock = MODULE.read_json(root.with_name("composer.lock"))
        lock["packages-dev"] = [{"name": "phpstan/phpstan", "version": "2.1.40"}]
        MODULE.write_json(root.with_name("composer.lock"), lock)
        source = self.manifest("tests/fixtures/worker/composer.json", "example/worker", **{"require-dev": {"phpstan/phpstan": "^2.1"}})
        receipt = self.run_audit()
        self.assertEqual("GREEN", receipt["status"], receipt)
        MODULE.verify_evidence(self.root, self.evidence)
        solve = next(command for command, *_ in self.runner.calls if command[4] == "update")
        self.assertEqual(["--with", "phpstan/phpstan:2.1.40"], solve[-2:])
        row = next(row for row in receipt["roots"] if row["manifest"] == str(source.relative_to(self.root)))
        directory = self.evidence / row["directory"]
        proof = MODULE.read_json(directory / "receipt.json")
        evidence = directory / next(command for command in proof["commands"] if command["role"] == "solve")["output"]
        recorded = MODULE.read_json(evidence)
        recorded["command"] = recorded["command"][:-2]
        MODULE.write_json(evidence, recorded)
        for command in proof["commands"]:
            if command["role"] == "solve":
                command["sha256"] = MODULE.digest(evidence)
        with self.assertRaisesRegex(MODULE.AuditError, "insecure/missing solve command"):
            MODULE.verify_commands(self.root, proof, directory)

    def test_failed_solve_missing_lock_timeout_or_unstable_harness_is_red(self):
        source = self.manifest("tests/fixtures/worker/composer.json", "example/worker")
        self.runner.fail = "update"
        self.assert_red(self.run_audit(), "bounded solve failed")
        self.runner.fail = None
        self.runner.omit_lock = True
        self.assert_red(self.run_audit(), "missing lock")
        self.runner.omit_lock = False
        self.runner.timeout = True
        self.assert_red(self.run_audit(), "failed")
        self.runner.timeout = False
        self.manifest(str(source.relative_to(self.root)), "example/worker", **{"minimum-stability": "dev"})
        self.assert_red(self.run_audit(), "unstable source harness constraints")

    def test_external_dev_constraint_does_not_bypass_stable_harness(self):
        self.manifest("tests/fixtures/worker/composer.json", "example/worker", require={"external/unstable": "dev-main"})
        self.assert_red(self.run_audit(), "unstable external harness dependency")

    def reviewed_snapshot(self):
        package = {
            "name": "external/reviewed", "type": "library", "version": "dev-main",
            "source": {"type": "git", "url": "https://example.com/reviewed.git", "reference": "a" * 40},
            "require": {"php": "^8.4"}, "autoload": {"psr-4": {"Reviewed\\": "src/"}},
            "extra": {"branch-alias": {"dev-main": "1.0.x-dev"}},
        }
        repository = {"type": "package", "package": package}
        self.manifest("composer.json", "example/root", locked=True, repositories=[repository])
        lock = MODULE.read_json(self.root / "composer.lock")
        lock["content-hash"] = MODULE.content_hash(MODULE.read_json(self.root / "composer.json"))
        lock["packages"].append(package)
        MODULE.write_json(self.root / "composer.lock", lock)
        return repository

    def test_exact_reviewed_pinned_external_dependency_is_allowed(self):
        repository = self.reviewed_snapshot()
        effective = {"minimum-stability": "stable", "repositories": [repository],
                     "require": {"external/reviewed": "^1.0@dev"}}
        MODULE.require_stable_harness(effective, [], self.root, "worker")

    def test_snapshot_allowance_rejects_shadowed_or_unreviewed_metadata(self):
        repository = self.reviewed_snapshot()
        effective = {"minimum-stability": "stable", "repositories": [repository],
                     "require": {"external/reviewed": "^1.0@dev"}}
        changed = json.loads(json.dumps(repository))
        changed["package"]["source"]["reference"] = "b" * 40
        effective["repositories"] = [changed, repository]
        with self.assertRaisesRegex(MODULE.AuditError, "effective snapshot differs"):
            MODULE.require_stable_harness(effective, [], self.root, "worker")
        effective["repositories"] = [repository]
        lock = MODULE.read_json(self.root / "composer.lock")
        lock["packages"][-1]["autoload"] = {"psr-4": {"Changed\\": "src/"}}
        MODULE.write_json(self.root / "composer.lock", lock)
        with self.assertRaisesRegex(MODULE.AuditError, "differs from reviewed root lock"):
            MODULE.require_stable_harness(effective, [], self.root, "worker")

    def test_snapshot_allowance_rejects_stale_root_lock(self):
        repository = self.reviewed_snapshot()
        effective = {"minimum-stability": "stable", "repositories": [repository],
                     "require": {"external/reviewed": "^1.0@dev"}}
        root_manifest = MODULE.read_json(self.root / "composer.json")
        root_manifest["require"]["example/stable"] = "^2.0"
        MODULE.write_json(self.root / "composer.json", root_manifest)
        with self.assertRaisesRegex(MODULE.AuditError, "content-hash mismatch"):
            MODULE.require_stable_harness(effective, [], self.root, "worker")

    def test_generated_and_legacy_install_artifacts_are_not_source_roots(self):
        for name in ("vendor/bad", "build/candidate", "plans/history", "artifacts/prototype", "delivery/rc", "node_modules/package"):
            self.manifest(f"{name}/composer.json", "bad/ignored")
        (self.root / "packages/old/src").mkdir(parents=True)
        MODULE.write_json(self.root / "packages/old/composer.lock", {})
        self.assertEqual(["composer.json"], [row["manifest"] for row in MODULE.inventory(self.root)])
        self.assertEqual("GREEN", self.run_audit()["status"])

    def test_release_only_fixture_requires_source_anchor_and_any_lock_overrides(self):
        fixture = next(iter(MODULE.RELEASE_FIXTURES))
        owner, anchor = MODULE.RELEASE_FIXTURES[fixture]
        self.manifest(fixture, "example/release")
        path = self.root / owner
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(anchor, encoding="utf-8")
        receipt = self.run_audit()
        self.assertEqual("GREEN", receipt["status"], receipt)
        self.assertEqual(1, len(receipt["roots"]))
        release = next(row for row in receipt["inventory"] if row["manifest"] == fixture)
        self.assertEqual("release-only", release["classification"])
        self.assertEqual(MODULE.digest(path), release["owner_sha256"])
        self.manifest(fixture, "example/release", locked=True)
        self.assertEqual("source", next(row for row in MODULE.inventory(self.root) if row["manifest"] == fixture)["classification"])
        self.assertEqual(2, len(self.run_audit()["roots"]))
        (self.root / fixture).with_name("composer.lock").unlink()
        path.write_text("changed semantics", encoding="utf-8")
        self.assert_red(self.run_audit(), "stale release-only classification")


if __name__ == "__main__":
    unittest.main()
