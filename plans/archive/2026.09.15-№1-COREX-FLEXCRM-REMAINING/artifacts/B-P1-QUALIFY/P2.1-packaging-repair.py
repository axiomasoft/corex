from pathlib import Path
import re,json,hashlib,importlib.util
from task import plan_bundle,plan_decisions,task_contract,plan_item_state,plan_status
p=Path('plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING');a=p/'artifacts/B-P1-QUALIFY';fp=p/'phases/P2/P2.1.md'
sha=lambda path:hashlib.sha256(path.read_bytes()).hexdigest()
old=(a/'P2.1-read-declaration-before.md').read_text() if (a/'P2.1-read-declaration-before.md').exists() else fp.read_text();(a/'P2.1-read-declaration-before.md').write_text(old)
def field(text,key):return re.search(r'^\*\*'+re.escape(key)+r':\*\* (.*)$',text,re.M)[1]
def quote_paths(text):return re.sub(r'(?<![`\w/])([\w./-]+\.(?:md|py|php|json))(?![`\w])',r'`\1`',text)
input_paths=list(dict.fromkeys(re.findall(r'[\w./-]+\.(?:md|py|php|json)',field(old,'Inputs'))))
input_entries=[]
for path in input_paths:
    owner='P1.2' if 'acceptance-map' in path else 'P1.3' if 'evidence-ledger' in path else 'P1.4' if path in ('findings/P1-requirements-lock.md','findings/P1-conflicts.md','findings/P1-downstream-gaps.md') else None
    suffix=' §0' if path in ('findings/P1-acceptance-map.md','findings/P1-evidence-ledger.md') else ''
    input_entries.append((owner+' → ' if owner else '')+f'`{path}`'+suffix)
inputs=' · '.join(input_entries)+'.'
required=field(old,'Required Reads')
paths=list(dict.fromkeys(re.findall(r'[\w./-]+\.(?:md|py|php|json)',required)))
all_reads=[]
for path in paths:
    if path in ('findings/P1-acceptance-map.md','findings/P1-evidence-ledger.md'):
        # Reuse already mechanically checked complete producer declaration coverage.
        b=json.loads((p/'bundles/P1.4.json').read_text())
        entries=[x for x in b['declaration']['required_reads'] if x['path']==path]
        assert len(entries)==(53 if 'acceptance-map' in path else 13)
        all_reads.extend(f'`{path}` §{x["selector"]["anchor"]}' for x in entries)
    else:all_reads.append(f'`{path}`')
new=old.replace('**Inputs:** '+field(old,'Inputs'),'**Inputs:** '+inputs)
new=new.replace('**Required Reads:** '+required,'**Required Reads:** '+' '.join(f'{i}) {text}.' for i,text in enumerate(all_reads,1))+' Остальные actual source/tests открывать по bounded anchors соответствующих R из qualified P1 ledger, не перечитывать все архивы.')
for key in ('Intent','Why','Scope Included','Scope Excluded','Files','Implementation Rules','Code Guidance','Validation','Deliverables'):assert field(new,key)==field(old,key),key
assert set(paths)==set(re.findall(r'[\w./-]+\.(?:md|py|php|json)',field(new,'Required Reads')))
fp.write_text(new)
plan_item_state.build_states(p);plan_status.build_status(p);plan_decisions.build_views(p)
finish=p/'artifacts/design-finish.md';ft=finish.read_text()
s=importlib.util.spec_from_file_location('taskcheck',p/'scripts/task-check.py');m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
fixed=m.design_inputs(p);assert len(fixed)==650
block='## Design inputs\n\n| Path | SHA256 |\n| :-- | :-- |\n'+'\n'.join(f'| `{path}` | {sha(p/path)} |' for path in sorted(fixed))+'\n\n'
ft=re.sub(r'^## Design inputs\n.*?(?=^## |\Z)',lambda _:block,ft,flags=re.M|re.S)
ft+='\n## P1 post-close successor carrier repair\n\nCurrent executor Sol/high repaired P2.1 canonical read declaration only after native successor preparation returned unresolvable_entry and final lint required local reversible repair. Every mandatory path retained; whole producer identity bound by policy0 and all 53 map/13 ledger operative sections explicitly enumerated. Intent/scope/Files/rules/guidance/Validation/Deliverables unchanged. No P2 execution, product/API/DDL/dependency/major/profile choice, source approval or new independent whole-plan review. Original Astra review retained; current 650 stable input hashes rebound. D19 remains B-P1-only; P2 hard aggregate policy unchanged.\n'
finish.write_text(ft)
bundle=plan_bundle.build(p,'P2.1');plan_bundle.build(p,'P2.1',check=True)
admission=task_contract.admit_batch(p,['P2.1']);(a/'P2.1-admission-after-repair.json').write_text(json.dumps(admission,ensure_ascii=False,indent=2)+'\n');assert admission['verdict']=='ALLOW',admission
receipt=task_contract.build_batch_receipt(admission['batch'],completed_prefix=[]);task_contract.validate_batch_receipt(receipt);(a/'P2.1-batch-receipt.json').write_text(json.dumps(receipt,ensure_ascii=False,indent=2)+'\n')
proof={'result':'PASS','classification':'reversible successor read-declaration packaging only','before_sha256':hashlib.sha256(old.encode()).hexdigest(),'after_sha256':sha(fp),'mandatory_paths_before':paths,'mandatory_paths_after':paths,'map_sections':53,'ledger_sections':13,'unchanged_fields':['Intent','Why','Scope Included','Scope Excluded','Files','Implementation Rules','Code Guidance','Validation','Deliverables'],'design_finish_inputs':650,'bundle':str(bundle),'admission':admission['verdict'],'P2_started':False,'policy':'D19 not extended; P2 original hard aggregate/per-read policy remains unchanged; budget assessment mandatory before execution'}
(a/'P2.1-read-declaration-repair.json').write_text(json.dumps(proof,ensure_ascii=False,indent=2)+'\n');print(json.dumps(proof,ensure_ascii=False))
