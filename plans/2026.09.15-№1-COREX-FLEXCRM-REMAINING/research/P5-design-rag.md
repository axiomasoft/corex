# P5 design RAG — verified after owner-resumed Perplexity

**Perplexity:** successful
**Search artifact:** `artifacts/P5-design/perplexity-response.json`
**SHA256:** `eb455634d98514641f998ecc219c00086956753f7e60d18d29a93b736cd05bfc`

Successful general response covers optional binding/immutable qualification, durable same-transaction
intent and SQL claim/fencing. It is incomplete after cancellation, and is NOT used to prove missing
root/digest sections. Separate successful source-selected web queries close these two gaps:

| Topic | Successful artifact | SHA256 |
|:--|:--|:--|
| Root routing/reconciliation | artifacts/P5-design/perplexity-response.resumed-routing-web.json | 9cb0067a1e72cc13b8855b8279461879a34587d6d956b55f08a87e2ae5840f07 |
| Digest UTC/windows/key/catch-up | artifacts/P5-design/perplexity-response.resumed-digest-web.json | 03bb2e2bbaad48983855279aba2d9d946eb82bdcbf899637524dcb3e2270eacb |

Canonical general artifact is byte-identical to preserved retry-owner response. Real retrieval,
not a fabricated/fallback answer. All queries neutral, no internal project/requirement/API/paths sent.
Direct primary verification (original plus resumed official pages) supports only named generic
primitives; local placement/signatures/SQL/fencing/window/key/closure choices remain RAG:— inference.

Initial three timeout results, later login-required, structure/no-answer response and deliberately
interrupted query remain preserved. Every actual failure/incomplete answer was owner-notified.
Interrupted focused query produced no retained response: not counted as success. Owner resumed,
separate routing and digest web searches succeeded. Failed/incomplete artifacts never substituted
for missing coverage. D4/D5 phase mandatory RAG is now satisfied, not whole-plan design-finish.

## Primary sources

Directly opened 2026-09-15; these sources establish named generic mechanisms, not correctness,
availability or acceptance of internal source, engine or resulting application. RAG:✅ only those
bounded general facts. RAG:— local application/selection in research/P5-wakeup-design.md.

| Origin | URL | Supported claim |
|:--|:--|:--|
| Laravel | https://laravel.com/docs/12.x/queues#jobs-and-database-transactions | Dispatch after outer commit, rollback discards deferred jobs; this is not a persisted outbox |
| AWS | https://docs.aws.amazon.com/prescriptive-guidance/latest/cloud-design-patterns/transactional-outbox.html | Dual-write failure/rollback concerns; persist outbox with domain update, downstream duplicate handling required |
| PostgreSQL | https://www.postgresql.org/docs/16/sql-select.html | SKIP LOCKED skips contended rows, queue-like multi-consumer use; inconsistent snapshot not general query correctness |
| AWS | https://docs.aws.amazon.com/prescriptive-guidance/latest/cloud-design-patterns/retry-backoff.html | Retry with backoff needs idempotent operations; bounded retry/cap and failure handling |
| Composer | https://getcomposer.org/doc/01-basic-usage.md | Application lock selects exact resolved versions at install; consumer lock/runtime/dist bytes qualified separately |
| PostgreSQL | https://www.postgresql.org/docs/16/sql-insert.html | Atomic ON CONFLICT insert/update and conditional predicates; application still owns hint revision/epoch protocol |
| AWS | https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/DynamoDBMapper.OptimisticLocking.html | Version-conditional update/delete optimistic concurrency example; DynamoDB not adopted as scheduler dependency |
| SemVer | https://semver.org/spec/v2.0.0.html | Declare public API and immutable released version; local major policy remains stricter |
| PostgreSQL | https://www.postgresql.org/docs/16/functions-conditional.html | LEAST gives minimum nonnull expression; it does not implement a distributed scheduler |
| AWS | https://aws.amazon.com/builders-library/caching-challenges-and-strategies/ | Cache can become harmful dependency; availability/failure/recovery needs explicit qualification |
| Laravel | https://laravel.com/docs/12.x/scheduling | Timezone/DST schedules may double or omit occurrence; scheduler invocation does not certify timer durability |
| PostgreSQL | https://www.postgresql.org/docs/16/rangetypes.html | Inclusive/exclusive timestamp range bounds explicit; canonical half-open choice local |
| Temporal | https://docs.temporal.io/develop/php/workflows/message-passing | Engine signal handling/history/signal-with-start API as alternative; not evidence of selected checkpoint engine behavior |

