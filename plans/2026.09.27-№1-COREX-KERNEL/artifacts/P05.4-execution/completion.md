# P05.4 — completion notes

Product commit `6bf07d9a` (local, not pushed). Run `47536990eeee4ec143eabeb5f13d0d80b891d3ee6f77cf54a2847e0d78238756` (solo, route `claude-code/claude-sonnet-5-5/high`, class `implementation/high`, owner invocation `task:plan-run … P05.4`).

## Delivered

- **`tests/Feature/ResidentLifecycleTest.php`** (3 tests): `Outbox`, `Inbox`, `TenantStorage`, `TenantContextRestorer` are different instances after `forgetScopedInstances()`; `CoreOptions` is the same instance; scoped services are stable inside one lifecycle; tenant storage built after the boundary points at the tenant that is current then. Mutation case `P05-scoped-to-singleton` (`scripts/mutations/P05-scoped-to-singleton.patch`, registry entry in `scripts/mutation-check.sh`) turns it red and the tree is restored.
- **`packages/tenancy/tests/Pg/ResidentTenantSwitchPgTest.php`** (group `pg`): two accounts with their own databases, kernel migrations in central and in both tenant databases; `Outbox::append()` under A, `end()`, `forgetScopedInstances()`, append under B. Markers are read through the explicit connection of each database: A only in A, B only in B, central empty. Negative control run: `scoped(Outbox)` → `singleton(Outbox)` turns the test red (`OutboxRefused`: the captured connection has no transaction); source restored.
- **`tests/Feature/ConfigCacheTest.php`** + composer `config-cache-check`: every `packages/*/config/*.php` survives `var_export` → `eval` strictly; a probe config with a closure or an object is rejected (negative control inside the test); an application is bootstrapped by Illuminate's `LoadConfiguration` from a real cached repository (`bootstrap/cache/config.php`) and `CoreOptions` is built from the cached values (a sentinel table name proves the cache, not the files, is read).
- **`scripts/configuration-reference.php`** (`--write` / `--check`) + **`scripts/configuration-classes.json`**: rows come from `packages/*/config/*.php` read statically with nikic/php-parser (key set, type, default; nothing executed, no host path or date); `typed_options_field` is `<ShortOptionsClass>::$property` resolved by reflection over the Options classes declared per config file; owner, secret, mode, reloadable, validation, deprecated_aliases, notes are carried from the JSON. `docs/architecture/configuration.md` is generated: 218 rows, 0 rows of `corex*`/`tenancy*` without `Options::$`. Two `--write` runs are byte-identical; a one-cell manual edit makes `--check` exit 1, restoring it exit 0; a key without an Options field is a generator error (exit 2).
- **Doctrine**: constitution articles RUN-004, RUN-005, CFG-002…CFG-005 now name executable enforcement (`runtime-globals-gate` G2/G3/G4, `SingletonInjectionTest` L1–L3, `ResidentLifecycleTest`, `ResidentTenantSwitchPgTest`, `config-composition-gate` C1–C4, `OptionsSingletonTest`, `RuntimeConfigGate`, `corex:config:validate`, `config-cache-check`, `configuration-reference`); the paragraph about 152 `config(` lines is removed; `docs/architecture/README.md` enforcement table mirrors it; `config-policy.md` has the validation layers, closed list of dangerous modes with acknowledgement, secret handling and the config:cache / resident-mode section; `research/04-architecture.md` §7 states the binding by D69 п.3.
- **Composer / CI**: scripts `config-cache-check`, `configuration-reference` (`--check`) beside the existing `runtime-globals-gate`; three steps in the `tests` job of `.github/workflows/ci.yml` and in `scripts/ci-local.sh`; no new actions, SHA pins and `permissions` unchanged.

## Findings

- **Lifetime defects found by the new tests: none.**
- **Stale mutation patches (found by `mutation-check.sh --all`, first full run since P05.1):** seven patches stopped applying because P05.1–P05.3 refactored their anchors (`P1.16-no-epoch-bump`, `P1.16-statereader-bare-binding`, `P1.21-killswitch`, `P1.21-killswitch-writeseam`, `P1.23-event-in-transaction`, `P1.23-stale-read`, `P1.29-upgrade-swallow`). They applied at `39ae489a`. Re-anchored to the current code with the same sabotage intent (`options->auditEnabled`, `$this->events->dispatch`, `ConnectionResolverInterface` reader); registered test commands unchanged. `--all` now exits 0 with all cases red.
- `P1.19-hardcode-uuid` still passes only through the loud 3-way fallback ("reanchor required"); untouched, green.
- The CI workflow had no `config-composition-gate` step: the spec's "рядом с config-composition-gate" has no anchor, the three steps were added after the `Tests` step of the `tests` job.
- Config rows: 61 keys that were missing from the stale registry (corex-operations, webhooks, messaging, custom-fields, …) were classified by the executor (`mode`/`secret` by key name, `reloadable`/`validation` `unobserved`). Existing rows carried unchanged. Registry rows `corex.*`, `corex.modules.cache.*`, `corex-access.identity`, `corex-access.identity.columns` were dropped (superseded by leaf keys / single reader `CoreOptions`); `auth.*`, `database.default`, `flex-crm.search_language`, `tenancy.identification.middleware`, `tenancy.membership_guard.guard` stay as `external` rows.
- `typed_options_field` special values: `TenancyOptions::$(reference)` for `tenancy.stancl.*` (Composer pins, not runtime, P7 design §2.3) and `TenancyOptions::$(registrar-read)` for `tenancy.identification.middleware` (read by `HttpRegistrar`; no Options property, adding one is a runtime change outside this item).
- The harness `scripts/mutation-check.sh` calls `rg`, which is absent on this host: its "no tests found" guard is skipped silently (pre-existing).
- No defect in `Impersonation/**` or the stancl adapter was met.

