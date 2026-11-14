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

Автоматического BC-диффа (Roave, `composer bc-check`) сейчас нет.
Изменения публичной поверхности проверяются `php-api/1` snapshots (`composer php-api`)
и Golden php-surface; изменение фиксируется в CHANGELOG пакета.
**До стабильного релиза** обязателен независимый BC-дифф против утверждённой базы,
а затем против опубликованного тега. Сохранённые отчёты прошлых проверок его не заменяют.
Проверка свежести snapshot не доказывает совместимость; обновление snapshot вместе
с кодом и CHANGELOG не авторизует breaking change поддерживаемого релиза.

Snapshots и behavioral tests дополняют друг друга. Семантика ошибок,
pagination, wire/schema, authorization и транзакций проверяется соответствующими
поведенческими gates и [API policy](api-policy.md).
