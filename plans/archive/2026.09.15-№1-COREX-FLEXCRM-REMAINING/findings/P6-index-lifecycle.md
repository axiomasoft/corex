# P6 index lifecycle receipt

**Schema:** index-lifecycle/1; p6-data-receipts/1
**Item:** P6.7

The local lifecycle keeps a committed dirty receipt in the current tenant connection. A claim is fenced by generation, restore epoch and monotonically increasing claim epoch; an unknown provider task or swap quarantines the lane. The optional Meilisearch adapter returns only task receipts. It neither treats a 202 response as completion nor performs a blind second (inverse) swap.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| IDX-01 | R32 | PostgreSQL 16 disposable `_test` | enclosing source transaction rolls back; later durable sweep claims committed row | rollback creates no dirty receipt; missed kick remains sweepable | `ab5cd7839ea13346ddd8323101bd3b17fab06a129021a69b0e87b2c8d1531765` | PASS | consumer must write dirty receipt in its own source transaction |
| IDX-02 | R32 | PostgreSQL 16 durable lane | duplicate, stale tombstone and later recreate revisions | only highest monotonic revision is emitted; older state cannot resurrect | `ab5cd7839ea13346ddd8323101bd3b17fab06a129021a69b0e87b2c8d1531765` | PASS | document payload/current policy remain consumer-owned |
| IDX-03 | R32 | PostgreSQL 16 durable lane | live claim reaches unknown task outcome | lane is quarantined and no later claim takes over | `ab5cd7839ea13346ddd8323101bd3b17fab06a129021a69b0e87b2c8d1531765` | PASS | explicit task reconciliation is required before any recovery |
| IDX-04 | R32 | PostgreSQL 16 durable lane | claimed batch, nonterminal/unknown provider result | no next claim until terminal; unknown is never indexed | `49e5ea5aeb10fbd8fc77a9c2cc05c5fa43ef3e13a74bc0bf3abffe63a55474fb` | PASS | provider status polling is owned by dispatcher/consumer |
| IDX-05 | R32 | PostgreSQL 16 rebuild | new generation, fence watermark and drain check | rebuild cannot swap while an uncompleted durable delta exists | `61f88b1ce0e1aa61f89618d9abaf9dc9ff729206c3fa39429285cebdc7b06abf` | PASS | snapshot capture/catch-up document construction stays consumer-owned |
| IDX-06 | R32 | D40 loopback Meilisearch v1.53.2 | queued document tasks then async target-generation swap | SDK returns receipts; swap waits on actual task before lifecycle can settle | `914156244199805bab82f2926edd7d7b4f9f08bf765c24e8717067559bf4a0fe` | PASS | disposable loopback container only; no production timing claim |
| IDX-07 | R32 | PostgreSQL 16 rebuild | swap receipt is lost/unknown | rebuild/lane become unknown/quarantined; second swap is rejected | `ab5cd7839ea13346ddd8323101bd3b17fab06a129021a69b0e87b2c8d1531765` | PASS | unresolved mapping must use authoritative PG fallback |
| IDX-08 | R32 | PostgreSQL 16 durable lane | old generation or restore epoch attempts a claim | old worker receives no claim and cannot target restored generation | `ab5cd7839ea13346ddd8323101bd3b17fab06a129021a69b0e87b2c8d1531765` | PASS | restore reindex/current policy remains consumer-owned |
| IDX-09 | R32 | tenant-local lifecycle SQL | generation rebuild requested against same generation; tombstone retained | new generation required; no tombstone reclamation path exists | `61f88b1ce0e1aa61f89618d9abaf9dc9ff729206c3fa39429285cebdc7b06abf` | PASS | retention may change only with proven worker/task horizon |
| IDX-10 | R32 | actual SDK/service plus PG16 | missing provider is not selected; local adapter and SQL suites run independently | explicit package boundary, actual service task receipt and PG proof; no live p99 claim | `cc8629bb23eb26b162059c476f62af090db2332b9cc0f0a42b508253b02f9842` | PASS | deployment/provider qualification and fleet load remain external gates |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `decisions/D40-p6-search-adoption.md` | `f73f2eb5168f6c6292326c2997c68a719de2d845f6a250bcbd993c5d04af98aa` | scoped provider, lifecycle and local-image adoption |
| `artifacts/B-P6-SEARCH/owner-adoption-receipts.json` | `b534adf9bfd4d98920dab8425adc37b815524ef2b847e930898905ef86e4fc50` | owner receipt for Q-P2-034–039 |
| `packages/search/database/migrations/2026_09_15_000001_create_corex_search_lifecycle_tables.php` | `205a7f0252a9af75407747eb4cd950ce2090a70dba0cc17d26dab3b8d807f5ce` | package-owned neutral lifecycle tables |
| `packages/search/src/Internal/Index/DurableIndexLane.php` | `49e5ea5aeb10fbd8fc77a9c2cc05c5fa43ef3e13a74bc0bf3abffe63a55474fb` | fenced dirty/claim/task state machine |
| `packages/search/src/Internal/Index/DurableIndexRebuild.php` | `61f88b1ce0e1aa61f89618d9abaf9dc9ff729206c3fa39429285cebdc7b06abf` | fence/drain/swap reconciliation state machine |
| `packages/search/tests/Pg/IndexLifecyclePgTest.php` | `ab5cd7839ea13346ddd8323101bd3b17fab06a129021a69b0e87b2c8d1531765` | executed PG16 rollback, stale, quarantine and swap tests |
| `packages/search-meilisearch/src/Internal/MeilisearchIndexAdapter.php` | `cc8629bb23eb26b162059c476f62af090db2332b9cc0f0a42b508253b02f9842` | D40-pinned SDK task adapter |
| `packages/search-meilisearch/tests/Feature/IndexLifecycleTest.php` | `914156244199805bab82f2926edd7d7b4f9f08bf765c24e8717067559bf4a0fe` | executed loopback SDK submission and swap receipt tests |

## Adoption

| Surface | Proposal hash | Protected receipt | Supported local adapter | Installed contract | External owner/resume |
|---|---|---|---|---|---|
| index-lifecycle/1 | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` | D40; Q-P2-034–039 | tenant-local PostgreSQL durable lane and D40-pinned Meilisearch task adapter | IndexDriver, IndexBatch, IndexSubmission, IndexOutcome and IndexSwap | consumer owns source transaction, dispatcher, policy, snapshot construction and production provider qualification |
