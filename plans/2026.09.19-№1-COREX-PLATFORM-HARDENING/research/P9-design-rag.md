# Внешние подтверждения P9

**Дата:** 2026-09-19. **Статус:** 4/4 первичных источника прочитаны.
Полный provenance и digest'ы — `artifacts/P9-design-rag/manifest.json`.

Perplexity в этой Codex-сессии не был подключён. Web-search transport дважды
вернул `connection failed`; поэтому использован прямой read-only HTTP fetch
первичных страниц. Это ограничение зафиксировано, а не замаскировано
под Perplexity-ответ.

## P9-Q1 — Зачем context-first

**Источник:** Martin Fowler, *Bounded Context*.

**Вывод:** крупную единую модель нужно делить на явные bounded contexts
и явно называть отношения между ними. Это поддерживает выбор
13 связных корневых групп вместо смешивания доменов и слоёв.

## P9-Q2 — Почему Laravel не домен

**Источник:** Microsoft Learn, *Design a DDD-oriented microservice*.

**Вывод:** domain layer должен быть независим от infrastructure; application layer
координирует, а не содержит business rules. Это поддерживает
отдельный `Laravel/` composition/adapters boundary и D44.

## P9-Q3 — PSR-4 и совместимая физическая раскладка

**Источник:** Composer schema, `autoload.psr-4`.

**Вывод:** namespace prefix может указывать на один путь или массив путей.
Значит P9 может физически разложить models/events/jobs по контекстам,
не ломая их стабильный FQCN и не вводя alias-классы.

## P9-Q4 — Порядок и lifecycle tenancy bootstrappers

**Источник:** Tenancy for Laravel v4, *Bootstrappers*.

**Вывод:** bootstrapper'ы идут в порядке конфига, revert — в обратном;
экземпляр один и хранит исходное central state. Поэтому простой
`class_exists` недостаточен: P9 проверяет порядок, lifetime и повторный cycle.

## Граница доказательства

Источники не решают CoreX-специфичную карту, не доказывают, что
какой-либо текущий FQCN можно сломать, и не обосновывают relay scheduler.
Эти решения опираются на репозиторий, D27/D44 и правила плана.

