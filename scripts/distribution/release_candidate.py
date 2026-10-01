#!/usr/bin/env python3
"""Build and verify the deterministic CoreX 1.0 package repository."""
from __future__ import annotations

import argparse
import copy
import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
import zipfile
from dataclasses import dataclass
from pathlib import Path, PurePosixPath
from typing import Any

ROOT = Path(__file__).resolve().parents[2]
POLICY_PATH = Path(__file__).with_name('release-1.0-policy.json')
FIXED_TIME = (1980, 1, 1, 0, 0, 0)
HEX_40 = re.compile(r'^[0-9a-f]{40}$')
HEX_64 = re.compile(r'^[0-9a-f]{64}$')
MIRROR = re.compile(r'^[a-z0-9_.-]+/[a-z0-9_.-]+$')
PACKAGE = re.compile(r'^[a-z0-9_.-]+/[a-z0-9_.-]+$')
EXACT_VERSION = re.compile(r'^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$')
WINDOWS_ABSOLUTE = re.compile(rb'(?i)(?:^|["\'])\\?[a-z]:[\\/](?:users|workspace|home)[\\/]')


class CandidateError(RuntimeError):
    """The source or candidate violates the frozen release contract."""


@dataclass(frozen=True)
class PackageSource:
    name: str
    path: str
    composer: dict[str, Any]


def sha256_bytes(value: bytes) -> str:
    return hashlib.sha256(value).hexdigest()


def sha256_path(path: Path) -> str:
    return sha256_bytes(path.read_bytes())


def canonical_json(value: object) -> bytes:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()


def pretty_json(value: object) -> bytes:
    return (json.dumps(value, ensure_ascii=False, indent=2, sort_keys=True) + '\n').encode()


def load_json(path: Path) -> dict[str, Any]:
    try:
        value = json.loads(path.read_text(encoding='utf-8'))
    except (OSError, json.JSONDecodeError) as error:
        raise CandidateError(f'invalid JSON {path}: {error}') from error
    if not isinstance(value, dict):
        raise CandidateError(f'JSON object required: {path}')
    return value


def git(root: Path, *args: str, check: bool = True) -> subprocess.CompletedProcess[bytes]:
    process = subprocess.run(('git', *args), cwd=root, capture_output=True)
    if check and process.returncode:
        message = process.stderr.decode('utf-8', 'replace').strip() or 'git command failed'
        raise CandidateError(message)
    return process


def git_bytes(root: Path, *args: str) -> bytes:
    return git(root, *args).stdout


def resolve_commit(root: Path, ref: str) -> str:
    commit = git_bytes(root, 'rev-parse', '--verify', f'{ref}^{{commit}}').decode().strip()
    if not HEX_40.fullmatch(commit):
        raise CandidateError(f'invalid source commit: {commit}')
    return commit


def load_policy(path: Path = POLICY_PATH) -> dict[str, Any]:
    policy = load_json(path)
    if policy.get('schema_version') != 'corex-release-policy/1':
        raise CandidateError('policy schema_version must be corex-release-policy/1')
    if not isinstance(policy.get('include'), list) or 'composer.json' not in policy['include']:
        raise CandidateError('policy include must contain composer.json')
    if not isinstance(policy.get('forbidden_segments'), list):
        raise CandidateError('policy forbidden_segments must be a list')
    channels = policy.get('channels')
    if not isinstance(channels, dict) or not channels:
        raise CandidateError('policy channels must be a non-empty object')
    excluded = policy.get('excluded')
    if excluded != [
        {'path': 'products/flex-crm', 'reason': 'flex-product-consumer'},
        {'path': 'packages/transfer', 'reason': 'tombstone'},
    ]:
        raise CandidateError('policy exclusions must preserve FlexCRM and the import-export tombstone')
    return policy


def git_file(root: Path, ref: str, path: str) -> bytes:
    return git_bytes(root, 'show', f'{ref}:{path}')


def discover_packages(root: Path, ref: str) -> list[PackageSource]:
    names = git_bytes(root, 'ls-tree', '-r', '--name-only', ref, '--', 'packages', 'products').decode().splitlines()
    composer_paths = sorted(
        path for path in names
        if re.fullmatch(r'(?:packages|products)/[^/]+/composer\.json', path)
    )
    packages: list[PackageSource] = []
    seen: set[str] = set()
    for composer_path in composer_paths:
        raw = git_file(root, ref, composer_path)
        try:
            composer = json.loads(raw)
        except json.JSONDecodeError as error:
            raise CandidateError(f'invalid committed metadata: {composer_path}: {error}') from error
        name = composer.get('name')
        path = str(PurePosixPath(composer_path).parent)
        if not isinstance(name, str) or not PACKAGE.fullmatch(name):
            raise CandidateError(f'invalid package name in {composer_path}')
        if name in seen:
            raise CandidateError(f'duplicate package name: {name}')
        seen.add(name)
        packages.append(PackageSource(name=name, path=path, composer=composer))
    return packages


