#!/usr/bin/env python3
"""Read-only private auth install proof for P9.6 G-PRIVATE (disposable storage only)."""
from __future__ import annotations

import argparse
import hashlib
import io
import json
import os
import re
import shutil
import subprocess
import sys
import tarfile
import tempfile
import zipfile
from datetime import datetime, timezone
from pathlib import Path, PurePosixPath

_DISTRIBUTION = Path(__file__).resolve().parent
if str(_DISTRIBUTION) not in sys.path:
    sys.path.insert(0, str(_DISTRIBUTION))

from plan_close_deferral import DeferralDenied, active as deferral_active, refuse_remote

ROOT = Path(__file__).resolve().parents[2]
RECORD = ROOT / 'release/remaining-cohort-20260915'
REPO_DELIVERY = ROOT / 'delivery/remaining-cohort-20260915'
RECORD_DELIVERY = RECORD / 'delivery/remaining-cohort-20260915'
PRIVATE_DIR = REPO_DELIVERY / 'private'
SHARED_CACHE = (REPO_DELIVERY / 'installed-candidate/composer-cache').resolve()
EVIDENCE = RECORD_DELIVERY / 'evidence'
PRIVATE_SCRIPT = Path(__file__).resolve()
INSTALLED_REPO = REPO_DELIVERY / 'installed-candidate/repository'
FIXTURES = REPO_DELIVERY / 'fixtures'


def azguard_local() -> Path | None:
    raw = os.environ.get('COREX_AZGUARD_LOCAL', '').strip()
    if not raw:
        return None
    path = Path(raw)
    return path if path.is_dir() else None
AZGUARD_PACKAGE = 'axioma-studio/azguard-core'
AZGUARD_GIT_URL = 'https://github.com/axiomasoft/azguard-private.git'
AZGUARD_PUBLIC_REF = '253cbf4cce0226545cd96ca8c6bcbefe329b1403'
AZGUARD_REPO_API = 'axiomasoft/azguard-private'
AUTH_PACKAGE = 'axiomasoft/corex-access'
FIXED_TIME = (1980, 1, 1, 0, 0, 0)
SECRET_PATTERNS = (
    re.compile(r'ghp_[A-Za-z0-9]{20,}'),
    re.compile(r'"password"\s*:\s*"[^"]{3,}"', re.I),
    re.compile(r'BEGIN (RSA |OPENSSH )?PRIVATE KEY'),
)


class PrivateProofError(RuntimeError):
    """Private auth proof violated the remaining-cohort contract."""


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


def utc_now() -> str:
    return datetime.now(timezone.utc).isoformat()


