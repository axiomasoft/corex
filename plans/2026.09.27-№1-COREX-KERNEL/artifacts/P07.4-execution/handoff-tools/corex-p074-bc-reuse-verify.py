#!/usr/bin/env python3
"""Verify current relevance of an existing BC result; never execute BC.

Tool/environment continuity is an explicit session assertion, because the old
receipt did not hash vendor or the runtime. Current hashes are not old hashes.
"""
import argparse
import hashlib
import json
import os
from pathlib import Path
import stat
import subprocess

ANCHOR = 'acca84ea2e7a67f1109d7e97f329f5b34f9a5a6d'
OLD = Path('plans/2026.09.27-№1-COREX-KERNEL/artifacts/P07.4-execution/canonical-validation/608be1592adb3a84ac9ade50e7ce5432f80d447f33296b95296beb158f5f5b47/final-acca84ea-r2')
RECEIPT_HASH = '2c513f2488a5823b5bb3dc88263d83b90b830b3e1d949e093e67cc1b5fd38133'
LOG_HASH = 'bfa6fea38f592acb6d81ab5ddaf963b4a0a79803e6a4d47cbe0d299861c96acf'
EXCLUDED = 'packages/access-azguard/tests/Feature/AzGuardBridgeTest.php'
UNRELEASED_SOURCE = 'packages/access-azguard/src/Internal/WorkspacePermissionLayer.php'
IGNORED_CI = {'.github/workflows/ci.yml', 'scripts/ci-native-access.py'}
BASE = '76f8bdde496154c0a8901e459175cf306bedd860'

def sha(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()

def git(*args):
    return subprocess.check_output(['git', *args])

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--assert-session-tool-environment-unchanged', action='store_true', required=True,
                    help='Executor asserts no install, vendor edits, runtime/tool/config or BC environment change since original BC')
args = parser.parse_args()
assert args.assert_session_tool_environment_unchanged
head = git('rev-parse', 'HEAD').decode().strip()
subprocess.run(['git', 'merge-base', '--is-ancestor', ANCHOR, head], check=True)
assert sha(OLD/'receipt.json') == RECEIPT_HASH, 'Original receipt changed'
assert sha(OLD/'bc.log') == LOG_HASH, 'Original BC log changed'
receipt = json.loads((OLD/'receipt.json').read_text())
assert receipt['results']['bc']['status'] == 'green'
assert receipt['results']['bc']['exit_code'] == 0
log = (OLD/'bc.log').read_text()
assert f'BC_CHECK_BASE={BASE} (PHP 8.4)' in log
assert 'bc-check: roave 8.22.0' in log
assert log.rstrip().endswith('bc-check: OK')
assert f'(access-azguard) |new-package| base missing at {BASE}' in log
assert subprocess.run(['git', 'cat-file', '-e', BASE+':packages/access-azguard'],
                      stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL).returncode != 0

members = {row['path']: row for row in receipt['candidate']}
for name, row in members.items():
    if name in IGNORED_CI: continue
    p = Path(name)
    assert p.is_file(), 'Missing receipt member: '+name
    assert stat.S_IMODE(p.stat().st_mode) == row['mode'], 'Changed member mode: '+name
    if name not in {EXCLUDED, UNRELEASED_SOURCE}:
        assert sha(p) == row['sha256'], 'Changed receipt member: '+name

assert Path(UNRELEASED_SOURCE).read_bytes() == git('show', '9e56afe69ef2525f7ec3a68ca3b252e6b9bf1078:'+UNRELEASED_SOURCE), 'Unreleased workspace expression must be original validated source'

# The original receipt has a focused member list. Bind the entire tracked
# package/script/decision/archive closure to the original clean commit as well.
scopes = ['packages', 'scripts', 'composer.json', 'composer.lock',
          'plans/2026.09.27-№1-COREX-KERNEL/decisions']
changed = git('diff', '--name-only', '-z', ANCHOR, '--', *scopes).decode().split('\0')
assert set(filter(None, changed)) <= {EXCLUDED, UNRELEASED_SOURCE, 'scripts/ci-native-access.py'}, 'Other BC-scoped tracked changes: '+repr(changed)
untracked = git('ls-files', '--others', '--exclude-standard', '-z', '--', 'packages', 'scripts').decode().split('\0')
assert not any(untracked), 'Untracked BC-scoped inputs: '+repr(untracked)
assert not Path('scripts/ci-native-access.py').exists()
assert Path('.github/workflows/ci.yml').read_bytes() == git('show', '9e56afe69ef2525f7ec3a68ca3b252e6b9bf1078:.github/workflows/ci.yml')
assert 'ci-native-access' not in Path('scripts/bc-check.sh').read_text()
rule = json.loads(Path('scripts/bc/approved-deltas.json').read_text())
for item in rule['packages'].values():
    for decision in item['decisions']:
        assert sha(decision['path']) == decision['sha256'], 'Changed BC admission decision'
