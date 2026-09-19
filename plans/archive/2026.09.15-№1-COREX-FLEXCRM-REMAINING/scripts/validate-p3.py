#!/usr/bin/env python3
"""Read-only P3 plan/report structure and hashes; semantic conformance remains item-owned."""
from __future__ import annotations

import argparse
import hashlib
import importlib.util
import json
from pathlib import Path
import re
import sys

spec = importlib.util.spec_from_file_location('validate_p1', Path(__file__).with_name('validate-p1.py'))
if spec is None or spec.loader is None:
    raise ImportError('plan-local validate-p1.py unavailable')
v = importlib.util.module_from_spec(spec)
spec.loader.exec_module(v)
REPORTS = {
    'findings/P3-storage-context.md': {'R01'},
    'findings/P3-claim-maintenance.md': {'R02', 'R33'},
    'findings/P3-identity-transport.md': {'R03', 'R04'},
    'findings/P3-durable-projection.md': {'R05'},
    'findings/P3-final-write.md': {'R06'},
    'findings/P3-account-list.md': {'R07'},
}
R_IDS = {f'R{i:02}' for i in range(1, 8)} | {'R33'}
FIELDS = ('Intent', 'Why', 'Scope Included', 'Scope Excluded', 'Inputs', 'Files', 'Required Reads', 'Implementation Rules', 'Code Guidance', 'Validation', 'Deliverables')
CASE_FIELDS = ('Case', 'R/AC', 'Adapter/profile', 'Given/fault', 'Expected', 'Actual evidence', 'Result', 'Limit/owner/resume')
ADOPTION_FIELDS = ('Surface', 'Baseline/proposal digest', 'Local supported mode', 'Protected receipt', 'Registration', 'Remaining P10 gate')


def file(plan: Path, path: str) -> Path:
    value = (plan / path).resolve()
    v.require(value.is_relative_to(plan.resolve()) and value.is_file(), f'missing or unsafe input: {path}')
    return value


def fields(plan: Path, path: str, heading: str, columns: tuple[str, ...]):
    result = v.table(file(plan, path), heading, columns)
    v.require(result, f'{path}: empty {heading}')
    for row in result:
        v.filled(row, *columns)
    return result


def evidence(plan: Path, path: str):
    result = json.loads(file(plan, path).read_text())
    v.require(result.get('isError') is not True, f'{path}: retrieval error')
    content = ' '.join(x.get('text', '') for x in result.get('content', []) if isinstance(x, dict))
    v.require(len(content) > 200 and 'No answer found' not in content, f'{path}: empty/failed retrieval')


def design(plan: Path):
    phase = file(plan, 'phases/P3/P3.md').read_text()
    v.require('СКЕЛЕТ — не детализирован' not in phase, 'P3 skeleton')
    actual = {x.name for x in (plan / 'phases/P3').glob('P3.*.md') if re.fullmatch(r'P3\.\d+\.md', x.name)}
    v.require(actual == {f'P3.{i}.md' for i in range(1, 8)}, 'seven P3 specs required')
    for name in sorted(actual):
        body = file(plan, 'phases/P3/' + name).read_text()
        v.require(tuple(re.findall(r'^\*\*([^*]+):\*\*', body, re.M)) == FIELDS, f'{name}: ordered eleven fields')
        for field in FIELDS:
            match = re.search(r'\*\*' + re.escape(field) + r':\*\*\s*(.*?)(?=\n\*\*|\Z)', body, re.S)
            v.require(match is not None and match[1].strip() not in {'', '—'}, f'{name}: empty {field}')
        v.require('decisions/D6.md' in body and '--check design' in body, f'{name}: D4/D6 missing')
        v.require('[NEEDS CLARIFICATION' not in body, f'{name}: unresolved internal choice')
        reads = body.split('**Required Reads:**')[1].split('**Implementation Rules:**')[0]
        for path in re.findall(r'(?:packages|docs)/[\w./*-]+', reads):
            if '*' not in path:
                v.require((plan.parents[1] / path.rstrip('.')).is_file(), f'{name}: missing source read {path}')
        for path in re.findall(r'findings/P1-[\w-]+\.md', body):
            known = {'findings/P1-requirements-lock.md', 'findings/P1-acceptance-map.md'}
            v.require(path in known, f'{name}: undeclared P1 producer filename {path}')
    rows = fields(plan, 'research/P3-adapter-design.md', 'R / AC ownership', ('R', 'Producer', 'Cases / obligations', 'Consumer / gate'))
    v.exact(rows, 'R', R_IDS)
    report = file(plan, 'research/P3-design-rag.md').read_text()
    v.require('**Perplexity:** successful' in report, 'mandatory RAG pending')
    artifact = re.search(r'\*\*Search artifact:\*\*\s*`([^`]+)`', report)
    checksum = re.search(r'\*\*SHA256:\*\*\s*`([0-9a-f]{64})`', report)
    v.require(artifact is not None and checksum is not None, 'RAG path/digest missing')
    v.require(v.digest(file(plan, artifact[1])) == checksum[1], 'RAG digest mismatch')
    evidence(plan, artifact[1])
    evidence(plan, 'artifacts/P3-design/perplexity-response.transport.json')
    transport_hash = v.digest(file(plan, 'artifacts/P3-design/perplexity-response.transport.json'))
    v.require(transport_hash in report, 'transport RAG digest mismatch')
    for path in ('artifacts/P3-design/perplexity-query.txt', 'artifacts/P3-design/perplexity-query.transport.txt'):
        query = file(plan, path).read_text()
        v.require(not re.search(r'core\s*x|flex\s*crm|R\d{2}|DI-\d|AP-\d|packages/', query, re.I), 'internal context in external query')
    sources = fields(plan, 'research/P3-design-rag.md', 'Primary sources', ('Origin', 'URL', 'Supported claim'))
    v.require(len({x['Origin'] for x in sources}) >= 2, 'independent primary origins absent')
    choices = fields(plan, 'research/P3-design-rag.md', 'Choice / independent origin mapping', ('Choice', 'Independent origins', 'What remains local inference'))
    v.require(len(choices) == 5, 'major choice evidence mapping absent')
    v.require('## Alternatives' in report and '## Cross-phase impact' in report, 'RAG alternatives/impact missing')
    anchors = fields(plan, 'research/P3-adapter-design.md', 'Seal inputs', ('Path', 'Role'))
    expected = set(REPORTS) | {'findings/P2-contract-catalogue.md', 'research/P3-adapter-design.md', 'research/03-gates.md', 'research/05-integrated-design.md', 'decisions/D8.md', 'phases/P3/P3.md'} | {f'phases/P3/P3.{i}.md' for i in range(1, 8)}
    v.exact(anchors, 'Path', expected)
    inventory = fields(plan, 'research/P3-adapter-design.md', 'Required local case IDs', ('Report', 'Required Case IDs'))
    v.exact(inventory, 'Report', set(REPORTS))
    for row in inventory:
        ids = [x.strip() for x in row['Required Case IDs'].split(';')]
        v.require(len(ids) == 7 and len(set(ids)) == 7, 'seven unique required local cases per branch')
    for seam in ('ObjectTenantStorage', 'FinalWriteAdmission', 'RedisAccountMenuCache', 'StrictAccountsTransport', 'CREATE TABLE tnt_membership_pairs', 'CREATE TABLE tnt_membership_inbox'):
        v.require(seam in file(plan, 'research/P3-adapter-design.md').read_text(), f'missing frozen candidate {seam}')


