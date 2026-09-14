#!/usr/bin/env python3
"""Verify existing immutable evidence and seal its exact local delivery inventory.

Read-only for consumers/databases/source. Writes only this delivery's new evidence
carriers. Never rebuilds the accepted Stancl candidate or rewrites prior receipts.
"""
import argparse
import hashlib
import json
from pathlib import Path
import re
import shutil
import subprocess
import zipfile

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--checkout', type=Path, required=True)
args = parser.parse_args()
here = Path(__file__).resolve().parent
root = here.parents[1]
checkout = args.checkout.resolve()
source = 'cb79f18095589124759fdd1107144764bb31d094'
qualification = '063f165e1065053984751f2efc41c18caefe90ac'
baseline = 'ea5f01dd81c03b238bcb15c24036862adc756eb9'


def digest(value):
    return hashlib.sha256(value).hexdigest()


def git(*args):
    return subprocess.check_output(['git', *args], cwd=root).decode().strip()


def tree(directory):
    rows = [path.relative_to(directory).as_posix() + '\0' + digest(path.read_bytes())
            for path in sorted(directory.rglob('*')) if path.is_file()]
    assert rows
    return digest(('\n'.join(rows) + '\n').encode())


authority = {
    '/home/vostrikov/projects/flexcrm/plans/FLEX-2/schema/DATABASE_SCHEMA.md': '1ef24ff57f3480829499ab621a9499adc9a96cbc5f618555d977a6f9f625e98f',
    '/home/vostrikov/projects/flexcrm/plans/FLEX-2/schema/approval.md': '9ef95fec6ed4f97c1e3fff7523bbbe926827401d3211fdffb7eae5358e5a2fb9',
    '/home/vostrikov/projects/flexcrm/plans/FLEX-2/brief/crm-install-schema.md': 'a65ed02ba8468a4cc90e7e8e54e6be0d0f72a5c1f06cb012030cb7de2b6615a5',
}
for path, sha in authority.items():
    assert digest(Path(path).read_bytes()) == sha, path
for ref in [source, qualification, baseline]:
    assert git('rev-parse', ref + '^{commit}') == ref

sealed = root / 'plans/2026.09.12-№2-COREX-APPROVED-SPECS/findings/P4.1-source'
source_map = json.loads((sealed / 'source-map.json').read_text())
assert digest((sealed / 'packages.json').read_bytes()) == source_map['packages_metadata_sha256']
for key, package in source_map['packages'].items():
    assert digest((sealed / (key + '-upstream.tar')).read_bytes()) == package['tar_sha256']
    archive_path = sealed / (key + '-candidate.zip')
    assert digest(archive_path.read_bytes()) == package['candidate_zip_sha256']
    assert hashlib.sha1(archive_path.read_bytes()).hexdigest() == package['candidate_zip_sha1']
    with zipfile.ZipFile(archive_path) as archive:
        assert digest(archive.read('composer.json')) == package['candidate_composer_sha256']

protected = ['scripts/distribution/export.py', 'scripts/distribution/export-policy.json',
             'packages/api/src', 'packages/core/src/Contracts', 'packages/core/src/Tenancy/Contracts',
             'packages/modules/src/Contracts', 'packages/tenancy/src', 'packages/tenancy/config',
             'packages/tenancy/database']
assert not git('diff', '--name-only', baseline, source, '--', *protected)
old_migrations = git('ls-tree', '-r', '--name-only', baseline, '--', 'packages/crm/database/migrations').splitlines()
assert not git('diff', '--name-only', baseline, source, '--', *old_migrations)
production = []
for name in ['core', 'modules', 'tenancy', 'api', 'crm']:
    production.extend(['packages/' + name + '/' + suffix for suffix in ['src', 'database', 'config', 'composer.json', 'CHANGELOG.md', 'UPGRADE.md']])
assert not git('diff', '--name-only', source, qualification, '--', *production)

profiles = {}
for name in ['installed', 'installed-laravel12']:
    folder = here / name
    evidence = json.loads((folder / 'evidence.json').read_text())
    assert evidence['source_ref'] == source
    assert evidence['config_sha256'] == digest((here / 'candidate.json').read_bytes())
    assert evidence['exporter_sha256'] == digest((root / 'scripts/distribution/export.py').read_bytes())
    for package_name, package in evidence['packages'].items():
        archive_path = folder / 'artifacts' / Path(package['composer']['dist']['url']).name
        assert archive_path.is_file() and digest(archive_path.read_bytes()) == package['dist_sha256']
        assert hashlib.sha1(archive_path.read_bytes()).hexdigest() == package['composer']['dist']['shasum']
        if package_name.startswith(('corex/', 'flex/')):
            assert package['subtree'] == git('rev-parse', source + ':packages/' + package_name.split('/')[1])
            assert not (folder / 'consumer-a/vendor' / package_name / 'tests').exists()
    a, b = evidence['consumers']['a'], evidence['consumers']['b']
    assert a['application_key_sha256'] != b['application_key_sha256']
    assert a['lock_sha256'] == b['lock_sha256']
    assert a['installed_package_trees'] == b['installed_package_trees']
    for suffix, consumer in evidence['consumers'].items():
        base = folder / ('consumer-' + suffix)
        assert digest((base / 'composer.lock').read_bytes()) == consumer['lock_sha256']
        assert digest((base / 'openapi.json').read_bytes()) == consumer['openapi_sha256']
        assert len(consumer['exchanges']) == 45
        assert len(consumer['locked_packages']) == 88
        assert len(consumer['catalogue']) == 12
        for package, sha in consumer['installed_package_trees'].items():
            assert tree(base / 'vendor' / package) == sha, package
    profiles[name] = {'laravel': a['laravel'], 'php': a['php'], 'lock_sha256': a['lock_sha256'],
                      'openapi_sha256': a['openapi_sha256'], 'consumers': 2, 'runtime_packages': 88,
                      'exchanges_per_consumer': 45, 'targets': [item for consumer in [a, b] for item in consumer['targets']]}
