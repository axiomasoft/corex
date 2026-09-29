#!/usr/bin/env python3
"""P02.3 mechanical transfer by the P00 maps (stdlib; run from the repository root).

Subcommands:
  plan   [--out build/p02]        compute moves / class map / package map; write them, exit 1 on any conflict
  apply  [--out build/p02] [--no-rector]
                                  execute the plan on a CLEAN worktree (dry run: a scratch `git worktree`)
  verify [--out build/p02]        post-apply structural checks (no old paths, namespace = path, one PSR-4 root)

Order inside `apply` (dossier §4.3): preflight → literal pass (strings/comments/non-PHP text) → Rector
RenameClassRector (code references) → namespace declarations → git mv (PHP by map, rest by extra-moves.tsv
and whole-directory renames) → manifests → tombstone removal → report.  Each token is rewritten exactly once:
the literal pass never touches PHP code tokens, Rector never touches literals.
"""
import argparse, collections, importlib.util, json, os, re, shutil, subprocess, sys, tempfile

PLAN = 'plans/2026.09.27-№1-COREX-KERNEL'
HERE = os.path.dirname(os.path.abspath(__file__))
spec = importlib.util.spec_from_file_location('cm', f'{PLAN}/artifacts/P00-design/scripts/check-maps.py')
cm = importlib.util.module_from_spec(spec)
spec.loader.exec_module(cm)

EXTRA_MOVES = f'{PLAN}/artifacts/P02-design/facts/extra-moves.tsv'
# Seam rows P02.3 itself resolves (own Tests\TestCase per split package, D35); anything else left = stop.
SCAFFOLD_TARGETS = {'CoreX\\Api\\Tests\\TestCase'}
# Paths whose text is history or generated elsewhere; the literal pass must not rewrite them.
LITERAL_SKIP = re.compile(r'^(plans/|audits/|vendor/|build/|composer\.lock$|tests/Golden/(baseline/|(?:rename-map|package-projection|promoted-fixtures)\.json$)'
                          r'|scripts/(?:p15-rename-completeness-manifest|rename-completeness)\.json$)|(^|/)CHANGELOG\.md$|/history/')
TEXT_EXT = {'.php', '.md', '.json', '.neon', '.yml', '.yaml', '.xml', '.dist', '.sh', '.py', '.txt', '.tsv', '.patch',
            '.ts', '.js', '.vue', '.toml', '.ini', '.stub', '.fixture', ''}
FQCN = re.compile(r'(?<![\w\\])((?:\\\\|\\)?(?:CoreX|Flex)(?:(?:\\\\|\\)[A-Za-z_]\w*)+)')


def die(msg):
    print(f'p02-transfer: {msg}', file=sys.stderr)
    sys.exit(1)


def git(*args, check=True):
    return subprocess.run(['git', *args], check=check, capture_output=True, text=True).stdout


# ------------------------------------------------------------------ plan

