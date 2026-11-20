#!/usr/bin/env python3
"""Bounded source proof using real Octane Worker handlers and a locked consumer."""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import xml.etree.ElementTree as ET

CONSUMER = Path("packages/core/tests/Release/octane-consumer")
CONSUMER_LOCK = ".composer-snapshots.lock"
IMPLEMENTATION = "Laravel\\Octane\\Worker"
TENANTS = ["0198a000-0000-7000-8000-00000000000a", "0198a000-0000-7000-8000-00000000000b"]
CASES = {
    "test_tenant_request_isolation": (2, 0, [200, 200], []),
    "test_failed_request_cleanup": (3, 0, [200, 500, 200], []),
    "test_worker_error_cleanup": (1, 1, [200], ["fixture transport failure"]),
    "test_worker_restart": (2, 0, [200, 200], []),
}
EVENT_PREFIX = "Laravel\\Octane\\Events\\"


class ProofError(ValueError):
    pass


def require(condition: bool, message: str) -> None:
    if not condition:
        raise ProofError(message)


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def source_hashes(root: Path) -> dict[str, str]:
    files = [root / "composer.json", root / "composer.lock", root / "packages/core/composer.json",
             root / "packages/core/tests/Release/OctaneLifecycleTest.php",
             root / "scripts/quality/octane-proof.py"]
    files += sorted((root / "packages/core/src").rglob("*.php"))
    files += sorted((root / "packages/core/config").rglob("*.php"))
    files += [root / CONSUMER / "composer.json", root / CONSUMER / "phpunit.xml",
              root / CONSUMER / "LifecycleServiceProvider.php"]
    files += sorted((root / CONSUMER / "bootstrap").glob("*.php"))
    files += sorted((root / CONSUMER / "config").glob("*.php"))
    return {str(path.relative_to(root)): sha256(path) for path in files}


def core_hashes(package: Path) -> dict[str, str]:
    files = [package / "composer.json"]
    files += sorted((package / "src").rglob("*.php"))
    files += sorted((package / "config").rglob("*.php"))
    return {str(path.relative_to(package)): sha256(path) for path in files}


def octane_hashes(consumer: Path) -> dict[str, str]:
    package = consumer / "vendor/laravel/octane"
    paths = sorted((package / "src").rglob("*.php")) + [package / "config/octane.php"]
    require(bool(paths) and (package / "src/Worker.php").is_file(), "actual Octane package is missing")
    return {str(path.relative_to(consumer)): sha256(path) for path in paths}


def junit_cases(path: Path) -> dict[str, int]:
    tree = ET.parse(path)
    require(all(next(tree.iter(tag), None) is None for tag in ("failure", "error", "skipped")),
            "failed/error/skipped Octane case")
    for suite in tree.iter("testsuite"):
        require(all(int(suite.get(tag, "0")) == 0 for tag in ("failures", "errors", "skipped", "warnings")),
                "non-green JUnit suite counters")
    cases = list(tree.iter("testcase"))
    require(len(cases) == len(CASES), "missing or duplicated Octane case")
    result = {}
    for case in cases:
        name = case.get("name")
        require(case.get("class") == "OctaneLifecycleTest" and name in CASES
                and name not in result, "wrong Octane handler case")
        assertions = int(case.get("assertions", "0"))
        require(assertions > 0, "unexecuted Octane assertions")
        result[name] = assertions
    return result


def validate_trace(trace: dict, case: str) -> None:
    require(trace.get("case") == case and trace.get("executed") is True, "unexecuted handler")
    require(trace.get("implementation") == IMPLEMENTATION
            and trace.get("execution_scope") == "real-php-worker-handler", "wrong or modeled handler")
    require(type(trace.get("pid")) is int and trace["pid"] > 0, "missing PHP process witness")
    tenants = [*TENANTS, TENANTS[0]] if case == "test_failed_request_cleanup" else TENANTS
    owners = ["lifecycle-tenant-" + tenant[-1] for tenant in tenants]
    require(trace.get("handles") == len(tenants) and trace.get("tenants") == tenants, "missing tenant handler cycles")
    require(trace.get("markers") == tenants and trace.get("connections") == owners and trace.get("bindings") == owners,
            "missing independent tenant binding and database evidence")
    require(trace.get("principals") == ["fixture-user:" + tenant for tenant in tenants], "missing scoped principal evidence")
    terminated, errors, statuses, messages = CASES[case]
    require(trace.get("statuses") == statuses and trace.get("errors") == messages, "failure cleanup outcome changed")
    expected_events = {"RequestReceived": len(tenants), "RequestHandled": len(tenants),
                       "RequestTerminated": terminated, "WorkerErrorOccurred": errors,
                       "WorkerStarting": 2 if case == "test_worker_restart" else 1,
                       "WorkerStopping": 2 if case == "test_worker_restart" else 1}
    actual = trace.get("events", {})
    require(all(actual.get(EVENT_PREFIX + key, 0) == count for key, count in expected_events.items()),
            "missing lifecycle event or cleanup")


