#!/usr/bin/env python3
"""Run a named Laravel scenario on a verified, isolated archive installation."""
from __future__ import annotations

import argparse
import hashlib
import importlib.util
import json
import os
import re
import secrets
import signal
import subprocess
import sys
import tempfile
import time
import uuid
from contextlib import ExitStack
from pathlib import Path
from urllib.error import HTTPError
from urllib.parse import urlsplit
from urllib.request import Request, urlopen

from consumer_repository import ConsumerError, assert_dist_install, load_json
from release_candidate import CandidateError, git_bytes, resolve_commit
import verify_extension_fixtures as extensions

ROOT = Path(__file__).resolve().parents[2]
FIXTURES = ROOT / "tests/Distribution/fixtures/scenarios"
STAGES = Path(__file__).with_name("scenario-stages.json")
SUPPORT = FIXTURES / "support"
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


def barrier_owner(path: Path) -> int:
    witness = json.loads(path.read_text(encoding="utf-8"))
    pid = witness["pid"] if isinstance(witness, dict) else witness
    if type(pid) is not int or pid <= 0:
        raise ScenarioError(f"invalid or missing barrier owner: {path.name}")
    return pid


def read_witness(output: Path, pid: int) -> dict:
    report = load_json(output)
    if report.get("pid") != pid or type(report.get("assertions")) is not int or report["assertions"] <= 0:
        raise ScenarioError(f"{output.name}: missing process or assertion evidence")
    for key in ("backend", "connection", "refs", "before", "after"):
        if key not in report:
            raise ScenarioError(f"{output.name}: missing {key} evidence")
    return report


def meilisearch_environment(version: str) -> tuple[dict[str, str], dict]:
    host = os.environ.get("MEILISEARCH_HOST", "").rstrip("/")
    endpoint = urlsplit(host)
    if (endpoint.scheme != "http" or endpoint.hostname not in {"127.0.0.1", "::1", "localhost"}
            or not endpoint.port or endpoint.username or endpoint.password or endpoint.path
            or endpoint.query or endpoint.fragment):
        raise ScenarioError("MEILISEARCH_HOST must identify the explicit loopback test service and port")
    key = os.environ.get("MEILISEARCH_MASTER_KEY", "")
    if not key:
        raise ScenarioError("MEILISEARCH_MASTER_KEY must identify the disposable Meilisearch client credentials")
    identity = meilisearch_request(host, key, "/version")
    if not isinstance(identity, dict) or identity.get("pkgVersion") != version or not identity.get("commitSha"):
        raise ScenarioError(f"a live Meilisearch {version} service is required")
    namespace = f"corex_scenario_{secrets.token_hex(10)}"
    return {
        "MEILISEARCH_HOST": host,
        "MEILISEARCH_MASTER_KEY": key,
        "COREX_SCENARIO_MEILI_NAMESPACE": namespace,
    }, {**identity, "namespace": namespace}


def meilisearch_request(host: str, key: str, path: str, method: str = "GET") -> dict:
    headers = {"Authorization": f"Bearer {key}"} if key else {}
    with urlopen(Request(f"{host}{path}", headers=headers, method=method), timeout=5) as response:
        body = response.read()
        return json.loads(body) if body else {}


def staged_meilisearch_targets(ledger: Path, namespace: str) -> set[str]:
    targets = set()
    if not ledger.is_file():
        return targets
    for line in ledger.read_text(encoding="utf-8").splitlines():
        record = json.loads(line)
        parts = record["parts"]
        if (len(parts) != 14 or not all(isinstance(part, str) for part in parts)
                or parts[0] != "corex.search.uid.v2" or parts[10:12] != ["meilisearch", namespace]):
            raise ScenarioError("Meilisearch cleanup encountered a coordinate outside this scenario")
        chunks = [part.encode() for part in parts]
        uid = "cx_" + hashlib.sha256(b"".join(len(chunk).to_bytes(4, "big") + chunk for chunk in chunks)).hexdigest()
        if uid != record["uid"]:
            raise ScenarioError("Meilisearch cleanup encountered a noncanonical index coordinate")
        targets.add(uid)
    return targets


