#!/usr/bin/env python3
"""Explicit full-set qualification, reusing the committed deterministic exporter.

Creates only new named test databases. No publication or populated conversion.
"""
import argparse
import base64
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import shutil
import subprocess
import zipfile
from urllib.parse import urlparse

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--ref', required=True)
parser.add_argument('--out', type=Path, required=True)
parser.add_argument('--prefix', required=True)
parser.add_argument('--laravel', default='13.*')
args = parser.parse_args()
ref = subprocess.check_output(['git', 'rev-parse', args.ref + '^{commit}'], cwd=ROOT, text=True).strip()
if args.ref != ref:
    raise RuntimeError('Supply the full immutable commit SHA, not a moving selector.')
out = args.out.resolve()
out.mkdir()  # Never overwrite prior candidates/evidence.
artifacts = out / 'artifacts'
artifacts.mkdir()
spec = importlib.util.spec_from_file_location('corex_export', ROOT / 'scripts/distribution/export.py')
exporter = importlib.util.module_from_spec(spec)
spec.loader.exec_module(exporter)
config = json.loads((HERE / 'candidate.json').read_text())
names = set(config['packages'])
manifest = {'source_ref': ref, 'config_sha256': exporter.digest((HERE / 'candidate.json').read_bytes()),
            'exporter_sha256': exporter.digest((ROOT / 'scripts/distribution/export.py').read_bytes()),
            'packages': {}, 'consumers': {}}
metadata = []
for name, source in sorted(config['packages'].items()):
    path = artifacts / (name.replace('/', '-') + '.zip')
    sha, sha1, license_sha = exporter.archive(path, ref, source, config, names)
    package = json.loads(exporter.patched_composer(ref, source, config['version'], names))
    package['dist'] = {'type': 'zip', 'url': path.as_uri(), 'reference': ref, 'shasum': sha1}
    metadata.append(package)
    manifest['packages'][name] = {'version': config['version'], 'subtree': exporter.git('rev-parse', ref + ':' + source).decode().strip(),
                                 'dist_sha256': sha, 'dist_sha1': sha1, 'license_sha256': license_sha,
                                 'composer': package}
    repeat = artifacts / (name.replace('/', '-') + '-repeat.zip')
    assert exporter.archive(repeat, ref, source, config, names) == (sha, sha1, license_sha)

sealed = ROOT / 'plans/2026.09.12-№2-COREX-APPROVED-SPECS/findings/P4.1-source'
for name, versions in json.loads((sealed / 'packages.json').read_text())['packages'].items():
    package = next(iter(versions.values()))
    source = sealed / Path(urlparse(package['dist']['url']).path).name
    # Supply-chain acceptance pins already sealed by the owning P4.1 candidate.
    assert hashlib.sha1(source.read_bytes()).hexdigest() == package['dist']['shasum']
    destination = artifacts / source.name
    shutil.copyfile(source, destination)
    package['dist']['url'] = destination.as_uri()
    metadata.append(package)
    manifest['packages'][name] = {'version': package['version'], 'source': package['source'],
                                 'dist_sha256': exporter.digest(destination.read_bytes()), 'composer': package}
(out / 'packages.json').write_text(json.dumps(metadata, indent=2, sort_keys=True) + '\n')
composition = {'name': 'example/physical-crm-probe', 'type': 'project',
               'require': {'php': '^8.4', 'laravel/framework': args.laravel, **{name: config['version'] for name in names}},
               'repositories': [{'type': 'package', 'package': package} for package in metadata],
               'minimum-stability': 'alpha', 'prefer-stable': True, 'config': {'allow-plugins': False}}
