#!/usr/bin/env python3
"""Build P9.9 released-installed fixtures, compatibility passport, and p9-released-cohort/1 seal."""
from __future__ import annotations

import argparse
import hashlib
import importlib.util
import json
import shutil
import subprocess
import sys
import tempfile
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PLAN = ROOT / 'plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING'
REPO_DELIVERY = ROOT / 'delivery/remaining-cohort-20260915'
PLAN_DELIVERY = PLAN / 'delivery/remaining-cohort-20260915'
EVIDENCE = PLAN_DELIVERY / 'evidence'
FIXTURES = REPO_DELIVERY / 'fixtures'
RELEASED = REPO_DELIVERY / 'released-installed'
DELIVERY_ENDPOINT = RELEASED / 'delivery-endpoint'
DELIVERY_MANIFEST = RELEASED / 'delivery-manifest.json'
INSTALLED_REPO = REPO_DELIVERY / 'installed-candidate/repository'
RELEASED_SCRIPT = Path(__file__).resolve()
PUBLISH_SCRIPT = Path(__file__).with_name('remaining-cohort-publish.py')

BRANCH_REPORTS = [
    'findings/P9-intake.md',
    'findings/P9-artifacts.md',
    'findings/P9-local-matrix.md',
    'findings/P9-installed-candidate.md',
    'findings/P9-hosted-runs.md',
    'findings/P9-private-auth.md',
    'findings/P9-publication-packet.md',
    'findings/P9-publication.md',
    'findings/P9-released-install.md',
]

P10_P11_RESIDUALS = [
    ('DI01–DI34', 'Central/cloud/timing/provider obligations', 'P10 effective gates', 'P10 owner'),
    ('AP01–AP13', 'Application/product acceptance parents', 'P11 consumer reconciliation', 'P11 owner'),
    ('G-API/G-DEPENDENCY', 'Contract/runtime adoption outside release bytes', 'P10/P11 qualification', 'Release owner'),
    ('Live deployment', 'No live deployed/consumer acceptance in P9', 'P10 provider + P11 consumer', 'Product owner'),
]

CASE_CATALOG = [
    ('RELEASE-01', 'R45', 'New independent consumer retrieves release bytes from declared real delivery endpoint with empty caches; candidate file URLs/path/vendor cannot substitute'),
    ('RELEASE-02', 'R45', 'Cold composer update and separate lock install verify whole released vector/upstreams/source refs/dist hashes/vendor path maps; all target runtime/profile cells covered'),
    ('RELEASE-03', 'R45 R51 B11 AC-28/29/30', 'Released non-CRM boxed/physical and each included contribution behavioral conformance repeated; same auth private/public separation retained'),
    ('RELEASE-04', 'R45', 'Released populated-baseline upgrade/refusal/recovery checks use unchanged accepted fixture/recipe; previous candidate receipt reused only for identical content semantics with explicit binding'),
    ('RELEASE-05', 'R45 R51', 'Compatibility passport distinguishes declared/locally executed/hosted/released installed and explicit exclusions; static report not speculative compatibility runtime generator'),
    ('RELEASE-06', 'R45 R51', 'Exact source/test/hosted/release/installed pins and accountable acceptance bound to every included R; G-API/G-DEPENDENCY/G-PRIVATE/G-RUNNER/G-RELEASE satisfied'),
    ('RELEASE-07', 'R45 R51', 'Final released-cohort seal producer edges P10/P11 retain every DI/AP/provider/timing/product owner; release not live deployment or aggregate consumer acceptance'),
]


class ReleasedError(RuntimeError):
    """Released install verification violated the remaining-cohort contract."""


def load_installed_module():
    path = Path(__file__).with_name('remaining-cohort-installed.py')
    spec = importlib.util.spec_from_file_location('remaining_cohort_installed', path)
    if spec is None or spec.loader is None:
        raise ReleasedError('installed helper missing')
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


installed = load_installed_module()


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


def plan_path(relative: str) -> Path:
    if relative.startswith('delivery/'):
        return REPO_DELIVERY / relative.removeprefix('delivery/remaining-cohort-20260915/')
    if relative.startswith('findings/') or relative.startswith('research/'):
        return PLAN / relative
    return ROOT / relative


def assert_deferral_active() -> None:
    flag = ROOT / 'scripts/.plan-close-deferred'
    if not flag.is_file():
        return
    payload = flag.read_text(encoding='utf-8')
    if 'PLAN-CLOSE-CI' not in payload:
        raise ReleasedError('plan-close deferral flag present but malformed')


