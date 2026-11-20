#!/usr/bin/env python3
"""Install five standalone extension fixtures against the frozen dist candidate."""
from __future__ import annotations

import argparse
import hashlib
import importlib.util
import json
import os
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

# Also support the test harness loading this script by file path.
sys.path.insert(0, str(Path(__file__).resolve().parent))
from ecosystem_repositories import is_corex_package
from consumer_repository import assert_dist_install, repositories

ROOT = Path(__file__).resolve().parents[2]
FIXTURES = (
    {
        "id": "custom-search-provider",
        "package": "thirdparty/custom-search-provider",
        "class": r"ThirdParty\Search\Module",
        "forbidden": r"CoreX\Search\Internal\UnavailableSearchProvider",
    },
    {
        "id": "custom-attribute-source",
        "package": "thirdparty/custom-attribute-source",
        "class": r"ThirdParty\Attributes\Module",
        "forbidden": r"CoreX\CustomFields\Internal\UnavailableAttributes",
    },
    {
        "id": "custom-transfer-codec",
        "package": "thirdparty/custom-transfer-codec",
        "class": r"ThirdParty\Transfer\Module",
        "forbidden": r"CoreX\ImportExport\Internal\Codecs\NdjsonCodec",
    },
    {
        "id": "custom-panel-provider",
        "package": "thirdparty/custom-panel-provider",
        "class": r"ThirdParty\Panels\Module",
        "forbidden": r"CoreX\Panels\Internal\ModulePanelCatalogue",
    },
    {
        "id": "custom-workflow-activity",
        "package": "thirdparty/custom-workflow-activity",
        "class": r"ThirdParty\Workflow\Module",
        "forbidden": r"CoreX\Workflow\Internal\WorkflowDefinitionStamp",
    },
)


def forbidden_diagnostic(package: str, symbol: str) -> str:
    return f"{package} imports forbidden {symbol}"


def autoload_contains_workspace(payload: str, workspace: Path) -> bool:
    resolved = str(workspace.resolve())
    return str(workspace) in payload or resolved in payload


def fixture_composer(fixture: Path) -> dict:
    return json.loads((fixture / "composer.json").read_text(encoding="utf-8"))


def assert_source_autoload(fixture: Path, workspace: Path) -> None:
    payload = (fixture / "composer.json").read_text(encoding="utf-8")
    if autoload_contains_workspace(payload, workspace):
        raise SystemExit(f"{fixture.name}: autoload records the workspace")
    composer = json.loads(payload)
    for prefix, path in composer.get("autoload", {}).get("psr-4", {}).items():
        if Path(path).is_absolute() or ".." in Path(path).parts:
            raise SystemExit(f"{fixture.name}: autoload path {path} is not package-local")
        if not prefix.startswith("ThirdParty\\"):
            raise SystemExit(f"{fixture.name}: namespace {prefix} is not ThirdParty")


def materialize_repository(candidate: Path, destination: Path) -> None:
    packages = json.loads((candidate / "packages.json").read_text(encoding="utf-8"))
    for versions in packages["packages"].values():
        for metadata in versions.values():
            url = metadata["dist"]["url"]
            if "://" not in url:
                metadata["dist"]["url"] = str((destination / url).resolve())
    destination.mkdir(parents=True)
    (destination / "packages.json").write_text(json.dumps(packages), encoding="utf-8")
    shutil.copytree(candidate / "artifacts", destination / "artifacts")


def write_consumer(consumer: Path, fixture: Path, repository: Path, *, drop_corex: bool) -> None:
    source = fixture_composer(fixture)
    requirements = dict(source["require"])
    if drop_corex:
        requirements = {name: constraint for name, constraint in requirements.items() if not is_corex_package(name)}
    target = consumer / "fixture"
    shutil.copytree(fixture, target)
    (target / "composer.json").write_text(json.dumps({**source, "require": requirements}, indent=4) + "\n", encoding="utf-8")
    composer = {
        "name": "corex/extension-fixture-consumer",
        "type": "project",
        "require": {source["name"]: "@dev"},
        "repositories": [
            {"type": "path", "url": "fixture", "options": {"symlink": False}},
            *repositories(repository, ROOT),
        ],
        "minimum-stability": "dev",
        "prefer-stable": True,
        "config": {"allow-plugins": True, "secure-http": False, "preferred-install": "dist"},
    }
    (consumer / "composer.json").write_text(json.dumps(composer, indent=4) + "\n", encoding="utf-8")


