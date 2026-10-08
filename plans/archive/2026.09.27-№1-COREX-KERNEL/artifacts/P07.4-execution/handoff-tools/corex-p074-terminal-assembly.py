#!/usr/bin/env python3
"""P07.4 plumbing: resolve repair before review; prepare terminal JSON after GREEN.

This script never calls plan_work.complete and never appends a terminal event.
--resolve-repair freezes composite validation proof and appends a qualified
post-repair journal observation.
--terminal writes only .plan-cache/P07.4-terminal-entry.json.
"""
from __future__ import annotations

import argparse
import copy
import hashlib
import json
from pathlib import Path
import re
import subprocess
import sys

RUNTIME = Path('/home/vostrikov/projects/packages/swissknifeman/packages/task/lib')
sys.path.insert(0, str(RUNTIME))
from task import journal, plan_layout, plan_repair, review_packet, task_contract, terminal_policy

ROOT = Path('/home/vostrikov/projects/packages/corex')
PLAN = ROOT / 'plans/2026.09.27-№1-COREX-KERNEL'
ITEM = 'P07.4'
CANDIDATE = '9e56afe69ef2525f7ec3a68ca3b252e6b9bf1078'
SPEC_SHA256 = '7708656ad5052b4bcabc5f2d08ac58f56d0a90118145a071cfff7b882de5a346'
FINAL_IDS = (
    'candidate-anchor', 'dependency-lock', 'api-write-1', 'api-write-2',
    'contract-head', 'contract-base', 'access-azguard-scan', 'access-closure',
    'access-tests', 'pg-preflight', 'access-pg', 'bridge-tests', 'graph',
    'manifests', 'ownership', 'tables', 'naming', 'package-tests', 'root-pg',
    'api', 'bc', 'golden-delta', 'golden', 'lint', 'analyse', 'dependencies',
    'anti-weakening', 'docs', 'mutation',
)
DEFAULT_RECEIPT = PLAN / ('artifacts/P07.4-execution/canonical-validation/'
    '0981013bf7583b33266b22b601bb1f7cd11cf7f39ff4469d5c7098eb4bae7da7/'
    'final-9e56afe6-r1/receipt.json')


def require(condition, message):
    if not condition:
        raise ValueError(message)


def local_file(value):
    path = Path(value)
    if not path.is_absolute():
        path = ROOT / path
    path = path.resolve()
    require(path.is_relative_to(ROOT) and path.is_file(), f'evidence must exist inside repository: {path}')
    return path


def evidence_id(value):
    return local_file(value).relative_to(ROOT).as_posix()


def load(value):
    return json.loads(local_file(value).read_text(encoding='utf-8'))


def start_identity():
    rows = journal.read_authoritative_entries(PLAN)
    starts = [row for row in rows if row.get('item_id') == ITEM
        and row.get('command') == 'plan-run' and row.get('result') == 'in-progress'
        and row.get('decision', {}).get('action') == 'route-bound']
    require(bool(starts), 'No durable route-bound start for P07.4')
    start = starts[-1]
    require(not any(row.get('item_id') == ITEM and row.get('run_id') == start['run_id']
        and row.get('result') in ('closed-green', 'closed-deviations', 'blocked', 'escalated')
        for row in rows), 'Run already has a terminal event')
    route = start['decision']['route_receipt']
    for key in ('plan_id', 'item_id', 'session_id', 'run_id', 'attempt'):
        require(route[key] == start[key], f'Start route identity mismatch: {key}')
    require(bool(start['run_id']) and bool(start['session_id']), 'Start has no durable identities')
    task_contract.validate_batch_receipt(start['batch_receipt'])
    require(start['batch_receipt']['membership'] == ['P07.4', 'P07.5'], 'Original batch membership changed')
    return start, rows


