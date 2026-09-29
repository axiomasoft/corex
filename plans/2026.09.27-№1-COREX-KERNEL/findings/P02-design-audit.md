# PLAN3 — design audit P00–P02 — 2026-09-28

РЕЖИМ: независимый аудит дизайна, запись только audit-owned evidence и handoff.
ВХОД: `finalize-design --finish`, fingerprint `83f9a46c53c7e2b70d55bddf263bf3d179e66f728cbe93a1201ec9f9d9ffed4d`, HEAD `dfcd5b8`.
СКОУП: P00–P02, 11 item-контрактов, зависимости и 7 строк execution-sheet; новый риск — P02.
Автор P02: claude-opus-5-5/high. Аудитор: codex/gpt-6-sol/high, независимая сессия.

Вердикт: **ATTENTION — 4 Major** (`audit-attention`). Текущий fingerprint не получает GREEN.
Это дефекты готовности дизайна; P02 не исполнялась, статусы её items не меняются.

## A1 — Major / issue: переименование config-файлов оставляет провайдеры со старым путём, обе проверки дают GREEN

**Место:** [`p02-identifiers.py`](../artifacts/P02-design/scripts/p02-identifiers.py), строки 16, 49–60, 70–80;
[`rename-completeness-gate.php`](../artifacts/P02-design/scripts/rename-completeness-gate.php), `$wordPattern`;
[`P02.4.md`](../phases/P02/P02.4.md), Scope Included, Implementation Rules, Validation.

`BEFORE = (?<![\w./@:-])` исключает совпадение после `/`. `apply` делает `git mv corex-auth.php → corex-access.php`,
но в фактическом `CoreAuthServiceProvider` меняет только ключ:

```php
$this->mergeConfigFrom(__DIR__.'/../config/corex-auth.php', 'corex-access');
```

Путь теперь указывает на отсутствующий файл. Аналогичные исходные пути есть у providers operations,
attributes, search-intl, tasks-workflow. `check` использует ту же границу; PHP-гейт повторяет её.

**Доказательство:** A1 в [`reproductions.json`](../artifacts/P02-design-audit/reproductions.json): настоящий `apply`
в отдельном git-репозитории переименовал все пять config-файлов; старый путь провайдера сохранился;
`identifiers check: 0 remaining` и `rename-completeness OK` вернули exit 0.

**Последствие:** boot/publish провайдера не может загрузить объявленный конфиг; проверки полноты не обнаруживают дефект.
**Принятое требование:** D45 §1, P02.4 Intent/Validation — пять config-ренеймов и 0 старых имён.
**Владелец:** дизайн P02.4. Предусмотреть отдельное точное преобразование ссылок на config-файлы и проверку этих путей
в обоих гейтах; негативный контроль должен оставить старый путь, обнаружить его и проверить boot после `git mv`.

## A2 — Major / issue: перенос оставляет classmap удалённого пакета modules

**Место:** [`p02-transfer.py`](../artifacts/P02-design/scripts/p02-transfer.py), `literal_rules`, `rewrite_text`,
`manifests` (root autoload-dev); [`examples.md`](../artifacts/P02-design/examples.md) §4.2;
[`P02-design.md`](../research/P02-design.md) §4.3 шаги 5–7.

У корневого `composer.json` есть `autoload-dev.classmap = …, packages/modules/tests, …`.
В таблице `dir_renames` merge modules отсутствует; точные пути отдельных PHP-файлов не совпадают с путём каталога.
`manifests()` переписывает корневой `autoload-dev.psr-4`, но не `classmap`.
После переноса тестов и удаления пустых каталогов этот путь перестаёт существовать. Runbook/examples не задают его замену.

**Доказательство:** A2 в `reproductions.json`: реальный `build_plan`/`literal_rules`/`rewrite_text` сохраняет
`packages/modules/tests`; Composer `dump-autoload --dev --no-scripts --no-plugins` в минимальной отдельной фикстуре
возвращает exit 1: `Could not scan for classes inside "packages/modules/tests"`.
В существующем монорепо dev-режим — обычный режим установленного тестового стека; `--dev` лишь делает фикстуру явной.

**Последствие:** шаг `composer update`/`dump-autoload` и запуск тестов прерываются до проверок переноса.
**Принятое требование:** D44 §2–3; P02.3 Validation — работающий Composer/autoload и зелёный сухой прогон.
**Владелец:** дизайн P02.3. Добавить явную проекцию каталожных autoload-путей при merge и пример итогового classmap;
проверять существование всех autoload-путей после переноса. Не обходить ошибку созданием старого пустого каталога.

## A3 — Major / issue: обязательное чтение P02.4 исчезает в результате P02.3

**Место:** [`P02.4.md`](../phases/P02/P02.4.md), Required Reads №7 (строка 33);
[`extra-moves.tsv`](../artifacts/P02-design/facts/extra-moves.tsv), первая строка move.

P02.3 выполняет `git mv packages/modules/config/corex-modules.php packages/core/config/corex-modules.php`.
P02.4 затем требует старый путь `packages/modules/config/corex-modules.php`.
Скобка «P02.3 переносит его в packages/core/config/» описывает перенос, но не меняет обязательный путь чтения.

