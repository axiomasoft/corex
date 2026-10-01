#!/usr/bin/env python3
"""Feature-module layout gate (PLAN3.D57; stdlib; run from the repository root).

Scope: every PHP file under `packages/*/src/` (the product keeps its own layout, glossary N8). Rules:

  R0  namespace = path: declared namespace == PSR-4 root of the package + directories under `src/`.
  R1  roots: the kernel `src/` holds only `CoreServiceProvider.php` and directories of the closed kernel-module list;
      a package `src/` has no layer bucket directory (`Support`, `Models`, `Commands`, ...).
  R2  a public category (`Contracts|Data|Enums|Events|Exceptions`) sits only directly under a module.
  R3  a file lives in a public category or in a bridge (`Internal|Laravel|Testing|PHPStan`) of its module; a module may
      also hold its service provider directly, and only a package root holds `<Pkg>Module.php`.
  R4  `Internal` only directly under a module (never nested).
  R5  no reference into the `Internal` of another module of the same package, except from a composition root
      (permitted provider/root entry, `Laravel` directly under the module; PLAN3.D58).

  R6  runtime files never reference a Tooling, PHPStan or Testing namespace, in any package.

The package root is itself a module (the package's primary module); its sub-directories that are neither categories nor
bridges are feature modules. The kernel has no root module. Foreign-package `Internal` references stay with
`package-graph-gate` R2.

  python3 scripts/module-layout-gate.py                      check against the baseline (new or stale entry -> exit 1)
  python3 scripts/module-layout-gate.py --write-baseline     rewrite the baseline from the current tree
  python3 scripts/module-layout-gate.py --report             print every violation, exit 0
  options: --root DIR (tree to scan), --config FILE, --baseline FILE,
           --project MAP (design only: evaluate the tree as moved by a P03 module-map.tsv)
"""
from __future__ import annotations

import argparse
import collections
import csv
import json
import re
import sys
from pathlib import Path

CATEGORIES = ('Contracts', 'Data', 'Enums', 'Events', 'Exceptions')
BRIDGES = ('Internal', 'Laravel', 'Testing', 'PHPStan', 'Tooling')
DEV_BRIDGES = ('Tooling', 'PHPStan', 'Testing')
KERNEL = 'axiomasoft/corex'
NS_RE = re.compile(r'^\s*namespace\s+([\w\\]+)\s*;', re.M)
DECL_RE = re.compile(r'^\s*(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+(\w+)', re.M)
USE_RE = re.compile(r'\buse\s+([^;()]+);')
NAME_RE = re.compile(r'(?<![\w\\])\\?[A-Za-z_]\w*(?:\\[A-Za-z_]\w*)+')
# Match strings and comments in a single pass: comment markers inside a quoted
# URL must not swallow a later import/reference. Preserve newlines for declarations.
NON_CODE_RE = re.compile(r"'(?:\\.|[^'\\])*'|\"(?:\\.|[^\"\\])*\"|/\*.*?\*/|//[^\n]*|#(?!\[)[^\n]*", re.S)


def resolve(name: str, namespace: str, imports: dict[str, str]) -> str:
    if name.startswith('\\'):
        return name.lstrip('\\')
    first, sep, rest = name.partition('\\')
    if first.lower() == 'namespace':
        return namespace + ('\\' + rest if sep else '')
    if first.lower() in imports:
        return imports[first.lower()] + ('\\' + rest if sep else '')
    return namespace + '\\' + name if namespace else name


def load_packages(root: Path) -> dict[str, dict]:
    packages = {}
    for manifest in sorted(root.glob('packages/*/composer.json')):
        data = json.loads(manifest.read_text(encoding='utf-8'))
        roots = [ns for ns, path in data.get('autoload', {}).get('psr-4', {}).items() if path.rstrip('/') == 'src']
        if roots:
            packages[manifest.parent.name] = {'name': data['name'], 'ns': roots[0].rstrip('\\')}
    return packages


