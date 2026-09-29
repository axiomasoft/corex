"""Regression checks for namespace moves without changing runtime/debt semantics."""
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[5]
SCRIPT = Path(__file__).with_name('p03-transfer.py')
SPEC = importlib.util.spec_from_file_location('transfer', SCRIPT)
TRANSFER = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(TRANSFER)


class TransferTest(unittest.TestCase):
    def test_golden_projection_rejects_frozen_drift_and_unplanned_rules(self):
        with tempfile.TemporaryDirectory() as folder:
            previous = Path.cwd()
            try:
                os.chdir(folder)
                subprocess.run(['git', 'init', '-q'], check=True)
                target = Path(TRANSFER.GOLDEN_MAP)
                target.parent.mkdir(parents=True)
                target.write_text(json.dumps({'generated': [{'kind': 'class', 'from': 'Old\\A', 'to': 'Current\\A'}],
                                              'ambiguous_packages': {}, 'manual': []}))
                subprocess.run(['git', 'add', '.'], check=True)
                subprocess.run(['git', '-c', 'core.hooksPath=/dev/null', '-c', 'commit.gpgsign=false',
                                '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test',
                                'commit', '-qm', 'preimage'], check=True)
                Path('build/p03').mkdir(parents=True)
                Path('build/p03/plan.json').write_text(json.dumps({'classes': {'Current\\A': 'Next\\A'}}))
                TRANSFER.cmd_golden('build/p03')
                self.assertEqual(0, TRANSFER.cmd_golden_check('build/p03'))
                good = target.read_text()
                bad = json.loads(good)
                bad['generated'][0]['to'] = 'Wrong\\A'
                target.write_text(json.dumps(bad))
                self.assertEqual(1, TRANSFER.cmd_golden_check('build/p03'))
                bad = json.loads(good)
                bad['manual'].append({'kind': 'identifier', 'from': 'Extra\\A', 'to': 'Next\\A'})
                target.write_text(json.dumps(bad))
                self.assertEqual(1, TRANSFER.cmd_golden_check('build/p03'))
            finally:
                os.chdir(previous)

    def test_package_relative_baseline_paths_preserve_debt(self):
        with tempfile.TemporaryDirectory() as folder:
            previous = Path.cwd()
            try:
                os.chdir(folder)
                baseline = Path('packages/example/phpstan-baseline.neon')
                baseline.parent.mkdir(parents=True)
                before = 'parameters:\n    ignoreErrors:\n        -\n            message: unchanged\n            count: 2\n            path: src/Models/Entry.php\n'
                baseline.write_text(before)
                plan = {'moves': [('packages/example/src/Models/Entry.php',
                                    'packages/example/src/Internal/Models/Entry.php')]}
                TRANSFER.baseline_paths(plan)
                expected = before.replace('path: src/Models/', 'path: src/Internal/Models/')
                self.assertEqual(expected, baseline.read_text())
                TRANSFER.baseline_paths(plan)
                self.assertEqual(expected, baseline.read_text())
            finally:
                os.chdir(previous)

    def test_moved_caller_keeps_unmoved_sibling_type(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            def run(args):
                return subprocess.run(args, cwd=root, check=True, capture_output=True, text=True).stdout
            run(['git', 'init', '-q'])
            run(['git', 'config', 'user.email', 'fixture@example.test'])
            run(['git', 'config', 'user.name', 'Transfer fixture'])
            caller = '<?php\nnamespace Example;\nfinal class Caller { public function make(): Stable { return new Stable(); } }\n'
            (root / 'Caller.php').write_text(caller)
            (root / 'Stable.php').write_text('<?php\nnamespace Example;\nfinal class Stable {}\n')
            run(['git', 'add', '.'])
            run(['git', '-c', 'core.hooksPath=/dev/null', '-c', 'commit.gpgsign=false', 'commit', '-qm', 'fixture'])
            (root / 'Internal').mkdir()
            (root / 'Internal/Caller.php').write_text(caller.replace('namespace Example;', 'namespace Example\\Internal;'))
            (root / 'Caller.php').unlink()
            (root / 'vendor').symlink_to(ROOT / 'vendor', target_is_directory=True)
            (root / 'build/p02').mkdir(parents=True)
            (root / 'build/p02/plan.json').write_text(json.dumps({
                'moves': [['Caller.php', 'Internal/Caller.php']],
                'class_sources': {'Example\\Caller': 'Caller.php', 'Example\\Stable': 'Stable.php'},
                'all_classes': {'Example\\Caller': 'Example\\Internal\\Caller', 'Example\\Stable': 'Example\\Stable'},
            }))
            check = ['scripts/php84', 'php']
            check[0] = str(ROOT / check[0])
            runtime = "require 'Stable.php'; require 'Internal/Caller.php'; echo get_class((new Example\\Internal\\Caller)->make());"
            before = subprocess.run(check + ['-r', runtime], cwd=root, capture_output=True)
            self.assertNotEqual(0, before.returncode)
            output = run(check + [str(ROOT / TRANSFER.P02 / 'p02-imports.php')])
            self.assertIn('implicit imports: 1 preserved', output)
            self.assertEqual('Example\\Stable', run(check + ['-r', runtime]))


if __name__ == '__main__':
    unittest.main()
