#!/usr/bin/env python3
"""Generate tests/Golden/rename-map.json from the P00 transfer maps (D38).

Rules are exact tokens, not prefixes: namespace-map.tsv is keyed by directory/file
(psr-4 folding), so the old->new relation is only a function per class. Class rules
come from check-maps.py `symbol_table()`; package rules only where the package map is
one old name -> one new name. One-to-many packages (split) are listed under
`ambiguous_packages`; the phase that splits them adds `manual` rules for each field.
`manual` rules already in the target file are preserved.

Usage (repository root): golden-rename-map.py [--check] [--target tests/Golden/rename-map.json]
Exit: 0 written/current, 1 stale (--check) or map errors.
"""
from __future__ import annotations

import argparse
import collections
import importlib.util
import json
import sys
from pathlib import Path

PLAN = Path('plans/2026.09.27-№1-COREX-KERNEL')
SCHEMA = 'corex-golden-rename-map/1'


def load_check_maps():
    spec = importlib.util.spec_from_file_location('check_maps', PLAN / 'artifacts/P00-design/scripts/check-maps.py')
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def build(manual: list) -> dict:
    cm = load_check_maps()
    maps = cm.Maps(str(PLAN / 'artifacts/P00'), str(PLAN / 'research/03-glossary.md'))
    if cm.errors:
        raise SystemExit('P00 maps invalid:\n' + '\n'.join(cm.errors))

    classes = {}
    for current, _file, final, _owner in cm.symbol_table(maps).values():
        if final != current and '\\Tests\\' not in current and not current.startswith('CoreX\\Tests\\'):
            classes[current] = final

    # The split is expressed per path in namespace-map.tsv, not in package-map.tsv.
    targets = collections.defaultdict(set)
    for row in maps.ns_rows:
        old, new = row['old_package'], row['new_package']
        if old not in ('', '—') and new not in ('', '—'):
            targets[old].add(new)
    packages = {old: next(iter(new)) for old, new in targets.items() if len(new) == 1 and old not in new}
    ambiguous = {old: sorted(new) for old, new in targets.items() if len(new) > 1}

    rules = [{'kind': 'class', 'from': k, 'to': v} for k, v in sorted(classes.items())]
    rules += [{'kind': 'package', 'from': k, 'to': v} for k, v in sorted(packages.items())]
    return {'schema': SCHEMA, 'generated': rules, 'ambiguous_packages': ambiguous, 'manual': manual}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('--check', action='store_true')
    parser.add_argument('--target', type=Path, default=Path('tests/Golden/rename-map.json'))
    args = parser.parse_args()

    current = json.loads(args.target.read_text(encoding='utf-8')) if args.target.is_file() else {}
    document = build(current.get('manual', []))
    text = json.dumps(document, indent=2, ensure_ascii=False, sort_keys=True) + '\n'
    summary = (f"{sum(r['kind'] == 'class' for r in document['generated'])} class, "
               f"{sum(r['kind'] == 'package' for r in document['generated'])} package, "
               f"{len(document['ambiguous_packages'])} ambiguous, {len(document['manual'])} manual")

    if args.check:
        if not args.target.is_file() or args.target.read_text(encoding='utf-8') != text:
            print(f'rename-map stale: {args.target}', file=sys.stderr)
            return 1
        print(f'rename-map OK: {summary}')
        return 0
    args.target.parent.mkdir(parents=True, exist_ok=True)
    args.target.write_text(text, encoding='utf-8')
    print(f'rename-map written: {summary}')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
