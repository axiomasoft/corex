# HANDOFF — 2026-07-15 — after P1.26

**Next:** **P2.1** — stancl/tenancy v4 readiness PoC (блокирующий гейт для всей фазы P2, §2 Rules: «ни
один tenancy-item P2.2+ не стартует до GO по P2.1»). §3 Routing: `opus/high` / `manual`.

| Параметр | Значение |
|:--|:--|
| Model | opus |
| Thinking | high — PoC-гейт: инфра-дизайн + GO/NO-GO-решение, не механический exec |
| Context | continue (/clear) — ручной item |
| Суть | P2.1 — stancl/tenancy v4 readiness PoC, GO/NO-GO для DB-per-account модели P2 |

```
ЗАПУСК ВРУЧНУЮ: opus/high
```

Читать ТЗ P2.1 в `plans/COREX-CORE/phases/P2.md` перед стартом (Required Reads item'а — там же);
предусловие OQ-5 (php ^8.4) сверить перед PoC.

**Done:** (P1.26 — закрытие фазы P1, item-коммит `9a69c35`, merge-коммит `27d44e4`)
- `packages/core/CHANGELOG.md` + `packages/modules/CHANGELOG.md` — Keep a Changelog, `[0.1.0] -
  2026-07-15`, секции Requires/Added/Changed/Fixed/Deprecated·Will break in 0.2/Known limitations,
  собраны из Completion Notes P1.1–P1.25 + P1.29.
- **OQ-12 решён** (вариант а): PostgreSQL 16 — явное требование обоих пакетов (README «Требование
  к СУБД», CLAUDE.md, CHANGELOG «Requires»). SQLite-лейн задокументирован как best-effort pure-PHP,
  не production-поддержка. `open-questions.md` OQ-12 → Resolved.
- `@deprecated` на `CoreX\Contracts\TenantService`, `CoreX\Concerns\BelongsToTenant`,
  `CoreX\Scopes\TenantScope` (row-level tenancy → замещается P2 DB-per-account).
- `.claude/auto-approve.env.ini` (A88) — Required Read `findings/P1-audit-2026-07-13.md` не
  существует в репо; решение принято независимо (файл уже трекнут, `MODE=bypass`, оставлен как есть).
- `## Phase Handoff` (`phases/P1.md`) заполнен: сдано / отклонения / готовность `TenantContext` к
  P2 / launch-block P2.1.
- Гейт D34 зелёный (item- и merge-коммит): lint passed · analyse 0 · test 105/105 (sqlite) ·
  test:pg 98/98 (PG16).
- **PR #12** (`p1r-remediation` → `main`) — CI 8/8 зелёные (run `29391390612`), смержен
  `gh pr merge 12 --merge` → merge-коммит `8819f9863d05f7b99cc1dc4497f132d2eac7441f` на `main`.
  Слияние `origin/main` в ветку по ходу разрешило add/add-конфликт `plan.md`/`handoff.md`
  (main нёс устаревший снапшот от 2026-07-06) — в пользу эволюционировавшей ветки.
- **Тег `v0.1.0`** (аннотированный, repo-level — D17-дефолт, per-package split-механизм не
  настроен) на `8819f986`, покрывает `corex/core v0.1.0` и `flex/modules v0.1.0`.
- Статус **🟠 Done with deviations** — детали Known Deviations в `phases/P1.md` P1.26 (форма тега,
  `files_outside_scope`, отсутствующий Required Read A88, перебор тира sonnet/medium вместо
  предписанного sonnet/low, явное подтверждение владельца на push/PR/merge/tag).
- **Фаза P1 переведена в терминальный статус** (`plan.md` §4: `1/29 🟢 · Терминальна`), борд
  **1 🟢 · 26 🟠 · 0 🔴 · 2 ⬜** (P1.27/P1.28 — time-gated follow-up, D47/D48, не блокируют).

**Remaining:** **P2.1** (PoC-гейт, блокирует P2.2+). Time-gated **P1.27** (coverage 80% + CI-метка
m-7) и **P1.28** (phpstan `tests`-lint) — открываются по владельческому триггеру, не автоматически;
не входят в критерий закрытия фазы P1 (уже закрыта).

**Sources of truth:** `plans/COREX-CORE/plan.md` (§2 Rules, §3 Routing, §4 борд, §5 D17/D68/D69,
§6 Update Log) · `plans/COREX-CORE/phases/P1.md` (P1.26 Completion Notes/Known Deviations,
`## Phase Handoff`) · `plans/COREX-CORE/phases/P2.md` (P2.1 ТЗ) · `open-questions.md` (OQ-5 блокер
P2.1, OQ-12 Resolved) · `packages/core/CHANGELOG.md` · `packages/modules/CHANGELOG.md`.

Cold-start reads для P2.1: `plans/COREX-CORE/plan.md` (§2/§3/§5 D1/D13) → этот файл →
`phases/P2.md` P2.1 (ТЗ + Required Reads) → `open-questions.md` (OQ-5).

**Open risks:**
- OQ-5 (php ^8.4) — блокер P2.1, сверить версию/доступность перед стартом PoC.
- DB_SCHEMA DRAFT (OQ-6); stancl v4 без релиза (пин sha — предмет самого P2.1 PoC).
- m-1 (P1.21, secret-колонки вне `password`/`remember_token` на `$guarded=[]`-моделях), FeatureFlags
  narrow→wide каскад не реализован (P1.22, A69) — оба ждут P2 tenancy.
- Полная карта того, что волна НЕ закрывает — `findings/P1R-premortem.md` (кластеры C1–C8, P1–P7, U1–U6).
- **Посторонний дифф в дереве** (НЕ трогается исполнителем): untracked `.mcp.json`, `.serena/`,
  `scratch_gate.log`.

**Workarounds/Deferred/Open questions:**
- workarounds: —
- deferred: m-1/m-2/m-3/n-1 (P1.29 §Audit минорные); партиционирование `sys_audit_log`; per-тариф
  retention; Infection/MSI; coverage 80%+CI-метка (P1.27); phpstan `tests`-lint (P1.28); per-call
  ctx→connection (P2, D13); трёхуровневый Settings-каскад (P2 tenancy); реальный narrow→wide
  FeatureFlags-каскад (A69, P1.22); UI фич-флагов + `FeatureFlagToggled`-продюсер; кеш настроек
  (P1.22 Pending Work).
- open_questions: **OQ-1..OQ-9, OQ-12 (Resolved), OQ-15 (Resolved → D68)**. Блокеры: OQ-5 (P2.1),
  OQ-7 (P2.10/P2.11).
