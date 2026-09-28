#!/usr/bin/env python3
"""Summarise JUnit XML files produced by `pest --log-junit` (P01 baseline metrics).

Usage: junit-summary.py <lane>=<file.xml> [...] [--compare <baseline.json>]
Prints JSON {lane: {tests, passed, failures, errors, skipped}}. With --compare, exit 1
when any lane present in the baseline lost tests (tests or passed decreased) or gained
failures/errors; the diff is printed to stderr.
"""
from __future__ import annotations

import json
import sys
import xml.etree.ElementTree as ET


def summarise(path: str) -> dict[str, int]:
    root = ET.parse(path).getroot()
    suites = [root] if root.tag == 'testsuite' else list(root.findall('testsuite'))
    total = {'tests': 0, 'failures': 0, 'errors': 0, 'skipped': 0}
    for suite in suites:
        for key in total:
            total[key] += int(float(suite.get(key, '0')))
    total['passed'] = total['tests'] - total['failures'] - total['errors'] - total['skipped']
    return total


def main(argv: list[str]) -> int:
    compare = None
    if '--compare' in argv:
        index = argv.index('--compare')
        compare = argv[index + 1]
        argv = argv[:index] + argv[index + 2:]
    result = {}
    for spec in argv:
        lane, _, path = spec.partition('=')
        if not path:
            print(f'bad argument {spec!r}, expected <lane>=<file.xml>', file=sys.stderr)
            return 2
        result[lane] = summarise(path)
    print(json.dumps(result, indent=2, sort_keys=True))
    if compare is None:
        return 0
    with open(compare, encoding='utf-8') as handle:
        baseline = json.load(handle)
    lanes = baseline.get('lanes', baseline)
    regressions = []
    for lane, before in lanes.items():
        after = result.get(lane)
        if after is None:
            regressions.append(f'{lane}: lane missing')
            continue
        for key in ('tests', 'passed'):
            if after[key] < before[key]:
                regressions.append(f'{lane}: {key} {before[key]} -> {after[key]}')
        for key in ('failures', 'errors'):
            if after[key] > before[key]:
                regressions.append(f'{lane}: {key} {before[key]} -> {after[key]}')
    for line in regressions:
        print(line, file=sys.stderr)
    return 1 if regressions else 0


if __name__ == '__main__':
    raise SystemExit(main(sys.argv[1:]))
