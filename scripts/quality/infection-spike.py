#!/usr/bin/env python3
"""Run a pinned Pest/Infection fragment, retaining raw reports for the declared matrix.

The adapter runs the complete fragment. JUnit and coverage stay byte-for-byte raw;
Pest's pretty JUnit names are never substituted into its generated coverage names.
This is compatibility evidence, not a production MSI measurement.
"""
import argparse
import hashlib
import itertools
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import time
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[2]
POLICY = ROOT / 'scripts/quality/infection-compatibility.json'
ADAPTER = 'scripts/quality/infection-pest-adapter'
SCHEMA = 'corex-infection-spike/v2'
PLATFORM_BASE = 'a83248a0adb02c82aca4c6dbdab71fb4c543cdac'
STATUSES = {'killed': 'killedCount', 'escaped': 'escapedCount',
            'timeouted': 'timeOutCount', 'errored': 'errorCount',
            'uncovered': 'notCoveredCount', 'syntaxErrors': 'syntaxErrorCount',
            'killedByStaticAnalysis': 'killedByStaticAnalysisCount', 'ignored': 'ignoredCount'}

PROBE = r'''<?php
declare(strict_types=1);

use CoreX\Tenancy\Provisioning\Internal\DatabaseValue;
use Illuminate\Database\Capsule\Manager;

it('decodes real PDO rows on '.getenv('SPIKE_DRIVER'), function (): void {
    $driver = getenv('SPIKE_DRIVER');
    $database = getenv('PGSQL_DATABASE');
    if ($driver === 'pgsql' && preg_match('/^[a-z0-9_]+_test$/D', $database) !== 1) {
        throw new RuntimeException('Unsafe test database.');
    }
    $capsule = new Manager;
    $capsule->addConnection($driver === 'sqlite'
        ? ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']
        : ['driver' => 'pgsql', 'host' => getenv('PGSQL_HOST'), 'port' => getenv('PGSQL_PORT'),
           'database' => $database, 'username' => getenv('PGSQL_USERNAME'),
           'password' => getenv('PGSQL_PASSWORD'), 'charset' => 'utf8', 'prefix' => '']);
    $connection = $capsule->getConnection();
    $actualDriver = $connection->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    expect($actualDriver)->toBe($driver);
    $connection->statement('CREATE TEMPORARY TABLE corex_spike_values (value BIGINT NOT NULL, label TEXT NOT NULL)');
    $connection->insert('INSERT INTO corex_spike_values (value, label) VALUES (?, ?)', [23, 'tenancy-spike']);
    $row = DatabaseValue::row($connection->selectOne('SELECT value, label FROM corex_spike_values'));
    expect(DatabaseValue::integer($row->value))->toBe(23)
        ->and(DatabaseValue::text($row->label))->toBe('tenancy-spike')
        ->and(DatabaseValue::decimal($row->value))->toBe('23');
    $actualDatabase = $driver === 'pgsql'
        ? $connection->selectOne('SELECT current_database() AS name')->name : ':memory:';
    file_put_contents(getenv('SPIKE_DB_WITNESS'), json_encode([
        'driver' => $actualDriver, 'database' => $actualDatabase,
        'server_version' => $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION),
        'laravel' => \Illuminate\Foundation\Application::VERSION,
        'fixture' => 'connection-local temporary table; no package schema migration',
    ], JSON_THROW_ON_ERROR));
    $connection->disconnect();
});
'''

CONTROL_SOURCE = r'''<?php
declare(strict_types=1);
namespace CoreX\InfectionSpike;
final class Controls
{
    public static function escaped(): bool { return false; }
    public static function uncovered(): bool { return false; }
    public static function timeout(): bool { return false; }
    public static function error(): bool { return false; }
}
'''

CONTROL_TEST = r'''<?php
declare(strict_types=1);
use CoreX\InfectionSpike\Controls;

it('executes the escaped control without asserting its boolean', function (): void {
    Controls::escaped();
    expect(true)->toBeTrue();
});
it('executes the timeout control', function (): void {
    if (Controls::timeout()) {
        pcntl_exec(PHP_BINARY, ['-r', 'fwrite(STDOUT, "SPIKE_TIMEOUT_CONTROL\\n"); sleep(10);']);
        throw new RuntimeException('Could not execute the timeout control.');
    }
    expect(true)->toBeTrue();
});
it('executes the process error control', function (): void {
    if (Controls::error()) {
        pcntl_exec(PHP_BINARY, ['-r', 'fwrite(STDOUT, "SPIKE_ERROR_CONTROL\\n"); exit(137);']);
        throw new RuntimeException('Could not execute the error control.');
    }
    expect(true)->toBeTrue();
});
'''


