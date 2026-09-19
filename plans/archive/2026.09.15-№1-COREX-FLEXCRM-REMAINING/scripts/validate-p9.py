#!/usr/bin/env python3
"""Read-only P9 design/evidence carrier checks; labels do not prove executed behavior."""
from __future__ import annotations

import argparse
import importlib.util
import json
from pathlib import Path
import re
import sys

loader = importlib.util.spec_from_file_location('p9_base', Path(__file__).with_name('validate-p4.py'))
if loader is None or loader.loader is None:
    raise ImportError('plan-local base checker missing')
a = importlib.util.module_from_spec(loader)
loader.loader.exec_module(a)
v = a.v
DESIGN = 'research/P9-cohort-design.md'
REPORTS = {
    'findings/P9-intake.md': ('INTAKE', 6),
    'findings/P9-artifacts.md': ('DIST', 8),
    'findings/P9-local-matrix.md': ('MATRIX', 7),
    'findings/P9-installed-candidate.md': ('INSTALL', 9),
    'findings/P9-hosted-runs.md': ('RUN', 7),
    'findings/P9-private-auth.md': ('PRIVATE', 6),
    'findings/P9-publication-packet.md': ('PACKET', 6),
    'findings/P9-publication.md': ('PUB', 8),
    'findings/P9-released-install.md': ('RELEASE', 7),
}
RIDS = {'R45', 'R51'}
GATES = {'P9.5': 'G-RUNNER', 'P9.6': 'G-PRIVATE', 'P9.8': 'G-RELEASE'}
CASE_FIELDS = ('Case', 'R/AC', 'Expected', 'Actual evidence', 'Result', 'Owner', 'Resume')
EVIDENCE_FIELDS = ('ID', 'Path', 'SHA256', 'Kind', 'Cohort', 'Cell', 'Command', 'Cwd', 'Runtime', 'Start', 'End', 'Exit', 'Result')


def wanted(report):
    prefix, count = REPORTS[report]
    return {f'{prefix}-{n:02}' for n in range(1, count + 1)}


def candidate(plan):
    phase = a.file(plan, 'phases/P9/P9.md').read_text()
    v.require('СКЕЛЕТ — не детализирован' not in phase and 'Phase Assurance | v1' in phase, 'P9 skeleton/assurance gap')
    specs = [x for x in (plan / 'phases/P9').glob('P9.*.md') if re.fullmatch(r'P9\.\d+\.md', x.name)]
    v.require({x.name for x in specs} == {f'P9.{n}.md' for n in range(1, 10)}, 'nine exact P9 specs required')
    for spec in specs:
        body = spec.read_text()
        fields = tuple(x for x in re.findall(r'^\*\*([^*]+):\*\*', body, re.M) if x != 'Authority')
        v.require(fields == a.FIELDS, 'ordered eleven stable fields: ' + spec.name)
        for field in a.FIELDS:
            found = re.search(r'\*\*' + re.escape(field) + r':\*\*\s*(.*?)(?=\n\*\*|\Z)', body, re.S)
            v.require(found is not None and found[1].strip() not in {'', '—'}, 'empty ' + spec.name + '/' + field)
        v.require('--check design' in body and 'decisions/D6.md' in body, 'D4/D6 missing')
        v.require('[NEEDS CLARIFICATION' not in body, 'unresolved internal outcome choice')
        gate = GATES.get(spec.stem)
        if gate:
            v.require('**Authority:** [OWNER-GATE:' + gate + ']' in body, 'finite protected Authority missing')
        else:
            v.require('**Authority:**' not in body, 'routine preparation incorrectly protected')
        reads = body.split('committed sources:')[1].split('**Implementation Rules:**')[0]
        for path in re.findall(r'(?:packages|docs|tests|delivery|scripts|\.github)/[\w./-]+', reads):
            v.require((plan.parents[1] / path.rstrip('.')).is_file(), 'missing current source ' + path)
        report = list(REPORTS)[int(spec.stem.split('.')[1]) - 1]
        v.require(report in body.split('**Deliverables:**')[1], 'future branch output missing')
        v.require(all(case in body for case in wanted(report)), 'case contract missing in spec')
    inventory = a.table(plan, DESIGN, 'Required case IDs', ('Report', 'Required Case IDs'))
    v.exact(inventory, 'Report', set(REPORTS))
    for row in inventory:
        ids = [x.strip() for x in row['Required Case IDs'].split(';')]
        v.require(len(ids) == len(wanted(row['Report'])) and set(ids) == wanted(row['Report']), 'case inventory drift')
    invariants = a.table(plan, DESIGN, 'Case invariants', ('Case', 'Required observable result'))
    v.exact(invariants, 'Case', set().union(*(wanted(r) for r in REPORTS)))
    v.require(len(invariants) == 64, '64 case invariants required')
    owners = a.table(plan, DESIGN, 'R / AC ownership', ('R', 'Producer', 'Cases / obligations', 'Consumer / gate'))
    v.exact(owners, 'R', RIDS)
    parents = a.table(plan, DESIGN, 'Parent AC ownership — originals retained', ('Parent', 'Generic owner', 'Generic slice / cases', 'Full original residual owner'))
    v.exact(parents, 'Parent', {f'B11 AC-{i}' for i in range(1, 33)})
    producers = a.table(plan, DESIGN, 'Future producer contracts', ('Path', 'Contract/version', 'Consumption'))
    v.exact(producers, 'Path', {x['Path'] for x in producers})
    for row in producers:
        v.require(any(row['Path'] in s.read_text().split('**Deliverables:**')[-1] for s in (plan / 'phases').glob('P*/P*.md') if re.fullmatch(r'P[1-8]\.\d+\.md', s.name)), 'invented predecessor ' + row['Path'])
    text = a.file(plan, DESIGN).read_text()
    for obligation in ('p9-cohort/1', 'p9-released-cohort/1', 'p9-publication-packet/1', 'source.reference', 'version vector', 'recipe', 'no-dev', 'check-platform-reqs', 'migrate:fresh', 'query-before-retry', 'Laravel12', 'Laravel13', 'P10/P11', 'nonrollback'):
        v.require(obligation in text, 'missing frozen obligation ' + obligation)
    publish = a.file(plan, 'phases/P9/P9.8.md').read_text().split('**Files:**')[1].split('**Required Reads:**')[0]
    prepare = a.file(plan, 'phases/P9/P9.3.md').read_text().split('**Files:**')[1].split('**Required Reads:**')[0]
    v.require('remaining-cohort-publish.py' in prepare and 'remaining-cohort-publish.py' not in publish, 'publication helper must be prepared before grant')