def validate_corex_metadata(package: PackageSource) -> None:
    corex = package.composer.get('extra', {}).get('corex', {})
    if corex.get('schema-version') != 1:
        raise CandidateError(f'{package.name}: extra.corex.schema-version must be 1')
    if corex.get('package-type') not in {'foundation', 'capability', 'integration'}:
        raise CandidateError(f'{package.name}: invalid extra.corex.package-type')
    if corex.get('stability') not in {'experimental', 'stable'}:
        raise CandidateError(f'{package.name}: invalid extra.corex.stability')
    if 'public-api' in corex:
        raise CandidateError(f'{package.name}: namespace public-api metadata is retired; use source Api/Spi')


def derive_cohort(packages: list[PackageSource], policy: dict[str, Any]) -> list[PackageSource]:
    live_corex = sorted((package for package in packages if package.name.startswith('corex/')), key=lambda row: row.name)
    foreign = sorted(package.path for package in packages if not package.name.startswith('corex/'))
    if foreign != ['products/flex-crm']:
        raise CandidateError(f'unexpected non-CoreX package set: {foreign}')
    if any(package.path == 'packages/transfer' for package in packages):
        raise CandidateError('packages/transfer tombstone unexpectedly exists')
    paths = {package.path for package in live_corex}
    policy_paths = set(policy['channels'])
    if paths != policy_paths:
        missing = sorted(paths - policy_paths)
        extra = sorted(policy_paths - paths)
        raise CandidateError(f'channel policy mismatch: missing={missing}, extra={extra}')
    for package in live_corex:
        validate_corex_metadata(package)
        channel = policy['channels'][package.path]
        if channel.get('distribution') not in {'public', 'private'}:
            raise CandidateError(f'{package.name}: unknown distribution channel')
        mirror = channel.get('mirror')
        if not isinstance(mirror, str) or not MIRROR.fullmatch(mirror):
            raise CandidateError(f'{package.name}: unknown mirror')
    return live_corex


def assert_clean_source(root: Path, ref: str, packages: list[PackageSource]) -> None:
    paths = [package.path for package in packages]
    status = git_bytes(root, 'status', '--porcelain=v1', '--untracked-files=all', '--', *paths)
    if status:
        rows = status.decode('utf-8', 'replace').splitlines()
        raise CandidateError(f'package source is dirty relative to {ref}: {rows[0]}')


def included(relative: PurePosixPath, policy: dict[str, Any]) -> bool:
    if any(segment in policy['forbidden_segments'] for segment in relative.parts):
        return False
    value = relative.as_posix()
    return any(value == rule or value.startswith(rule) for rule in policy['include'])


def committed_package_files(
    root: Path,
    ref: str,
    package: PackageSource,
    policy: dict[str, Any],
) -> list[tuple[str, str, str]]:
    rows = git_bytes(root, 'ls-tree', '-r', '-z', ref, '--', package.path).split(b'\0')
    files: list[tuple[str, str, str]] = []
    present_source: set[str] = set()
    for row in rows:
        if not row:
            continue
        metadata, raw_path = row.split(b'\t', 1)
        mode, kind, object_id = metadata.decode().split()
        full_path = raw_path.decode()
        relative = PurePosixPath(full_path).relative_to(package.path)
        if included(relative, policy):
            if kind != 'blob' or mode == '120000':
                raise CandidateError(f'symlink or non-file denied: {full_path}')
            if mode not in {'100644', '100755'}:
                raise CandidateError(f'unsupported file mode: {full_path}')
            files.append((relative.as_posix(), object_id, mode))
            present_source.add(relative.as_posix())
    if 'composer.json' not in present_source:
        raise CandidateError(f'{package.name}: committed composer.json missing')
    for rule in policy.get('required_if_present', []):
        source_has_rule = any(path == rule or path.startswith(rule) for path in present_source)
        exported_has_rule = any(path == rule or path.startswith(rule) for path, _, _ in files)
        if source_has_rule and not exported_has_rule:
            raise CandidateError(f'{package.name}: required export path missing: {rule}')
    return sorted(files)


