### P9.1 — Инвентаризация репозитория, зависимостей и Docker

**Intent:** Отчёт о реальном исходном состоянии и конкретных проблемах.
**Why:** Последовательный проверяемый шаг D38; отдельный результат позволяет владельцу корректировать работу.
**Scope Included:** Read-only snapshot: refs/locks, наличие среды, package mounts, legacy CoreX types и размещение migrations.
**Scope Excluded:** Соседние items; production rollout; незаявленная смена схемы; новые отраслевые бизнес-функции.
**Inputs:** `decisions/D38-implementation-order-and-schema-approval.md` · `roadmap.md`
**Files:** findings/P9.1-compatibility.md
**Required Reads:** 1) `decisions/D38-implementation-order-and-schema-approval.md` 2) `roadmap.md` 3) `schema/approval.md` 4) `docs/03_Dev/ADR/ADR-002-nuxt-solution-applications.md`
**Implementation Rules:** Предшествующих items нет; только read-only диагностика. Любые изменения кода — после P9.3 и verify approved schema version/hash. PHP/Artisan/Composer — в app-контейнере; тесты — отдельная PostgreSQL БД по testing-rules. Изменения владельца сохраняются.
**Code Guidance:** Один item за цикл: сообщить цель, выполнить только scope, проверить, показать результат и следующий шаг. Запись/транзакция — Action; HTTP thin; packages без App dependencies. Статус результата хранить в journal/state, не в этой карточке.
**Validation:** Исходники, lock-файлы и pins проверены; результаты со статусами доступности записаны; никакие данные не изменены.
**Deliverables:** Отчёт о реальном исходном состоянии и конкретных проблемах. Проверки и ограничения записаны в findings; handoff показывает следующий item и контрольную точку.
