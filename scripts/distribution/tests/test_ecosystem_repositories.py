"""Consumer manifests preserve reviewed owner refs without importing workspace repos."""
from __future__ import annotations

import importlib.util
import json
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
SCRIPTS = ROOT / "scripts/distribution"
sys.path.insert(0, str(SCRIPTS))
from ecosystem_repositories import snapshot_repositories


def load_script(filename: str):
    spec = importlib.util.spec_from_file_location(filename.replace("-", "_"), SCRIPTS / filename)
    assert spec and spec.loader
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


class EcosystemRepositoriesTest(unittest.TestCase):
    def test_snapshots_preserve_complete_metadata_and_exclude_workspace_repositories(self):
        expected = [r for r in json.loads((ROOT / "composer.json").read_text())["repositories"]
                    if r["type"] == "package"]
        self.assertTrue(expected)
        self.assertEqual(snapshot_repositories(ROOT), expected)
        self.assertNotIn(str(ROOT), json.dumps(snapshot_repositories(ROOT)))

    def test_modular_host_requires_access_and_resolves_its_authorization_engine_from_the_reviewed_source(self):
        profiles = json.loads((SCRIPTS / "install-profiles.json").read_text())["profiles"]
        packages = next(row["packages"] for row in profiles if row["id"] == "modular-single")
        access = json.loads((ROOT / "packages/access/composer.json").read_text())
        self.assertIn(access["name"], packages)
        self.assertNotIn("axiomasoft/azguard", packages)
        self.assertIn("axiomasoft/azguard", access["require"])
        source = next(r["package"]["source"] for r in snapshot_repositories(ROOT)
                      if r["package"]["name"] == "axiomasoft/azguard")
        lock = json.loads((ROOT / "composer.lock").read_text())
        dependency = next(p for p in lock["packages"] + lock["packages-dev"] if p["name"] == "axiomasoft/azguard")
        self.assertEqual(dependency["source"], source)

    def test_rejects_local_or_unpinned_sources_and_candidate_shadowing(self):
        template = snapshot_repositories(ROOT)[0]
        for source, name in [
            ({"type": "git", "url": "file:///tmp/local", "reference": "a" * 40}, "owner/package"),
            ({"type": "git", "url": "https://example.com/owner.git", "reference": "main"}, "owner/package"),
            (template["package"]["source"], "axiomasoft/corex"),
        ]:
            with self.subTest(name=name, source=source), tempfile.TemporaryDirectory() as temporary:
                root = Path(temporary)
                repository = {"type": "package", "package": {"name": name, "source": source}}
                (root / "composer.json").write_text(json.dumps({"repositories": [repository]}))
                with self.assertRaises(ValueError):
                    snapshot_repositories(root)

    def test_all_consumer_generators_keep_candidate_first_and_exact_owner_snapshots(self):
        snapshots = snapshot_repositories(ROOT)
        for filename in ["profile-proof.py", "verify_equipment_consumer.py", "verify_extension_fixtures.py"]:
            with self.subTest(generator=filename), tempfile.TemporaryDirectory() as temporary:
                module = load_script(filename)
                root = Path(temporary)
                consumer = root / "consumer"
                repository = root / "candidate"
                if filename == "verify_equipment_consumer.py":
                    module.prepare_install(module.APP, consumer, repository)
                else:
                    consumer.mkdir()
                    (consumer / "composer.json").write_text('{}')
                    if filename == "profile-proof.py":
                        module.write_consumer(consumer, ["axiomasoft/corex"], 13, repository, "8.4", "2.0.0")
                    else:
                        fixture = ROOT / "fixtures/extensions/custom-search-provider"
                        module.write_consumer(consumer, fixture, repository, drop_corex=False)
                manifest = json.loads((consumer / "composer.json").read_text())
                repositories = manifest["repositories"]
                self.assertEqual([r for r in repositories if r.get("type") == "package"], snapshots)
                candidate_index = repositories.index({"type": "composer", "url": str(repository)})
                self.assertLess(candidate_index, repositories.index(snapshots[0]))
                for repo in repositories:
                    if repo.get("type") == "path":
                        self.assertEqual(repo["url"], "fixture")
                packagist = next(r for r in repositories if r.get("url") == "https://repo.packagist.org")
                self.assertIn("axiomasoft/corex-*", packagist["exclude"])
                if filename != "verify_extension_fixtures.py":
                    self.assertEqual(manifest["require"]["laravel/framework"], "^13.0")

    def test_standalone_media_admits_only_reachable_reviewed_owner_snapshots(self):
        module = load_script("audit-roots.py")
        path = ROOT / "packages/media/composer.json"
        original = json.loads(path.read_text())
        effective, _ = module.source_manifest(ROOT, path, original)
        self.assertEqual(effective["minimum-stability"], "stable")
        self.assertEqual(effective["require"]["axiomasoft/packman"], "^0.1")
        self.assertNotIn("axiomasoft/packman-plugins", effective["require"])
        self.assertEqual(effective["require"]["axiomasoft/vaulter-media"], original["require"]["axiomasoft/vaulter-media"])
        self.assertNotIn("axiomasoft/azguard", effective["require"])
        self.assertNotIn("axiomasoft/tenant", effective["require"])
        self.assertNotIn("axiomasoft/packman-panels", effective["require"])
        self.assertEqual(json.loads(path.read_text()), original)

    def test_current_package_names_are_in_receipts_and_platform_checks(self):
        module = load_script("profile-proof.py")
        self.assertEqual(module.locked_summary({"locked": [
            {"name": "axiomasoft/corex", "version": "2.0.0", "direct-dependency": True, "source": None},
            {"name": "axiomasoft/corex-media", "version": "2.0.0", "direct-dependency": True, "source": None},
            {"name": "axiomasoft/packman", "version": "0.1.2", "direct-dependency": False, "source": "https://github.com/axiomasoft/packman"},
            {"name": "flex/crm", "version": "1.0.x-dev", "direct-dependency": True, "source": None},
            {"name": "laravel/framework", "version": "v13.33.0", "direct-dependency": False, "source": "https://github.com/laravel/framework"},
        ]}), ["axiomasoft/corex-media:2.0.0", "axiomasoft/corex:2.0.0", "flex/crm:1.0.x-dev", "laravel/framework:v13.33.0"])
        with self.assertRaisesRegex(module.ProfileError, "did not return the locked package list"):
            module.locked_summary({"installed": []})
        with tempfile.TemporaryDirectory() as temporary:
            consumer = Path(temporary)
            (consumer / "vendor/composer").mkdir(parents=True)
            (consumer / "vendor/composer/installed.json").write_text(json.dumps({"packages": [
                {"name": "axiomasoft/corex", "require": {"php": "^8.4"}},
            ]}))
            with self.assertRaises(module.ProfileError):
                module.assert_cohort_platform(consumer, (8, 3, 0), "php83")

    def test_extension_negative_removes_current_corex_dependencies(self):
        module = load_script("verify_extension_fixtures.py")
        with tempfile.TemporaryDirectory() as temporary:
            consumer = Path(temporary)
            fixture = ROOT / "fixtures/extensions/custom-search-provider"
            module.write_consumer(consumer, fixture, consumer / "candidate", drop_corex=True)
            requirements = json.loads((consumer / "fixture/composer.json").read_text())["require"]
            self.assertFalse(any(name == "axiomasoft/corex" or name.startswith("axiomasoft/corex-") for name in requirements))
            self.assertIn("php", requirements)


if __name__ == "__main__":
    unittest.main()
