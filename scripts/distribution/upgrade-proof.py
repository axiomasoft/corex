#!/usr/bin/env python3
"""Prove upgrade from each cohort package's latest immutable release to the frozen 1.0.0 candidate."""

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
import zipfile
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import quote

ROOT = Path(__file__).resolve().parents[2]
BASELINES_PATH = ROOT / "scripts/distribution/release-baselines.json"
RECEIPT_PATH = ROOT / "plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/findings/P17-upgrade-receipt.json"
COHORT_PATH = ROOT / "plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/artifacts/P17/release-cohort.json"
CANDIDATE_DIGEST = "9f0af7384d06db47a463cd64196324371450c91531e1aed66f7cfe3f2dc62a10"
SOURCE_COMMIT = "5db31fd4aebf1dcd172754479ad9cf9da59719d6"
VERSION = "1.0.0"
CACHE = Path(os.environ.get("COREX_UPGRADE_CACHE", "/tmp/corex-upgrade-baselines"))
COMMIT_RE = re.compile(r"^[0-9a-f]{40}$")
PROBE = r"""<?php
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = $argv[1];
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$tables = [];
$migrations = null;
$driver = config('database.default');
if ($driver === 'pgsql') {
    $tables = array_map(static fn ($row) => $row->tablename, DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"));
} else {
    $tables = array_map(static fn ($row) => $row->name, DB::select("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name"));
}
if (in_array('migrations', $tables, true)) {
    $migrations = (int) DB::table('migrations')->count();
}
$providers = array_values(array_filter(array_keys($app->getLoadedProviders()), static fn ($name) => str_starts_with($name, 'CoreX\\')));
sort($providers);
$config = array_keys(config()->all());
sort($config);
echo json_encode(['booted' => true, 'tables' => $tables, 'migrations' => $migrations, 'providers' => $providers, 'config' => $config], JSON_THROW_ON_ERROR);
"""


class UpgradeError(RuntimeError):
    pass


def load_json(path: Path) -> dict:
    return json.loads(path.read_text(encoding="utf-8"))


def profile_module():
    script = ROOT / "scripts/distribution/profile-proof.py"
    spec = importlib.util.spec_from_file_location("profile_proof", script)
    if spec is None or spec.loader is None:
        raise UpgradeError("missing package: profile-proof")
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    return module


def version_key(tag: str) -> tuple:
    raw = tag[1:] if tag.startswith("v") else tag
    number, _, pre = raw.partition("-")
    parts = tuple(int(part) for part in number.split(".") if part != "")
    return (parts, pre == "", pre)


def release_version(tag: str) -> str:
    return tag[1:] if tag.startswith("v") else tag


def assert_candidate(actual: str, expected: str = CANDIDATE_DIGEST) -> None:
    if actual != expected:
        raise UpgradeError("stale candidate")


def assert_baseline_digest(actual: str, expected: str) -> None:
    if actual != expected:
        raise UpgradeError("wrong baseline digest")


def assert_migration_recovery(migrated: bool, recovered: bool) -> None:
    if not migrated and not recovered:
        raise UpgradeError("migration fault without forward recovery")


def assert_baseline_row(row: dict, source_commit: str = SOURCE_COMMIT) -> None:
    classification = row.get("classification")
    if classification == "new-package":
        absence = row.get("absence")
        if row.get("prior_version") or row.get("archive_sha256") or not isinstance(absence, dict) or absence.get("observed") is not True:
            raise UpgradeError("missing remote absence")
        if row.get("reason") != "not-applicable/new-package":
            raise UpgradeError("missing remote absence")
        return
    if classification != "upgraded":
        raise UpgradeError("generic skip")
    ref = row.get("immutable_ref")
    channel = row.get("channel")
    if (not isinstance(ref, str) or not ref or ref == source_commit or COMMIT_RE.fullmatch(ref)
            or channel == "monorepo-tag"):
        raise UpgradeError("monorepo tag substitution")
    if channel != "github-release" or not isinstance(row.get("archive_sha256"), str):
        raise UpgradeError("wrong baseline digest")


