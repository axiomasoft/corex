#!/usr/bin/env python3
"""P00 transfer-map checker (stdlib; run from the repository root).

Modes:
  (default)        package-map / namespace-map / zone-consumers vs the tree (dossier §5 p.1-5)
  --dependencies   dependency-edges.tsv vs the statically extracted target graph (§5 p.7)
  --doctrine       doctrine-delta.md vs constitution/policy documents (§5 p.6)
  --write-edges    regenerate dependency-edges.tsv, keeping dispositions of existing rows

--maps-dir / --glossary / --delta point the checker at copies (negative control).
Exit 1 with one line per violation `<code>: <detail>`; otherwise one OK line.
"""
import argparse, collections, glob, json, os, re, subprocess, sys

PLAN = 'plans/2026.09.27-№1-COREX-KERNEL'
KERNEL = 'axiomasoft/corex'
PKG_TYPES = {'kernel', 'package', 'bridge', 'product', 'tombstone-remove'}
PHASES = {'P02', 'P03'}
TREES = ('src', 'tests', 'database')
DISPOSITIONS = {'keep', 'remove', 'invert', 'move'}
ROOT_TESTS = '(monorepo tests/)'
# a dotted literal in route-name position is a route name, not a config key
ROUTE_NAME_CONTEXT = re.compile(r"(?:->name|->named|\broute|Route::has|\['router'\]->has|\$router->has|'as'\s*=>)\s*\(?\s*$")
EDGE_COLS = ['source_file', 'source_symbol', 'target_symbol', 'current_owner', 'target_owner', 'disposition',
             'projected_source', 'projected_target', 'resolution', 'owner_phase']
DECL = re.compile(r'^\s*(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+(\w+)', re.M)
NS = re.compile(r'^\s*namespace\s+([\w\\]+)\s*;', re.M)

errors = []


def err(code, detail=''):
    line = f'{code}: {detail}' if detail else code
    if line not in errors:
        errors.append(line)


def pascal(kebab):
    return ''.join(p[:1].upper() + p[1:] for p in kebab.split('-'))


def read_tsv(path, cols):
    try:
        lines = open(path, encoding='utf-8').read().splitlines()
    except OSError as exc:
        err('unreadable', f'{path} ({exc.strerror})')
        return []
    if not lines or lines[0].split('\t') != cols:
        err('bad-header', f'{path}: expected {" ".join(cols)}')
        return []
    out = []
    for n, line in enumerate(lines[1:], 2):
        if not line.strip():
            continue
        cells = line.split('\t')
        if len(cells) != len(cols):
            err('bad-row', f'{path}:{n}: {len(cells)} columns')
            continue
        out.append(dict(zip(cols, cells), _line=n))
    return out


# ---------------------------------------------------------------- glossary

def glossary_tables(path):
    """Return {section: [row cells]} for `## Section` markdown tables."""
    try:
        text = open(path, encoding='utf-8').read()
    except OSError as exc:
        err('unreadable', f'{path} ({exc.strerror})')
        return {}
    tables, section = collections.defaultdict(list), None
    for line in text.splitlines():
        head = re.match(r'^##\s+(.+?)\s*$', line)
        if head:
            section = head.group(1)
            continue
        if section and line.startswith('|') and not re.match(r'^\|\s*:?-', line):
            cells = [c.strip() for c in line.strip().strip('|').split('|')]
            tables[section].append(cells)
    return {k: v[1:] for k, v in tables.items()}  # drop header row


def ticked(cell):
    m = re.search(r'`([^`]+)`', cell)
    return m.group(1) if m else cell


# ---------------------------------------------------------------- tree facts

def php_files():
    out = []
    for tree in TREES:
        out += glob.glob(f'packages/*/{tree}/**/*.php', recursive=True)
    return sorted(out)


_file_cache = {}


def php_facts(path):
    """(first namespace, its declarations, source, [(namespace, declarations)] per namespace block)."""
    if path not in _file_cache:
        src = open(path, encoding='utf-8').read()
        code = strip_php(src)[0]
        heads = list(NS.finditer(code))
        blocks = [(h.group(1), DECL.findall(code[h.end():heads[i + 1].start() if i + 1 < len(heads) else len(code)]))
                  for i, h in enumerate(heads)]
        first = blocks[0] if blocks else (None, DECL.findall(code))
        _file_cache[path] = (first[0], first[1], src, blocks)
    return _file_cache[path]


def extra_namespaces(row):
    """`ns:<Old\\Ns>=<New\\Ns>` tokens in note map secondary namespace blocks of a multi-namespace file."""
    return dict(re.findall(r'ns:([\w\\]+)=([\w\\]+)', row['note']))


