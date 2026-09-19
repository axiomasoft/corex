#!/usr/bin/env python3
"""Read-only P8 carrier/digest checker; execution and gate consent remain owner-proved."""
from __future__ import annotations
import argparse
import importlib.util
import json
from pathlib import Path
import re
import sys

loader = importlib.util.spec_from_file_location('p4_base', Path(__file__).with_name('validate-p4.py'))
if loader is None or loader.loader is None:
    raise ImportError('plan-local base checker missing')
a = importlib.util.module_from_spec(loader)
loader.loader.exec_module(a)
v = a.v
REPORTS = {
    'findings/P8-source-qualification.md': ('QUAL', 5),
    'findings/P8-descriptors.md': ('DESC', 9),
    'findings/P8-catalogue-policy.md': ('CAT', 9),
    'findings/P8-invocation.md': ('INVOKE', 12),
    'findings/P8-introspection.md': ('INTRO', 7),
    'findings/P8-contributions.md': ('CONTRIB', 8),
    'findings/P8-installed.md': ('INST', 6),
}
RIDS = {'R08', 'R36', 'R50'}
DESIGN = 'research/P8-tool-design.md'
CASE_FIELDS = ('Case', 'R/AC', 'Expected', 'Actual evidence', 'Result', 'Owner', 'Resume')
ADOPTION_FIELDS = ('Surface', 'Gate', 'Exact digest', 'Owner', 'Result', 'Resume')


def candidate(plan):
    phase = a.file(plan, 'phases/P8/P8.md').read_text()
    v.require('СКЕЛЕТ — не детализирован' not in phase and 'Phase Assurance | v1' in phase, 'P8 skeleton/assurance missing')
    specs = [f for f in (plan / 'phases/P8').glob('P8.*.md') if re.fullmatch(r'P8\.\d+\.md', f.name)]
    v.require({f.name for f in specs} == {f'P8.{i}.md' for i in range(1, 8)}, 'seven exact specs required')
    for spec in specs:
        body = spec.read_text()
        v.require(tuple(re.findall(r'^\*\*([^*]+):\*\*', body, re.M)) == a.FIELDS, 'ordered eleven stable fields: '+spec.name)
        for field in a.FIELDS:
            match = re.search(r'\*\*'+re.escape(field)+r':\*\*\s*(.*?)(?=\n\*\*|\Z)', body, re.S)
            v.require(match is not None and match[1].strip() not in {'', '—'}, 'empty '+spec.name+'/'+field)
        v.require('--check design' in body and 'decisions/D6.md' in body, 'D4/D6 missing')
        v.require('[NEEDS CLARIFICATION' not in body, 'unresolved internal architectural choice')
        reads = body.split('committed sources:')[1].split('**Implementation Rules:**')[0]
        for path in re.findall(r'(?:packages|docs|tests|delivery)/[\w./-]+', reads):
            v.require((plan.parents[1]/path.rstrip('.')).is_file(), 'missing committed source '+path)
        for path in re.findall(r'findings/P[1-7]-[\w-]+\.md', body):
            producers = [x for x in (plan/'phases').glob('P*/P*.md') if re.fullmatch(r'P[1-7]\.\d+\.md', x.name)]
            v.require(any(path in x.read_text().split('**Deliverables:**')[-1] for x in producers), 'undeclared future producer '+path)
    ownership = a.table(plan, DESIGN, 'R / AC ownership', ('R', 'Producer', 'Cases / obligations', 'Consumer / gate'))
    v.exact(ownership, 'R', RIDS)
    rows = a.table(plan, DESIGN, 'Required local case IDs', ('Report', 'Required Case IDs'))
    v.exact(rows, 'Report', set(REPORTS))
    required = set()
    for row in rows:
        prefix, count = REPORTS[row['Report']]
        ids = [x.strip() for x in row['Required Case IDs'].split(';')]
        want = {f'{prefix}-{i:02}' for i in range(1, count+1)}
        v.require(len(ids) == count and set(ids) == want, 'case inventory drift')
        required |= want
    invariants = a.table(plan, DESIGN, 'Case invariants', ('Case', 'Required observable result'))
    v.exact(invariants, 'Case', required)
    v.require(len(invariants) == 56, '56 unique observable cases required')
    parents = a.table(plan, DESIGN, 'Parent AC ownership — originals retained', ('Parent', 'Generic owner', 'Generic slice / cases', 'Full original residual owner'))
    v.exact(parents, 'Parent', {f'B32 AC-{i}' for i in range(1,16)} | {f'B10 AC-{i}' for i in range(1,35)})
    tool_rows = a.table(plan, DESIGN, 'R08 control-plane tool ownership — no tenant auto-exposure', ('Canonical source name', 'Effect', 'Source', 'Owner', 'Generic disposition'))
    expected = set()
    for src, start, end in [('b33',648,670), ('b34',605,624)]:
        lines = a.file(plan, f'brief/sources/{src}.md').read_text().splitlines()[start-1:end]
        expected |= {m[1] for line in lines if (m := re.match(r'- `([^`]+)`', line))}
    v.exact(tool_rows, 'Canonical source name', expected)
    v.require(len(expected) == 19 and all('external' in row['Generic disposition'] for row in tool_rows), 'external19 plane map drift')
    text = a.file(plan, DESIGN).read_text()
    for seam in ('Api::tool', 'Api::exposeTool', 'ToolCatalogue::discover', 'ToolInvoker::invoke', 'ToolExecutionGuard::execute', 'ToolRequestContext', 'RegistryIntrospection::extensionPoints', 'tool-schema/1', 'tool-name/1', 'same subschema', 'mathematically integral', 'MCP2025-11-25', 'committed+output_contract_failed', 'validation-only subprocess'):
        v.require(seam in text, 'frozen seam missing: '+seam)
    v.require(DESIGN in a.file(plan, 'scripts/task-check.py').read_text(), 'stable indirect finish input missing')
    for path in (plan/'artifacts/P8-design').glob('perplexity-query*.txt'):
        v.require(not re.search(r'core\s*x|flex\s*crm|\bR\d{2}\b|\b(?:DI|AP)-\d|\bpackages/', path.read_text(), re.I), 'internal context in external query')