def parse(text: str) -> tuple[str, list[str], set[str]]:
    code = NON_CODE_RE.sub(lambda m: re.sub(r'[^\n]', ' ', m[0]), text)
    ns = NS_RE.search(code)
    namespace = ns.group(1) if ns else ''
    refs, imports, spans = set(), {}, []
    for m in USE_RE.finditer(code):
        # A trait-use inside a class is not a namespace import. Closure use (...)
        # is excluded by USE_RE. Package sources use one unbracketed namespace.
        before = code[:m.start()]
        if before.count('{') != before.count('}'):
            continue
        spans.append(m.span())
        body = m[1].strip()
        if re.match(r'(?:function|const)\b', body):
            continue
        group = re.fullmatch(r'([\w\\]+)\s*\{(.*)\}', body, re.S)
        prefix, members = (group[1].rstrip('\\') + '\\', group[2]) if group else ('', body)
        for member in members.split(','):
            part = re.fullmatch(r'\s*([\w\\]+)(?:\s+as\s+(\w+))?\s*', member)
            if not part:  # grouped function/const imports are not class references
                continue
            full = (prefix + part[1]).lstrip('\\')
            imports[(part[2] or full.rsplit('\\', 1)[-1]).lower()] = full
            refs.add(full)
    body = code
    for start, end in reversed(spans):
        body = body[:start] + ' ' * (end - start) + body[end:]
    body = NS_RE.sub('', body)
    refs.update(resolve(m[0], namespace, imports) for m in NAME_RE.finditer(body))
    return namespace, DECL_RE.findall(code), refs


def scan(root: Path, packages: dict) -> list[dict]:
    files = []
    for directory, meta in packages.items():
        src = root / 'packages' / directory / 'src'
        for path in sorted(src.rglob('*.php')):
            namespace, decls, refs = parse(path.read_text(encoding='utf-8', errors='replace'))
            files.append({'package': meta['name'], 'root_ns': meta['ns'], 'dir': directory,
                          'rel': path.relative_to(src).parts, 'namespace': namespace, 'decls': decls, 'refs': refs})
    return files


def project(files: list[dict], map_path: Path) -> None:
    """Rewrite paths, namespaces and references as the P03 transfer would (design-time prediction)."""
    rows = list(csv.DictReader(map_path.open(encoding='utf-8'), delimiter='\t'))
    by_path = {r['path']: r for r in rows}
    classes = {f"{r['namespace']}\\{r['symbol']}": f"{r['target_namespace']}\\{r['symbol']}"
               for r in rows if r['symbol'] and r['path'] != r['target_path']}
    for f in files:
        row = by_path.get('packages/' + f['dir'] + '/src/' + '/'.join(f['rel']))
        if row:
            f['rel'] = tuple(row['target_path'].split('/src/', 1)[1].split('/'))
            f['namespace'] = row['target_namespace']
        f['refs'] = {classes.get(ref, ref) for ref in f['refs']}


def module_of(f: dict, kernel_modules: set[str]) -> tuple[str | None, tuple[str, ...]]:
    """(module, path inside the module) — module '' is the package root module, None = outside any module."""
    dirs = f['rel'][:-1]
    if f['package'] == KERNEL:
        return (dirs[0], dirs[1:]) if dirs and dirs[0] in kernel_modules else (None, dirs)
    if not dirs or dirs[0] in CATEGORIES or dirs[0] in BRIDGES:
        return '', dirs
    return dirs[0], dirs[1:]


