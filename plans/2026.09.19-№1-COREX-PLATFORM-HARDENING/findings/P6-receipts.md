# P6 receipts

## P6.1 — Карта внутренних зон

- Создан нормативный документ `docs/architecture/core-zones.md` версии 1.0.0.
- Документ содержит восемь разделов: матрица, карта принадлежности, правила и исключения,
  обоснование Support/Data, цели P15, кандидаты уровней, не-обещания и enforcement.
- `find packages/core/src -type f -name '*.php' | wc -l` — **146**.
- Контрольная сумма карты — **146**: 141 файл в десяти зонах, 3 Composition, 2 Testing.
- Исходный baseline P6.1 в 109 файлов устарел после P4/P5: классифицированы все 37 новых
  файлов; строки не подгонялись и файлы не исключались.
- Публичные FQCN и PHP-код не изменялись. D32/D33 сохранены; `class_alias` не добавлялся.
- Раздел «не обещает» называет D32 и D33 явно: смена FQCN и вынос Testing из production-autoload в P6 не обещаны.
- `git diff --stat -- 'packages/'` — пусто для P6.1.
- `composer lint:check` — GREEN (`vendor/bin/pint --test`, exit 0).
- Обязательные маркеры `E-BASEMODEL`, `TenancyContracts`, `LITMUS_ELOQUENT`,
  `class_alias`, R-Z0/R-Z1/R-Z2/R-L3/R-T1 и `RT1_EXISTING` присутствуют.
- `git diff --check` для трёх declared files — GREEN.
- Повторная сверка 2026-09-22: каталоги §2.1 дают 89 файлов, поимённые строки §2.2 — 57,
  сумма 146 и совпадает с `find packages/core/src -name '*.php' | wc -l`.
  Число 109 в Validation — baseline дизайна; Implementation Rules требуют живой подсчёт.
- `composer lint:check` этой сессии — exit 0.
- `git diff --stat -- 'packages/'` показывает 37 ранее изменённых файлов чужого контура.
  P6.1 не меняет `packages/`.

## P6.2 — Arch-гейт зон

`tests/Arch/CoreZoneTest` кодирует матрицу §2 и карту P6.1. `PubSub/` ∈ Messaging
по пути; физический перенос не выполнен, D32. Целевой FQCN — таблица §4
`docs/architecture/core-zones.md`. P5.11 (`MessagingZoneTest`, R-M1/R-M2)
поглощён R-Z1/R-Z2. `ALLOWED['Foundation']` пуст. `CoreOptions`,
`PackageRegistrar` и `RegistersNothingOnBoot` — exact COMPOSITION; `ViolationList`,
`InvalidConfiguration`, `SecretRef` — Foundation. Файла `Support/CoreOptions.php`
в дереве нет, поэтому 12 cross-zone imports ещё не наблюдаются; путь уже в
закрытом списке COMPOSITION.

### Негативные посадки (дословный вывод коллекторов)

```
R-Z1 packages/core/src/Support/Data/RecordRef.php imports CoreX\Views\Internal\SqlSavedViewRepository
R-Z1 packages/core/src/Tenancy/AccountRef.php imports CoreX\Views\Internal\SqlSavedViewRepository
R-Z2 packages/core/src/Enums/ActorType.php imports CoreX\Views\Internal\SqlSavedViewRepository
R-Z0 packages/core/src/Support/Probe.php unclassified
```

После снятия посадок файлы восстановлены, `Support/Probe.php` удалён.
R-Z0, R-Z1 и R-Z2 на чистом дереве зелёные. Ложный `use CoreX\Audit\CurrentActor`
в `ImpersonationService` снят: тип был только в `{@see}` докблока.
Шесть публичных импортов закрыты D116 по паре «файл + символ».
`ALLOWED['Foundation']` пуст. Посадки выше остаются красными на время прогона
и зелёными после восстановления.

## P6.3 — Core Litmus Test

`tests/Arch/CoreLitmusTest` кодирует шесть условий A29 только для Foundation.
Список файлов — константа из 24 путей живой карты `docs/architecture/core-zones.md`
(baseline дизайна в 109 файлов снят P6.1). Это не дополнение «все остальные».
Условие 3 fail-closed (D34): импорт `Illuminate\Database\Eloquent`,
`Illuminate\Http` или `Illuminate\Foundation\Application` даёт R-L3.
Условие 1 ниже порога grep по `packages/*/src` — warning, не fail.
Ненаблюдаемая ссылка — `unresolved`, не fail. Условие 5 длиннее 200 строк
вне `Contracts` / `Enums` / `Data` — `unresolved` (P2 §7.1).

