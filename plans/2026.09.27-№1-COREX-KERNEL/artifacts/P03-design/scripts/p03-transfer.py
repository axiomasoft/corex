#!/usr/bin/env python3
"""P03 feature-module transfer by `artifacts/P03/module-map.tsv` (PLAN3.D57; stdlib; run from the repository root).

Reuses the P02.3 passes of `artifacts/P02-design/scripts/p02-transfer.py` with a plan built from the module map:
no package, manifest, path or directory renames; only class moves inside each package's single PSR-4 root.

  plan     [--out build/p03]   build the plan; exit 1 on a stale map, collision, chain, missing source or occupied target
  apply    [--out build/p03] [--no-rector] [--source-tree SHA]
                               execute on a CLEAN worktree (dry run: a scratch `git worktree`)
  verify   [--out build/p03]   old paths gone, targets present, namespace = path, no old FQCN left, Golden rules present
  manifest                     extend scripts/rename-completeness.json with the P03 classes and vanished namespaces
  golden                       add Golden `manual` identifier rules (baseline name -> P03 name) to tests/Golden/rename-map.json

Order inside `apply` (as P02.3, dossier research/P03-design.md §4): relative __DIR__ paths → literal pass → Rector
RenameClassRector (packages, products, tests, scripts, fixtures) → qualified-name pass → namespace declarations → git mv.
The P02 helpers keep their scratch files under build/p02/ (created here).
"""
import argparse, collections, csv, importlib.util, json, os, re, subprocess, sys

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
    plan = {'classes': dict(sorted(classes.items())), 'namespace_rules': dict(sorted(ns_rules.items())),
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
    t.relative_pass(plan)
    t.literal_pass(plan)
    rector(plan, no_rector)
    subprocess.run(['scripts/php84', 'php', f'{P02}/p02-references.php',
                    'build/p02/literal-rules.json', 'build/p02/literal-php-files.txt'], check=True)
    t.namespace_pass(plan)
    t.move_pass(plan)
    print('apply: done — continue with research/P03-design.md §4 steps 4–8 (verify, manifest, golden, pint, gates)')
    return 0


def golden_rules(plan):
    """Baseline names are pre-P02 names projected by `generated`; add one exact rule per moved class."""
    data = json.load(open(GOLDEN_MAP, encoding='utf-8'))
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
    plan = json.load(open(f'{out}/plan.json'))
    data, wanted = golden_rules(plan)
    have = {(r.get('kind'), r['from']) for r in data['manual']}
    added = [r for r in wanted if (r['kind'], r['from']) not in have]
    data['manual'] = data['manual'] + added
    open(GOLDEN_MAP, 'w', encoding='utf-8').write(json.dumps(data, indent=4, ensure_ascii=False) + '\n')
    print(f'golden: {len(added)} manual rules added, {len(data["manual"])} total')
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
    ap.add_argument('command', choices=['plan', 'apply', 'verify', 'manifest', 'golden'])
    ap.add_argument('--out', default='build/p03')
    ap.add_argument('--no-rector', action='store_true', help='design diagnostics only; never in P03 acceptance')
    ap.add_argument('--source-tree', help='exact dry-run input tree')
    a = ap.parse_args()
    return {'plan': lambda: cmd_plan(a.out), 'apply': lambda: cmd_apply(a.out, a.no_rector, a.source_tree),
            'verify': lambda: cmd_verify(a.out), 'manifest': lambda: cmd_manifest(a.out),
            'golden': lambda: cmd_golden(a.out)}[a.command]()


if __name__ == '__main__':
    sys.exit(main())
