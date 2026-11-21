#!/usr/bin/env python3
"""Install five standalone extension fixtures against the frozen dist candidate."""
from __future__ import annotations

import argparse
import base64
import hashlib
import importlib.util
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
from contextlib import contextmanager
from pathlib import Path

# Also support the test harness loading this script by file path.
sys.path.insert(0, str(Path(__file__).resolve().parent))
from ecosystem_repositories import is_corex_package
from consumer_repository import assert_dist_install, repositories

ROOT = Path(__file__).resolve().parents[2]
FIELD_SEARCH_HOST = {
    "axiomasoft/corex-panels": "^2.0",
    "axiomasoft/corex-search-custom-fields": "^2.0",
}
FIXTURES = (
    {
        "id": "custom-search-provider",
        "package": "thirdparty/custom-search-provider",
        "class": r"ThirdParty\Search\Module",
        "forbidden": r"CoreX\Search\Query\Internal\AuthorizedSearch",
        "search_proof": {
            "entity": "csp.document", "record": "eq-1", "backend": "thirdparty-search",
            "adapter": r"ThirdParty\Search\MemoryDriver", "field_id": "01900000-0000-7000-8000-000000000005",
        },
    },
    {
        "id": "custom-attribute-source",
        "package": "thirdparty/custom-attribute-source",
        "class": r"ThirdParty\Attributes\Module",
        "field_proof": True,
        "forbidden": r"CoreX\CustomFields\Values\Internal\JsonbFieldValues",
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


def write_consumer(
    consumer: Path, fixture: Path, repository: Path, *, drop_corex: bool, laravel_host: bool = False,
    host_requirements: dict[str, str] | None = None,
) -> None:
    source = fixture_composer(fixture)
    requirements = dict(source["require"])
    if drop_corex:
        requirements = {name: constraint for name, constraint in requirements.items() if not is_corex_package(name)}
    target = consumer / "fixture"
    shutil.copytree(fixture, target, ignore=shutil.ignore_patterns("vendor", "composer.lock"))
    (target / "composer.json").write_text(json.dumps({**source, "require": requirements}, indent=4) + "\n", encoding="utf-8")
    composer = {
        "name": "corex/extension-fixture-consumer",
        "type": "project",
        "require": {source["name"]: "@dev", **(host_requirements or {})},
        "repositories": [
            {"type": "path", "url": "fixture", "options": {"symlink": False}},
            *repositories(repository, ROOT),
        ],
        "minimum-stability": "dev",
        "prefer-stable": True,
        "config": {"allow-plugins": True, "secure-http": False, "preferred-install": "dist"},
    }
    if laravel_host:
        composer["require"]["laravel/framework"] = "^13.0"
    (consumer / "composer.json").write_text(json.dumps(composer, indent=4) + "\n", encoding="utf-8")


def run(command: list[str], cwd: Path, env: dict[str, str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(command, cwd=cwd, env=env, text=True, capture_output=True, check=False)


def profile_proof_module():
    script = ROOT / "scripts/distribution/profile-proof.py"
    spec = importlib.util.spec_from_file_location("field_fixture_profile_proof", script)
    if spec is None or spec.loader is None:
        raise SystemExit("distribution profile verifier is unavailable")
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    return module


def composer_env(home: Path, php: str | None = None) -> dict[str, str]:
    if php is not None:
        profiles = profile_proof_module()
        interpreter = profiles.interpreter_for(php, profiles.interpreters())
        env = profiles.composer_env(home, home.parent / "composer-cache", Path(interpreter["binary"]))
        env["COREX_FIXTURE_PHP_VERSION"] = ".".join(map(str, interpreter["version"]))
        return env
    env = os.environ.copy()
    env.update({
        "COMPOSER_HOME": str(home),
        "COMPOSER_DISABLE_XDEBUG_WARN": "1",
        "COMPOSER_NO_INTERACTION": "1",
    })
    return env


def install(consumer: Path, env: dict[str, str]) -> subprocess.CompletedProcess[str]:
    if php := env.get("COREX_FIXTURE_PHP_VERSION"):
        manifest = consumer / "composer.json"
        composer = json.loads(manifest.read_text(encoding="utf-8"))
        composer["config"]["platform"] = {"php": php}
        manifest.write_text(json.dumps(composer, indent=4) + "\n", encoding="utf-8")
    command = profile_proof_module().composer_command(
        "update", "--no-dev", "--no-interaction", "--no-progress", "--prefer-dist",
    )
    return run(command, consumer, env)


def validate_field_witness(witness: dict, databases: dict[str, str], fixture: dict) -> None:
    if (witness.get("fact") != "fields.values.changed.v2" or witness.get("sealed") is not True
            or witness.get("denied_filter") is not True or not witness.get("schema_revision")
            or not witness.get("message_id") or not witness.get("policy_witness")
            or witness.get("entity") != fixture["entity"] or witness.get("values") != fixture["values"]
            or witness.get("filter_operand") != fixture["filter_operand"]):
        raise SystemExit(f"{fixture['entity']}: installed field contract proof is incomplete")
    pid = witness.get("pid")
    ids = witness.get("field_ids")
    if (type(pid) is not int or pid < 1 or not isinstance(witness.get("php"), str)
            or not isinstance(ids, list) or not all(isinstance(value, str) for value in ids)
            or len(set(ids)) != len(ids) or len(ids) != len(fixture["values"])):
        raise SystemExit(f"{fixture['entity']}: field proof process or field identities are incomplete")
    validate_isolated_databases(witness, databases, fixture["entity"])


def validate_isolated_databases(witness: dict, databases: dict[str, str], entity: str) -> None:
    owner = witness.get("owner")
    control = witness.get("control")
    if (not isinstance(owner, dict) or not isinstance(control, dict)
            or owner.get("database") != databases["COREX_SCENARIO_DB_A"]
            or control.get("database") != databases["COREX_SCENARIO_DB_CONTROL"]
            or not owner.get("session") or not control.get("session") or owner["session"] == control["session"]
            or not owner.get("oid") or not control.get("oid") or owner["oid"] == control["oid"]
            or not str(owner.get("backend", "")).startswith("PostgreSQL ")
            or not str(control.get("backend", "")).startswith("PostgreSQL ")):
        raise SystemExit(f"{entity}: installed proof did not use both isolated databases")
    if budget_database := databases.get("COREX_SCENARIO_DB_BUDGET"):
        budget = witness.get("budget")
        if (not isinstance(budget, dict) or budget.get("database") != budget_database
                or budget_database in {owner["database"], control["database"]}
                or not budget.get("session") or budget["session"] in {owner["session"], control["session"]}
                or not budget.get("oid") or budget["oid"] in {owner["oid"], control["oid"]}
                or budget.get("backend") != owner["backend"]):
            raise SystemExit(f"{entity}: installed proof did not use an independent budget database")


def probe_phase(consumer: Path, command: list[str], env: dict[str, str], entity: str, phase: str) -> dict:
    completed = run(command, consumer, env)
    if completed.returncode:
        raise SystemExit(completed.stderr or completed.stdout or f"{entity}: {phase} phase failed")
    try:
        witness = json.loads(completed.stdout)
    except json.JSONDecodeError as error:
        raise SystemExit(f"{entity}: invalid {phase} witness: {completed.stdout.strip()}") from error
    if not isinstance(witness, dict) or witness.get("phase") != phase:
        raise SystemExit(f"{entity}: missing {phase} phase witness")
    witness["exit_code"] = completed.returncode
    witness["command"] = command
    witness["stdout_sha256"] = hashlib.sha256(completed.stdout.encode()).hexdigest()
    return witness


@contextmanager
def installed_runtime(consumer: Path, env: dict[str, str], postgres: int | None, *, budget: bool = False):
    profiles = profile_proof_module()
    profile = {"database": "postgres-isolated", "database_roles": ["control", "a"], "default_database": "a"}
    if budget:
        profile["database_roles"].append("budget")
    for path in ("bootstrap/cache", "storage/logs", "storage/framework/views"):
        (consumer / path).mkdir(parents=True, exist_ok=True)
    with profiles.isolated_scenario_databases(profile, postgres) as databases:
        yield databases, {
            **env, **databases, "APP_ENV": "testing",
            "APP_KEY": "base64:" + base64.b64encode(os.urandom(32)).decode("ascii"),
        }


def run_field_proof(
    consumer: Path, probe: Path, fixture: dict, env: dict[str, str], postgres: int | None, php: str | None,
    *, budget: bool = False,
) -> dict:
    with installed_runtime(consumer, env, postgres, budget=budget) as (databases, runtime):
        command = ["php", str(probe), fixture["class"]]
        compiled = probe_phase(consumer, [*command, "compile"], runtime, fixture["entity"], "compile")
        witness = probe_phase(consumer, [*command, "exercise"], runtime, fixture["entity"], "exercise")
        validate_field_witness(witness, databases, fixture)
        compiler_pid = compiled.get("pid")
        if type(compiler_pid) is not int or compiler_pid < 1 or compiler_pid == witness["pid"]:
            raise SystemExit(f"{fixture['entity']}: module compilation requires a separate process")
        if compiled.get("php") != witness.get("php") or (
            php is not None and not str(witness.get("php", "")).startswith(php + ".")
        ):
            raise SystemExit(f"{fixture['entity']}: installed field proof did not use PHP {php}")
        witness["compiler"] = compiled
        return witness


def validate_search_witness(
    witness: dict, databases: dict[str, str], fixture: dict, php: str | None, *, profile: str = "absent",
) -> None:
    if (witness.get("entrypoint") != r"CoreX\Search\Query\Contracts\Search" or witness.get("tagged") is not True
            or witness.get("profile") != profile or witness.get("compiled_fields") != ["title"]
            or not re.fullmatch(r"[a-f0-9]{64}", str(witness.get("mapping", "")))
            or type(witness.get("assertions")) is not int or witness["assertions"] < 1
            or witness.get("candidate_calls") != 1 or witness.get("count") != "unavailable"
            or witness.get("stale_text_rejected") is not True
            or witness.get("projection") != {"title": "pump alpha"}
            or any(witness.get(key) != fixture[key] for key in ("entity", "record", "backend", "adapter"))
            or not re.fullmatch(r"cx_[a-f0-9]{64}", str(witness.get("uid", "")))
            or type(witness.get("generation")) is not int or witness["generation"] < 1
            or type(witness.get("pid")) is not int or witness["pid"] < 1
            or not isinstance(witness.get("php"), str) or (php is not None and not witness["php"].startswith(php + "."))):
        raise SystemExit(f"{fixture['entity']}: public Search proof is incomplete")
    validate_isolated_databases(witness, databases, fixture["entity"])


def run_search_profile(
    consumer: Path, probe: Path, fixture: dict, env: dict[str, str], profile: str, phase: str,
) -> dict:
    command = ["php", str(probe), fixture["class"], profile]
    runtime = {**env, "COREX_SEARCH_CUSTOM_FIELDS_ENABLED": "true" if profile == "enabled" else "false"}
    compiler = probe_phase(consumer, [*command, "compile"], runtime, fixture["entity"], "module-compile")
    witness = probe_phase(consumer, command, runtime, fixture["entity"], phase)
    if (compiler.get("profile") != profile or compiler.get("php") != witness.get("php")
            or type(compiler.get("pid")) is not int or compiler["pid"] < 1 or compiler["pid"] == witness.get("pid")
            or type(compiler.get("assertions")) is not int or compiler["assertions"] < 1
            or not str(compiler.get("compiler_output", "")).strip()):
        raise SystemExit(f"{fixture['entity']}: {profile} module compilation is incomplete")
    return {**witness, "compiler": compiler}


def run_search_proof(
    consumer: Path, probe: Path, fixture: dict, env: dict[str, str], postgres: int | None, php: str | None,
    *, profile: str = "absent",
) -> dict:
    with installed_runtime(consumer, env, postgres, budget=profile == "equipment") as (databases, runtime):
        witness = run_search_profile(consumer, probe, fixture, runtime, profile, "search")
        validate_search_witness(witness, databases, fixture, php, profile=profile)
        return witness


def validate_field_search_compilation(
    witness: dict, databases: dict[str, str], fixture: dict, absent: dict, php: str | None,
) -> None:
    field = fixture["field_id"]
    view = witness.get("view_id", "")
    mapping_pairs = (
        ("mapping", "renamed_mapping"),
        ("schema_revision_before", "schema_revision_after"),
        ("schema_mapping_before", "schema_mapping_after"),
    )
    digests = [key for pair in mapping_pairs for key in pair] + ["view_grammar"]
    if (witness.get("profile") != "enabled" or witness.get("entity") != fixture["entity"]
            or witness.get("driver") != fixture["adapter"]
            or witness.get("field_id") != field or witness.get("compiled_fields") != [field, "title"]
            or witness.get("view_projection") != [field] or witness.get("view_revision") != "1"
            or witness.get("view_filter") != {"field": field, "op": "eq", "value": "PUMP-1"}
            or witness.get("view_sort") != [{"field": field, "direction": "asc"}]
            or not re.fullmatch(r"[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}", str(view))
            or witness.get("dependencies") != [f"saved-view:{view}"]
            or witness.get("old_key") != "asset_code" or witness.get("new_key") != "equipment_code"
            or witness.get("old_label") != "Asset code" or witness.get("new_label") != "Equipment code"
            or witness.get("masked_rejected") is not True or witness.get("masked_error") != "panels.invalid_saved_view"
            or any(not re.fullmatch(r"[a-f0-9]{64}", str(witness.get(key, ""))) for key in digests)
            or any(witness[left] == witness[right] for left, right in mapping_pairs)
            or witness["mapping"] == absent["mapping"]
            or type(witness.get("pid")) is not int or witness["pid"] < 1 or witness["pid"] == absent["pid"]
            or type(witness.get("assertions")) is not int or witness["assertions"] < 1
            or not isinstance(witness.get("php"), str) or (php is not None and not witness["php"].startswith(php + "."))):
        raise SystemExit(f"{fixture['entity']}: enabled field-search compilation proof is incomplete")
    validate_isolated_databases(witness, databases, fixture["entity"])
    if any(witness[key]["database"] in {absent["owner"]["database"], absent["control"]["database"]} for key in ("owner", "control")):
        raise SystemExit(f"{fixture['entity']}: field-search compilation reused a provider-only database")


def run_field_search_compilation(
    consumer: Path, probe: Path, fixture: dict, absent: dict, env: dict[str, str],
    postgres: int | None, php: str | None,
) -> dict:
    with installed_runtime(consumer, env, postgres) as (databases, runtime):
        witness = run_search_profile(consumer, probe, fixture, runtime, "enabled", "field-search-compile")
        validate_field_search_compilation(witness, databases, fixture, absent, php)
        return witness


def prove(consumer: Path, fixture: dict, workspace: Path, env: dict[str, str]) -> dict:
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
    ], consumer, env)
    if completed.returncode != 0:
        raise SystemExit(completed.stderr or completed.stdout or f"{fixture['id']} probe failed")
    return json.loads(completed.stdout)


def missing_dependency_is_red(
    fixture: Path, repository: Path, env: dict[str, str], workspace: Path, spec: dict,
) -> str:
    with tempfile.TemporaryDirectory(prefix="corex-extension-missing-") as temporary:
        consumer = Path(temporary)
        write_consumer(consumer, fixture, repository, drop_corex=True)
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
        ], consumer, env)
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


