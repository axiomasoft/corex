#!/usr/bin/env python3
"""P03 feature-module transfer by `artifacts/P03/module-map.tsv` (PLAN3.D57; stdlib; run from the repository root).

Reuses the P02.3 passes of `artifacts/P02-design/scripts/p02-transfer.py` with a plan built from the module map:
no package or package-directory renames; local manifest FQCN metadata is rewritten; only class moves inside each package's single PSR-4 root.

  plan     [--out build/p03]   build the plan; exit 1 on a stale map, collision, chain, missing source or occupied target
  apply    [--out build/p03] [--no-rector] [--source-tree SHA]
                               execute on a CLEAN worktree (dry run: a scratch `git worktree`)
  lock     [--out build/p03]   refresh local package metadata with Composer; reject dependency drift
  verify   [--out build/p03]   old paths gone, targets present, namespace = path, no old FQCN left, Golden rules present
  manifest                     extend scripts/rename-completeness.json with the P03 classes and vanished namespaces
  golden                       add Golden `manual` identifier rules (baseline name -> P03 name) to tests/Golden/rename-map.json
  golden-check                 unchanged preimage fields + exactly the required manual additions
  adapters                     after Pint: verify normalized AST equivalence, then refresh dynamic-adapter hashes

Order inside `apply` (as P02.3, dossier research/P03-design.md §4): relative __DIR__ paths → literal pass → Rector
RenameClassRector (packages, products, tests, scripts, fixtures) → qualified-name pass → namespace declarations → git mv.
The P02 helpers keep their scratch files under build/p02/ (created here).
"""
import argparse, collections, copy, csv, importlib.util, json, os, re, subprocess, sys
from pathlib import Path

PLAN = 'plans/2026.09.27-№1-COREX-KERNEL'
HERE = os.path.dirname(os.path.abspath(__file__))
MAP = f'{PLAN}/artifacts/P03/module-map.tsv'
P02 = f'{PLAN}/artifacts/P02-design/scripts'
spec = importlib.util.spec_from_file_location('p02', f'{P02}/p02-transfer.py')
t = importlib.util.module_from_spec(spec)
spec.loader.exec_module(t)
GOLDEN_MAP = 'tests/Golden/rename-map.json'
COMPLETENESS = 'scripts/rename-completeness.json'


def rows():
    return list(csv.DictReader(open(MAP, encoding='utf-8'), delimiter='\t'))


def build_plan():
    problems = []
    if subprocess.run([sys.executable, f'{HERE}/p03-module-map.py', '--check'], capture_output=True).returncode:
        problems.append('module map is stale: rerun cloud/scan.py and p03-module-map.py')
    tracked = set(t.git('ls-files').splitlines())
    moves, classes, namespaces = [], {}, {}
    for r in rows():
        if r['path'] == r['target_path']:
            continue
        moves.append((r['path'], r['target_path']))
        if r['path'] not in tracked:
            problems.append(f"missing source {r['path']}")
        if os.path.exists(r['target_path']):
            problems.append(f"occupied target {r['target_path']}")
        if r['namespace'] != r['target_namespace']:
            namespaces[r['path']] = {r['namespace']: r['target_namespace']}
        if r['symbol']:
            classes[f"{r['namespace']}\\{r['symbol']}"] = f"{r['target_namespace']}\\{r['symbol']}"
    for old, new in classes.items():
        if new in classes:
            problems.append(f'chain {old} -> {new} -> {classes[new]}')
    for new, n in collections.Counter(b for _a, b in moves).items():
        if n > 1:
            problems.append(f'collision {new}')
    # A namespace token (docs, phpunit, neon) is rewritten only when every class of that namespace lands in one namespace.
    spread = collections.defaultdict(set)
    for r in rows():
        spread[r['namespace']].add(r['target_namespace'])
    ns_rules = {old: next(iter(new)) for old, new in spread.items() if len(new) == 1 and old not in new}
    plan = {'classes': dict(sorted(classes.items())),
            'all_classes': {f"{r['namespace']}\\{r['symbol']}": f"{r['target_namespace']}\\{r['symbol']}"
                            for r in rows() if r['symbol']},
            'class_sources': {f"{r['namespace']}\\{r['symbol']}": r['path'] for r in rows() if r['symbol']},
            'namespace_rules': dict(sorted(ns_rules.items())),
            'moves': sorted(moves), 'namespaces': dict(sorted(namespaces.items())), 'packages': {},
            'dir_renames': {}, 'extra_moves': [], 'tombstones': [],
            'vanished_namespaces': sorted(old for old, new in spread.items()
                                          if old not in {r['target_namespace'] for r in rows()}
                                          and not any(n.startswith(old + '\\') for r in rows()
                                                      for n in [r['target_namespace']]))}
    return plan, problems


