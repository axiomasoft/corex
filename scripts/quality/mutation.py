#!/usr/bin/env python3
"""Fail-closed package × zone mutation runner for the pinned Infection/Pest stack."""
import argparse
from collections import Counter
from decimal import Decimal
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import signal
import subprocess
import sys
import tempfile
import time
import uuid
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[2]
QUALITY = Path('scripts/quality')
THRESHOLDS = {'security': 95, 'messaging': 90, 'other': 75}
GROUPS = {'killed': 'killedCount', 'escaped': 'escapedCount', 'timeouted': 'timeOutCount',
          'errored': 'errorCount', 'uncovered': 'notCoveredCount', 'syntaxErrors': 'syntaxErrorCount',
          'killedByStaticAnalysis': 'killedByStaticAnalysisCount', 'ignored': 'ignoredCount'}
STATS = {'totalMutantsCount', 'skippedCount', 'msi', 'mutationCodeCoverage', 'coveredCodeMsi', *GROUPS.values()}
SECURITY_PACKAGES = {'access', 'tenancy', 'tools', 'webhooks'}
MESSAGING_PACKAGES = {'notifications', 'scheduler', 'workflow', 'recurring-tasks', 'import-export'}
SECURITY_CORE = ('Context/', 'Tenancy/', 'Database/', 'Data/', 'Foundation/Internal/PackmanActorResolver.php',
                 'Foundation/Internal/PackmanContextResolver.php', 'Foundation/Internal/PackmanFeatureFlags.php')
ANCHORS = ('Foundation/Internal/PackmanActorResolver.php', 'Foundation/Internal/PackmanContextResolver.php',
           'Foundation/Internal/PackmanFeatureFlags.php',
           'Data/Contracts/DataScopeAuthorizer.php', 'Data/Contracts/FieldUsePolicy.php',
           'Data/Internal/DenyDataScopeAuthorizer.php', 'Data/Internal/CallableDataScopeAuthorizer.php',
           'Context/Internal/VerifiedWorkScope.php', 'Context/Laravel/DatabaseScopeControl.php')
INFRA = re.compile(r'connection refused|could not find driver|SQLSTATE\[(?:080\w*|3D000)\]|'
                   r'Allowed memory size|out of memory|Segmentation fault|Pinned Pest executable is missing|'
                   r'Could not open input file|Could not execute the pinned Pest|No tests (?:executed|found)|'
                   r'PHPUnit.*(?:discovery|bootstrap).*error|Cannot open bootstrap script|'
                   r'failed opening required|SPIKE_ERROR_CONTROL|exit.?137', re.I)
COMPILE = re.compile(r'Cannot redeclare|Declaration of .*must be compatible|Cannot (?:extend|override|inherit)|'
                     r'contains .*abstract method|Type of .*must be|Cannot use .* as .*because|'
                     r'Access level to .*must be|Cannot make .*method.*(?:static|non.static)', re.I)
FLAGS = ['--with-uncovered', '--with-timeouts', '--threads=1', '--log-verbosity=all', '--debug', '--no-progress', '--quiet']


class MutationError(ValueError):
    pass


def require(ok, message):
    if not ok:
        raise MutationError(message)


def digest(data):
    return hashlib.sha256(data).hexdigest()


def sha(path):
    return digest(Path(path).read_bytes())


def load(path):
    def pairs(items):
        result = {}
        for key, value in items:
            require(key not in result, 'Duplicate JSON field: ' + key)
            result[key] = value
        return result
    return json.loads(Path(path).read_text(), object_pairs_hook=pairs)


def save(path, data):
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, indent=2, sort_keys=True, ensure_ascii=False) + '\n')


def expected_zone(path):
    parts = Path(path).parts
    require(len(parts) >= 4 and parts[2] == 'src', 'Invalid production path: ' + path)
    package = parts[1]
    relative = '/'.join(parts[3:])
    if parts[0] == 'products' and package == 'flex-crm' and relative.startswith('Context/'):
        return 'security'
    if parts[0] == 'packages':
        if package in SECURITY_PACKAGES:
            return 'security'
        if package == 'core':
            if relative.startswith(SECURITY_CORE):
                return 'security'
            if relative.startswith('Messaging/'):
                return 'messaging'
        if package == 'operations':
            return 'security' if relative.startswith('Authorization/') else 'messaging'
        if package in MESSAGING_PACKAGES:
            return 'messaging'
    return 'other'


def production_files(root):
    files = sorted(str(p.relative_to(root)) for pattern in ('packages/*/src/**/*.php', 'products/flex-crm/src/**/*.php')
                   for p in root.glob(pattern) if p.is_file())
    require(files, 'No production PHP source')
    tracked = subprocess.run(['git', 'ls-files', '-z', '--', 'packages', 'products/flex-crm'],
                             cwd=root, capture_output=True, check=True).stdout.decode().split('\0')
    for path in tracked:
        if re.match(r'(?:packages/[^/]+|products/flex-crm)/src/.*\.php$', path):
            require(path in files, 'Missing committed source: ' + path)
    for path in files:
        require(not (root / path).is_symlink(), 'Symlink production file: ' + path)
    return files


def ast_sources(root, sources):
    result = subprocess.run(['php', str(ROOT / QUALITY / 'mutation-ast.php')], cwd=root,
                            input=json.dumps(sources),
                            text=True, capture_output=True, timeout=120)
    require(result.returncode == 0, 'PHPParser inventory failed: ' + result.stderr[:1000])
    return json.loads(result.stdout)


def ast_inventory(root, files):
    return ast_sources(root, {p: (root / p).read_text() for p in files})


def make_map(files):
    fragments = {}
    for path in files:
        package = path.split('/')[1]
        zone = expected_zone(path)
        fragments.setdefault((package, zone), []).append(path)
    return {'schema': 'corex-mutation-zones/1', 'zones': THRESHOLDS,
            'fragments': [{'package': p, 'zone': z, 'files': f} for (p, z), f in sorted(fragments.items())]}


def check_inventory(mapping, root, ast=None, zero=None):
    require(mapping.get('schema') == 'corex-mutation-zones/1', 'Unknown zone map schema')
    require(mapping.get('zones') == THRESHOLDS, 'MSI thresholds must remain 95/90/75')
    files = production_files(root)
    seen = set()
    identities = set()
    for fragment in mapping.get('fragments', []):
        identity = (fragment.get('package'), fragment.get('zone'))
        require(identity not in identities, 'Duplicate package-fragment: ' + str(identity))
        identities.add(identity)
        require(fragment.get('files'), 'Empty production fragment: ' + str(identity))
        for path in fragment['files']:
            require(path in files, 'Missing/nonproduction configured path: ' + path)
            require(path not in seen, 'Multiply assigned source: ' + path)
            require(identity == (path.split('/')[1], expected_zone(path)), 'Wrong zone/package for ' + path)
            seen.add(path)
    missing = sorted(set(files) - seen)
    require(not missing, 'Unassigned source: ' + ', '.join(missing[:10]))
    for anchor in ANCHORS:
        path = 'packages/core/src/' + anchor
        require(path in seen and expected_zone(path) == 'security', 'Missing security95 anchor: ' + path)
    if ast is not None:
        require(set(ast) == seen, 'Incomplete AST source inventory')
        require(zero is not None and zero.get('schema') == 'corex-mutation-zero-ledger/1', 'Missing zero ledger')
        expected = {p: {'sha256': a['sha256'], 'reason': a['reason']} for p, a in ast.items() if a['declaration_only']}
        require(zero.get('files') == expected, 'Stale/incomplete AST declaration ledger')
        for zone in THRESHOLDS:
            require(any(expected_zone(p) == zone and not ast[p]['declaration_only'] for p in seen),
                    'Empty behavioral zone: ' + zone)
    return files