def final_receipt(args, required_ids):
    sources = [local_file(args.receipt)] + [local_file(value) for value in args.extra_receipt]
    require(len(sources) == len(set(sources)), 'Duplicate composite receipt path')
    base = load(sources[0])
    require(base['attempt_id'] == 'final-9e56afe6-r1', 'Not the final candidate validation attempt')
    require([row['id'] for row in base['checks']] == list(FINAL_IDS), 'Original final 29 checks differ')
    selected, receipts = {}, []
    for path in sources:
        receipt = load(path)
        require(receipt['schema_version'] == 'validation-attempt/v1', 'Wrong canonical receipt schema')
        checks = receipt['checks']
        names = [row['id'] for row in checks]
        require(bool(names) and len(names) == len(set(names)) and set(names) <= set(FINAL_IDS) | {'redis-runtime'},
            'Composite receipt contains duplicate or undeclared checks')
        require(set(receipt['results']) == set(names), 'Receipt check/results mismatch')
        require(receipt['candidate'] == base['candidate'] and receipt['candidate_sha256'] == base['candidate_sha256'],
            'Composite receipts do not bind identical candidate members and digest')
        require(receipt['candidate_sha256'] == hashlib.sha256(review_packet._json(receipt['candidate'])).hexdigest(),
            'Canonical candidate digest mismatch')
        require(receipt.get('candidate_fresh') is True, 'Validation candidate was not fresh')
        require(review_packet._members(ROOT, [row['path'] for row in receipt['candidate']]) == receipt['candidate'],
            'Current candidate members differ from validation')
        receipt_id = evidence_id(path)
        receipts.append({'receipt': receipt_id, 'sha256': hashlib.sha256(path.read_bytes()).hexdigest(),
            'attempt_id': receipt['attempt_id'], 'complete': receipt['complete']})
        for check in checks:
            name = check['id']
            if name in FINAL_IDS and receipt['results'][name].get('exit_code') == 0 and receipt['results'][name].get('status') == 'green':
                # Inputs are ordered oldest first; retain the latest real GREEN
                # observation for each node, preserving its exact argv and log.
                log_path = local_file(path.parent / (name + '.log'))
                selected[name] = {'receipt': receipt_id, 'attempt_id': receipt['attempt_id'],
                    'result': receipt['results'][name], 'argv': check['argv'], 'env': check.get('env', {}),
                    'log': evidence_id(log_path), 'log_sha256': hashlib.sha256(log_path.read_bytes()).hexdigest()}
    require(subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip() == CANDIDATE,
        'HEAD differs from final product candidate')
    require(set(selected) == set(FINAL_IDS), 'Composite is missing GREEN final carriers: ' +
        ', '.join(name for name in FINAL_IDS if name not in selected))
    summary = {'schema_version': 'corex-composite-validation/v1', 'item_id': ITEM,
        'product_commit': CANDIDATE, 'candidate_sha256': base['candidate_sha256'],
        'candidate': base['candidate'], 'source_receipts': receipts,
        'selection': {name: selected[name] for name in FINAL_IDS},
        'all_original_29_green': True, 'candidate_fresh': True}
    summary_path = PLAN / 'artifacts/P07.4-execution/composite-validation-9e56afe6-r1.json'
    if args.resolve_repair:
        require(not list((PLAN / 'artifacts/P07.4/owner-review').glob('packet-*/review-route.json')),
            'Review packet already exists; freeze composite proof before review')
        if summary_path.exists():
            require(load(summary_path) == summary, 'Existing composite summary differs; preserve original evidence')
        else:
            with summary_path.open('x', encoding='utf-8') as handle:
                json.dump(summary, handle, ensure_ascii=False, sort_keys=True, indent=2)
                handle.write('\n')
    else:
        require(load(summary_path) == summary, 'Composite proof was not frozen before owner review')
    return summary, {name: selected[name]['log'] for name in required_ids}, evidence_id(summary_path)


