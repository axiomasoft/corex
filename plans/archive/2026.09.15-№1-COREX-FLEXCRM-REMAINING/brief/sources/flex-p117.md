### P11.7 — Профиль, workspace context и reuse авторизации

**Intent:** Авторизация и профиль готовы как общая базовая функция.
**Why:** Последовательный проверяемый шаг D38; отдельный результат позволяет владельцу корректировать работу.
**Scope Included:** Профиль, выбранный workspace/account, membership switch; общая auth composition для двух shell solutions; собственные sessions и credentials scopes, local mode до SSO.
**Scope Excluded:** Соседние items; production rollout; незаявленная смена схемы; новые отраслевые бизнес-функции.
**Inputs:** `decisions/D38-implementation-order-and-schema-approval.md` · `roadmap.md`
**Files:** packages/flexcrm/**; packages/frontend/**; solutions/**; frontend/**; tests/**
**Required Reads:** 1) `decisions/D38-implementation-order-and-schema-approval.md` 2) `roadmap.md` 3) `schema/approval.md` 4) `docs/03_Dev/ADR/ADR-002-nuxt-solution-applications.md` 5) `schema/DATABASE_SCHEMA.md` §0
**Implementation Rules:** P11.6. После P9.3 — verify approved schema version/hash до изменения кода. PHP/Artisan/Composer — в app-контейнере; тесты — отдельная PostgreSQL БД по testing-rules. Изменения владельца сохраняются.
**Code Guidance:** Один item за цикл: сообщить цель, выполнить только scope, проверить, показать результат и следующий шаг. Запись/транзакция — Action; HTTP thin; packages без App dependencies. Статус результата хранить в journal/state, не в этой карточке.
**Validation:** A→B isolation; cache/subscriptions сброшены при переключении; решения не делят APP_KEY/cookie; profile API validation; одинаковый auth contract работает в base/solution.
**Deliverables:** Авторизация и профиль готовы как общая базовая функция. Проверки и ограничения записаны в findings; handoff показывает следующий item и контрольную точку.