### LITMUS_ELOQUENT

```text
packages/core/src/Models/BaseModel.php
packages/core/src/Concerns/HasConfiguredId.php
packages/core/src/Support/Schema/IdColumns.php
```

Четвёртый путь требует запись в Decision Log.

### Негативная посадка R-L3

В `packages/core/src/Support/Data/RecordRef.php` на время прогона вставлен
`use Illuminate\Database\Eloquent\Model;`, затем файл восстановлен в `finally`.

```text
R-L3 packages/core/src/Support/Data/RecordRef.php imports Illuminate\Database\Eloquent\Model
```

Сообщение содержит `R-L3`, `RecordRef.php` и `Eloquent`. После снятия посадки
чистое дерево зелёное: `BaseModel` и `HasConfiguredId` остаются в allowlist.

Цитата Inputs у P6.4 свёрнута с каталога `packages/core/src/Departments`
на файл `packages/core/src/Departments/Department.php`, чтобы bundle
преемника резолвился. Смысл входа не менялся.

## P6.4 — Кандидаты Features, Departments и Money

`roadmap.md` §7 получил две строки из `research/P6-design.md` §10 дословно.
Строка `corex/money` требует внешнего или boxed-потребителя без `brick/money`
и оплаченный split. Строка Departments — уровень 1 (FlexCMS): второй продукт
без оргдерева либо расхождение смысла «отдел» между auth и CRM; до условия
зона остаётся в ядре. Пункт A30 не переписан: к нему добавлена ссылка
«Money — отдельная строка P6.4; Features/Settings пакетом не становятся».

`findings/P6-departments.md` называет трёх потребителей — ядро, auth и
tenancy — и `ScopePredicate`. Вынос не объявлен. PHP в `packages/` этим
item не менялся: `git diff --stat -- packages/` сохранил прежний чужой
контур (38 путей, sha256 stat
`3944b5d71a335f1944b96f4697e998c078f36410e150efb69bf5efaaac3c547e`).

`CHANGELOG.md` — одна строка `P6.4: roadmap candidates money package and
departments level-1`.

Цитата Inputs у P6.5 свёрнута с фразы «три пути RT1_EXISTING» на три файла
allowlist из §8: `CrmTestCase.php`, `CoreAuthTestCase.php`,
`TasksTestCase.php`. Смысл входа не менялся; bundle преемника резолвится.

## P6.5 — Testing: канонический autoload-dev и R-T1

`packages/core/composer.json` получил канонический `autoload-dev`
`CoreX\Testing\` → `src/Testing/`. Уже существовавший префикс
`CoreX\Testing\Fixtures\` → `tests/Fixtures/` сохранён: это не харнесс
`src/Testing`. Комментарий D33 лежит в `_comment` над `psr-4`, потому что
JSON не принимает `//`. Production `autoload.psr-4` `CoreX\` → `src/`
не сужен.

Production autoload сохранён, снятие — P16.

`RT1_EXISTING` — ровно три пути §8:

```text
packages/crm/src/Testing/CrmTestCase.php
packages/auth/src/Testing/CoreAuthTestCase.php
packages/tasks/src/Testing/TasksTestCase.php
```

