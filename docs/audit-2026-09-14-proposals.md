# Решения владельца по архитектурным замечаниям B01–B14

Это предложения, не одобренный план переноса пакетов. Исправления поведения описаны в
[remediation](audit-2026-09-14-remediation.md). Namespace, публичные интерфейсы и принадлежность
пакетов в рамках remediation не менялись. Membership reference не зарегистрирован в runtime.

| ID | Конкретное предложение | Решение и безопасный переход |
| --- | --- | --- |
| B01 | В следующем major отделить обязательные embeddingModel/embeddingDim от `ProvisionParams`; AI-метаданные и template compatibility принадлежат устанавливаемому AI-потребителю. Locale должен задаваться потребителем, без обязательного `ru`. | Согласовать новый DTO/контракт и владельца metadata. Сначала dual-read адаптер старого формата и тест boxed provisioning без AI; удаление обязательных полей/DDL только после миграции потребителей. |
| B02 | Сохранить DB-per-account механизм, но вынести trial/grace/payment/retention в один заменяемый account policy либо отдельный SaaS-пакет. Не вводить workflow engine. | Выбрать policy seam или opt-in пакет. Зафиксировать таблицу переходов, срок хранения и владельца экспорта; adapter обязан сохранять старые переходы и resumable purge до переключения. |
| B03 | Сделать organization-функциональность самостоятельным opt-in пакетом: departments, ltree, primary department и organizational membership. | Согласовать название и уровень пакета. Сначала зависимости/миграции boxed-профиля, затем aliases и ownership DDL; не переносить контракты вместе с файлами без major-перехода. |
| B04 | Оставить существующую организационную семантику auth как стандартный driver; отделить ownership/department/workspace predicates и каталог действий от общего access decision API. | Нужен второй реальный потребитель с другой схемой или действием `approve`. До него — документировать profile. Для перехода: расширить action CHECK additive-миграцией, сохранить parity record/query и deny-by-default. |
| B05 | Явно объявить Tasks profile: PostgreSQL, configured IDs и текущие identity/workspace таблицы, ограничения глубины. Независимость обеспечивать узкими policies только при конкретном потребителе. | Согласовать, обещается ли arbitrary host schema. Если да — отдельно определить identity/workspace seams и compatibility suite; не добавлять необоснованные repositories. |
| B06 | Сохранить закрытый набор extenders. Перед новым видом contributions определить владельца данных и потребителя runtime projection. | Девятый extender/универсальный plugin framework не включён. Для будущего решения нужен пример contribution, формат compiled artifact, правила конфликтов и совместимости cache. |
| B07 | Завершить один реальный DI-путь provisioner/connection resolver и tenant model resolver либо сузить публичное обещание конфигурации. | Выбрать, какие подмены действительно поддерживаются. Проверка должна подменять binding и проводить полноценный pool claim/provision/host resolve. Final concrete-реализации и публичные constructor-сигнатуры не менять скрыто. |
| B08 | Не менять PubSub enum сейчас. При появлении второго внешнего транспорта добавить небольшой `extend(name, creator)` на уровне manager, сохранив текущие defaults. | Нужны второй driver и требования worker lifetime. Не заменять рабочий PubSub binding новым framework ради симметрии с Laravel. |
| B09 | Согласовать канон: `Contracts` только interfaces или также стабильные transport DTO. IdentitySyncer/WorkspaceResolver должны подчиняться тому же правилу. | Если выбирается переезд публичных символов — major либо документированный alias/deprecation период, проверки всех split-consumers. Механические namespace-переносы не выполнены. |
| B10 | Сделать enforcement morph map управляемым переходом: сначала инвентаризация host models и FQCN в данных, затем aliases/backfill, затем `enforceMorphMap`. | Глобальный enforcement без этой информации может сломать host-модели. Требуются список допустимых aliases, миграция существующих morph-значений и dry-run отчёт неизвестных типов. |
| B11 | Разделить локальные Laravel events и переносимые DomainEvent envelopes. Существующие Tasks/CRM after-commit events оставить совместимыми. | Уточнить, какие события публикуются вне процесса. Для них отдельно определить versioned envelope и outbox; не менять базовый класс всех событий автоматически. |
| B12 | Документировать TaskTarget/TaskWatcher как composite-identity records: поддерживаются relation/query mutation paths, не произвольный instance CRUD. | Выбрать pivot semantics либо явный surrogate key в следующем schema-переходе. Проверить attach/detach/update конкретной пары; обычные save/delete нельзя обещать до этого. |
| B13 | Уточнить CLI help/README: текущий migration wave последовательный; `--p` не даёт parallelism, `--skip-failing` не меняет уже продолжающееся после ошибки поведение. | Сохранить совместимость flags, добавить явное предупреждение об их неоперационности либо согласовать deprecation. Реальные parallel workers — отдельная работа с лимитом нагрузки/повторами и проверкой batch state. |
| B14 | Фактологию test topology и floor 80 в doctrine исправлено; CRM README описывает per-package tests, UUIDv7 default и lifecycle. | В `AGENTS.md` и composer descriptions остаётся формулировка ULID. Предлагается согласовать её замену на «configured IDs, default UUIDv7» с владельцем instruction templates; runtime ID strategy не менять. |

## Эксплуатационные решения, не скрытые изменения контрактов

- Экспорт: предоставить production writer и, для remote storage, verifier, затем выполнить restore drill.
  Default теперь запрещает фиктивный ready; наличие файла и совпадение размера сами по себе не доказывают восстанавливаемость архива.
- Legacy CRM: согласовать adoption уже существующей схемы и ownership claims до любого purge.
  Новые установки проходят стандартный lifecycle; автоматическое присвоение исторических таблиц не выполнялось.
- Registry epoch: устранено умножение SQL на число сущностей; один `hasTable`/`count` на operation пока остаётся.
  Отдельная epoch-row имеет смысл после измерения большой lifecycle history, с additive schema migration и rollback-правилом.
- Membership reference: до adoption нужны отдельное решение, compatibility protocol и rollout evidence.
  Исправление stale regrant не делает reference production-ready и не подключает его.
