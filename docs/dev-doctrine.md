# CoreX — доктрина разработки

Входной документ будущего плана/исполнителя: правила и ссылки, НЕ пересказ первоисточников.
Каждый раздел несёт строку `Источник:`. Числа перемерены 2026-07-26 на дереве `7fa6036`
командой, указанной рядом с числом. D# — записи Decision Log `plans/archive/COREX-CORE/plan.md` §5.

## 1. Слои и тест агностичности

Три уровня (формулировка владельца 2026-07-26, D91; эталон — Bitrix). Зависимости — строго вниз.

- **0 — CoreX** (`corex/*`): абстрактный загрузчик модулей и утилиты, годные ЛЮБОМУ Laravel-проекту. Прямой связки с Flex в коде быть не должно
  вообще — ни в идентификаторах, ни в DDL, ни в описаниях команд. FlexCRM сегодня единственный потребитель, но «единственный» ≠ «целевой».
- **1 — FlexCMS** (`flex/*`): собственные компоненты и модули поверх CoreX; переиспользуются между продуктами Flex.
- **2 — FlexCRM**: композиция — потребляет модули уровня 1 и CoreX напрямую, своего кода минимум.
- Та же лестница в нумерации D70 (дословно): «`corex/*` = агностик-платформа (L0) + домен-основы без продуктовой семантики (L1, аналог iblock);
  `flex/*` = тематические модули Flex (L2); продукты = композиция (L3); cross-ecosystem — standalone». D91 уточняет формулировку, не запреты.
- **Тест агностичности:** «`corex/*` полезен проекту БЕЗ CRM/CMS/Store». Не прошёл — код едет на уровень 1 либо в standalone-пакет (AI-gateway, биллинг, метеринг, модерация).
- Зависимость вверх запрещена: `corex/*` ↛ `flex/*`, `corex/core` не зависит ни от одного пакета монорепо (гейт — `tests/Arch/LayerTest.php`, §6).
- Запрет буквален и в ТЕКСТЕ: `flex`-литералов в `packages/{core,modules,tenancy,auth,tasks}`
  (php/json/md/sql) быть не должно — ни в DDL, ни в описаниях, ни в README, ни в идентификаторах
  фикстур. Легальны только `flex/crm`/`Flex\Crm` (имя пакета слоя L2, D82) и исторические
  `CHANGELOG`-записи о ренеймах. Гейт — то же правило source-scan'а (§6, P2.17/D94).

Источник: D91 (владелец, 2026-07-26); D70 + `plans/archive/COREX-CORE/findings/P3.8-doctrine-source-2026-07-26.md`
(дословный захват `CoreX.md`); vault `2026.07.17-AXIOMA-ORCHESTRA/research/01-layer-doctrine.md` (таблица слоёв, SSOT).

## 2. Тест-топология

- Новые тесты пакета кладутся В ПАКЕТ: `packages/<pkg>/tests/**` со своими `phpunit.xml`,
  `phpstan.neon`, `composer.json` (образец — `packages/tenancy/tests`). Канон D81, supersedes D19.
- Пакетные сьюты сегодня — 4: `auth`, `crm`, `tasks`, `tenancy` (`ls -d packages/*/tests`).
  `core` и `modules` собственных `tests/` НЕ имеют; их сьюты живут в корне.
- Корневой `tests/` — харнесс ядра/модулей и кросс-пакетные проверки: `Arch`, `Feature`,
  `Unit`, `PHPStan`, `Stubs`, `Support`, `database`. Он НЕ дом для тестов прикладных пакетов.
- Каждый новый пакетный сьют получает СВОЙ CI-джоб (образец — `tenancy-tests`): сьют без
  джоба = отсутствие регрессионной защиты (аудит A3, Blocker).
- Агрегатор — `composer test:packages` (цикл auth/crm/tasks/tenancy).

Источник: D81; `plans/archive/COREX-CORE/research/04-p3-outcomes-2026-07-26.md` §7; корневой `composer.json`.

## 3. Нейминг-канон

- Публичная поверхность `corex/*` — только `corex`-идентификаторы: конфиг `corex-modules.php`,
  env `COREX_MODULES_*`, artisan `corex:modules:*`, publish-теги `corex-modules-*`, компилят
  `bootstrap/cache/corex_modules.php`, PHPStan-параметр `corexModules`. Новых `flex`-идентификаторов
  в `corex/*`-пакетах не заводить (P3.3).
- `flex/crm` — ЦЕЛЕВОЕ имя слоя L2, а не легаси (D82): обратный ренейм в `corex/crm` запрещён.
- Module id = composer-имя пакета; оно хранится в `mod_*`-таблицах, поэтому ренейм модуля —
  это data-миграция `mod_*`, а не правка манифеста (D82, из находки A4).
- LEGACY-реестр «17 пакетов ядра» в `CoreX.md` источником прописки новых пакетов НЕ является
  (прописка — по §1).

