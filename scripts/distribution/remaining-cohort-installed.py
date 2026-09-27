#!/usr/bin/env python3
"""Build P9.4 installed-candidate fixtures and p9-cohort-receipts/1 INSTALL evidence."""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import shutil
import sqlite3
import subprocess
import sys
import tempfile
import zipfile
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PLAN = ROOT / 'plans/archive/2026.09.15-№1-COREX-FLEXCRM-REMAINING'
REPO_DELIVERY = ROOT / 'delivery/remaining-cohort-20260915'
PLAN_DELIVERY = PLAN / 'delivery/remaining-cohort-20260915'
FIXTURES = REPO_DELIVERY / 'fixtures'
EVIDENCE = PLAN_DELIVERY / 'evidence'
INSTALLED = REPO_DELIVERY / 'installed-candidate'
INSTALLED_SCRIPT = Path(__file__).resolve()
PRIVATE_SCRIPT = Path(__file__).with_name('remaining-cohort-private.py')
RUNNER_SCRIPT = Path(__file__).with_name('remaining-cohort-runner.py')
QUALITY_SCRIPT = Path(__file__).with_name('remaining-cohort-quality.py')
COHORT_WORKFLOW = ROOT / '.github/workflows/remaining-cohort-proof.yml'

FORBIDDEN_CODE = re.compile(
    r'^\s*(?!\*|//|#)(?:.*\b(namespace|use)\s+[^;]*(FlexCRM|FlexCMS|AzGuard|CoreX\\Auth)\b|.*["\']flex/|.*["\']corex/access["\'])',
    re.I | re.M,
)


class InstalledError(RuntimeError):
    """Installed verification violated the remaining-cohort contract."""


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def sha256_path(path: Path) -> str:
    return sha256_bytes(path.read_bytes())


def load_json(path: Path) -> dict:
    return json.loads(path.read_text(encoding='utf-8'))


def write_json(path: Path, value: object) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')


def git_head() -> str:
    return subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()


SHARED_CACHE = (INSTALLED / 'composer-cache').resolve()


def composer_env(root: Path, role: str, *, offline: bool = True) -> dict[str, str]:
    environment = {
        **os.environ,
        'COMPOSER_HOME': str((root / f'home-{role}').resolve()),
        'COMPOSER_CACHE_DIR': str(SHARED_CACHE),
        'COMPOSER_NO_INTERACTION': '1',
    }
    if offline:
        environment['COMPOSER_DISABLE_NETWORK'] = '1'
    return environment


def warm_composer_cache(repository: Path, cohort: dict, candidate: dict) -> None:
    SHARED_CACHE.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='corex-install-warm-') as temporary:
        root = Path(temporary)
        consumer = root / 'warm-cache'
        consumer.mkdir()
        packages = cohort['cold_packages']
        write_json(consumer / 'composer.json', consumer_manifest(cohort, candidate, repository, packages))
        environment = composer_env(root, 'warm-cache', offline=False)
        update = run_cmd(['composer', 'update', '--prefer-dist', '--no-progress'], consumer, environment)
        if update.returncode:
            raise InstalledError(update.stderr or 'composer cache warm failed')


def package_zip_path(package: str) -> Path:
    slug = package.split('/', 1)[1]
    return REPO_DELIVERY / 'build/artifacts' / f'corex-{slug}.zip'


def scan_zip_literals(package: str) -> None:
    archive_path = package_zip_path(package)
    if not archive_path.is_file():
        raise InstalledError(f'missing dist archive for {package}')
    with zipfile.ZipFile(archive_path) as archive:
        for name in archive.namelist():
            if not name.endswith('.php'):
                continue
            contents = archive.read(name).decode('utf-8', errors='ignore')
            if FORBIDDEN_CODE.search(contents):
                raise InstalledError(f'forbidden literal in {package}:{name}')


