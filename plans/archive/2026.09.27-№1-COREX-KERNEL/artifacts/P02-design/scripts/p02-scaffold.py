#!/usr/bin/env python3
"""Complete P02.3 scaffolding and mechanical tooling projection after apply.

Run in a transferred checkout; input is build/p02/plan.json and the preimage
manifests retained by Git. No frozen Golden data or identifier tokens are edited.
"""
import copy, json, os, re, subprocess
from pathlib import Path

ROOT = Path.cwd()
PLAN = 'plans/2026.09.27-№1-COREX-KERNEL'
transfer = json.loads(Path('build/p02/plan.json').read_text())

def read(path):
    return json.loads(Path(path).read_text())

def write(path, data):
    Path(path).parent.mkdir(parents=True, exist_ok=True)
    Path(path).write_text(json.dumps(data, indent=4, ensure_ascii=False) + '\n')

def before(path):
    return subprocess.check_output(['git', 'show', f'HEAD:{path}'], text=True)

def text_edit(path, callback):
    p = Path(path)
    p.write_text(callback(p.read_text()))

base_engine = read('packages/operations/composer.json')
for area, provider in [('tools', 'ToolServiceProvider'), ('webhooks', 'WebhooksServiceProvider')]:
    root = 'CoreX\\' + area.title() + '\\'
    manifest = copy.deepcopy(base_engine)
    manifest['name'] = 'axiomasoft/corex-' + area
    manifest['description'] = ('Guarded tool runtime for CoreX operations: schemas, contract hash and MCP projection.' if area == 'tools'
                               else 'Webhook subscriptions, signing, delivery and replay for CoreX operations.')
    manifest['autoload'] = {'psr-4': {root: 'src/'}}
    manifest['autoload-dev'] = {'psr-4': {root + 'Tests\\': 'tests/'}}
    manifest['extra'] = {'corex': {'schema-version': 1, 'package-type': 'capability', 'stability': 'stable',
                                 'public-api': [root + 'Contracts', root + 'Data']},
                         'branch-alias': {'dev-main': '1.0.x-dev'}, 'laravel': {'providers': [root + provider]}}
    manifest['require']['axiomasoft/corex-operations'] = '^1.0@dev'
    manifest['scripts']['analyse'] = f'cd ../.. && vendor/bin/phpstan analyse -c packages/{area}/phpstan.neon --memory-limit=2G --no-progress'
    write(f'packages/{area}/composer.json', manifest)
    for path in ['phpunit.xml', 'phpstan.neon', 'composer-dependency-analyser.php']:
        src = Path('packages/operations', path).read_text()
        src = src.replace('packages/operations', 'packages/' + area).replace('build/phpstan/operations', 'build/phpstan/' + area)
        src = src.replace('<testsuite name="API">', f'<testsuite name="{area.title()}">')
        if path == 'phpstan.neon' and not Path(f'packages/{area}/database/migrations').is_dir():
            src = src.replace('        - database/migrations\n', '')
        if path == 'composer-dependency-analyser.php' and not Path(f'packages/{area}/database').is_dir():
            src = src.replace("    ->addPathToScan(__DIR__.'/database', isDev: true)\n", '')
        Path('packages', area, path).write_text(src)
    # Root's shared bootstrap for local validation; Composer scripts still use
    # each package's own installation through Testbench's ancestor discovery.

    src = Path('packages/operations/tests/TestCase.php').read_text().replace('namespace CoreX\\Operations\\Tests;', f'namespace {root}Tests;')
    src = src.replace('use CoreX\\Operations\\ApiServiceProvider;', 'use CoreX\\Operations\\ApiServiceProvider;\nuse ' + root + provider + ';')
    src = re.sub(r'return \[.*?;', lambda _: 'return [\\CoreX\\Operations\\ApiServiceProvider::class, \\' + root + provider + '::class];', src)
    Path(f'packages/{area}/tests/TestCase.php').write_text(src)
    for f in Path('packages', area, 'tests').rglob('*.php'):
        if f.name != 'TestCase.php':
            text_edit(f, lambda s: s.replace('CoreX\\Operations\\Tests\\TestCase', root + 'Tests\\TestCase'))
    Path(f'packages/{area}/README.md').write_text(f'# CoreX {area.title()}\n\n{manifest["description"]}\n\n```bash\ncomposer require axiomasoft/corex-{area}\n```\n\nRequires PHP 8.4 and Laravel 12 or 13. Uses CoreX Operations through its public contracts.\n')
    if area == 'tools':
        docs = '\n## Configuration\n\nRegister `CoreX\\Tools\\ToolServiceProvider` alongside `CoreX\\Operations\\ApiServiceProvider`. Tool admission uses the host bindings for trusted context, authorization and guarded execution. Install `opis/json-schema` when offline JSON Schema validation is required.\n\n## Public contracts\n\nDeclare tools through the existing module `Api::tool()` extender and compile the module registry. Exposed tools invoke the declared operation; headless tools require an explicit guarded handler. See the [operation contract](../operations/README.md).\n\n## Failure handling\n\nUnknown tools and rejected authorization fail before handler execution. Missing schema capability returns `schema_unavailable`; schema workers enforce memory and deadline bounds. Correct the capability or declaration before retrying.\n'
    else:
        docs = '\n## Configuration\n\nRegister `CoreX\\Webhooks\\WebhooksServiceProvider` alongside `CoreX\\Operations\\ApiServiceProvider`. Publish `corex-webhooks-config` and `corex-webhooks-migrations-tenant` for the tenant connection. Remote delivery requires both `delivery_enabled` and `worker_ready`, plus a host-owned key ring; both flags default to false.\n\n## Public contracts\n\nConfigure explicit topics and payload projectors in `corex-webhooks`. Bind the package contracts for signing keys and guarded delivery in the host. Subscriptions and replay use the trusted operation context; see the [operation contract](../operations/README.md).\n\n## Failure handling\n\nUnavailable transport or signing capability refuses delivery. URL policy, subscription scope, replay horizon and lease checks remain mandatory. Failed attempts retain their recorded outcome; resolve the cause before replaying through the supported API.\n'
    with Path(f'packages/{area}/README.md').open('a') as f:
        f.write(docs)
    Path(f'packages/{area}/CHANGELOG.md').write_text('## Unreleased\n\n- Extracted from CoreX Operations with an independent PSR-4 root, service provider and test suite.\n')

