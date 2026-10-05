#!/usr/bin/env python3
"""Compare two schema catalogue snapshots (tests/Golden/Support/SchemaCatalogue JSON).

Usage: schema-diff.py BEFORE.json AFTER.json
Prints one line per difference. Indexes/constraints are matched by (table, kind, columns, predicate/expression),
so a pure rename prints `rename <old> -> <new>` instead of drop/add. Exit 0 always; classification is the reader's
job (runbook research-P08-design §6.4 step 3).

Expected snapshot shape:
{"tables": {"<table>": {
   "columns": {"<col>": {"type": "...", "nullable": bool, "default": str|null}},
   "indexes": [{"name": "...", "kind": "pk|uq|ix", "columns": [...], "predicate": str|null}],
   "foreign_keys": [{"name": "...", "columns": [...], "references": "table(cols)", "on_delete": "..."}],
   "checks": [{"name": "...", "expression": "..."}]}}}
"""
import json, sys


def load(path):
    with open(path, encoding='utf-8') as fh:
        return json.load(fh).get('tables', {})


def keyed(objs, sig):
    out = {}
    for o in objs:
        out.setdefault(sig(o), []).append(o['name'])
    return out


SIGS = {
    'indexes': lambda o: (o['kind'], tuple(o['columns']), o.get('predicate')),
    'foreign_keys': lambda o: (tuple(o['columns']), o['references'], o.get('on_delete')),
    'checks': lambda o: (o['expression'],),
}


def main(a, b):
    before, after = load(a), load(b)
    for t in sorted(set(before) | set(after)):
        if t not in after:
            print(f'-table {t}')
            continue
        if t not in before:
            print(f'+table {t}')
            continue
        bc, ac = before[t]['columns'], after[t]['columns']
        for c in sorted(set(bc) | set(ac)):
            if c not in ac:
                print(f'{t}: -col {c}')
            elif c not in bc:
                print(f'{t}: +col {c} {ac[c]["type"]}')
            else:
                for f, tag in (('type', '~type'), ('nullable', '~null'), ('default', '~default')):
                    if bc[c].get(f) != ac[c].get(f):
                        print(f'{t}: {tag} {c} {bc[c].get(f)!r} -> {ac[c].get(f)!r}')
        for kind, sig in SIGS.items():
            kb, ka = keyed(before[t].get(kind, []), sig), keyed(after[t].get(kind, []), sig)
            for s in sorted(set(kb) | set(ka), key=repr):
                ob, oa = sorted(kb.get(s, [])), sorted(ka.get(s, []))
                if ob == oa:
                    continue
                if len(ob) == 1 and len(oa) == 1:
                    print(f'{t}: rename {kind} {ob[0]} -> {oa[0]}')
                else:
                    for n in ob:
                        if n not in oa:
                            print(f'{t}: -{kind} {n} {s}')
                    for n in oa:
                        if n not in ob:
                            print(f'{t}: +{kind} {n} {s}')


if __name__ == '__main__':
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    main(sys.argv[1], sys.argv[2])