def build_plan():
    m = cm.Maps(f'{PLAN}/artifacts/P00', f'{PLAN}/research/03-glossary.md')
    table = cm.symbol_table(m)
    classes = {fq: final for fq, _f, final, owner in table.values() if owner != cm.ROOT_TESTS and fq != final}
    # A namespace token (docs, manifests, phpunit) is rewritten only when all its classes land in one namespace.
    spread = collections.defaultdict(set)
    for fq, _f, final, owner in table.values():
        if owner != cm.ROOT_TESTS:
            spread[fq.rsplit('\\', 1)[0]].add(final.rsplit('\\', 1)[0])
    ns_rules = {old: next(iter(new)) for old, new in spread.items() if len(new) == 1 and old not in new}
    problems = []
    for old, new in classes.items():
        if new in classes:
            problems.append(f'chain {old} -> {new} -> {classes[new]}')
    for new, n in collections.Counter(classes.values()).items():
        if n > 1:
            problems.append(f'duplicate-target {new}')
    moves, namespaces, seen = [], {}, collections.Counter()
    for f in cm.php_files():
        row = m.match(f)
        if row is None:
            problems.append(f'unmapped {f}')
            continue
        fns = m.final_ns(f, row)
        target = m.target_path(f, row, fns)
        if target is None:
            problems.append(f'no-target {f} ({row["new_package"]})')
            continue
        seen[target] += 1
        ns, _decls, _src, blocks = cm.php_facts(f)
        rename = {}
        if ns and fns and ns != fns:
            rename[ns] = fns
        rename.update({o: n for o, n in cm.extra_namespaces(row).items() if o != n})
        if rename:
            namespaces[f] = rename
        if target != f:
            moves.append((f, target))
    sources = {f for f, _ in moves}
    for t, n in seen.items():
        if n > 1:
            problems.append(f'target-collision {t}')
    for _src, t in moves:
        if os.path.exists(t) and t not in sources:
            problems.append(f'target-exists {t}')  # would overwrite a file that stays in place
    packages, ambiguous = {}, collections.defaultdict(list)
    for r in m.pkg_rows:
        if r['type'] == 'tombstone-remove':
            continue
        ambiguous[r['old_name']].append(r['new_name'])
    for old, news in ambiguous.items():
        packages[old] = news[0]  # primary successor: first package-map row (kernel for core/modules, engine for operations)
    dirs = {}
    by_dir = collections.defaultdict(list)
    for r in m.pkg_rows:
        by_dir[r['old_dir']].append(r)
    for old_dir, rows in by_dir.items():
        if len(rows) == 1 and rows[0]['type'] not in ('tombstone-remove', 'kernel') and rows[0]['new_dir'] != old_dir:
            dirs[old_dir] = rows[0]['new_dir']  # whole-directory rename (attributes, crm)
    public_origins = {}
    for fq, f, final, owner in table.values():
        if owner == cm.ROOT_TESTS:
            continue
        manifest = '/'.join(f.split('/')[:2]) + '/composer.json'
        data = json.load(open(manifest))
        if any(fq == prefix or fq.startswith(prefix + '\\') for prefix in data.get('extra', {}).get('corex', {}).get('public-api', [])):
            public_origins[fq] = final
    tombstones = [r['old_dir'] for r in m.pkg_rows if r['type'] == 'tombstone-remove']
    return {
        'classes': dict(sorted(classes.items())),
        'public_origins': public_origins,
        'all_classes': {fq: final for fq, _f, final, owner in table.values() if owner != cm.ROOT_TESTS},
        'class_sources': {fq: f for fq, f, _final, owner in table.values() if owner != cm.ROOT_TESTS},
        'namespace_rules': dict(sorted(ns_rules.items())),
        'moves': sorted(moves),
        'namespaces': dict(sorted(namespaces.items())),
        'packages': dict(sorted(packages.items())),
        'successors': {k: v for k, v in sorted(ambiguous.items()) if len(v) > 1},
        'dir_renames': dict(sorted(dirs.items())),
        'tombstones': tombstones,
        'extra_moves': read_extra_moves(problems),
    }, problems


def read_extra_moves(problems):
    out = []
    if not os.path.exists(EXTRA_MOVES):
        problems.append(f'missing {EXTRA_MOVES}')
        return out
    for line in open(EXTRA_MOVES, encoding='utf-8').read().splitlines()[1:]:
        if not line.strip():
            continue
        action, src, dst, why = (line.split('\t') + ['', '', '', ''])[:4]
        if action not in ('move', 'delete', 'merge-neon', 'merge-composer'):
            problems.append(f'extra-move action {action}')
        if not os.path.exists(src):
            # rows marked `created-by:<item>` name files a seam item creates before the transfer
            (PENDING if 'created-by:' in why else problems).append(f'extra-move missing {src}')
        out.append((action, src, dst))
    return out


PENDING = []


def residual_seams():
    """Non-keep rows of dependency-edges.tsv still present in the code graph."""
    m = cm.Maps(f'{PLAN}/artifacts/P00', f'{PLAN}/research/03-glossary.md')
    edges, _unresolved, _t = cm.discover_edges(m)
    rows = cm.read_tsv(f'{PLAN}/artifacts/P00/dependency-edges.tsv', cm.EDGE_COLS)
    return [r for r in rows if r['disposition'] != 'keep'
            and (r['source_file'], r['source_symbol'], r['target_symbol']) in edges]


def cmd_plan(out):
    plan, problems = build_plan()
    os.makedirs(out, exist_ok=True)
    json.dump(plan, open(f'{out}/plan.json', 'w'), indent=1, ensure_ascii=False)
    with open(f'{out}/moves.tsv', 'w') as fh:
        fh.write('old_path\tnew_path\n')
        for a, b in plan['moves']:
            fh.write(f'{a}\t{b}\n')
    for p in problems:
        print(p)
    for p in PENDING:
        print('pending ' + p)
    print(f"plan: {len(plan['classes'])} classes, {len(plan['moves'])} php moves, {len(plan['namespaces'])} namespace "
          f"rewrites, {len(plan['packages'])} packages, {len(plan['dir_renames'])} dir renames, "
          f"{len(plan['extra_moves'])} extra moves, {len(PENDING)} pending, {len(problems)} problems")
    return 1 if problems else 0


