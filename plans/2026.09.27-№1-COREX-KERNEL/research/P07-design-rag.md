# P07 — verified retrieval note

Синтез внешних фактов для D95/D96. Сырые ответы — `artifacts/P07-design-rag/` (индекс `README.md`). Дата: 2026-10-04.

| # | Факт | Статус | Источник | Решение |
|:--|:--|:--|:--|:--|
| W1 | `stancl/tenancy` не имеет тега v4; последний стабильный `v3.10.1` (2026-08-05), MIT, Laravel ^10–^13. `dev-master` подвижен (обновлён 2026-09-30) и требует `stancl/jobpipeline 2.0.0-rc7`. | RAG:✅ первичный | `http-01`, `pplx-01` | D95 п.1: v4 недопустим (D16) |
| W2 | `v3.10.1` требует `stancl/jobpipeline ^1.8.0` и `facade/ignition-contracts`; это другой API, чем используемая v4-поверхность (`facts/stancl-coupling.txt`). | RAG:✅ первичный + RAG:— | `http-01` | D95 п.1: 3.10 отклонён |
| W3 | Переключение DB-per-tenant: именованное соединение tenant, `purge()` перед сменой, default восстанавливается в `finally`; не переключать соединение внутри открытой транзакции. | RAG:✅ | `pplx-02` (Laravel docs database) | D95 п.2, п.4 |
| W4 | Очереди: в payload — скалярный tenant id; worker заново загружает tenant, активирует до загрузки моделей, снимает в `finally`; отсутствующий tenant — явная ошибка без отката на предыдущую базу. | RAG:✅ | `pplx-02` (Laravel docs queues) | D95 п.5 |
| W5 | Resident-режим: не хранить tenant в static/singleton-поле без явного сброса; cache prefix менять через пересоздание store, а не только `config()`; `Cache::flush()` в tenant-коде запрещён. | RAG:✅ | `pplx-02` (Laravel docs cache, octane) | D95 п.2–п.4 |
| W6 | Support impersonation: just-in-time grant на один tenant, короткий TTL (15–60 мин), второй утверждающий для чувствительного, staff остаётся актором, два коррелированных журнала (tenant и central), отказы и сбои логируются; SOC 2/HIPAA не предписывают реализацию. | RAG:✅ | `pplx-03` (OWASP Multi-Tenant, AICPA, HHS) | D96 п.4–п.5 |
| W7 | AzGuard не опубликован на Packagist ни под одним из имён `axioma-studio/azguard-core`, `axioma-studio/azguard`, `axiomasoft/azguard`, `axiomasoft/azguard-core`. | RAG:✅ первичный | `http-01` | D96 п.7 |

Не искалось (RAG:—): структура и связность кода, наличие `HasUuids` с UUIDv7 (проверено в `vendor/laravel/framework`), правила D69/D70.

## VERIFY-AT-EXEC

Первым шагом P07.2 (перед удалением зависимостей) исполнитель повторяет запрос Packagist и записывает результат в Completion Notes:

- `curl -s https://repo.packagist.org/p2/stancl/tenancy.json | python3 -c "import json,sys;print([v['version'] for v in json.load(sys.stdin)['packages']['stancl/tenancy'][:3]])"` → нет `v4*`. Если появился стабильный `v4.x` — стоп и эскалация автору плана: D95 п.1 пересматривается отдельным D#, исполнение P07.2 не продолжается.