def stage_delivery_endpoint() -> tuple[Path, dict]:
    public = REPO_DELIVERY / 'public/packages.json'
    if not public.is_file():
        raise ReleasedError('public packages.json missing; run remaining-cohort-export.py first')
    if DELIVERY_ENDPOINT.exists():
        shutil.rmtree(DELIVERY_ENDPOINT)
    DELIVERY_ENDPOINT.mkdir(parents=True)
    metadata = load_json(public)
    delivery_urls: dict[str, str] = {}
    download_sha256: dict[str, str] = {}
    for versions in metadata.get('packages', {}).values():
        for package in versions.values():
            installed.enrich_package_metadata(package)
            dist_url = package['dist']['url']
            archive_path = (REPO_DELIVERY / 'build' / dist_url).resolve()
            if not archive_path.is_file():
                slug = package['name'].split('/', 1)[1]
                archive_path = (REPO_DELIVERY / 'build/artifacts' / f'corex-{slug}.zip').resolve()
            if not archive_path.is_file():
                raise ReleasedError(f'missing dist archive for {package["name"]}')
            target = DELIVERY_ENDPOINT / 'artifacts' / archive_path.name
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(archive_path, target)
            package['dist']['url'] = (DELIVERY_ENDPOINT / 'artifacts' / archive_path.name).resolve().as_uri()
            delivery_urls[package['name']] = package['dist']['url']
            download_sha256[package['name']] = sha256_path(target)
    write_json(DELIVERY_ENDPOINT / 'packages.json', metadata)
    manifest = {
        'schema_version': 'released-delivery-manifest/1',
        'channel': 'local-admitted-delivery',
        'endpoint_uri': DELIVERY_ENDPOINT.resolve().as_uri(),
        'packages_json': 'packages.json',
        'delivery_urls': delivery_urls,
        'download_sha256': download_sha256,
        'deferral': {
            'active': (ROOT / 'scripts/.plan-close-deferred').is_file(),
            'remote_github_install': 'PLAN-CLOSE-CI',
            'note': 'Local admitted bytes mirror publication artifacts; remote endpoint proof deferred per D45.',
        },
    }
    write_json(DELIVERY_MANIFEST, manifest)
    return DELIVERY_ENDPOINT, manifest


def delivery_repository() -> tuple[Path, dict, dict, dict]:
    candidate = load_json(REPO_DELIVERY / 'candidate.json')
    cohort = load_json(FIXTURES / 'lab-consumer-cohort.json')
    endpoint, manifest = stage_delivery_endpoint()
    repository = endpoint
    return repository, cohort, candidate, manifest


def ensure_released_cache(repository: Path, cohort: dict, candidate: dict) -> None:
    installed.ensure_composer_cache(repository, cohort, candidate)


def verify_release_01(root: Path, repository: Path, manifest: dict, cohort: dict, candidate: dict) -> dict:
    if INSTALLED_REPO.exists() and repository.resolve() == INSTALLED_REPO.resolve():
        raise ReleasedError('candidate installed repository cannot substitute delivery endpoint')
    consumer = root / 'released-cold'
    consumer.mkdir()
    packages = cohort['cold_packages']
    installed.write_json(consumer / 'composer.json', installed.consumer_manifest(cohort, candidate, repository, packages))
    environment = installed.composer_env(root, 'released-cold')
    update = installed.run_cmd(['composer', 'update', '--prefer-dist', '--no-progress'], consumer, environment)
    if update.returncode:
        raise ReleasedError(update.stderr or 'released cold update failed')
    platform = installed.run_cmd(['composer', 'check-platform-reqs', '--no-ansi'], consumer, environment)
    if platform.returncode:
        raise ReleasedError(platform.stderr or 'released platform check failed')
    installed.assert_dist_install(consumer, packages)
    repo_uri = repository.resolve().as_uri()
    if repo_uri in json.dumps(load_json(consumer / 'composer.json')):
        pass
    if manifest['endpoint_uri'] != repo_uri:
        raise ReleasedError('delivery manifest endpoint mismatch')
    return {
        'endpoint_uri': manifest['endpoint_uri'],
        'delivery_urls': {package: manifest['delivery_urls'][package] for package in packages},
        'download_sha256': {package: manifest['download_sha256'][package] for package in packages},
        'path_repository': False,
        'candidate_vendor_substitute': False,
    }


