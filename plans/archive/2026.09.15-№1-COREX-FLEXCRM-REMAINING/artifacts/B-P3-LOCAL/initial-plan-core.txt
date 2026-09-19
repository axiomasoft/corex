# 2026.09.15-№1-COREX-FLEXCRM-REMAINING — План №1 — Оставшиеся reusable требования CoreX для FlexCRM

## 0. Meta

| Поле | Значение |
|:--|:--|
| Plan ID | 2026.09.15-№1-COREX-FLEXCRM-REMAINING |
| Short ID | PLAN1 |
| Title | План №1 — Оставшиеся reusable требования CoreX для FlexCRM |
| Layout | v2 |
| Document Type | Executable Master Plan |
| Authoring Model | gpt-6-astra (Astra shell); gpt-5.6-sol/high (P1/P2/P3/P4/P5/P6/P7/P8/P9/P10/P11 detail, provider-attested 2026-09-15) |
| Repository | corex |
| Home | repo:corex (новый локальный план; Brain sync не выполнялся) |
| Related Packages | corex/core, modules, tenancy, api, tasks; остальные только после qualification |
| Execution Mode | full-plan-first: P1–P11 detail + integrated design-finish before any item execution; then dependency DAG |
| Target Operator Classes | frontier/high gpt-5.6-sol для детализации; execution models выбирает Sol |
| Approval Owner | Dmitry Vostrikov; external ownership/authority отдельно по gate ledger |
| visibility | private |
| Paused By | — |

## 1. Context

Задача — закрыть **оставшиеся универсальные CoreX-зависимости** фиксированного набора требований
FlexCRM и затем передать дальнейшее развитие приложению. Это верхний design слой Astra:
P1 и P2 детализированы Sol по четыре execution contracts, P3 — семь, P4 — восемь, P5 — шесть, P6 — восемь, P7 — девять, P8 — семь, P9 — девять, P10 — девять; P11 — семь; все фазы имеют детальные спецификации, whole-plan review и межфазовые исправления описаны D17; актуальность design-finish проверяется scripts/task-check.py --check design.
Реализация/публичные API/DDL/dependencies/releases/чужие планы текущей сессией не меняются.
Матрица из 52 R-строк включает всю открытую P6.5–P6.26 программу, P9.1/P11.7 и B38 gaps;
atomic AC index сохраняет исходные критерии. P1 замораживает их exact owner/evidence mapping.
Исторические GREEN/архивы и новые local candidates не заменяют package/production acceptance.
Принятые foundations A01–A09 сохраняются; Sol проектирует только доказанные дельты.
CoreX не содержит Flex namespaces/entities/storage/identity/permissions/events/transport assumptions.
Product modules, standalone engines и central/cloud operations имеют отдельных владельцев.
Финальная P11 требует immutable release+consumer receipts и нулевого остатка CoreX-зависимостей.
Только после этого допустима команда: дальнейшее развитие — в FlexCRM; новые CoreX требования
оформляются отдельно. Milestones FLEX-2 этим планом не закрываются.

## 2. Execution Rules

