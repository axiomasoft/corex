#!/usr/bin/env python3
"""Local release qualification. Never publishes; NO-GO never emits a packet.

Run `check` before changing stability or committing a release candidate. `seal`
requires an entirely committed stable source and reruns the qualification matrix.
No old producer receipt is promoted to evidence for a rebuilt candidate.
"""
from __future__ import annotations

import argparse
import hashlib
import importlib.util
import json
import os
import platform
import re
import subprocess
import tempfile
import zipfile
from pathlib import Path

import release_candidate as candidate

ROOT = Path(__file__).resolve().parents[2]
PLAN = ROOT / 'plans/2026.09.19-№1-COREX-PLATFORM-HARDENING'
COHORT = PLAN / 'artifacts/P17/release-cohort.json'
RECEIPT = PLAN / 'findings/P17-release-preflight.json'
PACKET = PLAN / 'artifacts/P17/publication-packet.json'
BAD_OUTPUT = re.compile(r'\b(?:skipped|not-run|infrastructure-error)\b|bc-check: skip|plan-close deferred', re.I)


class NoGo(RuntimeError):
    pass


def digest(value: object) -> str:
    return hashlib.sha256(candidate.canonical_json(value)).hexdigest()


def execute(argv: list[str], root: Path, *, timeout: int = 1800) -> dict:
    """Persist argv/exit/digests, never arbitrary subprocess output or environment."""
    try:
        result = subprocess.run(argv, cwd=root, capture_output=True, timeout=timeout)
        output = result.stdout + result.stderr
        logs = Path(os.environ.get('COREX_RELEASE_LOG_DIR', '/tmp/corex-release-qualification'))
        logs.mkdir(parents=True, exist_ok=True)
        (logs / (digest(argv) + '.log')).write_bytes(output)
        return {'argv': argv, 'exit_code': result.returncode,
                'output_sha256': hashlib.sha256(output).hexdigest(),
                'disallowed_output': bool(BAD_OUTPUT.search(output.decode(errors='replace')))}
    except (OSError, subprocess.TimeoutExpired):
        return {'argv': argv, 'exit_code': None, 'diagnostic': 'unavailable-or-timeout'}


def require_command(argv: list[str], root: Path, checks: list[dict]) -> None:
    row = execute(argv, root)
    checks.append(row)
    if row['exit_code'] != 0 or row.get('disallowed_output'):
        raise NoGo('qualification failed: ' + ' '.join(argv))


def inspect_dod(value: dict, source: str, cohort: dict, *, binding: dict | None = None) -> list[dict]:
    expected = [f'DOD-{index:02d}' for index in range(1, 28)]
    if (value.get('schema_version') != 'corex-dod-result/1'
            or value.get('profile') != 'release'
            or value.get('source_commit') != source
            or value.get('candidate_source_commit') != cohort['source_commit']
            or value.get('candidate_sha256') != (binding['candidate_sha256'] if binding else digest(cohort))
            or binding is not None and value.get('candidate_file_sha256') != binding['file_sha256']
            or [row.get('id') for row in value.get('rows', [])] != expected):
        raise NoGo('DoD result is missing, stale or incomplete')
    return [row for row in value['rows'] if row.get('status') != 'green']


SOURCE_PATHS = ('packages', 'scripts', 'tests', 'api', 'composer.json', 'composer.lock',
                '.github', 'docs', 'CHANGELOG.md', 'phpunit.xml', 'phpstan.neon')


def dirty_source(root: Path) -> bool:
    # Generated plan receipts/packet follow the source commit; production code does not.
    return bool(candidate.git_bytes(root, 'status', '--porcelain', '--untracked-files=all',
                                    '--', *SOURCE_PATHS))


