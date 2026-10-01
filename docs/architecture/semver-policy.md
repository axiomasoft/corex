# Совместимость PHP API/SPI

Публичная поверхность определяется source атрибутом типа (PLAN3.D63/D64).
`since` — версия введения символа; опубликованный релиз подтверждается отдельно.

| Слой | Потребитель | Пример breaking change |
|:--|:--|:--|
| Api | Вызов, чтение, catch | Удаление типа/member, переименование параметра, сужение input, расширение output, nullability/default drift, required DTO field, удаление или смена backing value enum |
| Spi Implement | Реализация/наследование | Новый обязательный interface/abstract member, изменение protected hook, construction contract или consumer signature |
| Spi Call | Вызов/подписка/документированная интеграция | Изменение payload, constructor/default, declared subscription/data schema |
| Internal | Только владелец | Внешнего BC обещания нет; foreign import запрещён |

Новый необязательный параметр сохраняет прежнее поведение default. Добавление callable
метода в Api interface не создаёт обязательства внешнего implementer: внешняя реализация
Api запрещена. Protected hooks обещаются только Spi Implement.
Имена параметров входят в контракт named arguments. Native и вложенные PHPDoc типы,
properties, enum values, константы и defaults входят в `php-api/1` snapshots.

`composer bc-check` сравнивает каждый пакет отдельно через Roave.
Default base — `scripts/bc/accepted-base.json`, принятая unpublished поверхность P04.3
по D01/D63/D64. `BC_CHECK_BASE` выбирает конкретный release revision; stable BC gate
действует для всех изменений после этого anchor. Base и candidate
имеют собственную selection: удалённый base Api/Spi остаётся в сравнении даже при
отсутствии в candidate manifest. Атрибуты читаются из соответствующих source bytes;
Internal зависимости сохраняются для reflector. Историческая revision до разметки
может использовать только свой прежний namespace carrier; текущий carrier удалён.
Generated manifests никогда не авторизуют BC membership. Api/Spi с `@internal` запрещены
strict scanner, blanket internal filter для поддерживаемых типов недопустим.

Stable/deprecated BC break — RED. До 1.0 experimental delta допускается только с
объявленной breaking записью PLAN3.P04 в changelog пакета (D01/D63/D64); Roave infrastructure
error, missing dependency и неподдерживаемый PHP всегда RED. Это разрешение не означает
релиз, публикацию или live CI proof. `BC_CHECK_FORCE_STABLE` проверяет строгую ветку.

PHP signature BC, snapshots и behavioral tests дополняют друг друга. Семантика ошибок,
pagination, wire/schema, authorization и транзакций проверяется соответствующими
поведенческими gates и [API policy](api-policy.md); Roave их не доказывает.
