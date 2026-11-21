# Зоны и модули ядра CoreX

`axiomasoft/corex` публикует общие контракты прикладных пакетов. Корень
`packages/core/src/` содержит `CoreServiceProvider.php` и следующие модули.

| Модуль | Ответственность |
| --- | --- |
| `Audit` | Аудит действий и политика хранения журнала. |
| `Config` | Конфигурация ядра и runtime-проверка привязок. |
| `Context` | Проверенный контекст, principal, физический binding, restore epoch и допуск работы. |
| `Data` | DataScope, идентификаторы, записи, поля и их контракт авторизации. |
| `Database` | Соединение владельца, базовая модель и регистрация модели в каталогах. |
| `Departments` | Справочник подразделений. |
| `Errors` | Общие ошибки CoreX и интеграция с каталогом Packman. |
| `Features` | Прикладные feature flags. |
| `Foundation` | Композиция ядра, публичный вход lifecycle и допуск ссылок. |
| `Invocation` | Контракты обработчика и транспорта для доверенного запроса. |
| `Messaging` | Доменные факты, durable payload, inbox и outbox. |
| `Observability` | Трассировка, метрики и контекст операции. |
| `Scheduling` | Контракты планирования отложенной работы. |
| `Settings` | Метаданные и сохранение настроек. |
| `Storage` | Контракты tenant storage и scoped runtime. |
| `Time` | Часы и правила представления времени. |

Runtime namespace соответствует текущему PSR-4 пути. Сканер публичной поверхности
и его DTO находятся в dev-мосте `CoreX\Foundation\Tooling`. Скрипты используют
Packman API/SPI attributes. Runtime-зависимости от dev-мостов запрещает R6
гейта раскладки.

Ядро использует публичное обнаружение и компиляцию Packman. Lifecycle-допуск
CoreX удерживает ссылки владельцев до подтверждения их транзакции или эффекта.
Схемы и фактические переходы выполняют Packman Schema и Packman Modules.
Сохранённые представления принадлежат Packman Panels, типы денег — Intl,
прикладные поля — Custom Fields. Laravel-адаптер principal принадлежит Access
и доступен по пути `CoreX\Access\Laravel\PrincipalIdentity`.

Правила импорта зон и композиционные входы проверяет `CoreZoneTest`.
`scripts/module-layout.json` и `scripts/ownership-map.json` задают актуальный
пакетный каталог. [Раскладка](structure-profiles.md) · [Карта пакетов](package-map.md)