- Current owner scope: полный design review/research/repair по brief/02-astra-whole-plan-review.md (Astra/high); D17 описывает точные исправления. Scope не включает execution items; после actual finish рекомендуется B-P1-QUALIFY на frontier/high = gpt-5.6-sol/high: первый ready carrier P1.1, затем P1.2–P1.4 в том же root по D18.
- P1 и P2 имеют по 4 detailed specs, P3 — 7, P4 — 8, P5 — 6, P6 — 8, P7 — 9, P8 — 7, P9 — 9, P10 — 9, P11 — 7; execution admission для всех items закрыта до полного дизайна P1–P11 и design-finish по D4. P1 mandatory Perplexity receipt verified дополнительным запросом (D5); P2 mandatory neutral RAG verified (D6/D7); P3 mandatory neutral RAG verified (D8); P4 mandatory neutral RAG verified (D9); P5 mandatory neutral RAG verified after owner-resumed general/routing/digest verification (D10); P6 mandatory neutral RAG verified after initial incomplete answer and separate transfer/search queries (D11); P7 mandatory neutral RAG verified after notified incomplete replies and topic queries (D12); P8 mandatory neutral RAG verified after notified incomplete initial answer and separate guard/schema queries (D13); P9 mandatory neutral RAG verified after owner-notified incomplete responses and complete short query/direct sources (D14); P10 mandatory neutral RAG verified with supplementary queries/direct primary sources (D15); P11 neutral mandatory RAG verified after notified failed/incomplete retrievals and awaited successful source-selected response/direct primary checks (D16). Whole integrated review — D17; current readiness requires the byte-current finish check.
- **Perplexity:** только общие практики/конкретные технические вопросы/публичные примеры; CoreX — внутренняя разработка, факты о ней проверяются локально (D6).
- docs/dev-doctrine.md + research/04-architecture.md обязательны; upward dependency и product literals запрещены.
- Матрица research/01-traceability.md — owner требований; source inventory — provenance; статусы — journal/generated views.
- Каждый exact CoreX gap связан с R/AC; отсутствие symbol name не доказательство отсутствия equivalent implementation.
- P1 freezes requirement set, P2 выбирает минимальные contracts; accepted A01–A09 не перепроектируются без воспроизведённой причины.
- Public Contracts/major, DDL, dependency/source/private credentials и release требуют scoped gates для actual delta.
- Future Files замораживаются Sol до execution; не создавать blanket repo-wide allowlist.
- Test topology per-package; D34 оба root lanes + affected package; D98 mutation при src; no weakened floors.
- Installed proof включает independent non-CRM consumer; два solutions FlexCRM не заменяют этот тест универсальности.
- Runtime/source, local tests, installed candidate, released package, hosted CI и live deployment — разные evidence classes.
- External/central/cloud gates — research/03-gates.md; missing receipt = named owner + exact resume, не fake acceptance.
- Продолжать независимые ready subsets по DAG; ожидание membership deployment не задерживает pure utilities.
- Новый механизм расширения/девятый extender только RFC; standalone engines не клонируются в CoreX.
- Архивы/чужие планы сохраняются; foreign mutations только будущая P11 в owning FlexCRM scope после accepted dependencies.
- Не закрывать FLEX-2 milestones; P11 меняет dependency receipts/pins/handoff, aggregate product acceptance отдельно.
- GREEN только по Phase GREEN и exact item receipts; подготовительные NO-GO packets не завершают P9/P10/P11.
- Review depth не phase-audit consent; независимый audit только с отдельным owner-bound admission.
- Design root завершается handoff; не запускает Sol/исполнителя автоматически.

## Обязательная политика дизайна — D4 / D6

- **Область Perplexity — обязательное правило для P1–P11 (D6):** CoreX — внутренняя разработка. У Perplexity не спрашивать про CoreX, его устройство, API, gaps, состояние, публикацию или принятие: он не является источником внутренних фактов. Perplexity используется только для общих лучших практик разработки, конкретных технических вопросов и публичных примеров. Запрос формулировать нейтрально, без имени CoreX/FlexCRM, внутренних ID/R/AC, путей, схем и профилей; локальный контекст не передавать как предмет веб-исследования. Применение практики к CoreX — отдельный локальный вывод из source/tests/receipts, с RAG:—; внешний источник подтверждает только названную общую практику, с RAG:✅. Mandatory RAG D4 и уведомление о сбое D5 сохраняются.

