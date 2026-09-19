### P6.8 — flex/import-export (B-18 миграторы) — репа + план + GREEN

**Intent:** Создать репу пакета import/export (B-18: миграторы Б24/amoCRM/1С + generic import/export), развернуть план, довести до GREEN + тег.
**Why:** Перенос данных с текущей CRM — барьер входа №1 при онбординге; GTM-критично (потребители: FlexCRM-миграция, FlexStore 1С-синк). RAG:✅ B-18 + roadmap §4 (фаза P2 OSS-выносов) 2026-07-13.
**Scope Included:** scaffold репы; план из B-18 + C-01; exec до GREEN; тег + CHANGELOG.
**Scope Excluded:** продуктовые маппинги FlexCRM (app-item P6.18); нативная 1С-синхронизация FlexStore (stub FLEXSTORE, использует этот пакет как базу).
**Inputs:** B-18 блюпринт · C-01 · форматы источников (Б24/amoCRM REST, выгрузки 1С) — RAG-верификация актуальных API на исполнении (`verify-claims`) · вердикт Q80.
**Files:** новая репа import-export-пакета · её `plans/**`.
**Required Reads:** 1) `05-Projects/01-Products/FlexCRM/plans/FLEX-2/findings/P6-implementation-map.md` 2) `05-Projects/01-Products/FlexCRM/plans/flex/30_blueprints/B-18_import-export.md` 3) `05-Projects/01-Products/FlexCRM/plans/flex/30_blueprints/C-01_code-conventions.md`
**Implementation Rules:** пакет в своей репе; идемпотентность импорта — обязательное свойство (повторный прогон не дублирует записи, dedup по внешнему ключу источника); внешние API источников — только через RAG (версии/эндпоинты меняются), не по памяти; C-01-нейминг.
**Code Guidance:** ДЕЛЕГИРОВАНИЕ + package-дисциплина (протокол как P6.6). Миграторы ОБЯЗАНЫ быть: идемпотентными (dedup по `external_id` источника), потоковыми (chunked — датасет клиента не помещается в память), с построчным отчётом об ошибках (битая запись не роняет прогон). План пакета закладывает эти три свойства + тесты на каждое. Запрещено: зашивать продуктовые маппинги в generic-пакет (они в app); глотать ошибки импорта (тихий skip = потеря данных клиента); грузить весь датасет в память.
**Validation:** `task:plan-audit` плана пакета = GREEN; composer-гейт зелёный; тег есть; тесты идемпотентности + chunked-импорта + отчёта об ошибках зелёные.
**Deliverables:** репа import-export-пакета + план закрыт + тег + roadmap-строка + Status Board.