def fraction(killed, valid, threshold):
    require(type(killed) is int and type(valid) is int and valid > 0 and 0 <= killed <= valid, 'Invalid MSI arithmetic')
    return {'killed': killed, 'valid': valid, 'msi': str(Decimal(100) * killed / valid),
            'threshold': threshold, 'passed': 100 * killed >= threshold * valid}


def mutant_key(row, relative, source_hash):
    m = row['mutator']
    value = [relative, source_hash, m['mutatorName'], m['originalStartLine'], digest(m['mutatedSourceCode'].encode())]
    return digest(json.dumps(value, separators=(',', ':')).encode())


def confirmation(ref, root, identity):
    require(isinstance(ref, dict) and set(ref) == {'path', 'sha256'}, 'Missing independent confirmation')
    path = (root / ref['path']).resolve()
    require(path.is_relative_to(root.resolve()) and path.is_file() and sha(path) == ref['sha256'], 'Stale confirmation')
    report = load(path)
    if 'schema' in report:
        recognized = report['schema'] == 'corex-mutation-confirmation/1'
    else:
        legacy_item = load(ROOT / QUALITY / 'infection-compatibility.json')['legacy_confirmation_review_item']
        recognized = report.get('review_item') == legacy_item
    require(recognized and report.get('verdict') == 'confirmed' and
            identity in report.get('confirmed', []), 'Unconfirmed exception: ' + identity)
    return report


def equivalent_ids(ledger, root, rows, valid):
    require(ledger.get('schema') == 'corex-mutation-equivalents/1' and ledger.get('cap_percent') == 5,
            'Equivalent cap must remain exactly 5%')
    excluded = set()
    for entry in ledger.get('entries', []):
        if entry.get('id') not in rows:
            continue
        key = entry['id']
        row, path, source_hash, group = rows[key]
        require(key not in excluded and group in ('escaped', 'uncovered', 'timeouted'), 'Invalid equivalent disposition')
        require(entry.get('path') == path and entry.get('source_sha256') == source_hash and entry.get('reason'),
                'Stale equivalent source/rationale')
        replay = entry.get('replay', {})
        require(replay.get('path') and replay.get('sha256'), 'Missing equivalent replay')
        replay_path = (root / replay['path']).resolve()
        require(replay_path.is_relative_to(root.resolve()) and replay_path.is_file() and sha(replay_path) == replay['sha256'],
                'Stale equivalent replay')
        confirmation(entry.get('confirmation'), root, key)
        excluded.add(key)
    require(100 * len(excluded) <= 5 * valid, 'Equivalent exclusions exceed fragment 5% cap')
    return excluded


def check_equivalent_ledger(ledger, root, ast):
    require(ledger.get('schema') == 'corex-mutation-equivalents/1' and ledger.get('cap_percent') == 5 and
            isinstance(ledger.get('entries'), list), 'Equivalent cap/schema changed')
    seen = set()
    for entry in ledger['entries']:
        require(isinstance(entry, dict) and isinstance(entry.get('id'), str) and entry['id'] not in seen,
                'Duplicated/invalid equivalent entry')
        seen.add(entry['id'])
        path = entry.get('path')
        require(path in ast and entry.get('source_sha256') == ast[path]['sha256'], 'Expired equivalent source: ' + str(path))
        confirmation(entry.get('confirmation'), root, entry['id'])


def parse_report(report, fragment, sandbox, ast, equivalent=None, proof=None, process_proof=None, exception_root=None):
    require(isinstance(report, dict) and set(report) == {'stats', *GROUPS}, 'Unknown/missing Infection JSON disposition')
    stats = report['stats']
    require(isinstance(stats, dict) and set(stats) == STATS, 'Unknown/missing Infection stats')
    for counter in ('totalMutantsCount', 'skippedCount', *GROUPS.values()):
        require(type(stats[counter]) is int and stats[counter] >= 0, 'Invalid counter: ' + counter)
    for name in ('msi', 'coveredCodeMsi', 'mutationCodeCoverage'):
        require(type(stats[name]) in (int, float) and 0 <= stats[name] <= 100, 'Invalid upstream percentage')
    require(stats['skippedCount'] == stats['ignoredCount'] == stats['killedByStaticAnalysisCount'] == 0,
            'Skipped/ignored/static-analysis mutant is not test evidence')
    total = 0
    originals = {}
    for group in GROUPS:
        require(isinstance(report[group], list), 'Invalid disposition list')
        for row in report[group]:
            require(isinstance(row, dict) and isinstance(row.get('mutator'), dict), 'Unknown mutant row')
            source = row['mutator'].get('originalSourceCode')
            require(isinstance(source, str), 'Missing original source')
            originals[digest(source.encode())] = source
    normalized = ast_sources(sandbox, originals) if originals else {}
    rows = {}
    counts = Counter()
    invalid = []
    killed = 0
    for group, counter in GROUPS.items():
        require(isinstance(report[group], list) and len(report[group]) == stats[counter], 'Count mismatch: ' + group)
        total += len(report[group])
        for row in report[group]:
            require(isinstance(row, dict) and set(row) == {'mutator', 'diff', 'processOutput'}, 'Unknown mutant row')
            m = row['mutator']
            require(isinstance(m, dict) and set(m) == {'mutatorName', 'originalSourceCode', 'mutatedSourceCode',
                                                      'originalFilePath', 'originalStartLine'}, 'Unknown mutator row')
            require(all(isinstance(m[k], str) and m[k] for k in ('mutatorName', 'originalSourceCode',
                                                               'mutatedSourceCode', 'originalFilePath')), 'Empty mutant data')
            require(type(m['originalStartLine']) is int and m['originalStartLine'] > 0 and row['diff'] and
                    m['originalSourceCode'] != m['mutatedSourceCode'] and isinstance(row['processOutput'], str), 'Fake mutant')
            path = Path(m['originalFilePath']).resolve()
            require(path.is_relative_to(sandbox.resolve()), 'Foreign mutant source')
            relative = str(path.relative_to(sandbox.resolve()))
            require(relative in fragment['files'], 'Unexpected fragment mutant: ' + relative)
            source_hash = ast[relative]['sha256']
            require(path.is_file() and sha(path) == source_hash, 'Changed original source: ' + relative)
            require(normalized[digest(m['originalSourceCode'].encode())]['normalized_sha256'] == ast[relative]['normalized_sha256'],
                    'Report original source mismatch: ' + relative)
            key = mutant_key(row, relative, source_hash)
            require(key not in rows, 'Duplicated mutant')
            rows[key] = (row, relative, source_hash, group)
            output = re.sub(r'\x1b\[[0-9;]*m', '', row['processOutput'])
            require(not INFRA.search(output), 'Infrastructure mutant failure: ' + relative)
            if process_proof is not None and group not in ('uncovered', 'syntaxErrors'):
                process_proof(row, group)
            exclusion = proof(row, key) if proof and group in ('killed', 'errored', 'syntaxErrors') else None
            if exclusion is not None:
                require(exclusion in ('syntax', 'compile'), 'Unknown invalid-mutant proof')
                invalid.append({'id': key, 'path': relative, 'kind': exclusion})
                continue
            require(group not in ('errored', 'syntaxErrors'), 'Unreproduced error mutant: ' + relative)
            if group == 'killed':
                require('FAILED' in output and re.search(r'Tests:\s+[1-9][0-9]* failed', output),
                        'Killed without real Pest test failure: ' + relative)
                killed += 1
            if group == 'escaped':
                require(re.search(r'Tests:\s+[1-9][0-9]* passed', output) and 'FAILED' not in output,
                        'Escaped without passing Pest output')
            if group == 'uncovered':
                require(output == '', 'Uncovered mutant unexpectedly executed')
            counts[relative] += 1
    require(total == stats['totalMutantsCount'] and total > 0, 'Zero/incomplete generated mutants')
    valid = total - len(invalid)
    for path in fragment['files']:
        require(ast[path]['declaration_only'] or counts[path] > 0, 'Behavioral file has zero valid mutants: ' + path)
    excluded = equivalent_ids(equivalent or {'schema': 'corex-mutation-equivalents/1', 'cap_percent': 5, 'entries': []},
                              exception_root or sandbox, rows, valid)
    for entry in (equivalent or {}).get('entries', []):
        require(entry.get('path') not in fragment['files'] or entry.get('id') in rows, 'Expired/unproduced equivalent mutant')
    score = fraction(killed, valid - len(excluded), THRESHOLDS[fragment['zone']])
    return {**score, 'generated': total, 'pre_equivalence_valid': valid, 'equivalents': sorted(excluded),
            'invalid': invalid, 'valid_per_file': dict(counts)}


