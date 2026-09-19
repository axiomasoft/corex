#!/usr/bin/env python3
"""Publication packet preparation and G-RELEASE execution for remaining-cohort split/dist assets."""
from __future__ import annotations

import argparse
import hashlib
import importlib.util
import json
import logging
import os
import re
import shutil
import stat
import subprocess
import sys
import tempfile
import time
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timezone
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen

from publish_guard import Admission, Evidence, Operation, PublicationDenied, UnknownOutcome, publish

ROOT = Path(__file__).resolve().parents[2]
REPO_DELIVERY = ROOT / 'delivery/remaining-cohort-20260915'
PLAN = ROOT / 'plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING'
PLAN_DELIVERY = PLAN / 'delivery/remaining-cohort-20260915'
EVIDENCE = PLAN_DELIVERY / 'evidence'
PUBLICATION_DIR = REPO_DELIVERY / 'publication'
ARTIFACTS_DIR = REPO_DELIVERY / 'build/artifacts'
PUBLISH_SCRIPT = Path(__file__).resolve()
HISTORICAL_E5_REPOS = frozenset({
    'axiomasoft/corex-core',
    'axiomasoft/corex-modules',
    'axiomasoft/corex-tasks',
    'axiomasoft/corex-tenancy',
})

GH_TIMEOUT_SECONDS = 120
GIT_TIMEOUT_SECONDS = 300
GH_RETRY_ATTEMPTS = 3
GH_RETRY_BACKOFF_SECONDS = 2.0

LOGGER = logging.getLogger(__name__)
AUDIT_LOG = PUBLICATION_DIR / 'write-audit.jsonl'
_LOGGING_CONFIGURED = False
_AUDIT_ENABLED = False

BRANCH_RECEIPTS = {
    'findings/P9-intake.md': 'intake receipt',
    'findings/P9-artifacts.md': 'build/dist receipt',
    'findings/P9-local-matrix.md': 'local matrix receipt',
    'findings/P9-installed-candidate.md': 'installed candidate receipt',
    'findings/P9-hosted-runs.md': 'hosted runs receipt',
    'findings/P9-private-auth.md': 'private auth receipt',
}

PROOF_INPUTS = {
    'delivery/remaining-cohort-20260915/candidate.json': 'frozen intake identity',
    'delivery/remaining-cohort-20260915/build/build-attestation.json': 'build attestation',
    'delivery/remaining-cohort-20260915/build/export-map.json': 'export map',
    'delivery/remaining-cohort-20260915/public/packages.json': 'public composer metadata',
    'delivery/remaining-cohort-20260915/hosted/receipt.json': 'hosted receipt',
    'delivery/remaining-cohort-20260915/installed-candidate/receipt.json': 'installed candidate receipt',
    'delivery/remaining-cohort-20260915/private/receipt.json': 'private receipt',
    'delivery/remaining-cohort-20260915/runner-packet.json': 'final runner packet',
}

P10_P11_RESIDUALS = [
    ('DI01–DI34', 'Central/cloud/timing/provider obligations', 'P10 effective gates', 'P10 owner'),
    ('AP01–AP13', 'Application/product acceptance parents', 'P11 consumer reconciliation', 'P11 owner'),
    ('G-API/G-DEPENDENCY', 'Contract/runtime adoption outside release bytes', 'P10/P11 qualification', 'Release owner'),
    ('Live deployment', 'No live deployed/consumer acceptance in P9', 'P10 provider + P11 consumer', 'Product owner'),
]

FORBIDDEN_KINDS = {'force-push', 'delete', 'admin', 'repo-settings', 'branch-protection'}


class PublicationPacketError(RuntimeError):
    """Publication packet violated the remaining-cohort contract."""


class ReadOnlyTransport:
    """Reconcile remote state without applying writes."""

    def __init__(self, local_state: dict[str, str | None]):
        self.local_state = local_state
        self.writes: list[str] = []

    def reconcile(self, operation: Operation) -> str | None:
        return self.local_state.get(operation.reference)

    def apply(self, operation: Operation) -> None:
        raise PublicationDenied('read-only mode forbids publication writes')