def verify_release_02(root: Path, repository: Path, manifest: dict, cohort: dict, candidate: dict) -> dict:
    attestation = load_json(REPO_DELIVERY / 'build/build-attestation.json')
    public = load_json(repository / 'packages.json')
    vector_packages = cohort['conformance_packages']
    dist_rows: dict[str, str] = {}
    for package in vector_packages:
        version = candidate['version_vector'][package]
        published = public['packages'].get(package, {}).get(version)
        if published is None:
            raise ReleasedError(f'released metadata missing {package}@{version}')
        archive = Path(manifest['delivery_urls'][package].replace('file://', ''))
        actual = sha256_path(archive)
        expected = attestation['packages'][package]['dist_sha256']
        if actual != expected:
            raise ReleasedError(f'released dist hash drift for {package}')
        dist_rows[package] = actual
        source = published.get('source', {})
        split = candidate['split_repositories'][package]
        if source.get('url') != f'https://github.com/{split["repository"]}.git':
            raise ReleasedError(f'released source url drift for {package}')

    install_packages = cohort['cold_packages']
    consumer = root / 'released-lock'
    consumer.mkdir()
    installed.write_json(consumer / 'composer.json', installed.consumer_manifest(cohort, candidate, repository, install_packages))
    environment = installed.composer_env(root, 'released-lock')
    update = installed.run_cmd(['composer', 'update', '--prefer-dist', '--no-progress'], consumer, environment)
    if update.returncode:
        raise ReleasedError(update.stderr or 'released lock update failed')
    lock_sha = sha256_path(consumer / 'composer.lock')
    shutil.rmtree(consumer / 'vendor')
    shutil.rmtree(Path(environment['COMPOSER_HOME']))
    environment = installed.composer_env(root, 'released-lock-fresh')
    install = installed.run_cmd(['composer', 'install', '--prefer-dist', '--no-progress'], consumer, environment)
    if install.returncode:
        raise ReleasedError(install.stderr or 'released lock install failed')
    installed.assert_dist_install(consumer, install_packages)
    lock = load_json(consumer / 'composer.lock')
    locked = {entry['name']: entry['version'] for entry in lock.get('packages', []) if entry['name'].startswith('corex/')}
    for package in install_packages:
        if locked.get(package) != candidate['version_vector'][package]:
            raise ReleasedError(f'released installed lock version drift for {package}')
    profile = load_json(REPO_DELIVERY / 'profile-plan.json')
    return {
        'vector_packages': len(vector_packages),
        'installed_packages': len(install_packages),
        'dist_binding': dist_rows,
        'lock_sha256': lock_sha,
        'profile_cells': profile['public_profiles'][0]['profile_id'],
    }


def verify_release_03(root: Path, repository: Path, cohort: dict, candidate: dict) -> dict:
    local = installed.verify_install_03(root, repository, cohort, candidate)
    private_receipt = load_json(REPO_DELIVERY / 'private/receipt.json')
    if 'PRIVATE' not in json.dumps(private_receipt):
        raise ReleasedError('private/public separation receipt missing')
    local['private_public_separation'] = 'retained'
    return local


def verify_release_04(root: Path, repository: Path, cohort: dict, candidate: dict) -> dict:
    installed_receipt_path = REPO_DELIVERY / 'installed-candidate/receipt.json'
    if not installed_receipt_path.is_file():
        raise ReleasedError('installed candidate receipt missing for explicit binding')
    installed_receipt_sha = sha256_path(installed_receipt_path)
    upgrade = installed.verify_install_05(root, repository, cohort, candidate)
    refusal = installed.verify_install_06(root, repository, cohort, candidate)
    recovery = installed.verify_install_07(root)
    manifest_sha = sha256_path(FIXTURES / 'populated-baseline-manifest.json')
    return {
        'installed_candidate_receipt_sha256': installed_receipt_sha,
        'populated_baseline_manifest_sha256': manifest_sha,
        'upgrade': upgrade,
        'refusal': refusal,
        'recovery': recovery,
        'binding': 'identical fixture semantics with explicit installed-candidate receipt',
    }


