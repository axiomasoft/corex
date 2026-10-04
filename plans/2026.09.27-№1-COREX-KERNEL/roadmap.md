# Roadmap — 2026.09.27-№1-COREX-KERNEL

**Обновлён:** 2026-10-04 · **Соответствует plan.md:** P00–P05 и P10 закрыты GREEN; P05 закрыта на mirror CI `37123316791` (D73). P06 детализирована по фактическому дереву `3f568ddd` (D90, D91); finish P00–P06 + P10 записывает строки sheet 21–24. Следующий шаг — независимый design audit нового fingerprint, затем B06-A. P07 детализирована 2026-10-04 до закрытия P06 по решению владельца (D94–D97); исполнение P07 ждёт закрытия P06. P08–P09 остаются skeleton в `backlog/` по D21/D24.

## Жизненный цикл плана (D21, D24)

Для каждой фазы по порядку: `task:plan-design 2026.09.27-№1-COREX-KERNEL Pn` (`backlog/Pnn.md` → `phases/Pnn/`;
досье/примеры/runbook/self-check, ресерч с captures) → `task:plan-design 2026.09.27-№1-COREX-KERNEL finish` →
`task:plan-audit 2026.09.27-№1-COREX-KERNEL design` (независимая модель; только детализированные фазы) → исполнение строк
execution-sheet → закрытие фазы → дизайн следующей по фактическому коду. Дизайн фаз вперёд не делается (B5).

## Карта фаз

| Фаза | Суть | Маршрут (детали — execution-sheet) | Зависимости | Owner gate |
|:--|:--|:--|:--|:--|
| P00 | glossary, карты, архитектура, supersede PLAN2 | frontier/high | — | — |
| P01 | окружение PHP 8.4, платформа, golden, ratchet-гейты, CI supply chain | impl/high | P00 | O-1 расширения php8.4, O-2 роль PG, O-3 push + live CI |
| P02 | швы ядра/operations, механический перенос, идентификаторы, гейт полноты | impl/high, impl/xhigh (P02.3) | P01 | O-3 (hosted CI P01) до старта |
| P03 | feature modules + Internal (D57), гейт раскладки | impl/high, impl/xhigh (P03.2) | P02 | — |
| P04 | #[Api]/#[Spi], DTO, ошибки | impl/high, fr/high | P03 | — |
| P10 | зелёный hosted CI на базе P04: лейны тестов, герметичность гейтов, дрейф CI, покрытие, зависимости (D73, D74) — **исполняется до P05** | impl/high; review light, P10.2 и P10.3 full (D75) | P04 | push в `origin` и чтение hosted CI на каждом item |
| P05 | typed config, композиция, порты, время жизни (D69, D70) | impl/high; review light, P05.3 full (D71) | P04, P10 | — |
| P06 | wire: RFC 9457, конверт входа, коды tools, CloudEvents, контракт доставки и egress вебхуков (D90, D91) | impl/high; review light, Review P06 frontier/high | P05 | — |
| P07 | tenancy-движок (собственный, D95), impersonation на портах, access без AzGuard + мост, `CoreX\Scheduling` (D96) | impl/xhigh (P07.1), impl/high; review light, Review P07 frontier/high (D97) | P06 (D94) | публикация AzGuard (Q2, только мост) |
| P08 | схема baseline v1 | impl/high–xhigh, fr/high | P07 | — |
| P09 | качество, docs, split, `1.0.0-rc.1` | impl/high | P08 | публикация (Packagist, split repos, теги) |

## Критический путь и риски

| Риск | Где | Митигация |
|:--|:--|:--|
| PHP 8.4 без расширений, PG без роли (хост, 2026-09-28) | P01+ | `scripts/php84 --check`, owner gates O-1/O-2 (`research/P01-design.md` §7); иначе стоп |
| Массовые переносы ломают поведение | P02, P03 | golden P01, per-package тесты, карта + инструмент, сухой прогон |
| Замена движка tenancy | P07 | D95: собственный движок, граница scoped = tenant, PG-тест с mutation-кейсом, Review P07 |
| Boot/время жизни биндингов во всех пакетах | P05 | гейты C/S/G и L1–L3, сборочный сценарий без окружения, PG-сценарий смены tenant, независимое ревью P05.3; baseline только Impersonation |
| Захват default-соединения в scoped-сервисах (60 параметров) | P07 | D95 п.3: сброс scoped-экземпляров на каждой смене контекста; `ScopeBoundaryPgTest` |
| Локальный GREEN при красном hosted CI (с 2026-07-15) | P01–P04 | P10 + правило D73: фаза закрывается только при зелёном hosted CI |
| Расхождение принципов с chatom | все | D22, `research/02` §12, сверка при дизайне каждой фазы |