def check(root: Path, cohort_path: Path, checks: list[dict], dod_path: Path) -> dict:
    source = candidate.resolve_commit(root, 'HEAD')
    cohort = candidate.load_json(cohort_path)
    policy = candidate.load_policy(root / 'scripts/distribution/release-1.0-policy.json')
    candidate.validate_candidate_shape(cohort, policy)
    live = candidate.derive_cohort(candidate.discover_packages(root, source), policy)
    if {(row.name, row.path) for row in live} != {(row['name'], row['path']) for row in cohort['packages']}:
        raise NoGo('frozen cohort differs from committed package identities')
    argv = ['python3', 'scripts/dod-1.0.py', 'check', '--profile', 'release',
            '--candidate', str(cohort_path), '--output', str(dod_path)]
    checks.append(execute(argv, root))
    dod = candidate.load_json(dod_path)
    spec = importlib.util.spec_from_file_location('release_dod_owner', root / 'scripts/dod-1.0.py')
    if spec is None or spec.loader is None:
        raise NoGo('DoD owner unavailable')
    dod_owner = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(dod_owner)
    red = inspect_dod(dod, source, cohort, binding=dod_owner.candidate_binding(cohort_path, root))
    manifest = dod_owner.load_manifest(root / 'scripts/dod-1.0.json', root)
    manifest_digest = dod_owner.sha256_bytes(dod_owner.canonical_dumps(manifest).encode())
    if (dod.get('manifest_sha256') != manifest_digest
            or dod.get('environment') != dod_owner.current_environment()
            or checks[-1].get('disallowed_output')
            or not red and checks[-1]['exit_code'] != 0):
        raise NoGo('DoD command, manifest or environment is stale')
    return {'source_commit': source, 'cohort_sha256': digest(cohort),
            'dod_sha256': digest(dod), 'dod_manifest_sha256': candidate.sha256_path(root / 'scripts/dod-1.0.json'),
            'red_assertions': red, 'dod_result': dod,
            'dirty_source': dirty_source(root),
            'checks': checks}


def remote_read(path: str, *, absent: bool = False) -> dict | None:
    result = subprocess.run(['gh', 'api', '--method', 'GET', path], capture_output=True, timeout=60)
    if result.returncode:
        if absent and b'HTTP 404' in result.stderr:
            return None
        # GitHub returns 409 for any ref in a confirmed empty Git repository.
        # A generic conflict is never accepted as absence.
        if absent and '/git/ref/' in path and b'HTTP 409' in result.stderr:
            try:
                response = json.loads(result.stdout)
            except ValueError:
                response = {}
            if isinstance(response, dict) and response.get('message') == 'Git Repository is empty.':
                return None
        raise NoGo('remote state/permission unknown: ' + path)
    return json.loads(result.stdout)


def reconcile(operations: list[dict], read=remote_read) -> list[dict]:
    observations = []
    repositories = {}
    for operation in operations:
        repository = operation['repository']
        if repository not in repositories:
            metadata = read(f'repos/{repository}')
            if (not metadata or metadata.get('full_name') != repository or metadata.get('archived')
                    or metadata.get('disabled') or not metadata.get('permissions', {}).get('push')
                    or metadata.get('private') != (operation['distribution'] == 'private')):
                raise NoGo('remote visibility/permission mismatch: ' + repository)
            repositories[repository] = metadata
        if operation['kind'] == 'release':
            current = read(f'repos/{repository}/releases/tags/1.0.0', absent=True)
            if current is not None:
                assets = current.get('assets', [])
                if (current.get('draft') or current.get('prerelease')
                        or current.get('body') != operation['body']
                        or current.get('name') != operation['title']
                        or len(assets) != 1 or assets[0].get('name') != operation['asset_name']
                        or assets[0].get('digest') != 'sha256:' + operation['archive_sha256']):
                    raise NoGo('remote release collision: ' + repository)
        else:
            current = read(f'repos/{repository}/git/ref/{operation["reference"].removeprefix("refs/")}', absent=True)
            if current is not None and (current.get('object', {}).get('type') != 'commit'
                                       or current.get('object', {}).get('sha') != operation['commit']):
                raise NoGo('immutable reference collision: ' + repository)
        observations.append({'operation_sha256': digest(operation),
                             'state': 'absent' if current is None else 'byte-identical',
                             'remote_sha256': digest(current)})
    return observations


