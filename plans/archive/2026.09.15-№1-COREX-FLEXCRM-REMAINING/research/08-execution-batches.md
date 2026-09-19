# Группы выполнения и сохранение контекста — D18

RAG:— Репозиторный анализ. Основание: прямое поручение владельца после whole-plan review
сократить лишние запуски, сохранив разумные границы; затем commit/push всей подготовленной работы.

## Исходная находка и evidence

До исправления: 78 items, 57 routing units (15 групп и 42 solo). Исходные точные rows,
route maxima, spec/routing digests и статические read sets сохранены в
artifacts/astra-whole-plan-review/batch-routing.before.json до изменения Routing.
Отдельные P1/P2 intake/seal и P3/P4/P6/P8 final receipts разделяли общий corpus без
необходимости нового root. Cold installed fixture ошибочно использовался как довод
для отдельного запуска агента. Коммит, item closure и новая phase сами по себе не
требуют нового root по текущему Task; phase boundary запрещает лишь один batch через фазы.

Owning rules: configured Swissknifeman packages/task/runtime/plan-protocol/SKILL.md,
§5 Routing и раздел continuity; task/task_contract.py compile_batch_routing/admit_batch.
Это настройка данного плана, shared runtime не изменяется. Исходные D1–D17 сохраняются.

## Принятое правило

Одна группа — общий рабочий контекст и рекомендуемый execution scope. Она не является
одной транзакцией, общим разрешением на запись или обязанностью удерживать всё в памяти.
Сохраняются все 78 item contracts, отдельные outputs/checks/reviews/closure/journal,
исходные item model/effort/Exec/Review и все G-* gates. Маршрут запуска группы — maxima
модели, effort, review и union capabilities по нативному compiler. Поэтому P1 whole-group
recommendation использует frontier/high (gpt-5.6-sol/high), хотя первый P1.1 сам medium.

Все обычные группы recommended. Работа последовательная; эти группы не предписывают
субагентов. Перед каждым item: актуальный design gate, реальные producer outputs и
разрешения, свежие item state/bundle, actual route и штатный admission. После GREEN
закрыть item, сохранить checkpoint, получить bundle следующего реально готового item
и продолжить в том же root в пределах явно запрошенной группы. Нельзя выводить future
artifacts в bundle или объявлять predecessor GREEN ради запуска диапазона.

Нативный admit_batch для recommended диапазона проверяет readiness каждого члена уже
при admission. Поэтому full-chain P1.1-P1.4 сейчас BATCH_STALE: P1.1 ещё не произвёл
reconciliation. Рабочий cold-start carrier — P1.1 плюс явно заявленный scope всей группы;
P1.2/3/4 получают отдельный повторный admission после producer closure в том же root.
Это несколько команд внутри одной сессии. Текущий plan_lint_checks/engine.py
требует перечислить весь remaining group scope в handoff.Next даже для recommended
batch, хотя admit_batch отдельно поддерживает ready subsets. Поэтому semantic Next
перечисляет P1.1 P1.2 P1.3 P1.4 как полный запрашиваемый scope, а copyable native prompt
начинает с P1.1 и явно поручает successive admissions оставшихся членов. Это не
утверждение, что весь range runnable сейчас, и не обход producer checks. Shared runtime
не исправляется этим поручением.  Диапазон можно отправлять как одну команду
только если весь requested ready subset прошёл штатный admission. Compiler ALLOW состава
не доказывает наличие actual producer/authority; runtime readiness остаётся обязательной.

`continue-root` — default внутри разрешённого ready scope. Unknown context telemetry
не доказывает необходимость новой сессии. При реально недостаточном контексте сохранить
item checkpoint и оставшийся scope, выбрать cold-start-root: context-budget через
штатный continuity reducer; не закрывать unfinished item и не читать весь план заново
без нужды. Число items не задаёт memory budget: P1 corpus особенно велик и может
потребовать дополнительных сессий. Чтение порциями с доказуемым progress допустимо;
группировка не отменяет обязательные reads и не заменяет их механическим hashing.

`cold installed` означает изолированную fixture/DB/vendor среду, а не новый агент.
Результаты этой проверки можно получить и закрыть в том же root. На границе phase
закрыть eligible phase, затем отдельно допустить successor; новый root нужен только
по фактическому continuity решению, а переход за scope требует явного нового задания.
Design-root-terminal после текущего design остаётся корректной fresh-session границей.

## Группы и реальные границы

Каждый диапазон ниже — envelope, а не безусловный runnable request. Внутри max5 items;
это наблюдаемое свойство выбранного разбиения, не новый общий лимит Swissknifeman.