def cmd_plan(out):
    plan, problems = build_plan()
    os.makedirs(out, exist_ok=True)
    json.dump(plan, open(f'{out}/plan.json', 'w'), indent=1, ensure_ascii=False)
    for p in problems:
        print(p)
    print(f"plan: {len(plan['classes'])} classes, {len(plan['moves'])} php moves, {len(plan['namespaces'])} namespace "
          f"rewrites, {len(plan['namespace_rules'])} namespace tokens, {len(plan['vanished_namespaces'])} vanished "
          f"namespaces, {len(problems)} problems")
    return 1 if problems else 0


def rector(plan, no_rector):
    """p02 `rector` with products/ in scope (flex-crm consumes moved package classes)."""
    cfg = 'build/p02/rector.php'
    pairs = ''.join(f"        {json.dumps(o)} => {json.dumps(n)},\n" for o, n in plan['classes'].items())
    files = [f for f in t.git('ls-files').splitlines()
             if f.endswith('.php') and f.startswith(('packages/', 'products/', 'tests/', 'scripts/', 'fixtures/'))
             and not t.LITERAL_SKIP.search(f) and os.path.isfile(f)]
    source_paths = ',\n'.join("        __DIR__.'/../../" + f.replace("'", "\\'") + "'" for f in files)
    open(cfg, 'w').write(
        "<?php\n\ndeclare(strict_types=1);\n\nuse Rector\\Config\\RectorConfig;\n"
        "use Rector\\Renaming\\Rector\\Name\\RenameClassRector;\n\n"
        "return RectorConfig::configure()\n"
        "    ->withoutParallel()\n"
        "    ->withImportNames(importShortClasses: false)\n"
        "    ->withPaths([\n" + source_paths + "\n    ])\n"
        "    ->withSkip([__DIR__.'/../../tests/Golden/baseline', '*/vendor/*'])\n"
        "    ->withConfiguredRule(RenameClassRector::class, [\n" + pairs + "    ]);\n")
    if no_rector:
        print(f'rector: skipped (config {cfg})')
        return
    res = subprocess.run(['scripts/php84', 'vendor/bin/rector', 'process', '--config', cfg, '--no-progress-bar',
                          '--no-diffs'], capture_output=True, text=True)
    if res.returncode:
        t.die('rector failed: ' + (res.stdout + res.stderr)[-2000:])
    print('rector: done')


def cmd_apply(out, no_rector, source_tree):
    if source_tree:
        expected = t.git('rev-parse', source_tree + '^{tree}').strip()
        actual = t.git('rev-parse', 'HEAD^{tree}').strip()
        if actual != expected or t.git('status', '--porcelain').strip():
            t.die('worktree differs from the validated source tree')
    elif t.git('status', '--porcelain').strip():
        t.die('worktree not clean')
    plan, problems = build_plan()
    if problems:
        t.die(f'plan has {len(problems)} problems; run `plan`')
    os.makedirs(out, exist_ok=True)
    os.makedirs('build/p02', exist_ok=True)
    json.dump(plan, open(f'{out}/plan.json', 'w'), indent=1, ensure_ascii=False)
    Path(f'{out}/composer-lock.before.json').write_bytes(Path('composer.lock').read_bytes())
    Path(f'{out}/composer-root.before.json').write_bytes(Path('composer.json').read_bytes())
    t.relative_pass(plan)
    t.literal_pass(plan)
    rector(plan, no_rector)
    subprocess.run(['scripts/php84', 'php', f'{P02}/p02-references.php',
                    'build/p02/literal-rules.json', 'build/p02/literal-php-files.txt'], check=True)
    t.namespace_pass(plan)
    t.move_pass(plan)
    if not no_rector:
        # Rector renames moved symbols, but an unmoved sibling referenced by a
        # short name still needs an import after the caller's namespace moves.
        Path('build/p02/plan.json').write_text(json.dumps(plan, indent=1) + '\n')
        subprocess.run(['scripts/php84', 'php', f'{P02}/p02-imports.php'], check=True)
    baseline_paths(plan)
    subprocess.run([sys.executable, f'{HERE}/p03-fixtures.py', f'{out}/plan.json'], check=True)
    print('apply: done — continue with research/P03-design.md §4.2 (manifest, golden, lock, verify, pint, baseline, gates)')
    return 0