class ProofError(ValueError):
    pass


def require(condition, message):
    if not condition:
        raise ProofError(message)


def sha(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def save(path, value):
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, indent=2, sort_keys=True) + '\n')


def inputs(root, policy):
    paths = ['composer.json', 'composer.lock', str(POLICY.relative_to(ROOT)),
             'scripts/quality/infection-spike.py', 'scripts/manifest-check.py',
             'scripts/quality/tests/test_infection_spike.py', '.github/workflows/ci.yml',
             policy['source'], policy['test']]
    paths += [str(p.relative_to(root)) for pattern in ('packages/*/composer.json', 'products/*/composer.json')
              for p in root.glob(pattern)]
    paths += [str(p.relative_to(root)) for p in (root / ADAPTER).rglob('*') if p.is_file()]
    return {p: sha(root / p) for p in sorted(paths)}


def artifact(path, output):
    return {'path': str(path.relative_to(output)), 'sha256': sha(path)}


def read_artifact(ref, output):
    require(isinstance(ref, dict) and set(ref) == {'path', 'sha256'}, 'Missing artifact binding')
    path = (output / ref['path']).resolve()
    require(path.is_relative_to(output.resolve()) and path.is_file(), 'Missing or unsafe artifact: ' + str(path))
    require(sha(path) == ref['sha256'], 'Artifact digest mismatch: ' + str(path))
    return path


def junit(path, driver):
    xml = ET.parse(path).getroot()
    cases = xml.findall('.//testcase')
    require(len(cases) == 13, 'Missing/extra Pest cases; expected 9 package + PDO + 3 controls')
    require(all(int(c.get('assertions', '0')) > 0 and not list(c) for c in cases), 'Failed/empty/skipped JUnit case')
    require(any(c.get('name') == 'it decodes real PDO rows on ' + driver for c in cases), 'Missing actual PDO Pest case')
    require(all(c.get('class', '').startswith('Tests\\') for c in cases), 'PHPUnit-only or unexpected test class')
    for suite in xml.findall('.//testsuite'):
        require(all(int(suite.get(k, '0')) == 0 for k in ('failures', 'errors', 'skipped')), 'Failed JUnit suite')


def mutation_report(path):
    report = json.loads(path.read_text())
    require(set(report) == {'stats', *STATUSES}, 'Unknown or missing Infection report fields')
    stats = report['stats']
    require(stats.get('totalMutantsCount', 0) > 0, 'No mutants generated')
    total = 0
    for group, counter in STATUSES.items():
        rows = report[group]
        require(isinstance(rows, list) and len(rows) == stats.get(counter), 'Infection count mismatch: ' + group)
        total += len(rows)
        for row in rows:
            require(row.get('diff') and row.get('mutator', {}).get('originalSourceCode') !=
                    row.get('mutator', {}).get('mutatedSourceCode'), 'Fake/non-mutating report row')
    require(total == stats['totalMutantsCount'] and stats.get('skippedCount') == 0, 'Incomplete mutation report')
    require(all(stats.get(k) == 0 for k in ('syntaxErrorCount', 'ignoredCount', 'killedByStaticAnalysisCount')), 'Unexpected mutant disposition')
    require(report['killed'] and all(r['mutator']['originalFilePath'].endswith('/DatabaseValue.php') and
                'FAILED' in r['processOutput'] and re.search(r'Tests:\s+[1-9][0-9]* failed', r['processOutput']) and
                not re.search(r'SQLSTATE\[|connection refused|Could not open input file', r['processOutput'], re.I)
                for r in report['killed']), 'No real package mutant killed by Pest, or an infrastructure failure')
    for group, method, marker in [('escaped', 'escaped', 'passed'), ('uncovered', 'uncovered', ''),
                                   ('timeouted', 'timeout', 'SPIKE_TIMEOUT_CONTROL'),
                                   ('errored', 'error', 'SPIKE_ERROR_CONTROL')]:
        require(len(report[group]) == 1, 'Missing/extra ' + group + ' control')
        row = report[group][0]
        require(row['mutator']['originalFilePath'].endswith('/Controls.php') and
                'function ' + method + '(' in row['mutator']['originalSourceCode'] and
                marker in row['processOutput'], 'Wrong ' + group + ' control')
    return stats


