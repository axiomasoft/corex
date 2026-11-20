#!/usr/bin/env python3
"""Install and boot the declared profiles from a verified CoreX candidate."""

from __future__ import annotations

import argparse
import hashlib
import importlib.util
import json
import os
import re
import secrets
import shutil
import subprocess
import sys
import tempfile
from contextlib import contextmanager
from pathlib import Path

# Also support the test harness loading this script by file path.
sys.path.insert(0, str(Path(__file__).resolve().parent))
from ecosystem_repositories import is_corex_package, snapshot_repositories
from consumer_repository import assert_dist_install, dist_closure, repositories

ROOT = Path(__file__).resolve().parents[2]
MANIFEST_PATH = ROOT / "scripts/distribution/install-profiles.json"
MATRIX_PATH = ROOT / "build/distribution/profile-matrix.json"
RECEIPT_PATH = ROOT / "build/distribution/profile-receipt.json"
CARET = re.compile(r"^\^(\d+)\.(\d+)(?:\.(\d+))?$")
INTERPRETER_MINORS = ("8.3", "8.4", "8.5")
PROFILE_PHP = "8.4"
REMOVAL_NAMES = {"axiomasoft/corex-search-intl": "Search Intl"}
PROBE = r"""<?php
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

$root = $argv[1];
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$installed = json_decode((string) file_get_contents($root . '/vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);
$packages = [];
foreach ($installed['packages'] ?? [] as $package) {
    if (is_array($package) && isset($package['name'])) {
        $packages[] = $package['name'];
    }
}
$databases = [];
$databaseVersions = [];
$names = getenv('COREX_PG_DATABASES') ?: '';
if ($names !== '') {
    foreach (explode(',', $names) as $name) {
        $pdo = new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', getenv('PGSQL_HOST'), getenv('PGSQL_PORT'), $name),
            (string) getenv('PGSQL_USERNAME'),
            (string) getenv('PGSQL_PASSWORD'),
        );
        $server = $pdo->query('SELECT current_database(), version()')->fetch(PDO::FETCH_NUM);
        if ($server === false || $server[0] !== $name) {
            throw new RuntimeException('PostgreSQL profile connected to the wrong database.');
        }
        $databases[] = $name;
        $databaseVersions[$name] = $server[1];
    }
}
$sqlite = getenv('COREX_SQLITE') ?: '';
if ($sqlite !== '') {
    $pdo = new PDO('sqlite:' . $sqlite);
    $databaseVersions['sqlite'] = $pdo->query('SELECT sqlite_version()')->fetchColumn();
    $databases[] = 'sqlite';
}
echo json_encode([
    'packages' => $packages,
    'providers' => array_keys($app->getLoadedProviders()),
    'databases' => $databases,
    'database_versions' => $databaseVersions,
    'php' => PHP_VERSION,
    'http_routes' => is_file($root . '/routes/web.php'),
], JSON_THROW_ON_ERROR);
"""


class ProfileError(RuntimeError):
    """A profile, edge, or planted negative failed by name."""


def load_json(path: Path) -> dict:
    return json.loads(path.read_text(encoding="utf-8"))


def extension_module():
    script = ROOT / "scripts/distribution/verify_extension_fixtures.py"
    spec = importlib.util.spec_from_file_location("verify_extension_fixtures", script)
    if spec is None or spec.loader is None:
        raise ProfileError("extension verifier is unavailable")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def require_profile(manifest: dict, profile_id: str) -> dict:
    matches = [profile for profile in manifest["profiles"] if profile["id"] == profile_id]
    if not matches:
        raise ProfileError(f"missing profile: {profile_id}")
    return matches[0]


def validate_profiles(manifest: dict) -> None:
    expected = manifest.get("required_profiles")
    actual = [profile["id"] for profile in manifest["profiles"]]
    if not expected or len(set(expected)) != len(expected) or len(set(actual)) != len(actual):
        raise ProfileError("profile IDs must be nonempty and unique")
    if set(actual) != set(expected):
        raise ProfileError(f"profile set mismatch: expected {sorted(expected)}, defined {sorted(actual)}")


def expand_packages(profile: dict, cohort: set[str]) -> list[str]:
    requested = profile["packages"]
    if requested == ["*"]:
        return sorted(cohort)
    missing = [name for name in requested if name not in cohort]
    if missing:
        raise ProfileError(f"missing package: {missing[0]}")
    return list(requested)


def allow_one(constraint: str, version: tuple[int, int, int]) -> bool:
    match = CARET.fullmatch(constraint.strip())
    if match is None:
        raise ProfileError(f"unsupported constraint: {constraint}")
    lower = (int(match.group(1)), int(match.group(2)), int(match.group(3) or 0))
    upper = (lower[0] + 1, 0, 0)
    return lower <= version < upper


def allows(constraint: str, version: tuple[int, int, int]) -> bool:
    parts = [part.strip() for part in constraint.split("|") if part.strip()]
    if not parts:
        raise ProfileError(f"unsupported constraint: {constraint}")
    return any(allow_one(part, version) for part in parts)


