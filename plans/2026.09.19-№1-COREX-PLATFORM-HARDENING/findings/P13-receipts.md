# Receipts — фаза P13

## P13.11 — API compatibility gate

**Дата:** 2026-09-25 · **Actor:** plan-exec/cursor-grok-4.7/high

`scripts/check-api-contract.php` compares parsed `operations.json` and `errors.json`.
It does not write a baseline and does not line-diff JSON. Diagnostics name the rule,
operation or error, and a JSON pointer. Deprecation does not allow removal inside major.

Planted pairs live in `tests/fixtures/api-contract/`. The Arch test calls that CLI.

| Carrier | Result |
|:--|:--|
| eight forbidden deltas | exit 1, rule and pointer present |
| optional field, operation, enum, error code, detail key | `api-contract: OK` |
| `composer api-contract` on `api/v1` | `api-contract: OK` |
| `tests/Arch/ApiContractCompatibilityTest.php` | 11 passed, 42 assertions |
| `corex:api-snapshots --check` on `api/v1` | passed (18 assertions) |
| `.github/workflows/ci.yml` | YAML parses; `composer api-contract` is in the layer-gate job |

`api/v1/operations.json` and `api/v1/manifest.json` were refreshed because
`contract_hash` had drifted from the current fixture profile. `errors.json` and
`openapi.json` bytes were unchanged. The compatibility gate accepts that hash-only
delta. The check command still refuses `--update`.
