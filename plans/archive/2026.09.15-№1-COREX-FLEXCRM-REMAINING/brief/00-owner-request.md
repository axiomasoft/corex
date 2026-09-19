# Авторизация и границы текущего дизайна

## Исходный запрос

Пользователь вызвал `$ task:plan-design COREX-FLEXCRM-REMAINING`: новый CoreX plan contract v2,
traceability всех оставшихся требований FlexCRM, reusable boundaries, exact external/central/cloud/release
gates, dependency-ordered phases и финальная consumer reconciliation. Design only: не реализовать
код, не менять публичные API, БД, зависимости, релизы, чужие планы; не закрывать FLEX-2 milestones.

## Уточнение владельца: Astra → Sol → исполнители

Дословные ключевые фрагменты текущих сообщений владельца:

> «ты делаешь верхнюю оболочку, самой надежной, самой продуманной. детализация каждой фазы будет моделью Sol делаться»

> «План у нас изначально самый верхний слой проектируется именно моделью Astra, как я и сказал. А уже каждая фаза детализируется уже моделью Sol.»

> «Он должен быть максимально универсальным.»

Это authoring scope: Astra проектирует верхний слой сейчас; Sol отдельными `plan-design ... Pn`
детализирует фазы и выбирает execution models/effort по риску. Ни одна фаза этого каркаса не
execution-ready. Это не поручение запускать Sol или писать код в текущей сессии.

## Маршрутизация

Фактический автор: gpt-6-astra. Task capture предыдущего turn завершился
`ROUTE_LAUNCH_MISMATCH: expected exactly one Codex model-class mapping for 'gpt-6-astra', observed 0`.
Владелец после этого дважды явно закрепил Astra для верхнего слоя; этот выбор имеет приоритет
над старым skill mapping. Runtime/mapping не изменён, успешная provider route attestation не
выдумана. Journal — только design bootstrap, без старта/закрытия product item.
Sol при детализации и будущие исполнители используют фактическую проверку своего маршрута.