def autoload(pkg):
    try:
        data = json.load(open(f'packages/{pkg}/composer.json', encoding='utf-8'))
    except OSError:
        return [], []
    psr4, classmap = [], []
    for sect in ('autoload', 'autoload-dev'):
        for prefix, dirs in data.get(sect, {}).get('psr-4', {}).items():
            for d in dirs if isinstance(dirs, list) else [dirs]:
                psr4.append((prefix, f'packages/{pkg}/' + d.rstrip('/') + '/'))
        for d in data.get(sect, {}).get('classmap', []):
            classmap.append(f'packages/{pkg}/' + d)
    return psr4, classmap


def conforming_prefixes(path, ns, psr4):
    out = set()
    for prefix, d in psr4:
        if path.startswith(d):
            rel = os.path.dirname(path[len(d):])
            want = prefix.rstrip('\\') + ('\\' + rel.replace('/', '\\') if rel else '')
            if want == ns:
                out.add(prefix)
    return out


def dest_of(row):
    m = re.search(r'dest=(\S+/)', row['note'])
    return m.group(1) if m else None


class Maps:
    def __init__(self, maps_dir, glossary):
        self.pkg_rows = read_tsv(f'{maps_dir}/package-map.tsv',
                                 ['old_dir', 'old_name', 'new_dir', 'new_name', 'new_root', 'type', 'phase'])
        self.ns_rows = read_tsv(f'{maps_dir}/namespace-map.tsv',
                                ['old_package', 'old_path', 'old_prefix', 'new_package', 'new_prefix', 'phase', 'note'])
        self.maps_dir = maps_dir
        g = glossary_tables(glossary)
        self.gloss = g
        self.kernel_modules = {ticked(r[0]).rstrip('\\') for r in g.get('Kernel modules', []) if r}
        self.root_symbols = {ticked(r[0]): ticked(r[1]) for r in g.get('Root symbols', []) if len(r) > 1}
        self.reserved = {ticked(r[0]).rstrip('\\'): ticked(r[1]) for r in g.get('Reserved segments', []) if len(r) > 1}
        self.dev_roots = [(ticked(r[0]).rstrip('\\'), ticked(r[1])) for r in g.get('Dev roots', []) if len(r) > 1]
        self.by_name = {}
        for r in self.pkg_rows:
            if r['type'] != 'tombstone-remove':
                self.by_name.setdefault(r['new_name'], r)
        self.rows_by_path = {}
        for r in self.ns_rows:
            if r['old_path'] in self.rows_by_path:
                err('duplicate', r['old_path'])
            self.rows_by_path[r['old_path']] = r

    def match(self, path):
        best = None
        for p, r in self.rows_by_path.items():
            if p == path or (p.endswith('/') and path.startswith(p)):
                if best is None or len(p) > len(best['old_path']):
                    best = r
        return best

    def final_ns(self, path, row):
        if row['new_prefix'] == '—':
            return None
        base = row['new_prefix'].rstrip('\\')
        if row['old_path'].endswith('/'):
            rel = os.path.dirname(path[len(row['old_path']):])
            if rel:
                base += '\\' + rel.replace('/', '\\')
        return base

    def roots_for(self, pkg_name):
        """Namespace roots → directory for the target package (longest first)."""
        p = self.by_name.get(pkg_name)
        if not p:
            return []
        root = 'CoreX' if p['type'] == 'kernel' else p['new_root']
        roots = [(root, p['new_dir'] + '/src/'), (root + '\\Tests', p['new_dir'] + '/tests/')]
        roots += [(ns, d) for ns, d in self.dev_roots if d.startswith(p['new_dir'] + '/')]
        return sorted(roots, key=lambda x: -len(x[0]))

    def target_path(self, path, row, fns):
        dest = dest_of(row)
        if dest:
            rel = path[len(row['old_path']):] if row['old_path'].endswith('/') else os.path.basename(path)
            return dest + rel
        pkg = self.by_name.get(row['new_package'])
        if pkg is None:
            return None
        if fns is None:
            return pkg['new_dir'] + '/' + path.split('/', 2)[2]
        for ns, d in self.roots_for(row['new_package']):
            if fns == ns or fns.startswith(ns + '\\'):
                rel = fns[len(ns):].strip('\\').replace('\\', '/')
                return d + (rel + '/' if rel else '') + os.path.basename(path)
        return None


# ---------------------------------------------------------------- default mode

