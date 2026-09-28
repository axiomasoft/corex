# P01 — предвычисленные факты репозитория (RAG:—)

Снято 2026-09-28 на `2b77ba4` (main, чистое дерево) из корня репозитория. Каждый блок: команда → сырой вывод.
Исполнитель перепроверяет только блоки, помеченные «перепроверить на старте» (окружение меняется владельцем).

## F1. Окружение хоста (перепроверить на старте)

```bash
php -v | head -1; ls /usr/bin/php*; docker info >/dev/null 2>&1 && echo DOCKER_OK || echo DOCKER_NO
```
```text
PHP 8.3.33 (cli) (built: Jul 30 2026 15:39:15) (NTS)
/usr/bin/php  /usr/bin/php8.1  /usr/bin/php8.3  /usr/bin/php8.4
DOCKER_NO
```

```bash
php8.4 -v | head -1
for e in mbstring xml dom curl intl pdo_sqlite sqlite3 bcmath zip gmp pcov pdo_pgsql pgsql gd; do php8.4 -m | grep -qix "$e" || printf "MISSING:%s " $e; done
```
```text
PHP 8.4.26 (cli) (built: Sep 24 2026 17:15:37) (NTS)
MISSING:xml MISSING:dom MISSING:intl MISSING:pdo_sqlite MISSING:sqlite3 MISSING:bcmath MISSING:zip MISSING:gmp MISSING:pcov MISSING:gd
```
(владелец доустановил `php8.4-pgsql` в ходе дизайна: `pdo_pgsql`, `pgsql`, `mbstring`, `curl` уже есть.) Все недостающие
пакеты доступны в apt (`ondrej/php`, кандидат `8.4.26-1+ubuntu24.04.1+deb.sury.org+1`, `php8.4-pcov 1.0.12`).

```bash
pg_isready -h 127.0.0.1 -p 5432; PGPASSWORD=corex psql -h 127.0.0.1 -U corex -d postgres -tAc 'select 1'
```
```text
127.0.0.1:5432 - accepting connections           # системный PostgreSQL 16 (/etc/postgresql/16)
FATAL:  password authentication failed for user "corex"
```
`phpunit.xml` задаёт `PGSQL_PORT=5434` (docker-compose), `<env>` без `force` → переопределяется переменной окружения.

## F2. Манифесты (перепроверять не нужно — меняются только P01.1)

Все 21 `packages/*/composer.json`: `php ^8.3` (кроме tenancy `^8.4`), `minimum-stability: dev`, межпакетные ссылки
`@dev`, нет `extra.branch-alias`. `corex/access`: `illuminate/* ^12.0`, `orchestra/testbench ^10.0`,
`axioma-studio/azguard-core ^0.2@dev` + VCS. Root: `php ^8.3`, `minimum-stability: dev`, `prefer-stable: true`,
`pestphp/pest ^4.0`, `orchestra/testbench ^10.0|^11.0`, `laravel/pint ^1.13`, `rector ^2.0`, `phpstan ^2.0`, `larastan ^3.0`.
`composer.lock`: laravel/framework v13.32.0, pest v4.7.5, phpunit 12.5.30, testbench v11.1.0, larastan v3.10.0,
phpstan 2.2.6, rector 2.5.8, pint v1.29.3, `platform.php ^8.3`.

```bash
composer validate --strict --no-check-lock   # root
```
```text
- require.corex/<16 пакетов> : unbound version constraints (@dev) should be avoided   → exit ≠ 0
```

Транзитивное замыкание монорепо-пакетов, которых нет в require/require-dev данного корня (скрипт — досье §2.3):
```text
ROOT                 missing-root-reqs=['corex/recurrence', 'corex/search']
modules              missing-path-repos=['core']
search-intl          missing-root-reqs=['corex/modules'] missing-path-repos=['modules']
tenancy              missing-root-reqs=['corex/modules']
остальные 17         —
```

