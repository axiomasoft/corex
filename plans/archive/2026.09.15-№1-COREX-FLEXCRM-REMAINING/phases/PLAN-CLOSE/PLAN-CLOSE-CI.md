### PLAN-CLOSE-CI — Remote publication + CI (terminal, после P11.7)

**Intent:** Одним terminal carrier: полная cohort publication на GitHub, remote receipts, зелёный CI.

**Why:** D44/D45 — owner: нестабильная сеть; product code P9.8–P11 без remote/CI; всё remote — здесь.

**Scope Included:** Удалить `scripts/.plan-close-deferred`; `remaining-cohort-publish.py --write` (18 packages); reconcile/finalize P9.8 PUB-03..08; P9.9 released-install remote proof если применимо; `.github/workflows/*`; package-graph gate; `composer test:arch`; `findings/PLAN-CLOSE-ci.md`.

**Scope Excluded:** Новый product-код P10/P11; redesign CoreX runtime/contracts; пересборка cohort artifacts.

**Inputs:** Terminal P11.7; D43 grant; sealed packet `1447985a…`; product helpers готовы локально.

**Files:** `scripts/.plan-close-deferred` (delete); `scripts/distribution/remaining-cohort-publish.py`; `.github/workflows/**`; `scripts/package-graph-gate.*`; `tests/Arch/PackageGraphTest.php`; `delivery/remaining-cohort-20260915/publication/**`; `findings/P9-publication.md`; `findings/PLAN-CLOSE-ci.md`.

**Required Reads:** decisions/D44-ci-close-deferral.md; decisions/D45-remote-publication-deferral.md; decisions/D43-p98-release-grant.md; brief/02-ci-deferral-executor.md.

**Implementation Rules:** **Шаг 1:** удалить `scripts/.plan-close-deferred`. **Шаг 2:** стабильная сеть → `--write` full cohort. **Шаг 3:** reconcile receipts. **Шаг 4:** CI/workflows/gates. Один bounded diff.

**Validation:** `remaining-cohort-publish.py --write` exit 0; PUB-01..08 PASS; `composer lint:check`; `composer analyse`; `composer test`; `composer test:arch`; CI green или documented fix map.

**Deliverables:** Full publication ledger `complete`; green CI; `findings/PLAN-CLOSE-ci.md`; plan terminal handoff.

**Authority:** D43 G-RELEASE at write boundary; owner maintenance for CI.