def check_maps(m):
    if errors:
        return
    old_dirs = sorted(d.rstrip('/') for d in glob.glob('packages/*/'))
    seen_old = collections.defaultdict(list)
    for r in m.pkg_rows:
        seen_old[r['old_dir']].append(r)
        if r['type'] not in PKG_TYPES:
            err('package-type', f"{r['old_dir']}: {r['type']}")
        if r['phase'] not in PHASES:
            err('package-phase', f"{r['old_dir']}: {r['phase']}")
        if not os.path.isdir(r['old_dir']):
            err('dead-row', r['old_dir'])
        if r['type'] == 'tombstone-remove':
            if (r['new_dir'], r['new_name'], r['new_root']) != ('—', '—', '—'):
                err('tombstone', f"{r['old_dir']}: target must be —")
            if glob.glob(r['old_dir'] + '/**/*.php', recursive=True):
                err('tombstone', f"{r['old_dir']}: contains PHP")
            continue
        if r['type'] == 'product':
            continue
        m_name = re.fullmatch(r'axiomasoft/corex(?:-([a-z0-9]+(?:-[a-z0-9]+)*))?', r['new_name'])
        if not m_name:
            err('package-name', f"{r['new_name']} violates axiomasoft/corex[-<kebab>]")
            continue
        if r['type'] == 'kernel':
            if r['new_name'] != KERNEL:
                err('package-name', f"kernel row {r['old_dir']} -> {r['new_name']}")
            seg = r['new_root'].split('\\')
            if r['new_root'] != 'CoreX' and not (len(seg) == 2 and r['new_root'] in m.kernel_modules):
                err('foreign-root', f"{r['new_root']} ({KERNEL}) not in kernel modules")
        elif r['new_root'] != 'CoreX\\' + pascal(m_name.group(1) or ''):
            err('package-root', f"{r['new_name']} -> {r['new_root']} (expected CoreX\\{pascal(m_name.group(1) or '')})")
        if r['new_root'].rstrip('\\') in m.kernel_modules and r['type'] != 'kernel':
            err('root-collision', f"{r['new_root']} is a kernel module and a package root ({r['new_name']})")
    for d in old_dirs:
        if d not in seen_old:
            err('package-missing', d)
    for d, rs in seen_old.items():
        if len(rs) > 1 and len({r['new_dir'] for r in rs}) != len(rs):
            err('package-duplicate', d)
    targets = collections.defaultdict(set)
    for r in m.pkg_rows:
        if r['type'] != 'tombstone-remove':
            targets[r['new_name']].add(r['new_dir'])
    for name, dirs in targets.items():
        if len(dirs) != 1:
            err('package-target', f"{name} -> {', '.join(sorted(dirs))}")

    names_by_old = {d: {r['new_name'] for r in rs} for d, rs in seen_old.items()}
    oldname_by_dir = {d: rs[0]['old_name'] for d, rs in seen_old.items()}
    fed = collections.defaultdict(set)
    for r in m.ns_rows:
        path = r['old_path']
        parts = path.split('/')
        if len(parts) < 4 or parts[0] != 'packages' or parts[2] not in TREES:
            err('bad-path', path)
            continue
        old_dir = '/'.join(parts[:2])
        if r['old_package'] != oldname_by_dir.get(old_dir):
            err('old-package', f"{path}: {r['old_package']} != {oldname_by_dir.get(old_dir)}")
        if r['new_package'] not in m.by_name:
            err('unknown-package', f"{path}: {r['new_package']}")
        if r['phase'] not in PHASES:
            err('phase', f"{path}: {r['phase']}")
        if re.fullmatch(r'packages/[^/]+/src/', path):
            err('root-fallback', path)
        if not os.path.exists(path):
            err('dead-row', path)
        if r['new_prefix'] != '—' and not r['new_prefix'].endswith('\\'):
            err('bad-prefix', f"{path}: {r['new_prefix']}")
        fed[old_dir].add(r['new_package'])
    for d, names in names_by_old.items():
        if seen_old[d][0]['type'] == 'tombstone-remove':
            if fed.get(d):
                err('tombstone', f'{d}: has namespace-map rows')
            continue
        for n in names - fed.get(d, set()):
            err('package-unmapped', f'{d} -> {n}: no namespace-map row')

    # coverage, prefixes, final FQCN, target paths
    psr4 = {}
    covered = collections.Counter()
    fqcn_owner, root_owner, paths = {}, collections.defaultdict(set), {}
    for f in php_files():
        pkg = f.split('/')[1]
        row = m.match(f)
        if row is None:
            err('uncovered', f)
            continue
        covered[row['old_path']] += 1
        if pkg not in psr4:
            psr4[pkg] = autoload(pkg)
        ns, decls, _, blocks = php_facts(f)
        extra = extra_namespaces(row)
        for bns, bdecls in blocks[1:]:
            if bns not in extra:
                err('multi-namespace', f'{f}: namespace {bns} needs ns:{bns}=<target> in the row note')
                continue
            for cls in bdecls:
                fq = extra[bns] + '\\' + cls
                if fq.lower() in fqcn_owner and fqcn_owner[fq.lower()][1] != f:
                    err('fqcn-collision', f'{fq}: {fqcn_owner[fq.lower()][1]}, {f}')
                fqcn_owner[fq.lower()] = (fq, f)
                if not fq.startswith(m.by_name.get(row['new_package'], {}).get('new_root', '') + '\\'):
                    err('foreign-root', f"{fq} ({row['new_package']}) outside its root")
        if ns is None:
            if row['old_prefix'] != '—' or row['new_prefix'] != '—':
                err('prefix-mismatch', f'{f}: namespace-less file under prefixed row {row["old_path"]}')
        else:
            conf = conforming_prefixes(f, ns, psr4[pkg][0])
            ok = row['old_prefix'] in conf if conf else \
                (ns + '\\').startswith(row['old_prefix']) and row['old_prefix'] != '—'
            if not ok:
                want = ', '.join(sorted(conf)) or ns + '\\'
                err('prefix-mismatch', f'{f}: row {row["old_path"]} old_prefix {row["old_prefix"]} (actual {want})')
            if row['new_prefix'] == '—':
                err('prefix-mismatch', f'{f}: namespaced file under row without new_prefix')
                continue
        fns = m.final_ns(f, row)
        tp = m.target_path(f, row, fns)
        owner = m.by_name.get(row['new_package'])
        if tp is None:
            err('target-path', f'{f}: {fns} has no root in {row["new_package"]} (declare a dev root)')
        else:
            if owner and not tp.startswith(owner['new_dir'] + '/'):
                err('target-path', f'{f} -> {tp} outside {owner["new_dir"]}')
            if tp.split('/')[2 if tp.startswith(('packages/', 'products/')) else 1] != f.split('/')[2] and not dest_of(row):
                err('tree-mismatch', f'{f} -> {tp}')
            if tp.lower() in paths:
                err('path-collision', f'{tp} <- {paths[tp.lower()]}, {f}')
            paths[tp.lower()] = f
        if fns is None or owner is None:
            continue
        for cls in decls:
            fq = fns + '\\' + cls
            key = fq.lower()
            if key in fqcn_owner and fqcn_owner[key][1] != f:
                err('fqcn-collision', f'{fq}: {fqcn_owner[key][1]}, {f}')
            fqcn_owner[key] = (fq, f)
            seg = fq.split('\\')
            root2 = '\\'.join(seg[:2])
            root_owner[root2].add(row['new_package'])
            if owner['type'] == 'kernel':
                if fq in m.root_symbols:
                    continue
                if len(seg) < 3 or (root2 not in m.kernel_modules and root2 not in m.reserved):
                    err('foreign-root', f'{root2} ({KERNEL}) not in kernel modules')
            elif not fq.startswith(owner['new_root'] + '\\'):
                err('foreign-root', f"{fq} ({row['new_package']}) outside {owner['new_root']}")
    for root2, owners in sorted(root_owner.items()):
        if len(owners) > 1:
            err('root-collision', f"{root2} -> {', '.join(sorted(owners))}")
    for p in m.rows_by_path:
        if covered[p] == 0 and os.path.exists(p):
            err('dead-row', f'{p} (no file selects it)')
    for fq, owner in m.root_symbols.items():
        if fq.lower() not in fqcn_owner:
            err('root-symbol', f'{fq} declared in glossary but not produced by the map')
    for seg, owner in m.reserved.items():
        if owner not in m.by_name:
            err('reserved-owner', f'{seg}: {owner}')

    # zone consumers (§5 p.5)
    rows = read_tsv(f'{m.maps_dir}/zone-consumers.tsv',
                    ['zone', 'consumer_packages', 'package_count', 'imports', 'verdict'])
    fresh = subprocess.run([sys.executable, f'{PLAN}/artifacts/P00-design/scripts/zone-consumers.py'],
                           capture_output=True, text=True, check=False)
    if fresh.returncode:
        err('zone-consumers', f'zone-consumers.py exit {fresh.returncode}')
    measured = [l.split('\t') for l in fresh.stdout.splitlines()[1:]]
    got = [[r['zone'], r['consumer_packages'], r['package_count'], r['imports']] for r in rows]
    if got != measured:
        err('zone-stale', 'columns 1-4 differ from zone-consumers.py output')
    for r in rows:
        v = r['verdict']
        if v != 'keep' and not (v.startswith('move→') and v[5:] in m.by_name):
            err('zone-verdict', f"{r['zone']}: {v}")
        zone_dir = f"packages/core/src/{r['zone'].replace(chr(92), '/')}/"
        if v.startswith('move→') and zone_dir in m.rows_by_path and m.rows_by_path[zone_dir]['new_package'] != v[5:]:
            err('zone-verdict-mismatch', f"{r['zone']}: {v} but map sends {zone_dir} to "
                                         f"{m.rows_by_path[zone_dir]['new_package']}")

    # glossary packages
    gl = {ticked(r[1]): r for r in m.gloss.get('Packages', []) if len(r) >= 5}
    for name in m.by_name:
        r = gl.get(name)
        if r is None or not r[4].strip() or r[4].strip() == '—':
            err('glossary-missing', f'{name}: no §Packages row with a one-sentence purpose')
    return len(m.ns_rows), len(m.by_name)


