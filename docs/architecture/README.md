# Каталог `docs/architecture/`

Нормативный слой CoreX уровня 0: инварианты, политики, референсы и карта того, что уже
исполняется машиной.

## Состав каталога

| Файл | Что owns | Как читать |
|:--|:--|:--|
| `constitution.md` | Реестр нормативных статей: инвариант + владелец + enforcement + исключения | Сначала; статья по ID |
| `taxonomy.md` | Схема `extra.corex`, закрытые наборы `package-type`/`stability` | При смене метаданных пакета |
| `naming.md` | Словарь суффиксов и правила имён | При именовании классов и полей |
| `rename-registry.md` | Реестр «сейчас → целевое → фаза → условие» | Перед любым переименованием |
| `structure-profiles.md` | Раскладка корневых и feature modules, процедуры STR-002…STR-006 | При реорганизации `src/` |
| `api-policy.md` | Публичная поверхность, BC, депрекация | Рядом со статьями API-* |
| `config-policy.md` | Классификация конфигурации и ввод ключей | Рядом со статьями CFG-* |
| [schema-policy.md](schema-policy.md) | Наборы, scope, исполнение и форма схемы | Рядом со статьями DATA-005 и DATA-007 |
| `data-ownership-policy.md` | Владение таблицами и исключения FK | Рядом со статьями DATA-* |
| `message-policy.md` | Конверт, именование и версии сообщений | Рядом со статьями MSG-* |
| `extension-policy.md` | Реестры и lifecycle расширений | Рядом со статьями EXT-* |
| [ecosystem-dependencies.md](ecosystem-dependencies.md) | Зафиксированные внешние зависимости и установка development-снимков | Перед установкой и подготовкой CI |
| `references.md` | Внешние референсы с конкретным механизмом | При обосновании заимствования |
| `README.md` | Этот индекс и сводный реестр enforcement | Точка входа после доктрины |

Вне каталога: `docs/rejected-optimizations.md` — отклонённые решения с условием возврата.

### Исключения и особые пакеты

- Каталог `exceptions/` — именованные исключения из статей конституции; каждая запись несёт
  условие снятия.
- `packages/import-export` — действующий `axiomasoft/corex-import-export`; историческая
  статья PKG-009 о пустом каталоге отменена.

## Сводный реестр правил

