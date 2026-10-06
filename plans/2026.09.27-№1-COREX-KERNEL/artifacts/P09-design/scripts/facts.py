#!/usr/bin/env python3
"""Read-only inventory for rechecking the P09 design against the checkout."""
import hashlib
import json
import re
import subprocess
from pathlib import Path

root = Path(__file__).resolve().parents[5]
rows = []
for file in sorted([*root.glob('packages/*/composer.json'), *root.glob('products/*/composer.json')]):
    manifest = json.loads(file.read_text())
    baseline = file.parent / 'phpstan-baseline.neon'
    config = file.parent / 'phpstan.neon'
    baseline_text = baseline.read_text() if baseline.exists() else ''
    counts = [int(x) for x in re.findall(r'^\s+count: (\d+)$', baseline_text, re.M)]
    rows.append({'path': str(file.parent.relative_to(root)), 'name': manifest['name'],
                 'baseline_count': sum(counts), 'baseline_entries': len(counts),
                 'phpstan_max': config.exists() and 'level: max' in config.read_text(),
                 'require': manifest.get('require', {}), 'type': manifest.get('type'),
                 'stability': manifest.get('extra', {}).get('corex', {}).get('stability'),
                 'coverage_command': manifest.get('scripts', {}).get('test:coverage')})
anchors = ['composer.json', 'composer.lock', 'phpstan.neon', 'phpstan-extensions.neon',
           'phpstan-baseline.neon', 'rector.php', 'pint.json', 'scripts/quality-ceilings.json',
           'scripts/phpstan-packages.sh', 'scripts/quality-ceiling-guard.php',
           'scripts/distribution/release_candidate.py', 'scripts/distribution/release-1.0-policy.json',
           'scripts/distribution/install-profiles.json', 'scripts/distribution/export-policy.json',
           'scripts/distribution/profile-proof.py', 'scripts/bc-check.sh',
           'scripts/bc/accepted-base.json', '.github/workflows/ci.yml',
           'scripts/schema-sets.json', 'scripts/schema-gate.php', 'scripts/dod-1.0.json']
root_counts = [int(x) for x in re.findall(r'^\s+count: (\d+)$', (root/'phpstan-baseline.neon').read_text(), re.M)]
result = {'source_commit': subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=root, text=True).strip(),
          'packages': rows, 'root_baseline_count': sum(root_counts),
          'baseline_total': sum(x['baseline_count'] for x in rows)+sum(root_counts),
          'anchors': {p: hashlib.sha256((root/p).read_bytes()).hexdigest() for p in anchors},
          'tests_run': False}
print(json.dumps(result, ensure_ascii=False, indent=2, sort_keys=True))
