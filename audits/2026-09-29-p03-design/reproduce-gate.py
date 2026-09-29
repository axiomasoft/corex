#!/usr/bin/env python3
"""Reproduce P03 A3-A5 using the design's real gate and its temporary fixtures.

Run from the repository root. Writes no product or plan files.
"""
import importlib.util
import json
from pathlib import Path
import tempfile

PLAN = Path('plans/2026.09.27-№1-COREX-KERNEL')
path = PLAN / 'artifacts/P03-design/scripts/test_module_layout_gate.py'
spec = importlib.util.spec_from_file_location('p03_test_gate', path)
fixture = importlib.util.module_from_spec(spec)
spec.loader.exec_module(fixture)

CASES = {
    'full-import-control': ('search/src/Data/Consumer.php',
        'namespace CoreX\\Search\\Data; use CoreX\\Search\\Index\\Internal\\Driver; '
        'final class Consumer { public function run(): object { return new Driver(); }}', ['R5']),
    'namespace-alias': ('search/src/Data/Consumer.php',
        'namespace CoreX\\Search\\Data; use CoreX\\Search\\Index; '
        'final class Consumer { public function run(): object { return new Index\\Internal\\Driver(); }}', []),
    'laravel-nested-in-internal': ('search/src/Index/Internal/Laravel/Uses.php',
        'namespace CoreX\\Search\\Index\\Internal\\Laravel; '
        'use CoreX\\Search\\Internal\\Hydrator; final class Uses {}', []),
    'unknown-kernel-root-provider': ('core/src/ExtraServiceProvider.php',
        'namespace CoreX; final class ExtraServiceProvider {}', []),
}

results = []
for name, (relative, body, expected) in CASES.items():
    with tempfile.TemporaryDirectory() as temporary:
        root = fixture.build(Path(temporary), {relative: body})
        found = fixture.GATE.evaluate(fixture.GATE.scan(root, fixture.GATE.load_packages(root)), fixture.CONFIG)
        assert [entry['rule'] for entry in found] == expected, (name, found)
        results.append({'case': name, 'source': body, 'violations': found})
print(json.dumps(results, indent=2))