def artifact_path(directory: Path, relative: str) -> Path:
    path = (directory / relative).resolve()
    require(path.is_relative_to(directory.resolve()), "receipt path escapes evidence directory")
    require(path.is_file(), "missing raw receipt artifact")
    return path


def validate_receipt(receipt: dict, root: Path, directory: Path, matrix: list[str]) -> None:
    consumer = root / CONSUMER
    require(receipt.get("schema") == "corex-octane-source-proof/v1", "wrong receipt schema")
    require(receipt.get("source_hashes") == source_hashes(root), "source/hash binding changed")
    require(receipt.get("consumer_lock_sha256") == sha256(consumer / CONSUMER_LOCK), "consumer lock changed")
    require(receipt.get("octane_hashes") == octane_hashes(consumer), "installed Octane source changed")
    installed_core = consumer / "vendor/axiomasoft/corex"
    source_core_hashes = core_hashes(root / "packages/core")
    require(receipt.get("installed_core_hashes") == core_hashes(installed_core) == source_core_hashes,
            "installed CoreX mirror differs from the verified source")
    lock = json.loads((consumer / CONSUMER_LOCK).read_text())
    packages = {package["name"]: package for package in lock["packages"]}
    cells = receipt.get("cells", [])
    require(len(cells) == len(matrix) and {cell.get("cell") for cell in cells} == set(matrix), "incomplete runtime matrix")
    for cell in cells:
        require(cell.get("executed") is True and type(cell.get("exit_code")) is int
                and cell["exit_code"] == 0, "unexecuted or failed PHP worker")
        runtime = cell["runtime"]
        require(runtime["php"].startswith(cell["cell"] + "."), "wrong PHP version cell")
        for name, prefix, key in [("laravel/framework", "v13.", "laravel"), ("laravel/octane", "v2.", "octane")]:
            version = packages[name]["version"]
            require(version.startswith(prefix) and runtime[key] == version, "wrong installed package version")
        require(runtime["octane_ref"] == packages["laravel/octane"]["source"]["reference"], "wrong Octane source reference")
        require(Path(runtime["core_path"]).resolve() == installed_core.resolve(), "wrong installed CoreX root")
        require(runtime["implementation"] == IMPLEMENTATION
                and Path(runtime["worker_file"]).resolve() == (consumer / "vendor/laravel/octane/src/Worker.php").resolve(),
                "wrong Worker implementation path")
        junit = artifact_path(directory, cell["raw_junit"])
        require(sha256(junit) == cell["raw_junit_sha256"], "raw JUnit hash changed")
        witness = artifact_path(directory, cell["runtime_witness"])
        require(sha256(witness) == cell["runtime_witness_sha256"]
                and json.loads(witness.read_text()) == runtime, "runtime witness changed")
        log = artifact_path(directory, cell["raw_log"])
        require(sha256(log) == cell["raw_log_sha256"], "raw worker log changed")
        counts = junit_cases(junit)
        traces = cell.get("cases", {})
        require(set(traces) == set(CASES), "missing handler receipt")
        pids = set()
        for name, artifact in traces.items():
            path = artifact_path(directory, artifact["path"])
            require(sha256(path) == artifact["sha256"], "handler receipt hash changed")
            trace = json.loads(path.read_text())
            validate_trace(trace, name)
            pids.add(trace["pid"])
            require(artifact["assertions"] == counts[name], "JUnit assertion count mismatch")
        require(len(pids) == 1, "handler cases did not execute in one persistent PHP process")


def run(command: list[str], *, cwd: Path, env: dict, log: Path, timeout: int = 30) -> int:
    with log.open("w") as output:
        try:
            return subprocess.run(command, cwd=cwd, env=env, stdout=output, stderr=subprocess.STDOUT,
                                  timeout=timeout, check=False).returncode
        except subprocess.TimeoutExpired as error:
            raise ProofError(f"bounded command exceeded {timeout}s; see {log}") from error