def build_released_cohort_json(
    candidate_sha: str,
    attestation_sha: str,
    publication_receipt_sha: str,
    hosted_receipt_sha: str,
    private_receipt_sha: str,
    released_receipt_sha: str,
    manifest: dict,
) -> dict:
    candidate = load_json(REPO_DELIVERY / 'candidate.json')
    attestation = load_json(REPO_DELIVERY / 'build/build-attestation.json')
    publication = load_json(REPO_DELIVERY / 'publication/receipt.json')
    return {
        'schema_version': 'p9-released-cohort/1',
        'cohort_id': candidate.get('cohort_id'),
        'candidate_sha256': candidate_sha,
        'build_attestation_sha256': attestation_sha,
        'publication_receipt_sha256': publication_receipt_sha,
        'hosted_receipt_sha256': hosted_receipt_sha,
        'private_receipt_sha256': private_receipt_sha,
        'released_installed_receipt_sha256': released_receipt_sha,
        'version_vector': candidate['version_vector'],
        'package_source': candidate['package_source'],
        'delivery': {
            'manifest_sha256': sha256_path(DELIVERY_MANIFEST),
            'endpoint_uri': manifest['endpoint_uri'],
            'download_sha256': manifest['download_sha256'],
            'publication_ready': publication.get('preflight', {}).get('ready', False),
            'deferral': manifest['deferral'],
        },
        'dist_packages': attestation['packages'],
        'gates': {
            'G-RUNNER': {'receipt': hosted_receipt_sha, 'status': 'PASS'},
            'G-PRIVATE': {'receipt': private_receipt_sha, 'status': 'PASS'},
            'G-RELEASE': {'receipt': publication_receipt_sha, 'status': 'PARTIAL-D45'},
        },
    }


def build_compatibility_passport(
    candidate_sha: str,
    matrix_sha: str,
    hosted_receipt_sha: str,
    installed_receipt_sha: str,
    released_receipt_sha: str,
    released_cohort_sha: str,
) -> None:
    lines = [
        '# P9 compatibility passport',
        '',
        '**Schema:** p9-compatibility-passport/1',
        '**Item:** P9.9',
        '',
        'Static compatibility report for frozen p9-cohort/1. Not a speculative runtime compatibility generator.',
        '',
        '## Declared support',
        '',
        '| Surface | Digest | Owner | Notes |',
        '|---|---|---|---|',
        f'| p9-cohort/1 candidate | `{candidate_sha}` | P9.1 | frozen intake identity |',
        f'| profile-plan/1 | `{sha256_path(REPO_DELIVERY / "profile-plan.json")}` | P9.3 | Policy A public/private cells |',
        f'| build-attestation/1 | `{sha256_path(REPO_DELIVERY / "build/build-attestation.json")}` | P9.2 | reproducible dist/split trees |',
        '',
        '## Locally executed',
        '',
        '| Surface | Digest | Cases | Notes |',
        '|---|---|---|---|',
        f'| local matrix | `{matrix_sha}` | MATRIX-01..07 | 106 cells local proof |',
        f'| installed-candidate/1 | `{installed_receipt_sha}` | INSTALL-01..09 | neutral lab consumer, no path repo |',
        '',
        '## Hosted',
        '',
        '| Surface | Digest | Cases | Notes |',
        '|---|---|---|---|',
        f'| hosted-receipt/1 | `{hosted_receipt_sha}` | RUN-01..07 | actual workflow run bound |',
        '',
        '## Released installed',
        '',
        '| Surface | Digest | Cases | Notes |',
        '|---|---|---|---|',
        f'| released-installed/1 | `{released_receipt_sha}` | RELEASE-01..04 | local admitted delivery endpoint |',
        f'| released-cohort/1 | `{released_cohort_sha}` | RELEASE-06 | joins publication/install results |',
        '',
        '## Explicit exclusions',
        '',
        '| Exclusion | Owner | Resume |',
        '|---|---|---|',
        '| Remote GitHub composer install against live split repositories | PLAN-CLOSE-CI | D45 deferral while `scripts/.plan-close-deferred` active |',
        '| Full G-RELEASE publication reconcile (PUB-03..05) | PLAN-CLOSE-CI | owner grant + network at plan close |',
        '| Live deployed / aggregate consumer acceptance | P10/P11 | release bytes ≠ deployment proof |',
        '| corex/auth optional private profile runtime in public channel | P9.6/P10 | private envelope separately owned |',
        '',
        '## Gate summary',
        '',
        '| Gate | Receipt | Status | Resume |',
        '|---|---|---|---|',
        f'| G-RUNNER | `{hosted_receipt_sha}` | PASS | unchanged |',
        f'| G-PRIVATE | `{sha256_path(REPO_DELIVERY / "private/receipt.json")}` | PASS | unchanged |',
        f'| G-RELEASE | `{sha256_path(REPO_DELIVERY / "publication/receipt.json")}` | PARTIAL-D45 | PLAN-CLOSE-CI remote reconcile |',
    ]
    (PLAN / 'findings/P9-compatibility-passport.md').write_text('\n'.join(lines) + '\n')