# ------------------------------------------------------------------ apply

def literal_rules(plan):
    """Exact tokens for text outside PHP code: FQCNs (single and doubled backslashes), composer names, paths."""
    # Class tokens win over namespace tokens: a token is looked up whole, never by prefix.
    classes = {**plan['namespace_rules'], **plan['classes']}
    names = dict(plan['packages'])
    paths = {a: b for a, b in plan['moves']}
    for action, src, dst in plan['extra_moves']:
        if action == 'move':
            paths[src] = dst
    dirs = {**plan['dir_renames'], 'packages/modules/tests': 'packages/core/tests/Modules',
            'packages/modules/src': 'packages/core/src/Modules', 'packages/modules': 'packages/core',
            'packages/import-export': 'packages/transfer'}
    # Standalone package harnesses resolve installed dependencies by Composer
    # vendor path, which has its own leading slash inside relative literals.
    dirs.update({'vendor/' + old: 'vendor/' + new for old, new in names.items()})
    dirs['vendor/corex/modules/src'] = 'vendor/axiomasoft/corex/src/Modules'
    dirs['vendor/corex/modules/tests'] = 'vendor/axiomasoft/corex/tests/Modules'
    return classes, names, paths, dirs


_ALT = {}


def alternation(table, before, after):
    """One compiled regex per rule table (longest alternative first); lookup by the matched token."""
    key = (id(table), before, after)
    if key not in _ALT:
        alts = '|'.join(re.escape(k) for k in sorted(table, key=len, reverse=True))
        _ALT[key] = re.compile(f'{before}({alts}){after}') if table else None
    return _ALT[key]


def rewrite_text(text, classes, names, paths, dirs):
    def fq(mo):
        tok = mo.group(1)
        doubled = '\\\\' in tok
        lead = tok.startswith('\\\\') if doubled else tok.startswith('\\')
        bare = tok.replace('\\\\', '\\').lstrip('\\')
        new = classes.get(bare)
        if new is None:
            return tok
        new = new.replace('\\', '\\\\') if doubled else new
        return ('\\\\' if doubled else '\\') + new if lead else new
    text = FQCN.sub(fq, text)
    for table, before, after in ((paths, r'(?<![\w.-])', r'(?![\w/])'), (dirs, r'(?<![\w.-])', r'(?=/|\b)'),
                                 (names, r'(?<![\w./@-])', r'(?![\w-])')):
        rx = alternation(table, before, after)
        if rx is not None:
            text = rx.sub(lambda mo, t=table: t[mo.group(1)], text)
    return text



