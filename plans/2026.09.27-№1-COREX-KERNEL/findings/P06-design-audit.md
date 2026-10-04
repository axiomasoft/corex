# Design audit — P00–P06 + P10

MODE: design, read-only product and authored contracts
INPUT: current finish `d9961145d19f227c16b137ecd0fe18f654596778c605ef5becdcf9788408b1c2`
SCOPE: eight declared phases, 30 item contracts, 24 execution-sheet admissions; changed risk P06
DO NOT TOUCH: product, tests, decisions, phase/item specs, dossier, examples, roadmap or plan.md

**Verdict: ATTENTION** — 0 Blocker, 6 Major, 2 Minor. P06 execution remains closed by the required design-audit gate.

Root audit: `codex/gpt-6.1-sol/xhigh` (provider-thread-state).
Independent reviewer: fresh isolated `gpt-6-astra/xhigh`; same current fingerprint and raw repository inputs,
without the finish author's reasoning. Independent report: `artifacts/P06-design-audit/independent-review.md`.
Root reran the four small PHP reproductions directly and verified the remaining findings against source/tooling.

## A1 — Major: planned 415 problem cannot enter the current registry

Owner: P06.1. Location: `artifacts/P06-design/examples.md:68` and `research/P06-design.md:68`.
The definition combines HTTP 415 with `ErrorCategory::Unsupported`; the planned reader's `ApiError` supplies no
category. `CompiledProblemRegistry::categoryForStatus()` (`packages/core/src/Errors/Internal/CompiledProblemRegistry.php:130`)
maps 415 to Validation, and `ApiError` also derives Validation for that occurrence. Instantiating the real registry
with the exact proposed definition throws `ERROR-INVALID-DEFINITION operations.unsupported_media_type`.
Consequently boot and the declared error-registry acceptance cannot pass with this definition.

Repair: explicitly align definition, occurrence and registry status/category rules while preserving the accepted
415 status. If changing core status semantics, declare its affected source/contract and verification; otherwise
correct the planned category to the existing rule. Add a focused 415 serialization/registry assertion.
Proof: `http-415-registry.php`, `http-415-registry.json`, `root-reproduction-verification.json`.

## A2 — Major: retry budget misses lost-worker recovery

Owner: P06.4. Locations: `research/P06-design.md:100`–`105`, `phases/P06/P06.4.md:13`.
The design moves the budget into `WebhookRetryPolicy`, but does not include `SqlWebhookDeliveryStore`.
Its `recoverExpired()` (`packages/webhooks/src/Internal/SqlWebhookDeliveryStore.php:238`) independently uses
12 attempts and PT72H. It runs when a worker dies after claiming a delivery, bypassing the retry policy.
The real method, invoked with a mock connection and no database, records retry/ack_unknown when attempt=3 under
maxAttempts=3, and when age=7200 under retryHorizonSeconds=3600; the accepted budget requires dead in both cases.
The opposite configuration maxAttempts=20 would still terminate after the old 12-attempt threshold.

Repair: apply the same validated options and reason classification to normal completion and expired-lease recovery;
include the store in Files. Add recovery acceptance for both attempt and horizon boundaries, including process-loss behavior.
Proof: `webhook-lease-budget.php`, `webhook-lease-budget.json`, `root-reproduction-verification.json`.

## A3 — Major: delivery persistence does not fit the closed failure taxonomy

Owner: P06.4. Locations: `decisions/D90-p06-wire-contract.md:21`, `research/P06-design.md:101`,
`phases/P06/P06.4.md:17`. The normative result changes `decide()['error']` to WebhookFailureReason and requires
last_error to contain only enum values. The unchanged store passes that value to `finishAttempt(..., ?string $error, ...)`
(`SqlWebhookDeliveryStore.php:303`) and writes it directly into last_error. It also writes `ack_unknown` and
`webhook_endpoint_changed` (lines 252/153), neither of which belongs to D90's closed 18-case set.
Preserving the declared store leaves both the typed persistence seam and lost-ack/changed-subscription semantics unresolved.

Repair: explicitly choose and document how those two existing outcomes map into the accepted taxonomy, preserve
unknown-ack safety, and serialize enum values at both persistence sites. Include the store and tests for rejected
claims, expired leases and attempt rows in Files/Validation. A new public enum choice must use the existing decision mechanism.
Proof: source locations above; `webhook-lease-budget.json` shows an actual out-of-set persisted value without a database.

## A4 — Major: external projected data may advertise its internal schema

Owner: P06.3. Locations: `decisions/D90-p06-wire-contract.md:20`, `artifacts/P06-design/examples.md:214`.
D90 takes dataschema from MessageContract for both profiles, while encodeExternal replaces data with the projector's output.
The existing ExportProjector (`packages/webhooks/tests/Feature/WebhookFanoutTest.php:263`) strips secret from the internal
payload. A permitted MessageContract schema requiring id and secret validates the internal payload but rejects the projected
external payload. The proposed external profile still declares that internal schema URI. This violates the adopted
CloudEvents meaning of dataschema (phase primary capture `http-01-cloudevents-spec.md:173`).

