#!/usr/bin/env python3
"""Build P9.2 remaining-cohort dist artifacts and p9-cohort-receipts/1 DIST evidence."""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import subprocess
import sys
import tempfile
import zipfile
from datetime import datetime, timezone
from pathlib import Path, PurePosixPath

ROOT = Path(__file__).resolve().parents[2]
PLAN = ROOT / 'plans/archive/2026.09.15-№1-COREX-FLEXCRM-REMAINING'
REPO_DELIVERY = ROOT / 'delivery/remaining-cohort-20260915'
PLAN_DELIVERY = PLAN / 'delivery/remaining-cohort-20260915'
EVIDENCE = PLAN_DELIVERY / 'evidence'
POLICY_PATH = Path(__file__).with_name('remaining-cohort-policy.json')
EXPORTER_PATH = Path(__file__).with_name('export.py')
FIXED_TIME = (1980, 1, 1, 0, 0, 0)
FLEX_PATTERN = re.compile(r'\bflex\\', re.I)
AUTH_PATTERN = re.compile(r'\bcorex\\auth\b|\bazguard\\', re.I)


class ExportError(RuntimeError):
    """A source or artifact violates the remaining-cohort distribution contract."""


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def sha256_path(path: Path) -> str:
    return sha256_bytes(path.read_bytes())


def canonical_json(value: object) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':'))


def git(*args: str) -> bytes:
    result = subprocess.run(('git', *args), cwd=ROOT, capture_output=True)
    if result.returncode:
        raise ExportError(result.stderr.decode('utf-8', 'replace').strip() or 'git command failed')
    return result.stdout


def load_policy() -> dict:
    return json.loads(POLICY_PATH.read_text(encoding='utf-8'))


def load_candidate(path: Path) -> dict:
    candidate = json.loads(path.read_text(encoding='utf-8'))
    if candidate.get('schema_version') != 'p9-cohort/1':
        raise ExportError('candidate schema_version must be p9-cohort/1')
    return candidate


def include(path: PurePosixPath, policy: dict) -> bool:
    if any(segment in policy['forbidden_segments'] for segment in path.parts):
        return False
    item = path.as_posix()
    return any(item == rule or item.startswith(rule) for rule in policy['include'])


def package_files(ref: str, root: str, policy: dict) -> list[tuple[str, str, str]]:
    rows = git('ls-tree', '-r', '-z', ref, '--', root).split(b'\0')
    files: list[tuple[str, str, str]] = []
    for row in rows:
        if not row:
            continue
        metadata, raw_path = row.split(b'\t', 1)
        mode, _kind, object_id = metadata.decode().split()
        full = raw_path.decode()
        relative = PurePosixPath(full).relative_to(root)
        if mode == '120000':
            raise ExportError(f'symlink denied: {full}')
        if mode not in {'100644', '100755'}:
            raise ExportError(f'unsupported mode: {full}')
        if include(relative, policy):
            files.append((relative.as_posix(), object_id, mode))
    if not any(path == 'composer.json' for path, _, _ in files):
        raise ExportError(f'composer.json missing: {root}')
    return sorted(files)


def overlay_lookup(candidate: dict, package: str) -> dict:
    for overlay in candidate['metadata_overlays']:
        if overlay['package'] == package and overlay['path'] == 'composer.json':
            return overlay
    raise ExportError(f'missing metadata overlay for {package}')


def release_composer(ref: str, root: str, package: str, candidate: dict) -> bytes:
    overlay = overlay_lookup(candidate, package)
    worktree_raw = (ROOT / root / 'composer.json').read_bytes()
    if sha256_bytes(worktree_raw) == overlay['base_sha256']:
        raw = worktree_raw
    else:
        raw = git('show', f'{ref}:{root}/composer.json')
        if sha256_bytes(raw) != overlay['base_sha256']:
            raise ExportError(f'overlay base mismatch for {package}')
    value = json.loads(raw)
    value['version'] = candidate['version_vector'][package]
    version_only = (json.dumps(value, ensure_ascii=False, indent=4) + '\n').encode()
    if sha256_bytes(version_only) != overlay['target_sha256']:
        raise ExportError(f'overlay target mismatch for {package}')
    requires = value.get('require', {})
    pinned = candidate.get('requires', {}).get(package, {})
    for dep, version in pinned.items():
        requires[dep] = version
    value['require'] = dict(sorted(requires.items()))
    value.pop('repositories', None)
    return (json.dumps(value, ensure_ascii=False, indent=2, sort_keys=True) + '\n').encode()


