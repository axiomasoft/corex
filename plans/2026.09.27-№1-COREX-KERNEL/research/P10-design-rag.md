# P10 — верифицированные внешние факты

| ID | Факт и decision impact | Источник / capture | Статус |
|:--|:--|:--|:--|
| X1 | `composer update --with <package>:<constraint>` добавляет временное ограничение, не изменяя `composer.json`. D74 п.6. | `artifacts/P10-design-rag/local-01-composer-with.md` (Composer 2.8.2) | RAG:✅ первичный |

## VERIFY-AT-EXEC

Проверяются самим hosted CI: результат шага и есть доказательство.

| Факт | Проверка | Ожидание | При расхождении |
|:--|:--|:--|:--|
| V1 | шаг `if command -v rg; then echo has-rg; else echo no-rg; fi` в BC-джобе (временный, удаляется после проверки) | на раннере `rg` отсутствует — это причина H10 | если `rg` есть — причина иная: прочитать полный лог шага `bc-check`, чинить по факту в P10.1 |
| V2 | установка клиента PostgreSQL 18 на раннере `ubuntu-latest` из репозитория PGDG | `pg_dump --version` печатает 18.x до шага тестов | если пакет недоступен — запускать `pg_dump` из образа сервиса (`docker run --rm --network host postgres:18 pg_dump …`) через обёртку в `PATH` джоба |
| V3 | `composer update --with "illuminate/contracts:12.*"` в лейне Laravel 12 | разрешение зависимостей проходит, `git status --short composer.json` пуст | если пересечение пусто — оставить `require --no-update` только в этом лейне и записать причину в Completion Notes |

## Границы

Содержимое манифеста (схема `php-api/1` сохраняется), категория `Tooling/`, правило R6, протокол готовности worker, стартовый бюджет 5 000 мс, новый
триггер `contract-guard` — author choices D72/D74, внешним источникам не приписаны. Причины отказов установлены
по логам run `36835915965` и воспроизведению в чистой копии; число и состав находок — в `artifacts/P10-design/facts/`.
