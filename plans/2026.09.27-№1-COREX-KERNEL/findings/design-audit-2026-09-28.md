# PLAN3 — design audit P00–P01 — 2026-09-28

РЕЖИМ: независимый design audit; read-only над дизайном и продуктом, запись только результатов аудита.
ВХОД: завершённый `finalize-design --finish`, fingerprint `1f1ad5233e42abd67cea016617880637728cfadb095fd62eba1fec594f81c027`.
СКОУП: детализированные фазы P00 и P01 (7 items), их зависимости и строки execution-sheet. Дельта риска — P01 и finish-правки `plan.md` / `roadmap.md` / `phases/P01/P01.md`. Контракты P00.1–P00.3, `research/P00-design.md`, `research/P00-design-rag.md` и `artifacts/P00-design/examples.md` с коммита GREEN-аудита `5893436` не менялись.
НЕ ТРОГАТЬ: продукт, нормативные спецификации и решения, PLAN2. Исполнение P01 не начато.

Вердикт: **ATTENTION** (`audit-attention`): 1 Blocker, 2 Major. Текущий дизайн не получает GREEN.

Автор: `cursor/grok-4.7/high`, независим от автора дизайна P01 `claude-opus-5-5/high` и от finish `codex/gpt-6-astra/high`.
Provider session id этой сессии не захвачен. Checkout: рабочее дерево поверх `9a1b2a8` с незакоммиченным finish. Выводы ниже — `RAG:—`, кроме явно названных команд.

## B1 · Blocker · issue — golden сравнивает текущий код с уже переименованным baseline

**Место:** [`artifacts/P01-design/examples.md`](../artifacts/P01-design/examples.md) §3.3 (запись baseline и сравнение);
[`decisions/D38-golden-rename-normalizer.md`](../decisions/D38-golden-rename-normalizer.md) п.2;
[`phases/P01/P01.2.md`](../phases/P01/P01.2.md) Validation.

D38 задаёт `normalize(apply(rename-map, baseline)) == normalize(current)`.
`GOLDEN_UPDATE=1` записывает в baseline снимок **текущего** кода, без применения карты.
`RenameMap::apply` затем переписывает в этом снимке каждый точный FQCN `from → to`.
На P01 код ещё носит старые имена, а генератор уже содержит 666 правил `final != current`.

Воспроизведение из корня репозитория (файл карты не пишется):

```bash
python3 plans/2026.09.27-№1-COREX-KERNEL/findings/design-audit-2026-09-28-repro.py
```

Первое class-правило: `CoreX\Api\ApiController` → `CoreX\Operations\Http\ApiController`.
После `apply` ключ baseline — новое имя, ключ current — старое. Равенство ложно.
При этом Validation P01.2 требует `scripts/php84 composer golden` → `Tests: 4 passed` до каких-либо переносов P02.
Исполнитель, скопировавший скелет, не может закрыть item. Сетка, которая должна быть зелёной перед P02, красная на коде P01 и становится осмысленной только после переименований, которые сама же должна стеречь.

**Исправление дизайна:** зафиксировать одну проекцию, которая зелёная и до переноса, и после него. Рабочий вид: при записи и при сравнении применять карту к **обеим** сторонам (baseline хранит уже спроецированный снимок, current тоже проходит через `apply`). Префиксная замена по-прежнему запрещена.
**Owning continuation:** дизайн P01 / P01.2 и D38. Evidence: JSON `B1`.

## B2 · Major · issue — скелет php-surface не содержит полей, которые требует досье

**Место:** [`research/P01-design.md`](../research/P01-design.md) §4.2 п.2 (`constants` name→value-type, `properties`, `params.default?`);
[`artifacts/P01-design/examples.md`](../artifacts/P01-design/examples.md) вступление и §3.2 `phpSurface()`;
[`phases/P01/P01.2.md`](../phases/P01/P01.2.md) Code Guidance.

Досье перечисляет публичные properties (`name`, `type`, `static`, `readonly`) и константы как `name→value-type`, у параметров — `default?`.
Скелет `phpSurface()` свойства не собирает, константы сводит к списку имён, у параметра хранит `optional` вместо значения по умолчанию.
Тот же файл примеров разрешает исполнителю менять скелет только в местах `ADAPT`. Code Guidance указывает копировать этот скелет.
Следование скелету выпускает golden, который не видит смену типа публичного свойства или значения константы при P02/P03. Следование досье нарушает правило «только ADAPT».