def input_hashes(root):
    paths = set(production_files(root))
    for base in ('tests', 'packages', 'products/flex-crm', 'scripts/quality', 'scripts/ci', 'api', 'docs'):
        for p in (root / base).rglob('*'):
            if p.is_file() and 'vendor' not in p.parts and not any(x in p.parts for x in ('__pycache__', 'storage', 'build')) and \
                    '.cache' not in p.name and not any(part.startswith('.phpunit') for part in p.parts):
                paths.add(str(p.relative_to(root)))
    paths.update(('composer.json', 'composer.lock', 'phpunit.xml', '.github/workflows/ci.yml', 'scripts/php84'))
    native_config = os.environ.get('COREX_TEST_STORAGE_CONFIG') or os.environ.get('COREX_P3_STORAGE_CONFIG')
    if native_config:
        native_path = Path(native_config).resolve()
        require(native_path.is_relative_to(root.resolve()) and native_path.is_file(), 'Native test config must be inside the repository')
        paths.add(str(native_path.relative_to(root.resolve())))
    for package in ('infection/infection', 'pestphp/pest', 'phpunit/phpunit', 'phpunit/php-code-coverage', 'nikic/php-parser',
                    'aws/aws-sdk-php', 'league/flysystem-aws-s3-v3', 'mtdowling/jmespath.php', 'aws/aws-crt-php'):
        directory = root / 'vendor' / package
        require(directory.is_dir(), 'Missing locked tool: ' + package)
        paths.update(str(p.relative_to(root)) for p in directory.rglob('*') if p.is_file())
    return {p: sha(root / p) for p in sorted(paths)}


def artifact(path, base):
    return {'path': str(path.relative_to(base)), 'sha256': sha(path)}


def verify_artifact(ref, base):
    require(isinstance(ref, dict) and set(ref) == {'path', 'sha256'}, 'Missing artifact binding')
    path = (base / ref['path']).resolve()
    require(path.is_relative_to(base.resolve()) and path.is_file() and sha(path) == ref['sha256'], 'Missing/stale artifact')
    return path


def run(argv, cwd, env, stem, base, timeout=600, accepted=(0,)):
    start = time.monotonic()
    stem.parent.mkdir(parents=True, exist_ok=True)
    with stem.with_suffix('.stdout').open('w') as out, stem.with_suffix('.stderr').open('w') as err:
        process = subprocess.Popen(list(map(str, argv)), cwd=cwd, env=env, stdout=out, stderr=err, start_new_session=True)
        timed_out = False
        try:
            process.wait(timeout=timeout)
        except subprocess.TimeoutExpired:
            timed_out = True
            os.killpg(process.pid, signal.SIGTERM)
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                os.killpg(process.pid, signal.SIGKILL)
                process.wait()
    record = {'argv': list(map(str, argv)), 'exit': process.returncode, 'timed_out': timed_out,
              'duration_seconds': round(time.monotonic() - start, 3),
              'stdout': artifact(stem.with_suffix('.stdout'), base), 'stderr': artifact(stem.with_suffix('.stderr'), base)}
    save(stem.with_suffix('.process.json'), record)
    require(not timed_out and process.returncode in accepted, 'Process failed: ' + str(stem) + ' exit=' + str(process.returncode))
    return record


def check_junit(path):
    tree = ET.parse(path).getroot()
    cases = tree.findall('.//testcase')
    require(cases and all(not list(c) and int(c.get('assertions', '0')) > 0 for c in cases), 'Missing/failed/skipped/empty JUnit')
    for suite in tree.findall('.//testsuite'):
        require(all(int(suite.get(k, '0')) == 0 for k in ('failures', 'errors', 'skipped')), 'Failed JUnit suite')
    return len(cases)


def check_coverage(directory, files, sandbox):
    index = directory / 'index.xml'
    require(index.is_file(), 'Missing coverage XML index')
    tree = ET.parse(index).getroot()
    expected = {str((sandbox / p).resolve()) for p in files}
    covered = set()
    namespace = {'c': 'https://schema.phpunit.de/coverage/1.0'}
    project = tree.find('c:project', namespace)
    require(project is not None and project.get('source'), 'Missing coverage source root')
    source = Path(project.get('source'))
    def walk(node, relative):
        for child in node:
            if child.tag == '{' + namespace['c'] + '}directory':
                walk(child, relative / child.get('name', '').lstrip('/'))
            if child.tag == '{' + namespace['c'] + '}file':
                path = (source / relative / child.get('name', '')).resolve()
                reference = (directory / child.get('href', '')).resolve()
                require(reference.is_relative_to(directory.resolve()) and reference.is_file(), 'Missing coverage file XML')
                ET.parse(reference)
                require(path.is_relative_to(sandbox.resolve()) and path.is_file() and
                        hashlib.sha1(path.read_bytes()).hexdigest() == child.get('hash'), 'Coverage source hash mismatch')
                covered.add(str(path))
    walk(project, Path('.'))
    require(expected <= covered, 'Coverage omits fragment sources: ' + ', '.join(sorted(expected - covered)[:3]))
    return tree


