#!/usr/bin/env python3
"""Project standalone analysis inputs and debt attached to exact moved sources."""
import json, re, subprocess
from pathlib import Path

def edit(path, fn):
    p = Path(path)
    p.write_text(fn(p.read_text()))

for area, removals in {
    'core': ['brick/money'],
    'access': ['axiomasoft/corex-tenancy', 'axiomasoft/stancl-tenancy', 'axiomasoft/stancl-jobpipeline'],
    'tools': ['ext-filter', 'ext-mbstring', 'psr/clock'],
    'webhooks': ['ext-mbstring', 'symfony/http-kernel'],
}.items():
    p = Path('packages', area, 'composer.json')
    data = json.loads(p.read_text())
    for key in removals:
        for section in ['require', 'require-dev']:
            data.get(section, {}).pop(key, None)
    if area == 'access':
        data.setdefault('suggest', {})['axiomasoft/corex-tenancy'] = 'Optional workspace scope; the configured class is loaded only when installed.'
    for name in data.get('require', {}):
        data.get('require-dev', {}).pop(name, None)
    p.write_text(json.dumps(data, indent=4) + '\n')
for area in ['tenancy']:
    p = Path('packages', area, 'composer.json')
    data = json.loads(p.read_text())
    for name in data.get('require', {}):
        data.get('require-dev', {}).pop(name, None)
    p.write_text(json.dumps(data, indent=4) + '\n')

edit('packages/core/composer-dependency-analyser.php', lambda s: s if "__DIR__.'/src/Modules/PHPStan'" in s else s.replace("    ->addPathToScan(__DIR__.'/src/Messaging/PHPStan', isDev: true)", "    ->addPathToScan(__DIR__.'/src/Messaging/PHPStan', isDev: true)\n    ->addPathToScan(__DIR__.'/src/Modules/PHPStan', isDev: true)"))

# Exact obsolete ignores from the former combined installation. Removing an
# unused exception strengthens the standalone check.
prune = {
    'core': ['StubBaseModule', 'StubFailingModule', 'StubFeatureFlagModule', "Stubs\\\\Widget", "ignoreErrorsOnPackage('laravel/framework'"],
    'custom-fields': ["ignoreErrorsOnPackage('axiomasoft/corex'"],
    'operations': ["ignoreErrorsOnPackage('opis/json-schema'", 'ignoreErrorsOnExtension(\'ext-curl\'', 'CurlRuntimeFixture'],
    'tools': ["ignoreErrorsOnPackage('axiomasoft/corex-transfer'", 'ignoreErrorsOnExtension(\'ext-curl\'', 'CurlRuntimeFixture'],
    'webhooks': ["ignoreErrorsOnPackage('axiomasoft/corex-transfer'", "ignoreErrorsOnPackage('opis/json-schema'"],
}
for area, keys in prune.items():
    p = Path('packages', area, 'composer-dependency-analyser.php')
    lines = p.read_text().splitlines()
    out, methods = [], set()
    for line in lines:
        if any(key in line for key in keys) or ('->' in line and line.strip().rstrip(';') in methods):
            if out and out[-1].strip().startswith('//'):
                out.pop()
            continue
        if '->' in line:
            methods.add(line.strip().rstrip(';'))
        out.append(line)
    p.write_text('\n'.join(out).rstrip().rstrip(';') + ';\n')
edit('packages/tasks-workflow/composer-dependency-analyser.php', lambda s: s.replace(
    "['ActorType', 'DB', 'DataScope', 'DataScopeAuthorizer', 'MaterializationHasher', 'ModuleRegistry', 'Project', 'ScopeContextMismatch', 'SeriesSeeder', 'Stage', 'Task', 'TaskAccess', 'TaskRecurrenceCommand', 'TenantContextResolver', 'TenantContextRestorer']",
    "['DB', 'MaterializationHasher', 'ModuleRegistry', 'SeriesSeeder', 'TaskRecurrenceCommand']"))