<!-- execution-sheet/v1 -->

Эта таблица — **единственное конкретное per-admission execution-расписание**.
P00–P03 закрыты GREEN. Строки 1–9 сохраняют историю принятых запусков;
D47 исторически откладывал push/hosted CI; с P10 действует D73. P04 строки 10–13 подтверждены finish
2026-09-29 (D65): четыре последовательных solo, без изменения маршрутов/review.
Для текущего fingerprint P04 повторный design audit снят владельцем (D66):
GREEN finish и явное owner acceptance открывают строку 10. Строки 11–13 требуют
GREEN результатов своих предшественников. Строки 14–20 записаны finish 2026-10-01:
четыре solo P10 (D75), затем B05-A, P05.3, P05.4 (D71); порядок строгий, P05 стартует только
после GREEN P10 с зелёным hosted CI (D73). Строки 14–20 открывает design-gate GREEN для
fingerprint этого finish; текущий допуск требует независимого GREEN design audit; D77 superseded by D79. Строки 14–20 уже исполнены; P05 закрыта GREEN на mirror CI `37123316791`.
Finish 2026-10-03 включает P06: строки 21–24 по D91, строго B06-A → P06.3 → P06.4 → P06.5.
Их открывает независимый GREEN design audit нового fingerprint (`**Design Audit:** required`);
D89 не переносит waiver на новый дизайн P06. P07–P09 backlog не входили в finish 2026-10-03 (D24); P07 детализирована позже (D94).
Finish 2026-10-04 включает P07: строки 25–29 по D97, строго P07.1 → P07.2 → P07.3 → B07-A → P07.6;
строки 1–24 не меняются. Их открывает независимый GREEN design audit нового fingerprint и закрытие P06
на зелёном hosted CI (D94 п.2). P08–P09 остаются backlog и в этот finish не входят.
P04.1 → P04.2 → P04.3 → P04.4: first report tooling, then classified/DTO surface,
then typed registry, then fixed strict cutover. Public semantics требуют frontier/high
для первых трёх, последняя строка implementation/high. Все reviews full и другой моделью.