def select_latest(releases: list[dict]) -> dict | None:
    usable = [release for release in releases if release.get("asset")]
    if not usable:
        return None
    return max(usable, key=lambda release: version_key(str(release["tag"])))


def cohort_packages() -> list[dict]:
    return load_json(COHORT_PATH)["packages"]


def gh_json(path: str) -> tuple[int, dict | list | None, str]:
    completed = subprocess.run(["gh", "api", path], text=True, capture_output=True, check=False)
    if completed.returncode:
        detail = (completed.stderr or completed.stdout).strip()
        if "404" in detail or "Not Found" in detail:
            return 404, None, detail
        raise UpgradeError(f"remote baseline query failed: {detail}")
    return 200, json.loads(completed.stdout), ""


def discover_package(package: dict) -> dict:
    mirror = package["mirror"]
    status, payload, detail = gh_json(f"repos/{mirror}/releases?per_page=20")
    if status == 404 or not isinstance(payload, list):
        return {
            "package": package["name"],
            "classification": "new-package",
            "prior_version": None,
            "channel": "github-release",
            "immutable_ref": None,
            "archive_sha256": None,
            "archive_url": None,
            "mirror": mirror,
            "reason": "not-applicable/new-package",
            "absence": {"observed": True, "status": status, "mirror": mirror, "detail": detail[:240]},
        }
    releases = []
    for release in payload:
        if release.get("draft"):
            continue
        assets = [asset for asset in release.get("assets", []) if str(asset.get("name", "")).endswith(".zip")]
        if not assets:
            continue
        releases.append({
            "tag": release["tag_name"],
            "asset": assets[0]["name"],
            "api": assets[0]["url"],
            "url": assets[0].get("browser_download_url"),
        })
    archives = []
    for release in releases:
        version = release_version(str(release["tag"]))
        archive = download_asset(mirror, str(release["tag"]), str(release["api"]), CACHE / f"{package['name'].replace('/', '-')}-{version}.zip")
        archives.append({
            "version": version,
            "immutable_ref": str(release["tag"]),
            "archive_sha256": hashlib.sha256(archive.read_bytes()).hexdigest(),
            "archive_url": release.get("url"),
            "asset_api": release["api"],
            "mirror": mirror,
        })
    latest = select_latest(releases)
    if latest is None:
        return {
            "package": package["name"],
            "classification": "new-package",
            "prior_version": None,
            "channel": "github-release",
            "immutable_ref": None,
            "archive_sha256": None,
            "archive_url": None,
            "mirror": mirror,
            "reason": "not-applicable/new-package",
            "absence": {"observed": True, "status": 200, "mirror": mirror, "asset_count": 0},
        }
    selected = next(archive for archive in archives if archive["immutable_ref"] == latest["tag"])
    return {
        "package": package["name"],
        "classification": "upgraded",
        "prior_version": selected["version"],
        "channel": "github-release",
        "immutable_ref": selected["immutable_ref"],
        "archive_sha256": selected["archive_sha256"],
        "archive_url": selected["archive_url"],
        "asset_api": selected["asset_api"],
        "mirror": mirror,
        "reason": "latest published GitHub release asset",
        "absence": None,
        "supporting_archives": [archive for archive in archives if archive["immutable_ref"] != selected["immutable_ref"]],
    }


def download_asset(mirror: str, tag: str, api_url: str, destination: Path) -> Path:
    destination.parent.mkdir(parents=True, exist_ok=True)
    if destination.is_file():
        return destination
    completed = subprocess.run(
        ["gh", "api", api_url, "-H", "Accept: application/octet-stream"],
        capture_output=True,
        check=False,
    )
    if completed.returncode or not completed.stdout:
        raise UpgradeError(f"remote baseline download failed: {mirror}@{tag}")
    destination.write_bytes(completed.stdout)
    return destination


def discover(path: Path = BASELINES_PATH) -> dict:
    rows = [discover_package(package) for package in cohort_packages()]
    for row in rows:
        assert_baseline_row(row)
    document = {
        "schema_version": "corex-release-baselines/1",
        "candidate_sha256": CANDIDATE_DIGEST,
        "source_commit": SOURCE_COMMIT,
        "observed_at": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
        "packages": rows,
    }
    path.write_text(json.dumps(document, indent=2) + "\n", encoding="utf-8")
    return document


