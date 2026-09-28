#!/usr/bin/env python3
"""Golden rebaseline scope check for P02 seam items (PLAN3.D40, D44).

Compares tests/Golden/baseline/*.json of <base> with the worktree:
  * routes / events / wire must be byte-identical (seams do not change behaviour or wire);
  * php-surface may only add, remove or change types declared in `packages/*/src` files that differ
    from <base> (the item's own diff), projected through tests/Golden/rename-map.json.
Prints `golden-delta OK: +A -R ~C (allowed N)` or one line per out-of-scope type; exit 1 on any.

Usage: python3 golden-delta.py [--base HEAD]
"""
import argparse, json, re, subprocess, sys

LAYERS = ('routes', 'events', 'wire')
NS = re.compile(r'^\s*namespace\s+([\w\\]+)\s*;', re.M)
DECL = re.compile(r'^\s*(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+(\w+)', re.M)


def show(ref, path):
    res = subprocess.run(['git', 'show', f'{ref}:{path}'], capture_output=True, text=True)
    return res.stdout if res.returncode == 0 else None


def changed_sources(base):
    out = subprocess.run(['git', 'diff', '--name-only', base, '--', 'packages/*/src'], capture_output=True,
                         text=True, check=True).stdout.split()
    out += subprocess.run(['git', 'ls-files', '--others', '--exclude-standard', 'packages/*/src'],
                          capture_output=True, text=True, check=True).stdout.split()
    return sorted(set(f for f in out if f.endswith('.php')))


def declared(text):
    ns = NS.search(text or '')
    return {f'{ns.group(1)}\\{c}' for c in DECL.findall(text)} if ns else set()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--base', default='HEAD')
    a = ap.parse_args()
    bad = []
    for layer in LAYERS:
        path = f'tests/Golden/baseline/{layer}.json'
        if show(a.base, path) != open(path, encoding='utf-8').read():
            bad.append(f'layer {layer} changed (seams must keep routes/events/wire)')
    rules = {r['from']: r['to'] for r in json.load(open('tests/Golden/rename-map.json'))['generated'] if r['kind'] == 'class'}
    allowed = set()
    for f in changed_sources(a.base):
        before = show(a.base, f) or ''
        try:
            after = open(f, encoding='utf-8').read()
        except FileNotFoundError:
            after = ''
        for fq in declared(before) | declared(after):
            allowed.add(rules.get(fq, fq))
    old = json.loads(show(a.base, 'tests/Golden/baseline/php-surface.json') or '{}')
    new = json.load(open('tests/Golden/baseline/php-surface.json', encoding='utf-8'))
    added = set(new) - set(old)
    removed = set(old) - set(new)
    changed = {k for k in set(old) & set(new) if old[k] != new[k]}
    for kind, keys in (('added', added), ('removed', removed), ('changed', changed)):
        for k in sorted(keys - allowed):
            bad.append(f'php-surface {kind} {k} outside the item diff')
    for line in bad:
        print(line)
    print(f'golden-delta {"FAIL" if bad else "OK"}: +{len(added)} -{len(removed)} ~{len(changed)} (allowed {len(allowed)})')
    return 1 if bad else 0


if __name__ == '__main__':
    sys.exit(main())