def coverage_evidence(refs, directory, base, files, sandbox):
    require(isinstance(refs, list) and refs, 'Missing required coverage evidence')
    paths = [verify_artifact(ref, base) for ref in refs]
    require(len(paths) == len(set(paths)) and set(paths) == set(p.resolve() for p in directory.rglob('*.xml')),
            'Incomplete coverage XML evidence')
    check_coverage(directory, files, sandbox)


def php_environment(sandbox, fragment):
    binary = shutil.which('php')
    version = subprocess.check_output([binary, '-r', 'echo PHP_MAJOR_VERSION,".",PHP_MINOR_VERSION;'], text=True)
    require(version in ('8.4', '8.5'), 'PHP8.4/8.5 required; use scripts/php84')
    env = {**os.environ, 'APP_ENV': 'testing', 'PAO_DISABLE': '1', 'XDEBUG_MODE': 'coverage',
           'PGSQL_HOST': os.environ.get('PGSQL_HOST', '127.0.0.1'),
           'PGSQL_PORT': os.environ.get('PGSQL_PORT', '5434'),
           'PGSQL_USERNAME': os.environ.get('PGSQL_USERNAME', 'corex'),
           'PGSQL_PASSWORD': os.environ.get('PGSQL_PASSWORD', 'corex'),
           'PGSQL_DATABASE': 'corex_msi_' + fragment['package'].replace('-', '_') + '_' + fragment['zone'] + '_test',
           'COREX_MUTATION_LOGS': str(sandbox.parent / 'processes')}
    require(re.fullmatch(r'[a-z0-9_]+_test', env['PGSQL_DATABASE']), 'Unsafe worker DB')
    env.pop('PGSQL_BUDGET_DATABASE', None)
    env.pop('PGSQL_CONTROL_DATABASE', None)
    if fragment['package'] == 'operations':
        env['PGSQL_BUDGET_DATABASE'] = env['PGSQL_DATABASE'].removesuffix('_test') + '_budget_test'
    if fragment['package'] == 'search':
        env['PGSQL_CONTROL_DATABASE'] = env['PGSQL_DATABASE'].removesuffix('_test') + '_control_test'
    return binary, version, env


def php_witness(binary):
    expression = ('echo json_encode(["version"=>PHP_VERSION,"extensions"=>get_loaded_extensions(),'
                  '"ini"=>array_map(fn($p)=>hash_file("sha256",$p),array_filter(array_merge('
                  '[php_ini_loaded_file()],preg_split("/[,\\n]+/",php_ini_scanned_files()?:"")),fn($p)=>is_string($p)&&trim($p)!=""))]);')
    result = subprocess.run([binary, '-r', expression], text=True, capture_output=True, check=True)
    return json.loads(result.stdout)


def pg_preflight(binary, sandbox, env, base, label='pg'):
    name = env['PGSQL_DATABASE']
    require(re.fullmatch(r'[a-z0-9_]+_test', name), 'Unsafe PostgreSQL database')
    common = ['-h', env['PGSQL_HOST'], '-p', env['PGSQL_PORT'], '-U', env['PGSQL_USERNAME']]
    run(['pg_isready', *common, '-d', name], sandbox, env, base / (label + '-ready'), base)
    query_env = {**env, 'PGPASSWORD': env['PGSQL_PASSWORD']}
    exists = subprocess.check_output(['psql', *common, '-d', 'postgres', '-Atqc',
                                     "SELECT 1 FROM pg_database WHERE datname='" + name + "'"], env=query_env, text=True)
    if exists.strip() != '1':
        run(['psql', *common, '-d', 'postgres', '-v', 'ON_ERROR_STOP=1', '-c', 'CREATE DATABASE ' + name],
            sandbox, query_env, base / (label + '-create'), base)
    expression = ('echo json_encode((new PDO("pgsql:host=".getenv("PGSQL_HOST").";port=".getenv("PGSQL_PORT").'
                  '";dbname=".getenv("PGSQL_DATABASE"),getenv("PGSQL_USERNAME"),getenv("PGSQL_PASSWORD")))'
                  '->query("SELECT current_database() AS database, current_setting(\'server_version\') AS version")'
                  '->fetch(PDO::FETCH_ASSOC));')
    witness = run([binary, '-r', expression], sandbox, env, base / (label + '-witness'), base)
    value = load(verify_artifact(witness['stdout'], base))
    require(value.get('database') == name, 'Wrong actual worker database')
    return value


def snapshot(sandbox, root, test_directory='tests'):
    sandbox.mkdir()
    for name in ('packages', 'products', 'tests', 'scripts', 'vendor', 'api', 'docs'):
        shutil.copytree(root / name, sandbox / name, symlinks=True,
                        ignore=shutil.ignore_patterns('vendor' if name != 'vendor' else '__no_match__', 'node_modules',
                                                     '__pycache__', 'storage', 'build', '.env', '.env.*'))
    for p in root.iterdir():
        if p.is_file() and not p.name.startswith('.') and p.name != 'auth.json' and p.suffix in ('.json', '.lock', '.xml', '.php', '.neon'):
            shutil.copy2(p, sandbox / p.name)
    for p in (sandbox / 'vendor').rglob('*'):
        if p.is_symlink() and Path(os.readlink(p)).is_absolute():
            target = Path(os.readlink(p))
            require(target.is_relative_to(root), 'External absolute vendor link: ' + str(p))
            p.unlink()
            p.symlink_to(sandbox / target.relative_to(root))
    # Testbench resolves its compiled-view path with realpath before booting.
    testbench_storage = sandbox / 'vendor/orchestra/testbench-core/laravel/storage'
    for directory in ('framework/views', 'framework/cache/data', 'framework/sessions', 'logs'):
        (testbench_storage / directory).mkdir(parents=True, exist_ok=True)
    # This shipped template is framework source, needed by the stream interceptor too.
    testbench_env = Path('vendor/orchestra/testbench-core/src/Bootstrap/stubs/.env.testbench')
    shutil.copy2(root / testbench_env, sandbox / testbench_env)
    # Instrument the sandbox executable only. The pinned adapter still launches real Pest.
    executable = sandbox / 'vendor/bin/corex-pest'
    if executable.is_symlink():
        executable.unlink()
    executable.write_text('''#!/usr/bin/env php
<?php
declare(strict_types=1);
pcntl_exec('/usr/bin/python3', [__DIR__.'/../../scripts/quality/mutation.py', '--supervise', '--', PHP_BINARY,
    __DIR__.'/../pestphp/pest/bin/pest', ''' + json.dumps('--test-directory=' + test_directory) + ''', ...array_slice($argv, 1)]);
exit(127);
''')
    executable.chmod(0o755)


