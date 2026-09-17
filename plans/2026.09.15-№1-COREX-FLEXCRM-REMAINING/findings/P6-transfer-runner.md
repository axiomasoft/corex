# P6 transfer runner receipt

**Schema:** transfer/1; p6-data-receipts/1
**Item:** P6.4

The supported local profile is PostgreSQL 16 and a consumer-provided, transaction-safe `ImportTarget`. External targets are not auto-enabled: an `unknown` acknowledgement is retained as a quarantine state and does not advance the contiguous cursor.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| TRN-01 | R12 | PG16 local target | two jobs, same external identity/revision | one target apply and one ref | `a183f74bb0438626c57f026df0522d3bb0d219004d7e64e387fe26c4cb22f0f6` | PASS | consumer owns target semantics |
| TRN-02 | R12 | PG16 local target | result/ref/row/checkpoint transaction | result and CAS checkpoint commit together | `b352c6d3b72984d2bb23c05f858b29901575f2caac295b8445bf04604b5a1a9f` | PASS | external sender is not invoked in this profile |
| TRN-03 | R12 | PG16 savepoint | first target row throws, second applies | failed row rolls back and batch continues | `a183f74bb0438626c57f026df0522d3bb0d219004d7e64e387fe26c4cb22f0f6` | PASS | error is bounded `rowFailed` |
| TRN-04 | R12 | dryRun | mapped row under a fixed snapshot | no row/ref/checkpoint write | `a183f74bb0438626c57f026df0522d3bb0d219004d7e64e387fe26c4cb22f0f6` | PASS | target preview remains consumer DI |
| TRN-05 | R12 | resume fence | changed source hash or current policy denial | reject before target/ref mutation | `a183f74bb0438626c57f026df0522d3bb0d219004d7e64e387fe26c4cb22f0f6` | PASS | mapping/schema/restore also fence token |
| TRN-06 | R12 | quarantine | target returns unknown acknowledgement | persisted unknown; no blind retry/cursor advance | `a183f74bb0438626c57f026df0522d3bb0d219004d7e64e387fe26c4cb22f0f6` | PASS | qualified external lookup remains consumer-owned |
| TRN-07 | R12 | ImportTarget port | consumer conflict rule and target result | no direct domain-table write or type fallback | `b352c6d3b72984d2bb23c05f858b29901575f2caac295b8445bf04604b5a1a9f` | PASS | consumer owns conflict policy |
| TRN-08 | R12 | CSV/NDJSON bounded codecs | multiline, escaped quote, object records, oversize bytes | logical boundaries and pre-allocation limit reject | `03e0b739ba170ef2e6e13cad1d8b88e3434259556094ef5008cab8cfedaa89cb` | PASS | CSV and NDJSON only |
| TRN-09 | R12 | DataScope | forged/currently denied authorization | deny before writes and no credential payload | `a183f74bb0438626c57f026df0522d3bb0d219004d7e64e387fe26c4cb22f0f6` | PASS | policy closure is trusted ephemeral ingress |
| TRN-10 | R12 | PG16 checkpoint | unknown gap or stale sequence/epoch | cursor remains contiguous and CAS fences stale worker | `b352c6d3b72984d2bb23c05f858b29901575f2caac295b8445bf04604b5a1a9f` | PASS | cancellation/event trigger remains consumer-owned |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `packages/import-export/src/Internal/Transfer/DatabaseTransferRunner.php` | `b352c6d3b72984d2bb23c05f858b29901575f2caac295b8445bf04604b5a1a9f` | fenced runner and savepoint |
| `packages/import-export/database/migrations/2026_09_15_000001_create_corex_transfer_tables.php` | `ff30d7090783057f411d0e969004bc6b3d64d1c3401ebf09e96b93cd4bc1d63a` | neutral local schema |
| `packages/import-export/tests/Pg/DatabaseTransferRunnerTest.php` | `a183f74bb0438626c57f026df0522d3bb0d219004d7e64e387fe26c4cb22f0f6` | PostgreSQL conformance |
| `packages/import-export/tests/Unit/CodecTest.php` | `f8a5f6857fffb3565f0051c7e9ea3fe51f7f55a47ebc732e5ef18ad93552b474` | bounded codec conformance |

## Adoption

| Surface | Proposal hash | Protected receipt | Supported local adapter | Installed contract | External owner/resume |
|---|---|---|---|---|---|
| transfer/1 | `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` | Q-P2-028, Q-P2-029, Q-P2-030 | PostgreSQL local target only | TransferSource, RecordMapper, ImportTarget, TransferRunner | consumer qualifies external sender and lookup |