## F3. Прототип `branch-alias` + `^1.0@dev` + `minimum-stability: stable` (Composer 2, scratch)

Два path-пакета `x/a`, `x/b` (`extra.branch-alias.dev-main = 1.0.x-dev`), `x/b` require `x/a ^1.0@dev`:
```text
root require x/a + x/b ^1.0@dev, stable      → Locking x/a (dev-main), x/b (dev-main); validate --strict: valid
packages/b standalone, stable                → Locking x/a (dev-main); validate --strict: valid
root require только x/b (x/a транзитивно)    → Problem: x/b dev-main requires x/a ^1.0@dev -> found x/a[...] but it does not match your minimum-stability.
```
Вывод: каждый installable корень перечисляет **всё** транзитивное замыкание монорепо-пакетов с `@dev`
(флаг стабильности действует только в корне).

## F4. PHPUnit 13 removals в тестах

```bash
for pat in 'isType(' 'assertContainsOnly(' 'assertNotContainsOnly(' 'containsOnly(' 'RunClassInSeparateProcess' 'CoversNothing' '->any()' 'createStub(' 'withConsecutive'; do grep -rlF -- "$pat" tests packages/*/tests | wc -l; done
```
```text
0 во всех девяти шаблонах; 463 php-файла тестов
```
`executionOrder`: root `default`, access/tasks `random` (не deprecated в 13.2).

## F5. CI

```bash
grep -hoE "uses: [^ ]+" .github/workflows/*.yml | sort | uniq -c
```
```text
32 uses: actions/checkout@v4
28 uses: shivammathur/setup-php@v2
```
`ci.yml` (691 строк, 20 jobs): `tests` matrix php `8.3/8.4/8.5` × laravel `12.*/13.*` + шаг «Boxed profile (drop 8.4-only
tenancy)» для 8.3; PG-лейны на `postgres:16`; Roave job «experimental report-only» (`ci.yml:549`); `permissions` нет в
`ci.yml`/`distribution-proof.yml`, есть в `remaining-cohort-proof.yml`. Каждый install-шаг предваряет
`python3 scripts/distribution/stage-stancl-providers.py` (регистрирует `file://`-репозиторий в root и tenancy
`composer.json` — только CI-эфемерно). `composer audit` нигде нет. Нет `.github/dependabot.yml`, `CODEOWNERS`.
Remote `https://github.com/axiomasoft/corex.git`; `gh api user` → `academici`.

## F6. Существующие гейты, которых касается P01

| Гейт | Факт | Судьба в P01 |
|:--|:--|:--|
| `scripts/anti-weakening-gate.sh:71–76` | запрещает `baseline` в `phpstan.neon` и новые `*baseline.neon` | P01.3: заменить запрет правилом ceiling (досье §4.3) |
| `scripts/phpstan-contour.php` | пишет в артефакты PLAN2, синхронизирует paths root `phpstan.neon` | P01.3: удалить вместе с `analyse:packages` |
| `phpstan.neon` | level 6, 24 paths, 6 ignoreErrors intl/search-intl, includes core/modules/tenancy `extension.neon` | P01.3: агрегат заменён per-package |
| `scripts/check-api-contract.php` + `api/v1/*` | intra-major сравнение wire-снимков с committed baseline | не меняется; golden P01.2 — отдельный слой |
| `corex:api-snapshots {dir} [--check] [--fixture=]` | `packages/operations/src/OpenApi/Commands/WriteApiSnapshots.php:19` | golden P01.2 вызывает генератор |
| `scripts/table-ownership-gate.php` | T1/T2/T4 по `docs/architecture/table-ownership.md` | не меняется; ownership v2 читает тот же каталог |
| `scripts/package-graph-gate.php` R3 | владение namespace по `extra.corex` | не меняется; ownership v2 — поверх |
| `DomainEvent::name()` | 16 классов `extends DomainEvent` в `packages/*/src` | golden events |
