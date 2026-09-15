#!/usr/bin/env python3
"""Read-only P10 contract/envelope checks. Actual operator acceptance remains semantic."""
from __future__ import annotations

import argparse
import hashlib
import importlib.util
import json
from pathlib import Path
import re
import sys

loader = importlib.util.spec_from_file_location('p10_base', Path(__file__).with_name('validate-p4.py'))
if loader is None or loader.loader is None:
    raise ImportError('plan-local base checker missing')
a = importlib.util.module_from_spec(loader)
loader.loader.exec_module(a)
v = a.v
DESIGN = 'research/P10-effective-design.md'
REPORTS = {
    'findings/P10-intake.md': ('INTAKE', 6),
    'findings/P10-central-qualification.md': ('CENTRAL', 6),
    'findings/P10-cloud-qualification.md': ('CLOUD', 6),
    'findings/P10-security-acceptance.md': ('SECURITY', 6),
    'findings/P10-race-restore-acceptance.md': ('RACES', 6),
    'findings/P10-fe60-acceptance.md': ('FE', 6),
    'findings/P10-monthly-acceptance.md': ('MONTH', 7),
    'findings/P10-ops-acceptance.md': ('OPS', 10),
    'findings/P10-effective-receipts.md': ('SEAL', 6),
}


def wanted(report):
    prefix, count = REPORTS[report]
    return {f'{prefix}-{n:02}' for n in range(1, count + 1)}


def candidate(plan):
    body = a.file(plan, 'phases/P10/P10.md').read_text()
    v.require('СКЕЛЕТ — не детализирован' not in body and 'Phase Assurance | v1' in body, 'skeleton/assurance gap')
    names = {x.name for x in (plan / 'phases/P10').glob('P10.*.md') if re.fullmatch(r'P10\.\d+\.md', x.name)}
    v.require(names == {f'P10.{n}.md' for n in range(1, 10)}, 'nine exact item contracts required')
    for n, report in enumerate(REPORTS, 1):
        text = a.file(plan, f'phases/P10/P10.{n}.md').read_text()
        v.require(tuple(re.findall(r'^\*\*([^*]+):\*\*', text, re.M)) == a.FIELDS, 'ordered eleven stable fields')
        for field in a.FIELDS:
            match = re.search(r'\*\*' + re.escape(field) + r':\*\*\s*(.*?)(?=\n\*\*|\Z)', text, re.S)
            v.require(match is not None and match[1].strip() not in {'', '—'}, 'empty item field ' + field)
        v.require('**Authority:**' not in text, 'read-only import item must not authorize external operation')
        v.require('--check design' in text and 'decisions/D6.md' in text, 'full design/neutral policy missing')
        v.require('[NEEDS CLARIFICATION' not in text, 'unresolved internal contract choice')
        v.require(report in text.split('**Deliverables:**')[1] and all(x in text for x in wanted(report)), 'future output/case missing')
        reads = text.split('committed sources:')[1].split('**Implementation Rules:**')[0]
        for path in re.findall(r'(?:packages|docs|tests)/[\w./-]+', reads):
            v.require((plan.parents[1] / path.rstrip('.')).is_file(), 'missing committed source read ' + path)
    inventory = a.table(plan, DESIGN, 'Required case IDs', ('Report', 'Required Case IDs'))
    v.exact(inventory, 'Report', set(REPORTS))
    for row in inventory:
        ids = [x.strip() for x in row['Required Case IDs'].split(';')]
        v.require(len(ids) == len(wanted(row['Report'])) and set(ids) == wanted(row['Report']), 'case inventory drift')
    invariants = a.table(plan, DESIGN, 'Case invariants', ('Case', 'Required observable result'))
    v.exact(invariants, 'Case', set().union(*(wanted(r) for r in REPORTS)))
    v.require(len(invariants) == 59, '59 case contracts required')
    originals = a.file(plan, 'research/03-gates.md').read_text()
    sub_ids = set()
    for kind, count in [('DI', 34), ('AP', 13)]:
        columns = (kind, 'Original required scope', 'Original SHA256', 'Owner item', 'Subconditions')
        rows = a.table(plan, DESIGN, kind + ' originals — literal owner requirements', columns)
        v.exact(rows, kind, {f'{kind}-{n:02}' for n in range(1, count + 1)})
        for row in rows:
            pattern = r'^\| ' + row[kind] + r' \| ' + (r'\*\*UNFILLED\*\* \| ' if kind == 'DI' else '') + r'([^|]+) \|'
            match = re.search(pattern, originals, re.M)
            v.require(match is not None and match[1].strip() == row['Original required scope'], 'original condition silently changed')
            v.require(hashlib.sha256(row['Original required scope'].encode()).hexdigest() == row['Original SHA256'], 'original hash mismatch')
            ids = [x.strip() for x in row['Subconditions'].split(';')]
            v.require(ids and len(ids) == len(set(ids)) and all(re.fullmatch(re.escape(row[kind]) + r'\.\d+', x) for x in ids), 'subcondition identity gap')
            sub_ids.update(ids)
    rows = a.table(plan, DESIGN, 'Subcondition invariants', ('Subcondition', 'Owner item', 'Required stage', 'Observable obligation'))
    v.exact(rows, 'Subcondition', sub_ids)
    v.require(len(rows) == 169, '169 exact subconditions required')
    for row in rows:
        v.require(re.fullmatch(r'P10\.[2-7]', row['Owner item']) and 'accepted+effective' in row['Required stage'], 'missing actual target stage/owner')
        v.require(not row['Subcondition'].startswith('AP') or 'executed' in row['Required stage'], 'AP actual execution missing')
    parents = a.table(plan, DESIGN, 'Parent AC ownership — originals retained', ('Parent', 'Qualification owner', 'Dependency slice / cases', 'Full original residual owner'))
    v.exact(parents, 'Parent', {f'B33 AC-{n}' for n in range(1, 16)} | {f'B34 AC-{n}' for n in range(1, 16)} | {'B34 AC-19'})
    owners = a.table(plan, DESIGN, 'R / AC ownership', ('R', 'Producer', 'Cases / obligations', 'Consumer / gate'))
    v.exact(owners, 'R', {'R39', 'R40', 'R46'})
    producer_rows = a.table(plan, DESIGN, 'Future producer contracts', ('Path', 'Contract/version', 'Consumption'))
    specs = [x.read_text() for x in (plan / 'phases').glob('P*/P*.md') if re.fullmatch(r'P[1-9]\.\d+\.md', x.name)]
    for row in producer_rows:
        v.require(any(row['Path'] in text.split('**Deliverables:**')[-1] for text in specs), 'invented future predecessor ' + row['Path'])
    text = a.file(plan, DESIGN).read_text()
    for key in ('p10-effective-receipts/1', 'p10-consumer-input/1', 'p10-external-evidence/1', 'no-data', '+infinity', 'ceil(0.999*N)', 'FE-60', 'rollback set', 'query-before-retry', 'all nine items plan-exec', 'no Authority', '600', '300', '200DB', 'P11'):
        v.require(key in text, 'missing frozen boundary ' + key)
    prepare = a.file(plan, 'phases/P10/P10.1.md').read_text().split('**Files:**')[1].split('**Required Reads:**')[0]
    v.require('effective-acceptance.py' in prepare and 'fixtures/P10-evidence-contract' in prepare, 'verifier/refusal fixtures not prepared before acceptance')
    for n in range(2, 10):
        files = a.file(plan, f'phases/P10/P10.{n}.md').read_text().split('**Files:**')[1].split('**Required Reads:**')[0]
        v.require('effective-acceptance.py' not in files, 'acceptance must reuse qualified prepared verifier')
    finish = a.file(plan, 'scripts/task-check.py').read_text()
    for path in (DESIGN, 'decisions/D15-effective-acceptance.md', 'scripts/validate-p10.py'):
        v.require(path in finish, 'indirect finish anchor missing ' + path)


