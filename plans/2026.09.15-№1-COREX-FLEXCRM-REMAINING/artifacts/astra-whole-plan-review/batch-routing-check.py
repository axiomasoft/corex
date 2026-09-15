#!/usr/bin/env python3
"""Read-only checks for the D18 routing change against the installed Task runtime."""
from pathlib import Path
import hashlib
import json
import sys

RUNTIME = Path('/home/vostrikov/projects/packages/swissknifeman/packages/task')
sys.path.insert(0, str(RUNTIME / 'lib'))
from task import task_contract

ARTIFACTS = Path(__file__).resolve().parent
PLAN = ARTIFACTS.parent.parent
before = json.loads((ARTIFACTS / 'batch-routing.before.json').read_text())
selected = json.loads((ARTIFACTS / 'batch-routing.selection.json').read_text())
current = task_contract.compile_batch_routing(PLAN)
assert len(current['items']) == 78 and len(current['batches']) == 35
for item, row in current['items'].items():
    old = before['compiled']['items'][item]
    assert all(row[field] == old[field] for field in ('route', 'exec', 'review')), item
for group in selected:
    key = f"solo:{group['items'][0]}" if group['batch'] == 'solo' else group['batch']
    assert list(current['batches'][key]['items']) == group['items']
    assert current['batches'][key]['recommended']

checks = []
for name, request, expected in (
    ('first-ready-item', 'P1.1', 'ALLOW'),
    ('future-producer-chain', 'P1.1-P1.4', 'BATCH_STALE'),
    ('cross-phase', ('P1.1', 'P2.1'), 'BATCH_AMBIGUOUS'),
    ('runner-private-authority', ('P9.5', 'P9.6'), 'BATCH_AMBIGUOUS'),
    ('packet-publication-exec-mix', ('P9.7', 'P9.8'), 'BATCH_AMBIGUOUS'),
):
    result = task_contract.admit_batch(PLAN, request)
    assert result['verdict'] == expected, (name, result)
    checks.append({'name': name, 'request': request, 'expected': expected,
                   'actual': result['verdict'], 'reason': result.get('reason')})
# In-memory synthetic continuation tests native stale-digest rejection; no persisted fake receipt.
batch = current['batches']['B-P1-QUALIFY']
receipt = task_contract.build_batch_receipt(batch, completed_prefix=('P1.1', 'P1.2', 'P1.3'))
receipt['routing_digest'] = '0' * 64
result = task_contract.admit_batch(PLAN, 'P1.4', receipt=receipt)
assert result['verdict'] == 'BATCH_STALE', result
checks.append({'name': 'synthetic-stale-continuation', 'expected': 'BATCH_STALE',
               'actual': result['verdict'], 'synthetic_only': True})

read_sets = before['static_read_sets']
def cost(units):
    return sum(sum(row['bytes'] for row in {path: data for item in items
               for path, data in read_sets[item].items()}.values()) for items in units)
old_bytes = cost([b['items'] for b in before['compiled']['batches'].values()])
new_bytes = cost([b['items'] for b in current['batches'].values()])
report = {'schema': 'batch-design-check/1', 'result': 'PASS', 'command': [sys.executable, '-B', str(Path(__file__).resolve())],
          'cwd': str(Path.cwd()), 'runtime': str(RUNTIME),
          'runtime_sources': {name: hashlib.sha256((RUNTIME / name).read_bytes()).hexdigest()
                              for name in ('runtime/plan-protocol/SKILL.md', 'lib/task/task_contract.py', 'lib/task/plan_lint_checks/engine.py')},
          'items': 78, 'before_units': len(before['compiled']['batches']), 'after_units': len(current['batches']),
          'groups': sum(len(b['items']) > 1 for b in current['batches'].values()),
          'solo': sum(len(b['items']) == 1 for b in current['batches'].values()),
          'item_routes_and_review_preserved': True, 'compiler': current, 'checks': checks,
          'read_cost': {'method': 'frozen existing whole-file read sets, unique resolved path per envelope; not provider tokens or execution estimate',
                        'before_bytes': old_bytes, 'after_bytes': new_bytes,
                        'saved_percent': round((old_bytes - new_bytes) * 100 / old_bytes, 2)}}
(ARTIFACTS / 'checks/batch-routing-check.json').write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
print(f'PASS: 78 unchanged item routes/reviews, 35 units (24 groups/11 solo), 6 admission checks; known read bytes {old_bytes} -> {new_bytes}')
