# HANDOFF — 2026-07-08 — after P0.0

**Next:** /task:plan-exec FLEX-IMPL P0.1
**Done:** Программа FLEX-IMPL создана (bootstrap) как координационный мастер-индекс над roadmap-фазами P0–P5. 5 фаз, 16 items-майлстоунов, все поля заполнены до program-уровня (Intent/Why/Scope/Required Reads/Validation/Code Guidance-делегирование). Foundation делегирован существующему `corex/plans/COREX-CORE` (не дублируется). Vault `plans/ACTIVE.md` переключён THEMEON→FLEX-IMPL.
**Remaining:** Исполнять фазы phase-first P0→P1→P2→P3→P4 (P1 допустимо параллельно P0, но закрывается после — Routing §3). Каждый item = майлстоун, исполняется командами ЦЕЛЕВОЙ репы (не `task:plan-exec` в vault — см. Code Guidance item'а). Первый реальный шаг P0.1: доразвернуть и исполнить COREX-CORE Phase P1 в репе corex (`task:plan-design COREX-CORE P1.2` … → `task:plan-exec` → `task:plan-audit COREX-CORE P1`).
**Sources of truth:** plans/FLEX-IMPL/plan.md · plans/FLEX-IMPL/phases/P0..P4.md · ~/Vaults/Brain/plans/flex/50_execution/00_EXECUTION_ROADMAP.md (roadmap-первоисточник) · ~/projects/packages/corex/plans/COREX-CORE/** (делегированный foundation-план) · ~/Vaults/Brain/plans/flex/60_contracts/DB_SCHEMA.md · ~/Vaults/Brain/plans/FLEXCRM-W2/plan.md (дизайн-гейт P2.2)
**Open risks:** COREX-CORE items P1.2–P1.13 / P2.2–P2.13 ещё скелеты (нужен `task:plan-design COREX-CORE Pn.m` до exec); stancl/tenancy v4 не зарелижен (PoC-гейт COREX-CORE P2.1); DB_SCHEMA.md статус (DRAFT→FROZEN) — предусловие финала migration-items; имя FlexCRM-app репо не выбрано (открытое решение P2.1); FLEXCRM-W2 дизайн 🟡 (гейт FlexCRM-импл).
**Workarounds/Deferred/Open questions:**
- workarounds: —
- deferred: `corex/components` (B-10 §3.5) отложен из COREX-CORE (D6) — потребитель FlexCMS P4.4, возможен loop-back.
- open_questions: имя/расположение FlexCRM-app репо (P2.1); FlexStore edition-тиры (дизайн-гейт P4.1, `[UNVERIFIED]`); compatibility.json CI-тулинг тем (дизайн-гейт P4.3, `[UNVERIFIED]`).
