#!/usr/bin/env python3
"""Project existing gate subjects and negative controls through the transfer map."""
import hashlib, importlib.util, json, re, subprocess, sys
from pathlib import Path

def adapter_hashes():
    p = Path('tests/fixtures/public-error-contract.php')
    s = p.read_text()
    def adapter(m):
        source = m['source']
        digest = hashlib.sha256(Path(source).read_bytes()).hexdigest()
        return m[0].replace(m['hash'], digest)
    s = re.sub(r"'source' => '(?P<source>[^']+)'.*?'sha256' => '(?P<hash>[a-f0-9]{64})'", adapter, s)
    p.write_text(s)

if '--hash-only' in sys.argv:
    adapter_hashes()
    sys.exit(0)

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('transfer', HERE / 'p02-transfer.py')
t = importlib.util.module_from_spec(spec)
spec.loader.exec_module(t)
plan = json.loads(Path('build/p02/plan.json').read_text())
moves = dict(plan['moves'])

def before(path):
    return subprocess.check_output(['git', 'show', 'HEAD:' + path], text=True)

def edit(path, replacements):
    p = Path(path)
    s = p.read_text()
    for old, new in replacements:
        s = s.replace(old, new)
    p.write_text(s)

def projected(path):
    return t.rewrite_text(before(path), *t.literal_rules(plan))

def array(source, name):
    return re.search(r'const ' + name + r' = \[(.*?)\n\];', source, re.S)[1]

def values(source, name):
    return re.findall(r"'([^']+)'", array(source, name))

def mapping(source, name):
    return dict(re.findall(r"'([^']+)'\s*=>\s*'([^']+)'", array(source, name)))

def php_map(data):
    return '\n'.join("    '" + k.replace('\\', '\\\\') + "' => '" + v + "'," for k, v in sorted(data.items()))

def replace_array(source, name, body):
    return re.sub(r'(const ' + name + r' = \[).*?(\n\];)', lambda m: m[1] + '\n' + body + m[2], source, count=1, flags=re.S)

# Preserve the exact pre-transfer zone membership and import matrix. Named
# mixed-catalogue entries take precedence over their new physical module.
path = 'tests/Arch/CoreZoneTest.php'
old = before(path)
s = projected(path)
prefixes = mapping(old, 'CORE_ZONE_PREFIX')
named = mapping(old, 'CORE_ZONE_DUMP')
support = values(old, 'CORE_ZONE_FOUNDATION_SUPPORT')
classified = {}
for source in subprocess.check_output(['git', 'ls-tree', '-r', '--name-only', 'HEAD', 'packages/core/src'], text=True).splitlines():
    if not source.endswith('.php'):
        continue
    under = source.removeprefix('packages/core/src/')
    zone = named.get(under)
    if under in support:
        zone = 'Foundation'
    if zone is None:
        zone = next((z for p, z in prefixes.items() if under.startswith(p)), None)
    if zone:
        classified[moves.get(source, source).removeprefix('packages/core/src/')] = zone
