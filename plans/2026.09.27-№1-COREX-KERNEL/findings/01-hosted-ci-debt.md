# Реестр долга hosted CI на базе P04

Дата: 2026-10-01 · commit `39ae489a` (закрытие P04; продуктовый код = `975cc3f0`) · автор: designer/claude-opus-5-5/high.
Источник: GitHub Actions, workflow `CI`, run `36835915965` (push, `39ae489a`), логи джобов через `gh api`.
Метод: только чтение. Локально ничего не запускалось: в рабочем дереве автора нет `vendor/` и не запущен Docker.

## Главные факты

| Факт | Доказательство |
|:--|:--|
| Hosted CI исполняется: лимиты, из-за которых принят D47, больше не блокируют запуск | джобы run `36835915965` выполняют шаги от 20 секунд до 10 минут |
| Последний зелёный hosted CI — 2026-07-15, commit `97fdc77c` | `gh run list --workflow CI --status success` |
| Из последних 60 запусков `CI` 53 красные | `gh run list --workflow CI --limit 60` |
| На commit закрытия P04 упал 21 джоб из 37 (15 зелёных, 1 пропущен) | run `36835915965` |
| Локальный стек P04.4 зелёный при PostgreSQL на host-порту 5434 | `artifacts/P04.4-execution/completion.md` (root 883/884, PG 240, packages exit 0) |

Вывод: статус «GREEN» фаз P01–P04 доказан только для локального окружения исполнителя. Hosted-матрица
(PHP 8.4/8.5 × Laravel 12/13, покрытие, статанализ, аудит зависимостей, BC) с июля не подтверждала ни один commit.

## Классы отказов

Разобраны логи джобов PHP 8.4 / Laravel 13 и всех не матричных. Лейны Laravel 12 и PHP 8.5 падают на тех же шагах;
их специфику проверяет дизайн P10.

| ID | Класс | Что падает | Причина по логу | Природа |
|:--|:--|:--|:--|:--|
| H1 | PG-тесты в sqlite-лейне | root `Tests` (2 теста webhooks), `Panels` (1 тест) | `connection to server at "127.0.0.1", port 5434 failed` — тест без группы `pg` ходит в PostgreSQL; порт 5434 локального Docker зашит как умолчание | дефект тестов: локальное окружение маскирует неверный лейн |
| H2 | Гейты красные в CI | `Tests\Arch\PackageGraphTest > package graph gate reports no violations`; `AttributesArchitectureGateTest` (custom-fields) | воспроизведено: `[R4b] API-STALE` для манифестов core и access — генератор разворачивает унаследованный метод фреймворка, новая patch-версия добавила ему `@throws` | манифест зависит от версии зависимостей — дефект герметичности |
| H3 | Тест против шага CI | `Operations … LoadContourBootTest > public surface keeps transfer optional` | список `require` получил `laravel/framework`: его добавляет шаг CI `composer require laravel/framework:$LARAVEL_VERSION`, тест сверяет точный список | конфликт CI-шага и теста |
| H4 | Версия клиента PG | `Tenancy (PG18)`: 8 тестов `ClaimLifecycleConformanceTest` | `pg_dump: server version: 18.6; pg_dump version: 16.15` | образ CI без клиента PG 18 |
| H5 | Дедлайн worker включает запуск процесса | root `Tests`, `Coverage`, `Tools`: `schema_limit`/`not-executed`; в `Coverage` также transfer (`memoryLimitReached`), всего 31 тест | воспроизведено без покрытия: 16 тестов tools — 100 мс `WORKER_DEADLINE_MS` включают старт PHP-процесса | дефект продукта: под нагрузкой инструмент отказывает так же |
| H6 | Пороги покрытия | `Tools` 72.6 % < 80; `Webhooks` 75.9 % < 80; `Tasks` 82.1 % < 92 | тесты зелёные, порог не достигнут | недопокрытие после разреза operations (P02) |
| H7 | Статанализ | `PHPStan (modules-lint on tests/)` | `packages/access/vendor/autoload.php: No such file` — конфиг требует per-package `vendor/` | дрейф CI-конфига |
| H8 | Объявление зависимостей | `Standalone dependencies`: core — 2 unknown classes, 1 shadow dependency (`PhpParser` в `Attributes/Internal/SurfaceScanner`), 3 dev-зависимости в production-коде; tasks-workflow — 1 unknown class | анализатор зависимостей | реальный дефект манифестов после P04 |
| H9 | Contract-guard | `src/Contracts changed WITHOUT CHANGELOG.md` в access, custom-fields, media, panels, recurrence, search, search-intl, tasks-workflow и далее | гейт требует запись CHANGELOG при изменении контрактов | документация не обновлена при P03/P04 |
| H10 | BC-джоб | `Roave BC diff (experimental report-only)` | `The url supplied for the path (packages/_vendor/phpstan) repository does not exist`: `bc-check.sh` ветвится по `rg`, которого нет на раннере (V1) | скрипт зависит от инструмента вне объявленного набора |
| H11 | Агрегатор пакетов | `Remaining cohort package aggregator` | `packages/access`: тесты запускаются без установки зависимостей пакета (воспроизведено: `pest: not found`) | джоб не самодостаточен |
| H12 | Шум healthcheck | `Tasks`: `FATAL: role "root" does not exist` | healthcheck контейнера PG под пользователем `root` | косметика, не причина отказа |

Зелёные джобы того же запуска: PG-лейны (postgres 16/18 × Laravel 12/13), Crm, Media, Notifications,
Manifest policy, Workflow supply chain security, Anti-weakening gate.

## Что это значит для плана

1. Отказы H1, H2, H8, H9 — дефекты продукта и тестов, внесённые или не замеченные в P02–P04; H3, H4, H7, H10, H11 —
   дрейф CI; H5, H6 — качество тестов. Ни один не является «внешней» проблемой раннера.
2. Исполнять P05 на красной базе нельзя: P05 меняет boot и время жизни биндингов во всех пакетах, новые отказы
   смешались бы со старыми.
3. D47 принят при заблокированном раннере; его условие («после восстановления лимитов») наступило.

Решения — `decisions/D73-hosted-ci-acceptance-boundary.md`, `decisions/D74-p10-hermetic-base.md`; ремонт — `phases/P10/`.

## Не проверено

- Лейны Laravel 12 и PHP 8.5: сверх общих отказов — `OperationContractTest::real routes…`, `ToolAuthorization… InvalidArgumentException` (Laravel 12); ожидаемо следствие H5, проверяется после P10.3.
- Отказы transfer (`memoryLimitReached`) под драйвером покрытия: причина не разобрана, владелец — P10.4.
- Воспроизведено автором в чистой копии без PostgreSQL (`artifacts/P10-design/facts/`): H1, H2, H5, H7, H11. Исправления не запускались.
- Workflows `distribution-proof.yml` и `remaining-cohort-proof.yml`: их запуски не анализировались.
