#!/usr/bin/env python3
"""Retain failed extension-restart evidence, retry full root PHP 8.5 solves.

Composer's XdebugHandler restarted PHP without the temporary -d extension.
COMPOSER_ALLOW_XDEBUG=1 keeps the locally extracted module in this process.
"""
import importlib.util
import json
import os
from pathlib import Path
import shutil

OUT = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('solves', OUT / 'verify-solves.py')
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
temporary = Path(json.loads((OUT / 'environment.json').read_text())['temporary'])
extension = json.loads((OUT / 'bcmath-package.json').read_text())['module']
receipt = json.loads((OUT / 'receipt.json').read_text())
for cell in receipt['cells']:
    if cell['php'] != '8.5':
        continue
    work = temporary / ('php8.5-laravel' + cell['laravel'])
    destination = OUT / 'solves' / work.name
    shutil.move(destination, OUT / ('extension-restart-' + work.name))
    destination.mkdir()
    shutil.copy2(work / 'composer.json', destination / 'composer.json')
    binary = Path(shutil.which('php8.5')).resolve()
    php = [binary, '-d', 'extension=' + extension]
    env = {**os.environ, 'PATH': str(work / 'php-bin') + os.pathsep + os.environ['PATH'],
           'COMPOSER_HOME': str(temporary / 'composer-home'), 'COMPOSER_NO_INTERACTION': '1',
           'COMPOSER_ALLOW_XDEBUG': '1'}
    composer = shutil.which('composer')
    m.run([*php, '-r', 'echo PHP_VERSION," bcmath=",extension_loaded("bcmath")?"yes":"no",PHP_EOL;'],
          work, env, destination / 'php-version')
    m.run([*php, composer, '--version'], work, env, destination / 'composer-version')
    cell['environment'] = {'COMPOSER_ALLOW_XDEBUG': '1', 'bcmath_module_sha256': m.digest(Path(extension))}
    cell['solve'] = m.run([*php, composer, 'update', '--no-install', '--no-plugins', '--no-scripts', '--no-progress'],
                          work, env, destination / 'solve')
    assert cell['solve']['exit'] == 0
    shutil.copy2(work / 'composer.lock', destination / 'composer.lock')
    lock = json.loads((work / 'composer.lock').read_text())
    versions = {p['name']: p['version'].lstrip('v') for p in lock['packages'] + lock['packages-dev']}
    expected = m.COMMON | m.STACKS[cell['laravel']]
    assert all(versions[k] == v for k, v in expected.items())
    cell['versions'] = {k: versions[k] for k in expected | {'brianium/paratest': '', 'orchestra/testbench-core': ''}}
    for name, arguments in [('platform', ['check-platform-reqs', '--lock']),
                            ('audit', ['audit', '--locked', '--format=json'])]:
        cell[name] = m.run([*php, composer, *arguments, '--no-plugins'], work, env, destination / name)
        assert cell[name]['exit'] == 0
    canonical = OUT / 'solves' / ('php8.4-laravel' + cell['laravel']) / 'composer.lock'
    shutil.copy2(canonical, work / 'composer.lock')
    shutil.copy2(canonical, destination / 'replay-composer.lock')
    cell['shared_lock_install'] = m.run([*php, composer, 'install', '--dry-run', '--no-plugins', '--no-scripts', '--no-progress'],
                                        work, env, destination / 'shared-lock-install')
    cell['shared_lock_platform'] = m.run([*php, composer, 'check-platform-reqs', '--lock', '--no-plugins'],
                                         work, env, destination / 'shared-lock-platform')
    assert cell['shared_lock_install']['exit'] == cell['shared_lock_platform']['exit'] == 0
    m.save(OUT / 'receipt.json', receipt)
    print(work.name + ': solve, platform, audit and shared-lock replay exit 0', flush=True)
before = json.loads((OUT / 'protected-inputs.json').read_text())['before']
assert before == {p: m.digest(m.ROOT / p) for p in before}