**Исправление дизайна:** привести скелет к списку полей досье и пометить недостающие поля как обязательные, не как свободную адаптацию.
**Owning continuation:** дизайн P01 / P01.2. Evidence: JSON `B2`.

## B3 · Major · issue — Validation-команды завершаются exit 1 на корректном дереве

**Место:** [`phases/P01/P01.1.md`](../phases/P01/P01.1.md) Validation (`grep -c stancl-providers`, `grep -rnE` PHP 8.3);
[`phases/P01/P01.3.md`](../phases/P01/P01.3.md) Validation (`grep -rn` contour);
[`phases/P01/P01.4.md`](../phases/P01/P01.4.md) Validation и [`research/P01-design.md`](../research/P01-design.md) §4.4 шаг 2 (pinact `grep -vcE`).

Это тот же класс, что закрытый A3: носитель приёмки на правильном результате печатает ожидаемый текст и возвращает exit 1, потому что `grep` без совпадений (и `grep -c`/`-vc` при счёте 0) завершается 1.

Измерено:

| Команда | stdout | exit |
|:--|:--|:--|
| `grep -c stancl-providers composer.json packages/tenancy/composer.json` | `composer.json:0`, `packages/tenancy/composer.json:0` | 1 |
| полностью запиненный `uses:` \| `grep -vcE '@[0-9a-f]{40} # v?[0-9]'` | `0` | 1 |
| `grep -nE "'8\\.3'\|Boxed profile"` по строке без этих токенов | пусто | 1 |

`grep -L '^permissions: {}'` при совпадении во всех файлах даёт пустой stdout и exit 0; эта команда в finding не входит.

**Исправление дизайна:** для каждого такого носителя задать команду с exit 0 на корректном дереве и exit 1 при нарушении (отдельная проверка счёта/пустоты, не голый `grep`).
**Owning continuation:** дизайн P01 / P01.1, P01.3, P01.4. Evidence: JSON `B3`.

## Покрытие и проверки

- `design-gate` до записи этого аудита: `PLAN_DESIGN_AUDIT_MISSING`, fingerprint совпал с finish.
- Routing compiler: P01.1 `solo` implementation/high full; B01-B = P01.2 → P01.3 implementation/high full; P01.4 `solo` implementation/high full. Совпадает с тремя новыми строками execution-sheet. Reviewer `cursor/grok-4.7/high` ≠ executor `codex/gpt-6-sol/high`.
- P00: specs и досье/примеры не менялись с `5893436`. В fingerprint добавлен только Phase Handoff закрытия в `phases/P00/P00.md` (статус и указатели на карты). Прежний GREEN A1–A5 на `e8524b9f64353b897decff9a8246fa85c39ae888a7a244fefe4369c3979b40c2` переиспользован для контрактов P00.1–P00.3. B00-A уже закрыт исполнением.
- P02–P09 остаются backlog по D24.
- `golden-rename-map.py` build() без записи: `666 class, 16 package, 3 ambiguous` — заявленные числа генератора верны; дефект B1 в том, как тест эти правила применяет.
- Сырой вывод — [`design-audit-2026-09-28-evidence.json`](design-audit-2026-09-28-evidence.json).

Границы: PHP/DB-тесты и `plan-lint` продукта не запускались; lint finish уже был exit 0 и не опровергает B1–B3. Внешние версии стека (E1–E7) заново не сверялись: findings от них не зависят. Нет утверждения о полноте дизайна P02–P09.

## Продолжение

Владелец исправления — дизайн P01: устранить B1–B3, затем `finish` и design audit нового fingerprint.
Повторная оценка проверяет изменённые риски; неизменённые контракты P00 переиспользуются.
Текущая команда заканчивается артефактом аудита. Исполнение P01.1 не допускается, пока design-gate этого fingerprint не GREEN.

`validate_design_handoff_target(P01)` возвращает `PLAN_DESIGN_NOT_NEEDED`, потому что items уже детализированы. Машинный Next — `manual` с заданием исправить дизайн. Это не допуск `plan-run`.
