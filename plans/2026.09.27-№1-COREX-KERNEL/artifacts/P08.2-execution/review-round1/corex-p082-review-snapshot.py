#!/usr/bin/env python3
import argparse, hashlib, json, pathlib, re, shutil, subprocess

ROOT = pathlib.Path('/home/vostrikov/projects/packages/corex')
BASE = 'f6d9e41e13e39dbf7b44ce89788591ed8e742e41'
PLAN = ROOT / 'plans/2026.09.27-№1-COREX-KERNEL'

def changed_owner_names(files):
    return sorted({'/'.join(f['path'].split('/')[:2]) for f in files if f['path'].startswith(('packages/','products/'))})

def main():
    p=argparse.ArgumentParser(); p.add_argument('--output',required=True); p.add_argument('--validation-summary',required=True); a=p.parse_args()
    out=pathlib.Path(a.output); out.mkdir(exist_ok=False)
    entries=subprocess.check_output(['git','diff','--name-status','--no-renames',BASE,'HEAD'],cwd=ROOT,text=True).splitlines()
    files=[]; sources=[]
    for entry in entries:
        status,name=entry.split('\t',1)
        if not name.startswith(('packages/','products/','tests/','scripts/','docs/')): continue
        if status=='D':
            data=subprocess.check_output(['git','show',BASE+':'+name],cwd=ROOT)
            dest=out/'deleted'/name
        else:
            data=(ROOT/name).read_bytes(); dest=out/'product'/name
            if name.endswith('.php'): sources.append(name)
        dest.parent.mkdir(parents=True,exist_ok=True); dest.write_bytes(data)
        files.append({'change':status,'path':name,'snapshot_path':str(dest),'sha256':hashlib.sha256(data).hexdigest(),'bytes':len(data)})
    # Direct imports are available as complete files; do not copy unrelated monorepo history.
    dependencies={}
    for name in sources:
        content=(ROOT/name).read_text(errors='replace')
        for imported in re.findall(r'^use\s+([A-Za-z0-9_\\]+)(?:\s+as\s+\w+)?;',content,re.M):
            parts=imported.split('\\')
            if parts[0]=='CoreX':
                if len(parts)>2:
                    candidate=ROOT/'packages'/re.sub(r'(?<!^)(?=[A-Z])','-',parts[1]).lower()/'src'/pathlib.Path(*parts[2:]).with_suffix('.php')
                    if not candidate.is_file(): candidate=ROOT/'packages/core/src'/pathlib.Path(*parts[1:]).with_suffix('.php')
                    if candidate.is_file(): dependencies[str(candidate.relative_to(ROOT))]=candidate
            elif parts[:2]==['Flex','Crm']:
                candidate=ROOT/'products/flex-crm/src'/pathlib.Path(*parts[2:]).with_suffix('.php')
                if candidate.is_file(): dependencies[str(candidate.relative_to(ROOT))]=candidate
    for harness in ['phpunit.xml','phpunit.pg.xml','tests/Pest.php','tests/TestCase.php','scripts/test-pg.sh','scripts/test-packages.sh']:
        if (ROOT/harness).is_file():dependencies[harness]=ROOT/harness
    for name in changed_owner_names(files):
        owner=ROOT/name
        for suffix in ['composer.json','phpunit.xml','tests/Pest.php','tests/TestCase.php']:
            source=owner/suffix
            if source.is_file():dependencies[str(source.relative_to(ROOT))]=source
    for vendor in ['vendor/illuminate/database/Eloquent/Concerns/HasUuids.php','vendor/illuminate/database/Eloquent/Concerns/HasUniqueStringIds.php','vendor/illuminate/database/Eloquent/Concerns/HasUniqueIds.php','vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/HasUuids.php','vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/HasUniqueStringIds.php','vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/HasUniqueIds.php','vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php','vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/HasEvents.php','vendor/laravel/framework/src/Illuminate/Support/Str.php']:
        if (ROOT/vendor).is_file(): dependencies[vendor]=ROOT/vendor
    depindex=[]
    changed={f['path'] for f in files}
    for name,source in sorted(dependencies.items()):
        if name in changed:continue
        dest=out/'dependencies'/name; dest.parent.mkdir(parents=True,exist_ok=True); shutil.copyfile(source,dest)
        depindex.append({'path':name,'snapshot_path':str(dest),'sha256':hashlib.sha256(dest.read_bytes()).hexdigest()})
    norms=out/'norms';norms.mkdir()
    item=json.loads((PLAN/'phases/P08/P08.2.json').read_text()); item.pop('result',None);item.pop('updated_at',None);item['status']='review-candidate'
    (norms/'P08.2-spec.json').write_text(json.dumps(item,ensure_ascii=False,indent=2)+'\n')
    for did in ['D01','D22','D40','D90','D92','D103','D110','D111','D112','D113','D114','D116','D117']:
        source=PLAN/'decisions'/f'{did}.json'
        if source.is_file():shutil.copyfile(source,norms/source.name)
    dossier=json.loads((PLAN/'knowledge/research-P08-design.json').read_text())
    (norms/'dossier-sections.json').write_text(json.dumps({'sections':[s for s in dossier['sections'] if s['title'].startswith(('6.2','7.'))]},ensure_ascii=False,indent=2)+'\n')
    for path in ['docs/dev-doctrine.md','docs/architecture/semver-policy.md','composer.json','composer.lock']:
        dest=out/'dependencies'/path;dest.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(ROOT/path,dest)
    validation=out/'validation';validation.mkdir()
    summary=json.loads(pathlib.Path(a.validation_summary).read_text())
    chosen=[]
    for result in summary.get('logs',{}).values():
        if isinstance(result,dict) and result.get('exit_matches_expectation') and result.get('product_current'):
            result_path=result.get('result_path')
            if result_path:
                original=pathlib.Path(result_path).parent;chosen.append(original)
                result['snapshot_result_path']='validation/'+original.name+'/result.json'
                result['snapshot_log_path']='validation/'+original.name+'/output.log'
    for d in sorted(set(chosen)):
        dest=validation/d.name;shutil.copytree(d,dest,ignore=shutil.ignore_patterns('candidate.json'))
    (validation/'summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2)+'\n')
    if chosen and (chosen[-1]/'candidate.json').is_file():shutil.copyfile(chosen[-1]/'candidate.json',validation/'candidate.json')
    if summary.get('environment_witness') and pathlib.Path(summary['environment_witness']).is_file():shutil.copyfile(summary['environment_witness'],validation/'environment-witness.json')
    selfcheck=pathlib.Path('/tmp/corex-p082-self-check.json')
    if selfcheck.is_file():shutil.copyfile(selfcheck,validation/'self-check.json')
    for tag in ['before','after']:
        d=pathlib.Path('/tmp/corex-p082-schema-'+tag)
        target=validation/('schema-'+tag);target.mkdir()
        for f in d.glob('*'):
            if f.is_file() and f.suffix in ('.json','.sql','.log') and (tag!='before' or f.name not in ('run-central.log','run-tenant.log')): shutil.copyfile(f,target/f.name)
    # Complete diff, including generated JSON changes; each whole generated file is available above.
    diff=subprocess.check_output(['git','diff','--no-renames',BASE,'HEAD','--','packages','products','tests','scripts','docs'],cwd=ROOT)
    (out/'complete-product.diff').write_bytes(diff)
    manifest={'base_revision':BASE,'candidate_commit':subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip(),'product_files':files,'direct_dependencies':depindex,'validation_summary':'validation/summary.json','input_contract':'Complete changed/new/deleted product, adopted norms, direct dependencies and final validation evidence. Executor journal, session transcript and reasoning are excluded.'}
    (out/'manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
    for f in out.rglob('*'):
        if f.is_file(): f.chmod(0o444)
    print(json.dumps({'snapshot':str(out),'changed_files':len(files),'direct_dependencies':len(depindex),'changed_bytes':sum(f['bytes'] for f in files),'manifest_sha256':hashlib.sha256((out/'manifest.json').read_bytes()).hexdigest()}))

if __name__=='__main__':main()