def requirement(composer: dict, name: str) -> str | None:
    value = composer.get("require", {}).get(name)
    return value if isinstance(value, str) else None


def illuminate_constraints(composer: dict) -> dict[str, str]:
    return {
        name: constraint
        for name, constraint in composer.get("require", {}).items()
        if name.startswith("illuminate/") and isinstance(constraint, str)
    }


def declared_laravel_majors(composer: dict) -> tuple[int, ...]:
    constraints = illuminate_constraints(composer)
    if not constraints:
        return ()
    majors: set[int] = set()
    for constraint in constraints.values():
        for part in constraint.split("|"):
            match = CARET.fullmatch(part.strip())
            if match is None:
                raise ProfileError(f"unsupported constraint: {constraint}")
            majors.add(int(match.group(1)))
    allowed = []
    for major in sorted(majors):
        version = (major, 0, 0)
        if all(allows(constraint, version) for constraint in constraints.values()):
            allowed.append(major)
    return tuple(allowed)


def php_constraint(composer: dict) -> str:
    constraint = requirement(composer, "php")
    if constraint is None:
        raise ProfileError(f"missing package constraint: {composer.get('name', '?')} php")
    return constraint


def signature(composer: dict) -> tuple[str, tuple[int, ...]]:
    return (php_constraint(composer), declared_laravel_majors(composer))


def cohort_laravel_majors(composers: dict[str, dict]) -> tuple[int, ...]:
    majors: set[int] = set()
    for composer in composers.values():
        majors.update(declared_laravel_majors(composer))
    return tuple(sorted(majors))


def exclusion_reason(packages: list[str], composers: dict[str, dict], laravel: int | None, php: tuple[int, int, int]) -> str | None:
    for name in packages:
        composer = composers[name]
        if not allows(php_constraint(composer), php):
            return f"{name} php constraint {php_constraint(composer)}"
        majors = declared_laravel_majors(composer)
        if laravel is not None and majors and laravel not in majors:
            sample = next(iter(illuminate_constraints(composer).values()))
            return f"{name} constraint {sample}"
    return None


def assert_profile_clean(profile: dict, installed: list[str], *, http_routes: bool | None = None) -> None:
    present = set(installed)
    for rule in profile.get("forbid", []):
        if rule["package"] in present:
            raise ProfileError(f"{profile['id']}: {rule['name']}")
    if http_routes and not profile.get("http", True):
        raise ProfileError(f"{profile['id']}: HTTP")
    for package in profile.get("remove_observed", []):
        if package in present:
            raise ProfileError(f"{profile['id']}: {REMOVAL_NAMES.get(package, package)}")


def assert_no_workspace(payload: str, workspace: Path) -> None:
    if str(workspace.resolve()) in payload:
        raise ProfileError(f"workspace path: {workspace}")


def assert_cell_closed(cell: dict) -> None:
    status = cell.get("status")
    if status == "excluded":
        if not cell.get("reason"):
            raise ProfileError(f"undeclared skip: {cell['id']}")
        return
    if status != "passed":
        raise ProfileError(f"undeclared skip: {cell['id']}")


def group_packages(composers: dict[str, dict]) -> list[dict]:
    groups: dict[tuple, list[str]] = {}
    for name in sorted(composers):
        groups.setdefault(signature(composers[name]), []).append(name)
    rows = []
    for (php, majors), names in groups.items():
        rows.append({"php": php, "laravel": list(majors), "packages": names})
    return rows


def interpreters() -> list[dict]:
    found = []
    for minor in INTERPRETER_MINORS:
        binary = shutil.which(f"php{minor}") or shutil.which("php")
        if binary is None:
            continue
        completed = subprocess.run((binary, "-r", "echo PHP_VERSION;"), text=True, capture_output=True, check=False)
        if completed.returncode != 0 or not completed.stdout.startswith(f"{minor}."):
            continue
        version = re.match(r'^\d+\.\d+\.\d+', completed.stdout)
        if version is not None:
            found.append({"minor": minor, "binary": str(Path(binary).resolve()), "version": tuple(map(int, version[0].split(".")))})
    if not found:
        raise ProfileError("missing PHP interpreter")
    return found


def interpreter_for(minor: str, available: list[dict]) -> dict:
    matches = [item for item in available if item["minor"] == minor]
    if not matches:
        raise ProfileError(f"missing interpreter: {minor}")
    return matches[0]


def profile_php(packages: list[str], composers: dict[str, dict], available: list[dict]) -> dict:
    allowed = []
    for item in available:
        if exclusion_reason(packages, composers, None, item["version"]) is None:
            allowed.append(item)
    preferred = [item for item in allowed if item["minor"] == PROFILE_PHP]
    if preferred:
        return preferred[0]
    if not allowed:
        raise ProfileError(f"missing interpreter: {packages[0]}")
    return allowed[0]


