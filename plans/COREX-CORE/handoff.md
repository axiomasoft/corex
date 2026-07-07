# HANDOFF — 2026-07-06 — after P0.0

**Next:** /task:plan-exec COREX-CORE P1.1
**Done:** План COREX-CORE создан (bootstrap). Дельта посчитана против текущего кода corex. Фазы P1 (B-10 ядро/модули, 13 items) и P2 (B-11 tenancy/auth, 13 items) разложены; P1.1 и P2.1 детализированы, остальные — скелеты (Intent/Why/Scope заполнены, execution-детали «—» под `task:plan-design COREX-CORE Pn.m`).
**Remaining:** Детализировать остальные items P1 (P1.2–P1.13) и P2 (P2.2–P2.13) до исполнимого ТЗ; затем исполнять phase-first начиная с P1.1. Фаза P2 заблокирована до GO по P2.1 (stancl PoC).
**Sources of truth:** plans/COREX-CORE/plan.md · plans/COREX-CORE/phases/P1.md · plans/COREX-CORE/phases/P2.md · ~/Vaults/Brain/plans/flex/60_contracts/DB_SCHEMA.md (DB-контракт, обязателен для migration-items) · ~/Vaults/Brain/plans/flex/50_execution/00_EXECUTION_ROADMAP.md · ~/Vaults/Brain/plans/flex/30_blueprints/B-10_*, B-11_* · R-11_stancl-readiness.md
**Open risks:** stancl/tenancy v4 не зарелижен (пин sha, PoC-гейт P2.1 обязателен); собственный tenancy-каркас corex/core замещается физической моделью — миграция потребителей (crm/tasks TenantModel).
**Workarounds/Deferred/Open questions:**
- workarounds: —
- deferred: corex/components (B-10 §3.5) отложен из P1 (D6 — потребитель FlexCMS в roadmap-P5).
- open_questions: DomainEvent↔CoreXEvent/Cancellable соотношение (решить в P1.9); updated_at в append-only sys_audit_log (B-10 §9 п.7 — эскалировать в P1.8); users полная vs тонкая проекция (принято full — D4, но B-11 §9 п.1 помечал неутверждённым — подтвердить у Approval Owner при желании).