def design(plan):
    candidate(plan)
    rag = a.file(plan, 'research/P10-design-rag.md').read_text()
    v.require('**Perplexity:** successful' in rag, 'mandatory RAG pending')
    path = re.search(r'\*\*Search artifact:\*\*\s*`([^`]+)`', rag)
    checksum = re.search(r'\*\*SHA256:\*\*\s*`([0-9a-f]{64})`', rag)
    v.require(path is not None and checksum is not None and v.digest(a.file(plan, path[1])) == checksum[1], 'stale/missing retrieval digest')
    for name in ('perplexity-initial-response.json', 'perplexity-response.json', 'perplexity-ops-response.json'):
        artifact = 'artifacts/P10-design/' + name
        a.evidence(plan, artifact)
        raw = json.loads(a.file(plan, artifact).read_text())
        v.require(v.digest(a.file(plan, artifact)) in rag, 'missing retrieval digest')
        v.require(not re.search(r'core\s*x|flex\s*crm|R\d{2}|DI-\d|AP-\d|packages/', raw['query'], re.I), 'internal external query')
    primary = a.table(plan, 'research/P10-design-rag.md', 'Primary sources', ('Origin', 'URL', 'Supported claim'))
    by_origin = {row['Origin'] for row in primary}
    choices = a.table(plan, 'research/P10-design-rag.md', 'Choice / independent origin mapping', ('Choice', 'Independent origins', 'What remains local inference'))
    v.require(len(choices) == 6, 'six key choice comparisons required')
    for row in choices:
        origins = {x.strip() for x in row['Independent origins'].split('+')}
        v.require(len(origins) >= 2 and origins <= by_origin, 'two direct independent origins per choice required')
    v.require('## Alternatives' in rag and '## Cross-phase impact' in rag, 'alternatives/integration missing')
    retrieval = json.loads(a.file(plan, 'artifacts/P10-design/primary-source-retrieval.json').read_text())
    v.require({row['URL'] for row in primary} <= {row['url'] for row in retrieval}, 'primary direct verification missing')
    for row in retrieval:
        v.require(row['result'] == 'retrieved' and v.digest(a.file(plan, row['artifact'])) == row['compressed_sha256'], 'primary retrieval stale/failed')


