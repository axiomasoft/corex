# P05 — retrieval provenance

Дата: 2026-10-01. Синтез и границы доверия — `research/P05-design-rag.md`.

| Capture | Tool / source | Вопрос |
|:--|:--|:--|
| `ctx7-01-testbench-define-environment.md` | context7 `/websites/packages_tools_testbench` | когда выполняется `defineEnvironment` относительно register/boot |
| `ctx7-02-octane-container-lifetime.md` | context7 `/laravel/docs` (13.x: `octane.md`, `container.md`) | singleton/scoped под Octane, config repository, static state |
| `ctx7-03-config-cache-merge.md` | context7 `/laravel/docs` (13.x: `packages.md`, `configuration.md`, `deployment.md`) | `config:cache`, глубина `mergeConfigFrom`, `env()` |
| `pplx-01-connection-capture-captive-dependency.md` | perplexity-web `search` | захват `ConnectionInterface` vs resolver при DB-per-tenant; captive dependency под Octane |
| `pplx-02-layered-validation-production-guards.md` | perplexity-web `search` | слои валидации config (сборка / boot / использование); production-guards и acknowledgement |

Переиспользовано без повторного поиска: `artifacts/research-rag/pplx-07-laravel-typed-config-providers.md`
(typed options на границе, один публичный provider, сценарий двух запросов).

Первая редакция дизайна обошлась context7: три вопроса о поведении библиотек закрывает документация. Два вопроса
проектной практики (время жизни соединения БД, слои валидации и guards) в ней решались без внешней сверки;
после D72 они проверены perplexity, и оба ответа изменили решения (D72, строки R2–R5, R7). `vendor/` в рабочем дереве автора отсутствует, поэтому сверка с установленным
первичным кодом не выполнена и вынесена в `VERIFY-AT-EXEC` (`research/P05-design-rag.md`).
Поиск не является доказательством. Инвентарь репозитория — `RAG:—`
(`artifacts/P05-design/facts/`, скрипт `artifacts/P05-design/scripts/p05-facts.sh`).