## Validation evidence (candidate = product commit `6bf07d9a`; receipts in `validation-receipts.json`, logs in `validation/`)

| Command | Result |
|:--|:--|
| `pest tests/Feature/ResidentLifecycleTest.php` | 3 passed |
| `PGSQL_PORT=5434 composer --working-dir=packages/tenancy test -- --group=pg --filter=ResidentTenantSwitch` | 1 passed (6 assertions) |
| `mutation-check.sh P05 scoped-to-singleton` | red under patch, tree restored |
| `mutation-check.sh --all` | exit 0, all registered cases red (the previous 34 kept, plus `P05`) |
| `composer config-cache-check` | 3 passed |
| `configuration-reference.php --write` ×2 / `--check` / awk / manual edit | identical sha256 / exit 0 / `0` / exit 1 then exit 0 |
| constitution `grep -c` and RUN-005 identifiers | `0`; all four named |
| `package-docs-gate`, `anti-weakening-gate.sh` | OK |
| `config-composition-gate`, `provider-size-gate`, `runtime-globals-gate`, `SingletonInjectionTest` (10 tests, empty allowlist), `quality-ceiling-guard` | OK |
| `composer test:hermetic` | 993 passed + 17 passed |
| `PGSQL_PORT=5434 composer test:pg` | 242 passed |
| `composer test:packages` | all 20 packages passed |
| `composer test:arch` | 135 passed |
| `composer golden` | 4 passed, unchanged vs P05.3 |
| `composer analyse`, `composer lint:check` | exit 0 / `pint passed` (first run found 46 phpstan findings and 1 pint fixer in the new files; fixed and rerun) |

## Hosted CI (D73) — left the item by owner decision D89

The hosted-CI Validation bullet was removed from the P05.4 spec at the owner's direction («CI не фикси, это отдельно сделает sol», option «Перенести carrier», `owner-direction-d89.md`, decision D89, design-audit waiver for the new fingerprint). Nothing was pushed in that session. **Phase P05 was not closed then**: D73 still required a green workflow `CI` on the final phase commit.

review: self-check GREEN (dossier §6: Options and gates by `configuration-reference`, lifetime by the two resident tests with negative controls, docs-gate OK). The owner asked for an independent review on `codex/gpt-6.1-sol` (high): it is a separate read-only `Review` run, not started from that session and not started from the closure session either.

## Closure session — tails and hosted CI

Executor `grok-4.7/high`. Product commits after `f444568a`, pushed only to `mirror`:

- `a5c1f350` — `mutation-check.sh` no longer calls `rg`. A Pest run that prints `No tests found` exits 2 (shown with a filter that matches nothing). `P1.19-hardcode-uuid` is reanchored to `CoreOptions::settingsTable`: strict `git apply --check` passes and the ulid settings test goes red (`uuid` vs `bpchar`). `bash scripts/mutation-check.sh --all` exit 0, 36/36, no `stale patch context`. Tree restored.
- `a7a7d4a4`, `9aaaa7f6`, `0c7f7e84` — repairs for red mirror jobs. Detail and the green run are in `hosted-ci.md`.

Green hosted run: `37123316791` on `0c7f7e84819e8730815afdc93e2256c13ab0f0cd`, conclusion `success`, 36 success / 0 failure / 1 skipped (mutation registry is `pull_request`-only). `phase_audit_policy` for P05 is `PHASE_DIRECT_CLOSE_ASSURED` (`audit_required` false, `close_allowed` true). No phase audit was admitted or run.

The owner then permitted the contract heading change. `phases/P05/P05.md` now starts the contract with `> **Phase Contract.**`; the contract text after the heading is unchanged. `plan_closure` wrote `phases/P05/P05.closed.md`. Journal `plan-close` for P05 is `closed-green` (run id `e9e41291d68088bb39e904a5453de1d4da5e501a681a9e91dcd9877deef3da21`). `plan-work finalize --item P05.4` built `terminal-delivery-receipt/v1` with status `🟢 Done` and Next `design-phase: task:plan-design 2026.09.27-№1-COREX-KERNEL P06` (`artifacts/P05.4-execution/terminal-receipt.json`). `plan-lint` OK. The D89 waiver fingerprint is no longer the current `design_snapshot`; D89 itself was not rewritten.

Recorded and not repaired:

- Foreign bundles P00.1–P00.3 and P01.1 stay stale at HEAD (glossary digest; `WriteApiSnapshots.php` unresolvable in P01.2).
- Independent review of P0 and of P05.4 remains for `codex/gpt-6.1-sol`. This session did not launch it.