# ---------------------------------------------------------------- PHP reference extraction

KEYWORDS = set('''abstract and array as bool break callable case catch class clone const continue declare default do
echo else elseif empty enddeclare endfor endforeach endif endswitch endwhile enum eval exit extends false final finally
float fn for foreach function global goto if implements include include_once instanceof insteadof int interface isset
iterable list match mixed namespace never new null object or parent print private protected public readonly require
require_once return self static string switch throw trait true try unset use var void while xor yield from'''.split())
NAME = re.compile(r'(?<![\w\\$>:])(\\?[A-Za-z_]\w*(?:\\[A-Za-z_]\w*)*)(?![\w\\])')
DOC_TAG = re.compile(r'@(?:phpstan-|psalm-)?(param|return|var|throws|property(?:-read|-write)?|method|extends|implements'
                     r'|use|mixin|template(?:-covariant|-contravariant)?|type|import-type|param-out|assert\w*|self-out)'
                     r'\s+(.*)')


def strip_php(src):
    """Return (code without comments/strings, docblocks, string literals, code tail before each literal)."""
    code, docs, strings, ctx = [], [], [], []
    i, n = 0, len(src)
    while i < n:
        c = src[i]
        if src.startswith('/**', i):
            j = src.find('*/', i + 3)
            j = n if j < 0 else j + 2
            docs.append(src[i:j])
            code.append(' ')
            i = j
        elif src.startswith('/*', i):
            j = src.find('*/', i + 2)
            i = n if j < 0 else j + 2
            code.append(' ')
        elif src.startswith('//', i) or (c == '#' and not src.startswith('#[', i)):
            j = src.find('\n', i)
            i = n if j < 0 else j
        elif c in '\'"':
            j, buf = i + 1, []
            while j < n and src[j] != c:
                if src[j] == '\\' and j + 1 < n:
                    buf.append(src[j:j + 2])
                    j += 2
                    continue
                buf.append(src[j])
                j += 1
            strings.append(''.join(buf))
            ctx.append(''.join(code[-40:])[-40:])
            code.append('""')
            i = j + 1
        elif src.startswith('<<<', i):
            m = re.match(r"<<<\s*['\"]?(\w+)['\"]?\n", src[i:])
            if not m:
                code.append(c)
                i += 1
                continue
            end = re.search(r'^\s*' + m.group(1) + r'\b', src[i + m.end():], re.M)
            j = n if end is None else i + m.end() + end.end()
            strings.append(src[i:j])
            ctx.append('')
            code.append('""')
            i = j
        else:
            code.append(c)
            i += 1
    return ''.join(code), docs, strings, ctx