def zip_metadata(archive: Path) -> dict:
    with zipfile.ZipFile(archive) as bundle:
        composer = json.loads(bundle.read("composer.json"))
    composer["version"] = composer.get("version")
    return composer


def stage_archive(package: str, archive_row: dict, artifacts: Path, packages: dict[str, dict]) -> None:
    version = archive_row["version"] if "version" in archive_row else archive_row["prior_version"]
    archive = CACHE / f"{package.replace('/', '-')}-{version}.zip"
    if not archive.is_file():
        download_asset(archive_row["mirror"], archive_row["immutable_ref"], archive_row["asset_api"], archive)
    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    assert_baseline_digest(digest, archive_row["archive_sha256"])
    staged = artifacts / archive.name
    shutil.copyfile(archive, staged)
    composer = zip_metadata(staged)
    composer["name"] = package
    composer["version"] = version
    composer["dist"] = {"type": "zip", "url": staged.resolve().as_uri()}
    packages.setdefault(package, {})[version] = composer


def stage_baseline_repository(baselines: list[dict], repository: Path) -> None:
    artifacts = repository / "artifacts"
    artifacts.mkdir(parents=True, exist_ok=True)
    packages: dict[str, dict] = {}
    for row in baselines:
        if row["classification"] != "upgraded":
            continue
        stage_archive(row["package"], row, artifacts, packages)
        for archive in row.get("supporting_archives") or []:
            stage_archive(row["package"], archive, artifacts, packages)
    (repository / "packages.json").write_text(json.dumps({"packages": packages}) + "\n", encoding="utf-8")


