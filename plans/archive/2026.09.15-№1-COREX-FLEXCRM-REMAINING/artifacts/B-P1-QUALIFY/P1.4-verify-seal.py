"""Independent final source/projection/seal coverage checks; no runtime acceptance."""
from pathlib import Path
import hashlib
import importlib.util
import json
import re

p=Path(__file__).resolve().parents[2]; a=p/'artifacts/B-P1-QUALIFY'
sha=lambda path:hashlib.sha256(path.read_bytes()).hexdigest()
s=importlib.util.spec_from_file_location('validator',p/'scripts/validate-p1.py')
v=importlib.util.module_from_spec(s);s.loader.exec_module(v)
inventory,criteria,rids=v.inputs(p)
v.sources(p,inventory);v.criteria_check(p,criteria,rids);v.evidence(p,rids);v.seal(p,rids)
model=json.loads((a/'P1.2-semantic-model.json').read_text())
source_atoms={z['Atom']:z for x in model['parents'] for z in x['atoms']}
projection=json.loads((a/'P1.4-effective-acceptance-projection.json').read_text())
assert projection['source_map_sha256']==sha(p/'findings/P1-acceptance-map.md')
assert projection['source_model_sha256']==sha(a/'P1.2-semantic-model.json')
assert projection['original_index_sha256']==sha(p/'research/01a-acceptance-index.md')
assert len(criteria)==len(model['parents'])==365
assert len(projection['rows'])==len(source_atoms)==688
assert {x['source_row_id'] for x in projection['rows']}==set(source_atoms)
expected={'B11 AC-8.a3':'B11 AC-8.a2','B11 AC-8.a5':'B11 AC-8.a4','B11 AC-8.a7':'B11 AC-8.a6','B11 AC-8.a9':'B11 AC-8.a8','B11 AC-8.a11':'B11 AC-8.a10','B34 AC-19.a2':'B34 AC-19.a1'}
assert projection['associations']==expected
effective=set()
for row in projection['rows']:
    source=source_atoms[row['source_row_id']]; target=source_atoms[row['acceptance_id']]
    assert row['parent']==source['Parent AC'] and row['R']==source['R']
    if row['source_row_id'] in expected:
        assert row['kind']=='delimiter-provenance'
        assert source['Source fragment'] in ('\n',' ')
        assert target['Source fragment'].strip()
        assert source['Parent AC']==target['Parent AC']
        if row['source_row_id']=='B34 AC-19.a2':
            assert source['R']=='R40' and target['R']=='R43'
            assert source['Source fragment']==' '
        else:
            assert all(source[k]==target[k] for k in ('R','Boundary','Owner','Destination'))
    else:
        assert row['kind']=='substantive-condition' and row['acceptance_id']==row['source_row_id']
        assert source['Condition'].strip() and not source['Condition'].rstrip().endswith(':')
        effective.add(row['acceptance_id'])
    assert row['acceptance_R']==target['R']
    assert row['acceptance_owner']==target['Owner']
    assert row['acceptance_boundary']==target['Boundary']
    assert row['acceptance_destination']==target['Destination']
assert len(effective)==682
for parent in model['parents']:
    prim=[z for z in parent['atoms'] if 'Referenced target' not in z]
    assert ''.join(z['Source fragment'] for z in prim)==parent['full_body']
    for z in parent['atoms']:
        if 'Referenced target' in z:
            path,line=z['Referenced target'].split(':L'); line=int(line)
            original=(p/path).read_text().splitlines(keepends=True)[line-1]
            chunks=[x for x in parent['atoms'] if x.get('Referenced target')==z['Referenced target']]
            assert ''.join(x['Source fragment'] for x in chunks)==original
    if parent['label']=='B16.§8.20':
        assert len(prim)==5 and all(z['Atom'] in effective for z in prim)
        assert {z['Focus'] for z in prim}=={'PanelHost','MessageService','PermissionResolver','NotificationCenter','RelationLookup'}
        assert all('level 8' in z['Condition'] and '@phpstan-ignore' in z['Condition'] for z in prim)

cols=('R','Baseline disposition','Smallest gap or no-delta','Producer phase','Acceptance receipt','Owner','Resume','Consumer scenario')
gaps=v.table(p/'findings/P1-downstream-gaps.md','Requirements',cols)
ledger=v.table(p/'findings/P1-evidence-ledger.md','Requirements',('R','Disposition','Owner','Source anchor','Equivalent behavior','Tests and receipt class','Evidence or missing receipt','Gap owner','Resume','Destination'))
by_ledger={r['R']:r for r in ledger}; frozen_model={r['R']:r for r in json.loads((a/'P1.3-semantic-model.json').read_text())['requirements']}
for row in gaps:
    r=by_ledger[row['R']]
    assert row['Owner']==r['Owner'] and row['Resume']==r['Resume'] and row['Producer phase']==r['Destination']
    assert row['Consumer scenario']==frozen_model[row['R']]['Consumer scenario']
    assert frozen_model[row['R']]['smallest_gap'] in row['Smallest gap or no-delta']
    if r['Disposition'] in ('QUALIFY','DELTA','INVARIANT','KEEP'):
        assert re.search(r'P[1-9]|P10|P11',row['Producer phase'])
        assert 'Observed equivalent:' in row['Smallest gap or no-delta']
    if r['Disposition']=='DELTA':
        assert 'P2' in row['Producer phase'] and row['Consumer scenario'].strip()
conflicts=v.table(p/'findings/P1-conflicts.md','Conflicts',('ID','Question','Evidence','Alternatives','Owner','Disposition','Decision or resume','Affected R'))
assert len(conflicts)==17 and {r['ID'] for r in conflicts}=={f'C{i:02}' for i in range(1,18)}
for c in conflicts: assert set(re.findall(r'R\d{2}',c['Affected R']))<=rids and re.findall(r'R\d{2}',c['Affected R'])
c2=next(c for c in conflicts if c['ID']=='C02')
assert set(re.findall(r'R\d{2}',c2['Affected R']))=={'R18','R42'}
assert 'FlexCRM' in c2['Owner']
condition_rows=v.table(p/'findings/P1-evidence-ledger.md','DI/AP',('ID','R','Owner','Required receipt','Resume'))
assert len(condition_rows)==47 and all('UNFILLED' in z['Required receipt'] for z in condition_rows)
assert json.loads((a/'P1.4-required-read-progress.json').read_text())['all_complete']
assert json.loads((a/'P1.4-product-read-progress.json').read_text())['complete_chunks']==40
result={'result':'PASS','sources':100,'requirements':52,'parents':365,'historical_map_rows':688,'effective_substantive_conditions':682,'retained_delimiter_records':6,'retained_shared_phpstan_targets':5,'conflicts':17,'sealed_inputs':11,'DI_unfilled':34,'AP_unfilled':13,'scope':'source/requirement qualification only; semantic judgment separate','product_sha256':{f:sha(p/'findings'/f) for f in ['P1-requirements-lock.md','P1-conflicts.md','P1-downstream-gaps.md']}}
(a/'P1.4-independent-seal-check.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')
print(json.dumps(result,ensure_ascii=False))