def doc_types(doc):
    out = []
    for line in doc.splitlines():
        m = DOC_TAG.search(line)
        if not m:
            continue
        rest, depth, tok = m.group(2).strip(), 0, []
        for ch in rest:
            if ch in '<({[':
                depth += 1
            elif ch in '>)}]':
                depth -= 1
            elif ch.isspace() and depth <= 0:
                break
            tok.append(ch)
        typ = ''.join(tok)
        if m.group(1).startswith('template'):
            of = re.search(r'\bof\s+(\S+)', rest)
            typ = of.group(1) if of else ''
        out.append(typ)
    return out


def references(path):
    """Yield (name, kind) where kind ∈ {use, code, doc, string}; names already resolved to FQCN."""
    src = open(path, encoding='utf-8').read()
    code, docs, strings, ctx = strip_php(src)
    ns_m = NS.search(code)
    ns = ns_m.group(1) if ns_m else ''
    aliases, out, traits = {}, [], []
    depth, pos = 0, 0
    # import-level `use` (brace depth 0); class-level `use` = trait use
    for m in re.finditer(r'[{}]|\buse\s+(?!function\b|const\b)([^;(]+);', code):
        tok = m.group(0)
        if tok == '{':
            depth += 1
            continue
        if tok == '}':
            depth -= 1
            continue
        body = m.group(1).strip()
        if depth == 0:
            group = re.match(r'([\w\\]+)\\\{(.*)\}', body, re.S)
            items = [(group.group(1) + '\\' + x.strip()) for x in group.group(2).split(',') if x.strip()] \
                if group else [x.strip() for x in body.split(',')]
            for it in items:
                am = re.match(r'\\?([\w\\]+)(?:\s+as\s+(\w+))?$', it)
                if am:
                    full = am.group(1)
                    aliases[(am.group(2) or full.rsplit('\\', 1)[-1]).lower()] = full
                    out.append((full, 'use'))
        else:
            traits += [it.strip() for it in body.split('{')[0].split(',') if it.strip()]
    code_wo_use = re.sub(r'^\s*(?:namespace|use)\s+[^;]+;', lambda x: ' ' * len(x.group(0)), code, flags=re.M)
    heads = [(h.start(), h.group(1)) for h in NS.finditer(code)]

    def ns_at(pos):
        cur = ns
        for start, name in heads:
            if start <= pos:
                cur = name
        return cur

    def resolve(name, at=0):
        if name.startswith('\\'):
            return name[1:]
        head, _, tail = name.partition('\\')
        if head.lower() in aliases:
            return aliases[head.lower()] + ('\\' + tail if tail else '')
        cur = ns_at(at)
        return (cur + '\\' + name) if cur else name

    skip_after = re.compile(r'(?:function|const|case|goto|->|\?->|::|enum|class|interface|trait)\s*$')
    for m in NAME.finditer(code_wo_use):
        name = m.group(1)
        if name.lower() in KEYWORDS:
            continue
        if skip_after.search(code_wo_use[max(0, m.start() - 12):m.start()]):
            continue
        out.append((resolve(name, m.start()), 'code' if '\\' in name else 'short'))
    for d in docs:
        for typ in doc_types(d):
            for nm in re.findall(r'\\?[A-Za-z_]\w*(?:\\[A-Za-z_]\w*)*', typ):
                if nm.lower() not in KEYWORDS:
                    out.append((resolve(nm), 'doc'))
    for s, before in zip(strings, ctx):
        if re.fullmatch(r'[a-z][a-z0-9-]*\.[\w.*-]+', s):
            if ROUTE_NAME_CONTEXT.search(before):
                continue
            out.append((s, 'config'))
            continue
        s2 = s.replace('\\\\', '\\').lstrip('\\')
        if re.fullmatch(r'(?:CoreX|Flex)\\[\w\\]+', s2):
            out.append((s2.rstrip('\\'), 'string'))
    out += [(resolve(t), 'code') for t in traits]
    return out, ns