- **Perplexity RAG обязателен при каждой детализации P1–P11, включая P1**, до итогового принятия phase design. Не откладывать external design verification на исполнение.
- Для каждого ключевого architectural choice: Perplexity query/реальный successful response+digest, минимум два независимых primary sources, direct verification, alternatives/selection/failure modes и cross-phase impact. Два пересказа одного источника не считаются независимыми подтверждениями.
- Durable result: research/Pn-design-rag.md и artifacts/Pn-design/perplexity-response.json. Записать **Perplexity: successful**, **Search artifact:** plan-relative response path и **SHA256:** raw artifact digest только при фактическом успехе. Сохранить ## Primary sources (Origin / URL / Supported claim), ## Alternatives и ## Cross-phase impact. Отдельно назвать unresolved premises. Tool/login failure = pending; fallback не закрывает mandatory Perplexity step.
- **Если Perplexity/RAG не сработал или подтвердить claim не удалось — сразу уведомить владельца (фаза/query/error/unconfirmed conclusions), оставить RAG pending и закрыть gap отдельным дополнительным verification query.** Не принимать design/исполнение и не подменять mandatory шаг fallback. После автовхода повторить; success и первоисточники сохранить. D5.
- Repository facts — rg/source/tests/recorded receipts; Perplexity не доказывает наличия/отсутствия локального поведения и не превращает test source в executed proof.
- **Сначала полностью детализировать P1–P11, затем единая проверка дизайна и design-finish, только потом исполнять P1.1 и другие items.** Это распространяется и на research/freeze execution items P1.
- Единая картина: research/05-integrated-design.md; каждый R имеет owner/destination, phase producer→consumer contracts/versions/failure model, no dependency cycles, non-CRM scenario, compatibility/gates/risk owner. Проверка всех межфазовых пересечений и pre-mortem до finish; нет обязательного independent phase audit без consent.
- Дизайн читает available source/R/AC/A/E evidence; produced artifacts предыдущих execution items описываются как future contracts и не исполняются ради детализации. Их actual GREEN остаётся prerequisite execution. Не подделывать producer output; неизвестный material choice снять в дизайне.
- До product mutation каждого item: `python3 plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/scripts/task-check.py --check design`. Отсутствующая детализация/RAG/общий finish = DESIGN_PENDING, выполнение не начинается. После изменения design inputs заново проверить affected integrated delta/finish hashes.
- Finish artifact: artifacts/design-finish.md, **Design finish: verified**, ## Design inputs (Path / SHA256). D17/scripts/task-check.py::design_inputs defines the exact full stable input closure, including all indirect research/source/decision/validator/raw evidence inputs. Self/volatile journal/status/state/bundle/handoff/check logs excluded; own generated views checked separately. Structural checks and recorded semantic integration review must pass before sealing; changed stable inputs invalidate finish.

## 3. Routing

D18/research/08-execution-batches.md:35 context envelopes (24 groups +11solo), не78
обязательных новых сессий. Перед запуском группы прочитать этот execution guide.
Первый реально готовый item допускается отдельно; его producer consumers продолжаются
в том же root после per-item closure/bundle/admission. Route группы — maxima строк.
Ready subsets не требуют ещё не созданных outputs будущих членов. Нельзя допустить
whole dependency chain заранее, когда native admit_batch возвращает BATCH_STALE.
Каждый group scope должен быть явно включён в invocation владельца; текущий запрос
разрешает группировку и commit/push дизайна, не выполнение items.


Фазовая детализация P1–P11: **frontier/high = gpt-5.6-sol/high**.
P1–P11 строки ниже — реальные execution items; ни одна не допущена до D4 finish. Sol выбирает models/effort,
Batch/Review по сложности, integration/security/public риску. Детальный phase routing — в каждой фазе.