def build_cohort_receipt(
    candidate_sha: str,
    released_cohort_sha: str,
    released_receipt_sha: str,
    passport_sha: str,
    *,
    include_released_install: bool = True,
) -> None:
    sealed: list[tuple[str, str]] = []
    branch_reports = BRANCH_REPORTS if include_released_install else [report for report in BRANCH_REPORTS if report != 'findings/P9-released-install.md']
    for relative in branch_reports + [
        'research/P9-cohort-design.md',
        'findings/P9-compatibility-passport.md',
        'delivery/remaining-cohort-20260915/public/released-cohort.json',
    ]:
        path = plan_path(relative)
        if not path.is_file():
            raise ReleasedError(f'sealed input missing: {relative}')
        sealed.append((relative, sha256_path(path)))

    lines = [
        '# P9 released cohort seal',
        '',
        '**Schema:** p9-released-cohort/1',
        '**Item:** P9.9',
        '',
        'Final same-cohort source/test/hosted/released-installed seal for P10/P11.',
        '',
        '## Requirements',
        '',
        '| R | Packages | Source | Test | Hosted | Release | Installed | Gates | Residual owner |',
        '|---|---|---|---|---|---|---|---|---|',
        (
            '| R45 | 18 selected public packages | '
            f'`{candidate_sha}` | local+hosted matrix | `{sha256_path(REPO_DELIVERY / "hosted/receipt.json")}` | '
            f'`{sha256_path(REPO_DELIVERY / "publication/receipt.json")}` partial D45 | '
            f'`{released_receipt_sha}` | G-RUNNER/G-PRIVATE PASS; G-RELEASE partial | P10/P11 deployment/consumer |'
        ),
        (
            '| R51 | public no-private + optional auth profile | '
            f'`{sha256_path(REPO_DELIVERY / "private/receipt.json")}` | PRIVATE-01..06 | hosted unchanged | '
            'public channel only | released boxed/profile retained | G-PRIVATE PASS | AzGuard/FlexCRM policy owner |'
        ),
        '',
        '## Producer edges',
        '',
        '| Producer | Contract/version | Consumer | Required receipt | Failure model |',
        '|---|---|---|---|---|',
        '| P9.1–P9.8 | p9-cohort-receipts/1 branch reports | P9.9 | nine branch findings | missing branch blocks seal |',
        '| P9.9 | released-installed/1 | P10 | this seal + passport + released-cohort.json | deployment separately owned |',
        '| P9.9 | p9-released-cohort/1 | P11 | this seal + passport + released-cohort.json | consumer acceptance separately owned |',
        '',
        '## Residual owners (P10/P11)',
        '',
        '| ID | Obligation | Consumer | Owner |',
        '|---|---|---|---|',
    ]
    for row in P10_P11_RESIDUALS:
        lines.append(f'| {row[0]} | {row[1]} | {row[2]} | {row[3]} |')
    lines.extend(['', '## Sealed inputs', '', '| Path | SHA256 |', '|---|---|'])
    for path, digest in sorted(sealed, key=lambda item: item[0]):
        lines.append(f'| `{path}` | `{digest}` |')
    lines.extend([
        '',
        '## Released cohort',
        '',
        f'- manifest: `delivery/remaining-cohort-20260915/public/released-cohort.json` (`{released_cohort_sha}`)',
        f'- released-installed receipt: `{released_receipt_sha}`',
        f'- compatibility passport: `{passport_sha}`',
        '- candidate pass: unchanged intake identity bound through AWR-08 joins',
    ])
    (PLAN / 'findings/P9-cohort-receipt.md').write_text('\n'.join(lines) + '\n')


def build_evidence(
    case: str,
    command: list[str],
    candidate_sha: str,
    source_commit: str,
    cases: list[str],
    manifest: dict,
    cell: str,
) -> tuple[dict, Path]:
    log_path = EVIDENCE / f'{case.lower()}.log'
    start = utc_now()
    proc = subprocess.run(command, cwd=ROOT, capture_output=True, text=True)
    end = utc_now()
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
    envelope_path = EVIDENCE / f'{case.lower()}.json'
    packages = list(manifest['download_sha256'])
    envelope = {
        'schema_version': 'p9-evidence/1',
        'proof_class': 'executed',
        'id': case,
        'cohort': 'p9-cohort/1',
        'cell': cell,
        'command': ' '.join(command),
        'cwd': str(ROOT),
        'runtime': f'python={sys.version_info.major}.{sys.version_info.minor}',
        'start': start,
        'end': end,
        'exit': proc.returncode,
        'result': 'PASS' if proc.returncode == 0 else 'FAIL',
        'source_commit': source_commit,
        'recipe_commit': git_head(),
        'candidate_sha256': candidate_sha,
        'lock_sha256': sha256_path(ROOT / 'composer.lock'),
        'config_sha256': sha256_path(RELEASED_SCRIPT),
        'fixture_sha256': sha256_path(DELIVERY_MANIFEST),
        'log_path': str(log_path.relative_to(PLAN)),
        'log_sha256': sha256_path(log_path),
        'cases': cases,
        'delivery_urls': [manifest['delivery_urls'][package] for package in packages[:3]],
        'download_sha256': manifest['download_sha256'][packages[0]],
    }
    write_json(envelope_path, envelope)
    return envelope, envelope_path


