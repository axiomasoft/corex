#!/usr/bin/env python3
"""Match a finite, decision-bound unpublished API delta; unknown breaks fail closed."""
import argparse
import hashlib
import json
from pathlib import Path
import sys


def admitted(root, package, base, errors):
    carrier = json.loads((root / 'scripts/bc/approved-deltas.json').read_text())
    if carrier.get('schema') != 'corex-bc-approved-deltas/1':
        return False
    rule = carrier.get('packages', {}).get(package)
    if not isinstance(rule, dict) or rule.get('base_revision') != base:
        return False
    decisions = rule.get('decisions')
    if not isinstance(decisions, list) or not decisions:
        return False
    for decision in decisions:
        path = (root / decision['path']).resolve()
        if not path.is_relative_to(root.resolve()) or not path.is_file():
            return False
        if hashlib.sha256(path.read_bytes()).hexdigest() != decision['sha256']:
            return False
        source = path.read_text()
        if '\nstatus: accepted\n' not in source:
            return False
    def signature(row):
        return (hashlib.sha256(row['description'].encode()).hexdigest(), row['path'], row['modificationType'])
    actual = {signature(row) for row in errors}
    approved = {(row['description_sha256'], row['path'], row['modificationType']) for row in rule.get('changes', [])}
    return bool(actual) and actual == approved


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root', type=Path, required=True)
    parser.add_argument('--package', required=True)
    parser.add_argument('--base', required=True)
    args = parser.parse_args()
    try:
        reports = [json.loads(line)['errors'] for line in sys.stdin if line.startswith('{"errors":')]
        return 0 if len(reports) == 1 and admitted(args.root, args.package, args.base, reports[0]) else 1
    except (OSError, ValueError, KeyError, TypeError):
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