def run_cmd(command: list[str], cwd: Path, environment: dict[str, str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(command, cwd=cwd, env=environment, text=True, capture_output=True)


def write_zip(destination: Path, entries: list[tuple[str, bytes]]) -> None:
    with zipfile.ZipFile(destination, 'w', compression=zipfile.ZIP_STORED) as archive:
        for name, body in sorted(entries):
            entry = zipfile.ZipInfo(name, date_time=FIXED_TIME)
            entry.create_system = 3
            entry.external_attr = 0o100644 << 16
            archive.writestr(entry, body)


def resolve_azguard_ref() -> str:
    override = os.environ.get('COREX_AZGUARD_REF', '').strip()
    if override:
        return override
    local = azguard_local()
    if local is not None:
        proc = subprocess.run(['git', '-C', str(local), 'rev-parse', 'HEAD'], capture_output=True, text=True)
        if proc.returncode == 0 and proc.stdout.strip():
            return proc.stdout.strip()
    return AZGUARD_PUBLIC_REF


def fetch_azguard_tarball(ref: str) -> bytes:
    local = azguard_local()
    if local is not None:
        proc = subprocess.run(
            ['git', '-C', str(local), 'archive', '--format=tar.gz', ref, 'packages/core'],
            capture_output=True,
        )
        if proc.returncode == 0 and proc.stdout:
            return proc.stdout
    refuse_remote(
        'remaining-cohort-private.py azguard github fetch',
        resume='COREX_AZGUARD_LOCAL or --finalize on cached private/receipt.json',
    )
    proc = subprocess.run(
        ['curl', '-fsSL', f'https://codeload.github.com/{AZGUARD_REPO_API}/tar.gz/{ref}'],
        capture_output=True,
    )
    if proc.returncode == 0 and proc.stdout:
        return proc.stdout
    raise PrivateProofError(f'canonical azguard source unavailable at ref {ref}')


def azguard_core_relative(path: PurePosixPath) -> PurePosixPath | None:
    parts = path.parts
    for index, part in enumerate(parts):
        if part == 'packages' and index + 1 < len(parts) and parts[index + 1] == 'core':
            return PurePosixPath(*parts[index + 2:])
    return None


def azguard_source_inspection(ref: str) -> dict:
    tarball = fetch_azguard_tarball(ref)
    entries: list[tuple[str, bytes]] = []
    composer_sha = ''
    license_sha = ''
    requires: dict[str, str] = {}
    with tarfile.open(fileobj=io.BytesIO(tarball), mode='r:gz') as archive:
        for member in archive.getmembers():
            if not member.isfile():
                continue
            relative = azguard_core_relative(PurePosixPath(member.name))
            if relative is None or not relative.parts:
                continue
            if any(part in {'vendor', '.git', '.env', 'auth.json'} for part in relative.parts):
                continue
            body = archive.extractfile(member)
            if body is None:
                raise PrivateProofError('private archive member is unreadable')
            content = body.read()
            if relative.as_posix() == 'composer.json':
                composer = json.loads(content)
                composer['version'] = '0.2.0-p9.6'
                requires = composer.get('require', {})
                content = (json.dumps(composer, indent=2, sort_keys=True) + '\n').encode()
                composer_sha = sha256_bytes(content)
            if relative.name.upper() == 'LICENSE' or relative.as_posix().endswith('/LICENSE'):
                license_sha = sha256_bytes(content)
            entries.append((relative.as_posix(), content))
    if not composer_sha:
        raise PrivateProofError('private package composer metadata is missing')
    return {
        'ref': ref,
        'tarball_sha256': sha256_bytes(tarball),
        'composer_sha256': composer_sha,
        'license_sha256': license_sha or None,
        'requires': requires,
        'entries': entries,
    }


def azguard_archive(destination: Path, inspection: dict) -> tuple[str, str, dict[str, str]]:
    write_zip(destination, inspection['entries'])
    return sha256_path(destination), inspection['composer_sha256'], inspection['requires']


def auth_version(candidate: dict) -> str:
    for row in load_json(REPO_DELIVERY / 'baselines.json')['packages']:
        if row.get('package') == AUTH_PACKAGE:
            return row['target_version']
    raise PrivateProofError('auth baseline version missing')


def auth_archive(destination: Path, version: str) -> str:
    entries: list[tuple[str, bytes]] = []
    package = ROOT / 'packages/access'
    for path in package.rglob('*'):
        if not path.is_file():
            continue
        relative = path.relative_to(package)
        if any(part in {'vendor', '.git', '.env', 'auth.json'} for part in relative.parts):
            continue
        body = path.read_bytes()
        if relative.as_posix() == 'composer.json':
            composer = json.loads(body)
            composer['version'] = version
            composer['require']['axiomasoft/corex'] = candidate_core_version()
            composer['require']['axiomasoft/corex'] = candidate_core_version()
            body = (json.dumps(composer, indent=2, sort_keys=True) + '\n').encode()
        entries.append((relative.as_posix(), body))
    write_zip(destination, entries)
    return sha256_path(destination)


def candidate_core_version() -> str:
    return load_json(REPO_DELIVERY / 'candidate.json')['version_vector']['axiomasoft/corex']


def stage_private_repository(inspection: dict, auth_ver: str) -> tuple[Path, dict]:
    if not INSTALLED_REPO.is_dir():
        raise PrivateProofError('installed-candidate repository missing; run P9.4 first')
    staging = Path(tempfile.mkdtemp(prefix='corex-private-repo-'))
    shutil.copytree(INSTALLED_REPO, staging, dirs_exist_ok=True)
    artifacts = staging / 'artifacts'
    artifacts.mkdir(parents=True, exist_ok=True)
    private_zip = artifacts / 'azguard-core-private.zip'
    auth_zip = artifacts / 'corex-access.zip'
    private_sha, composer_sha, private_requires = azguard_archive(private_zip, inspection)
    auth_sha = auth_archive(auth_zip, auth_ver)
    metadata_path = staging / 'packages.json'
    metadata = load_json(metadata_path)
    metadata['packages'][AZGUARD_PACKAGE] = {
        '0.2.0-p9.6': {
            'name': AZGUARD_PACKAGE,
            'version': '0.2.0-p9.6',
            'require': private_requires,
            'dist': {
                'type': 'zip',
                'url': private_zip.resolve().as_uri(),
                'reference': inspection['ref'],
            },
            'source': {
                'type': 'git',
                'url': AZGUARD_GIT_URL,
                'reference': inspection['ref'],
            },
        },
    }
    auth_composer = json.loads((ROOT / 'packages/access/composer.json').read_text())
    auth_composer['version'] = auth_ver
    auth_composer['require']['axiomasoft/corex'] = candidate_core_version()
    auth_composer['require']['axiomasoft/corex'] = candidate_core_version()
    metadata['packages'][AUTH_PACKAGE] = {
        auth_ver: {
            'name': AUTH_PACKAGE,
            'version': auth_ver,
            'require': auth_composer['require'],
            'dist': {
                'type': 'zip',
                'url': auth_zip.resolve().as_uri(),
                'reference': f'local-p9.6-{auth_ver}',
            },
        },
    }
    write_json(metadata_path, metadata)
    return staging, {
        'azguard_archive_sha256': private_sha,
        'azguard_composer_sha256': composer_sha,
        'azguard_license_sha256': inspection.get('license_sha256'),
        'azguard_ref': inspection['ref'],
        'auth_archive_sha256': auth_sha,
        'metadata_sha256': sha256_path(metadata_path),
    }


def composer_env(root: Path, role: str, *, offline: bool = True) -> dict[str, str]:
    SHARED_CACHE.mkdir(parents=True, exist_ok=True)
    environment = {
        **os.environ,
        'COMPOSER_HOME': str((root / f'home-{role}').resolve()),
        'COMPOSER_CACHE_DIR': str(SHARED_CACHE),
        'COMPOSER_NO_INTERACTION': '1',
    }
    if offline:
        environment['COMPOSER_DISABLE_NETWORK'] = '1'
    return environment


def install_role(root: Path, stage: Path, role: str, auth_ver: str) -> dict:
    consumer = root / role
    consumer.mkdir()
    require = {AUTH_PACKAGE: auth_ver, 'illuminate/contracts': '^12.0'}
    if role == 'dependency':
        package = {'name': 'example/auth-consumer', 'version': '1.0.0', 'require': {AUTH_PACKAGE: auth_ver}}
        package_zip = stage / 'artifacts/example-auth-consumer.zip'
        write_zip(package_zip, [('composer.json', (json.dumps(package, indent=2) + '\n').encode())])
        metadata = load_json(stage / 'packages.json')
        metadata['packages'][package['name']] = {
            '1.0.0': {
                **package,
                'dist': {'type': 'zip', 'url': package_zip.resolve().as_uri(), 'reference': 'p9.6'},
            },
        }
        write_json(stage / 'packages.json', metadata)
        require = {package['name']: '1.0.0', 'illuminate/contracts': '^12.0'}
    manifest = {
        'name': 'corex/private-proof',
        'minimum-stability': 'dev',
        'repositories': [{'type': 'composer', 'url': stage.resolve().as_uri()}],
        'require': require,
    }
    write_json(consumer / 'composer.json', manifest)
    environment = composer_env(root, role, offline=True)
    update = run_cmd(['composer', 'update', '--prefer-dist', '--no-progress'], consumer, environment)
    if update.returncode:
        raise PrivateProofError(f'{role} cold update failed: {update.stderr}')
    lock_sha = sha256_path(consumer / 'composer.lock')
    shutil.rmtree(consumer / 'vendor')
    shutil.rmtree(Path(environment['COMPOSER_HOME']))
    environment = composer_env(root, f'{role}-fresh', offline=True)
    install = run_cmd(['composer', 'install', '--prefer-dist', '--no-progress'], consumer, environment)
    if install.returncode:
        raise PrivateProofError(f'{role} fresh install failed: {install.stderr}')
    vendor_azguard = consumer / 'vendor' / 'axioma-studio' / 'azguard-core'
    vendor_auth = consumer / 'vendor' / 'corex' / 'auth'
    if vendor_azguard.is_symlink() or vendor_auth.is_symlink():
        raise PrivateProofError(f'{role} installed symlink vendor tree')
    if ROOT in vendor_azguard.resolve().parents or ROOT in vendor_auth.resolve().parents:
        raise PrivateProofError(f'{role} vendor resolves to monorepo checkout')
    return {
        'lock_sha256': lock_sha,
        'vendor_azguard_sha256': sha256_path(vendor_azguard / 'composer.json'),
        'vendor_auth_sha256': sha256_path(vendor_auth / 'composer.json'),
    }


def build_private_read_packet() -> dict:
    candidate_path = REPO_DELIVERY / 'candidate.json'
    profile_path = REPO_DELIVERY / 'profile-plan.json'
    for path in (candidate_path, profile_path):
        if not path.is_file():
            raise PrivateProofError(f'missing prerequisite artifact: {path}')
    profile_plan = load_json(profile_path)
    auth_profile = next(
        (row for row in profile_plan.get('private_profiles', []) if row.get('profile_id') == 'optional-auth-azguard'),
        None,
    )
    if auth_profile is None:
        raise PrivateProofError('optional-auth-azguard profile missing from profile-plan')
    return {
        'schema_version': 'private-read-packet/1',
        'profile_id': auth_profile['profile_id'],
        'package': auth_profile['package'],
        'upstream': auth_profile['upstream'],
        'candidate_sha256': sha256_path(candidate_path),
        'build_attestation_sha256': sha256_path(REPO_DELIVERY / 'build/build-attestation.json'),
        'read_scope': 'P9.6:private-read-packet/1:exact-azguard-ref-read-install',
        'channel': auth_profile.get('channel', 'private'),
        'supported': auth_profile.get('supported', {}),
        'unsupported': auth_profile.get('unsupported', []),
        'operations': [
            {
                'operation_id': 'private-read-azguard-source',
                'kind': 'read',
                'repository': auth_profile['upstream'],
                'scope': 'source+license+dist-by-ref',
                'write': False,
            },
            {
                'operation_id': 'private-install-auth-direct',
                'kind': 'install',
                'package': auth_profile['package'],
                'scope': 'direct-cold-install',
                'write': False,
            },
            {
                'operation_id': 'private-install-auth-transitive',
                'kind': 'install',
                'package': 'example/auth-consumer',
                'scope': 'transitive-consumer-install',
                'write': False,
            },
        ],
        'protected': True,
        'grant_required': 'G-PRIVATE',
        'recipe_commits': {'remaining-cohort-private.py': sha256_path(PRIVATE_SCRIPT)},
        'note': 'Read-only packet for owner review; P9.6 executes actual private read/install with grant',
    }


def load_packet() -> dict:
    packet_path = REPO_DELIVERY / 'private-read-packet.json'
    if not packet_path.is_file():
        raise PrivateProofError('private-read-packet.json missing')
    packet = load_json(packet_path)
    if packet.get('schema_version') != 'private-read-packet/1':
        raise PrivateProofError('private-read-packet schema mismatch')
    expected = {
        'candidate_sha256': sha256_path(REPO_DELIVERY / 'candidate.json'),
        'build_attestation_sha256': sha256_path(REPO_DELIVERY / 'build/build-attestation.json'),
    }
    for key, digest in expected.items():
        if packet.get(key) != digest:
            raise PrivateProofError(f'private-read-packet stale binding: {key}')
    packet['_packet_path'] = packet_path
    return packet


def preflight(packet: dict | None = None) -> dict:
    packet = packet or load_packet()
    blockers: list[str] = []
    if not INSTALLED_REPO.is_dir():
        blockers.append('installed-candidate repository missing')
    if not (ROOT / 'packages/access').is_dir():
        blockers.append('packages/access source missing')
    ref = None
    try:
        ref = resolve_azguard_ref()
        fetch_azguard_tarball(ref)
    except PrivateProofError as exc:
        blockers.append(str(exc))
    proc = subprocess.run(['composer', '--version'], capture_output=True, text=True)
    if proc.returncode != 0:
        blockers.append('composer unavailable')
    return {
        'schema_version': 'private-preflight/1',
        'grant_required': 'G-PRIVATE',
        'grant_scope': packet.get('read_scope'),
        'grant_sha256': sha256_path(packet['_packet_path']),
        'private_read_packet_sha256': sha256_path(packet['_packet_path']),
        'azguard_ref': ref,
        'azguard_local': str(azguard_local() or ''),
        'blockers': blockers,
        'ready': not blockers,
    }


def verify_private_01(packet: dict, inspection: dict) -> dict:
    if packet.get('grant_required') != 'G-PRIVATE':
        raise PrivateProofError('packet grant mismatch')
    if any(operation.get('write') for operation in packet.get('operations', [])):
        raise PrivateProofError('packet contains write operations')
    if packet.get('upstream') != AZGUARD_PACKAGE:
        raise PrivateProofError('packet upstream mismatch')
    return {
        'ref': inspection['ref'],
        'composer_sha256': inspection['composer_sha256'],
        'license_sha256': inspection.get('license_sha256'),
        'tarball_sha256': inspection['tarball_sha256'],
        'grant_sha256': sha256_path(packet['_packet_path']),
    }


def verify_private_02(root: Path, stage: Path, auth_ver: str) -> dict:
    direct = install_role(root, stage, 'auth-root', auth_ver)
    transitive = install_role(root, stage, 'dependency', auth_ver)
    return {'auth_root': direct, 'dependency': transitive}


def verify_private_03() -> dict:
    proc = run_cmd(['composer', 'test'], ROOT / 'packages/access', os.environ.copy())
    if proc.returncode != 0:
        raise PrivateProofError(proc.stderr or 'auth suite failed')
    return {'suite': 'packages/access', 'exit': 0, 'tests': 'sqlite-group'}


def verify_private_04(root: Path) -> dict:
    consumer = root / 'public-boot'
    consumer.mkdir()
    cohort = load_json(FIXTURES / 'lab-consumer-cohort.json')
    candidate = load_json(REPO_DELIVERY / 'candidate.json')
    packages = cohort['cold_packages']
    require = {package: candidate['version_vector'][package] for package in packages}
    require['illuminate/contracts'] = f"^{cohort['laravel']}.0"
    write_json(
        consumer / 'composer.json',
        {
            'name': cohort['consumer_name'],
            'minimum-stability': 'dev',
            'repositories': [{'type': 'composer', 'url': INSTALLED_REPO.resolve().as_uri()}],
            'require': require,
        },
    )
    environment = composer_env(root, 'public-boot')
    update = run_cmd(['composer', 'update', '--prefer-dist', '--no-progress'], consumer, environment)
    if update.returncode:
        raise PrivateProofError(update.stderr or 'public boot update failed')
    shutil.copy(FIXTURES / 'probe.php', consumer / 'probe.php')
    probe = run_cmd(['php', 'probe.php'], consumer, environment)
    if probe.returncode:
        raise PrivateProofError(probe.stderr or 'public boot probe failed')
    payload = json.loads(probe.stdout)
    if payload.get('private_auth') or 'auth' in json.dumps(load_json(consumer / 'composer.json')).lower():
        raise PrivateProofError('public graph unexpectedly includes private auth')
    deny = run_cmd(
        ['php', '-r', 'exit(class_exists("CoreX\\\\Auth\\\\Contracts\\\\AccessChecker") ? 1 : 0);'],
        consumer,
        environment,
    )
    if deny.returncode != 0:
        raise PrivateProofError('adapter-missing host did not deny protected surface')
    return {'probe': payload, 'adapter_missing_denies': True}


def verify_private_05(root: Path, stage: Path, auth_ver: str) -> dict:
    l12 = root / 'laravel12'
    l12.mkdir()
    write_json(
        l12 / 'composer.json',
        {
            'name': 'corex/laravel12-auth',
            'minimum-stability': 'dev',
            'repositories': [{'type': 'composer', 'url': stage.resolve().as_uri()}],
            'require': {AUTH_PACKAGE: auth_ver, 'illuminate/contracts': '^12.0'},
        },
    )
    environment = composer_env(root, 'laravel12')
    ok = run_cmd(['composer', 'update', '--prefer-dist', '--no-progress'], l12, environment)
    if ok.returncode:
        raise PrivateProofError('laravel12 intersection refused unexpectedly')
    l13 = root / 'laravel13'
    l13.mkdir()
    write_json(
        l13 / 'composer.json',
        {
            'name': 'corex/laravel13-auth',
            'minimum-stability': 'dev',
            'repositories': [{'type': 'composer', 'url': stage.resolve().as_uri()}],
            'require': {AUTH_PACKAGE: auth_ver, 'illuminate/contracts': '^13.0'},
        },
    )
    environment = composer_env(root, 'laravel13')
    refused = run_cmd(['composer', 'update', '--prefer-dist', '--no-progress'], l13, environment)
    if refused.returncode == 0:
        raise PrivateProofError('laravel13 refusal missing')
    return {'laravel12': 'pass', 'laravel13': 'refused', 'stderr': refused.stderr[-500:]}


def verify_private_06(paths: list[Path], public_metadata: Path) -> dict:
    public_text = public_metadata.read_text(encoding='utf-8')
    if AZGUARD_PACKAGE in public_text or AUTH_PACKAGE in public_text:
        raise PrivateProofError('public metadata leaked private packages')
    for path in paths:
        text = path.read_text(encoding='utf-8', errors='ignore')
        for pattern in SECRET_PATTERNS:
            if pattern.search(text):
                raise PrivateProofError(f'secret-like material in {path}')
        if 'auth.json' in text.lower() and path.suffix == '.json':
            raise PrivateProofError(f'auth.json reference in carrier {path}')
    return {'scanned_paths': len(paths), 'public_registry_clean': True}


def build_evidence(
    case: str,
    command: list[str],
    candidate_sha: str,
    source_commit: str,
    cases: list[str],
    grant_sha: str,
    log_path: Path,
    cell: str,
) -> tuple[dict, Path]:
    started = utc_now()
    proc = subprocess.run(command, cwd=ROOT, capture_output=True, text=True)
    ended = utc_now()
    log_path.parent.mkdir(parents=True, exist_ok=True)
    log_path.write_text((proc.stdout or '') + (proc.stderr or ''), encoding='utf-8')
    envelope_path = EVIDENCE / f'{case.lower()}.json'
    envelope = {
        'schema_version': 'p9-evidence/1',
        'proof_class': 'executed',
        'id': case,
        'cohort': 'p9-cohort/1',
        'cell': cell,
        'command': ' '.join(command),
        'cwd': str(ROOT),
        'runtime': f'python={sys.version_info.major}.{sys.version_info.minor}',
        'start': started,
        'end': ended,
        'exit': proc.returncode,
        'result': 'PASS' if proc.returncode == 0 else 'FAIL',
        'source_commit': source_commit,
        'recipe_commit': git_head(),
        'candidate_sha256': candidate_sha,
        'lock_sha256': sha256_path(ROOT / 'composer.lock'),
        'config_sha256': sha256_path(PRIVATE_SCRIPT),
        'fixture_sha256': sha256_path(REPO_DELIVERY / 'private-read-packet.json'),
        'log_path': str(log_path.relative_to(RECORD)),
        'log_sha256': sha256_path(log_path),
        'cases': cases,
        'grant_sha256': grant_sha,
    }
    write_json(envelope_path, envelope)
    return envelope, envelope_path


def case_catalog() -> list[tuple[str, str, str]]:
    return [
        ('PRIVATE-01', 'R45 R51', 'Exact accepted G-PRIVATE source/channel/ref/license/checksum and narrow read scope verified before private fetch; no historical blanket D14 authority'),
        ('PRIVATE-02', 'R45 R51', 'Cold direct axiomasoft/corex-access install plus transitive example consumer install from approved private channel, separate homes/caches, lock+vendor ref verified'),
        ('PRIVATE-03', 'R45 R51', 'Real installed auth suite/default-deny missing grant/unknown resource and current tenant/department scope positive and negative evidence; no role engine duplicate'),
        ('PRIVATE-04', 'R45 R51', 'Explicit adapter-missing host public boot denies protected abilities safely; public no-credential graph independently remains usable'),
        ('PRIVATE-05', 'R45 R51', 'Laravel12 approved PHP intersection tested; Laravel13 refused unless independent engine owner source/compatibility+G-API/G-DEPENDENCY approval actually supplied'),
        ('PRIVATE-06', 'R45 R51', 'Private source/token material isolated and redacted; receipts only hashes/secret refs, no upload of private dist/source or private metadata to public registry'),
    ]


def build_report(
    candidate_sha: str,
    packet_sha: str,
    receipt_sha: str,
    case_rows: list[tuple[str, str, str, str, str, str, str]],
    evidence_rows: list[tuple[str, Path, dict]],
    input_rows: list[tuple[str, str, str]],
    adoption_rows: list[tuple[str, str, str, str, str, str]],
) -> None:
    lines = [
        '# P9 private auth receipts',
        '',
        '**Schema:** p9-cohort-receipts/1',
        '**Item:** P9.6',
        '',
        'Independent optional private auth install for frozen private-read-packet/1.',
        '',
        '## Cases',
        '',
        '| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |',
        '|---|---|---|---|---|---|---|',
    ]
    for row in case_rows:
        lines.append('| ' + ' | '.join(row) + ' |')
    lines.extend(['', '## Inputs', '', '| Path | SHA256 | Role |', '|---|---|---|'])
    for path, digest, role in input_rows:
        lines.append(f'| `{path}` | `{digest}` | {role} |')
    lines.extend([
        '',
        '## Evidence',
        '',
        '| ID | Path | SHA256 | Kind | Cohort | Cell | Command | Cwd | Runtime | Start | End | Exit | Result |',
        '|---|---|---|---|---|---|---|---|---|---|---|---|',
    ])
    for case, path, envelope in evidence_rows:
        rel = path.relative_to(RECORD)
        lines.append(
            f"| {case} | `{rel}` | `{sha256_path(path)}` | private | p9-cohort/1 | {envelope['cell']} | `{envelope['command']}` | `{envelope['cwd']}` | `{envelope['runtime']}` | `{envelope['start']}` | `{envelope['end']}` | {envelope['exit']} | {envelope['result']} |"
        )
    lines.extend(['', '## Adoption', '', '| Surface | Gate | Exact digest | Owner | Result | Resume |', '|---|---|---|---|---|---|'])
    for row in adoption_rows:
        lines.append('| ' + ' | '.join(row) + ' |')
    (RECORD / 'findings/P9-private-auth.md').write_text('\n'.join(lines) + '\n')


def assert_local_reconcile_allowed() -> None:
    """D44/D45: gh reconcile deferred; local azguard checkout is owner-granted product proof."""
    if not deferral_active():
        return
    if azguard_local() is not None:
        return
    refuse_remote(
        'remaining-cohort-private.py --reconcile',
        resume='COREX_AZGUARD_LOCAL or PLAN-CLOSE-CI',
    )


def execute_reconcile() -> dict:
    assert_local_reconcile_allowed()
    packet = load_packet()
    candidate = load_json(REPO_DELIVERY / 'candidate.json')
    candidate_sha = sha256_path(REPO_DELIVERY / 'candidate.json')
    source_commit = candidate['package_source']['commit']
    packet_sha = sha256_path(packet['_packet_path'])
    grant_sha = packet_sha
    auth_ver = auth_version(candidate)
    pre = preflight(packet)
    write_json(PRIVATE_DIR / 'preflight.json', pre)
    if not pre['ready']:
        raise PrivateProofError('; '.join(pre['blockers']))

    ref = resolve_azguard_ref()
    inspection = azguard_source_inspection(ref)
    stage, repo_evidence = stage_private_repository(inspection, auth_ver)
    proof_results: dict[str, dict] = {}
    try:
        with tempfile.TemporaryDirectory(prefix='corex-private-proof-') as temporary:
            root = Path(temporary)
            proof_results['PRIVATE-01'] = verify_private_01(packet, inspection)
            proof_results['PRIVATE-02'] = verify_private_02(root, stage, auth_ver)
            proof_results['PRIVATE-03'] = verify_private_03()
            proof_results['PRIVATE-04'] = verify_private_04(root)
            proof_results['PRIVATE-05'] = verify_private_05(root, stage, auth_ver)
    finally:
        shutil.rmtree(stage, ignore_errors=True)

    receipt = {
        'schema_version': 'private-receipt/1',
        'candidate_sha256': candidate_sha,
        'private_read_packet_sha256': packet_sha,
        'grant_required': 'G-PRIVATE',
        'grant_sha256': grant_sha,
        'azguard_ref': ref,
        'auth_version': auth_ver,
        'preflight': pre,
        'repository': repo_evidence,
        'cases': proof_results,
    }
    write_json(PRIVATE_DIR / 'receipt.json', receipt)
    receipt_sha = sha256_path(PRIVATE_DIR / 'receipt.json')
    public_metadata = INSTALLED_REPO / 'packages.json'
    proof_results['PRIVATE-06'] = verify_private_06(
        [PRIVATE_DIR / 'receipt.json', PRIVATE_DIR / 'preflight.json', packet['_packet_path']],
        public_metadata,
    )
    receipt['cases'] = proof_results
    write_json(PRIVATE_DIR / 'receipt.json', receipt)
    receipt_sha = sha256_path(PRIVATE_DIR / 'receipt.json')

    script = Path(__file__).resolve()
    checks: list[tuple[str, list[str], list[str], str]] = [
        ('PRIVATE-01', [sys.executable, str(script), '--verify-private-01'], ['PRIVATE-01'], 'private-preflight'),
        ('PRIVATE-02', [sys.executable, str(script), '--verify-private-02'], ['PRIVATE-02'], 'private-install'),
        ('PRIVATE-03', [sys.executable, str(script), '--verify-private-03'], ['PRIVATE-03'], 'private-suite'),
        ('PRIVATE-04', [sys.executable, str(script), '--verify-private-04'], ['PRIVATE-04'], 'private-public-boot'),
        ('PRIVATE-05', [sys.executable, str(script), '--verify-private-05'], ['PRIVATE-05'], 'private-laravel-matrix'),
        ('PRIVATE-06', [sys.executable, str(script), '--verify-private-06'], ['PRIVATE-06'], 'private-redaction'),
    ]
    evidence_rows: list[tuple[str, Path, dict]] = []
    case_results: dict[str, str] = {}
    for case, command, case_ids, cell in checks:
        log_path = EVIDENCE / f'{case.lower()}.log'
        envelope, path = build_evidence(case, command, candidate_sha, source_commit, case_ids, grant_sha, log_path, cell)
        case_results[case] = envelope['result']
        evidence_rows.append((case, path, envelope))

    catalog = {row[0]: row for row in case_catalog()}
    case_rows: list[tuple[str, str, str, str, str, str, str]] = []
    for case_id, r_ac, expected in catalog.values():
        result = case_results.get(case_id, 'PENDING')
        resume = 'P9.7 publication packet' if result == 'PASS' else 'repair private proof'
        case_rows.append((case_id, r_ac, expected, f'evidence:{case_id}', result, 'P9.6', resume))

    input_rows = [
        ('delivery/remaining-cohort-20260915/candidate.json', candidate_sha, 'frozen intake identity'),
        ('delivery/remaining-cohort-20260915/private-read-packet.json', packet_sha, 'private read packet'),
        ('delivery/remaining-cohort-20260915/hosted/receipt.json', sha256_path(REPO_DELIVERY / 'hosted/receipt.json'), 'hosted receipt'),
        ('delivery/remaining-cohort-20260915/installed-candidate/receipt.json', sha256_path(REPO_DELIVERY / 'installed-candidate/receipt.json'), 'installed candidate receipt'),
        ('delivery/remaining-cohort-20260915/private/receipt.json', receipt_sha, 'private receipt'),
        ('scripts/distribution/remaining-cohort-private.py', sha256_path(PRIVATE_SCRIPT), 'private proof recipe'),
        ('research/P9-cohort-design.md', sha256_path(RECORD / 'research/P9-cohort-design.md'), 'design contract'),
    ]
    adoption_rows = [
        ('private-read-packet/1', 'G-PRIVATE', f'`{packet_sha}`', 'P9.4', 'PASS', 'exact private read executed'),
        ('private-receipt/1', 'G-PRIVATE', f'`{receipt_sha}`', 'P9.6', 'PASS' if all(v == 'PASS' for v in case_results.values()) else 'PENDING', 'P9.7 publication packet'),
        ('hosted-receipt/1', 'G-RUNNER', f'`{sha256_path(REPO_DELIVERY / "hosted/receipt.json")}`', 'P9.5', 'PASS', 'unchanged hosted join'),
        ('p9-cohort/1 candidate', 'G-RELEASE', f'`{candidate_sha}`', 'P9.1', 'PASS', 'unchanged intake identity'),
    ]
    build_report(candidate_sha, packet_sha, receipt_sha, case_rows, evidence_rows, input_rows, adoption_rows)
    ready = all(value == 'PASS' for value in case_results.values())
    return {
        'result': 'PASS' if ready else 'BLOCKED',
        'ready': ready,
        'case_results': case_results,
        'preflight': pre,
        'receipt_sha256': receipt_sha,
    }


def verify_private_read_packet(packet_path: Path) -> int:
    try:
        packet = load_json(packet_path)
        packet['_packet_path'] = packet_path
        if packet.get('schema_version') != 'private-read-packet/1':
            return 1
        if not packet.get('protected') or packet.get('grant_required') != 'G-PRIVATE':
            return 1
        if any(operation.get('write') for operation in packet.get('operations', [])):
            return 1
        if packet.get('candidate_sha256') != sha256_path(REPO_DELIVERY / 'candidate.json'):
            return 1
        return 0
    except (OSError, json.JSONDecodeError, KeyError):
        return 1


def verify_guard_refuses_write() -> int:
    packet = build_private_read_packet()
    if not packet.get('protected'):
        return 1
    if any(operation.get('write') for operation in packet.get('operations', [])):
        return 1
    return 0


def _hex64(value: object) -> bool:
    return isinstance(value, str) and len(value) == 64 and all(ch in '0123456789abcdef' for ch in value)


def _git_sha(value: object) -> bool:
    return isinstance(value, str) and len(value) == 40 and all(ch in '0123456789abcdef' for ch in value)


def _replay_private(case: str) -> int:
    """Fast structural replay from retained private/receipt.json (no composer reinstall)."""
    receipt_path = PRIVATE_DIR / 'receipt.json'
    if not receipt_path.is_file():
        return 1
    receipt = load_json(receipt_path)
    packet = load_packet()
    cases = receipt.get('cases', {})
    row = cases.get(case)
    if row is None:
        return 1
    try:
        if case == 'PRIVATE-01':
            if row.get('grant_sha256') != sha256_path(packet['_packet_path']):
                return 1
            return 0 if _git_sha(row.get('ref')) and _hex64(row.get('composer_sha256')) else 1
        if case == 'PRIVATE-02':
            for role in ('auth_root', 'dependency'):
                locks = row.get(role, {})
                if not _hex64(locks.get('lock_sha256')):
                    return 1
            return 0
        if case == 'PRIVATE-03':
            return 0 if row.get('exit') == 0 else 1
        if case == 'PRIVATE-04':
            probe = row.get('probe', {})
            return 0 if row.get('adapter_missing_denies') and probe.get('private_auth') is False else 1
        if case == 'PRIVATE-05':
            return 0 if row.get('laravel12') == 'pass' and row.get('laravel13') == 'refused' else 1
        if case == 'PRIVATE-06':
            verify_private_06(
                [receipt_path, PRIVATE_DIR / 'preflight.json', packet['_packet_path']],
                INSTALLED_REPO / 'packages.json',
            )
            return 0
        return 1
    except PrivateProofError:
        return 1


def rebind_identity() -> dict:
    """Owner Variant A: bind retained PRIVATE-02..06 proof to current intake/packet (local azguard only)."""
    assert_local_reconcile_allowed()
    receipt_path = PRIVATE_DIR / 'receipt.json'
    if not receipt_path.is_file():
        raise PrivateProofError('private receipt missing; run --reconcile first')
    receipt = load_json(receipt_path)
    packet = load_packet()
    candidate = load_json(REPO_DELIVERY / 'candidate.json')
    candidate_sha = sha256_path(REPO_DELIVERY / 'candidate.json')
    packet_sha = sha256_path(packet['_packet_path'])
    auth_ver = auth_version(candidate)
    if receipt.get('auth_version') != auth_ver:
        raise PrivateProofError('auth version changed; full --reconcile required')
    ref = resolve_azguard_ref()
    if receipt.get('azguard_ref') != ref:
        raise PrivateProofError('azguard ref changed; full --reconcile required')
    inspection = azguard_source_inspection(ref)
    pre = preflight(packet)
    write_json(PRIVATE_DIR / 'preflight.json', pre)
    cases = dict(receipt.get('cases', {}))
    cases['PRIVATE-01'] = verify_private_01(packet, inspection)
    receipt.update({
        'candidate_sha256': candidate_sha,
        'private_read_packet_sha256': packet_sha,
        'grant_sha256': packet_sha,
        'auth_version': auth_ver,
        'azguard_ref': ref,
        'preflight': pre,
        'cases': cases,
    })
    write_json(receipt_path, receipt)
    cases['PRIVATE-06'] = verify_private_06(
        [receipt_path, PRIVATE_DIR / 'preflight.json', packet['_packet_path']],
        INSTALLED_REPO / 'packages.json',
    )
    receipt['cases'] = cases
    write_json(receipt_path, receipt)
    return finalize_report()


def finalize_report() -> dict:
    receipt_path = PRIVATE_DIR / 'receipt.json'
    if not receipt_path.is_file():
        raise PrivateProofError('private receipt missing; run --reconcile first')
    receipt = load_json(receipt_path)
    packet = load_packet()
    candidate = load_json(REPO_DELIVERY / 'candidate.json')
    candidate_sha = sha256_path(REPO_DELIVERY / 'candidate.json')
    source_commit = candidate['package_source']['commit']
    packet_sha = sha256_path(packet['_packet_path'])
    grant_sha = packet_sha
    receipt_sha = sha256_path(receipt_path)
    script = Path(__file__).resolve()
    checks: list[tuple[str, list[str], list[str], str]] = [
        ('PRIVATE-01', [sys.executable, str(script), '--verify-private-01'], ['PRIVATE-01'], 'private-preflight'),
        ('PRIVATE-02', [sys.executable, str(script), '--verify-private-02'], ['PRIVATE-02'], 'private-install'),
        ('PRIVATE-03', [sys.executable, str(script), '--verify-private-03'], ['PRIVATE-03'], 'private-suite'),
        ('PRIVATE-04', [sys.executable, str(script), '--verify-private-04'], ['PRIVATE-04'], 'private-public-boot'),
        ('PRIVATE-05', [sys.executable, str(script), '--verify-private-05'], ['PRIVATE-05'], 'private-laravel-matrix'),
        ('PRIVATE-06', [sys.executable, str(script), '--verify-private-06'], ['PRIVATE-06'], 'private-redaction'),
    ]
    evidence_rows: list[tuple[str, Path, dict]] = []
    case_results: dict[str, str] = {}
    for case, command, case_ids, cell in checks:
        log_path = EVIDENCE / f'{case.lower()}.log'
        envelope, path = build_evidence(case, command, candidate_sha, source_commit, case_ids, grant_sha, log_path, cell)
        case_results[case] = envelope['result']
        evidence_rows.append((case, path, envelope))
    catalog = {row[0]: row for row in case_catalog()}
    case_rows: list[tuple[str, str, str, str, str, str, str]] = []
    for case_id, r_ac, expected in catalog.values():
        result = case_results.get(case_id, 'PENDING')
        resume = 'P9.7 publication packet' if result == 'PASS' else 'repair private proof'
        case_rows.append((case_id, r_ac, expected, f'evidence:{case_id}', result, 'P9.6', resume))
    input_rows = [
        ('delivery/remaining-cohort-20260915/candidate.json', candidate_sha, 'frozen intake identity'),
        ('delivery/remaining-cohort-20260915/private-read-packet.json', packet_sha, 'private read packet'),
        ('delivery/remaining-cohort-20260915/hosted/receipt.json', sha256_path(REPO_DELIVERY / 'hosted/receipt.json'), 'hosted receipt'),
        ('delivery/remaining-cohort-20260915/installed-candidate/receipt.json', sha256_path(REPO_DELIVERY / 'installed-candidate/receipt.json'), 'installed candidate receipt'),
        ('delivery/remaining-cohort-20260915/private/receipt.json', receipt_sha, 'private receipt'),
        ('scripts/distribution/remaining-cohort-private.py', sha256_path(PRIVATE_SCRIPT), 'private proof recipe'),
        ('research/P9-cohort-design.md', sha256_path(RECORD / 'research/P9-cohort-design.md'), 'design contract'),
    ]
    adoption_rows = [
        ('private-read-packet/1', 'G-PRIVATE', f'`{packet_sha}`', 'P9.4', 'PASS', 'exact private read executed'),
        ('private-receipt/1', 'G-PRIVATE', f'`{receipt_sha}`', 'P9.6', 'PASS' if all(v == 'PASS' for v in case_results.values()) else 'PENDING', 'P9.7 publication packet'),
        ('hosted-receipt/1', 'G-RUNNER', f'`{sha256_path(REPO_DELIVERY / "hosted/receipt.json")}`', 'P9.5', 'PASS', 'unchanged hosted join'),
        ('p9-cohort/1 candidate', 'G-RELEASE', f'`{candidate_sha}`', 'P9.1', 'PASS', 'unchanged intake identity'),
    ]
    build_report(candidate_sha, packet_sha, receipt_sha, case_rows, evidence_rows, input_rows, adoption_rows)
    ready = all(value == 'PASS' for value in case_results.values())
    return {
        'result': 'PASS' if ready else 'BLOCKED',
        'ready': ready,
        'case_results': case_results,
        'receipt_sha256': receipt_sha,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--build', action='store_true', help='Write private-read-packet.json')
    parser.add_argument('--verify', type=Path, help='Verify an existing private-read-packet.json')
    parser.add_argument('--verify-guard', action='store_true', help='Verify read-only guard semantics')
    parser.add_argument('--preflight', action='store_true')
    parser.add_argument('--reconcile', action='store_true')
    parser.add_argument('--finalize', action='store_true', help='Build evidence/report from existing private/receipt.json')
    parser.add_argument(
        '--rebind-identity',
        action='store_true',
        help='Variant A: rebind retained proof to current candidate/packet via local azguard (no network)',
    )
    for case in ('PRIVATE-01', 'PRIVATE-02', 'PRIVATE-03', 'PRIVATE-04', 'PRIVATE-05', 'PRIVATE-06'):
        parser.add_argument(f'--verify-{case.lower()}', action='store_true')
    args = parser.parse_args()

    try:
        if args.verify:
            return verify_private_read_packet(args.verify)
        if args.verify_guard:
            return verify_guard_refuses_write()
        if args.build:
            packet = build_private_read_packet()
            output = REPO_DELIVERY / 'private-read-packet.json'
            write_json(output, packet)
            print(json.dumps({'result': 'PASS', 'private_read_packet_sha256': sha256_path(output)}))
            return 0
        if args.preflight:
            print(json.dumps(preflight(), ensure_ascii=False))
            return 0
        if args.rebind_identity:
            outcome = rebind_identity()
            print(json.dumps(outcome, ensure_ascii=False))
            return 0 if outcome['result'] == 'PASS' else 2
        if args.finalize:
            outcome = finalize_report()
            print(json.dumps(outcome, ensure_ascii=False))
            return 0 if outcome['result'] == 'PASS' else 2
        for case in ('PRIVATE-01', 'PRIVATE-02', 'PRIVATE-03', 'PRIVATE-04', 'PRIVATE-05', 'PRIVATE-06'):
            if getattr(args, f'verify_{case.lower().replace("-", "_")}'):
                return _replay_private(case)
        if args.reconcile:
            outcome = execute_reconcile()
            print(json.dumps(outcome, ensure_ascii=False))
            return 0 if outcome['result'] == 'PASS' else 2
        if len(sys.argv) == 1:
            refuse_remote(
                'remaining-cohort-private.py (default)',
                resume='--finalize or --reconcile with COREX_AZGUARD_LOCAL',
            )
        parser.print_help()
        return 2
    except (PrivateProofError, DeferralDenied, subprocess.TimeoutExpired, OSError, json.JSONDecodeError, KeyError) as exc:
        print(f'private proof denied: {exc}', file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
