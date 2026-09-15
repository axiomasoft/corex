#!/usr/bin/env python3
"""Read-only P6 structure/hash checker; executed conformance remains item-owned."""
from __future__ import annotations
import argparse
import importlib.util
import json
from pathlib import Path
import re
import sys

loader=importlib.util.spec_from_file_location('validate_p4',Path(__file__).with_name('validate-p4.py'))
if loader is None or loader.loader is None: raise ImportError('plan-local checker missing')
a=importlib.util.module_from_spec(loader);loader.loader.exec_module(a);v=a.v
REPORTS={
 'findings/P6-engine-qualification.md':('ENG6',5),
 'findings/P6-field-binding.md':('FIELD',8),
 'findings/P6-schema-bridge.md':('DDL',8),
 'findings/P6-transfer-runner.md':('TRN',10),
 'findings/P6-export.md':('EXP',8),
 'findings/P6-search-query.md':('SRCH',8),
 'findings/P6-index-lifecycle.md':('IDX',10),
}
RIDS={'R11','R12','R32','R52'}
def candidate(plan):
 phase=a.file(plan,'phases/P6/P6.md').read_text()
 v.require('СКЕЛЕТ — не детализирован' not in phase and 'Phase Assurance | v1' in phase,'skeleton/assurance gap')
 specs={x.name for x in (plan/'phases/P6').glob('P6.*.md') if re.fullmatch(r'P6\.\d+\.md',x.name)}
 v.require(specs=={f'P6.{i}.md' for i in range(1,9)},'eight exact specs required')
 for name in sorted(specs):
  body=a.file(plan,'phases/P6/'+name).read_text()
  v.require(tuple(re.findall(r'^\*\*([^*]+):\*\*',body,re.M))==a.FIELDS,'ordered eleven stable fields')
  for field in a.FIELDS:
   match=re.search(r'\*\*'+re.escape(field)+r':\*\*\s*(.*?)(?=\n\*\*|\Z)',body,re.S)
   v.require(match is not None and match[1].strip() not in {'','—'},name+'/'+field+' empty')
  v.require('--check design' in body and 'decisions/D6.md' in body,'D4/D6 missing')
  v.require('[NEEDS CLARIFICATION' not in body,'unresolved internal choice')
  for path in re.findall(r'(?:packages|docs|tests|delivery)/[\w./-]+',body.split('committed sources:')[1].split('**Implementation Rules:**')[0]):
   v.require((plan.parents[1]/path.rstrip('.')).is_file(),'missing committed read '+path)
 design=a.file(plan,'research/P6-data-design.md').read_text()
 ownership=a.table(plan,'research/P6-data-design.md','R / AC ownership',('R','Producer','Cases / obligations','Consumer / gate'))
 v.exact(ownership,'R',RIDS)
 inventory=a.table(plan,'research/P6-data-design.md','Required local case IDs',('Report','Required Case IDs'))
 v.exact(inventory,'Report',set(REPORTS));expected=set()
 for row in inventory:
  prefix,count=REPORTS[row['Report']];ids=[x.strip() for x in row['Required Case IDs'].split(';')]
  want={f'{prefix}-{i:02}' for i in range(1,count+1)}
  v.require(len(ids)==count and set(ids)==want,'case inventory drift');expected|=want
 cases=a.table(plan,'research/P6-data-design.md','Case invariants',('Case','Required observable result'))
 v.exact(cases,'Case',expected);v.require(len(cases)==57,'57 unique case contracts required')
 for seam in ('AttributeStore::write','SchemaOperations::request','TransferRunner::step','SearchProvider::query','IndexDriver::swap','restoreEpoch','repeatable-read','sourceRevision','single','publication fence','Unknown','field-binding/1','schema-bridge/1','transfer/1','search/1','index-lifecycle/1'):
  v.require(seam.lower() in design.lower(),'frozen seam missing '+seam)
 for query in (plan/'artifacts/P6-design').glob('perplexity-query*.txt'):
  v.require(not re.search(r'core\s*x|flex\s*crm|\bR\d{2}\b|\b(?:DI|AP)-\d|\bpackages/(?:core|modules|tenancy|api|attributes|search|import-export)(?:/|\b)',query.read_text(),re.I),'internal context in external query')
 v.require('research/P6-data-design.md' in a.file(plan,'scripts/task-check.py').read_text(),'indirect finish seal missing')
 v.require('P6.5' not in a.file(plan,'phases/P3/P3.2.md').read_text(),'unexpected reverse P3 export edge')

