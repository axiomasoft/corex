#!/usr/bin/env python3
"""Build committed ZIP packages and probe two clean installed boxed consumers.

Does not provision an account database or qualify a product installation.
"""
import argparse
import base64
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import zipfile

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--ref', required=True, help='Immutable monorepo commit')
parser.add_argument('--laravel', default='13.*')
args = parser.parse_args()
repository = Path(subprocess.check_output(['git', 'rev-parse', '--show-toplevel'], text=True).strip())
ref = subprocess.check_output(['git', 'rev-parse', '--verify', args.ref + '^{commit}'], cwd=repository, text=True).strip()
work = Path(tempfile.mkdtemp(prefix='corex-api-installed-', suffix='_test'))
artifacts = work / 'artifacts'
artifacts.mkdir()


def blob(path):
    return subprocess.check_output(['git', 'show', ref + ':' + path], cwd=repository)


def archive(source, name):
    target = artifacts / (name.replace('/', '-') + '.zip')
    paths = subprocess.check_output(['git', 'ls-tree', '-r', '--name-only', ref, '--', source], cwd=repository, text=True).splitlines()
    with zipfile.ZipFile(target, 'w', compression=zipfile.ZIP_DEFLATED) as output:
        for path in paths:
            item = zipfile.ZipInfo(name.split('/')[1] + '/' + path[len(source) + 1:], date_time=(2026, 9, 14, 0, 0, 0))
            item.external_attr = 0o100644 << 16
            item.compress_type = zipfile.ZIP_DEFLATED
            output.writestr(item, blob(path))
    metadata = json.loads(blob(source + '/composer.json'))
    metadata['version'] = 'dev-main'
    metadata['dist'] = {'type': 'zip', 'url': target.as_uri(), 'reference': ref,
                        'shasum': hashlib.sha1(target.read_bytes()).hexdigest()}
    return metadata, hashlib.sha256(target.read_bytes()).hexdigest()


packages, hashes = [], {}
for source, name in [('packages/core', 'corex/core'), ('packages/modules', 'corex/modules'),
                     ('packages/api', 'corex/api'),
                     ('delivery/corex-api-extension-20260914/fixtures/notes', 'example/notes')]:
    metadata, digest = archive(source, name)
    packages.append(metadata)
    hashes[name] = digest

composition = {
    'name': 'example/installed-probe', 'type': 'project',
    'require': {'php': '^8.4', 'laravel/framework': args.laravel, 'corex/api': 'dev-main', 'example/notes': 'dev-main'},
    'repositories': [{'type': 'package', 'package': package} for package in packages],
    'minimum-stability': 'dev', 'prefer-stable': True, 'config': {'allow-plugins': False},
}
environment = dict(os.environ, APP_ENV='testing', DB_CONNECTION='sqlite', DB_DATABASE=':memory:')
evidence = {'ref': ref, 'directory': str(work), 'dist_sha256': hashes, 'consumers': {}}
for name in ['consumer-a', 'consumer-b']:
    consumer = work / name
    consumer.mkdir()
    (consumer / 'composer.json').write_text(json.dumps(composition, indent=4) + '\n')
    if name == 'consumer-b':
        shutil.copyfile(work / 'consumer-a/composer.lock', consumer / 'composer.lock')
    with (consumer / 'install.log').open('w') as log:
        command = ['composer', 'update' if name == 'consumer-a' else 'install', '--no-scripts', '--no-interaction', '--no-progress', '--prefer-dist']
        subprocess.run(command, cwd=consumer, env=environment, stdout=log, stderr=subprocess.STDOUT, check=True)
    for directory in ['bootstrap/cache', 'config', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs']:
        (consumer / directory).mkdir(parents=True, exist_ok=True)
    (consumer / 'bootstrap/providers.php').write_text('<?php\ndeclare(strict_types=1);\nreturn [];\n')
    key = 'base64:' + base64.b64encode(os.urandom(32)).decode()
    (consumer / 'config/app.php').write_text("<?php\ndeclare(strict_types=1);\nreturn ['env'=>'testing','debug'=>false,'name'=>'" + name + "','key'=>'" + key + "','cipher'=>'AES-256-CBC','timezone'=>'UTC','locale'=>'en','fallback_locale'=>'en'];\n")
    (consumer / 'config/database.php').write_text("<?php\ndeclare(strict_types=1);\nreturn ['default'=>'sqlite','connections'=>['sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]];\n")
    (consumer / 'config/cache.php').write_text("<?php\ndeclare(strict_types=1);\nreturn ['default'=>'array','stores'=>['array'=>['driver'=>'array']]];\n")
    (consumer / 'config/corex-api.php').write_text("<?php\ndeclare(strict_types=1);\nreturn ['require_physical_tenancy'=>false];\n")
    (consumer / 'probe.php').write_bytes(blob('delivery/corex-api-extension-20260914/fixtures/probe.php'))
    result = subprocess.run(['php', 'probe.php'], cwd=consumer, env=environment, capture_output=True, text=True)
    (consumer / 'probe.log').write_text(result.stdout + result.stderr)
    if result.returncode:
        raise RuntimeError('Installed probe failed; see ' + str(consumer / 'probe.log'))
    receipt = json.loads(result.stdout[result.stdout.index('{'):])
    receipt['lock_sha256'] = hashlib.sha256((consumer / 'composer.lock').read_bytes()).hexdigest()
    for path in (consumer / 'vendor').rglob('*'):
        if path.is_symlink():
            raise RuntimeError('Installed vendor contains a symlink: ' + str(path))
    evidence['consumers'][name] = receipt

first, second = evidence['consumers'].values()
assert first['lock_sha256'] == second['lock_sha256']
assert first['openapi_sha256'] == second['openapi_sha256']
assert first['application_key_sha256'] != second['application_key_sha256']
(work / 'evidence.json').write_text(json.dumps(evidence, indent=4) + '\n')
print(json.dumps(evidence, indent=4))
