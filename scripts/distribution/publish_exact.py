#!/usr/bin/env python3
"""D17-bound two-provider publisher; default mode reconciles without writes."""
from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path
import subprocess
import sys

from publish_guard import Admission, Evidence, Operation, PublicationDenied, UnknownOutcome, publish

ROOT = Path(__file__).resolve().parents[2]
PLAN = ROOT / 'plans/2026.09.12-№2-COREX-APPROVED-SPECS'
D17_SHA = '62a488fdf07be354b4f40ee66e266e44fc93d7acb919649427df102d5eb7c364'
SOURCE_SHA = '4faf08b013afa4a3020d6666e2e00054698f639f201aa27806fd4f500139146f'
SPEC_SHA = '26351a7c882100288695510af46cea5ee49b26dc933e2c980651e82c2ee74c94'


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def run(parts, *, cwd=None, allow_missing=False):
    result = subprocess.run(parts, cwd=cwd, text=True, capture_output=True)
    if result.returncode:
        if allow_missing and ('HTTP 404' in result.stderr
                              or 'HTTP 409' in result.stderr
                              and 'Git Repository is empty' in result.stderr):
            return None
        # Never persist arbitrary transport stderr: it may contain credentials.
        raise PublicationDenied(f'transport failed ({result.returncode}); reconcile before any retry')
    return result.stdout


def api(path, *, allow_missing=False):
    raw = run(['gh', 'api', path], allow_missing=allow_missing)
    return json.loads(raw) if raw is not None else None


class GitHubTransport:
    def __init__(self, label, record, staging, *, write):
        self.record = record
        self.repository = record['name']
        self.local = staging / label
        self.archive = PLAN / 'findings/P4.1-source' / f'{label}-candidate.zip'
        self.tag = record['tag_proposal']
        self.write = write
        self.writes = []

    def reconcile(self, operation):
        repository = self.repository
        if operation.kind == 'create':
            value = api(f'repos/{repository}', allow_missing=True)
            if value is None:
                return None
            return (operation.fingerprint if value['full_name'] == repository
                    and not value['private'] and not value['archived'] else 'collision')
        if operation.kind == 'actions-disable':
            return 'disabled' if not api(f'repos/{repository}/actions/permissions')['enabled'] else None
        if operation.kind in {'push', 'tag'}:
            ref = 'heads/main' if operation.kind == 'push' else f'tags/{self.tag}'
            value = api(f'repos/{repository}/git/ref/{ref}', allow_missing=True)
            return value['object']['sha'] if value else None
        value = api(f'repos/{repository}/releases/tags/{self.tag}', allow_missing=True)
        if value is None:
            return None
        assets = value['assets']
        if (value['draft'] or not value['prerelease'] or len(assets) != 1
                or assets[0]['name'] != self.archive.name):
            return 'collision'
        return assets[0].get('digest') or 'missing-asset-digest'

    def apply(self, operation):
        if not self.write:
            raise PublicationDenied('read-only mode forbids missing-result writes')
        repository = self.repository
        if operation.kind == 'create':
            command = ['gh', 'repo', 'create', repository, '--public']
        elif operation.kind == 'actions-disable':
            command = ['gh', 'api', '-X', 'PUT', f'repos/{repository}/actions/permissions', '-F', 'enabled=false']
        elif operation.kind in {'push', 'tag'}:
            if api(f'repos/{repository}/actions/permissions')['enabled']:
                raise PublicationDenied('Actions must be disabled before push')
            ref = 'refs/heads/main' if operation.kind == 'push' else f'refs/tags/{self.tag}'
            command = ['git', 'push', f'https://github.com/{repository}.git', f'{ref}:{ref}']
        else:
            command = ['gh', 'release', 'create', self.tag, str(self.archive), '--repo', repository,
                       '--verify-tag', '--prerelease', '--title', f'{repository} {self.record["version"]}',
                       '--notes', f'Sealed P4.1 candidate {self.record["candidate_commit_sha1"]}. ZIP SHA256: {self.record["candidate_zip_sha256"]}. Actions disabled; no hosted-runner proof claimed.']
        self.writes.append(operation.kind)
        try:
            run(command, cwd=self.local)
        except PublicationDenied:
            raise UnknownOutcome() from None