def verify(
    candidate: Path, workspace: Path = ROOT, only: str | None = None,
    *, php: str | None = None, postgres: int | None = None,
) -> dict:
    candidate = candidate_directory(candidate)
    binding = candidate_binding(candidate)
    rows = []
    with tempfile.TemporaryDirectory(prefix="corex-extension-fixtures-") as temporary:
        root = Path(temporary)
        repository = root / "repository"
        materialize_repository(candidate, repository)
        home = root / "composer-home"
        home.mkdir()
        env = composer_env(home, php)
        for spec in selected_fixtures(only):
            fixture = workspace / "fixtures/extensions" / spec["id"]
            assert_source_autoload(fixture, workspace)
            consumer = root / spec["id"]
            write_consumer(
                consumer, fixture, repository, drop_corex=False,
                laravel_host=bool(spec.get("field_proof") or spec.get("search_proof")),
            )
            installed = install(consumer, env)
            if installed.returncode != 0:
                raise SystemExit(installed.stderr or installed.stdout or f"{spec['id']} install failed")
            proof = prove(consumer, spec, workspace, env)
            proof.update(assert_dist_install(consumer, repository, list(fixture_composer(fixture)["require"]), workspace))
            if search := spec.get("search_proof"):
                search = {"class": spec["class"], **search}
                proof["installed_search"] = run_search_proof(
                    consumer, consumer / "vendor" / spec["package"] / "bin/search-proof.php",
                    search, env, postgres, php,
                )
                enabled = root / f"{spec['id']}-field-search"
                enabled_home = root / "field-search-composer-home"
                enabled_home.mkdir()
                enabled_env = composer_env(enabled_home, php)
                write_consumer(enabled, fixture, repository, drop_corex=False, laravel_host=True, host_requirements=FIELD_SEARCH_HOST)
                installed = install(enabled, enabled_env)
                if installed.returncode != 0:
                    raise SystemExit(installed.stderr or installed.stdout or f"{spec['id']} field-search install failed")
                enabled_lock = assert_dist_install(
                    enabled, repository, [*fixture_composer(fixture)["require"], *FIELD_SEARCH_HOST], workspace,
                )
                proof["installed_field_search_compilation"] = run_field_search_compilation(
                    enabled, enabled / "vendor" / spec["package"] / "bin/search-proof.php",
                    search, proof["installed_search"], enabled_env, postgres, php,
                )
                proof["installed_field_search_compilation"].update(enabled_lock)
            if spec.get("field_proof"):
                proof["installed_fields"] = run_field_proof(
                    consumer, consumer / "vendor" / spec["package"] / "bin/field-proof.php",
                    {**spec, "entity": "cas.equipment", "values": {"serial": "pump-1", "hours": 12, "inspection_code": "A-42"},
                     "filter_operand": "inspection:A-42"},
                    env, postgres, php,
                )
            proof["missing_dependency"] = missing_dependency_is_red(fixture, repository, env, workspace, spec)
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
    parser.add_argument("--php", choices=("8.4", "8.5"))
    parser.add_argument("--postgres", type=int, choices=(16, 18))
    parser.add_argument("--receipt", type=Path)
    args = parser.parse_args()
    if args.candidate:
        receipt = verify(args.candidate, only=args.only, php=args.php, postgres=args.postgres)
    else:
        with tempfile.TemporaryDirectory(prefix="corex-extension-candidate-") as temporary:
            candidate = build_candidate(ROOT, Path(temporary) / "candidate")
            receipt = verify(candidate, only=args.only, php=args.php, postgres=args.postgres)
    if args.receipt:
        args.receipt.parent.mkdir(parents=True, exist_ok=True)
        args.receipt.write_text(json.dumps(receipt, indent=2, sort_keys=True) + "\n")
    print(json.dumps(receipt, indent=2))
    return 0 if receipt["result"] == "PASS" else 1


if __name__ == "__main__":
    raise SystemExit(main())