# Project existing public surface prefixes through the class map. Ambiguous
# prefixes expand to the precise resulting classes; no new API is invented.
class_map = transfer['all_classes']
package_map = transfer['packages']
original_dirs = {d.name: d for d in Path('packages').iterdir() if d.is_dir()}
for manifest_path in [*Path('packages').glob('*/composer.json'), *Path('products').glob('*/composer.json')]:
    data = read(manifest_path)
    roots = list(data['autoload']['psr-4'])
    data['autoload'].pop('classmap', None)
    if data['name'] == 'axiomasoft/corex-operations':
        data['extra']['laravel']['providers'] = []
    api = data.get('extra', {}).get('corex', {}).get('public-api', [])
    projected = []
    for prefix in api:
        if any(old == prefix or old.startswith(prefix + '\\') for old in class_map):
            projected.extend(new if old == prefix else new.rsplit('\\', 1)[0] for old, new in class_map.items() if old == prefix or old.startswith(prefix + '\\'))
        else:
            projected.append(prefix)
    data.setdefault('extra', {}).setdefault('corex', {})['public-api'] = sorted(set(p for p in projected if any(p.startswith(r) or p == r.rstrip('\\') for r in roots)))
    if data['name'] == 'axiomasoft/corex':
        old_modules = json.loads(before('packages/modules/composer.json'))
        for prefix in old_modules['extra']['corex']['public-api']:
            projected = [new if old == prefix else new.rsplit('\\', 1)[0] for old, new in class_map.items() if old == prefix or old.startswith(prefix + '\\')]
            data['extra']['corex']['public-api'] += projected or [prefix]
        data['extra']['corex']['public-api'] = sorted(set(data['extra']['corex']['public-api']))
        data['scripts']['test'] = data['scripts']['test'].replace('--testsuite=CorePackage', '--testsuite=CorePackage,ModulesPackage')
        data['scripts']['test:pg'] = data['scripts']['test:pg'].replace('--testsuite=CorePackage', '--testsuite=CorePackage,ModulesPackage')
        data['scripts']['test:coverage'] = data['scripts']['test:coverage'].replace('--testsuite=CorePackage', '--testsuite=CorePackage,ModulesPackage')
    write(manifest_path, data)