def run(command: list[str], cwd: Path, env: dict[str, str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(command, cwd=cwd, env=env, text=True, capture_output=True, check=False)


def composer_env(home: Path) -> dict[str, str]:
    env = os.environ.copy()
    env.update({
        "COMPOSER_HOME": str(home),
        "COMPOSER_DISABLE_XDEBUG_WARN": "1",
        "COMPOSER_NO_INTERACTION": "1",
    })
    return env


def install(consumer: Path, env: dict[str, str]) -> subprocess.CompletedProcess[str]:
    return run(["composer", "update", "--no-dev", "--no-interaction", "--no-progress", "--prefer-dist"], consumer, env)


def prove(consumer: Path, fixture: dict, workspace: Path) -> dict:
    vendor = consumer / "vendor"
    autoload = (vendor / "composer" / "autoload_psr4.php").read_text(encoding="utf-8")
    if autoload_contains_workspace(autoload, workspace):
        raise SystemExit(f"{fixture['id']}: generated autoload records the workspace")
    completed = run([
        "php", str(ROOT / "scripts/distribution/extension_fixture_probe.php"),
        "--vendor", str(vendor),
        "--fixture", str(consumer / "fixture"),
        "--class", fixture["class"],
        "--package", fixture["package"],
        "--forbidden", fixture["forbidden"],
        "--workspace", str(workspace.resolve()),
    ], consumer, os.environ.copy())
    if completed.returncode != 0:
        raise SystemExit(completed.stderr or completed.stdout or f"{fixture['id']} probe failed")
    return json.loads(completed.stdout)


def missing_dependency_is_red(fixture: Path, repository: Path, home: Path, workspace: Path, spec: dict) -> str:
    with tempfile.TemporaryDirectory(prefix="corex-extension-missing-") as temporary:
        consumer = Path(temporary)
        write_consumer(consumer, fixture, repository, drop_corex=True)
        env = composer_env(home)
        completed = install(consumer, env)
        if completed.returncode != 0:
            return "composer-rejected"
        probed = run([
            "php", str(ROOT / "scripts/distribution/extension_fixture_probe.php"),
            "--vendor", str(consumer / "vendor"),
            "--fixture", str(consumer / "fixture"),
            "--class", spec["class"],
            "--package", spec["package"],
            "--forbidden", spec["forbidden"],
            "--workspace", str(workspace.resolve()),
        ], consumer, os.environ.copy())
        if probed.returncode == 0:
            raise SystemExit(f"{spec['id']}: missing candidate dependency was accepted")
        return "probe-rejected"


def selected_fixtures(only: str | None) -> tuple[dict, ...]:
    if only is None:
        return FIXTURES
    chosen = tuple(spec for spec in FIXTURES if spec["id"] == only)
    if not chosen:
        raise SystemExit(f"unknown fixture {only}")
    return chosen


def verify(candidate: Path, workspace: Path = ROOT, only: str | None = None) -> dict:
    candidate = candidate_directory(candidate)
    binding = candidate_binding(candidate)
    rows = []
    with tempfile.TemporaryDirectory(prefix="corex-extension-fixtures-") as temporary:
        root = Path(temporary)
        repository = root / "repository"
        materialize_repository(candidate, repository)
        home = root / "composer-home"
        home.mkdir()
        for spec in selected_fixtures(only):
            fixture = workspace / "fixtures/extensions" / spec["id"]
            assert_source_autoload(fixture, workspace)
            consumer = root / spec["id"]
            write_consumer(consumer, fixture, repository, drop_corex=False)
            installed = install(consumer, composer_env(home))
            if installed.returncode != 0:
                raise SystemExit(installed.stderr or installed.stdout or f"{spec['id']} install failed")
            proof = prove(consumer, spec, workspace)
            proof.update(assert_dist_install(consumer, repository, list(fixture_composer(fixture)["require"]), workspace))
            proof["missing_dependency"] = missing_dependency_is_red(fixture, repository, home, workspace, spec)
            proof["id"] = spec["id"]
            rows.append(proof)
    receipt = {
        "schema_version": "corex-extension-fixture-receipt/1",
        "item": "P17.2",
        "result": "PASS",
        **binding,
        "fixtures": rows,
    }
    if any(row["contract"] != "pass" or row["internal_import"] != forbidden_diagnostic(row["package"], next(spec["forbidden"] for spec in FIXTURES if spec["package"] == row["package"])) for row in rows):
        receipt["result"] = "FAIL"
    return receipt


def release_candidate_module():
    script = ROOT / "scripts/distribution/release_candidate.py"
    spec = importlib.util.spec_from_file_location("release_candidate", script)
    if spec is None or spec.loader is None:
        raise SystemExit("release candidate builder is unavailable")
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    return module


def candidate_directory(path: Path) -> Path:
    return path.parent if path.name == "candidate.json" else path


def candidate_binding(directory: Path) -> dict:
    """Validate dist bytes before binding a proof to its actual candidate."""
    module = release_candidate_module()
    path = candidate_directory(directory) / "candidate.json"
    document = module.verify_candidate(path, ROOT, composer_checks=False)
    return {"source_commit": document["source_commit"],
            "candidate_version": document["version"],
            "candidate_sha256": hashlib.sha256(path.read_bytes()).hexdigest()}


def build_candidate(root: Path, output: Path) -> Path:
    module = release_candidate_module()
    source = module.resolve_commit(root, 'HEAD')
    policy = module.source_policy(root, source)
    module.build_repository(root, source, module.approved_version(policy), output, policy, enforce_clean=True)
    return output


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--candidate", type=Path)
    parser.add_argument("--only")
    parser.add_argument("--receipt", type=Path)
    args = parser.parse_args()
    if args.candidate:
        receipt = verify(args.candidate, only=args.only)
    else:
        with tempfile.TemporaryDirectory(prefix="corex-extension-candidate-") as temporary:
            candidate = build_candidate(ROOT, Path(temporary) / "candidate")
            receipt = verify(candidate, only=args.only)
    if args.receipt:
        args.receipt.parent.mkdir(parents=True, exist_ok=True)
        args.receipt.write_text(json.dumps(receipt, indent=2, sort_keys=True) + "\n")
    print(json.dumps(receipt, indent=2))
    return 0 if receipt["result"] == "PASS" else 1


if __name__ == "__main__":
    raise SystemExit(main())