def check_receipt(receipt, output, root=ROOT):
    require(receipt.get('schema') == SCHEMA and receipt.get('result') == 'compatible', 'Incomplete/fake receipt')
    return check_rows(receipt, output, root, complete=True)


def check_rows(receipt, output, root=ROOT, complete=False):
    """Verify partial diagnostics without granting full-matrix compatibility."""
    policy = json.loads((root / POLICY.relative_to(ROOT)).read_text())
    check_policy(policy)
    require(receipt.get('schema') == SCHEMA, 'Unknown receipt schema')
    require(receipt.get('adapter') == policy['adapter'], 'Unverified adapter')
    require(receipt.get('inputs') == inputs(root, policy), 'Stale product/tool/lock inputs')
    expected = set(itertools.product(policy['php'], policy['laravel'], policy['drivers']))
    rows = receipt.get('rows', [])
    require(len(rows) == len(expected) if complete else 0 < len(rows) <= len(expected), 'Missing or duplicated matrix row')
    seen = set()
    for row in rows:
        identity = (row.get('php'), row.get('laravel'), row.get('driver'))
        require(identity in expected and identity not in seen, 'Invalid/duplicate matrix row')
        seen.add(identity)
        require(row.get('result') == 'passed', 'Failed matrix row')
        lock = json.loads(read_artifact(row['lock'], output).read_text())
        locked = {p['name']: p['version'].lstrip('v') for p in lock['packages'] + lock.get('packages-dev', [])}
        versions = json.loads(read_artifact(row['versions'], output).read_text())
        require(all(locked.get(k) == v and versions.get(k) == v for k, v in policy['versions'].items()), 'Version downgrade/mismatch')
        require(versions.get('php', '').startswith(row['php'] + '.') and
                versions.get('laravel/framework') == locked.get('laravel/framework') and
                locked['laravel/framework'].startswith(row['laravel'] + '.'), 'Runtime matrix/version mismatch')
        binary = Path(row['php_binary']['path'])
        require(binary.is_file() and sha(binary) == row['php_binary']['sha256'], 'Changed PHP executable')
        require('--with-uncovered' in read_artifact(row['help'], output).read_text() and
                '--with-timeouts' in read_artifact(row['help'], output).read_text(), 'Missing locked command flags')
        for name in ('pest', 'infection'):
            process = row[name]
            require(process.get('exit') == 0, 'Failed ' + name + ' process')
            stdout = read_artifact(process['stdout'], output).read_text()
            read_artifact(process['stderr'], output)
            require(any(arg.endswith('/corex-pest') for arg in process['argv']) if name == 'pest'
                    else all(flag in process['argv'] for flag in policy['required_flags']), 'PHPUnit-only/missing flags')
            if name == 'pest':
                require(re.search(r'Tests:\s+13 passed\s+\(\d+ assertions\)', stdout), 'Fake/empty Pest output')
        trace = [json.loads(line) for line in read_artifact(row['trace'], output).read_text().splitlines()]
        require(len(trace) >= 4 and all(t['executable'].endswith('/vendor/pestphp/pest/bin/pest') and
                t['php_version'] == versions['php'] for t in trace), 'Missing actual Pest process trace')
        require(any(any(a.startswith('--configuration=') and '/pest.' in a for a in t['argv']) for t in trace), 'No actual Pest mutant process')
        junit(read_artifact(row['junit'], output), row['driver'])
        coverage_files = [read_artifact(a, output) for a in row['coverage']]
        require(any(p.name == 'index.xml' for p in coverage_files) and len(coverage_files) >= 3, 'Missing coverage XML')
        index = next(p for p in coverage_files if p.name == 'index.xml')
        tests = ET.parse(index).findall('.//{*}test')
        require(len(tests) == 13 and all(t.get('name', '').startswith('P\\Tests\\') and
                t.get('status') == 'success' for t in tests), 'Missing actual Pest coverage tests')
        require(any('DatabaseValue.php' in p.name and ET.parse(p).findall('.//{*}covered') for p in coverage_files), 'Missing covered package source')
        stats = mutation_report(read_artifact(row['mutants'], output))
        require(row['mutant_stats'] == stats, 'Counterfeit report summary')
        require(len(trace) == stats['totalMutantsCount'] - stats['notCoveredCount'] + 2,
                'Missing/extra Pest initial or mutant invocation')
        witness = json.loads(read_artifact(row['database'], output).read_text())
        require(witness['driver'] == row['driver'] and witness['laravel'] == versions['laravel/framework'], 'Wrong actual database/Laravel')
        require(witness.get('server_version') and (witness['database'] == ':memory:' if row['driver'] == 'sqlite'
                else re.fullmatch(r'[a-z0-9_]+_test', witness['database'])), 'Unsafe/missing database proof')
        for ref in row['harness']:
            read_artifact(ref, output)
    return len(rows)


