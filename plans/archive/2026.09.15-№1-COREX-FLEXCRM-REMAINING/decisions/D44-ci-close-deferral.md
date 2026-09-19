---
id: D44
date: 2026-09-18
status: accepted
item: PLAN-CLOSE-CI
items: [P9.8, P9.9, P10, P11, PLAN-CLOSE-CI]
supersedes: []
superseded_by: null
---

# D44 — CI/ hosted workflow fixes only at plan close

**Date:** 2026-09-18
**Status:** accepted
**Actor:** repository owner; recorded by Cursor/Composer 2.5
**Evidence:** owner directive 2026-09-18: CI work тормозит разработку; вынести в конец плана отдельной командой.
**Supersedes:** —
**Superseded By:** —

## Решение

Все правки **CI / GitHub Actions / hosted workflow alignment / package-graph gate / distribution-proof workflow / split-repo Actions policy / mirror-vs-canonical runner wiring**, которые не являются обязательным локальным receipt для текущего item, **не выполняются внутри P9.8–P11** и не блокируют product execution.

Единственный owning carrier для сведения CI в зелёное состояние — terminal item **`PLAN-CLOSE-CI`** после **`P11.7`** (или сразу после terminal P11, если P11.7 уже closed-green).

## Команда возобновления (только в конце плана)

```bash
exec-items: task:plan-exec 2026.09.15-№1-COREX-FLEXCRM-REMAINING PLAN-CLOSE-CI
```

## Scope PLAN-CLOSE-CI (фиксированный backlog)

- `.github/workflows/ci.yml`
- `.github/workflows/distribution-proof.yml`
- `.github/workflows/remaining-cohort-proof.yml` (если требуется для canonical/mirror parity)
- `scripts/package-graph-gate.php` / `scripts/package-graph-gate.json`
- `tests/Arch/PackageGraphTest.php` и связанные CI-only гейты
- hosted runner receipts / workflow file presence на `axiomasoft/corex` vs mirror policy — **только** как CI closure, не как повтор P9.5 product proof

## Repo flag (active)

`scripts/.plan-close-deferred` — пока файл существует (D44+D45):

- `composer test:arch` / `package-graph-gate` → exit 0 без прогона (`scripts/ci-deferred-guard.sh`)
- `remaining-cohort-publish.py --write` → refused (D45)
- GitHub Actions jobs с `hashFiles('scripts/.plan-close-deferred')` → skipped

Снять только в `PLAN-CLOSE-CI` (вместе с full publication + CI).

## Запрет вызова (P9.8–P11) — executors MUST NOT

До terminal `PLAN-CLOSE-CI` **не вызывать, не править и не блокировать product items** следующими carriers:

- `composer test:arch` (включая `PackageGraphTest`)
- `.github/workflows/ci.yml`, `.github/workflows/distribution-proof.yml`, `.github/workflows/remaining-cohort-proof.yml`
- `scripts/package-graph-gate.php`, `scripts/package-graph-gate.json`
- `tests/Arch/PackageGraphTest.php`
- `gh workflow run` / workflow dry-run / hosted CI alignment / mirror-vs-canonical runner wiring
- `remaining-cohort-hosted.py` без `--finalize` (reconcile/preflight/query/dispatch) — guard `plan_close_deferral.py`
- `remaining-cohort-private.py --reconcile` (default) — только `--finalize` на cached `private/receipt.json`

Product items P9.8–P11 закрываются только своими publication/distribution/validate-* carriers; CI backlog — исключительно `PLAN-CLOSE-CI`. P9.9 rebaseline playbook: `brief/02-ci-deferral-executor.md`.

## Explicit non-waiver

- Локальные item receipts (`composer lint/analyse/test`, per-package suites, distribution helpers, validate-p9 branches) остаются обязательными для GREEN соответствующего item **кроме явно отменённых в item spec по D44** (P9.8 publication — только validate-p9 + remaining-cohort-publish + unittest).
- D44 не отменяет уже принятые hosted/private/publication receipts; не разрешает подменять product proof badge-only CI green.
