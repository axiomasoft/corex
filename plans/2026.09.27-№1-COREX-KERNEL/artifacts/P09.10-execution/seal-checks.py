"""Project completed raw receipts into Task check-evidence carriers."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json

ROOT = Path(__file__).resolve().parents[4]
OUT = Path(__file__).resolve().parent
PLAN = OUT.parents[1]
summary = json.loads((OUT / 'summary.json').read_text())
item = json.loads((PLAN / 'phases/P09/P09.10.json').read_text())
checks = OUT / 'checks'
checks.mkdir(exist_ok=True)

for validation in item['validation']:
    key = validation['id']
    receipt_path = OUT / (key + '-receipt.json')
    receipt = json.loads(receipt_path.read_text())
    assert receipt['command'] == validation['cmd'] and receipt['exit_code'] == 0, key
    log = OUT / receipt['log']
    digest = hashlib.sha256(log.read_bytes()).hexdigest()
    assert summary['checks'][key]['log_sha256'] == digest, key
    finished = receipt_path.stat().st_mtime
    started_at = receipt.get('started_at') or datetime.fromtimestamp(
        finished - receipt['duration_seconds'], timezone.utc).isoformat()
    finished_at = receipt.get('finished_at') or datetime.fromtimestamp(finished, timezone.utc).isoformat()
    evidence = {
        '$schema': 'urn:swissknifeman:task:plan:check-evidence:v1',
        'evidence_id': 'PLAN3.P09.10.' + key,
        'check_id': key, 'subject': 'PLAN3.P09.10',
        'procedure': {'cmd': receipt['command'], 'cwd': str(ROOT)},
        'candidate': {'head': summary['source_sha']},
        'started_at': started_at, 'finished_at': finished_at,
        'producer': {'kind': 'executor', 'actor': item['result']['executor']},
        'environment': {'platform': summary['environment']},
        'log': {'path': str(log.relative_to(ROOT)), 'sha256': digest},
        'exit': receipt['exit_code'],
    }
    (checks / (key + '.json')).write_text(json.dumps(evidence, indent=2) + '\n')

(OUT / 'evidence-timestamps.json').write_text(json.dumps({
    'method': 'Direct UTC timestamps when recorded by runner; otherwise receipt modification time minus measured monotonic duration reconstructs approximate command start/finish.',
    'product_binding': 'summary.json product_sha256 binds the dirty product on source_sha; no new candidate commit or hosted proof is invented.',
}, indent=2) + '\n')
print('Sealed ' + str(len(item['validation'])) + ' completed Task check-evidence carriers.')