# All installing roots must declare their monorepo closure at ^1.0@dev and
# supply relative path repositories. Optional integrations remain dev-only.
manifests = {p: read(p) for p in [*Path('packages').glob('*/composer.json'), *Path('products').glob('*/composer.json')]}
by_name = {d['name']: p for p, d in manifests.items()}
for path, data in manifests.items():
    data.get('require', {}).pop(data['name'], None)
    data.get('require-dev', {}).pop(data['name'], None)
    all_deps = set(data.get('require', {})) | set(data.get('require-dev', {}))
    queue = list(all_deps & by_name.keys())
    seen = set()
    while queue:
        name = queue.pop()
        if name in seen or name == data['name']:
            continue
        seen.add(name)
        queue.extend(set(manifests[by_name[name]].get('require', {})) & by_name.keys())
    if 'axiomasoft/corex-tenancy' in seen or data['name'] == 'axiomasoft/corex-tenancy':
        data.setdefault('require-dev', {}).update({'axiomasoft/stancl-tenancy': '>=4.0.0-alpha1 <=4.0.0-alpha1', 'axiomasoft/stancl-jobpipeline': '>=2.0.0-alpha1 <=2.0.0-alpha1'})
    for name in sorted(seen - all_deps):
        data.setdefault('require-dev', {})[name] = '^1.0@dev'
    repos = data.get('repositories', [])
    repos = list(repos.values()) if isinstance(repos, dict) else repos
    repos = [r for r in repos if r.get('type') != 'path']
    repos += [{'type': 'path', 'url': os.path.relpath(by_name[n].parent, path.parent)} for n in sorted(seen)]
    data['repositories'] = repos
    for name in data.get('require', {}):
        data.get('require-dev', {}).pop(name, None)
    write(path, data)

root = read('composer.json')
repos = root['repositories']
repos = list(repos.values()) if isinstance(repos, dict) else repos
if not any(r.get('url') == 'products/*' for r in repos):
    repos.insert(1, {'type': 'path', 'url': 'products/*'})
root['repositories'] = repos
# Moved package tests remain part of the original root lanes.
xml = Path('phpunit.xml').read_text()
xml = xml.replace('            <directory>packages/core/tests</directory>', '\n'.join('            <directory>packages/core/tests/' + area + '</directory>' for area in ['Unit', 'Storage', 'Views', 'Feature', 'Pg', 'Support']))
xml = xml.replace('        <testsuite name="ApiPackage">', '        <testsuite name="ToolsPackage">\n            <directory>packages/tools/tests</directory>\n        </testsuite>\n        <testsuite name="WebhooksPackage">\n            <directory>packages/webhooks/tests</directory>\n        </testsuite>\n        <testsuite name="ApiPackage">')
xml = xml.replace('        <testsuite name="CorePackage">', '        <testsuite name="SavedViewsPackage">\n            <directory>packages/panels/tests/SavedViews</directory>\n        </testsuite>\n        <testsuite name="CorePackage">')
Path('phpunit.xml').write_text(xml)
for area in ['tools', 'webhooks']:
    root['require-dev']['axiomasoft/corex-' + area] = '^1.0@dev'
root['scripts']['test:packages'] = 'for p in ' + ' '.join(str(p.parent) for p in sorted(manifests)) + '; do echo "==> $p" && composer --working-dir="$p" test || exit 1; done'
write('composer.json', root)

# Merge dependency-analyser configuration by method chains rather than two
# competing return statements; classify relocated PHPStan rules as dev tools.
core = Path('packages/core/composer-dependency-analyser.php')
module = before('packages/modules/composer-dependency-analyser.php')
module_chain = module.split("    // discovery: standalone", 1)[1]
core.write_text(core.read_text().rstrip().rstrip(';') + "\n    ->addPathToScan(__DIR__.'/src/Modules/PHPStan', isDev: true)\n    // discovery: standalone" + module_chain)
text_edit(core, lambda s: s.replace("__DIR__.'/src/PHPStan'", "__DIR__.'/src/Modules/PHPStan'"))

