#!/usr/bin/env python3
"""Run the public and Octane consumers against the candidate ZIP repository."""
from __future__ import annotations

import argparse
import importlib.util
import json
import os
import shutil
import sys
import tempfile
from pathlib import Path

from consumer_repository import ConsumerError, assert_dist_install, load_json, repositories
from release_candidate import CandidateError
import verify_extension_fixtures as extensions

ROOT = Path(__file__).resolve().parents[2]
PUBLIC = ROOT / "tests/Distribution/fixtures/public-consumer"
OCTANE = ROOT / "packages/core/tests/Release/octane-consumer"


def install(consumer: Path, repository: Path, home: Path, php: str) -> dict:
    manifest = load_json(consumer / "composer.json")
    manifest["repositories"] = repositories(repository, ROOT)
    manifest["config"] = {
        **manifest.get("config", {}),
        "secure-http": False,
        "preferred-install": "dist",
        "platform": {"php": php},
    }
    (consumer / "composer.json").write_text(json.dumps(manifest, indent=4) + "\n", encoding="utf-8")
    completed = extensions.run(
        extensions.profile_proof_module().composer_command("update", "--prefer-dist", "--no-scripts", "--no-interaction", "--no-progress"),
        consumer, extensions.composer_env(home, ".".join(php.split(".")[:2])),
    )
    if completed.returncode:
        raise ConsumerError(completed.stderr or completed.stdout or "Composer install failed")
    return assert_dist_install(consumer, repository, list(manifest["require"]), ROOT)


def php_version(binary: str) -> str:
    completed = extensions.run([binary, "-r", "echo PHP_VERSION;"], ROOT, os.environ.copy())
    if completed.returncode:
        raise ConsumerError(completed.stderr or "PHP is unavailable")
    return completed.stdout.strip()


def public_proof(consumer: Path, binary: str, version: str) -> dict:
    probe = r"""
require 'vendor/autoload.php';
$provider = new ReflectionClass(CoreX\Tasks\TasksServiceProvider::class);
echo json_encode([
    'version' => Composer\InstalledVersions::getPrettyVersion('axiomasoft/corex-tasks'),
    'provider' => $provider->getFileName(),
], JSON_THROW_ON_ERROR);
"""
    completed = extensions.run([binary, "-r", probe], consumer, os.environ.copy())
    if completed.returncode:
        raise ConsumerError(completed.stderr or "public consumer failed")
    result = json.loads(completed.stdout)
    expected = consumer / "vendor/axiomasoft/corex-tasks/src/TasksServiceProvider.php"
    if result != {"version": version, "provider": str(expected)}:
        raise ConsumerError(f"public consumer loaded unexpected Tasks provider: {result}")
    return {"assertions": 2, "provider": "CoreX\\Tasks\\TasksServiceProvider"}


def octane_proof(consumer: Path, binary: str, evidence: Path) -> dict:
    script = ROOT / "scripts/quality/octane-proof.py"
    spec = importlib.util.spec_from_file_location("octane_proof", script)
    proof = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(proof)
    traces = evidence / "traces"
    traces.mkdir()
    junit = evidence / "junit.xml"
    env = {**os.environ, "COREX_OCTANE_TRACE_DIR": str(traces)}
    code = proof.run([binary, "vendor/bin/phpunit", "-c", "phpunit.xml", "--log-junit", str(junit)],
                     cwd=consumer, env=env, log=evidence / "worker.log")
    if code != 0:
        raise ConsumerError(f"real Octane worker failed: {evidence / 'worker.log'}")
    try:
        cases = proof.junit_cases(junit)
        pids = set()
        for case in cases:
            recorded = load_json(traces / f"{case}.json")
            proof.validate_trace(recorded, case)
            pids.add(recorded["pid"])
        proof.require(len(pids) == 1, "Octane cases did not execute in one persistent process")
    except ValueError as error:
        raise ConsumerError(str(error)) from error
    installed = {row["name"]: row for row in load_json(consumer / "composer.lock")["packages"]}
    return {
        "assertions": sum(cases.values()), "cases": cases, "pid": pids.pop(),
        "laravel": installed["laravel/framework"]["version"], "octane": installed["laravel/octane"]["version"],
    }


def prove(candidate: Path, which: str, minor: str, evidence: Path) -> dict:
    binary = shutil.which(f"php{minor}") or shutil.which("php")
    if binary is None:
        raise ConsumerError(f"PHP {minor} is unavailable")
    version = php_version(binary)
    if not version.startswith(f"{minor}."):
        raise ConsumerError(f"PHP {minor} is unavailable, found {version}")
    binding = extensions.candidate_binding(candidate)
    evidence.mkdir(parents=True, exist_ok=False)
    with tempfile.TemporaryDirectory(prefix=f"corex-{which}-dist-") as temporary:
        root = Path(temporary)
        repository = root / "repository"
        extensions.materialize_repository(extensions.candidate_directory(candidate), repository)
        fixture = PUBLIC if which == "public" else OCTANE
        consumer = root / fixture.name
        shutil.copytree(fixture, consumer, ignore=shutil.ignore_patterns("vendor", "composer.lock", ".phpunit.result.cache"))
        if which == "octane":
            shutil.copyfile(fixture.parent / "OctaneLifecycleTest.php", root / "OctaneLifecycleTest.php")
        lock = install(consumer, repository, root / "home", version)
        observed = public_proof(consumer, binary, binding["candidate_version"]) if which == "public" else octane_proof(consumer, binary, evidence)
    receipt = {**binding, **lock, **observed, "consumer": which, "php": version, "result": "PASS"}
    (evidence / "receipt.json").write_text(json.dumps(receipt, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    return receipt


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", type=Path, required=True)
    parser.add_argument("--consumer", choices=("public", "octane"), required=True)
    parser.add_argument("--php", choices=("8.4", "8.5"), default="8.4")
    parser.add_argument("--evidence-dir", type=Path, required=True)
    args = parser.parse_args()
    try:
        receipt = prove(args.candidate, args.consumer, args.php, args.evidence_dir)
    except (ConsumerError, CandidateError, OSError, ValueError) as error:
        print(f"installed consumer denied: {error}", file=sys.stderr)
        return 1
    print(json.dumps(receipt, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