Исторически на закрытие P1 машинно исполнялись **13** из **58** статей (гейты `composer test:arch`,
`composer package-graph-gate`, `composer test:packages`, `scripts/contract-guard.sh`, проверка
диффа миграций в validation code-item'ов). Текущая структурная норма — PLAN3.D57/D58; гейт раскладки включён в CI,
остаточные нарушения ограничены `scripts/module-layout-baseline.json`. Остальные статьи
несут состояние enforcement в таблице ниже.

### Статьи конституции

| ID | Группа | Enforcement | Статус enforcement |
|:--|:--|:--|:--|
| PKG-001 | PKG | Нет — P3 (гейт scorecard'а читает первую строку README и падает на пустой либо на перечислении). … | объявлено, фаза P3 |
| PKG-002 | PKG | `composer test:arch` → `tests/Arch/LayerTest.php` («foundation packages do not import flex/* name… | исполняется |
| PKG-003 | PKG | `composer test:arch` → «axiomasoft/corex declares no monorepo package dependencies», «axiomasoft/corex source… | исполняется |
| PKG-004 | PKG | `composer package-graph-gate` → правило R1, на каждый PR | исполняется |
| PKG-005 | PKG | `composer package-graph-gate` → allowlist с валидацией guard'а | исполняется |
| PKG-006 | PKG | `composer package-graph-gate` → R2 плюс ревью `Contracts/` bridge-пакета | исполняется |
| PKG-007 | PKG | `composer test:arch` → «foundation packages carry no flex product literals» | исполняется |
| PKG-008 | PKG | `composer test:packages` (пакеты и продукты с `phpunit.xml`) | исполняется |
| PKG-009 | PKG | Историческая статья о пустом каталоге | отменена |
| STR-002 | STR | `composer module-layout-gate`; ревью принадлежности use case | исполняется (структурная часть) |
| STR-003 | STR | `composer module-layout-gate` → R0–R3 | исполняется, ratchet-baseline |
| STR-004 | STR | нет — ревью | объявлено, фаза P1 |
| STR-005 | STR | `composer package-graph-gate` → R2; `composer module-layout-gate` → R4/R5 | исполняется, ratchet-baseline |
| STR-006 | STR | `composer module-layout-gate` → R1–R3 | исполняется, ratchet-baseline |
| API-001 | API | нет — P3 (правило R4 гейта графа) | объявлено, фаза P3 |
| API-002 | API | `composer package-graph-gate` → R2/R4 (не PHPStan, см. D12) | исполняется |
| API-003 | API | нет — P3 (R4) | объявлено, фаза P3 |
| API-004 | API | `composer package-graph-gate` → R2 | исполняется |
| API-005 | API | нет — P3 (BC-дифф), P17 (условие тега) | объявлено, фаза P3 |
| API-006 | API | нет — P3/P13 | объявлено, фаза P3 |
| API-007 | API | `scripts/contract-guard.sh` (warn-прокси на диффе `src/Contracts/**`) | исполняется |
| API-008 | API | нет — P13 (snapshot), P3 (BC-дифф) | объявлено, фаза P13 |
| API-009 | API | ревью + `scripts/contract-guard.sh` | исполняется |
| DATA-001 | DATA | `composer table-ownership-gate`; `composer schema-gate` → S9 | исполняется |
| DATA-002 | DATA | `composer schema-gate` → S9; `composer table-ownership-gate`; ревью ALTER | исполняется (создание), ревью (ALTER) |
| DATA-003 | DATA | `composer table-write-ownership-gate` | исполняется |
| DATA-004 | DATA | `composer table-ownership-gate` → T4 | исполняется |
| DATA-005 | DATA | Golden-схема (DDL); `composer schema-gate` → S8 (baseline до rc); проверка сохранности файлов при выпуске | гейты исполняются; неизменность выпущенных файлов — с rc |
| DATA-006 | DATA | owner-gate фазы (G-DDL) | объявлено, фаза P1 |
| DATA-007 | DATA | `composer schema-gate` → S1–S9; Golden-схема; ревью формы | исполняется (гейт и Golden), ревью |
| RUN-001 | RUN | нет — P4 | объявлено, фаза P4 |
| RUN-002 | RUN | нет — P3 (гейт сериализуемости), P4 | объявлено, фаза P3 |
| RUN-003 | RUN | нет — P3 | объявлено, фаза P3 |
| RUN-004 | RUN | `composer runtime-globals-gate` → G2/G3 | исполняется |
| RUN-005 | RUN | `composer runtime-globals-gate` → G4; `tests/Arch/SingletonInjectionTest.php`; `ResidentLifecycleTest`; `ResidentTenantSwitchPgTest` | исполняется |
| RUN-006 | RUN | `packages/tenancy/tests/Pg/ScopeBoundaryPgTest.php`; `tests/Arch/SingletonInjectionTest.php` | исполняется |
| CFG-001 | CFG | нет — P2 (реестр), P7 | объявлено, фаза P2 |
| CFG-002 | CFG | `composer config-composition-gate` → C1–C4 | исполняется |
| CFG-003 | CFG | `tests/Arch/OptionsSingletonTest.php`; `composer configuration-reference` | исполняется |
| CFG-004 | CFG | `RuntimeConfigGate`; `composer config-cache-check` | исполняется |
| CFG-005 | CFG | `RuntimeConfigGate`; `composer configuration-reference` (`***`) | исполняется |
| CFG-006 | CFG | ревью | объявлено, фаза P1 |
| MSG-001 | MSG | нет — P5 | объявлено, фаза P5 |
| MSG-002 | MSG | нет — P5 | объявлено, фаза P5 |
| MSG-003 | MSG | нет — P5 | объявлено, фаза P5 |
| MSG-004 | MSG | нет — P5 | объявлено, фаза P5 |
| MSG-005 | MSG | нет — P3 | объявлено, фаза P3 |
| MSG-006 | MSG | нет — P5 | объявлено, фаза P5 |
| MSG-007 | MSG | нет — P5 | объявлено, фаза P5 |
| MSG-008 | MSG | нет — P5 (outbox) | объявлено, фаза P5 |
| EXT-001 | EXT | `packages/core/src/Modules/Contracts/Extender.php` (докблок закрытого набора) + ревью | объявлено, фаза P1 |
| EXT-002 | EXT | ревью | объявлено, фаза P1 |
| EXT-003 | EXT | нет — P11 | объявлено, фаза P11 |
| EXT-004 | EXT | нет — P11 | объявлено, фаза P11 |
| EXT-005 | EXT | нет — P11 | объявлено, фаза P11 |
| EXT-006 | EXT | нет — P11 | объявлено, фаза P11 |
| EXT-007 | EXT | нет — P11 | объявлено, фаза P11 |
| EXT-008 | EXT | ревью | объявлено, фаза P1 |
| EXT-009 | EXT | ревью | объявлено, фаза P1 |

### Анти-цели

| ID | Группа | Enforcement | Статус enforcement |
|:--|:--|:--|:--|
| NON-001 | NON | D1 | объявлено, фаза P1 |
| NON-002 | NON | новое решение при радикальном росте числа пакетов (A24) | объявлено, фаза P1 |
| NON-003 | NON | D8: отдельный release lifecycle либо внешний потребитель без остального ядра | объявлено, фаза P1 |
| NON-004 | NON | A45: имя закреплено ADR-011/ADR-014 и доктриной | объявлено, фаза P1 |
| NON-005 | NON | D2 | объявлено, фаза P1 |
| NON-006 | NON | следствие D2 | объявлено, фаза P1 |
| NON-007 | NON | D7 | объявлено, фаза P1 |
| NON-008 | NON | S34: измеренное dependency pressure | объявлено, фаза P1 |
| NON-009 | NON | D4 | объявлено, фаза P1 |
| NON-010 | NON | A85; допускается только правилом, которое существующий гейт выразить не может (Q5) | объявлено, фаза P1 |
| NON-011 | NON | решение фазы, вводящей зависимость; dev-зависимость — только названная в решении | объявлено, фаза P1 |
| NON-012 | NON | A52: требование уже выполнено | объявлено, фаза P1 |
| NON-013 | NON | Q4 в P14 | объявлено, фаза P1 |
| NON-014 | NON | S39 | объявлено, фаза P1 |
| NON-015 | NON | S39 | объявлено, фаза P1 |
| NON-016 | NON | RUN-001 | объявлено, фаза P1 |
| NON-017 | NON | EXT-008 | объявлено, фаза P1 |
| NON-018 | NON | доктрина §4, урок Битрикса | объявлено, фаза P1 |
| NON-019 | NON | доктрина §4, урок WordPress | объявлено, фаза P1 |
| NON-020 | NON | доктрина §4, урок October | объявлено, фаза P1 |
| NON-021 | NON | EXT-001, урок Flarum | объявлено, фаза P1 |
| NON-022 | NON | доктрина §4, урок Битрикса | объявлено, фаза P1 |