def input_file(plan, path):
    v.require(not Path(path).is_absolute(), 'nonportable input')
    for root in (plan, plan.parents[1]):
        target = (root / path).resolve()
        if target.is_relative_to(root.resolve()) and target.is_file():
            return target
    raise ValueError('missing portable producer input ' + path)


def branch(plan, report):
    v.require(report in REPORTS, 'unknown branch report')
    body = a.file(plan, report).read_text()
    v.require('p10-qualification/1' in body, 'branch schema version missing')
    cases = a.table(plan, report, 'Cases', ('Case', 'R/AC', 'Expected', 'Actual evidence', 'Result', 'Owner', 'Resume'))
    v.exact(cases, 'Case', wanted(report))
    evidence = a.table(plan, report, 'Evidence', ('ID', 'Path', 'SHA256', 'Kind', 'Environment', 'Cohort', 'Command', 'Start', 'End', 'Result'))
    v.exact(evidence, 'ID', {x['ID'] for x in evidence})
    by_id = {}
    for row in evidence:
        file = a.file(plan, row['Path'])
        v.require(v.digest(file) == row['SHA256'], 'evidence digest stale')
        raw = json.loads(file.read_text())
        v.require(raw.get('schema_version') == 'p10-external-evidence/1' or (report == list(REPORTS)[0] and raw.get('schema_version') == 'p10-local-intake-evidence/1'), 'wrong evidence class')
        for key in ('id', 'proof_class', 'accepting_actor', 'owner_receipt_path', 'owner_receipt_sha256', 'owning_repository', 'source_commit', 'source_tree', 'artifact_sha256', 'config_sha256', 'profile_sha256', 'schema_sha256', 'fixture_sha256', 'cohort_sha256', 'released_version_vector', 'environment_id', 'command', 'cwd', 'runtime', 'start', 'end', 'log_path', 'log_sha256', 'assertions', 'evidence_inputs'):
            v.require(raw.get(key), 'missing source/env/actor/raw envelope ' + key)
        v.require(v.digest(a.file(plan, raw['log_path'])) == raw['log_sha256'], 'retained raw log stale')
        v.require(v.digest(a.file(plan, raw['owner_receipt_path'])) == raw['owner_receipt_sha256'], 'accepting provenance stale')
        for item in raw['evidence_inputs']:
            v.require(item['Path'] != row['Path'] and v.digest(a.file(plan, item['Path'])) == item['SHA256'], 'self/stale evidence input')
        v.require(raw.get('proof_class') in {'executed', 'effective', 'accepted', 'source'}, 'invalid stage')
        if report != list(REPORTS)[0]:
            v.require(re.fullmatch(r'artifacts/P10-external/[0-9a-f]{64}\.json', row['Path']), 'nonfinite external import')
            v.require(raw.get('deployed_release') and raw.get('registration_sha256'), 'missing actual deployed registration')
            v.require(file.stem == row['SHA256'] and raw.get('origin_sha256') and raw.get('sanitization'), 'content identity/sanitization not bound')
        for column, key in [('ID', 'id'), ('Kind', 'proof_class'), ('Environment', 'environment_id'), ('Cohort', 'cohort_sha256'), ('Command', 'command'), ('Start', 'start'), ('End', 'end')]:
            v.require(str(raw.get(key)) == row[column], 'table/raw identity mismatch ' + key)
        v.require('observed_exit' in raw and row['Result'] == 'PASS', 'observed result missing/red')
        by_id[row['ID']] = raw
    for row in cases:
        v.require(row['Result'] == 'PASS', 'required case not PASS')
        ids = re.findall(r'evidence:([\w.-]+)', row['Actual evidence'])
        v.require(ids and all(x in by_id for x in ids), 'case not bound to actual evidence')
        v.require(any(any(x.get('condition') == row['Case'] and all(x.get(k) for k in ('expected', 'observed', 'assertion')) and x.get('result') == 'PASS' for x in by_id[e]['assertions']) for e in ids), 'raw actual assertion missing')
    if report not in (list(REPORTS)[0], list(REPORTS)[7], list(REPORTS)[8]):
        subconditions = a.table(plan, DESIGN, 'Subcondition invariants', ('Subcondition', 'Owner item', 'Required stage', 'Observable obligation'))
        number = list(REPORTS).index(report) + 1
        needed = {r['Subcondition'] for r in subconditions if r['Owner item'] == f'P10.{number}'}
        rows = a.table(plan, report, 'Conditions', ('Subcondition', 'Stage', 'Evidence IDs', 'Result', 'Owner', 'Resume'))
        v.exact(rows, 'Subcondition', needed)
        for row in rows:
            v.require(row['Result'] == 'PASS' and 'accepted' in row['Stage'] and 'effective' in row['Stage'], 'condition not accepted/effective')
            v.require(not row['Subcondition'].startswith('AP') or 'executed' in row['Stage'], 'AP not executed')
            ids = re.findall(r'evidence:([\w.-]+)', row['Evidence IDs'])
            v.require(ids and all(x in by_id for x in ids), 'subcondition evidence missing')
            v.require(any(any(x.get('condition') == row['Subcondition'] and x.get('result') == 'PASS' and all(x.get(k) for k in ('expected', 'observed', 'assertion')) for x in by_id[e]['assertions']) for e in ids), 'subcondition raw assertion absent')
    inputs = a.table(plan, report, 'Inputs', ('Path', 'SHA256', 'Role'))
    v.exact(inputs, 'Path', {x['Path'] for x in inputs})
    for row in inputs:
        v.require(row['Path'] != report and v.digest(input_file(plan, row['Path'])) == row['SHA256'], 'self/stale branch input')
    a.table(plan, report, 'Acceptance', ('Owner', 'Receipt', 'SHA256', 'Covered', 'Result'))
    # Domain timings/provider are verified by the prepared operator verifier with raw assertions.
    tool = a.file(plan, 'scripts/effective-acceptance.py')
    import subprocess
    result = subprocess.run([sys.executable, str(tool), '--verify', str(a.file(plan, report))], check=False)
    v.require(result.returncode == 0, 'prepared domain verifier red')


