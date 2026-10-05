#!/usr/bin/env python3
"""P07.4 new candidate: freeze full29 proof, then assemble terminal JSON after fresh GREEN. Never appends journal or calls plan_work.complete."""
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
sys.path.insert(0, str(RUNTIME.parent.parent.parent))
from task import journal, plan_layout, plan_repair, review_packet, task_contract, terminal_policy

ROOT = Path('/home/vostrikov/projects/packages/corex')
PLAN = ROOT / 'plans/2026.09.27-№1-COREX-KERNEL'
ITEM = 'P07.4'
CANDIDATE = None
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
    path = local_file(args.receipt)
    receipt = load(path)
    require(receipt['schema_version'] == 'validation-attempt/v1', 'Wrong canonical receipt schema')
    require(receipt['complete'] is True, 'Full canonical receipt is incomplete')
    require(receipt['attempt_id'] == args.attempt_id, 'Wrong canonical attempt')
    require([row['id'] for row in receipt['checks']] == list(FINAL_IDS), 'Final 29 checks differ')
    require(set(receipt['results']) == set(FINAL_IDS), 'Final 29 results differ')
    require(receipt['candidate_sha256'] == hashlib.sha256(review_packet._json(receipt['candidate'])).hexdigest(),
        'Canonical candidate digest mismatch')
    require(receipt.get('candidate_fresh') is True, 'Validation candidate was not fresh')
    require(review_packet._members(ROOT, [row['path'] for row in receipt['candidate']]) == receipt['candidate'],
        'Current candidate members differ from validation')
    require(subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip() == CANDIDATE,
        'HEAD differs from final product candidate')
    receipt_id = evidence_id(path)
    selected = {}
    for index, check in enumerate(receipt['checks']):
        name = check['id']
        require(check.get('depends_on', []) == ([] if index == 0 else [FINAL_IDS[index-1]]),
            'Canonical checks are not the expected serial chain: ' + name)
        result = receipt['results'][name]
        require(result.get('exit_code') == 0 and result.get('status') == 'green', 'Check is not GREEN: ' + name)
        log = local_file(path.parent / (name + '.log'))
        selected[name] = {'receipt': receipt_id, 'attempt_id': receipt['attempt_id'], 'result': result,
            'argv': check['argv'], 'env': check.get('env', {}), 'log': evidence_id(log),
            'log_sha256': hashlib.sha256(log.read_bytes()).hexdigest()}
    anchor = local_file(path.parent / 'candidate-anchor.log').read_text()
    require(CANDIDATE in anchor, 'Candidate anchor log does not name final commit')
    deps = selected['dependencies']
    require(deps['env'].get('PHP_INI_SCAN_DIR') == '/etc/php/8.4/cli/conf.d:/tmp/corex-p074-php-runtime/ini',
        'Real Redis runtime environment is missing from dependency carrier')
    require(not plan_repair.unresolved_incidents(PLAN, ITEM), 'Qualified repair remains unresolved')
    summary = {'schema_version': 'corex-final-validation/v1', 'item_id': ITEM,
        'product_commit': CANDIDATE, 'candidate_sha256': receipt['candidate_sha256'],
        'candidate': receipt['candidate'], 'source_receipts': [{'receipt': receipt_id,
            'sha256': hashlib.sha256(path.read_bytes()).hexdigest(), 'attempt_id': receipt['attempt_id'], 'complete': True}],
        'selection': selected, 'all_original_29_green': True, 'candidate_fresh': True}
    summary_path = Path(args.summary)
    if not summary_path.is_absolute(): summary_path = ROOT / summary_path
    summary_path = summary_path.resolve()
    require(summary_path.is_relative_to(PLAN / 'artifacts/P07.4-execution'), 'Summary must be in P07.4 execution evidence')
    if args.freeze:
        rounds = [row for row in review_packet._history(review_packet._location(PLAN, ITEM)) if row['status'] == 'complete']
        require(len(rounds) == 1, 'Freeze the candidate before second completed review')
        if summary_path.exists():
            require(load(summary_path) == summary, 'Existing frozen summary differs; preserve it')
        else:
            with summary_path.open('x', encoding='utf-8') as handle:
                json.dump(summary, handle, ensure_ascii=False, sort_keys=True, indent=2)
                handle.write('\n')
    else:
        require(load(summary_path) == summary, 'Final validation proof was not frozen before owner review')
    return summary, {name: selected[name]['log'] for name in required_ids}, evidence_id(summary_path)



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
    require(receipt['all_original_29_green'] is True, 'Final 29 proof is incomplete')
    require(not plan_repair.unresolved_incidents(PLAN, ITEM), 'Qualified repair remains unresolved')
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
    completion = local_file(args.completion)
    require('review: self-check GREEN' in local_file(completion).read_text(encoding='utf-8'),
        'Completion Notes do not declare self-check GREEN')
    require(review_packet.review_request(PLAN, ITEM) is None, 'Cancelled owner request remains active')
    authority = PLAN / 'decisions/D109-p074-ci-exclusion-and-review-cancellation.md'
    require('status: accepted' in authority.read_text() and 'items: [P07.4]' in authority.read_text(), 'Owner cancellation authority missing')
    owner_evidence = PLAN / 'artifacts/P07.4-execution/owner-close-direction.md'
    archived_request = PLAN / 'artifacts/P07.4/owner-review/request-withdrawn-D109.json'
    require(load(archived_request)['executor_session'] == start['session_id'], 'Archived request identity differs')
    review_authority_ids = [evidence_id(authority), evidence_id(owner_evidence), evidence_id(archived_request)]
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
            ids += [evidence_id(args.golden_summary), evidence_id(PLAN / 'artifacts/P07.4-execution/canonical-validation/608be1592adb3a84ac9ade50e7ce5432f80d447f33296b95296beb158f5f5b47/final-acca84ea-r2/receipt.json'), evidence_id(PLAN / 'artifacts/P07.4-execution/canonical-validation/608be1592adb3a84ac9ade50e7ce5432f80d447f33296b95296beb158f5f5b47/final-acca84ea-r2/bc.log')]
        if index == 8:
            ids += [evidence_id(completion), *review_authority_ids]
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
        note='P07.4 final 29 checks and examples §11 GREEN; independent review cancelled by owner D109; CI excluded, no independent GREEN claimed. Owner requested P07.5 only in a fresh session; batch checkpoint retains membership and completed P07.4 prefix.')
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
        'next_fresh_session_command': '$ task:plan-run 2026.09.27-№1-COREX-KERNEL P07.5'}, ensure_ascii=False))


