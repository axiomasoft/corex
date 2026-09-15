---
type: decision
plan: FLEX-2
status: approved
schema_version: 0.2.2
review_sha256: 1039c18dddc102398df84ccfe75ba91ab93e5c6f61aee689c51762916b91a0a5
approved_sha256: 1ef24ff57f3480829499ab621a9499adc9a96cbc5f618555d977a6f9f625e98f
approved_by: Dmitry Vostrikov
approved_at: 2026-09-11T09:34:45Z
approval_scope: full-target-contract
---

# Утверждение схемы БД — обязательный gate

**Статус: APPROVED — владелец явно утвердил исправленную deployment-дельту D39.**

Документ: `schema/DATABASE_SCHEMA.md`. Версия 0.2.1 остаётся последней утверждённой DDL-базой. Владелец уточнил data topology: base CRM, construction и property-management одного account используют одну account DB, а Docker изолирует runtime, не данные. DDL, столбцы и связи 0.2.1 не меняются; меняется утверждённый deployment profile.

Первый кандидат 0.2.2-review был подтверждён владельцем, но независимое ревью обнаружило внутренние противоречия gate и неполное распространение D39. После исправления байты изменились, поэтому прежнее подтверждение не переносится на новый кандидат. Construction и property-management пока не получают собственных доменных таблиц; будущие таблицы добавляются в общую account DB только отдельной schema delta.

Review SHA-256 с обнулённым полем `review_sha256`: `1039c18dddc102398df84ccfe75ba91ab93e5c6f61aee689c51762916b91a0a5`. Обычный SHA-256 утверждённого файла: `1ef24ff57f3480829499ab621a9499adc9a96cbc5f618555d977a6f9f625e98f`. Точная пара подтверждена владельцем в текущем Codex thread и разрешает реализацию P10.5 в пределах D39.

Предыдущая утверждённая база: версия `0.2.1`, review SHA-256 `abad39fe364dbf7eaed1c88b009870284b1cb716bcaada53fba51c1e1ac843cd`, artifact SHA-256 `bc516d29df82a676e27b5ef22ddbd2bde006a93f257e85e9396724afaa9fdc25`.

После любой правки утверждённой схемы требуется согласовать версионированную дельту; прежние SHA перестают подтверждать новую версию.

Этот gate не разрешает production deployment, удаление БД/volumes или необратимый перенос рабочих данных. Отраслевые shells не имеют новых доменных таблиц; добавление таких таблиц — будущая отдельная дельта.