## Choice / independent origin mapping

| Choice | Independent origins | What remains local inference |
|:--|:--|:--|
| Independent engine/FSM DI binding and immutable qualification | Composer + SemVer | Exact local ports/placement and real upstream ABI/source availability; no web proof of internal library |
| Atomic durable producer intent and local handler completion | Laravel + AWS | Same-account SQL boundary, generation/commit/unknown semantics need actual local fault proof |
| Concurrent claim/retry/cancel/fencing | PostgreSQL + AWS | Row lock/generation checks and cancellation linearization are designed application behavior, not SKIP LOCKED automatic guarantee |
| Root routing hint/reconciliation | PostgreSQL + AWS | LEAST+revision/epoch CAS and all-account sweep are local proposed design, no distributed guarantee from primitive/cache article |
| Digest UTC/window/key/late discovery seam | Laravel + PostgreSQL | Explicit UTC half-open bounds address DST hazards; key construction/catch-up/lock order and exact bridge need local conformance |

## Alternatives

| Choice | Alternatives | Selection / failure modes |
|:--|:--|:--|
| Engine reuse | Copy engine; replace checkpoint engine with replay engine; typed source-qualified binding | Thin qualified binding; clone violates ownership, replay engine changes accepted execution model; unsupported actual ABI/source remains dependency gate |
| Timer placement | Per-feature scheduler; engine-only timers; independent utility/shared sources | Core-only wakeup plus engine/FSM binding optional; digest/reminder no engine dependency, registered due sources use one runner |
| Durable delivery | afterCommit only; volatile delayed queue; same-transaction SQL intent plus polling | Persist-first; commit→dispatch crash recoverable, external effect unknown requires upstream lookup/dedup, no exactly-once |
| Root optimization | Authoritative root schedule; no cache; reconstructible hints plus reconciliation | Tenant truth/local mode always; root outage/spurious hints harmless, racing update protected by per-upsert revision/epoch CAS |
| Digest scheduling | Local cron at DST; completed-key reinsertion; explicit UTC windows plus durable due source | Explicit windows/stable key, late items discovered from persistent consumer rows; reverse lock order prohibited |

## Cross-phase impact

P2.3 consumes stable candidate signatures/SQL/failure and actual P2 approvals precede runtime.
P5.1 source qualification→P5.4 only; P5.2 doesn't wait source. P5.3 approved P3.1 physical context only.
P5.2+P4.4/5→P5.5 glue/tick DTO→P4.6 actual handler/provider/DueSource→P4.8 full DIG installed proof.
No cycle via P4.6 or phase seals; neutral glue fixture cannot certify actual full digest. P5.6 installed
upstream+reminder/shared runner seal→P9 declared matrix/source/release→P10 effective fleet AC13
p95≤30s at10%-due/tick/reconciliation conditions→P11 immutable consumer pins. P7 pure recurrence/
utilities proceed independently; task workflow integration requires durable capture and actual engine.
Finish hash set binds stable P5 candidate D10; original 30 B12/365 aggregate parents retained.

## Unresolved premises

Mandatory Perplexity retrieval and per-choice primary verification complete from three successful
responses plus independent official sources; external primitives are not application correctness proof. Real agnostic independent engine/FSM immutable source/ABI/suites,
P1 equivalents/P2 adoption/major/schema/source approvals and P4/P3 actual producer outputs are future
execution prerequisites. Generic sources do not prove present internal behavior, installed support,
released/cohort/hosted CI/live clock/fleet timing, provider dedup horizon or product/consumer acceptance.

## Local application / rejected illustrative shortcuts

Framework-free core suggested externally is not a local requirement: CoreX intentionally targets
Laravel. Generic row-level tenant_id example does not change physical DB-per-account; external
FSM direct apply/status snippets do not authorize bypassing StateMachine::fire/Guard/Policy/Action.
Root minimum-only example without revision is insufficient: local protocol increments revision EVERY
hint publication, and CAS compares BOTH revision and claim epoch. AWS version checks corroborate
conditional concurrency primitive, not adoption of DynamoDB/global-table guarantees.
Digest example seven-day look-back is not adopted as a silent retention/drop horizon; local DueSource
discovers all required persisted pending/late items under bounded pages. Window membership uses
explicit trusted UTC bounds; logical occurrence key is wakeup dedup, actual external delivery key
includes admitted run revision/render digest/content to prevent different payload reuse. Unknown
provider outcome requires its real dedup/lookup capability/horizon; no blind new-key retry or
exactly-once effect promise. SemVer external guidance does not weaken local owner/major policy.