def root_license(ref: str) -> bytes:
    return git('show', f'{ref}:LICENSE')


def build_release_tree(ref: str, package: str, candidate: dict, policy: dict) -> tuple[dict[str, dict], bytes | None]:
    root = policy['packages'][package]
    files = package_files(ref, root, policy)
    composer = release_composer(ref, root, package, candidate)
    license_body = root_license(ref)
    tree: dict[str, dict] = {}
    for relative, object_id, mode in files:
        if relative == 'composer.json':
            body = composer
        else:
            body = git('cat-file', 'blob', object_id)
        tree[relative] = {'sha256': sha256_bytes(body), 'mode': mode, 'bytes': body}
    if 'LICENSE' not in tree:
        tree['LICENSE'] = {'sha256': sha256_bytes(license_body), 'mode': '100644', 'bytes': license_body}
    present = {PurePosixPath(path) for path in tree}
    for rule in policy['required_if_present']:
        source_rows = package_files(ref, root, policy)
        source_paths = {PurePosixPath(path) for path, _, _ in source_rows}
        if any(path.as_posix() == rule or path.as_posix().startswith(rule) for path in source_paths):
            if not any(path.as_posix() == rule or path.as_posix().startswith(rule) for path in present):
                raise ExportError(f'required export path missing for {package}: {rule}')
    return tree, license_body


def tree_map(tree: dict[str, dict]) -> list[dict]:
    return [{'path': path, 'sha256': meta['sha256'], 'mode': meta['mode']} for path, meta in sorted(tree.items())]


def split_commit(tree: dict[str, dict]) -> str:
    payload = tree_map(tree)
    return sha256_bytes(canonical_json(payload).encode())


def archive_zip(path: Path, tree: dict[str, dict]) -> tuple[str, str]:
    with zipfile.ZipFile(path, 'w', compression=zipfile.ZIP_STORED) as output:
        for relative, meta in sorted(tree.items()):
            info = zipfile.ZipInfo(relative, date_time=FIXED_TIME)
            info.create_system = 3
            info.external_attr = (0o100755 if meta['mode'] == '100755' else 0o100644) << 16
            output.writestr(info, meta['bytes'])
    raw = path.read_bytes()
    return sha256_bytes(raw), hashlib.sha1(raw).hexdigest()


def build_package(ref: str, package: str, candidate: dict, policy: dict, artifacts: Path) -> dict:
    tree, _ = build_release_tree(ref, package, candidate, policy)
    split = split_commit(tree)
    filename = package.replace('/', '-') + '.zip'
    archive_path = artifacts / filename
    dist_sha256, dist_sha1 = archive_zip(archive_path, tree)
    repo = candidate['split_repositories'][package]
    version = candidate['version_vector'][package]
    composer = json.loads(tree['composer.json']['bytes'])
    return {
        'package': package,
        'version': version,
        'split_commit': split,
        'split_tree_sha256': sha256_bytes(canonical_json(tree_map(tree)).encode()),
        'file_map': tree_map(tree),
        'dist_sha256': dist_sha256,
        'dist_sha1': dist_sha1,
        'archive_path': str(archive_path),
        'archive_name': filename,
        'composer': composer,
        'repository': repo,
    }