def symbol_table(m):
    """current FQCN(lower) -> (current FQCN, file, final FQCN, owner)."""
    table = {}
    for f in php_files():
        row = m.match(f)
        ns, decls, _, blocks = php_facts(f)
        if row is None or ns is None:
            continue
        fns = m.final_ns(f, row)
        extra = extra_namespaces(row)
        for i, (bns, bdecls) in enumerate(blocks):
            target = (fns or bns) if i == 0 else extra.get(bns, bns)
            for cls in bdecls:
                table[(bns + '\\' + cls).lower()] = (bns + '\\' + cls, f, target + '\\' + cls, row['new_package'])
    # monorepo-level tests/ are outside the maps; a package reaching into them must be resolved (not kept)
    for f in sorted(glob.glob('tests/**/*.php', recursive=True)):
        ns, decls, _, _ = php_facts(f)
        for cls in decls if ns and ns.startswith('CoreX\\Tests') else ():
            table.setdefault((ns + '\\' + cls).lower(), (ns + '\\' + cls, f, ns + '\\' + cls, ROOT_TESTS))
    return table


def composition_files():
    """Config/route files: string/DI composition outside src (owner = first package-map target)."""
    return sorted(glob.glob('packages/*/config/*.php') + glob.glob('packages/*/routes/*.php'))


def discover_edges(m):
    table = symbol_table(m)
    namespaces = set()
    for fq, *_ in table.values():
        parts = fq.split('\\')
        for i in range(1, len(parts)):
            namespaces.add('\\'.join(parts[:i]).lower())
    first_target = {}
    for r in m.pkg_rows:
        first_target.setdefault(r['old_dir'], r['new_name'])
    edges, unresolved = {}, set()
    config_owner, config_keys = {}, {}
    for c in glob.glob('packages/*/config/*.php'):
        root = os.path.basename(c)[:-4]
        config_owner[root] = first_target.get('/'.join(c.split('/')[:2]))
        config_keys[root] = set(re.findall(r"^    '([\w-]+)'\s*=>", open(c, encoding='utf-8').read(), re.M))
    sources = [f for f in php_files() if f.split('/')[2] in ('src', 'tests')] + composition_files()
    for f in sources:
        if f.split('/')[2] in ('src', 'tests'):
            row = m.match(f)
            if row is None:
                continue
            owner = row['new_package']
            ns, decls, _, _ = php_facts(f)
            src_sym = (ns + '\\' + decls[0]) if ns and decls else '—'
        else:
            owner = first_target.get('/'.join(f.split('/')[:2]))
            src_sym = '—'
        refs, _ = references(f)
        for name, kind in refs:
            if kind == 'config':
                root, key = (name.split('.') + [''])[:2]
                if config_owner.get(root) not in (None, owner) and key in config_keys.get(root, ()):
                    edges[(f, src_sym, 'config:' + root)] = (owner, config_owner[root], 'config:' + root)
                continue
            hit = table.get(name.lower())
            if hit is None:
                if kind in ('use', 'code') and re.match(r'(?:CoreX|Flex)\\', name) \
                        and name.lower() not in namespaces:
                    unresolved.add((f, name))
                continue
            tgt_fq, tgt_file, tgt_final, tgt_owner = hit
            if tgt_owner == owner or tgt_fq == src_sym:
                continue
            edges[(f, src_sym, tgt_fq)] = (owner, tgt_owner, tgt_final)
    return edges, unresolved, table