def relative_pass(plan):
    """Preserve resource targets of __DIR__/dirname after their caller moves."""
    moved = dict(plan['moves'])
    moved.update({src: dst for action, src, dst in plan['extra_moves'] if action == 'move'})
    directories = {**plan['dir_renames'], 'packages/modules/tests': 'packages/core/tests/Modules',
                   'packages/modules/src': 'packages/core/src/Modules', 'packages/modules': 'packages/core'}
    directories.update({src: dst for action, src, dst in plan['extra_moves']
                        if action == 'move' and os.path.isdir(src)})
    # Directory resources can be projected only when every descendant retains
    # its relative suffix under the same destination (fixtures, lint rules).
    candidates = collections.defaultdict(set)
    # Include unchanged resources: one moved config file does not imply its
    # entire directory moved. Missing siblings would produce a false consensus.
    files = set(git('ls-files').splitlines()) | set(moved)
    for old in files:
        new = moved.get(old, old)
        if old not in moved:
            for source, target in sorted(directories.items(), key=lambda kv: -len(kv[0])):
                if old.startswith(source + '/'):
                    new = target + old[len(source):]
                    break
        parent = os.path.dirname(old)
        while parent.count('/') >= 1:
            suffix = old[len(parent):]
            candidates[parent].add(new[:-len(suffix)] if new.endswith(suffix) else None)
            parent = os.path.dirname(parent)
    for old, targets in candidates.items():
        if len(targets) == 1 and None not in targets:
            directories[old] = next(iter(targets))
    root = os.getcwd()
    rx = re.compile(r"(?P<base>__DIR__|dirname\(\s*__DIR__\s*(?:,\s*(?P<up>\d+)\s*)?\))(?P<join>\s*\.\s*)(?P<q>['\"])(?P<path>/[^'\"$\n]*)(?P=q)")
    changed = 0
    for f in git('ls-files').splitlines():
        if not f.endswith('.php') or LITERAL_SKIP.search(f) or not os.path.isfile(f):
            continue
        src = open(f, encoding='utf-8').read()
        def replace(m):
            base = os.path.dirname(os.path.join(root, f))
            if m['base'] != '__DIR__':
                for _ in range(int(m['up'] or 1)):
                    base = os.path.dirname(base)
            target = os.path.relpath(os.path.normpath(os.path.join(base, m['path'].lstrip('/'))), root)
            new_target = moved.get(target, target)
            if target not in moved:
                for old, new in sorted(directories.items(), key=lambda kv: -len(kv[0])):
                    if target == old or target.startswith(old + '/'):
                        new_target = new + target[len(old):]
                        break
            # An executable component must look for its own package installation.
            old_owner = '/'.join(f.split('/')[:2])
            new_file = moved.get(f, f)
            new_owner = '/'.join(new_file.split('/')[:2])
            if target == old_owner + '/vendor/autoload.php':
                new_target = new_owner + '/vendor/autoload.php'
            new_base = os.path.dirname(os.path.join(root, new_file))
            if new_target == target and new_file == f:
                return m.group(0)
            suffix = '/' + os.path.relpath(os.path.join(root, new_target), new_base)
            if m['path'].endswith('/'):
                suffix += '/'
            return '__DIR__' + m['join'] + m['q'] + suffix + m['q']
        new = rx.sub(replace, src)
        if new != src:
            open(f, 'w', encoding='utf-8').write(new)
            changed += 1
    print(f'relative paths: {changed} files')


def literal_pass(plan):
    classes, names, paths, dirs = literal_rules(plan)
    php_helper = os.path.join(HERE, 'p02-literal-pass.php')
    rules = 'build/p02/literal-rules.json'
    json.dump({'classes': classes, 'names': names, 'paths': paths, 'dirs': dirs}, open(rules, 'w'))
    changed = 0
    php_files = []
    for f in git('ls-files').splitlines():
        if LITERAL_SKIP.search(f) or not os.path.isfile(f):
            continue
        if os.path.splitext(f)[1] not in TEXT_EXT:
            continue
        if f.endswith(('.php', '.php.fixture')):
            php_files.append(f)
            continue
        src = open(f, encoding='utf-8', errors='surrogateescape').read()
        new = rewrite_text(src, classes, names, paths, dirs)
        if new != src:
            open(f, 'w', encoding='utf-8', errors='surrogateescape').write(new)
            changed += 1
    listing = 'build/p02/literal-php-files.txt'
    open(listing, 'w').write('\n'.join(php_files) + '\n')
    res = subprocess.run(['scripts/php84', 'php', php_helper, rules, listing], capture_output=True, text=True)
    if res.returncode:
        die('literal pass (php) failed: ' + res.stderr.strip())
    print(f'literal pass: {changed} text files; {res.stdout.strip()}')


def rector(plan, no_rector):
    cfg = 'build/p02/rector.php'
    pairs = ''.join(f"        {json.dumps(o)} => {json.dumps(n)},\n" for o, n in plan['classes'].items())
    # Directory discovery traverses standalone vendor trees and path-repository
    # symlinks before applying skips. Tracked source files give the same scope
    # without walking installed dependencies or resolving recursive links.
    files = [f for f in git('ls-files').splitlines()
             if f.endswith('.php') and f.startswith(('packages/', 'tests/', 'scripts/', 'fixtures/'))
             and not LITERAL_SKIP.search(f) and os.path.isfile(f)]
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
        die('rector failed: ' + (res.stdout + res.stderr)[-2000:])
    print('rector: done')