def check_policy(policy):
    require(policy.get('schema') == 'corex-infection-compatibility-policy/v2' and
            policy.get('php') == ['8.4', '8.5'] and policy.get('laravel') == ['13'] and
            policy.get('drivers') == ['sqlite', 'pgsql'], 'Unsupported platform policy')


def platform_manifests(root):
    return [root / 'composer.json', *sorted(root.glob('packages/*/composer.json')),
            *sorted(root.glob('products/*/composer.json'))]


def platform_declarations(data):
    for section in ('require', 'require-dev'):
        for name in data.get(section, {}):
            if name == 'laravel/framework' or name.startswith('illuminate/'):
                data[section][name] = '^13.0'
            elif name == 'orchestra/testbench':
                data[section][name] = '^11.3'
            elif name == 'orchestra/testbench-core':
                data[section][name] = '^11.5'
    return data


def base_json(path, root):
    return json.loads(subprocess.check_output(['git', 'show', PLATFORM_BASE + ':' + str(path.relative_to(root))],
                                            cwd=root, text=True))


def check_platform_contract(root=ROOT):
    """Prove the finite D132 delta against its committed pre-amendment baseline."""
    manifests = platform_manifests(root)
    for path in manifests:
        require(json.loads(path.read_text()) == platform_declarations(base_json(path, root)),
                'Unexpected platform manifest delta: ' + str(path.relative_to(root)))
    baseline = base_json(root / 'composer.lock', root)
    current = json.loads((root / 'composer.lock').read_text())
    expected = json.loads(json.dumps(baseline))
    refreshed = []
    for package in expected['packages'] + expected['packages-dev']:
        dist = package.get('dist', {})
        if dist.get('type') != 'path' or not dist.get('url', '').startswith(('packages/', 'products/')):
            continue
        manifest = root / dist['url'] / 'composer.json'
        data = json.loads(manifest.read_text())
        for section in ('require', 'require-dev'):
            if section in data:
                package[section] = data[section]
        dist['reference'] = hashlib.sha1(manifest.read_bytes() + b'a:0:{}').hexdigest()
        refreshed.append(package['name'])
    # Composer itself owns the root content hash; do not approximate its JSON serialization.
    php = shutil.which('php8.4')
    require(php is not None, 'PHP 8.4 is unavailable')
    expression = "require 'vendor/autoload.php'; echo Composer\\Package\\Locker::getContentHash(file_get_contents('composer.json'));"
    expected['content-hash'] = subprocess.check_output([php, '-r', expression], cwd=root, text=True)
    require(current == expected, 'Unexpected root lock delta: upstream records/pins must stay unchanged')
    return {'baseline_commit': PLATFORM_BASE, 'manifests': len(manifests),
            'refreshed_first_party': refreshed, 'content_hash': current['content-hash'],
            'upstream_records_unchanged': True}