text_edit('scripts/manifest-check.py', lambda s: s.replace("sorted(ROOT.glob('packages/*/composer.json'))", "sorted([*ROOT.glob('packages/*/composer.json'), *ROOT.glob('products/*/composer.json')])"))
text_edit('scripts/phpstan-packages.sh', lambda s: s.replace('packages/*/phpstan.neon phpstan.neon', 'packages/*/phpstan.neon products/*/phpstan.neon phpstan.neon'))
text_edit('scripts/phpstan-packages.sh', lambda s: s.replace('status=0\n', 'if [[ "${1:-}" == --generate-baseline ]]; then\n    task_config="${2:?supply the package directory}/phpstan.neon"\n    [[ -f "$task_config" ]] || exit 2\n    task_baseline="$(dirname "$task_config")/phpstan-baseline.neon"\n    [[ -f "$task_baseline" ]] || printf "parameters:\\n    ignoreErrors: []\\n" > "$task_baseline"\n    vendor/bin/phpstan analyse -c "$task_config" --memory-limit=2G --no-progress --generate-baseline="$task_baseline" --allow-empty-baseline\n    exit $?\nfi\n\nstatus=0\n', 1))
text_edit('scripts/dependency-analyser.sh', lambda s: s.replace('local dir="$1"', 'local dir="$1"').replace('packages/$dir', '$dir').replace('for manifest in packages/*/composer.json;', 'for manifest in packages/*/composer.json products/*/composer.json;').replace('dir="$(basename "$(dirname "$manifest")")"', 'dir="$(dirname "$manifest")"').replace('"$dir" == tenancy || "$dir" == access', '"$dir" == packages/tenancy || "$dir" == packages/access').replace('run "${1:?usage: dependency-analyser.sh <package-dir>|--all}"', 'dir="${1:?usage: dependency-analyser.sh <package-dir>|--all}"\n[[ "$dir" == */* ]] || dir="packages/$dir"\nrun "$dir"'))
text_edit('scripts/quality-ceiling-guard.php', lambda s: s.replace("glob($root.'/packages/*/composer.json') ?: []", "array_merge(glob($root.'/packages/*/composer.json') ?: [], glob($root.'/products/*/composer.json') ?: [])"))
text_edit('scripts/ownership-gate.php', lambda s: s.replace("$map['packages'][$record['package']]['target']", "$record['package']"))

ownership = json.loads(before('scripts/ownership-map.json'))
packages = {}
for old, rule in ownership['packages'].items():
    name = package_map.get(old, old)
    if name == 'flex/crm' or name == 'axiomasoft/corex' and rule['dir'] == 'modules':
        continue
    rule = {k: v for k, v in rule.items() if k != 'target'}
    rule['dir'] = by_name[name].parent.name
    packages[name] = rule
for area in ['tools', 'webhooks']:
    packages['axiomasoft/corex-' + area] = {'dir': area, 'root': 'CoreX\\' + area.title(), 'config': 'corex-' + area,
                                         'tag_prefix': 'corex-' + area + '-', 'env_prefix': 'COREX_' + area.upper() + '_', 'command_prefix': 'corex:' + area + ':'}
ownership['packages'] = packages
ownership['kernel']['packages'] = ['axiomasoft/corex']
# Sources were projected by the literal pass; transfer ownership follows their
# resulting package directory, avoiding global identifier exemptions.
for declaration in ownership.get('planned_identifier_sources', []):
    moves = dict(transfer['moves'])
    declaration['source_files'] = [moves.get(f, f) for f in declaration['source_files']]
    declaration['id'] = class_map.get(declaration['id'], transfer['namespace_rules'].get(declaration['id'], declaration['id']))
    first = declaration['source_files'][0]
    owner = next((d['name'] for p, d in manifests.items() if first.startswith(str(p.parent) + '/')), None)
    if owner:
        declaration['package'] = owner
# The saved-view migration directory moved, while its two global publish tags
# remain unchanged until the separately scoped identifier pass. Bind both
# original tags to exactly the two provider declarations composing that group.
ownership['projected_shared_tags'] = {}
for tag in ['corex-migrations', 'corex-migrations-tenant']:
    ownership['projected_shared_tags'][tag] = ['axiomasoft/corex', 'axiomasoft/corex-panels']
    ownership['planned_identifier_sources'].append({
        'package': 'axiomasoft/corex-panels', 'kind': 'tag', 'id': tag,
        'source_files': ['packages/panels/src/PanelsServiceProvider.php'],
        'decision': 'PLAN3.D52'})
