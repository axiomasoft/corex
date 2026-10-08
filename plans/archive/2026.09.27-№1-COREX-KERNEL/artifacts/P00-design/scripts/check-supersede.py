#!/usr/bin/env python3
"""Snapshot/check the three P00.3 edits; unrelated PLAN2 lint is diagnostic only."""
import argparse
import hashlib
import json
from pathlib import Path
import re
import sys

PLAN2 = '2026.09.19-№1-COREX-PLATFORM-HARDENING'
PLAN3 = '2026.09.27-№1-COREX-KERNEL'
PATHS = ('plans/ACTIVE.md', f'plans/{PLAN2}/plan.md', f'plans/{PLAN2}/handoff.md')


def normalize(text, kind):
    patterns = {
        'active': r'^\*\*(?:Active|Updated|Paused \(superseded\)):\*\*.*$',
        'plan': r'^\| Paused By \|.*$',
        'handoff': r'^\*\*Next:\*\*.*$',
    }
    # Ignore blank lines only in ACTIVE, where the inserted paused row needs spacing.
    lines = [line for line in text.splitlines() if not re.match(patterns[kind], line)]
    if kind == 'active':
        lines = [line for line in lines if line.strip()]
    return '\n'.join(lines)


def snapshot(root):
    plan2 = root / 'plans' / PLAN2
    protected = [plan2 / name for name in ('journal.jsonl', 'status.md', 'open-questions.md')]
    protected += list((plan2 / 'decisions').glob('*.md'))
    protected += list((plan2 / 'phases').rglob('*.state.md'))
    protected += list((plan2 / 'phases').rglob('*.closed.md'))
    return {
        'schema': 'plan3-supersede-baseline/v1',
        'files': {name: (root / name).read_text() for name in PATHS},
        'protected': {str(path.relative_to(root)): hashlib.sha256(path.read_bytes()).hexdigest()
                      for path in sorted(protected) if path.is_file()},
    }


def check(before, after):
    if before.get('schema') != 'plan3-supersede-baseline/v1':
        raise ValueError('invalid baseline schema')
    if before['protected'] != after['protected']:
        raise ValueError('protected PLAN2 carrier changed; reconcile concurrent work before retry')
    for name, kind in zip(PATHS, ('active', 'plan', 'handoff')):
        if normalize(before['files'][name], kind) != normalize(after['files'][name], kind):
            raise ValueError(f'out-of-scope edit: {name}')
    active, plan, handoff = (after['files'][name] for name in PATHS)
    patterns = [
        (active, rf'^\*\*Active:\*\* \[PLAN3[^\n]*\]\({re.escape(PLAN3)}/plan\.md\)$'),
        (active, rf'^\*\*Paused \(superseded\):\*\* \[PLAN2[^\n]*\]\({re.escape(PLAN2)}/plan\.md\).*PLAN3\.D23'),
        (active, r'^\*\*Updated:\*\* \d{4}-\d{2}-\d{2}$'),
        (plan, rf'^\| Paused By \| PLAN3 .*{re.escape(PLAN3)}.*PLAN3\.D23.*\|$'),
        (handoff, r'^\*\*Next:\*\* waiting: PLAN2 superseded by PLAN3.*PLAN3\.D23.*condition=.*target=manual:'),
    ]
    for text, pattern in patterns:
        if len(re.findall(pattern, text, re.M)) != 1:
            raise ValueError(f'missing/ambiguous semantic field: {pattern}')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('mode', choices=('snapshot', 'check'))
    parser.add_argument('--baseline', type=Path, required=True)
    parser.add_argument('--root', type=Path, default=Path.cwd())
    args = parser.parse_args()
    current = snapshot(args.root)
    if args.mode == 'snapshot':
        # Never overwrite the execution preimage on a resumed check.
        args.baseline.parent.mkdir(parents=True, exist_ok=True)
        with args.baseline.open('x') as stream:
            json.dump(current, stream, ensure_ascii=False, indent=2)
            stream.write('\n')
        print('supersede baseline OK')
    else:
        check(json.loads(args.baseline.read_text()), current)
        print('supersede OK: 3 files, protected carriers unchanged')


if __name__ == '__main__':
    try:
        main()
    except (OSError, ValueError, KeyError) as exc:
        print(f'supersede ERROR: {exc}', file=sys.stderr)
        sys.exit(1)