def resolve_repair(args):
    start, rows = start_identity()
    receipt, logs, receipt_id = final_receipt(args, ('package-tests', 'dependencies', 'anti-weakening'))
    pre_path = PLAN / 'artifacts/P07.4-execution/repair-config-pre.json'
    record = load(pre_path)
    plan_repair.validate(record)
    incident = plan_repair.incident_key(record)
    matching = [row for row in rows if row.get('item_id') == ITEM and row.get('repair_decision')
        and plan_repair.incident_key(row['repair_decision']) == incident]
    require(bool(matching), 'Original repair was not recorded in canonical journal')
    if any(plan_repair.post_validation_complete(row['repair_decision']) for row in matching):
        print('Qualified repair already resolved; no append')
        return
    require(any(row['repair_decision'] == record and row.get('run_id') == start['run_id'] for row in matching),
        'Repair pre-record differs from durable incident')
    require(not list((PLAN / 'artifacts/P07.4/owner-review').glob('packet-*/review-route.json')),
        'Review packet already exists; resolve repair before freezing execution evidence')
    check_names = ('package-tests', 'dependencies', 'anti-weakening')
    post = copy.deepcopy(record)
    post['post_validation'] = []
    for original, name in zip(record['pre_validation'], check_names, strict=True):
        # The digest identifies the exact failed carrier, not its new log bytes.
        post['post_validation'].append({**original, 'result': 'green'})
        evidence_id(logs[name])
    post['evidence_ids'] = list(dict.fromkeys(record['evidence_ids'] +
        [receipt_id] + [receipt['selection'][name]['receipt'] for name in check_names] +
        [logs[name] for name in check_names]))
    for value in post['evidence_ids']:
        evidence_id(value)
    plan_repair.validate(post)
    decision = plan_repair.evaluate(post, plan_dir=PLAN)
    require(decision['action'] == 'repair-in-current-attempt' and plan_repair.post_validation_complete(post),
        'Qualified repair post-record does not resolve original carrier')
    entry = journal.new_entry(plan_id=start['plan_id'], item_id=ITEM, command=start['command'],
        actor=start['actor'], result='local-repair', session_id=start['session_id'], run_id=start['run_id'],
        attempt=start['attempt'], friction=['local-repair'],
        note='Qualified repair post-validation GREEN: exact test:packages carrier, deps:analyse and anti-weakening on final candidate.')
    entry.update(repair_decision=post, repair_digest=plan_repair.digest(post), repair_action=decision['action'])
    journal.validate_entry(entry)
    journal.validate_append_policy(entry, plan_dir=PLAN)
    journal.append(PLAN, entry)
    require(not plan_repair.unresolved_incidents(PLAN, ITEM), 'Qualified repair still unresolved after append')
    print(json.dumps({'repair_post_appended': True, 'run_id': start['run_id'], 'receipt': receipt_id}))


def negative_evidence(value):
    path = local_file(value)
    summary = load(path)
    require(summary['candidate'] == CANDIDATE and summary['passed'] is True and summary['restored'] is True,
        'Negative harness did not pass and restore final candidate')
    require(summary['preimage'] == summary['postimage'], 'Negative harness pre/post image mismatch')
    expected = ('01-bridge-unreleased-graph', '02-baseline-manifests', '03-mystery-graph',
        '04-mystery-manifests', '05-foreign-unreleased-manifests')
    require([row['name'] for row in summary['runs']] == list(expected), 'Missing examples section11 carriers')
    ids = [evidence_id(path)]
    for row in summary['runs']:
        require(row['passed'] is True and row['restored'] is True and row['candidate'] == CANDIDATE,
            f'Negative carrier failed: {row["name"]}')
        ids.append(evidence_id(path.parent / row['log']))
    return ids