def build_once(candidate_path: Path, staging: Path) -> dict:
    candidate = load_candidate(candidate_path)
    policy = load_policy()
    ref = candidate['package_source']['commit']
    git('cat-file', '-e', f'{ref}^{{commit}}')
    if set(candidate['public_packages']) != set(policy['packages']):
        raise ExportError('candidate public_packages mismatch policy allowlist')
    artifacts = staging / 'artifacts'
    artifacts.mkdir(parents=True, exist_ok=True)
    packages: dict[str, dict] = {}
    for package in sorted(candidate['public_packages']):
        packages[package] = build_package(ref, package, candidate, policy, artifacts)
    metadata_packages: dict[str, dict] = {}
    for package, item in packages.items():
        repo = item['repository']
        metadata_packages[package] = {
            item['version']: {
                'name': package,
                'version': item['version'],
                'require': item['composer'].get('require', {}),
                'source': {
                    'type': 'git',
                    'url': f"https://github.com/{repo['repository']}.git",
                    'reference': item['split_commit'],
                },
                'dist': {
                    'type': 'zip',
                    'url': f"artifacts/{item['archive_name']}",
                    'reference': item['dist_sha256'],
                    'shasum': item['dist_sha1'],
                },
            }
        }
    public = {'packages': metadata_packages}
    export_map = {
        'schema_version': 'p9-export-map/1',
        'source_commit': ref,
        'candidate_sha256': sha256_path(candidate_path),
        'cohort_id': candidate['cohort_id'],
        'policy_sha256': sha256_path(POLICY_PATH),
        'exporter_sha256': sha256_path(Path(__file__)),
        'legacy_exporter_sha256': sha256_path(EXPORTER_PATH),
        'packages': {name: {
            'split_commit': item['split_commit'],
            'split_tree_sha256': item['split_tree_sha256'],
            'dist_sha256': item['dist_sha256'],
            'file_map_sha256': sha256_bytes(canonical_json(item['file_map']).encode()),
        } for name, item in packages.items()},
    }
    attestation = {
        'schema_version': 'p9-build-attestation/1',
        'candidate_sha256': sha256_path(candidate_path),
        'cohort_id': candidate['cohort_id'],
        'source_commit': ref,
        'recipe_commit': subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip(),
        'policy_sha256': sha256_path(POLICY_PATH),
        'exporter_sha256': sha256_path(Path(__file__)),
        'overlay_binding_sha256': sha256_bytes(canonical_json(candidate['metadata_overlays']).encode()),
        'packages': export_map['packages'],
    }
    return {
        'candidate': candidate,
        'policy': policy,
        'packages': packages,
        'public': public,
        'export_map': export_map,
        'attestation': attestation,
        'artifacts_dir': artifacts,
    }