def harness(fragment, sandbox, lane, files, probe=False):
    base = Path(files[0]).parts[:2]
    config = sandbox.joinpath(*base, 'phpunit.xml')
    if fragment['package'] == 'core':
        config = sandbox / 'phpunit.xml'
    require(config.is_file(), 'Missing package PHPUnit harness: ' + str(config))
    tree = ET.parse(config)
    xml = tree.getroot()
    config_base = config.parent
    # Resolve existing harness paths before moving the config into the sandbox root.
    bootstrap = xml.get('bootstrap')
    if bootstrap:
        resolved = (config_base / bootstrap).resolve()
        if bootstrap == 'vendor/autoload.php':
            resolved = sandbox / 'vendor/autoload.php'
        require(resolved.is_file(), 'Missing test bootstrap: ' + str(resolved))
        xml.set('bootstrap', str(resolved))
    for node in xml.findall('.//testsuite/directory') + xml.findall('.//testsuite/file') + xml.findall('.//testsuite/exclude'):
        if node.text:
            node.text = str((config_base / node.text).resolve())
    if probe:
        suites = xml.find('testsuites')
        suites.clear()
        suite = ET.SubElement(suites, 'testsuite', {'name': 'probe'})
        ET.SubElement(suite, 'file').text = str(sandbox / 'packages/tenancy/tests/Unit/DatabaseValueTest.php')
    if fragment['package'] == 'core' and not probe:
        suites = xml.find('testsuites')
        suites.clear()
        for name in ('Unit', 'Feature', 'Modules', 'Data', 'Pg', 'Storage', 'Attributes'):
            path = sandbox / 'packages/core/tests' / name
            if path.is_dir():
                ET.SubElement(ET.SubElement(suites, 'testsuite', {'name': name}), 'directory').text = str(path)
        for name in ('Unit', 'Feature'):
            path = sandbox / 'tests' / name
            ET.SubElement(ET.SubElement(suites, 'testsuite', {'name': 'root-' + name}), 'directory').text = str(path)
    old_source = xml.find('source')
    if old_source is not None:
        xml.remove(old_source)
    include = ET.SubElement(ET.SubElement(xml, 'source'), 'include')
    for path in files:
        ET.SubElement(include, 'file').text = str(sandbox / path)
    old_groups = xml.find('groups')
    if old_groups is not None:
        xml.remove(old_groups)
    if lane == 'sqlite':
        ET.SubElement(ET.SubElement(ET.SubElement(xml, 'groups'), 'exclude'), 'group').text = 'pg'
    php = xml.find('php')
    if php is None:
        php = ET.SubElement(xml, 'php')
    for node in list(php):
        if node.get('name', '').startswith('PGSQL_'):
            php.remove(node)
    ET.SubElement(php, 'env', {'name': 'APP_ENV', 'value': 'testing', 'force': 'true'})
    xml.set('failOnEmptyTestSuite', 'true')
    xml.set('failOnWarning', 'true')
    xml.set('failOnRisky', 'true')
    tree.write(sandbox / 'phpunit.xml', encoding='utf-8', xml_declaration=True)


def infection_source(sandbox, files, inputs):
    source_root = Path(*Path(files[0]).parts[:3])
    source = {'directories': [str(sandbox / source_root)]}
    excludes = ['/^' + re.escape(str(Path(p).relative_to(source_root))).replace('/', r'\/') + '$/'
                for p in inputs if p.endswith('.php') and Path(p).is_relative_to(source_root) and p not in files]
    # Infection's schema forbids an empty excludes list; omitting it includes the whole root.
    if excludes:
        source['excludes'] = excludes
    return source


def invalid_prover(binary, sandbox, base, env):
    def prove(row, key):
        source = row['mutator']['mutatedSourceCode']
        directory = base / 'invalid' / key
        directory.mkdir(parents=True, exist_ok=True)
        mutant = directory / 'mutant.php'
        mutant.write_text(source)
        lint = run([binary, '-n', '-l', mutant], sandbox, env, directory / 'lint', base, accepted=(0, 255))
        if lint['exit'] != 0:
            output = verify_artifact(lint['stdout'], base).read_text() + verify_artifact(lint['stderr'], base).read_text()
            require(str(mutant) in output and re.search(r'(?:on line|:)\s*\d+', output), 'Unlocated invalid-mutant failure')
            if re.search(r'Parse error|syntax error|unexpected token', output, re.I):
                save(directory / 'proof.json', {'kind': 'syntax', 'mutant': artifact(mutant, base), 'lint': lint})
                return 'syntax'
            require(COMPILE.search(output), 'Unrecognized PHP lint failure')
        if not COMPILE.search(row['processOutput']):
            return None
        original = directory / 'original.php'
        original.write_text(row['mutator']['originalSourceCode'])
        expression = 'require $argv[1]; require $argv[2];'
        before = run([binary, '-n', '-r', expression, sandbox / 'vendor/autoload.php', original], sandbox, env,
                     directory / 'original-load', base)
        after = run([binary, '-n', '-r', expression, sandbox / 'vendor/autoload.php', mutant], sandbox, env,
                    directory / 'mutant-load', base, accepted=(0, 255))
        output = verify_artifact(after['stdout'], base).read_text() + verify_artifact(after['stderr'], base).read_text()
        if after['exit'] == 0 or 'Uncaught ' in output:
            return None
        require(after['exit'] == 255 and COMPILE.search(output) and str(mutant) in output, 'No deterministic compile failure')
        save(directory / 'proof.json', {'kind': 'compile', 'mutant': artifact(mutant, base), 'original': artifact(original, base),
                                       'original_load': before, 'mutant_load': after, 'lint': lint})
        return 'compile'
    return prove


def process_prover(base, temporary):
    by_source = {}
    for path in temporary.glob('mutant.*.infection.php'):
        key = path.name.split('.')[1]
        by_source.setdefault(sha(path), []).append(key)
    def check(row, group):
        ids = by_source.get(digest(row['mutator']['mutatedSourceCode'].encode()), [])
        require(ids, 'Missing retained raw mutant')
        records = [base / 'processes' / (key + '.json') for key in ids]
        records = [p for p in records if p.is_file()]
        require(records, 'Missing raw mutant exit/stdout/stderr')
        matched = False
        for path in records:
            record = load(path)
            output = verify_artifact(record['stdout'], base).read_text() + verify_artifact(record['stderr'], base).read_text()
            require(not INFRA.search(output) and record['exit'] not in (137, -9, 127, 139, -11), 'Infrastructure process exit')
            if group == 'timeouted':
                matched |= record.get('terminated', False)
            elif group == 'escaped':
                matched |= record['exit'] == 0 and not record.get('terminated')
            else:
                matched |= record['exit'] != 0 and not record.get('terminated')
        require(matched, 'Raw process contradicts Infection disposition: ' + group)
    return check


