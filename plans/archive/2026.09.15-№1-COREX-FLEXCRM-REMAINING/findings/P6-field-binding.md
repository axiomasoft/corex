# P6 field binding receipt

**Schema:** field-binding/1; p6-data-receipts/1
**Item:** P6.2

The package reads only active compiled `EntityField` declarations. It does not own declaration metadata. JSONB and EAV share authorization, physical connection, version/token, and typed-field boundaries.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| FIELD-01 | R11 | shared data | trusted scope and record reference | no second tenant authority | `ed54a73d9f551fda3682eb4dbb281eb7401085832aa1b3f5f8acd8865d9d88bb` | PASS | caller supplies trusted DataScope |
| FIELD-02 | R11 | registry catalogue | active/sleeping module fields | active scalar snapshot only | `8c80c91c2cbe5821a74d851096d26175182981ff3911f367fcd39404389da8ef` | PASS | registry remains owner of declarations |
| FIELD-03 | R11 | EAV | 50 records × 30 fields | one values query, ordered batch | `960b5727df188c939007e56330a1a80fa5a042f07450d3c93ca02a27424d32dd` | PASS | query budget is package-tested |
| FIELD-04 | R11 | JSONB | 50 records | one documents query | `e467e86d46146eea893dbbb711d238c927996ba5a39efbae9e9f5e366996308d` | PASS | PostgreSQL-only driver |
| FIELD-05 | R11 | typed validator | 15 scalar types | valid canonical values accepted | `63b593df5dc54c50f35af3c6a346f499789444c19012e035a34604fd7b2bca23` | PASS | complex semantics remain registry metadata |
| FIELD-06 | R11 | write boundary | stale version/key collision | replay or explicit conflict | `40b2cf1820dbee56e6d17929ed1bad1f1be85ca893af0e032725e7478c6fed11` | PASS | caller retains retry policy |
| FIELD-07 | R12 | filter boundary | unknown field/operator | structural allowlist denial | `ed54a73d9f551fda3682eb4dbb281eb7401085832aa1b3f5f8acd8865d9d88bb` | PASS | SQL query integration is consumer-owned |
| FIELD-08 | R11 | physical scope | wrong database identity | fail before read/write | `e467e86d46146eea893dbbb711d238c927996ba5a39efbae9e9f5e366996308d` | PASS | connection convention is `pgsql:<database>` |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `packages/attributes/src/Internal/Engine/JsonbAttributeStore.php` | `40b2cf1820dbee56e6d17929ed1bad1f1be85ca893af0e032725e7478c6fed11` | JSONB driver |
| `packages/attributes/src/Internal/Engine/EavAttributeStore.php` | `0d53ab7830f2054c13f9e97b5e4207f9d6a4b0fd30f95e63918a6abfa5478961` | EAV driver |
| `packages/attributes/src/Internal/Engine/FieldValueValidator.php` | `8c80c91c2cbe5821a74d851096d26175182981ff3911f367fcd39404389da8ef` | typed snapshot validation |
| `packages/attributes/tests/Pg/EavAttributeStoreTest.php` | `960b5727df188c939007e56330a1a80fa5a042f07450d3c93ca02a27424d32dd` | 50×30 conformance |
| `packages/attributes/tests/Pg/JsonbAttributeStoreTest.php` | `e467e86d46146eea893dbbb711d238c927996ba5a39efbae9e9f5e366996308d` | JSONB and wrong-connection proof |
| `packages/attributes/tests/Unit/FieldValueValidatorTest.php` | `63b593df5dc54c50f35af3c6a346f499789444c19012e035a34604fd7b2bca23` | all typed values |

## Adoption

| Surface | Proposal hash | Protected receipt | Supported local adapter | Installed contract | External owner/resume |
|---|---|---|---|---|---|
| typed attribute values | `2a2b3a02ab94da1a29a34e2b304c2190e761de9d087525c2145d9628d7cff769` | D39 | JSONB and EAV stores | AttributeStore | registry owns field declaration |
