#!/usr/bin/env python3
"""Run a named Laravel scenario on a verified, isolated archive installation."""
from __future__ import annotations

import argparse
import importlib.util
import json
import os
import re
import shutil
import signal
import subprocess
import sys
import tempfile
import time
from contextlib import ExitStack
from pathlib import Path

from consumer_repository import ConsumerError, assert_dist_install, load_json
from release_candidate import CandidateError, git_bytes, resolve_commit
import verify_extension_fixtures as extensions

ROOT = Path(__file__).resolve().parents[2]
FIXTURES = ROOT / "tests/Distribution/fixtures/scenarios"
STAGES = Path(__file__).with_name("scenario-stages.json")
NAME = re.compile(r"[a-z][a-z0-9-]*")


class ScenarioError(RuntimeError):
    """A required scenario failed or could not execute."""


def profile_module():
    script = Path(__file__).with_name("profile-proof.py")
    spec = importlib.util.spec_from_file_location("profile_proof", script)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def write_json(path: Path, value: dict) -> None:
    path.write_text(json.dumps(value, indent=2, sort_keys=True) + "\n", encoding="utf-8")


def barrier_path(directory: Path, name: str, suffix: str) -> Path:
    if not NAME.fullmatch(name):
        raise ScenarioError(f"invalid barrier name: {name}")
    return directory / f"{name}.{suffix}"


def stop(process: subprocess.Popen, witness: dict) -> None:
    if process.poll() is None:
        try:
            os.killpg(process.pid, signal.SIGKILL)
        except ProcessLookupError:
            pass
    witness["exit_code"] = process.wait()


def read_witness(output: Path, pid: int) -> dict:
    report = load_json(output)
    if report.get("pid") != pid or type(report.get("assertions")) is not int or report["assertions"] <= 0:
        raise ScenarioError(f"{output.name}: missing process or assertion evidence")
    for key in ("backend", "connection", "refs", "before", "after"):
        if key not in report:
            raise ScenarioError(f"{output.name}: missing {key} evidence")
    return report


def run_actions(spec: dict, consumer: Path, fixture: Path, env: dict, evidence: Path, trace: dict) -> dict:
    directory = evidence / "barriers"
    directory.mkdir()
    timeout = spec.get("timeout_seconds", 30)
    deadline = time.monotonic() + timeout
    runtime = {**env, "COREX_SCENARIO_BARRIERS": str(directory)}
    running = {}
    reports = {}
    with ExitStack() as cleanup:
        for item in spec["actions"]:
            action, name = item["action"], item["name"]
            if not NAME.fullmatch(name):
                raise ScenarioError(f"invalid action name: {name}")
            if action == "start":
                if name in running:
                    raise ScenarioError(f"duplicate worker: {name}")
                stdout = evidence / f"{name}.json"
                log = cleanup.enter_context(stdout.open("w", encoding="utf-8"))
                errors = cleanup.enter_context((evidence / f"{name}.stderr").open("w", encoding="utf-8"))
                command = spec["processes"][name]
                command = [value.replace("{fixture}", str(fixture)) for value in command]
                process = subprocess.Popen(command, cwd=consumer, env=runtime, stdout=log, stderr=errors, start_new_session=True)
                running[name] = process
                trace[name] = {"pid": process.pid}
                cleanup.callback(stop, process, trace[name])
            elif action == "await":
                reached = barrier_path(directory, name, "reached")
                while not reached.is_file():
                    if time.monotonic() >= deadline:
                        raise ScenarioError(f"barrier timeout: {name}")
                    if not any(process.poll() is None for process in running.values()):
                        raise ScenarioError(f"workers exited before barrier: {name}")
                    time.sleep(0.025)
            elif action == "release":
                if not barrier_path(directory, name, "reached").is_file():
                    raise ScenarioError(f"barrier was not reached: {name}")
                with barrier_path(directory, name, "release").open("x", encoding="utf-8"):
                    pass
            elif action == "complete":
                process = running[name]
                code = process.wait(timeout=max(0.01, deadline - time.monotonic()))
                trace[name]["exit_code"] = code
                if code != 0:
                    raise ScenarioError(f"{name} failed with exit status {code}: {evidence / (name + '.stderr')}")
                reports[name] = read_witness(evidence / f"{name}.json", process.pid)
            else:
                raise ScenarioError(f"unknown scenario action: {action}")
    if not reports or set(reports) != set(spec["processes"]):
        raise ScenarioError("each declared worker must complete with assertions")
    if spec.get("distinct_connections") and len({report["connection"] for report in reports.values()}) != len(reports):
        raise ScenarioError("workers did not obtain independent database connections")
    return reports


