# PLAN-CLOSE-CI — terminal receipt

**Item:** `phases/PLAN-CLOSE/PLAN-CLOSE-CI.md`  
**Disposition:** 🟡 **Closed (residual owner)** — 2026-09-19. CI/deferral scope delivered; full cohort publication not complete.

**Commits (product):** `1696cb3` … `dbe0f3c` (+ auth public GitHub VCS on close).

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
| 35435304191 | `981884b` | PG green; API ~72.3%; auth/tasks/tenancy/quality | `stage-stancl-providers` also registers `packages/tenancy`; auth path repos + public AzGuard VCS; tasks PG service + `test:pg`; PHPStan AWS stub; API unit tests |

**Expected after next push:** mirror CI green except publication-dependent items; auth job registers public GitHub VCS `axiomasoft/azguard` (no secrets) then `composer update` in `packages/auth`.

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
| Green mirror CI | **Delivered fixes** — verify latest run on `academici/corex-mirror` |
| Ledger `complete` | **Owner** (P9.8 / PUB-07) |
| `findings/PLAN-CLOSE-ci.md` | This file |
| Plan terminal handoff | `handoff.md`, `phases/PLAN-CLOSE/PLAN-CLOSE-CI.state.md` |