def design_ready(plan):
    candidate(plan)
    rag = a.file(plan, 'research/P8-design-rag.md').read_text()
    v.require('**Perplexity:** successful' in rag, 'mandatory Perplexity pending')
    for path in ('artifacts/P8-design/perplexity-response.json', 'artifacts/P8-design/perplexity-response.schema.json'):
        a.evidence(plan, path)
        v.require(v.digest(a.file(plan, path)) in rag, 'retrieval digest mismatch')
    sources = a.table(plan, 'research/P8-design-rag.md', 'Primary sources', ('Origin','URL','Supported claim'))
    v.require(len({x['Origin'] for x in sources}) >= 2 and all(x['URL'].startswith('https://') for x in sources), 'independent primary sources missing')
    choices = a.table(plan, 'research/P8-design-rag.md', 'Choice / independent origin mapping', ('Choice','Independent origins','What remains local inference'))
    v.require(len(choices) == 6 and all(len({s.strip() for s in row['Independent origins'].split('+')}) >= 2 for row in choices), 'independent choice mapping incomplete')
    v.require('## Alternatives' in rag and '## Cross-phase impact' in rag, 'alternatives/integrated check missing')
    v.require('**Status:** accepted' in a.file(plan, 'decisions/D13-tool-introspection.md').read_text(), 'D13 candidate not accepted')


def branch(plan, path):
    v.require(path in REPORTS, 'unknown branch')
    v.require('p8-tool-receipts/1' in a.file(plan, path).read_text(), 'report schema missing')
    prefix, count = REPORTS[path]
    rows = a.table(plan, path, 'Cases', CASE_FIELDS)
    v.exact(rows, 'Case', {f'{prefix}-{i:02}' for i in range(1,count+1)})
    anchors = a.table(plan, path, 'Inputs', ('Path','SHA256','Role'))
    v.exact(anchors, 'Path', {row['Path'] for row in anchors})
    digests = set()
    for row in anchors:
        v.require(v.digest(a.resolve_input(plan, row['Path'])) == row['SHA256'], 'changed report/log input '+row['Path'])
        digests.add(row['SHA256'])
    for row in rows:
        ids = set(re.findall(r'R\d{2}', row['R/AC']))
        v.require(bool(ids) and ids <= RIDS, 'invalid R ownership')
        found = set(re.findall(r'[0-9a-f]{64}', row['Actual evidence']))
        v.require(row['Result'] == 'PASS' and bool(found) and found <= digests, 'case lacks actual bound PASS log')
    for row in a.table(plan, path, 'Adoption', ADOPTION_FIELDS):
        v.require(row['Gate'].startswith('G-') and bool(re.fullmatch(r'[0-9a-f]{64}',row['Exact digest'])), 'invalid adoption reference')
        v.require(row['Result'] in {'PASS','not-applicable'}, 'unfulfilled adoption surface')
    # These structural checks do not authenticate owner consent or execute case evidence.