def split_commit(archive: Path, name: str, source: str) -> str:
    with tempfile.TemporaryDirectory(prefix='corex-release-split-') as temporary:
        work = Path(temporary)
        candidate.git(work, 'init', '-q')
        with zipfile.ZipFile(archive) as exported:
            for member in exported.infolist():
                path = work / member.filename
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_bytes(exported.read(member))
                path.chmod(0o755 if (member.external_attr >> 16) & 0o111 else 0o644)
        candidate.git(work, 'add', '.')
        tree = candidate.git_bytes(work, 'write-tree').decode().strip()
        env = {**os.environ, 'GIT_AUTHOR_NAME': 'CoreX Release', 'GIT_COMMITTER_NAME': 'CoreX Release',
               'GIT_AUTHOR_EMAIL': 'release@corex.invalid', 'GIT_COMMITTER_EMAIL': 'release@corex.invalid',
               'GIT_AUTHOR_DATE': '2000-01-01T00:00:00Z', 'GIT_COMMITTER_DATE': '2000-01-01T00:00:00Z'}
        return subprocess.check_output(['git', 'commit-tree', tree, '-m', f'{name} 1.0.0\nSource: {source}'],
                                       cwd=work, env=env, text=True).strip()


def postgres_preflight(root: Path, checks: list[dict]) -> None:
    database = os.environ.get('PGSQL_DATABASE', 'corex_test')
    if not re.fullmatch(r'[a-z0-9_]+_test', database):
        raise NoGo('PostgreSQL target must end in _test')
    require_command(['docker', 'compose', 'exec', '-T', 'pgsql', 'pg_isready', '-d', database], root, checks)
    require_command(['pg_isready', '-h', os.environ.get('PGSQL_HOST', '127.0.0.1'),
                     '-p', os.environ.get('PGSQL_PORT', '5434'), '-d', database], root, checks)
    # The endpoint and defaults are the host-mode package TestCase connection.
    # RefreshDatabase owns migrations; this read only proves the disposable DB exists.
    require_command(['php', '-r',
                     "$db=getenv('PGSQL_DATABASE')?:'corex_test';"
                     "$host=getenv('PGSQL_HOST')?:'127.0.0.1';"
                     "$port=getenv('PGSQL_PORT')?:'5434';"
                     "$pdo=new PDO('pgsql:host='.$host.';port='.$port.';dbname='.$db,"
                     "getenv('PGSQL_USERNAME')?:'corex',getenv('PGSQL_PASSWORD')?:'corex');"
                     "if($pdo->query('select current_database()')->fetchColumn()!==$db){exit(1);}"], root, checks)


def qualify(root: Path, packages: list, checks: list[dict], candidate_dir: Path) -> None:
    postgres_preflight(root, checks)
    # Only invoke owners. Missing package scripts or skipped checks are release failures.
    for package in packages:
        for script in ('lint:check', 'analyse', 'test'):
            require_command(['composer', '--working-dir=' + package.path, script], root, checks)
        if (root / package.path / 'tests/Pg').is_dir():
            if 'test:pg' in package.composer.get('scripts', {}):
                require_command(['composer', '--working-dir=' + package.path, 'test:pg'], root, checks)
            else:
                # These owners' test script already includes their PG suite.
                require_command(['composer', '--working-dir=' + package.path, 'test', '--', '--group=pg'], root, checks)
    for script in ('lint:check', 'analyse', 'test', 'test:pg', 'test:arch', 'package-graph-gate',
                   'naming-gate', 'api-contract', 'bc-check'):
        require_command(['composer', script], root, checks)
    require_command(['php', '-d', 'zend.assertions=1', '-d', 'assert.exception=1',
                     'scripts/bc/test-property-default.php'], root, checks)
    require_command(['php', 'scripts/package-docs-gate.php', '--pristine', '--json'], root, checks)
    require_command(['bash', 'scripts/anti-weakening-gate.sh'], root, checks)
    require_command(['bash', 'scripts/mutation-check.sh', '--all'], root, checks)
    for script in ('verify_extension_fixtures.py', 'verify_api_clients.py', 'verify_async_clients.py',
                   'verify_equipment_consumer.py', 'profile-proof.py'):
        argv = ['python3', 'scripts/distribution/' + script]
        if script in ('verify_extension_fixtures.py', 'verify_equipment_consumer.py', 'profile-proof.py'):
            argv.extend(['--candidate', str(candidate_dir)])
        if script == 'verify_extension_fixtures.py':
            argv.extend(['--receipt', str(root / PLAN.relative_to(ROOT) / 'findings/P17-extension-receipt.json')])
        require_command(argv, root, checks)
    require_command(['python3', 'scripts/distribution/upgrade-proof.py', 'prove',
                     '--candidate', str(candidate_dir)], root, checks)


