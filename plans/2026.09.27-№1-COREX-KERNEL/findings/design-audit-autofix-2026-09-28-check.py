#!/usr/bin/env python3
"""Verify the current design's executable snippets without modifying product files."""
from __future__ import annotations

import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile

PLAN = Path(__file__).resolve().parents[1]
ROOT = PLAN.parents[1]


def run(args, cwd, env=None):
    result = subprocess.run(args, cwd=cwd, env=env, text=True, capture_output=True)
    assert result.returncode == 0, (args, result.returncode, result.stdout, result.stderr)
    return {'exit': result.returncode, 'stdout': result.stdout, 'stderr': result.stderr}


def main():
    examples = (PLAN / 'artifacts/P01-design/examples.md').read_text()
    blocks = re.findall(r'```php\n(.*?)\n```', examples, re.S)
    checks = {}
    prior = importlib.util.spec_from_file_location('prior', PLAN / 'findings/design-remediation-2026-09-28-check.py')
    module = importlib.util.module_from_spec(prior)
    prior.loader.exec_module(module)
    checks['B1-B3'] = module.check()
    with tempfile.TemporaryDirectory(prefix='corex-design-audit-') as folder:
        scratch = Path(folder)
        for name, needle in [('GoldenSnapshot', 'final class GoldenSnapshot'), ('RenameMap', 'final readonly class RenameMap'), ('Normalizer', 'final class Normalizer')]:
            (scratch / f'{name}.php').write_text(next(block for block in blocks if needle in block))
        spec = importlib.util.spec_from_file_location('generator', PLAN / 'artifacts/P01-design/scripts/golden-rename-map.py')
        generator = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(generator)
        (scratch / 'map.json').write_text(json.dumps(generator.build([])))
        # The current root does not install access. P01.1 explicitly fixes that closure;
        # use the already installed access upstream for this read-only dry run.
        fixture = '''<?php
require __DIR__.'/GoldenSnapshot.php';
require __DIR__.'/RenameMap.php';
require __DIR__.'/Normalizer.php';
use CoreX\\Tests\\Golden\\Support\\{GoldenSnapshot, RenameMap, Normalizer};
function verify(bool $ok, string $why): void { if (!$ok) { throw new RuntimeException($why); } }
$loader = require ROOT.'/vendor/autoload.php';
$loader->addPsr4('AzGuard\\\\', ROOT.'/packages/access/vendor/axioma-studio/azguard-core/src');
$classes = GoldenSnapshot::sourceClasses(ROOT);
$raw = GoldenSnapshot::phpSurface($classes);
$map = RenameMap::load(__DIR__.'/map.json');
$baseline = Normalizer::normalize($map->apply($raw));
$after = $map->apply($raw);
$derivedChanges = 0;
foreach ($after as $name => &$row) {
    $internal = str_contains($name, '\\\\Internal\\\\');
    if ($row['internal'] !== $internal) { $derivedChanges++; }
    $row['internal'] = $internal;
    sort($row['interfaces']);
}
unset($row);
verify($baseline === Normalizer::normalize($after), 'real source projection before/after');
verify($baseline === Normalizer::normalize($map->apply($baseline)), 'real source idempotence');
$json = json_encode($baseline, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR);
verify($baseline === json_decode($json, true, flags: JSON_THROW_ON_ERROR), 'real surface JSON roundtrip');
class FloatFixture { public const RATE = 1.0; public function run(float $value = 0.0): void {} }
$floats = GoldenSnapshot::phpSurface([FloatFixture::class]);
verify($floats === json_decode(json_encode($floats, JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR), true), 'float defaults/constants roundtrip');
verify($floats !== json_decode(json_encode($floats), true), 'old JSON flags reproduce mismatch');
class OrderA { public const OPTIONS=['nested'=>['b'=>2,'a'=>1]]; public function run(array $options=['b'=>2,'a'=>1]): void {} }
class OrderB { public const OPTIONS=['nested'=>['a'=>1,'b'=>2]]; public function run(array $options=['a'=>1,'b'=>2]): void {} }
$orderA = GoldenSnapshot::phpSurface([OrderA::class])[OrderA::class];
$orderB = GoldenSnapshot::phpSurface([OrderB::class])[OrderB::class];
verify(Normalizer::normalize(['Example'=>$orderA]) !== Normalizer::normalize(['Example'=>$orderB]), 'associative defaults/constants preserve insertion order');
verify(Normalizer::normalize(['Example'=>$orderA])['Example']['constants']['OPTIONS']['value']['nested'] === ['b'=>2,'a'=>1], 'nested constant ordering');
verify(Normalizer::normalize(['Example'=>$orderA])['Example']['methods']['run']['params'][0]['default'] === ['b'=>2,'a'=>1], 'default ordering');
$wire = ['operations' => [['id'=>'one', 'request_schema'=>[], 'response_schema'=>[], 'contract_hash'=>'old', 'module'=>'old']]];
$projected = $wire; $projected['operations'][0]['contract_hash']='new';
verify(Normalizer::normalize($wire) === Normalizer::normalize($projected), 'derived operation hash');
$projected['operations'][0]['request_schema']=['type'=>'string'];
verify(Normalizer::normalize($wire) !== Normalizer::normalize($projected), 'semantic request schema');
$semantic = ['methods'=>['files'=>[], 'generated_at'=>[]], 'schema'=>['properties'=>['contract_hash'=>['type'=>'string']]], 'params'=>[2,1]];
verify(Normalizer::normalize($semantic)['params'] === [2,1], 'ordered parameter/default arrays');
verify(isset(Normalizer::normalize($semantic)['methods']['files']), 'same-named API members retained');
echo json_encode(['classes'=>count($classes), 'derived_internal_changes'=>$derivedChanges, 'before_after'=>true, 'roundtrip'=>true, 'float_controls'=>true, 'semantic_controls'=>true]);
'''.replace('ROOT', json.dumps(str(ROOT)))
        (scratch / 'real-surface.php').write_text(fixture)
        checks['real-source'] = run(['php8.4', str(scratch / 'real-surface.php')], ROOT)
        wire = r'''<?php
namespace Illuminate\Support\Facades {
    final class Artisan {
        public static string $mode = 'good';
        public static function call(string $name, array $args): int {
            if ($name !== 'corex:api-snapshots' || !is_file($args['--fixture'] ?? '')) { throw new \RuntimeException('missing fixture'); }
            mkdir($args['directory']);
            foreach (['errors','operations','openapi','webhooks','manifest'] as $file) {
                if (self::$mode === 'partial' && $file === 'webhooks') { continue; }
                file_put_contents($args['directory'].'/'.$file.'.json', '{}');
            }
            return self::$mode === 'failed' ? 1 : 0;
        }
        public static function output(): string { return 'test failure'; }
    }
}
namespace {
    require __DIR__.'/GoldenSnapshot.php';
    use CoreX\Tests\Golden\Support\GoldenSnapshot;
    use Illuminate\Support\Facades\Artisan;
    $fixture = __DIR__.'/fixture.php'; file_put_contents($fixture, '<?php return [];');
    foreach (['good','failed','partial'] as $mode) {
        Artisan::$mode=$mode;
        $tmp=__DIR__.'/wire-'.$mode;
        $caught=false;
        try { $result=GoldenSnapshot::wire($tmp,$fixture); } catch (\RuntimeException $error) { $caught=true; }
        if (($mode==='good')===$caught || is_dir($tmp)) { throw new \RuntimeException('wire mode/cleanup '.$mode); }
    }
    echo "wire fixture/exit/missing-document/cleanup controls GREEN\n";
}
'''
        (scratch / 'wire.php').write_text(wire)
        checks['wire'] = run(['php8.4', str(scratch / 'wire.php')], scratch)
        # Run the actual ownership block against committed scratch histories.
        match = re.search(r'(python3 - "\$DIFF_RANGE" <<\'PYGUARD\'.*?\nPYGUARD)', examples, re.S)
        assert match
        ownership = scratch / 'ownership'
        ownership.mkdir()
        run(['git', 'init', '-q', str(ownership)], ROOT)
        run(['git', 'config', 'user.name', 'Design fixture'], ownership)
        run(['git', 'config', 'user.email', 'fixture@example.invalid'], ownership)
        (ownership / 'README').write_text('fixture')
        run(['git', 'add', '.'], ownership)
        run(['git', 'commit', '-qm', 'base'], ownership)
        base = run(['git', 'rev-parse', 'HEAD'], ownership)['stdout'].strip()
        (ownership / 'scripts').mkdir()
        baseline = ownership / 'scripts/ownership-gate-baseline.json'
        baseline.write_text(json.dumps({'violations':['O1 old']}))
        run(['git', 'add', '.'], ownership)
        run(['git', 'commit', '-qm', 'initial baseline'], ownership)
        initial = run(['git', 'rev-parse', 'HEAD'], ownership)['stdout'].strip()
        script = ownership / 'check.sh'
        script.write_text('set -euo pipefail\nDIFF_RANGE="$1..HEAD"\nlog_fail() { exit 1; }\n'+match[1]+'\n')
        checks['ownership-initial'] = run(['bash', str(script), base], ownership)
        baseline.write_text(json.dumps({'violations':['O1 old','O2 new']}))
        run(['git', 'add', 'scripts'], ownership)
        run(['git', 'commit', '-qm', 'growth'], ownership)
        result = subprocess.run(['bash', str(script), initial], cwd=ownership, capture_output=True, text=True)
        assert result.returncode == 1, result
        checks['ownership-growth'] = {'exit':result.returncode}
        result = subprocess.run(['bash', str(script), base], cwd=ownership, capture_output=True, text=True)
        assert result.returncode == 1, result
        checks['ownership-growth-precreation-base'] = {'exit':result.returncode}
        baseline.write_text(json.dumps({'violations':[]}))
        run(['git', 'add', 'scripts'], ownership)
        run(['git', 'commit', '-qm', 'tighten'], ownership)
        checks['ownership-tighten'] = run(['bash', str(script), initial], ownership)
        ceiling_match = re.search(r'(python3 - "\$DIFF_RANGE" <<\'PY\'.*?\nPY)', examples, re.S)
        assert ceiling_match
        ceiling_script = ownership / 'ceilings-check.sh'
        ceiling_script.write_text('set -euo pipefail\nDIFF_RANGE="$1..HEAD"\nlog_fail() { exit 1; }\n'+ceiling_match[1]+'\n')
        ceiling_file = ownership / 'scripts/quality-ceilings.json'
        ceiling_file.write_text(json.dumps({'phpstan':{'a':1},'dependencies':{}}))
        run(['git', 'add', 'scripts'], ownership)
        run(['git', 'commit', '-qm', 'initial ceilings'], ownership)
        checks['ceilings-initial'] = run(['bash', str(ceiling_script), base], ownership)
        ceiling_file.write_text(json.dumps({'phpstan':{'a':2},'dependencies':{}}))
        result = subprocess.run(['bash', str(ceiling_script), base], cwd=ownership, capture_output=True, text=True)
        assert result.returncode == 1, result
        checks['ceilings-growth-precreation-base'] = {'exit':result.returncode}
        ceiling_file.write_text(json.dumps({'phpstan':{'a':1,'new':1},'dependencies':{}}))
        result = subprocess.run(['bash', str(ceiling_script), base], cwd=ownership, capture_output=True, text=True)
        assert result.returncode == 1, result
        checks['ceilings-new-key-growth'] = {'exit':result.returncode}
        ceiling_file.write_text(json.dumps({'phpstan':{'a':0},'dependencies':{}}))
        checks['ceilings-tighten'] = run(['bash', str(ceiling_script), base], ownership)
        analyser = scratch / 'analyser'
        analyser.mkdir()
        run(['git', 'init', '-q', str(analyser)], ROOT)
        for directory in ['packages/access', 'packages/tenancy', 'vendor/bin', 'bin']:
            (analyser / directory).mkdir(parents=True, exist_ok=True)
        for package in ['access', 'tenancy']:
            (analyser / f'packages/{package}/composer.json').write_text('{}')
        shell_blocks = re.findall(r'```bash\n(.*?)\n```', examples, re.S)
        analyser_script = next(block for block in shell_blocks if 'run() {' in block and 'composer-dependency-analyser' in block)
        (analyser / 'run.sh').write_text(analyser_script)
        fake_composer = '''#!/usr/bin/env bash
printf 'composer %s\\n' "$*" >> "$CALL_LOG"
if [[ "$*" == *update* && "$*" == *"packages/$FAIL_PACKAGE"* ]]; then exit 7; fi
exit 0
'''
        for name, content in [('composer', fake_composer), ('python3', '#!/usr/bin/env bash\nprintf "stage\\n" >> "$CALL_LOG"\nexit 0\n')]:
            file = analyser / 'bin' / name
            file.write_text(content)
            file.chmod(0o755)
        executable = analyser / 'vendor/bin/composer-dependency-analyser'
        executable.write_text('#!/usr/bin/env bash\nprintf "analyser %s\\n" "$*" >> "$CALL_LOG"\nexit 0\n')
        executable.chmod(0o755)
        log = analyser / 'calls'
        env = dict(os.environ, PATH=str(analyser / 'bin')+':'+os.environ['PATH'], CALL_LOG=str(log), FAIL_PACKAGE='tenancy')
        result = subprocess.run(['bash', 'run.sh', '--all'], cwd=analyser, env=env, capture_output=True, text=True)
        calls = log.read_text()
        assert result.returncode == 1, result
        assert 'analyser --composer-json=packages/access/' in calls
        assert 'analyser --composer-json=packages/tenancy/' not in calls
        assert 'composer config --unset repositories.stancl-providers' in calls
        assert 'composer --working-dir=packages/tenancy config --unset repositories.stancl-providers' in calls
        checks['analyser-failed-install'] = {'exit':result.returncode, 'calls':calls}
        log.write_text('')
        env['FAIL_PACKAGE'] = 'none'
        checks['analyser-clean'] = run(['bash', 'run.sh', '--all'], analyser, env=env)
    print(json.dumps({'checks':checks,'scope':'current design snippets; no product tests/DB/execution'}, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()