write('scripts/ownership-map.json', ownership)
text_edit('scripts/ownership-gate.php', lambda s: s.replace(
    '                    preg_match_all($pattern, $source, $matches);',
    r'''                    preg_match_all($pattern, $source, $matches);
                    if ($kind === 'tag') {
                        preg_match_all('/->publishes(?:Migrations)?\(\s*\[.*?\]\s*,\s*\[([^\]]*)\]/s', $source, $tagArrays);
                        foreach ($tagArrays[1] as $tags) {
                            preg_match_all('~[\'"]([^\'"]+)[\'"]~', $tags, $tagNames);
                            $matches[1] = array_merge($matches[1], $tagNames[1]);
                        }
                    }''').replace(
    '    $inventory = [];',
    '    /** @var array<string, array<string, array<string, list<string>>>> $sourceInventory */\n    $sourceInventory = [];\n    $inventory = [];').replace(
    "                $declaredSources = $identifierSources[$kind][$id] ?? [];",
    "                $declaredSources = $identifierSources[$kind][$id] ?? [];\n                $sourceInventory[$kind][$id][$package] = $declaredSources;").replace(
    '            if (count(array_unique($packages)) > 1) {',
    """            $shared = $kind === 'tag' && ! in_array('--strict', $argv, true)
                ? ($map['projected_shared_tags'][$id] ?? []) : [];
            $sourcesMatch = $shared !== [] && array_diff(array_keys($packages), $shared) === [];
            foreach (array_keys($packages) as $owner) {
                $expectedSources = $owner === 'axiomasoft/corex'
                    ? ['packages/core/src/CoreServiceProvider.php']
                    : ($plannedSources[$owner][$kind][$id] ?? []);
                if (($sourceInventory[$kind][$id][$owner] ?? []) !== $expectedSources) {
                    $sourcesMatch = false;
                }
            }
            if (count(array_unique($packages)) > 1 && ! $sourcesMatch) {"""))

successors = {'schema': 'corex-ratchet-successors/1', 'groups': [
    {'before': ['core', 'modules'], 'after': ['core']},
    {'before': ['operations'], 'after': ['operations', 'tools', 'webhooks']},
    {'before': ['attributes'], 'after': ['custom-fields']},
    {'before': ['crm'], 'after': ['flex-crm']}], 'packages': package_map}
write('scripts/ratchet-successors.json', successors)
# Baseline entries are filtered only after exact composer-name projection;
# --write-baseline and the anti-weakening gate prove that the remainder shrinks.
baseline = read('scripts/ownership-gate-baseline.json')
projected = []
for value in baseline['violations']:
    for old in sorted(package_map, key=len, reverse=True):
        value = re.sub(r'(?<![\w/-])' + re.escape(old) + r'(?![\w/-])', package_map[old], value)
    projected.append(value)
baseline['violations'] = sorted(set(projected))
write('scripts/ownership-gate-baseline.json', baseline)


# Compare debt by the finite split/merge groups and exact package-name projection.
anti = Path('scripts/anti-weakening-gate.sh').read_text()
anti = anti.replace("    if kind == 'ownership':\n        return not (set(new['violations']) - set(old['violations']))", "    succ_path = pathlib.Path('scripts/ratchet-successors.json')\n    succ = json.loads(succ_path.read_text()) if succ_path.exists() else {'groups': [], 'packages': {}}\n    if kind == 'ownership':\n        def project(value):\n            for name in sorted(succ['packages'], key=len, reverse=True):\n                value = re.sub(r'(?<![\\w/-])' + re.escape(name) + r'(?![\\w/-])', succ['packages'][name], value)\n            return value\n        return not (set(new['violations']) - {project(v) for v in old['violations']})")
anti = anti.replace('import json, pathlib, subprocess, sys', 'import json, pathlib, subprocess, sys, re')
anti = anti.replace("        if any(value > before.get(package, 0) for package, value in after.items()):", "        grouped = set()\n        for group in succ['groups']:\n            if not all(k in before for k in group['before']) or any(k in before for k in group['after'] if k not in group['before']):\n                continue\n            if sum(after.get(k, 0) for k in group['after']) > sum(before.get(k, 0) for k in group['before']):\n                return False\n            grouped.update(group['after'])\n        if any(value > before.get(package, 0) for package, value in after.items() if package not in grouped):")
anti = anti.replace('"$worktree"/packages/*/tests;', '"$worktree"/packages/*/tests "$worktree"/products/*/tests;')
Path('scripts/anti-weakening-gate.sh').write_text(anti)

text_edit('.github/dependabot.yml', lambda s: s.replace("['/', '/packages/*']", "['/', '/packages/*', '/products/*']"))
workflow = Path('.github/workflows/ci.yml').read_text()
start = workflow.index('  api-tests:')
end = workflow.index('  notifications-wakeup-tests:', start)
engine_job = workflow[start:end].replace('api-tests:', 'operations-tests:').replace('API package', 'Operations package').replace('API tests', 'Operations tests').replace('API static', 'Operations static')
jobs = engine_job
for area in ['tools', 'webhooks']:
    jobs += engine_job.replace('operations-tests:', area + '-tests:').replace('Operations', area.title()).replace('packages/operations', 'packages/' + area)