def seal(plan):
    for report in REPORTS:
        branch(plan, report)
    path = list(REPORTS)[-1]
    v.require('p10-effective-receipts/1' in a.file(plan, path).read_text(), 'effective seal missing')
    rows = a.table(plan, path, 'Requirements', ('R', 'Released cohort', 'Effective targets', 'DI', 'AP', 'Ops', 'Receipt', 'Result', 'Residual owner'))
    v.exact(rows, 'R', {'R39', 'R40', 'R46'})
    v.require(all(row['Result'] == 'PASS' for row in rows), 'required R effective not GREEN')
    rows = a.table(plan, path, 'Conditions', ('Subcondition', 'Stage', 'Evidence IDs', 'Result', 'Owner', 'Resume'))
    invariants = a.table(plan, DESIGN, 'Subcondition invariants', ('Subcondition', 'Owner item', 'Required stage', 'Observable obligation'))
    v.require({x['Subcondition'] for x in invariants} <= {x['Subcondition'] for x in rows} and all(x['Result'] == 'PASS' for x in rows), 'all-subcondition seal incomplete/red')
    rows = a.table(plan, path, 'Sealed inputs', ('Path', 'SHA256'))
    v.exact(rows, 'Path', {x['Path'] for x in rows})
    v.require(set(REPORTS) - {path} | {DESIGN, 'findings/P9-cohort-receipt.md', 'scripts/effective-acceptance.py'} <= {r['Path'] for r in rows}, 'final seal inputs incomplete')
    for row in rows:
        v.require(row['Path'] != path and v.digest(input_file(plan, row['Path'])) == row['SHA256'], 'self/stale effective seal')
    result = json.loads(a.file(plan, 'artifacts/P10-effective/dependency-receipts.json').read_text())
    v.require(result.get('schema_version') == 'p10-consumer-input/1' and result.get('required_gaps') == [] and result.get('released_version_vector') and result.get('effective_targets'), 'no actual complete consumer input')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--stage', choices=('candidate', 'design', 'branch', 'seal'), required=True)
    parser.add_argument('--report')
    parser.add_argument('--plan-dir', type=Path, default=Path(__file__).resolve().parent.parent)
    args = parser.parse_args()
    try:
        candidate(args.plan_dir) if args.stage == 'candidate' else design(args.plan_dir)
        if args.stage == 'branch':
            branch(args.plan_dir, args.report)
        if args.stage == 'seal':
            seal(args.plan_dir)
        print(json.dumps(dict(stage=args.stage, result='PASS', specs=9, di=34, ap=13, subconditions=169, case_contracts=59, original_parents=31, runtime_or_live_proof=False)))
        return 0
    except (OSError, ValueError, KeyError, TypeError, IndexError, AttributeError) as exc:
        print('P10 validation FAIL: ' + str(exc), file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