| Batch | Items | Почему вместе / где остановиться |
|:--|:--|:--|
| B-P1-QUALIFY | P1.1-P1.4 | Один frozen source corpus: reconciliation → atoms/equivalents → seal; per-item closure внутри сессии. |
| B-P2-PROPOSALS | P2.1-P2.4 | Один P1 lock/BC/trust context: intake → proposals → catalogue; public adoption grants остаются отдельными gates. |
| B-P3-LOCAL | P3.1-P3.2 | Общий storage/physical PG harness; независимые isolation и claim reviews сохраняются. |
| B-P3-IDENTITY | P3.3-P3.7 | Общие transport/pair/fence/cache inputs; read-only итоговый seal переиспользует проверенные branch receipts, требует P3.1/2. |
| solo | P4.1 | Headless provider/guest-policy catalogue; соседний media IO требует другого storage/fault harness, coupling не окупается. |
| B-P4-MEDIA | P4.2-P4.3 | Общий media/storage/file-lock/decode fault harness; lifecycle → conversions. |
| B-P4-NOTIFICATIONS | P4.4-P4.6 | Общий outbox/channel/digest SQL context; P4.6 только после P5.2/5.5, при их отсутствии закончить ready P4.4/5 subset. |
| B-P4-VIEWS-SEAL | P4.7-P4.8 | Core saved-view/descriptor checks → installed extension seal; нужны остальные branch receipts, cold fixture не новая сессия. |
| solo | P5.1 | Чужой immutable engine ABI/source grant может ожидаться независимо; его ожидание не связывает готовый wakeup utility. |
| B-P5-WAKEUP | P5.2-P5.3 | Один runner/SQL/context harness: local sweep → root hints; physical resolver нужен только выбранной ветке. |
| B-P5-CONSUMERS | P5.4-P5.6 | Общий wakeup consumer/conformance harness: engine binding и digest bridge → installed seal; missing engine не мешает ready P5.5. |
| B-P6-FIELDS | P6.1-P6.3 | Квалификация source/ABI → field binding → schema bridge в одном engine/catalog context; ready transfer/search не ждут missing engine. |
| B-P6-TRANSFER | P6.4-P6.5 | Общий codec/storage/SQL target context: resumable import → coherent export. |
| B-P6-SEARCH | P6.6-P6.8 | Общий query/provider/rebuild harness → installed data seal после остальных branches; callbacks/ACL/fault reviews сохраняются. |
| B-P7-RECURRENCE | P7.1-P7.2 | Квалифицированный source/recurrence ABI → pure bounded adapter; общий immutable source/context. |
| B-P7-TASKS | P7.3-P7.4 | Existing task model/actions/transaction harness → recurrence materializer; binding только после P7.2 и P5.2. |
| B-P7-INTL | P7.5-P7.7 | Общие intl/config/decimal/dataset inputs; sequential shared writes, scoped source/license gates. |
| B-P7-BRIDGES-SEAL | P7.8-P7.9 | Selected intl/search/label adapters → installed utility seal; потребляются актуальные остальные branch receipts. |
| B-P8-TOOLS | P8.1-P8.4 | Registry/API source qualification → declarations → catalogue → guarded invocation; последовательная квалификация без повторного полного чтения. |
| B-P8-INTEGRATION | P8.5-P8.7 | Registry diagnostics и selected contributions → installed tool seal; P8.5 готов независимо, P8.6 ждёт только выбранных producers. |
| B-P9-BUILD | P9.1-P9.2 | Source/vector/overlay freeze → exact split/dist build; один export-map context, no remote dispatch. |
| B-P9-LOCAL-PROOF | P9.3-P9.4 | Matrix/CI helpers → pristine/populated installed fixtures; общий actual build/recipe context, bounded отдельно от export implementation. |
| solo | P9.5 | Manual G-RUNNER: удалённый dispatch/current credentials и собственный finite grant; не смешивать с private authority. |
| solo | P9.6 | Manual G-PRIVATE: отдельный конфиденциальный graph и finite read grant; не объединять authority с runner. |
| solo | P9.7 | Локальный publication packet после двух manual branches; соседние Exec=manual, готовые bytes до G-RELEASE. |
| solo | P9.8 | Manual G-RELEASE: необратимые split/tag/assets и recovery; отдельный exact publication grant. |
| solo | P9.9 | Локальный delivered-install/passport после manual publication; actual remote result появляется на другой authority boundary. |
| B-P10-TARGETS | P10.1-P10.3 | Один released/profile/import context: verifier/intake → central/cloud receipts, independent ready subsets. |
| B-P10-AP | P10.4-P10.6 | Один deployed target/census и fault receipt context: security → races/restore → FE; все исходные budgets и reviews обязательны. |
| solo | P10.7 | Полный закрытый monthly census — отдельное календарное ожидание; нет busy polling или удержания остальных branches. |
| B-P10-OPS-SEAL | P10.8-P10.9 | Provider operation imports → effective seal на том же verifier; P10.8 не ждёт month, P10.9 ждёт все actual branches. |
| B-P11-QUALIFY | P11.1-P11.4 | Один source/vector/consumer/atom corpus: verifier → pins → tests → ledger; последовательный re-admission по готовности. |
| solo | P11.5 | Новая finite foreign write surface и full независимый review exact three-file diff; не повышать light qualification batch до full ради packet. |
| solo | P11.6 | Manual G-CONSUMER-RECONCILE: отдельная accepting/write authority, exclusivity и foreign commit. |
| solo | P11.7 | Локальный final consumer seal после manual foreign commit; anchor становится доступен после отдельной write boundary. |

