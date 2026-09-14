#!/usr/bin/env python3
"""Transport-only local Composer test setup; leaves committed manifests unchanged."""
import argparse
import hashlib
import json
from pathlib import Path
from urllib.parse import urlparse
import xml.etree.ElementTree as ET

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--checkout', type=Path, required=True)
parser.add_argument('--sealed-artifacts', type=Path, required=True)
parser.add_argument('--installed-set', type=Path)
parser.add_argument('--auth-static-autoload', type=Path)
parser.add_argument('--mutation-source', action='store_true')
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
    if args.mutation_source:
        # Existing registered sabotage patches target committed source paths.
        # Require byte equality before routing the mutation-only class loader
        # there. This prevents a false GREEN from patching unexecuted copies.
        import zipfile
        for package in metadata:
            name = package['name']
            if not name.startswith(('corex/', 'flex/')):
                continue
            folder = root / 'packages' / name.split('/')[1]
            with zipfile.ZipFile(installed / 'artifacts' / (name.replace('/', '-') + '.zip')) as archive:
                for member in archive.namelist():
                    if member.startswith('src/'):
                        assert (folder / member).read_bytes() == archive.read(member)
            for prefix, path in package['autoload']['psr-4'].items():
                value['autoload-dev']['psr-4'][prefix] = str(folder.relative_to(root) / path)
    (root / '.qualification-composer.json').write_text(json.dumps(value, indent=2, sort_keys=True) + '\n')
    # Same coverage source sets, relocated to the files actually executed from
    # immutable vendor. Original XML, tests, exclusions and floors stay intact.
    package_names = {package['name'].split('/')[1]: package['name'] for package in metadata if package['name'].startswith(('corex/', 'flex/'))}
    xml = ET.parse(root / 'phpunit.xml')
    for directory in xml.findall('./source/include/directory'):
        name = directory.text.split('/')[1]
        directory.text = 'vendor/' + package_names[name] + '/src'
    xml.write(root / '.qualification-phpunit.xml', encoding='utf-8', xml_declaration=True)
    for name in ['crm', 'api', 'tenancy']:
        folder = root / 'packages' / name
        xml = ET.parse(folder / 'phpunit.xml')
        xml.getroot().set('bootstrap', '../../vendor/autoload.php')
        for directory in xml.findall('./source/include/directory'):
            directory.text = '../../vendor/' + package_names[name] + '/src'
        xml.write(folder / '.qualification-phpunit.xml', encoding='utf-8', xml_declaration=True)
    if args.auth_static_autoload:
        path = args.auth_static_autoload.resolve()
        assert path.is_file()
        source = (root / 'phpstan-tests-lint.neon').read_text()
        (root / '.qualification-tests-lint.neon').write_text(source.replace('packages/auth/vendor/autoload.php', str(path)))
    print('Pinned every installed runtime dependency for exact-set root/package quality checks.')
print('Prepared exact sealed dist transport for owning tenancy test manifest.')
