"""Protocol negatives use explicitly synthetic artifacts, never compatibility evidence."""
import copy
import importlib.util
import itertools
import json
import os
from pathlib import Path
import tempfile
import unittest

SCRIPT = Path(__file__).resolve().parents[1] / 'infection-spike.py'
spec = importlib.util.spec_from_file_location('infection_spike', SCRIPT)
spike = importlib.util.module_from_spec(spec)
spec.loader.exec_module(spike)


class InfectionSpikeTest(unittest.TestCase):
    def setUp(self):
        temp = tempfile.TemporaryDirectory()
        self.addCleanup(temp.cleanup)
        self.output = Path(temp.name)
        self.policy = json.loads(spike.POLICY.read_text())
        self.receipt = {'schema': spike.SCHEMA, 'result': 'compatible', 'adapter': self.policy['adapter'],
                        'inputs': spike.inputs(spike.ROOT, self.policy), 'rows': []}
        for php, laravel, driver in itertools.product(self.policy['php'], self.policy['laravel'], self.policy['drivers']):
            prefix = php + '-' + laravel + '-' + driver
            binary = self.output / 'synthetic-php'
            binary.write_text('synthetic executable fixture')
            versions = {**self.policy['versions'], 'php': php + '.1', 'laravel/framework': laravel + '.1.0'}
            lock = {'packages': [{'name': k, 'version': v} for k, v in versions.items() if k != 'php'], 'packages-dev': []}
            cases = ['it decodes real PDO rows on ' + driver] + ['synthetic ' + str(n) for n in range(12)]
            junit = '<testsuites><testsuite tests="13" failures="0" errors="0" skipped="0">' + ''.join(
                '<testcase name="' + n + '" class="Tests\\SyntheticTest" assertions="1"/>' for n in cases) + '</testsuite></testsuites>'
            coverage = '<coverage xmlns="https://schema.phpunit.de/coverage/1.0"><tests>' + ''.join('<test name="P\\Tests\\Synthetic::' + str(n) + '" status="success"/>'
                                                   for n in range(13)) + '</tests></coverage>'
            report = {'stats': {'totalMutantsCount': 5, 'skippedCount': 0}, **{k: [] for k in spike.STATUSES}}
            for group, method, output in [('killed', 'value', 'FAILED\nTests: 1 failed'), ('escaped', 'escaped', 'passed'),
                                           ('uncovered', 'uncovered', ''), ('timeouted', 'timeout', 'SPIKE_TIMEOUT_CONTROL'),
                                           ('errored', 'error', 'SPIKE_ERROR_CONTROL')]:
                report[group] = [{'mutator': {'originalSourceCode': 'function ' + method + '() { return false; }',
                                              'mutatedSourceCode': 'function ' + method + '() { return true; }',
                                              'originalFilePath': '/synthetic/' + ('DatabaseValue.php' if group == 'killed' else 'Controls.php')},
                                   'diff': 'synthetic mutation', 'processOutput': output}]
            report['stats'].update({counter: len(report[group]) for group, counter in spike.STATUSES.items()})
            row = {'php': php, 'laravel': laravel, 'driver': driver, 'result': 'passed',
                   'php_binary': {'path': str(binary), 'sha256': spike.sha(binary)},
                   'lock': self.put(prefix + '/composer.lock', lock), 'versions': self.put(prefix + '/versions.json', versions),
                   'help': self.put(prefix + '/help.txt', '--with-uncovered --with-timeouts'),
                   'pest': self.process(prefix + '/pest', ['/synthetic/corex-pest'], 'Tests: 13 passed (13 assertions)'),
                   'infection': self.process(prefix + '/infection', self.policy['required_flags'], 'synthetic infection output'),
                   'trace': self.put(prefix + '/trace.jsonl', ''.join(json.dumps({'executable': '/synthetic/vendor/pestphp/pest/bin/pest',
                       'php_version': versions['php'], 'argv': ['--configuration=/synthetic/pest.mutant.xml']}) + '\n' for _ in range(6))),
                   'junit': self.put(prefix + '/junit.xml', junit),
                   'coverage': [self.put(prefix + '/coverage/index.xml', coverage),
                                self.put(prefix + '/coverage/DatabaseValue.php.xml', '<coverage><covered/></coverage>'),
                                self.put(prefix + '/coverage/Controls.php.xml', '<coverage/>')],
                   'mutants': self.put(prefix + '/mutants.json', report), 'mutant_stats': report['stats'],
                   'database': self.put(prefix + '/db.json', {'driver': driver, 'database': ':memory:' if driver == 'sqlite' else 'synthetic_test',
                                                            'server_version': 'synthetic', 'laravel': versions['laravel/framework']}),
                   'harness': [self.put(prefix + '/config.xml', '<synthetic/>')]}
            self.receipt['rows'].append(row)

    def put(self, path, value):
        path = self.output / path
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(value if isinstance(value, str) else json.dumps(value))
        return spike.artifact(path, self.output)

    def process(self, prefix, argv, output):
        return {'argv': argv, 'exit': 0, 'stdout': self.put(prefix + '.stdout', output), 'stderr': self.put(prefix + '.stderr', '')}

    def check(self, message=None):
        if message:
            with self.assertRaisesRegex(spike.ProofError, message):
                spike.check_receipt(self.receipt, self.output)
        else:
            self.assertEqual(spike.check_receipt(self.receipt, self.output), 4)

    def test_complete_protocol_fixture(self):
        self.check()

    def test_partial_diagnostic_is_not_full_matrix_admission(self):
        self.receipt['result'] = 'incompatible'
        self.receipt['rows'] = self.receipt['rows'][:2]
        self.assertEqual(spike.check_rows(self.receipt, self.output), 2)
        self.check('Incomplete/fake receipt')

    def test_fake_pass_without_actual_matrix(self):
        self.receipt = {'schema': spike.SCHEMA, 'result': 'compatible', 'adapter': 'phpunit'}
        self.check('Unverified adapter')

    def test_missing_or_duplicate_matrix_cell(self):
        self.receipt['rows'].pop()
        self.check('Missing or duplicated matrix row')
        self.receipt['rows'].append(copy.deepcopy(self.receipt['rows'][0]))
        self.check('Invalid/duplicate matrix row')

    def test_failed_cell(self):
        self.receipt['rows'][0]['result'] = 'failed'
        self.check('Failed matrix row')

    def test_failed_process(self):
        self.receipt['rows'][0]['infection']['exit'] = 137
        self.check('Failed infection process')

    def test_phpunit_only_executable(self):
        self.receipt['rows'][0]['pest']['argv'] = ['/synthetic/phpunit']
        self.check('PHPUnit-only')

    def test_missing_mandatory_flag(self):
        self.receipt['rows'][0]['infection']['argv'] = ['--threads=1']
        self.check('missing flags')

    def test_downgrade_even_with_rehashed_lock_and_versions(self):
        row = self.receipt['rows'][0]
        lock = json.loads(spike.read_artifact(row['lock'], self.output).read_text())
        next(p for p in lock['packages'] if p['name'] == 'phpunit/phpunit')['version'] = '12.0.0'
        row['lock'] = self.put(row['lock']['path'], lock)
        self.check('Version downgrade/mismatch')

    def test_missing_raw_coverage(self):
        row = self.receipt['rows'][0]
        spike.read_artifact(row['coverage'][0], self.output).unlink()
        self.check('Missing or unsafe artifact')

    def test_counterfeit_and_missing_raw_reports(self):
        row = self.receipt['rows'][0]
        spike.read_artifact(row['mutants'], self.output).write_text('{"passed":true}')
        self.check('Artifact digest mismatch')
        row['mutants'] = self.put(row['mutants']['path'], {'passed': True})
        self.check('Unknown or missing Infection report fields')

    def test_stale_source_tool_or_lock(self):
        self.receipt['inputs']['composer.lock'] = 'counterfeit'
        self.check('Stale product/tool/lock inputs')

    def test_empty_junit_and_phpunit_class(self):
        row = self.receipt['rows'][0]
        text = spike.read_artifact(row['junit'], self.output).read_text()
        row['junit'] = self.put(row['junit']['path'], text.replace('assertions="1"', 'assertions="0"'))
        self.check('Failed/empty/skipped JUnit case')
        row['junit'] = self.put(row['junit']['path'], text.replace('Tests\\SyntheticTest', 'PHPUnit\\Synthetic'))
        self.check('PHPUnit-only')

    def test_unexpected_error_is_not_killed(self):
        row = self.receipt['rows'][0]
        report = json.loads(spike.read_artifact(row['mutants'], self.output).read_text())
        report['errored'][0]['processOutput'] = 'PDO connection refused'
        row['mutants'] = self.put(row['mutants']['path'], report)
        self.check('Wrong errored control')

    def test_bootstrap_or_database_error_cannot_be_killed(self):
        row = self.receipt['rows'][0]
        report = json.loads(spike.read_artifact(row['mutants'], self.output).read_text())
        report['killed'][0]['processOutput'] = 'FAILED\nTests: 1 failed\nSQLSTATE[08006] connection refused'
        row['mutants'] = self.put(row['mutants']['path'], report)
        self.check('infrastructure failure')

    def test_missing_pest_mutant_process_is_rejected(self):
        row = self.receipt['rows'][0]
        trace = spike.read_artifact(row['trace'], self.output).read_text().splitlines()
        row['trace'] = self.put(row['trace']['path'], '\n'.join(trace[:-1]) + '\n')
        self.check('Missing/extra Pest initial or mutant invocation')

    def test_timeout_and_uncovered_controls_required(self):
        row = self.receipt['rows'][0]
        report = json.loads(spike.read_artifact(row['mutants'], self.output).read_text())
        report['timeouted'] = []
        report['stats']['timeOutCount'] = 0
        report['stats']['totalMutantsCount'] -= 1
        row['mutants'] = self.put(row['mutants']['path'], report)
        self.check('Missing/extra timeouted control')

    def test_wrong_or_unsafe_database(self):
        row = self.receipt['rows'][1]
        witness = json.loads(spike.read_artifact(row['database'], self.output).read_text())
        witness['database'] = 'production'
        row['database'] = self.put(row['database']['path'], witness)
        self.check('Unsafe/missing database proof')

    def test_artifact_path_escape(self):
        self.receipt['rows'][0]['lock']['path'] = '../outside/composer.lock'
        self.check('Missing or unsafe artifact')

    def test_retired_laravel12_and_unsupported_php(self):
        row = self.receipt['rows'][0]
        row['laravel'] = '12'
        self.check('Invalid/duplicate matrix row')
        row['laravel'] = '13'
        row['php'] = '8.3'
        self.check('Invalid/duplicate matrix row')

    def test_duplicate_database_driver(self):
        self.receipt['rows'][1]['driver'] = 'sqlite'
        self.check('Invalid/duplicate matrix row')

    def test_wrong_runtime_framework_even_with_rehashed_artifacts(self):
        row = self.receipt['rows'][0]
        versions = json.loads(spike.read_artifact(row['versions'], self.output).read_text())
        lock = json.loads(spike.read_artifact(row['lock'], self.output).read_text())
        versions['laravel/framework'] = '12.69.3'
        next(p for p in lock['packages'] if p['name'] == 'laravel/framework')['version'] = '12.69.3'
        row['versions'] = self.put(row['versions']['path'], versions)
        row['lock'] = self.put(row['lock']['path'], lock)
        self.check('Runtime matrix/version mismatch')

    def test_old_eight_row_schema_cannot_be_relabelled(self):
        self.receipt['schema'] = 'corex-infection-spike/v1'
        self.check('Incomplete/fake receipt')
        self.receipt['schema'] = spike.SCHEMA
        self.receipt['rows'] += copy.deepcopy(self.receipt['rows'])
        self.check('Missing or duplicated matrix row')

    def test_stale_policy_and_manifest_inputs(self):
        for path in ('scripts/quality/infection-compatibility.json', 'packages/core/composer.json'):
            with self.subTest(path=path):
                original = self.receipt['inputs'][path]
                self.receipt['inputs'][path] = 'old-policy-hash'
                self.check('Stale product/tool/lock inputs')
                self.receipt['inputs'][path] = original

    def test_policy_cannot_restore_laravel12(self):
        policy = copy.deepcopy(self.policy)
        policy['laravel'] = ['12', '13']
        with self.assertRaisesRegex(spike.ProofError, 'Unsupported platform policy'):
            spike.check_policy(policy)


