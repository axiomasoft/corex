"""P17.1 deterministic release-candidate boundary tests."""
from __future__ import annotations

import copy
import importlib.util
import json
import subprocess
import sys
import tempfile
import unittest
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
SCRIPT = ROOT / 'scripts/distribution/release_candidate.py'
SPEC = importlib.util.spec_from_file_location('release_candidate', SCRIPT)
assert SPEC and SPEC.loader
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)


class ReleaseCandidateTest(unittest.TestCase):
    def setUp(self) -> None:
        self.temporary = tempfile.TemporaryDirectory(prefix='corex-release-test-')
        self.root = Path(self.temporary.name)
        self.git('init', '-q')
        self.git('config', 'user.email', 'candidate@example.test')
        self.git('config', 'user.name', 'Candidate Test')
        self.write('LICENSE', 'fixture license\n')
        self.write_package('alpha', 'axiomasoft/corex-alpha', {'axiomasoft/corex-beta': '@dev', 'vendor/exact': '1.2.3'})
        self.write_package('beta', 'axiomasoft/corex-beta', {})
        self.write_package('access-azguard', 'axiomasoft/corex-access-azguard', {}, stability='unreleased')
        self.write_package('flex-crm', 'flex/crm', {'axiomasoft/corex-alpha': '@dev'}, package_type='product')
        self.git('add', '-A')
        self.git('commit', '-qm', 'fixture')
        self.commit = self.git('rev-parse', 'HEAD').strip()
        self.policy = {
            'schema_version': 'corex-release-policy/1',
            'include': ['composer.json', 'src/', 'README.md', 'LICENSE'],
            'forbidden_segments': ['vendor', 'node_modules', 'tests', '.env', 'auth.json', '.git', '.plan-cache'],
            'required_if_present': ['README.md'],
            'channels': {
                'packages/alpha': {'distribution': 'public', 'mirror': 'example/corex-alpha'},
                'packages/beta': {'distribution': 'private', 'mirror': 'example/corex-beta'},
            },
            'excluded': [
                {'path': 'products/flex-crm', 'reason': 'flex-product-consumer'},
                {'path': 'packages/access-azguard', 'reason': 'unreleased'},
            ],
        }

    def tearDown(self) -> None:
        self.temporary.cleanup()

    def git(self, *args: str) -> str:
        return subprocess.check_output(('git', *args), cwd=self.root, text=True)

    def write(self, relative: str, body: str) -> None:
        path = self.root / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(body, encoding='utf-8')

    def write_package(
        self,
        path: str,
        name: str,
        requires: dict[str, str],
        *,
        package_type: str = 'capability',
        stability: str = 'experimental',
    ) -> None:
        composer = {
            'name': name,
            'require': {'php': '^8.3', **requires},
            'repositories': [{'type': 'path', 'url': '../*'}],
            'autoload': {'psr-4': {f"Fixture\\{path.title()}\\": 'src/'}},
            'extra': {
                'corex': {
                    'schema-version': 1,
                    'package-type': package_type,
                    'stability': stability,
                },
            },
        }
        package_root = 'products' if package_type == 'product' else 'packages'
        self.write(f'{package_root}/{path}/composer.json', json.dumps(composer, indent=2) + '\n')
        self.write(f'{package_root}/{path}/README.md', f'# {name}\n')
        self.write(f'{package_root}/{path}/src/Example.php', '<?php\n')
        self.write(f'{package_root}/{path}/tests/Excluded.php', '<?php\n')

    def build(self, name: str = 'candidate') -> tuple[Path, dict]:
        output = self.root / name
        candidate = MODULE.build_repository(
            self.root,
            self.commit,
            '1.0.0',
            output,
            self.policy,
            enforce_clean=True,
        )
        return output, candidate

    def write_candidate(self, output: Path, candidate: dict) -> None:
        (output / 'candidate.json').write_bytes(MODULE.pretty_json(candidate))

    def test_two_builds_are_byte_identical_and_package_only(self) -> None:
        first, candidate = self.build('first')
        second, _ = self.build('second')

        self.assertEqual(MODULE.output_files(first), MODULE.output_files(second))
        self.assertEqual(['axiomasoft/corex-alpha', 'axiomasoft/corex-beta'], [row['name'] for row in candidate['packages']])
        alpha = first / 'artifacts/axiomasoft-corex-alpha-1.0.0.zip'
        with zipfile.ZipFile(alpha) as archive:
            self.assertNotIn('tests/Excluded.php', archive.namelist())
            composer = json.loads(archive.read('composer.json'))
        self.assertNotIn('version', composer)
        self.assertNotIn('repositories', composer)
        self.assertEqual('^1.0', composer['require']['axiomasoft/corex-beta'])
        self.assertEqual('>=1.2.3 <=1.2.3', composer['require']['vendor/exact'])
        MODULE.verify_candidate(first / 'candidate.json', self.root, self.policy, composer_checks=False)

    def test_build_rejects_dirty_package_source(self) -> None:
        self.write('packages/alpha/src/Dirty.php', '<?php\n')

        with self.assertRaisesRegex(MODULE.CandidateError, 'source is dirty'):
            self.build()

    def test_verify_rejects_omitted_package(self) -> None:
        output, candidate = self.build()
        candidate['packages'].pop()
        self.write_candidate(output, candidate)

        with self.assertRaisesRegex(MODULE.CandidateError, 'package set mismatch'):
            MODULE.verify_candidate(output / 'candidate.json', self.root, self.policy, composer_checks=False)

    def test_verify_rejects_extra_package(self) -> None:
        output, candidate = self.build()
        extra = copy.deepcopy(candidate['packages'][0])
        extra['name'] = 'axiomasoft/corex-gamma'
        extra['path'] = 'packages/gamma'
        candidate['packages'].append(extra)
        candidate['packages'].sort(key=lambda row: row['name'])
        self.write_candidate(output, candidate)

        with self.assertRaisesRegex(MODULE.CandidateError, 'unexpected candidate package path'):
            MODULE.verify_candidate(output / 'candidate.json', self.root, self.policy, composer_checks=False)

    def test_verify_rejects_wrong_channel(self) -> None:
        output, candidate = self.build()
        candidate['packages'][0]['distribution'] = 'private'
        self.write_candidate(output, candidate)

        with self.assertRaisesRegex(MODULE.CandidateError, 'wrong distribution channel'):
            MODULE.verify_candidate(output / 'candidate.json', self.root, self.policy, composer_checks=False)

    def test_verify_rejects_forbidden_archive_member(self) -> None:
        output, candidate = self.build()
        archive = output / 'artifacts/axiomasoft-corex-alpha-1.0.0.zip'
        with zipfile.ZipFile(archive, 'a') as target:
            target.writestr('tests/Leak.php', '<?php\n')
        candidate['packages'][0]['archive_sha256'] = MODULE.sha256_path(archive)
        self.write_candidate(output, candidate)

        with self.assertRaisesRegex(MODULE.CandidateError, 'forbidden archive member'):
            MODULE.verify_candidate(output / 'candidate.json', self.root, self.policy, composer_checks=False)

    def test_dependency_gap_is_rejected(self) -> None:
        output, candidate = self.build()
        original = output / 'artifacts/axiomasoft-corex-alpha-1.0.0.zip'
        tree = {}
        with zipfile.ZipFile(original) as source:
            for name in source.namelist():
                tree[name] = {'bytes': source.read(name), 'mode': '100644'}
        composer = json.loads(tree['composer.json']['bytes'])
        composer['require']['axiomasoft/corex-missing'] = '^1.0'
        tree['composer.json']['bytes'] = MODULE.pretty_json(composer)
        archive = output / 'gap.zip'
        archive_sha = MODULE.write_archive(archive, tree)
        row = copy.deepcopy(candidate['packages'][0])
        row['archive_sha256'] = archive_sha
        row['composer_sha256'] = MODULE.sha256_bytes(tree['composer.json']['bytes'])
        row['tree_sha256'] = MODULE.sha256_bytes(MODULE.canonical_json(MODULE.tree_map(tree)))

        with self.assertRaisesRegex(MODULE.CandidateError, 'dependency gap: axiomasoft/corex-missing'):
            MODULE.validate_archive(
                archive,
                row,
                '1.0.0',
                {'axiomasoft/corex-alpha', 'axiomasoft/corex-beta'},
                self.policy,
                self.root,
            )

    def test_verify_rejects_workspace_path_leakage(self) -> None:
        output, _ = self.build()
        repository = json.loads((output / 'packages.json').read_text())
        repository['workspace'] = str(self.root)
        (output / 'packages.json').write_bytes(MODULE.pretty_json(repository))

        with self.assertRaisesRegex(MODULE.CandidateError, 'workspace path leaked'):
            MODULE.verify_candidate(output / 'candidate.json', self.root, self.policy, composer_checks=False)

    def test_verify_rejects_one_byte_archive_drift(self) -> None:
        output, _ = self.build()
        archive = output / 'artifacts/axiomasoft-corex-alpha-1.0.0.zip'
        archive.write_bytes(archive.read_bytes() + b'!')

        with self.assertRaisesRegex(MODULE.CandidateError, 'archive digest mismatch'):
            MODULE.verify_candidate(output / 'candidate.json', self.root, self.policy, composer_checks=False)

    def test_verify_rejects_wrong_commit(self) -> None:
        output, candidate = self.build()
        candidate['source_commit'] = '0' * 40
        self.write_candidate(output, candidate)

        with self.assertRaises(MODULE.CandidateError):
            MODULE.verify_candidate(output / 'candidate.json', self.root, self.policy, composer_checks=False)


if __name__ == '__main__':
    unittest.main()
