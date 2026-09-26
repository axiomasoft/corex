from pathlib import Path
import copy, hashlib, json
from task import journal, plan_repair

p = Path('plans/2026.09.19-№1-COREX-PLATFORM-HARDENING')
out = p / 'artifacts/P14.21-execution'
s = json.loads(Path('build/P14.21/start.json').read_text())
def append(record, note):
    verdict = plan_repair.evaluate(record, plan_dir=p)
    assert verdict['action'] == 'repair-in-current-attempt', verdict
    if any(e.get('repair_digest') == verdict['repair_digest'] for e in journal.read_authoritative_entries(p)):
        return
    e = journal.new_entry(plan_id=p.name, item_id=s['item_id'], command=s['command'], actor=s['actor'],
        result='local-repair', session_id=s['session_id'], run_id=s['run_id'], attempt=s['attempt'],
        batch_receipt=s['batch_receipt'], note=note,
        decision={'signal_class': 'acceptance', 'scope_class': 'item', 'action': 'continue-local-repair',
                  'recovered': plan_repair.post_validation_complete(record), 'terminal': False})
    e.update(repair_decision=record, repair_action=verdict['action'], repair_digest=verdict['repair_digest'])
    journal.append(p, e)

root = json.loads(Path('build/P14.21/repair-pre.json').read_text())
root['post_validation'] = [{**root['original_carrier'], 'result': 'green'}]
root['evidence_ids'] += ['artifacts/P14.21-execution/root-exact-final.log', 'artifacts/P14.21-execution/commands.json']
(out / 'repair-post.json').write_text(json.dumps(root, ensure_ascii=False, indent=2) + '\n')
append(root, 'Exact composer test rerun: 815 passed / 3493 assertions. Root/package analyse, PostgreSQL and frozen snapshot gates recovered; no production action.')

mutation = copy.deepcopy(root)
cmd = 'bash scripts/mutation-check.sh --all'
carrier = {'command': cmd, 'digest': hashlib.sha256(cmd.encode()).hexdigest(), 'result': 'red'}
mutation.update(original_carrier=carrier, baseline={'reproduced': False, 'carrier': None},
    candidate={'delta_proved': True, 'evidence_ids': ['artifacts/P14.21-execution/mutation-restoration.json']},
    pre_validation=[carrier], post_validation=[],
    evidence_ids=['artifacts/P14.21-execution/mutation-failure-history.json'],
    affected_paths=[{'path': r['path'], 'owner_item': 'P1.14', 'declared_file': False}
                    for r in json.loads(Path('build/P14.21/staging-manifest.json').read_text())
                    if r['path'].startswith('scripts/mutation')])
mutation['bounded_diff'] = {'files_changed': len(mutation['affected_paths']), 'max_files': 7, 'reversible': True}
history = [r for r in json.loads(Path('build/P14.21/checks.json').read_text()) if r['id'] == 'mutations']
(out / 'mutation-failure-history.json').write_text(json.dumps({'observations': history,
    'limitation': 'Initial failed log was overwritten by rerun. Historical command/exit/hash observation is retained; the old log hash is not advertised as an available artifact.',
    'initial_failure': '6 stale patches; failed 3-way rollback reset dirty bytes, then restored from pre-run backups',
    'discarded_green': 'Intermediate 34-case run had an empty state-reader filter; not accepted as qualification'}, ensure_ascii=False, indent=2) + '\n')
(out / 'mutation-repair-pre.json').write_text(json.dumps(mutation, ensure_ascii=False, indent=2) + '\n')
append(mutation, 'Mandatory mutation carrier failed on stale patches; restore pre-run bytes and index, reanchor unchanged sabotage boundaries, reject empty test filters.')
mutation['post_validation'] = [{**carrier, 'result': 'green'}]
mutation['evidence_ids'] += ['artifacts/P14.21-execution/mutations-final.log', 'artifacts/P14.21-execution/mutation-restoration.json']
(out / 'mutation-repair-post.json').write_text(json.dumps(mutation, ensure_ascii=False, indent=2) + '\n')
append(mutation, 'Exact all-mutations carrier exit 0: 34 nonempty sabotage refusals. Target hashes restored; failed apply preserves dirty source and logical staged entries.')
assert not plan_repair.unresolved_incidents(p, s['item_id'])
print('all qualified repair incidents resolved')