def check_ci_platform(root=ROOT):
    """Prove that ordinary CI changed only the exact retired Laravel 12 cells/steps."""
    path = root / '.github/workflows/ci.yml'
    baseline_text = subprocess.check_output(['git', 'show', PLATFORM_BASE + ':.github/workflows/ci.yml'],
                                            cwd=root, text=True)
    expected = baseline_text.replace("laravel: ['12.*', '13.*']", "laravel: ['13.*']")
    expected = expected.replace('permissions: {}\n\njobs:',
                                'permissions: {}\n\nenv:\n  COMPOSER_ROOT_VERSION: dev-main\n\njobs:')
    expected = re.sub(r"      - name: (?:Root )?Laravel 12 test stack\n"
                      r"        if: matrix.laravel == '12\.\*'\n        run: [^\n]+\n\n", '', expected)
    current = path.read_text()
    require(current == expected, 'Ordinary CI platform/installer drift beyond D132')
    cells = [m.group(1) for m in re.finditer(r'^  ([\w-]+):\n(.*?)(?=^  [\w-]+:|\Z)', current, re.M | re.S)
             if "laravel: ['13.*']" in m.group(2)]
    require("php: ['8.4', '8.5']" in current, 'Missing PHP CI lane')
    require(cells and 'tests-pg' in cells and 'tenancy-tests' in cells, 'Missing database CI lane')
    return cells


def check_platform_matrix(output, root=ROOT):
    contract = check_platform_contract(root)
    ci_jobs = check_ci_platform(root)
    output.mkdir(parents=True, exist_ok=True)
    policy = json.loads(POLICY.read_text())
    check_policy(policy)
    receipt = {'schema': 'corex-platform-matrix/v2', 'result': 'running', 'inputs': inputs(root, policy),
               'contract': contract, 'ci_jobs': ci_jobs, 'cells': []}
    lock = json.loads((root / 'composer.lock').read_text())
    pinned = {p['name']: p['version'] for p in lock['packages'] + lock['packages-dev']
              if p.get('dist', {}).get('type') != 'path'}
    for php in policy['php']:
        work = output / ('php' + php + '-laravel13')
        work.mkdir(parents=True, exist_ok=True)
        for path in platform_manifests(root) + [root / 'composer.lock']:
            dest = work / path.relative_to(root)
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(path, dest)
        shutil.copytree(root / ADAPTER, work / ADAPTER, dirs_exist_ok=True)
        binary = shutil.which('php' + php)
        require(binary is not None, 'PHP ' + php + ' is unavailable')
        binary = Path(binary).resolve()
        env = php_environment(binary, work)
        env['COMPOSER_ROOT_VERSION'] = 'dev-main'
        args = [binary, shutil.which('composer'), 'update', '--no-install', '--no-scripts', '--no-plugins', '--no-progress']
        for name, version in pinned.items():
            args += ['--with', name + ':' + version]
        solve = run(args, work, env, work / 'solve', output, timeout=1200)
        platform = run([binary, shutil.which('composer'), 'check-platform-reqs', '--lock', '--no-plugins'],
                       work, env, work / 'platform', output)
        audit = run([binary, shutil.which('composer'), 'audit', '--locked', '--no-plugins', '--format=json'],
                    work, env, work / 'audit', output)
        resolved = json.loads((work / 'composer.lock').read_text())
        versions = {p['name']: p['version'].lstrip('v') for p in resolved['packages'] + resolved['packages-dev']}
        require(all(versions.get(k) == v for k, v in policy['versions'].items()), 'Platform solve changed engine pins')
        require(versions.get('laravel/framework', '').startswith('13.'), 'Unsupported framework solve')
        receipt['cells'].append({'php': php, 'laravel': '13', 'binary': {'path': str(binary), 'sha256': sha(binary)},
                                 'solve': solve, 'platform': platform, 'audit': audit,
                                 'lock': artifact(work / 'composer.lock', output), 'versions': versions})
        save(output / 'receipt.json', receipt)
    receipt['result'] = 'compatible'
    save(output / 'receipt.json', receipt)
    return receipt


def run(argv, cwd, env, log, output, timeout=600, accept=(0,)):
    log.parent.mkdir(parents=True, exist_ok=True)
    start = time.monotonic()
    with log.with_suffix('.stdout').open('w') as stdout, log.with_suffix('.stderr').open('w') as stderr:
        result = subprocess.run(list(map(str, argv)), cwd=cwd, env=env, stdout=stdout, stderr=stderr, timeout=timeout)
    record = {'argv': list(map(str, argv)), 'cwd': str(cwd), 'exit': result.returncode,
              'duration_seconds': round(time.monotonic() - start, 3),
              'stdout': artifact(log.with_suffix('.stdout'), output), 'stderr': artifact(log.with_suffix('.stderr'), output)}
    save(log.with_suffix('.process.json'), record)
    require(result.returncode in accept, 'Process failed (' + str(result.returncode) + '): ' + str(log))
    return record