class PlatformGateTest(unittest.TestCase):
    def setUp(self):
        temp = tempfile.TemporaryDirectory()
        self.addCleanup(temp.cleanup)
        self.root = Path(temp.name)
        paths = spike.platform_manifests(spike.ROOT) + [spike.ROOT / '.github/workflows/ci.yml',
                                                      spike.ROOT / 'composer.lock', spike.ROOT / 'scripts/manifest-check.py']
        for path in paths:
            target = self.root / path.relative_to(spike.ROOT)
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(path.read_bytes())
        os.symlink(spike.ROOT / '.git', self.root / '.git')
        os.symlink(spike.ROOT / 'vendor', self.root / 'vendor')

    def change_manifest(self, dependency, value):
        path = self.root / 'packages/core/composer.json'
        data = json.loads(path.read_text())
        section = next(section for section in ('require', 'require-dev') if dependency in data.get(section, {}))
        data[section][dependency] = value
        path.write_text(json.dumps(data))

    def test_manifest_gate_rejects_old_framework_and_testbench(self):
        for dependency, value, diagnostic in [('illuminate/contracts', '^12.0', 'illuminate'),
                                               ('orchestra/testbench', '^10.12', 'stack'),
                                               ('orchestra/testbench-core', '^10.12', 'stack')]:
            with self.subTest(dependency=dependency):
                self.change_manifest(dependency, value)
                result = spike.subprocess.run(['python3', 'scripts/manifest-check.py'], cwd=self.root,
                                              capture_output=True, text=True)
                self.assertEqual(result.returncode, 1)
                self.assertIn(diagnostic + ': ' + dependency, result.stdout)
                with self.assertRaisesRegex(spike.ProofError, 'Unexpected platform manifest delta'):
                    spike.check_platform_contract(self.root)
                source = spike.ROOT / 'packages/core/composer.json'
                (self.root / 'packages/core/composer.json').write_bytes(source.read_bytes())

    def test_contract_rejects_unrelated_manifest_change(self):
        path = self.root / 'composer.json'
        data = json.loads(path.read_text())
        data['prefer-stable'] = False
        path.write_text(json.dumps(data))
        with self.assertRaisesRegex(spike.ProofError, 'Unexpected platform manifest delta'):
            spike.check_platform_contract(self.root)

    def test_contract_rejects_upstream_lock_change(self):
        path = self.root / 'composer.lock'
        lock = json.loads(path.read_text())
        next(p for p in lock['packages'] if p['name'] == 'laravel/framework')['version'] = 'v13.0.0'
        path.write_text(json.dumps(lock))
        with self.assertRaisesRegex(spike.ProofError, 'Unexpected root lock delta'):
            spike.check_platform_contract(self.root)

    def test_ci_rejects_retired_stack_and_missing_php_lane(self):
        path = self.root / '.github/workflows/ci.yml'
        original = path.read_text()
        for text in (original.replace("laravel: ['13.*']", "laravel: ['12.*', '13.*']", 1),
                     original.replace("php: ['8.4', '8.5']", "php: ['8.4']", 1),
                     original.replace('  COMPOSER_ROOT_VERSION: dev-main\n', '')):
            path.write_text(text)
            with self.assertRaisesRegex(spike.ProofError, 'Ordinary CI platform/installer drift'):
                spike.check_ci_platform(self.root)


if __name__ == '__main__':
    unittest.main()