def retained_invalid_proof(base):
    def check(row, key):
        path = base / 'invalid' / key / 'proof.json'
        if not path.is_file():
            return None
        proof = load(path)
        mutant = verify_artifact(proof['mutant'], base)
        require(mutant.read_text() == row['mutator']['mutatedSourceCode'], 'Changed invalid-mutant reproduction')
        def output(process):
            require(process.get('timed_out') is False, 'Timed-out invalid-mutant reproduction')
            return verify_artifact(process['stdout'], base).read_text() + verify_artifact(process['stderr'], base).read_text()
        lint_output = output(proof['lint'])
        if proof['kind'] == 'syntax':
            require(proof['lint']['exit'] == 255 and str(mutant) in lint_output and
                    re.search(r'Parse error|syntax error|unexpected token', lint_output, re.I), 'Invalid retained syntax proof')
        elif proof['kind'] == 'compile':
            original = verify_artifact(proof['original'], base)
            require(original.read_text() == row['mutator']['originalSourceCode'], 'Changed compile-original reproduction')
            output(proof['original_load'])
            mutant_output = output(proof['mutant_load'])
            require(proof['original_load']['exit'] == 0 and proof['mutant_load']['exit'] == 255 and
                    COMPILE.search(mutant_output) and 'Uncaught ' not in mutant_output and str(mutant) in mutant_output,
                    'Invalid retained compile proof')
        else:
            raise MutationError('Unknown retained invalid-mutant proof')
        return proof['kind']
    return check


def supervise(argv):
    """Capture Pest without translating names or masking exit codes; kill its process group on timeout."""
    config = next((a.split('=', 1)[1] for a in argv if a.startswith('--configuration=')), '')
    match = re.search(r'pest\.([a-f0-9]+)\.xml$', config)
    if match is not None and len(argv) > 1 and Path(argv[1]).name == 'pest':
        # Pest loads its test-directory bootstrap before PHPUnit's XML bootstrap.
        # Intercept first, while letting Pest load Composer itself in its normal order.
        early = Path(config).parent / ('interceptor.' + match.group(1) + '.php')
        require(early.is_file(), 'Missing early mutant interception bootstrap')
        argv = [argv[0], '-d', 'auto_prepend_file=' + str(early), *argv[1:]]
    key = match.group(1) if match else 'initial-' + uuid.uuid4().hex
    logs = Path(os.environ['COREX_MUTATION_LOGS'])
    logs.mkdir(parents=True, exist_ok=True)
    controller = os.getpid()
    guardian = os.fork()
    if guardian:
        def forward(signum, frame):
            try:
                os.kill(guardian, signum)
            except ProcessLookupError:
                pass
        signal.signal(signal.SIGTERM, forward)
        signal.signal(signal.SIGINT, forward)
        _, status = os.waitpid(guardian, 0)
        code = os.waitstatus_to_exitcode(status)
        return code if code >= 0 else 128 - code
    # Symfony's timeout calls stop(0), killing the controller before it can journal.
    # A separate session retains the actual child exit and reaps it after controller death.
    os.setsid()
    try:
        code = supervised_child(argv, logs, key, controller, match is not None)
        sys.stdout.flush()
        sys.stderr.flush()
    except BaseException:
        import traceback
        traceback.print_exc()
        sys.stderr.flush()
        code = 1
    os._exit(code)


def supervised_child(argv, logs, key, controller, mutant):
    base = logs.parent
    terminated = False
    stopping_at = None
    start = time.monotonic()
    with (logs / (key + '.stdout')).open('wb') as out, (logs / (key + '.stderr')).open('wb') as err:
        process = subprocess.Popen(argv, stdout=out, stderr=err, start_new_session=True)
        def stop(signum, frame):
            nonlocal terminated, stopping_at
            terminated = True
            if stopping_at is None:
                stopping_at = time.monotonic()
            try:
                os.killpg(process.pid, signal.SIGTERM)
            except ProcessLookupError:
                pass
        signal.signal(signal.SIGTERM, stop)
        signal.signal(signal.SIGINT, stop)
        deadline = start + (15 if mutant else 600)
        while process.poll() is None:
            if not terminated and (os.getppid() != controller or time.monotonic() >= deadline):
                stop(signal.SIGTERM, None)
            if stopping_at is not None and time.monotonic() - stopping_at >= 1:
                try:
                    os.killpg(process.pid, signal.SIGKILL)
                except ProcessLookupError:
                    pass
            try:
                process.wait(timeout=.05)
            except subprocess.TimeoutExpired:
                pass
    record = {'argv': argv, 'exit': process.returncode, 'terminated': terminated,
              'controller_pid': controller, 'guardian_pid': os.getpid(),
              'duration_seconds': round(time.monotonic() - start, 3),
              'stdout': artifact(logs / (key + '.stdout'), base), 'stderr': artifact(logs / (key + '.stderr'), base)}
    save(logs / (key + '.json'), record)
    sys.stdout.buffer.write((logs / (key + '.stdout')).read_bytes())
    sys.stderr.buffer.write((logs / (key + '.stderr')).read_bytes())
    return process.returncode if process.returncode >= 0 else 128 - process.returncode


