---
id: D49
date: 2026-09-28
status: accepted
item: P02.1
items: [P02.1, P02.2, P02.3, P04]
supersedes: []
superseded_by: null
---
# D49 — Согласование действующих гейтов с принятыми швами

**Actor:** executor/codex/gpt-6-sol/high, routine author-choice по plan.md §2.
**Evidence:** RAG:— `artifacts/P02.1-execution/`; старые гейты R-Z0/R-Z1/R-Z2,
R-T1, R4/R4b, provider-size и extension-contract не учитывали D27/D28/D32/D35.

## Решение

1. FieldCastRegistry классифицируется как boot-built catalogue; consumer Testing
   namespaces Messaging/Search/Wakeup объявляются в public-api владельцев.
   Новые разделы отчёта и компиляторы секций явно internal.
2. Два новых места чтения effective config объявляются точными composition paths.
   ConfigReportSection — Foundation; KernelConfigSection — composition.
   Единственное ребро ImpersonationGuard → AuditLog разрешено по точному символу
   и файлу для закрытого списка исключений write-guard (D32).
3. Bindings остаются у Modules/Panel providers через именованные registrars;
   provider-size ratchets не увеличиваются. Public-api exceptions для существующих
   типов SPI добавляются только по конкретным composition files, до P02.3/P04.
4. До физического переноса SavedViews разрешаются ровно четыре Internal-ребра
   SavedViewsRegistrar panels → существующие реализации core. Пары file/symbol
   закрыты; отсутствие старого target_file отключает исключение. Иные Internal
   импорты остаются RED. P02.3 удаляет истёкшие internal_composition_edges.
5. R-T1 разрешает consumer tests использовать только CoreX\Testing\Stubs;
   чужие TestCase/harness по-прежнему запрещены в tests и production src.
   Зависимые lifecycle-стабы копируются целиком; копия Widget для kernel tests
   не содержит optional money cast. Исходные monorepo stubs сохранены, money-cast
   поведение отдельно проверяет RegistryFieldCastsTest потребителя CRM.
6. Перенесённые operation tests получают собственный StubOperationHandler;
   новые tools unit tests используют Orchestra напрямую без зависимости на
   tests/ другого будущего пакета. Общий introspection suite ядра сохраняется,
   отдельная tools-регрессия проверяет identity, fingerprint и redaction через
   публичный RegistryIntrospection. Это уточняет форму теста из D48.
7. Три obsolete PHPStan entries извлечённой логики удаляются из modules baseline;
   nullable provenance и лишний cast исправляются в реализациях секций.

## Почему

Полный стек воспроизвёл конкретные противоречия старых гейтов уже принятым
границам швов. Широкие namespace-исключения, повышение потолков и перенос
PHPStan долга в новые пакеты не нужны. Отдельные отрицательные проверки
сохраняют enforcement запретов вне точного разрешённого набора.

## Consequences

Локальный ремонт остаётся в текущем attempt P02.1; исходные Validation повторяются.
Физический перенос остаётся P02.3, полная symbol-классификация — P04.
Acceptance и GREEN fingerprint исходного дизайна сохраняются; дополнительные
доказательства гейтов записываются в execution artifacts и Completion Notes.

- Validation tooling: `golden-delta.py` uses explicit recursive Git glob paths for `packages/*/src/**/*.php`; the original pathspec returned zero changed sources. Allowed types still derive only from changed source declarations; routes/events/wire remain byte-identical. Root PHPStan config records are validated on load; three obsolete baseline entries are removed. CRM adds intl as a test dependency for the required money registration regression.

- Standalone dependency analysis: copied kernel dev fixtures use the existing modules library via an explicit core require-dev edge; production require remains independent. The four existing module fixture unknown-class entries are rekeyed to CoreX\Testing\Stubs with unchanged budget, expiring on merged kernel autoload. New local fixtures use PSR-4 dev Support/Fixtures paths rather than declarations embedded in Pest files.
- Pint excludes only `plans/*/artifacts/*/preimage/*`, immutable audit snapshots with historical PHP source bytes. Product source, tests, gates and active plan tooling remain linted; two active tooling scripts receive formatting only.

- Dependency runner preserves exact manifest bytes around temporary Stancl repository staging (list-shaped repositories previously survived named `composer config --unset`). Modules explicitly declares Testbench Core for its new independent section test. No analyser ceilings increase.
