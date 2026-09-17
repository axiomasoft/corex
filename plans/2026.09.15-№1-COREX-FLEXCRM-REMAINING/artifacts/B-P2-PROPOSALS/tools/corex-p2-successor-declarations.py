exec(open('/tmp/corex-p2-common.py').read())
from task import plan_bundle,plan_item_state,plan_status,plan_decisions,token_estimate
f=p/'phases/P3/P3.1.md';old=f.read_text();(a/'P3.1-admission-declaration-before.md').write_text(old)
(p/'decisions/D22-P2-successor-admission.md').write_text('''---
id: D22
status: accepted
item: design
items: [design]
supersedes: []
---

# D22 — finite successor admission carrier repair after P2.4

Deterministic executor correction within authorized P2.4 closure and next-handoff preparation. P2.4 attempt1 local validation/semantic passed; post-close named lint actually failed because P3.1 next bundle could not parse old semicolon/unquoted Inputs/Required Reads. Preserve exact original diagnostic/product/proofs in artifacts/B-P2-PROPOSALS/P2.4-attempt1. Start P2.4 owning attempt2 before correction; P3.1 remains Not started, no P3 recovery/start/API/schema/dependency action.

Quote/number original P3.1 source paths and explicitly bind prior P1/P2 producers. Bounded selections cover the ENTIRE original P1 map and P2 proposal/catalogue source contents; no clause/source removed. Format own P2 catalogue Requirements into four13-R numeric groups, Producer edges into four bounded groups, Sealed inputs into numeric3; exact table cells unchanged. All original fields except duplicate Inputs/read syntax unchanged. Keep original G gates, producer ownership, source/runtime distinctions and per-read limits. D20 scope remains ONLY B-P2; P3 aggregate budget is not made advisory or otherwise approved. Shared runtime unchanged. Reseal these finite static declarations under current whole-plan design-finish before closing P2.4.

This prepares a reviewable handoff, not execution/adoption admission for P3.1. Any missing protected receipt or P3 budget remains named in the handoff. No new material feature/design choice; no independent phase audit.
''')
# Same table bytes with bounded numeric headings, no dropped rows.
fcat=p/'findings/P2-contract-catalogue.md';b=(a/'P2.4-attempt1/P2-contract-catalogue.md').read_text();oldrows=tbl('findings/P2-contract-catalogue.md','Requirements','R / Disposition / Contract IDs / Local acceptance / Protected adoption / Owner / Resume / Destination');oldedges=tbl('findings/P2-contract-catalogue.md','Producer edges','Producer / Contract version / Consumer / Required receipt / Failure model');lines=b.splitlines(True);out=[];heading='';count=0
for line in lines:
 if line.startswith('## '):heading=line.strip()[3:];count=0
 if heading=='Requirements' and re.match(r'^\| R\d{2} \|',line):
  if count%13==0:out.append('\n### 1.'+str(count//13+1)+' — Requirements group\n\n')
  count+=1
 if heading=='Producer edges' and line.startswith('| ') and not line.startswith('| Producer ') and not line.startswith('| :--'):
  if count%33==0:out.append('\n### 2.'+str(count//33+1)+' — Producer edge group\n\n')
  count+=1
 if heading=='Sealed inputs' and line.startswith('| Path '):out.append('\n### 3 — Exact sealed input table\n\n')
 out.append(line)
fcat.write_text(''.join(out));assert oldrows==tbl('findings/P2-contract-catalogue.md','Requirements','R / Disposition / Contract IDs / Local acceptance / Protected adoption / Owner / Resume / Destination');assert oldedges==tbl('findings/P2-contract-catalogue.md','Producer edges','Producer / Contract version / Consumer / Required receipt / Failure model')
required=re.search(r'\*\*Required Reads:\*\* (.*?)(?=\n\*\*)',old,re.S)[1];names=list(dict.fromkeys(re.findall(r'(?:[\w.-]+/)+[\w.-]+\.(?:md|php)',required)));assert 'plan.md' not in names;names.insert(0,'plan.md')
inputs=re.search(r'\*\*Inputs:\*\* (.*?)(?=\n\*\*)',old,re.S)[1];initial=list(dict.fromkeys(re.findall(r'(?:[\w.-]+/)+[\w.-]+\.md',inputs)));initial=[x for x in initial if x not in names];assert set(initial)=={'findings/P1-requirements-lock.md','findings/P1-acceptance-map.md','findings/P2-contract-catalogue.md','findings/P2-contract-proposals.md','findings/P2-consumer-cases.md','findings/P2-gate-requests.md'}
entries=['`'+x+'`' for x in names]
mapsections=['0']+['1.'+str(i) for i in range(1,10)]+['2.'+str(i) for i in range(1,40)]+['3.'+str(i) for i in range(1,5)]
for name in initial:
 if name=='findings/P1-acceptance-map.md':entries+=['`'+name+'` '+', '.join('§'+s for s in mapsections[i:i+2])+' (producer: P1.4)' for i in range(0,len(mapsections),2)]
 elif name=='findings/P2-contract-catalogue.md':entries+=['`'+name+'` '+', '.join('§'+s for s in ['0','3']+['1.'+str(i) for i in range(1,5)]+['2.'+str(i) for i in range(1,5)])+' (producer: P2.4)']
 elif name=='findings/P2-contract-proposals.md':entries+=['`'+name+'` '+', '.join('§'+s for s in ['0','1']+['3.'+str(i) for i in range(1,11)])+' (producer: P2.3)']
 else:entries+=['`'+name+'` (producer: '+('P1.4' if 'P1-' in name else 'P2.3' if 'consumer-cases' in name else 'P2.4')+')']
b=re.sub(r'\*\*Inputs:\*\* .*?(?=\n\*\*)','**Inputs:** P2.4 → `findings/P2-contract-catalogue.md` §0. All original predecessor sources are source-complete explicit Required Reads below; no source/check/per-read limit or G receipt removed, duplicate input declarations only.',old,flags=re.S)
b=re.sub(r'\*\*Required Reads:\*\* .*?(?=\n\*\*)',lambda _:'**Required Reads:** '+' '.join(str(n)+') '+x+'.' for n,x in enumerate(entries,1)),b,flags=re.S);f=p/'phases/P3/P3.1.md';f.write_text(b)
for field in ['Intent','Why','Scope Included','Scope Excluded','Files','Implementation Rules','Code Guidance','Validation','Deliverables']:
 extract=lambda text:re.search(r'\*\*'+re.escape(field)+r':\*\*\s*(.*?)(?=\n\*\*|\Z)',text,re.S)[1];assert extract(old)==extract(b),field
plan_bundle.build(p,'P3.1');plan_bundle.build(p,'P2.4');plan_item_state.build_states(p);plan_status.build_status(p);plan_decisions.build_views(p)
(a/'P3.1-admission-declaration-repair.json').write_text(json.dumps({'original_sha256':hashlib.sha256(old.encode()).hexdigest(),'current_sha256':sha(f),'original_sources':names+initial,'required_entries':len(entries),'table_cells_unchanged':True,'substantive_fields_unchanged':True,'scope':'P2.4 handoff carrier repair only','P3_started':False,'P3_budget_approved':False,'runtime_changed':False},indent=2)+'\n');print('Successor carrier source-complete repaired; P3.1 bundle prepared, NOT STARTED; entries',len(entries))