def run_fragment(fragment, root, output, ast, inputs, probe=False):
    start = time.monotonic()
    def budget(limit):
        remaining = 3600 - (time.monotonic() - start)
        require(remaining > 0, 'Fragment 60 minute budget exhausted')
        return min(limit, remaining)
    base = output / ('probe-' if probe else '') / (fragment['package'] + '-' + fragment['zone']) / uuid.uuid4().hex
    base.mkdir(parents=True)
    if not probe and all(ast[p]['declaration_only'] for p in fragment['files']):
        zero = load(root / QUALITY / 'mutation-zero-ledger.json')
        report = confirmation(zero.get('confirmation'), root, fragment['package'] + '/' + fragment['zone'])
        require(set(report.get('checks', {})) == {'api', 'phpstan', 'schema'}, 'Missing declaration API/PHPStan/schema checks')
        for check in report['checks'].values():
            require(check.get('exit') == 0, 'Failed declaration check')
            verify_artifact(check.get('evidence'), root)
        receipt = {'schema': 'corex-mutation-fragment/1', 'kind': 'measurement', 'result': 'N/A-declaration',
                   'package': fragment['package'], 'zone': fragment['zone'], 'files': fragment['files'],
                   'inputs': inputs, 'confirmation': zero['confirmation']}
        save(base / 'receipt.json', receipt)
        save(output / ('latest-' + fragment['package'] + '-' + fragment['zone'] + '.json'), artifact(base / 'receipt.json', output))
        print(fragment['package'] + '/' + fragment['zone'] + ': N/A-declaration (independently confirmed)', flush=True)
        return receipt
    sandbox = base / 'sandbox'
    test_directory = 'tests' if fragment['package'] == 'core' else str(Path(*Path(fragment['files'][0]).parts[:2]) / 'tests')
    snapshot(sandbox, root, test_directory)
    binary, version, env = php_environment(sandbox, fragment)
    db = pg_preflight(binary, sandbox, env, base)
    for role, variable in [('budget', 'PGSQL_BUDGET_DATABASE'), ('control', 'PGSQL_CONTROL_DATABASE')]:
        if variable in env:
            store_env = {**env, 'PGSQL_DATABASE': env[variable]}
            db[role] = pg_preflight(binary, sandbox, store_env, base, label='pg-' + role)
    files = ['packages/tenancy/src/Provisioning/Internal/DatabaseValue.php'] if probe else fragment['files']
    selected = {**fragment, 'files': files}
    versions = subprocess.check_output([binary, '-r', 'require "vendor/autoload.php"; echo json_encode(array_map('
        'fn($p)=>ltrim(Composer\\InstalledVersions::getPrettyVersion($p),"v"),array_combine('
        '["infection/infection","pestphp/pest","phpunit/phpunit","nikic/php-parser"],'
        '["infection/infection","pestphp/pest","phpunit/phpunit","nikic/php-parser"])));'], cwd=sandbox, env=env, text=True)
    versions = json.loads(versions)
    policy = load(root / QUALITY / 'infection-compatibility.json')
    for name in ('infection/infection', 'pestphp/pest', 'phpunit/phpunit'):
        require(versions[name] == policy['versions'][name], 'Unpinned engine: ' + name)
    lanes = []
    kinds = ['sqlite', 'pgsql']
    for lane in kinds:
        lane_dir = base / lane
        lane_dir.mkdir()
        harness(selected, sandbox, lane, files, probe)
        shutil.copy2(sandbox / 'phpunit.xml', lane_dir / 'pest-spike.xml')
        config = {'source': infection_source(sandbox, files, inputs),
                  'testFramework': 'pest-spike', 'timeout': 10, 'tmpDir': str(lane_dir / 'tmp'),
                  'logs': {'json': str(lane_dir / 'mutants.json')},
                  'mutators': {'@default': True, 'CoreX\\Tooling\\Infection\\NullReturn': True,
                               'CoreX\\Tooling\\Infection\\NonNullReturn': True,
                               'CoreX\\Tooling\\Infection\\DefaultObjectArgument': True}}
        if probe:
            config['mutators'] = {'LogicalNot': True, 'FalseValue': True}
        save(lane_dir / 'infection.json', config)
        # The adapter generates raw coverage and JUnit during Infection's fresh initial run.
        initial = run([binary, '-d', 'pcov.directory=' + str(sandbox), sandbox / 'vendor/bin/corex-pest',
                       '--configuration=' + str(sandbox / 'phpunit.xml'), '--compact', '--colors=never',
                       '--coverage-xml=' + str(lane_dir / 'coverage-xml'), '--log-junit=' + str(lane_dir / 'junit.xml')],
                      sandbox, env, lane_dir / 'initial', base, timeout=budget(600))
        cases = check_junit(lane_dir / 'junit.xml')
        behavioral_files = [p for p in files if not ast[p]['declaration_only']]
        check_coverage(lane_dir / 'coverage-xml', behavioral_files, sandbox)
        if lane == 'sqlite':
            lanes.append({'lane': lane, 'initial': initial, 'cases': cases,
                          'junit': artifact(lane_dir / 'junit.xml', base),
                          'coverage': [artifact(p, base) for p in sorted((lane_dir / 'coverage-xml').rglob('*.xml'))]})
            continue
        infection = run([binary, '-d', 'pcov.directory=' + str(sandbox), sandbox / 'vendor/bin/infection',
                         '--configuration=' + str(lane_dir / 'infection.json'), *FLAGS,
                         '--initial-tests-php-options=-d pcov.directory=' + str(sandbox)],
                        sandbox, env, lane_dir / 'infection', base, timeout=budget(3300))
        raw = lane_dir / 'tmp/infection'
        check_junit(raw / 'junit.xml')
        check_coverage(raw / 'coverage-xml', behavioral_files, sandbox)
        score = parse_report(load(lane_dir / 'mutants.json'), selected, sandbox, ast,
                             load(root / QUALITY / 'mutation-equivalents.json'),
                             invalid_prover(binary, sandbox, base, env), process_prover(base, raw), root)
        lanes.append({'lane': lane, 'initial': initial, 'infection': infection, 'score': score, 'cases': cases,
                      'report': artifact(lane_dir / 'mutants.json', base),
                      'junit': artifact(raw / 'junit.xml', base),
                      'test_history': [artifact(p, base) for p in sorted((raw / 'test-history').rglob('*')) if p.is_file()],
                      'coverage': [artifact(p, base) for p in sorted((raw / 'coverage-xml').rglob('*.xml'))]})
    require(input_hashes(root) == inputs, 'Source/test/tool/lock changed during measurement')
    receipt = {'schema': 'corex-mutation-fragment/1', 'kind': 'probe' if probe else 'measurement',
               'package': fragment['package'], 'zone': fragment['zone'], 'files': files, 'inputs': inputs,
               'php': version, 'php_binary': {'path': str(Path(binary).resolve()), 'sha256': sha(binary)},
               'versions': versions, 'database': db, 'lanes': lanes,
               'environment': php_witness(binary),
               'connection': {k: env[k] for k in ('PGSQL_HOST', 'PGSQL_PORT', 'PGSQL_USERNAME')},
               'native_test_environment': {k: env[k] for k in ('COREX_TEST_REDIS_PORT', 'COREX_TEST_STORAGE_CONFIG',
                                                               'COREX_TEST_SDK_AUTOLOAD', 'P3_REDIS_PORT',
                                                               'COREX_P3_STORAGE_CONFIG', 'COREX_P3_SDK_AUTOLOAD') if k in env},
               'process_evidence': [artifact(p, base) for directory in ('processes', 'invalid')
                                    for p in sorted((base / directory).rglob('*')) if p.is_file()],
               'duration_seconds': round(time.monotonic() - start, 3),
               'result': 'probe' if probe else ('passed' if lanes[-1]['score']['passed'] else 'below-threshold')}
    save(base / 'receipt.json', receipt)
    save(output / ('probe-latest.json' if probe else 'latest-' + fragment['package'] + '-' + fragment['zone'] + '.json'),
         artifact(base / 'receipt.json', output))
    print(fragment['package'] + '/' + fragment['zone'] + ': MSI=' + lanes[-1]['score']['msi'] + '; SQLite + PG initial suites', flush=True)
    require(probe or receipt['result'] == 'passed', 'Fragment below threshold')
    return receipt


