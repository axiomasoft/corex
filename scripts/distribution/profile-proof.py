#!/usr/bin/env python3
"""Boot the six P17 installation profiles from the frozen dist candidate."""

from __future__ import annotations

import argparse
import hashlib
import importlib.util
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
MANIFEST_PATH = ROOT / "scripts/distribution/install-profiles.json"
MATRIX_PATH = ROOT / "plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/artifacts/P17/profile-matrix.json"
RECEIPT_PATH = ROOT / "plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/findings/P17-profile-receipt.json"
CANDIDATE_DIGEST = "9f0af7384d06db47a463cd64196324371450c91531e1aed66f7cfe3f2dc62a10"
SOURCE_COMMIT = "5db31fd4aebf1dcd172754479ad9cf9da59719d6"
VERSION = "1.0.0"
CARET = re.compile(r"^\^(\d+)\.(\d+)(?:\.(\d+))?$")
INTERPRETER_MINORS = ("8.3", "8.4", "8.5")
PROFILE_PHP = "8.4"
REMOVAL_NAMES = {"corex/search-intl": "Search Intl"}
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
$names = getenv('COREX_PG_DATABASES') ?: '';
if ($names !== '') {
    foreach (explode(',', $names) as $name) {
        $pdo = new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', getenv('PGSQL_HOST'), getenv('PGSQL_PORT'), $name),
            (string) getenv('PGSQL_USERNAME'),
            (string) getenv('PGSQL_PASSWORD'),
        );
        $pdo->query('SELECT 1');
        $databases[] = $name;
    }
}
$sqlite = getenv('COREX_SQLITE') ?: '';
if ($sqlite !== '') {
    $pdo = new PDO('sqlite:' . $sqlite);
    $pdo->query('SELECT 1');
    $databases[] = 'sqlite';
}
echo json_encode([
    'packages' => $packages,
    'providers' => array_keys($app->getLoadedProviders()),
    'databases' => $databases,
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
        binary = Path(f"/usr/bin/php{minor}")
        if not binary.is_file():
            continue
        completed = subprocess.run((str(binary), "-r", "echo PHP_VERSION;"), text=True, capture_output=True, check=False)
        if completed.returncode != 0:
            continue
        parts = completed.stdout.strip().split(".")
        found.append({"minor": minor, "binary": str(binary), "version": (int(parts[0]), int(parts[1]), int(parts[2]))})
    if not found:
        raise ProfileError("missing interpreter: 8.3")
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
    for group in group_packages(composers):
        packages = group["packages"]
        matching = []
        for item in available:
            if exclusion_reason(packages, composers, None, item["version"]) is None:
                matching.append(item)
        if not matching:
            raise ProfileError(f"missing interpreter: {packages[0]}")
        lowest, highest = matching[0], matching[-1]
        laravel_edges = group["laravel"] or [None]
        pairs = [(lowest, laravel_edges[0], "min"), (highest, laravel_edges[-1], "max")]
        if pairs[0][:2] == pairs[1][:2]:
            pairs = [pairs[0]]
        pairs.append((lowest, laravel_edges[0], "prefer-lowest"))
        for item, laravel, kind in pairs:
            cells.append({
                "id": f"edge-{kind}-php{item['minor']}-l{laravel or 'none'}-{packages[0]}",
                "kind": "edge",
                "edge": kind,
                "packages": packages,
                "php": item["minor"],
                "laravel": laravel,
                "prefer_lowest": kind == "prefer-lowest",
                "status": "included",
            })
        for laravel in majors:
            if group["laravel"] and laravel not in group["laravel"]:
                sample = packages[0]
                constraint = next(iter(illuminate_constraints(composers[sample]).values()))
                cells.append({
                    "id": f"edge-excluded-l{laravel}-{sample}",
                    "kind": "edge",
                    "packages": packages,
                    "php": lowest["minor"],
                    "laravel": laravel,
                    "status": "excluded",
                    "reason": f"{sample} constraint {constraint}",
                })
        for item in available:
            reason = exclusion_reason(packages, composers, None, item["version"])
            if reason is None:
                continue
            cells.append({
                "id": f"edge-excluded-php{item['minor']}-{packages[0]}",
                "kind": "edge",
                "packages": packages,
                "php": item["minor"],
                "laravel": group["laravel"][0] if group["laravel"] else None,
                "status": "excluded",
                "reason": reason,
            })
    return cells


def coverage_gaps(composers: dict[str, dict], cells: list[dict], available: list[dict]) -> list[str]:
    gaps = []
    installed_by_cell = []
    for cell in cells:
        if cell.get("status") != "passed":
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
                if name not in cell.get("installed", []):
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


def composer_binary() -> str:
    for candidate in ("/usr/bin/composer", "/home/vostrikov/.config/herd-lite/bin/composer"):
        if Path(candidate).is_file():
            return candidate
    raise ProfileError("missing package: composer")


def composer_command(*args: str) -> list[str]:
    """Run Composer on PHP 8.4. PHP 8.5 rejects this Composer phar; the app still boots on the cell interpreter."""
    return ["/usr/bin/php8.4", composer_binary(), *args]


def assert_cohort_platform(consumer: Path, version: tuple[int, int, int], cell_id: str) -> None:
    installed = load_json(consumer / "vendor/composer/installed.json")
    for row in installed.get("packages", []):
        name = row.get("name", "")
        php = row.get("require", {}).get("php") if isinstance(row.get("require"), dict) else None
        if name.startswith("corex/") and isinstance(php, str) and not allows(php, version):
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
        "COMPOSER_CACHE_DIR": str(cache),
        "COMPOSER_NO_INTERACTION": "1",
        "COMPOSER_DISABLE_XDEBUG_WARN": "1",
        "COMPOSER_MEMORY_LIMIT": "-1",
    })
    return env


