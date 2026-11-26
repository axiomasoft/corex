"""Synthetic boundary fixtures, not evidence of historical/live dispatch."""
from __future__ import annotations

from dataclasses import replace
import sys
from pathlib import Path
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from publish_guard import Admission, Evidence, Operation, PublicationDenied, UnknownOutcome, publish
from github_transport import GitHubTransport


class FakeTransport:
    def __init__(self, current=None, unknown=None, mismatch=False):
        self.current = current
        self.unknown = unknown
        self.mismatch = mismatch
        self.calls = []

    def reconcile(self, operation):
        self.calls.append("read")
        return self.current

    def apply(self, operation):
        self.calls.append("write")
        if self.unknown != "absent":
            self.current = "collision" if self.mismatch else operation.fingerprint
        if self.unknown:
            raise UnknownOutcome()


class PublicationBoundaryTest(unittest.TestCase):
    def setUp(self):
        self.operation = Operation("axiomasoft/corex-core", "tag", "v0.1.0-alpha.3", "exact-tag-object")
        self.evidence = Evidence(self.operation, "sealed-source", "sealed-zip", "exact-grant")
        self.admission = Admission(frozenset({self.evidence}), frozenset({self.operation.repository}), True)

    def assert_no_write(self, evidence=None, admission=None, current=None):
        transport = FakeTransport(current=current)
        with self.assertRaises(PublicationDenied):
            publish(evidence or self.evidence, admission or self.admission, transport)
        self.assertNotIn("write", transport.calls)

    def test_denied_grant_writes_nothing(self):
        self.assert_no_write(admission=replace(self.admission, allowed=frozenset()))

    def test_stale_source_artifact_authority_each_write_nothing(self):
        for field in ("source_digest", "artifact_digest", "authority_digest"):
            with self.subTest(field=field):
                self.assert_no_write(evidence=replace(self.evidence, **{field: "stale"}))

    def test_reference_collision_writes_nothing(self):
        self.assert_no_write(current="different-immutable-object")

    def test_accepted_exception_is_exact_evidence_bound_not_narrow_capability(self):
        admission = replace(self.admission, credential_scope_verified=False,
                            credential_exception=frozenset({self.evidence}))
        transport = FakeTransport()
        self.assertEqual(publish(self.evidence, admission, transport), "published-verified")
        self.assertFalse(admission.credential_scope_verified)
        self.assert_no_write(evidence=replace(self.evidence, authority_digest="other"),
                             admission=admission)

    def test_broad_or_foreign_credential_writes_nothing(self):
        self.assert_no_write(admission=replace(self.admission, credential_scope_verified=False))
        self.assert_no_write(admission=replace(self.admission, credential_repositories=frozenset()))

    def test_unapproved_resource_or_reference_writes_nothing(self):
        for field, value in (("repository", "axiomasoft/unapproved"), ("reference", "v-other")):
            with self.subTest(field=field):
                changed = replace(self.operation, **{field: value})
                self.assert_no_write(evidence=replace(self.evidence, operation=changed))

    def test_secret_packagist_force_delete_even_if_injected_are_denied(self):
        for kind in ("secret", "packagist", "force-push", "delete"):
            with self.subTest(kind=kind):
                evidence = replace(self.evidence, operation=replace(self.operation, kind=kind))
                admission = replace(self.admission, allowed=frozenset({evidence}))
                self.assert_no_write(evidence=evidence, admission=admission)

    def test_matching_reference_is_read_only_no_op(self):
        transport = FakeTransport(current=self.operation.fingerprint)
        self.assertEqual(publish(self.evidence, self.admission, transport), "matching-no-op")
        self.assertEqual(transport.calls, ["read"])

    def test_success_has_exact_read_write_read_order(self):
        transport = FakeTransport()
        self.assertEqual(publish(self.evidence, self.admission, transport), "published-verified")
        self.assertEqual(transport.calls, ["read", "write", "read"])

    def test_unknown_applied_is_reconciled_then_replay_no_ops(self):
        transport = FakeTransport(unknown="applied")
        self.assertEqual(publish(self.evidence, self.admission, transport), "unknown-reconciled")
        self.assertEqual(transport.calls, ["read", "write", "read"])
        self.assertEqual(publish(self.evidence, self.admission, transport), "matching-no-op")
        self.assertEqual(transport.calls.count("write"), 1)

    def test_unknown_absent_is_reconciled_without_retry(self):
        transport = FakeTransport(unknown="absent")
        with self.assertRaises(PublicationDenied):
            publish(self.evidence, self.admission, transport)
        self.assertEqual(transport.calls, ["read", "write", "read"])

    def test_mismatched_write_result_has_no_retry(self):
        transport = FakeTransport(mismatch=True)
        with self.assertRaises(PublicationDenied):
            publish(self.evidence, self.admission, transport)
        self.assertEqual(transport.calls, ["read", "write", "read"])


class LiveAdapterBoundaryTest(unittest.TestCase):
    def transport(self, *, write=True):
        record = {'name': 'axiomasoft/example-provider', 'tag_proposal': 'v2.0.0-alpha1'}
        return GitHubTransport('jobpipeline', record, Path('/virtual/staging'), archive=Path('/virtual/candidate.zip'), write=write)

    def test_read_only_adapter_never_calls_write_transport(self):
        operation = Operation('axiomasoft/example-provider', 'create', 'repository', 'public')
        with patch('github_transport.run') as command:
            with self.assertRaises(PublicationDenied):
                self.transport(write=False).apply(operation)
            command.assert_not_called()

    def test_enabled_actions_prevent_any_push(self):
        operation = Operation('axiomasoft/example-provider', 'push', 'refs/heads/main', 'commit')
        with patch('github_transport.api', return_value={'enabled': True}), patch('github_transport.run') as command:
            with self.assertRaises(PublicationDenied):
                self.transport().apply(operation)
            command.assert_not_called()

    def test_actions_disable_is_exact_repository_boolean_setting(self):
        operation = Operation('axiomasoft/example-provider', 'actions-disable', 'actions/permissions', 'disabled')
        with patch('github_transport.run') as command:
            self.transport().apply(operation)
            self.assertEqual(command.call_args.args[0],
                             ['gh', 'api', '-X', 'PUT', 'repos/axiomasoft/example-provider/actions/permissions', '-F', 'enabled=false'])

    def test_push_uses_only_exact_main_refspec_after_disabled_check(self):
        operation = Operation('axiomasoft/example-provider', 'push', 'refs/heads/main', 'commit')
        with patch('github_transport.api', return_value={'enabled': False}) as read, patch('github_transport.run') as command:
            self.transport().apply(operation)
            read.assert_called_once_with('repos/axiomasoft/example-provider/actions/permissions')
            self.assertEqual(command.call_args.args[0],
                             ['git', 'push', 'https://github.com/axiomasoft/example-provider.git', 'refs/heads/main:refs/heads/main'])


if __name__ == "__main__":
    unittest.main()
