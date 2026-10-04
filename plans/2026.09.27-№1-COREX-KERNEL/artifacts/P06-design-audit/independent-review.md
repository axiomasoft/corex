# Independent design review — P06 changed risk

verdict: ATTENTION
review: codex/gpt-6-astra/xhigh
date: 2026-10-03
fingerprint: d9961145d19f227c16b137ecd0fe18f654596778c605ef5becdcf9788408b1c2

0 Blocker, 6 Major, 2 Minor. Read-only review of the design; no product execution,
implementation repair, database test, full test suite, commit or staging was performed.
The reviewer wrote only this report and small reproduction files in this audit directory.

## A1 — Major — the proposed HTTP 415 definition cannot enter the existing registry

**Location:** `artifacts/P06-design/examples.md:68`, also `:52`;
`research/P06-design.md:68`; owning item **P06.1**.

The example fixes `operations.unsupported_media_type` to status 415 and
`ErrorCategory::Unsupported`. The actual `CompiledProblemRegistry` checks the
status/category relation at `packages/core/src/Errors/Internal/CompiledProblemRegistry.php:69`
and permits `Unsupported` only for 422 (`:138`); 415 falls through to `Validation`
(`:141`). Adding the specified definition therefore fails registry construction,
before the required `InputEnvelopeTest` can exercise HTTP 415. Independently, the
reader example constructs `ApiError` without an explicit category and produces
`Validation`, conflicting with its proposed definition.

**Evidence:** `http-415-registry.php` loads the actual product classes and contributes
an isolated enum with the exact proposed code/definition. `http-415-registry.json`:
`ERROR-INVALID-DEFINITION operations.unsupported_media_type`, proposed definition
category `unsupported`, occurrence category `validation`.

**Failed acceptance:** P06.1 Validation lines 23 and 25 (HTTP 415 and green registry),
D90 §2. This is a new design/code incompatibility, not a claim that P06 is already implemented.

**Continuation:** P06.1 design must select one consistent category policy. Either use
the existing valid status/category pairing or explicitly include the necessary core
registry and occurrence changes, with checks for registry boot and an actual non-JSON request.

## A2 — Major — the configurable retry budget does not reach lost-lease recovery

**Location:** `research/P06-design.md:100`–`:104`, `phases/P06/P06.4.md:13`;
owning item **P06.4**.

The runbook moves the budget only into `WebhookRetryPolicy`, and Files omits
`SqlWebhookDeliveryStore`. That store has a second budget implementation in
`packages/webhooks/src/Internal/SqlWebhookDeliveryStore.php:247`–`:249`:
`PT72H` and `attempt >= 12`. It recovers expired sending leases before the next claim.
Following the prescribed change leaves those constants in force.

**Evidence:** `webhook-lease-budget.php` invokes the actual private `recoverExpired`
method using a mock connection/query builder; no database connection is opened.
`webhook-lease-budget.json` records both counterexamples:

- Configured `maxAttempts=3`, delivery attempt 3, age 120 seconds: recovery writes
  `retry/ack_unknown`, admitting a fourth attempt instead of `dead`.
- Configured horizon 3600 seconds, attempt 1, age 7200 seconds: recovery again writes
  `retry/ack_unknown`, admitting an attempt after the chosen horizon.

The configured values describe the accepted future options; the reproduced code is
the existing method that the item leaves untouched. No future implementation is claimed.

**Failed acceptance:** D90 §5 and P06.4's finite configurable delivery budget;
`WebhookRetryBudgetTest` must cover recovery after a worker dies, not only `decide()`.

**Continuation:** Include the durable store in P06.4 and make claim/recovery consume
the same budget policy, with lost-lease boundary scenarios for attempts and horizon.

## A3 — Major — the closed webhook failure taxonomy omits persisted lifecycle reasons

**Location:** `decisions/D90-p06-wire-contract.md:21`,
`artifacts/P06-design/examples.md:229`–`:248`, `research/P06-design.md:101`,
`phases/P06/P06.4.md:13` and `:17`; owning item **P06.4**.

The item requires only enum values in `last_error`, fixes the enum to 18 cases, and
changes the retry result's `error` to `WebhookFailureReason`. The omitted durable
store also writes `webhook_endpoint_changed` on a changed/disabled subscription
(`SqlWebhookDeliveryStore.php:153`, persisted at `:289`) and `ack_unknown` during
expired-lease recovery (`:249`, persisted at `:272`). Neither belongs to the proposed
enum. The recovery reproduction in A2 actually observes `ack_unknown`.

