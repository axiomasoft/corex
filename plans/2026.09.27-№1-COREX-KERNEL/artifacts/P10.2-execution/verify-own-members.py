#!/usr/bin/env python3
"""Compare committed own AST declarations and candidate projection, including signatures."""
import hashlib
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[4]
ARTIFACTS = Path(__file__).resolve().parent
BASE = '428f47dbbab1ea1663a673f0fa1d6c2709f8e148'

before = json.loads((ARTIFACTS / 'own-ast-before.json').read_text())
after = json.loads((ARTIFACTS / 'own-ast-after.json').read_text())
assert before == after, 'Own AST declarations or signatures changed'
counts, missing, signature_delta, omissions = {}, [], [], []
for manifest in sorted(ROOT.glob('packages/*/resources/contracts/php-api.json')):
    current = json.loads(manifest.read_text())
    previous = json.loads(subprocess.check_output(
        ['git', 'show', f'{BASE}:{manifest.relative_to(ROOT)}'], cwd=ROOT))
    package = current['package']
    declarations = before[package]
    assert set(declarations) == set(current['symbols']) == set(previous['symbols'])
    count = 0
    for symbol, categories in declarations.items():
        for category, members in categories.items() if isinstance(categories, dict) else []:
            for member in members:
                count += 1
                now = current['symbols'][symbol][category]
                old = previous['symbols'][symbol][category]
                if member not in now:
                    missing.append([symbol, category, member])
                elif member not in old:
                    omissions.append([symbol, category, member])
                elif old[member] != now[member]:
                    signature_delta.append([symbol, category, member])
    counts[package] = {'symbols': len(declarations), 'own_members': count}
assert len(omissions) == 15 and all(category == "properties" for _, category, _ in omissions), omissions
assert not missing and not signature_delta
result = {
    'schema': 'own-members-diff/v1', 'base': BASE,
    'source': 'own-ast.php over committed base and candidate sources; verify-own-members.py projects each AST own declaration into both manifests',
    'preimage_sha256': hashlib.sha256((ARTIFACTS / 'own-ast-before.json').read_bytes()).hexdigest(),
    'candidate_ast_sha256': hashlib.sha256((ARTIFACTS / 'own-ast-after.json').read_bytes()).hexdigest(),
    'packages': counts, 'ast_signatures_equal': True, 'missing_members': missing,
    'manifest_signature_delta': signature_delta, 'preexisting_omissions_repaired': omissions,
    'equal': True,
}
(ARTIFACTS / 'own-members-diff.json').write_text(json.dumps(result, indent=2) + '\n')
print(f'own-members: {len(counts)} packages, {sum(c["symbols"] for c in counts.values())} symbols, '
      f'{sum(c["own_members"] for c in counts.values())} members, no missing/signature delta')
