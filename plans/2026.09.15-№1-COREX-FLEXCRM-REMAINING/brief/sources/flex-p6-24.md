### P6.24 — Chatom contract integration (inbox/realtime, B-16) → GREEN

**Intent:** Реализовать стык FlexCRM ↔ ChatomIo по B-16: unified inbox, привязка переписок к CRM-сущностям, realtime-панели через PubSub — до GREEN.
**Why:** Омниканальный inbox — ключевой гап против Б24 (R-21); realtime-обновление панелей — базовое ожидание менеджера. RAG:✅ B-16 §2.2 (`cht_` 6 таблиц) + R-21 (unified inbox patterns) 2026-07-13.
**Scope Included:** app-сторона контракта Chatom (потребление inbox, привязка thread ↔ CRM-entity, realtime через PubSub ядра); тесты стыка; exec до GREEN.
**Scope Excluded:** сам сервис ChatomIo (своя репа/канон — потребляется); дизайн inbox (R-21/B-28 — закрыт); своя realtime-шина.
**Inputs:** B-16 · R-21 unified-inbox-patterns · ChatomIo-контракты (репа `~/projects/packages/chatom`) · PubSub (COREX-CORE P1) · `corex/panels` (P6.9).
**Files:** app-репа: Chatom-интеграция `app/**`, realtime · план делегата · Status Board этого файла.
**Required Reads:** 1) `05-Projects/01-Products/FlexCRM/plans/FLEX-2/findings/P6-implementation-map.md` 2) `05-Projects/01-Products/FlexCRM/plans/flex/30_blueprints/B-16_satellite-contracts.md` (§2.2 `cht_`) 3) `05-Projects/01-Products/FlexCRM/plans/flex/20_research/R-21_unified-inbox-patterns.md`
**Implementation Rules:** предусловия — P6.18 (app-слой), желательно после P6.23 (тот же паттерн adapter'а). Chatom потребляется через контракты, не форкается. Realtime — ТОЛЬКО через PubSub ядра (инвалидация/панели); собственный websocket-слой запрещён (две шины = рассинхрон и двойная эксплуатация).
**Code Guidance:** ДЕЛЕГИРОВАНИЕ + integration-дисциплина. Стык — adapter к контракту Chatom (как P6.23). Обязательные тесты: сообщение привязывается к сделке и видно в её панели; панель обновляется по PubSub-событию (без polling); пользователь без права на сделку не видит её переписку. Запрещено: форк Chatom в app; параллельная realtime-шина мимо PubSub; inbox-логика мимо паттернов R-21; прямой доступ к `cht_*` мимо контракта.
**Validation:** `task:plan-audit` делегата = GREEN; тесты привязки thread ↔ CRM-entity, realtime-обновления панели и прав зелёные; grep: нет прямых запросов к `cht_*` из app-домена.
**Deliverables:** стык FlexCRM ↔ Chatom функционален (inbox/realtime/привязка) + план закрыт + Status Board.