first = json.loads((here / 'installed/evidence.json').read_text())
second = json.loads((here / 'installed-laravel12/evidence.json').read_text())
assert {key: value['dist_sha256'] for key, value in first['packages'].items()} == {key: value['dist_sha256'] for key, value in second['packages'].items()}

quality_lock = json.loads((checkout / '.qualification-composer.lock').read_text())
quality_packages = {package['name']: package for package in quality_lock['packages'] + quality_lock['packages-dev']}
runtime_proof = {}
for package in first['consumers']['a']['locked_packages']:
    actual = quality_packages[package['name']]
    for key in ['version', 'source', 'dist']:
        assert actual.get(key) == package.get(key), (package['name'], key)
    actual_tree = tree(checkout / 'vendor' / package['name'])
    assert actual_tree == first['consumers']['a']['installed_package_trees'][package['name']], package['name']
    runtime_proof[package['name']] = {'version': package['version'], 'source': package.get('source'),
                                     'dist': package.get('dist'), 'installed_tree_sha256': actual_tree}

gates = json.loads((here / 'gates.json').read_text())
for gate in gates['gates']:
    log = (here / gate['log']).read_text()
    assert gate['exit'] == 0
    if gate['id'].startswith('installed-'):
        assert source in log and '"locks_equal": true' in log
    elif gate['id'] == 'registered-mutation-check':
        assert 'все 34 кейса(ов) покраснели под саботажем — OK' in log
        assert len(re.findall(r'mutation-check\[[^]]+\]: OK', log)) == 34
    elif gate['id'] in ['contract-guard', 'anti-weakening']:
        assert 'OK' in log
    else:
        values = [json.loads(line) for line in log.splitlines() if line.startswith('{"tool":')]
        assert values and values[-1]['result'] == 'passed', gate['id']
        for field in ['tests', 'assertions', 'errors']:
            if field in gate:
                assert values[-1][field] == gate[field], (gate['id'], field)
        if 'coverage' in gate:
            coverage = float(re.search(r'Total: ([\d.]+) %', log).group(1))
            assert coverage == gate['coverage'] and coverage >= gate['floor']

snapshot = here / 'quality'
snapshot.mkdir(exist_ok=True)
files = ['.qualification-composer.json', '.qualification-composer.lock', '.qualification-phpunit.xml',
         '.qualification-tests-lint.neon', 'composer.json', 'phpunit.xml', 'phpstan.neon', 'phpstan-tests-lint.neon', 'pint.json']
files += ['packages/' + name + '/' + file for name in ['crm', 'api', 'tenancy']
          for file in ['composer.json', 'phpunit.xml', '.qualification-phpunit.xml', 'phpstan.neon']]
for file in files:
    destination = snapshot / file
    destination.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(checkout / file, destination)
proof = {'schema_version': 'corex-crm-install-seal/v1', 'source_ref': source, 'qualification_ref': qualification,
         'baseline_ref': baseline, 'authority': authority, 'profiles': profiles, 'exact_quality_runtime': runtime_proof,
         'production_delta_after_source': [], 'historical_migration_delta': [], 'protected_platform_delta': [],
         'sealed_source_map_sha256': digest((sealed / 'source-map.json').read_bytes())}
(here / 'seal.json').write_text(json.dumps(proof, indent=2, sort_keys=True) + '\n')

inventory = []
for path in sorted(here.rglob('*')):
    if not path.is_file() or path.name in ['hashes.json', 'receipt.md']:
        continue
    relative = path.relative_to(here)
    if any(part in ['vendor', 'config', 'storage', 'bootstrap', '__pycache__'] for part in relative.parts):
        continue
    if relative.parts[0] in ['installed', 'installed-laravel12']:
        if len(relative.parts) == 2 and relative.name in ['evidence.json', 'packages.json']:
            pass
        elif relative.parts[1] == 'artifacts':
            pass
        elif relative.parts[1] in ['consumer-a', 'consumer-b'] and relative.name in ['composer.json', 'composer.lock', 'evidence.json', 'openapi.json', 'install.log', 'probe.log']:
            pass
        else:
            continue
    inventory.append({'path': relative.as_posix(), 'bytes': path.stat().st_size, 'sha256': digest(path.read_bytes())})
(here / 'hashes.json').write_text(json.dumps({'schema_version': 'corex-crm-delivery-hashes/v1',
                                           'receipt_excluded_to_avoid_recursive_hash': True, 'files': inventory}, indent=2, sort_keys=True) + '\n')
print(json.dumps({'verified': True, 'source_ref': source, 'profiles': profiles,
                  'quality_runtime_packages': len(runtime_proof), 'inventory_files': len(inventory)}, indent=2))
