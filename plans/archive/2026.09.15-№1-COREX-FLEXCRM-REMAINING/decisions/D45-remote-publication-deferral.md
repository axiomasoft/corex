---
id: D45
date: 2026-09-18
status: accepted
item: PLAN-CLOSE-CI
items: [P9.8, P9.9, P10, P11, PLAN-CLOSE-CI]
supersedes: []
superseded_by: null
---

# D45 — GitHub publication/reconcile writes only at plan close

**Date:** 2026-09-18
**Status:** accepted
**Actor:** repository owner; recorded by Cursor/Composer 2.5
**Evidence:** owner directive 2026-09-18: unstable network; product code first; all `gh`/split publish/release only at final plan step together with CI (D44).
**Supersedes:** —
**Superseded By:** —

## Решение

До terminal **`PLAN-CLOSE-CI`** (после P11.7) executors **не вызывают** remote publication carriers:

- `remaining-cohort-publish.py --write`
- массовый `--reconcile` для закрытия PUB-03..08 (read-only preflight/local verify допустимы)
- любые `gh release` / split push / создание split repos вне PLAN-CLOSE-CI

P9.8–P11: **только локальный product-код**, helpers, fixtures, validate-* structural, unit tests без сетевого GitHub.

## Repo flag

`scripts/.plan-close-deferred` (вместе с D44 CI guard). Снять только в `PLAN-CLOSE-CI` перед полным `--write` cohort + CI green.

## PLAN-CLOSE-CI расширен

Terminal item включает: снять deferral → `remaining-cohort-publish.py --write` (full 18) → reconcile P9.8/P9.9 remote receipts → CI/workflows/gates (D44 backlog).
