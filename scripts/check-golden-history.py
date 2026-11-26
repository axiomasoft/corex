#!/usr/bin/env python3
"""Require a new, durable rebaseline receipt for each snapshot-changing commit."""
import re
import subprocess
import sys
from datetime import date
from functools import cache
from pathlib import Path

LEDGER = 'tests/Golden/REBASELINE.md'
SNAPSHOTS = ('tests/Golden/baseline', 'tests/Golden/schema', 'tests/Golden/package-projection.json',
             'tests/Golden/promoted-fixtures.json', 'tests/Golden/rename-map.json')
ROW = re.compile(r'^\|\s*(\d{4}-\d{2}-\d{2})\s*\|([^|]*)\|([^|]*)\|(.+)\|$')
FENCE = re.compile(r'^ {0,3}(`{3,}|~{3,})(.*)$')
TARGET = re.compile(r'\(commit ([a-f0-9]{40})\)$')


def git(*args):
    return subprocess.check_output(['git', *args], text=True)


@cache
def rows(revision):
    if revision is None:
        content = Path(LEDGER).read_text() if Path(LEDGER).is_file() else ''
    elif git('ls-tree', '--name-only', revision, '--', LEDGER).strip():
        content = git('show', f'{revision}:{LEDGER}')
    else:
        content = ''
    entries = set()
    fence = ''
    for line in re.sub(r'<!--.*?(?:-->|\Z)', '', content, flags=re.DOTALL).splitlines():
        marker = FENCE.match(line)
        if marker:
            if not fence:
                fence = marker[1]
            elif marker[1].startswith(fence) and not marker[2].strip():
                fence = ''
            continue
        match = ROW.fullmatch(line)
        if not fence and match:
            fields = tuple(field.strip() for field in match.groups())
            if all(fields):
                date.fromisoformat(fields[0])
                entries.add(fields)
    return entries


def target(row, own):
    decision = row[1]
    reference = TARGET.search(decision)
    # The decision column owns the reference. Digests in the rationale are evidence.
    hashes = re.findall(r'[a-fA-F0-9]{40}', decision)
    if reference and hashes == [reference[1]]:
        return reference[1]
    if '(commit' in decision.lower() or hashes:
        raise ValueError(f'Expected exactly one (commit <full SHA>) in the decision column: {decision}')
    return own


def check(base):
    end = rows(None)
    seen = set(rows(base)) if base else set()
    required = set()
    covered = set()
    commits = git('rev-list', '--full-history', '--topo-order', '--reverse',
                  f'{base}..HEAD' if base else 'HEAD', '--', LEDGER, *SNAPSHOTS).splitlines()
    for commit in [*commits, None]:
        if commit is None:
            previous = rows('HEAD')
            changed = git('status', '--porcelain', '--', *SNAPSHOTS)
        else:
            parents = git('show', '-s', '--format=%P', commit).split()
            previous = set().union(*(rows(parent) for parent in parents))
            # A clean merge inherits receipts from its parents. A manual resolution
            # that changes a snapshot introduces an independently reviewable change.
            changed = git('show', '--format=', '--remerge-diff', '--name-only', commit, '--', *SNAPSHOTS)
        if changed.strip():
            required.add(commit)
        current = rows(commit)
        added = current - previous - seen
        # Editing a row never creates evidence. A separate documentation change
        # can translate earlier entries without authorizing a new snapshot.
        if previous <= current:
            for row in added & end:
                authorized = target(row, commit)
                if authorized == commit:
                    if changed.strip():
                        covered.add(commit)
                elif authorized in required and (commit is None or subprocess.run(
                    ['git', 'merge-base', '--is-ancestor', authorized, commit], check=False
                ).returncode == 0):
                    covered.add(authorized)
        seen.update(current)
    missing = sorted(commit or 'worktree' for commit in required - covered)
    if missing:
        raise ValueError('Missing a new REBASELINE.md row for ' + ', '.join(missing))


if __name__ == '__main__':
    try:
        check(sys.argv[1])
    except ValueError as error:
        sys.exit(str(error))