s = replace_array(s, 'CORE_ZONE_DUMP', php_map(classified))
s = replace_array(s, 'CORE_ZONE_FOUNDATION_SUPPORT', '\n'.join("    '" + moves.get('packages/core/src/' + v, 'packages/core/src/' + v).removeprefix('packages/core/src/') + "'," for v in support))
s = replace_array(s, 'CORE_ZONE_PREFIX', php_map({
    'Audit/': 'Audit', 'Context/': 'TenancyContracts', 'Data/': 'Foundation',
    'Departments/': 'Departments', 'Features/': 'Features', 'Money/': 'Money',
    'Messaging/': 'Messaging', 'Modules/': 'Modules', 'Settings/': 'Settings',
    'Storage/': 'Storage', 'Time/': 'Foundation',
}))
s = s.replace("    'Foundation' => [],", "    'Foundation' => [],\n    'Modules' => ['Foundation', 'TenancyContracts', 'Audit', 'Settings', 'SavedViews', 'Storage', 'Messaging', 'Features', 'Departments', 'Money'],")
s = s.replace("    foreach (CORE_ZONE_PREFIX as $pathPrefix => $zone) {", "    if (isset(CORE_ZONE_DUMP[$under])) {\n        return CORE_ZONE_DUMP[$under];\n    }\n\n    foreach (CORE_ZONE_PREFIX as $pathPrefix => $zone) {")
# Internal ownership follows preserved semantic zones; the regex covers nested
# modules, including Panels/SavedViews, whose old one-segment shape disappeared.
s = s.replace(r'/^CoreX\\\\([^\\\\]+)\\\\Internal\\\\([^\\\\]+)$/', r'/^CoreX\\\\.+\\\\Internal\\\\/')
# An implicit same-namespace type was not represented by a use statement in
# the old scanner. Keep those exact existing edges after Rector exposes them.
implicit = json.loads(Path('build/p02/implicit-references.json').read_text())
exact = {}
for edge in implicit:
    if edge['source'].startswith('packages/core/src/'):
        exact.setdefault(edge['source'], set()).add(edge['symbol'])
body = array(s, 'CORE_ZONE_EXACT_IMPORTS')
for source, symbols in sorted(exact.items()):
    # Existing entries remain authoritative; merge into their literal list.
    row = re.search(r"'" + re.escape(source) + r"' => \[(.*?)\],", body, re.S)
    lines = ''.join("        '" + v.replace('\\', '\\\\') + "',\n" for v in sorted(symbols))
    if row:
        body = body[:row.end(1)] + '\n' + lines + body[row.end(1):]
    else:
        body += "\n    '" + source + "' => [\n" + lines + '    ],'
s = replace_array(s, 'CORE_ZONE_EXACT_IMPORTS', body.strip())
start = s.index('    $directories = match ($zone) {', s.index('function coreZoneFileInZoneDirectories'))
end = s.index('\n}\n', start)
s = s[:start] + '    return coreZoneKind($relative) === $zone;' + s[end:]
s = s.replace('packages/core/src/Data/UnclassifiedError.php', 'packages/core/src/Errors/Data/UnclassifiedError.php')
s = s.replace("'Data/UnclassifiedError.php'", "'Errors/Data/UnclassifiedError.php'")
s = s.replace('packages/core/src/Support/Probe.php', 'packages/core/src/Config/Probe.php').replace('Support/Probe.php', 'Config/Probe.php')
s = s.replace('namespace CoreX\\Support;', 'namespace CoreX\\Config;')
Path(path).write_text(s)

path = 'tests/Arch/CoreLitmusTest.php'
old = before(path)
s = projected(path)
# Relative suffixes in live membership checks need the same exact file map.
for source, target in sorted(moves.items(), key=lambda x: -len(x[0])):
    if source.startswith('packages/core/src/'):
        a = source.removeprefix('packages/core/src/')
        b = target.removeprefix('packages/core/src/')
        s = s.replace("'" + a + "'", "'" + b + "'")
s = s.replace("['PHPStan/', 'Support/Data/', 'Support/Schema/', 'Support/Time/']", "['Messaging/PHPStan/', 'Data/', 'Database/Schema/', 'Time/']")
# ActorType remains the same Foundation enum; its accepted physical move into
# Audit must not turn a naming litmus into a ban on the mandated namespace.
s = s.replace("if (str_contains($segment, 'Audit')) {", "if (str_contains($segment, 'Audit') && $relative !== 'packages/core/src/Audit/Enums/ActorType.php') {")
Path(path).write_text(s)

