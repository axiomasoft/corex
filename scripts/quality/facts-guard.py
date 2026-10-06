#!/usr/bin/env python3
"""Seal the pre-work facts and reject drift outside a frozen item write set."""
import argparse
import hashlib
import json
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PLAN = Path('plans/2026.09.27-№1-COREX-KERNEL')
FACTS_SCRIPT = PLAN / 'artifacts/P09-design/scripts/facts.py'


def digest(data):
    return hashlib.sha256(data).hexdigest()


def read_json(path):
    def unique(pairs):
        result = {}
        for key, value in pairs:
            if key in result:
                raise ValueError(f'duplicate JSON key: {key}')
            result[key] = value
        return result
    return json.loads(Path(path).read_text(), object_pairs_hook=unique)


def git(root, *args):
    return subprocess.check_output(['git', *args], cwd=root)


def inventory(root):
    paths = git(root, 'ls-files', '-z', '--cached', '--others', '--exclude-standard').decode().split('\0')
    return {p: digest((root / p).read_bytes()) for p in sorted(set(paths))
            if p and not p.startswith(('plans/', 'build/')) and (root / p).is_file()}


def differences(before, after, prefix=''):
    if isinstance(before, dict) and isinstance(after, dict):
        return [row for key in sorted(before.keys() | after.keys())
                for row in differences(before.get(key), after.get(key), f'{prefix}.{key}' if prefix else key)]
    if before != after:
        return [{'key': prefix, 'previous': before, 'current': after}]
    return []


def seal(path, value):
    path = Path(path)
    if path.exists() or Path(str(path) + '.sha256').exists():
        raise ValueError(f'receipt already exists: {path}')
    path.parent.mkdir(parents=True, exist_ok=True)
    data = (json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2) + '\n').encode()
    path.write_bytes(data)
    Path(str(path) + '.sha256').write_text(digest(data) + '\n')


def read_sealed(path):
    path = Path(path)
    if digest(path.read_bytes()) != Path(str(path) + '.sha256').read_text().strip():
        raise ValueError(f'mutated receipt: {path}')
    receipt = read_json(path)
    if 'scope_extension' in receipt:
        extension = receipt['scope_extension']
        decision = Path(extension['decision_path'])
        if digest(decision.read_bytes()) != extension['decision_sha']:
            raise ValueError('scope extension decision digest mismatch')
        parent = Path(extension['parent_path'])
        if digest(parent.read_bytes()) != extension['parent_sha']:
            raise ValueError('scope extension parent digest mismatch')
        original = read_sealed(parent)
        immutable = [k for k in original if k not in ('write_set', 'write_set_sha', 'scope_extension')]
        if any(receipt.get(k) != original[k] for k in immutable):
            raise ValueError('scope extension changed pre-work evidence')
        old_paths = {r['path']: r for r in original['write_set']['paths']}
        new_paths = {r['path']: r for r in receipt['write_set']['paths']}
        if any(new_paths.get(p) != row for p, row in old_paths.items()):
            raise ValueError('scope extension changed an existing allowance')
        if extension['added_paths'] != sorted(new_paths.keys() - old_paths.keys()):
            raise ValueError('scope extension path inventory mismatch')
        for path in new_paths.keys() - old_paths.keys():
            if new_paths[path]['before_sha256'] != original['product_hashes'].get(path):
                raise ValueError(f'scope extension before hash mismatch: {path}')
    return receipt