Четвёртый импорт `use CoreX\Testing\` из `packages/*/src` — дефект
потребителя, не расширение константы. Снятие allowlist — P16.

### Негативная посадка R-T1

В `packages/core/src/Enums/ActorType.php` на время прогона вставлен
`use CoreX\Testing\CoreXTestCase;`, затем файл восстановлен в `finally`.

```text
R-T1 packages/core/src/Enums/ActorType.php imports CoreX\Testing\CoreXTestCase
```

Сообщение содержит `R-T1` и `ActorType.php`. После снятия чистое дерево
зелёное: три staged-потребителя остаются в allowlist.

`composer dump-autoload -o` — exit 0. `jq` на
`.autoload."psr-4"."CoreX\\"` печатает `src/`. `pest --testsuite=Arch` —
47 passed. `composer --working-dir=packages/core test` — 84 passed.
`composer lint:check` — exit 0. `bash scripts/anti-weakening-gate.sh` —
OK (`sqlite 546 -> 552`, `pg 508 -> 508`).

Цитата Inputs у P6.6 свёрнута с фразы «receipt'ы P6.1–P6.5» на
`findings/P6-receipts.md`, где эти блоки уже лежат. Смысл входа не
менялся; bundle преемника резолвится.

## P6.6 — Повторная квалификация границ зон и потребителей

`RAG:—` 2026-09-23, текущая независимая сессия `gpt-6-sol/medium` по
разовому owner-решению D117. P6.1–P6.5 исполнялись в других сессиях.

### Негативные посадки

Заново запущены focused Arch-тесты, которые временно вставляют импорт,
вызывают живой коллектор и восстанавливают исходные байты в `finally`.
Команда `vendor/bin/pest tests/Arch/CoreZoneTest.php
tests/Arch/CoreLitmusTest.php tests/Arch/TestingAutoloadTest.php
--filter='planting' --order-by=default` завершилась `exit 0`:
**6 passed, 23 assertions** (пять обязательных посадок и дополнительная R-Z0).

| Правило | Временный файл | Проверенное красное сообщение |
|:--|:--|:--|
| R-Z1 Foundation | `Support/Data/RecordRef.php` | `R-Z1`, `RecordRef.php`, `SqlSavedViewRepository` |
| R-Z1 TenancyContracts | `Tenancy/AccountRef.php` | `R-Z1`, `AccountRef.php`, `SqlSavedViewRepository` |
| R-Z2 | `Enums/ActorType.php` | `R-Z2`, `ActorType.php`, `SqlSavedViewRepository` |
| R-L3 | `Support/Data/RecordRef.php` | `R-L3`, `RecordRef.php`, `Eloquent` |
| R-T1 | `Enums/ActorType.php` | `R-T1`, `ActorType.php`, `CoreXTestCase` |

Полный focused прогон этих трёх файлов: **16 passed, 59 assertions**.
SHA256 трёх arch-файлов после прогона совпадает с P6.6 bundle:
`65c4935a…`, `2de57e31…`, `fee2e715…` соответственно. Посадочные
файлы `RecordRef.php`, `AccountRef.php`, `ActorType.php` отсутствуют в
`git status --short` после прогона. `LITMUS_ELOQUENT` остался из трёх путей.

### Свод команд

| Проверка | Результат |
|:--|:--|
| `composer package-graph-gate` | exit 0; ядро не приобрело зависимость на другой пакет CoreX |
| `composer --working-dir=packages/core test` | exit 0; 84 passed, 197 assertions |
| `composer --working-dir=packages/core test:pg` | exit 0; 37 passed, 180 assertions; база `corex_test` подтверждена preflight |
| `composer test:packages` | exit 0; все 20 пакетных команд прошли; tenancy: 472 passed, 7 skipped (native SDK требует отдельной изолированной конфигурации) |
| `composer test` | exit 0; 415 passed, 1335 assertions |
| `composer analyse` | exit 0; PHPStan 0 errors |
| `composer lint:check` | exit 0; Pint passed |
| `bash scripts/anti-weakening-gate.sh` | exit 0; sqlite 546 → 555, pg 508 → 508; OK |
| `git diff --check` | exit 0 |

`git diff --stat -- packages/ tests/` не пуст из-за существовавшего до
P6.6 чужого рабочего дерева. Item не менял продуктовые файлы: только
временные посадки с `finally`, и все их пути вернулись к исходному статусу.
`tests/Arch/CoreZoneTest.php` был untracked на входе и остался untracked;
его digest совпадает с входным bundle. Буквальное условие «общий diff пуст»
невыполнимо без уничтожения чужих изменений; item-local delta пуст.

### Именованные долги

- P7: `PrunePubSubMessages` использует `new DateTimeImmutable`; согласовать
  с Clock/Options. Оставшиеся `now()` вне P4.10 здесь не инъектируются.
- P9: DATA-003, запись ORM в таблицу другого пакета, остаётся вне P6.
- P15: целевые FQCN зон и `Views` → `SavedViews` остаются задачей P15;
  `PubSub/` относится к Messaging по пути, физического переноса нет (D32).
- P16: снять production-покрытие `src/Testing` только после миграции трёх
  `RT1_EXISTING` потребителей; N09 пока staged (D33).
- E-BASEMODEL остаётся одним Foundation → Audit исключением; новые пакеты,
  DATA-003 и Deptrac/PHPat/Pest Arch этой фазой не обещаны (§13).

Общий `plan-lint` на этом рабочем дереве красный по 53 ранее существовавшим
ошибкам носителей P1–P6 (в том числе старые `write-site` в state и
`read-path-v1` P6.2). Эти ошибки не устранялись внутри Files P6.6.