def release_meilisearch(env: dict, ledger: Path) -> dict:
    host, key, namespace = (env[name] for name in (
        "MEILISEARCH_HOST", "MEILISEARCH_MASTER_KEY", "COREX_SCENARIO_MEILI_NAMESPACE",
    ))
    released = {}
    for uid in sorted(staged_meilisearch_targets(ledger, namespace)):
        credentials = []
        for access in ("search", "index", "observe"):
            credential = uuid.uuid5(uuid.NAMESPACE_URL, f"urn:corex:scenario-meili:{uid}:{access}")
            try:
                details = meilisearch_request(host, key, f"/keys/{credential}")
            except HTTPError as response:
                if response.code != 404:
                    raise
                continue
            if (details.get("uid") != str(credential) or details.get("name") != f"CoreX scenario {namespace} {access}"
                    or details.get("indexes") != [uid]):
                raise ScenarioError("Meilisearch cleanup encountered a credential outside this scenario")
            meilisearch_request(host, key, f"/keys/{credential}", "DELETE")
            credentials.append(str(credential))
        try:
            meilisearch_request(host, key, f"/indexes/{uid}")
        except HTTPError as response:
            if response.code != 404:
                raise
            released[uid] = {"status": "absent", "released_credentials": credentials}
            continue
        task = meilisearch_request(host, key, f"/indexes/{uid}", "DELETE")["taskUid"]
        deadline = time.monotonic() + 15
        while time.monotonic() < deadline:
            observation = meilisearch_request(host, key, f"/tasks/{task}")
            if observation["status"] == "succeeded" and observation["indexUid"] == uid:
                released[uid] = {"status": "deleted", "task": task, "released_credentials": credentials}
                break
            if observation["status"] in {"failed", "canceled"}:
                raise ScenarioError(f"Meilisearch cleanup did not delete {uid}: {task}")
            time.sleep(0.05)
        else:
            raise ScenarioError(f"Meilisearch cleanup did not confirm deletion of {uid}: {task}")
    return released


def run_actions(spec: dict, consumer: Path, fixture: Path, env: dict, evidence: Path, trace: dict) -> dict:
    directory = evidence / "barriers"
    directory.mkdir()
    timeout = spec.get("timeout_seconds", 30)
    deadline = time.monotonic() + timeout
    runtime = {**env, "COREX_SCENARIO_BARRIERS": str(directory)}
    running = {}
    reports = {}
    auxiliary = set(spec.get("auxiliary_processes", []))
    if not auxiliary < set(spec["processes"]):
        raise ScenarioError("auxiliary processes require a declared scenario worker")
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
                overrides = item.get("environment", {})
                if any(not key.startswith("COREX_SCENARIO_") or key in runtime or not isinstance(value, str)
                       for key, value in overrides.items()):
                    raise ScenarioError(f"{name}: invalid process-specific scenario environment")
                process = subprocess.Popen(command, cwd=consumer, env={**runtime, **overrides}, stdout=log, stderr=errors, start_new_session=True)
                running[name] = process
                trace[name] = {"pid": process.pid}
                cleanup.callback(stop, process, trace[name])
            elif action == "await":
                reached = barrier_path(directory, name, "reached")
                owner = item.get("process")
                peers = [running[owner]] if owner is not None else list(running.values())
                while not reached.is_file():
                    if time.monotonic() >= deadline:
                        raise ScenarioError(f"barrier timeout: {name}")
                    if not any(process.poll() is None for process in peers):
                        raise ScenarioError(f"workers exited before barrier: {name}, see {evidence}")
                    time.sleep(0.025)
                owners = [process.pid for process in peers if process.poll() is None]
                if barrier_owner(reached) not in owners:
                    raise ScenarioError(f"barrier was not reached by a live scenario worker: {name}")
            elif action == "release":
                if not barrier_path(directory, name, "reached").is_file():
                    raise ScenarioError(f"barrier was not reached: {name}")
                with barrier_path(directory, name, "release").open("x", encoding="utf-8"):
                    pass
            elif action == "complete":
                if name in reports:
                    raise ScenarioError(f"duplicate process completion: {name}")
                process = running[name]
                code = process.wait(timeout=max(0.01, deadline - time.monotonic()))
                trace[name]["exit_code"] = code
                if code != 0:
                    raise ScenarioError(f"{name} failed with exit status {code}: {evidence / (name + '.stderr')}")
                reports[name] = read_witness(evidence / f"{name}.json", process.pid)
            elif action == "terminate":
                process = running[name]
                reached = barrier_path(directory, item["barrier"], "reached")
                if name in reports or not reached.is_file() or barrier_owner(reached) != process.pid:
                    raise ScenarioError(f"{name} did not reach its termination barrier")
                reports[name] = read_witness(evidence / f"{name}.json", process.pid)
                stop(process, trace[name])
                if trace[name]["exit_code"] != -signal.SIGKILL:
                    raise ScenarioError(f"{name} did not terminate at the barrier")
            else:
                raise ScenarioError(f"unknown scenario action: {action}")
    if not reports or set(reports) != set(spec["processes"]):
        raise ScenarioError("each declared worker must complete with assertions")
    workers = [report for name, report in reports.items() if name not in auxiliary]
    if "COREX_SCENARIO_PG_VERSION" in env:
        databases = set(env["COREX_PG_DATABASES"].split(","))
        for report in workers:
            backend = report["backend"]
            connection = report["connection"]
            if (not isinstance(backend, str) or not backend.startswith("PostgreSQL ")
                    or not isinstance(connection, dict) or connection.get("database") not in databases
                    or type(connection.get("session")) is not int or connection["session"] <= 0):
                raise ScenarioError("worker did not report a provisioned PostgreSQL session")
    if spec.get("distinct_connections") and len({json.dumps(report["connection"], sort_keys=True) for report in workers}) != len(workers):
        raise ScenarioError("workers did not obtain independent database connections")
    return reports


