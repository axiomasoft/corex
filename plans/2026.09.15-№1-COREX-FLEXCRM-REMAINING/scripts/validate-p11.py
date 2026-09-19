#!/usr/bin/env python3
"""Read-only PLAN1.P11 structure/identity checks; actual acceptance remains item-owned."""
from __future__ import annotations
import argparse
import gzip
import hashlib
import importlib.util
import json
from pathlib import Path
import re
import subprocess
import sys

loader = importlib.util.spec_from_file_location('p11_base', Path(__file__).with_name('validate-p4.py'))
if loader is None or loader.loader is None:
    raise ImportError('plan-local base checker missing')
a = importlib.util.module_from_spec(loader)
loader.loader.exec_module(a)
v = a.v
DESIGN = 'research/P11-reconciliation-design.md'
REPORTS = {
    'intake': ('INTAKE', 6, 'findings/P11-intake.md'),
    'pins': ('PINS', 7, 'findings/P11-consumer-pins.md'),
    'tests': ('TESTS', 7, 'findings/P11-consumer-tests.md'),
    'ledger': ('LEDGER', 8, 'findings/P11-dependency-ledger.md'),
    'packet': ('PACKET', 6, 'findings/P11-reconciliation-packet.md'),
    'write': ('WRITE', 6, 'findings/P11-consumer-write.md'),
    'seal': ('SEAL', 6, 'findings/P11-final-reconciliation.md'),
}
FOREIGN = {'plans/FLEX-2/findings/P6.5-corex-remaining-dependency-acceptance.md', 'plans/FLEX-2/findings/P6.5-delegate-progress.md', 'plans/FLEX-2/handoff.md'}


def wanted(stage):
    prefix, count, _ = REPORTS[stage]
    return {f'{prefix}-{i:02}' for i in range(1, count + 1)}


def input_file(plan, path):
    v.require(not Path(path).is_absolute(), 'nonportable input')
    for root in (plan, plan.parents[1]):
        target = (root / path).resolve()
        if target.is_relative_to(root.resolve()) and target.is_file():
            return target
    raise ValueError('missing portable producer input ' + path)


def design(plan):
    phase = a.file(plan, 'phases/P11/P11.md').read_text()
    v.require('СКЕЛЕТ — не детализирован' not in phase and 'Phase Assurance | v1' in phase, 'phase detail/assurance missing')
    specs = {x.name for x in (plan / 'phases/P11').glob('P11.*.md') if re.fullmatch(r'P11\.\d+\.md', x.name)}
    v.require(specs == {f'P11.{n}.md' for n in range(1, 8)}, 'seven exact specs required')
    all_prior = '\n'.join(x.read_text() for x in (plan / 'phases').glob('P*/P*.md') if re.fullmatch(r'P(?:[1-9]|10)\.\d+\.md', x.name))
    for n, stage in enumerate(REPORTS, 1):
        body = a.file(plan, f'phases/P11/P11.{n}.md').read_text()
        expected = a.FIELDS + (('Authority',) if n == 6 else ())
        v.require(tuple(re.findall(r'^\*\*([^*]+):\*\*', body, re.M)) == expected, 'stable field order/authority drift')
        for field in expected:
            match = re.search(r'\*\*' + re.escape(field) + r':\*\*\s*(.*?)(?=\n\*\*|\Z)', body, re.S)
            v.require(match is not None and match[1].strip() not in {'', '—'}, 'empty stable field')
        v.require('--check design' in body and 'decisions/D6.md' in body and '[NEEDS CLARIFICATION' not in body, 'design/neutral/unresolved contract gap')
        v.require(REPORTS[stage][2] in body.split('**Deliverables:**')[1] and all(x in body for x in wanted(stage)), 'output/case inventory gap')
        inputs = body.split('**Inputs:**')[1].split('**Files:**')[0]
        for path in re.findall(r'(?:findings/P(?:[1-9]|10)-[\w-]+\.md|delivery/[\w./-]+\.json|artifacts/P10-effective/[\w.-]+\.json)', inputs):
            v.require(path in all_prior, 'undeclared future producer ' + path)
        if n == 6:
            v.require('[OWNER-GATE:G-CONSUMER-RECONCILE]' in body and 'branch=`consumer-dependency-reconciliation`' in body and 'scope=`p11-consumer-reconciliation/1:repo-flexcrm:FLEX-2:three-file-packet`' in body, 'finite write authority missing')
        else:
            v.require('repo:flexcrm plans/' not in body.split('**Files:**')[1].split('**Required Reads:**')[0], 'foreign write outside protected item')
    inventory = a.table(plan, DESIGN, 'Required case IDs', ('Report', 'Required Case IDs'))
    v.exact(inventory, 'Report', {x[2] for x in REPORTS.values()})
    for stage, (_, _, report) in REPORTS.items():
        row = next(x for x in inventory if x['Report'] == report)
        ids = row['Required Case IDs'].split('; ')
        v.require(len(ids) == len(wanted(stage)) and set(ids) == wanted(stage), 'case count/identity drift')
    cases = a.table(plan, DESIGN, 'Case invariants', ('Case', 'Required observable result'))
    v.exact(cases, 'Case', set().union(*(wanted(x) for x in REPORTS)))
    v.require(len(cases) == 46, '46 distinct observable cases required')
    source = a.file(plan, DESIGN).read_text()
    v.require(set(re.findall(r'plans/FLEX-2/[\w./-]+\.md', source)) == FOREIGN, 'foreign allowlist drift')
    v.exact(a.table(plan, DESIGN, 'R / AC ownership', ('R', 'Producer', 'Cases / obligations', 'Consumer / gate')), 'R', {'R47', 'R41', 'R42', 'R52'})
    rag = a.file(plan, 'research/P11-design-rag.md').read_text()
    v.require('**Perplexity:** successful' in rag and '## Alternatives' in rag and '## Cross-phase impact' in rag, 'mandatory RAG incomplete')
    artifact = re.search(r'\*\*Search artifact:\*\* `([^`]+)`', rag)
    sha = re.search(r'\*\*SHA256:\*\* `([0-9a-f]{64})`', rag)
    v.require(artifact is not None and sha is not None and v.digest(a.file(plan, artifact[1])) == sha[1], 'RAG byte identity failed')
    a.evidence(plan, artifact[1])
    primary = a.table(plan, 'research/P11-design-rag.md', 'Primary sources', ('Origin', 'URL', 'Supported claim'))
    v.require(len({x['Origin'] for x in primary}) >= 2, 'independent primary origins missing')
    retrieval = json.loads(a.file(plan, 'artifacts/P11-design/primary-source-retrieval.json').read_text())
    v.require({x['url'] for x in retrieval} == {x['URL'] for x in primary}, 'direct source retrieval gap')
    for row in retrieval:
        raw = gzip.decompress(a.file(plan, row['artifact']).read_bytes())
        v.require(row['http'] == 200 and len(raw) == row['bytes'] and hashlib.sha256(raw).hexdigest() == row['sha256'], 'primary raw bytes invalid')
    history = json.loads(a.file(plan, 'artifacts/P11-design/retrieval-history.json').read_text())
    for row in history['queries']:
        v.require(not re.search(r'core\s*x|flex\s*crm|\bR\d{2}\b|DI-\d|AP-\d|packages/', row['query'], re.I), 'internal premise in external query')