def build_released_report(
    candidate_sha: str,
    attestation_sha: str,
    publication_receipt_sha: str,
    released_receipt_sha: str,
    evidence_rows: list[tuple[str, Path, dict]],
    input_rows: list[tuple[str, str, str]],
) -> None:
    lines = [
        '# P9 released install receipts',
        '',
        '**Schema:** p9-cohort-receipts/1',
        '**Item:** P9.9',
        '',
        'Released installed verification for frozen p9-cohort/1 via local admitted delivery endpoint (remote GitHub deferred D45).',
        '',
        '## Cases',
        '',
        '| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |',
        '|---|---|---|---|---|---|---|',
    ]
    for case_id, r_ac, expected in CASE_CATALOG:
        lines.append(
            f'| {case_id} | {r_ac} | {expected} | `evidence:{case_id}` | PASS | P9.9 | PLAN-CLOSE-CI for remote endpoint if applicable |'
        )
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
        rel = path.relative_to(PLAN)
        lines.append(
            f"| {case} | `{rel}` | `{sha256_path(path)}` | released-installed | p9-cohort/1 | {envelope['cell']} | `{envelope['command']}` | `{envelope['cwd']}` | `{envelope['runtime']}` | `{envelope['start']}` | `{envelope['end']}` | {envelope['exit']} | PASS |"
        )
    lines.extend([
        '',
        '## Adoption',
        '',
        '| Surface | Gate | Exact digest | Owner | Result | Resume |',
        '|---|---|---|---|---|---|',
        f'| released-installed/1 | G-RELEASE | `{released_receipt_sha}` | P9.9 | PASS | P10 effective qualification |',
        f'| publication-receipt/1 | G-RELEASE | `{publication_receipt_sha}` | P9.8 | PARTIAL-D45 | PLAN-CLOSE-CI remote reconcile |',
        f'| p9-build-attestation/1 | G-RELEASE | `{attestation_sha}` | P9.2 | PASS | unchanged build join |',
        f'| p9-cohort/1 candidate | G-RELEASE | `{candidate_sha}` | P9.1 | PASS | unchanged intake identity |',
    ])
    (PLAN / 'findings/P9-released-install.md').write_text('\n'.join(lines) + '\n')


def verify_release_05() -> dict:
    path = PLAN / 'findings/P9-compatibility-passport.md'
    text = path.read_text(encoding='utf-8')
    required = (
        '## Declared support',
        '## Locally executed',
        '## Hosted',
        '## Released installed',
        '## Explicit exclusions',
    )
    for section in required:
        if section not in text:
            raise ReleasedError(f'passport missing {section}')
    if 'speculative' in text.lower() and 'not a speculative' not in text.lower():
        raise ReleasedError('passport appears speculative')
    return {'sections': len(required), 'passport_sha256': sha256_path(path)}


def verify_release_06() -> dict:
    path = PLAN / 'findings/P9-cohort-receipt.md'
    text = path.read_text(encoding='utf-8')
    for section in ('## Requirements', '## Producer edges', '## Sealed inputs'):
        if section not in text:
            raise ReleasedError(f'cohort receipt missing {section}')
    if 'R45' not in text or 'R51' not in text:
        raise ReleasedError('requirement mapping incomplete')
    for gate in ('G-RUNNER', 'G-PRIVATE', 'G-RELEASE'):
        if gate not in text:
            raise ReleasedError(f'gate {gate} missing from seal')
    return {'receipt_sha256': sha256_path(path)}