def produce(root: Path, directory: Path, matrix: list[str]) -> dict:
    consumer = root / CONSUMER
    require((consumer / "vendor/bin/phpunit").is_file(), "install the locked Octane consumer before running proof")
    before = source_hashes(root)
    lock_hash = sha256(consumer / CONSUMER_LOCK)
    octane_before = octane_hashes(consumer)
    installed_core = consumer / "vendor/axiomasoft/corex"
    core_before = core_hashes(installed_core)
    require(core_before == core_hashes(root / "packages/core"), "refresh the installed CoreX mirror from this checkout")
    execution = Path(tempfile.mkdtemp(prefix="run-", dir=directory))
    receipt = {"schema": "corex-octane-source-proof/v1", "source_hashes": before,
               "consumer_lock_sha256": lock_hash, "octane_hashes": octane_before,
               "installed_core_hashes": core_before, "cells": []}
    for cell in matrix:
        php = shutil.which("php" + cell) or shutil.which("php")
        require(php is not None, f"PHP {cell} runtime is unavailable")
        cell_dir = execution / ("php" + cell)
        cell_dir.mkdir()
        traces = cell_dir / "traces"
        traces.mkdir()
        env = {**os.environ, "COREX_OCTANE_TRACE_DIR": str(traces)}
        runtime_code = (
            "require 'vendor/autoload.php'; echo json_encode(["
            "'php'=>PHP_VERSION, 'laravel'=>Composer\\InstalledVersions::getPrettyVersion('laravel/framework'),"
            "'octane'=>Composer\\InstalledVersions::getPrettyVersion('laravel/octane'),"
            "'octane_ref'=>Composer\\InstalledVersions::getReference('laravel/octane'),"
            "'core_path'=>realpath(Composer\\InstalledVersions::getInstallPath('axiomasoft/corex')),"
            "'implementation'=>Laravel\\Octane\\Worker::class,"
            "'worker_file'=>(new ReflectionClass(Laravel\\Octane\\Worker::class))->getFileName()]);"
        )
        runtime_log = cell_dir / "runtime.json"
        require(run([php, "-r", runtime_code], cwd=consumer, env=env, log=runtime_log) == 0, "runtime preflight failed")
        junit = cell_dir / "junit.xml"
        code = run([php, "vendor/bin/phpunit", "-c", "phpunit.xml", "--log-junit", str(junit)],
                   cwd=consumer, env=env, log=cell_dir / "worker.log")
        require(code == 0, f"real Octane Worker failed on {cell}; see {cell_dir / 'worker.log'}")
        counts = junit_cases(junit)
        artifacts = {}
        for case in CASES:
            path = traces / (case + ".json")
            require(path.is_file(), f"missing handler trace {case}")
            artifacts[case] = {"path": str(path.relative_to(directory)), "sha256": sha256(path), "assertions": counts[case]}
        receipt["cells"].append({"cell": cell, "executed": True, "exit_code": code,
                                  "runtime": json.loads(runtime_log.read_text()),
                                  "runtime_witness": str(runtime_log.relative_to(directory)),
                                  "runtime_witness_sha256": sha256(runtime_log),
                                  "raw_log": str((cell_dir / "worker.log").relative_to(directory)),
                                  "raw_log_sha256": sha256(cell_dir / "worker.log"),
                                  "raw_junit": str(junit.relative_to(directory)), "raw_junit_sha256": sha256(junit),
                                  "cases": artifacts})
    require(source_hashes(root) == before and sha256(consumer / CONSUMER_LOCK) == lock_hash
            and octane_hashes(consumer) == octane_before and core_hashes(installed_core) == core_before,
            "source/lock/tool changed during execution")
    validate_receipt(receipt, root, directory, matrix)
    return receipt


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source-root", type=Path, required=True)
    parser.add_argument("--matrix", choices=["all", "8.4", "8.5"], default="all")
    parser.add_argument("--evidence-dir", type=Path, required=True)
    parser.add_argument("--verify", type=Path, help="verify existing raw source receipts without executing handlers")
    args = parser.parse_args()
    root, directory = args.source_root.resolve(), args.evidence_dir.resolve()
    matrix = ["8.4", "8.5"] if args.matrix == "all" else [args.matrix]
    try:
        if args.verify:
            validate_receipt(json.loads(args.verify.read_text()), root, directory, matrix)
        else:
            directory.mkdir(parents=True, exist_ok=True)
            receipt = produce(root, directory, matrix)
            (directory / "receipt.json").write_text(json.dumps(receipt, indent=2) + "\n")
        print(json.dumps({"status": "passed", "cells": matrix, "handler_cases": len(CASES) * len(matrix)}))
        return 0
    except (ProofError, OSError, ValueError, KeyError, TypeError, ET.ParseError) as error:
        print(f"Octane source proof RED: {error}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