def run(command: list[str], cwd: Path, env: dict[str, str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(command, cwd=cwd, env=env, text=True, capture_output=True, check=False)


def fail_command(cell: str, completed: subprocess.CompletedProcess[str]) -> None:
    detail = (completed.stderr or "") + "\n" + (completed.stdout or "")
    Path("/tmp/upgrade-cell-failure.txt").write_text(detail, encoding="utf-8")
    lines = [line.strip() for line in detail.splitlines() if line.strip()]
    raise UpgradeError(f"{cell}: {' | '.join(lines[-8:])}")


def write_consumer(consumer: Path, packages: dict[str, str], repository: Path, candidate: Path | None) -> None:
    composer = load_json(consumer / "composer.json")
    require = dict(composer.get("require", {}))
    require["laravel/framework"] = "^12.0"
    require.update(packages)
    composer["require"] = dict(sorted(require.items()))
    repositories = []
    if candidate is not None:
        repositories.append({"type": "composer", "url": str(candidate)})
    repositories.extend([
        {"type": "composer", "url": str(repository)},
        {"type": "composer", "url": "https://repo.packagist.org", "exclude": ["corex/*", "axioma-studio/*"]},
        {"packagist.org": False},
    ])
    composer["repositories"] = repositories
    composer["minimum-stability"] = "dev"
    composer["prefer-stable"] = True
    config = dict(composer.get("config", {}))
    config.update({
        "allow-plugins": True,
        "secure-http": False,
        "preferred-install": "dist",
        "sort-packages": True,
        "audit": {"block-insecure": False},
        "platform": {"php": "8.4.1"},
    })
    composer["config"] = config
    (consumer / "composer.json").write_text(json.dumps(composer, indent=4) + "\n", encoding="utf-8")


def configure_application(consumer: Path, database: str | None, env: dict[str, str]) -> None:
    example = consumer / ".env.example"
    target = consumer / ".env"
    target.write_text(example.read_text(encoding="utf-8"), encoding="utf-8")
    text = target.read_text(encoding="utf-8")
    if database is None:
        text = text.replace("DB_CONNECTION=pgsql", "DB_CONNECTION=sqlite")
        text += "\nDB_DATABASE=database/database.sqlite\n"
        (consumer / "database").mkdir(exist_ok=True)
        (consumer / "database/database.sqlite").touch()
    else:
        replacements = {
            "DB_CONNECTION=sqlite": "DB_CONNECTION=pgsql",
            "DB_HOST=127.0.0.1": "DB_HOST=127.0.0.1",
            "DB_PORT=3306": "DB_PORT=5434",
        }
        for old, new in replacements.items():
            text = text.replace(old, new)
        if "DB_CONNECTION=" not in text:
            text += "\nDB_CONNECTION=pgsql\n"
        text += f"\nDB_HOST=127.0.0.1\nDB_PORT=5434\nDB_DATABASE={database}\nDB_USERNAME=corex\nDB_PASSWORD=corex\n"
    target.write_text(text, encoding="utf-8")
    key = run(["/usr/bin/php8.4", "artisan", "key:generate", "--force", "--no-interaction"], consumer, env)
    if key.returncode:
        fail_command(consumer.name, key)


def ensure_database(name: str) -> None:
    if not name.endswith("_test"):
        raise UpgradeError(f"database name {name}")
    environment = os.environ.copy()
    environment["PGPASSWORD"] = "corex"
    exists = run(["psql", "-h", "127.0.0.1", "-p", "5434", "-U", "corex", "-d", "postgres", "-tAc",
                  f"SELECT 1 FROM pg_database WHERE datname='{name}'"], Path("/"), environment)
    if exists.returncode:
        fail_command(name, exists)
    if exists.stdout.strip() == "1":
        return
    created = run(["psql", "-h", "127.0.0.1", "-p", "5434", "-U", "corex", "-d", "postgres", "-c",
                   f'CREATE DATABASE "{name}"'], Path("/"), environment)
    if created.returncode:
        fail_command(name, created)


def publish_migrations(package_dir: Path, consumer: Path) -> list[str]:
    root = package_dir / "database" / "migrations"
    if not root.is_dir():
        return []
    destination = consumer / "database" / "migrations"
    destination.mkdir(parents=True, exist_ok=True)
    published = []
    for source in sorted(root.rglob("*.php")):
        target = destination / source.name
        if target.exists():
            continue
        shutil.copy2(source, target)
        published.append(source.name)
    return published


def boot_report(consumer: Path, env: dict[str, str]) -> dict:
    probe = consumer / "upgrade-probe.php"
    probe.write_text(PROBE, encoding="utf-8")
    completed = run(["/usr/bin/php8.4", "upgrade-probe.php", str(consumer)], consumer, env)
    if completed.returncode:
        fail_command(consumer.name, completed)
    return json.loads(completed.stdout)


def install_consumer(parent: Path, packages: dict[str, str], baseline_repo: Path, candidate_repo: Path | None, profiles) -> Path:
    created = run(profiles.composer_command(
        "create-project", "laravel/laravel:^12.0", "consumer", "--no-install", "--no-scripts", "--no-interaction", "--no-progress",
    ), parent, profiles.composer_env(parent / "home", parent / "cache", Path("/usr/bin/php8.4")))
    if created.returncode:
        fail_command("create-project", created)
    consumer = parent / "consumer"
    write_consumer(consumer, packages, baseline_repo, candidate_repo)
    env = profiles.composer_env(parent / "home", parent / "cache", Path("/usr/bin/php8.4"))
    updated = run(profiles.composer_command(
        "update", "--no-dev", "--no-interaction", "--no-progress", "--prefer-dist", "--no-scripts",
    ), consumer, env)
    if updated.returncode:
        fail_command(consumer.name, updated)
    dumped = run(profiles.composer_command(
        "dump-autoload", "-o", "--strict-psr", "--no-dev", "--no-interaction", "--no-scripts",
    ), consumer, env)
    if dumped.returncode:
        fail_command(consumer.name, dumped)
    return consumer


def vendor_package(consumer: Path, name: str) -> Path:
    path = consumer / "vendor" / name
    if not path.is_dir():
        raise UpgradeError(f"missing package: {name}")
    return path


def clear_bootstrap(consumer: Path) -> None:
    cache = consumer / "bootstrap/cache"
    for filename in ("packages.php", "services.php"):
        file = cache / filename
        if file.exists():
            file.unlink()


def publish_installed_migrations(consumer: Path) -> list[str]:
    vendor = consumer / "vendor/corex"
    if not vendor.is_dir():
        return []
    published = []
    for package_dir in sorted(path for path in vendor.iterdir() if path.is_dir()):
        published.extend(publish_migrations(package_dir, consumer))
    return published


def migrate(consumer: Path, env: dict[str, str], cell: str) -> bool:
    completed = run(["/usr/bin/php8.4", "artisan", "migrate", "--force", "--no-interaction"], consumer, env)
    if completed.returncode:
        detail = (completed.stderr or "") + "\n" + (completed.stdout or "")
        Path("/tmp/upgrade-cell-failure.txt").write_text(detail, encoding="utf-8")
        lines = [line.strip() for line in detail.splitlines() if line.strip()]
        raise UpgradeError(f"{cell}: migration fault without forward recovery | {' | '.join(lines[-6:])}")
    return True


def prove_row(row: dict, baseline_repo: Path, candidate_repo: Path, profiles) -> dict:
    assert_baseline_row(row)
    slug = row["package"].replace("/", "_").replace("-", "_")
    print(f"upgrade-proof: {row['package']}", file=sys.stderr)
    with tempfile.TemporaryDirectory(prefix=f"corex-upgrade-{slug}-") as temporary:
        parent = Path(temporary)
        if row["classification"] == "new-package":
            consumer = install_consumer(parent, {row["package"]: VERSION}, baseline_repo, candidate_repo, profiles)
            env = profiles.composer_env(parent / "home", parent / "cache", Path("/usr/bin/php8.4"))
            configure_application(consumer, None, env)
            clear_bootstrap(consumer)
            discovered = run(["/usr/bin/php8.4", "artisan", "package:discover", "--no-interaction"], consumer, env)
            if discovered.returncode:
                fail_command(row["package"], discovered)
            report = boot_report(consumer, env)
            return {"package": row["package"], "classification": "new-package", "booted": report["booted"]}
        database = f"corex_p177_{slug}_test"
        ensure_database(database)
        consumer = install_consumer(parent, {row["package"]: row["prior_version"]}, baseline_repo, None, profiles)
        env = profiles.composer_env(parent / "home", parent / "cache", Path("/usr/bin/php8.4"))
        vendor_package(consumer, row["package"])
        published = publish_installed_migrations(consumer)
        configure_application(consumer, database if published else None, env)
        clear_bootstrap(consumer)
        discovered = run(["/usr/bin/php8.4", "artisan", "package:discover", "--no-interaction"], consumer, env)
        if discovered.returncode:
            fail_command(row["package"], discovered)
        if published:
            migrate(consumer, env, row["package"])
        before = boot_report(consumer, env)
        marker = {"package": row["package"], "prior_version": row["prior_version"]}
        marker_path = consumer / "storage/upgrade-fixture.json"
        marker_path.write_text(json.dumps(marker, sort_keys=True), encoding="utf-8")
        marker_digest = hashlib.sha256(marker_path.read_bytes()).hexdigest()
        before_lock = (consumer / "composer.lock").read_text(encoding="utf-8")
        write_consumer(consumer, {row["package"]: VERSION}, baseline_repo, candidate_repo)
        updated = run(profiles.composer_command(
            "update", "--no-dev", "--no-interaction", "--no-progress", "--prefer-dist", "--no-scripts",
        ), consumer, env)
        if updated.returncode:
            fail_command(row["package"], updated)
        after_lock = (consumer / "composer.lock").read_text(encoding="utf-8")
        if hashlib.sha256(marker_path.read_bytes()).hexdigest() != marker_digest:
            raise UpgradeError(f"{row['package']}: populated fixture changed")
        vendor_package(consumer, row["package"])
        publish_installed_migrations(consumer)
        clear_bootstrap(consumer)
        dumped = run(profiles.composer_command(
            "dump-autoload", "-o", "--strict-psr", "--no-dev", "--no-interaction", "--no-scripts",
        ), consumer, env)
        if dumped.returncode:
            fail_command(row["package"], dumped)
        rediscovered = run(["/usr/bin/php8.4", "artisan", "package:discover", "--no-interaction"], consumer, env)
        if rediscovered.returncode:
            fail_command(row["package"], rediscovered)
        if published:
            migrate(consumer, env, row["package"])
        after = boot_report(consumer, env)
        missing_tables = sorted(set(before["tables"]) - set(after["tables"]))
        if missing_tables:
            raise UpgradeError(f"{row['package']}: schema lost {missing_tables[0]}")
        missing_providers = sorted(set(before["providers"]) - set(after["providers"]))
        if missing_providers:
            raise UpgradeError(f"{row['package']}: public provider lost {missing_providers[0]}")
        missing_config = sorted(set(before["config"]) - set(after["config"]))
        if missing_config:
            raise UpgradeError(f"{row['package']}: config lost {missing_config[0]}")
        if published:
            migrate(consumer, env, row["package"])
            rerun = boot_report(consumer, env)
            if rerun["migrations"] != after["migrations"]:
                raise UpgradeError(f"{row['package']}: migration rerun is not idempotent")
        return {
            "package": row["package"],
            "classification": "upgraded",
            "prior_version": row["prior_version"],
            "immutable_ref": row["immutable_ref"],
            "archive_sha256": row["archive_sha256"],
            "tables": after["tables"],
            "migrations": after["migrations"],
            "lock_changed": hashlib.sha256(before_lock.encode()).hexdigest() != hashlib.sha256(after_lock.encode()).hexdigest(),
            "fixture_sha256": marker_digest,
        }


def prove(path: Path, only: str | None) -> dict:
    document = load_json(path)
    if document.get("candidate_sha256") != CANDIDATE_DIGEST or document.get("source_commit") != SOURCE_COMMIT:
        raise UpgradeError("stale candidate")
    rows = document["packages"]
    names = [row["package"] for row in rows]
    expected = [package["name"] for package in cohort_packages()]
    if names != expected:
        raise UpgradeError("generic skip")
    for row in rows:
        assert_baseline_row(row)
    selected = [row for row in rows if only is None or row["package"] == only]
    if only is not None and not selected:
        raise UpgradeError(f"missing package: {only}")
    profiles = profile_module()
    with tempfile.TemporaryDirectory(prefix="corex-upgrade-candidate-") as temporary:
        root = Path(temporary)
        candidate = profiles.build_candidate(root / "candidate", profiles.extension_module())
        digest = hashlib.sha256((candidate / "candidate.json").read_bytes()).hexdigest()
        assert_candidate(digest)
        repository = root / "repository"
        profiles.extension_module().materialize_repository(candidate, repository)
        profiles.merge_sealed_providers(repository)
        profiles.merge_azguard(repository)
        baseline_repo = root / "baselines"
        stage_baseline_repository(rows, baseline_repo)
        profiles.merge_sealed_providers(baseline_repo)
        results = [prove_row(row, baseline_repo, repository, profiles) for row in selected]
    complete = only is None and len(results) == len(rows)
    return {
        "schema_version": "corex-upgrade-receipt/1",
        "item": "P17.7",
        "result": "PASS" if complete else "PARTIAL",
        "candidate_sha256": CANDIDATE_DIGEST,
        "packages": results,
        "dod": {"DOD-21": "PASS" if complete else "FAIL", "DOD-22": "PASS" if complete else "FAIL"},
    }


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("action", choices=("discover", "prove"))
    parser.add_argument("--baselines", type=Path, default=BASELINES_PATH)
    parser.add_argument("--receipt", type=Path, default=RECEIPT_PATH)
    parser.add_argument("--only")
    args = parser.parse_args(argv)
    try:
        if args.action == "discover":
            document = discover(args.baselines)
            print(json.dumps({"result": "PINNED", "packages": len(document["packages"]), "baselines": str(args.baselines)}))
            return 0
        receipt = prove(args.baselines, args.only)
        if args.only is None and any(value != "PASS" for value in receipt["dod"].values()):
            raise UpgradeError(str(receipt["dod"]))
    except UpgradeError as error:
        print(f"upgrade proof denied: {error}", file=sys.stderr)
        return 1
    args.receipt.parent.mkdir(parents=True, exist_ok=True)
    args.receipt.write_text(json.dumps(receipt, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    print(json.dumps({"result": receipt["result"], "packages": len(receipt["packages"]), "receipt": str(args.receipt)}))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
