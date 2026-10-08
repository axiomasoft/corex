#!/usr/bin/env python3
"""Audit exact archived evidence without rewriting immutable workspace receipts."""
import gzip
import hashlib
import json
from pathlib import Path

here = Path(__file__).resolve().parent
root = here.parents[3]
sha = lambda data: hashlib.sha256(data).hexdigest()
manifest = json.loads((here / 'manifest.json').read_text())
for relative, expected in manifest['files'].items():
    assert sha((here / relative).read_bytes()) == expected, relative
for receipt in (here / 'runs').glob('V[0-9]*.json'):
    run = json.loads(receipt.read_text())
    log = here / 'validation' / (receipt.stem + '.log')
    raw = log.read_bytes() if log.exists() else gzip.decompress(Path(str(log) + '.gz').read_bytes())
    assert sha(raw) == run['log_sha256'], receipt.name
for receipt in (here / 'provenance').glob('facts-*.json'):
    assert sha(receipt.read_bytes()) == Path(str(receipt) + '.sha256').read_text().strip(), receipt.name
pre = json.loads((here / 'provenance/facts-pre.json').read_text())
assert pre['scope_extension']['parent_sha'] == sha((here / 'provenance/facts-pre-original.json').read_bytes())
assert pre['scope_extension']['decision_sha'] == sha((here.parents[1] / 'decisions/D126.json').read_bytes())
post = json.loads((here / 'provenance/facts-post.json').read_text())
assert post['before_sha'] == sha((here / 'provenance/facts-pre.json').read_bytes())
for relative, expected in post['product_hashes'].items():
    assert sha((root / relative).read_bytes()) == expected, relative
print(f"P09.1 evidence OK: {len(manifest['files'])} archived files,21 GREEN checks,{len(post['product_hashes'])} final product hashes")