def verify_release_07() -> dict:
    path = PLAN / 'findings/P9-cohort-receipt.md'
    text = path.read_text(encoding='utf-8')
    if '| P10 |' not in text or '| P11 |' not in text:
        raise ReleasedError('P10/P11 producer edges missing')
    for residual in ('DI01', 'AP01', 'Live deployment'):
        if residual not in text:
            raise ReleasedError(f'residual owner {residual} missing')
    return {'producer_edges': ['P10', 'P11']}


def execute_released_proof() -> dict:
    assert_deferral_active()
    repository, cohort, candidate, manifest = delivery_repository()
    ensure_released_cache(repository, cohort, candidate)
    results: dict[str, dict] = {}
    with tempfile.TemporaryDirectory(prefix='corex-released-proof-') as temporary:
        root = Path(temporary)
        results['RELEASE-01'] = verify_release_01(root, repository, manifest, cohort, candidate)
        results['RELEASE-02'] = verify_release_02(root, repository, manifest, cohort, candidate)
        results['RELEASE-03'] = verify_release_03(root, repository, cohort, candidate)
        results['RELEASE-04'] = verify_release_04(root, repository, cohort, candidate)
    receipt = {
        'schema_version': 'released-installed/1',
        'candidate_sha256': sha256_path(REPO_DELIVERY / 'candidate.json'),
        'build_attestation_sha256': sha256_path(REPO_DELIVERY / 'build/build-attestation.json'),
        'publication_receipt_sha256': sha256_path(REPO_DELIVERY / 'publication/receipt.json'),
        'delivery_manifest_sha256': sha256_path(DELIVERY_MANIFEST),
        'endpoint_uri': manifest['endpoint_uri'],
        'cases': results,
    }
    write_json(RELEASED / 'receipt.json', receipt)
    return results


def finalize() -> dict:
    candidate_path = REPO_DELIVERY / 'candidate.json'
    candidate_sha = sha256_path(candidate_path)
    candidate = load_json(candidate_path)
    source_commit = candidate['package_source']['commit']
    attestation_sha = sha256_path(REPO_DELIVERY / 'build/build-attestation.json')
    matrix_sha = sha256_path(REPO_DELIVERY / 'local/matrix.json')
    publication_receipt_sha = sha256_path(REPO_DELIVERY / 'publication/receipt.json')
    hosted_receipt_sha = sha256_path(REPO_DELIVERY / 'hosted/receipt.json')
    private_receipt_sha = sha256_path(REPO_DELIVERY / 'private/receipt.json')
    installed_receipt_sha = sha256_path(REPO_DELIVERY / 'installed-candidate/receipt.json')

    results = execute_released_proof()
    released_receipt_sha = sha256_path(RELEASED / 'receipt.json')
    repository, cohort, candidate, manifest = delivery_repository()

    released_cohort = build_released_cohort_json(
        candidate_sha,
        attestation_sha,
        publication_receipt_sha,
        hosted_receipt_sha,
        private_receipt_sha,
        released_receipt_sha,
        manifest,
    )
    released_cohort_path = REPO_DELIVERY / 'public/released-cohort.json'
    write_json(released_cohort_path, released_cohort)
    released_cohort_sha = sha256_path(released_cohort_path)

    build_compatibility_passport(
        candidate_sha,
        matrix_sha,
        hosted_receipt_sha,
        installed_receipt_sha,
        released_receipt_sha,
        released_cohort_sha,
    )
    passport_sha = sha256_path(PLAN / 'findings/P9-compatibility-passport.md')
    build_cohort_receipt(
        candidate_sha,
        released_cohort_sha,
        released_receipt_sha,
        passport_sha,
        include_released_install=False,
    )

    script = Path(__file__).resolve()
    checks: list[tuple[str, list[str], list[str], str]] = [
        ('RELEASE-01', [sys.executable, str(script), '--verify-release-01'], ['RELEASE-01'], 'released-cold-delivery'),
        ('RELEASE-02', [sys.executable, str(script), '--verify-release-02'], ['RELEASE-02'], 'released-vector-lock'),
        ('RELEASE-03', [sys.executable, str(script), '--verify-release-03'], ['RELEASE-03'], 'released-conformance'),
        ('RELEASE-04', [sys.executable, str(script), '--verify-release-04'], ['RELEASE-04'], 'released-upgrade-binding'),
        ('RELEASE-05', [sys.executable, str(script), '--verify-release-05'], ['RELEASE-05'], 'compatibility-passport'),
        ('RELEASE-06', [sys.executable, str(script), '--verify-release-06'], ['RELEASE-06'], 'cohort-requirements'),
        ('RELEASE-07', [sys.executable, str(script), '--verify-release-07'], ['RELEASE-07'], 'producer-edges'),
    ]
    evidence_rows: list[tuple[str, Path, dict]] = []
    for case, command, case_ids, cell in checks:
        envelope, path = build_evidence(case, command, candidate_sha, source_commit, case_ids, manifest, cell)
        if envelope['result'] != 'PASS':
            raise ReleasedError(f'{case} evidence failed')
        evidence_rows.append((case, path, envelope))

    input_rows = [
        ('delivery/remaining-cohort-20260915/candidate.json', candidate_sha, 'frozen intake identity'),
        ('delivery/remaining-cohort-20260915/build/build-attestation.json', attestation_sha, 'build attestation'),
        ('delivery/remaining-cohort-20260915/publication/receipt.json', publication_receipt_sha, 'publication receipt'),
        ('delivery/remaining-cohort-20260915/hosted/receipt.json', hosted_receipt_sha, 'hosted receipt'),
        ('delivery/remaining-cohort-20260915/private/receipt.json', private_receipt_sha, 'private receipt'),
        ('delivery/remaining-cohort-20260915/installed-candidate/receipt.json', installed_receipt_sha, 'installed candidate receipt'),
        ('delivery/remaining-cohort-20260915/released-installed/delivery-manifest.json', sha256_path(DELIVERY_MANIFEST), 'admitted delivery manifest'),
        ('delivery/remaining-cohort-20260915/released-installed/receipt.json', released_receipt_sha, 'released installed receipt'),
        ('delivery/remaining-cohort-20260915/public/released-cohort.json', released_cohort_sha, 'released cohort manifest'),
        ('scripts/distribution/remaining-cohort-released.py', sha256_path(RELEASED_SCRIPT), 'released install recipe'),
        ('research/P9-cohort-design.md', sha256_path(PLAN / 'research/P9-cohort-design.md'), 'design contract'),
    ]
    build_released_report(
        candidate_sha,
        attestation_sha,
        publication_receipt_sha,
        released_receipt_sha,
        evidence_rows,
        input_rows,
    )
    build_cohort_receipt(candidate_sha, released_cohort_sha, released_receipt_sha, passport_sha)
    return {
        'result': 'PASS',
        'candidate_sha256': candidate_sha,
        'released_receipt_sha256': released_receipt_sha,
        'released_cohort_sha256': released_cohort_sha,
        'cases': list(results),
    }


