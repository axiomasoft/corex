#!/usr/bin/env python3
"""Design evidence only: solve projected root graphs in disposable directories.

No repository manifests, locks, code, vendors or database are mutated.
Run from the CoreX root; raw output is retained beside this script.
"""
import hashlib
import json
import os
from pathlib import Path
import shutil
import shlex
import subprocess
import sys
import tempfile
import time
import urllib.request

ROOT = Path.cwd().resolve()
SPIKE_ONLY = '--spike' in sys.argv
OUT = Path(__file__).resolve().parent / 'spike' if SPIKE_ONLY else Path(__file__).resolve().parent
STACKS = {
    '12': {'laravel/framework': '12.69.3', 'orchestra/testbench': '10.12.0',
           'pestphp/pest': '4.7.8', 'phpunit/phpunit': '12.5.33', 'symfony/process': '7.4.19'},
    '13': {'laravel/framework': '13.35.0', 'orchestra/testbench': '11.3.0',
           'pestphp/pest': '5.2.1', 'phpunit/phpunit': '13.3.4', 'symfony/process': '8.1.7'},
}
COMMON = {'infection/infection': '0.35.6', 'infection/abstract-testframework-adapter': '0.5.1',
          'infection/include-interceptor': '1.0.0', 'axiomasoft/corex-infection-pest-adapter': '0.1.0'}


def save(path, value):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, indent=2, ensure_ascii=False) + '\n')


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def run(argv, work, env, destination):
    destination.mkdir(parents=True, exist_ok=True)
    started = time.monotonic()
    with (destination / 'stdout').open('w') as stdout, (destination / 'stderr').open('w') as stderr:
        process = subprocess.run(list(map(str, argv)), cwd=work, env=env,
                                 stdout=stdout, stderr=stderr, timeout=600)
    record = {'argv': list(map(str, argv)), 'cwd': str(work), 'exit': process.returncode,
              'seconds': round(time.monotonic() - started, 3),
              'stdout_sha256': digest(destination / 'stdout'), 'stderr_sha256': digest(destination / 'stderr')}
    save(destination / 'process.json', record)
    return record