def extend_scope(root, args):
    before = read_sealed(args.before)
    decision = read_json(args.decision)
    if decision.get('status') != 'accepted' or 'P09.1' not in decision.get('applies_to', []):
        raise ValueError('scope extension requires an accepted owning decision')
    proposed = read_json(args.write_set)
    old = {r['path']: r for r in before['write_set']['paths']}
    new = {r['path']: r for r in proposed['paths']}
    if len(new) != len(proposed['paths']) or any(new.get(p) != row for p, row in old.items()):
        raise ValueError('scope extension changed existing frozen paths')
    current = inventory(root)
    for path in new.keys() - old.keys():
        if before['product_hashes'].get(path) != current.get(path):
            raise ValueError(f'retroactive scope extension: {path}')
        if new[path]['before_sha256'] != current.get(path):
            raise ValueError(f'scope extension before hash mismatch: {path}')
    check_write_set(root, proposed, before['product_hashes'])
    result = {**before, 'write_set': proposed,
              'write_set_sha': digest(Path(args.write_set).read_bytes()),
              'scope_extension': {'parent_path': str(Path(args.before).resolve()),
                                  'parent_sha': digest(Path(args.before).read_bytes()),
                                  'decision_path': str(Path(args.decision).resolve()),
                                  'decision_sha': digest(Path(args.decision).read_bytes()),
                                  'added_paths': sorted(new.keys() - old.keys())}}
    seal(args.output, result)


def measure(root):
    return json.loads(subprocess.check_output([sys.executable, str(root / FACTS_SCRIPT)], cwd=root))