The same unaddressed boundary accepts the retry result and passes its `error` to
`finishAttempt(..., ?string $error, ...)` (`:215`, `:307`), so the prescribed enum return
needs an explicit conversion there. The store and `CurlWebhookTransport` also retain
reason literals caught by P06.4's own source scan despite being absent from P06.4 Files.
Finally, changing public `WebhookHttpResult::$errorCode` from string to enum is not
included in the exact approved public delta (`research/P06-design.md:123`).

**Evidence:** `webhook-reason-coverage.json` lists the exact persisted statements
and proves both values absent from the planned enum. Product source at the cited
locations establishes the result-to-persistence type boundary.

**Failed acceptance:** D90 §5; P06.4 Implementation Rules that every `last_error`
is a `WebhookFailureReason` value; Validation literal scan and enum-aware delivery tests.

**Continuation:** Complete P06.4's taxonomy/mapping for lifecycle refusals and unknown
acknowledgments; include all transport/store producers and consumers, persistence
conversion, and the required public delta. Preserve their existing fencing semantics.

## A4 — Major — the external profile advertises the internal schema after redaction

**Location:** `decisions/D90-p06-wire-contract.md:20`–`:21`,
`research/P06-design.md:91`–`:94`, `artifacts/P06-design/examples.md:214`–`:219`;
owning item **P06.3**.

Both encoders obtain `dataschema` from the same `MessageContract`, while
`encodeExternal` replaces `data` with arbitrary approved projector output. These
are already different shapes in the repository: `WebhookFanoutTest.php:214`–`:226`
creates `id` plus `secret`, and its `ExportProjector` at `:263`–`:276` exports only `id`.
A schema requiring both fields is valid for the internal message under the proposed
contract; the external body retains that URI while dropping a required field.

**Evidence:** `external-dataschema.php` uses the installed Opis validator on that
exact redaction shape and an explicit schema fixture: internal data validates,
external data does not (`external-dataschema.json`). This is a constructed accepted
input counterexample, not an assertion that an existing contract already has a schema URI.
The primary capture `artifacts/P06-design-rag/http-01-cloudevents-spec.md:173`–`:182`
defines `dataschema` as identifying the schema of `data`.

**Failed acceptance:** D11 and D90's CloudEvents external profile, including truthful
schema semantics after projection. The current worked example uses identical data on
both sides and misses the actual redaction case.

**Continuation:** P06.3 design must define the external schema separately or omit
the internal schema URI from the external profile; add a redacting projector example
whose emitted `data` validates against every emitted `dataschema`.

## A5 — Major — the mandatory review item has unresolvable Required Reads

**Location:** `phases/P06/P06.5.md:15`; owning item **P06.5 design**.

Required Read 2 contains a full path followed by the separately quoted shorthand
`P06.4.md`; it does not declare P06.2/P06.3. Read 5 is only `diff фазы по путям Files`
and has no source path. These are static failures, independent of the four legitimate
future completion artifacts. The installed Task parser resolves neither the shorthand
at plan/repository roots nor the source-free entry.

**Evidence supplied by the owning root and inspected here:**
`P06.5-read-declarations.json`, `P06.5-static-bundle-repro.json`; both entries produce
`unresolvable_entry`. Braced predecessor completion paths correctly expand and are
not findings. P06.1's valid dry bundle does not prove this future item's reads.

**Failed acceptance:** adopted plan-protocol v2 requires resolvable ordered Required
Reads before dispatch; D91/P06 Phase Contract makes the independent P06.5 verdict
mandatory for closure. Completing predecessors cannot resolve these two entries.

**Continuation:** Use full paths/brace expansion for all four specifications and a
declared predecessor-produced diff artifact, or leave the `git diff` instruction in
Code Guidance instead of declaring it as an input source.

## A6 — Major — public manifest changes omit their required package changelogs

**Location:** `phases/P06/P06.1.md:13`, `P06.2.md:13`, `P06.3.md:13`, `P06.4.md:13`;
owning items **P06.1–P06.4**.

