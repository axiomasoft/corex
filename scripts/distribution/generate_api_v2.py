#!/usr/bin/env python3
"""Regenerate the published v2 contract in the canonical archive consumer."""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import shutil
import tempfile
from pathlib import Path

import prove_scenario as scenarios
import verify_extension_fixtures as archives
from consumer_repository import assert_dist_install, load_json
from release_candidate import git_bytes, resolve_commit

CARRIERS = {"errors.json", "manifest.json", "openapi.json", "operations.json", "webhooks.json"}


def payload(directory: Path) -> dict[str, str]:
    actual = {entry.name for entry in directory.iterdir() if entry.is_file()}
    if actual != CARRIERS or {entry.name for entry in directory.iterdir()} != CARRIERS:
        raise scenarios.ScenarioError("native generator did not write the exact five v2 carriers")
    manifest = load_json(directory / "manifest.json")
    digests = {name: hashlib.sha256((directory / name).read_bytes()).hexdigest() for name in sorted(CARRIERS)}
    if manifest["major"] != "v2" or manifest["files"] != {key: value for key, value in digests.items() if key != "manifest.json"}:
        raise scenarios.ScenarioError("generated v2 manifest does not cover its payloads")
    return digests


def native(consumer: Path, runtime: dict, directory: Path, profile, arguments: list[str], name: str) -> None:
    command = ["php", "artisan", *arguments]
    result = profile.run(command, consumer, runtime)
    (directory / (name + ".stdout")).write_text(result.stdout)
    (directory / (name + ".stderr")).write_text(result.stderr)
    scenarios.write_json(directory / (name + ".json"), {"command": command, "exit": result.returncode})
    if result.returncode:
        profile.fail_command("canonical API " + name, result)


def declarations(directory: Path) -> list[str]:
    operations = load_json(directory / "operations.json")
    ids = {row["id"] for row in operations}
    required = {"notes.create", "fields.describe", "search.describe"}
    if (len(operations) != len(ids) or not required <= ids
            or sum(value.startswith("bulk-operations.") for value in ids) != 5
            or sum(value.startswith("webhook-") for value in ids) != 9
            or any(row["version"] != "v2" for row in operations)):
        raise scenarios.ScenarioError("compiled canonical v2 operations are incomplete")

    openapi = load_json(directory / "openapi.json")
    http_methods = {"get", "post", "put", "patch", "delete"}
    actual = {(path, method, row["operationId"]) for path, item in openapi["paths"].items()
              for method, row in item.items() if method in http_methods}
    expected = {(row["path"], row["method"].lower(), row["id"]) for row in operations}
    if actual != expected or [row["url"] for row in openapi["servers"]] != ["/api/v2"]:
        raise scenarios.ScenarioError("OpenAPI paths or literal v2 server differ from the canonical declarations")

    for row in operations:
        operation = openapi["paths"][row["path"]][row["method"].lower()]
        headers = [parameter for parameter in operation.get("parameters", [])
                   if parameter["in"] == "header" and parameter["name"].lower() == "idempotency-key"]
        mode = row["idempotency"]
        if mode == "forbidden" and not headers:
            continue
        if (mode not in {"optional", "required"} or len(headers) != 1
                or headers[0].get("required") is not (mode == "required")
                or headers[0].get("schema") != {"type": "string", "minLength": 1, "maxLength": 255}):
            raise scenarios.ScenarioError(f"OpenAPI Idempotency-Key differs from the {row['id']} declaration")

    webhooks = load_json(directory / "webhooks.json")
    topics = {(row["major"], row["name"], row["version"]) for row in webhooks["topics"]}
    if topics != {("v2", "fixture.recordchanged", 1)}:
        raise scenarios.ScenarioError("canonical v2 webhook topic is absent or differs")
    return sorted(ids)


def check_runtime(directory: Path, generated: Path) -> int:
    operations = load_json(generated / "operations.json")
    expected = {(row["method"], "api/v2" + row["path"]) for row in operations
                if row["id"].startswith(("bulk-operations.", "webhook-"))}

    def routes(name):
        rows = load_json(directory / (name + ".stdout"))
        return sorted((row["name"], row["uri"], row["method"]) for row in rows
                      if (row["name"] or "").startswith("corex.api."))

    cold = routes("routes-cold")
    if cold != routes("routes-warm"):
        raise scenarios.ScenarioError("config cache changed the registered CoreX HTTP routes")

    actual = [(method, path) for name, path, methods in cold
              if name.startswith(("corex.api.bulk.", "corex.api.webhooks."))
              for method in methods.split("|") if method != "HEAD"]
    if len(actual) != len(expected) or set(actual) != expected:
        raise scenarios.ScenarioError("registered Bulk or Webhook routes differ from their v2 declarations")

    discovery = {(name, path) for name, path, _ in cold if not
                 name.startswith(("corex.api.bulk.", "corex.api.webhooks."))}
    if discovery != {("corex.api.discovery", "api"), ("corex.api.capabilities", "api/v2/capabilities"),
                     ("corex.api.openapi", "api/v2/openapi.json"), ("corex.api.operation", "api/v2/{path?}")}:
        raise scenarios.ScenarioError("installed CoreX discovery or operation routes differ from the v2 contract")

    commands = [row for row in load_json(directory / "commands.stdout")["commands"]
                if row["name"] == "corex:operations:snapshot"]
    if len(commands) != 1:
        raise scenarios.ScenarioError("native snapshot command is missing or duplicated")
    options = commands[0]["definition"]["options"]
    if options.get("major", {}).get("default") != "v2" or "check" not in options or "fixture" in options:
        raise scenarios.ScenarioError("native snapshot CLI differs from the published v2 command")
    return len(cold)