def release_composer(package: PackageSource, version: str, cohort_names: set[str]) -> bytes:
    composer = copy.deepcopy(package.composer)
    composer.pop('version', None)
    composer.pop('repositories', None)
    version_parts = version.split('.')
    cohort_constraint = f'^{version_parts[0]}.{version_parts[1]}'
    for section in ('require', 'require-dev'):
        requirements = composer.get(section)
        if not isinstance(requirements, dict):
            continue
        for dependency in sorted(requirements):
            if dependency in cohort_names:
                requirements[dependency] = cohort_constraint
            elif isinstance(requirements[dependency], str) and EXACT_VERSION.fullmatch(requirements[dependency]):
                exact = requirements[dependency]
                requirements[dependency] = f'>={exact} <={exact}'
        composer[section] = dict(sorted(requirements.items()))
    if isinstance(composer.get('require-dev'), dict):
        runtime_dependencies = set(composer.get('require', {}))
        composer['require-dev'] = {
            dependency: constraint
            for dependency, constraint in composer['require-dev'].items()
            if dependency not in runtime_dependencies
        }
    return pretty_json(composer)


def tree_map(tree: dict[str, dict[str, Any]]) -> list[dict[str, str]]:
    return [
        {'mode': metadata['mode'], 'path': path, 'sha256': sha256_bytes(metadata['bytes'])}
        for path, metadata in sorted(tree.items())
    ]


def release_tree(
    root: Path,
    ref: str,
    package: PackageSource,
    version: str,
    cohort_names: set[str],
    policy: dict[str, Any],
) -> dict[str, dict[str, Any]]:
    composer = release_composer(package, version, cohort_names)
    tree: dict[str, dict[str, Any]] = {}
    for relative, object_id, mode in committed_package_files(root, ref, package, policy):
        body = composer if relative == 'composer.json' else git_bytes(root, 'cat-file', 'blob', object_id)
        tree[relative] = {'bytes': body, 'mode': mode}
    if 'LICENSE' not in tree:
        tree['LICENSE'] = {'bytes': git_file(root, ref, 'LICENSE'), 'mode': '100644'}
    return tree


def write_archive(path: Path, tree: dict[str, dict[str, Any]]) -> str:
    with zipfile.ZipFile(path, 'w', compression=zipfile.ZIP_STORED) as archive:
        for relative, metadata in sorted(tree.items()):
            info = zipfile.ZipInfo(relative, date_time=FIXED_TIME)
            info.create_system = 3
            info.compress_type = zipfile.ZIP_STORED
            info.external_attr = (0o100755 if metadata['mode'] == '100755' else 0o100644) << 16
            archive.writestr(info, metadata['bytes'])
    return sha256_path(path)


def metadata_package(composer: dict[str, Any], version: str, archive_name: str, archive_sha: str) -> dict[str, Any]:
    metadata = copy.deepcopy(composer)
    metadata['version'] = version
    metadata['dist'] = {
        'type': 'zip',
        'url': f'artifacts/{archive_name}',
        'reference': archive_sha,
        'shasum': '',
    }
    metadata.pop('repositories', None)
    return metadata


def build_repository(
    root: Path,
    ref: str,
    version: str,
    output: Path,
    policy: dict[str, Any],
    *,
    enforce_clean: bool,
) -> dict[str, Any]:
    commit = resolve_commit(root, ref)
    packages = derive_cohort(discover_packages(root, commit), policy)
    if enforce_clean:
        assert_clean_source(root, commit, packages)
    output.mkdir(parents=True, exist_ok=False)
    artifacts = output / 'artifacts'
    artifacts.mkdir()
    cohort_names = {package.name for package in packages}
    manifest_rows: list[dict[str, str]] = []
    repository: dict[str, dict[str, dict[str, Any]]] = {}
    for package in packages:
        tree = release_tree(root, commit, package, version, cohort_names, policy)
        archive_name = f"{package.name.replace('/', '-')}-{version}.zip"
        archive_path = artifacts / archive_name
        archive_sha = write_archive(archive_path, tree)
        composer_bytes = tree['composer.json']['bytes']
        composer = json.loads(composer_bytes)
        channel = policy['channels'][package.path]
        manifest_rows.append({
            'name': package.name,
            'path': package.path,
            'distribution': channel['distribution'],
            'mirror': channel['mirror'],
            'tree_sha256': sha256_bytes(canonical_json(tree_map(tree))),
            'archive_sha256': archive_sha,
            'composer_sha256': sha256_bytes(composer_bytes),
        })
        repository[package.name] = {
            version: metadata_package(composer, version, archive_name, archive_sha),
        }
    candidate = {
        'schema_version': 'corex-release-cohort/1',
        'version': version,
        'source_commit': commit,
        'packages': manifest_rows,
        'excluded': policy['excluded'],
    }
    (output / 'packages.json').write_bytes(pretty_json({'packages': repository}))
    (output / 'candidate.json').write_bytes(pretty_json(candidate))
    return candidate