Источник: D82; `plans/archive/COREX-CORE/research/04-p3-outcomes-2026-07-26.md` §8;
`plans/archive/COREX-CORE/findings/P3.8-doctrine-source-2026-07-26.md` (warning-box реестра).

## 4. Extension-канон

- Пять механизмов расширения: события (`DomainEvent`; подписки ТОЛЬКО в коде манифеста),
  extenders, компоненты (override по стабильному handle), capabilities, Contracts/DI-драйверы.
- Extenders — ЗАКРЫТЫЙ набор из 8: `Entity`, `EntityField`, `Permissions`, `Api`, `Filament`,
  `Menu`, `Routes`, `Workflow` (`ls packages/modules/src/Extend` — 8 + маркер-интерфейс
  `Extender.php`). Девятый extender заводится только через RFC (≥2 недель), не «по ходу item'а».
- Модуль = composer-пакет `type: corex-module` + наследник `CorexModule` с `manifest()`;
  discovery читает `vendor/composer/installed.json` (`packages/modules/src/Discovery/ModuleDiscovery.php`).
  Один пакет = один модуль; версия — из `installed.json`, не из манифеста.
- Запрещённые анти-паттерны: WP-hooks, подписки в БД, рой extenders (Flarum),
  alias-override (October), инфоблоки. Monkey-patching запрещён.

Источник: `plans/archive/COREX-CORE/findings/brain-recon-doctrine-2026-07-18.md` §3–§4 (ADR-011/ADR-014);
`packages/modules/src/Extend/Extender.php` (докблок закрытого набора).

## 5. Контракт-дисциплина

- Контракты — только интерфейсы в `packages/<pkg>/src/Contracts/`; реализации скрыты.
  Каталоги есть у `core` (13 файлов) и `modules` (10) — `ls packages/core/src/Contracts | wc -l`.
- Любой дифф `src/Contracts/**` ОБЯЗАН нести строку в `CHANGELOG.md` ТОГО ЖЕ пакета —
  иначе `scripts/contract-guard.sh` краснеет (warn-прокси, реального BC-анализа не делает).
- До v1.0 breaking-изменения контрактов легальны в MINOR (D17) — но объявляются в CHANGELOG.
  После v1.0 — строгий SemVer, breaking только в major.
- Тег ставится на закрытии фазы по GREEN-аудиту + запись в per-package `CHANGELOG.md`;
  1.0 не ставится, пока контракты не обкатаны потребителями (D17).

Источник: D17; `scripts/contract-guard.sh` (шапка); `plans/archive/COREX-CORE/research/04-p3-outcomes-2026-07-26.md` §3.

## 6. Гейты качества — и их честное состояние

- **Закрытие code-item'а (D34) — ОБА лейна:** `composer lint:check && composer analyse && composer test && composer test:pg`; один sqlite-лейн = ложно-зелёное.
- **Слой-гейт (D84 + P2.17):** source-scan `tests/Arch/LayerTest.php` + `tests/Support/SourceScanner.php`, **4 правила**, `composer test:arch`,
  CI-джоб `layer-gate`; `pest-plugin-arch` отвергнут — пакеты как path-репо под `vendor/` дают ВАКУУМНОЕ прохождение правила.
  Четвёртое правило (P2.17) — «нет `flex`-литералов в `packages/{core,modules,tenancy,auth,tasks}`» с ЯВНЫМ allow-list
  (`flex/crm`/`Flex\Crm` + `CHANGELOG.md`); негатив-проверено 2026-07-26 подсадкой литерала в `packages/core/src` (php) и `.sql`-файл —
  обе стороны красные с именем файла, зелено после снятия.
- **Contract-guard:** `scripts/contract-guard.sh`, warn-режим до v1.0 (§5).
- **Mutation-check:** `scripts/mutation-check.sh`, 30 патчей в едином repo-доме `scripts/mutations/` (`ls scripts/mutations | wc -l` = 30, D86);
  правки — только там, дублирующий экземпляр в vault снят `git rm` (D152, P2.18). Кейс пинит АВТОРИТЕТНУЮ точку enforcement, избыточный DiD-слой изолируется тестом, не retire (D85).
  **Состояние на item-коммите P2.19 — ЗЕЛЁНЫЙ: `bash scripts/mutation-check.sh --all` = 30/30**, все 30 применяются СТРОГИМ `git apply --check` (без фоллбэка).
  10 патчей (`P1.16`/`P1.18`/`P1.21`×2/`P1.23`×2/`P1.25`×2/`P1.29`×2), протухших от докблок-правки P2.16 (`3ba6ad2`), переанкорены при идентичном саботаже (D97).
  `run_case` несёт аварийный **3-way-фоллбэк** (D97): строгий apply остаётся первичным путём; при его отказе пробуется `git apply -3` — успех печатает
  ГРОМКУЮ строку в логе с именем кейса («контекст протух, реанкорь») и кейс всё равно доказывается RED-ом, отказ обеих схем — честное «граница сдвинулась»
  (`return 2`). Откат зеркалит режим наложения и ДОКАЗЫВАЕТ слепком хэшей таргет-путей, что дерево вернулось в исходное состояние; расхождение — громкий
  `exit 3`, а не тихое продолжение. **Правило D98:** item, тронувший `packages/*/src`, обязан прогнать `bash scripts/mutation-check.sh --all` до закрытия —
  гейт ловит именно этот класс дрейфа (текстовый проход по коду сдвигает контекст хунка, не саботаж), а не только продакшен-регрессии.