workflow = workflow[:start] + jobs + workflow[end:]
workflow = workflow.replace('vendor/bin/pest packages/core/tests packages/modules/tests', 'vendor/bin/pest packages/core/tests')
workflow = workflow.replace('composer.json packages/*/composer.json;', 'composer.json packages/*/composer.json products/*/composer.json;')
workflow = workflow.replace('for manifest in packages/*/composer.json;', 'for manifest in packages/*/composer.json products/*/composer.json;')
Path('.github/workflows/ci.yml').write_text(workflow)
print('scaffold: tools/webhooks, installing roots, tooling, ownership and CI projected')

for file in ['packages/core/README.md', 'packages/core/PACKAGE.md']:
    with Path(file).open('a') as f:
        f.write('\nThe merged module subsystem is documented in [modules/README](docs/modules/README.md) and [modules/PACKAGE](docs/modules/PACKAGE.md). Its original changelog is retained in [history](docs/history/corex-modules-CHANGELOG.md).\n')

# Golden keeps its frozen baseline; a sidecar binds the thirteen unchanged
# test fixtures promoted into shipped Testing by the accepted P00 map.
text_edit('tests/Golden/GoldenTestCase.php', lambda s: s.replace("../../packages/operations/composer.json", "../../packages/webhooks/composer.json"))
text_edit('tests/Golden/Support/GoldenSnapshot.php', lambda s: s.replace("glob($repoRoot.'/packages/*/src') ?: []", "array_merge(glob($repoRoot.'/packages/*/src') ?: [], glob($repoRoot.'/products/*/src') ?: [])"))
text_edit('tests/Golden/GoldenTest.php', lambda s: s.replace("    $expected = Normalizer::normalize($map->apply($baseline));", "    if ($layer === 'php-surface') {\n        $baseline += json_decode((string) file_get_contents(__DIR__.'/promoted-fixtures.json'), true, flags: JSON_THROW_ON_ERROR);\n    }\n    $expected = Normalizer::normalize($map->apply($baseline));"))

text_edit('scripts/attributes-architecture-gate.php', lambda s: s.replace("$namespacePath = $subsystem === 'Internal' ? $relative : 'Internal/'.$relative;", "$namespacePath = $relative;"))

text_edit('tests/Pest.php', lambda s: s.replace("in('../packages/core/tests');", "in('../packages/core/tests/Unit', '../packages/core/tests/Storage', '../packages/core/tests/Views', '../packages/core/tests/Feature', '../packages/core/tests/Pg');"))
for name in ['CoreXTestCase', 'IsolatesDatabasePath']:
    src = Path('packages/core/tests/Support', name + '.php').read_text()
    src = src.replace('namespace CoreX\\Testing;', 'namespace CoreX\\Panels\\Tests;')
    if name == 'CoreXTestCase':
        src = src.replace('abstract class CoreXTestCase', 'abstract class SavedViewsTestCase')
    Path('packages/panels/tests', ('SavedViewsTestCase' if name == 'CoreXTestCase' else name) + '.php').write_text(src)
text_edit('packages/panels/tests/Pest.php', lambda s: s + "\nuses(\\CoreX\\Panels\\Tests\\SavedViewsTestCase::class)->in('SavedViews');\n")

text_edit('tests/Golden/Support/RenameMap.php', lambda s: s.replace("        return new self($classes, $packages, $data['manual']);", "\n        $projectionPath = dirname($path).'/package-projection.json';\n        if (is_file($projectionPath)) {\n            $projection = json_decode((string) file_get_contents($projectionPath), true, flags: JSON_THROW_ON_ERROR);\n            foreach ($projection as $old => $target) {\n                if (! in_array($target, $data['ambiguous_packages'][$old] ?? [], true)) {\n                    throw new \\RuntimeException('Invalid primary package projection.');\n                }\n                $packages[$old] = $target;\n            }\n        }\n\n" + "        return new self($classes, $packages, $data['manual']);"))

text_edit('tests/Pest.php', lambda s: s + "\nuses(\\CoreX\\Panels\\Tests\\SavedViewsTestCase::class)->in('../packages/panels/tests/SavedViews');\n")
