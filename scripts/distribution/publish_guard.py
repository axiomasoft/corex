"""Fail-closed operation boundary for exact immutable publication.

The caller owns verified authority/credential provenance and real transport.
This module does not mint grants or inspect token values. Unknown outcomes
must be reconciled; they never trigger an automatic write retry.
"""
from __future__ import annotations

from dataclasses import dataclass
from typing import Protocol


class PublicationDenied(RuntimeError):
    pass


class UnknownOutcome(RuntimeError):
    """A transport cannot establish whether a write took effect."""


@dataclass(frozen=True)
class Operation:
    repository: str
    kind: str
    reference: str
    fingerprint: str


@dataclass(frozen=True)
class Evidence:
    operation: Operation
    source_digest: str
    artifact_digest: str
    authority_digest: str


@dataclass(frozen=True)
class Admission:
    allowed: frozenset[Evidence]
    credential_repositories: frozenset[str]
    credential_scope_verified: bool
    credential_exception: frozenset[Evidence] = frozenset()


class Transport(Protocol):
    def reconcile(self, operation: Operation) -> str | None: ...
    def apply(self, operation: Operation) -> None: ...


def publish(evidence: Evidence, admission: Admission, transport: Transport) -> str:
    """Apply at most one admitted write and verify its exact remote result."""
    operation = evidence.operation
    if evidence not in admission.allowed:
        raise PublicationDenied("denied or stale source/artifact/authority proof")
    if operation.kind not in {"create", "actions-disable", "push", "tag", "release"}:
        raise PublicationDenied("operation excluded from publication boundary")
    if (operation.repository not in admission.credential_repositories
            or not admission.credential_scope_verified
            and evidence not in admission.credential_exception):
        raise PublicationDenied("verified narrow credential or exact accepted exception required")
    current = transport.reconcile(operation)
    if current == operation.fingerprint:
        return "matching-no-op"
    if current is not None:
        raise PublicationDenied("immutable reference collision")
    try:
        transport.apply(operation)
    except UnknownOutcome:
        current = transport.reconcile(operation)
        if current == operation.fingerprint:
            return "unknown-reconciled"
        raise PublicationDenied("unknown outcome unresolved; no automatic retry") from None
    if transport.reconcile(operation) != operation.fingerprint:
        raise PublicationDenied("write result mismatch; no automatic retry")
    return "published-verified"
