"""Verify sealed raw design facts without copying them into execution context."""
from pathlib import Path
import hashlib
import json
base = Path(__file__).resolve().parent.parent
manifest = json.loads((base / 'input-manifest.json').read_text())
assert manifest['schema'] == 'p04-design-inputs/1'
for relative, digest in manifest['files'].items():
    assert hashlib.sha256((base / relative).read_bytes()).hexdigest() == digest, relative
print('P04 design inputs OK: ' + str(len(manifest['files'])) + ' sealed files')
