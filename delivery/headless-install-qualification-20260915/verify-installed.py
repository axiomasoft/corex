#!/usr/bin/env python3
"""Archive an immutable CoreX ref and qualify two clean, neutral consumers.

The verifier never uses path repositories or the source worktree as a vendor
directory.  It is deliberately local: this is installed-candidate evidence,
not a package release or deployment claim.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import zipfile

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--ref', default='HEAD', help='immutable monorepo commit')
parser.add_argument('--laravel', default='^12.0', help='Laravel framework constraint')
args = parser.parse_args()

repository = Path(subprocess.check_output(['git', 'rev-parse', '--show-toplevel'], text=True).strip())
ref = subprocess.check_output(['git', 'rev-parse', '--verify', args.ref + '^{commit}'], cwd=repository, text=True).strip()
output = repository / 'delivery/headless-install-qualification-20260915/evidence'
output.mkdir(parents=True, exist_ok=True)
work = Path(tempfile.mkdtemp(prefix='corex-headless-installed-', suffix='_test'))
artifacts = work / 'artifacts'
artifacts.mkdir()


def blob(path: str) -> bytes:
    return subprocess.check_output(['git', 'show', ref + ':' + path], cwd=repository)


def archive(source: str, name: str) -> tuple[dict, str]:
    target = artifacts / (name.replace('/', '-') + '.zip')
    paths = subprocess.check_output(['git', 'ls-tree', '-r', '--name-only', ref, '--', source], cwd=repository, text=True).splitlines()
    if not paths:
        raise RuntimeError('Package absent from immutable ref: ' + source)
    with zipfile.ZipFile(target, 'w', compression=zipfile.ZIP_DEFLATED) as output_zip:
        for path in paths:
            item = zipfile.ZipInfo(name.split('/')[1] + '/' + path[len(source) + 1:], date_time=(2026, 9, 16, 0, 0, 0))
            item.external_attr = 0o100644 << 16
            item.compress_type = zipfile.ZIP_DEFLATED
            output_zip.writestr(item, blob(path))
    metadata = json.loads(blob(source + '/composer.json'))
    metadata['version'] = 'dev-main'
    metadata['dist'] = {'type': 'zip', 'url': target.as_uri(), 'reference': ref, 'shasum': hashlib.sha1(target.read_bytes()).hexdigest()}
    return metadata, hashlib.sha256(target.read_bytes()).hexdigest()


sources = [
    ('packages/core', 'corex/core'),
    ('packages/modules', 'corex/modules'),
    ('packages/panels', 'corex/panels'),
    ('packages/media', 'corex/media'),
    ('packages/wakeup', 'corex/wakeup'),
    ('packages/notifications', 'corex/notifications'),
]
packages, hashes = zip(*(archive(source, name) for source, name in sources))
composition = {
    'name': 'example/headless-installed-probe',
    'type': 'project',
    'require': {'php': '^8.3', 'laravel/framework': args.laravel, 'corex/panels': 'dev-main', 'corex/media': 'dev-main', 'corex/notifications': 'dev-main'},
    'repositories': [{'type': 'package', 'package': package} for package in packages],
    'minimum-stability': 'dev', 'prefer-stable': True, 'config': {'allow-plugins': False},
}
environment = dict(os.environ, APP_ENV='testing')
evidence = {'ref': ref, 'laravel_constraint': args.laravel, 'dist_sha256': dict(zip((name for _, name in sources), hashes)), 'consumers': {}}
for name in ('consumer-a', 'consumer-b'):
    consumer = work / name
    consumer.mkdir()
    (consumer / 'composer.json').write_text(json.dumps(composition, indent=2) + '\n')
    if name == 'consumer-b':
        shutil.copyfile(work / 'consumer-a/composer.lock', consumer / 'composer.lock')
    command = ['composer', 'update' if name == 'consumer-a' else 'install', '--no-scripts', '--no-interaction', '--no-progress', '--prefer-dist']
    result = subprocess.run(command, cwd=consumer, env=environment, capture_output=True, text=True)
    (consumer / 'install.log').write_text(result.stdout + result.stderr)
    if result.returncode:
        raise RuntimeError('Composer install failed for ' + name + ': ' + str(consumer / 'install.log'))
    shutil.copyfile(repository / 'delivery/headless-install-qualification-20260915/fixtures/probe.php', consumer / 'probe.php')
    result = subprocess.run(['php', 'probe.php'], cwd=consumer, env=environment, capture_output=True, text=True)
    (consumer / 'probe.log').write_text(result.stdout + result.stderr)
    if result.returncode:
        raise RuntimeError('Installed probe failed for ' + name + ': ' + str(consumer / 'probe.log'))
    for path in (consumer / 'vendor').rglob('*'):
        if path.is_symlink():
            raise RuntimeError('Installed vendor contains symlink: ' + str(path))
    installed = (consumer / 'vendor/corex')
    for path in installed.rglob('*'):
        if path.is_file() and b'FlexCRM' in path.read_bytes():
            raise RuntimeError('Product literal in installed CoreX package: ' + str(path))
    receipt = json.loads(result.stdout)
    receipt['lock_sha256'] = hashlib.sha256((consumer / 'composer.lock').read_bytes()).hexdigest()
    evidence['consumers'][name] = receipt

first, second = evidence['consumers'].values()
if first['lock_sha256'] != second['lock_sha256'] or first['manifests'] != second['manifests']:
    raise RuntimeError('Independent installed consumers are not reproducible.')
(output / 'installed.json').write_text(json.dumps(evidence, indent=2) + '\n')
print(json.dumps(evidence, indent=2))
