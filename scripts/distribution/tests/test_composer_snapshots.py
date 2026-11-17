"""Standalone Composer preparation preserves source roots and isolates generated locks."""
from __future__ import annotations

import importlib.util
import json
import subprocess
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

SCRIPT = Path(__file__).resolve().parents[2] / "composer-with-snapshots.py"
SPEC = importlib.util.spec_from_file_location("composer_snapshots", SCRIPT)
assert SPEC and SPEC.loader
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)


class ComposerSnapshotsTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        self.package = self.root / "packages/example"
        self.package.mkdir(parents=True)
        (self.root / "composer.json").write_text(json.dumps({
            "name": "example/root", "repositories": [{"type": "path", "url": "packages/*"}],
        }))
        self.source = self.package / "composer.json"
        self.source.write_text(json.dumps({
            "name": "example/package", "minimum-stability": "stable", "autoload": {"psr-4": {"Example\\": "src/"}},
        }))

    def test_keeps_source_and_lock_untouched_and_preserves_local_autoload(self):
        original = self.source.read_bytes()
        lock = self.package / "composer.lock"
        lock.write_text('original source lock')
        generated = MODULE.prepare(self.root, self.package)
        effective = json.loads(generated.read_text())
        self.assertEqual(generated, self.package / ".composer-snapshots.json")
        self.assertEqual(self.source.read_bytes(), original)
        self.assertEqual(lock.read_text(), 'original source lock')
        self.assertEqual(effective["autoload"]["psr-4"]["Example\\"], "src/")
        self.assertEqual(effective["repositories"][0]["url"], str(self.root / "packages/*"))
        self.assertFalse(effective["repositories"][0]["options"]["symlink"])

    def test_runs_composer_in_package_with_generated_manifest_and_propagates_failure(self):
        with patch.object(MODULE, "ROOT", self.root), patch.object(MODULE.subprocess, "run") as run:
            run.return_value = subprocess.CompletedProcess([], 7)
            result = MODULE.main(["--working-dir", str(self.package), "audit", "--locked"])
        self.assertEqual(result, 7)
        arguments, options = run.call_args
        self.assertEqual(arguments[0], ["composer", "audit", "--locked"])
        self.assertEqual(options["cwd"], self.package)
        self.assertEqual(options["env"]["COMPOSER"], ".composer-snapshots.json")

    def test_update_pins_only_reachable_reviewed_analysis_tools(self):
        audit = MODULE.audit_module()
        (self.root / "composer.lock").write_text(json.dumps({"packages": [], "packages-dev": [
            {"name": "larastan/larastan", "version": "v3.12.2", "require": {"phpstan/phpstan": "^2.2"}},
            {"name": "phpstan/phpstan", "version": "2.2.16"},
            {"name": "phpstan/phpstan-strict-rules", "version": "2.0.7"},
        ]}))
        manifest = {"require-dev": {"larastan/larastan": "^3.12"}}
        self.assertEqual(audit.reviewed_toolchain_arguments(self.root, manifest), [
            "--with", "phpstan/phpstan:2.2.16", "--with", "larastan/larastan:v3.12.2",
        ])
        self.assertEqual(audit.reviewed_toolchain_arguments(self.root, {"require": {"psr/log": "^3.0"}}), [])

    def test_rejects_external_workdir_and_generated_symlinks(self):
        with self.assertRaises(ValueError):
            MODULE.prepare(self.root, self.root.parent)
        (self.package / MODULE.GENERATED).symlink_to(self.source)
        with self.assertRaises(ValueError):
            MODULE.prepare(self.root, self.package)


if __name__ == "__main__":
    unittest.main()