def namespace_pass(plan):
    for f, rename in plan['namespaces'].items():
        src = open(f, encoding='utf-8').read()
        for old, new in rename.items():
            src, n = re.subn(r'^(\s*namespace\s+)' + re.escape(old) + r'(\s*[;{])', r'\g<1>' + new.replace('\\', '\\\\') + r'\2',
                             src, count=1, flags=re.M)
            if n != 1:
                die(f'namespace {old} not found in {f}')
        # A file must not import a class it declares (Rector may rename such a `use` onto the new namespace).
        ns = re.search(r'^\s*namespace\s+([\w\\]+)\s*;', src, re.M)
        if ns:
            for decl in cm.DECL.findall(src):
                src = re.sub(r'^use\s+' + re.escape(ns.group(1) + '\\' + decl) + r'\s*;\n', '', src, flags=re.M)
        open(f, 'w', encoding='utf-8').write(src)
    print(f"namespace pass: {len(plan['namespaces'])} files")


def mv(src, dst):
    os.makedirs(os.path.dirname(dst) or '.', exist_ok=True)
    subprocess.run(['git', 'mv', src, dst], check=True)


def move_pass(plan):
    for src, dst in plan['moves']:
        mv(src, dst)
    for action, src, dst in plan['extra_moves']:
        if action == 'move':
            mv(src, dst)
        elif action == 'delete':
            subprocess.run(['git', 'rm', '-qf', src], check=True)
        elif action == 'merge-neon':
            merge_neon(src, dst)
        elif action == 'merge-composer':
            merge_composer(src, dst)
    for old, new in plan['dir_renames'].items():
        for f in git('ls-files', old).splitlines():
            mv(f, new + f[len(old):])
    for t in plan['tombstones']:
        subprocess.run(['git', 'rm', '-rq', t], check=True)
    left = [d for d in ('packages/modules',) if git('ls-files', d).strip()]
    if left:
        die('files left in merged package: ' + git('ls-files', *left))
    print(f"move pass: {len(plan['moves'])} php, {len(plan['extra_moves'])} extra, {len(plan['dir_renames'])} dirs")


def merge_neon(src, dst):
    """modules extension.neon becomes the kernel extension; kernel services are appended (examples §4.3)."""
    core = open(dst, encoding='utf-8').read()
    services = core.split('services:', 1)[1]
    merged = open(src, encoding='utf-8').read().rstrip('\n') + '\n' + services.lstrip('\n')
    open(dst, 'w', encoding='utf-8').write(merged)
    subprocess.run(['git', 'rm', '-qf', src], check=True)


def merge_composer(src, dst):
    """Union third-party require/require-dev/suggest of a merged package into the kernel manifest."""
    a, b = json.load(open(src, encoding='utf-8')), json.load(open(dst, encoding='utf-8'))
    for section in ('require', 'require-dev', 'suggest'):
        for k, v in a.get(section, {}).items():
            if not k.startswith(('corex/', 'axiomasoft/corex')):
                b.setdefault(section, {}).setdefault(k, v)
    for k, v in a.get('extra', {}).get('laravel', {}).items():
        for item in v:
            if item not in b.setdefault('extra', {}).setdefault('laravel', {}).setdefault(k, []):
                b['extra']['laravel'][k].append(item)
    json.dump(b, open(dst, 'w', encoding='utf-8'), indent=4, ensure_ascii=False)
    open(dst, 'a').write('\n')
    subprocess.run(['git', 'rm', '-qf', src], check=True)


def project_root_autoload(data, plan):
    """Project classmap/files paths; the merged namespace-less tests retain a classmap."""
    paths = dict(plan['moves'])
    paths.update({src: dst for action, src, dst in plan['extra_moves'] if action == 'move'})
    directories = {**plan['dir_renames'], 'packages/modules/tests': 'packages/core/tests/Modules'}
    for section in ('autoload', 'autoload-dev'):
        for kind in ('classmap', 'files'):
            if kind not in data.get(section, {}):
                continue
            projected = []
            for path in data[section][kind]:
                new = paths.get(path, path)
                if new == path:
                    for old, target in sorted(directories.items(), key=lambda pair: -len(pair[0])):
                        if path == old or path.startswith(old + '/'):
                            new = target + path[len(old):]
                            break
                if new not in projected:
                    projected.append(new)
            data[section][kind] = projected


def root_autoload_problems(data):
    problems = []
    for section in ('autoload', 'autoload-dev'):
        config = data.get(section, {})
        paths = list(config.get('classmap', [])) + list(config.get('files', []))
        for value in config.get('psr-4', {}).values():
            paths.extend(value if isinstance(value, list) else [value])
        for path in paths:
            if not os.path.exists(path):
                problems.append(f'composer.json: missing {section} path {path}')
    return problems