def design_ready(plan):
    candidate(plan)
    rag = a.file(plan, 'research/P9-design-rag.md').read_text()
    v.require('**Perplexity:** successful' in rag, 'mandatory RAG pending')
    response = re.search(r'\*\*Search artifact:\*\*\s*`([^`]+)`', rag)
    checksum = re.search(r'\*\*SHA256:\*\*\s*`([0-9a-f]{64})`', rag)
    v.require(response is not None and checksum is not None, 'retrieval path/hash absent')
    v.require(v.digest(a.file(plan, response[1])) == checksum[1], 'retrieval digest stale')
    for name in ('perplexity-response.json', 'perplexity-initial-response.json', 'perplexity-matrix-incomplete.json'):
        path = 'artifacts/P9-design/' + name
        a.evidence(plan, path)
        raw = json.loads(a.file(plan, path).read_text())
        v.require(v.digest(a.file(plan, path)) in rag, 'missing supplemental digest')
        v.require(not re.search(r'core\s*x|flex\s*crm|R\d{2}|DI-\d|AP-\d|packages/', raw['query'], re.I), 'internal context in external query')
    sources = a.table(plan, 'research/P9-design-rag.md', 'Primary sources', ('Origin', 'URL', 'Supported claim'))
    v.require(len({row['Origin'] for row in sources}) >= 2, 'independent primary origins absent')
    choices = a.table(plan, 'research/P9-design-rag.md', 'Choice / independent origin mapping', ('Choice', 'Independent origins', 'What remains local inference'))
    v.require(len(choices) == 6 and all(len({x.strip() for x in row['Independent origins'].split('+')}) >= 2 for row in choices), 'each choice needs two independent origins')
    v.require('## Alternatives' in rag and '## Cross-phase impact' in rag, 'alternatives/integration absent')
    direct = json.loads(a.file(plan, 'artifacts/P9-design/primary-source-retrieval.json').read_text())
    v.require(len(direct) == 13 and all(row['result'] == 'retrieved' for row in direct), 'primary retrieval pending')
    for row in direct:
        v.require(v.digest(a.file(plan, row['artifact'])) == row['compressed_sha256'], 'direct primary artifact stale')