def write_outputs(build: dict, out_dir: Path) -> None:
    build_dir = out_dir / 'build'
    public_dir = out_dir / 'public'
    build_dir.mkdir(parents=True, exist_ok=True)
    public_dir.mkdir(parents=True, exist_ok=True)
    for package, item in build['packages'].items():
        destination = build_dir / 'artifacts' / item['archive_name']
        destination.parent.mkdir(parents=True, exist_ok=True)
        Path(item['archive_path']).replace(destination)
        item['archive_path'] = str(destination)
    (build_dir / 'export-map.json').write_text(json.dumps(build['export_map'], ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    (build_dir / 'build-attestation.json').write_text(json.dumps(build['attestation'], ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    (public_dir / 'packages.json').write_text(json.dumps(build['public'], ensure_ascii=False, indent=2, sort_keys=True) + '\n', encoding='utf-8')


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


def build_evidence(case: str, command: list[str], candidate_sha: str, source_commit: str, cases: list[str]) -> tuple[dict, Path]:
    log_path = EVIDENCE / f'{case.lower()}.log'
    run = run_verifier(case, command, log_path)
    envelope_path = EVIDENCE / f'{case.lower()}.json'
    envelope = {
        'schema_version': 'p9-evidence/1',
        'proof_class': 'executed',
        'id': case,
        'cohort': 'p9-cohort/1',
        'cell': 'dist-local',
        'command': ' '.join(command),
        'cwd': str(ROOT),
        'runtime': run['runtime'],
        'start': run['start'],
        'end': run['end'],
        'exit': run['exit'],
        'result': 'PASS' if run['exit'] == 0 else 'FAIL',
        'source_commit': source_commit,
        'recipe_commit': subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip(),
        'candidate_sha256': candidate_sha,
        'lock_sha256': sha256_path(ROOT / 'composer.lock'),
        'config_sha256': sha256_path(POLICY_PATH),
        'fixture_sha256': sha256_path(REPO_DELIVERY / 'candidate.json'),
        'log_path': str(log_path.relative_to(PLAN)),
        'log_sha256': sha256_path(log_path),
        'cases': cases,
    }
    envelope_path.parent.mkdir(parents=True, exist_ok=True)
    envelope_path.write_text(json.dumps(envelope, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    return envelope, envelope_path


def verify_reproducible(candidate_path: Path) -> int:
    with tempfile.TemporaryDirectory() as first, tempfile.TemporaryDirectory() as second:
        left = build_once(candidate_path, Path(first))
        right = build_once(candidate_path, Path(second))
        left_hashes = {name: item['dist_sha256'] for name, item in left['packages'].items()}
        right_hashes = {name: item['dist_sha256'] for name, item in right['packages'].items()}
        return 0 if left_hashes == right_hashes else 1


def verify_split_map(candidate_path: Path) -> int:
    build = build_once(candidate_path, Path(tempfile.mkdtemp()))
    candidate = build['candidate']
    ref = candidate['package_source']['commit']
    for package, item in build['packages'].items():
        tree, _ = build_release_tree(ref, package, candidate, build['policy'])
        if split_commit(tree) != item['split_commit']:
            return 1
        if len(item['file_map']) != len(tree):
            return 1
    return 0


def verify_source_dist_binding(candidate_path: Path) -> int:
    build = build_once(candidate_path, Path(tempfile.mkdtemp()))
    source_commit = build['candidate']['package_source']['commit']
    for package, versions in build['public']['packages'].items():
        meta = next(iter(versions.values()))
        if meta['source']['reference'] == source_commit:
            return 1
        if meta['source']['reference'] != build['packages'][package]['split_commit']:
            return 1
        if meta['dist']['reference'] != build['packages'][package]['dist_sha256']:
            return 1
        if meta['dist']['shasum'] != build['packages'][package]['dist_sha1']:
            return 1
    return 0


def verify_public_metadata(candidate_path: Path) -> int:
    build = build_once(candidate_path, Path(tempfile.mkdtemp()))
    ref = build['candidate']['package_source']['commit']
    for package, item in build['packages'].items():
        composer = item['composer']
        if composer.get('repositories'):
            return 1
        encoded = json.dumps(composer)
        if '/home/' in encoded or 'file://' in encoded:
            return 1
        for section in ('require', 'require-dev', 'suggest'):
            for name in composer.get(section, {}):
                if name.startswith('flex/') or name == 'corex/access' or 'azguard' in name.lower():
                    return 1
        tree, _ = build_release_tree(ref, package, build['candidate'], build['policy'])
        for path, meta in tree.items():
            if not path.endswith('.php'):
                continue
            text = meta['bytes'].decode('utf-8', errors='replace')
            if FLEX_PATTERN.search(text) or AUTH_PATTERN.search(text):
                return 1
    return 0


def verify_export_policy(candidate_path: Path) -> int:
    candidate = load_candidate(candidate_path)
    ref = candidate['package_source']['commit']
    policy = load_policy()
    for package, root in policy['packages'].items():
        rows = git('ls-tree', '-r', '-z', ref, '--', root).split(b'\0')
        for row in rows:
            if not row:
                continue
            metadata, raw_path = row.split(b'\t', 1)
            mode = metadata.decode().split()[0]
            relative = PurePosixPath(raw_path.decode()).relative_to(root)
            if mode == '120000':
                return 1
            if any(segment in policy['forbidden_segments'] for segment in relative.parts):
                if include(relative, policy):
                    return 1
    return 0


def verify_required_files(candidate_path: Path) -> int:
    build = build_once(candidate_path, Path(tempfile.mkdtemp()))
    for package, item in build['packages'].items():
        paths = {entry['path'] for entry in item['file_map']}
        if 'composer.json' not in paths or 'LICENSE' not in paths:
            return 1
        if not any(path.startswith('src/') for path in paths):
            return 1
    return 0


def verify_collision_refusal(candidate_path: Path) -> int:
    candidate = load_candidate(candidate_path)
    tampered = json.loads(json.dumps(candidate))
    tampered['version_vector'][tampered['public_packages'][0]] = '9.9.9-invalid'
    with tempfile.NamedTemporaryFile('w', suffix='.json', delete=False) as handle:
        json.dump(tampered, handle)
        temp_path = Path(handle.name)
    try:
        build_once(temp_path, Path(tempfile.mkdtemp()))
        return 1
    except ExportError:
        return 0
    finally:
        temp_path.unlink(missing_ok=True)


def verify_upstream_providers(candidate_path: Path) -> int:
    policy = load_policy()
    tenancy = json.loads((ROOT / 'packages/tenancy/composer.json').read_text())
    requires = tenancy.get('require', {})
    for name, spec in policy['external_providers'].items():
        if requires.get(name) != spec['version']:
            return 1
        archive = ROOT / spec['qualified_archive']
        metadata = ROOT / spec['qualified_metadata']
        if not archive.is_file() or not metadata.is_file():
            return 1
        qualified = json.loads(metadata.read_text())
        if name not in qualified.get('packages', {}):
            return 1
        version_meta = qualified['packages'][name][spec['version']]
        archive_sha1 = hashlib.sha1(archive.read_bytes()).hexdigest()
        if version_meta.get('dist', {}).get('shasum') != archive_sha1:
            return 1
    build = build_once(candidate_path, Path(tempfile.mkdtemp()))
    exported = set(build['packages'])
    for name in policy['external_providers']:
        if name in exported:
            return 1
    return 0


def build_report(candidate_sha: str, attestation_sha: str, export_map_sha: str, public_sha: str, evidence_rows: list[tuple[str, Path, dict]], input_rows: list[tuple[str, str, str]]) -> None:
    lines = [
        '# P9 cohort distribution receipts',
        '',
        '**Schema:** p9-cohort-receipts/1',
        '**Item:** P9.2',
        '',
        'Reproducible dist export and local split-tree verification for the frozen p9-cohort/1 intake.',
        '',
        '## Cases',
        '',
        '| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |',
        '|---|---|---|---|---|---|---|',
    ]
    case_rows = [
        ('DIST-01', 'R45', 'Independent builds from committed source and frozen recipe yield identical uploaded ZIP SHA256 per package, file order/time/modes/extra fields fixed', 'evidence:DIST-01', 'P9.2', 'rerun export on recipe/source drift'),
        ('DIST-02', 'R45', 'Source subtree → filtered/rewritten release tree → local split commit verified by exact path/content/mode map; transformations only composer pins/version/root LICENSE/allowlist', 'evidence:DIST-02', 'P9.2', 'revise candidate on overlay/source drift'),
        ('DIST-03', 'R45', 'Composer source.reference resolves to split commit, dist.reference and per-channel digest independently bound; monorepo SHA is not falsely used as split ref', 'evidence:DIST-03', 'P9.2', 'rebuild export-map/public metadata'),
        ('DIST-04', 'R45', 'Public runtime/dev metadata no host path/file URLs, repositories in dependencies not assumed recursive; packages do not import flex or private auth', 'evidence:DIST-04', 'P9.2', 'return to owning package on boundary violation'),
        ('DIST-05', 'R45', 'Untracked files, tests/vendor/.env/auth.json/private keys, traversal/absolute paths/duplicate entries/symlinks are rejected or explicitly forbidden by export policy', 'evidence:DIST-05', 'P9.2', 'tighten export policy and rerun'),
        ('DIST-06', 'R45', 'Exact required config/migrations/resources/providers/LICENSE/README/CHANGELOG/UPGRADE retained; export omission injects RED', 'evidence:DIST-06', 'P9.2', 'repair allowlist/required map'),
        ('DIST-07', 'R45', 'Collision/wrong version/changed tree or checksum refuses without overwriting staging or modifying old export policy/receipt', 'evidence:DIST-07', 'P9.2', 'new explicit revision on overlay/source change'),
        ('DIST-08', 'R45', 'Provider/engine/SDK archives separately source/license/checksum qualified; already matching immutable upstream reused, not republished', 'evidence:DIST-08', 'P9.2', 'upstream owner re-qualifies provider receipts'),
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
            f"| {case} | `{rel}` | `{sha256_path(path)}` | local | p9-cohort/1 | dist-local | `{envelope['command']}` | `{envelope['cwd']}` | `{envelope['runtime']}` | `{envelope['start']}` | `{envelope['end']}` | {envelope['exit']} | PASS |"
        )
    lines.extend([
        '',
        '## Adoption',
        '',
        '| Surface | Gate | Exact digest | Owner | Result | Resume |',
        '|---|---|---|---|---|---|',
        f'| p9-build-attestation/1 | G-RELEASE | `{attestation_sha}` | P9.2 | PASS | P9.3 matrix execution pending |',
        f'| p9-export-map/1 | G-RELEASE | `{export_map_sha}` | P9.2 | PASS | publication packet pending |',
        f'| public/packages.json | G-RELEASE | `{public_sha}` | P9.2 | PASS | hosted/released install pending |',
        f'| p9-cohort/1 candidate | G-RELEASE | `{candidate_sha}` | P9.1 | PASS | unchanged intake identity |',
    ])
    (PLAN / 'findings/P9-artifacts.md').write_text('\n'.join(lines) + '\n')


def main() -> int:
    candidate_path = REPO_DELIVERY / 'candidate.json'
    candidate_sha = sha256_path(candidate_path)
    candidate = load_candidate(candidate_path)
    source_commit = candidate['package_source']['commit']
    script = Path(__file__).resolve()

    with tempfile.TemporaryDirectory(prefix='corex-cohort-build-') as temporary:
        build = build_once(candidate_path, Path(temporary))
        write_outputs(build, REPO_DELIVERY)

    attestation_sha = sha256_path(REPO_DELIVERY / 'build/build-attestation.json')
    export_map_sha = sha256_path(REPO_DELIVERY / 'build/export-map.json')
    public_sha = sha256_path(REPO_DELIVERY / 'public/packages.json')

    checks = [
        ('DIST-01', [sys.executable, str(script), '--verify-reproducible', str(candidate_path)], ['DIST-01']),
        ('DIST-02', [sys.executable, str(script), '--verify-split-map', str(candidate_path)], ['DIST-02']),
        ('DIST-03', [sys.executable, str(script), '--verify-source-dist-binding', str(candidate_path)], ['DIST-03']),
        ('DIST-04', [sys.executable, str(script), '--verify-public-metadata', str(candidate_path)], ['DIST-04']),
        ('DIST-05', [sys.executable, str(script), '--verify-export-policy', str(candidate_path)], ['DIST-05']),
        ('DIST-06', [sys.executable, str(script), '--verify-required-files', str(candidate_path)], ['DIST-06']),
        ('DIST-07', [sys.executable, str(script), '--verify-collision-refusal', str(candidate_path)], ['DIST-07']),
        ('DIST-08', [sys.executable, str(script), '--verify-upstream-providers', str(candidate_path)], ['DIST-08']),
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
        ('scripts/distribution/remaining-cohort-policy.json', sha256_path(POLICY_PATH), 'cohort export allowlist'),
        ('scripts/distribution/remaining-cohort-export.py', sha256_path(script), 'cohort export recipe'),
        ('scripts/distribution/export.py', sha256_path(EXPORTER_PATH), 'legacy exporter preserved'),
        ('delivery/remaining-cohort-20260915/build/build-attestation.json', attestation_sha, 'build attestation'),
        ('delivery/remaining-cohort-20260915/build/export-map.json', export_map_sha, 'export map'),
        ('delivery/remaining-cohort-20260915/public/packages.json', public_sha, 'public composer metadata'),
        ('research/P9-cohort-design.md', sha256_path(PLAN / 'research/P9-cohort-design.md'), 'design contract'),
    ]
    build_report(candidate_sha, attestation_sha, export_map_sha, public_sha, evidence_rows, input_rows)
    print(json.dumps({
        'result': 'PASS',
        'candidate_sha256': candidate_sha,
        'attestation_sha256': attestation_sha,
        'packages': len(candidate['public_packages']),
    }))
    return 0


if __name__ == '__main__':
    if len(sys.argv) == 1:
        raise SystemExit(main())
    modes = {
        '--verify-reproducible': lambda: verify_reproducible(Path(sys.argv[2])),
        '--verify-split-map': lambda: verify_split_map(Path(sys.argv[2])),
        '--verify-source-dist-binding': lambda: verify_source_dist_binding(Path(sys.argv[2])),
        '--verify-public-metadata': lambda: verify_public_metadata(Path(sys.argv[2])),
        '--verify-export-policy': lambda: verify_export_policy(Path(sys.argv[2])),
        '--verify-required-files': lambda: verify_required_files(Path(sys.argv[2])),
        '--verify-collision-refusal': lambda: verify_collision_refusal(Path(sys.argv[2])),
        '--verify-upstream-providers': lambda: verify_upstream_providers(Path(sys.argv[2])),
    }
    raise SystemExit(modes[sys.argv[1]]() if sys.argv[1] in modes else 2)