def aggregate(mapping, output, root, inputs):
    receipts = []
    for fragment in mapping['fragments']:
        latest = output / ('latest-' + fragment['package'] + '-' + fragment['zone'] + '.json')
        require(latest.is_file(), 'Missing fragment receipt: ' + fragment['package'] + '/' + fragment['zone'])
        path = verify_artifact(load(latest), output)
        receipt = load(path)
        require(receipt.get('schema') == 'corex-mutation-fragment/1' and receipt.get('kind') == 'measurement' and
                receipt.get('result') in ('passed', 'N/A-declaration') and receipt.get('inputs') == inputs and
                receipt.get('files') == fragment['files'] and receipt.get('package') == fragment['package'] and
                receipt.get('zone') == fragment['zone'], 'Stale/incomplete/nonmeasurement fragment receipt')
        if receipt['result'] == 'N/A-declaration':
            ast = ast_inventory(root, fragment['files'])
            require(all(a['declaration_only'] for a in ast.values()), 'Behavior cannot become N/A')
            report = confirmation(receipt.get('confirmation'), root, fragment['package'] + '/' + fragment['zone'])
            require(set(report.get('checks', {})) == {'api', 'phpstan', 'schema'}, 'Missing declaration checks')
            for check in report['checks'].values():
                require(check.get('exit') == 0, 'Failed declaration check')
                verify_artifact(check.get('evidence'), root)
            receipts.append({'fragment': fragment['package'] + '/' + fragment['zone'], 'result': 'N/A-declaration',
                             'valid': 0, 'equivalents': 0})
            continue
        require(sha(receipt['php_binary']['path']) == receipt['php_binary']['sha256'], 'Changed PHP binary')
        require(php_witness(receipt['php_binary']['path']) == receipt.get('environment'), 'Changed PHP runtime/extensions/INI')
        process_paths = []
        for ref in receipt.get('process_evidence', []):
            process_paths.append(verify_artifact(ref, path.parent))
        require(receipt.get('process_evidence'), 'Missing process/reproduction evidence')
        expected_processes = {p.resolve() for name in ('processes', 'invalid')
                              for p in (path.parent / name).rglob('*') if p.is_file()}
        require(len(process_paths) == len(set(process_paths)) and set(process_paths) == expected_processes,
                'Incomplete process/reproduction evidence')
        require(len(receipt['lanes']) == 2 and {l['lane'] for l in receipt['lanes']} == {'sqlite', 'pgsql'},
                'Missing required PostgreSQL/SQLite lane')
        equivalents = 0
        valid = 0
        for lane in receipt['lanes']:
            initial = lane['initial']
            require(initial.get('exit') == 0 and initial.get('timed_out') is False, 'Failed initial suite receipt')
            verify_artifact(initial['stdout'], path.parent)
            verify_artifact(initial['stderr'], path.parent)
            check_junit(verify_artifact(lane['junit'], path.parent))
            if lane['lane'] == 'pgsql':
                require(lane.get('test_history'), 'Missing fresh initial test history')
                for ref in lane['test_history']:
                    verify_artifact(ref, path.parent)
            ast = ast_inventory(root, receipt['files'])
            directory = path.parent / lane['lane'] / ('coverage-xml' if lane['lane'] == 'sqlite' else 'tmp/infection/coverage-xml')
            coverage_evidence(lane.get('coverage'), directory, path.parent,
                              [p for p in fragment['files'] if not ast[p]['declaration_only']], path.parent / 'sandbox')
            if lane['lane'] == 'sqlite':
                continue
            score = lane['score']
            require(score['passed'] and score == parse_report(load(verify_artifact(lane['report'], path.parent)),
                    {**fragment, 'files': receipt['files']}, path.parent / 'sandbox',
                    ast, load(root / QUALITY / 'mutation-equivalents.json'),
                    retained_invalid_proof(path.parent),
                    process_prover(path.parent, path.parent / lane['lane'] / 'tmp/infection'), root),
                    'Tampered fragment score')
            equivalents += len(score['equivalents'])
            valid += score['pre_equivalence_valid']
        receipts.append({'fragment': fragment['package'] + '/' + fragment['zone'], 'valid': valid, 'equivalents': equivalents})
    require(100 * sum(r['equivalents'] for r in receipts) <= 5 * sum(r['valid'] for r in receipts), 'Global equivalents cap exceeded')
    save(output / 'aggregate.json', {'schema': 'corex-mutation-aggregate/1', 'inputs': inputs, 'fragments': receipts})
    return receipts


def main():
    if len(sys.argv) > 1 and sys.argv[1] == '--supervise':
        return supervise(sys.argv[3:])
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--inventory', action='store_true')
    parser.add_argument('--map', type=Path, default=ROOT / QUALITY / 'mutation-zones.json',
                        help='Read an alternate map for inventory diagnostics; the same immutable policy is enforced')
    parser.add_argument('--check', action='store_true')
    parser.add_argument('--write-inventory', action='store_true')
    parser.add_argument('--matrix', action='store_true')
    parser.add_argument('--self-test', action='store_true')
    parser.add_argument('--probe', action='store_true')
    parser.add_argument('--package')
    parser.add_argument('--zone', choices=THRESHOLDS)
    parser.add_argument('--all', action='store_true')
    parser.add_argument('--output', type=Path, default=ROOT / 'build/quality/mutation')
    args = parser.parse_args()
    try:
        if args.self_test:
            return subprocess.call([sys.executable, '-m', 'unittest', 'discover', '-s', str(ROOT / QUALITY / 'tests'), '-p', 'test_mutation.py'])
        files = production_files(ROOT)
        require(args.inventory or args.map.resolve() == (ROOT / QUALITY / 'mutation-zones.json').resolve(),
                'Alternate map is inventory-diagnostics only')
        if not args.write_inventory:
            check_inventory(load(args.map), ROOT)
        ast = ast_inventory(ROOT, files)
        if args.write_inventory:
            save(ROOT / QUALITY / 'mutation-zones.json', make_map(files))
            save(ROOT / QUALITY / 'mutation-zero-ledger.json', {'schema': 'corex-mutation-zero-ledger/1',
                 'files': {p: {'sha256': a['sha256'], 'reason': a['reason']} for p, a in ast.items() if a['declaration_only']},
                 'confirmation': None})
        mapping = load(args.map)
        check_inventory(mapping, ROOT, ast, load(ROOT / QUALITY / 'mutation-zero-ledger.json'))
        ledger = load(ROOT / QUALITY / 'mutation-equivalents.json')
        check_equivalent_ledger(ledger, ROOT, ast)
        if args.matrix:
            print(json.dumps({'include': [{k: f[k] for k in ('package', 'zone')} for f in mapping['fragments']]}))
            return 0
        if args.inventory or args.write_inventory:
            print('Mutation inventory OK: ' + str(len(files)) + ' files; ' + str(len(mapping['fragments'])) +
                  ' package-fragments; ' + str(sum(a['declaration_only'] for a in ast.values())) + ' exact AST declarations')
            return 0
        inputs = input_hashes(ROOT)
        output = args.output.resolve()
        require(output.is_relative_to(ROOT / 'build'), 'Output must be under repository build/')
        output.mkdir(parents=True, exist_ok=True)
        if args.all:
            receipts = aggregate(mapping, output, ROOT, inputs)
            print('Full mutation acceptance: ' + str(len(receipts)) + ' fresh fragments')
            return 0
        selected = [f for f in mapping['fragments'] if (args.package is None or f['package'] == args.package) and
                    (args.zone is None or f['zone'] == args.zone)]
        require(selected and (args.package or args.zone), 'Select --package/--zone, or --all to verify receipts')
        require(not args.probe or (args.package == 'tenancy' and args.zone == 'security'), 'Probe is tenancy/security only')
        for fragment in selected:
            run_fragment(fragment, ROOT, output, ast, inputs, args.probe)
        return 0
    except (MutationError, OSError, ValueError, KeyError, TypeError, subprocess.SubprocessError, ET.ParseError) as exc:
        print('Mutation RED: ' + str(exc), file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