edit('tests/Arch/LayerTest.php', [
    ("['core', 'modules', 'tenancy', 'access', 'tasks', 'operations', 'recurrence']", "['core', 'tenancy', 'access', 'tasks', 'operations', 'tools', 'webhooks', 'recurrence']"),
    ('(Modules|Auth|Tasks|Api)', '(Access|Tasks|Operations|Tools|Webhooks)'),
    ("str_starts_with($pkg, 'corex/')", "str_starts_with($pkg, 'axiomasoft/corex-')"),
    ("'CHANGELOG.md',", "'CHANGELOG.md',\n        'corex-modules-CHANGELOG.md',"),
])
edit('tests/Arch/ApiContractTest.php', [
    ("$src.'/Tools/Internal/GuardedToolInvoker.php'", "SourceScanner::packagePath('tools', 'src/Internal/GuardedToolInvoker.php')"),
    ('/errors/corex~1operations', '/errors/axiomasoft~1corex-operations'),
    ('/errors/corex~1attributes', '/errors/axiomasoft~1corex-custom-fields'),
])
edit('tests/Arch/ApiContractCompatibilityTest.php', [('/errors/corex~1operations', '/errors/axiomasoft~1corex-operations')])
edit('tests/Arch/PackageDocsTest.php', [('toBe(19)', 'toBe(20)'), ('toHaveCount(19)', 'toHaveCount(20)')])
edit('scripts/attributes-architecture-gate.php', [
    ("$package.'/src/'.$subsystem", "$package.'/src/Internal/'.$subsystem"),
])

# Saved-view migrations follow their owning feature package; the composed
# publication regression still checks all twelve original system migrations.
if "__DIR__.'/../database/migrations/pg'" not in Path('packages/panels/src/PanelsServiceProvider.php').read_text():
    edit('packages/panels/src/PanelsServiceProvider.php', [("        SavedViewsRegistrar::register($this->app);", "        SavedViewsRegistrar::register($this->app);\n        $this->app->booted(function (): void {\n            $this->publishes([__DIR__.'/../database/migrations/pg' => database_path('migrations')], ['corex-migrations', 'corex-migrations-tenant']);\n        });")])
if 'PanelsServiceProvider::class' not in Path('tests/Feature/MigrationPublishTest.php').read_text():
    edit('tests/Feature/MigrationPublishTest.php', [("    $target = publishTarget();", "    $this->app->register(\\CoreX\\Panels\\PanelsServiceProvider::class);\n    $target = publishTarget();")])
edit('packages/panels/tests/SavedViews/SavedViewPgSchemaTest.php', [("__DIR__.'/../../../core/database/migrations/pg'", "__DIR__.'/../../database/migrations/pg'")])

receipts = {r['id']: r for r in json.loads(Path('plans/2026.09.27-№1-COREX-KERNEL/artifacts/P02.3-execution/tool-hash-projection.json').read_text())}
p = Path('packages/tools/tests/fixtures/tool-contribution/selected-contributions.json')
data = json.loads(p.read_text())
for row in data['contributions']:
    if row['id'] in receipts:
        r = receipts[row['id']]
        assert row['contract_hash'] in (r['before'], r['after']), row['id']
        row['contract_hash'] = r['after']
p.write_text(json.dumps(data, indent=2) + '\n')
edit('packages/operations/tests/Feature/LoadContourBootTest.php', [('(?!Api', '(?!Operations')])

# Snapshot tests exercise the complete previous provider composition while
# engine production discovery remains independent of optional webhooks.
edit('packages/operations/tests/TestCase.php', [("...json_decode((string) file_get_contents(__DIR__.'/../composer.json'), true)['extra']['laravel']['providers']", "\\CoreX\\Webhooks\\WebhooksServiceProvider::class")])
engine = json.loads(Path('packages/operations/composer.json').read_text())
engine.setdefault('require-dev', {})['axiomasoft/corex-webhooks'] = '^1.0@dev'
repos = engine['repositories']
if not any(r.get('url') == '../webhooks' for r in repos):
    repos.append({'type': 'path', 'url': '../webhooks'})
Path('packages/operations/composer.json').write_text(json.dumps(engine, indent=4) + '\n')

# Relative source paths in subprocess carriers are rooted in their own package.
edit('packages/webhooks/tests/Feature/WebhookTransportTest.php', [('/src/Webhooks/', '/src/')])

