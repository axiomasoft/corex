import json,re,subprocess
from pathlib import Path
root=Path.cwd();out=root/'plans/2026.09.27-№1-COREX-KERNEL/artifacts/P01.3-execution';results=[]
def run(name,args,expected,contains=None):
 r=subprocess.run(['scripts/php84',*args],capture_output=True,text=True);txt=r.stdout+r.stderr
 (out/(name+'.log')).write_text(txt)
 assert r.returncode==expected,(name,r.returncode,txt[:1500])
 if contains:assert all(x in txt for x in contains),(name,txt[:1500])
 results.append({'control':name,'exit':r.returncode,'expected':expected,'assertions':contains or []})
base=root/'scripts/ownership-gate-baseline.json';saved=base.read_bytes()
try:
 data=json.loads(saved);total=len(data['violations'])
 run('ownership-default',['php','scripts/ownership-gate.php'],0,['ownership OK:'])
 run('ownership-strict',['php','scripts/ownership-gate.php','--strict'],1,['ownership FAIL: new:'])
 assert (out/'ownership-strict.log').read_text().count('ownership FAIL: new:')==total
 data['violations'].pop(0);base.write_text(json.dumps(data)+'\n')
 run('ownership-live-row-removed',['php','scripts/ownership-gate.php'],1,['ownership FAIL: new:'])
 data=json.loads(saved);data['violations'].append('O2 config fake stale expected none');base.write_text(json.dumps(data)+'\n')
 run('ownership-stale-row',['php','scripts/ownership-gate.php'],1,['ownership FAIL: stale:'])
finally:base.write_bytes(saved)
fixture=root/'packages/tasks/src/OwnershipNegativeControl.php'
assert not fixture.exists()
try:
 fixture.write_text("<?php\nnamespace CoreX\\Tasks;\nclass OwnershipNegativeControl { public function register(): void { $this->mergeConfigFrom('unused.php', 'corex-media'); } }\n")
 run('ownership-duplicate-config',['php','scripts/ownership-gate.php'],1,['O1 config corex-media','O2 config corex/tasks corex-media'])
finally:fixture.unlink()
run('ownership-restored',['php','scripts/ownership-gate.php'],0,['ownership OK:'])
base=root/'packages/recurrence/phpstan-baseline.neon';saved=base.read_bytes()
try:
 text=saved.decode();base.write_text(re.sub(r'(count: )(\d+)',lambda m:m[1]+str(int(m[2])+1),text,count=1))
 run('ceilings-baseline-growth',['php','scripts/quality-ceiling-guard.php'],1,['phpstan.recurrence: 10 > ceiling 9'])
 base.write_text(re.sub(r'\n\t\t-\n(?:\t\t\t.*\n)+','\n',text,count=1))
 run('phpstan-baseline-row-removed',['vendor/bin/phpstan','analyse','-c','packages/recurrence/phpstan.neon','--memory-limit=2G','--no-progress'],1,['Only booleans'])
finally:base.write_bytes(saved)
run('ceilings-restored',['php','scripts/quality-ceiling-guard.php'],0,['ceilings OK'])
fixture=root/'packages/recurrence/src/DependencyNegativeControl.php';assert not fixture.exists()
try:
 fixture.write_text("<?php\nnamespace CoreX\\Recurrence;\nfunction dependencyNegativeControl(string $text): int { return mb_strlen($text); }\n")
 run('dependency-shadow',['vendor/bin/composer-dependency-analyser','--composer-json=packages/recurrence/composer.json','--config=packages/recurrence/composer-dependency-analyser.php'],1,['shadow dependency','ext-mbstring'])
finally:fixture.unlink()
run('dependency-shadow-restored',['vendor/bin/composer-dependency-analyser','--composer-json=packages/recurrence/composer.json','--config=packages/recurrence/composer-dependency-analyser.php'],0,['No composer issues found'])
for relative,contents,expected in [
 ('packages/core/src/OwnershipRootNegativeControl.php', '<?php\nnamespace CoreX;\nclass OwnershipRootNegativeControl {}\n', 'O3 symbol corex/core CoreX\\OwnershipRootNegativeControl'),
 ('packages/tasks/database/migrations/OwnershipTableNegativeControl.php', "<?php\nSchema::create('aut_role_assignments', function () {});\n", 'O4 table corex/tasks aut_role_assignments catalog-owner corex/access'),
]:
 fixture=root/relative;assert not fixture.exists()
 try:
  fixture.write_text(contents)
  run('ownership-'+fixture.stem,['php','scripts/ownership-gate.php'],1,[expected])
 finally:fixture.unlink()
run('ownership-all-restored',['php','scripts/ownership-gate.php'],0,['ownership OK:'])
(out/'negative-controls.json').write_text(json.dumps(results,indent=2)+'\n');print(json.dumps(results,indent=2))