**Доказательство:** A3 в `reproductions.json` сопоставляет фактический `extra_moves` с `Required Reads`.
Это детерминированное следствие `git mv`, без запуска всей фазы.
**Последствие:** executor/bundle для P02.4 получает отсутствующий required input и обязан fail closed.
**Принятое требование:** plan-protocol §1 — «Follow Required Reads … fail closed on a missing required input»;
P02 Phase Context — строго P02.3 → P02.4 с потреблением результата предшественника.
**Владелец:** дизайн P02.4. Указать новый путь `packages/core/config/corex-modules.php` и producer P02.3;
проверить Required Reads/bundle на дереве после переноса.

## A4 — Major / issue: Validation запрещает необходимые переименования кода Golden

**Место:** [`P02.3.md`](../phases/P02/P02.3.md), Validation, строка 55:
`git diff --exit-code tests/Golden/` должен быть пустым.

Сьюта касаются обязательные переименования: `GoldenSnapshot` использует `CoreX\Events\DomainEvent`,
`GoldenTestCase` — `CoreX\Auth`, `CoreX\Attributes`, `CoreX\Api`, `CoreX\Search\Meilisearch` и другие старые FQCN.
Rector P02.3 обрабатывает `tests/`, поэтому корректный перенос меняет эти файлы.
Запрет всего `tests/Golden/` шире принятого запрета rebaseline и изменения rename-map.

**Доказательство:** A4 в `reproductions.json`: установленный Rector 2.6.7 с настоящим `RenameClassRector`
перенёс ссылку `DomainEvent::class` в копии `GoldenSnapshot.php` по существующей карте P00.
Rector exit 0; frozen baseline не менялся; заявленный `git diff --exit-code tests/Golden/` вернул exit 1.

**Последствие:** правильный перенос не может выполнить собственный критерий закрытия P02.3.
**Принятое требование:** D44 §5 и P02.3 Implementation Rules — Golden без rebaseline, неизменный rename-map,
при этом все ссылки на старые FQCN должны быть исправлены (D01).
**Владелец:** дизайн P02.3. Ограничить проверку неизменности `tests/Golden/baseline/` и `tests/Golden/rename-map.json`;
разрешить необходимую проекцию кода сьюта и сохранить `composer golden` 4 passed.

## Покрытие и ограничения

- `design-gate`: совпавший finish/fingerprint; до аудита `PLAN_DESIGN_AUDIT_MISSING`.
- `plan-lint`: 1 план, 0 ошибок, 0 предупреждений.
- Карты: `maps OK: 558 prefixes, 21 packages`; зависимости: 1756 edges, acyclic, kernel-independent.
- Компилятор Routing, все 7 строк execution-sheet и supporting artifacts P00–P02 прошли соответствующие Task-проверки.
- Все 7 исполненных spec P00–P01 совпадают с `spec_sha256` их GREEN terminal evidence.
  Их продуктовые тесты и фазовый аудит повторно не выполнялись. P02 — четыре `Not started`.
- Воспроизведения выполнялись только в временных git-фикстурах. Полный перенос и suite после P02.1/P02.2
  не запускались: швы ещё не реализованы. Сохранённый F5 использует `--no-rector --allow-seams` и не доказывает готовность полного переноса.
- `php84 --check`: PHP 8.4.26, необходимые расширения доступны.
- E1 сверялся с установленным исходником Rector 2.6.7 и [официальной страницей правила](https://getrector.com/rule-detail/rename-class-rector).
  На проверенном reference правило меняет ссылки/PHPDoc, а объявление namespace остаётся задачей скрипта.
  Это не заменяет полный сухой прогон P02.3.
- Аудитор фактически `implementation/high`, тогда как adapter рекомендует `frontier/xhigh`, handoff — `frontier/high`.
  Отклонение явно записано в journal и `route.json`; frontier assurance не заявляется. Другая модель относительно автора P02 соблюдена.
- O-3 из P01 остаётся отдельным owner gate. Новый hosted CI, billing и push этим аудитом не проверялись.

Evidence: [`coverage.json`](../artifacts/P02-design-audit/coverage.json), [`design-snapshot.json`](../artifacts/P02-design-audit/design-snapshot.json),
[`route.json`](../artifacts/P02-design-audit/route.json), [`product-anchor.json`](../artifacts/P02-design-audit/product-anchor.json),
[`reproduce.py`](../artifacts/P02-design-audit/reproduce.py), [`reproductions.json`](../artifacts/P02-design-audit/reproductions.json).

Повторить из корня:

```bash
python3 plans/2026.09.27-№1-COREX-KERNEL/artifacts/P02-design-audit/reproduce.py
```

## Owning continuation

Исправить дизайн P02.3/P02.4 ровно по A1–A4: инструменты, обязательные чтения, Validation, соответствующие примеры/runbooks.
Затем `task:plan-design … finish` и независимый design audit нового fingerprint. Исполнение P02 остаётся за GREEN и O-3.

`validate_design_handoff_target(P02)` не допускает автоматический `plan-design` Next для уже детализированной незавершённой фазы.
Поэтому машинный Next — `manual: frontier/high` с точным заданием исправить дизайн; это не допуск `plan-run`.
Текущий audit scope завершён; continuity `continue-root`, без обязательной новой сессии.
