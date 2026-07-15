# HANDOFF — 2026-07-15 — after P1.29

**Next:** **P1.26** — закрытие фазы P1 (CHANGELOG обоих пакетов + секция `Deprecated / Will break in 0.2` +
`@deprecated` + правка CLAUDE.md/README + semver-теги `corex/core v0.1.0` / `flex/modules v0.1.0` + заполненный
`## Phase Handoff` + PR ветки `p1r-remediation` → `main` с зелёным CI). §3 Routing: `sonnet/low` / `plan-exec`.
Предусловие **P1.29 GREEN снято** (M-1/M-2 исправлены, гейт D34 зелёный, оба mutation-кейса пинятся); OQ-15
Resolved → D68. Остаётся решить OQ-12 (PG16-only, Decision pending) — влияет на CHANGELOG/README.

| Параметр | Значение |
|:--|:--|
| Model | sonnet (P1.26, §3 Routing — закрытие фазы, новых решений нет) |
| Thinking | low — CHANGELOG/теги/handoff по готовым Completion Notes |
| Context | continue (/clear) — ручной item |
| Суть | Закрыть фазу P1: CHANGELOG + semver-теги + Phase Handoff + PR в main |

```
/task:plan-exec COREX-CORE P1.26
```

**Done:** (P1.29 — M-1/M-2 симметричные фиксы, item-коммит `478d49f`)
- **M-1** — `DatabaseModuleLifecycle::upgrade()`: провал catch-up-миграции → откат+`throw ModuleUpgradeFailedException`, симметрично `enable()` D40 (был тихий commit `schema_version=end(ran)` + лог `failed` без rethrow).
- **M-2** — `DatabaseDepartmentRepository::move()`: `$newParent->workspace_id !== $lockWorkspaceId → throw CrossWorkspaceMoveException` под уже взятым локом, симметрично descendant-фильтру D67.
- 2 PG-regression-теста + 2 mutation-кейса (`P1.29-upgrade-swallow`, `P1.29-move-parent-workspace`) зарегистрированы и воспроизведены харнессом (краснеют под саботажем, script `exit=0`).
- Гейт D34 зелёный на `478d49f`: lint 0 · analyse 0 · test 105/105 sqlite · test:pg 98/98 (PG16 healthy).
- §Audit 2026-07-15 M-1/M-2 → **✅ ИСПРАВЛЕНО (P1.29)**; борд **1 🟢 · 25 🟠 · 0 🔴 · 3 ⬜** (⬜ = P1.26/P1.27/P1.28).
- Статус **🟠 Done with deviations** — `files_outside_scope`≠∅ (Files-поле мис-нейминг: `DepartmentRepositoryTest.php`→канонический `DepartmentTest.php`; два новых Exceptions-файла вне буквального поля), все — верная реализация Scope, не дефект кода/валидации (та же линза, что развела P1.19/21/23).

**Remaining:** **P1.26** (закрытие фазы) → `/task:plan-close COREX-CORE P1` → P2.1. Time-gated **P1.27**
(coverage 80% + CI-метка m-7) и **P1.28** (phpstan `tests`-lint) — после P1.26, не блокируют закрытие (D47/D48).
Полный ре-слепой аудит = AUDIT-CAP (3-й), НЕ требуется — точечная ре-верификация фиксов P1.29 выполнена.

**Sources of truth:** `plans/COREX-CORE/plan.md` (§2 Rules, §3 Routing, §5 D28–D69, §4 борд, §6 Update Log) ·
`plans/COREX-CORE/phases/P1.md` (ТЗ items, P1.29 Completion Notes, §Audit 2026-07-15 M-1/M-2 ✅) ·
`plans/COREX-CORE/open-questions.md` (OQ-1..OQ-15) ·
`plans/COREX-CORE/findings/P1-audit-2026-07-15.md` (слепой аудит) · `findings/P1-audit-2026-07-13.md` (RED A1..A93).

Cold-start reads для P1.26: `plans/COREX-CORE/plan.md` (§2/§3/§5) → этот файл → `phases/P1.md P1.26` (ТЗ) →
`open-questions.md` (OQ-12/OQ-15) → Completion Notes волн 1–2 (источник CHANGELOG).

**Open risks:**
- **OQ-12 (PG16-only) — Decision pending**, решить ДО тега (влияет на CHANGELOG/README P1.26). OQ-15 закрыт (D68).
- **m-1 остаточен** (P1.21): secret-колонки вне `password`/`remember_token` (`api_token`/`two_factor_secret`) на `$guarded=[]`-моделях утекают, если хост не объявил `auditSensitive()` — deferred, канонический User-кейс закрыт (D64).
- **FeatureFlags-каскад (A69)** частично открыт (P1.22): `findOverride()` single-scope exact-match, narrow→wide не реализован (WAI-докблок) — ждёт P2 tenancy.
- Ядро де-факто PG16-only (OQ-12); OQ-5 (php ^8.4) — блокер P2.1; DB_SCHEMA DRAFT (OQ-6); stancl v4 без релиза.
- Полная карта того, что волна НЕ закрывает — `findings/P1R-premortem.md` (кластеры C1–C8, P1–P7, U1–U6).
- **Посторонний дифф в дереве** (НЕ трогается исполнителем): untracked `.mcp.json`, `.serena/`, `scratch_gate.log`.

**Workarounds/Deferred/Open questions:**
- workarounds: —
- deferred (не блокируют v0.1.0): m-1 (остаточные secret-колонки), m-2 (µs `occurred_at`), m-3 (scalar-guard компилятора), n-1 (индекс prune) — все в аудите D68 §Minor/Nit; партиционирование `sys_audit_log`; per-тариф retention; Infection/MSI; **coverage 80% + CI-метка** (P1.27); **phpstan `tests`-lint** (P1.28); **per-call ctx→connection** (P2, D13/D52); трёхуровневый Settings-каскад (P2 tenancy); **реальный narrow→wide FeatureFlags-каскад** (A69, P1.22); UI фич-флагов + `FeatureFlagToggled`-продюсер (P1.9/P1.22/P1.25 Scope Excluded); кеш настроек (P1.22 Pending Work).
- open_questions: **OQ-1..OQ-15** (OQ-10/11/13/14 не существуют — OQ-11 опровергнут D57; нумерация разрежена). Блокеры: OQ-5 (P2.1), OQ-12 (P1.26, pending), OQ-7 (P2.10/P2.11).