- **Coverage floor = 55** (`scripts/coverage-min-floor.txt`, ослаблен с 80) — поднять только закрытием time-gated item'а P1.27 (D47).
- **Anti-weakening-гейт ЗЕЛЁНЫЙ (P2.15/D93):** `bash scripts/anti-weakening-gate.sh` = `OK` на чистом дереве
  (прогон 2026-07-26, `0f778de` против базы `97fdc77`). Три дефекта, названных здесь ранее — счёт только
  корневого `tests/`, вакуумный `xargs command grep` для pg-лейна, неучтённое одобренное удаление
  `TenantBoundaryTest` (D79) — устранены: `count_tests()` считает объединение `tests/` + `packages/*/tests`
  на обоих деревьях, pg-счёт реально считает pg-файлы (раздельный отчёт `sqlite X -> Y, pg A -> B`),
  а одобренная дельта смягчена `scripts/anti-weakening-allowances.txt` с ЯКОРЕМ БАЗЫ (скидка авто-истекает,
  когда `origin/main` уезжает с `97fdc77` — постоянный floor намеренно отвергнут).
- **Живого CI-прогона нет:** 12 джобов `ci.yml` описаны, run `30204984851` отказан по биллингу — «зелёные» P3.1–P3.7 суть ЛОКАЛЬНЫЕ прогоны.

Источник: D34/D47/D84/D85/D86/D93; `plans/archive/COREX-CORE/research/04-p3-outcomes-2026-07-26.md` §1–§6;
`plans/archive/COREX-CORE/findings/P2.15-anti-weakening-diagnosis-2026-07-26.md`; прогоны перечисленных команд 2026-07-26.

## 7. Очередь «спека требует — кода ещё нет»

Не заводить как «баг»: это известные разрывы, у каждого — источник и владелец.

- Интроспекция реестра: CLI `*:modules:why`, MCP `registry.extension_points` (ADR-011) — кода нет.
- Webhooks (ADR-021) — proposed, Год 2; marketplace (ADR-015) — superseded → RegHub; `compatibility.json` (ADR-008) — не заведён.
- Split-зеркала `splitsh-lite` (ADR-002) + изоляционный install-тест — workflow отсутствует (аудит A16, release-фаза, D82).
- Coverage: `Architecture` §1 требует 85, `Package_Governance` §2 — 80, факт-floor 55; расхождение самих доков не разрешено (разбор при P1.27).
- ADR-003 (ULID) и ADR-004 (row-level tenancy) superseded (UUIDv7 + DB-per-account), файлы в vault не переписаны — **наводка владельцу:** переписать.
- CI-матрица `Development_Process` §2 (8.2–8.4 × L11/12) расходится с ADR-001 (8.3–8.5, L12–13) и фактом `ci.yml` (8.3/8.4 × 12/13).

Источник: `plans/archive/COREX-CORE/findings/brain-recon-doctrine-2026-07-18.md` §3/§6/«Статусы источников»; `plans/archive/COREX-CORE/research/04-p3-outcomes-2026-07-26.md` §11.

## 8. Как этим пользоваться будущему плану

- Этот файл — входная точка. Слои знаний плана: `plans/archive/COREX-CORE/findings/` (сырьё),
  `plans/archive/COREX-CORE/research/` (синтез), `plan.md` §5 (решения). Спека и ADR — в Brain-vault,
  не в репозитории; vault правит владелец.
- Новый пакет: прописка — по §1 (агностик → `corex/*`, тематика → `flex/*`, cross-ecosystem →
  standalone); имя — по §3; тесты и CI-джоб — по §2; контракты и CHANGELOG — по §5.
- Новое правило расширения (девятый extender, новый механизм) — только RFC/ADR (§4),
  не решением исполнителя внутри item'а.
- Утверждение о гейте пишется вместе с его состоянием (§6): «гейт X есть» без «X сейчас
  красный/не гонялся» — ложь для следующего исполнителя.
- Расхождение доктрины с деревом — СТОП и новый D#, а не молчаливая правка правила.

Источник: `plans/archive/COREX-CORE/plan.md` §2 Execution Rules; D89 (дом этого документа — репо);
`plans/archive/COREX-CORE/research/02-p3-clean-api-2026-07-18.md` §1 (бриф владельца §5–6).
