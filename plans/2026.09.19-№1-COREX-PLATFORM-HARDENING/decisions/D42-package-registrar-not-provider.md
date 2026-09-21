---
id: D42
date: 2026-09-19
status: superseded
item: P8
items: [P8, P8.1, P8.2, P8.3, P8.4, P8.5]
supersedes: []
superseded_by: D101
---
# D42 — PackageRegistrar не наследует ServiceProvider

**Actor:** plan-designer/cursor-grok-4.6/high
**Evidence:** RAG:✅ P8-Q1 Laravel providers / packages, Symfony compiler
passes. RAG:— в дереве 20 `*ServiceProvider.php`, ни одного registrar'а;
`extra.laravel.providers` tenancy несёт ровно `TenancyServiceProvider`.
`CoreX\Contracts\ModuleRegistrar` — другой контракт (регистрация дочерних
провайдеров модулей), его не расширяем и не переименовываем.

## Решение

1. Вводится `CoreX\Support\PackageRegistrar` в пакете `corex/core`:
   `register(Application $app): void` и `boot(Application $app): void`.
   Пустой `boot` даёт trait `RegistersNothingOnBoot`, не default method
   интерфейса. Интерфейс **не** лежит в `Contracts/`: это соглашение
   composition root, не продуктовый контракт. В `extra.corex.public-api`
   не входит.
2. Реализация **не** наследует `Illuminate\Support\ServiceProvider` и
   **не** попадает в `extra.laravel.providers`.
3. Главный провайдер пакета вызывает registrar'ы из своих `register()` и
   `boot()`. `mergeConfigFrom`, `publishes` и `optimizes` остаются на
   провайдере: это хуки фреймворка (`RAG:✅` P8-Q3).
4. Вложенный `$this->app->register(InternalProvider::class)` запрещён.

## Почему

Эскиз `01-shapes-runtime.md` §5 принят. Альтернатива «под-провайдер на
модуль» отклонена: тип врёт, класс можно случайно автодискаверить, unit-тест
тянет lifecycle фреймворка. Альтернатива «только register(), boot остаётся
в провайдере» отклонена: P8.4 как раз выносит команды, маршруты, middleware
и подписки; без `boot()` на registrar'е провайдер снова станет складом.
Пустой default `boot()` не делает registrar самостоятельным lifecycle.

## Consequences

P8.1 пишет интерфейс. P8.2–P8.5 реализуют его. `ModuleRegistrar` не
трогается. P9 потребляет каталог `Laravel/` и не превращает registrar в
`ServiceProvider`.
