# P6 engine qualification

**Schema:** engine-qualification/1; p6-data-receipts/1
**Item:** P6.1
**Owner amendment:** D39 authorizes the neutral CoreX-owned engine; an unavailable third-party package is not treated as a prerequisite.

`corex/attributes` is PHP 8.3 and Laravel 12/13 infrastructure. It owns only additive `sys_attribute_*` storage, has no upward product dependency, consumes existing entity/module registries, and keeps implementations internal behind four public ports.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| ENG6-01 | R11 | owned package | clean package manifest | neutral PHP 8.3 package | `692efe7c6b1fc82938f792083a18ce2a32077ae65062e54b819282658115d264` | PASS | package publish remains release-owned |
| ENG6-02 | R11 | Laravel 12/13 | package static analysis | supported profile analyses | `ed54a73d9f551fda3682eb4dbb281eb7401085832aa1b3f5f8acd8865d9d88bb` | PASS | consumer matrix remains CI-owned |
| ENG6-03 | R12 | owned ABI | contracts plus Internal implementations | no public engine internals | `ed54a73d9f551fda3682eb4dbb281eb7401085832aa1b3f5f8acd8865d9d88bb` | PASS | major contract evolution is owner-governed |
| ENG6-04 | R11 | PostgreSQL 16 | isolated `_test` runtime suite | JSONB, EAV and schema operations execute | `003019ddad4ea19c5c3c9c2224be7eea024b200315614f482982d06e9b57556e` | PASS | production qualification is separate |
| ENG6-05 | R11 | owned capabilities | storage and DDL source inspection | JSONB, EAV, async bridge present | `1b898d79b6e4c0081a0679249af725299478aae7816378ecee06a10de9034b7d` | PASS | queue dispatch remains application-owned |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `decisions/D39-p6-owned-attributes-engine.md` | `6d34ed63e7e0e0737bcfa4c1bc31275600ad90d8d8c0b4c6390e6e49f1318ede` | owner-approved source condition |
| `packages/attributes/composer.json` | `692efe7c6b1fc82938f792083a18ce2a32077ae65062e54b819282658115d264` | supported package profile |
| `packages/attributes/src/AttributesServiceProvider.php` | `ed54a73d9f551fda3682eb4dbb281eb7401085832aa1b3f5f8acd8865d9d88bb` | public/internal bindings |
| `packages/attributes/tests/Pg/QueuedSchemaOperationsTest.php` | `003019ddad4ea19c5c3c9c2224be7eea024b200315614f482982d06e9b57556e` | PG16 execution proof |

## Adoption

| Surface | Proposal hash | Protected receipt | Supported local adapter | Installed contract | External owner/resume |
|---|---|---|---|---|---|
| attributes engine | `2a2b3a02ab94da1a29a34e2b304c2190e761de9d087525c2145d9628d7cff769` | D39 | `corex/attributes` | FieldCatalogue, AttributeStore, AttributeFilter, SchemaOperations | application owns queue dispatch |