def design_ready(plan):
 candidate(plan);rag=a.file(plan,'research/P6-design-rag.md').read_text()
 v.require('**Perplexity:** successful' in rag,'mandatory RAG pending')
 for path in ('perplexity-response.json','perplexity-response.transfer.json','perplexity-response.initial.json'):
  ref='artifacts/P6-design/'+path;a.evidence(plan,ref)
  v.require(v.digest(a.file(plan,ref)) in rag,'retrieval digest missing/stale '+ref)
 choices=a.table(plan,'research/P6-design-rag.md','Choice / independent origin mapping',('Choice','Independent origins','What remains local inference'))
 v.require(len(choices)==6 and all(len({s.strip() for s in x['Independent origins'].split('+')})>=2 for x in choices),'independent choice sources gap')
 sources=a.table(plan,'research/P6-design-rag.md','Primary sources',('Origin','URL','Supported claim'))
 v.require(len({x['Origin'] for x in sources})>=2 and all(x['URL'].startswith('https://') for x in sources),'primary origins invalid')
 v.require('## Alternatives' in rag and '## Cross-phase impact' in rag,'alternatives/integration gap')
 v.require('**Status:** accepted' in a.file(plan,'decisions/D11-data-import-search.md').read_text(),'D11 not accepted')

def branch(plan,path):
 v.require(path in REPORTS,'unknown branch report')
 body=a.file(plan,path).read_text();v.require('p6-data-receipts/1' in body,'report schema missing')
 rows=a.table(plan,path,'Cases',a.CASE_FIELDS);prefix,count=REPORTS[path]
 v.exact(rows,'Case',{f'{prefix}-{i:02}' for i in range(1,count+1)})
 for row in rows:
  ids=set(re.findall(r'R\d{2}',row['R/AC']))
  v.require(bool(ids) and ids<=RIDS,'case ownership invalid')
  v.require(row['Result']=='PASS' and bool(re.search(r'[0-9a-f]{64}',row['Actual evidence'])),'actual PASS/log hash missing')
 inputs=a.table(plan,path,'Inputs',('Path','SHA256','Role'))
 for row in inputs:v.require(v.digest(a.resolve_input(plan,row['Path']))==row['SHA256'],'branch input changed')
 a.table(plan,path,'Adoption',a.ADOPTION_FIELDS)

def seal(plan):
 for path in REPORTS:branch(plan,path)
 path='findings/P6-data-receipts.md';v.require('p6-data-receipts/1' in a.file(plan,path).read_text(),'seal version missing')
 rows=a.table(plan,path,'Requirements',('R','Items','Local case receipts','Installed receipts','Pending effective gates','Owner','Resume'))
 v.exact(rows,'R',{'R11','R12','R32'})
 edges=a.table(plan,path,'Producer edges',('Producer','Contract/version','Consumer','Required receipt','Failure model'))
 v.require({'P7','P8','P9','P10','P11'}<={x['Consumer'] for x in edges},'downstream consumers missing')
 anchors=a.table(plan,path,'Sealed inputs',('Path','SHA256'));v.exact(anchors,'Path',{x['Path'] for x in anchors})
 required=set(REPORTS)|{'research/P6-data-design.md','research/P6-design-rag.md','decisions/D11-data-import-search.md','phases/P6/P6.md','research/05-integrated-design.md','research/03-gates.md','findings/P1-requirements-lock.md','findings/P1-acceptance-map.md','findings/P2-contract-catalogue.md','findings/P2-contract-proposals.md','findings/P2-gate-requests.md','delivery/data-search-install-qualification-20260915/receipt.md'}|{f'phases/P6/P6.{i}.md' for i in range(1,9)}
 v.require(required<={x['Path'] for x in anchors},'mandatory seal inputs missing')
 for row in anchors:v.require(v.digest(a.resolve_input(plan,row['Path']))==row['SHA256'],'seal input changed')
def main():
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--stage',choices=('candidate','design','branches','seal'),required=True);parser.add_argument('--report');parser.add_argument('--plan-dir',type=Path,default=Path(__file__).resolve().parent.parent);args=parser.parse_args()
 try:
  candidate(args.plan_dir) if args.stage=='candidate' else design_ready(args.plan_dir)
  if args.stage=='branches':branch(args.plan_dir,args.report)
  if args.stage=='seal':seal(args.plan_dir)
  print(json.dumps(dict(stage=args.stage,result='PASS',specs=8,requirements=3,case_contracts=57,semantic_acceptance='item-owned; structure never executed proof',runtime_or_live_proof=False)));return 0
 except (OSError,ValueError,TypeError,KeyError,IndexError,AttributeError) as exc:print('P6 validation FAIL: '+str(exc),file=sys.stderr);return 1
if __name__=='__main__':raise SystemExit(main())