def main():
    global CANDIDATE
    parser = argparse.ArgumentParser(description='Freeze one full final29 receipt or assemble the terminal entry after fresh GREEN. Never appends terminal journal.')
    mode = parser.add_mutually_exclusive_group(required=True)
    mode.add_argument('--freeze', action='store_true')
    mode.add_argument('--terminal', action='store_true')
    parser.add_argument('--candidate', default='HEAD')
    parser.add_argument('--receipt', type=Path, required=True)
    parser.add_argument('--attempt-id', required=True)
    parser.add_argument('--summary', type=Path, required=True)
    parser.add_argument('--completion', type=Path)
    parser.add_argument('--golden-summary', type=Path, default=PLAN / 'artifacts/P07.4-execution/golden-semantic-delta.json')
    parser.add_argument('--negative-summary', type=Path)
    args = parser.parse_args()
    CANDIDATE = subprocess.check_output(['git','rev-parse','--verify',args.candidate+'^{commit}'],cwd=ROOT,text=True).strip()
    if args.freeze:
        start_identity()
        summary, logs, summary_id = final_receipt(args, FINAL_IDS)
        print(json.dumps({'frozen_summary': summary_id, 'product_commit': CANDIDATE, 'attempt_id': args.attempt_id, 'complete': True}))
    else:
        require(args.completion is not None, '--terminal requires frozen final --completion')
        terminal(args)


if __name__ == '__main__':
    main()
