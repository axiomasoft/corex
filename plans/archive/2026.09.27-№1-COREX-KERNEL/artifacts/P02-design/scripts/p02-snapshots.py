#!/usr/bin/env python3
"""Admit regenerated name-dependent snapshot hashes only after shape projection."""
import hashlib, importlib.util, json, subprocess
from pathlib import Path
HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('transfer', HERE / 'p02-transfer.py')
t = importlib.util.module_from_spec(spec)
spec.loader.exec_module(t)
plan = json.loads(Path('build/p02/plan.json').read_text())
subprocess.run(['scripts/php84', 'php', str(HERE / 'p02-snapshots.php')], check=True)
names = ['errors.json', 'openapi.json', 'operations.json', 'manifest.json', 'webhooks.json']
def semantic(value):
    if isinstance(value, dict):
        return {k: semantic(v) for k, v in value.items() if k not in {'contract_hash', 'contract_fingerprint', 'registry_fingerprint', 'files'}}
    if isinstance(value, list):
        return [semantic(v) for v in value]
    return value
candidate = Path('build/p02/api-projection')
manifest = json.loads((candidate / 'manifest.json').read_text())
for name in names:
    raw = subprocess.check_output(['git', 'show', 'HEAD:api/v1/' + name], text=True)
    expected = json.loads(t.rewrite_text(raw, *t.literal_rules(plan)))
    actual = json.loads((candidate / name).read_text())
    if semantic(expected) != semantic(actual):
        raise SystemExit('snapshot semantic projection mismatch: ' + name)
    if name != 'manifest.json':
        assert manifest['files'][name] == hashlib.sha256((candidate / name).read_bytes()).hexdigest(), name
for name in names:
    Path('api/v1', name).write_bytes((candidate / name).read_bytes())
print('API snapshots: five shapes preserved, name-dependent hashes recomputed and verified')
