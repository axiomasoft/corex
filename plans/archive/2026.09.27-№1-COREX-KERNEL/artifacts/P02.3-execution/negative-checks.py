import argparse,json,os,re,subprocess,time
from pathlib import Path
ap=argparse.ArgumentParser();ap.add_argument('--out',required=True);a=ap.parse_args();out=Path(a.out);out.mkdir(parents=True,exist_ok=True);rows=[]
env={**os.environ,'PGSQL_PORT':'5432','XDEBUG_MODE':'off'}
def run(name,cmd,expect=0):
 with (out/(name+'.log')).open('w') as f:r=subprocess.run(cmd,stdout=f,stderr=subprocess.STDOUT,env=env)
 row={'check':name,'exit':r.returncode,'expected':expect};rows.append(row);(out/'checks.json').write_text(json.dumps(rows,indent=2)+'\n');print(row,flush=True)
 if r.returncode!=expect:raise RuntimeError(row)
def php(*args):return ['scripts/php84','php',*args]
script='plans/2026.09.27-№1-COREX-KERNEL/artifacts/P02-design/scripts/p02-transfer.py'
run('frozen-positive',['git','diff','--exit-code','--','tests/Golden/baseline/','tests/Golden/rename-map.json'])
p=next(Path('tests/Golden/baseline').rglob('*.json'));original=p.read_bytes()
try:
 p.write_bytes(original+b'\n');run('frozen-negative',['git','diff','--exit-code','--',str(p)],1)
finally:p.write_bytes(original)
run('frozen-restored',['git','diff','--exit-code','--','tests/Golden/baseline/','tests/Golden/rename-map.json'])
p=Path('README.md');original=p.read_bytes()
try:
 p.write_bytes(original+b'\nCoreX\\Auth\\Testing\n');run('rename-negative',php('scripts/rename-completeness-gate.php','--sections=classes,namespaces,packages,paths'),1)
finally:p.write_bytes(original)
run('rename-restored',php('scripts/rename-completeness-gate.php','--sections=classes,namespaces,packages,paths'))
p=Path('scripts/rename-completeness.json');original=p.read_bytes()
try:
 d=json.loads(original);d['tokens']=None;p.write_text(json.dumps(d));run('rename-malformed',php('scripts/rename-completeness-gate.php'),2)
finally:p.write_bytes(original)
p=Path('packages/panels/src/TagProjectionProbe.php')
assert not p.exists()
try:
 p.write_text("<?php\nnamespace CoreX\\Panels;\nclass TagProjectionProbe { public function boot(): void { $this->publishes([], ['corex-migrations']); } }\n")
 run('shared-tag-new-source-negative',php('scripts/ownership-gate.php'),1)
 assert 'O1 tag corex-migrations owners ' in (out/'shared-tag-new-source-negative.log').read_text()
finally:p.unlink(missing_ok=True)
run('shared-tag-restored',php('scripts/ownership-gate.php'))
old_test_path=Path('packages/modules/tests');parked_test_path=Path('build/p02/negative-module-test-dirs')
assert not parked_test_path.exists()
parked=old_test_path.exists()
if parked:old_test_path.rename(parked_test_path)
p=Path('composer.json');original=p.read_bytes()
try:
 d=json.loads(original);d['autoload-dev']['classmap'].append('packages/modules/tests');p.write_text(json.dumps(d,indent=4)+'\n')
 run('autoload-verify-negative',['python3',script,'verify'],1)
 run('autoload-composer-negative',['scripts/php84','composer','dump-autoload','--dev'],1)
finally:p.write_bytes(original)
run('autoload-composer-restored',['scripts/php84','composer','dump-autoload','--dev'])
run('autoload-verify-restored',['python3',script,'verify'])
if parked:parked_test_path.rename(old_test_path)
p=Path('scripts/quality-ceilings.json');original=p.read_bytes()
try:
 d=json.loads(original);d['phpstan']['intl']=162;p.write_text(json.dumps(d,indent=4)+'\n')
 run('ratchet-source-transfer-negative',['bash','scripts/anti-weakening-gate.sh'],1)
finally:p.write_bytes(original)
run('ratchet-restored',['bash','scripts/anti-weakening-gate.sh'])
p=Path('packages/tools/phpstan-baseline.neon');original=p.read_bytes()
try:
 p.write_text(re.sub(r'count: (\d+)',lambda m:'count: '+str(int(m[1])+1),original.decode(),count=1))
 run('ceiling-negative',php('scripts/quality-ceiling-guard.php'),1)
finally:p.write_bytes(original)
run('ceiling-restored',php('scripts/quality-ceiling-guard.php'))
for manifest in [Path('composer.json'),*sorted(Path('packages').glob('*/composer.json')),*sorted(Path('products').glob('*/composer.json'))]:
 run('strict-'+('root' if manifest.parent==Path('.') else manifest.parent.name),['scripts/php84','composer','--working-dir='+str(manifest.parent),'validate','--strict'])
assert len(list(Path('packages').glob('*/composer.json')))+len(list(Path('products').glob('*/composer.json')))+1==22
files=[]
for base in ['packages','products','tests','scripts','fixtures']:
 for directory,subdirs,names in os.walk(base):
  subdirs[:]=[d for d in subdirs if d not in ['vendor','node_modules','.phpunit.cache']]
  files.extend(str(Path(directory)/name) for name in names if name.endswith('.php'))
with (out/'php-l.log').open('w') as f:
 for start in range(0,len(files),200):
  batch=files[start:start+200]
  r=subprocess.run(php('-l',*batch),stdout=f,stderr=subprocess.STDOUT)
  if r.returncode:raise RuntimeError('php-l: see '+str(out/'php-l.log'))
rows.append({'check':'php-l','files':len(files),'exit':0,'expected':0})
violations=[p for p in files if p.startswith(('packages/','products/','tests/')) and 'CoreX\\Api\\' in Path(p).read_text()]
assert violations==[],violations
rows.append({'check':'E1','files':len(files),'exit':0,'expected':0});(out/'checks.json').write_text(json.dumps(rows,indent=2)+'\n');print('negative controls and supplemental acceptance GREEN')
