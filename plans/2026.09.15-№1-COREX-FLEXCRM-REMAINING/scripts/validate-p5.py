#!/usr/bin/env python3
"""Read-only P5 candidate/evidence checker; never substitutes executed acceptance."""
from __future__ import annotations
import argparse
import importlib.util
import json
from pathlib import Path
import re
import sys
loader = importlib.util.spec_from_file_location('validate_p4', Path(__file__).with_name('validate-p4.py'))
if loader is None or loader.loader is None:
    raise ImportError('plan-local checker unavailable')
a = importlib.util.module_from_spec(loader)
loader.loader.exec_module(a)
v = a.v
REPORTS = {
    'findings/P5-engine-qualification.md': ('ENG', 5),
    'findings/P5-local-wakeup.md': ('WAK', 8),
    'findings/P5-root-routing.md': ('ROOT', 6),
    'findings/P5-workflow-binding.md': ('BIND', 8),
    'findings/P5-digest-bridge.md': ('BRIDGE', 5),
}

def candidate(plan):
    phase = a.file(plan, 'phases/P5/P5.md').read_text()
    v.require('СКЕЛЕТ — не детализирован' not in phase and 'Phase Assurance | v1' in phase, 'skeleton/assurance gap')
    paths = {x.name for x in (plan/'phases/P5').glob('P5.*.md') if re.fullmatch(r'P5\.\d+\.md', x.name)}
    v.require(paths == {f'P5.{n}.md' for n in range(1,7)}, 'six exact specs required')
    for path in sorted(paths):
        body = a.file(plan, 'phases/P5/'+path).read_text()
        v.require(tuple(re.findall(r'^\*\*([^*]+):\*\*',body,re.M)) == a.FIELDS,'ordered eleven stable fields')
        for field in a.FIELDS:
            match=re.search(r'\*\*'+re.escape(field)+r':\*\*\s*(.*?)(?=\n\*\*|\Z)',body,re.S)
            v.require(match is not None and match[1].strip() not in {'','—'}, f'{path}/{field} empty')
        v.require('--check design' in body and 'decisions/D6.md' in body, 'D4/D6 gate missing')
        v.require('[NEEDS CLARIFICATION' not in body, 'unresolved internal choice')
        reads=body.split('committed sources:')[1].split('**Implementation Rules:**')[0]
        for read in re.findall(r'(?:packages|docs|tests|delivery)/[\w./-]+',reads):
            v.require((plan.parents[1]/read.rstrip('.')).is_file(),f'missing committed read: {read}')
    ownership=a.table(plan,'research/P5-wakeup-design.md','R / AC ownership',('R','Producer','Cases / obligations','Consumer / gate')); v.exact(ownership,'R',{'R09','R10'})
    rows=a.table(plan,'research/P5-wakeup-design.md','Required local case IDs',('Report','Required Case IDs'))
    v.exact(rows,'Report',set(REPORTS))
    expected=set()
    for row in rows:
        prefix,count=REPORTS[row['Report']]
        ids=[x.strip() for x in row['Required Case IDs'].split(';')]
        want={f'{prefix}-{n:02}' for n in range(1,count+1)}
        v.require(len(ids)==count and set(ids)==want,'case inventory drift'); expected |= want
    design=a.file(plan,'research/P5-wakeup-design.md').read_text()
    actual=re.findall(r'^\| ((?:ENG|WAK|ROOT|BIND|BRIDGE)-\d{2}) \|',design,re.M)
    v.require(len(actual)==32 and set(actual)==expected,'32 unique invariants required')
    for seam in ('WakeupScheduler','WakeupHandler','WakeupSweeper','DueSource','WakeupHint','request_digest','generation','hint_revision','claim_epoch','EngineAdapter','WorkflowBinding','DigestWakeupAdapter','DigestTick','nextDue','routing-hint/1','engine-binding/1'):
        v.require(seam in design,f'missing frozen seam {seam}')
    for query in (plan/'artifacts/P5-design').glob('perplexity-query*.txt'):
        v.require(not re.search(r'core\s*x|flex\s*crm|R\d{2}|DI-\d|AP-\d|packages/',query.read_text(),re.I),'internal context in external query')
    v.require('P5.5 DOES NOT depend on P4.6' in design and 'DueSource' in a.file(plan,'phases/P4/P4.6.md').read_text(),'digest edge/catch-up missing')
    v.require('research/P5-wakeup-design.md' in a.file(plan,'scripts/task-check.py').read_text(),'indirect finish seal gap')