def baseline_paths(plan):
    """Project package-relative PHPStan paths; preserve messages/counts and debt."""
    changed = 0
    for baseline in sorted(Path('packages').glob('*/phpstan*.neon')):
        before = baseline.read_text()
        mapping = {os.path.relpath(old, baseline.parent): os.path.relpath(new, baseline.parent)
                   for old, new in plan['moves'] if old.startswith(str(baseline.parent) + '/')}
        after = re.sub(r'(?m)^(\s*path:\s*)([^\n]+)',
                       lambda m: m[1] + mapping.get(m[2], m[2]), before)
        if after != before:
            baseline.write_text(after)
            changed += 1
    print(f'baseline paths: {changed} files (no rebaseline)')


def lock_invariants(before, after):
    """Only path package FQCN metadata (public-api/module) and its dist reference may change."""
    def stable(lock):
        value = copy.deepcopy(lock)
        for section in ('packages', 'packages-dev'):
            for package in value.get(section, []):
                if package.get('dist', {}).get('type') == 'path':
                    package['dist'].pop('reference', None)
                    for field in ('public-api', 'module'):
                        package.get('extra', {}).get('corex', {}).pop(field, None)
        return value
    if stable(before) != stable(after):
        raise ValueError('lock dependency/metadata drift outside path public-api/module and dist.reference')


def cmd_lock(out):
    before = json.loads(Path(f'{out}/composer-lock.before.json').read_text())
    if Path(f'{out}/composer-root.before.json').read_bytes() != Path('composer.json').read_bytes():
        raise ValueError('root manifest changed since apply; refusing to reuse its lock hash')
    lock_path = Path('composer.lock')
    original_lock = lock_path.read_bytes()
    manifests = {Path(f): Path(f).read_bytes() for f in
                 ('composer.json', 'packages/tenancy/composer.json', 'packages/access/composer.json')}
    try:
        try:
            subprocess.run(['scripts/php84', 'python3', 'scripts/distribution/stage-stancl-providers.py'], check=True)
            for path, contents in manifests.items():
                if path != Path('composer.json'):
                    path.write_bytes(contents)
            subprocess.run(['scripts/php84', 'composer', 'update', '--lock', '--no-install',
                            '--no-scripts', '--no-plugins', '--no-interaction'], check=True)
        finally:
            for path, contents in manifests.items():
                path.write_bytes(contents)
        subprocess.run(['python3', 'scripts/distribution/stage-stancl-providers.py', '--portable-locks'], check=True)
        after = json.loads(lock_path.read_text())
        # Staging only changed repository transport. Restore the hash of the identical root input.
        after['content-hash'] = before['content-hash']
        Path(f'{out}/composer-lock.candidate.json').write_text(json.dumps(after, indent=4) + '\n')
        lock_invariants(before, after)
        for package in after.get('packages', []) + after.get('packages-dev', []):
            if package.get('dist', {}).get('type') != 'path':
                continue
            manifest = json.loads(Path(package['dist']['url'], 'composer.json').read_text())
            for field in ('public-api', 'module'):
                if package.get('extra', {}).get('corex', {}).get(field) != manifest.get('extra', {}).get('corex', {}).get(field):
                    raise ValueError(f"stale {field} metadata in lock: {package['name']}")
        lock_path.write_text(json.dumps(after, indent=4, ensure_ascii=False) + '\n')
    except Exception:
        lock_path.write_bytes(original_lock)
        raise
    print('lock: local FQCN metadata matches; dependencies unchanged; staging manifests restored')
    return 0


def golden_rules(plan):
    """Baseline names are pre-P02 names projected by `generated`; add one exact rule per moved class."""
    data = json.loads(Path(GOLDEN_MAP).read_text(encoding='utf-8'))
    back = collections.defaultdict(set)
    for rule in data['generated']:
        if rule['kind'] == 'class':
            back[rule['to']].add(rule['from'])
    wanted = []
    for current, final in plan['classes'].items():
        for origin in sorted(back.get(current, set()) | {current}):
            wanted.append({'kind': 'identifier', 'from': origin, 'to': final, 'reason': 'PLAN3.D57 feature-module move'})
    return data, wanted


def cmd_golden(out):
    plan = json.loads(Path(f'{out}/plan.json').read_text())
    data, wanted = golden_rules(plan)
    have = {(r.get('kind'), r['from']) for r in data['manual']}
    added = [r for r in wanted if (r['kind'], r['from']) not in have]
    data['manual'] = data['manual'] + added
    # P01's freshness gate compares canonical bytes, not just the JSON values.
    Path(GOLDEN_MAP).write_text(json.dumps(data, indent=2, ensure_ascii=False, sort_keys=True) + '\n', encoding='utf-8')
    print(f'golden: {len(added)} manual rules added, {len(data["manual"])} total')
    return 0