def seal(plan):
    for path in REPORTS:
        branch(plan, path)
    path = 'findings/P8-tool-receipts.md'
    v.require('p8-tool-receipts/1' in a.file(plan, path).read_text(), 'seal schema missing')
    requirements = a.table(plan, path, 'Requirements', ('R','Items','Local case receipts','Installed receipts','Pending effective gates','Owner','Resume'))
    v.exact(requirements, 'R', RIDS)
    edges = a.table(plan, path, 'Producer edges', ('Producer','Contract/version','Consumer','Required receipt','Failure model'))
    v.require({'P9','P10','P11'} <= {row['Consumer'] for row in edges}, 'downstream edges missing')
    contributions = a.table(plan, path, 'Contributions', ('R','Tool tuple','Owner','Plane','Producer path','Producer hash','Contract hash','Case evidence','Disposition'))
    for row in contributions:
        v.require(bool(set(re.findall(r'R\d{2}',row['R'])) & RIDS), 'unknown contribution requirement')
        v.require(row['Disposition'] in {'PASS','external-owner'}, 'required contribution pending')
        v.require(v.digest(a.resolve_input(plan,row['Producer path'])) == row['Producer hash'], 'changed producer contribution')
        v.require(bool(re.fullmatch(r'[0-9a-f]{64}',row['Contract hash'])), 'contract hash missing')
        if row['Disposition'] == 'external-owner':
            v.require(row['Plane'] == 'control-plane', 'external ownership must remain control-plane')
    anchors = a.table(plan, path, 'Sealed inputs', ('Path','SHA256'))
    v.exact(anchors, 'Path', {row['Path'] for row in anchors})
    required = set(REPORTS) | {DESIGN, 'research/P8-design-rag.md','decisions/D13-tool-introspection.md','research/05-integrated-design.md','research/03-gates.md','phases/P8/P8.md','delivery/tool-install-qualification-20260915/receipt.md','findings/P1-requirements-lock.md','findings/P1-acceptance-map.md','findings/P1-evidence-ledger.md','findings/P2-contract-catalogue.md','findings/P2-contract-proposals.md','findings/P2-consumer-cases.md','findings/P2-gate-requests.md'} | {f'phases/P8/P8.{i}.md' for i in range(1,8)} | {row['Producer path'] for row in contributions}
    v.require(required <= {row['Path'] for row in anchors}, 'seal required input missing')
    for row in anchors:
        v.require(v.digest(a.resolve_input(plan,row['Path'])) == row['SHA256'], 'stale sealed input '+row['Path'])


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--stage',choices=('candidate','design','branches','seal'),required=True)
    parser.add_argument('--report')
    parser.add_argument('--plan-dir',type=Path,default=Path(__file__).resolve().parent.parent)
    args = parser.parse_args()
    try:
        candidate(args.plan_dir) if args.stage == 'candidate' else design_ready(args.plan_dir)
        if args.stage == 'branches':
            branch(args.plan_dir,args.report)
        if args.stage == 'seal':
            seal(args.plan_dir)
        print(json.dumps(dict(stage=args.stage,result='PASS',specs=7,requirements=3,case_contracts=56,parent_ownership=49,external_control_plane_tools=19,semantic_acceptance='item-owned; structure is not executed proof',runtime_or_live_proof=False)))
        return 0
    except (OSError,ValueError,TypeError,KeyError,IndexError,AttributeError) as exc:
        print('P8 validation FAIL: '+str(exc),file=sys.stderr)
        return 1

if __name__ == '__main__':
    raise SystemExit(main())