def design_ready(plan):
    candidate(plan)
    rag=a.file(plan,'research/P5-design-rag.md').read_text()
    v.require('**Perplexity:** successful' in rag,'DESIGN_PENDING: mandatory Perplexity verification unavailable')
    path=re.search(r'\*\*Search artifact:\*\*\s*`([^`]+)`',rag)
    checksum=re.search(r'\*\*SHA256:\*\*\s*`([0-9a-f]{64})`',rag)
    v.require(path is not None and checksum is not None,'retrieval bytes/hash missing')
    v.require(v.digest(a.file(plan,path[1]))==checksum[1],'retrieval digest stale'); a.evidence(plan,path[1])
    for supplemental in ('perplexity-response.resumed-routing-web.json','perplexity-response.resumed-digest-web.json'):
        supplemental_path='artifacts/P5-design/'+supplemental
        a.evidence(plan,supplemental_path)
        v.require(v.digest(a.file(plan,supplemental_path)) in rag,'supplementary evidence digest missing/stale')
    sources=a.table(plan,'research/P5-design-rag.md','Primary sources',('Origin','URL','Supported claim'))
    v.require(len({x['Origin'] for x in sources})>=2,'independent primary sources missing')
    choices=a.table(plan,'research/P5-design-rag.md','Choice / independent origin mapping',('Choice','Independent origins','What remains local inference'))
    v.require(len(choices)==5 and all(len({s.strip() for s in x['Independent origins'].split('+')})>=2 for x in choices),'choice origins missing')
    v.require('## Alternatives' in rag and '## Cross-phase impact' in rag,'alternative/integration gap')
    v.require('**Status:** accepted' in a.file(plan,'decisions/D10-workflow-wakeup.md').read_text(),'D10 remains proposed')

def branch(plan,path):
    v.require(path in REPORTS,'unknown branch report')
    v.require('p5-wakeup-receipts/1' in a.file(plan,path).read_text(),'report schema missing')
    rows=a.table(plan,path,'Cases',a.CASE_FIELDS)
    prefix,count=REPORTS[path]; required={f'{prefix}-{n:02}' for n in range(1,count+1)}
    v.exact(rows,'Case',required)
    v.require(all(set(re.findall(r'R\d{2}',x['R/AC'])) and set(re.findall(r'R\d{2}',x['R/AC'])) <= {'R09','R10','R16'} for x in rows),'case R ownership invalid')
    v.require(all(x['Result']=='PASS' and re.search(r'[0-9a-f]{64}',x['Actual evidence']) for x in rows),'required case not PASS/current proof hash missing')
    for row in a.table(plan,path,'Inputs',('Path','SHA256','Role')):
        v.require(v.digest(a.resolve_input(plan,row['Path']))==row['SHA256'],'branch input changed')
    a.table(plan,path,'Adoption',a.ADOPTION_FIELDS)

def seal(plan):
    for path in REPORTS: branch(plan,path)
    path='findings/P5-wakeup-receipts.md'
    v.require('p5-wakeup-receipts/1' in a.file(plan,path).read_text(),'seal version missing')
    rows=a.table(plan,path,'Requirements',('R','Items','Local case receipts','Installed receipts','Pending effective gates','Owner','Resume'));v.exact(rows,'R',{'R09','R10'})
    edges=a.table(plan,path,'Producer edges',('Producer','Contract/version','Consumer','Required receipt','Failure model'))
    v.require({'P4','P7','P8','P9','P10','P11'}<={x['Consumer'] for x in edges},'consumer edges missing')
    rows=a.table(plan,path,'Sealed inputs',('Path','SHA256'));v.exact(rows,'Path',{x['Path'] for x in rows})
    required=set(REPORTS)|{'research/P5-wakeup-design.md','research/P5-design-rag.md','decisions/D10-workflow-wakeup.md','phases/P5/P5.md','research/05-integrated-design.md','research/03-gates.md','findings/P1-requirements-lock.md','findings/P1-acceptance-map.md','findings/P2-contract-catalogue.md','findings/P2-contract-proposals.md','delivery/workflow-wakeup-install-qualification-20260915/receipt.md'}|{f'phases/P5/P5.{n}.md' for n in range(1,7)}
    v.require(required<={x['Path'] for x in rows},'seal mandatory inputs missing')
    for row in rows:v.require(v.digest(a.resolve_input(plan,row['Path']))==row['SHA256'],'seal input changed')

def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--stage',choices=('candidate','design','branches','seal'),required=True);parser.add_argument('--report');parser.add_argument('--plan-dir',type=Path,default=Path(__file__).resolve().parent.parent);args=parser.parse_args()
    try:
        candidate(args.plan_dir) if args.stage=='candidate' else design_ready(args.plan_dir)
        if args.stage=='branches':branch(args.plan_dir,args.report)
        if args.stage=='seal':seal(args.plan_dir)
        print(json.dumps(dict(stage=args.stage,result='PASS',specs=6,requirements=2,case_contracts=32,semantic_acceptance='item-owned; structure never substitutes executed proof',runtime_or_live_proof=False)));return 0
    except (OSError,ValueError,TypeError,KeyError) as exc:print(f'P5 validation FAIL: {exc}',file=sys.stderr);return 1
if __name__=='__main__':raise SystemExit(main())