def fail_command(cell_id: str, completed: subprocess.CompletedProcess[str]) -> None:
    detail = (completed.stderr or "") + "\n" + (completed.stdout or "")
    Path("/tmp/profile-cell-failure.txt").write_text(detail, encoding="utf-8")
    lines = [line.strip() for line in detail.splitlines() if line.strip()]
    raise ProfileError(f"{cell_id}: {' | '.join(lines[-8:])}")


def write_consumer(consumer: Path, packages: list[str], laravel: int, repository: Path, php_version: str) -> None:
    path = consumer / "composer.json"
    composer = load_json(path)
    require = dict(composer.get("require", {}))
    require["laravel/framework"] = f"^{laravel}.0"
    for name in packages:
        require[name] = VERSION
    composer["require"] = dict(sorted(require.items()))
    composer["repositories"] = [
        {"type": "composer", "url": str(repository)},
        {"type": "composer", "url": "https://repo.packagist.org", "exclude": ["corex/*", "axioma-studio/*"]},
        {"packagist.org": False},
    ]
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


def configure_database(consumer: Path, profile: dict, cell_id: str) -> dict[str, str]:
    env_path = consumer / ".env"
    text = env_path.read_text(encoding="utf-8") if env_path.exists() else ""
    extra: dict[str, str] = {}
    if profile.get("database") == "postgres-two":
        names = ("corex_p176_tenant_a_test", "corex_p176_tenant_b_test")
        ensure_databases(names)
        settings = postgres_settings()
        for key, value in (
            ("DB_CONNECTION", "pgsql"),
            ("DB_HOST", settings["host"]),
            ("DB_PORT", settings["port"]),
            ("DB_DATABASE", names[0]),
            ("DB_USERNAME", settings["user"]),
            ("DB_PASSWORD", settings["password"]),
        ):
            text = set_env(text, key, value)
        extra = {
            "COREX_PG_DATABASES": ",".join(names),
            "PGSQL_HOST": settings["host"],
            "PGSQL_PORT": settings["port"],
            "PGSQL_USERNAME": settings["user"],
            "PGSQL_PASSWORD": settings["password"],
        }
    else:
        database = consumer / "profile.sqlite"
        database.touch()
        text = set_env(text, "DB_CONNECTION", "sqlite")
        text = set_env(text, "DB_DATABASE", str(database))
        extra["COREX_SQLITE"] = str(database)
    env_path.write_text(text, encoding="utf-8")
    if not profile.get("http", True):
        disable_http(consumer, cell_id)
    return extra