assert json.loads(Path('scripts/bc/accepted-base.json').read_text())['revision'] == BASE
assert '--exclude=/vendor --exclude=/tests' in Path('scripts/bc-check.sh').read_text()

# No root autoload registration eagerly includes the edited feature test.
for f in ['vendor/composer/autoload_files.php', 'vendor/composer/autoload_classmap.php']:
    assert EXCLUDED not in Path(f).read_text(), 'Edited test is now registered with root autoload'
for key in ['BC_CHECK_ONLY', 'BC_CHECK_FORCE_STABLE', 'BC_CHECK_PROBE_INFRA']:
    assert not os.environ.get(key), 'BC control flag differs: '+key
assert os.environ.get('BC_CHECK_BASE', BASE) in ['', BASE]
assert os.environ.get('BC_CHECK_JOBS') == '1'
assert os.environ.get('XDEBUG_MODE') == 'off'

# Archive bytes are selected from the current HEAD name set, then bound to
# the historical lock SHA1 before extraction. A changed/unavailable archive
# or changed matching-path cohort would therefore be relevant.
base_lock = json.loads(git('show', BASE+':composer.lock'))
names_now = set(filter(None, git('ls-tree', '-r', '-z', '--name-only', 'HEAD').decode().split('\0')))
names_then = set(filter(None, git('ls-tree', '-r', '-z', '--name-only', ANCHOR).decode().split('\0')))
archive_bindings = {}
for package in base_lock.get('packages', []) + base_lock.get('packages-dev', []):
    dist = package.get('dist', {})
    url = dist.get('url', '')
    if dist.get('type') != 'zip' or '://' in url or not url.endswith('.zip'):
        continue
    basename = Path(url).name
    cohort_now = {name for name in names_now if Path(name).name == basename}
    cohort_then = {name for name in names_then if Path(name).name == basename}
    assert cohort_now == cohort_then, 'Changed historical archive name cohort'
    matching = {Path(name).read_bytes() for name in cohort_now if Path(name).is_file()
                and hashlib.sha1(Path(name).read_bytes()).hexdigest() == dist['shasum']}
    assert len(matching) == 1, 'Historical archive bytes are not uniquely digest-bound'
    archive_bindings[package['name']] = {'historical_sha1': dist['shasum'], 'current_sha256': hashlib.sha256(next(iter(matching))).hexdigest()}

current_tool_files = ['vendor/composer/installed.json', 'vendor/composer/autoload_real.php',
                      'vendor/roave/backward-compatibility-check/bin/roave-backward-compatibility-check.php',
                      'vendor/phpstan/phpstan/phpstan.phar']

print(json.dumps({
    'schema': 'bc-current-relevance/v1',
    'original_attempt': 'final-acca84ea-r2',
    'original_receipt': str(OLD/'receipt.json'),
    'original_receipt_sha256': RECEIPT_HASH,
    'original_bc_log': str(OLD/'bc.log'),
    'original_bc_log_sha256': LOG_HASH,
    'original_bc_result': receipt['results']['bc'],
    'current_product_commit': head,
    'anchor_product_commit': ANCHOR,
    'changed_excluded_test': EXCLUDED,
    'changed_unreleased_source_absent_at_bc_base': UNRELEASED_SOURCE,
    'test_before_sha256': members[EXCLUDED]['sha256'],
    'test_current_sha256': sha(EXCLUDED),
    'unchanged_receipt_members': len(members)-1-len(IGNORED_CI),
    'owner_ignored_ci_paths': sorted(IGNORED_CI),
    'reused_result': True,
    'bc_executed_in_this_check': False,
    'current_tool_marker_sha256': {name: sha(name) for name in current_tool_files if Path(name).is_file()},
    'current_historical_archive_bindings': archive_bindings,
    'tool_environment_continuity': 'executor session continuity assertion; original receipt has no historical tool hashes',
}, sort_keys=True), flush=True)