def output_files(root: Path) -> dict[str, bytes]:
    return {
        path.relative_to(root).as_posix(): path.read_bytes()
        for path in sorted(root.rglob('*'))
        if path.is_file()
    }


def has_workspace_path(data: bytes, root: Path) -> bool:
    candidates = {
        str(root).encode(),
        str(root.resolve()).encode(),
        b'file:///',
    }
    return any(value and value in data for value in candidates) or WINDOWS_ABSOLUTE.search(data) is not None


def validate_archive(
    archive_path: Path,
    package: dict[str, Any],
    version: str,
    cohort_names: set[str],
    policy: dict[str, Any],
    root: Path,
) -> None:
    if sha256_path(archive_path) != package['archive_sha256']:
        raise CandidateError(f"{package['name']}: archive digest mismatch")
    with zipfile.ZipFile(archive_path) as archive:
        names = archive.namelist()
        if len(names) != len(set(names)):
            raise CandidateError(f"{package['name']}: duplicate archive member")
        if 'composer.json' not in names or 'LICENSE' not in names:
            raise CandidateError(f"{package['name']}: required archive member missing")
        tree: dict[str, dict[str, Any]] = {}
        for name in names:
            pure = PurePosixPath(name)
            if pure.is_absolute() or '..' in pure.parts or not name or name.endswith('/'):
                raise CandidateError(f"{package['name']}: unsafe archive member: {name}")
            if name != 'LICENSE' and not included(pure, policy):
                raise CandidateError(f"{package['name']}: forbidden archive member: {name}")
            info = archive.getinfo(name)
            mode = (info.external_attr >> 16) & 0o170000
            if mode not in {0, 0o100000}:
                raise CandidateError(f"{package['name']}: non-regular archive member: {name}")
            body = archive.read(name)
            if has_workspace_path(body, root):
                raise CandidateError(f"{package['name']}: workspace path leaked in {name}")
            executable = bool((info.external_attr >> 16) & 0o111)
            tree[name] = {'bytes': body, 'mode': '100755' if executable else '100644'}
        if sha256_bytes(canonical_json(tree_map(tree))) != package['tree_sha256']:
            raise CandidateError(f"{package['name']}: tree digest mismatch")
        composer_bytes = tree['composer.json']['bytes']
        if sha256_bytes(composer_bytes) != package['composer_sha256']:
            raise CandidateError(f"{package['name']}: composer digest mismatch")
        composer = json.loads(composer_bytes)
        if 'version' in composer:
            raise CandidateError(f"{package['name']}: archive composer.json contains version")
        if 'repositories' in composer:
            raise CandidateError(f"{package['name']}: archive composer.json contains repositories")
        if composer.get('name') != package['name']:
            raise CandidateError(f"{package['name']}: archive identity mismatch")
        for dependency, constraint in composer.get('require', {}).items():
            if dependency.startswith('corex/'):
                if dependency not in cohort_names:
                    raise CandidateError(f"{package['name']}: dependency gap: {dependency}")
                version_parts = version.split('.')
                if constraint != f'^{version_parts[0]}.{version_parts[1]}':
                    raise CandidateError(f"{package['name']}: unpinned cohort dependency: {dependency}")