def manifests(plan):
    """Names, one PSR-4 root per package, requires by projected edges (dossier §4.3, D44 п.2)."""
    m = cm.Maps(f'{PLAN}/artifacts/P00', f'{PLAN}/research/03-glossary.md')
    rows = cm.read_tsv(f'{PLAN}/artifacts/P00/dependency-edges.tsv', cm.EDGE_COLS)
    needs = collections.defaultdict(lambda: {'require': set(), 'require-dev': set()})
    for r in rows:
        if r['disposition'] == 'remove' or r['projected_source'] == r['projected_target']:
            continue
        section = 'require-dev' if '/tests/' in r['source_file'] else 'require'
        needs[r['projected_source']][section].add(r['projected_target'])
    report = []
    for pkg in m.by_name.values():
        path = f"{pkg['new_dir']}/composer.json"
        if not os.path.exists(path):
            report.append(f'scaffold {path} (examples §4.1)')
            continue
        data = json.load(open(path, encoding='utf-8'))
        data['name'] = pkg['new_name']
        root = 'CoreX\\' if pkg['type'] == 'kernel' else pkg['new_root'] + '\\'
        data.setdefault('autoload', {})['psr-4'] = {root: 'src/'}
        # Kernel tests are namespace-less Pest files; its dev autoload is the glossary dev roots only.
        dev = {} if pkg['type'] == 'kernel' else {root + 'Tests\\': 'tests/'}
        for ns, d in m.dev_roots:
            if d.startswith(pkg['new_dir'] + '/') and not ns.endswith('\\Fixtures'):
                dev[ns + '\\'] = d[len(pkg['new_dir']) + 1:]
        data.setdefault('autoload-dev', {})['psr-4'] = dev
        if pkg['type'] != 'kernel':
            data['autoload-dev'].pop('classmap', None)
        release = set(m.by_name)
        for section in ('require', 'require-dev'):
            # The literal pass already renamed names (ambiguous ones to their primary successor);
            # projected edges add the other successors a package really uses.
            deps = data.get(section, {})
            before = {k for k in deps if k in release}
            wanted = (before | needs[pkg['new_name']][section]) - {pkg['new_name']}
            if section == 'require':
                # Guarded optional integrations stay optional. An unguarded
                # shipped edge (Access→Tenancy, CRM→Intl) is a runtime require
                # even when the preimage installed its target only for tests.
                wanted -= set(data.get('suggest', {})) - before
                optional = {('axiomasoft/corex-operations', 'axiomasoft/corex-transfer'),
                            ('axiomasoft/corex-tenancy', 'axiomasoft/corex-wakeup'),
                            ('axiomasoft/corex-tasks-workflow', 'axiomasoft/corex-wakeup')}
                wanted -= {target for source, target in optional if source == pkg['new_name']}
            else:
                wanted -= set(data.get('require', {}))
            for k in sorted(wanted):
                deps[k] = '^1.0@dev'
                if k not in before:
                    report.append(f'{path}: {section} added {k} (edge-derived)')
            if deps:
                data[section] = dict(sorted(deps.items(), key=lambda kv: (not kv[0].startswith(('php', 'ext-')), kv[0])))
            if section == 'require':
                for name in wanted:
                    data.get('require-dev', {}).pop(name, None)
        json.dump(data, open(path, 'w', encoding='utf-8'), indent=4, ensure_ascii=False)
        open(path, 'a').write('\n')
    # Root autoload-dev: the monorepo runner autoloads every package test root (`composer test`).
    root_manifest = json.load(open('composer.json', encoding='utf-8'))
    project_root_autoload(root_manifest, plan)
    psr4 = root_manifest.setdefault('autoload-dev', {}).setdefault('psr-4', {})
    for key in [k for k, d in psr4.items() if str(d).startswith(('packages/', 'products/'))]:
        psr4.pop(key)
    for pkg in m.by_name.values():
        if pkg['type'] != 'kernel' and os.path.isdir(f"{pkg['new_dir']}/tests"):
            psr4[pkg['new_root'] + '\\Tests\\'] = f"{pkg['new_dir']}/tests/"
    for ns, d in m.dev_roots:
        if not ns.endswith('\\Fixtures'):
            psr4[ns + '\\'] = d
    root_manifest['autoload-dev']['psr-4'] = dict(sorted(psr4.items()))
    json.dump(root_manifest, open('composer.json', 'w', encoding='utf-8'), indent=4, ensure_ascii=False)
    open('composer.json', 'a').write('\n')
    for line in report:
        print('manifest: ' + line)