def plan_profiles(manifest: dict, composers: dict[str, dict], available: list[dict]) -> list[dict]:
    cohort = set(composers)
    majors = cohort_laravel_majors(composers)
    cells = []
    for profile in manifest["profiles"]:
        packages = expand_packages(profile, cohort)
        chosen = profile_php(packages, composers, available)
        for laravel in majors:
            reason = exclusion_reason(packages, composers, laravel, chosen["version"])
            cell = {
                "id": f"{profile['id']}-php{chosen['minor']}-l{laravel}",
                "kind": "profile",
                "profile": profile["id"],
                "packages": packages,
                "php": chosen["minor"],
                "laravel": laravel,
                "prefer_lowest": False,
                "status": "excluded" if reason else "included",
            }
            if reason:
                cell["reason"] = reason
            cells.append(cell)
    return cells


def plan_edges(composers: dict[str, dict], available: list[dict]) -> list[dict]:
    cells = []
    majors = cohort_laravel_majors(composers)
    for name, composer in sorted(composers.items()):
        packages = [name]
        matching = [item for item in available if exclusion_reason(packages, composers, None, item["version"]) is None]
        if not matching:
            raise ProfileError(f"missing interpreter: {name}")
        lowest, highest = matching[0], matching[-1]
        owner_majors = declared_laravel_majors(composer)
        laravel_edges = owner_majors or [None]
        pairs = [(lowest, laravel_edges[0], "min"), (highest, laravel_edges[-1], "max")]
        if pairs[0][:2] == pairs[1][:2]:
            pairs = [pairs[0]]
        pairs.append((lowest, laravel_edges[0], "prefer-lowest"))
        for item, laravel, kind in pairs:
            cells.append({
                "id": f"edge-{kind}-php{item['minor']}-l{laravel or 'none'}-{name}",
                "kind": "edge",
                "edge": kind,
                "packages": packages,
                "php": item["minor"],
                "laravel": laravel,
                "prefer_lowest": kind == "prefer-lowest",
                "status": "included",
            })
        for laravel in majors:
            if owner_majors and laravel not in owner_majors:
                constraint = next(iter(illuminate_constraints(composer).values()))
                cells.append({
                    "id": f"edge-excluded-l{laravel}-{name}",
                    "kind": "edge",
                    "packages": packages,
                    "php": lowest["minor"],
                    "laravel": laravel,
                    "status": "excluded",
                    "reason": f"{name} constraint {constraint}",
                })
        for item in available:
            reason = exclusion_reason(packages, composers, None, item["version"])
            if reason is None:
                continue
            cells.append({
                "id": f"edge-excluded-php{item['minor']}-{name}",
                "kind": "edge",
                "packages": packages,
                "php": item["minor"],
                "laravel": owner_majors[0] if owner_majors else None,
                "status": "excluded",
                "reason": reason,
            })
    return cells


def coverage_gaps(composers: dict[str, dict], cells: list[dict], available: list[dict]) -> list[str]:
    gaps = []
    installed_by_cell = []
    for cell in cells:
        if cell.get("status") != "passed" or cell.get("kind") != "edge":
            continue
        installed_by_cell.append(cell)
    for name, composer in sorted(composers.items()):
        matching = [item for item in available if allows(php_constraint(composer), item["version"])]
        if not matching:
            gaps.append(f"missing interpreter: {name}")
            continue
        majors = declared_laravel_majors(composer)
        required = [(matching[0]["minor"], majors[0] if majors else None, False), (matching[-1]["minor"], majors[-1] if majors else None, False)]
        if required[0][:2] == required[1][:2]:
            required = [required[0]]
        required.append((matching[0]["minor"], majors[0] if majors else None, True))
        for php, laravel, prefer in required:
            found = False
            for cell in installed_by_cell:
                if cell["packages"] != [name] or name not in cell.get("installed", []):
                    continue
                if cell.get("php") != php or bool(cell.get("prefer_lowest")) != prefer:
                    continue
                if majors and cell.get("laravel") != laravel:
                    continue
                found = True
                break
            if not found:
                label = "prefer-lowest" if prefer else f"php{php}/l{laravel}"
                gaps.append(f"missing edge: {name} {label}")
    return gaps