def validate_candidate_shape(candidate: dict[str, Any], policy: dict[str, Any]) -> None:
    if candidate.get('schema_version') != 'corex-release-cohort/1':
        raise CandidateError('candidate schema_version must be corex-release-cohort/1')
    if candidate.get('version') != '1.0.0':
        raise CandidateError('candidate version must be 1.0.0')
    if not isinstance(candidate.get('source_commit'), str) or not HEX_40.fullmatch(candidate['source_commit']):
        raise CandidateError('candidate source_commit must be 40 lowercase hex characters')
    packages = candidate.get('packages')
    if not isinstance(packages, list) or not packages:
        raise CandidateError('candidate packages must be a non-empty list')
    names = [row.get('name') for row in packages if isinstance(row, dict)]
    paths = [row.get('path') for row in packages if isinstance(row, dict)]
    if len(names) != len(packages) or len(names) != len(set(names)):
        raise CandidateError('candidate package names must be unique')
    if len(paths) != len(set(paths)):
        raise CandidateError('candidate package paths must be unique')
    if names != sorted(names):
        raise CandidateError('candidate packages must be sorted by name')
    if candidate.get('excluded') != policy['excluded']:
        raise CandidateError('candidate exclusions mismatch policy')
    for row in packages:
        expected = policy['channels'].get(row.get('path'))
        if expected is None:
            raise CandidateError(f"unexpected candidate package path: {row.get('path')}")
        if row.get('distribution') != expected['distribution']:
            raise CandidateError(f"{row.get('name')}: wrong distribution channel")
        if row.get('mirror') != expected['mirror']:
            raise CandidateError(f"{row.get('name')}: wrong mirror")
        for field in ('tree_sha256', 'archive_sha256', 'composer_sha256'):
            if not isinstance(row.get(field), str) or not HEX_64.fullmatch(row[field]):
                raise CandidateError(f"{row.get('name')}: invalid {field}")


def run_composer_validation(candidate_dir: Path, candidate: dict[str, Any]) -> None:
    composer_binary = shutil.which('composer')
    if composer_binary is None:
        raise CandidateError('composer executable not found')
    with tempfile.TemporaryDirectory(prefix='corex-release-composer-') as temporary:
        temp = Path(temporary)
        for package in candidate['packages']:
            archive = candidate_dir / 'artifacts' / f"{package['name'].replace('/', '-')}-{candidate['version']}.zip"
            extracted = temp / package['name'].replace('/', '-')
            extracted.mkdir()
            with zipfile.ZipFile(archive) as source:
                source.extractall(extracted)
            process = subprocess.run(
                (composer_binary, 'validate', '--strict', '--no-check-publish', str(extracted / 'composer.json')),
                cwd=extracted,
                capture_output=True,
                text=True,
            )
            if process.returncode:
                detail = process.stderr.strip() or process.stdout.strip()
                raise CandidateError(f"{package['name']}: composer validate failed: {detail}")
        consumer = temp / 'consumer'
        consumer.mkdir()
        manifest = {
            'name': 'corex/release-candidate-probe',
            'repositories': [
                {'type': 'composer', 'url': candidate_dir.resolve().as_uri()},
                {'packagist.org': False},
            ],
            'require': {},
        }
        (consumer / 'composer.json').write_bytes(pretty_json(manifest))
        environment = dict(os.environ)
        environment['COMPOSER_HOME'] = str(temp / 'composer-home')
        for package in candidate['packages']:
            process = subprocess.run(
                (composer_binary, 'show', '--all', package['name'], candidate['version'], '--format=json'),
                cwd=consumer,
                env=environment,
                capture_output=True,
                text=True,
            )
            if process.returncode:
                detail = process.stderr.strip() or process.stdout.strip()
                raise CandidateError(f"{package['name']}: candidate repository resolution failed: {detail}")


