# CoreX offline migration prototype

This standalone PHP 8.4 CLI exercises a sealed SQLite export of the 1.x
Webhooks subscription, delivery and attempt tables at
`1c11e0e35961409631cf68ebb28c7d2cd53ebb91`. The source SQL in
`tests/fixtures/source-v1-schema.sql` records the SQLite schema emitted by the
three Webhooks migrations under `packages/webhooks/database/migrations/tenant`,
including their indexes and triggers. The synthetic fixture rows come from the
1.x SQL subscription store, fan-out and delivery store. Laravel's SQLite
connection stores UTC timestamps without an offset, and the importer
normalizes them to UTC ISO 8601 strings in the target.

A single boxed account maps to an isolated target SQLite schema. The importer
preserves subscription IDs, external delivery IDs, payload bytes and terminal
attempt evidence. It accepts pre-send deliveries with zero attempts, successful
deliveries with a single confirmed 2xx receipt, and deliveries rejected before
send because their payload is too large. A missing receipt, unresolved attempt,
active lease, retry or unsupported state blocks the slice before copying.
The target fixture has no application or provider transport.

## Rehearse

Run from the checkout root with the PHP SQLite3 and sodium extensions. The
process interruption checks use POSIX signals. Supply an existing parent
directory and a fresh child path. The optional second argument binds the
fixture deployment to the candidate Git SHA.

```bash
php scripts/migration/corex-v2/tests/rehearse.php /path/to/new-run "$TARGET_SHA"
```

The harness generates ephemeral Ed25519 signing keys, runs the executable
against disposable databases, and retains the public trust file, plans, signed
approvals, command exit codes, verification results and target files.
`summary.json` aggregates the CLI results. The uninterrupted and resumed
runs use the same source export and compare semantic digests after separate
verification. `resumed/target.sqlite` is available for independent SQL reads.

The frozen exporter fixture loads the audited 1.x checkout and its locked Composer
dependencies. The same rehearsal accepts
the generated SQLite file as its positive source, preserving the owner-produced
database bytes.

```bash
php scripts/migration/corex-v2/tests/export-source-v1.php.fixture "$BASELINE" "$EXPORT"
diff -u scripts/migration/corex-v2/tests/fixtures/source-v1-schema.sql "$EXPORT/source-v1-schema.sql"
diff -u scripts/migration/corex-v2/tests/fixtures/source-v1-data.sql "$EXPORT/source-v1-data.sql"
php scripts/migration/corex-v2/tests/rehearse.php "$DIRECT_RUN" "$TARGET_SHA" "$EXPORT/source-v1.sqlite"
```

## CLI

`COREX_MIGRATION_TRUST_STORE` points to a separately supplied canonical JSON
map of key IDs to `principal`, base64 Ed25519 `public_key` and allowed
`purposes` (`snapshot` or `approval`). Each signed input has a `body` and a
base64 detached `signature` over the UTF-8 bytes of
`corex-migrate/stage0/<purpose>`, a newline, and the canonical JSON body.
The signed format uses fixed ASCII object keys, safe integers and no floats.
The generated descriptors in the rehearsal give examples for all inputs.

```bash
MIG=scripts/migration/corex-v2/bin/corex-migrate
"$MIG" plan --source-snapshot "$RUN/source.json" --target "$RUN/target.json" \
  --profile "$RUN/profile.json" --output "$RUN/plan.json"
"$MIG" copy --plan "$RUN/plan.json" --approval "$RUN/approval.json" \
  --approve-digest "$APPROVED_DIGEST"
"$MIG" verify --plan "$RUN/plan.json"
```

After inspecting the plan, a trusted reviewer binds the approval to its
digest, snapshot digest, target deployment and profile. Retrying copy uses
the same plan and approval. `--segment subscriptions` and
`--segment deliveries` restrict one invocation. Deliveries require the
completed subscription segment. Each SQLite transaction commits a target row,
its source mapping and the checkpoint together. A reopened read-only verifier
compares actual owner rows with independently read source rows and checks the
checkpoint and mapping separately.

Exit status 2 means an invalid input, identity or execution error. Exit status
3 means a data blocker or a verification mismatch. A blocked dry run publishes
a diagnostic at `<output>.blocked.json`. The target application schema,
production database and authority adapters, other data families, and
reconciliation and cutover belong to the release rehearsal.
