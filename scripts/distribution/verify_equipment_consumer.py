#!/usr/bin/env python3
"""Install the equipment registry from the frozen candidate and run its smoke flow."""
from __future__ import annotations

import argparse
import importlib.util
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

# Also support the test harness loading this script by file path.
sys.path.insert(0, str(Path(__file__).resolve().parent))
from ecosystem_repositories import is_corex_package, snapshot_repositories

ROOT = Path(__file__).resolve().parents[2]
APP = ROOT / "fixtures" / "consumers" / "equipment-registry"
RECEIPT = ROOT / "build" / "distribution" / "consumer-receipt.json"
PROBE = ROOT / "scripts" / "distribution" / "extension_fixture_probe.php"
BANNED = re.compile(r"\b(?:flex|crm|cms|store|storefront|customer|lead|deal)\b|content-page|Flex\\\\|flex/", re.IGNORECASE)
PACKAGE = "equipment/registry"
FORBIDDEN = r"CoreX\Search\Internal\UnavailableSearchProvider"


def load_extension_verifier():
    script = ROOT / "scripts/distribution/verify_extension_fixtures.py"
    spec = importlib.util.spec_from_file_location("verify_extension_fixtures", script)
    if spec is None or spec.loader is None:
        raise SystemExit("extension verifier is unavailable")
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    return module


def banned_hits(root: Path) -> list[str]:
    hits = []
    for path in root.rglob("*"):
        if not path.is_file() or path.suffix not in {".php", ".json", ".md"}:
            continue
        if BANNED.search(path.read_text(encoding="utf-8")):
            hits.append(str(path.relative_to(root)))
    return hits


def flex_dependency_fails(composer: dict) -> bool:
    return "flex/crm" in composer.get("require", {})


def prepare_install(source: Path, destination: Path, repository: Path) -> None:
    shutil.copytree(source, destination, ignore=shutil.ignore_patterns("vendor"))
    composer = json.loads((destination / "composer.json").read_text(encoding="utf-8"))
    composer["repositories"] = [
        {"type": "composer", "url": str(repository)},
        *snapshot_repositories(ROOT),
        {"type": "composer", "url": "https://repo.packagist.org", "exclude": ["axiomasoft/corex", "axiomasoft/corex-*", "corex/*"]},
        {"packagist.org": False},
    ]
    composer["config"] = {"allow-plugins": True, "secure-http": False, "preferred-install": "dist", "sort-packages": True}
    (destination / "composer.json").write_text(json.dumps(composer, indent=4) + "\n", encoding="utf-8")


def prove(candidate: Path) -> dict:
    hits = banned_hits(APP)
    if hits:
        raise SystemExit(f"forbidden vocabulary: {hits}")
    committed = json.loads((APP / "composer.json").read_text(encoding="utf-8"))
    if flex_dependency_fails(committed):
        raise SystemExit("committed consumer requires flex/crm")
    planted = {**committed, "require": {**committed["require"], "flex/crm": "^1.0"}}
    if not flex_dependency_fails(planted):
        raise SystemExit("flex/crm dependency was not rejected")
    extensions = load_extension_verifier()
    with tempfile.TemporaryDirectory(prefix="corex-equipment-") as temporary:
        root = Path(temporary)
        output = root / "candidate"
        if (candidate / "candidate.json").is_file():
            built = candidate
        else:
            built = extensions.build_candidate(ROOT, output)
        binding = extensions.candidate_binding(built)
        repository = root / "repository"
        extensions.materialize_repository(built, repository)
        home = root / "composer-home"
        home.mkdir()
        install_root = root / "app"
        prepare_install(APP, install_root, repository)
        installed = extensions.install(install_root, extensions.composer_env(home))
        if installed.returncode != 0:
            raise SystemExit(installed.stderr or installed.stdout or "equipment install failed")
        autoload = (install_root / "vendor/composer/autoload_psr4.php").read_text(encoding="utf-8")
        if extensions.autoload_contains_workspace(autoload, ROOT):
            raise SystemExit("generated autoload records the workspace")
        probe = extensions.run([
            "php", str(PROBE),
            "--vendor", str(install_root / "vendor"),
            "--fixture", str(install_root),
            "--class", r"Equipment\Registry\Smoke",
            "--package", PACKAGE,
            "--forbidden", FORBIDDEN,
            "--workspace", str(ROOT.resolve()),
        ], install_root, os.environ.copy())
        if probe.returncode != 0:
            raise SystemExit(probe.stderr or probe.stdout or "equipment probe failed")
        contract = json.loads(probe.stdout)
        database = root / "equipment.sqlite"
        env = os.environ.copy()
        for mode in ("schedule", "worker", "read"):
            completed = subprocess.run(
                ["php", "bin/smoke.php", mode, str(database)],
                cwd=install_root,
                env=env,
                text=True,
                capture_output=True,
                check=False,
            )
            if completed.returncode != 0:
                raise SystemExit(completed.stderr or completed.stdout or f"{mode} failed")
            if mode == "read":
                result = json.loads(completed.stdout)
    if result["maintenance"] != "completed" or result["imported"] != 1 or result["failed"] != "missing-serial":
        raise SystemExit(f"worker restart lost the equipment result: {result}")
    return {
        "schema_version": "corex-equipment-consumer-receipt/1",
        "item": "P17.5",
        "result": "PASS",
        **binding,
        "package": PACKAGE,
        "fingerprint": contract["fingerprint"],
        "internal_import": contract["internal_import"],
        "flex_dependency": "rejected",
        "vocabulary": "clean",
        "autoload_workspace": False,
        "worker_restart": result,
        "lower_level_packages": sorted(name for name in committed["require"] if is_corex_package(name)),
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--candidate", type=Path)
    parser.add_argument("--receipt", type=Path, default=RECEIPT)
    args = parser.parse_args()
    receipt = prove(args.candidate or Path())
    args.receipt.parent.mkdir(parents=True, exist_ok=True)
    args.receipt.write_text(json.dumps(receipt, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    print(json.dumps({"result": receipt["result"], "receipt": str(args.receipt)}))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