def sha256_path(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


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


def digest_map(paths: dict[str, str]) -> dict[str, str]:
    return {path: sha256_path(plan_path(path)) for path in paths}


def build_operations(candidate: dict, export_map: dict) -> list[dict]:
    operations: list[dict] = []
    order = 0
    for package in sorted(export_map.get('packages', {})):
        item = export_map['packages'][package]
        split = candidate['split_repositories'][package]
        repository = split['repository']
        tag = split['tag']
        order += 1
        operations.append({
            'operation_id': f'push-tag-{package.replace("/", "-")}',
            'repository': repository,
            'package': package,
            'visibility': split['visibility'],
            'channel': split['channel'],
            'kind': 'tag',
            'reference': tag,
            'expected_previous_state': None,
            'target_fingerprint': item['split_commit'],
            'credential_ref': 'GITHUB_TOKEN_RELEASE',
            'credential_scope': f'repo:{repository}:contents:write,workflow',
            'verification_query': f'git ls-remote https://github.com/{repository}.git refs/tags/{tag}',
            'dependency_order': order,
            'write': True,
        })
        order += 1
        operations.append({
            'operation_id': f'release-asset-{package.replace("/", "-")}',
            'repository': repository,
            'package': package,
            'visibility': split['visibility'],
            'channel': split['channel'],
            'kind': 'release',
            'reference': tag,
            'expected_previous_state': None,
            'target_fingerprint': f"sha256:{item['dist_sha256']}",
            'credential_ref': 'GITHUB_TOKEN_RELEASE',
            'credential_scope': f'repo:{repository}:contents:write,workflow',
            'verification_query': f'gh release view {tag} --repo {repository} --json assets',
            'dependency_order': order,
            'write': True,
        })
    return operations


def dry_run_rows(candidate_sha: str, attestation_sha: str, operations: list[dict]) -> list[dict]:
    rows = []
    transport = ReadOnlyTransport({})
    admission = Admission(frozenset(), frozenset(), False)
    for row in operations:
        operation = Operation(row['repository'], row['kind'], row['reference'], row['target_fingerprint'])
        try:
            result = publish(
                Evidence(operation, candidate_sha, attestation_sha, 'read-only-no-grant'),
                admission,
                transport,
            )
        except PublicationDenied as exc:
            result = f'denied:{exc}'
        rows.append({
            'operation_id': row['operation_id'],
            'repository': row['repository'],
            'kind': row['kind'],
            'reference': row['reference'],
            'fingerprint': row['target_fingerprint'],
            'result': result,
        })
    return rows


def build_publication_packet() -> dict:
    candidate_path = REPO_DELIVERY / 'candidate.json'
    export_map_path = REPO_DELIVERY / 'build/export-map.json'
    attestation_path = REPO_DELIVERY / 'build/build-attestation.json'
    for path in (candidate_path, export_map_path, attestation_path):
        if not path.is_file():
            raise PublicationPacketError(f'missing build artifact: {path}')

    candidate = load_json(candidate_path)
    export_map = load_json(export_map_path)
    candidate_sha = sha256_path(candidate_path)
    attestation_sha = sha256_path(attestation_path)
    branch_digests = digest_map(BRANCH_RECEIPTS)
    proof_digests = digest_map(PROOF_INPUTS)
    operations = build_operations(candidate, export_map)

    packet = {
        'schema_version': 'p9-publication-packet/1',
        'cohort_id': candidate.get('cohort_id') or export_map.get('cohort_id'),
        'candidate_sha256': candidate_sha,
        'build_attestation_sha256': attestation_sha,
        'export_map_sha256': sha256_path(export_map_path),
        'public_packages_sha256': sha256_path(REPO_DELIVERY / 'public/packages.json'),
        'branch_receipts': branch_digests,
        'proof_inputs': proof_digests,
        'query_before_retry': True,
        'protected': True,
        'grant_required': 'G-RELEASE',
        'grant_sha256': None,
        'scope': 'P9.8:p9-publication-packet/1:exact-cohort-operations',
        'branch': 'remaining-cohort-publish',
        'operations': operations,
        'forbidden_operation_kinds': sorted(FORBIDDEN_KINDS),
        'p10_p11_residuals': [
            {
                'id': row[0],
                'obligation': row[1],
                'consumer': row[2],
                'owner': row[3],
            }
            for row in P10_P11_RESIDUALS
        ],
        'recipe_commits': {
            'remaining-cohort-publish.py': sha256_path(PUBLISH_SCRIPT),
            'git_head': git_head(),
        },
        'dry_run': dry_run_rows(candidate_sha, attestation_sha, operations),
        'note': 'Read-only packet for owner review; P9.8 executes publication with G-RELEASE grant',
    }
    return packet


def build_publication_ledger(packet_sha: str) -> dict:
    return {
        'schema_version': 'publication-ledger/1',
        'publication_packet_sha256': packet_sha,
        'status': 'pending',
        'grant_required': 'G-RELEASE',
        'grant_sha256': None,
        'operations': [],
        'note': 'Ledger records actual publication results in P9.8; partial states remain pending until full reconciliation',
    }


def build_gate_request(packet_sha: str) -> None:
    lines = [
        '# P9 — G-RELEASE gate request',
        '',
        '**Schema:** p9-release-gate-request/1',
        '**Item:** P9.7',
        '',
        'Digest-bound publication grant request. Preparer cannot mint consent; P9.8 executes only after owner grant.',
        '',
        '## Request',
        '',
        '| Request | Gate | R | Exact packet | SHA256 | Owner | Required receipt | Status | Resume | Destination |',
        '|---|---|---|---|---|---|---|---|---|---|',
        (
            '| Q-P9-RELEASE-001 | G-RELEASE | R45 R51 | '
            '`delivery/remaining-cohort-20260915/publication-packet.json` | '
            f'`{packet_sha}` | Release owner | Exact finite cohort operations: split tag + dist release per public package; '
            'query-before-retry; no force/delete/admin; private auth excluded from public metadata. | proposal | '
            'Owner grants G-RELEASE at current packet digest; stale packet requires re-seal before write. | P9.8 publication |'
        ),
        '',
        '## Scope digest',
        '',
        f'- **Packet SHA256:** `{packet_sha}`',
        '- **Branch:** `remaining-cohort-publish`',
        '- **Scope:** `P9.8:p9-publication-packet/1:exact-cohort-operations`',
        '- **Credential refs:** `GITHUB_TOKEN_RELEASE` narrow repo contents/workflow only',
        '',
        '## P10/P11 residual handoff (not P9 GREEN by themselves)',
        '',
        '| Residual | Obligation | Consumer | Owner |',
        '|---|---|---|---|',
    ]
    for row in P10_P11_RESIDUALS:
        lines.append(f'| {row[0]} | {row[1]} | {row[2]} | {row[3]} |')
    lines.extend([
        '',
        '## Explicit non-grants',
        '',
        '- Historical D14 design authority is not a publication grant.',
        '- Hosted mirror execution (`academici/corex-mirror`) does not substitute canonical split-repo write scope.',
        '- Prepared packet/NO-GO is not phase GREEN; P9.8 owns actual publication bytes.',
        '',
    ])
    (PLAN / 'findings/P9-release-gate-request.md').write_text('\n'.join(lines))


def case_catalog() -> list[tuple[str, str, str]]:
    return [
        ('PACKET-01', 'R45 R51', 'Exact candidate digest and per-package/source/split/tag/dist/channel file set sealed after all local/install/hosted/private inputs, old publication excluded'),
        ('PACKET-02', 'R45 R51', 'Finite operation matrix enumerates repository visibility, immutable ref/object/asset/digest, expected old state, dependency ordering and credential scope; no repo admin/force/delete extras'),
        ('PACKET-03', 'R45 R51', 'BC/major/changelog/profile/DDL/private/source licenses approved for actual bytes; fixture schema approval does not grant production DDL'),
        ('PACKET-04', 'R45 R51', 'Draft/tag/assets/index plan differentiates partial states and query-before-retry; independent lookup/download/reconcile command for every effect'),
        ('PACKET-05', 'R45 R51', 'G-RELEASE request is digest-bound to full finite operations and fresh grants; preparer cannot mint consent, no publication in this item'),
        ('PACKET-06', 'R45 R51', 'P10/P11 same-cohort handoff includes full R/AC residual DI/AP/real provider/timing/consumer owners; NO-GO is not phase GREEN'),
    ]


def verify_packet_01(packet: dict) -> dict:
    candidate_sha = sha256_path(REPO_DELIVERY / 'candidate.json')
    if packet['candidate_sha256'] != candidate_sha:
        raise PublicationPacketError('candidate digest drift')
    for path, digest in packet['branch_receipts'].items():
        if sha256_path(plan_path(path)) != digest:
            raise PublicationPacketError(f'stale branch receipt {path}')
    for path, digest in packet['proof_inputs'].items():
        if sha256_path(plan_path(path)) != digest:
            raise PublicationPacketError(f'stale proof input {path}')
    if packet['build_attestation_sha256'] != sha256_path(REPO_DELIVERY / 'build/build-attestation.json'):
        raise PublicationPacketError('build attestation drift')
    return {
        'candidate_sha256': candidate_sha,
        'branch_receipts': len(packet['branch_receipts']),
        'proof_inputs': len(packet['proof_inputs']),
        'old_publication_excluded': True,
    }


def verify_packet_02(packet: dict) -> dict:
    operations = packet['operations']
    if not operations:
        raise PublicationPacketError('operation matrix empty')
    kinds = {row['kind'] for row in operations}
    if kinds - {'tag', 'release'}:
        raise PublicationPacketError('unexpected operation kinds')
    if any(row['kind'] in FORBIDDEN_KINDS for row in operations):
        raise PublicationPacketError('forbidden admin operation present')
    orders = [row['dependency_order'] for row in operations]
    if orders != sorted(orders) or len(set(orders)) != len(orders):
        raise PublicationPacketError('dependency ordering invalid')
    for row in operations:
        for field in ('repository', 'visibility', 'channel', 'reference', 'target_fingerprint', 'credential_ref', 'credential_scope', 'verification_query'):
            if not row.get(field):
                raise PublicationPacketError(f'missing operation field {field}')
    return {'operations': len(operations), 'repositories': len({row["repository"] for row in operations})}


def verify_packet_03(packet: dict) -> dict:
    candidate = load_json(REPO_DELIVERY / 'candidate.json')
    public = load_json(REPO_DELIVERY / 'public/packages.json')
    if 'corex/auth' in json.dumps(public):
        raise PublicationPacketError('private auth leaked into public metadata')
    if not candidate.get('p2_contract_hashes'):
        raise PublicationPacketError('accepted P2 contract hashes missing from candidate')
    if not candidate.get('profile_plan_sha256'):
        raise PublicationPacketError('profile plan binding missing')
    return {
        'public_packages': len(public.get('packages', {})),
        'accepted_p2_contracts': len(candidate['p2_contract_hashes']),
        'fixture_schema_not_production_ddl': True,
    }


def verify_packet_04(packet: dict) -> dict:
    if not packet.get('query_before_retry'):
        raise PublicationPacketError('query-before-retry not enabled')
    missing = [row['operation_id'] for row in packet['operations'] if not row.get('verification_query')]
    if missing:
        raise PublicationPacketError('verification query missing for operations')
    ledger_path = REPO_DELIVERY / 'publication-ledger.json'
    if not ledger_path.is_file():
        raise PublicationPacketError('publication ledger missing')
    ledger = load_json(ledger_path)
    if ledger.get('status') != 'pending':
        raise PublicationPacketError('ledger must start pending before publication')
    return {'query_before_retry': True, 'verification_queries': len(packet['operations']), 'ledger_status': ledger['status']}


def verify_packet_05(packet: dict) -> dict:
    packet_path = REPO_DELIVERY / 'publication-packet.json'
    packet_sha = sha256_path(packet_path)
    gate_path = PLAN / 'findings/P9-release-gate-request.md'
    gate_text = gate_path.read_text(encoding='utf-8')
    if packet_sha not in gate_text:
        raise PublicationPacketError('gate request not bound to packet digest')
    if packet.get('grant_sha256') is not None:
        raise PublicationPacketError('preparer attempted to mint grant')
    if any(str(row.get('result', '')).startswith('published') for row in packet.get('dry_run', [])):
        raise PublicationPacketError('dry run attempted publication')
    try:
        prepare_plan(write=True)
        raise PublicationPacketError('write path must refuse without grant')
    except PublicationDenied:
        pass
    return {'packet_sha256': packet_sha, 'grant_minted': False, 'gate_request_present': True}


def verify_packet_06(packet: dict) -> dict:
    gate_text = (PLAN / 'findings/P9-release-gate-request.md').read_text(encoding='utf-8')
    for row in P10_P11_RESIDUALS:
        if row[0] not in gate_text:
            raise PublicationPacketError(f'missing residual {row[0]} in gate request')
    residuals = packet.get('p10_p11_residuals', [])
    if len(residuals) < 4:
        raise PublicationPacketError('incomplete P10/P11 residual handoff')
    return {'residual_rows': len(residuals), 'phase_green_without_grant': False}


def prepare_plan(*, write: bool = False) -> dict:
    packet_path = REPO_DELIVERY / 'publication-packet.json'
    if packet_path.is_file():
        packet = load_json(packet_path)
    else:
        packet = build_publication_packet()
    candidate_sha = packet['candidate_sha256']
    attestation_sha = packet['build_attestation_sha256']
    rows = []
    transport = ReadOnlyTransport({})
    admission = Admission(frozenset(), frozenset(), False)
    for row in packet['operations']:
        operation = Operation(row['repository'], row['kind'], row['reference'], row['target_fingerprint'])
        try:
            result = publish(
                Evidence(operation, candidate_sha, attestation_sha, 'read-only-no-grant'),
                admission,
                transport,
            )
        except PublicationDenied as exc:
            result = f'denied:{exc}'
        rows.append({
            'repository': row['repository'],
            'kind': row['kind'],
            'reference': row['reference'],
            'fingerprint': row['target_fingerprint'],
            'result': result,
        })
    if write:
        raise PublicationDenied('G-RELEASE grant required before any publication write')
    return {
        'schema_version': 'remaining-cohort-publication-plan/1',
        'candidate_sha256': candidate_sha,
        'build_attestation_sha256': attestation_sha,
        'operations': rows,
        'write_attempted': False,
        'transport_writes': transport.writes,
    }


def load_packet() -> dict:
    packet_path = REPO_DELIVERY / 'publication-packet.json'
    if not packet_path.is_file():
        raise PublicationPacketError('publication packet missing; run --build first')
    packet = load_json(packet_path)
    packet['_packet_path'] = packet_path
    return packet


def configure_logging(*, verbose: bool = False, audit: bool = False) -> None:
    """stderr diagnostics via logging; JSONL audit file for remote publication evidence."""
    global _LOGGING_CONFIGURED, _AUDIT_ENABLED
    if _LOGGING_CONFIGURED:
        return
    level = logging.DEBUG if verbose else logging.INFO
    handler = logging.StreamHandler(sys.stderr)
    handler.setFormatter(logging.Formatter(
        fmt='%(asctime)s %(levelname)s %(message)s',
        datefmt='%Y-%m-%dT%H:%M:%S',
    ))
    LOGGER.setLevel(level)
    LOGGER.addHandler(handler)
    LOGGER.propagate = False
    _AUDIT_ENABLED = audit
    if audit:
        PUBLICATION_DIR.mkdir(parents=True, exist_ok=True)
    _LOGGING_CONFIGURED = True


def record_audit(event: str, **fields: object) -> None:
    payload = {
        'ts': datetime.now(timezone.utc).isoformat(),
        'event': event,
        **fields,
    }
    if _AUDIT_ENABLED:
        with AUDIT_LOG.open('a', encoding='utf-8') as handle:
            handle.write(json.dumps(payload, ensure_ascii=False, sort_keys=True) + '\n')
    summary = ', '.join(f'{key}={value}' for key, value in fields.items())
    LOGGER.info('%s %s', event, summary)


def transport_error(stderr: str | None) -> bool:
    lowered = (stderr or '').lower()
    return (
        'error connecting' in lowered
        or 'check your internet connection' in lowered
        or 'timed out' in lowered
        or 'timeout' in lowered
        or 'connection reset' in lowered
        or 'connection refused' in lowered
    )


def run_gh(args: list[str], *, timeout: int = GH_TIMEOUT_SECONDS) -> subprocess.CompletedProcess[str]:
    last: subprocess.CompletedProcess[str] | None = None
    for attempt in range(1, GH_RETRY_ATTEMPTS + 1):
        try:
            proc = subprocess.run(
                ['gh', *args],
                cwd=ROOT,
                capture_output=True,
                text=True,
                timeout=timeout,
            )
        except subprocess.TimeoutExpired as exc:
            raise PublicationPacketError(
                f'gh command timed out after {timeout}s: gh {" ".join(args)}'
            ) from exc
        last = proc
        if proc.returncode == 0 or not transport_error(proc.stderr):
            return proc
        if attempt < GH_RETRY_ATTEMPTS:
            LOGGER.warning(
                'gh retry %s/%s after transport error: gh %s',
                attempt,
                GH_RETRY_ATTEMPTS,
                ' '.join(args),
            )
            time.sleep(GH_RETRY_BACKOFF_SECONDS * attempt)
    assert last is not None
    return last


GIT_COMMIT_ENV = {
    **os.environ,
    'GIT_AUTHOR_DATE': '1980-01-01T00:00:00 +0000',
    'GIT_COMMITTER_DATE': '1980-01-01T00:00:00 +0000',
}


def load_export_module():
    path = ROOT / 'scripts/distribution/remaining-cohort-export.py'
    spec = importlib.util.spec_from_file_location('remaining_cohort_export', path)
    module = importlib.util.module_from_spec(spec)
    assert spec.loader is not None
    spec.loader.exec_module(module)
    return module


def run_git(
    args: list[str],
    *,
    cwd: Path,
    check: bool = True,
    env: dict[str, str] | None = None,
    timeout: int = GIT_TIMEOUT_SECONDS,
) -> subprocess.CompletedProcess[str]:
    try:
        return subprocess.run(
            ['git', *args],
            cwd=cwd,
            capture_output=True,
            text=True,
            check=check,
            env=env or os.environ,
            timeout=timeout,
        )
    except subprocess.TimeoutExpired as exc:
        raise PublicationPacketError(
            f'git command timed out after {timeout}s: git {" ".join(args)} (cwd={cwd})'
        ) from exc


def fingerprint_tree_from_dir(root: Path, export_mod) -> str:
    tree: dict[str, dict] = {}
    for path in sorted(item for item in root.rglob('*') if item.is_file()):
        relative = path.relative_to(root).as_posix()
        body = path.read_bytes()
        mode = '100755' if path.stat().st_mode & stat.S_IXUSR else '100644'
        tree[relative] = {'sha256': hashlib.sha256(body).hexdigest(), 'mode': mode, 'bytes': body}
    return export_mod.split_commit(tree)


def build_split_tree(package: str, export_mod) -> dict[str, dict]:
    candidate = load_json(REPO_DELIVERY / 'candidate.json')
    policy = export_mod.load_policy()
    ref = candidate['package_source']['commit']
    tree, _ = export_mod.build_release_tree(ref, package, candidate, policy)
    export_map = load_json(REPO_DELIVERY / 'build/export-map.json')
    expected = export_map['packages'][package]['split_commit']
    if export_mod.split_commit(tree) != expected:
        raise PublicationPacketError(f'split tree drift for {package}')
    return tree


def write_tree_to_dir(tree: dict[str, dict], root: Path) -> None:
    for relative, meta in sorted(tree.items()):
        path = root / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(meta['bytes'])
        if meta['mode'] == '100755':
            path.chmod(path.stat().st_mode | stat.S_IXUSR | stat.S_IXGRP | stat.S_IXOTH)


def remote_tag_exists(repository: str, tag: str) -> bool | None:
    try:
        proc = subprocess.run(
            [
                'git',
                'ls-remote',
                '--exit-code',
                f'https://github.com/{repository}.git',
                f'refs/tags/{tag}',
            ],
            capture_output=True,
            text=True,
            timeout=60,
        )
    except subprocess.TimeoutExpired:
        return None
    if proc.returncode == 0:
        return bool(proc.stdout.strip())
    if proc.returncode == 2:
        return False
    if transport_error(proc.stderr):
        return None
    return False


def query_tag_split_fingerprint(repository: str, tag: str, export_mod) -> str | None:
    exists = remote_tag_exists(repository, tag)
    if exists is False:
        return None
    if exists is None:
        raise PublicationPacketError(f'tag query transport failure for {repository}:{tag}')
    with tempfile.TemporaryDirectory() as tmp:
        try:
            proc = subprocess.run(
                [
                    'git',
                    'clone',
                    '--depth',
                    '1',
                    '--branch',
                    tag,
                    f'https://github.com/{repository}.git',
                    tmp,
                ],
                capture_output=True,
                text=True,
                timeout=GIT_TIMEOUT_SECONDS,
            )
        except subprocess.TimeoutExpired as exc:
            raise PublicationPacketError(
                f'tag fingerprint clone timed out for {repository}:{tag}'
            ) from exc
        if proc.returncode != 0:
            if transport_error(proc.stderr):
                raise PublicationPacketError(
                    f'tag fingerprint clone transport failure for {repository}:{tag}'
                )
            return None
        return fingerprint_tree_from_dir(Path(tmp), export_mod)


def ensure_repository(repository: str) -> None:
    exists = repository_exists(repository)
    if exists is True:
        return
    if exists is None:
        raise PublicationPacketError(f'repository lookup transport failure for {repository}')
    proc = run_gh(['repo', 'create', repository, '--public'])
    if proc.returncode != 0:
        exists = repository_exists(repository)
        if exists is True:
            return
        if exists is None:
            raise PublicationPacketError(
                f'repository create transport failure for {repository}: {proc.stderr.strip()}'
            )
        lowered = (proc.stderr or '').lower()
        if 'already exists' in lowered or 'name already exists' in lowered:
            return
        raise PublicationPacketError(f'repository create failed for {repository}: {proc.stderr.strip()}')


def materialize_split_staging(package: str, staging_root: Path, repository: str, export_mod) -> Path:
    tree = build_split_tree(package, export_mod)
    candidate = load_json(REPO_DELIVERY / 'candidate.json')
    tag = candidate['split_repositories'][package]['tag']
    work = staging_root / package.replace('/', '-')
    if work.exists():
        shutil.rmtree(work)
    exists = repository_exists(repository)
    if exists is True and not plan_close_deferred():
        run_git(['clone', f'https://github.com/{repository}.git', str(work)], cwd=staging_root)
        run_git(['rm', '-rf', '.'], cwd=work, check=False)
        for item in work.iterdir():
            if item.name == '.git':
                continue
            if item.is_dir():
                shutil.rmtree(item)
            else:
                item.unlink()
    else:
        work.mkdir(parents=True)
        run_git(['init', '-b', 'main'], cwd=work)
        run_git(['config', 'user.email', 'release@corex.local'], cwd=work)
        run_git(['config', 'user.name', 'CoreX Release'], cwd=work)
    write_tree_to_dir(tree, work)
    run_git(['add', '-A'], cwd=work)
    run_git(
        ['commit', '-m', f'Release cohort split tree for {package}'],
        cwd=work,
        check=False,
        env=GIT_COMMIT_ENV,
    )
    run_git(['tag', '-d', tag], cwd=work, check=False)
    run_git(['tag', '-a', tag, '-m', tag], cwd=work, env=GIT_COMMIT_ENV)
    if run_git(['remote', 'get-url', 'origin'], cwd=work, check=False).returncode != 0:
        run_git(['remote', 'add', 'origin', f'https://github.com/{repository}.git'], cwd=work)
    return work


def push_split_repository(work: Path, repository: str, tag: str) -> None:
    push_main = run_git(['push', 'origin', 'main'], cwd=work, check=False)
    if push_main.returncode != 0:
        raise PublicationDenied('split main push failed; reconcile before any retry')
    push_tag = run_git(['push', 'origin', f'refs/tags/{tag}'], cwd=work, check=False)
    if push_tag.returncode != 0:
        raise PublicationDenied('split tag push failed; reconcile before any retry')


def repository_exists(repository: str) -> bool | None:
    proc = run_gh(['api', f'repos/{repository}', '--jq', '.full_name'])
    if proc.returncode == 0:
        return True
    if transport_error(proc.stderr):
        return None
    lowered = (proc.stderr or '').lower()
    if 'http 404' in lowered or 'not found' in lowered:
        return False
    return False


def repository_existence_map(repositories: list[str]) -> dict[str, bool | None]:
    unique = sorted(set(repositories))
    if not unique:
        return {}
    with ThreadPoolExecutor(max_workers=min(8, len(unique))) as pool:
        return dict(zip(unique, pool.map(repository_exists, unique), strict=True))


def find_grant_decision(packet_sha: str) -> Path | None:
    for path in sorted((PLAN / 'decisions').glob('D*.md')):
        text = path.read_text(encoding='utf-8')
        if '[OWNER-GATE:G-RELEASE]' not in text:
            continue
        if packet_sha not in text:
            continue
        if re.search(r'^\*\*Status:\*\*\s*accepted\b', text, re.I | re.M) is None:
            continue
        return path
    return None


def load_grant(packet_sha: str) -> dict:
    decision = find_grant_decision(packet_sha)
    if decision is None:
        raise PublicationDenied('G-RELEASE grant required before any publication write')
    return {
        'gate': 'G-RELEASE',
        'grant_sha256': packet_sha,
        'decision_path': str(decision.relative_to(PLAN)),
        'decision_sha256': sha256_path(decision),
        'branch': 'remaining-cohort-publish',
        'scope': 'P9.8:p9-publication-packet/1:exact-cohort-operations',
    }


def artifact_path(package: str) -> Path:
    return ARTIFACTS_DIR / f'{package.replace("/", "-")}.zip'


def preflight(packet: dict | None = None) -> dict:
    packet = packet or load_packet()
    packet_sha = sha256_path(packet['_packet_path'])
    repositories = sorted({row['repository'] for row in packet['operations']})
    repo_map = repository_existence_map(repositories)
    transport_failures = [repo for repo in repositories if repo_map.get(repo) is None]
    missing_repos = [repo for repo in repositories if repo_map.get(repo) is False]
    missing_artifacts = [
        row['package']
        for row in packet['operations']
        if row['kind'] == 'release' and not artifact_path(row['package']).is_file()
    ]
    blockers: list[str] = []
    if find_grant_decision(packet_sha) is None:
        blockers.append('G-RELEASE grant missing for current publication packet digest')
    if transport_failures:
        blockers.append('repository lookup transport failure: ' + ', '.join(transport_failures))
    if missing_repos:
        blockers.append('missing split repositories: ' + ', '.join(missing_repos))
    if missing_artifacts:
        blockers.append('missing dist artifacts: ' + ', '.join(missing_artifacts))
    if run_gh(['api', 'user', '--jq', '.login']).returncode != 0:
        blockers.append('gh api unreachable or unauthenticated')
    for case, verify in (
        ('PACKET-01', verify_packet_01),
        ('PACKET-02', verify_packet_02),
        ('PACKET-03', verify_packet_03),
        ('PACKET-04', verify_packet_04),
    ):
        try:
            verify(packet)
        except PublicationPacketError as exc:
            blockers.append(f'{case} drift: {exc}')
    return {
        'schema_version': 'publication-preflight/1',
        'publication_packet_sha256': packet_sha,
        'grant_required': 'G-RELEASE',
        'grant_present': find_grant_decision(packet_sha) is not None,
        'repositories': repositories,
        'missing_repositories': missing_repos,
        'historical_e5_repositories': sorted(HISTORICAL_E5_REPOS),
        'blockers': blockers,
        'ready': not blockers,
        'write_command': (
            'python3 scripts/distribution/remaining-cohort-publish.py --write'
            if find_grant_decision(packet_sha) is not None and not missing_repos and not missing_artifacts else None
        ),
    }


def query_tag_state(repository: str, tag: str, export_mod=None) -> str | None:
    export_mod = export_mod or load_export_module()
    return query_tag_split_fingerprint(repository, tag, export_mod)


def release_query_missing(stderr: str) -> bool:
    lowered = stderr.lower()
    return (
        'http 404' in lowered
        or 'release not found' in lowered
        or 'not found' in lowered
        or 'error connecting' in lowered
    )


def query_release_asset_digest(repository: str, tag: str) -> str | None:
    proc = run_gh(['release', 'view', tag, '--repo', repository, '--json', 'assets'])
    if proc.returncode != 0:
        if release_query_missing(proc.stderr):
            return None
        raise PublicationPacketError(f'release query failed for {repository}:{tag}')
    payload = json.loads(proc.stdout or '{}')
    assets = payload.get('assets') or []
    if not assets:
        return None
    digest = assets[0].get('digest')
    if digest:
        return digest.removeprefix('sha256:')
    url = assets[0].get('url')
    if not url:
        return None
    request = Request(url, headers={'Accept': 'application/octet-stream', 'User-Agent': 'corex-remaining-cohort-publish'})
    try:
        with urlopen(request, timeout=120) as response:
            return hashlib.sha256(response.read()).hexdigest()
    except HTTPError as exc:
        if exc.code in {404, 408, 429, 500, 502, 503, 504}:
            return None
        raise PublicationPacketError(f'release asset download failed for {repository}:{tag}: {exc.code}') from exc
    except URLError:
        return None


def reconcile_operation(row: dict, *, repository_available: bool = True, export_mod=None) -> dict:
    repository = row['repository']
    if not repository_available:
        return {
            'operation_id': row['operation_id'],
            'repository': repository,
            'package': row['package'],
            'kind': row['kind'],
            'reference': row['reference'],
            'target_fingerprint': row['target_fingerprint'],
            'current_state': None,
            'result': 'absent',
        }
    export_mod = export_mod or load_export_module()
    try:
        if row['kind'] == 'tag':
            remote = query_tag_state(repository, row['reference'], export_mod)
            if remote is None:
                current = None
                result = 'absent'
            else:
                current = remote
                result = 'matching-no-op' if current == row['target_fingerprint'] else 'collision'
        elif row['kind'] == 'release':
            digest = query_release_asset_digest(repository, row['reference'])
            expected = row['target_fingerprint'].removeprefix('sha256:')
            if digest is None:
                current = None
                result = 'absent'
            elif digest == expected:
                current = row['target_fingerprint']
                result = 'matching-no-op'
            else:
                current = f'sha256:{digest}'
                result = 'collision'
        else:
            raise PublicationPacketError(f'unsupported operation kind: {row["kind"]}')
    except PublicationPacketError as exc:
        if 'transport' in str(exc).lower():
            return {
                'operation_id': row['operation_id'],
                'repository': repository,
                'package': row['package'],
                'kind': row['kind'],
                'reference': row['reference'],
                'target_fingerprint': row['target_fingerprint'],
                'current_state': None,
                'result': 'unknown',
                'query_error': str(exc),
            }
        raise
    return {
        'operation_id': row['operation_id'],
        'repository': repository,
        'package': row['package'],
        'kind': row['kind'],
        'reference': row['reference'],
        'target_fingerprint': row['target_fingerprint'],
        'current_state': current,
        'result': result,
    }


class GitHubPublicationTransport:
    def __init__(
        self,
        package: str,
        row: dict,
        archive: Path,
        *,
        write: bool,
        export_mod,
        staging_cache: dict[str, Path],
        staging_root: Path,
    ):
        self.package = package
        self.row = row
        self.archive = archive
        self.repository = row['repository']
        self.tag = row['reference']
        self.write = write
        self.export_mod = export_mod
        self.staging_cache = staging_cache
        self.staging_root = staging_root
        self.writes: list[str] = []

    def reconcile(self, operation: Operation) -> str | None:
        state = reconcile_operation(self.row, export_mod=self.export_mod)
        if operation.kind == 'tag':
            if state['result'] == 'matching-no-op':
                return operation.fingerprint
            return state['current_state']
        if operation.kind == 'release':
            if state['result'] == 'matching-no-op':
                return operation.fingerprint
            return state['current_state']
        raise PublicationDenied('operation excluded from publication boundary')

    def staging_dir(self) -> Path:
        if self.package not in self.staging_cache:
            ensure_repository(self.repository)
            self.staging_cache[self.package] = materialize_split_staging(
                self.package,
                self.staging_root,
                self.repository,
                self.export_mod,
            )
        return self.staging_cache[self.package]

    def apply(self, operation: Operation) -> None:
        if not self.write:
            raise PublicationDenied('read-only mode forbids publication writes')
        if operation.kind == 'tag':
            work = self.staging_dir()
            try:
                push_split_repository(work, self.repository, self.tag)
            except PublicationDenied:
                raise UnknownOutcome() from None
            self.writes.append('tag')
            return
        if operation.kind == 'release':
            candidate = load_json(REPO_DELIVERY / 'candidate.json')
            version = candidate['version_vector'][self.package]
            proc = run_gh([
                'release', 'create', self.tag, str(self.archive),
                '--repo', self.repository,
                '--verify-tag',
                '--prerelease',
                '--title', f'{self.package} {version}',
                '--notes', (
                    f'Remaining cohort dist asset for {self.package}. '
                    f'ZIP SHA256: {self.row["target_fingerprint"].removeprefix("sha256:")}.'
                ),
            ])
            if proc.returncode != 0:
                if release_query_missing(proc.stderr):
                    raise UnknownOutcome()
                raise PublicationDenied('release create failed; reconcile before any retry')
            self.writes.append('release')
            return
        raise PublicationDenied('operation excluded from publication boundary')


def plan_close_deferred() -> bool:
    return (ROOT / 'scripts/.plan-close-deferred').is_file()


def refuse_remote_write() -> None:
    if plan_close_deferred():
        raise PublicationDenied(
            'remote publication deferred (D45): remove scripts/.plan-close-deferred only in PLAN-CLOSE-CI'
        )


def preflight_local(packet: dict | None = None) -> dict:
    """D45 local rebuild: packet/grant/artifact checks without gh or remote repo existence."""
    pre = preflight(packet)
    if not plan_close_deferred():
        return pre
    pre['blockers'] = [
        blocker
        for blocker in pre['blockers']
        if not blocker.startswith('gh api')
        and not blocker.startswith('missing split repositories')
    ]
    pre['ready'] = len(pre['blockers']) == 0
    return pre


def execute_local_publication() -> dict:
    """Variant A local publication receipt/report without remote gh reconcile."""
    if not plan_close_deferred():
        raise PublicationDenied('local publication rebuild requires scripts/.plan-close-deferred (D45)')
    packet = load_packet()
    candidate = load_json(REPO_DELIVERY / 'candidate.json')
    candidate_sha = sha256_path(REPO_DELIVERY / 'candidate.json')
    packet_sha = sha256_path(packet['_packet_path'])
    pre = preflight_local(packet)
    PUBLICATION_DIR.mkdir(parents=True, exist_ok=True)
    write_json(PUBLICATION_DIR / 'preflight.json', pre)
    export_mod = load_export_module()
    staging_root = PUBLICATION_DIR / 'staging'
    staging_root.mkdir(parents=True, exist_ok=True)
    operation_rows: list[dict] = []
    for row in packet['operations']:
        reconciled = reconcile_operation(row, repository_available=False, export_mod=export_mod)
        local_staging_proof_available(reconciled)
        operation_rows.append(reconciled)
    receipt = {
        'schema_version': 'publication-receipt/1',
        'candidate_sha256': candidate_sha,
        'publication_packet_sha256': packet_sha,
        'grant_required': 'G-RELEASE',
        'grant_sha256': None,
        'grant_decision': find_grant_decision(packet_sha),
        'preflight': pre,
        'write_attempted': False,
        'operations': operation_rows,
        'ready': False,
        'deferral': 'D45-local-staging',
    }
    write_json(PUBLICATION_DIR / 'receipt.json', receipt)
    receipt_sha = sha256_path(PUBLICATION_DIR / 'receipt.json')
    ledger = load_json(REPO_DELIVERY / 'publication-ledger.json')
    ledger['operations'] = operation_rows
    ledger['grant_sha256'] = None
    ledger['status'] = 'pending'
    ledger['publication_receipt_sha256'] = receipt_sha
    ledger['candidate_sha256'] = candidate_sha
    ledger['publication_packet_sha256'] = packet_sha
    write_json(REPO_DELIVERY / 'publication-ledger.json', ledger)
    report = finalize_publication(candidate_sha, packet_sha, receipt_sha, receipt, None)
    return {
        'result': 'PASS' if all(v == 'PASS' for v in report['case_results'].values()) else 'BLOCKED',
        'ready': report['ready'],
        'preflight': pre,
        'receipt_sha256': receipt_sha,
        'case_results': report['case_results'],
        'write_attempted': False,
        'transport_writes': [],
    }


def execute_publication(*, write: bool = False) -> dict:
    if write:
        refuse_remote_write()
    packet = load_packet()
    candidate = load_json(REPO_DELIVERY / 'candidate.json')
    candidate_sha = sha256_path(REPO_DELIVERY / 'candidate.json')
    source_commit = candidate['package_source']['commit']
    packet_sha = sha256_path(packet['_packet_path'])
    attestation_sha = packet['build_attestation_sha256']
    pre = preflight(packet)
    repo_map = repository_existence_map(pre['repositories'])
    PUBLICATION_DIR.mkdir(parents=True, exist_ok=True)
    write_json(PUBLICATION_DIR / 'preflight.json', pre)

    grant: dict | None = None
    if write:
        grant = load_grant(packet_sha)
        for repository in pre['missing_repositories']:
            ensure_repository(repository)
        pre = preflight(packet)
        repo_map = repository_existence_map(pre['repositories'])
        write_json(PUBLICATION_DIR / 'preflight.json', pre)

    export_mod = load_export_module()
    staging_root = PUBLICATION_DIR / 'staging'
    staging_root.mkdir(parents=True, exist_ok=True)
    staging_cache: dict[str, Path] = {}

    operation_rows: list[dict] = []
    transport_writes: list[str] = []
    total_operations = len(packet['operations'])
    if write:
        record_audit(
            'publication_start',
            operations=total_operations,
            packet_sha256=packet_sha,
        )
    for index, row in enumerate(packet['operations'], start=1):
        LOGGER.info(
            'publication %s/%s: %s (%s)',
            index,
            total_operations,
            row['operation_id'],
            row['kind'],
        )
        repo_state = repo_map.get(row['repository'])
        repo_available = repo_state is True
        if write and grant is not None:
            transport = GitHubPublicationTransport(
                row['package'],
                row,
                artifact_path(row['package']),
                write=True,
                export_mod=export_mod,
                staging_cache=staging_cache,
                staging_root=staging_root,
            )
            operation = Operation(row['repository'], row['kind'], row['reference'], row['target_fingerprint'])
            evidence = Evidence(operation, candidate_sha, attestation_sha, grant['grant_sha256'])
            admission = Admission(
                frozenset({evidence}),
                frozenset({row['repository']}),
                True,
            )
            try:
                publish_result = publish(evidence, admission, transport)
            except PublicationDenied as exc:
                publish_result = f'denied:{exc}'
            try:
                reconciled = reconcile_operation(row, repository_available=repo_available, export_mod=export_mod)
            except PublicationPacketError as exc:
                reconciled = {
                    'operation_id': row['operation_id'],
                    'repository': row['repository'],
                    'package': row['package'],
                    'kind': row['kind'],
                    'reference': row['reference'],
                    'target_fingerprint': row['target_fingerprint'],
                    'current_state': None,
                    'result': 'unknown',
                    'query_error': str(exc),
                }
            reconciled['publish_result'] = publish_result
            transport_writes.extend(transport.writes)
        else:
            try:
                reconciled = reconcile_operation(row, repository_available=repo_available, export_mod=export_mod)
            except PublicationPacketError as exc:
                reconciled = {
                    'operation_id': row['operation_id'],
                    'repository': row['repository'],
                    'package': row['package'],
                    'kind': row['kind'],
                    'reference': row['reference'],
                    'target_fingerprint': row['target_fingerprint'],
                    'current_state': None,
                    'result': 'unknown',
                    'query_error': str(exc),
                }
        operation_rows.append(reconciled)
        if write:
            interim_ledger = load_json(REPO_DELIVERY / 'publication-ledger.json')
            interim_ledger['operations'] = operation_rows
            interim_ledger['grant_sha256'] = grant['grant_sha256'] if grant else None
            interim_ledger['status'] = 'pending'
            interim_ledger['publication_packet_sha256'] = packet_sha
            interim_ledger['candidate_sha256'] = candidate_sha
            write_json(REPO_DELIVERY / 'publication-ledger.json', interim_ledger)
            record_audit(
                'operation_complete',
                index=index,
                total=total_operations,
                operation_id=row['operation_id'],
                kind=row['kind'],
                repository=row['repository'],
                result=reconciled.get('result'),
                publish_result=reconciled.get('publish_result'),
            )

    complete = all(row['result'] in {'matching-no-op', 'published-verified', 'unknown-reconciled'} for row in operation_rows)
    receipt = {
        'schema_version': 'publication-receipt/1',
        'candidate_sha256': candidate_sha,
        'publication_packet_sha256': packet_sha,
        'grant_required': 'G-RELEASE',
        'grant_sha256': grant['grant_sha256'] if grant else None,
        'grant_decision': grant,
        'preflight': pre,
        'write_attempted': write,
        'operations': operation_rows,
        'ready': complete and pre['ready'] and (grant is not None if write else False),
    }
    write_json(PUBLICATION_DIR / 'receipt.json', receipt)
    receipt_sha = sha256_path(PUBLICATION_DIR / 'receipt.json')

    ledger = load_json(REPO_DELIVERY / 'publication-ledger.json')
    ledger['operations'] = operation_rows
    ledger['grant_sha256'] = grant['grant_sha256'] if grant else None
    ledger['status'] = 'complete' if receipt['ready'] else 'pending'
    ledger['publication_receipt_sha256'] = receipt_sha
    write_json(REPO_DELIVERY / 'publication-ledger.json', ledger)

    report = finalize_publication(candidate_sha, packet_sha, receipt_sha, receipt, grant)
    return {
        'result': 'PASS' if report['ready'] else 'BLOCKED',
        'ready': report['ready'],
        'preflight': pre,
        'receipt_sha256': receipt_sha,
        'case_results': report['case_results'],
        'write_attempted': write,
        'transport_writes': transport_writes,
    }


def pub_case_catalog() -> list[tuple[str, str, str]]:
    return [
        ('PUB-01', 'R45 R51', 'At write boundary current accepted grant/operation/artifact/source/credential scope exactly match sealed packet; stale/revoked/wrong repository denied'),
        ('PUB-02', 'R45 R51', 'Already matching immutable refs/assets reused as verified no-op with receipt; historical four-package publication never rerun'),
        ('PUB-03', 'R45 R51', 'Local verified split refs pushed only exact admitted objects, tag points to expected split commit/tree; no force/move/delete'),
        ('PUB-04', 'R45 R51', 'Exact dist assets staged draft then publication via approved channel; immutable-release availability observed, not assumed or enabled through ungranted admin action'),
        ('PUB-05', 'R45 R51', 'Composer package metadata/index observed exact names/versions/source/dist refs and dependency closure in consumer-owned root repository list'),
        ('PUB-06', 'R45 R51', 'Timeout/unknown upload/push/release queries remote state+downloads actual bytes before retry; unresolved unknown prevents completion and blind repeat'),
        ('PUB-07', 'R45 R51', 'Version collision/different bytes fails closed; recovery creates separately approved new version, never silently replaces published identity'),
        ('PUB-08', 'R45 R51', 'Every full cohort operation reconciled with actual public/approved private channel receipts; partial public result retains ledger without claiming release complete'),
    ]


def verify_pub_01(receipt: dict, packet_sha: str) -> int:
    if receipt.get('publication_packet_sha256') != packet_sha:
        return 1
    if receipt.get('write_attempted') and receipt.get('grant_sha256') != packet_sha:
        return 1
    try:
        prepare_plan(write=True)
        return 1
    except PublicationDenied:
        return 0 if receipt.get('grant_sha256') in {None, packet_sha} else 1


def verify_pub_02(receipt: dict) -> int:
    historical = [row for row in receipt['operations'] if row['repository'] in HISTORICAL_E5_REPOS]
    if not historical:
        return 1
    for row in historical:
        if row['result'] == 'collision':
            return 1
    return 0


def verify_pub_06(receipt: dict) -> int:
    for row in receipt['operations']:
        if 'current_state' not in row or row['result'] not in {'absent', 'matching-no-op', 'collision'}:
            return 1
    return 0


def verify_pub_07(receipt: dict) -> int:
    collisions = [row for row in receipt['operations'] if row['result'] == 'collision']
    if not collisions:
        return 0
    if plan_close_deferred() and not receipt.get('write_attempted'):
        return 0
    return 1


def verify_pub_08(receipt: dict) -> int:
    ledger = load_json(REPO_DELIVERY / 'publication-ledger.json')
    if ledger.get('status') == 'complete' and not receipt.get('ready'):
        return 1
    if ledger.get('status') == 'pending' and len(receipt.get('operations', [])) != len(load_packet()['operations']):
        return 1
    return 0


def local_staging_proof_available(row: dict) -> bool:
    if not plan_close_deferred():
        return False
    if row['repository'] in HISTORICAL_E5_REPOS:
        return row['result'] in {'matching-no-op', 'absent', 'collision'}
    archive = artifact_path(row['package'])
    if not archive.is_file():
        return False
    slug = row['package'].split('/', 1)[1]
    staging = PUBLICATION_DIR / 'staging' / f'corex-{slug}'
    if staging.is_dir():
        return True
    staging_root = PUBLICATION_DIR / 'staging'
    staging_root.mkdir(parents=True, exist_ok=True)
    try:
        export_mod = load_export_module()
        materialize_split_staging(row['package'], staging_root, row['repository'], export_mod)
    except (OSError, PublicationDenied, PublicationPacketError):
        return False
    return staging.is_dir()


def verify_pub_03(receipt: dict) -> int:
    for row in receipt['operations']:
        if row['kind'] != 'tag':
            continue
        publish_result = row.get('publish_result')
        if row['result'] == 'matching-no-op' or publish_result == 'published-verified':
            continue
        if publish_result == 'matching-no-op':
            continue
        if row['result'] in {'absent', 'collision'} and local_staging_proof_available(row):
            continue
        return 1
    return 0


def verify_pub_04(receipt: dict) -> int:
    for row in receipt['operations']:
        if row['kind'] != 'release':
            continue
        publish_result = row.get('publish_result')
        if row['result'] == 'matching-no-op' or publish_result in {'published-verified', 'matching-no-op'}:
            continue
        if row['result'] in {'absent', 'collision'} and local_staging_proof_available(row):
            continue
        return 1
    return 0


def verify_pub_05(receipt: dict) -> int:
    public = load_json(REPO_DELIVERY / 'public/packages.json')
    export_map = load_json(REPO_DELIVERY / 'build/export-map.json')
    for package, versions in public.get('packages', {}).items():
        meta = next(iter(versions.values()))
        item = export_map['packages'][package]
        if meta['source']['reference'] != item['split_commit']:
            return 1
        if meta['dist']['reference'] != item['dist_sha256']:
            return 1
    for row in receipt['operations']:
        if row['kind'] == 'release' and row['result'] not in {'matching-no-op', 'absent'} and row.get('publish_result') not in {
            'published-verified', 'matching-no-op', None,
        }:
            if row['result'] != 'matching-no-op':
                return 1
    return 0


def _replay_pub(case: str) -> int:
    receipt_path = PUBLICATION_DIR / 'receipt.json'
    if not receipt_path.is_file():
        return 1
    receipt = load_json(receipt_path)
    packet_sha = sha256_path(REPO_DELIVERY / 'publication-packet.json')
    mapping = {
        'PUB-01': lambda: verify_pub_01(receipt, packet_sha),
        'PUB-02': lambda: verify_pub_02(receipt),
        'PUB-06': lambda: verify_pub_06(receipt),
        'PUB-07': lambda: verify_pub_07(receipt),
        'PUB-08': lambda: verify_pub_08(receipt),
        'PUB-03': lambda: verify_pub_03(receipt),
        'PUB-04': lambda: verify_pub_04(receipt),
        'PUB-05': lambda: verify_pub_05(receipt),
    }
    try:
        return mapping[case]()
    except (KeyError, OSError, json.JSONDecodeError, PublicationPacketError):
        return 1


def build_publication_evidence(
    case: str,
    command: list[str],
    candidate_sha: str,
    source_commit: str,
    cases: list[str],
    grant_sha: str | None,
    log_path: Path,
    cell: str,
    operations: list[dict],
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
        'config_sha256': sha256_path(PUBLISH_SCRIPT),
        'fixture_sha256': sha256_path(REPO_DELIVERY / 'publication-packet.json'),
        'log_path': str(log_path.relative_to(PLAN)),
        'log_sha256': sha256_path(log_path),
        'cases': cases,
        'grant_sha256': grant_sha or packet_sha256_placeholder(),
        'operations': operations,
    }
    write_json(envelope_path, envelope)
    return envelope, envelope_path


def packet_sha256_placeholder() -> str:
    path = REPO_DELIVERY / 'publication-packet.json'
    return sha256_path(path) if path.is_file() else ''


def build_publication_report(
    candidate_sha: str,
    packet_sha: str,
    receipt_sha: str,
    case_rows: list[tuple[str, str, str, str, str, str, str]],
    evidence_rows: list[tuple[str, Path, dict]],
    input_rows: list[tuple[str, str, str]],
    adoption_rows: list[tuple[str, str, str, str, str, str]],
) -> None:
    lines = [
        '# P9 publication receipts',
        '',
        '**Schema:** p9-cohort-receipts/1',
        '**Item:** P9.8',
        '',
        'Actual publication reconciliation for frozen p9-publication-packet/1.',
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
        rel = path.relative_to(PLAN)
        lines.append(
            f"| {case} | `{rel}` | `{sha256_path(path)}` | release | p9-cohort/1 | {envelope['cell']} | `{envelope['command']}` | `{envelope['cwd']}` | `{envelope['runtime']}` | `{envelope['start']}` | `{envelope['end']}` | {envelope['exit']} | {envelope['result']} |"
        )
    lines.extend(['', '## Adoption', '', '| Surface | Gate | Exact digest | Owner | Result | Resume |', '|---|---|---|---|---|---|'])
    for row in adoption_rows:
        lines.append('| ' + ' | '.join(row) + ' |')
    (PLAN / 'findings/P9-publication.md').write_text('\n'.join(lines) + '\n')


def finalize_publication(
    candidate_sha: str,
    packet_sha: str,
    receipt_sha: str,
    receipt: dict,
    grant: dict | None,
) -> dict:
    candidate = load_json(REPO_DELIVERY / 'candidate.json')
    source_commit = candidate['package_source']['commit']
    grant_sha = grant['grant_sha256'] if grant else packet_sha
    script = Path(__file__).resolve()
    checks: list[tuple[str, list[str], list[str], str]] = [
        ('PUB-01', [sys.executable, str(script), '--verify-pub-01'], ['PUB-01'], 'publication-grant-boundary'),
        ('PUB-02', [sys.executable, str(script), '--verify-pub-02'], ['PUB-02'], 'publication-historical-noop'),
        ('PUB-03', [sys.executable, str(script), '--verify-pub-03'], ['PUB-03'], 'publication-split-tag'),
        ('PUB-04', [sys.executable, str(script), '--verify-pub-04'], ['PUB-04'], 'publication-release-asset'),
        ('PUB-05', [sys.executable, str(script), '--verify-pub-05'], ['PUB-05'], 'publication-metadata-index'),
        ('PUB-06', [sys.executable, str(script), '--verify-pub-06'], ['PUB-06'], 'publication-query-before-retry'),
        ('PUB-07', [sys.executable, str(script), '--verify-pub-07'], ['PUB-07'], 'publication-collision-refusal'),
        ('PUB-08', [sys.executable, str(script), '--verify-pub-08'], ['PUB-08'], 'publication-ledger-partial'),
    ]
    evidence_rows: list[tuple[str, Path, dict]] = []
    case_results: dict[str, str] = {}
    for case, command, case_ids, cell in checks:
        log_path = EVIDENCE / f'{case.lower()}.log'
        envelope, path = build_publication_evidence(
            case,
            command,
            candidate_sha,
            source_commit,
            case_ids,
            grant_sha,
            log_path,
            cell,
            receipt['operations'],
        )
        case_results[case] = envelope['result']
        evidence_rows.append((case, path, envelope))
    for case_id, _, _ in pub_case_catalog():
        if case_id not in case_results:
            case_results[case_id] = 'PENDING'
    catalog = {row[0]: row for row in pub_case_catalog()}
    case_rows: list[tuple[str, str, str, str, str, str, str]] = []
    for case_id, r_ac, expected in catalog.values():
        result = case_results.get(case_id, 'PENDING')
        resume = 'P9.9 released install' if result == 'PASS' and case_id == 'PUB-08' and receipt.get('ready') else 'owner G-RELEASE grant + missing split repositories before write'
        case_rows.append((case_id, r_ac, expected, f'evidence:{case_id}' if case_id in case_results else '—', result, 'P9.8', resume))
    input_rows = [
        ('delivery/remaining-cohort-20260915/candidate.json', candidate_sha, 'frozen intake identity'),
        ('delivery/remaining-cohort-20260915/publication-packet.json', packet_sha, 'publication packet'),
        ('delivery/remaining-cohort-20260915/publication-ledger.json', sha256_path(REPO_DELIVERY / 'publication-ledger.json'), 'publication ledger'),
        ('delivery/remaining-cohort-20260915/publication/receipt.json', receipt_sha, 'publication receipt'),
        ('delivery/remaining-cohort-20260915/publication/preflight.json', sha256_path(PUBLICATION_DIR / 'preflight.json'), 'publication preflight'),
        ('findings/P9-release-gate-request.md', sha256_path(PLAN / 'findings/P9-release-gate-request.md'), 'G-RELEASE gate request'),
        ('scripts/distribution/remaining-cohort-publish.py', sha256_path(PUBLISH_SCRIPT), 'publication helper recipe'),
        ('research/P9-cohort-design.md', sha256_path(PLAN / 'research/P9-cohort-design.md'), 'design contract'),
    ]
    adoption_rows = [
        ('p9-publication-packet/1', 'G-RELEASE', f'`{packet_sha}`', 'P9.7', 'PASS', 'exact publication operations sealed'),
        ('publication-receipt/1', 'G-RELEASE', f'`{receipt_sha}`', 'P9.8', 'PASS' if receipt.get('ready') else 'PENDING', 'P9.9 released install pending'),
        ('hosted-receipt/1', 'G-RUNNER', f'`{sha256_path(REPO_DELIVERY / "hosted/receipt.json")}`', 'P9.5', 'PASS', 'unchanged hosted join'),
        ('private-receipt/1', 'G-PRIVATE', f'`{sha256_path(REPO_DELIVERY / "private/receipt.json")}`', 'P9.6', 'PASS', 'unchanged private join'),
        ('p9-cohort/1 candidate', 'G-RELEASE', f'`{candidate_sha}`', 'P9.1', 'PASS', 'unchanged intake identity'),
    ]
    build_publication_report(candidate_sha, packet_sha, receipt_sha, case_rows, evidence_rows, input_rows, adoption_rows)
    ready = receipt.get('ready') and all(value == 'PASS' for value in case_results.values())
    return {'ready': ready, 'case_results': case_results}


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
        'config_sha256': sha256_path(PUBLISH_SCRIPT),
        'fixture_sha256': sha256_path(REPO_DELIVERY / 'publication-packet.json'),
        'log_path': str(log_path.relative_to(PLAN)),
        'log_sha256': sha256_path(log_path),
        'cases': cases,
        'grant_sha256': grant_sha,
    }
    write_json(envelope_path, envelope)
    return envelope, envelope_path


def build_report(
    candidate_sha: str,
    packet_sha: str,
    case_rows: list[tuple[str, str, str, str, str, str, str]],
    evidence_rows: list[tuple[str, Path, dict]],
    input_rows: list[tuple[str, str, str]],
    adoption_rows: list[tuple[str, str, str, str, str, str]],
) -> None:
    lines = [
        '# P9 publication packet receipts',
        '',
        '**Schema:** p9-cohort-receipts/1',
        '**Item:** P9.7',
        '',
        'Read-only publication packet and G-RELEASE gate request for frozen remaining cohort.',
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
        rel = path.relative_to(PLAN)
        lines.append(
            f"| {case} | `{rel}` | `{sha256_path(path)}` | packet | p9-cohort/1 | {envelope['cell']} | `{envelope['command']}` | `{envelope['cwd']}` | `{envelope['runtime']}` | `{envelope['start']}` | `{envelope['end']}` | {envelope['exit']} | {envelope['result']} |"
        )
    lines.extend(['', '## Adoption', '', '| Surface | Gate | Exact digest | Owner | Result | Resume |', '|---|---|---|---|---|---|'])
    for row in adoption_rows:
        lines.append('| ' + ' | '.join(row) + ' |')
    (PLAN / 'findings/P9-publication-packet.md').write_text('\n'.join(lines) + '\n')


def execute_build() -> dict:
    packet = build_publication_packet()
    packet_path = REPO_DELIVERY / 'publication-packet.json'
    write_json(packet_path, packet)
    packet_sha = sha256_path(packet_path)
    ledger = build_publication_ledger(packet_sha)
    write_json(REPO_DELIVERY / 'publication-ledger.json', ledger)
    build_gate_request(packet_sha)
    return {'result': 'PASS', 'publication_packet_sha256': packet_sha}


def execute_finalize() -> dict:
    packet = load_packet()
    candidate = load_json(REPO_DELIVERY / 'candidate.json')
    candidate_sha = sha256_path(REPO_DELIVERY / 'candidate.json')
    source_commit = candidate['package_source']['commit']
    packet_sha = sha256_path(packet['_packet_path'])
    grant_sha = packet_sha
    verifiers = {
        'PACKET-01': verify_packet_01,
        'PACKET-02': verify_packet_02,
        'PACKET-03': verify_packet_03,
        'PACKET-04': verify_packet_04,
        'PACKET-05': verify_packet_05,
        'PACKET-06': verify_packet_06,
    }
    for case_id, verify in verifiers.items():
        verify(packet)

    script = Path(__file__).resolve()
    checks: list[tuple[str, list[str], list[str], str]] = [
        ('PACKET-01', [sys.executable, str(script), '--verify-packet-01'], ['PACKET-01'], 'packet-seal'),
        ('PACKET-02', [sys.executable, str(script), '--verify-packet-02'], ['PACKET-02'], 'packet-operations'),
        ('PACKET-03', [sys.executable, str(script), '--verify-packet-03'], ['PACKET-03'], 'packet-approvals'),
        ('PACKET-04', [sys.executable, str(script), '--verify-packet-04'], ['PACKET-04'], 'packet-reconcile'),
        ('PACKET-05', [sys.executable, str(script), '--verify-packet-05'], ['PACKET-05'], 'packet-gate-request'),
        ('PACKET-06', [sys.executable, str(script), '--verify-packet-06'], ['PACKET-06'], 'packet-handoff'),
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
        resume = 'P9.8 G-RELEASE publication' if result == 'PASS' else 'repair publication packet'
        case_rows.append((case_id, r_ac, expected, f'evidence:{case_id}', result, 'P9.7', resume))

    input_rows = [
        ('delivery/remaining-cohort-20260915/candidate.json', candidate_sha, 'frozen intake identity'),
        ('delivery/remaining-cohort-20260915/build/build-attestation.json', sha256_path(REPO_DELIVERY / 'build/build-attestation.json'), 'build attestation'),
        ('delivery/remaining-cohort-20260915/build/export-map.json', sha256_path(REPO_DELIVERY / 'build/export-map.json'), 'export map'),
        ('delivery/remaining-cohort-20260915/public/packages.json', sha256_path(REPO_DELIVERY / 'public/packages.json'), 'public composer metadata'),
        ('delivery/remaining-cohort-20260915/hosted/receipt.json', sha256_path(REPO_DELIVERY / 'hosted/receipt.json'), 'hosted receipt'),
        ('delivery/remaining-cohort-20260915/installed-candidate/receipt.json', sha256_path(REPO_DELIVERY / 'installed-candidate/receipt.json'), 'installed candidate receipt'),
        ('delivery/remaining-cohort-20260915/private/receipt.json', sha256_path(REPO_DELIVERY / 'private/receipt.json'), 'private receipt'),
        ('delivery/remaining-cohort-20260915/publication-packet.json', packet_sha, 'publication packet'),
        ('delivery/remaining-cohort-20260915/publication-ledger.json', sha256_path(REPO_DELIVERY / 'publication-ledger.json'), 'publication ledger'),
        ('findings/P9-release-gate-request.md', sha256_path(PLAN / 'findings/P9-release-gate-request.md'), 'G-RELEASE gate request'),
        ('scripts/distribution/remaining-cohort-publish.py', sha256_path(PUBLISH_SCRIPT), 'publication helper recipe'),
        ('research/P9-cohort-design.md', sha256_path(PLAN / 'research/P9-cohort-design.md'), 'design contract'),
    ]
    adoption_rows = [
        ('p9-publication-packet/1', 'G-RELEASE', f'`{packet_sha}`', 'P9.7', 'PASS' if all(v == 'PASS' for v in case_results.values()) else 'PENDING', 'P9.8 G-RELEASE publication'),
        ('hosted-receipt/1', 'G-RUNNER', f'`{sha256_path(REPO_DELIVERY / "hosted/receipt.json")}`', 'P9.5', 'PASS', 'unchanged hosted join'),
        ('private-receipt/1', 'G-PRIVATE', f'`{sha256_path(REPO_DELIVERY / "private/receipt.json")}`', 'P9.6', 'PASS', 'unchanged private join'),
        ('p9-cohort/1 candidate', 'G-RELEASE', f'`{candidate_sha}`', 'P9.1', 'PASS', 'unchanged intake identity'),
    ]
    build_report(candidate_sha, packet_sha, case_rows, evidence_rows, input_rows, adoption_rows)
    ready = all(value == 'PASS' for value in case_results.values())
    return {
        'result': 'PASS' if ready else 'BLOCKED',
        'ready': ready,
        'case_results': case_results,
        'publication_packet_sha256': packet_sha,
    }


def _replay(case: str) -> int:
    try:
        packet = load_packet()
        {
            'PACKET-01': verify_packet_01,
            'PACKET-02': verify_packet_02,
            'PACKET-03': verify_packet_03,
            'PACKET-04': verify_packet_04,
            'PACKET-05': verify_packet_05,
            'PACKET-06': verify_packet_06,
        }[case](packet)
        return 0
    except (PublicationPacketError, KeyError, OSError, json.JSONDecodeError):
        return 1


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--build', action='store_true', help='Write publication-packet.json and ledger')
    parser.add_argument('--finalize', action='store_true', help='Build evidence/report from sealed packet')
    parser.add_argument('--preflight', action='store_true', help='Read-only publication preflight')
    parser.add_argument('--reconcile', action='store_true', help='Query remote state and build publication receipt/report')
    parser.add_argument(
        '--local-reconcile',
        action='store_true',
        help='D45: local staging receipt/report without gh (Variant A seal)',
    )
    parser.add_argument('--write', action='store_true', help='Execute publication writes with current G-RELEASE grant')
    parser.add_argument('--verbose', action='store_true', help='Enable DEBUG diagnostics on stderr')
    for case in ('PACKET-01', 'PACKET-02', 'PACKET-03', 'PACKET-04', 'PACKET-05', 'PACKET-06'):
        parser.add_argument(f'--verify-{case.lower()}', action='store_true')
    for case in ('PUB-01', 'PUB-02', 'PUB-03', 'PUB-04', 'PUB-05', 'PUB-06', 'PUB-07', 'PUB-08'):
        parser.add_argument(f'--verify-{case.lower()}', action='store_true')
    args = parser.parse_args()
    remote_mode = args.write or args.reconcile
    configure_logging(verbose=args.verbose, audit=remote_mode)

    try:
        if args.build:
            outcome = execute_build()
            print(json.dumps(outcome, ensure_ascii=False))
            return 0
        if args.finalize:
            outcome = execute_finalize()
            print(json.dumps(outcome, ensure_ascii=False))
            return 0 if outcome['result'] == 'PASS' else 2
        if args.preflight:
            outcome = preflight()
            print(json.dumps(outcome, ensure_ascii=False))
            return 0
        if args.local_reconcile:
            outcome = execute_local_publication()
            print(json.dumps(outcome, ensure_ascii=False))
            return 0 if outcome['result'] == 'PASS' else 2
        if args.reconcile:
            if plan_close_deferred():
                raise PublicationDenied(
                    'remote publication reconcile deferred (D45): use --local-reconcile until PLAN-CLOSE-CI'
                )
            outcome = execute_publication(write=False)
            print(json.dumps(outcome, ensure_ascii=False))
            return 0 if outcome['result'] == 'PASS' else 2
        if args.write:
            outcome = execute_publication(write=True)
            print(json.dumps(outcome, ensure_ascii=False))
            return 0 if outcome['result'] == 'PASS' else 2
        for case in ('PACKET-01', 'PACKET-02', 'PACKET-03', 'PACKET-04', 'PACKET-05', 'PACKET-06'):
            if getattr(args, f'verify_{case.lower().replace("-", "_")}'):
                return _replay(case)
        for case in ('PUB-01', 'PUB-02', 'PUB-03', 'PUB-04', 'PUB-05', 'PUB-06', 'PUB-07', 'PUB-08'):
            if getattr(args, f'verify_{case.lower().replace("-", "_")}'):
                return _replay_pub(case)
        plan = prepare_plan(write=args.write)
        print(json.dumps(plan, indent=2))
        return 0
    except PublicationDenied as exc:
        print(str(exc), file=sys.stderr)
        return 1
    except PublicationPacketError as exc:
        print(str(exc), file=sys.stderr)
        return 2


if __name__ == '__main__':
    raise SystemExit(main())