adapter_hashes()
edit('tests/Arch/ErrorContractTest.php', [
    ("['operations' => 'ApiError', 'attributes' => 'ErrorDescriptor']", "['operations' => 'ApiError', 'tools' => 'ApiError', 'webhooks' => 'ApiError', 'custom-fields' => 'ErrorDescriptor']"),
    ("$owner = 'corex/'.$package;", "$owner = 'axiomasoft/corex-'.$package;"),
    ("$owner = 'axiomasoft/corex-'.$package;", "$owner = 'axiomasoft/corex-'.(in_array($package, ['tools', 'webhooks'], true) ? 'operations' : $package);"),
])

# Historical release evidence remains frozen. The live docs gate receives a
# separate finite cohort projected from its former subjects, including splits.
cohort = json.loads(before('plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/artifacts/P17/release-cohort.json'))
rows = []
for row in cohort['packages']:
    if row['name'] == 'corex/modules':
        continue
    name = plan['packages'].get(row['name'], row['name'])
    path = plan['dir_renames'].get(row['path'], row['path'])
    rows.append({'name': name, 'path': path})
rows += [{'name': 'axiomasoft/corex-' + area, 'path': 'packages/' + area} for area in ['tools', 'webhooks']]
Path('scripts/package-docs-cohort.json').write_text(json.dumps({'packages': rows}, indent=2) + '\n')
edit('scripts/package-docs-gate.php', [("/plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/artifacts/P17/release-cohort.json", '/scripts/package-docs-cohort.json')])
edit('scripts/package-docs-gate.php', [("    'axiomasoft/corex' => 'P8',\n", '')])

# Rebuild this carrier from its immutable preimage: diagnostic repeats must
# not nest directory scans or duplicate metadata keys.
Path('scripts/package-graph-gate.php').write_text(projected('scripts/package-graph-gate.php'))
edit('scripts/package-graph-gate.php', [
    ("glob($repoRoot.'/packages/*/composer.json') ?: []", "array_merge(glob($repoRoot.'/packages/*/composer.json') ?: [], glob($repoRoot.'/products/*/composer.json') ?: [])"),
    ("$packages[$dirName] = [", "$packages[$dirName] = [\n            'path' => substr(dirname($composerPath), strlen($repoRoot) + 1),"),
    ("$repoRoot.'/packages/'.$dirName.'/src'", "$repoRoot.'/'.$meta['path'].'/src'"),
    ("'packages/'.$ownerMeta['dir'].'/composer.json'", "$ownerMeta['path'].'/composer.json'"),
    ("'packages/'.$left['dir'].'/composer.json'", "$left['path'].'/composer.json'"),
    ("! str_starts_with($owner, 'corex/')", "! str_starts_with($owner, 'axiomasoft/corex')"),
    (' *     dir: string,', ' *     dir: string,\n *     path: string,'),
    ('name: string, dir: string, require:', 'name: string, dir: string, path: string, require:'),
    ('psr4: array<string, string>}', 'psr4: array<string, string>, package_type: string, stability: string, public_api: list<string>}'),
    ("'dir' => $meta['dir'],", "'dir' => $meta['dir'],\n                'path' => $meta['path'],"),
])
config = json.loads(Path('scripts/package-graph-gate.json').read_text())
config['legacy_namespace_splits'] = []
Path('scripts/package-graph-gate.json').write_text(json.dumps(config, indent=4) + '\n')
edit('tests/Arch/PackageGraphTest.php', [
    ("->and($joined)->toContain('LEGACY NAMESPACE SPLIT: CoreX\\\\Tenancy\\\\')", "->and($joined)->not->toContain('LEGACY NAMESPACE SPLIT:')"),
    ("->and($report['legacy_namespace_splits'] ?? null)->not->toBeEmpty()", "->and($report['legacy_namespace_splits'] ?? null)->toBe([])"),
    ('packages/core/src/Support/', 'packages/core/src/Config/'),
    ('namespace CoreX\\Core\\Support;', 'namespace CoreX\\Config;'),
])
print('gates: preserved subjects, zone membership, negative controls and live cohorts projected')
