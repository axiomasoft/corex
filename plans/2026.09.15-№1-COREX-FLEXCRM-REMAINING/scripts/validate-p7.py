#!/usr/bin/env python3
"""Read-only P7 contract/digest checker; execution conformance remains item-owned."""
from __future__ import annotations
import argparse
import importlib.util
import json
from pathlib import Path
import re
import sys

loader=importlib.util.spec_from_file_location('validate_p4',Path(__file__).with_name('validate-p4.py'))
if loader is None or loader.loader is None:raise ImportError('plan-local base checker missing')
a=importlib.util.module_from_spec(loader);loader.loader.exec_module(a);v=a.v
REPORTS={
 'findings/P7-source-qualification.md':('ENG7',5),
 'findings/P7-recurrence.md':('REC',12),
 'findings/P7-task-primitives.md':('PRIM',8),
 'findings/P7-task-recurrence.md':('MAT',9),
 'findings/P7-locale.md':('LOC',9),
 'findings/P7-money-rates.md':('FX',10),
 'findings/P7-geography.md':('GEO',10),
 'findings/P7-optional-bridges.md':('OPT',7),
}
RIDS={'R17','R20','R23','R24','R25'}
CASE_FIELDS=('Case','R/AC','Expected','Actual evidence','Result','Owner','Resume')
ADOPTION_FIELDS=('Surface','Gate','Exact digest','Owner','Result','Resume')
DESIGN='research/P7-utility-design.md'

def candidate(plan):
 phase=a.file(plan,'phases/P7/P7.md').read_text()
 v.require('СКЕЛЕТ — не детализирован' not in phase and 'Phase Assurance | v1' in phase,'skeleton/assurance gap')
 specs={x.name for x in (plan/'phases/P7').glob('P7.*.md') if re.fullmatch(r'P7\.\d+\.md',x.name)}
 v.require(specs=={f'P7.{i}.md' for i in range(1,10)},'nine exact specs required')
 for name in sorted(specs):
  body=a.file(plan,'phases/P7/'+name).read_text()
  v.require(tuple(re.findall(r'^\*\*([^*]+):\*\*',body,re.M))==a.FIELDS,'ordered eleven stable fields')
  for field in a.FIELDS:
   match=re.search(r'\*\*'+re.escape(field)+r':\*\*\s*(.*?)(?=\n\*\*|\Z)',body,re.S)
   v.require(match is not None and match[1].strip() not in {'','—'},name+'/'+field+' empty')
  v.require('--check design' in body and 'decisions/D6.md' in body,'D4/D6 missing')
  v.require('[NEEDS CLARIFICATION' not in body,'unresolved internal choice')
  reads=body.split('committed sources:')[1].split('**Implementation Rules:**')[0]
  for path in re.findall(r'(?:packages|docs|tests|delivery)/[\w./-]+',reads):
   v.require((plan.parents[1]/path.rstrip('.')).is_file(),'missing committed read '+path)
  for path in re.findall(r'findings/P[1-6]-[\w-]+\.md',body):
   producer=[x for x in (plan/'phases').glob('P[1-6]/P*.md') if re.fullmatch(r'P\d+\.\d+\.md',x.name)]
   v.require(any(path in x.read_text().split('**Deliverables:**')[-1] for x in producer),'undeclared future producer '+path)
 ownership=a.table(plan,DESIGN,'R / AC ownership',('R','Producer','Cases / obligations','Consumer / gate'));v.exact(ownership,'R',RIDS)
 inventory=a.table(plan,DESIGN,'Required local case IDs',('Report','Required Case IDs'));v.exact(inventory,'Report',set(REPORTS));expected=set()
 for row in inventory:
  prefix,count=REPORTS[row['Report']];ids=[x.strip() for x in row['Required Case IDs'].split(';')];want={f'{prefix}-{i:02}' for i in range(1,count+1)}
  v.require(len(ids)==count and set(ids)==want,'case inventory drift');expected|=want
 cases=a.table(plan,DESIGN,'Case invariants',('Case','Required observable result'));v.exact(cases,'Case',expected);v.require(len(cases)==70,'70 unique observable contracts required')
 parents=a.table(plan,DESIGN,'Parent AC ownership — originals retained',('Parent','Generic owner','Generic slice / cases','Full original residual owner'))
 v.exact(parents,'Parent',{f'{src} AC-{i}' for src in ('B27','B29','B35') for i in range(1,16)})
 text=a.file(plan,DESIGN).read_text()
 for seam in ('RecurrenceExpander::expand','TaskPlanningCommand::recordTime','TaskRecurrenceCommand::materialize','LocaleResolver::resolve','RateImporter::import','CurrencyConverter::convert','GeographyProvider::query','DatasetImporter::publish','AddressSuggestProvider::suggest','rate-snapshot/1','reference-data/1','generated gap','COUNT','contiguous prefix','float-only','same task SQL transaction','pinned','wakeup/1'):
  v.require(seam.lower() in text.lower(),'frozen seam missing '+seam)
 v.require(DESIGN in a.file(plan,'scripts/task-check.py').read_text(),'indirect finish seal missing')
 for query in (plan/'artifacts/P7-design').glob('perplexity-query*.txt'):
  v.require(not re.search(r'core\s*x|flex\s*crm|\bR\d{2}\b|\b(?:DI|AP)-\d|\bpackages/(?:core|modules|tenancy|intl|tasks|recurrence)(?:/|\b)',query.read_text(),re.I),'internal context in external query')

