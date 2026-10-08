import argparse,csv,json,os,re,shutil,subprocess,time
from pathlib import Path
ap=argparse.ArgumentParser();ap.add_argument('mode',choices=['setup','transfer','validate']);ap.add_argument('--tree',required=True);ap.add_argument('--out',required=True);ap.add_argument('--source',default='/home/vostrikov/projects/packages/corex');a=ap.parse_args()
tree=Path(a.tree).resolve();source=Path(a.source).resolve();out=Path(a.out).resolve();out.mkdir(parents=True,exist_ok=True)
plan='plans/2026.09.27-№1-COREX-KERNEL';tool=plan+'/artifacts/P03-design/scripts/p03-transfer.py'
env={**os.environ,'PGSQL_HOST':'127.0.0.1','PGSQL_PORT':'5432','PGSQL_DATABASE':'corex_test','XDEBUG_MODE':'off'}
rows=[]
def run(name,cmd):
 start=time.monotonic()
 with (out/(name+'.log')).open('w') as log:r=subprocess.run(cmd,cwd=tree,env=env,stdout=log,stderr=subprocess.STDOUT)
 row={'check':name,'command':cmd,'exit':r.returncode,'seconds':round(time.monotonic()-start,2),'log':name+'.log'};rows.append(row)
 (out/(a.mode+'-checks.json')).write_text(json.dumps(rows,indent=2)+'\n');print(name,r.returncode,flush=True)
 if r.returncode:raise SystemExit(r.returncode)
def git(*args):return subprocess.check_output(['git',*args],cwd=tree,text=True).strip()
roots=json.loads((tree/'composer.json').read_text())['scripts']['test:packages'].split('for p in ',1)[1].split('; do',1)[0].split()
if a.mode=='setup':
 assert tree!=source and tree.is_relative_to(source/'build')
 run('sealed-staging',['python3','scripts/distribution/stage-stancl-providers.py'])
 run('restore-staging-manifests',['git','restore','--','composer.json','packages/tenancy/composer.json','packages/access/composer.json'])
 run('root-install',['scripts/php84','composer','install','--no-scripts','--no-interaction'])
 for root in roots:
  target=tree/root/'vendor';assert (source/root/'vendor').is_dir()
  assert not target.exists()
  run('copy-vendor-'+Path(root).name,['cp','-a','--reflink=auto',str(source/root/'vendor'),str(target)])
 links=0
 for root in ['.',*roots]:
  for path in (tree/root/'vendor').rglob('*'):
   if not path.is_symlink():continue
   resolved=path.resolve()
   if not resolved.is_relative_to(tree):
    assert resolved.is_relative_to(source),(str(path),str(resolved))
    path.unlink();path.symlink_to(tree/resolved.relative_to(source))
   assert path.resolve().is_relative_to(tree),(str(path),str(path.resolve()))
   links+=1
 for root in roots:run('autoload-'+Path(root).name,['scripts/php84','composer','--working-dir='+root,'dump-autoload','--no-scripts','--no-interaction'])
 assert not git('status','--porcelain'),git('status','--porcelain')
 (out/'package-setup.json').write_text(json.dumps({'source':str(source),'tree':str(tree),'base':git('rev-parse','HEAD'),'roots':roots,'independent_vendor_copies':True,'symlinks_inside_scratch':links,'scope':'local installed dependency set; no fresh-install or hosted-CI claim'},indent=2)+'\n')
elif a.mode=='transfer':
 assert not git('status','--porcelain'),git('status','--porcelain')
 run('projection-before',['python3',plan+'/artifacts/P03.2-execution/project-map.py'])
 for action in ('plan','apply','manifest','golden','lock','verify'):run(action,['python3',tool,action])
 run('root-install-after',['scripts/php84','composer','install','--no-scripts','--no-interaction'])
 files=[f for f in git('diff','--name-only','HEAD').splitlines() if f.endswith('.php') and (tree/f).is_file()]
 run('pint',['scripts/php84','vendor/bin/pint',*files])
 run('adapters',['python3',tool,'adapters'])
 for root in roots:run('autoload-'+Path(root).name,['scripts/php84','composer','--working-dir='+root,'dump-autoload','--no-scripts','--no-interaction'])
 run('baseline',['python3','scripts/module-layout-gate.py','--write-baseline'])
 run('predicted-baseline',['cmp','scripts/module-layout-baseline.json',plan+'/artifacts/P03/module-layout-baseline.predicted.json'])
 package_roots=sorted({row['path'].split('/src/')[0] for row in csv.DictReader((tree/plan/'artifacts/P03/module-map.tsv').open(),delimiter='\t') if row['path']!=row['target_path']})
 note='### Changed\n\n- PLAN3.D57: implementation classes moved to `<Module>\\Internal\\…`, public types to module categories\n  (`Contracts|Data|Enums|Events|Exceptions`); see `docs/architecture/constitution.md` STR-003. Drain queues before\n  upgrading; stored `*_type` values holding an old model FQCN no longer resolve (no aliases, PLAN3.D01).\n\n'
 for root in package_roots:
  p=tree/root/'CHANGELOG.md';s=p.read_text();m=re.search(r'^## (?:\[)?Unreleased[^\n]*\n',s,re.M);assert m,p
  p.write_text(s[:m.end()]+'\n'+note+s[m.end():].lstrip('\n'))
 (out/'changelog-packages.json').write_text(json.dumps(package_roots,indent=2)+'\n')
 fixture=tree/'scripts/tests/test_module_layout_gate.py'
 before=fixture.read_text()
 old="'R1': ('core/src/Support/Helper.php', 'namespace CoreX\\\\Support; final class Helper {}')"
 new="'R1': ('core/src/Unlisted/Helper.php', 'namespace CoreX\\\\Unlisted; final class Helper {}')"
 assert before.count(old)==1
 fixture.write_text(before.replace(old,new))
 run('layout-python-negatives',['python3','-m','unittest','discover','-s','scripts/tests','-p','test_module_layout_gate.py'])
 run('projection-after',['python3',plan+'/artifacts/P03.2-execution/project-map.py'])
 run('golden-map',['python3',tool,'golden-check'])
 run('frozen-golden',['git','diff','--exit-code','52446de','--','tests/Golden/baseline/'])
 (out/'diff-stat.txt').write_text(git('diff','--stat','HEAD')+'\n')
 (out/'product-files.json').write_text(json.dumps(git('diff','--name-only','HEAD').splitlines(),indent=2)+'\n')
 (out/'product.patch').write_text(git('diff','HEAD')+'\n')
 for filename in ('adapter-projection.json','fixture-projection.json'):(out/filename).write_bytes((tree/'build/p03'/filename).read_bytes())
 (out/'implicit-references.json').write_bytes((tree/'build/p02/implicit-references.json').read_bytes())
elif a.mode=='validate':
 run('stack',['python3',plan+'/artifacts/P02.3-execution/check.py','--out',str(out/'stack'),'--fail-fast','manifests','lint','golden','root-test','pg-test','packages-test','analyse','deps-analyse','ownership','ceilings','anti-weakening','package-graph-gate','api-contract','contract-guard','naming-gate','package-docs-gate','mutation'])
 for name,cmd in [('module-layout',['scripts/php84','composer','module-layout-gate']),('rename-all',['scripts/php84','php','scripts/rename-completeness-gate.php']),('ownership-strict',['scripts/php84','php','scripts/ownership-gate.php','--strict']),('arch',['scripts/php84','composer','test:arch']),('plan-lint',['python3','/home/vostrikov/projects/packages/swissknifeman/packages/task/scripts/plan-lint.py',plan])]:run(name,cmd)