def run(command: list[str], cwd: Path, env: dict[str, str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(command, cwd=cwd, env=env, text=True, capture_output=True, check=False)


def composer_command(*args: str) -> list[str]:
    """Run Composer on PHP 8.4. PHP 8.5 rejects this Composer phar; the app still boots on the cell interpreter."""
    composer = shutil.which("composer")
    if composer is None:
        raise ProfileError("missing package: composer")
    php = shutil.which("php8.4")
    return [php, composer, *args] if php else [composer, *args]


def assert_cohort_platform(consumer: Path, version: tuple[int, int, int], cell_id: str) -> None:
    installed = load_json(consumer / "vendor/composer/installed.json")
    for row in installed.get("packages", []):
        name = row.get("name", "")
        php = row.get("require", {}).get("php") if isinstance(row.get("require"), dict) else None
        if is_corex_package(name) and isinstance(php, str) and not allows(php, version):
            raise ProfileError(f"{cell_id}: {name} php constraint {php}")


def composer_env(home: Path, cache: Path, binary: Path) -> dict[str, str]:
    bindir = home / "bin"
    bindir.mkdir(parents=True, exist_ok=True)
    link = bindir / "php"
    if link.exists() or link.is_symlink():
        link.unlink()
    link.symlink_to(binary)
    env = os.environ.copy()
    env.update({
        "PATH": str(bindir) + os.pathsep + env.get("PATH", ""),
        "COMPOSER_HOME": str(home),
        "COMPOSER_CACHE_DIR": os.environ.get("COMPOSER_CACHE_DIR", str(cache)),
        "COMPOSER_NO_INTERACTION": "1",
        "COMPOSER_DISABLE_XDEBUG_WARN": "1",
        "COMPOSER_MEMORY_LIMIT": "-1",
    })
    return env


def fail_command(cell_id: str, completed: subprocess.CompletedProcess[str]) -> None:
    detail = (completed.stderr or "") + "\n" + (completed.stdout or "")
    lines = [line.strip() for line in detail.splitlines() if line.strip()]
    raise ProfileError(f"{cell_id}: {' | '.join(lines[-8:])}")


def write_consumer(consumer: Path, packages: list[str], laravel: int, repository: Path, php_version: str, candidate_version: str) -> None:
    path = consumer / "composer.json"
    composer = load_json(path)
    require = dict(composer.get("require", {}))
    require["laravel/framework"] = f"^{laravel}.0"
    for name in packages:
        require[name] = candidate_version
    composer["require"] = dict(sorted(require.items()))
    composer["repositories"] = repositories(repository, ROOT)
    composer["minimum-stability"] = "dev"
    composer["prefer-stable"] = True
    config = dict(composer.get("config", {}))
    config.update({
        "allow-plugins": True,
        "secure-http": False,
        "preferred-install": "dist",
        "sort-packages": True,
        "audit": {"block-insecure": False},
        "platform": {"php": php_version},
    })
    composer["config"] = config
    path.write_text(json.dumps(composer, indent=4) + "\n", encoding="utf-8")


def disable_http(consumer: Path, cell_id: str) -> None:
    path = consumer / "bootstrap/app.php"
    text = path.read_text(encoding="utf-8")
    updated = re.sub(r"\n\s*web:\s*__DIR__\.'\/\.\.\/routes\/web\.php',?", "", text)
    updated = re.sub(r"\n\s*health:\s*'\/up',?", "", updated)
    if "routes/web.php" in updated:
        raise ProfileError(f"{cell_id}: HTTP")
    path.write_text(updated, encoding="utf-8")
    web = consumer / "routes/web.php"
    if web.exists():
        web.unlink()


def postgres_settings() -> dict[str, str]:
    return {
        "host": os.environ.get("PGSQL_HOST", "127.0.0.1"),
        "port": os.environ.get("PGSQL_PORT", "5434"),
        "user": os.environ.get("PGSQL_USERNAME", "corex"),
        "password": os.environ.get("PGSQL_PASSWORD", "corex"),
    }


def psql(settings: dict[str, str], database: str, statement: str) -> str:
    completed = subprocess.run(
        ["psql", "-X", "-h", settings["host"], "-p", settings["port"], "-U", settings["user"],
         "-d", database, "-At", "-v", "ON_ERROR_STOP=1", "-c", statement],
        text=True, capture_output=True, check=False,
        env={**os.environ, "PGPASSWORD": settings["password"], "PGCONNECT_TIMEOUT": "5"},
    )
    if completed.returncode:
        raise ProfileError(f"PostgreSQL {database}: {completed.stderr.strip()}")
    return completed.stdout.strip()


@contextmanager
def isolated_scenario_databases(profile: dict, expected_major: int | None):
    if profile["database"] != "postgres-isolated":
        if expected_major is not None:
            raise ProfileError("this scenario does not declare an isolated PostgreSQL profile")
        yield {}
        return
    roles = profile["database_roles"]
    if (not roles or len(set(roles)) != len(roles)
            or not all(re.fullmatch(r"[a-z][a-z0-9_]{0,19}", role) for role in roles)
            or profile["default_database"] not in roles):
        raise ProfileError("invalid scenario database roles")
    settings = postgres_settings()
    version = int(psql(settings, "postgres", "SHOW server_version_num"))
    major = version // 10000
    if major not in (16, 18) or (expected_major is not None and major != expected_major):
        raise ProfileError(f"expected PostgreSQL {expected_major or '16 or 18'}, observed {version}")
    prefix = f"corex_scenario_{secrets.token_hex(8)}"
    names = {role: f"{prefix}_{role}_test" for role in roles}
    created = []
    try:
        for name in names.values():
            psql(settings, "postgres", f'CREATE DATABASE "{name}" TEMPLATE template0')
            created.append(name)
        yield {
            "COREX_PG_DATABASES": ",".join(names.values()),
            "COREX_SCENARIO_DB_DEFAULT": names[profile["default_database"]],
            "COREX_SCENARIO_PG_VERSION": str(version),
            **{f"COREX_SCENARIO_DB_{role.upper()}": name for role, name in names.items()},
            "PGSQL_HOST": settings["host"], "PGSQL_PORT": settings["port"],
            "PGSQL_USERNAME": settings["user"], "PGSQL_PASSWORD": settings["password"],
        }
    finally:
        failures = []
        for name in reversed(created):
            try:
                psql(settings, "postgres", f'DROP DATABASE IF EXISTS "{name}" WITH (FORCE)')
            except ProfileError as error:
                failures.append(str(error))
        if failures:
            raise ProfileError("isolated PostgreSQL cleanup failed: " + " | ".join(failures))


def ensure_databases(names: tuple[str, ...]) -> None:
    settings = postgres_settings()
    ready = subprocess.run(
        ("pg_isready", "-h", settings["host"], "-p", settings["port"], "-U", settings["user"]),
        text=True, capture_output=True, check=False,
    )
    if ready.returncode != 0:
        raise ProfileError("modular-multi: postgres is not ready")
    env = os.environ.copy()
    env["PGPASSWORD"] = settings["password"]
    for name in names:
        if not re.fullmatch(r"[a-z0-9_]+_test", name):
            raise ProfileError(f"modular-multi: {name}")
        exists = subprocess.run(
            ("psql", "-h", settings["host"], "-p", settings["port"], "-U", settings["user"], "-d", "postgres",
             "-Atqc", f"SELECT 1 FROM pg_database WHERE datname='{name}'"),
            text=True, capture_output=True, check=False, env=env,
        )
        if exists.stdout.strip() == "1":
            continue
        created = subprocess.run(
            ("psql", "-h", settings["host"], "-p", settings["port"], "-U", settings["user"], "-d", "postgres",
             "-c", f'CREATE DATABASE "{name}" OWNER "{settings["user"]}"'),
            text=True, capture_output=True, check=False, env=env,
        )
        if created.returncode != 0:
            raise ProfileError(f"modular-multi: {created.stderr.strip() or name}")


def set_env(text: str, key: str, value: str) -> str:
    pattern = re.compile(rf"^{re.escape(key)}=.*$", re.M)
    if pattern.search(text):
        return pattern.sub(f"{key}={value}", text, count=1)
    return text + f"\n{key}={value}\n"


def configure_database(consumer: Path, profile: dict, cell_id: str, isolated: dict[str, str]) -> dict[str, str]:
    env_path = consumer / ".env"
    text = env_path.read_text(encoding="utf-8") if env_path.exists() else ""
    extra: dict[str, str] = {}
    if profile.get("database") in {"postgres-two", "postgres-isolated"}:
        if profile["database"] == "postgres-two":
            names = ("corex_p176_tenant_a_test", "corex_p176_tenant_b_test")
            ensure_databases(names)
            settings = postgres_settings()
            extra = {
                "COREX_PG_DATABASES": ",".join(names),
                "PGSQL_HOST": settings["host"], "PGSQL_PORT": settings["port"],
                "PGSQL_USERNAME": settings["user"], "PGSQL_PASSWORD": settings["password"],
                "COREX_SCENARIO_DB_DEFAULT": names[0],
            }
        elif not isolated:
            raise ProfileError(f"{cell_id}: isolated PostgreSQL databases were not provisioned")
        else:
            extra = isolated
        for key, value in (
            ("DB_CONNECTION", "pgsql"),
            ("DB_HOST", extra["PGSQL_HOST"]),
            ("DB_PORT", extra["PGSQL_PORT"]),
            ("DB_DATABASE", extra["COREX_SCENARIO_DB_DEFAULT"]),
            ("DB_USERNAME", extra["PGSQL_USERNAME"]),
            ("DB_PASSWORD", extra["PGSQL_PASSWORD"]),
        ):
            text = set_env(text, key, value)
    elif profile.get("database") == "sqlite-single":
        database = consumer / "profile.sqlite"
        database.touch()
        text = set_env(text, "DB_CONNECTION", "sqlite")
        text = set_env(text, "DB_DATABASE", str(database))
        extra["COREX_SQLITE"] = str(database)
    else:
        raise ProfileError(f"{cell_id}: unknown database profile {profile.get('database')}")
    env_path.write_text(text, encoding="utf-8")
    if not profile.get("http", True):
        disable_http(consumer, cell_id)
    return extra


def locked_summary(payload: dict) -> list[str]:
    rows = payload.get("locked")
    if not isinstance(rows, list):
        raise ProfileError("Composer show --locked did not return the locked package list")
    names = []
    for row in rows:
        if not isinstance(row, dict) or not isinstance(row.get("name"), str) or not isinstance(row.get("version"), str):
            raise ProfileError("Composer show --locked returned invalid package metadata")
        name = row["name"]
        if is_corex_package(name) or name in {"flex/crm", "laravel/framework", "illuminate/support"}:
            names.append(f"{name}:{row['version']}")
    return sorted(names)


def install_app(
    root: Path,
    cell: dict,
    profile: dict,
    repository: Path,
    workspace: Path,
    binary: dict,
    candidate_version: str,
    *,
    isolated: dict[str, str] | None = None,
    host_overlays: tuple[Path, ...] = (),
) -> tuple[Path, dict[str, str], list[str]]:
    laravel = cell["laravel"] or 13
    home = root / "home"
    cache = repository.parent / "composer-cache"
    home.mkdir(parents=True)
    cache.mkdir(exist_ok=True)
    env = composer_env(home, cache, Path(binary["binary"]))
    created = run(
        composer_command("create-project", f"laravel/laravel:^{laravel}.0", "consumer", "--no-install", "--no-scripts", "--no-interaction", "--no-progress"),
        root, env,
    )
    if created.returncode != 0:
        fail_command(cell["id"], created)
    consumer = root / "consumer"
    packages = list(cell["packages"])
    write_consumer(consumer, packages, laravel, repository, ".".join(map(str, binary["version"])), candidate_version)
    prerequisites = reviewed_prerequisites(consumer, profile.get("prerequisites", []), workspace)
    if profile.get("product") == "flex/crm":
        shutil.copytree(workspace / "products/flex-crm", consumer / "flex-crm", ignore=shutil.ignore_patterns("vendor", "composer.lock"))
        manifest = load_json(consumer / "composer.json")
        manifest["require"]["flex/crm"] = "^1.0@dev"
        manifest["repositories"].append({
            "type": "path",
            "url": "flex-crm",
            "options": {"symlink": False, "versions": {"flex/crm": "1.0.x-dev"}},
        })
        (consumer / "composer.json").write_text(json.dumps(manifest, indent=4) + "\n", encoding="utf-8")
        packages.extend(load_json(consumer / "flex-crm/composer.json")["require"])
    update = composer_command("update", "--no-dev", "--no-interaction", "--no-progress", "--prefer-dist", "--no-scripts")
    if cell.get("prefer_lowest"):
        update.extend(["--prefer-lowest", "--prefer-stable"])
    installed = run(update, consumer, env)
    if installed.returncode != 0:
        fail_command(cell["id"], installed)
    assert_cohort_platform(consumer, binary["version"], cell["id"])
    validated = run(composer_command("validate", "--no-check-publish", "--no-interaction"), consumer, env)
    if validated.returncode:
        fail_command(cell["id"], validated)
    assert_dist_install(consumer, repository, packages, workspace)
    assert_prerequisites(consumer, prerequisites)
    example = consumer / ".env.example"
    env_file = consumer / ".env"
    if not env_file.exists() and example.exists():
        env_file.write_text(example.read_text(encoding="utf-8"), encoding="utf-8")
    runtime = {**env, **configure_database(consumer, profile, cell["id"], isolated or {})}
    for overlay in host_overlays:
        shutil.copytree(overlay, consumer, dirs_exist_ok=True)
    closure = dist_closure(packages, load_json(repository / "packages.json")["packages"])
    if "axiomasoft/corex-operations" in closure:
        providers = consumer / "bootstrap/providers.php"
        contents = providers.read_text(encoding="utf-8")
        providers.write_text(contents.replace("return [", "return [\n    \\CoreX\\Operations\\OperationsServiceProvider::class,", 1), encoding="utf-8")
    generated = run(["php", "artisan", "key:generate", "--ansi", "--force"], consumer, runtime)
    if generated.returncode != 0:
        fail_command(cell["id"], generated)
    return consumer, runtime, packages


def reviewed_prerequisites(consumer: Path, names: list[str], workspace: Path) -> dict:
    if not names:
        return {}
    snapshots = {item["package"]["name"]: item["package"] for item in snapshot_repositories(workspace)}
    unknown = set(names) - set(snapshots)
    if unknown:
        raise ProfileError(f"unreviewed prerequisites: {sorted(unknown)}")
    manifest_path = consumer / "composer.json"
    manifest = load_json(manifest_path)
    requirements = {name: snapshots[name] for name in names}
    manifest["require"].update({name: row["version"] for name, row in requirements.items()})
    manifest_path.write_text(json.dumps(manifest, indent=4) + "\n", encoding="utf-8")
    return requirements


def assert_prerequisites(consumer: Path, prerequisites: dict) -> None:
    if not prerequisites:
        return
    locked = {row["name"]: row for row in load_json(consumer / "composer.lock")["packages"]}
    installed = {row["name"]: row for row in load_json(consumer / "vendor/composer/installed.json")["packages"]}
    for name, expected in prerequisites.items():
        for actual in (locked.get(name, {}), installed.get(name, {})):
            if any(actual.get(key) != expected[key] for key in ("version", "source")):
                raise ProfileError(f"{name}: installed prerequisite differs from reviewed Composer source")


def boot_app(consumer: Path, runtime: dict, cell_id: str) -> dict:
    dumped = run(composer_command("dump-autoload", "-o", "--strict-psr", "--no-dev", "--no-interaction", "--no-scripts"), consumer, runtime)
    if dumped.returncode != 0:
        fail_command(cell_id, dumped)
    for cache_name in ("config.php", "packages.php", "services.php"):
        (consumer / "bootstrap/cache" / cache_name).unlink(missing_ok=True)
    for command in ("package:discover", "config:cache"):
        completed = run(["php", "artisan", command, "--no-interaction"], consumer, runtime)
        if completed.returncode != 0:
            fail_command(cell_id, completed)
    probe = consumer / "profile-probe.php"
    probe.write_text(PROBE, encoding="utf-8")
    completed = run(["php", str(probe), str(consumer)], consumer, runtime)
    if completed.returncode != 0:
        fail_command(cell_id, completed)
    return json.loads(completed.stdout)


def assert_providers(consumer: Path, report: dict, cell_id: str) -> None:
    packages = load_json(consumer / "vendor/composer/installed.json")["packages"]
    providers = set(report["providers"])
    for package in packages:
        name = package["name"]
        if not is_corex_package(name) and name != "flex/crm":
            continue
        expected = package.get("extra", {}).get("laravel", {}).get("providers", [])
        if name == "axiomasoft/corex-operations":
            expected = [r"CoreX\Operations\OperationsServiceProvider"]
        for provider in expected:
            if provider not in providers:
                raise ProfileError(f"{cell_id}: {name} provider {provider} was not loaded")


def execute_cell(cell: dict, profile: dict | None, repository: Path, workspace: Path, available: list[dict], candidate_version: str) -> dict:
    binary = interpreter_for(cell["php"], available)
    profile = profile or {"id": cell["id"], "database": "sqlite-single", "http": True}
    prefix = "corex-profile-" + cell["id"].replace("/", "-") + "-"
    with tempfile.TemporaryDirectory(prefix=prefix) as temporary:
        consumer, runtime, packages = install_app(Path(temporary), cell, profile, repository, workspace, binary, candidate_version)
        report = boot_app(consumer, runtime, cell["id"])
        assert_providers(consumer, report, cell["id"])
        initial_dist = assert_dist_install(consumer, repository, packages, workspace)
        assert_profile_clean(profile, report["packages"], http_routes=report["http_routes"])
        expected_databases = ["sqlite"] if profile["database"] == "sqlite-single" else runtime["COREX_PG_DATABASES"].split(",")
        if report["databases"] != expected_databases or report["http_routes"] != profile.get("http", True):
            raise ProfileError(f"{profile['id']}: database or HTTP profile mismatch")
        removed = profile.get("remove", [])
        if removed:
            removal = run(composer_command("remove", *removed, "--no-interaction", "--no-progress", "--no-update"), consumer, runtime)
            if removal.returncode != 0:
                fail_command(cell["id"], removal)
            refreshed = run(composer_command("update", "--no-dev", "--no-interaction", "--no-progress", "--prefer-dist", "--no-scripts"), consumer, runtime)
            if refreshed.returncode != 0:
                fail_command(cell["id"], refreshed)
            report = boot_app(consumer, runtime, cell["id"])
            assert_providers(consumer, report, cell["id"])
            assert_profile_clean({**profile, "remove_observed": removed}, report["packages"], http_routes=report["http_routes"])
        dist = assert_dist_install(consumer, repository, [name for name in packages if name not in removed], workspace)
        show = run(composer_command("show", "--locked", "--format=json"), consumer, runtime)
        if show.returncode != 0:
            fail_command(cell["id"], show)
        shown = locked_summary(json.loads(show.stdout))
        if [entry for entry in shown if is_corex_package(entry.split(":", 1)[0])] != dist["installed"]:
            raise ProfileError(f"{cell['id']}: Composer show --locked differs from the installed CoreX closure")
        framework = f"laravel/framework:v{cell['laravel'] or 13}."
        if not any(entry.startswith(framework) for entry in shown):
            raise ProfileError(f"{cell['id']}: Composer show --locked is missing the expected Laravel framework")
        if profile.get("product") == "flex/crm" and "flex/crm:1.0.x-dev" not in shown:
            raise ProfileError(f"{cell['id']}: independent FlexCRM version line changed")
        cohort_installed = sorted(name for name in report["packages"] if is_corex_package(name))
        return {
            "installed": cohort_installed,
            "locked": shown,
            "lock_sha256": dist["lock_sha256"],
            "initial_lock_sha256": initial_dist["lock_sha256"],
            "providers": sorted(name for name in report["providers"] if name.startswith(("CoreX\\", "Flex\\"))),
            "databases": report["databases"],
            "database_versions": report["database_versions"],
            "php_version": report["php"],
            "config_cache": True,
            "removed": removed,
        }


def archive_composers(candidate: Path) -> dict[str, dict]:
    import zipfile
    composers = {}
    artifacts = candidate / "artifacts"
    for archive in sorted(artifacts.glob("*.zip")):
        with zipfile.ZipFile(archive) as bundle:
            composer = json.loads(bundle.read("composer.json"))
        composers[composer["name"]] = composer
    if not composers:
        raise ProfileError("missing package: candidate")
    return composers


def build_candidate(destination: Path, extensions) -> Path:
    return extensions.build_candidate(ROOT, destination)


def select_cells(cells: list[dict], mode: str, only: str | None, php: str | None) -> list[dict]:
    selected = []
    for cell in cells:
        if only is not None and cell.get("profile") != only and cell["kind"] == "profile":
            continue
        if only is not None and cell["kind"] != "profile":
            continue
        if mode == "profiles" and cell["kind"] != "profile":
            continue
        if mode == "edges" and cell["kind"] != "edge":
            continue
        if php is not None and cell.get("php") != php and cell["status"] == "included":
            continue
        selected.append(cell)
    return selected


def prove(candidate: Path | None, *, mode: str, only: str | None, php: str | None) -> dict:
    extensions = extension_module()
    available = interpreters()
    if php is not None:
        interpreter_for(php, available)
    elif mode == "all" and only is None:
        for minor in ("8.4", "8.5"):
            interpreter_for(minor, available)
    manifest = load_json(MANIFEST_PATH)
    validate_profiles(manifest)
    with tempfile.TemporaryDirectory(prefix="corex-profile-candidate-") as temporary:
        root = Path(temporary)
        built = extensions.candidate_directory(candidate) if candidate else build_candidate(root / "candidate", extensions)
        binding = extensions.candidate_binding(built)
        repository = root / "repository"
        extensions.materialize_repository(built, repository)
        composers = archive_composers(built)
        profiles = {profile["id"]: profile for profile in manifest["profiles"]}
        if only is not None:
            require_profile(manifest, only)
        profile_interpreters = [item for item in available if php is None or item["minor"] == php]
        planned = plan_profiles(manifest, composers, profile_interpreters) + plan_edges(composers, available)
        selected = select_cells(planned, mode, only, php)
        included = {cell["id"] for cell in selected if cell["status"] == "included"}
        expected_profiles = {only} if only else set(manifest["required_profiles"])
        if not included:
            raise ProfileError("no executable matrix cells selected")
        if mode != "edges" and {cell["profile"] for cell in selected if cell["kind"] == "profile" and cell["id"] in included} != expected_profiles:
            raise ProfileError(f"missing executable profile: {sorted(expected_profiles)}")
        results = []
        for cell in selected:
            if cell["status"] == "excluded":
                assert_cell_closed(cell)
                results.append(cell)
                continue
            print(f"profile-proof: {cell['id']}", file=sys.stderr)
            profile = profiles.get(cell.get("profile", ""))
            outcome = execute_cell(cell, profile, repository, ROOT, available, binding["candidate_version"])
            finished = {**cell, "status": "passed", **outcome}
            assert_cell_closed(finished)
            results.append(finished)
        if mode == "all" and only is None and php is None:
            gaps = coverage_gaps(composers, results, available)
            if gaps:
                raise ProfileError(gaps[0])
        for cell in results:
            assert_cell_closed(cell)
        if {cell["id"] for cell in results if cell["status"] == "passed"} != included:
            raise ProfileError("executed cell IDs differ from the selected matrix")
        passed_profiles = [cell for cell in results if cell["kind"] == "profile" and cell["status"] == "passed"]
        portable = [cell for cell in passed_profiles if cell["profile"] == "search-portable"]
        return {
            "schema_version": "corex-profile-receipt/1",
            "item": "P17.6",
            "result": "PASS",
            **binding,
            "mode": mode,
            "matrix": results,
            "dod": {
                "DOD-14": "NOT_APPLICABLE" if not portable else ("PASS" if all("axiomasoft/corex-search-meilisearch" not in cell["installed"] for cell in portable) else "FAIL"),
                "DOD-15": "NOT_APPLICABLE" if not portable else ("PASS" if all("axiomasoft/corex-search-intl" in cell["removed"] for cell in portable) else "FAIL"),
                "DOD-26": "NOT_APPLICABLE" if mode == "edges" else "PASS",
            },
        }


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--candidate", type=Path)
    parser.add_argument("--mode", choices=("all", "profiles", "edges"), default="all")
    parser.add_argument("--only")
    parser.add_argument("--php")
    parser.add_argument("--receipt", type=Path, default=RECEIPT_PATH)
    parser.add_argument("--matrix", type=Path, default=MATRIX_PATH)
    args = parser.parse_args(argv)
    try:
        receipt = prove(args.candidate, mode=args.mode, only=args.only, php=args.php)
    except ProfileError as error:
        print(f"profile proof denied: {error}", file=sys.stderr)
        return 1
    if any(value == "FAIL" for value in receipt["dod"].values()):
        print(f"profile proof denied: {receipt['dod']}", file=sys.stderr)
        return 1
    args.receipt.parent.mkdir(parents=True, exist_ok=True)
    args.matrix.parent.mkdir(parents=True, exist_ok=True)
    matrix = {
        "schema_version": "corex-profile-matrix/1",
        "candidate_sha256": receipt["candidate_sha256"],
        "cells": [
            {key: value for key, value in cell.items() if key in {"id", "kind", "profile", "edge", "php", "laravel", "status", "reason", "packages", "prefer_lowest"}}
            for cell in receipt["matrix"]
        ],
    }
    args.matrix.write_text(json.dumps(matrix, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    args.receipt.write_text(json.dumps(receipt, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    print(json.dumps({"result": receipt["result"], "cells": len(receipt["matrix"]), "receipt": str(args.receipt)}))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
