from pathlib import Path
exec(Path(__file__).with_name('corex-p2-common.py').read_text())
import sqlite3,fnmatch,subprocess
mapping=tbl('findings/P2-delta-map.md','Requirements','R / Disposition / Contract IDs / Smallest gap or no-delta / Producer / Consumer / Gate / Resume')
contracts=tbl('findings/P2-contract-proposals.md','Contracts','Contract / R / Owner / Existing anchor / Proposed section / Public surface / BC and version / Schema or dependency / Approval / Producer')
cases=tbl('findings/P2-consumer-cases.md','Cases','Contract / Scenario / Setup / Expected behavior / Negative or failure / Producer / Consumer receipt')
b=(p/'findings/P2-contract-proposals.md').read_text();decl=json.loads((a/'P2.3-declaration-bytes.json').read_text());sql=json.loads((a/'P2.3-sql-proposals.json').read_text());checks=[]
assert len(mapping)==52 and len(contracts)==30 and len(cases)==60
for old,new in zip(lock,mapping):assert old['R']==new['R'] and old['Disposition']==new['Disposition'] and old['Resume']==new['Resume']
for r in contracts:
 section=re.search(r'^## '+r['Contract']+r'\n(.*?)(?=^## |\Z)',b,re.M|re.S)[1];m=json.loads(re.search(r'```json\n(.*?)\n```',section,re.S)[1]);assert set(m['requirement_sources'])==set(r['R'].split())
 assert sha(p/m['owning_design']['path'])==m['owning_design']['sha256']
 for rid,x in m['requirement_sources'].items():assert x['full_map_sha256']==sha(p/'findings/P1-acceptance-map.md') and x['full_ledger_sha256']==sha(p/'findings/P1-evidence-ledger.md') and x['destination']==ld[rid]['Destination'] and x['frozen_source']==mr[rid]['Atoms or non-AC source']
 assert m['runtime_or_live_proof'] is False and (r['Approval']=='no-delta')==(m['new']=='none')
 own_cases=[x for x in cases if x['Contract']==r['Contract']];assert len(own_cases)==2 and len({x['Expected behavior'] for x in own_cases})==2 and len({x['Negative or failure'] for x in own_cases})==2
 assert all('future' in x['Consumer receipt'] for x in own_cases)
 checks.append({'contract':r['Contract'],'source_shape_ownership':'PASS','two_distinct_future_cases':'PASS','protected_adoption':r['Approval']})
# Every generated declaration belongs to the exact existing producer Files carrier, with whole source hash.
for x in decl:
 assert hashlib.sha256(x['declaration'].encode()).hexdigest()==x['sha256'];assert x['declaration'] in b;assert 'declare(strict_types=1);' in x['declaration']
 cid=x['contract'];r=next(r for r in contracts if r['Contract']==cid);sec=re.search(r'^## '+cid+r'\n(.*?)(?=^## |\Z)',b,re.M|re.S)[1];m=json.loads(re.search(r'```json\n(.*?)\n```',sec,re.S)[1]);assert x['path'] in m['files']
 if '/Contracts/' in x['path']:assert 'interface ' in x['declaration'] and 'final readonly class' not in x['declaration']
 elif 'enum ' not in x['declaration']:assert 'final readonly class' in x['declaration'] and 'public function __construct(' in x['declaration']
 if '/Contracts/' in x['path'] or cid!='C-P2-014':
  own=[row for row in m['indexed_files'] if x['path'] in row['files_exact'] or any(fnmatch.fnmatch(x['path'],tok.rstrip(';')) for tok in row['files_exact'].split() if tok.startswith('packages/'))]
  assert own,(cid,x['path'],'not in exact owning Files')
  for row in own:assert sha(p/row['path'])==row['sha256']