def cmd_golden_check(out):
    plan = json.loads(Path(f'{out}/plan.json').read_text())
    current, wanted = golden_rules(plan)
    before = json.loads(t.git('show', f'HEAD:{GOLDEN_MAP}'))
    have = {(r.get('kind'), r['from']) for r in before['manual']}
    expected = copy.deepcopy(before)
    expected['manual'] += [r for r in wanted if (r['kind'], r['from']) not in have]
    if current != expected:
        print('golden-check FAIL: frozen fields/manual projection differ from preimage and plan')
        return 1
    print(f"golden-check OK: frozen fields unchanged, {len(expected['manual']) - len(before['manual'])} exact manual additions")
    return 0


def cmd_manifest(out):
    plan = json.load(open(f'{out}/plan.json'))
    data = json.load(open(COMPLETENESS, encoding='utf-8'))
    classes = data['tokens']['classes']
    for token in classes:  # a P02 successor that P03 moves again points at its final name
        token['new'] = plan['classes'].get(token['new'], token['new'])
    seen = {token['old'] for token in classes}
    classes += [{'old': o, 'new': n, 'match': 'fqcn'} for o, n in plan['classes'].items() if o not in seen]
    seen_ns = {token['old'] for token in data['tokens']['namespaces']}
    data['tokens']['namespaces'] += [{'old': ns, 'new': '—', 'match': 'prefix'}
                                     for ns in plan['vanished_namespaces'] if ns not in seen_ns]
    open(COMPLETENESS, 'w', encoding='utf-8').write(json.dumps(data, indent=4, ensure_ascii=False) + '\n')
    print(f"manifest: classes {len(classes)}, namespaces {len(data['tokens']['namespaces'])}")
    return 0


def cmd_verify(out):
    plan = json.load(open(f'{out}/plan.json'))
    problems = []
    for src, dst in plan['moves']:
        if os.path.exists(src):
            problems.append(f'old path exists {src}')
        if not os.path.exists(dst):
            problems.append(f'missing {dst}')
    for f in t.git('ls-files', 'packages/*/src/*.php').splitlines():
        root = json.load(open(f.split('/src/')[0] + '/composer.json'))['autoload']['psr-4']
        ns = re.search(r'^\s*namespace\s+([\w\\]+)\s*;', open(f, encoding='utf-8').read(), re.M)
        rel = os.path.dirname(f.split('/src/', 1)[1]).replace('/', '\\')
        want = next(iter(root)).rstrip('\\') + ('\\' + rel if rel else '')
        if ns and ns.group(1) != want:
            problems.append(f'namespace-path {f}: {ns.group(1)} != {want}')
    old = set(plan['classes'])
    for f in t.git('ls-files').splitlines():
        if t.LITERAL_SKIP.search(f) or os.path.splitext(f)[1] not in t.TEXT_EXT or not os.path.isfile(f):
            continue
        for tok in t.FQCN.findall(open(f, encoding='utf-8', errors='replace').read()):
            if tok.replace('\\\\', '\\').lstrip('\\') in old:
                problems.append(f'old FQCN {tok} in {f}')
    _data, wanted = golden_rules(plan)
    have = {(r.get('kind'), r['from'], r['to']) for r in json.load(open(GOLDEN_MAP, encoding='utf-8'))['manual']}
    problems += [f"golden rule missing {w['from']}" for w in wanted if (w['kind'], w['from'], w['to']) not in have]
    for p in problems:
        print(p)
    print(f'verify: {len(problems)} problems')
    return 1 if problems else 0


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('command', choices=['plan', 'apply', 'lock', 'verify', 'manifest', 'golden', 'golden-check', 'adapters'])
    ap.add_argument('--out', default='build/p03')
    ap.add_argument('--no-rector', action='store_true', help='design diagnostics only; never in P03 acceptance')
    ap.add_argument('--source-tree', help='exact dry-run input tree')
    a = ap.parse_args()
    return {'plan': lambda: cmd_plan(a.out), 'apply': lambda: cmd_apply(a.out, a.no_rector, a.source_tree),
            'lock': lambda: cmd_lock(a.out), 'verify': lambda: cmd_verify(a.out), 'manifest': lambda: cmd_manifest(a.out),
            'golden': lambda: cmd_golden(a.out),
            'golden-check': lambda: cmd_golden_check(a.out),
            'adapters': lambda: subprocess.run(['scripts/php84', 'php', f'{HERE}/p03-adapters.php',
                                               f'{a.out}/plan.json']).returncode}[a.command]()


if __name__ == '__main__':
    sys.exit(main())
