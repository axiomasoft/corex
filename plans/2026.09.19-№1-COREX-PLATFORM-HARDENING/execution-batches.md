# Исполнение P7–P17: входы, батчи и контрольные точки

**Срез:** 2026-09-23, после P6.6. **Первый item:** P7.1. Это маршрут исполнения,
а не замена item-спек. Нормативны `plan.md`, `phases/Pn/Pn.md`,
`phases/Pn/Pn.m.md`, решения и соответствующий `research/Pn-design.md`.
`status.md` и `*.state.md` — генерируемые виды; перед запуском сверять их с
`journal.jsonl`. Уже закрытые P1–P6 повторно не запускать.

## Как исполнять батч

1. Читать `plan.md` §2–§3, фазу, **каждый** item батча и его `Required Reads`
   в указанном порядке. `research/Pn-design.md` задаёт код и алгоритм;
   `research/Pn-design-rag.md` и `artifacts/Pn-design-rag/manifest.json`
   фиксируют внешнее подтверждение и его пределы. Внешний ответ не заменяет
   локальный контракт. Сначала сверить `Files` и текущие public consumers.
2. Батч — **одна сессия с общим чтением**, items исполняются последовательно
   по зависимостям. Для каждого item отдельно сохранить scope, test/PG receipt,
   терминальное состояние и checkpoint. Нельзя закрывать группу одним общим
   «тесты зелёные». Если следующий item требует результата предыдущего,
   пересобрать его bundle после терминального checkpoint производителя.
3. Выполнять только готовое подмножество. Owner gate проверяется **до**
   соответствующей записи, DDL, freeze или публикации; соседние items могут
   идти дальше, если их зависимости позволяют. RED принимается только с
   воспроизведённым baseline; иначе repair принадлежит породившему item.
   Новые product-файлы вне `Files` требуют исправить item-контракт до правки.
4. Перед SQL-тестами пройти локальный DB isolation/preflight; итоговые
   `lint:check`, `analyse`, `test`, а для SQL `test:pg` — по правилам `plan.md`
   §2. Печатать точные команды и результаты, не только цвет. Для gates нужны
   именованные красные посадки и восстановление чистого дерева.
5. Фазовый final item запускается в **другой сессии**, читает фактические
   receipts, воспроизводит интеграционные/негативные проверки и закрывает
   фазу. Контрольные точки после P8, P10, P12, P14 и P16 выполняются этим
   final item; отдельный пустой аудит не запускается.

Для группы из нескольких соседних items launch адресует **всю готовую
цепочку** (`task:plan-run <ID> Pn.a-Pn.b`); одиночный item —
`task:plan-exec <ID> Pn.m`. Handoff `Batch` должен дословно совпадать с
`plan.md` §3.1. Метка E… ниже — идентификатор сессии в этой карте;
для одиночной строки официальный Task `Batch` равен `solo`. Числовой
пропуск, например P7.1→P7.12, не является Task batch: это две сессии
E07a/E07b.

**Наблюдаемое ограничение на старом закрытии:** на срезе 2026-09-23 общий
`plan-lint` показывает 53 legacy errors и 6 warnings по P1–P6: ранние
Completion Notes в проекции журнала не содержат новых write-site кодов.
Это не результат P7 и не повод править сгенерированные `*.state.md` или
называть общий lint GREEN. Перед запуском проверять готовность текущего
bundle отдельно; исправление исторического журнала требует отдельного
допустимого lifecycle repair с сохранением preimage. Ошибка P5.6 о поиске
`now()` разобрана в `findings/P5-receipts.md` §P5.6: разрешённый
`$clock->now()` попадает под старый regex, глобальный helper проверяется
уточнённым поиском. Закрытый item и его receipt не переписывать.

