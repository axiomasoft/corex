#!/usr/bin/env python3
"""P02.4 identifier renames by facts/identifier-map.tsv (stdlib; run from the repository root, after P02.3).

  apply   git mv renamed config files, then rewrite exact tokens in tracked text (history excluded)
  check   list remaining occurrences of old identifiers; exit 1 if any

Rows whose `new` is `-` or whose note starts with `section:` are manual steps (runbook §4.4 steps 3–4);
`apply` prints them and does not touch them.
"""
import os, re, subprocess, sys

PLAN = 'plans/2026.09.27-№1-COREX-KERNEL'
MAP = f'{PLAN}/artifacts/P02-design/facts/identifier-map.tsv'
SKIP = re.compile(r'^(plans/|audits/|vendor/|build/|tests/Golden/rename-map\.json$|scripts/rename-completeness\.json$)'
                  r'|(^|/)CHANGELOG\.md$|/docs/history/|^docs/architecture/rename-registry\.md$')
BEFORE, AFTER = r'(?<![\w./@:-])', r'(?![\w-])'


def rows():
    out = []
    for line in open(MAP, encoding='utf-8').read().splitlines()[1:]:
        kind, old, new, pkg, note = line.split('\t')
        out.append({'kind': kind, 'old': old, 'new': new, 'package': pkg, 'note': note})
    return out


def automatic(r):
    return r['new'] != '-' and not r['note'].startswith('section:')


def identifier_pattern(table):
    """Match complete config basenames even after a slash, plus ordinary identifiers."""
    identifiers = '|'.join(re.escape(r['old']) for r in sorted(table, key=lambda r: -len(r['old'])))
    files = '|'.join(re.escape(r['old'] + '.php') for r in table if r['kind'] == 'config')
    return re.compile(r'(?<![\w.-])(?P<file>' + files + r')(?![\w.-])|'
                      + BEFORE + '(?P<id>' + identifiers + ')' + AFTER)


def text_files():
    for f in subprocess.run(['git', 'ls-files'], capture_output=True, text=True, check=True).stdout.splitlines():
        if SKIP.search(f) or not os.path.isfile(f):
            continue
        try:
            yield f, open(f, encoding='utf-8').read()
        except UnicodeDecodeError:
            continue


def package_dir(name):
    for manifest in subprocess.run(['git', 'ls-files', 'packages/*/composer.json'], capture_output=True, text=True,
                                   check=True).stdout.split():
        if f'"name": "{name}"' in open(manifest, encoding='utf-8').read():
            return os.path.dirname(manifest)
    sys.exit(f'p02-identifiers: no package {name}')


def apply():
    table = rows()
    for r in table:
        m = re.match(r'file (config/\S+\.php)', r['note'])
        if r['kind'] == 'config' and m:
            src = f"{package_dir(r['package'])}/config/{r['old']}.php"
            subprocess.run(['git', 'mv', src, f"{package_dir(r['package'])}/{m.group(1)}"], check=True)
    automatic_rows = [r for r in table if automatic(r)]
    rules = {r['old']: r['new'] for r in automatic_rows}
    files = {r['old'] + '.php': r['new'] + '.php' for r in automatic_rows if r['kind'] == 'config'}
    rx = identifier_pattern(automatic_rows)
    changed = 0
    for f, text in text_files():
        new = rx.sub(lambda mo: files[mo['file']] if mo['file'] else rules[mo['id']], text)
        if new != text:
            open(f, 'w', encoding='utf-8').write(new)
            changed += 1
    for r in table:
        if not automatic(r):
            print(f"manual: {r['kind']} {r['old']} -> {r['new']} ({r['note']})")
    print(f'identifiers: {len(rules)} rules, {changed} files')


def check():
    table = rows()
    rx = identifier_pattern(table)
    hits = 0
    for f, text in text_files():
        for i, line in enumerate(text.splitlines(), 1):
            for mo in rx.finditer(line):
                print(f'{f}:{i} {mo["file"] or mo["id"]}')
                hits += 1
    print(f'identifiers check: {hits} remaining')
    return 1 if hits else 0


if __name__ == '__main__':
    cmd = sys.argv[1] if len(sys.argv) > 1 else ''
    if cmd == 'apply':
        apply()
    elif cmd == 'check':
        sys.exit(check())
    else:
        sys.exit(__doc__)