def check_rebase(root, snapshot, current, rebase):
    rows = rebase['exact_deltas']
    changes = differences(snapshot, current)
    if len({row['key'] for row in rows}) != len(rows):
        raise ValueError('duplicate rebase key')
    expected = {r['key']: r for r in changes}
    if set(expected) != {r['key'] for r in rows}:
        raise ValueError('rebase keys differ: ' + ', '.join(sorted(set(expected) ^ {r['key'] for r in rows})))
    ci = rebase['hosted_receipt']
    run = read_json(root / PLAN / ci['run_path'])
    jobs = read_json(root / PLAN / ci['jobs_path'])
    sha = ci['head_sha']
    if (run['id'] != ci['run_id'] or run['head_sha'] != sha
            or run['status'] != 'completed' or run['conclusion'] != 'success'
            or jobs['total_count'] != len(jobs['jobs']) or not jobs['jobs']
            or any(j['head_sha'] != sha or j['status'] != 'completed' or j['conclusion'] != 'success'
                   for j in jobs['jobs'])):
        raise ValueError('closed P08 CI run/SHA/jobs mismatch')
    closed = read_json(root / PLAN / 'phases/P08/P08.10.json')
    if closed['status'] not in ('done', 'done_with_deviations'):
        raise ValueError('P08.10 is not closed')
    if (root / 'scripts/.plan-close-deferred').exists():
        raise ValueError('scripts/.plan-close-deferred')
    head = current['source_commit']
    subprocess.run(['git', 'merge-base', '--is-ancestor', sha, head], cwd=root, check=True,
                   stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    lineage = [p for p in git(root, 'diff', '--name-only', '-z', sha, head).decode().split('\0') if p]
    if any(not p.startswith('plans/') for p in lineage):
        raise ValueError('P08 lineage has product changes: ' + ', '.join(lineage))
    for row in rows:
        key = row['key']
        if '*' in key or key not in ('source_commit', 'anchors..github/workflows/ci.yml',
                                     'anchors.scripts/schema-gate.php'):
            raise ValueError(f'unapproved rebase key: {key}')
        if row['previous'] != expected[key]['previous'] or row['current'] != expected[key]['current']:
            raise ValueError(f'rebase value mismatch: {key}')
        if row['owning_item'] != 'P08.3' or row['candidate_source_sha'] != sha or row['hosted_ci_run_id'] != run['id']:
            raise ValueError(f'rebase provenance mismatch: {key}')
        if not row.get('reason'):
            raise ValueError(f'rebase reason missing: {key}')
        if key.startswith('anchors.'):
            path = key[len('anchors.'):]
            if digest(git(root, 'show', f'{sha}:{path}')) != row['current']:
                raise ValueError(f'P08 anchor mismatch: {path}')
        elif rebase['lineage'] != {'tested_sha': sha, 'pre_source_sha': head, 'plan_only': True}:
            raise ValueError('source_commit lineage mismatch')


def check_write_set(root, write_set, product):
    item = read_json(root / PLAN / 'phases/P09/P09.1.json')
    specs = [r['path'] for r in item['task_files']]
    rows = write_set['paths']
    if len({r['path'] for r in rows}) != len(rows):
        raise ValueError('duplicate write-set path')
    for row in rows:
        path = row['path']
        if '*' in path or '..' in Path(path).parts or Path(path).is_absolute():
            raise ValueError(f'write-set path is not exact: {path}')
        if not any(Path(path).match(pattern) or
                   (pattern.endswith('/**') and path.startswith(pattern[:-2])) for pattern in specs):
            raise ValueError(f'write-set outside item: {path}')
        if row['before_sha256'] != product.get(path) or not row.get('allowed_change'):
            raise ValueError(f'write-set before hash/intent mismatch: {path}')


def pre(root, args):
    snapshot = read_json(args.snapshot)
    current = measure(root)
    rebase = read_json(args.p08_rebase)
    check_rebase(root, snapshot, current, rebase)
    product = inventory(root)
    write_set = read_json(args.write_set)
    check_write_set(root, write_set, product)
    receipt = {'schema': 'corex-facts-pre/1', 'design_snapshot_sha': digest(Path(args.snapshot).read_bytes()),
               'snapshot_path': str(Path(args.snapshot).resolve()),
               'facts_script_sha': digest((root / FACTS_SCRIPT).read_bytes()),
               'pre_source_sha': current['source_commit'], 'current_facts': current,
               'approved_deltas': rebase, 'rebase_sha': digest(Path(args.p08_rebase).read_bytes()),
               'write_set': write_set, 'write_set_sha': digest(Path(args.write_set).read_bytes()),
               'product_hashes': product,
               'ceilings': read_json(root / 'scripts/quality-ceilings.json'),
               'analysis': analysis_configs(root)}
    seal(args.output, receipt)


def verify_pre(root, args):
    receipt = read_sealed(args.receipt)
    if receipt['design_snapshot_sha'] != digest(Path(args.snapshot).read_bytes()):
        raise ValueError('design snapshot digest mismatch')
    if receipt['facts_script_sha'] != digest((root / FACTS_SCRIPT).read_bytes()):
        raise ValueError('original facts script changed')
    if receipt['rebase_sha'] != digest(Path(args.p08_rebase).read_bytes()):
        raise ValueError('P08 rebase digest mismatch')
    check_rebase(root, read_json(args.snapshot), receipt['current_facts'], receipt['approved_deltas'])


def analysis_configs(root):
    result = {}
    for path in [root / 'phpstan.neon', *root.glob('packages/*/phpstan.neon'),
                 *root.glob('products/*/phpstan.neon')]:
        text = path.read_text()
        block = re.search(r'^    paths:\s*\n((?:        .+\n)+)', text, re.M)
        result[str(path.relative_to(root))] = {
            'paths': re.findall(r'^        - (.+)$', block[1], re.M) if block else [],
            'max': bool(re.search(r'^    level: max$', text, re.M)),
            'bleedingEdge': 'phar://phpstan.phar/conf/bleedingEdge.neon' in text,
            'extensions': 'phpstan-extensions.neon' in text,
        }
    return result


def check_post(root, before, write_set, current, product):
    if write_set != before['write_set']:
        raise ValueError('frozen write-set changed')
    allowed = {r['path'] for r in write_set['paths']}
    changed = [p for p in sorted(before['product_hashes'].keys() | product.keys())
               if before['product_hashes'].get(p) != product.get(p)]
    outside = [p for p in changed if p not in allowed]
    if outside:
        raise ValueError('non-owned product drift: ' + ', '.join(outside))
    old = before['current_facts']
    if {p['path'] for p in old['packages']} != {p['path'] for p in current['packages']}:
        raise ValueError('package inventory changed')
    for a, b in zip(old['packages'], current['packages']):
        for field in a:
            if field in ('baseline_count', 'baseline_entries'):
                if b[field] > a[field]:
                    raise ValueError(f"{b['path']}.{field} increased")
                if b[field] != a[field] and b['path'] != 'packages/core':
                    raise ValueError(f"non-owned {b['path']}.{field} changed")
            elif b[field] != a[field]:
                raise ValueError(f"{b['path']}.{field} changed")
    if current['root_baseline_count'] != 0 or next(p for p in current['packages'] if p['path'] == 'packages/core')['baseline_count'] != 0:
        raise ValueError('root/core baseline debt is not zero')
    ceilings = read_json(root / 'scripts/quality-ceilings.json')
    for kind, values in before['ceilings'].items():
        if kind == 'schema':
            if ceilings[kind] != values:
                raise ValueError('ceilings schema changed')
            continue
        if not set(values) <= set(ceilings[kind]):
            raise ValueError(f'ceilings.{kind} paths removed')
        for key, value in ceilings[kind].items():
            if value > values.get(key, 0):
                raise ValueError(f'ceilings.{kind}.{key} increased')
    if any(ceilings['phpstan'].get(k) != 0 for k in ('root', 'core', 'access-azguard')):
        raise ValueError('foundation ceilings are not zero')
    actual = analysis_configs(root)
    if actual.keys() != before['analysis'].keys():
        raise ValueError('analysis config inventory changed')
    for path, config in actual.items():
        if not all(config[k] for k in ('max', 'bleedingEdge', 'extensions')):
            raise ValueError(f'analysis rules weakened: {path}')
        if not set(before['analysis'][path]['paths']) <= set(config['paths']):
            raise ValueError(f'analysis paths removed: {path}')
    return changed


def post(root, args):
    before = read_sealed(args.before)
    if digest(Path(before['snapshot_path']).read_bytes()) != before['design_snapshot_sha']:
        raise ValueError('original design snapshot changed')
    if digest((root / FACTS_SCRIPT).read_bytes()) != before['facts_script_sha']:
        raise ValueError('original facts script changed')
    if digest(Path(args.write_set).read_bytes()) != before['write_set_sha']:
        raise ValueError('frozen write-set digest mismatch')
    current = measure(root)
    subprocess.run(['git', 'merge-base', '--is-ancestor', before['pre_source_sha'], current['source_commit']],
                   cwd=root, check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    product = inventory(root)
    changed = check_post(root, before, read_json(args.write_set), current, product)
    seal(args.output, {'schema': 'corex-facts-post/1', 'before_sha': digest(Path(args.before).read_bytes()),
                       'current_source_sha': current['source_commit'], 'current_facts': current,
                       'diff_paths': changed, 'product_hashes': product})


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root', type=Path, default=ROOT)
    commands = parser.add_subparsers(dest='command', required=True)
    p = commands.add_parser('pre')
    for flag in ('snapshot', 'p08-rebase', 'write-set', 'output'):
        p.add_argument('--' + flag, required=True)
    p = commands.add_parser('verify-pre')
    for flag in ('snapshot', 'receipt', 'p08-rebase'):
        p.add_argument('--' + flag, required=True)
    p = commands.add_parser('post')
    for flag in ('before', 'write-set', 'output'):
        p.add_argument('--' + flag, required=True)
    p = commands.add_parser('extend-scope')
    for flag in ('before', 'write-set', 'decision', 'output'):
        p.add_argument('--' + flag, required=True)
    args = parser.parse_args()
    try:
        {'pre': pre, 'verify-pre': verify_pre, 'post': post, 'extend-scope': extend_scope}[args.command](args.root.resolve(), args)
    except (ValueError, KeyError, OSError, subprocess.CalledProcessError) as error:
        print(f'facts {args.command} FAIL: {error}', file=sys.stderr)
        return 1
    print(f'facts {args.command} OK')
    return 0


if __name__ == '__main__':
    sys.exit(main())
