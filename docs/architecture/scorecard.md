# Архитектурный scorecard пакетов (базовый)

Шкала 0/1/2 по двенадцати критериям. Это карта неоднородности, не рейтинг и не
KPI. Один сводный числовой показатель по критериям не строится: дыра в владении
данными не компенсируется чистыми именами. Финальный пересчёт — P17, после гейтов P3.

## Оценки по пакетам

| package | responsibility | standalone-install | public-api | config | extension | data-ownership | worker-safety | package-tests | naming | structure | observability | bc-policy |
|:--|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| corex/operations | 2 | 2 | 2 | 1 | 2 | 2 | 2 | 2 | 2 | 1 | 0 | 1 |
| corex/attributes | 2 | 2 | 2 | 1 | 2 | 1 | 2 | 2 | 2 | 2 | 0 | 1 |
| corex/access | 2 | 2 | 2 | 0 | 2 | 1 | 2 | 2 | 2 | 2 | 0 | 1 |
| corex/core | 2 | 2 | 2 | 0 | 1 | 1 | 1 | 2 | 0 | 2 | 0 | 1 |
| corex/intl | 2 | 2 | 2 | 1 | 1 | 1 | 2 | 2 | 2 | 1 | 0 | 1 |
| corex/media | 2 | 2 | 2 | 0 | 1 | 1 | 1 | 2 | 2 | 2 | 0 | 1 |
| corex/modules | 2 | 2 | 2 | 0 | 2 | 1 | 2 | 2 | 2 | 2 | 0 | 1 |
| corex/notifications | 2 | 2 | 2 | 1 | 1 | 1 | 1 | 2 | 2 | 1 | 0 | 1 |
| corex/panels | 2 | 2 | 2 | 1 | 1 | 2 | 2 | 2 | 2 | 2 | 0 | 1 |
| corex/recurrence | 2 | 2 | 2 | 1 | 1 | 2 | 2 | 2 | 2 | 2 | 0 | 1 |
| corex/search | 2 | 2 | 2 | 1 | 1 | 1 | 2 | 2 | 2 | 2 | 0 | 1 |
| corex/search-intl | 2 | 2 | 2 | 1 | 1 | 2 | 2 | 2 | 2 | 2 | 0 | 1 |
| corex/search-meilisearch | 2 | 2 | 2 | 1 | 0 | 2 | 2 | 2 | 2 | 2 | 0 | 1 |
| corex/tasks | 2 | 2 | 2 | 1 | 1 | 1 | 2 | 2 | 2 | 2 | 0 | 1 |
| corex/tasks-workflow | 2 | 2 | 2 | 1 | 1 | 1 | 2 | 2 | 2 | 2 | 0 | 1 |
| corex/tenancy | 2 | 2 | 2 | 0 | 1 | 1 | 1 | 2 | 1 | 1 | 1 | 1 |
| corex/transfer | 2 | 2 | 2 | 1 | 1 | 1 | 2 | 2 | 2 | 2 | 0 | 1 |
| corex/wakeup | 2 | 2 | 2 | 1 | 1 | 1 | 2 | 2 | 2 | 2 | 0 | 1 |
| corex/workflow | 2 | 2 | 2 | 1 | 1 | 1 | 2 | 2 | 2 | 2 | 0 | 1 |
| flex/crm | 2 | 2 | 2 | 0 | 1 | 1 | 2 | 2 | 2 | 2 | 0 | 1 |

## Неоднородность

Критерии, где размах по живым пакетам ≥ 2 (есть и 0, и 2): `extension`, `naming`.
Пакеты с ≥ 4 оценками 0: нет.
Критерии, где все 20 пакетов имеют одну и ту же оценку: `responsibility` (2), `standalone-install` (2), `public-api` (2), `package-tests` (2), `bc-policy` (1) — кандидат снять с базового круга как неразличающий.
