# Архитектурные ограничения верхнего слоя

## Критерий размещения

CoreX = reusable L0 (и уже принятые бездоменные примитивы). Пример должен работать в обычном
Laravel-приложении без CRM/CMS/Store, Flex identifiers и приватной инфраструктуры. Два FlexCRM
solution-приложения доказывают composition reuse, но **не** независимость от продуктового домена:
для новой CoreX-возможности нужен отдельный non-CRM consumer fixture с собственными сущностями.

Generic engines сначала сопоставляются с существующими standalone библиотеками. Workflow engine
по B12 AC29 должен работать без CoreX: его место standalone; CoreX содержит binding. Схожий
критерий применяется к AI gateway, billing, metering, messaging. Переименование engine в CoreX
не доказывает reuse. Product-specific adapter живёт в app/flex/standalone integration package;
CoreX держит только инфраструктурные adapters общего назначения.

## Контракт-дизайн для Sol

1. Inventory существующего поведения → минимальный gap → alternatives → typed contract → tests
   на наблюдаемое поведение → exact file/routing/acceptance items. Никакого interface-per-class.
2. Public contracts — интерфейсы; final immutable DTO/value objects отдельно; internal implementation
   не публичная точка расширения. Точные namespaces/signatures создаёт Sol, этот каркас их не утверждает.
3. Domain owns ports; adapters depend on ports. Потребитель передаёт проверенные identity/context,
   authorization policy, schema metadata и infrastructure bindings; payload не может переопределить authority.
4. Никаких CRM моделей, product permissions/edition strings, Flex domains/routes, central table
   assumptions в новых CoreX interfaces/events/storage/transport. UUID-only identity profile не меняет
   ID-policy всех моделей. Физическое tenancy остаётся выбранной архитектурой, boxed profile явный.
5. Versioned contract/envelope/schema; data migration и BC явно. Изменение принятых Contracts —
   отдельный major/owner gate согласно AGENTS, без ссылки на старое pre-1.0 послабление.
6. Existing EntityRegistry/8 extenders/events/DI и API SPI остаются extension mechanism.
   Новый registry/event bus/permission engine или ninth extender не вводится без отдельного RFC.
7. Storage/clock/queue/transport/authorization seams проверяются независимо; retries ограничены,
   idempotency/failure/unknown-commit outcomes типизированы. No mock-based production acceptance.
8. Никакого универсального CRUD, произвольных class names/SQL/connection selectors из запроса.
   Query/filter/tool descriptors имеют allowlist, версии, ограничения и explicit deny.
9. Configuration/optional adapters действительно optional: base consumer boot/install без absent
   integration не пытается получить её secrets и не регистрирует неработающие capabilities.
10. Не строить всё «на будущее»: необязательная абстракция требует конкретного R-ID, второго
    consumer scenario и объяснения, почему существующий порт не годится.

## Проверяемое качество

Sol выбирает минимальные meaningful tests для реализации: happy path + boundary/negative +
concurrency/crash/recovery там, где меняется состояние. Per-package suites/CI обязательны;
D34 root SQLite+PG, static analysis, Pint, layer scan и D98 mutation для src сохраняются.
Coverage floors не снижаются; текущие root 80, policy target 85, stricter package floors
проверяются по committed config, не копируются из старых чисел. Новые scopes включаются в scans.
Контрактная conformance запускается на каждом заявленном adapter и installed artifact.
Отдельный fault/abuse review нужен на действительно изменённых security/public/concurrency seams;
маршрут review не выдаёт автоматического phase audit consent.

Каждая Sol phase детализация обязана выдавать точные Files, commands/environment, output receipt,
связь R/AC→item, Routing Batch/Review с обоснованием, Phase Assurance и одну проверяемую GREEN
формулу. Реализация начинается только после детализации и отдельной авторизации, а не потому,
что существует фазовый каркас. Подбирать implementation model должен Sol по конкретному риску.

## Внешние первоисточники, проверенные при дизайне 2026-09-15

- RAG:✅ [SemVer 2.0.0](https://semver.org/spec/v2.0.0.html): опубликованные version bytes не
  изменяются, public API заявляется явно. Более строгая local major policy остаётся приоритетной.
- RAG:✅ [RFC 8705](https://www.rfc-editor.org/rfc/rfc8705.html): client authentication и
  certificate-bound tokens — отдельные механизмы. Поддержка библиотеки не доказывает регистрацию
  и enforcement конкретного AS/resource/edge; нужны DI/AP receipts.
- RAG:✅ [CloudEvents](https://cloudevents.io/): возможный стандарт event envelope/interoperability,
  не выбранная зависимость и не замена существующего DomainEvent. Sol сравнивает с текущим
  wire profile; adoption только при реальной необходимости, не новый event bus.

Никакие версии новых SDK, лимиты провайдера или актуальные цены отсюда не выводятся.
Версии/security advisories/data licenses проверяются в primary sources при выборе exact dependency.