def report(plan, stage):
    path = REPORTS[stage][2]
    v.require('p11-reconciliation-report/1' in a.file(plan, path).read_text(), 'stage report version missing')
    cases = a.table(plan, path, 'Cases', ('ID', 'Result', 'Expected', 'Actual', 'Evidence'))
    v.exact(cases, 'ID', wanted(stage))
    v.require(all(x['Result'] == 'PASS' and x['Actual'].strip() and x['Expected'].strip() for x in cases), 'missing observed PASS or assertion')
    inputs = a.table(plan, path, 'Inputs', ('Path', 'SHA256'))
    v.exact(inputs, 'Path', {x['Path'] for x in inputs})
    for row in inputs:
        v.require(v.digest(input_file(plan, row['Path'])) == row['SHA256'], 'stale report input')
    evidence = a.table(plan, path, 'Evidence', ('ID', 'Repo', 'Commit', 'Stage', 'Actor proof', 'Covered atoms', 'Artifact', 'SHA256', 'Command', 'Runtime', 'Start', 'End', 'Exit', 'Outcome'))
    v.exact(evidence, 'ID', {x['ID'] for x in evidence})
    by_id = {x['ID']: x for x in evidence}
    for row in cases:
        v.require(row['Evidence'] in by_id, 'missing raw evidence binding')
    for row in evidence:
        v.require(v.digest(a.file(plan, row['Artifact'])) == row['SHA256'] and re.fullmatch(r'-?\d+', row['Exit']) is not None and row['Outcome'] == 'PASS', 'evidence hash/exit/outcome mismatch')
        v.require(row['Stage'] != 'synthetic' or stage == 'intake', 'synthetic proof as actual acceptance')
    if stage in {'ledger', 'seal'}:
        rows = a.table(plan, path, 'Requirements', ('R', 'Original source', 'Disposition', 'Selected dependency atoms', 'Producer receipts', 'Consumer receipts', 'Result', 'Full original residual owner'))
        v.exact(rows, 'R', {f'R{i:02}' for i in range(1, 53)})
        atoms = a.table(plan, path, 'Selected atoms', ('Atom', 'R', 'Parent', 'Disposition', 'Owner', 'Required stages', 'Contract', 'Evidence', 'Result'))
        v.exact(atoms, 'Atom', {x['Atom'] for x in atoms})
        v.require(all(x['R'] != 'R47' and x['Result'] == 'PASS' for x in atoms), 'self-edge or unfinished dependency')
        # Full original atom-set/source/owner semantics are verified by P11.1 tool and owning executor.
    verify_prepared_report(plan, path)


def verify_prepared_report(plan, report_path):
    """Require the P11.1 verifier for execution reports; never run it for design."""
    tool = a.file(plan, 'scripts/reconcile-consumer.py').resolve()
    report_file = a.file(plan, report_path).resolve()
    try:
        result = subprocess.run(
            [sys.executable, '-B', str(tool), '--verify', str(report_file)],
            cwd=plan.resolve(), check=False, timeout=60,
        )
    except subprocess.TimeoutExpired as exc:
        raise ValueError('prepared consumer verifier timed out') from exc
    v.require(result.returncode == 0, 'prepared consumer verifier red')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--stage', choices=('design', *REPORTS), default='design')
    parser.add_argument('--plan-dir', type=Path, default=Path(__file__).resolve().parent.parent)
    args = parser.parse_args()
    try:
        design(args.plan_dir)
        if args.stage != 'design':
            report(args.plan_dir, args.stage)
        print('P11 structural identity check PASS; actual executed/accepting-owner/authority truth remains item-owned')
        return 0
    except (OSError, ValueError, KeyError, IndexError, json.JSONDecodeError) as exc:
        print('P11 check FAIL: ' + str(exc), file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