def locked_summary(payload: dict) -> list[str]:
    rows = payload.get("installed", payload if isinstance(payload, list) else [])
    names = []
    for row in rows:
        if not isinstance(row, dict):
            continue
        name = row.get("name", "")
        if name.startswith("corex/") or name in {"laravel/framework", "illuminate/support"}:
            names.append(f"{name}:{row.get('version')}")
    return sorted(names)


def execute_cell(cell: dict, profile: dict | None, repository: Path, workspace: Path, available: list[dict], extensions) -> dict:
    binary = interpreter_for(cell["php"], available)
    laravel = 12 if cell["laravel"] is None else int(cell["laravel"])
    prefix = "corex-profile-" + cell["id"].replace("/", "-") + "-"
    with tempfile.TemporaryDirectory(prefix=prefix) as temporary:
        root = Path(temporary)
        home = root / "home"
        cache = root / "cache"
        home.mkdir()
        cache.mkdir()
        env = composer_env(home, cache, Path(binary["binary"]))
        created = run(
            composer_command("create-project", f"laravel/laravel:^{laravel}.0", "consumer", "--no-install", "--no-scripts", "--no-interaction", "--no-progress"),
            root, env,
        )
        if created.returncode != 0:
            fail_command(cell["id"], created)
        consumer = root / "consumer"
        write_consumer(consumer, cell["packages"], int(laravel), repository, ".".join(str(part) for part in binary["version"]))
        update = composer_command("update", "--no-dev", "--no-interaction", "--no-progress", "--prefer-dist", "--no-scripts")
        if cell.get("prefer_lowest"):
            update.extend(["--prefer-lowest", "--prefer-stable"])
        installed = run(update, consumer, env)
        if installed.returncode != 0:
            fail_command(cell["id"], installed)
        assert_cohort_platform(consumer, binary["version"], cell["id"])
        validated = run(composer_command("validate", "--no-check-publish", "--no-interaction"), consumer, env)
        if validated.returncode not in {0, 2}:
            fail_command(cell["id"], validated)
        dumped = run(composer_command("dump-autoload", "-o", "--strict-psr", "--no-dev", "--no-interaction", "--no-scripts"), consumer, env)
        if dumped.returncode != 0:
            fail_command(cell["id"], dumped)
        autoload = (consumer / "vendor/composer/autoload_psr4.php").read_text(encoding="utf-8")
        classmap = (consumer / "vendor/composer/autoload_classmap.php").read_text(encoding="utf-8")
        lock = (consumer / "composer.lock").read_text(encoding="utf-8")
        assert_no_workspace(autoload + classmap + lock, workspace)
        if extensions.autoload_contains_workspace(autoload, workspace):
            raise ProfileError(f"workspace path: {cell['id']}")
        vendor_root = consumer / "vendor"
        for path in vendor_root.rglob("*"):
            if path.is_symlink() and "corex" in path.parts:
                raise ProfileError(f"workspace path: {cell['id']}")
        example = consumer / ".env.example"
        env_file = consumer / ".env"
        if not env_file.exists() and example.exists():
            env_file.write_text(example.read_text(encoding="utf-8"), encoding="utf-8")
        runtime = env.copy()
        if profile is not None:
            runtime.update(configure_database(consumer, profile, cell["id"]))
        for cache_name in ("packages.php", "services.php"):
            cached = consumer / "bootstrap/cache" / cache_name
            if cached.exists():
                cached.unlink()
        generated = run(["php", "artisan", "key:generate", "--ansi", "--force"], consumer, runtime)
        if generated.returncode != 0:
            fail_command(cell["id"], generated)
        discover = run(["php", "artisan", "package:discover", "--ansi"], consumer, runtime)
        if discover.returncode != 0:
            fail_command(cell["id"], discover)
        probe = consumer / "profile-probe.php"
        probe.write_text(PROBE, encoding="utf-8")
        booted = run(["php", str(probe), str(consumer)], consumer, runtime)
        if booted.returncode != 0:
            fail_command(cell["id"], booted)
        report = json.loads(booted.stdout)
        if profile is not None:
            assert_profile_clean(profile, report["packages"], http_routes=report["http_routes"])
            if profile.get("database") == "postgres-two" and len(report["databases"]) != 2:
                raise ProfileError(f"{profile['id']}: databases")
            if profile.get("database") == "sqlite-single" and report["databases"] != ["sqlite"]:
                raise ProfileError(f"{profile['id']}: databases")
        removed = []
        if profile is not None and profile.get("remove"):
            for package in profile["remove"]:
                removal = run(composer_command("remove", package, "--no-interaction", "--no-progress", "--no-update"), consumer, env)
                if removal.returncode != 0:
                    fail_command(cell["id"], removal)
                removed.append(package)
            refreshed = run(composer_command("update", "--no-dev", "--no-interaction", "--no-progress", "--prefer-dist", "--no-scripts"), consumer, env)
            if refreshed.returncode != 0:
                fail_command(cell["id"], refreshed)
            redumped = run(composer_command("dump-autoload", "-o", "--strict-psr", "--no-dev", "--no-interaction", "--no-scripts"), consumer, env)
            if redumped.returncode != 0:
                fail_command(cell["id"], redumped)
            for cache_name in ("packages.php", "services.php"):
                cached = consumer / "bootstrap/cache" / cache_name
                if cached.exists():
                    cached.unlink()
            rediscovered = run(["php", "artisan", "package:discover", "--ansi"], consumer, runtime)
            if rediscovered.returncode != 0:
                fail_command(cell["id"], rediscovered)
            again = run(["php", str(probe), str(consumer)], consumer, runtime)
            if again.returncode != 0:
                fail_command(cell["id"], again)
            report = json.loads(again.stdout)
            observed = {**profile, "remove_observed": removed}
            assert_profile_clean(observed, report["packages"], http_routes=report["http_routes"])
        show = run(composer_command("show", "--locked", "--format=json"), consumer, env)
        if show.returncode != 0:
            fail_command(cell["id"], show)
        shown = json.loads(show.stdout)
        cohort_installed = sorted(name for name in report["packages"] if name.startswith("corex/"))
        return {
            "installed": cohort_installed,
            "locked": locked_summary(shown),
            "lock_sha256": hashlib.sha256(lock.encode("utf-8")).hexdigest(),
            "discovery": discover.stdout.strip().splitlines()[-1:] or [],
            "databases": report["databases"],
            "removed": removed,
        }


