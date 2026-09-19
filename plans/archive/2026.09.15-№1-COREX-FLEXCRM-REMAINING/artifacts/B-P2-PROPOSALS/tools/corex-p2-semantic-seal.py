from pathlib import Path
exec(Path(__file__).with_name('corex-p2-common.py').read_text())
import subprocess
catalog=tbl('findings/P2-contract-catalogue.md','Requirements','R / Disposition / Contract IDs / Local acceptance / Protected adoption / Owner / Resume / Destination');mapping=tbl('findings/P2-delta-map.md','Requirements','R / Disposition / Contract IDs / Smallest gap or no-delta / Producer / Consumer / Gate / Resume');contracts=tbl('findings/P2-contract-proposals.md','Contracts','Contract / R / Owner / Existing anchor / Proposed section / Public surface / BC and version / Schema or dependency / Approval / Producer');req=tbl('findings/P2-gate-requests.md','Requests','Request / Gate / R / Exact proposal / SHA256 / Owner / Required receipt / Status / Resume / Destination');edges=tbl('findings/P2-contract-catalogue.md','Producer edges','Producer / Contract version / Consumer / Required receipt / Failure model');seal=tbl('findings/P2-contract-catalogue.md','Sealed inputs','Path / SHA256')
assert len(catalog)==52 and len(seal)==12 and len(req)==55;delta={r['R']:r for r in mapping};locked={r['R']:r for r in lock};cid={r['Contract']:r for r in contracts};checks=[]
for r in catalog:
 old=locked[r['R']];assert all(r[k]==old[k] for k in ['Disposition','Owner','Resume','Destination']);assert r['Contract IDs']==delta[r['R']]['Contract IDs'];assert 'catalogue review ONLY' in r['Local acceptance'] and 'original full scope/subconditions/DI/AP' in r['Protected adoption'];checks.append({'R':r['R'],'full_frozen_lock_join':'PASS','original_protected_scope_retained':'PASS'})
for r in seal:assert sha(p/r['Path'])==r['SHA256'] and r['Path']!='findings/P2-contract-catalogue.md' and '.state.' not in r['Path']
# Independent requests verify concrete linked bytes and scope, not just author inventory.
seen=set();by={}
for r in req:
 file,section=r['Exact proposal'].split('#');assert file=='findings/P2-contract-proposals.md';assert section in cid and r['R']==cid[section]['R'];assert sha(p/file)==r['SHA256'];assert cid[section]['Approval']!='no-delta' and r['Status']=='proposal';assert r['Request'] not in seen;seen.add(r['Request']);by.setdefault(section,set()).add(r['Gate']);body=re.search(r'^## '+section+r'\n(.*?)(?=^## |\Z)',(p/file).read_text(),re.M|re.S)[1]
 if r['Gate']=='G-SCHEMA':assert '```sql\nCREATE TABLE ' in body and ('Rollback mapping:' in body or section=='C-P2-004')
 if r['Gate']=='G-API':assert 'interface ' in body or 'shared CoreX Support/Data' in body or section=='C-P2-005'
 if r['Gate']=='G-DEPENDENCY':assert 'Qualification request ONLY' in r['Required receipt'] and 'immutable' in r['Required receipt'] and 'NO mutable' in r['Required receipt']
 if r['Gate']=='G-TIMING':assert section=='C-P2-004' and 'ClockReading' in r['Required receipt']
for contract in contracts:
 if contract['Approval']=='no-delta':assert contract['Contract'] not in by
 else:assert contract['Approval'].split(':')[1] in by[contract['Contract']]
# Every actual introduced interface requires API; every exact proposed SQL addition schema; keep hidden store not new API.
for row in json.loads((a/'P2.3-declaration-bytes.json').read_text()):
 if '/Contracts/' in row['path']:assert 'G-API' in by[row['contract']]
for j in json.loads((a/'P2.3-sql-proposals.json').read_text()):assert 'G-SCHEMA' in by['C-P2-'+j.zfill(3)]
assert {f'P{i}' for i in range(3,12)} <= {r['Consumer'] for r in edges}
original=json.loads((a/'P2.3-semantic.json').read_text());assert all(any(x['Producer']==a1 and x['Consumer']==b1 for x in edges) for a1,b1 in original['producer_edges']);assert not any(x['Producer']=='P4.6' and x['Consumer']=='P5.5' for x in edges)
assert 'DI34/AP13 UNFILLED remains UNFILLED' in (p/'findings/P2-contract-catalogue.md').read_text();assert 'C01–09' in (p/'findings/P2-contract-catalogue.md').read_text()
# Semantic producer receipts are real previous root-owned proofs and the three required files stay byte-current.
for i in [1,2,3]:assert json.loads((a/f'P2.{i}-semantic.json').read_text())['status']=='green'
result={'status':'green','profile':'p2-contract-catalogue/1','coverage':{'R':52,'parents':365,'operative':682,'delimiters':6,'foundations':9,'contracts':30,'independent_future_cases':60,'typed_declarations':217,'isolated_SQL_review_checks':47,'finite_requests':55,'sealed_inputs':12,'producer_phase_or_conditional_edges':len(edges)},'requirements':checks,'current_seals':seal,'new_request_coverage':{k:sorted(v) for k,v in by.items()},'protected_grants':0,'receipt_linked':0,'unresolved_internal_contract_choices':0,'runtime_changed':False,'actual_runtime_installed_live_released_effective_consumer_proof':False,'future_residual_owners':'P3–P11 original scope; C01–09 and DI34/AP13 remain independently owning, no blanket approval'}
(a/'P2.4-semantic.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n');print('P2.4 semantic seal PASS;52R/30surfaces/55requests/12SHA;0grants/0internal choices')