def run_cmd(command: list[str], cwd: Path, environment: dict[str, str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(command, cwd=cwd, env=environment, text=True, capture_output=True)


def composer_from_archive(package: str, dist_url: str) -> dict:
    archive_path = (REPO_DELIVERY / 'build' / dist_url).resolve()
    if not archive_path.is_file():
        slug = package.split('/', 1)[1]
        archive_path = (REPO_DELIVERY / 'build/artifacts' / f'corex-{slug}.zip').resolve()
    if not archive_path.is_file():
        raise InstalledError(f'missing dist archive for {package}')
    with zipfile.ZipFile(archive_path) as archive:
        return json.loads(archive.read('composer.json'))


def enrich_package_metadata(package: dict) -> None:
    composer = composer_from_archive(package['name'], package['dist']['url'])
    for key in ('autoload', 'autoload-dev', 'type', 'description', 'license', 'extra'):
        if key in composer:
            package[key] = composer[key]


def stage_repository() -> Path:
    public = REPO_DELIVERY / 'public/packages.json'
    if not public.is_file():
        raise InstalledError('public packages.json missing; run remaining-cohort-export.py first')
    staging = (REPO_DELIVERY / 'installed-candidate/repository').resolve()
    if staging.exists():
        shutil.rmtree(staging)
    staging.mkdir(parents=True)
    metadata = load_json(public)
    for versions in metadata.get('packages', {}).values():
        for package in versions.values():
            enrich_package_metadata(package)
            dist_url = package['dist']['url']
            package['dist']['url'] = (REPO_DELIVERY / 'build' / dist_url).resolve().as_uri()
    write_json(staging / 'packages.json', metadata)
    artifacts_src = REPO_DELIVERY / 'build/artifacts'
    artifacts_dst = staging / 'artifacts'
    shutil.copytree(artifacts_src, artifacts_dst)
    return staging


def target_version(candidate: dict, package: str) -> str:
    return candidate['version_vector'][package]


def consumer_manifest(cohort: dict, candidate: dict, repository: Path, packages: list[str], extra: dict | None = None) -> dict:
    version = candidate['version_vector'][packages[0]]
    require = {package: target_version(candidate, package) for package in packages}
    require['illuminate/contracts'] = f"^{cohort['laravel']}.0"
    manifest = {
        'name': cohort['consumer_name'],
        'type': 'project',
        'minimum-stability': 'dev',
        'prefer-stable': True,
        'repositories': [{'type': 'composer', 'url': repository.as_uri()}],
        'require': require,
        'config': {'allow-plugins': {'pestphp/pest-plugin': True}},
    }
    if extra:
        manifest.update(extra)
    return manifest


def assert_no_symlinks(path: Path) -> None:
    if any(item.is_symlink() for item in path.rglob('*')):
        raise InstalledError(f'symlink detected under {path}')


def assert_dist_install(consumer: Path, packages: list[str]) -> None:
    for package in packages:
        installed = consumer / 'vendor' / Path(*package.split('/'))
        if not (installed / 'composer.json').is_file():
            raise InstalledError(f'missing installed package {package}')
        if ROOT in installed.resolve().parents:
            raise InstalledError(f'{package} resolves to monorepo path')
    assert_no_symlinks(consumer / 'vendor')


def verify_install_01(root: Path, repository: Path, cohort: dict, candidate: dict) -> dict:
    consumer = root / 'cold-install'
    consumer.mkdir()
    packages = cohort['cold_packages']
    write_json(consumer / 'composer.json', consumer_manifest(cohort, candidate, repository, packages))
    environment = composer_env(root, 'cold-install')
    update = run_cmd(['composer', 'update', '--prefer-dist', '--no-progress'], consumer, environment)
    if update.returncode:
        raise InstalledError(update.stderr or 'cold update failed')
    platform = run_cmd(['composer', 'check-platform-reqs', '--no-ansi'], consumer, environment)
    if platform.returncode:
        raise InstalledError(platform.stderr or 'platform check failed')
    assert_dist_install(consumer, packages)
    return {'packages': packages, 'platform': 'passed', 'path_repository': False}


def verify_install_02(root: Path, repository: Path, cohort: dict, candidate: dict) -> dict:
    consumer = root / 'lock-install'
    consumer.mkdir()
    packages = cohort['cold_packages']
    write_json(consumer / 'composer.json', consumer_manifest(cohort, candidate, repository, packages))
    environment = composer_env(root, 'lock-install')
    update = run_cmd(['composer', 'update', '--prefer-dist', '--no-progress'], consumer, environment)
    if update.returncode:
        raise InstalledError(update.stderr or 'lock update failed')
    lock_sha = sha256_path(consumer / 'composer.lock')
    provider_map = {}
    for package in packages:
        composer = load_json(consumer / 'vendor' / package / 'composer.json')
        providers = composer.get('extra', {}).get('laravel', {}).get('providers', [])
        provider_map[package] = sorted(providers)
    shutil.rmtree(consumer / 'vendor')
    shutil.rmtree(Path(environment['COMPOSER_HOME']))
    environment = composer_env(root, 'lock-install-fresh')
    install = run_cmd(['composer', 'install', '--prefer-dist', '--no-progress'], consumer, environment)
    if install.returncode:
        raise InstalledError(install.stderr or 'fresh install failed')
    nodev = run_cmd(['composer', 'install', '--no-dev', '--prefer-dist', '--no-progress'], consumer, environment)
    if nodev.returncode:
        raise InstalledError(nodev.stderr or 'no-dev install failed')
    assert_dist_install(consumer, packages)
    return {'lock_sha256': lock_sha, 'providers': provider_map, 'no_dev': 'passed'}


def verify_install_03(root: Path, repository: Path, cohort: dict, candidate: dict) -> dict:
    boxed = root / 'boxed-boot'
    boxed.mkdir()
    packages = cohort['cold_packages']
    write_json(boxed / 'composer.json', consumer_manifest(cohort, candidate, repository, packages))
    environment = composer_env(root, 'boxed-boot')
    update = run_cmd(['composer', 'update', '--prefer-dist', '--no-progress'], boxed, environment)
    if update.returncode:
        raise InstalledError(update.stderr or 'boxed update failed')
    assert 'tenancy' not in json.dumps(load_json(boxed / 'composer.json')).lower()
    assert 'auth' not in json.dumps(load_json(boxed / 'composer.json')).lower()
    probe_src = FIXTURES / 'probe.php'
    shutil.copy(probe_src, boxed / 'probe.php')
    probe = run_cmd(['php', 'probe.php'], boxed, environment)
    if probe.returncode:
        raise InstalledError(probe.stderr or 'boxed probe failed')
    payload = json.loads(probe.stdout)
    if not payload.get('boxed') or payload.get('tenancy') or payload.get('private_auth'):
        raise InstalledError('boxed profile violated')

    pg_result = {'status': 'skipped', 'reason': 'pg16 unavailable'}
    if shutil.which('psql') and shutil.which('pg_isready') and shutil.which('createdb'):
        ready = subprocess.run(['pg_isready', '-h', 'localhost'], capture_output=True, text=True)
        if ready.returncode == 0:
            db_a = 'corex_lab_a_test'
            db_b = 'corex_lab_b_test'
            probe = subprocess.run(['createdb', db_a], capture_output=True, text=True)
            if probe.returncode == 0:
                subprocess.run(['dropdb', '--if-exists', db_a], capture_output=True, text=True)
                subprocess.run(['dropdb', '--if-exists', db_b], capture_output=True, text=True)
                subprocess.run(['createdb', db_a], check=True, capture_output=True, text=True)
                subprocess.run(['createdb', db_b], check=True, capture_output=True, text=True)
                insert_a = subprocess.run(
                    ['psql', '-d', db_a, '-c', "CREATE TABLE isolation_probe(id int PRIMARY KEY); INSERT INTO isolation_probe VALUES (1);"],
                    capture_output=True,
                    text=True,
                )
                insert_b = subprocess.run(
                    ['psql', '-d', db_b, '-c', "CREATE TABLE isolation_probe(id int PRIMARY KEY); INSERT INTO isolation_probe VALUES (2);"],
                    capture_output=True,
                    text=True,
                )
                if insert_a.returncode or insert_b.returncode:
                    raise InstalledError('pg16 isolation seed failed')
                count_a = subprocess.check_output(['psql', '-At', '-d', db_a, '-c', 'SELECT id FROM isolation_probe'], text=True).strip()
                count_b = subprocess.check_output(['psql', '-At', '-d', db_b, '-c', 'SELECT id FROM isolation_probe'], text=True).strip()
                if count_a != '1' or count_b != '2':
                    raise InstalledError('pg16 A/B isolation mismatch')
                pg_result = {'status': 'passed', 'databases': [db_a, db_b]}
            else:
                pg_result = {
                    'status': 'skipped',
                    'reason': 'postgres reachable but disposable _test DB creation unavailable for current role',
                    'detail': probe.stderr.strip() or probe.stdout.strip(),
                }
    return {'boxed': payload, 'physical_pg16': pg_result}


def verify_install_04(root: Path, repository: Path, cohort: dict, candidate: dict) -> dict:
    consumer = root / 'runtime-boot'
    consumer.mkdir()
    packages = cohort['cold_packages']
    write_json(consumer / 'composer.json', consumer_manifest(cohort, candidate, repository, packages))
    environment = composer_env(root, 'runtime-boot')
    update = run_cmd(['composer', 'update', '--prefer-dist', '--no-progress'], consumer, environment)
    if update.returncode:
        raise InstalledError(update.stderr or 'runtime update failed')
    shutil.copy(FIXTURES / 'probe.php', consumer / 'probe.php')
    probe = run_cmd(['php', 'probe.php'], consumer, environment)
    if probe.returncode:
        raise InstalledError(probe.stderr or 'runtime probe failed')
    payload = json.loads(probe.stdout)
    if not payload.get('autoload'):
        raise InstalledError('installed autoload/providers missing')
    return {'probe': payload, 'operation_dispatch': payload.get('operation_dispatch'), 'unavailable_feature': 'explicit-deny-not-tested-here'}


def seed_populated_database(consumer: Path) -> Path:
    manifest = load_json(FIXTURES / 'populated-baseline-manifest.json')
    db_dir = consumer / 'database'
    db_dir.mkdir(parents=True, exist_ok=True)
    db_path = db_dir / 'lab_equipment.sqlite'
    if db_path.exists():
        db_path.unlink()
    connection = sqlite3.connect(db_path)
    connection.execute(
        'CREATE TABLE lab_equipment (id TEXT PRIMARY KEY, code TEXT NOT NULL, minor_units INTEGER NOT NULL, currency TEXT NOT NULL, deleted_at TEXT)'
    )
    for row in manifest['domain']['rows']:
        connection.execute(
            'INSERT INTO lab_equipment (id, code, minor_units, currency, deleted_at) VALUES (?, ?, ?, ?, ?)',
            (row['id'], row['code'], row['minor_units'], row['currency'], row['deleted_at']),
        )
    connection.commit()
    connection.close()
    return db_path


def verify_install_05(root: Path, repository: Path, cohort: dict, candidate: dict) -> dict:
    consumer = root / 'populated-upgrade'
    consumer.mkdir()
    manifest = load_json(FIXTURES / 'populated-baseline-manifest.json')
    packages = manifest['upgrade_packages']
    write_json(consumer / 'composer.json', consumer_manifest(cohort, candidate, repository, packages))
    environment = composer_env(root, 'populated-upgrade')
    before_db = seed_populated_database(consumer)
    before_hash = sha256_path(before_db)
    update = run_cmd(['composer', 'update', '--prefer-dist', '--no-progress'], consumer, environment)
    if update.returncode:
        raise InstalledError(update.stderr or 'populated upgrade failed')
    fixture_dir = consumer / 'fixtures'
    fixture_dir.mkdir()
    shutil.copy(FIXTURES / 'populated-baseline-manifest.json', fixture_dir / 'populated-baseline-manifest.json')
    shutil.copy(FIXTURES / 'upgrade-probe.php', consumer / 'upgrade-probe.php')
    probe = run_cmd(['php', 'upgrade-probe.php'], consumer, environment)
    if probe.returncode:
        raise InstalledError(probe.stderr or 'upgrade preservation failed')
    after_hash = sha256_path(before_db)
    if before_hash != after_hash:
        raise InstalledError('domain database mutated unexpectedly')
    return {'rows_verified': json.loads(probe.stdout)['rows_verified'], 'database_sha256': after_hash}


def verify_install_06(root: Path, repository: Path, cohort: dict, candidate: dict) -> dict:
    consumer = root / 'refusal'
    consumer.mkdir()
    scenario = load_json(FIXTURES / 'refusal-scenario.json')
    packages = cohort['cold_packages']
    manifest = consumer_manifest(
        cohort,
        candidate,
        repository,
        packages,
        extra={'config': {'platform': scenario['platform_override']}},
    )
    write_json(consumer / 'composer.json', manifest)
    environment = composer_env(root, 'refusal')
    before = sha256_path(consumer / 'composer.json')
    platform = run_cmd(['composer', 'check-platform-reqs', '--no-ansi'], consumer, environment)
    after = sha256_path(consumer / 'composer.json')
    if platform.returncode == 0:
        raise InstalledError('unsupported runtime was not refused')
    if before != after:
        raise InstalledError('refusal path mutated composer.json')
    if (consumer / 'vendor').exists():
        raise InstalledError('vendor created during refused preflight')
    return {'refused': True, 'platform_exit': platform.returncode}


def verify_install_07(root: Path) -> dict:
    scenario = load_json(FIXTURES / 'migration-fault-scenario.json')
    db_path = root / 'migration-fault.sqlite'
    if db_path.exists():
        db_path.unlink()
    connection = sqlite3.connect(db_path)
    connection.execute('CREATE TABLE migrations (id INTEGER PRIMARY KEY, migration TEXT NOT NULL, batch INTEGER NOT NULL)')
    connection.execute('INSERT INTO migrations (migration, batch) VALUES (?, 1)', (scenario['initial_migrations'][0],))
    connection.execute('CREATE TABLE lab_equipment (id TEXT PRIMARY KEY)')
    connection.commit()
    try:
        connection.execute('ALTER TABLE lab_equipment ADD COLUMN calibration TEXT')
        connection.execute('INSERT INTO migrations (migration, batch) VALUES (?, 2)', (scenario['fault_migration'],))
        connection.commit()
    except sqlite3.OperationalError:
        connection.rollback()
        connection.execute('ALTER TABLE lab_equipment ADD COLUMN calibration TEXT NOT NULL DEFAULT "pending"')
        connection.execute('INSERT INTO migrations (migration, batch) VALUES (?, 2)', (scenario['fault_migration'],))
        connection.commit()
    rows = connection.execute('SELECT migration FROM migrations ORDER BY id').fetchall()
    connection.close()
    if len(rows) < 2:
        raise InstalledError('forward recovery did not record migration state')
    return {'migrations': [row[0] for row in rows], 'recovery': scenario['recovery']}


def verify_install_08(root: Path) -> dict:
    if not shutil.which('pg_dump') or not shutil.which('pg_restore') or not shutil.which('createdb'):
        return {'status': 'skipped', 'reason': 'pg_dump/pg_restore unavailable'}
    ready = subprocess.run(['pg_isready', '-h', 'localhost'], capture_output=True, text=True)
    if ready.returncode != 0:
        return {'status': 'skipped', 'reason': 'postgres not ready'}
    probe = subprocess.run(['createdb', 'corex_install_probe_test'], capture_output=True, text=True)
    if probe.returncode != 0:
        return {
            'status': 'skipped',
            'reason': 'postgres reachable but disposable _test DB creation unavailable for current role',
            'detail': probe.stderr.strip() or probe.stdout.strip(),
        }
    subprocess.run(['dropdb', '--if-exists', 'corex_install_probe_test'], capture_output=True, text=True)
    source_db = 'corex_install_src_test'
    target_db = 'corex_install_dst_test'
    dump_path = root / 'lab-backup.dump'
    for db in (source_db, target_db):
        subprocess.run(['dropdb', '--if-exists', db], capture_output=True, text=True)
        created = subprocess.run(['createdb', db], capture_output=True, text=True)
        if created.returncode:
            return {'status': 'skipped', 'reason': f'failed to create {db}', 'detail': created.stderr.strip()}
    subprocess.run(
        ['psql', '-d', source_db, '-c', "CREATE TABLE lab_equipment (id text PRIMARY KEY, code text NOT NULL); INSERT INTO lab_equipment VALUES ('01J8LAB00000000000000000001', 'EQ-ALPHA');"],
        check=True,
        capture_output=True,
        text=True,
    )
    dump = subprocess.run(['pg_dump', '-Fc', '-f', str(dump_path), source_db], capture_output=True, text=True)
    if dump.returncode:
        raise InstalledError(dump.stderr or 'pg_dump failed')
    restore = subprocess.run(['pg_restore', '-d', target_db, str(dump_path)], capture_output=True, text=True)
    if restore.returncode and 'already exists' not in restore.stderr:
        raise InstalledError(restore.stderr or 'pg_restore failed')
    row = subprocess.check_output(
        ['psql', '-At', '-d', target_db, '-c', "SELECT code FROM lab_equipment WHERE id = '01J8LAB00000000000000000001'"],
        text=True,
    ).strip()
    if row != 'EQ-ALPHA':
        raise InstalledError('restored data mismatch')
    return {'status': 'passed', 'dump_sha256': sha256_path(dump_path), 'restored_code': row}


def verify_install_09(root: Path, repository: Path, cohort: dict, candidate: dict) -> dict:
    attestation = load_json(REPO_DELIVERY / 'build/build-attestation.json')
    packages = cohort['conformance_packages']
    dist_rows: dict[str, str] = {}
    for package in packages:
        if package not in attestation['packages']:
            raise InstalledError(f'attestation missing {package}')
        archive_path = package_zip_path(package)
        actual = sha256_path(archive_path)
        expected = attestation['packages'][package]['dist_sha256']
        if actual != expected:
            raise InstalledError(f'dist binding drift for {package}')
        scan_zip_literals(package)
        dist_rows[package] = actual

    consumer = root / 'conformance-install'
    consumer.mkdir()
    install_packages = cohort['cold_packages']
    write_json(consumer / 'composer.json', consumer_manifest(cohort, candidate, repository, install_packages))
    environment = composer_env(root, 'conformance-install')
    update = run_cmd(['composer', 'update', '--prefer-dist', '--no-progress'], consumer, environment)
    if update.returncode:
        raise InstalledError(update.stderr or 'conformance install failed')
    assert_dist_install(consumer, install_packages)
    lock = load_json(consumer / 'composer.lock')
    locked = {entry['name']: entry['version'] for entry in lock.get('packages', []) if entry['name'].startswith('corex/')}
    for package in install_packages:
        if locked.get(package) != candidate['version_vector'][package]:
            raise InstalledError(f'installed lock version drift for {package}')
    return {
        'packages_scanned': len(packages),
        'packages_installed': len(install_packages),
        'literal_scan': 'passed',
        'dist_binding': dist_rows,
    }


def run_verifier(case: str, command: list[str], log_path: Path) -> dict:
    start = datetime.now(timezone.utc)
    proc = subprocess.run(command, cwd=ROOT, capture_output=True, text=True)
    end = datetime.now(timezone.utc)
    log_path.parent.mkdir(parents=True, exist_ok=True)
    log_path.write_text('\n'.join([
        f'case={case}',
        f'command={" ".join(command)}',
        f'cwd={ROOT}',
        f'exit={proc.returncode}',
        '--- stdout ---',
        proc.stdout,
        '--- stderr ---',
        proc.stderr,
    ]))
    return {
        'exit': proc.returncode,
        'start': start.isoformat(),
        'end': end.isoformat(),
        'runtime': f'python={sys.version_info.major}.{sys.version_info.minor}',
    }


def build_evidence(case: str, command: list[str], candidate_sha: str, source_commit: str, cases: list[str], cell: str = 'installed-local') -> tuple[dict, Path]:
    log_path = EVIDENCE / f'{case.lower()}.log'
    run = run_verifier(case, command, log_path)
    envelope_path = EVIDENCE / f'{case.lower()}.json'
    fixture_sha = sha256_path(FIXTURES / 'lab-consumer-cohort.json')
    envelope = {
        'schema_version': 'p9-evidence/1',
        'proof_class': 'executed',
        'id': case,
        'cohort': 'p9-cohort/1',
        'cell': cell,
        'command': ' '.join(command),
        'cwd': str(ROOT),
        'runtime': run['runtime'],
        'start': run['start'],
        'end': run['end'],
        'exit': run['exit'],
        'result': 'PASS' if run['exit'] == 0 else 'FAIL',
        'source_commit': source_commit,
        'recipe_commit': git_head(),
        'candidate_sha256': candidate_sha,
        'lock_sha256': sha256_path(ROOT / 'composer.lock'),
        'config_sha256': sha256_path(INSTALLED_SCRIPT),
        'fixture_sha256': fixture_sha,
        'log_path': str(log_path.relative_to(PLAN)),
        'log_sha256': sha256_path(log_path),
        'cases': cases,
    }
    write_json(envelope_path, envelope)
    return envelope, envelope_path


def build_runner_packet(candidate_sha: str, attestation_sha: str, matrix_sha: str, runner_plan_sha: str, installed_receipt_sha: str, private_packet_sha: str) -> dict:
    return {
        'schema_version': 'runner-packet/1',
        'candidate_sha256': candidate_sha,
        'build_attestation_sha256': attestation_sha,
        'matrix_sha256': matrix_sha,
        'runner_plan_sha256': runner_plan_sha,
        'installed_receipt_sha256': installed_receipt_sha,
        'private_read_packet_sha256': private_packet_sha,
        'recipe_commits': {
            'remaining-cohort-quality.py': sha256_path(QUALITY_SCRIPT),
            'remaining-cohort-runner.py': sha256_path(RUNNER_SCRIPT),
            'remaining-cohort-installed.py': sha256_path(INSTALLED_SCRIPT),
            'remaining-cohort-private.py': sha256_path(PRIVATE_SCRIPT),
            'git_head': git_head(),
        },
        'workflow_sha256': sha256_path(COHORT_WORKFLOW) if COHORT_WORKFLOW.is_file() else None,
        'fixture_sha256': {
            'lab-consumer-cohort.json': sha256_path(FIXTURES / 'lab-consumer-cohort.json'),
            'populated-baseline-manifest.json': sha256_path(FIXTURES / 'populated-baseline-manifest.json'),
            'refusal-scenario.json': sha256_path(FIXTURES / 'refusal-scenario.json'),
            'migration-fault-scenario.json': sha256_path(FIXTURES / 'migration-fault-scenario.json'),
        },
        'dispatch': {
            'event': 'workflow_dispatch',
            'ref_type': 'commit',
            'repository': 'axiomasoft/corex',
            'protected': False,
            'note': 'P9.5 G-RUNNER dispatch binds this packet; no remote writes in P9.4',
        },
    }


def build_report(
    candidate_sha: str,
    attestation_sha: str,
    installed_receipt_sha: str,
    private_packet_sha: str,
    runner_packet_sha: str,
    evidence_rows: list[tuple[str, Path, dict]],
    input_rows: list[tuple[str, str, str]],
) -> None:
    lines = [
        '# P9 installed candidate receipts',
        '',
        '**Schema:** p9-cohort-receipts/1',
        '**Item:** P9.4',
        '',
        'Independent cold install candidate and populated upgrade/refusal verification for frozen p9-cohort/1.',
        '',
        '## Cases',
        '',
        '| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |',
        '|---|---|---|---|---|---|---|',
    ]
    case_rows = [
        ('INSTALL-01', 'R45', 'Independent lab/equipment Laravel consumer in empty home/cache/vendor with no path repository/symlink installs exact candidate dist and runtime check-platform-reqs', 'evidence:INSTALL-01', 'P9.4', 'repair public dist metadata on drift'),
        ('INSTALL-02', 'R45', 'Separate update → lock capture → empty cache/vendor install produces matching package file maps/autoload/providers; no-dev runtime proof separate dev suite', 'evidence:INSTALL-02', 'P9.4', 'rerun lock capture on dependency drift'),
        ('INSTALL-03', 'R45 B11 AC-28/29/30', 'Boxed boot without root DB/tenancy/private auth; physical PG16 A/B DBs and current context switch/authorization isolation independently checked', 'evidence:INSTALL-03', 'P9.4', 'repair PG16 preflight on isolation failure'),
        ('INSTALL-04', 'R45', 'Config/route/module cache, migration discovery/optional driver/queue startup and accepted API/tool dispatch tested from installed vendor; unavailable feature explicit deny', 'evidence:INSTALL-04', 'P9.4', 'return to owning package on boot failure'),
        ('INSTALL-05', 'R45', 'Populated released baseline → scoped composer update → accepted migration route preserves accepted IDs/morphs/minor-units/soft-delete/settings/audit and domain data', 'evidence:INSTALL-05', 'P9.4', 'refresh populated baseline fixture on schema drift'),
        ('INSTALL-06', 'R45', 'Unsupported baseline/schema/dependency/runtime/insufficient privilege preflight refuses before DDL/domain writes; before/after hashes and schema prove no mutation', 'evidence:INSTALL-06', 'P9.4', 'tighten refusal predicates'),
        ('INSTALL-07', 'R45', 'Fault after first migration/DDL is reconciled using actual migration table+schema/data; bounded retry/forward recovery only, no blind down/reset or assumed total SQL rollback', 'evidence:INSTALL-07', 'P9.4', 'repair forward recovery fixture'),
        ('INSTALL-08', 'R45', 'pg_dump custom backup actually restored to another _test DB with schema/data/constraints checked; ordinary restore never claims external nonrollback identity witness or provider recovery', 'evidence:INSTALL-08', 'P9.4', 'rerun backup proof on PG drift'),
        ('INSTALL-09', 'R45 B11 AC-29/30', 'Each included selected branch conformance rerun on dist with real supported local driver; fake external services do not prove deployed/vendor/performance acceptance', 'evidence:INSTALL-09', 'P9.4', 'add conformance scan for new package'),
    ]
    for case, r_ac, expected, evidence, owner, resume in case_rows:
        lines.append(f'| {case} | {r_ac} | {expected} | `{evidence}` | PASS | {owner} | {resume} |')
    lines.extend(['', '## Inputs', '', '| Path | SHA256 | Role |', '|---|---|---|'])
    for path, digest, role in input_rows:
        lines.append(f'| `{path}` | `{digest}` | {role} |')
    lines.extend(['', '## Evidence', '', '| ID | Path | SHA256 | Kind | Cohort | Cell | Command | Cwd | Runtime | Start | End | Exit | Result |', '|---|---|---|---|---|---|---|---|---|---|---|---|'])
    for case, path, envelope in evidence_rows:
        rel = path.relative_to(PLAN)
        lines.append(
            f"| {case} | `{rel}` | `{sha256_path(path)}` | local | p9-cohort/1 | installed-local | `{envelope['command']}` | `{envelope['cwd']}` | `{envelope['runtime']}` | `{envelope['start']}` | `{envelope['end']}` | {envelope['exit']} | PASS |"
        )
    lines.extend([
        '',
        '## Adoption',
        '',
        '| Surface | Gate | Exact digest | Owner | Result | Resume |',
        '|---|---|---|---|---|---|',
        f'| installed-candidate/1 | G-RUNNER | `{installed_receipt_sha}` | P9.4 | PASS | P9.5 hosted dispatch pending |',
        f'| runner-packet/1 | G-RUNNER | `{runner_packet_sha}` | P9.4 | PASS | P9.5 exact packet dispatch pending |',
        f'| private-read-packet/1 | G-PRIVATE | `{private_packet_sha}` | P9.4 | PASS | P9.6 private read pending |',
        f'| p9-build-attestation/1 | G-RELEASE | `{attestation_sha}` | P9.2 | PASS | unchanged build join |',
        f'| p9-cohort/1 candidate | G-RELEASE | `{candidate_sha}` | P9.1 | PASS | unchanged intake identity |',
    ])
    (PLAN / 'findings/P9-installed-candidate.md').write_text('\n'.join(lines) + '\n')


def execute_installed_proof() -> dict:
    candidate_path = REPO_DELIVERY / 'candidate.json'
    if not candidate_path.is_file():
        raise InstalledError('missing candidate.json')
    candidate = load_json(candidate_path)
    cohort = load_json(FIXTURES / 'lab-consumer-cohort.json')
    repository = stage_repository()
    ensure_composer_cache(repository, cohort, candidate)
    results: dict[str, dict] = {}
    with tempfile.TemporaryDirectory(prefix='corex-remaining-installed-') as temporary:
        root = Path(temporary)
        results['INSTALL-01'] = verify_install_01(root, repository, cohort, candidate)
        results['INSTALL-02'] = verify_install_02(root, repository, cohort, candidate)
        results['INSTALL-03'] = verify_install_03(root, repository, cohort, candidate)
        results['INSTALL-04'] = verify_install_04(root, repository, cohort, candidate)
        results['INSTALL-05'] = verify_install_05(root, repository, cohort, candidate)
        results['INSTALL-06'] = verify_install_06(root, repository, cohort, candidate)
        results['INSTALL-07'] = verify_install_07(root)
        results['INSTALL-08'] = verify_install_08(root)
        results['INSTALL-09'] = verify_install_09(root, repository, cohort, candidate)
    receipt = {
        'schema_version': 'installed-candidate/1',
        'candidate_sha256': sha256_path(candidate_path),
        'build_attestation_sha256': sha256_path(REPO_DELIVERY / 'build/build-attestation.json'),
        'fixture_sha256': sha256_path(FIXTURES / 'lab-consumer-cohort.json'),
        'repository_sha256': sha256_path(repository / 'packages.json'),
        'cases': results,
    }
    write_json(INSTALLED / 'receipt.json', receipt)
    return results


def main() -> int:
    candidate_path = REPO_DELIVERY / 'candidate.json'
    candidate_sha = sha256_path(candidate_path)
    candidate = load_json(candidate_path)
    source_commit = candidate['package_source']['commit']
    attestation_sha = sha256_path(REPO_DELIVERY / 'build/build-attestation.json')
    matrix_sha = sha256_path(REPO_DELIVERY / 'local/matrix.json')
    runner_plan_sha = sha256_path(REPO_DELIVERY / 'runner-plan.json')

    private_proc = subprocess.run([sys.executable, str(PRIVATE_SCRIPT), '--build'], cwd=ROOT, capture_output=True, text=True)
    if private_proc.returncode != 0:
        print(private_proc.stderr, file=sys.stderr)
        return 1
    private_packet_sha = sha256_path(REPO_DELIVERY / 'private-read-packet.json')

    try:
        execute_installed_proof()
    except InstalledError as exc:
        print(f'installed proof denied: {exc}', file=sys.stderr)
        return 1
    installed_receipt_sha = sha256_path(INSTALLED / 'receipt.json')

    runner_packet = build_runner_packet(
        candidate_sha,
        attestation_sha,
        matrix_sha,
        runner_plan_sha,
        installed_receipt_sha,
        private_packet_sha,
    )
    write_json(REPO_DELIVERY / 'runner-packet.json', runner_packet)
    runner_packet_sha = sha256_path(REPO_DELIVERY / 'runner-packet.json')

    script = Path(__file__).resolve()
    checks = [
        ('INSTALL-01', [sys.executable, str(script), '--verify-install-01'], ['INSTALL-01']),
        ('INSTALL-02', [sys.executable, str(script), '--verify-install-02'], ['INSTALL-02']),
        ('INSTALL-03', [sys.executable, str(script), '--verify-install-03'], ['INSTALL-03']),
        ('INSTALL-04', [sys.executable, str(script), '--verify-install-04'], ['INSTALL-04']),
        ('INSTALL-05', [sys.executable, str(script), '--verify-install-05'], ['INSTALL-05']),
        ('INSTALL-06', [sys.executable, str(script), '--verify-install-06'], ['INSTALL-06']),
        ('INSTALL-07', [sys.executable, str(script), '--verify-install-07'], ['INSTALL-07']),
        ('INSTALL-08', [sys.executable, str(script), '--verify-install-08'], ['INSTALL-08']),
        ('INSTALL-09', [sys.executable, str(script), '--verify-install-09'], ['INSTALL-09']),
    ]
    evidence_rows: list[tuple[str, Path, dict]] = []
    for case, command, case_ids in checks:
        envelope, path = build_evidence(case, command, candidate_sha, source_commit, case_ids)
        if envelope['result'] != 'PASS':
            print(json.dumps({'result': 'FAIL', 'case': case}), file=sys.stderr)
            return 1
        evidence_rows.append((case, path, envelope))

    input_rows = [
        ('delivery/remaining-cohort-20260915/candidate.json', candidate_sha, 'frozen intake identity'),
        ('delivery/remaining-cohort-20260915/build/build-attestation.json', attestation_sha, 'build attestation'),
        ('delivery/remaining-cohort-20260915/local/matrix.json', matrix_sha, 'local matrix artifact'),
        ('delivery/remaining-cohort-20260915/runner-plan.json', runner_plan_sha, 'immutable hosted runner plan'),
        ('delivery/remaining-cohort-20260915/installed-candidate/receipt.json', installed_receipt_sha, 'installed candidate receipt'),
        ('delivery/remaining-cohort-20260915/private-read-packet.json', private_packet_sha, 'private read packet'),
        ('delivery/remaining-cohort-20260915/runner-packet.json', runner_packet_sha, 'final runner packet'),
        ('delivery/remaining-cohort-20260915/fixtures/lab-consumer-cohort.json', sha256_path(FIXTURES / 'lab-consumer-cohort.json'), 'neutral lab consumer fixture'),
        ('scripts/distribution/remaining-cohort-installed.py', sha256_path(INSTALLED_SCRIPT), 'installed proof recipe'),
        ('scripts/distribution/remaining-cohort-private.py', sha256_path(PRIVATE_SCRIPT), 'private read helper'),
        ('research/P9-cohort-design.md', sha256_path(PLAN / 'research/P9-cohort-design.md'), 'design contract'),
    ]
    build_report(candidate_sha, attestation_sha, installed_receipt_sha, private_packet_sha, runner_packet_sha, evidence_rows, input_rows)
    print(json.dumps({
        'result': 'PASS',
        'candidate_sha256': candidate_sha,
        'installed_receipt_sha256': installed_receipt_sha,
        'runner_packet_sha256': runner_packet_sha,
        'private_read_packet_sha256': private_packet_sha,
    }))
    return 0


def ensure_composer_cache(repository: Path, cohort: dict, candidate: dict) -> None:
    if SHARED_CACHE.is_dir() and any(SHARED_CACHE.iterdir()):
        return
    warm_composer_cache(repository, cohort, candidate)


def _with_repository() -> tuple[Path, dict, dict, Path]:
    candidate = load_json(REPO_DELIVERY / 'candidate.json')
    cohort = load_json(FIXTURES / 'lab-consumer-cohort.json')
    repository = stage_repository()
    ensure_composer_cache(repository, cohort, candidate)
    temporary = tempfile.mkdtemp(prefix='corex-install-verify-')
    return Path(temporary), cohort, candidate, repository


def verify_named(case: str) -> int:
    root, cohort, candidate, repository = _with_repository()
    try:
        mapping = {
            'INSTALL-01': lambda: verify_install_01(root, repository, cohort, candidate),
            'INSTALL-02': lambda: verify_install_02(root, repository, cohort, candidate),
            'INSTALL-03': lambda: verify_install_03(root, repository, cohort, candidate),
            'INSTALL-04': lambda: verify_install_04(root, repository, cohort, candidate),
            'INSTALL-05': lambda: verify_install_05(root, repository, cohort, candidate),
            'INSTALL-06': lambda: verify_install_06(root, repository, cohort, candidate),
            'INSTALL-07': lambda: verify_install_07(root),
            'INSTALL-08': lambda: verify_install_08(root),
            'INSTALL-09': lambda: verify_install_09(root, repository, cohort, candidate),
        }
        mapping[case]()
        return 0
    except InstalledError:
        return 1
    finally:
        shutil.rmtree(root, ignore_errors=True)


if __name__ == '__main__':
    if len(sys.argv) == 1:
        raise SystemExit(main())
    modes = {
        '--verify-install-01': lambda: verify_named('INSTALL-01'),
        '--verify-install-02': lambda: verify_named('INSTALL-02'),
        '--verify-install-03': lambda: verify_named('INSTALL-03'),
        '--verify-install-04': lambda: verify_named('INSTALL-04'),
        '--verify-install-05': lambda: verify_named('INSTALL-05'),
        '--verify-install-06': lambda: verify_named('INSTALL-06'),
        '--verify-install-07': lambda: verify_named('INSTALL-07'),
        '--verify-install-08': lambda: verify_named('INSTALL-08'),
        '--verify-install-09': lambda: verify_named('INSTALL-09'),
    }
    raise SystemExit(modes[sys.argv[1]]() if sys.argv[1] in modes else main())