edit('packages/wakeup/composer-dependency-analyser.php', lambda s: s if "'FailingWakeupTestHandler'" in s else s.rstrip().rstrip(';') +
     "\n    // Pest declares these exact local fixtures while executing its test files.\n"
     "    ->ignoreUnknownClasses(['FailingWakeupTestHandler', 'NotAWakeupHandler', 'PayloadRecordingWakeupHandler', 'PgVersionedWakeupHandler', 'RecordingWakeupHint', 'RegistryTestHandler', 'WakeupTestHandler']);\n")

# Removing the old production classmap exposes two secondary declarations.
# Give their unchanged declarations ordinary PSR-4 files.
p = Path('packages/operations/src/Cost/Internal/BudgetAdmission.php')
s = p.read_text()
header = s[:s.index('/**')]
for name in ['BudgetStoreUnavailable', 'BudgetRateLimited']:
    match = re.search(r'/\*\*[^*]*\*/\s*final class ' + name + r' extends [^{}]+\{\}', s)
    if match:
        p.with_name(name + '.php').write_text(header + match[0] + '\n')
        s = s[:match.start()] + s[match.end():]
p.write_text(s.rstrip() + '\n')

# Composer vendor sorting changed during rename. Explicit analysis composition
# keeps the original CoreX console signatures when stancl uses the same names.
Path('scripts/phpstan-tenancy-commands.php').write_text('''<?php

declare(strict_types=1);

namespace CoreX\\Tenancy\\Laravel;

require_once __DIR__.'/../vendor/larastan/larastan/bootstrap.php';

(new \\CoreX\\Tenancy\\Laravel\\ConsoleRegistrar)->boot(app());
''')
edit('packages/tenancy/phpstan.neon', lambda s: s if 'phpstan-tenancy-commands.php' in s else s.replace(
    '    level: max\n', '    level: max\n    bootstrapFiles:\n        - ../../scripts/phpstan-tenancy-commands.php\n'))

# Each moved baseline row grants its target exactly that count and deducts the
# same count from its source. This is narrower than pooling target packages.
plan = json.loads(Path('build/p02/plan.json').read_text())
moves = dict(plan['moves'])
old = subprocess.check_output(['git', 'show', 'HEAD:packages/core/phpstan-baseline.neon'], text=True)
rows = []
for block in old.split('\n\t\t-\n'):
    path = re.search(r'^\s+path: (.+)$', block, re.M)
    count = re.search(r'^\s+count: (\d+)$', block, re.M)
    if not path or not count:
        continue
    source = 'packages/core/' + path[1]
    target = moves.get(source, source)
    if target.split('/')[1] != 'core':
        rows.append({'source': source, 'target': target, 'count': int(count[1])})
transfers = []
for target in sorted({r['target'].split('/')[1] for r in rows}):
    transfers.append({'from': 'core', 'to': target, 'phpstan': sum(r['count'] for r in rows if r['target'].split('/')[1] == target)})
assert transfers == [{'from': 'core', 'to': 'intl', 'phpstan': 2}, {'from': 'core', 'to': 'panels', 'phpstan': 31}], transfers
p = Path('scripts/ratchet-successors.json')
successors = json.loads(p.read_text())
successors['source_transfers'] = {'when_before_contains': ['core', 'modules'], 'entries': transfers}
p.write_text(json.dumps(successors, indent=4) + '\n')
Path('build/p02/debt-source-projection.json').write_text(json.dumps({'rows': rows, 'transfers': transfers}, indent=2) + '\n')
# Rector removed the former DepartmentModel alias. The sabotage remains exactly
# the original stale read / missing parent guard, with its reference projected.
for name in ['P1.23-stale-read', 'P1.29-move-parent-workspace']:
    edit('scripts/mutations/' + name + '.patch', lambda s: s.replace('DepartmentModel', 'Models\\Department'))
edit('scripts/anti-weakening-gate.sh', lambda s: s.replace(
    '        before, after = old[category], new[category]',
    """        before, after = dict(old[category]), new[category]
        projection = succ.get('source_transfers', {})
        if all(k in before for k in projection.get('when_before_contains', ['__absent__'])):
            for transfer in projection['entries']:
                amount = transfer.get(category, 0)
                before[transfer['from']] -= amount
                before[transfer['to']] = before.get(transfer['to'], 0) + amount"""))
print('quality: exact source debt, standalone dependencies and analysis composition projected')