Each item declares public API/manifest changes and an exact allowed Files set, but
none includes the corresponding package `CHANGELOG.md`. The adopted D64 Consequences
(`decisions/D64-p04-errors-and-golden-delta.md:83`) require changelogs for these
pre-1.0 deltas. `scripts/contract-guard.sh` enforces a matching changelog when
`packages/<name>/resources/contracts/php-api.json` changes; phase closure still
requires green hosted CI under D73/D91.

**Evidence supplied by the owning root and inspected here:**
`reproduce-contract-guard.py`, `contract-guard-repro.log`: an isolated committed Git
fixture with a tools API manifest change exits 1 without the tools changelog and
exits 0 when that same package changelog is present. Product was not changed.

**Failed acceptance:** mandatory contract discipline and final hosted CI for the
declared public deltas, even if an individual item's narrower local commands pass.

**Continuation:** Include and require the owning package changelogs in the respective
items: operations, tools, core/webhooks, and webhooks. Record each wire/API change.

## m1 — Minor — the proposed internal exception redeclares Exception::$code

**Location:** `research/P06-design.md:78` (and access through `$e->code` at `:26`);
owning item **P06.2**.

`ToolSchemaFailure extends RuntimeException` cannot declare
`public readonly ToolsErrorCode $code`: PHP's inherited property is non-readonly.
`tool-exception-code.php` reproduces PHP 8.4 exit 255 with
`Cannot redeclare non-readonly property Exception::$code as readonly ToolSchemaFailure::$code`.

Use a distinct property such as `errorCode` and update the catch sites. The exact
failure is real, but correction is an internal naming detail with no wire or public
contract choice; it need not independently block design acceptance once tracked.

## m2 — Minor — the source regex is a character filter, not URI-reference validation

**Location:** `research/P06-design.md:89`; owning item **P06.3**.

The exact regex accepts `%`, `%zz`, `1:foo`, and `http://[not-ip]/`, despite D90 §4
requiring an RFC 3986 URI-reference. It never validates percent triplets, the scheme
grammar, or bracketed host syntax. The two planned encode negatives only test spaces
and an unknown metadata key.

**Evidence supplied by the owning root and inspected here:**
`reproduce-source-check.php`, `source-check-repro.log`. The primary CloudEvents JSON
capture `http-02-cloudevents-json-format.md:21`–`:23` binds URI(-reference) strings to
RFC 3986. This is a bounded validation-example correction: retain the accepted URI
requirement and validate its syntax, with these negative cases, in P06.3.

## Coverage and limits

- Read AGENTS.md, the adopted review-mode reference and artifact-review skill;
  plan Context/Execution Rules/Routing, D10/D11/D12/D31/D90/D91, P06 phase and all
  five item contracts, dossier, examples, retrieval note and relevant primary captures.
- Whole-plan scope P00–P06 plus P10: reused terminal historical evidence for
  P00–P05/P10, their generated closure carriers, and the prior P05/P10 independent
  design reaudit. No unchanged completed phase was re-executed or reopened.
- Checked execution-sheet rows 21–24 against D91: B06-A, solo P06.3/P06.4 and
  independent P06.5 use the declared order/routes. No routing defect found.
- Examined actual HTTP dispatch/error registry/serializer, schema supervisor and worker,
  message types/registry/payload, webhook fanout/projector, URL policy/transport,
  retry/store/worker/replay, Options parser and composition code. Composer scripts
  and core testsuite mapping exist; no absent-suite finding is asserted.
- A2/A3 are separate obligations in the same omitted store: finite budget under
  worker loss versus closed reason/type semantics. They can share one owning repair.
- Egress-before-DNS plus the current replay-to-normal-attempt path is implementable;
  no independently demonstrated egress bypass is claimed. Existing endpoint revision
  fencing and signed stored-body bytes must be preserved as the design already requires.
- Strict decode/round-trip, payload types, metadata and trace rules were read against
  current MessageEnvelope and the captured CloudEvents profile. No claim of working
  future codec, transport, integration tests or final hosted CI is made.
- No fresh external research was needed for the reported decisions: the captured
  primary standard text already supplies the schema and URI requirements. Root-owned
  reproduction evidence is identified explicitly; the four independent PHP fixtures
  are reproducible through `reproduce-independent.py`.
- No repairs were made. The owning continuation is bounded correction of P06 design
  and its acceptance carriers, followed by the normal finish/gate handling. This
  verdict does not authorize implementation or a separate phase audit.