def prove(candidate: Path | None, case: str, evidence: Path, php: str) -> dict:
    phases = load_json(STAGES)
    if case not in phases:
        raise ScenarioError(f"unknown scenario: {case}")
    fixture = FIXTURES / case
    if not (fixture / "case.json").is_file():
        raise ScenarioError(f"{case} is required from stage {phases[case]} and its fixture is missing")
    fixtures = [fixture.relative_to(ROOT), STAGES.relative_to(ROOT)]
    if git_bytes(ROOT, "status", "--porcelain=v1", "--untracked-files=all", "--", *map(str, fixtures)):
        raise ScenarioError(f"scenario source is dirty: {case}")
    spec = load_json(fixture / "case.json")
    if spec["id"] != case:
        raise ScenarioError(f"scenario identity mismatch: {case}")
    evidence.mkdir(parents=True, exist_ok=False)
    profiles = profile_module()
    interpreter = profiles.interpreter_for(php, profiles.interpreters())
    with tempfile.TemporaryDirectory(prefix=f"corex-scenario-{case}-") as temporary:
        root = Path(temporary)
        built = extensions.candidate_directory(candidate) if candidate else extensions.build_candidate(ROOT, root / "candidate")
        binding = extensions.candidate_binding(built)
        if binding["source_commit"] != resolve_commit(ROOT, "HEAD"):
            raise ScenarioError("scenario fixture checkout must match the candidate source commit")
        receipt = {
            **binding, "case": case, "profile": spec["profile"]["id"], "exit_code": 1,
            "php": ".".join(map(str, interpreter["version"])), "processes": {},
        }
        try:
            repository = root / "repository"
            extensions.materialize_repository(built, repository)
            profile = spec["profile"]
            packages = profiles.expand_packages(profile, set(profiles.archive_composers(built)))
            cell = {"id": case, "packages": packages, "php": php, "laravel": spec["laravel"]}
            consumer, runtime, declared = profiles.install_app(root / "app", cell, profile, repository, ROOT, interpreter, binding["candidate_version"])
            host = fixture / "host"
            if host.is_dir():
                shutil.copytree(host, consumer, dirs_exist_ok=True)
            for arguments in spec.get("artisan", []):
                command = ["php", "artisan", *arguments]
                completed = profiles.run(command, consumer, runtime)
                if completed.returncode:
                    profiles.fail_command(case, completed)
            booted = profiles.boot_app(consumer, runtime, case)
            profiles.assert_providers(consumer, booted, case)
            profiles.assert_profile_clean(profile, booted["packages"], http_routes=booted["http_routes"])
            receipt.update(assert_dist_install(consumer, repository, declared, ROOT))
            witnesses = run_actions(spec, consumer, fixture, runtime, evidence, receipt["processes"])
            receipt.update({
                "assertions": sum(report["assertions"] for report in witnesses.values()),
                "witnesses": witnesses, "exit_code": 0,
            })
        except (ConsumerError, ScenarioError, OSError, ValueError, KeyError, RuntimeError, subprocess.SubprocessError) as error:
            receipt["error"] = str(error)
            raise
        finally:
            write_json(evidence / "receipt.json", receipt)
    return receipt


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", type=Path)
    parser.add_argument("--case", required=True)
    parser.add_argument("--evidence-dir", type=Path)
    parser.add_argument("--php", choices=("8.4", "8.5"), default="8.4")
    args = parser.parse_args()
    evidence = args.evidence_dir
    if evidence is None:
        parent = ROOT / "build/distribution/scenarios"
        parent.mkdir(parents=True, exist_ok=True)
        evidence = Path(tempfile.mkdtemp(prefix="run-", dir=parent)) / "evidence"
    try:
        receipt = prove(args.candidate, args.case, evidence.resolve(), args.php)
    except (CandidateError, OSError, ValueError, KeyError, RuntimeError, subprocess.SubprocessError) as error:
        print(f"scenario denied: {error}", file=sys.stderr)
        return 1
    print(json.dumps({"result": "PASS", "case": args.case, "assertions": receipt["assertions"], "receipt": str(evidence / "receipt.json")}))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