Repair: accept an explicit external-schema rule, for example a schema for the projected contract or omission of the internal
schema URI in the external profile; update D90, examples and validation together. Do not restore private fields to satisfy the schema.
Add the redacting-projector plus non-null dataschema counterexample to acceptance.
Proof: `external-dataschema.php`, `external-dataschema.json`, `root-reproduction-verification.json`.

## A5 — Major: Review P06 cannot resolve its required bundle inputs

Owner: P06.5. Location: `phases/P06/P06.5.md:15`.
The exact Task parser sees a bare quoted P06.4.md in the ellipsis expression; that file exists only under phases/P06,
not at either resolver root. Required Read 5, “diff фазы по путям Files”, contains no resolvable declared source.
plan_bundle.model rejects these as unresolvable_entry even after the actual P06.1–P06.4 completion artifacts are produced.
The brace expressions for completion artifacts expand correctly and are not findings.

Repair: use full paths for every spec (a qualified brace expression is supported). Declare a portable predecessor-produced diff
artifact, or move the diff-generation operation to Code Guidance and keep Required Reads as resolvable carriers.
Do not manufacture predecessor completion evidence to make the future bundle render early.
Proof: `P06.5-read-declarations.json`, `P06.5-static-bundle-repro.json`; actual installed Task parser/resolver used.

## A6 — Major: public surface deltas have no declared changelog scope

Owners: P06.1–P06.4. Location: each item's Files at line 13 and dossier §4.
Each item updates package php-api.json and public contracts, but none declares matching package CHANGELOG.md files.
The adopted contract discipline (`docs/dev-doctrine.md` §5, PLAN3.D64 Consequences) and `scripts/contract-guard.sh`
require the same-package changelog in the candidate diff. An isolated committed Git fixture runs that actual gate:
manifest-only diff exits 1; adding a same-package changelog exits 0. Required hosted CI therefore cannot accept
an implementation confined to these declared file sets.

Repair: explicitly include and require the matching changelog deltas for operations, tools, core and webhooks,
as applicable per item. Add the already adopted contract guard to the focused verification of those deltas.
Proof: `reproduce-contract-guard.py`, `contract-guard-repro.log`; fixture touches no project refs or product files.

## m1 — Minor: ToolSchemaFailure cannot redeclare Exception::$code

Owner: P06.2. Location: `research/P06-design.md:78`.
The prescribed readonly ToolsErrorCode $code property collides with RuntimeException's inherited non-readonly property.
PHP 8.4 exits 255 before execution. Use a distinct internal property such as errorCode and update its readers;
this does not require a new public outcome or change the accepted tools codes.
Proof: `tool-exception-code.php`, `tool-exception-code.json`.

## m2 — Minor: source character allowlist is not a URI-reference validator

Owner: P06.3. Location: `research/P06-design.md:89`.
The exact regex accepts bare `%` and `%zz`; RFC 3986 §2.1 requires two hexadecimal digits in a percent escape.
The item already requires a valid URI-reference, so implement that requirement rather than copying the permissive regex.
Retain valid relative sources, and add malformed escape cases to validation.
RAG:✅ [RFC 3986 §2.1](https://www.rfc-editor.org/rfc/rfc3986.html#section-2.1).
Proof: `reproduce-source-check.php`, `source-check-repro.log`, `rfc3986-source-verification.md`.

## Coverage and limits

- Exact current finish and declared supporting set checked; all eight dossiers/retrieval notes/examples/capture indexes exist
  and are linked. Routing compiler and 24 exact sheet rows agree. P06 dependencies follow P05 → P06.1 → … → P06.5.
- P00–P05 and P10 remain terminal. Their prior verdicts and execution evidence are reused; those historical conclusions are
  not re-certified as current live/runtime results by this design audit.
- plan-lint: exit 0, zero errors, one historical P04 workflow-prose warning. P06.1 dry bundle: 20 entries.
  Future producer-dependent bundles were not materialized; their static declarations were checked where needed.
- Four focused PHP reproductions, the contract-guard Git fixture and source regex repro ran. No PostgreSQL connection,
  product test suite, hosted CI, mutation harness or product implementation was run.
- Root hashes all 2743 accessible tracked files outside plans before/after; existing CLAUDE.md and graph-report changes
  were preserved. Authoring fingerprint is unchanged. The actual before/after results are saved with finalization.
- The installed Task 0.27.0 runtime is unchanged. Its core.provider dependency resolves through the adjacent source root
  via PYTHONPATH, matching the finish's recorded fallback. No tooling behavior or plan schema was rewritten for this audit.

## Owning continuation

Repair the bounded P06 design findings, then repeat finish and independent design audit for the changed fingerprint.
P06 product execution is not admitted by this ATTENTION verdict. No implementation item is marked blocked or terminal.
A1–A6 and m1–m2 have exact owning items above; this is a same-root recommendation for authorized correction,
not an automatic new root, product execution or a second audit invocation.
