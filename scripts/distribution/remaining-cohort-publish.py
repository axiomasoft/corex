#!/usr/bin/env python3
"""Read-only-by-default publication helper for remaining-cohort split/dist assets."""
from __future__ import annotations

import argparse
import hashlib
import json
import sys
from pathlib import Path

from publish_guard import Admission, Evidence, Operation, PublicationDenied, publish

ROOT = Path(__file__).resolve().parents[2]
REPO_DELIVERY = ROOT / 'delivery/remaining-cohort-20260915'
PLAN = ROOT / 'plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING'


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


def build_operations(candidate: dict, export_map: dict) -> list[Operation]:
    operations: list[Operation] = []
    for package, item in export_map.get('packages', {}).items():
        repository = candidate['split_repositories'][package]['repository']
        tag = candidate['split_repositories'][package]['tag']
        operations.append(Operation(repository, 'tag', tag, item['split_commit']))
        operations.append(Operation(repository, 'release', tag, f"sha256:{item['dist_sha256']}"))
    return operations


def prepare_plan(*, write: bool = False) -> dict:
    candidate_path = REPO_DELIVERY / 'candidate.json'
    export_map_path = REPO_DELIVERY / 'build/export-map.json'
    attestation_path = REPO_DELIVERY / 'build/build-attestation.json'
    for path in (candidate_path, export_map_path, attestation_path):
        if not path.is_file():
            raise PublicationDenied(f'missing build artifact: {path}')

    candidate = load_json(candidate_path)
    export_map = load_json(export_map_path)
    attestation_sha = sha256_path(attestation_path)
    candidate_sha = sha256_path(candidate_path)

    operations = build_operations(candidate, export_map)
    rows = []
    transport = ReadOnlyTransport({})
    admission = Admission(frozenset(), frozenset(), False)

    for operation in operations:
        try:
            result = publish(
                Evidence(operation, candidate_sha, attestation_sha, 'read-only-no-grant'),
                admission,
                transport,
            )
        except PublicationDenied as exc:
            result = f'denied:{exc}'
        rows.append({
            'repository': operation.repository,
            'kind': operation.kind,
            'reference': operation.reference,
            'fingerprint': operation.fingerprint,
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


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--write', action='store_true', help='Refused without G-RELEASE grant')
    args = parser.parse_args()
    try:
        plan = prepare_plan(write=args.write)
        print(json.dumps(plan, indent=2))
        return 0
    except PublicationDenied as exc:
        print(str(exc), file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