def main():
    protected = [ROOT / 'composer.json', ROOT / 'composer.lock',
                 ROOT / 'scripts/quality/infection-compatibility.json']
    protected += sorted((ROOT / 'scripts/quality/infection-pest-adapter').rglob('*.php'))
    protected += [ROOT / 'scripts/quality/infection-pest-adapter/composer.json',
                  ROOT / 'scripts/quality/infection-spike.py']
    before = {str(p.relative_to(ROOT)): digest(p) for p in protected}
    temporary = Path(tempfile.mkdtemp(prefix='corex-p099-stack-design-'))
    save(OUT / 'environment.json', {'temporary': str(temporary), 'source_commit': subprocess.check_output(
        ['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip(), 'protected_before': before})
    adapter = temporary / 'adapter'
    shutil.copytree(ROOT / 'scripts/quality/infection-pest-adapter', adapter)
    manifest = json.loads((adapter / 'composer.json').read_text())
    manifest['require']['pestphp/pest'] = '4.7.8 || 5.2.1'
    manifest['require']['phpunit/phpunit'] = '12.5.33 || 13.3.4'
    save(adapter / 'composer.json', manifest)
    save(OUT / 'hypothetical-adapter-composer.json', manifest)

    # The host PHP 8.5 lacks a root product extension. Extract the matching binary
    # package locally; never install it or alter system PHP configuration.
    extension = None
    if not SPIKE_ONLY and subprocess.run(['php8.5', '-r', 'exit(extension_loaded("bcmath") ? 0 : 1);']).returncode:
        package_dir = temporary / 'php85-bcmath'
        package_dir.mkdir()
        package_version = '8.5.11-1+ubuntu24.04.1+deb.sury.org+1'
        save(OUT / 'bcmath-package.json', {'version': package_version, 'action': 'download and extract only'})
        (OUT / 'bcmath-package-metadata.txt').write_text(subprocess.check_output(['apt-cache', 'show', 'php8.5-bcmath'], text=True))
        command = run(['apt-get', 'download', 'php8.5-bcmath=' + package_version], package_dir, os.environ, OUT / 'bcmath-download')
        assert command['exit'] == 0
        deb = next(package_dir.glob('*.deb'))
        command = run(['dpkg-deb', '-x', deb, package_dir / 'extracted'], package_dir, os.environ, OUT / 'bcmath-extract')
        assert command['exit'] == 0
        extension = next((package_dir / 'extracted').rglob('bcmath.so'))
        save(OUT / 'bcmath-package.json', {'version': package_version, 'action': 'download and extract only',
             'deb_sha256': digest(deb), 'module_sha256': digest(extension), 'module': str(extension)})

    sources = [
        ('pest-4.7.8', 'https://raw.githubusercontent.com/pestphp/pest/v4.7.8/composer.json'),
        ('pest-5.2.1', 'https://raw.githubusercontent.com/pestphp/pest/v5.2.1/composer.json'),
        ('phpunit-12.5.33', 'https://raw.githubusercontent.com/sebastianbergmann/phpunit/12.5.33/composer.json'),
        ('phpunit-13.3.4', 'https://raw.githubusercontent.com/sebastianbergmann/phpunit/13.3.4/composer.json'),
        ('laravel-12.69.3', 'https://raw.githubusercontent.com/laravel/framework/v12.69.3/composer.json'),
        ('laravel-13.35.0', 'https://raw.githubusercontent.com/laravel/framework/v13.35.0/composer.json'),
        ('testbench-10.12.0', 'https://raw.githubusercontent.com/orchestral/testbench/v10.12.0/composer.json'),
        ('testbench-11.3.0', 'https://raw.githubusercontent.com/orchestral/testbench/v11.3.0/composer.json'),
        ('process-7.4.19', 'https://raw.githubusercontent.com/symfony/process/v7.4.19/composer.json'),
        ('process-8.1.7', 'https://raw.githubusercontent.com/symfony/process/v8.1.7/composer.json'),
        ('infection-0.35.6', 'https://raw.githubusercontent.com/infection/infection/0.35.6/composer.json'),
    ]
    captures = []
    for name, url in sources:
        path = OUT / 'sources' / (name + '-composer.json')
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(urllib.request.urlopen(url, timeout=60).read())
        captures.append({'url': url, 'path': str(path.relative_to(OUT)), 'sha256': digest(path)})
    save(OUT / 'sources/index.json', captures)

    records = []
    composer = shutil.which('composer')
    for laravel, stack in STACKS.items():
        canonical_lock = None
        for php in ('8.4', '8.5'):
            work = temporary / ('php' + php + '-laravel' + laravel)
            work.mkdir()
            binary = Path(shutil.which('php' + php)).resolve()
            shim = work / 'php-bin'
            shim.mkdir()
            php_cli = [binary] + (['-d', 'extension=' + str(extension)] if php == '8.5' and extension else [])
            (shim / 'php').write_text('#!/bin/sh\nexec ' + shlex.join(list(map(str, php_cli))) + ' "$@"\n')
            (shim / 'php').chmod(0o755)
            env = {**os.environ, 'PATH': str(shim) + os.pathsep + os.environ['PATH'],
                   'COMPOSER_HOME': str(temporary / 'composer-home'), 'COMPOSER_NO_INTERACTION': '1',
                   'COMPOSER_ALLOW_XDEBUG': '1'}
            env.pop('COMPOSER', None)
            env.pop('COMPOSER_VENDOR_DIR', None)
            root = json.loads((ROOT / 'composer.json').read_text())
            if SPIKE_ONLY:
                local_adapter = work / 'scripts/quality/infection-pest-adapter'
                shutil.copytree(adapter, local_adapter)
                root = {'name': 'corex/infection-spike', 'description': 'Repository-only P09.9 compatibility harness',
                        'type': 'project', 'license': 'MIT', 'require-dev': {'php': '^8.4'},
                        'repositories': [{'type': 'path', 'url': 'scripts/quality/infection-pest-adapter',
                                          'options': {'symlink': False, 'versions': {'axiomasoft/corex-infection-pest-adapter': '0.1.0'}}}],
                        'autoload': {'psr-4': {'CoreX\\Tenancy\\': 'packages/tenancy/src/', 'CoreX\\InfectionSpike\\': 'controls/'}},
                        'config': {'allow-plugins': {'pestphp/pest-plugin': True, 'infection/extension-installer': True}},
                        'minimum-stability': 'stable', 'prefer-stable': True}
            root['require-dev'].update(COMMON | stack)
            for repository in root['repositories']:
                if repository['type'] == 'path' and not SPIKE_ONLY:
                    repository['url'] = str(adapter) if repository['url'].endswith('infection-pest-adapter') else str(ROOT / repository['url'])
            save(work / 'composer.json', root)
            cell = OUT / 'solves' / work.name
            save(cell / 'composer.json', root)
            version = run([*php_cli, '-r', 'echo PHP_VERSION, PHP_EOL;'], work, env, cell / 'php-version')
            run([*php_cli, composer, '--version'], work, env, cell / 'composer-version')
            solve = run([*php_cli, composer, 'update', '--no-install', '--no-plugins', '--no-scripts', '--no-progress'],
                        work, env, cell / 'solve')
            record = {'php': php, 'laravel': laravel, 'php_binary_sha256': digest(binary), 'solve': solve}
            if solve['exit'] == 0:
                shutil.copy2(work / 'composer.lock', cell / 'composer.lock')
                locked = json.loads((work / 'composer.lock').read_text())
                versions = {p['name']: p['version'].lstrip('v') for p in locked['packages'] + locked['packages-dev']}
                assert all(versions[name] == value for name, value in (COMMON | stack).items())
                record['versions'] = {k: versions[k] for k in COMMON | stack | {'brianium/paratest': '', 'orchestra/testbench-core': ''}}
                record['platform'] = run([*php_cli, composer, 'check-platform-reqs', '--lock', '--no-plugins'], work, env, cell / 'platform')
                record['audit'] = run([*php_cli, composer, 'audit', '--locked', '--no-plugins', '--format=json'], work, env, cell / 'audit')
                if canonical_lock is None:
                    canonical_lock = (work / 'composer.lock').read_bytes()
                else:
                    # Replay the exact PHP 8.4 lock on the other real interpreter.
                    (work / 'composer.lock').write_bytes(canonical_lock)
                    shutil.copy2(work / 'composer.lock', cell / 'replay-composer.lock')
                    record['shared_lock_install'] = run([*php_cli, composer, 'install', '--dry-run', '--no-plugins', '--no-scripts', '--no-progress'],
                                                       work, env, cell / 'shared-lock-install')
                    record['shared_lock_platform'] = run([*php_cli, composer, 'check-platform-reqs', '--lock', '--no-plugins'],
                                                        work, env, cell / 'shared-lock-platform')
            records.append(record)
            save(OUT / 'receipt.json', {'result': 'solve-evidence-only', 'cells': records})
            print(work.name + ': solve exit ' + str(solve['exit']), flush=True)
    after = {str(p.relative_to(ROOT)): digest(p) for p in protected}
    assert before == after, 'Repository dependencies/tooling changed during design'
    save(OUT / 'protected-inputs.json', {'before': before, 'after': after, 'unchanged': True})
    print('Protected repository inputs unchanged. Disposable directory: ' + str(temporary), flush=True)


if __name__ == '__main__':
    main()