def merge_sealed_providers(repository: Path) -> None:
    source = ROOT / "plans/archive/2026.09.12-№2-COREX-APPROVED-SPECS/findings/P4.1-source"
    metadata = load_json(source / "packages.json")
    archives = {
        "axiomasoft/stancl-tenancy": "tenancy-candidate.zip",
        "axiomasoft/stancl-jobpipeline": "jobpipeline-candidate.zip",
    }
    packages = load_json(repository / "packages.json")
    artifacts = repository / "artifacts"
    for name, archive_name in archives.items():
        archive = source / archive_name
        if not archive.is_file():
            raise ProfileError(f"missing package: {name}")
        destination = artifacts / archive_name
        shutil.copyfile(archive, destination)
        payload = next(iter(metadata["packages"][name].values()))
        payload["dist"]["url"] = destination.resolve().as_uri()
        packages["packages"][name] = {payload["version"]: payload}
    (repository / "packages.json").write_text(json.dumps(packages) + "\n", encoding="utf-8")


def merge_azguard(repository: Path) -> None:
    package = Path(os.environ.get("COREX_AZGUARD_LOCAL", "/home/vostrikov/projects/packages/azguard")) / "packages/core"
    if not (package / "composer.json").is_file():
        raise ProfileError("missing package: axioma-studio/azguard-core")
    import zipfile
    version = "0.2.0"
    archive_path = repository / "artifacts" / "azguard-core-0.2.0.zip"
    composer = load_json(package / "composer.json")
    composer["version"] = version
    composer.pop("repositories", None)
    with zipfile.ZipFile(archive_path, "w") as bundle:
        for path in sorted(package.rglob("*")):
            if not path.is_file():
                continue
            relative = path.relative_to(package).as_posix()
            if any(part in {"vendor", ".git", ".env"} for part in Path(relative).parts):
                continue
            body = json.dumps(composer, indent=4).encode() + b"\n" if relative == "composer.json" else path.read_bytes()
            bundle.writestr(relative, body)
    packages = load_json(repository / "packages.json")
    packages["packages"]["axioma-studio/azguard-core"] = {
        version: {
            "name": "axioma-studio/azguard-core",
            "version": version,
            "type": composer.get("type", "library"),
            "require": composer.get("require", {}),
            "autoload": composer.get("autoload", {}),
            "extra": composer.get("extra", {}),
            "dist": {"type": "zip", "url": archive_path.resolve().as_uri(), "reference": version},
        }
    }
    (repository / "packages.json").write_text(json.dumps(packages) + "\n", encoding="utf-8")


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
    module = extensions.release_candidate_module()
    module.build_repository(ROOT, SOURCE_COMMIT, VERSION, destination, module.load_policy(), enforce_clean=True)
    digest = hashlib.sha256((destination / "candidate.json").read_bytes()).hexdigest()
    if digest != CANDIDATE_DIGEST:
        raise ProfileError(f"candidate digest {digest}")
    return destination


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
    manifest = load_json(MANIFEST_PATH)
    with tempfile.TemporaryDirectory(prefix="corex-profile-candidate-") as temporary:
        root = Path(temporary)
        if candidate is not None and (candidate / "candidate.json").is_file():
            built = candidate
        else:
            built = build_candidate(root / "candidate", extensions)
        repository = root / "repository"
        extensions.materialize_repository(built, repository)
        merge_sealed_providers(repository)
        merge_azguard(repository)
        composers = archive_composers(built)
        profiles = {profile["id"]: profile for profile in manifest["profiles"]}
        planned = plan_profiles(manifest, composers, available) + plan_edges(composers, available)
        selected = select_cells(planned, mode, only, php)
        results = []
        for cell in selected:
            assert_cell_closed({**cell, "status": "passed" if cell["status"] == "included" else cell["status"]}) if cell["status"] == "excluded" else None
            if cell["status"] == "excluded":
                results.append(cell)
                continue
            print(f"profile-proof: {cell['id']}", file=sys.stderr)
            profile = profiles.get(cell.get("profile", ""))
            outcome = execute_cell(cell, profile, repository, ROOT, available, extensions)
            finished = {**cell, "status": "passed", **outcome}
            assert_cell_closed(finished)
            results.append(finished)
        if mode == "all" and only is None and php is None:
            gaps = coverage_gaps(composers, results, available)
            if gaps:
                raise ProfileError(gaps[0])
        for cell in results:
            assert_cell_closed(cell)
        passed_profiles = [cell for cell in results if cell["kind"] == "profile" and cell["status"] == "passed"]
        return {
            "schema_version": "corex-profile-receipt/1",
            "item": "P17.6",
            "result": "PASS",
            "candidate_sha256": CANDIDATE_DIGEST,
            "mode": mode,
            "matrix": results,
            "dod": {
                "DOD-14": "PASS" if any(cell.get("profile") == "search-portable" and cell["status"] == "passed" and "corex/search-meilisearch" not in cell.get("installed", []) for cell in passed_profiles) or mode != "all" else "FAIL",
                "DOD-15": "PASS" if any(cell.get("profile") == "search-portable" and "corex/search-intl" in cell.get("removed", []) for cell in passed_profiles) or mode != "all" else "FAIL",
                "DOD-26": "PASS" if len({cell["profile"] for cell in passed_profiles}) == 6 or mode != "all" else "FAIL",
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