# Compare fully qualified proposed outward signatures with stable design actual spelling/shape.
def simplify(s):return re.sub(r'\\(?:[A-Z][A-Za-z0-9]*\\)*([A-Za-z0-9]+)',r'\1',s)
for x in decl:
 if '/Contracts/' not in x['path']:continue
 cid=x['contract'];r=next(r for r in contracts if r['Contract']==cid);j=int(cid[-3:]);design=next(d for d in ['research/P4-extension-design.md','research/P5-wakeup-design.md','research/P6-data-design.md','research/P7-utility-design.md','research/P8-tool-design.md'] if ('P'+str({7:4,8:4,9:4,10:4,11:4,12:5,13:5,15:6,16:6,17:6,18:6,19:6,20:6,21:7,22:7,23:7,24:7,25:7,26:7,27:7,28:8,29:8}[j])) in d)
  # The complete design exposes either PHP block or ::method notation, same parameter/return types.
 source=(p/design).read_text()
 for meth in re.findall(r'public function (.*?);',x['declaration']):
  simple=simplify(meth);assert simple in source,(cid,x['path'],simple,'stable signature mismatch')
# Normative portable recipes parse in disposable memory only, no project DB or files.
smoke=[]
for k in ['uuid','ulid','int']:
 conn=sqlite3.connect(':memory:');conn.execute('PRAGMA foreign_keys=ON')
 for j in sorted(sql,key=int):
  profile='sqlite.'+k if 'sqlite.'+k in sql[j] else 'sqlite.uuid';statement=sql[j][profile];assert statement.rstrip() in b;conn.executescript(statement);smoke.append({'K':k,'contract':j,'SQL_sha256':hashlib.sha256(statement.encode()).hexdigest(),'parse':'PASS'})
 conn.close()
# Meaningful integrity checks: nullable shared-view defaults, digest FK, canonical counter, CAS, parent snapshot.
c=sqlite3.connect(':memory:');c.execute('PRAGMA foreign_keys=ON')
for j in sorted(sql,key=int):c.executescript(sql[j]['sqlite.uuid'])
h='0'*64;utc='2026-09-15T10:00:00.000000Z';counter=lambda n:str(n).zfill(20)
def insert(table,values):
 c.execute('INSERT INTO '+table+' ('+','.join(values)+') VALUES ('+','.join('?' for _ in values)+')',tuple(values.values()))
def rejects(name,fn):
 try:fn()
 except sqlite3.IntegrityError:smoke.append({'case':name,'expected':'integrity refusal','actual':'integrity refusal','result':'PASS'})
 else:raise AssertionError(name+' accepted forbidden candidate state')