def check_dependencies(m, path):
    edges, unresolved, table = discover_edges(m)
    for f, name in sorted(unresolved):
        err('unresolved-edge', f'{f}: {name}')
    rows = read_tsv(path, EDGE_COLS)
    seen = {}
    graph = collections.defaultdict(set)
    kernel = KERNEL
    for r in rows:
        key = (r['source_file'], r['source_symbol'], r['target_symbol'])
        if key in seen:
            err('edge-duplicate', ' '.join(key))
            continue
        seen[key] = r
        if key not in edges:
            err('edge-stale', ' '.join(key))
            continue
        owner, tgt_owner, tgt_final = edges[key]
        if (r['current_owner'], r['target_owner']) != (owner, tgt_owner):
            err('edge-owner', f"{' '.join(key)}: {r['current_owner']}->{r['target_owner']} != {owner}->{tgt_owner}")
        d = r['disposition']
        if d not in DISPOSITIONS:
            err('edge-disposition', f"{' '.join(key)}: {d}")
            continue
        ps, pt = r['projected_source'], r['projected_target']
        if d == 'keep':
            if tgt_owner == ROOT_TESTS:
                err('edge-projection', f"{' '.join(key)}: package code cannot keep a dependency on monorepo tests/")
            if (ps, pt) != (owner, tgt_owner):
                err('edge-projection', f"{' '.join(key)}: keep must project {owner}->{tgt_owner}")
            if '\\Internal\\' in tgt_final and ps != pt:
                err('internal-edge', f"{r['source_file']} -> {tgt_final} ({pt})")
            if re.search(r'\\Tests\\|^CoreX\\Testing\\', tgt_final) and ps != pt:
                err('test-support-edge', f"{r['source_file']} -> {tgt_final} ({pt}): tests/ of a package is not "
                                         f"installed for its dependents")
        elif d == 'remove':
            if (ps, pt) != ('—', '—'):
                err('edge-projection', f"{' '.join(key)}: remove must project —/—")
        else:
            for p in (ps, pt):
                if p not in m.by_name:
                    err('edge-projection', f"{' '.join(key)}: unknown package {p}")
        if d != 'keep' and (not re.search(r'\bD\d{2,}\b', r['resolution']) or r['owner_phase'] != 'P02'):
            err('edge-resolution', f"{' '.join(key)}: {d} needs resolution with D# and owner_phase P02")
        if d != 'remove' and ps != pt and ps in m.by_name and pt in m.by_name:
            graph[ps].add(pt)
    for key in sorted(set(edges) - set(seen)):
        err('edge-missing', ' '.join(key))
    for src, tgts in sorted(graph.items()):
        if src == kernel:
            for t in sorted(tgts):
                err('kernel-dependency', f'{kernel} -> {t}')
    for cyc in cycles(graph):
        err('dependency-cycle', ' -> '.join(cyc + [cyc[0]]))
    return len(rows), len(graph)


def cycles(graph):
    """Tarjan SCC; return non-trivial components."""
    index, low, stack, on, out, counter = {}, {}, [], set(), [], [0]
    sys.setrecursionlimit(10000)

    def visit(v):
        index[v] = low[v] = counter[0]
        counter[0] += 1
        stack.append(v)
        on.add(v)
        for w in sorted(graph.get(v, ())):
            if w not in index:
                visit(w)
                low[v] = min(low[v], low[w])
            elif w in on:
                low[v] = min(low[v], index[w])
        if low[v] == index[v]:
            comp = []
            while True:
                w = stack.pop()
                on.discard(w)
                comp.append(w)
                if w == v:
                    break
            if len(comp) > 1:
                out.append(sorted(comp))
    for v in sorted(set(graph) | {w for ws in graph.values() for w in ws}):
        if v not in index:
            visit(v)
    return out


def write_edges(m, path):
    edges, unresolved, _ = discover_edges(m)
    old = {}
    if os.path.exists(path):
        for r in read_tsv(path, EDGE_COLS):
            old[(r['source_file'], r['source_symbol'], r['target_symbol'])] = r
    with open(path, 'w', encoding='utf-8') as fh:
        fh.write('\t'.join(EDGE_COLS) + '\n')
        for key in sorted(edges):
            owner, tgt, _ = edges[key]
            r = old.get(key)
            if r and (r['current_owner'], r['target_owner']) == (owner, tgt):
                cells = [r[c] for c in EDGE_COLS]
            else:
                cells = [*key, owner, tgt, 'keep', owner, tgt, '—', '—']
            fh.write('\t'.join(cells) + '\n')
    print(f'edges written: {len(edges)} ({len(set(edges) & set(old))} kept), unresolved {len(unresolved)}')