def generate(candidate: Path, out: Path, php: str, postgres: int, verify: Path | None) -> dict:
    root = scenarios.ROOT
    if git_bytes(root, "status", "--porcelain=v1", "--untracked-files=all"):
        raise scenarios.ScenarioError("canonical generation requires a clean exact candidate source checkout")
    built = archives.candidate_directory(candidate)
    binding = archives.candidate_binding(built)
    if binding["source_commit"] != resolve_commit(root, "HEAD"):
        raise scenarios.ScenarioError("canonical transport fixture requires the candidate source commit")
    fixture = scenarios.FIXTURES / "transport"
    case = load_json(fixture / "case.json")
    spec = {**case, **case["variants"]["cloud"], "variant": "cloud"}
    profile = spec["profile"]
    if profile["id"] != "canonical-installed-notes-v2" or not spec["host_support"]:
        raise scenarios.ScenarioError("canonical composing consumer has changed identity")
    components = scenarios.host_components(spec)
    profiles = scenarios.profile_module()
    interpreter = profiles.interpreter_for(php, profiles.interpreters())
    out.mkdir(parents=True, exist_ok=False)
    report = {**binding, "profile": profile["id"], "php": ".".join(map(str, interpreter["version"])), "exit_code": 1}
    try:
        with tempfile.TemporaryDirectory(prefix="corex-canonical-api-v2-") as temp:
            work = Path(temp)
            repository = work / "repository"
            archives.materialize_repository(built, repository)
            packages = profiles.expand_packages(profile, set(profiles.archive_composers(built)))
            cell = {"id": "transport", "packages": packages, "php": interpreter["minor"], "laravel": spec["laravel"]}
            with profiles.isolated_scenario_databases(profile, postgres) as database:
                database.update({"COREX_SCENARIO_CASE": "transport", "COREX_SCENARIO_VARIANT": "cloud"})
                consumer, runtime, declared = profiles.install_app(
                    work / "app", cell, profile, repository, root, interpreter, binding["candidate_version"],
                    isolated=database, host_overlays=(scenarios.SUPPORT / "host", fixture / "host"),
                    host_components=components,
                )
                booted = profiles.boot_app(consumer, runtime, "canonical API")
                profiles.assert_providers(consumer, booted, "canonical API")
                report["databases"] = booted["database_versions"]
                report.update(assert_dist_install(consumer, repository, declared, root))
                report["host_require"] = profiles.assert_host_requirements(consumer, profile.get("host_require", {}))
                for path, name in (("composer.json", "composer.json"), ("composer.lock", "composer.lock"),
                                   ("vendor/composer/installed.json", "composer-installed.json")):
                    shutil.copyfile(consumer / path, out / name)
                native(consumer, runtime, out, profiles, ["modules:compile", "--no-interaction"], "compile")
                native(consumer, runtime, out, profiles, ["route:list", "--json"], "routes-cold")
                native(consumer, runtime, out, profiles, ["list", "--format=json"], "commands")
                native(consumer, runtime, out, profiles, ["config:cache"], "config-cache")
                native(consumer, runtime, out, profiles, ["route:list", "--json"], "routes-warm")
                generated = out / "api-v2"
                command = ["corex:operations:snapshot", str(generated), "--major=v2"]
                native(consumer, runtime, out, profiles, command, "generate")
                digests = payload(generated)
                ids = declarations(generated)
                routes = check_runtime(out, generated)
                native(consumer, runtime, out, profiles, [*command, "--check"], "check")
                native(consumer, runtime, out, profiles, command, "regenerate")
                if payload(generated) != digests:
                    raise scenarios.ScenarioError("regeneration changed canonical v2 bytes")
                if verify is not None:
                    native(consumer, runtime, out, profiles, ["corex:operations:snapshot", str(verify), "--major=v2", "--check"], "check-published")
                    if payload(verify) != digests:
                        raise scenarios.ScenarioError("published v2 bytes differ from canonical installed generation")
                report.update({"carriers": digests, "operations": ids, "registered_corex_routes": routes, "exit_code": 0})
    finally:
        scenarios.write_json(out / "receipt.json", report)
    return report


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", required=True, type=Path)
    parser.add_argument("--evidence-dir", required=True, type=Path)
    parser.add_argument("--php", required=True, choices=("8.4", "8.5"))
    parser.add_argument("--postgres", required=True, choices=(16, 18), type=int)
    parser.add_argument("--check-published", type=Path)
    args = parser.parse_args()
    result = generate(args.candidate.resolve(), args.evidence_dir.resolve(), args.php, args.postgres,
                      args.check_published.resolve() if args.check_published else None)
    print(json.dumps(result, indent=2, sort_keys=True))
    return result["exit_code"]


if __name__ == "__main__":
    raise SystemExit(main())
