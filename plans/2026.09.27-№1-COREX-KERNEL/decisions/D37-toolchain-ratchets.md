---
id: D37
date: 2026-09-28
status: accepted
item: P01.1
items: [P01.1, P01.3, P01.4, P09]
supersedes: []
superseded_by: null
---
# D37 — toolchain-ratchets (accepted)

**Actor:** author-choice (claude-opus-5-5/high, Claude Code, `task:plan-design … P01`, 2026-09-28) по мандату B6; уточняет D03 (VERIFY-AT-EXEC версий), D16 (анализатор), D17 (baseline-guard).
**Evidence:** RAG:✅ `artifacts/P01-design-rag/http-01-packagist-versions.md`, `http-02-releases-postgres.md`, `pplx-01-composer-dependency-analyser.md`, `pplx-02-pest5-phpunit13.md`; RAG:— `artifacts/P01-design/facts/README.md` F1, F4, F6.

## Решение

1. **Тест-стек:** `pestphp/pest ^5.2` (PHPUnit `^13.3` транзитивно), `orchestra/testbench ^10.12|^11.3`, `laravel/pint ^1.32`, `phpstan/phpstan ^2.2.16`, `larastan/larastan ^3.12`, `phpstan/phpstan-strict-rules ^2.0.12`, `phpstan/extension-installer ^1.4.3`, `rector/rector ^2.6` + `driftingly/rector-laravel ^2.6`, `shipmonk/composer-dependency-analyser ^1.8.4`, `roave/backward-compatibility-check ^8.22`. `brick/money` не трогается (переезд — P02/D27).
2. **PHPStan per package:** `packages/<p>/phpstan.neon` — level `max`, strict-rules и bleedingEdge, baseline `packages/<p>/phpstan-baseline.neon`; расширения — через `phpstan/extension-installer` и `extra.phpstan.includes` в `composer.json` core/modules/tenancy (без относительных путей к vendor — переживает split). Запуск из корня: `vendor/bin/phpstan analyse -c packages/<p>/phpstan.neon`.
3. **Ratchet (только сужается):** `scripts/quality-ceilings.json` хранит потолок на пакет: `phpstan.<dir>` (сумма `count` в baseline) и `dependencies.<dir>` (число ignore-записей с маркером `ratchet:`). `scripts/quality-ceiling-guard.php`: факт > потолка → exit 1; `--tighten` понижает потолок до факта; повышение потолка в диффе ловит anti-weakening gate. Запрет baseline в anti-weakening заменяется этим правилом.
4. **Dependency analyser:** на каждый пакет в его standalone-установке (`composer --working-dir=packages/<p> update`), конфиг `packages/<p>/composer-dependency-analyser.php`; shadow-зависимость исправляется объявлением в P01, архитектурные нарушения (напр. notifications→wakeup, P07) — ignore с `// ratchet: <фаза> <причина>`.
5. **PostgreSQL:** поддержка 16+; CI: основной PG-лейн — матрица `16`, `18` (текущий мажор и новейший), пакетные PG-jobs — `18`; локально — системный PG 16 хоста (порт 5432) через `PGSQL_PORT=5432`.
6. **CI-матрица:** PHP `8.4`, `8.5` × Laravel `12.*`, `13.*`; PHP 8.3 и шаг «Boxed profile» удаляются. Roave остаётся report-only до `1.0.0-rc.1` (D17, P09).

## Почему

- Pest 5 требует PHP 8.4 и PHPUnit 13; Testbench 10.12 и 11.3 принимают PHPUnit 13 — Pest 5 работает на L12 и L13; удалённых API PHPUnit 13 в тестах 0 (F4).
- Ratchet по счётчику имени-независим: P02 переносит файлы и перегенерирует baseline, потолок не может вырасти.
- PG 14 теряет поддержку 2026-11-12; 16 — установлен локально и в CI сейчас; 18 — актуальный мажор (http-02).
- Анализатор в standalone-установке — единственный способ проверить манифест, который получит split-репозиторий (pplx-01).

## Consequences

P01.1 — стек; P01.3 — per-package PHPStan, guard, анализатор; P01.4 — матрица и CI-подключение; P09 — пустые baseline и блокирующий Roave.
