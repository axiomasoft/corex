from pathlib import Path
import subprocess,json,re,sys
repo=Path(__file__).resolve().parents[4]
root=Path(sys.argv[1]).resolve()
log=Path(__file__).with_name('controls.log')
php84=str(repo/'scripts/php84')
def run(label,expected=0,env=None):
 cmd=[php84,'vendor/bin/pest','--testsuite=Golden']
 if env: cmd=['env',*env, *cmd]
 p=subprocess.run(cmd,cwd=root,text=True,capture_output=True)
 with log.open('a') as f:f.write(f'{label}: exit {p.returncode} (expected {expected})\n'+p.stdout+p.stderr+'\n')
 if (p.returncode==0)!=(expected==0): raise RuntimeError(label)
def mutation(label,file,old,new,expected=1):
 p=root/file;s=p.read_text();assert old in s,label
 try:p.write_text(s.replace(old,new));run(label,expected)
 finally:p.write_text(s)
 run(label+' restored')
run('unchanged pre-rename')
# A leaf contract does not require production autoload before the process-local source map.
p=root/'packages/core/src/Contracts/DurablePayload.php';s=p.read_text();m=json.loads((root/'tests/Golden/rename-map.json').read_text());rule=next(x for x in m['generated'] if x['from']=='CoreX\\Contracts\\DurablePayload')
newns,newname=rule['to'].rsplit('\\',1)
try:
 p.write_text(s.replace('namespace CoreX\\Contracts;',f'namespace {newns};').replace('interface DurablePayload',f'interface {newname}'))
 # Update fully qualified references; the collector installs all declared final names.
 changed=[]
 for f in (root/'packages').glob('*/src/**/*.php'):
  t=f.read_text()
  if rule['from'] in t:changed.append((f,t));f.write_text(t.replace(rule['from'],rule['to']))
 run('planned class rename with frozen baseline/map')
finally:
 p.write_text(s)
 for f,t in changed:f.write_text(t)
run('planned rename restored')
mutation('route URI',Path('packages/operations/routes/api.php'),"'{version}/capabilities'","'{version}/capabilities-broken'")
mutation('public method',Path('packages/core/src/Support/SecretRef.php'),'function reveal()','function revealBroken()')
p=root/'packages/core/src/Contracts/DurablePayload.php';s=p.read_text();mapfile=root/'tests/Golden/rename-map.json';maps=mapfile.read_text()
changed=[]
try:
 p.write_text(s.replace('namespace CoreX\\Contracts;','namespace CoreX\\GoldenUnmapped;'))
 for f in (root/'packages').glob('*/src/**/*.php'):
  t=f.read_text()
  if 'CoreX\\Contracts\\DurablePayload' in t:changed.append((f,t));f.write_text(t.replace('CoreX\\Contracts\\DurablePayload','CoreX\\GoldenUnmapped\\DurablePayload'))
 run('unmapped namespace',1)
 m=json.loads(maps);m['manual'].append({'kind':'identifier','from':'CoreX\\GoldenUnmapped\\DurablePayload','to':rule['to']});mapfile.write_text(json.dumps(m))
 run('namespace covered by exact manual rule')
finally:
 p.write_text(s);mapfile.write_text(maps)
 for f,t in changed:f.write_text(t)
run('manual rename restored')
mutation('wire error code',Path('tests/fixtures/public-error-contract.php'),"'code' => 'not_found'","'code' => 'not_found_broken'")
# Exercise independent surface deltas on one dedicated scratch declaration.
probe=root/'packages/core/src/GoldenProbe.php'
probe.write_text('''<?php
namespace CoreX\\GoldenProbe;
final class Probe {
 public const LIMIT = 3;
 public string $label;
 public function run(?string $mode = null, float $ratio = 1.0): void {}
}
''')
run('scratch-only surface probe baseline',env=['GOLDEN_UPDATE=1'])
for label,old,new in [('property type','public string $label','public int $label'),('constant value','LIMIT = 3','LIMIT = 4'),('constant type','LIMIT = 3',"LIMIT = '3'"),('parameter default','?string $mode = null',"?string $mode = 'fast'"),('null vs absent default','?string $mode = null','?string $mode')]:
 mutation(label,Path('packages/core/src/GoldenProbe.php'),old,new)
probe.unlink()
subprocess.run(['git','restore','tests/Golden/baseline'],cwd=root,check=True)
run('all controls restored')