# ---------------------------------------------------------------- doctrine

def check_doctrine(path):
    docs_dir = 'docs/architecture'
    try:
        text = open(path, encoding='utf-8').read()
    except OSError as exc:
        err('unreadable', f'{path} ({exc.strerror})')
        return 0
    try:
        constitution = open(f'{docs_dir}/constitution.md', encoding='utf-8').read()
    except OSError as exc:
        err('unreadable', f'{docs_dir}/constitution.md ({exc.strerror})')
        return 0
    articles = re.findall(r'^### ([A-Z]+-[0-9]+)', constitution, re.M)
    known = set(articles)
    anti_goals = set(re.findall(r'^\| (NON-[0-9]+) \|', constitution, re.M))
    rows = []
    for line in text.splitlines():
        if not line.startswith('|') or re.match(r'^\|\s*:?-', line):
            continue
        cells = [c.strip() for c in line.strip().strip('|').split('|')]
        if len(cells) != 6 or cells[0] in ('Документ', 'Document'):
            continue
        rows.append(cells)
    if not rows:
        err('doctrine-empty', path)
    seen, counts = set(), collections.Counter()
    for doc, rid, action, rule, phase, gate in rows:
        new_doc = doc.endswith('(new)')
        doc = doc.replace('(new)', '').strip().strip('`')
        key = (doc, rid)
        if key in seen:
            err('doctrine-duplicate', rid if doc == 'constitution.md' else f'{doc} {rid}')
            continue
        seen.add(key)
        if action not in ('keep', 'rewrite', 'remove', 'add'):
            err('doctrine-action', f'{doc} {rid}: {action}')
        if not new_doc and not os.path.isfile(f'{docs_dir}/{doc}'):
            err('doctrine-document', f'{doc} {rid}: no such document')
        if rid.startswith('NEW-'):
            if action != 'add':
                err('doctrine-action', f'{doc} {rid}: NEW-* requires add')
        elif doc == 'constitution.md' and rid.startswith('NON-'):
            if rid not in anti_goals:
                err('doctrine-unknown', rid)
        elif doc == 'constitution.md':
            if not re.fullmatch(r'[A-Z]+-[0-9]+', rid) or rid not in known:
                err('doctrine-unknown', rid)
            counts[rid] += 1
        elif re.fullmatch(r'§(\d+)', rid):
            if not new_doc and os.path.isfile(f'{docs_dir}/{doc}'):
                sections = re.findall(r'^## ', open(f'{docs_dir}/{doc}', encoding='utf-8').read(), re.M)
                if int(rid[1:]) < 1 or int(rid[1:]) > len(sections):
                    err('doctrine-section', f'{doc} {rid}: document has {len(sections)} sections')
        else:
            err('doctrine-unknown', f'{doc} {rid}')
        if action in ('rewrite', 'add'):
            if not rule or rule == '—' or not re.fullmatch(r'P0[1-9]', phase) or not gate or gate == '—':
                err('doctrine-incomplete', f'{doc} {rid}: rewrite/add need rule, phase P01-P09 and gate')
    for rid in articles:
        if counts[rid] == 0:
            err('doctrine-missing', rid)
    return len(known)


# ---------------------------------------------------------------- main

def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    mode = ap.add_mutually_exclusive_group()
    mode.add_argument('--dependencies', action='store_true')
    mode.add_argument('--doctrine', action='store_true')
    mode.add_argument('--write-edges', action='store_true')
    ap.add_argument('--maps-dir', default=f'{PLAN}/artifacts/P00')
    ap.add_argument('--glossary', default=f'{PLAN}/research/03-glossary.md')
    ap.add_argument('--delta', default=None, help='doctrine-delta.md (default: <maps-dir>/doctrine-delta.md)')
    a = ap.parse_args()
    if not os.path.isdir('packages'):
        print('run from the repository root', file=sys.stderr)
        return 2
    if a.doctrine:
        n = check_doctrine(a.delta or f'{a.maps_dir}/doctrine-delta.md')
        ok = f'doctrine OK: {n} articles'
    else:
        m = Maps(a.maps_dir, a.glossary)
        if a.write_edges:
            write_edges(m, f'{a.maps_dir}/dependency-edges.tsv')
            return 0
        if a.dependencies:
            n, p = check_dependencies(m, f'{a.maps_dir}/dependency-edges.tsv')
            ok = f'dependencies OK: acyclic, kernel-independent ({n} edges, {p} source packages)'
        else:
            res = check_maps(m)
            ok = f'maps OK: {res[0]} prefixes, {res[1]} packages' if res else ''
    if errors:
        for e in errors:
            print(e)
        return 1
    print(ok)
    return 0


if __name__ == '__main__':
    sys.exit(main())
