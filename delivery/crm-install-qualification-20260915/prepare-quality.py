#!/usr/bin/env python3
"""Transport-only local Composer test setup; leaves committed manifests unchanged."""
import argparse
import hashlib
import json
from pathlib import Path
from urllib.parse import urlparse

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--checkout', type=Path, required=True)
parser.add_argument('--sealed-artifacts', type=Path, required=True)
parser.add_argument('--installed-set', type=Path)
args = parser.parse_args()
root = args.checkout.resolve()
sealed = root / 'plans/2026.09.12-№2-COREX-APPROVED-SPECS/findings/P4.1-source/packages.json'
packages = []
for versions in json.loads(sealed.read_text())['packages'].values():
    package = next(iter(versions.values()))
    path = args.sealed_artifacts.resolve() / Path(urlparse(package['dist']['url']).path).name
    assert hashlib.sha1(path.read_bytes()).hexdigest() == package['dist']['shasum']
    package['dist']['url'] = path.as_uri()
    packages.append(package)
for name in ['tenancy']:
    folder = root / 'packages' / name
    value = json.loads((folder / 'composer.json').read_text())
    value['repositories'] = [{'type': 'package', 'package': package} for package in packages] + value['repositories']
    (folder / '.qualification-composer.json').write_text(json.dumps(value, indent=2, sort_keys=True) + '\n')
if args.installed_set:
    installed = args.installed_set.resolve()
    lock = json.loads((installed / 'consumer-a/composer.lock').read_text())
    value = json.loads((root / 'composer.json').read_text())
    value['require'].update({package['name']: package['version'] for package in lock['packages']})
    metadata = json.loads((installed / 'packages.json').read_text())
    value['repositories'] = [{'type': 'package', 'package': package} for package in metadata] + value['repositories']
    value['autoload-dev']['psr-4'].update({'Flex\\Crm\\Tests\\': 'packages/crm/tests/', 'CoreX\\Tenancy\\Tests\\': 'packages/tenancy/tests/'})
    (root / '.qualification-composer.json').write_text(json.dumps(value, indent=2, sort_keys=True) + '\n')
    print('Pinned every installed runtime dependency for exact-set root/package quality checks.')
print('Prepared exact sealed dist transport for owning tenancy test manifest.')