def php_environment(binary, work):
    shim = work / 'php-bin'
    shim.mkdir(parents=True, exist_ok=True)
    link = shim / 'php'
    if link.is_symlink():
        link.unlink()
    link.symlink_to(binary)
    env = {**os.environ, 'PATH': str(shim) + os.pathsep + os.environ['PATH'],
           'XDEBUG_MODE': 'coverage', 'PAO_DISABLE': '1', 'COMPOSER_NO_INTERACTION': '1'}
    if binary.name == 'php8.5' and os.environ.get('COREX_PHP85_INI_SCAN_DIR'):
        env['PHP_INI_SCAN_DIR'] = os.environ['COREX_PHP85_INI_SCAN_DIR']
    return env


def prepare_cell(php, laravel, output, policy):
    work = output / 'work' / ('php' + php + '-laravel' + laravel)
    work.mkdir(parents=True, exist_ok=True)
    binary = shutil.which('php' + php)
    require(binary is not None, 'PHP ' + php + ' is unavailable')
    binary = Path(binary).resolve()
    env = php_environment(binary, work)
    packages = {'php': '^8.4', 'laravel/framework': '^' + laravel + '.0', **policy['versions']}
    manifest = {'name': 'corex/infection-spike', 'type': 'project', 'require-dev': packages,
                'repositories': [{'type': 'path', 'url': ADAPTER, 'options': {
                    'symlink': False, 'versions': {'axiomasoft/corex-infection-pest-adapter': '0.1.0'}}}],
                'autoload': {'psr-4': {'CoreX\\Tenancy\\': 'packages/tenancy/src/',
                                      'CoreX\\InfectionSpike\\': 'controls/'}},
                'config': {'allow-plugins': {'pestphp/pest-plugin': True, 'infection/extension-installer': True}},
                'minimum-stability': 'stable', 'prefer-stable': True}
    previous = (work / 'composer.json').read_text() if (work / 'composer.json').exists() else None
    save(work / 'composer.json', manifest)
    changed = previous != (work / 'composer.json').read_text()
    for path in (ROOT / ADAPTER).rglob('*'):
        if path.is_file():
            target = work / path.relative_to(ROOT)
            target.parent.mkdir(parents=True, exist_ok=True)
            changed |= not target.exists() or sha(path) != sha(target)
            shutil.copy2(path, target)
    for path, target in [(policy['source'], policy['source']), (policy['test'], 'tests/DatabaseValueTest.php')]:
        dest = work / target
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(ROOT / path, dest)
    (work / 'controls').mkdir(exist_ok=True)
    (work / 'controls/Controls.php').write_text(CONTROL_SOURCE)
    (work / 'tests/ControlsTest.php').write_text(CONTROL_TEST)
    (work / 'tests/DatabaseProbeTest.php').write_text(PROBE)
    (work / 'tests/Pest.php').write_text('<?php\ndeclare(strict_types=1);\n')
    # Reinstall the local extension on changes, even though its bounded package version is fixed.
    if (changed or not (work / 'vendor/axiomasoft/corex-infection-pest-adapter/src/PestAdapter.php').exists()) and (work / 'vendor').exists():
        shutil.rmtree(work / 'vendor')
    action = 'update' if changed or not (work / 'composer.lock').exists() else 'install'
    run([binary, shutil.which('composer'), action, '--no-scripts', '--no-progress'], work, env, work / 'solve', output)
    return work, binary, env


