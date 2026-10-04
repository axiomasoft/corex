---
id: D93
date: 2026-10-03
status: accepted
item: P06.2
items: [P06.2]
supersedes: []
superseded_by: null
---
# D93 — p06-2-guard-taxonomy (accepted)

**Actor:** owner choice, recorded by executor/codex/gpt-6.1-sol/high.
**Owner instruction:** «Расширить реестр кодами guard и обновить контракт P06.2 (рекомендую)» — answer to the active design-gap question in this invocation, 2026-10-03.
**Evidence:** RAG:— `packages/tools/src/Internal/ReadOnlyToolExecutionGuard.php`; `packages/tools/tests/Support/ToolGuardFixture.php`; D90 §3; `research/P06.2-guard-amendment.md`. External research is unnecessary: this preserves existing local guard decisions and the established core error categories.

## Solution

Amend only D90 §3 and the unstarted P06.2 contract. Add five cases to `ToolsErrorCode` and tools problem definitions: `ApprovalRequired`, `ApprovalInvalid`, `Forbidden` (403, Authorization, not retryable), `IdempotencyConflict` (409, Conflict, not retryable), `UnknownEffect` (500, Internal, not retryable). Their wire values are respectively `tools.approval_required`, `tools.approval_invalid`, `tools.forbidden`, `tools.idempotency_conflict`, `tools.unknown_effect`. Together with the original eight cases the tools registry contains thirteen codes.

Preserve distinctions between absent approval, invalid/revoked approval, visibility revocation, idempotency binding conflict and unknown external effect. Preserve `ToolOutcome::Unknown` for unknown effects and all existing guard ordering, callback count, approval checks and replay semantics. An unknown effect does not authorize automatic retry. Consumer guard fixtures use the same enum values and resolve every non-null error through `ProblemRegistry`. Forwarded registered operations codes remain unchanged.

The default read-only guard may still refuse a mutation; only its error spelling and typed outcome change. Register tools definitions during `ToolServiceProvider::register()`, before the core registry freezes. Do not register them in `boot()`.

## Why

The original eight-code list omitted a live default-guard refusal and four refusal reasons demonstrated by the consumer guard. Mapping these to generic internal failure would erase meaning and would not meet P06.2's requirement that every result error resolves in the registry.

## Consequences

`research/P06.2-guard-amendment.md` supplements, rather than rewrites, the already reviewed P06 dossier/examples. P06.1 and its digest-bound inputs stay unchanged. P06.2 binds this decision and supplement, checks thirteen registered codes and explicitly includes the default guard and consumer fixture migration. Grok 4.7 independently reviews the scoped contract amendment and its implementation as requested by the owner. The original design review remains historical evidence; the new authored fingerprint requires fresh finish and independent delta review.