def prove(candidate: Path | None, case: str, evidence: Path, php: str, *, variant: str | None = None, postgres: int | None = None) -> dict:
    phases = load_json(STAGES)
    if case not in phases:
        raise ScenarioError(f"unknown scenario: {case}")
    fixture = FIXTURES / case
    if not (fixture / "case.json").is_file():
        raise ScenarioError(f"{case} is required from stage {phases[case]} and its fixture is missing")
    spec = load_json(fixture / "case.json")
    profiles = profile_module()
    fixtures = [fixture.relative_to(ROOT), STAGES.relative_to(ROOT)]
    if spec.get("host_support") or any(row.get("host_support") for row in spec.get("variants", {}).values()):
        fixtures.append(SUPPORT.relative_to(ROOT))
    for item in [spec, *spec.get("variants", {}).values()]:
        extension = item.get("profile", {}).get("extension_fixture")
        if extension is not None:
            fixtures.append(profiles.extension_source(extension))
    if git_bytes(ROOT, "status", "--porcelain=v1", "--untracked-files=all", "--", *map(str, fixtures)):
        raise ScenarioError(f"scenario source is dirty: {case}")
    if spec["id"] != case:
        raise ScenarioError(f"scenario identity mismatch: {case}")
    variants = spec.get("variants")
    if variant is not None and (variants is None or variant not in variants):
        raise ScenarioError(f"unknown scenario variant: {variant}")
    evidence.mkdir(parents=True, exist_ok=False)
    interpreter = profiles.interpreter_for(php, profiles.interpreters())
    with tempfile.TemporaryDirectory(prefix=f"corex-scenario-{case}-") as temporary:
        root = Path(temporary)
        built = extensions.candidate_directory(candidate) if candidate else extensions.build_candidate(ROOT, root / "candidate")
        binding = extensions.candidate_binding(built)
        if binding["source_commit"] != resolve_commit(ROOT, "HEAD"):
            raise ScenarioError("scenario fixture checkout must match the candidate source commit")
        repository = root / "repository"
        extensions.materialize_repository(built, repository)
        names = set(profiles.archive_composers(built))
        if variants is None:
            return prove_variant(spec, fixture, evidence, root / "app", repository, binding, names, profiles, interpreter, postgres)
        selected = [variant] if variant else list(variants)
        receipt = {**binding, "case": case, "variants": {}, "exit_code": 1}
        try:
            for name in selected:
                directory = evidence / name
                directory.mkdir()
                variant_spec = {**spec, **variants[name], "variant": name}
                with tempfile.TemporaryDirectory(prefix="consumer-", dir=root) as consumer:
                    receipt["variants"][name] = prove_variant(
                        variant_spec, fixture, directory, Path(consumer), repository, binding, names, profiles, interpreter, postgres,
                    )
            receipt.update({"assertions": sum(row["assertions"] for row in receipt["variants"].values()), "exit_code": 0})
        finally:
            write_json(evidence / "receipt.json", receipt)
    return receipt