def branch(plan, report):
    v.require(report in REPORTS, 'unknown P9 report')
    v.require('p9-cohort-receipts/1' in a.file(plan, report).read_text(), 'receipt version missing')
    rows = a.table(plan, report, 'Cases', CASE_FIELDS)
    v.exact(rows, 'Case', wanted(report))
    evidence = a.table(plan, report, 'Evidence', EVIDENCE_FIELDS)
    v.exact(evidence, 'ID', {x['ID'] for x in evidence})
    by_id = {row['ID']: row for row in evidence}
    for row in evidence:
        artifact = a.file(plan, row['Path'])
        v.require(v.digest(artifact) == row['SHA256'], 'actual evidence artifact stale')
        raw = json.loads(artifact.read_text())
        for column, key in [('ID', 'id'), ('Cohort', 'cohort'), ('Cell', 'cell'), ('Command', 'command'), ('Cwd', 'cwd'), ('Runtime', 'runtime'), ('Start', 'start'), ('End', 'end'), ('Exit', 'exit'), ('Result', 'result')]:
            v.require(str(raw.get(key)) == row[column], 'evidence envelope mismatch: ' + key)
        v.require(raw.get('schema_version') == 'p9-evidence/1' and raw.get('proof_class') == 'executed', 'actual executed evidence envelope missing')
        for key in ('source_commit', 'recipe_commit', 'candidate_sha256', 'lock_sha256', 'config_sha256', 'fixture_sha256', 'log_path', 'log_sha256', 'cases'):
            v.require(raw.get(key), 'missing exact evidence input ' + key)
        v.require(v.digest(a.file(plan, raw['log_path'])) == raw['log_sha256'], 'retained raw log stale')
        if report.endswith('hosted-runs.md'):
            v.require(row['Kind'] == 'hosted' and all(raw.get(k) for k in ('run_url', 'run_id', 'run_attempt', 'job_id', 'workflow_sha')), 'actual hosted envelope missing')
        if report.endswith('private-auth.md'):
            v.require(row['Kind'] == 'private' and raw.get('grant_sha256'), 'private source scope proof missing')
        if report.endswith('publication.md'):
            v.require(row['Kind'] == 'release' and raw.get('grant_sha256') and raw.get('operations'), 'actual publication scope/result missing')
        if report.endswith('released-install.md'):
            v.require(row['Kind'] == 'released-installed' and raw.get('delivery_urls') and raw.get('download_sha256'), 'actual released delivery proof missing')
    for row in rows:
        v.require(row['Result'] == 'PASS', 'required actual case not PASS')
        v.require(set(re.findall(r'R\d{2}', row['R/AC'])) and set(re.findall(r'R\d{2}', row['R/AC'])) <= RIDS, 'invalid requirement mapping')
        ids = re.findall(r'evidence:([\w.-]+)', row['Actual evidence'])
        v.require(ids and all(x in by_id for x in ids), 'case has no retained actual evidence ID')
        v.require(any(row['Case'] in json.loads(a.file(plan, by_id[x]['Path']).read_text())['cases'] for x in ids), 'case absent from actual evidence envelope')
    inputs = a.table(plan, report, 'Inputs', ('Path', 'SHA256', 'Role'))
    v.exact(inputs, 'Path', {x['Path'] for x in inputs})
    for row in inputs:
        v.require(row['Path'] != report and v.digest(a.resolve_input(plan, row['Path'])) == row['SHA256'], 'self/stale branch input')
    a.table(plan, report, 'Adoption', ('Surface', 'Gate', 'Exact digest', 'Owner', 'Result', 'Resume'))


def seal(plan):
    for report in REPORTS:
        branch(plan, report)
    path = 'findings/P9-cohort-receipt.md'
    v.require('p9-released-cohort/1' in a.file(plan, path).read_text(), 'released seal version missing')
    rows = a.table(plan, path, 'Requirements', ('R', 'Packages', 'Source', 'Test', 'Hosted', 'Release', 'Installed', 'Gates', 'Residual owner'))
    v.exact(rows, 'R', RIDS)
    edges = a.table(plan, path, 'Producer edges', ('Producer', 'Contract/version', 'Consumer', 'Required receipt', 'Failure model'))
    v.require({'P10', 'P11'} <= {row['Consumer'] for row in edges}, 'effective/consumer edge lost')
    inputs = a.table(plan, path, 'Sealed inputs', ('Path', 'SHA256'))
    v.exact(inputs, 'Path', {x['Path'] for x in inputs})
    v.require(set(REPORTS) | {DESIGN, 'findings/P9-compatibility-passport.md', 'delivery/remaining-cohort-20260915/public/released-cohort.json'} <= {x['Path'] for x in inputs}, 'released seal incomplete')
    for row in inputs:
        v.require(row['Path'] != path and v.digest(a.resolve_input(plan, row['Path'])) == row['SHA256'], 'self/stale released seal')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--stage', choices=('candidate', 'design', 'branches', 'seal'), required=True)
    parser.add_argument('--report')
    parser.add_argument('--plan-dir', type=Path, default=Path(__file__).resolve().parent.parent)
    args = parser.parse_args()
    try:
        candidate(args.plan_dir) if args.stage == 'candidate' else design_ready(args.plan_dir)
        if args.stage == 'branches':
            branch(args.plan_dir, args.report)
        if args.stage == 'seal':
            seal(args.plan_dir)
        print(json.dumps(dict(stage=args.stage, result='PASS', specs=9, requirements=2, case_contracts=64, parent_ownership=32, runtime_or_live_proof=False, semantic_acceptance='owning executor verifies actual assertions; structural check not execution proof')))
        return 0
    except (OSError, ValueError, KeyError, TypeError, IndexError, AttributeError) as exc:
        print('P9 validation FAIL: ' + str(exc), file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
