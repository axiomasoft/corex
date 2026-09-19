### P6.7 — flex/attributes (B-15 кастом-поля / EAV) — репа + план + GREEN

**Intent:** Создать репу пакета кастом-полей (B-15: `fld_*` словарь, `cst_<slug>`-драйвер DDL, `Extend\EntityField`, `scopeFilter`, EntityRegistry-hook), развернуть план, довести до GREEN + тег.
**Why:** Кастом-поля — базовая продуктовая возможность CRM и предусловие двух модулей волны: сегменты маркетинга строятся по `fld_`-атрибутам (B-30), а `create_entity` студии процессов создаёт `cst_`-сущности (B-31). RAG:✅ B-30 §3 (сегменты через `scopeFilter`) + B-31 §3 (`create_entity` → `cst_`) 2026-07-13.
**Scope Included:** scaffold репы; план из B-15 + C-01; exec до GREEN; тег + CHANGELOG; DDL `fld_*` сверяется с DB_SCHEMA (FROZEN).
**Scope Excluded:** UI кастом-полей во FlexCRM (P6.18); EntityRegistry v2 сам (COREX-CORE P1 — зависимость, не scope); маркетинговые `cf_*`-поля first/last-touch (B-30 §2.13 — потребление, P6.16).
**Inputs:** B-15 блюпринт · C-01 · DB_SCHEMA (`fld_` — FROZEN после P6.1) · EntityRegistry v2 API (из COREX-CORE P1 — зависимость) · вердикт Q80 (нейминг).
**Files:** новая репа attributes-пакета · её `plans/**`.
**Required Reads:** 1) `05-Projects/01-Products/FlexCRM/plans/FLEX-2/findings/P6-implementation-map.md` 2) `05-Projects/01-Products/FlexCRM/plans/flex/30_blueprints/B-15_attributes-package.md` 3) `05-Projects/01-Products/FlexCRM/plans/flex/30_blueprints/C-01_code-conventions.md` 4) `05-Projects/01-Products/FlexCRM/plans/flex/60_contracts/DB_SCHEMA.md` (`fld_`) 5) `~/projects/packages/corex/plans/COREX-CORE/phases/P1.md` (EntityRegistry v2 — hook)
**Implementation Rules:** предусловие — P6.3 закрыт (EntityRegistry v2 доступен). DDL `fld_*` сверять с DB_SCHEMA поколоночно; `cst_<slug>`-таблицы создаёт драйвер `ddl` — их в контракте перечислять не требуется (шаблон, не фиксированный набор). C-01-нейминг.
**Code Guidance:** ДЕЛЕГИРОВАНИЕ + package-дисциплина (протокол как P6.6). Дополнительно: EAV — известный источник N+1 и деградации выборок; план пакета ОБЯЗАН заложить стратегию индексов + eager-load и тест «выборка 1000 сущностей с 20 кастом-полями — запросов O(1), не O(n)». Запрещено: строить EntityRegistry заново (потреблять из corex); смешивать UI с пакетом; молча менять `fld_`-DDL мимо FROZEN-контракта (расхождение → эскалация, не правка).
**Validation:** `task:plan-audit` плана пакета = GREEN; composer-гейт зелёный; тег есть; DDL `fld_*` совпадает с DB_SCHEMA (FROZEN); N+1-тест зелёный.
**Deliverables:** репа attributes-пакета + план закрыт + тег + roadmap-строка + Status Board.