def seal(root: Path, cohort: dict, state: dict, checks: list[dict], cohort_path: Path = COHORT) -> dict:
    if state['red_assertions'] or state['dirty_source']:
        raise NoGo('red DoD or uncommitted source')
    head = state['source_commit']
    source = cohort['source_commit']
    if candidate.git_bytes(root, 'diff', source, head, '--', *SOURCE_PATHS):
        raise NoGo('candidate source differs from qualification code')
    policy = candidate.load_policy(root / 'scripts/distribution/release-1.0-policy.json')
    packages = candidate.derive_cohort(candidate.discover_packages(root, source), policy)
    if any(row.composer['extra']['corex']['stability'] != 'stable' for row in packages):
        raise NoGo('cohort stability is not committed as stable')
    with tempfile.TemporaryDirectory(prefix='corex-preflight-') as temporary:
        first, second = Path(temporary) / 'first', Path(temporary) / 'second'
        rebuilt = candidate.build_repository(root, source, '1.0.0', first, policy, enforce_clean=True)
        candidate.build_repository(root, source, '1.0.0', second, policy, enforce_clean=True)
        if rebuilt != cohort or candidate.output_files(first) != candidate.output_files(second):
            raise NoGo('candidate rebuild is not byte-identical')
        candidate.verify_candidate(first / 'candidate.json', root, policy, composer_checks=True)
        qualify(root, packages, checks, first)
        final = check(root, cohort_path, checks, root / PLAN.relative_to(ROOT) / 'findings/P17-dod-result.json')
        if final['red_assertions']:
            raise NoGo('DoD is red after qualification')
        state.update(final)
        operations = []
        for row in cohort['packages']:
            archive_name = row['name'].replace('/', '-') + '-1.0.0.zip'
            commit = split_commit(first / 'artifacts' / archive_name, row['name'], source)
            base = {'repository': row['mirror'], 'distribution': row['distribution'], 'commit': commit}
            operations.extend([{**base, 'kind': 'push', 'reference': 'refs/heads/release/1.0.0'},
                               {**base, 'kind': 'tag', 'reference': 'refs/tags/1.0.0'},
                               {**base, 'kind': 'release', 'reference': '1.0.0', 'asset_name': archive_name,
                                'archive_sha256': row['archive_sha256'], 'title': row['name'] + ' 1.0.0',
                                'body': f'Source: {source}\nArchive SHA256: {row["archive_sha256"]}'}])
        remote = candidate.git_bytes(root, 'remote', 'get-url', 'origin').decode().strip()
        match = re.fullmatch(r'(?:https://github.com/|git@github.com:)([\w.-]+/[\w.-]+?)(?:\.git)?', remote)
        if not match:
            raise NoGo('unknown root publication destination')
        root_repository = match[1]
        metadata = remote_read('repos/' + root_repository)
        if not metadata or not isinstance(metadata.get('private'), bool):
            raise NoGo('root visibility unknown')
        operations.append({'repository': root_repository, 'distribution': 'private' if metadata['private'] else 'public',
                           'kind': 'tag', 'reference': 'refs/tags/1.0.0', 'commit': source})
        observations = reconcile(operations)
    if candidate.resolve_commit(root, 'HEAD') != head:
        raise NoGo('source changed during qualification')
    # Matrix producers may update tracked receipts. Never seal those dirty bytes.
    if candidate.git_bytes(root, 'status', '--porcelain', '--untracked-files=all', '--',
                           'packages', 'scripts', 'tests', 'api', 'composer.json', 'composer.lock',
                           '.github', 'docs', 'CHANGELOG.md'):
        raise NoGo('source became dirty during qualification; reconcile producer evidence first')
    return {'schema_version': 'publication-packet/v1', 'source_commit': source, 'version': '1.0.0',
            'cohort_sha256': state['cohort_sha256'], 'candidate_sha256': candidate.sha256_path(cohort_path),
            'dod_sha256': state['dod_sha256'], 'dod_manifest_sha256': state['dod_manifest_sha256'],
            'qualification_sha256': digest(checks), 'operations': operations, 'remote_reads': observations}