def branches(plan: Path, path: str):
    v.require(path in REPORTS, 'unknown branch report')
    body = file(plan, path).read_text()
    v.require('p3-adapter-receipts/1' in body, 'branch version absent')
    rows = fields(plan, path, 'Cases', CASE_FIELDS)
    v.exact(rows, 'Case', {r['Case'] for r in rows})
    inventory = {r['Report']: r['Required Case IDs'] for r in fields(plan, 'research/P3-adapter-design.md', 'Required local case IDs', ('Report', 'Required Case IDs'))}
    required = {x.strip() for x in inventory[path].split(';')}
    by_case = {r['Case']: r for r in rows}
    v.require(required <= set(by_case), 'required local case missing')
    v.require(all(by_case[x]['Result'] == 'PASS' for x in required), 'required local case not PASS')
    seen = set()
    for row in rows:
        linked = set(re.findall(r'R\d{2}', row['R/AC']))
        v.require(linked and linked <= R_IDS, 'case R mapping invalid')
        seen |= linked
        v.require(row['Result'] in {'PASS', 'FAIL', 'UNSUPPORTED', 'PENDING'}, 'unknown case result')
        # Structural checker cannot decide whether UNSUPPORTED/PENDING is required local scope.
        # That decision remains semantic Validation: required local case must be PASS.
        if row['Result'] == 'PASS':
            v.require(re.search(r'[0-9a-f]{64}', row['Actual evidence']), 'PASS needs exact proof digest')
    v.require(REPORTS[path] <= seen, 'branch requirement lost')
    inputs = fields(plan, path, 'Inputs', ('Path', 'SHA256', 'Role'))
    v.exact(inputs, 'Path', {r['Path'] for r in inputs})
    root = plan.parents[1]
    for row in inputs:
        target = (root / row['Path']).resolve() if row['Path'].startswith(('packages/', 'docs/', 'scripts/')) else file(plan, row['Path'])
        v.require(target.is_relative_to(root.resolve()) and target.is_file(), 'input outside repository')
        v.require(v.digest(target) == row['SHA256'], 'branch stale input hash')
    fields(plan, path, 'Adoption', ADOPTION_FIELDS)


def seal(plan: Path):
    for report in REPORTS:
        branches(plan, report)
    path = 'findings/P3-adapter-receipts.md'
    rows = fields(plan, path, 'Requirements', ('R', 'Items', 'Covered local cases', 'Installed contract', 'Pending effective conditions', 'Owner', 'Resume'))
    v.exact(rows, 'R', R_IDS)
    edges = fields(plan, path, 'Producer edges', ('Producer', 'Contract/version', 'Consumer', 'Required receipt', 'Failure model'))
    v.require({'P4', 'P6', 'P9', 'P10', 'P11'} <= {r['Consumer'] for r in edges}, 'consumer edge lost')
    expected = {r['Path'] for r in fields(plan, 'research/P3-adapter-design.md', 'Seal inputs', ('Path', 'Role'))}
    anchors = fields(plan, path, 'Sealed inputs', ('Path', 'SHA256'))
    v.exact(anchors, 'Path', expected)
    for row in anchors:
        v.require(v.digest(file(plan, row['Path'])) == row['SHA256'], 'stale P3 seal')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--stage', choices=('design', 'branches', 'seal'), required=True)
    parser.add_argument('--report')
    parser.add_argument('--plan-dir', type=Path, default=Path(__file__).resolve().parent.parent)
    args = parser.parse_args()
    try:
        design(args.plan_dir)
        if args.stage == 'branches':
            branches(args.plan_dir, args.report)
        if args.stage == 'seal':
            seal(args.plan_dir)
        print(json.dumps({'stage': args.stage, 'result': 'PASS', 'specs': 7, 'requirements': 8, 'semantic_acceptance': 'item-owned; checker cannot substitute executed proof', 'runtime_or_live_proof': False}))
        return 0
    except (OSError, ValueError, KeyError, TypeError) as exc:
        print(f'P3 validation FAIL: {exc}', file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
