import copy
import importlib.util
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import signal
import time
import unittest

SCRIPT = Path(__file__).resolve().parents[1] / 'mutation.py'
spec = importlib.util.spec_from_file_location('mutation', SCRIPT)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
FIXTURE = Path(__file__).parent / 'fixtures/mutation/infection-0.35.6.json'


class ParserTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.native = m.load(FIXTURE)
        self.report = copy.deepcopy(self.native)
        self.files = []
        for group in m.GROUPS:
            for row in self.report[group]:
                old = row['mutator']['originalFilePath']
                relative = 'packages/tenancy/src/' + Path(old).name
                row['mutator']['originalFilePath'] = str(self.root / relative)
                path = self.root / relative
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_text(row['mutator']['originalSourceCode'])
                if relative not in self.files:
                    self.files.append(relative)
        self.report['errored'] = []
        self.recount()
        self.fragment = {'package': 'tenancy', 'zone': 'security', 'files': self.files}
        self.ast = m.ast_inventory(self.root, self.files)

    def recount(self):
        for group, counter in m.GROUPS.items():
            self.report['stats'][counter] = len(self.report[group])
        self.report['stats']['totalMutantsCount'] = sum(len(self.report[g]) for g in m.GROUPS)

    def parse(self, **kwargs):
        return m.parse_report(self.report, self.fragment, self.root, self.ast, **kwargs)

    def test_real_locked_report_uses_test_kills_only(self):
        score = self.parse()
        self.assertEqual((score['killed'], score['valid']), (4, 7))
        self.assertFalse(score['passed'])
        self.assertLess(float(score['msi']), self.native['stats']['msi'])

    def test_actual_exit137_control_blocks(self):
        row = copy.deepcopy(self.native['errored'][0])
        row['mutator']['originalFilePath'] = str(self.root / 'packages/tenancy/src/Controls.php')
        self.report['errored'] = [row]
        self.recount()
        with self.assertRaisesRegex(m.MutationError, 'Infrastructure'):
            self.parse()

    def test_timeout_and_uncovered_stay_in_denominator(self):
        score = self.parse()
        self.assertEqual(score['valid'], 7)
        self.assertEqual(score['killed'], 4)

    def test_unknown_status_and_missing_status_block(self):
        for change in ('extra', 'missing'):
            with self.subTest(change=change):
                report = copy.deepcopy(self.report)
                if change == 'extra':
                    report['success'] = []
                else:
                    del report['uncovered']
                with self.assertRaisesRegex(m.MutationError, 'disposition'):
                    m.parse_report(report, self.fragment, self.root, self.ast)

    def test_count_disagreement_block(self):
        self.report['stats']['totalMutantsCount'] += 1
        with self.assertRaisesRegex(m.MutationError, 'incomplete'):
            self.parse()

    def test_duplicate_and_nonmutating_rows_block(self):
        self.report['killed'].append(copy.deepcopy(self.report['killed'][0]))
        self.recount()
        with self.assertRaisesRegex(m.MutationError, 'Duplicated'):
            self.parse()
        self.report['killed'].pop()
        self.recount()
        row = self.report['killed'][0]
        row['mutator']['mutatedSourceCode'] = row['mutator']['originalSourceCode']
        with self.assertRaisesRegex(m.MutationError, 'Fake mutant'):
            self.parse()

    def test_infrastructure_cannot_hide_as_killed(self):
        for output in ('SQLSTATE[08006] connection refused', 'Allowed memory size exhausted',
                       'Pinned Pest executable is missing.', 'PHPUnit bootstrap error',
                       'Could not open input file: pest', 'No tests executed!', 'Segmentation fault'):
            with self.subTest(output=output):
                self.report['killed'][0]['processOutput'] = 'FAILED\nTests: 1 failed\n' + output
                with self.assertRaisesRegex(m.MutationError, 'Infrastructure'):
                    self.parse()

    def test_unexplained_error_is_red(self):
        row = self.report['killed'].pop()
        self.report['errored'] = [row]
        self.recount()
        with self.assertRaisesRegex(m.MutationError, 'Unreproduced'):
            self.parse()

    def test_stale_source_and_foreign_path_block(self):
        path = self.root / self.files[0]
        path.write_text(path.read_text() + '\n// changed\n')
        with self.assertRaisesRegex(m.MutationError, 'Changed original'):
            self.parse()
        self.report['killed'][0]['mutator']['originalFilePath'] = '/etc/passwd'
        with self.assertRaisesRegex(m.MutationError, 'Foreign'):
            self.parse()

    def test_report_original_must_match_current_ast(self):
        self.report['killed'][0]['mutator']['originalSourceCode'] = '<?php class CompletelyDifferent {}'
        with self.assertRaisesRegex(m.MutationError, 'original source mismatch'):
            self.parse()

    def test_behavioral_file_with_no_valid_mutants_blocks(self):
        path = 'packages/tenancy/src/Unseen.php'
        (self.root / path).write_text('<?php function behavior() { return true; }')
        self.fragment['files'].append(path)
        self.ast.update(m.ast_inventory(self.root, [path]))
        with self.assertRaisesRegex(m.MutationError, 'zero valid mutants'):
            self.parse()

    def test_zero_mutants_never_numeric_green(self):
        for group in m.GROUPS:
            self.report[group] = []
        self.recount()
        with self.assertRaisesRegex(m.MutationError, 'Zero'):
            self.parse()

    def test_static_analysis_and_ignored_are_not_test_kills(self):
        for group in ('ignored', 'killedByStaticAnalysis'):
            with self.subTest(group=group):
                r = copy.deepcopy(self.report)
                r[group] = [r['killed'].pop()]
                r['stats'][m.GROUPS[group]] = 1
                r['stats']['killedCount'] -= 1
                with self.assertRaisesRegex(m.MutationError, 'not test evidence'):
                    m.parse_report(r, self.fragment, self.root, self.ast)

    def test_threshold_edges_are_not_rounded(self):
        for killed, valid, threshold, expected in ((9499, 10000, 95, False), (95, 100, 95, True),
                                                   (8999, 10000, 90, False), (90, 100, 90, True),
                                                   (7499, 10000, 75, False), (75, 100, 75, True)):
            self.assertEqual(m.fraction(killed, valid, threshold)['passed'], expected)

    def test_equivalent_cap_does_not_round_up(self):
        row = self.report['escaped'][0]
        relative = 'packages/tenancy/src/Controls.php'
        key = m.mutant_key(row, relative, self.ast[relative]['sha256'])
        replay = self.root / 'replay.txt'
        replay.write_text('exact replay fixture')
        proof = self.root / 'review.json'
        m.save(proof, {'review_item': 'P09.8', 'verdict': 'confirmed', 'confirmed': [key]})
        entry = {'id': key, 'path': relative, 'source_sha256': self.ast[relative]['sha256'], 'reason': 'fixture',
                 'replay': m.artifact(replay, self.root), 'confirmation': m.artifact(proof, self.root)}
        ledger = {'schema': 'corex-mutation-equivalents/1', 'cap_percent': 5, 'entries': [entry]}
        rows = {key: (row, relative, self.ast[relative]['sha256'], 'escaped')}
        self.assertEqual(m.equivalent_ids(ledger, self.root, rows, 20), {key})
        with self.assertRaisesRegex(m.MutationError, '5% cap'):
            m.equivalent_ids(ledger, self.root, rows, 19)
        proof.write_text('{}')
        with self.assertRaisesRegex(m.MutationError, 'Stale confirmation'):
            m.equivalent_ids(ledger, self.root, rows, 20)

    def test_missing_xml_and_junit_block(self):
        with self.assertRaises((m.MutationError, OSError)):
            m.check_junit(self.root / 'missing.xml')
        with self.assertRaisesRegex(m.MutationError, 'Missing coverage'):
            m.check_coverage(self.root / 'coverage-xml', self.files, self.root)

    def test_syntax_invalid_has_real_php_location_proof(self):
        if shutil.which('php') is None:
            self.fail('PHP required for actual invalid-mutant proof')
        row = copy.deepcopy(self.report['killed'][0])
        row['mutator']['mutatedSourceCode'] = '<?php function broken( { return true; }'
        proof = m.invalid_prover(shutil.which('php'), self.root, self.root, os.environ.copy())
        self.assertEqual(proof(row, 'actual-syntax'), 'syntax')
        receipt = m.load(self.root / 'invalid/actual-syntax/proof.json')
        self.assertEqual(receipt['lint']['exit'], 255)

    def test_compile_invalid_reproduces_original_and_mutant_load(self):
        (self.root / 'vendor').mkdir()
        (self.root / 'vendor/autoload.php').write_text('<?php class Base { function a(): int {return 1;} }')
        row = copy.deepcopy(self.report['killed'][0])
        row['mutator']['originalSourceCode'] = '<?php class C { function a() {} }'
        row['mutator']['mutatedSourceCode'] = '<?php class C { function a() {} function a() {} }'
        # Duplicate methods can fail lint; a signature mismatch passes lint and fails isolated load.
        row['mutator']['originalSourceCode'] = '<?php class C extends Base { function a(): int {return 2;} }'
        row['mutator']['mutatedSourceCode'] = '<?php class C extends Base { function a(): string {return "2";} }'
        row['processOutput'] = 'Declaration of C::a(): string must be compatible with Base::a(): int'
        proof = m.invalid_prover(shutil.which('php'), self.root, self.root, os.environ.copy())
        self.assertEqual(proof(row, 'actual-compile'), 'compile')
        receipt = m.load(self.root / 'invalid/actual-compile/proof.json')
        self.assertEqual(receipt['lint']['exit'], 0)
        self.assertEqual(receipt['original_load']['exit'], 0)
        self.assertNotEqual(receipt['mutant_load']['exit'], 0)

    def test_type_assertion_is_behavior_not_compile_invalid(self):
        row = copy.deepcopy(self.report['killed'][0])
        row['processOutput'] = 'FAILED TypeError\nTests: 1 failed'
        proof = m.invalid_prover(shutil.which('php'), self.root, self.root, os.environ.copy())
        self.assertIsNone(proof(row, 'behavior'))

    def test_runtime_exception_cannot_impersonate_a_compile_error(self):
        (self.root / 'vendor').mkdir()
        (self.root / 'vendor/autoload.php').write_text('<?php')
        row = copy.deepcopy(self.report['killed'][0])
        row['mutator']['originalSourceCode'] = '<?php if (false) { throw new RuntimeException("Cannot redeclare method"); }'
        row['mutator']['mutatedSourceCode'] = '<?php if (true) { throw new RuntimeException("Cannot redeclare method"); }'
        row['processOutput'] = 'FAILED RuntimeException: Cannot redeclare method\nTests: 1 failed'
        proof = m.invalid_prover(shutil.which('php'), self.root, self.root, os.environ.copy())
        self.assertIsNone(proof(row, 'behavioral-exception'))

    def test_malformed_and_duplicate_json_block(self):
        p = self.root / 'report.json'
        for source in ('{broken', '{"stats":{},"stats":{}}'):
            p.write_text(source)
            with self.assertRaises(ValueError):
                m.load(p)


class InventoryTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.files = m.production_files(m.ROOT)
        cls.mapping = m.make_map(cls.files)

    def test_exhaustive_disjoint_inventory(self):
        self.assertEqual(m.check_inventory(self.mapping, m.ROOT), self.files)

    def test_every_authorizer_and_activation_anchor_cannot_be_downgraded_or_omitted(self):
        anchors = ['packages/core/src/' + p for p in m.ANCHORS]
        anchors += [p for p in self.files if p.startswith('packages/core/src/Data/')]
        for path in anchors:
            for operation in ('downgrade', 'remove'):
                with self.subTest(path=path, operation=operation):
                    mapping = copy.deepcopy(self.mapping)
                    fragment = next(f for f in mapping['fragments'] if path in f['files'])
                    fragment['files'].remove(path)
                    if operation == 'downgrade':
                        other = next(f for f in mapping['fragments'] if f['package'] == 'core' and f['zone'] == 'other')
                        other['files'].append(path)
                    with self.assertRaisesRegex(m.MutationError, path):
                        m.check_inventory(mapping, m.ROOT)

    def test_missing_fragment_and_duplicate_path_red(self):
        mapping = copy.deepcopy(self.mapping)
        mapping['fragments'].pop()
        with self.assertRaisesRegex(m.MutationError, 'Unassigned'):
            m.check_inventory(mapping, m.ROOT)
        mapping = copy.deepcopy(self.mapping)
        mapping['fragments'][0]['files'].append(mapping['fragments'][0]['files'][0])
        with self.assertRaisesRegex(m.MutationError, 'Multiply'):
            m.check_inventory(mapping, m.ROOT)

    def test_threshold_change_and_empty_legacy_root_red(self):
        mapping = copy.deepcopy(self.mapping)
        mapping['zones']['security'] = 75
        with self.assertRaisesRegex(m.MutationError, '95/90/75'):
            m.check_inventory(mapping, m.ROOT)
        mapping = copy.deepcopy(self.mapping)
        mapping['fragments'][0]['files'].append('packages/core/src/Modules/Gate/obsolete.php')
        with self.assertRaisesRegex(m.MutationError, 'obsolete.php'):
            m.check_inventory(mapping, m.ROOT)

    def test_cli_rejects_each_named_security_anchor_downgrade_and_omission(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / 'map.json'
            for anchor in m.ANCHORS:
                source = 'packages/core/src/' + anchor
                for operation in ('downgrade', 'remove'):
                    with self.subTest(source=source, operation=operation):
                        mapping = copy.deepcopy(self.mapping)
                        original = next(f for f in mapping['fragments'] if source in f['files'])
                        original['files'].remove(source)
                        if operation == 'downgrade':
                            next(f for f in mapping['fragments'] if f['package'] == 'core' and f['zone'] == 'other')['files'].append(source)
                        m.save(path, mapping)
                        result = subprocess.run([shutil.which('python3'), SCRIPT, '--inventory', '--check', '--map', path],
                                                capture_output=True, text=True, timeout=30)
                        self.assertEqual(result.returncode, 1)
                        self.assertIn(source, result.stderr)

    def test_ast_declaration_and_hidden_behavior(self):
        sources = {'interface': '<?php interface I { public function a(): void; }',
                   'enum': '<?php enum E: string { case A = "a"; }',
                   'dto': '<?php final readonly class D { public function __construct(public string $id) {} }',
                   'abstract-behavior': '<?php abstract class B { public function a(): bool { return true; } }',
                   'dto-branch': '<?php final readonly class D { public function __construct(public string $id) { if (!$id) {throw new Exception;} } }',
                   'enum-behavior': '<?php enum E {case A; public function a(): bool {return true;} }',
                   'default-expression': '<?php final readonly class D { public function __construct(public bool $a = false) {} }',
                   'interface-expression': '<?php interface I {public function a(bool $v = false): void;}',
                   'declare-behavior': '<?php declare(ticks=1) {echo "side effect";}'}
        ast = m.ast_sources(m.ROOT, sources)
        self.assertEqual({p for p, a in ast.items() if a['declaration_only']}, {'interface', 'enum', 'dto'})

    def test_aggregate_missing_fragments_is_red(self):
        with tempfile.TemporaryDirectory() as tmp:
            with self.assertRaisesRegex(m.MutationError, 'Missing fragment'):
                m.aggregate(self.mapping, Path(tmp), m.ROOT, {})


class ProcessTests(unittest.TestCase):
    def test_missing_or_incomplete_pg_coverage_evidence_is_red(self):
        with tempfile.TemporaryDirectory() as tmp:
            base = Path(tmp)
            directory = base / 'coverage-xml'
            directory.mkdir()
            (directory / 'index.xml').write_text('<index/>')
            (directory / 'file.xml').write_text('<file/>')
            with self.assertRaisesRegex(m.MutationError, 'Missing required coverage'):
                m.coverage_evidence([], directory, base, [], base)
            with self.assertRaisesRegex(m.MutationError, 'Incomplete coverage'):
                m.coverage_evidence([m.artifact(directory / 'index.xml', base)], directory, base, [], base)
            ref = m.artifact(directory / 'index.xml', base)
            (directory / 'index.xml').write_text('<changed/>')
            with self.assertRaisesRegex(m.MutationError, 'Missing/stale artifact'):
                m.coverage_evidence([ref], directory, base, [], base)

    def test_source_hash_change_invalidates_raw_coverage_xml(self):
        with tempfile.TemporaryDirectory() as tmp:
            base = Path(tmp)
            source = base / 'packages/core/src'
            source.mkdir(parents=True)
            path = source / 'Example.php'
            path.write_text('<?php function foo() {return true;}')
            directory = base / 'coverage-xml'
            directory.mkdir()
            (directory / 'file.xml').write_text('<phpunit/>')
            import hashlib
            file_hash = hashlib.sha1(path.read_bytes()).hexdigest()
            (directory / 'index.xml').write_text('<phpunit xmlns="https://schema.phpunit.de/coverage/1.0">'
                '<project source="' + str(source) + '"><directory name="/">'
                '<file name="Example.php" href="file.xml" hash="' + file_hash + '"/>'
                '</directory></project></phpunit>')
            m.check_coverage(directory, ['packages/core/src/Example.php'], base)
            path.write_text('<?php function foo() {return false;}')
            with self.assertRaisesRegex(m.MutationError, 'Coverage source hash mismatch'):
                m.check_coverage(directory, ['packages/core/src/Example.php'], base)

    def test_actual_pest_bootstrap_and_discovery_failures_are_infrastructure(self):
        with tempfile.TemporaryDirectory() as tmp:
            base = Path(tmp)
            (base / 'empty').mkdir()
            for bootstrap in (str(base / 'missing-bootstrap.php'), str(m.ROOT / 'vendor/autoload.php')):
                xml = base / 'phpunit.xml'
                xml.write_text('<phpunit bootstrap="' + bootstrap + '" failOnEmptyTestSuite="true">'
                               '<testsuites><testsuite name="negative"><directory>' + str(base / 'empty') +
                               '</directory></testsuite></testsuites></phpunit>')
                result = subprocess.run([shutil.which('php'), m.ROOT / 'vendor/pestphp/pest/bin/pest',
                                         '--configuration=' + str(xml), '--colors=never'], cwd=m.ROOT,
                                        capture_output=True, text=True, timeout=30, env={**os.environ, 'PAO_DISABLE': '1'})
                output = result.stdout + result.stderr
                self.assertTrue(m.INFRA.search(output), output)

    def test_real_exit137_keeps_raw_exit_stdout_stderr(self):
        with tempfile.TemporaryDirectory() as tmp:
            base = Path(tmp)
            env = {**os.environ, 'COREX_MUTATION_LOGS': str(base / 'processes')}
            result = subprocess.run([shutil.which('python3'), SCRIPT, '--supervise', '--', shutil.which('php'),
                                     '-r', 'fwrite(STDOUT,"process marker"); fwrite(STDERR,"error marker"); exit(137);'],
                                    env=env, capture_output=True, timeout=30)
            self.assertEqual(result.returncode, 137)
            record = m.load(next((base / 'processes').glob('*.json')))
            self.assertEqual(record['exit'], 137)
            self.assertEqual(m.verify_artifact(record['stdout'], base).read_text(), 'process marker')
            self.assertEqual(m.verify_artifact(record['stderr'], base).read_text(), 'error marker')

    def test_timeout_terminates_the_child_and_retains_evidence(self):
        with tempfile.TemporaryDirectory() as tmp:
            base = Path(tmp)
            marker = base / 'child.pid'
            env = {**os.environ, 'COREX_MUTATION_LOGS': str(base / 'processes')}
            process = subprocess.Popen([shutil.which('python3'), SCRIPT, '--supervise', '--', shutil.which('php'),
                                        '-r', 'file_put_contents($argv[1],getmypid()); sleep(30);', marker,
                                        '--configuration=' + str(base / ('pest.' + 'a' * 32 + '.xml'))],
                                       env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            try:
                deadline = time.monotonic() + 10
                while not marker.exists() and time.monotonic() < deadline:
                    time.sleep(.02)
                self.assertTrue(marker.exists())
                child = int(marker.read_text())
                process.send_signal(signal.SIGTERM)
                process.wait(timeout=10)
                record = m.load(base / 'processes' / ('a' * 32 + '.json'))
                self.assertTrue(record['terminated'])
                with self.assertRaises(ProcessLookupError):
                    os.kill(child, 0)
            finally:
                if process.poll() is None:
                    process.kill()
                    process.wait()


if __name__ == '__main__':
    unittest.main()