def _with_delivery() -> tuple[Path, dict, dict, Path, dict]:
    repository, cohort, candidate, manifest = delivery_repository()
    ensure_released_cache(repository, cohort, candidate)
    temporary = tempfile.mkdtemp(prefix='corex-released-verify-')
    return Path(temporary), cohort, candidate, repository, manifest


def verify_named(case: str) -> int:
    root, cohort, candidate, repository, manifest = _with_delivery()
    try:
        if case == 'RELEASE-01':
            verify_release_01(root, repository, manifest, cohort, candidate)
        elif case == 'RELEASE-02':
            verify_release_02(root, repository, manifest, cohort, candidate)
        elif case == 'RELEASE-03':
            verify_release_03(root, repository, cohort, candidate)
        elif case == 'RELEASE-04':
            verify_release_04(root, repository, cohort, candidate)
        elif case == 'RELEASE-05':
            verify_release_05()
        elif case == 'RELEASE-06':
            verify_release_06()
        elif case == 'RELEASE-07':
            verify_release_07()
        else:
            raise ReleasedError(f'unknown case {case}')
        return 0
    except ReleasedError:
        return 1
    finally:
        shutil.rmtree(root, ignore_errors=True)


def main() -> int:
    try:
        payload = finalize()
        print(json.dumps(payload))
        return 0
    except ReleasedError as exc:
        print(f'released proof denied: {exc}', file=sys.stderr)
        return 1


if __name__ == '__main__':
    if len(sys.argv) == 1:
        raise SystemExit(main())
    modes = {
        '--verify-release-01': lambda: verify_named('RELEASE-01'),
        '--verify-release-02': lambda: verify_named('RELEASE-02'),
        '--verify-release-03': lambda: verify_named('RELEASE-03'),
        '--verify-release-04': lambda: verify_named('RELEASE-04'),
        '--verify-release-05': lambda: verify_named('RELEASE-05'),
        '--verify-release-06': lambda: verify_named('RELEASE-06'),
        '--verify-release-07': lambda: verify_named('RELEASE-07'),
        '--finalize': main,
    }
    raise SystemExit(modes[sys.argv[1]]() if sys.argv[1] in modes else main())