def design_ready(plan):
 candidate(plan);rag=a.file(plan,'research/P7-design-rag.md').read_text();v.require('**Perplexity:** successful' in rag,'mandatory RAG pending')
 for file in ('perplexity-response.json','perplexity-response.recurrence.json','perplexity-response.geo.json','perplexity-response.optional.json'):
  path='artifacts/P7-design/'+file;a.evidence(plan,path);v.require(v.digest(a.file(plan,path)) in rag,'stale retrieval digest '+path)
 sources=a.table(plan,'research/P7-design-rag.md','Primary sources',('Origin','URL','Supported claim'));v.require(len({x['Origin'] for x in sources})>=2 and all(x['URL'].startswith('https://') for x in sources),'invalid primary origins')
 choices=a.table(plan,'research/P7-design-rag.md','Choice / independent origin mapping',('Choice','Independent origins','What remains local inference'))
 v.require(len(choices)==6 and all(len({s.strip() for s in x['Independent origins'].split('+')})>=2 for x in choices),'independent choice coverage gap')
 v.require('## Alternatives' in rag and '## Cross-phase impact' in rag,'alternative/integration gap')
 v.require('**Status:** accepted' in a.file(plan,'decisions/D12-portable-utilities.md').read_text(),'D12 not accepted')

def branch(plan,path):
 v.require(path in REPORTS,'unknown branch report');v.require('p7-utility-receipts/1' in a.file(plan,path).read_text(),'receipt schema missing')
 prefix,count=REPORTS[path];rows=a.table(plan,path,'Cases',CASE_FIELDS);v.exact(rows,'Case',{f'{prefix}-{i:02}' for i in range(1,count+1)})
 inputs=a.table(plan,path,'Inputs',('Path','SHA256','Role'));v.exact(inputs,'Path',{x['Path'] for x in inputs})
 for x in inputs:v.require(v.digest(a.resolve_input(plan,x['Path']))==x['SHA256'],'branch input/log changed '+x['Path'])
 for row in rows:
  ids=set(re.findall(r'R\d{2}',row['R/AC']));v.require(bool(ids) and ids<=RIDS,'invalid ownership')
  v.require(row['Result']=='PASS' and bool(re.search(r'[0-9a-f]{64}',row['Actual evidence'])),'actual PASS/log hash missing')
 adoption=a.table(plan,path,'Adoption',ADOPTION_FIELDS)
 for x in adoption:v.require(bool(re.fullmatch(r'[0-9a-f]{64}',x['Exact digest'])),'adoption exact digest missing')

def seal(plan):
 for path in REPORTS:branch(plan,path)
 path='findings/P7-utility-receipts.md';v.require('p7-utility-receipts/1' in a.file(plan,path).read_text(),'seal schema missing')
 rows=a.table(plan,path,'Requirements',('R','Items','Local case receipts','Installed receipts','Pending effective gates','Owner','Resume'));v.exact(rows,'R',RIDS)
 edges=a.table(plan,path,'Producer edges',('Producer','Contract/version','Consumer','Required receipt','Failure model'));v.require({'P8','P9','P10','P11'}<={x['Consumer'] for x in edges},'downstream edges gap')
 anchors=a.table(plan,path,'Sealed inputs',('Path','SHA256'));v.exact(anchors,'Path',{x['Path'] for x in anchors})
 required=set(REPORTS)|{DESIGN,'research/P7-design-rag.md','decisions/D12-portable-utilities.md','research/05-integrated-design.md','research/03-gates.md','phases/P7/P7.md','findings/P1-requirements-lock.md','findings/P1-acceptance-map.md','findings/P2-contract-catalogue.md','findings/P2-contract-proposals.md','findings/P2-gate-requests.md','artifacts/qualification/p7/installed-qualification-receipt.md'}|{f'phases/P7/P7.{i}.md' for i in range(1,10)}
 v.require(required<={x['Path'] for x in anchors},'mandatory seal inputs gap')
 for x in anchors:v.require(v.digest(a.resolve_input(plan,x['Path']))==x['SHA256'],'seal input changed '+x['Path'])

def main():
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--stage',choices=('candidate','design','branches','seal'),required=True);parser.add_argument('--report');parser.add_argument('--plan-dir',type=Path,default=Path(__file__).resolve().parent.parent);args=parser.parse_args()
 try:
  candidate(args.plan_dir) if args.stage=='candidate' else design_ready(args.plan_dir)
  if args.stage=='branches':branch(args.plan_dir,args.report)
  if args.stage=='seal':seal(args.plan_dir)
  print(json.dumps(dict(stage=args.stage,result='PASS',specs=9,requirements=5,case_contracts=70,parent_ownership=45,semantic_acceptance='item-owned; structural checks never executed proof',runtime_or_live_proof=False)));return 0
 except (OSError,ValueError,TypeError,KeyError,IndexError,AttributeError) as exc:print('P7 validation FAIL: '+str(exc),file=sys.stderr);return 1
if __name__=='__main__':raise SystemExit(main())