def cmd_apply(out, no_rector, allow_seams, source_tree=None):
    if source_tree:
        # Admit only the exact preimage validated in the detached dry-run. A
        # private index preserves the owner's index and existing worktree edits.
        with tempfile.TemporaryDirectory() as tmp:
            env = {**os.environ, 'GIT_INDEX_FILE': os.path.join(tmp, 'index')}
            subprocess.run(['git', 'read-tree', 'HEAD'], env=env, check=True)
            subprocess.run(['git', 'add', '-A'], env=env, check=True)
            actual = subprocess.check_output(['git', 'write-tree'], env=env, text=True).strip()
        expected = git('rev-parse', source_tree + '^{tree}').strip()
        if actual != expected:
            die('worktree differs from the validated source tree')
    elif git('status', '--porcelain').strip():
        die('worktree not clean')
    plan, problems = build_plan()
    if problems or PENDING:
        die(f'plan has {len(problems)} problems and {len(PENDING)} pending sources; run `plan`')
    left = [r for r in residual_seams() if r['target_symbol'] not in SCAFFOLD_TARGETS]
    if left and allow_seams:
        print(f'WARNING: {len(left)} unresolved seams ignored (--allow-seams: design diagnostics only)')
    elif left:
        die(f'{len(left)} unresolved seams (P02.1/P02.2), e.g. {left[0]["source_file"]} -> {left[0]["target_symbol"]}')
    os.makedirs(out, exist_ok=True)
    json.dump(plan, open(f'{out}/plan.json', 'w'), indent=1, ensure_ascii=False)
    relative_pass(plan)
    literal_pass(plan)
    rector(plan, no_rector)
    subprocess.run(['scripts/php84', 'php', os.path.join(HERE, 'p02-references.php'),
                    'build/p02/literal-rules.json', 'build/p02/literal-php-files.txt'], check=True)
    namespace_pass(plan)
    move_pass(plan)
    manifests(plan)
    print('apply: done — continue with dossier §4.3 steps 4–9 (verify, scaffold, composer update, pint, gates)')
    return 0


# ------------------------------------------------------------------ gate manifest

VANISHED_ROOTS = ['CoreX\\Api', 'CoreX\\Auth', 'CoreX\\Search\\Meilisearch', 'CoreX\\Money', 'CoreX\\SavedViews',
                  'CoreX\\Contracts', 'CoreX\\Enums', 'CoreX\\Events', 'CoreX\\Exceptions', 'CoreX\\Models',
                  'CoreX\\Concerns', 'CoreX\\Support', 'CoreX\\PHPStan']  # glossary §Reserved segments
GATE_EXCLUDE = [r'~^(plans|audits)/~', r'~(^|/)CHANGELOG\.md$~', r'~/docs/history/~',
                r'~^docs/architecture/rename-registry\.md$~', r'~^tests/Golden/(?:rename-map|package-projection)\.json$~',
                r'~^scripts/rename-completeness\.json$~', r'~^tests/Golden/baseline/~',
                r'~^scripts/p15-rename-completeness-(gate\.php|manifest\.json)$~']


def cmd_manifest(target):
    """Write scripts/rename-completeness.json from the pre-transfer tree (run before `apply`)."""
    plan, problems = build_plan()
    if problems:
        die(f'plan has {len(problems)} problems')
    new_ns = {n.rsplit('\\', 1)[0] for n in plan['classes'].values()}
    m = cm.Maps(f'{PLAN}/artifacts/P00', f'{PLAN}/research/03-glossary.md')
    kept = {fq for fq, _f, final, owner in cm.symbol_table(m).values() if fq == final}
    new_ns |= {fq.rsplit('\\', 1)[0] for fq in kept}
    def covered(ns):
        return any(ns == r or ns.startswith(r + '\\') for r in VANISHED_ROOTS)
    tokens = {
        'classes': [{'old': o, 'new': n, 'match': 'fqcn'} for o, n in plan['classes'].items() if not covered(o)],
        'namespaces': [{'old': r, 'new': '—', 'match': 'prefix'} for r in VANISHED_ROOTS]
                      + [{'old': o, 'new': n, 'match': 'fqcn'} for o, n in plan['namespace_rules'].items()
                         if o not in new_ns and not covered(o)],
        'packages': [{'old': o, 'new': n, 'match': 'word'} for o, n in plan['packages'].items() if o != n],
        'paths': [{'old': d, 'new': plan['dir_renames'].get(d, '—'), 'match': 'word'}
                  for d in sorted({*plan['dir_renames'], *plan['tombstones'], 'packages/modules'})],
        'identifiers': [],
    }
    for line in open(f'{PLAN}/artifacts/P02-design/facts/identifier-map.tsv', encoding='utf-8').read().splitlines()[1:]:
        kind, old, new, _pkg, _note = line.split('\t')
        tokens['identifiers'].append({'old': old, 'new': new, 'match': 'word', 'kind': kind})
    data = {'schema': 'corex-rename-completeness/1', 'generated_by': f'{PLAN}/artifacts/P02-design/scripts/p02-transfer.py manifest',
            'exclude': GATE_EXCLUDE, 'tokens': tokens}
    json.dump(data, open(target, 'w', encoding='utf-8'), indent=1, ensure_ascii=False)
    open(target, 'a').write('\n')
    print('manifest: ' + ', '.join(f'{k} {len(v)}' for k, v in tokens.items()))
    return 0


