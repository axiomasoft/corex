# P09.8 independent retained-code review

review:codex/gpt-6-astra GREEN

No actionable defect was established in the retained P09 code delta. This is the independent code-review verdict, not P09.8 terminal acceptance. Exact-final-HEAD ordinary hosted CI remains a separate required gate owned by the integrator; it was pending on axiomik/corex-mirror when this report was issued.

## Identity and source

Actual native reviewer: codex/gpt-6-astra/high, session 01a1175f-c10c-78c1-baff-26e5fb2fa45f, independent of executor codex/gpt-6.1-sol/high. Runtime session_meta and turn_context observations are retained in reviewer-identity.json; no alternate route or second reviewer was represented as executed.

Reviewed base f0faead86201935c71cd0c2d8731e2e41bcc26e5 to HEAD 1bfcb0324197c9e3ae8a050dea2b8f1d84b7f6dc; tree ee90cd27aea65c76a2233bbde4b472a1e8d10454. The non-plan binary diff covers 1,131 changed paths and has SHA256 25eead518d70bc5afdb9e1de625f2ee2bd79a5bcc34a0f1f8dbdc234b313f970. Per-path current hashes, deleted paths and exact Git boundaries are in source-fingerprints.json.

## Normative scope and method

Applied installed Task 0.28.3 plan-run and reviewer-selection, plus quality:code-review 0.4.2. Read P09.8 and its selected required source contexts, docs/dev-doctrine.md, and D125–D130/D132/D133. Product review covered retained runtime/types, development tooling, source tests and quality-evidence integrity. Finite API/Golden allowances were evaluated against D126–D130, and the PHP 8.4–8.5/Laravel 13 matrix against D132.

Static comparison prioritized changed executable behavior: deserialization and SQL primitive boundaries; JSON object versus named-protocol maps; tenant/global-scope preservation through Eloquent/query-builder calls; callback/listener resolution; lock, retry, cursor and stale-worker fences; strict rejection paths; approved internal symbol removal; Composer/Rector/PHPStan/architecture/audit/unused-public tooling; and the actual behavior asserted by Transfer, Search and lifecycle tests. Documentation/import/typing/Rector bulk changes were grouped for navigation, with authoritative source and Git diff used to assess suspicious executable changes. This is a human-style static review and evidence inspection, not a proof that every possible runtime input is correct.

No product edit, product repair, test-suite execution, mutation/Infection/probe/self-test/replay execution, Composer solve, release/candidate/SBOM/DoD27 execution or publishing occurred in this review. Historical evidence was read and hashed only. Three intentionally disabled mutation CI jobs remain the D133 policy, and no mutation score qualification is claimed.

## Reviewed behavior and contracts

- Core compiled-registry deserialization retains general JSON payload semantics and validates declared shape boundaries; serialization of feature flags remains explicit. Lifecycle listener restoration, filesystem dispatch, module dependency diagnostics and outbox fences were inspected for behavior changes beyond typing.
- Access scope predicates return the original Eloquent builder after underlying query changes; tenancy and CRM locks likewise preserve the outer query/scopes. D127 covers the principal password return and database/cache input boundaries. Named-column SQL expressions quote identifiers through the active grammar.
- Operations canonical JSON still admits generic numeric JSON keys; named consumer maps have their own boundary checks, consistent with D128. Wakeup scheduling already bounded named top-level payload keys before the new map helper, so numeric-key rejection there was not treated as a newly introduced valid-input regression.
- Durable Search lane revision/generation fences, Transfer resume tokens and persisted cursor behavior, and webhook/media/notification database codecs were inspected. New integer codecs admit database integers and canonical native-range integer strings and fail closed on malformed values.
- D129's native Laravel Rector import is hash-bound, namespaced and retains upstream provenance; D130's finite readonly/constants/metadata changes and unused Internal removals were assessed against the approved surface carrier rather than presumed public compatibility from a green snapshot alone.
- Quality tooling retains strict/static-analysis settings, source roots, zero suppression ceilings and bounded entrypoint/evidence ledgers. Source audit validates retained command/output hashes and source/lock inventory; evidence verification does not equate an unexecuted command with success.
- Transfer source tests exercise actual persisted resume with a recreated executor, stale-source/token refusal and exact sink writes. Search tests assert actual PostgreSQL result behavior and Meilisearch asynchronous task completion, documents and index swaps. Lifecycle tests use actual Laravel queue/Octane worker lifecycle with fixture transport, tenant A→B isolation, failure cleanup and restart; they do not merely assert a fabricated receipt.

## Consumed evidence and independent checks

receipt-integrity.json records 69 structured declared-check log hashes plus 28 summary-log hashes, 23 current P09.13/P09.14/P09.15 input bindings, and nine raw JUnit documents. All inspected hashes match and every inspected JUnit reports zero failures, errors and skipped tests:

- Transfer idempotency: 1 case / 2 assertions; partial failure: 1 / 4; persisted resume: 1 / 35.
- Meilisearch: 3 / 18; PostgreSQL Search: 4 / 13.
- Core lifecycle: 11 / 49; PostgreSQL tenant queue: 3 / 21.
- Real Octane worker: PHP 8.4 and 8.5, each 4 cases / 109 assertions.

octane-integrity.json independently checks 514 retained bindings: 353 current source hashes, 146 installed Octane source hashes, one consumer lock hash, and 14 raw log/JUnit/runtime/trace hashes. No mismatch. Octane receipt SHA256: 6795e6e3c2593d0ac707a8eac0e8dac1a2ff2b7f154604e51ecff156fb26c3a5. These are consumed completed executions, not fresh test runs by the reviewer. Historical quality receipts are identified as historical; matching a log hash alone does not claim every historical command was rerun on final HEAD.

## Worktree, limitations and handoff

All 3,119 tracked non-plan files in the reviewer's initial broad manifest are byte-identical after review. Both product-before.json and product-after.json SHA256: ee40bdeca142536a2e497e2a33f84ec7f4632b340a59c2df3599dfdafb5bfde0. Manifest excludes plans/, .ai/, .agents/, AGENTS.md and CLAUDE.md; it includes the product roots and additional tracked repository material. scripts/.plan-close-deferred is absent. No product changes are present; preexisting dirty plan views and the owner's P09.9 stack-proposal work are preserved.

A genuine Task context-pack issue was recorded after admission: legacy context 3,385,749 bytes against a 262,144-byte budget, including 2,790,013 unbound bytes. Required source documents were selected directly; context-budget.json and the difficulty event retain this workflow issue. It is not a product-code finding.

The prior exact-HEAD ax1oma and academici CI attempts failed before any executable job began because of account billing/spending limits. They are not passing CI evidence and not product-test failures. The integrator owns the newly authorized axiomik mirror run 37652373568 and final hosted receipts under hosted/. This review does not finish, commit or authorize terminal closure of P09.8; final ordinary hosted CI acceptance must be evaluated independently.
