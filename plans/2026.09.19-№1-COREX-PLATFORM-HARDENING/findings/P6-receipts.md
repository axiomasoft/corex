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
