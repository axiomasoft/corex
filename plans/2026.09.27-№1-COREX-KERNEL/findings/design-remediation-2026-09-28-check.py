#!/usr/bin/env python3
"""Run actual P01 design snippets in temporary fixtures; never write product files."""
from __future__ import annotations

import importlib.util
import json
from pathlib import Path
import re
import shutil
import subprocess
import tempfile

PLAN = Path(__file__).resolve().parents[1]
EXAMPLES = (PLAN / 'artifacts/P01-design/examples.md').read_text()


def run(args, cwd):
    result = subprocess.run(args, cwd=cwd, text=True, capture_output=True)
    return {'exit': result.returncode, 'stdout': result.stdout, 'stderr': result.stderr}


def check():
    php = shutil.which('php8.4')
    assert php, 'PHP 8.4 required for design snippet verification'
    spec = importlib.util.spec_from_file_location(
        'rename_generator', PLAN / 'artifacts/P01-design/scripts/golden-rename-map.py')
    generator = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(generator)
    mapping = generator.build([])
    blocks = re.findall(r'```php\n(.*?)\n```', EXAMPLES, re.S)
    rename = next(b for b in blocks if 'final readonly class RenameMap' in b)
    surface = next(b for b in blocks if 'final class GoldenSnapshot' in b)
    golden = next(b for b in blocks if "dataset('layers'" in b)
    assert '$actual = Normalizer::normalize($map->apply($current));' in golden
    assert 'json_encode($actual,' in golden and 'expect($actual)->toBe($expected);' in golden
    checks = {}
    with tempfile.TemporaryDirectory(prefix='corex-p01-design-') as folder:
        scratch = Path(folder)
        (scratch / 'rename-map.json').write_text(json.dumps(mapping))
        (scratch / 'RenameMap.php').write_text(rename)
        (scratch / 'GoldenSnapshot.php').write_text(surface)
        fixture = r'''<?php
require __DIR__.'/RenameMap.php';
require __DIR__.'/GoldenSnapshot.php';
use CoreX\Tests\Golden\Support\RenameMap;
use CoreX\Tests\Golden\Support\GoldenSnapshot;
function verify(bool $ok, string $label): void {
    if (! $ok) { throw new RuntimeException($label); }
}
$document = json_decode(file_get_contents(__DIR__.'/rename-map.json'), true);
$map = RenameMap::load(__DIR__.'/rename-map.json');
$old = $new = [];
foreach ($document['generated'] as $rule) {
    $old[$rule['from']] = ['token' => $rule['from'], 'behavior' => 42];
    $new[$rule['to']] = ['token' => $rule['to'], 'behavior' => 42];
}
$baseline = $map->apply($old);
verify($map->apply($baseline) === $map->apply($old), 'P01 old names');
verify($map->apply($baseline) === $map->apply($new), 'P02 final names');
verify($map->apply($baseline) === $baseline, 'idempotent map');
$key = array_key_first($new);
$new[$key]['behavior'] = 43;
verify($map->apply($new) !== $baseline, 'behavior drift detected');
verify($map->apply(['?CoreX\\Api\\ApiController', 'CoreX\\Api\\ApiControllerExtra']) ===
    ['?CoreX\\Operations\\Http\\ApiController', 'CoreX\\Api\\ApiControllerExtra'], 'exact FQCN tokens');
enum Mode: string { case Fast = 'fast'; }
class SurfaceFixture {
    public const LIMIT = 3;
    public const MODE = Mode::Fast;
    private const SECRET = 1;
    public readonly string $label;
    public static int $count = 0;
    protected string $hidden;
    public function run(?string $mode = null, array $options = ['limit' => 3], Mode $enum = Mode::Fast): void {}
    public function required(?string $mode): void {}
    public function variadic(string ...$args): void {}
}
class SurfaceChanged {
    public const LIMIT = 4;
    public readonly int $label;
    public function run(?string $mode = 'fast'): void {}
}
class SurfaceTypeChanged { public const LIMIT = '3'; }
$a = GoldenSnapshot::phpSurface([SurfaceFixture::class])[SurfaceFixture::class];
$b = GoldenSnapshot::phpSurface([SurfaceChanged::class])[SurfaceChanged::class];
$c = GoldenSnapshot::phpSurface([SurfaceTypeChanged::class])[SurfaceTypeChanged::class];
verify($a['constants']['LIMIT'] === ['value' => 3, 'type' => 'int'], 'constant name/value/type');
verify($a['constants']['MODE']['value'] === ['enum' => Mode::class, 'case' => 'Fast', 'value' => 'fast'], 'enum value');
verify(! isset($a['constants']['SECRET']) && ! isset($a['properties']['hidden']), 'nonpublic excluded');
verify($a['properties']['label'] === ['name' => 'label', 'type' => 'string', 'static' => false, 'readonly' => true], 'public property');
verify($a['properties']['count']['static'] === true, 'static property');
verify(array_key_exists('default', $a['methods']['run']['params'][0]) && $a['methods']['run']['params'][0]['default'] === null, 'default null');
verify(! array_key_exists('default', $a['methods']['required']['params'][0]), 'missing default');
verify(! array_key_exists('default', $a['methods']['variadic']['params'][0]), 'variadic missing default');
verify($a['methods']['run']['params'][1]['default'] === ['limit' => 3], 'array default');
verify($a['methods']['run']['params'][2]['default'] === ['enum' => Mode::class, 'case' => 'Fast', 'value' => 'fast'], 'enum default');
verify($a['properties']['label']['type'] !== $b['properties']['label']['type'], 'property type drift');
verify($a['constants']['LIMIT'] !== $b['constants']['LIMIT'], 'constant value drift');
verify($a['constants']['LIMIT'] !== $c['constants']['LIMIT'], 'constant type drift');
verify($a['methods']['run']['params'][0] !== $b['methods']['run']['params'][0], 'default drift');
echo "B1: all generated renames pass before/after; idempotent; drift fails\n";
echo "B2: constants/properties/defaults recorded; value/type/default drift differs\n";
'''
        (scratch / 'fixture.php').write_text(fixture)
        checks['B1-B2'] = run([php, str(scratch / 'fixture.php')], scratch)
        assert checks['B1-B2']['exit'] == 0, checks['B1-B2']

        content = {
            'composer.json': '{}\n', 'packages/tenancy/composer.json': '{}\n',
            'packages/tenancy/README.md': 'PHP 8.4\n', 'packages/tenancy/PACKAGE.md': 'PHP 8.4\n',
            'README.md': 'PHP 8.4\n', 'docs/readme.md': 'PHP 8.4\n',
            'docs-public/readme.md': 'PHP 8.4\n', 'scripts/check.php': '<?php\n',
            'tests/check.php': '<?php\n',
            '.github/workflows/ci.yml': "permissions: {}\nphp: ['8.4', '8.5']\n"
                + '  - uses: actions/checkout@' + 'a' * 40 + ' # v7.0.1\n',
        }
        for relative, data in content.items():
            path = scratch / relative
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text(data)
        scenarios = [
            ('P01.1', 0, 'composer.json', '{"stancl-providers": {}}\n'),
            ('P01.1', 1, 'README.md', 'PHP 8.3\n'),
            ('P01.3', 0, 'scripts/check.php', 'phpstan-contour\nanalyse:packages\n'),
            ('P01.4', 0, '.github/workflows/ci.yml', '  - uses: actions/checkout@v7\n'),
            ('P01.4', 1, '.github/workflows/ci.yml', "php: ['8.3']\nBoxed profile\n"),
        ]
        for item, index, relative, violation in scenarios:
            text = (PLAN / f'phases/P01/{item}.md').read_text()
            commands = re.findall(r'`(bash -c [^`]+)`', text)
            command = commands[index]
            clean = run(['bash', '-euo', 'pipefail', '-c', command], scratch)
            path = scratch / relative
            path.write_text(violation)
            bad = run(['bash', '-euo', 'pipefail', '-c', command], scratch)
            required_target = scratch / 'scripts' if item == 'P01.3' else path
            backup = scratch / '.missing-carrier'
            required_target.rename(backup)
            missing = run(['bash', '-euo', 'pipefail', '-c', command], scratch)
            backup.rename(required_target)
            path.write_text(content[relative])
            checks[f'B3-{item}-{index}'] = {'command': command, 'clean': clean, 'violation': bad, 'missing': missing}
            assert clean['exit'] == 0 and bad['exit'] == 1 and missing['exit'] == 1, checks[f'B3-{item}-{index}']
        dossier = (PLAN / 'research/P01-design.md').read_text()
        assert checks['B3-P01.4-0']['command'] in dossier, 'same pin check in dossier'
    return {'scope': 'design snippets only; no product/PHP/DB suite or independent audit', 'checks': checks}


if __name__ == '__main__':
    print(json.dumps(check(), ensure_ascii=False, indent=2))
