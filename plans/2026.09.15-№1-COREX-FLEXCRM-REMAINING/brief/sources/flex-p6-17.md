### P6.17 — Setup: текущий flexcrm, Laravel API + Nuxt и пакетная композиция

**Intent:** Подготовить существующий flexcrm для целевой архитектуры D37/ADR-002; потребить доказательства P9/P10.
**Why:** Владелец 2026-09-10 выбрал один существующий репозиторий для backend, frontend и отраслей. Старое требование новой app-репы отменено; legacy код переносится выборочно после проверки CoreX contracts/tenancy.
**Scope Included:** Совместимые pins CoreX/внешних пакетов, frontend/ Nuxt SPA, API convention layer, packages/flexcrm и packages/frontend, per-app dependency roots, Docker dev/prod modes и architecture enforcement.
**Scope Excluded:** Новый Git repo, слепой порт modules/Tasks, полная реализация всех отраслей, production rollout; продуктовый frontend/UX/design и новые доменные UI layers по D49. Frontend foundation здесь максимум boot/auth/пустой dashboard.
**Inputs:** D37 · ADR-002 · P9/P10 · B-38 (D29; topology delta ADR-002).
**Files:** Существующий flexcrm: composer.json/lock, frontend/, packages/flexcrm/, packages/frontend/, docker/, architecture tests; findings/P6.17-app-foundation.md.
**Required Reads:** decisions/D49-backend-first-frontend-out-of-scope.md · decisions/D38-implementation-order-and-schema-approval.md · schema/approval.md · phases/P10/P10.md · decisions/D37-nuxt-solution-applications.md · phases/P9/P9.md · phases/P9/P9.1.md · phases/P9/P9.2.md · phases/P9/P9.3.md · docs/03_Dev/ADR/ADR-002-nuxt-solution-applications.md.
**Implementation Rules:** CoreX авторится в своей repo; dev path/symlink, CI/prod mirror/pinned dist и внешний checkout в build context. Не импортировать корневой App из solution. P9.1 фиксирует только статические факты; runtime tenancy/ID проверяется после DB approval в P9.4–P9.6, auth — P11. Ни один вывод не предполагается из status board.
**Code Guidance:** Кодовые шаги выполнять по P9.4–P10.6 после owner approval схемы в P9.3, в текущей repo. Корневой Laravel не перемещать; frontend routes/API contracts задать явно. Новые пакеты имеют собственные namespaces/migrations/tests. Не повторять историческое C-07 Inertia/Filament scaffold.
**Validation:** P9/P10 имеют проверенные findings · pins и команды воспроизводятся · static SPA/API boot зелёный · architecture boundaries проходят на base + втором test consumer · запись findings/P6.17-app-foundation.md содержит проверенные ссылки на evidence.
**Deliverables:** Подготовленная основа в существующем flexcrm; evidence P9/P10 и P6.17; актуальный handoff.
