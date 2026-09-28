---
id: D42
date: 2026-09-28
status: accepted
item: P01.1
items: [P01.1, P01.4]
supersedes: []
superseded_by: null
---
# D42 — Laravel 12 test stack correction (accepted)

**Actor:** owner choice, 2026-09-28: «Разрешить Pest 4.7 только в Laravel 12-лейне; Pest 5.2 оставить для Laravel 13 (рекомендуется)».
Mechanical validation correction: executor/codex/gpt-6-sol/high under D41's inline-correction mandate.
**Evidence:** `artifacts/P01.1-execution/upstream-process-constraints.json`,
`l12-update-with-dependencies.log`, `l12-proposal.json`, `l12-proposal-test.log`,
`validate-clean-worktree.log`, `validate-clean-manifests.log` in the same artifact directory.

## Решение

1. Уточнить D37: основные манифесты и Laravel 13 используют Pest `^5.2` / PHPUnit 13.
   Только Laravel 12 test/CI-лейн в своём checkout переопределяет dev requirement на
   Pest `^4.7` / PHPUnit 12 перед полным Composer update. PHP `^8.4`, Testbench
   `^10.12|^11.3`, runtime-поддержка Laravel 12/13 и остальные правила D37 сохраняются.
2. P01.1 runbook: require `illuminate/contracts:12.* --no-update`, затем
   `composer require --dev pestphp/pest:^4.7 --no-update`, staged provider update,
   cleanup и неизменённый `composer test`. P01.4 применяет то же переопределение
   только в Laravel 12 ветке матрицы; CI-код выполняется в P01.4.
3. Strict validation перебирает `composer.json` и `packages/*/composer.json`,
   используя родительский каталог каждого манифеста. Это ровно 21 installable root.
   Каталог с одним историческим CHANGELOG не является Composer-корнем.
   Строгость Composer и все отрицательные контроли сохраняются.

## Почему

Pest 5.2.0/5.2.1 требует Symfony Process 8.1; Laravel 12 требует Process 7.x.
Разрешение полного набора несовместимо даже с `--with-all-dependencies`.
Testbench, принимающий PHPUnit 13, не устраняет конфликт Pest → Symfony.
Проверенный Laravel 12 кандидат с Pest 4.7.8 проходит 808 + 10 корневых тестов
без изменения assertions. Исходная glob-команда strict-validation захватывает
`packages/import-export/CHANGELOG.md`, хотя у этого каталога нет манифеста.

## Consequences

P01.1 получает исполнимый Laravel 12 acceptance lane; P01.4 потребляет эту поправку.
Standalone package dev-манифесты остаются на Pest 5.2. Новая независимая проверка
текущего diff и evidence обязательна перед GREEN. Публикация и push не разрешаются.