| Admission | Command | Route | Review | Executor (provider/model + effort) | Independent Reviewer + effort or self-check | Auto-fix executor + effort/limit | Checkpoint | Order / short risk reason |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| 1 · B00-A | `task:plan-run 2026.09.27-№1-COREX-KERNEL P00.1 P00.2 P00.3` | `frontier/high` | `full` | `codex/gpt-6-astra/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-astra/high`, ≤2 rounds | — | P00.1 → P00.2 → P00.3; имена и карты ядра |
| 2 · solo P01.1 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P01.1` | `implementation/high` | `full` | `codex/gpt-6-sol/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-sol/high`, ≤2 rounds | O-1/O-2 preflight | После P00; окружение, манифесты, совместимость тестового стека |
| 3 · B01-B | `task:plan-run 2026.09.27-№1-COREX-KERNEL P01.2 P01.3` | `implementation/high` | `full` | `codex/gpt-6-sol/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-sol/high`, ≤2 rounds | P01.1 GREEN | P01.2 → P01.3; golden до изменения analysis/anti-weakening |
| 4 · solo P01.4 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P01.4` | `implementation/high` | `full` | `codex/gpt-6-sol/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-sol/high`, ≤2 rounds | P01.3 GREEN; O-3 после item | CI потребляет гейты P01.1–P01.3; live CI до P02 |
| 5 · B02-A | `task:plan-run 2026.09.27-№1-COREX-KERNEL P02.1 P02.2` | `implementation/high` | `full` | `codex/gpt-6-sol/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-sol/high`, ≤2 rounds | design audit GREEN; O-3 GREEN | P02.1 → P02.2; SPI ядра/engine без изменения вывода, seams 136 → 18 |
| 6 · solo P02.3 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P02.3` | `implementation/xhigh` | `full` | `codex/gpt-6-sol/xhigh` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-sol/xhigh`, ≤2 rounds | B02-A GREEN; `seams … 0 blocking` | Механический перенос по картам; сухой прогон; golden без rebaseline |
| 7 · solo P02.4 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P02.4` | `implementation/high` | `full` | `codex/gpt-6-sol/high` | `cursor/grok-4.6/high`, fresh | `codex/gpt-6-sol/high`, ≤2 rounds | P02.3 GREEN; O-3 для P02 после item | Идентификаторы, гейт полноты, ownership strict; push + hosted CI — владелец |
| 8 · solo P03.1 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P03.1` | `implementation/high` | `full` | `codex/gpt-6-sol/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-sol/high`, ≤2 rounds | design audit GREEN; P02 closed | Гейт раскладки R0–R5 с baseline 436, ретайр tenancy-structure, STR-доктрина |
| 9 · solo P03.2 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P03.2` | `implementation/xhigh` | `full` | `codex/gpt-6-sol/xhigh` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-sol/xhigh`, ≤2 rounds | P03.1 GREEN; `p03-transfer.py plan … 0 problems` | Перенос 278 классов по карте; manifest/lock sync (D58); сухой прогон; golden без rebaseline; baseline = прогноз 159 |
| 10 · solo P04.1 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P04.1` | `frontier/high` | `full` | `codex/gpt-6-astra/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-astra/high`, ≤2 rounds | GREEN finish + owner acceptance D66; P03 closed | Api/Spi/source scanner, report + negatives before mass classification |
| 11 · solo P04.2 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P04.2` | `frontier/high` | `full` | `codex/gpt-6-astra/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-astra/high`, ≤2 rounds | P04.1 GREEN | Type verdicts/DTO/transaction capture/R5=0, reviewed Golden delta and mechanical replay |
| 12 · solo P04.3 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P04.3` | `frontier/high` | `full` | `codex/gpt-6-astra/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-astra/high`, ≤2 rounds | P04.2 GREEN | Error ownership/boot compilation and safe persisted replay; 85 constructor sites |
| 13 · solo P04.4 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P04.4` | `implementation/high` | `full` | `codex/gpt-6-sol/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-sol/high`, ≤2 rounds | P04.2 + P04.3 artifacts GREEN | Strict graph/manifests/Roave/semver/docs, no new product design |
| 14 · solo P10.1 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P10.1` | `implementation/high` | `light` | `codex/gpt-6-sol/high` | self-check + affected tests, та же сессия; внешняя проверка — hosted CI | `codex/gpt-6-sol/high`, ≤2 rounds | design-gate GREEN; P04 closed; push в `origin` и чтение hosted CI доступны | CI-рецепт без изменения манифестов, `bc-check`, `contract-guard`, `ci-local.sh`; делает отказы остальных джобов читаемыми |
| 15 · solo P10.2 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P10.2` | `implementation/high` | `full` | `codex/gpt-6-sol/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-sol/high`, ≤2 rounds | P10.1 GREEN; hosted CI прочитан | Манифесты из собственных объявлений, мост `Tooling/` и R6, объявленные зависимости; полноту манифеста CI не видит |
| 16 · solo P10.3 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P10.3` | `implementation/high` | `full` | `codex/gpt-6-sol/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-sol/high`, ≤2 rounds | P10.2 GREEN; hosted CI прочитан | Протокол готовности worker схем; fail-closed и завершение процессов на всех ветках |
| 17 · solo P10.4 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P10.4` | `implementation/high` | `light` | `codex/gpt-6-sol/high` | self-check + affected tests, та же сессия; внешняя проверка — hosted CI; правка `packages/*/src` — `cursor/grok-4.7/high`, fresh (D76) | `codex/gpt-6-sol/high`, ≤2 rounds | P10.3 GREEN; hosted CI прочитан | PG-группа, `test:hermetic`, покрытие, лейны Laravel 12 и PHP 8.5; закрывает P10 при workflow `success`; event-skipped допустим по D76 |
| 18 · B05-A | `task:plan-run 2026.09.27-№1-COREX-KERNEL P05.1 P05.2` | `implementation/high` | `light` | `codex/gpt-6-sol/high` | self-check + affected tests, та же сессия | `codex/gpt-6-sol/high`, ≤2 rounds | P10 closed GREEN, hosted CI зелёный на базе (D73) | P05.1 → P05.2; Options и удаление static `Config`, затем registrars по тем же providers |
| 19 · solo P05.3 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P05.3` | `implementation/high` | `full` | `codex/gpt-6-sol/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-sol/high`, ≤2 rounds | B05-A GREEN | Порты вместо фасадов и static state в 16 пакетах; время жизни биндингов и соединение tenant тесты ловят слабо |
| 20 · solo P05.4 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P05.4` | `implementation/high` | `light` | `codex/gpt-6-sol/high` | self-check + affected tests, та же сессия | `codex/gpt-6-sol/high`, ≤2 rounds | P05.3 GREEN | Резидентный режим, смена tenant, `config:cache`, reference и доктрина; закрывает P05 при зелёном hosted CI (D73) |
| 21 · B06-A | `task:plan-run 2026.09.27-№1-COREX-KERNEL P06.1 P06.2` | `implementation/high` | `light` | `codex/gpt-6-sol/high` | self-check + affected tests, та же сессия (D91); независимое ревью фазы — P06.5 | `codex/gpt-6-sol/high`, ≤2 rounds | design audit GREEN текущего fingerprint; P05 closed GREEN | P06.1 → P06.2; единая ошибка и конверт входа, затем коды tools по тому же реестру |
| 22 · solo P06.3 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P06.3` | `implementation/high` | `light` | `codex/gpt-6-sol/high` | self-check + affected tests (D91); независимое ревью фазы — P06.5 | `codex/gpt-6-sol/high`, ≤2 rounds | B06-A GREEN | Кодек CloudEvents и тело вебхука; байтовый эталон, внешний профиль и неизменная подпись |
| 23 · solo P06.4 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P06.4` | `implementation/high` | `light` | `codex/gpt-6-sol/high` | self-check + affected tests (D91); независимое ревью фазы — P06.5 | `codex/gpt-6-sol/high`, ≤2 rounds | P06.3 GREEN | Причины отказа и бюджет, egress на подписке/попытке/replay; mutation-кейс и модель угроз |
| 24 · solo P06.5 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P06.5` | `frontier/high` | `none` | `codex/gpt-6-astra/high`, fresh, модель отличается от исполнителей P06.1–P06.4 | сам item — независимое read-only Review P06 (D91); повторное ревью reviewer не назначается | —; продуктовый ремонт выполняет владеющий item | P06.1–P06.4 GREEN | Проверяет согласованность wire и egress; единственная запись — verdict.md; затем отдельное закрытие P06 на зелёном hosted CI (D73, D91 п.4) |
| 25 · solo P07.1 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P07.1` | `implementation/xhigh` | `light` | `codex/gpt-6-sol/xhigh` | self-check по досье §6 + affected tests (D97); независимое ревью фазы — P07.6 | `codex/gpt-6-sol/xhigh`, ≤2 rounds | design audit GREEN текущего fingerprint; P06 closed GREEN на hosted CI (D73, D94 п.2); первым шагом сверка `p07-facts.sh` (D94 п.3) | Собственный движок tenancy, граница scoped = tenant; очереди, HTTP, сессии; PG-сценарии изоляции с mutation-кейсом |
| 26 · solo P07.2 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P07.2` | `implementation/high` | `light` | `codex/gpt-6-sol/high` | self-check по досье §6 + affected tests (D97); независимое ревью фазы — P07.6 | `codex/gpt-6-sol/high`, ≤2 rounds | P07.1 GREEN | Удаление stancl из моделей, provisioning, manifests, lock и CI после перевода движка; VERIFY-AT-EXEC stancl v4 (F5) |
| 27 · solo P07.3 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P07.3` | `implementation/high` | `light` | `codex/gpt-6-sol/high` | self-check по досье §6 + affected tests (D97); независимое ревью фазы — P07.6 | `codex/gpt-6-sol/high`, ≤2 rounds | P07.2 GREEN | Impersonation на портах, профили, fail-closed двойной аудит; матрица профилей и тесты отказа аудита |
| 28 · B07-A | `task:plan-run 2026.09.27-№1-COREX-KERNEL P07.4 P07.5` | `implementation/high` | `light` | `codex/gpt-6-sol/high` | self-check по досье §6 + affected tests (D97); независимое ревью фазы — P07.6 | `codex/gpt-6-sol/high`, ≤2 rounds | P07.3 GREEN | P07.4 → P07.5; access без AzGuard и мост `access-azguard` вне релиза, затем `CoreX\Scheduling` и граф без notifications → wakeup; мост в CI зависит от доступа к AzGuard (F6) |
| 29 · solo P07.6 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P07.6` | `frontier/high` | `none` | `codex/gpt-6-astra/high`, fresh, модель отличается от исполнителей P07.1–P07.5 | сам item — независимое read-only Review P07 (D97 п.2); повторное ревью reviewer не назначается | —; продуктовый ремонт выполняет владеющий item | P07.1–P07.5 GREEN | Единственная запись — `artifacts/P07.6-review/verdict.md`; затем отдельное закрытие P07 на зелёном hosted CI финального commit (D73, D97 п.4) |

D58: remediation и повторный finish выполнены codex/gpt-6-astra/high по прямому мандату владельца; исходный аудит ATTENTION сохранён. Следующий design audit читает delta A1–A5 в новом независимом контексте.

## P03 — последовательность команд

Все команды фазы по порядку; строки 3–4 — те же запуски, что строки 8–9 execution-sheet (группы и маршруты задаёт только
sheet). Носители плана пишет один автор за раз (D56): после каждого шага исполнителя автор плана сводит результат.

| # | Шаг | Команда | Кто (provider/model + effort) | Native launch | Условие старта |
|:--|:--|:--|:--|:--|:--|
| 0 | Дизайн фазы (выполнен) | `task:plan-design 2026.09.27-№1-COREX-KERNEL P03` | `claude-code/claude-opus-5-5/high` (автор плана) | `/task:plan-design 2026.09.27-№1-COREX-KERNEL P03` | P02 закрыта |
| 1 | Finish дизайна (выполнен) | `task:plan-design 2026.09.27-№1-COREX-KERNEL finish` | `claude-code/claude-opus-5-5/high` (автор плана, D56) | `/task:plan-design 2026.09.27-№1-COREX-KERNEL finish` | шаг 0; `plan-lint` OK |
| 2 | Независимый аудит нового fingerprint | `task:plan-audit 2026.09.27-№1-COREX-KERNEL design` | `codex/gpt-6-astra/xhigh` (отдельный reviewer, свежий контекст; маршрут Task audit) | `codex -C "$(git rev-parse --show-toplevel)" -m gpt-6-astra -c 'model_reasoning_effort="xhigh"' -a never -s danger-full-access '$ task:plan-audit 2026.09.27-№1-COREX-KERNEL design'` | finish записан; `design-gate` ждёт аудит |
| 3 | Исполнение P03.1 (sheet 8) | `task:plan-run 2026.09.27-№1-COREX-KERNEL P03.1` | `codex/gpt-6-sol/high`; review `cursor/grok-4.7/high` | `codex -C "$(git rev-parse --show-toplevel)" -m gpt-6-sol -c 'model_reasoning_effort="high"' -a never -s danger-full-access '$ task:plan-run 2026.09.27-№1-COREX-KERNEL P03.1'` | design audit GREEN |
| 4 | Исполнение P03.2 (sheet 9) | `task:plan-run 2026.09.27-№1-COREX-KERNEL P03.2` | `codex/gpt-6-sol/xhigh`; review `cursor/grok-4.7/high` | `codex -C "$(git rev-parse --show-toplevel)" -m gpt-6-sol -c 'model_reasoning_effort="xhigh"' -a never -s danger-full-access '$ task:plan-run 2026.09.27-№1-COREX-KERNEL P03.2'` | P03.1 GREEN |
| 5 | Закрытие P03 в owning continuation | последний GREEN item P03.2 закрывает фазу по D56.5 | root исполнения, по sheet | текущая сессия | items и independent review GREEN; phase close gate allow |
| 6 | Дополнительный adversarial audit (не обязателен) | `task:plan-audit 2026.09.27-№1-COREX-KERNEL P03` | `codex/gpt-6-astra/xhigh`, свежий reviewer | provider-native launch по Task | только принятое candidate/run-bound `audit-admission/v2`; не автоматически из Phase Assurance |
| 7 | Дизайн следующей фазы | `task:plan-design 2026.09.27-№1-COREX-KERNEL P04` | `claude-code/claude-opus-5-5/high` | `/task:plan-design 2026.09.27-№1-COREX-KERNEL P04` | P03 закрыта |

D59: P03 full replay исправляется текущим owner-authorized root; после GREEN design audit следующий шаг — P03.1 (sheet 8), без ещё одного круга дизайна. Полный audit вместо owning close не назначается автоматически.


## P04 — последовательность команд

| Шаг | Команда | Условие |
|:--|:--|:--|
| Дизайн P04 (выполнен) | `task:plan-design 2026.09.27-№1-COREX-KERNEL P04` | P03 GREEN; current author Sol/high по D62 |
| Finish (выполнен, D65) | `task:plan-design 2026.09.27-№1-COREX-KERNEL finish` | phase finalize + plan-lint GREEN; confirm rows 10–13 |
| Принятие дизайна (выполнено, D66) | owner acceptance текущего GREEN finish | повторный design audit отменён владельцем; fingerprint-bound gate GREEN |
| Исполнение | exact commands rows 10–13 | GREEN audit; each predecessor terminal GREEN |
| Закрытие | final GREEN P04.4 owning continuation | item Validation/full reviews + phase close gate GREEN |
| Дизайн P05 | `task:plan-design 2026.09.27-№1-COREX-KERNEL P05` | P04 closed; actual code input |

D62 authoring override applies P04 design; D65 отдельно разрешает текущий finish на Sol/high. Future executor routes stay pinned to
semantic class/effort in Routing; permission to design with Sol is not silently extended
to independent reviews or future frontier execution. Phase audit is never automatic.


## P05 — последовательность команд

Группы — предложение фазы (plan.md §3); строки execution-sheet записывает finish.

| Шаг | Команда | Условие |
|:--|:--|:--|
| Дизайн P05 (выполнен) | `task:plan-design 2026.09.27-№1-COREX-KERNEL P05` | P04 closed; D69–D72 |
| Дизайн P10 (выполнен) | `task:plan-design 2026.09.27-№1-COREX-KERNEL P10` | D73–D75; `findings/01-hosted-ci-debt.md` |
| Finish (выполнен 2026-10-01) | `task:plan-design 2026.09.27-№1-COREX-KERNEL finish` | phase finalize + plan-lint GREEN; строки sheet 14–17 (P10), 18–20 (B05-A, P05.3, P05.4) |
| Design audit (выполнен 2026-10-01: ATTENTION) | `task:plan-audit 2026.09.27-№1-COREX-KERNEL design` | `findings/P05-P10-design-audit.md`; правка — D76 |
| Повторный finish (выполнен, D77/D78) | `task:plan-design 2026.09.27-№1-COREX-KERNEL finish` | правка D76 в дереве; новый fingerprint |
| Независимый design audit (D79) | `task:plan-audit 2026.09.27-№1-COREX-KERNEL design` | GREEN текущего fingerprint; D77 superseded; затем строка 14, P10.1 |
| Исполнение | точные команды строк execution-sheet: сначала P10, затем P05 | design-gate GREEN; каждый предшественник GREEN; hosted CI зелёный на базе |
| Закрытие | финальный GREEN P05.4 в owning continuation | Validation items + phase close gate |
| Дизайн P06 (выполнен 2026-10-03) | `task:plan-design 2026.09.27-№1-COREX-KERNEL P06` | P05 closed; фактический код; D90, D91 |
| Finish P06 (выполнен 2026-10-03) | `task:plan-design 2026.09.27-№1-COREX-KERNEL finish` | `**Design Audit:** required`; строки sheet для B06-A, P06.3, P06.4, P06.5 |
| Design audit P06 | `task:plan-audit 2026.09.27-№1-COREX-KERNEL design` | GREEN fingerprint finish; затем B06-A |

## P07 — последовательность команд

Группы — предложение фазы (plan.md §3, D97); строки execution-sheet записывает finish.

| Шаг | Команда | Условие |
|:--|:--|:--|
| Дизайн P07 (выполнен 2026-10-04) | `task:plan-design 2026.09.27-№1-COREX-KERNEL P07` | D94: до закрытия P06 по решению владельца; D95–D97 |
| Finish (выполнен 2026-10-04) | `task:plan-design 2026.09.27-№1-COREX-KERNEL finish` | phase finalize + plan-lint GREEN; строки sheet 25–29 (P07.1, P07.2, P07.3, B07-A, P07.6) |
| Design audit | `task:plan-audit 2026.09.27-№1-COREX-KERNEL design` | `**Design Audit:** required`; GREEN текущего fingerprint |
| Исполнение | точные команды строк execution-sheet P07 | P06 closed GREEN на hosted CI (D73, D94 п.2); design audit GREEN; каждый предшественник GREEN |
| Закрытие | отдельный шаг после P07.6 | вердикт P07.6 GREEN; hosted CI на финальном commit — все джобы `success` (D73, D97 п.4) |