Модели ниже — Cursor native (`composer-2.5`, `cursor-grok-4.6-high`,
`grok-4.7-high`, `gpt-5.6-sol-high/xhigh/max`). Проверены через
`agent --list-models` 2026-09-23: прежние `cursor-grok-4.7-high`,
`gpt-5.6-sol` без effort и `gpt-6-sol` в Cursor отсутствуют. При запуске
в Codex использовать projection из `plan.md` §3.3. Усилие: C = medium,
G46/G47 = high, S56 = high (phase-final review = xhigh), F = Cursor
xhigh для P13.12 и max для P17.11–P17.12; Codex high/max. Слаг и effort
проверяются перед product mutation; недоступность exact pin фиксируется, модель не выбирается
самим исполнителем. Сохранены все owner gates item-спек.

## Прямые входы и выходы по фазам

| Фаза | Читать перед первым item | Производимый артефакт / точка сверки |
|:--|:--|:--|
| P7 | `research/P7-design.md` §0, §2–§5, §13, §15; `artifacts/architecture/config-keys.csv`; D38–D41, D100–D101; `findings/P6-receipts.md` | `findings/P7-receipts.md`; `typed_options_field`; D100 witness P7.12 |
| P8 | `research/P8-design.md` §0, §2, §5–§9, §11; P7 Options; D42–D45 | `findings/P8-binding-catalog.md`, `findings/P8-receipts.md`; before/after bindings и boot order |
| P9 | `research/P9-design.md` §0–§3, §11; P8 binding catalog; D46–D49; `artifacts/architecture/files.csv` только как прежний baseline | `findings/P9-tenancy-map.md`, compatibility fingerprint и receipt; карта строится заново после P8 |
| P10 | `research/P10-design.md` §1–§12; P4 scope, P5 outbox, P7 Options; D50–D56 | `findings/P10-receipts.md`; отдельные rollback/engine/write-check/transaction witnesses |
| P11 | `research/P11-design.md` §1–§13; P10 catalogue; D57–D59 | `findings/P11-receipts.md`; canonical bytes, version stamps, corrupt/replay fixtures |
| P12 | `research/P12-design.md` §1–§10; P5 trace carrier, P6 zone map, P9 impersonation; D60–D64 | `findings/P12-receipts.md`; stable wire, bounded cardinality, audit stamp |
| P13 | `research/P13-design.md` §1–§11; P11 snapshot, P12 errors, P7 options; D65–D73, D104–D106 | `findings/P13-receipts.md`; contract snapshots, replay/cursor/auth negative matrix |
| P14 | `research/P14-design.md` §1–§15; P13 frozen operation contract, P5 outbox; D74–D84 | `findings/P14-receipts.md`; SQL CAS, bounded bulk, signing, SSRF, retry/replay proof |
| P15 | `research/P15-design.md` §1–§6; P1 rename registry, `docs/architecture/core-zones.md`; D85–D90 | `findings/P15-receipts.md`; exact FQCN sweep, BC/consumer proof |
| P16 | `research/P16-design.md` §1–§13; P15 terminal FQCN map, P14-produced API files; D91–D94 | `findings/P16-receipts.md`; physical move map, unchanged FQCN/autoload proof |
| P17 | `research/P17-design.md` §1–§12; actual P1–P16 receipts and released candidate; D95–D97 | candidate manifest, external consumer evidence, 27-row DoD, sealed G-RELEASE packet |

Для каждой строки также читать `phases/Pn/Pn.md` и item `Required Reads`.
`artifacts/Pn-design-rag/` — доказательство design-решений, не product-файлы.
Пока producer не исполнился, названный здесь receipt **ожидаемый выход**, а не
существующий вход. Существующие `artifacts/architecture/{files,symbols,tables,
config-keys,scorecard}.csv`, три графа и `verdicts.csv` — снимок P2; P9/P16
должны пересчитать живую карту, не копировать старые числа (P6: 109 → 146).

## Батчи реализации

Обозначения: `C` = Composer 2.5, `G46` = Grok 4.6, `G47` = Grok 4.7,
`S56` = GPT-5.6 Sol, `F` = frontier projection из §3.3. Один ряд — одна сессия; внутри ряда
указанный порядок обязателен. `review` означает свежую сессию без записи
предшествовавших product items.