env = dict(os.environ, APP_ENV='testing', TENANCY_CENTRAL_CONNECTION='pgsql')
env.pop('COREX_MODULES_CONNECTION', None)
for suffix in ['a', 'b']:
    consumer = out / ('consumer-' + suffix)
    consumer.mkdir()
    (consumer / 'composer.json').write_text(json.dumps(composition, sort_keys=True, indent=2) + '\n')
    if suffix == 'b':
        shutil.copyfile(out / 'consumer-a/composer.lock', consumer / 'composer.lock')
    with (consumer / 'install.log').open('w') as log:
        command = ['composer', 'update' if suffix == 'a' else 'install', '--no-dev', '--no-scripts', '--no-interaction', '--no-progress', '--prefer-dist']
        subprocess.run(command, cwd=consumer, env=env, stdout=log, stderr=subprocess.STDOUT, check=True)
    for directory in ['bootstrap/cache', 'config', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs']:
        (consumer / directory).mkdir(parents=True, exist_ok=True)
    (consumer / 'bootstrap/providers.php').write_text('<?php\ndeclare(strict_types=1);\nreturn [];\n')
    key = 'base64:' + base64.b64encode(os.urandom(32)).decode()
    configuration = {
        'app': "['env'=>'testing','debug'=>true,'name'=>'physical-probe','key'=>'" + key + "','cipher'=>'AES-256-CBC','timezone'=>'UTC','locale'=>'en','fallback_locale'=>'en']",
        'database': "['default'=>'pgsql','connections'=>['pgsql'=>['driver'=>'pgsql','host'=>'127.0.0.1','port'=>5434,'database'=>env('Q_PREFIX').'_root_test','username'=>'corex','password'=>'corex','charset'=>'utf8','prefix'=>'','search_path'=>'public','sslmode'=>'prefer']]]",
        'cache': "['default'=>'array','stores'=>['array'=>['driver'=>'array']]]",
        'session': "['driver'=>'database','connection'=>'pgsql','table'=>'sessions','cookie'=>'probe','lifetime'=>120]",
        'queue': "['default'=>'sync','connections'=>['sync'=>['driver'=>'sync']]]",
        'filesystems': "['default'=>'local','disks'=>['local'=>['driver'=>'local','root'=>storage_path('app')]]]",
        'logging': "['default'=>'single','channels'=>['single'=>['driver'=>'single','path'=>storage_path('logs/laravel.log'),'level'=>'debug']]]",
        'corex-api': "['require_physical_tenancy'=>true]",
    }
    for name, value in configuration.items():
        (consumer / ('config/' + name + '.php')).write_text('<?php\ndeclare(strict_types=1);\nreturn ' + value + ';\n')
    shutil.copyfile(HERE / 'probe.php', consumer / 'probe.php')
    (consumer / 'StorageCatalogue.php').write_bytes(exporter.git('show', ref + ':packages/crm/tests/Support/StorageCatalogue.php'))
    (consumer / 'approved-storage.sql').write_bytes(exporter.git('show', ref + ':packages/crm/tests/Fixtures/approved-storage.sql'))
    runtime = dict(env, Q_PREFIX=args.prefix + suffix, Q_ADMIN_DATABASE='corex_crm_install_20260915_test')
    result = subprocess.run(['php', 'probe.php'], cwd=consumer, env=runtime, capture_output=True, text=True)
    (consumer / 'probe.log').write_text(result.stdout + result.stderr)
    if result.returncode:
        raise RuntimeError('Installed probe failed: ' + str(consumer / 'probe.log'))
    evidence = json.loads((consumer / 'evidence.json').read_text())
    boxed = consumer / 'boxed'
    for directory in ['bootstrap/cache', 'config', 'storage/logs']:
        (boxed / directory).mkdir(parents=True, exist_ok=True)
    (boxed / 'bootstrap/providers.php').write_text('<?php\ndeclare(strict_types=1);\nreturn [];\n')
    for name in ['app', 'cache', 'logging', 'corex-api']:
        shutil.copyfile(consumer / ('config/' + name + '.php'), boxed / ('config/' + name + '.php'))
    (boxed / 'config/database.php').write_text("<?php\ndeclare(strict_types=1);\nreturn ['default'=>'sqlite','connections'=>['sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]];\n")
    shutil.copyfile(consumer / 'bootstrap/cache/corex_modules.php', boxed / 'bootstrap/cache/corex_modules.php')
    shutil.copyfile(HERE / 'boxed.php', boxed / 'probe.php')
    boxed_result = subprocess.run(['php', 'probe.php'], cwd=boxed, env=runtime, capture_output=True, text=True)
    (boxed / 'probe.log').write_text(boxed_result.stdout + boxed_result.stderr)
    if boxed_result.returncode or not (boxed / 'evidence.json').exists():
        raise RuntimeError('Installed boxed probe failed: ' + str(boxed / 'probe.log'))
    evidence['boxed'] = json.loads((boxed / 'evidence.json').read_text())
    evidence['lock_sha256'] = exporter.digest((consumer / 'composer.lock').read_bytes())
    lock = json.loads((consumer / 'composer.lock').read_text())
    evidence['locked_packages'] = lock['packages']
    evidence['installed_package_trees'] = {}
    for dependency in lock['packages']:
        directory = consumer / 'vendor' / dependency['name']
        rows = []
        for path in sorted(directory.rglob('*')):
            if path.is_file():
                rows.append(path.relative_to(directory).as_posix() + '\0' + exporter.digest(path.read_bytes()))
        assert rows, 'Missing installed dependency: ' + dependency['name']
        evidence['installed_package_trees'][dependency['name']] = exporter.digest(('\n'.join(rows) + '\n').encode())
    assert not lock['packages-dev']
    for path in (consumer / 'vendor').rglob('*'):
        if path.is_symlink():
            raise RuntimeError('Unexpected source symlink: ' + str(path))
    for name in names:
        installed = consumer / 'vendor' / name
        with zipfile.ZipFile(artifacts / (name.replace('/', '-') + '.zip')) as archive:
            for member in archive.namelist():
                assert (installed / member).read_bytes() == archive.read(member), name + ':' + member
        assert not (installed / 'tests').exists()
    # Validate actual envelopes with an independent JSON Schema implementation.
    import jsonschema
    document = json.loads((consumer / 'openapi.json').read_text())
    for exchange in evidence['exchanges']:
        operation = document['paths'][exchange['schema_path']][exchange['method'].lower()]
        schema = operation['responses'][str(exchange['status'])]['content']['application/json']['schema']
        jsonschema.Draft202012Validator(schema, format_checker=jsonschema.FormatChecker()).validate(exchange['response'])
        if exchange['status'] < 300 and exchange['method'] == 'POST':
            jsonschema.Draft202012Validator(operation['requestBody']['content']['application/json']['schema']).validate(exchange['input'])
    evidence['jsonschema_version'] = __import__('importlib.metadata', fromlist=['version']).version('jsonschema')
    manifest['consumers'][suffix] = evidence
first, second = manifest['consumers'].values()
assert first['lock_sha256'] == second['lock_sha256']
assert first['openapi_sha256'] == second['openapi_sha256']
assert first['application_key_sha256'] != second['application_key_sha256']
assert first['catalogue'] == second['catalogue']
assert first['installed_package_trees'] == second['installed_package_trees']
(out / 'evidence.json').write_text(json.dumps(manifest, indent=2, sort_keys=True) + '\n')
print(json.dumps({'ref': ref, 'evidence': str(out / 'evidence.json'), 'locks_equal': True, 'independent_keys': True}, indent=2))