c.execute("INSERT INTO sys_saved_view_scopes VALUES ('archive.document','scope-shared')")
view={'id':'v1','entity_code':'archive.document','workspace_id':None,'owner_kind':None,'owner_id':None,'scope_key':'scope-shared','kind':'table','name':'{}','config':'{}','schema_version':'saved-view/1','revision':counter(1),'is_default':True,'default_scope_key':'scope-shared','position':0,'created_by':'archive-operator','created_at':utc,'updated_at':utc}
insert('sys_saved_views',view);rejects('two defaults shared null workspace/owner',lambda:insert('sys_saved_views',{**view,'id':'v2'}));insert('sys_saved_views',{**view,'id':'v3','is_default':False,'default_scope_key':None});insert('sys_saved_views',{**view,'id':'v4','is_default':False,'default_scope_key':None});rejects('nondefault marker mismatch',lambda:insert('sys_saved_views',{**view,'id':'v5','is_default':False}));rejects('counter uint64 overflow',lambda:insert('sys_saved_views',{**view,'id':'v6','revision':counter(18446744073709551616),'is_default':False,'default_scope_key':None}))
for snapshot in ['snapshot-A','snapshot-B']:insert('corex_reference_datasets',{'snapshot_id':snapshot,'manifest':'{}','raw_hash':h,'normalized_hash':h,'state':'published','created_at':utc})
ref={'snapshot_id':'snapshot-A','namespace':'manual','source_id':'parent','kind':'country','parent_namespace':None,'parent_source_id':None,'names':'{}','normalized_name':'parent','country_key':None,'coordinates':None,'timezones':'[]','provenance':'{}'};insert('corex_reference_records',ref);rejects('cross snapshot parent ref',lambda:insert('corex_reference_records',{**ref,'snapshot_id':'snapshot-B','source_id':'child','parent_namespace':'manual','parent_source_id':'parent'}));insert('corex_reference_records',{**ref,'source_id':'child','parent_namespace':'manual','parent_source_id':'parent'})
insert('corex_reference_current',{'dataset_key':'manual','snapshot_id':'snapshot-A','generation':counter(1)})
q='UPDATE corex_reference_current SET snapshot_id=?,generation=? WHERE dataset_key=? AND generation=?';assert c.execute(q,('snapshot-B',counter(2),'manual',counter(1))).rowcount==1;assert c.execute(q,('snapshot-A',counter(2),'manual',counter(1))).rowcount==0;smoke.append({'case':'dataset pointer stale generation CAS','actual_affected_rows':[1,0],'result':'PASS'})
# C10 exact candidate final FK table must refuse preexisting orphan intent on copy.
sec=re.search(r'^## C-P2-010\n(.*?)(?=^## |\Z)',b,re.M|re.S)[1];fk=next(s for s in re.findall(r'```sql\n(.*?)\n```',sec,re.S) if s.startswith('CREATE TABLE ntf_deliveries_new'))
c.executescript(fk);delivery={'id':'d1','notification_id':None,'digest_run_id':'absent-run','channel':'mail','delivery_key':h,'state':'pending','claim_epoch':counter(0),'attempts':0,'admitted_revision':counter(1)};rejects('digest orphan last FK',lambda:insert('ntf_deliveries_new',delivery));c.execute('DROP TABLE ntf_deliveries_new')
insert('ntf_notifications',{'id':'n1','recipient_kind':'user','recipient_id':'archive-operator','request_key':'archive:notify:1','request_digest':h,'event_type':'archived','template_key':'archive-notice','payload':'{}','revision':counter(1),'created_at':utc,'updated_at':utc})
insert('ntf_deliveries',{**delivery,'notification_id':'n1','digest_run_id':None})
c.commit();copy=json.loads((a/'P2.3-proposal-read-groups.json').read_text())['exact_SQLite_final_migration'];assert copy.rstrip() in b;c.executescript(copy)
assert c.execute('SELECT COUNT(*) FROM ntf_deliveries').fetchone()[0]==1
assert any(row[2]=='ntf_digest_runs' for row in c.execute('PRAGMA foreign_key_list(ntf_deliveries)'))
rejects('post-copy digest orphan',lambda:insert('ntf_deliveries',{**delivery,'id':'d2','delivery_key':'1'*64}))
smoke.append({'case':'exact final SQLite FK rebuild copy preserves row and adds target FK','row_count':1,'result':'PASS'});c.close()
# Explicit producer partial order: no reverse digest or engine/shared-data prerequisite; all branches acyclic.
edges=[('P3.1','P4.2'),('P3.1','P6.4'),('P4.2','P4.3'),('P4.4','P4.5'),('P5.2','P5.3'),('P5.1','P5.4'),('P5.2','P5.4'),('P4.5','P5.5'),('P5.2','P5.5'),('P5.5','P4.6'),('P4.6','P4.8'),('P6.1','P6.2'),('P6.2','P6.3'),('P6.4','P6.5'),('P6.6','P6.7'),('P7.1','P7.2'),('P7.2','P7.4'),('P7.3','P7.4'),('P5.2','P7.4'),('P6.6','P7.8'),('P6.7','P7.8')]
def visit(n,stack,done):
 assert n not in stack,('cycle',stack,n)
 if n in done:return
 for x,y in edges:
  if x==n:visit(y,stack+[n],done)
 done.add(n)
for n in {x for e in edges for x in e}:visit(n,[],set())
result={'status':'green','classification':'catalogue source/shape/DAG and isolated candidate SQLite integrity review; no Laravel/PG/live/provider conformance','coverage':{'R':52,'contract_surfaces':30,'future_independent_cases':60,'typed_declarations':len(decl)},'source_ownership_checks':checks,'isolated_SQL_checks':smoke,'producer_edges':edges,'protected_grants':0,'runtime_changed':False,'unresolved_internal_contract_choice':0,'external_receipts':'original G/C01–09/DI34/AP13 pending owning qualification/adoption, not inferred'}
(a/'P2.3-semantic.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n');print('Semantic P2.3 PASS',len(decl),'declarations;',len(smoke),'SQL shape/integrity checks')