def terminal(args):
    require(args.negative_summary is not None, '--terminal requires --negative-summary with the actual harness output')
    start, rows = start_identity()
    receipt, logs, receipt_id = final_receipt(args, FINAL_IDS)
    require(receipt['all_original_29_green'] is True, 'Composite final 29 proof is incomplete')
    require(not plan_repair.unresolved_incidents(PLAN, ITEM), 'Run --resolve-repair before owner review')
    spec = plan_layout.carrier_path(PLAN, 'item-spec', item_id=ITEM)
    require(hashlib.sha256(spec.read_bytes()).hexdigest() == SPEC_SHA256, 'Immutable P07.4 specification changed')
    text = spec.read_text(encoding='utf-8')
    block = text.split('**Validation:**\n', 1)[1].split('\n**Deliverables:**', 1)[0].strip()
    bullets = []
    for line in block.splitlines():
        if line.startswith('- '):
            bullets.append(line[2:])
        elif line.strip():
            require(bool(bullets), 'Unexpected Validation text')
            bullets[-1] += '\n' + line
    require(len(bullets) == 9, 'Expected one terminal row for each of nine immutable Validation bullets')
    negatives = negative_evidence(args.negative_summary)
    completion = PLAN / 'artifacts/P07.4-execution/completion.md'
    require('review: self-check GREEN' in local_file(completion).read_text(encoding='utf-8'),
        'Completion Notes do not declare self-check GREEN')
    request = review_packet.review_request(PLAN, ITEM)
    require(request is not None, 'No durable selected independent reviewer request')
    require(request['executor_session'] == start['session_id'], 'Review executor differs from durable start')
    reviewed = review_packet.require_green(PLAN, ITEM, request['reviewer'], effort=request['effort'])
    require(reviewed['reviewer_session'] != start['session_id'], 'Review is not independent')
    review_dir = review_packet._location(PLAN, ITEM)
    review_receipts = [path for path in review_dir.glob('attempt-*/receipt.json') if load(path) == reviewed]
    require(len(review_receipts) == 1, 'Cannot locate unique canonical current GREEN receipt')
    groups = (
        ('candidate-anchor', 'contract-head', 'contract-base'),
        ('access-azguard-scan',),
        ('access-closure', 'access-tests', 'pg-preflight', 'access-pg'),
        ('bridge-tests',),
        ('graph', 'manifests', 'ownership', 'tables', 'naming'),
        ('package-tests', 'root-pg'),
        ('api-write-1', 'api-write-2', 'api', 'bc', 'golden-delta', 'golden'),
        ('lint', 'analyse', 'dependencies', 'anti-weakening', 'docs'),
        (),
    )
    validation = []
    for index, (carrier, names) in enumerate(zip(bullets, groups, strict=True)):
        ids = [receipt_id] + [receipt['selection'][name]['receipt'] for name in names] + [logs[name] for name in names]
        if index == 4:
            ids += negatives
        if index == 6:
            ids += [evidence_id(PLAN / 'artifacts/P07.4-execution/golden-semantic-delta.json')]
        if index == 8:
            ids += [evidence_id(completion), evidence_id(review_receipts[0])]
        validation.append({'carrier': carrier, 'status': 'green', 'evidence_ids': list(dict.fromkeys(ids))})
    validation.append({'carrier': 'docs/dev-doctrine.md D98: bash scripts/mutation-check.sh --all before closure for packages/*/src changes',
        'status': 'green', 'evidence_ids': [evidence_id(ROOT / 'docs/dev-doctrine.md'), receipt_id,
            receipt['selection']['mutation']['receipt'], logs['mutation']]})
    for row in validation:
        for value in row['evidence_ids']:
            evidence_id(value)
    decision = terminal_policy.build_decision(PLAN, ITEM, result='closed-green', validation=validation)
    batch = copy.deepcopy(start['batch_receipt'])
    batch['completed_prefix'] = ['P07.4']
    # Current schema has only 'continue'. The owner fresh-session boundary is
    # expressed in the note and final delivery, without inventing an enum.
    batch['stop_disposition'] = 'continue'
    task_contract.validate_batch_receipt(batch)
    entry = journal.new_entry(plan_id=start['plan_id'], item_id=ITEM, command=start['command'],
        actor=start['actor'], result='closed-green', session_id=start['session_id'], run_id=start['run_id'],
        attempt=start['attempt'], decision=decision, batch_receipt=batch,
        note='P07.4 final 29 checks and examples §11 GREEN; independent owner review GREEN. Owner requested P07.5 only in a fresh session; batch checkpoint retains membership and completed P07.4 prefix.')
    journal.validate_entry(entry)
    journal.validate_append_policy(entry, plan_dir=PLAN)
    terminal_policy.validate_entry(entry, plan_dir=PLAN)
    output = PLAN / '.plan-cache/P07.4-terminal-entry.json'
    require(not output.exists(), f'Refusing to overwrite terminal entry: {output}')
    output.parent.mkdir(parents=True, exist_ok=True)
    with output.open('x', encoding='utf-8') as handle:
        json.dump(entry, handle, ensure_ascii=False, sort_keys=True, indent=2)
        handle.write('\n')
    print(json.dumps({'terminal_entry': str(output), 'terminal_appended': False,
        'validation_rows': len(validation), 'batch_receipt': batch,
        'next_fresh_session_command': '$ task:plan-run 2026.09.27-№1-COREX-KERNEL P07.5 --reviewer=grok/grok-4.7/500k'}, ensure_ascii=False))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group(required=True)
    mode.add_argument('--resolve-repair', action='store_true')
    mode.add_argument('--terminal', action='store_true')
    parser.add_argument('--receipt', type=Path, default=DEFAULT_RECEIPT)
    parser.add_argument('--extra-receipt', type=Path, action='append', default=[],
        help='repeatable canonical residual receipt, oldest first, for the identical candidate')
    parser.add_argument('--negative-summary', type=Path)
    args = parser.parse_args()
    if args.resolve_repair:
        resolve_repair(args)
    else:
        terminal(args)


if __name__ == '__main__':
    main()