def verify_packet(path: Path, expected_digest: str) -> dict:
    value = candidate.load_json(path)
    if (value.get('schema_version') != 'publication-packet/v1'
            or candidate.sha256_path(path) != expected_digest):
        raise NoGo('publication packet digest changed')
    return value


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=('check', 'seal', 'verify'))
    parser.add_argument('--candidate', type=Path, default=COHORT)
    parser.add_argument('--receipt', type=Path, default=RECEIPT)
    parser.add_argument('--packet', type=Path, default=PACKET)
    parser.add_argument('--digest')
    args = parser.parse_args()
    if args.action == 'verify':
        verify_packet(args.packet, args.digest or '')
        return 0
    checks: list[dict] = []
    state: dict | None = None
    receipt = {'schema_version': 'corex-release-preflight/1', 'result': 'NO-GO', 'checks': checks,
               'environment': {'python': platform.python_version(), 'platform': platform.system()},
               'external_writes': [], 'packet_emitted': False}
    try:
        if args.action == 'seal':
            state = {'source_commit': candidate.resolve_commit(ROOT, 'HEAD'),
                     'red_assertions': [], 'dirty_source': dirty_source(ROOT)}
        else:
            with tempfile.TemporaryDirectory(prefix='corex-preflight-dod-') as temporary:
                state = check(ROOT, args.candidate, checks, Path(temporary) / 'dod.json')
        receipt.update(state)
        if state['red_assertions'] or state['dirty_source']:
            raise NoGo('red DoD or uncommitted source')
        if args.action == 'seal':
            packet = seal(ROOT, candidate.load_json(args.candidate), state, checks, args.candidate)
            args.packet.parent.mkdir(parents=True, exist_ok=True)
            args.packet.write_bytes(candidate.pretty_json(packet))
            receipt.update(result='GO', packet_emitted=True, packet_sha256=candidate.sha256_path(args.packet))
        else:
            receipt['result'] = 'READY-FOR-QUALIFICATION'
    except (NoGo, candidate.CandidateError, OSError, ValueError, subprocess.SubprocessError) as error:
        receipt['diagnostic'] = str(error) if isinstance(error, (NoGo, candidate.CandidateError)) else type(error).__name__
    if state is not None:
        receipt.update(state)
    args.receipt.parent.mkdir(parents=True, exist_ok=True)
    args.receipt.write_bytes(candidate.pretty_json(receipt))
    print(json.dumps({'result': receipt['result'], 'packet_sha256': receipt.get('packet_sha256'),
                      'receipt': str(args.receipt)}))
    return 0 if receipt['result'] != 'NO-GO' else 1


if __name__ == '__main__':
    raise SystemExit(main())