# ------------------------------------------------------------------ verify

def cmd_verify(out):
    plan = json.load(open(f'{out}/plan.json'))
    problems = root_autoload_problems(json.load(open('composer.json', encoding='utf-8')))
    for src, dst in plan['moves']:
        if os.path.exists(src):
            problems.append(f'old path exists {src}')
        if not os.path.exists(dst):
            problems.append(f'missing {dst}')
    for d in ['packages/modules', *plan['dir_renames'], *plan['tombstones']]:
        if git('ls-files', d).strip():
            problems.append(f'tracked files remain under {d}')
    for manifest in sorted(git('ls-files', 'packages/*/composer.json', 'products/*/composer.json').splitlines()):
        data = json.load(open(manifest))
        roots = data.get('autoload', {}).get('psr-4', {})
        if len(roots) != 1 or list(roots.values()) != ['src/']:
            problems.append(f'{manifest}: autoload.psr-4 must be exactly one root -> src/ ({roots})')
        pkg_dir = os.path.dirname(manifest)
        root = next(iter(roots), '')
        for f in git('ls-files', f'{pkg_dir}/src').splitlines():
            if not f.endswith('.php'):
                continue
            ns = re.search(r'^\s*namespace\s+([\w\\]+)\s*;', open(f, encoding='utf-8').read(), re.M)
            rel = os.path.dirname(f[len(pkg_dir) + len('/src/'):]).replace('/', '\\')
            want = (root.rstrip('\\') + ('\\' + rel if rel else '')) if root else None
            if ns and want and ns.group(1) != want:
                problems.append(f'namespace-path {f}: {ns.group(1)} != {want}')
        for old, new in plan['packages'].items():
            if old != new and re.search(r'"' + re.escape(old) + r'"', json.dumps(data)):
                problems.append(f'{manifest}: old package name {old}')
    for p in problems:
        print(p)
    print(f'verify: {len(problems)} problems')
    return 1 if problems else 0


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('command', choices=['plan', 'apply', 'verify', 'seams', 'manifest'])
    ap.add_argument('--target', default='scripts/rename-completeness.json')
    ap.add_argument('--out', default='build/p02')
    ap.add_argument('--no-rector', action='store_true')
    ap.add_argument('--allow-seams', action='store_true', help='design diagnostics only; never in P02.3 acceptance')
    ap.add_argument('--source-tree', help='exact dry-run input tree; preserves pre-existing owner edits')
    a = ap.parse_args()
    if a.command == 'plan':
        return cmd_plan(a.out)
    if a.command == 'apply':
        return cmd_apply(a.out, a.no_rector, a.allow_seams, a.source_tree)
    if a.command == 'manifest':
        return cmd_manifest(a.target)
    if a.command == 'seams':
        rows = residual_seams()
        for r in rows:
            print(f"seam {r['disposition']} {r['source_file']} -> {r['target_symbol']}")
        rest = [r for r in rows if r['target_symbol'] not in SCAFFOLD_TARGETS]
        print(f'seams: {len(rows)} unresolved ({len(rows) - len(rest)} scaffold-owned, {len(rest)} blocking)')
        return 1 if rest else 0
    return cmd_verify(a.out)


if __name__ == '__main__':
    sys.exit(main())