def verify_candidate(
    candidate_path: Path,
    root: Path = ROOT,
    policy: dict[str, Any] | None = None,
    *,
    composer_checks: bool = True,
) -> dict[str, Any]:
    policy = policy or load_policy()
    candidate = load_json(candidate_path)
    validate_candidate_shape(candidate, policy)
    commit = resolve_commit(root, candidate['source_commit'])
    discovered = derive_cohort(discover_packages(root, commit), policy)
    assert_clean_source(root, commit, discovered)
    expected_names = {package.name for package in discovered}
    candidate_names = {row['name'] for row in candidate['packages']}
    if candidate_names != expected_names:
        raise CandidateError(
            f'candidate package set mismatch: missing={sorted(expected_names - candidate_names)}, '
            f'extra={sorted(candidate_names - expected_names)}'
        )
    candidate_dir = candidate_path.parent
    expected_archive_names = {
        f"{row['name'].replace('/', '-')}-{candidate['version']}.zip"
        for row in candidate['packages']
    }
    actual_archive_names = {
        path.name for path in (candidate_dir / 'artifacts').glob('*.zip')
    } if (candidate_dir / 'artifacts').is_dir() else set()
    if actual_archive_names != expected_archive_names:
        raise CandidateError(
            f'candidate archive set mismatch: missing={sorted(expected_archive_names - actual_archive_names)}, '
            f'extra={sorted(actual_archive_names - expected_archive_names)}'
        )
    allowed_files = {'candidate.json', 'packages.json'} | {f'artifacts/{name}' for name in expected_archive_names}
    actual_files = set(output_files(candidate_dir))
    if actual_files != allowed_files:
        raise CandidateError(
            f'candidate file set mismatch: missing={sorted(allowed_files - actual_files)}, '
            f'extra={sorted(actual_files - allowed_files)}'
        )
    cohort_names = {row['name'] for row in candidate['packages']}
    for row in candidate['packages']:
        archive_path = candidate_dir / 'artifacts' / f"{row['name'].replace('/', '-')}-{candidate['version']}.zip"
        validate_archive(archive_path, row, candidate['version'], cohort_names, policy, root)
    packages_json = load_json(candidate_dir / 'packages.json')
    repository = packages_json.get('packages')
    if not isinstance(repository, dict) or set(repository) != cohort_names:
        raise CandidateError('Composer repository package set mismatch')
    for name, versions in repository.items():
        if set(versions) != {candidate['version']}:
            raise CandidateError(f'{name}: Composer repository version mismatch')
        metadata = versions[candidate['version']]
        if metadata.get('version') != candidate['version'] or 'repositories' in metadata:
            raise CandidateError(f'{name}: invalid Composer repository metadata')
        dist = metadata.get('dist', {})
        if dist.get('type') != 'zip' or not str(dist.get('url', '')).startswith('artifacts/'):
            raise CandidateError(f'{name}: candidate repository must use relative dist only')
    for relative, body in output_files(candidate_dir).items():
        if has_workspace_path(body, root):
            raise CandidateError(f'workspace path leaked in candidate output: {relative}')
    with tempfile.TemporaryDirectory(prefix='corex-release-verify-a-') as first, tempfile.TemporaryDirectory(
        prefix='corex-release-verify-b-'
    ) as second:
        first_path = Path(first) / 'candidate'
        second_path = Path(second) / 'candidate'
        build_repository(root, commit, candidate['version'], first_path, policy, enforce_clean=False)
        build_repository(root, commit, candidate['version'], second_path, policy, enforce_clean=False)
        first_files = output_files(first_path)
        second_files = output_files(second_path)
        if first_files != second_files:
            raise CandidateError('independent candidate builds are not byte-identical')
        if output_files(candidate_dir) != first_files:
            raise CandidateError('candidate differs from deterministic committed-source rebuild')
    if composer_checks:
        run_composer_validation(candidate_dir, candidate)
    return candidate


def build_command(version: str, output: Path) -> dict[str, Any]:
    if version != '1.0.0':
        raise CandidateError('P17.1 builds version 1.0.0 only')
    policy = load_policy()
    if output.exists() and any(output.iterdir()):
        raise CandidateError(f'output directory must be absent or empty: {output}')
    parent = output.parent.resolve()
    parent.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='.corex-release-build-', dir=parent) as temporary:
        staging = Path(temporary) / 'candidate'
        candidate = build_repository(ROOT, 'HEAD', version, staging, policy, enforce_clean=True)
        if output.exists():
            output.rmdir()
        shutil.move(str(staging), output)
    return candidate


def parser() -> argparse.ArgumentParser:
    command = argparse.ArgumentParser(description=__doc__)
    subcommands = command.add_subparsers(dest='command', required=True)
    build = subcommands.add_parser('build')
    build.add_argument('--version', required=True)
    build.add_argument('--output', type=Path, required=True)
    verify = subcommands.add_parser('verify')
    verify.add_argument('--candidate', type=Path, required=True)
    return command


def main(argv: list[str] | None = None) -> int:
    args = parser().parse_args(argv)
    try:
        if args.command == 'build':
            candidate = build_command(args.version, args.output)
            result = {
                'result': 'PASS',
                'candidate': str(args.output / 'candidate.json'),
                'source_commit': candidate['source_commit'],
                'packages': len(candidate['packages']),
            }
        else:
            candidate = verify_candidate(args.candidate)
            result = {
                'result': 'PASS',
                'candidate': str(args.candidate),
                'source_commit': candidate['source_commit'],
                'packages': len(candidate['packages']),
            }
        print(json.dumps(result, ensure_ascii=False, sort_keys=True))
        return 0
    except CandidateError as error:
        print(f'release candidate denied: {error}', file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