def run_row(work, binary, env, php, laravel, driver, output, policy):
    rowdir = output / 'rows' / ('php' + php + '-laravel' + laravel + '-' + driver)
    if rowdir.exists():
        shutil.rmtree(rowdir)
    rowdir.mkdir(parents=True)
    env = {**env, 'SPIKE_DRIVER': driver, 'SPIKE_DB_WITNESS': str(rowdir / 'database.json'),
           'COREX_INFECTION_TRACE': str(rowdir / 'pest-trace.jsonl'),
           'PGSQL_HOST': os.environ.get('PGSQL_HOST', '127.0.0.1'),
           'PGSQL_PORT': os.environ.get('PGSQL_PORT', '5434'),
           'PGSQL_DATABASE': os.environ.get('PGSQL_DATABASE', 'corex_test'),
           'PGSQL_USERNAME': os.environ.get('PGSQL_USERNAME', 'corex'),
           'PGSQL_PASSWORD': os.environ.get('PGSQL_PASSWORD', 'corex')}
    require(re.fullmatch(r'[a-z0-9_]+_test', env['PGSQL_DATABASE']), 'Unsafe PG test database')
    if driver == 'pgsql':
        run(['pg_isready', '-h', env['PGSQL_HOST'], '-p', env['PGSQL_PORT'], '-U', env['PGSQL_USERNAME'], '-d', env['PGSQL_DATABASE']],
            work, env, rowdir / 'pg-ready', output)
        run(['psql', '-h', env['PGSQL_HOST'], '-p', env['PGSQL_PORT'], '-U', env['PGSQL_USERNAME'],
             '-d', env['PGSQL_DATABASE'], '-Atqc', 'SELECT current_database(), current_setting(\'server_version\');'],
            work, {**env, 'PGPASSWORD': env['PGSQL_PASSWORD']}, rowdir / 'pg-database', output)
    config = ('<phpunit bootstrap="' + str(work / 'vendor/autoload.php') + '" failOnWarning="true" failOnRisky="true" '
              'failOnEmptyTestSuite="true"><testsuites><testsuite name="spike"><directory>' + str(work / 'tests') +
              '</directory></testsuite></testsuites><source><include><directory>' + str(work / 'packages/tenancy/src') +
              '</directory><directory>' + str(work / 'controls') + '</directory></include></source></phpunit>')
    (work / 'pest-spike.xml').write_text(config)
    (rowdir / 'pest-spike.xml').write_text(config)
    shutil.copy2(work / 'composer.lock', rowdir / 'composer.lock')
    expression = "require 'vendor/autoload.php'; echo json_encode(['php'=>PHP_VERSION,'coverage_driver'=>extension_loaded('pcov')?'pcov':(extension_loaded('xdebug')?'xdebug':null),'laravel/framework'=>Illuminate\\Foundation\\Application::VERSION]+array_map(fn($p)=>ltrim(Composer\\InstalledVersions::getPrettyVersion($p),'v'),array_combine(" + json.dumps(list(policy['versions'])) + ',' + json.dumps(list(policy['versions'])) + ')));'
    versions = subprocess.check_output([str(binary), '-r', expression], cwd=work, env=env, text=True)
    parsed = json.loads(versions)
    require(parsed['coverage_driver'] is not None, 'No coverage driver for PHP ' + php)
    save(rowdir / 'versions.json', parsed)
    help_run = run([binary, work / 'vendor/bin/infection', '--help'], work, env, rowdir / 'help', output)
    pest = run([binary, '-d', 'pcov.directory=' + str(work), work / 'vendor/bin/corex-pest',
                '--configuration=' + str(work / 'pest-spike.xml'), '--compact', '--colors=never'], work, env, rowdir / 'pest', output)
    cfg = {'source': {'directories': [str(work / 'packages/tenancy/src'), str(work / 'controls')]},
           'testFramework': policy['adapter'], 'timeout': policy['timeout_seconds'],
           'tmpDir': str(rowdir / 'tmp'), 'logs': {'json': str(rowdir / 'mutants.json')},
           'mutators': {'LogicalNot': True, 'FalseValue': True}}
    save(rowdir / 'infection.json', cfg)
    infection = run([binary, '-d', 'pcov.directory=' + str(work), work / 'vendor/bin/infection',
                     '--configuration=' + str(rowdir / 'infection.json'), '--no-progress', *policy['required_flags']],
                    work, env, rowdir / 'infection', output)
    raw = rowdir / 'tmp/infection'
    files = list((raw / 'coverage-xml').rglob('*.xml'))
    # Only evidence is copied at completion; work/ retains installations for deterministic replay.
    return {'php': php, 'laravel': laravel, 'driver': driver, 'result': 'passed',
            'php_binary': {'path': str(binary), 'sha256': sha(binary)},
            'pest': pest, 'infection': infection, 'help': help_run['stdout'],
            'versions': artifact(rowdir / 'versions.json', output), 'lock': artifact(rowdir / 'composer.lock', output),
            'junit': artifact(raw / 'junit.xml', output), 'coverage': [artifact(p, output) for p in sorted(files)],
            'mutants': artifact(rowdir / 'mutants.json', output), 'mutant_stats': mutation_report(rowdir / 'mutants.json'),
            'trace': artifact(rowdir / 'pest-trace.jsonl', output), 'database': artifact(rowdir / 'database.json', output),
            'harness': [artifact(rowdir / p, output) for p in ('pest-spike.xml', 'infection.json')]}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--package', choices=['tenancy'], default='tenancy')
    parser.add_argument('--matrix', choices=['all'], default='all')
    parser.add_argument('--output', type=Path, default=ROOT / 'build/quality/infection-spike')
    parser.add_argument('--check', type=Path)
    parser.add_argument('--check-platform-contract', action='store_true')
    parser.add_argument('--check-platform-matrix', action='store_true')
    args = parser.parse_args()
    try:
        if args.check_platform_contract:
            proof = check_platform_contract()
            print('Platform contract OK: ' + str(proof['manifests']) + ' manifests; upstream lock unchanged')
            return 0
        if args.check_platform_matrix:
            check_platform_matrix(ROOT / 'build/quality/infection-platform-laravel13')
            print('Platform matrix OK: PHP 8.4/8.5 × Laravel 13; ordinary CI preserved')
            return 0
        if args.check:
            n = check_receipt(json.loads(args.check.read_text()), args.check.resolve().parent)
            print('Infection compatibility OK: ' + str(n) + ' actual matrix rows')
            return 0
        output = args.output.resolve()
        require(output.is_relative_to(ROOT / 'build'), 'Spike output must be inside the repository build directory')
        output.mkdir(parents=True, exist_ok=True)
        policy = json.loads(POLICY.read_text())
        check_policy(policy)
        start = time.monotonic()
        receipt = {'schema': SCHEMA, 'result': 'running', 'adapter': policy['adapter'],
                   'inputs': inputs(ROOT, policy), 'rows': [], 'failed_cells': []}
        save(output / 'receipt.json', receipt)
        for php, laravel in itertools.product(policy['php'], policy['laravel']):
            require(time.monotonic() - start < policy['budget_seconds'], 'Bounded adapter budget exhausted')
            print('Preparing PHP ' + php + ' / Laravel ' + laravel, flush=True)
            try:
                work, binary, env = prepare_cell(php, laravel, output, policy)
            except ProofError as exc:
                work = output / 'work' / ('php' + php + '-laravel' + laravel)
                failure = {'php': php, 'laravel': laravel, 'drivers_not_executed': policy['drivers'],
                           'stage': 'dependency-solve', 'diagnostic': str(exc)}
                record = work / 'solve.process.json'
                if record.exists():
                    failure['process'] = json.loads(record.read_text())
                receipt['failed_cells'].append(failure)
                save(output / 'receipt.json', receipt)
                print('Dependency solve RED: ' + php + '/' + laravel, flush=True)
                continue
            for driver in policy['drivers']:
                print('Executing ' + php + '/' + laravel + '/' + driver, flush=True)
                receipt['rows'].append(run_row(work, binary, env, php, laravel, driver, output, policy))
                save(output / 'receipt.json', receipt)
        receipt['duration_seconds'] = round(time.monotonic() - start, 3)
        if receipt['rows']:
            receipt['verified_partial_rows'] = check_rows(receipt, output)
        if receipt['failed_cells']:
            receipt['result'] = 'incompatible'
            save(output / 'receipt.json', receipt)
            raise ProofError('Unresolved dependency conflicts; partial rows cannot satisfy the matrix')
        # Compatibility is persisted only after validation succeeds; a verifier error stays running/RED.
        receipt['result'] = 'compatible'
        check_receipt(receipt, output)
        save(output / 'receipt.json', receipt)
        print('Infection compatibility OK: ' + str(len(receipt['rows'])) + ' actual matrix rows')
        return 0
    except (ProofError, OSError, ValueError, KeyError, TypeError, ET.ParseError, subprocess.SubprocessError) as exc:
        print('Infection compatibility RED: ' + str(exc), file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
