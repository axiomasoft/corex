# PLAN-CLOSE-CI — terminal receipt

**Item:** `phases/PLAN-CLOSE/PLAN-CLOSE-CI.md`  
**Commits (product):** `1696cb3`, `b4302c6`, `561b346`, `981884b`, CI follow-up on `main` (stancl tenancy register, auth/tasks/pg, API unit suite, PHPStan S3 stub).

## Deferral

| Check | Result |
|---|---|
| `scripts/.plan-close-deferred` removed | PASS |
| Workflow guards `hashFiles('.plan-close-deferred')` removed | PASS (`ci.yml`, `distribution-proof.yml`, `remaining-cohort-proof.yml`) |

## CI (`academici/corex-mirror`)

| Run | Commit | Outcome | Fix map |
|---|---|---|---|
| 35430335542 | `1696cb3` | 0s / no jobs | Invalid workflow guards → removed in `b4302c6` |
| 35432897570 | `b4302c6` | `composer update` fail (stancl) | `561b346`: `stage-stancl-providers.py` before every `composer update` in `ci.yml` + `distribution-proof.yml` |
| 35434414648 | `561b346` | PG + API coverage + root coverage | PG: create `corex_search_{a,b}_test`; coverage job: same + Meilisearch service `:7700`; API: include `tests/Unit` in `packages/api/phpunit.xml` (was excluded → ~71.6% on CI) |
| 35435304191 | `981884b` | PG green; API ~72.3%; auth/tasks/tenancy/quality | `stage-stancl-providers` also registers `packages/tenancy`; auth path repos + `AZGUARD_TOKEN`; tasks PG service + `test:pg`; PHPStan AWS stub; API unit tests |

**Expected after next push:** mirror CI green except publication-dependent items; auth job requires `secrets.AZGUARD_TOKEN` on `academici/corex-mirror`.

## Local gates (post-fix tree)

| Gate | Command | Result |
|---|---|---|
| Arch | `composer test:arch` | PASS (9 tests) |
| Stancl staging + root update | `stage-stancl-providers.py` + `composer update` | PASS |

## Publication (P9.8)

| Check | Result | Resume |
|---|---|---|
| `--write` exit 0 | **BLOCKED** (exit 2, ledger `pending`) | Owner: tag byte collisions on historical E5 splits + partial releases; PUB-02/03/04/06/07 FAIL per `findings/P9-publication.md` |
| PUB-01 / PUB-05 / PUB-08 | PASS | — |
| Mirror push | `git push mirror main` | Required for mirror CI; retried after transient GitHub outages |

**Policy:** PUB-07 — no silent tag replacement; recovery needs separately approved version or verified matching-no-op on all historical repos.

## Deliverables status

| Deliverable | Status |
|---|---|
| Green mirror CI | In flight (fixes committed; await run) |
| Ledger `complete` | **Pending** (publication) |
| `findings/PLAN-CLOSE-ci.md` | This file |
| Plan terminal handoff | Update `handoff.md` when CI green + owner resolves publication collisions |