| Сессия | Items | Модель | Конкретная работа и проверка на выходе |
|:--|:--|:--|:--|
| E07a | P7.1 | G47 | Ввести CoreOptions/SecretRef/bind по §3–§5 и сохранить динамический default; отдельный D100 proof следует в P7.12. |
| E07b | P7.12 | G47 | Доказать missing/null/zero TTL и central→tenant→central на одном repository. Если proof RED, ремонт P7.1. |
| E07c | P7.2 | G47 | TenancyOptions и три policy; сверить 75 читателей, дефолты и ключи с P7 §2 и `config-keys.csv`. |
| E07d | P7.3 → P7.4 → P7.5 | G46 | Три группы DI по готовому TenancyOptions; после каждого item сверить behavior и ключи. |
| E07e | P7.6 → P7.7 | C | AuthOptions и все объявленные access/matrix readers; сохранить ответы существующих consumer tests. |
| E07f | P7.8 → P7.9 | C | ModulesOptions и MediaOptions по тому же fromArray образцу; пакеты проверять по отдельности. |
| E07g | P7.10 | G47 | Boot validation и команда; secret redaction во всех трёх путях D40, две ошибки за один проход. |
| E07h review | P7.11 | G47 | Только после всех readers: блокирующий C1, planted probe и чистый проход; сверить все P7 receipts, затем P8 readiness. |
| E08a | P8.1 | G47 | Снять живой каталог register/boot после P7, объявить PackageRegistrar и сравнить с P8 §2. |
| E08b | P8.2 → P8.3 | G46 | Разнести register() tenancy по контекстам, не менять binding lifetime/identity; сверить каталог строка к строке. |
| E08c | P8.4 | G47 | Перенести boot() и сохранить порядок bootstrapper'ов/optional bindings; оба контекста DB проверить. |
| E08d | P8.5 | G46 | Modules registrar по живому каталогу, без нового поведения. |
| E08e review | P8.6 | G47 | Provider-size gate с негативной посадкой, before/after binding и boot-order diff; принять P9 только с полным каталогом. |
| E09a | P9.1 | G47 | Пересчитать post-P8 PHP map 100%, уникальные from/to, fingerprint и красные fixtures до переносов. |
| E09b | P9.2 | G47 | Runtime/Laravel: сохранить FQCN, routes, DB context и разрешённую D49 дельту; сверить fingerprint. |
| E09c | P9.3 | G47 | Membership: большая отдельная группа, сохранить FQCN и consumer behavior; сверить fingerprint. |
| E09d | P9.4 → P9.5 → P9.6 | G46 | Три непересекающиеся группы карты §6–§8; по каждой отдельно автозагрузка, сервисы и два DB lane. |
| E09e | P9.7 | G47 | Optional integration/wakeup и отрицательная посадка зависимости; relay control plane оставить условным D48. |
| E09f review | P9.8 | S56 | 100% map, zero DDL diff, unchanged public consumers, central/tenant behavior и planted gate; открыть P12 только после терминального proof. |
| E10a | P10.1 → P10.2 | G47 | Module-free catalogue, затем stable identity/snapshot; сверить deterministic reversal и публичные DTO до DDL. |
| E10b | P10.3 | S56 | Только с G-DDL: authoritative merge, новые таблицы и cache authority; pg rollback/constraint proof. |
| E10c | P10.4 | S56 | Dual-read/write, backfill и обратимый переход живых значений; аварийный restart и rollback matrix. |
| E10d | P10.5 | G47 | Storage resolver и индексная политика по измерению PostgreSQL, без самовольного DDL вне item. |
| E10e | P10.6 → P10.7 | S56 | Восемь write checks на двух engines, затем transactional mutation message; отказ/rollback и privacy payload. |
| E10f review | P10.8 | S56 | Пересчитать catalogue, миграции, checks, outbox и planted ratchets; сверить P11 вход. |
| E11a | P11.1 | G47 | Зафиксировать identity/version primitives и additive public surface. |
| E11b | P11.2 → P11.3 | G47 | Deterministic compiler → publication/freeze; digest одинаков при перестановке, frozen snapshot не мутирует. |
| E11c | P11.4 | G47 | Workflow stamps только в существующих durable carriers, legacy compatibility matrix. |
| E11d | P11.5 | S56 | Versioned wakeup registry, stamped envelope, quarantine и read-only preflight; unknown version не исполняется. |
| E11e | P11.6 → P11.7 | G46 | Tasks-workflow и notifications consume P11.5 по отдельности; записать два независимых replay receipts. |
| E11f review | P11.8 | S56 | Extension suite и ratchet против фактических producers/consumers; preflight P13. |
| E12a | P12.1 | G47 | Общий словарь ошибок, policy и zone classification; stable public vocabulary. |
| E12b | P12.2 → P12.3 | G46 | API и attributes projection; stable wire/error distinctions и негативы без новых HTTP полей. |
| E12c | P12.4 → P12.5 | G47 | Lifecycle/PSR-3, затем metrics/trace carrier/optional OTel; cardinality bound и отсутствие SDK без optional bridge. |
| E12d | P12.6 → P12.7 | G47 | Core audit stamp, затем tenancy session invariants/cleanup; actor/impersonator не теряются при switch. |
| E12e review | P12.8 | G47 | Error/observability/impersonation ratchets + отрицательные посадки; сверить P13 публичный error contract. |
| E13a | P13.1 → P13.2 | G47 | Compiled modes → единый invoker; один вызов имеет одинаковую семантику во всех проекциях. |
| E13b | P13.3 → P13.4 | G47 | Immutable profile → routes/discovery; capability и deprecation не расходятся между transport projections. |
| E13c | P13.5 | S56 | G-DDL перед idempotency store; schema/constraint и isolated pg proof. |
| E13d | P13.6 | S56 | Fingerprint, concurrent claim, replay и mutation ordering; stale/foreign tenant отказ. |
| E13e | P13.7 | G47 | Signed scoped cursor, tamper/scope/order negative matrix. |
| E13f | P13.8 | S56 | ResourceId и пять authorization layers, включая replay boundary D104–D106; cross-tenant отказ. |
| E13g | P13.9 → P13.10 | G47 | Error envelope и deterministic snapshots; сверить точные wire bytes. |
| E13h | P13.11 | G47 | Отдельный CI compatibility gate; planted breaking snapshot обязан падать. |
| E13i review | P13.12 | F | Финальная сверка producer/consumer и только затем G-API freeze `operation-contract/1`; пакет доказательств для P14. |
| E14a | P14.1 → P14.2 | G46 | Compiled recipe и pure estimator; legacy hash/default, overflow и reversed declarations. |
| E14b | P14.3 | S56 | G-DDL: схемы budget/bulk/webhook, ownership и изолированный pg proof. |
| E14c | P14.4 → P14.5 | S56 | Atomic hierarchical budget/settlement, затем admission в общем invoker; конкурентные CAS, refund и double-spend отрицания. |
| E14d | P14.6 | S56 | SQL bulk state machine: CAS, cancel/retry, lease loss и terminal invariants. |
| E14e | P14.7 → P14.8 | G47 | Transfer import limits/cancel, затем public export port с bounded capture; независимые receipts. |
| E14f | P14.9 → P14.10 | G47 | Optional adapter/worker, затем endpoints и result authorization; retry не повторяет принятый эффект. |
| E14g | P14.11 → P14.12 | G47 | Объявленные topics/subscription policy, затем transactional outbox fan-out; один source event не рождает неограниченные delivery identities. |
| E14h | P14.13 | S56 | Standard Webhooks bytes/signature и synthetic rotation; отдельный security review. |
| E14i | P14.14 | S56 | HTTPS pinning/SSRF: DNS rebinding, redirects, private ranges и negative transport fixtures; отдельный security review. |
| E14j | P14.15 → P14.16 | S56 | Leased retry/dead-letter, затем management/history/replay; replay ограничен и не обходит policy. |
| E14k | P14.17 | G47 | Retention на изолированных stores, с инвариантами восстановления. |
| E14l | P14.18 | G47 | Отдельный snapshots/compatibility gate с planted failures. |
| E14m | P14.19 | S56 | Только G-SECRET: typed options и activation wiring без реальных секретов/live sends. |
| E14n | P14.20 | G47 | Интегрировать поверхности/fixtures и прогнать package + root lanes. |
| E14o review | P14.21 | S56 | Свести SQL, security, bulk, webhook и compatibility receipts; переход к P16 только после полного proof. |
| E15a | P15.1 | G47 | Modules public set и девять discovery consumers; G-API-P15 перед сменой FQCN. |
| E15b | P15.2 → P15.3 → P15.4 → P15.5 → P15.6 | C | Пять независимых rename lanes по §2; для каждого search old/new, BC и пакетные consumer tests. |
| E15c | P15.7 → P15.8 | G47 | Wakeup и API public FQCN; G-API-P15 перед каждым item, wire invariance. |
| E15d | P15.9 | C | Tenancy result rename и invariant сериализации. |
| E15e | P15.10 | G47 | Core semantic-zone moves, G-API-P15, весь reverse-consumer sweep. |
| E15f | P15.11 → P15.12 | G47 | Только accepted owner branch D89/D90; каждый conditional item отдельно terminal/skipped, без вывода имени по догадке. |
| E15g | P15.13 | G46 | Сверить реестр, surfaces и все conditional outcomes. |
| E15h review | P15.14 | G47 | Full qualification и negative completeness proof; P16 получает окончательную FQCN map. |
| E16a | P16.1 → P16.2 → P16.3 | C | Три Profile-B package move по живой карте; после каждого FQCN/autoload/test invariant. |
| E16b | P16.4 → P16.5 → P16.6 | G46 | API Profile-C: operation, Cost/Bulk, Webhooks строго по producer order; сохранить public FQCN. |
| E16c | P16.7 → P16.8 | C | Modules и CRM physical maps; PSR-4 и reverse consumers по каждому пакету. |
| E16d | P16.9 | C | Panels no-move verdict по измеренным фактам. |
| E16e | P16.10 | G47 | Atomic dev-only Testing cutover после P16.3/P16.8; package suites не импортируют production harness. |
| E16f | P16.11 | C | Profile-B/Transfer growth map по живому census. |
| E16g | P16.12 | G46 | Structural metadata и path-bearing gates по живому дереву. |
| E16h review | P16.13 | G47 | Полный structural qualification, unchanged FQCN и negative proofs; P17 cohort готов. |
| E17a | P17.1 | S56 | Заморозить exact release cohort и candidate repository; digest далее неизменен. |
| E17b | P17.2 → P17.3 → P17.4 → P17.5 | G47 | External extension, REST/MCP, bulk/webhook и независимый consumer на одном candidate; каждый fixture закрыть отдельно. |
| E17c | P17.6 → P17.7 | G47 | Pristine install profiles и upgrade path на том же candidate, без локальной автозагрузки монорепо. |
| E17d | P17.8 | G46 | Scorecard и docs измерить по независимым outputs, не исправляя fixture под зелёный результат. |
| E17e | P17.9 → P17.10 | G47 | Заморозить 27-row manifest, затем исполняемый DoD с anti-false-green посадками. |
| E17f review | P17.11 | F | Sealed preflight: exact candidate, все 27 assertions, owner-review packet без внешней записи. |
| E17g manual | P17.12 | F | Только exact G-RELEASE: forward-only publication, ledger и exact-tag consumer proof по item-спеке. |

### Межфазные контрольные точки

- **P8.6 → P9.1:** P7 config gate, P8 bindings/boot order и живой source tree.
- **P10.8 → P11.1:** reversible evolution, authoritative catalogue и outbox privacy.
- **P12.8 → P13.1:** error wire, operation observer и impersonation provenance.
- **P13.12 → P14.1:** freeze имеет owner grant и exact snapshots.
- **P14.21 → P16/P17:** bulk/webhook security и compatibility receipts; P15
  может стартовать после своих producer dependencies из `roadmap.md` §2.
- **P16.13 → P17.1:** окончательные пути/FQCN и candidate без локальных
  source path зависимостей.

Приоритеты этих сверок — конкретные failures из `findings/execution-audit-P1-P5-2026-09-22.md`:
известный RED root gate не закрывается как GREEN, phase final читает реальный
результат, и публичные/provider изменения проверяются через reverse consumers.
