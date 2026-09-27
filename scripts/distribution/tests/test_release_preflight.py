"""Release boundary negatives: unknown/stale/colliding never becomes publishable."""
from __future__ import annotations

import copy
import importlib.util
import json
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

SCRIPTS = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(SCRIPTS))
SPEC = importlib.util.spec_from_file_location('release_preflight', SCRIPTS / 'release_preflight.py')
assert SPEC and SPEC.loader
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)


class PreflightTest(unittest.TestCase):
    def setUp(self) -> None:
        self.source = 'a' * 40
        self.cohort = {'source_commit': self.source}
        self.dod = {'schema_version': 'corex-dod-result/1', 'profile': 'release',
                    'source_commit': self.source, 'candidate_source_commit': self.source,
                    'candidate_sha256': MODULE.digest(self.cohort),
                    'rows': [{'id': f'DOD-{i:02d}', 'status': 'green'} for i in range(1, 28)]}
        self.operation = {'repository': 'example/corex', 'distribution': 'public',
                          'kind': 'tag', 'reference': 'refs/tags/1.0.0', 'commit': self.source}
        self.metadata = {'full_name': 'example/corex', 'private': False, 'archived': False,
                         'permissions': {'push': True}}

    def remote(self, path: str, *, absent=False):
        if not absent:
            return self.metadata
        return None

    def test_dod_requires_all_27_current_green_rows(self) -> None:
        self.assertEqual([], MODULE.inspect_dod(self.dod, self.source, self.cohort))
        for status in ('red', 'unknown', 'skipped', 'stale', 'not-run'):
            value = copy.deepcopy(self.dod)
            value['rows'][0]['status'] = status
            self.assertEqual(status, MODULE.inspect_dod(value, self.source, self.cohort)[0]['status'])

    def test_stale_or_incomplete_result_refuses(self) -> None:
        for field in ('source_commit', 'candidate_source_commit', 'candidate_sha256', 'profile'):
            value = {**self.dod, field: 'wrong'}
            with self.assertRaises(MODULE.NoGo):
                MODULE.inspect_dod(value, self.source, self.cohort)
        self.dod['rows'].pop()
        with self.assertRaises(MODULE.NoGo):
            MODULE.inspect_dod(self.dod, self.source, self.cohort)

    def test_changed_packet_byte_including_whitespace_refuses(self) -> None:
        with tempfile.TemporaryDirectory() as temporary:
            path = Path(temporary) / 'packet.json'
            path.write_text(json.dumps({'schema_version': 'publication-packet/v1'}))
            expected = MODULE.candidate.sha256_path(path)
            MODULE.verify_packet(path, expected)
            path.write_bytes(path.read_bytes() + b'\n')
            with self.assertRaisesRegex(MODULE.NoGo, 'digest changed'):
                MODULE.verify_packet(path, expected)

    def test_remote_ref_collision_refuses_without_write(self) -> None:
        calls = []

        def read(path, *, absent=False):
            calls.append(path)
            return {'object': {'type': 'commit', 'sha': 'b' * 40}} if absent else self.metadata

        with self.assertRaisesRegex(MODULE.NoGo, 'collision'):
            MODULE.reconcile([self.operation], read)
        self.assertEqual(['repos/example/corex', 'repos/example/corex/git/ref/tags/1.0.0'], calls)

    def test_absent_and_exact_remote_ref_are_allowed(self) -> None:
        self.assertEqual('absent', MODULE.reconcile([self.operation], self.remote)[0]['state'])

        def read(path, *, absent=False):
            return {'object': {'type': 'commit', 'sha': self.source}} if absent else self.metadata

        self.assertEqual('byte-identical', MODULE.reconcile([self.operation], read)[0]['state'])

    def test_unknown_permission_visibility_and_missing_repository_refuse(self) -> None:
        for value in (None, {**self.metadata, 'permissions': {}}, {**self.metadata, 'private': True},
                      {**self.metadata, 'archived': True}):
            with self.assertRaises(MODULE.NoGo):
                MODULE.reconcile([self.operation], lambda *args, **kwargs: value)

    def test_release_without_exact_asset_digest_refuses(self) -> None:
        operation = {**self.operation, 'kind': 'release', 'title': 'corex 1.0.0', 'body': 'sealed',
                     'asset_name': 'corex.zip', 'archive_sha256': 'c' * 64}
        release = {'draft': False, 'prerelease': False, 'name': operation['title'], 'body': 'sealed',
                   'assets': [{'name': 'corex.zip', 'digest': 'sha256:' + 'c' * 64}]}

        def read(path, *, absent=False):
            return release if absent else self.metadata

        self.assertEqual('byte-identical', MODULE.reconcile([operation], read)[0]['state'])
        del release['assets'][0]['digest']
        with self.assertRaisesRegex(MODULE.NoGo, 'collision'):
            MODULE.reconcile([operation], read)

    def test_dirty_source_and_red_dod_stop_before_remote_reads(self) -> None:
        for state in ({'red_assertions': [self.dod['rows'][0]], 'dirty_source': False},
                      {'red_assertions': [], 'dirty_source': True}):
            with patch.object(MODULE, 'remote_read') as remote:
                with self.assertRaises(MODULE.NoGo):
                    MODULE.seal(Path('/unused'), {}, state, [])
                remote.assert_not_called()

    def test_remote_transport_is_explicitly_get(self) -> None:
        with patch.object(MODULE.subprocess, 'run') as run:
            run.return_value.returncode = 0
            run.return_value.stdout = b'{}'
            MODULE.remote_read('repos/example/corex')
            self.assertEqual(['gh', 'api', '--method', 'GET', 'repos/example/corex'], run.call_args.args[0])

    def test_non_test_pg_target_refuses_before_any_command(self) -> None:
        with patch.dict(MODULE.os.environ, {'PGSQL_DATABASE': 'production'}):
            with patch.object(MODULE, 'execute') as execute:
                with self.assertRaisesRegex(MODULE.NoGo, '_test'):
                    MODULE.postgres_preflight(Path('/unused'), [])
                execute.assert_not_called()

    def test_no_go_does_not_emit_or_overwrite_a_packet(self) -> None:
        with tempfile.TemporaryDirectory() as temporary:
            receipt, packet = Path(temporary) / 'receipt.json', Path(temporary) / 'packet.json'
            packet.write_text('previous immutable packet')
            argv = ['release_preflight.py', 'seal', '--receipt', str(receipt), '--packet', str(packet)]
            state = {'red_assertions': [{'id': 'DOD-24', 'status': 'red'}], 'dirty_source': True}
            with patch.object(sys, 'argv', argv):
                with patch.object(MODULE, 'check', return_value=state):
                    with patch.object(MODULE, 'seal') as seal:
                        self.assertEqual(1, MODULE.main())
                        seal.assert_not_called()
            self.assertEqual('previous immutable packet', packet.read_text())
            self.assertFalse(json.loads(receipt.read_text())['packet_emitted'])

    def test_producer_frozen_candidate_is_never_used_for_a_new_source(self) -> None:
        with self.assertRaisesRegex(MODULE.NoGo, 'producer candidate pin is stale'):
            MODULE.require_producer_candidate(SCRIPTS.parents[1], self.source, 'c' * 64)

    def test_matrix_passes_rebuilt_directory_to_candidate_owners(self) -> None:
        calls = []
        directory = Path('/tmp/first-build')
        with patch.object(MODULE, 'postgres_preflight'):
            with patch.object(MODULE, 'require_command', side_effect=lambda argv, *args: calls.append(argv)):
                MODULE.qualify(SCRIPTS.parents[1], [], [], directory)
        for name in ('verify_extension_fixtures.py', 'verify_equipment_consumer.py', 'profile-proof.py'):
            self.assertIn(['python3', 'scripts/distribution/' + name, '--candidate', str(directory)], calls)


if __name__ == '__main__':
    unittest.main()