def prove_variant(spec: dict, fixture: Path, evidence: Path, root: Path, repository: Path, binding: dict, names: set[str], profiles, interpreter: dict, postgres: int | None) -> dict:
    case = spec["id"]
    profile = spec["profile"]
    receipt = {
        **binding, "case": case, "profile": profile["id"], "exit_code": 1,
        "php": ".".join(map(str, interpreter["version"])), "processes": {},
    }
    if "variant" in spec:
        receipt["variant"] = spec["variant"]
    cluster = {}
    try:
        packages = profiles.expand_packages(profile, names)
        cell = {"id": case, "packages": packages, "php": interpreter["minor"], "laravel": spec["laravel"]}
        layers = []
        if spec.get("host_support"):
            layers.append(SUPPORT / "host")
        if (fixture / "host").is_dir():
            layers.append(fixture / "host")
        with profiles.isolated_scenario_databases(profile, postgres) as database:
            if database:
                database.update({
                    "COREX_SCENARIO_CASE": case,
                    "COREX_SCENARIO_VARIANT": spec.get("variant", ""),
                })
            if meili := profile.get("meilisearch"):
                cluster, receipt["meilisearch"] = meilisearch_environment(meili)
                database.update(cluster)
            consumer, runtime, declared = profiles.install_app(
                root, cell, profile, repository, ROOT, interpreter, binding["candidate_version"],
                isolated=database, host_overlays=tuple(layers),
            )
            for arguments in spec.get("artisan", []):
                command = ["php", "artisan", *arguments]
                completed = profiles.run(command, consumer, runtime)
                if completed.returncode:
                    profiles.fail_command(case, completed)
            booted = profiles.boot_app(consumer, runtime, case)
            profiles.assert_providers(consumer, booted, case)
            profiles.assert_profile_clean(profile, booted["packages"], http_routes=booted["http_routes"])
            receipt.update(profiles.assert_fields_bridge(profile, booted))
            receipt["databases"] = booted["database_versions"]
            if database and set(booted["database_versions"]) != set(database["COREX_PG_DATABASES"].split(",")):
                raise ScenarioError("profile did not connect to every isolated PostgreSQL database")
            prerequisite_names = spec["profile"].get("prerequisites", [])
            receipt["prerequisites"] = {
                row["name"]: row["source"]["reference"]
                for row in load_json(consumer / "composer.lock")["packages"] if row["name"] in prerequisite_names
            }
            receipt.update(assert_dist_install(consumer, repository, declared, ROOT))
            if extension := profile.get("extension_fixture"):
                receipt["extension"] = profiles.assert_fixture_extension(consumer, extension)
            if spec.get("retain_install_evidence"):
                for source, name in (("composer.json", "composer.json"), ("composer.lock", "composer.lock"),
                                     ("vendor/composer/installed.json", "composer-installed.json")):
                    (evidence / name).write_bytes((consumer / source).read_bytes())
            witnesses = run_actions(spec, consumer, fixture, runtime, evidence, receipt["processes"])
            receipt.update({
                "assertions": sum(report["assertions"] for report in witnesses.values()),
                "witnesses": witnesses, "exit_code": 0,
            })
    except (ConsumerError, ScenarioError, OSError, ValueError, KeyError, RuntimeError, subprocess.SubprocessError) as error:
        receipt["exit_code"] = 1
        receipt["error"] = str(error)
        raise
    finally:
        try:
            if cluster:
                receipt["meilisearch"]["released"] = release_meilisearch(cluster, evidence / "barriers/meilisearch-targets.ndjson")
        except (OSError, ValueError, KeyError, ScenarioError) as cleanup:
            receipt["meilisearch"]["cleanup_error"] = str(cleanup)
            if receipt["exit_code"] == 0:
                receipt["exit_code"] = 1
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
    parser.add_argument("--variant")
    parser.add_argument("--postgres", type=int, choices=(16, 18))
    args = parser.parse_args()
    evidence = args.evidence_dir
    if evidence is None:
        parent = ROOT / "build/distribution/scenarios"
        parent.mkdir(parents=True, exist_ok=True)
        evidence = Path(tempfile.mkdtemp(prefix="run-", dir=parent)) / "evidence"
    try:
        receipt = prove(args.candidate, args.case, evidence.resolve(), args.php, variant=args.variant, postgres=args.postgres)
    except (CandidateError, OSError, ValueError, KeyError, RuntimeError, subprocess.SubprocessError) as error:
        print(f"scenario denied: {error}", file=sys.stderr)
        return 1
    print(json.dumps({"result": "PASS", "case": args.case, "assertions": receipt["assertions"], "receipt": str(evidence / "receipt.json")}))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