| Batch | Items | Model class/effort | Exec | Review | Почему |
|:--|:--|:--|:--|:--|:--|
| B-P1-QUALIFY | P1.1 | implementation/medium | plan-exec | light | recommended: Один frozen source corpus: reconciliation → atoms/equivalents → seal; per-item closure внутри сессии. |
| B-P1-QUALIFY | P1.2 | frontier/high | plan-exec | light | recommended: Один frozen source corpus: reconciliation → atoms/equivalents → seal; per-item closure внутри сессии. |
| B-P1-QUALIFY | P1.3 | frontier/high | plan-exec | light | recommended: Один frozen source corpus: reconciliation → atoms/equivalents → seal; per-item closure внутри сессии. |
| B-P1-QUALIFY | P1.4 | frontier/high | plan-exec | light | recommended: Один frozen source corpus: reconciliation → atoms/equivalents → seal; per-item closure внутри сессии. |
| B-P2-PROPOSALS | P2.1 | frontier/high | plan-exec | light | recommended: Один P1 lock/BC/trust context: intake → proposals → catalogue; public adoption grants остаются отдельными gates. |
| B-P2-PROPOSALS | P2.2 | frontier/high | plan-exec | light | recommended: Один P1 lock/BC/trust context: intake → proposals → catalogue; public adoption grants остаются отдельными gates. |
| B-P2-PROPOSALS | P2.3 | frontier/high | plan-exec | light | recommended: Один P1 lock/BC/trust context: intake → proposals → catalogue; public adoption grants остаются отдельными gates. |
| B-P2-PROPOSALS | P2.4 | frontier/high | plan-exec | light | recommended: Один P1 lock/BC/trust context: intake → proposals → catalogue; public adoption grants остаются отдельными gates. |
| B-P3-LOCAL | P3.1 | implementation/high | plan-exec | full | recommended: Общий storage/physical PG harness; независимые isolation и claim reviews сохраняются. |
| B-P3-LOCAL | P3.2 | implementation/high | plan-exec | full | recommended: Общий storage/physical PG harness; независимые isolation и claim reviews сохраняются. |
| B-P3-IDENTITY | P3.3 | implementation/high | plan-exec | full | recommended: Общие transport/pair/fence/cache inputs; read-only итоговый seal переиспользует проверенные branch receipts, требует P3.1/2. |
| B-P3-IDENTITY | P3.4 | implementation/high | plan-exec | full | recommended: Общие transport/pair/fence/cache inputs; read-only итоговый seal переиспользует проверенные branch receipts, требует P3.1/2. |
| B-P3-IDENTITY | P3.5 | implementation/high | plan-exec | full | recommended: Общие transport/pair/fence/cache inputs; read-only итоговый seal переиспользует проверенные branch receipts, требует P3.1/2. |
| B-P3-IDENTITY | P3.6 | implementation/high | plan-exec | full | recommended: Общие transport/pair/fence/cache inputs; read-only итоговый seal переиспользует проверенные branch receipts, требует P3.1/2. |
| B-P3-IDENTITY | P3.7 | implementation/medium | plan-exec | light | recommended: Общие transport/pair/fence/cache inputs; read-only итоговый seal переиспользует проверенные branch receipts, требует P3.1/2. |
| solo | P4.1 | implementation/high | plan-exec | full | recommended: Headless provider/guest-policy catalogue; соседний media IO требует другого storage/fault harness, coupling не окупается. |
| B-P4-MEDIA | P4.2 | implementation/high | plan-exec | full | recommended: Общий media/storage/file-lock/decode fault harness; lifecycle → conversions. |
| B-P4-MEDIA | P4.3 | implementation/high | plan-exec | full | recommended: Общий media/storage/file-lock/decode fault harness; lifecycle → conversions. |
| B-P4-NOTIFICATIONS | P4.4 | implementation/high | plan-exec | full | recommended: Общий outbox/channel/digest SQL context; P4.6 только после P5.2/5.5, при их отсутствии закончить ready P4.4/5 subset. |
| B-P4-NOTIFICATIONS | P4.5 | implementation/high | plan-exec | full | recommended: Общий outbox/channel/digest SQL context; P4.6 только после P5.2/5.5, при их отсутствии закончить ready P4.4/5 subset. |
| B-P4-NOTIFICATIONS | P4.6 | implementation/high | plan-exec | full | recommended: Общий outbox/channel/digest SQL context; P4.6 только после P5.2/5.5, при их отсутствии закончить ready P4.4/5 subset. |
| B-P4-VIEWS-SEAL | P4.7 | implementation/high | plan-exec | full | recommended: Core saved-view/descriptor checks → installed extension seal; нужны остальные branch receipts, cold fixture не новая сессия. |
| B-P4-VIEWS-SEAL | P4.8 | implementation/high | plan-exec | light | recommended: Core saved-view/descriptor checks → installed extension seal; нужны остальные branch receipts, cold fixture не новая сессия. |
| solo | P5.1 | implementation/high | plan-exec | light | recommended: Чужой immutable engine ABI/source grant может ожидаться независимо; его ожидание не связывает готовый wakeup utility. |
| B-P5-WAKEUP | P5.2 | implementation/high | plan-exec | full | recommended: Один runner/SQL/context harness: local sweep → root hints; physical resolver нужен только выбранной ветке. |
| B-P5-WAKEUP | P5.3 | implementation/high | plan-exec | full | recommended: Один runner/SQL/context harness: local sweep → root hints; physical resolver нужен только выбранной ветке. |
| B-P5-CONSUMERS | P5.4 | implementation/high | plan-exec | full | recommended: Общий wakeup consumer/conformance harness: engine binding и digest bridge → installed seal; missing engine не мешает ready P5.5. |
| B-P5-CONSUMERS | P5.5 | implementation/high | plan-exec | full | recommended: Общий wakeup consumer/conformance harness: engine binding и digest bridge → installed seal; missing engine не мешает ready P5.5. |
| B-P5-CONSUMERS | P5.6 | implementation/medium | plan-exec | light | recommended: Общий wakeup consumer/conformance harness: engine binding и digest bridge → installed seal; missing engine не мешает ready P5.5. |
| B-P6-FIELDS | P6.1 | implementation/high | plan-exec | light | recommended: Квалификация source/ABI → field binding → schema bridge в одном engine/catalog context; ready transfer/search не ждут missing engine. |
| B-P6-FIELDS | P6.2 | implementation/high | plan-exec | full | recommended: Квалификация source/ABI → field binding → schema bridge в одном engine/catalog context; ready transfer/search не ждут missing engine. |
| B-P6-FIELDS | P6.3 | implementation/high | plan-exec | full | recommended: Квалификация source/ABI → field binding → schema bridge в одном engine/catalog context; ready transfer/search не ждут missing engine. |
| B-P6-TRANSFER | P6.4 | implementation/high | plan-exec | full | recommended: Общий codec/storage/SQL target context: resumable import → coherent export. |
| B-P6-TRANSFER | P6.5 | implementation/high | plan-exec | full | recommended: Общий codec/storage/SQL target context: resumable import → coherent export. |
| B-P6-SEARCH | P6.6 | implementation/high | plan-exec | full | recommended: Общий query/provider/rebuild harness → installed data seal после остальных branches; callbacks/ACL/fault reviews сохраняются. |
| B-P6-SEARCH | P6.7 | implementation/high | plan-exec | full | recommended: Общий query/provider/rebuild harness → installed data seal после остальных branches; callbacks/ACL/fault reviews сохраняются. |
| B-P6-SEARCH | P6.8 | implementation/high | plan-exec | light | recommended: Общий query/provider/rebuild harness → installed data seal после остальных branches; callbacks/ACL/fault reviews сохраняются. |
| B-P7-RECURRENCE | P7.1 | implementation/medium | plan-exec | light | recommended: Квалифицированный source/recurrence ABI → pure bounded adapter; общий immutable source/context. |
| B-P7-RECURRENCE | P7.2 | implementation/high | plan-exec | full | recommended: Квалифицированный source/recurrence ABI → pure bounded adapter; общий immutable source/context. |
| B-P7-TASKS | P7.3 | implementation/high | plan-exec | full | recommended: Existing task model/actions/transaction harness → recurrence materializer; binding только после P7.2 и P5.2. |
| B-P7-TASKS | P7.4 | implementation/high | plan-exec | full | recommended: Existing task model/actions/transaction harness → recurrence materializer; binding только после P7.2 и P5.2. |
| B-P7-INTL | P7.5 | implementation/high | plan-exec | light | recommended: Общие intl/config/decimal/dataset inputs; sequential shared writes, scoped source/license gates. |
| B-P7-INTL | P7.6 | implementation/high | plan-exec | full | recommended: Общие intl/config/decimal/dataset inputs; sequential shared writes, scoped source/license gates. |
| B-P7-INTL | P7.7 | implementation/high | plan-exec | full | recommended: Общие intl/config/decimal/dataset inputs; sequential shared writes, scoped source/license gates. |
| B-P7-BRIDGES-SEAL | P7.8 | implementation/high | plan-exec | full | recommended: Selected intl/search/label adapters → installed utility seal; потребляются актуальные остальные branch receipts. |
| B-P7-BRIDGES-SEAL | P7.9 | implementation/medium | plan-exec | light | recommended: Selected intl/search/label adapters → installed utility seal; потребляются актуальные остальные branch receipts. |
| B-P8-TOOLS | P8.1 | implementation/medium | plan-exec | light | recommended: Registry/API source qualification → declarations → catalogue → guarded invocation; последовательная квалификация без повторного полного чтения. |
| B-P8-TOOLS | P8.2 | implementation/high | plan-exec | full | recommended: Registry/API source qualification → declarations → catalogue → guarded invocation; последовательная квалификация без повторного полного чтения. |
| B-P8-TOOLS | P8.3 | implementation/high | plan-exec | full | recommended: Registry/API source qualification → declarations → catalogue → guarded invocation; последовательная квалификация без повторного полного чтения. |
| B-P8-TOOLS | P8.4 | implementation/high | plan-exec | full | recommended: Registry/API source qualification → declarations → catalogue → guarded invocation; последовательная квалификация без повторного полного чтения. |
| B-P8-INTEGRATION | P8.5 | implementation/high | plan-exec | light | recommended: Registry diagnostics и selected contributions → installed tool seal; P8.5 готов независимо, P8.6 ждёт только выбранных producers. |
| B-P8-INTEGRATION | P8.6 | implementation/high | plan-exec | full | recommended: Registry diagnostics и selected contributions → installed tool seal; P8.5 готов независимо, P8.6 ждёт только выбранных producers. |
| B-P8-INTEGRATION | P8.7 | implementation/medium | plan-exec | light | recommended: Registry diagnostics и selected contributions → installed tool seal; P8.5 готов независимо, P8.6 ждёт только выбранных producers. |
| B-P9-BUILD | P9.1 | implementation/medium | plan-exec | light | recommended: Source/vector/overlay freeze → exact split/dist build; один export-map context, no remote dispatch. |
| B-P9-BUILD | P9.2 | implementation/high | plan-exec | full | recommended: Source/vector/overlay freeze → exact split/dist build; один export-map context, no remote dispatch. |
| B-P9-LOCAL-PROOF | P9.3 | implementation/high | plan-exec | full | recommended: Matrix/CI helpers → pristine/populated installed fixtures; общий actual build/recipe context, bounded отдельно от export implementation. |
| B-P9-LOCAL-PROOF | P9.4 | implementation/high | plan-exec | full | recommended: Matrix/CI helpers → pristine/populated installed fixtures; общий actual build/recipe context, bounded отдельно от export implementation. |
| solo | P9.5 | implementation/high | manual | full | recommended: Manual G-RUNNER: удалённый dispatch/current credentials и собственный finite grant; не смешивать с private authority. |
| solo | P9.6 | implementation/high | manual | full | recommended: Manual G-PRIVATE: отдельный конфиденциальный graph и finite read grant; не объединять authority с runner. |
| solo | P9.7 | implementation/medium | plan-exec | light | recommended: Локальный publication packet после двух manual branches; соседние Exec=manual, готовые bytes до G-RELEASE. |
| solo | P9.8 | implementation/high | manual | full | recommended: Manual G-RELEASE: необратимые split/tag/assets и recovery; отдельный exact publication grant. |
| solo | P9.9 | implementation/medium | plan-exec | light | recommended: Локальный delivered-install/passport после manual publication; actual remote result появляется на другой authority boundary. |
| B-P10-TARGETS | P10.1 | implementation/medium | plan-exec | light | recommended: Один released/profile/import context: verifier/intake → central/cloud receipts, independent ready subsets. |
| B-P10-TARGETS | P10.2 | implementation/high | plan-exec | light | recommended: Один released/profile/import context: verifier/intake → central/cloud receipts, independent ready subsets. |
| B-P10-TARGETS | P10.3 | implementation/high | plan-exec | light | recommended: Один released/profile/import context: verifier/intake → central/cloud receipts, independent ready subsets. |
| B-P10-AP | P10.4 | implementation/high | plan-exec | full | recommended: Один deployed target/census и fault receipt context: security → races/restore → FE; все исходные budgets и reviews обязательны. |
| B-P10-AP | P10.5 | implementation/high | plan-exec | full | recommended: Один deployed target/census и fault receipt context: security → races/restore → FE; все исходные budgets и reviews обязательны. |
| B-P10-AP | P10.6 | implementation/high | plan-exec | full | recommended: Один deployed target/census и fault receipt context: security → races/restore → FE; все исходные budgets и reviews обязательны. |
| solo | P10.7 | implementation/high | plan-exec | light | recommended: Полный закрытый monthly census — отдельное календарное ожидание; нет busy polling или удержания остальных branches. |
| B-P10-OPS-SEAL | P10.8 | implementation/high | plan-exec | light | recommended: Provider operation imports → effective seal на том же verifier; P10.8 не ждёт month, P10.9 ждёт все actual branches. |
| B-P10-OPS-SEAL | P10.9 | implementation/medium | plan-exec | light | recommended: Provider operation imports → effective seal на том же verifier; P10.8 не ждёт month, P10.9 ждёт все actual branches. |
| B-P11-QUALIFY | P11.1 | implementation/high | plan-exec | light | recommended: Один source/vector/consumer/atom corpus: verifier → pins → tests → ledger; последовательный re-admission по готовности. |
| B-P11-QUALIFY | P11.2 | implementation/high | plan-exec | light | recommended: Один source/vector/consumer/atom corpus: verifier → pins → tests → ledger; последовательный re-admission по готовности. |
| B-P11-QUALIFY | P11.3 | implementation/high | plan-exec | light | recommended: Один source/vector/consumer/atom corpus: verifier → pins → tests → ledger; последовательный re-admission по готовности. |
| B-P11-QUALIFY | P11.4 | implementation/high | plan-exec | light | recommended: Один source/vector/consumer/atom corpus: verifier → pins → tests → ledger; последовательный re-admission по готовности. |
| solo | P11.5 | implementation/high | plan-exec | full | recommended: Новая finite foreign write surface и full независимый review exact three-file diff; не повышать light qualification batch до full ради packet. |
| solo | P11.6 | implementation/high | manual | full | recommended: Manual G-CONSUMER-RECONCILE: отдельная accepting/write authority, exclusivity и foreign commit. |
| solo | P11.7 | implementation/medium | plan-exec | light | recommended: Локальный final consumer seal после manual foreign commit; anchor становится доступен после отдельной write boundary. |

## 4. Update Log

Owned projection: status.md; журнал journal.jsonl владеет update history и actor/model provenance.
Создан 2026-09-15 plan-designer/gpt-6-astra: верхний слой. P1/P2/P3/P4/P5/P6/P7/P8/P9/P10/P11 детализированы 2026-09-15 plan-designer/gpt-5.6-sol; product execution не начат, история — journal/generated status.

## 5. Decision Log

Каталог решений: decisions/; собрать: `python3 plan-views.py decisions --plan-dir <plan>`.