## Порядок при перекрёстных зависимостях

- P1.1 → P1.2/3 → P1.4; затем P2.1 → P2.2/3 → P2.4. Следующий item в том же
  root только после настоящего closure/refresh/admission; два phase-local envelopes.
- P3 local даёт storage для media/data. P4.4/5 и P5.2 открывают P5.5, затем P4.6.
  Если всё исполняет один root, завершить ready P4.4/5, перейти к отдельно разрешённому
  P5 subset, вернуться к P4.6 по свежему checkpoint. Нельзя ждать P4.6 внутри P5.5.
  При ручном запуске каждого envelope notification group обычно займёт два захода.
- P5.1 missing engine не блокирует P5.2/3 и ready P5.5. P5.6 ждёт полный actual set;
  grouped seal не позволяет скрыть отсутствующий engine report.
- P6 branches и P7 pure/intl независимы от чужих необязательных engines. Shared
  foundation D17 по-прежнему имеет одного serial writer; группировка не параллелит Files.
- P7.4 требует P7.2 и P5.2, P7.8 — только выбранные P6/P4 capabilities. P8.5 можно
  выполнить отдельно от неготовых optional tool contributions; P8.7 ждёт все required rows.
- P9.5, P9.6, P9.8 и P11.6 остаются четырьмя manual authority boundaries. Их grants
  различны; group scope и настоящий commit/push плана не разрешают будущие remote writes.
- P10.7 ждёт полный закрытый monthly census. P10.8 идёт независимо; P10.9 остаётся
  pending до monthly/других actual receipts. Готовая P10.8 не требует повторного выполнения.

## Измерение и компромиссы

Точный baseline ниже рассчитан по сохранённым до правки read sets: существующие локальные
файлы из Inputs/Required Reads плюс plan/phase/item carriers; одинаковый resolved path
читается один раз на envelope. Path selectors conservatively считаются whole-file;
будущие reports, runtime code exploration, logs, tests и tool/system prompts не оценены.
Это сравнение повторного чтения одних и тех же известных bytes, не billable tokens,
не provider context telemetry, не утверждение всех runtime costs и не обещание числа сессий.

| Вариант | Envelopes | Сумма известных cold-read bytes |
|:--|--:|--:|
| Каждый item отдельно | 78 | 42 138 078 |
| Предыдущий Routing | 57 | 32 173 559 |
| D18 | 35 | 20 657 887 |

D18: 24 группы + 11 solo; на22 envelope меньше (38,6%), расчётное повторное чтение
уменьшается на35,8% относительно прежнего Routing. 35 — плановое число envelopes при
готовых inputs, не гарантированные35 fresh sessions. P4/P5 interleave, P10 calendar,
actual context/runtime failures и external grants могут добавлять заходы; continue-root
между отдельно допущенными ready scopes может уменьшать число fresh roots.

Прежний57-unit вариант отвергнут за лишние intake/seal launches. Один giant batch на
весь план отвергнут за phase/Exec/authority boundaries и рост working context. Снижение
models/reviews отвергнуто: экономим повторный вход, не уменьшаем качество proof. Малые
steps иногда выполняются на максимальном route группы; сохранённый semantic context
сопоставляется с этой ценой по фактической runtime telemetry, не по выдуманным тарифам.
P9 build отделён от matrix/installed breadth; P11 full packet review отделён от light
qualification, чтобы не растягивать full depth на четыре более простых receipt steps.

## Проверка и следующий запуск

Native compiler должен дать78 items/35 phase-local adjacent units без смешения Exec;
root/phase tables совпадают, индивидуальные route/review значения неизменны. Проверить
ALLOW текущего P1.1, BATCH_STALE неготовой цепочки, отказ cross-group/cross-Exec запроса
и stale receipt. Эти проверки не стартуют execution и не создают producer outputs.
Named phase checks/lint/downstream/views, current finish обязательны после правки.

Единственная рекомендация: начать B-P1-QUALIFY с runnable P1.1 на Sol/high и продолжить
P1.2–P1.4 в том же root по готовности. Handoff содержит exact first carrier и полный
owner-requestable scope; ни один execution item текущая design сессия не выполняет.