def execute(staging, *, write=False):
    marketplaces = json.loads(run(['codex', 'plugin', 'marketplace', 'list', '--json']))
    runtimes = [Path(row['root']) / 'packages/task/lib'
                for row in marketplaces.get('marketplaces', [])
                if row.get('name') == 'swissknifeman'
                and (Path(row['root']) / 'packages/task/lib/task').is_dir()]
    if len(runtimes) != 1:
        raise PublicationDenied('unique configured Task runtime unavailable')
    sys.path.insert(0, str(runtimes[0]))
    from task import task_contract, route_evidence, plan_bundle
    for path, expected in ((PLAN / 'decisions/D17-p53-bounded-remediation.md', D17_SHA),
                           (PLAN / 'findings/P4.1-source/source-map.json', SOURCE_SHA),
                           (PLAN / 'phases/P5/P5.3.md', SPEC_SHA)):
        if digest(path) != expected:
            raise PublicationDenied('sealed authority/source/spec digest changed')
    plan_bundle.build(PLAN, 'P5.3', check=True)
    route = route_evidence.capture_launch(expected_invocation='$ task:plan-run '+PLAN.name+' P5.3', expected_cwd=ROOT)
    contract = task_contract.build(PLAN, 'P5.3', actual_route=route)
    if task_contract.pre_mutation(contract) is not None:
        raise PublicationDenied('canonical authority/route admission denied')
    if contract['authority']['granted_by'] != 'decisions/D17:1 [OWNER-GRANT:PUBLICATION-REMEDIATION]':
        raise PublicationDenied('D17 authority binding changed')
    if api('user')['login'] != 'academici':
        raise PublicationDenied('operator identity changed')
    source = json.loads((PLAN / 'findings/P4.1-source/source-map.json').read_bytes())
    rows = []
    for label in ('jobpipeline', 'tenancy'):
        record = source['packages'][label]
        transport = GitHubTransport(label, record, staging, write=write)
        if digest(transport.archive) != record['candidate_zip_sha256']:
            raise PublicationDenied('sealed ZIP changed')
        if run(['git', 'rev-parse', 'main'], cwd=transport.local).strip() != record['candidate_commit_sha1']:
            raise PublicationDenied('local candidate commit changed')
        if run(['git', 'rev-parse', 'main^{tree}'], cwd=transport.local).strip() != record['candidate_tree_sha1']:
            raise PublicationDenied('local candidate tree changed')
        tag_object = run(['git', 'rev-parse', f'refs/tags/{transport.tag}'], cwd=transport.local).strip()
        if run(['git', 'rev-parse', f'{transport.tag}^{{commit}}'], cwd=transport.local).strip() != record['candidate_commit_sha1']:
            raise PublicationDenied('local candidate tag changed')
        operations = [Operation(record['name'], 'create', record['name'], 'public:'+record['name']),
                      Operation(record['name'], 'actions-disable', 'actions/permissions', 'disabled'),
                      Operation(record['name'], 'push', 'refs/heads/main', record['candidate_commit_sha1']),
                      Operation(record['name'], 'tag', 'refs/tags/'+transport.tag, tag_object),
                      Operation(record['name'], 'release', transport.tag, 'sha256:'+record['candidate_zip_sha256'])]
        evidence = [Evidence(op, SOURCE_SHA, record['candidate_zip_sha256'], D17_SHA) for op in operations]
        admission = Admission(frozenset(evidence), frozenset({record['name']}), False,
                              credential_exception=frozenset(evidence))
        for proof in evidence:
            result = publish(proof, admission, transport)
            row = {'repository':record['name'], 'kind':proof.operation.kind,
                   'reference':proof.operation.reference,'fingerprint':proof.operation.fingerprint,
                   'result':result,'authority_sha256':D17_SHA}
            rows.append(row)
            print(json.dumps(row), flush=True)
        print(json.dumps({'repository':record['name'],'write_kinds':transport.writes}), flush=True)
    return rows


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--staging', type=Path, required=True)
    parser.add_argument('--write', action='store_true')
    args = parser.parse_args()
    try:
        execute(args.staging, write=args.write)
    except PublicationDenied:
        print('publication stopped: exact admission or reconciled result failed; no automatic retry', file=sys.stderr)
        raise SystemExit(1)
