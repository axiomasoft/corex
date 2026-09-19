---
id: D29
date: 2026-07-18
status: accepted
item: ''
items: []
supersedes: []
superseded_by: null
---
## Решение

**Доктрина слоёв (владелец, оркестр `2026.07.17-AXIOMA-ORCHESTRA` research/01-layer-doctrine.md) — supersedes D25 (вендор-критерий) и D22 в части дома.** Новая семантика: `corex/*` = агностик-платформа (L0) + домен-основы без продуктовой семантики (L1, аналог iblock D7); `flex/*` = тематические модули экосистемы Flex (L2); продукты = композиция (L3); cross-ecosystem — standalone (радар §4.7). Переименования волны (тегов нет, дёшево): `flex/modules`→`corex/modules`; `corex/crm`→`flex/crm`; `corex/tasks`→расщепление: `corex/tasks` = L1-примитивы, `flex/tasks` = L2-модуль на основе; `corex/social`→`flex/social`; `corex/calendar`→`flex/calendar` (`corex/rrule` остаётся L0); `corex/marketing`→`flex/marketing` (edition-монетизация D22 в силе). Дом `flex/workflow`/`flex/attributes`/`flex/import-export` пересматривается на scaffold-item'ах (механизм D18): агностик-движки — кандидаты corex/* или standalone. Имена в текстах P6-items/блюпринтов читать через этот мэппинг; физическая правка текстов — на соответствующих scaffold/exec-item'ах, не отдельной волной. Механика renames в коде — COREX-CORE P2.0/D70. RAG:✅ 2026-07-18 (эталон D7 — оркестр findings/bitrix-d7-layering-2026-07-18.md)

## Почему

Прежний D25 инвертировал семантику против философии владельца (тематика в corex/*, агностик-эталон под flex/); исправление до первых тегов/потребителей дёшево, после — дорого
