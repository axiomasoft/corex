# P6 export receipt

**Schema:** transfer/1; p6-data-receipts/1
**Item:** P6.5

`export/1` captures only a `coherent` manifest in a PostgreSQL repeatable-read
transaction. Canonical NDJSON parts are written under their content hash, read back and verified
before the manifest is sealed. A pre-existing unsealed manifest is marked `aborted` and must be
replaced by a new capture attempt. Emission reads only a sealed immutable part and checks the
current policy and restore epoch both before and after the storage read.

Raw NDJSON remains the machine round-trip representation. The separately labelled
`displaySafe/spreadsheet-formula-prefix/1` CSV projection prefixes formula-leading cells and is
not represented as a reversible or universal spreadsheet-safety format.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| EXP-01 | R12 | raw NDJSON + CSV sidecar | typed null/string payload and formula-like ID | raw canonical fields round-trip; display profile remains separate | `f8a5f6857fffb3565f0051c7e9ea3fe51f7f55a47ebc732e5ef18ad93552b474` | PASS | machine/raw and display are distinct |
| EXP-02 | R12 | displaySafe/spreadsheet-formula-prefix/1 | `=SUM(A1:A2)` cell | formula-leading content is prefixed and profile-labelled | `f8a5f6857fffb3565f0051c7e9ea3fe51f7f55a47ebc732e5ef18ad93552b474` | PASS | no universal spreadsheet guarantee |
| EXP-03 | R12 | PostgreSQL 16 | source page during manifest capture | source page observes repeatable-read; sealed manifest owns immutable parts | `195eb46d4cfe656a92e98a9316d955ae4e15b491594b904dc73a094ad12a6553` | PASS | source roster remains consumer-owned |
| EXP-04 | R12 | PostgreSQL 16 | pre-existing `capturing` manifest | abort rather than resume a mutable snapshot | `195eb46d4cfe656a92e98a9316d955ae4e15b491594b904dc73a094ad12a6553` | PASS | caller must allocate a new attempt |
| EXP-05 | R12 | supported local TenantStorage disk | immutable write/re-read acknowledgement | hash and byte length verify before SQL part checkpoint | `3c2c3d75b6d44c4fdd39459005cd7dbfffba790e25cd7040bff5868822661fbb` | PASS | no append replay or object rename assumption |
| EXP-06 | R12 | current DataScope | policy revoke or restore-epoch mismatch | deny output before returning immutable bytes | `195eb46d4cfe656a92e98a9316d955ae4e15b491594b904dc73a094ad12a6553` | PASS | download/access ownership remains consumer-owned |
| EXP-07 | R12 | TransferLimits | qualified batch/file bounds | oversized output is refused before storage persistence | `edcc3226eb6ec5c2f561acab44edca0c36510a4c02422ecef87f271d0203a4b4` | PASS | `maxFileBytes`, `batchRows`, runtime profile |
| EXP-08 | R12 | local Laravel filesystem disk + PostgreSQL 16 | boxed-style supported disk, no provider credentials | sealed part is readable and hash verified | `195eb46d4cfe656a92e98a9316d955ae4e15b491594b904dc73a094ad12a6553` | PASS | native object provider remains P10-owned |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `packages/import-export/src/Contracts/ExportSource.php` | `8d2a13b53076008beff47f2edf354ab6fbfb765eb9bcfcbfa140b170c586973a` | consumer export page port |
| `packages/import-export/src/Support/Export/ExportManifest.php` | `85f91043f168241a21d20c7fc90a89b18d784a8444ea5361088bfc03adb6a3a8` | sealed-manifest DTO |
| `packages/import-export/src/Internal/Export/DatabaseExportRunner.php` | `3c2c3d75b6d44c4fdd39459005cd7dbfffba790e25cd7040bff5868822661fbb` | repeatable-read capture and verified IO |
| `packages/import-export/src/Internal/Export/CanonicalExportCodec.php` | `edcc3226eb6ec5c2f561acab44edca0c36510a4c02422ecef87f271d0203a4b4` | raw/display codec split |
| `packages/import-export/database/migrations/2026_09_15_000002_create_corex_export_manifest_tables.php` | `c4c7733dfc88c19d9d9e242635d3f59d0a9a986f21747f9686487ec734d5024d` | neutral manifest and part tables |
| `packages/import-export/tests/Pg/ExportPgTest.php` | `195eb46d4cfe656a92e98a9316d955ae4e15b491594b904dc73a094ad12a6553` | PostgreSQL and supported-disk conformance |
| `packages/import-export/tests/Unit/CodecTest.php` | `f8a5f6857fffb3565f0051c7e9ea3fe51f7f55a47ebc732e5ef18ad93552b474` | raw/display codec boundary |

## Adoption

| Surface | Proposal hash | Protected receipt | Supported local adapter | Installed contract | External owner/resume |
|---|---|---|---|---|---|
| export/1 | `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` | Q-P2-031, Q-P2-032, Q-P2-033 | PostgreSQL 16 + local TenantStorage disk | ExportSource | object provider and consumer download remain separately qualified |
