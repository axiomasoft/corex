# P02 — внешние факты (RAG)

Индекс captures: `artifacts/P02-design-rag/README.md`. Остальные решения P02 — факты репозитория (`RAG:—`, досье §2).

| # | Факт | Статус | Источник | Влияние |
|:--|:--|:--|:--|:--|
| E1 | Rector 2.x `RenameClassRector` переименовывает ссылки на класс в коде (use, type hints, `new`, `instanceof`, `::class`) и типы в PHPDoc; **не** меняет объявление `namespace` файла, строковые литералы и расположение файла. `withImportNames()` влияет только на форматирование. | `RAG:✅` (perplexity, 3 источника) · `VERIFY-AT-EXEC` (сухой прогон) | `pplx-01-rector-rename-class.md`; context7 без совпадений — `context7-01-rector.md` | D44 п.2: объявления, литералы и файлы — скрипт; Rector — только код; `withImportNames()` не используется (минимальный дифф), форматирование — Pint. |

Проверка E1 при исполнении: runbook §4.3 шаг 3 — после `apply` в scratch-worktree `grep -rn "^use CoreX\\\\Api\\\\" packages products tests | head` пуст, `php -l` всех изменённых файлов без ошибок. Результат не зависит от того, переписывает ли Rector объявление сам: скрипт выставляет каждое объявление явно (идемпотентно).
