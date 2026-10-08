#!/usr/bin/env python3
"""Run local seam mutations, always preserving pre-existing source/map bytes."""
import json
import pathlib
import subprocess

ROOT = pathlib.Path(__file__).resolve().parents[4]
OUT = pathlib.Path(__file__).resolve().parent
PLAN = ROOT / 'plans/2026.09.27-№1-COREX-KERNEL'
MAP = PLAN / 'artifacts/P00-design/scripts/check-maps.py'
EDGES = PLAN / 'artifacts/P00/dependency-edges.tsv'
RESULTS = []

def run(name, argv, expected_exit=0, text=None):
    result = subprocess.run(argv, cwd=ROOT, capture_output=True, text=True)
    output = result.stdout + result.stderr
    (OUT / (name+'.log')).write_text(output)
    ok = (result.returncode == 0 if expected_exit == 0 else result.returncode != 0) and (text is None or text in output)
    RESULTS.append({'name': name, 'command': argv, 'exit': result.returncode, 'expected': expected_exit, 'matched': text, 'ok': ok})
    print(name, result.returncode, 'OK' if ok else 'FAIL', flush=True)
    return ok

def mutate(name, changes, negative, positive, needle):
    paths = {ROOT/path: (ROOT/path).read_bytes() for path in changes}
    paths[EDGES] = EDGES.read_bytes()
    try:
        for path, change in changes.items():
            target = ROOT/path
            before = target.read_text()
            after = change(before)
            if after == before:
                raise RuntimeError('mutation did not change '+path)
            target.write_text(after)
        if name.startswith('d-'):
            run(name+'-edges', ['python3', str(MAP), '--write-edges'])
        run(name+'-red', negative, 1, needle)
    finally:
        for path, original in paths.items():
            path.write_bytes(original)
    run(name+'-restored', positive)

money = ['scripts/php84', 'composer', '--working-dir=packages/crm', 'test', '--', 'tests/Feature/RegistryFieldCastsTest.php']
tool = ['scripts/php84', 'vendor/bin/pest', 'packages/operations/tests/Unit/ToolDeclarationTest.php']
config = ['scripts/php84', 'vendor/bin/pest', 'tests/Feature/ConfigReportCompositionTest.php']
deps = ['python3', str(MAP), '--dependencies']
graph = ['scripts/php84', 'php', 'scripts/package-graph-gate.php']
mutate('a-money-registration', {'packages/intl/src/IntlServiceProvider.php': lambda s: s.replace("$casts->register(MoneyCast::class, 'money');", '// registration removed for negative check')}, money, money, 'money')
mutate('b-tool-section-tag', {'packages/operations/src/Tools/ToolServiceProvider.php': lambda s: s.replace('$this->app->tag([ToolSectionCompiler::class], ManifestSectionCompiler::class);', '// tag removed for negative check')}, tool, tool, 'No compiler for API declaration mode: tool')
mutate('c-section-order', {'packages/intl/src/IntlConfigSection.php': lambda s: s.replace('return 40;', 'return 50;'), 'packages/search/src/Support/SearchConfigSection.php': lambda s: s.replace('return 50;', 'return 40;')}, config, config, 'ConfigReportCompositionTest')
mutate('d-foreign-audit-literal', {'packages/tenancy/src/Impersonation/ImpersonatedModelWriteGuard.php': lambda s: s.replace('ImpersonationGrant::class,', "ImpersonationGrant::class,\n        'CoreX\\\\Audit\\\\Internal\\\\AuditLog',")}, deps, deps, 'internal-edge: packages/tenancy/src/Impersonation/ImpersonatedModelWriteGuard.php -> CoreX\\Audit\\Internal\\AuditLog')
mutate('e-expiring-internal-edge', {'scripts/package-graph-gate.json': lambda s: s.replace('packages/core/src/SavedViews/Internal/DenyViewAccessPolicy.php', 'packages/core/src/SavedViews/Internal/ExpiredDenyViewAccessPolicy.php')}, graph, graph, 'R2')
mutate('f-foreign-internal-still-denied', {'packages/panels/src/Internal/SavedViewsRegistrar.php': lambda s: s.replace('use CoreX\\Support\\CoreOptions;', 'use CoreX\\Support\\CoreOptions;\nuse CoreX\\Messaging\\Internal\\SqlOutbox;')}, graph, graph, 'R2')
(OUT/'negatives.json').write_text(json.dumps(RESULTS, ensure_ascii=False, indent=2)+'\n')
raise SystemExit(any(not row['ok'] for row in RESULTS))
