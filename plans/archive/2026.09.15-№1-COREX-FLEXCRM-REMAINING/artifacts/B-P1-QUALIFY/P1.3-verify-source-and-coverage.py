from pathlib import Path
import json,hashlib,importlib.util,subprocess,re
p=Path('plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING');a=p/'artifacts/B-P1-QUALIFY'
s=importlib.util.spec_from_file_location('validator',p/'scripts/validate-p1.py');v=importlib.util.module_from_spec(s);s.loader.exec_module(v)
sha=lambda x:hashlib.sha256(Path(x).read_bytes()).hexdigest()
v.inputs(p)
catalog=json.loads((a/'P1.3-source-catalog.json').read_text());by_path={r['path']:r for r in catalog}
for z in catalog:
 assert sha(z['path'])==z['sha256'],z['path']
 body=subprocess.check_output(['git','cat-file','blob',z['blob']]);assert hashlib.sha256(body).hexdigest()==z['sha256'],z['path']
model=json.loads((a/'P1.3-semantic-model.json').read_text());rows=v.table(p/'findings/P1-evidence-ledger.md','Requirements',('R','Disposition','Owner','Source anchor','Equivalent behavior','Tests and receipt class','Evidence or missing receipt','Gap owner','Resume','Destination'))
atoms=v.table(p/'findings/P1-acceptance-map.md','Atoms',('Atom','Parent AC','R','Boundary','Owner','Condition','Required evidence','Destination'))
assert len(rows)==len(model['requirements'])==52
for row,z in zip(rows,model['requirements']):
 assert all(z[k].strip().strip('`')==x for k,x in row.items()),row['R']
 assert z['atom_ids']==[r['Atom'] for r in atoms if r['R']==row['R']]
 assert z['Consumer scenario'] and z['smallest_gap']
 assert all(x in by_path for x in z['source_paths'])
 assert 'Source-only full bodies:' in row['Tests and receipt class']
 assert 'Named missing receipt:' in row['Evidence or missing receipt']
assert sum(len(z['atom_ids']) for z in model['requirements'])==len(atoms)==688
gates=v.table(p/'findings/P1-evidence-ledger.md','Gates',('ID','R','Owner','Required receipt','Resume'))
original=v.table(p/'research/03-gates.md','Обязательные gates',('Gate','Владелец','Required receipt','Точное условие возобновления'))
for z,g in zip(original,gates):assert g['ID']==z['Gate'] and z['Required receipt'] in g['Required receipt'] and g['Resume']==z['Точное условие возобновления']
conditions=v.table(p/'findings/P1-evidence-ledger.md','DI/AP',('ID','R','Owner','Required receipt','Resume'));di=v.table(p/'research/03-gates.md','Полный DI ledger — 34 условия',('ID','Effective status','Missing input','Owner / required receipt'));ap=v.table(p/'research/03-gates.md','Полный AP ledger — 13 сценариев',('AP','Required executed scenario / mandatory subconditions','Owning operator','Status'))
for z,g in zip(di,conditions[:34]):assert z['ID']==g['ID'] and z['Missing input'] in g['Required receipt'] and z['Owner / required receipt'] in g['Required receipt'] and g['Required receipt'].startswith('UNFILLED effective:')
for z,g in zip(ap,conditions[34:]):assert z['AP']==g['ID'] and z['Required executed scenario / mandatory subconditions'] in g['Required receipt'] and g['Required receipt'].startswith('UNFILLED executed:')
app=json.loads((a/'P1.3-cohort-applicability.json').read_text());assert all(z['equal'] for z in app['D10_explicit_targets']);assert app['cohorts'][0]['changed_count']==51 and app['cohorts'][1]['changed_count']==0
for cohort in app['cohorts']:
 for z in cohort['package_tree_comparisons']:
  current=subprocess.check_output(['git','rev-parse','HEAD:'+z['path']],text=True).strip();assert current==z['observed_tree']
for z in model['requirements']:
 for path in z['source_paths']:assert by_path[path]['sha256'] in z['Source anchor']
assert sha(a/'P1.3-source-catalog.json')==model['source_catalog_sha256']
result={'result':'PASS','kind':'independent structural/source-byte consistency; semantic assessment separate','requirements':52,'atoms_bound':688,'source_bodies_and_git_blobs_verified':len(catalog),'gates':len(gates),'DI_effective_unfilled':34,'AP_executed_unfilled':13,'local_D10_targets_match':5,'E2_current_changed_paths':51,'E3_current_changed_paths':0,'ledger_sha256':sha(p/'findings/P1-evidence-ledger.md')}
(a/'P1.3-source-and-coverage-check.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n');print(json.dumps(result))