def evaluate(files: list[dict], config: dict) -> list[dict]:
    kernel_modules = set(config['kernel_modules'])
    buckets = set(config['layer_buckets'])
    out = []

    def add(rule, f, detail=''):
        out.append({'rule': rule, 'file': 'packages/' + f['dir'] + '/src/' + '/'.join(f['rel']), 'detail': detail})

    package_namespaces = {f['root_ns'] for f in files}
    owner = {}
    for f in files:
        f['module'], f['inner'] = module_of(f, kernel_modules)
        for decl in f['decls']:
            owner[f"{f['namespace']}\\{decl}".lower()] = f
    for f in files:
        dirs, name = f['rel'][:-1], f['rel'][-1]
        expected = '\\'.join((f['root_ns'],) + dirs)
        if f['namespace'] != expected:
            add('R0', f, f"{f['namespace']} != {expected}")
        kernel = f['package'] == KERNEL
        # A service provider may sit directly in the package root or in its own module; `<Pkg>Module.php` only in the root.
        root_entry = (name.endswith('ServiceProvider.php') and not f['inner'] and f['module'] is not None) or \
            (not dirs and (name == 'CoreServiceProvider.php' if kernel else
                          name.endswith(('ServiceProvider.php', 'Module.php'))))
        if kernel and not root_entry and f['module'] is None:
            add('R1', f, 'kernel root holds only CoreServiceProvider and kernel modules')
            continue
        if not kernel and dirs and dirs[0] in buckets:
            add('R1', f, f'layer bucket {dirs[0]}/ at package root')
        inner = f['inner']
        attribute_entry = (kernel and f['dir'] == 'core' and dirs == ('Attributes',)
                           and name in ('Api.php', 'Spi.php', 'SpiKind.php')
                           and f['namespace'] == 'CoreX\\Attributes'
                           and f['decls'] == [name[:-4]])
        if root_entry or attribute_entry:
            pass
        elif not inner:
            add('R3', f, 'file outside categories and bridges of its module')
        elif inner[0] not in CATEGORIES and inner[0] not in BRIDGES:
            add('R3', f, f'directory {inner[0]}/ is neither a category nor a bridge')
        if any(seg in CATEGORIES for seg in inner[1:]) and (not inner or inner[0] not in BRIDGES):
            add('R2', f, 'category not directly under the module')
        if 'Internal' in inner[1:]:
            add('R4', f, 'nested Internal')
        composition = root_entry or inner[:1] == ('Laravel',)
        for ref in sorted(f['refs']):
            if (not any(segment in DEV_BRIDGES for segment in dirs)
                    and any(ref == root_ns or ref.startswith(root_ns + '\\') for root_ns in package_namespaces)
                    and any(segment in DEV_BRIDGES for segment in ref.split('\\'))):
                add('R6', f, ref)
            target = owner.get(ref.lower())
            if (target is None or target is f or target['package'] != f['package'] or composition
                    or target['module'] == f['module'] or not target['inner'] or target['inner'][0] != 'Internal'):
                continue
            add('R5', f, ref)
    return sorted(out, key=lambda v: (v['rule'], v['file'], v['detail']))


def key(v: dict) -> str:
    return f"{v['rule']} {v['file']} {v['detail']}" if v['rule'] in ('R5', 'R6') else f"{v['rule']} {v['file']}"


def main(argv: list[str]) -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument('--root', default='.')
    ap.add_argument('--config', default='scripts/module-layout.json')
    ap.add_argument('--baseline', default='scripts/module-layout-baseline.json')
    ap.add_argument('--project')
    ap.add_argument('--report', action='store_true')
    ap.add_argument('--write-baseline', action='store_true')
    args = ap.parse_args(argv)
    root = Path(args.root)
    config = json.loads(Path(args.config).read_text(encoding='utf-8'))
    files = scan(root, load_packages(root))
    if args.project:
        project(files, Path(args.project))
    found = evaluate(files, config)
    counts = collections.Counter(v['rule'] for v in found)
    summary = ', '.join(f'{r} {counts.get(r, 0)}' for r in ('R0', 'R1', 'R2', 'R3', 'R4', 'R5', 'R6'))
    if args.report:
        for v in found:
            print(f"{v['rule']}\t{v['file']}\t{v['detail']}")
        print(f'module-layout: {len(files)} files; {summary}')
        return 0
    if args.write_baseline:
        entries = sorted({key(v) for v in found})
        Path(args.baseline).write_text(json.dumps({'schema': 'module-layout-baseline/1', 'entries': entries},
                                                  indent=1, ensure_ascii=False) + '\n', encoding='utf-8')
        print(f'module-layout: baseline written, {len(entries)} entries; {summary}')
        return 0
    baseline = set(json.loads(Path(args.baseline).read_text(encoding='utf-8'))['entries'])
    current = {key(v) for v in found}
    new, stale = sorted(current - baseline), sorted(baseline - current)
    for entry in new:
        print('new ' + entry)
    for entry in stale:
        print('stale ' + entry + ' (remove it from the baseline)')
    status = 'OK' if not new and not stale else 'FAIL'
    print(f'module-layout {status}: {len(files)} files, {len(current)} violations, {len(baseline)} baselined, '
          f'{len(new)} new, {len(stale)} stale; {summary}')
    return 0 if status == 'OK' else 1


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))
