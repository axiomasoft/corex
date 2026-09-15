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
P1 и P2 детализированы Sol по четыре execution contracts, P3 — семь, P4 — восемь, P5 — шесть, P6 — восемь, P7 — девять, P8 — семь, P9 — девять, P10 — девять; P11 — семь; все фазы имеют детальные спецификации, единый semantic/hash-current design-finish остаётся следующим обязательным шагом.
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

- Current owner scope: P11 plan-only детализация по явному `$ task:plan-design 2026.09.15-№1-COREX-FLEXCRM-REMAINING P11`; D6 neutral external queries, D16 freezes exact complete consumer dependency joins/accepting actor and finite future owning bookkeeping without external grants.
- P1 и P2 имеют по 4 detailed specs, P3 — 7, P4 — 8, P5 — 6, P6 — 8, P7 — 9, P8 — 7, P9 — 9, P10 — 9, P11 — 7; execution admission для всех items закрыта до полного дизайна P1–P11 и design-finish по D4. P1 mandatory Perplexity receipt verified дополнительным запросом (D5); P2 mandatory neutral RAG verified (D6/D7); P3 mandatory neutral RAG verified (D8); P4 mandatory neutral RAG verified (D9); P5 mandatory neutral RAG verified after owner-resumed general/routing/digest verification (D10); P6 mandatory neutral RAG verified after initial incomplete answer and separate transfer/search queries (D11); P7 mandatory neutral RAG verified after notified incomplete replies and topic queries (D12); P8 mandatory neutral RAG verified after notified incomplete initial answer and separate guard/schema queries (D13); P9 mandatory neutral RAG verified after owner-notified incomplete responses and complete short query/direct sources (D14); P10 mandatory neutral RAG verified with supplementary queries/direct primary sources (D15); P11 neutral mandatory RAG verified after notified failed/incomplete retrievals and awaited successful source-selected response/direct primary checks (D16). Whole integrated finish pending.
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
- Finish artifact: artifacts/design-finish.md, **Design finish: verified**, ## Design inputs (Path / SHA256) для plan.md, research/05-integrated-design.md, всех phase index/item specs, research/P1-design-rag.md–P11-design-rag.md и decisions/D4.md, а также stable candidate docs research/P2-contract-design.md/P3-adapter-design.md/P4-extension-design.md /P5-wakeup-design.md/P6-data-design.md/P7-utility-design.md/P8-tool-design.md/P9-cohort-design.md, decisions/D14-release-cohort.md и scripts/validate-p9.py, research/P10-effective-design.md, decisions/D15-effective-acceptance.md и scripts/validate-p10.py, research/P11-reconciliation-design.md, decisions/D16-consumer-reconciliation.md и scripts/validate-p11.py (D9/D10/D11/D12/D13/D14/D15/D16: indirect contract inputs must stay hash-current). Structural lint и содержательная совместимость обязательны; hashes создаются после итоговой записи стабильных inputs, без self/volatile carriers.

## 3. Routing

Фазовая детализация P1–P11: **frontier/high = gpt-5.6-sol/high**.
P1–P11 строки ниже — реальные execution items; ни одна не допущена до D4 finish. Sol выбирает models/effort,
Batch/Review по сложности, integration/security/public риску. Детальный phase routing — в каждой фазе.

| Batch | Items | Model class/effort | Exec | Review | Почему |
|:--|:--|:--|:--|:--|:--|
| solo | P1.1 | implementation/medium | plan-exec | light | recommended: детерминированная provenance сверка; source output нужен до qualification inputs |
| B-P1-QUALIFY | P1.2 | frontier/high | plan-exec | light | recommended: общий frozen source/context; mixed ownership и semantic equivalents требуют Sol/high, ready subsets допустимы |
| B-P1-QUALIFY | P1.3 | frontier/high | plan-exec | light | recommended: общий frozen source/context; mixed ownership и semantic equivalents требуют Sol/high, ready subsets допустимы |
| solo | P1.4 | frontier/high | plan-exec | light | recommended: lossless seal требует завершённых AC/evidence carriers и содержательной сверки их согласованности |
| solo | P2.1 | frontier/high | plan-exec | light | recommended: отдельная квалификация sealed P1/equivalents создаёт общий baseline до открытого contract выбора |
| B-P2-PROPOSALS | P2.2 | frontier/high | plan-exec | light | recommended: общий qualified intake/BC/trust context; parallel DAG branches допускают ready subsets без child agents |
| B-P2-PROPOSALS | P2.3 | frontier/high | plan-exec | light | recommended: общий qualified intake/BC/trust context; parallel DAG branches допускают ready subsets без child agents |
| solo | P2.4 | frontier/high | plan-exec | light | recommended: финальный catalogue/12-input seal требует завершённых exact identity и generic proposals; отдельный bounded compatibility check |
| B-P3-LOCAL | P3.1 | implementation/high | plan-exec | full | recommended: common storage/physical PG setup reused; hard tenant isolation/native driver seam needs bounded independent review |
| B-P3-LOCAL | P3.2 | implementation/high | plan-exec | full | recommended: common isolated PG/source setup; claim concurrency and export/drop refusal require bounded independent review |
| B-P3-IDENTITY | P3.3 | implementation/high | plan-exec | full | recommended: frozen wire/SQL/auth context reused across chain; mTLS/JWT identity trust hard seam |
| B-P3-IDENTITY | P3.4 | implementation/high | plan-exec | full | recommended: same producer/context; restore/fence/inbox concurrent state hard seam, ready subset admitted |
| B-P3-IDENTITY | P3.5 | implementation/high | plan-exec | full | recommended: same pair/credential/clock fault setup; final-write security+concurrency hard seam |
| B-P3-IDENTITY | P3.6 | implementation/high | plan-exec | full | recommended: same accepted profile and context; cache version versus authorization separation verified |
| solo | P3.7 | implementation/medium | plan-exec | light | recommended: distinct final source/seal checkpoint after every branch; read-only evidence consolidation |
| solo | P4.1 | implementation/high | plan-exec | full | recommended: distinct provider/guest-policy checkpoint; only registry/descriptor package context, later core query/default boundary separated by other producer chains |
| B-P4-MEDIA | P4.2 | implementation/high | plan-exec | full | recommended: shared media/storage/PG setup; attach/delete and non-transactional IO recovery hard concurrency seam |
| B-P4-MEDIA | P4.3 | implementation/high | plan-exec | full | recommended: reuse same storage/file-lock/fault harness; stale completion/delete and untrusted decode hard seam |
| B-P4-NOTIFICATIONS | P4.4 | implementation/high | plan-exec | full | recommended: common SQL/producer transaction/recipient context; commit/dispatch crash and unknown effect hard seam |
| B-P4-NOTIFICATIONS | P4.5 | implementation/high | plan-exec | full | recommended: same delivery/preference lock context; current authorization/cancellation and unknown provider effect hard seam |
| B-P4-NOTIFICATIONS | P4.6 | implementation/high | plan-exec | full | recommended: same frozen delivery/digest context; prerequisite P5 gate explicit, safe ready subsets allowed; concurrent claims/admitted content hard seam |
| solo | P4.7 | implementation/high | plan-exec | full | recommended: distinct core-only schema/SQL/public boundary and nullable default/inference tests; no sibling import, required panels adapter producer explicit |
| solo | P4.8 | implementation/high | plan-exec | light | recommended: distinct cold installed archive/environment checkpoint after all producers; deterministic manifests/seal and meaningful installed integration checks suffice |
| solo | P5.1 | implementation/high | plan-exec | light | recommended: distinct standalone source/install qualification before any engine ABI adoption; unavailable source must not delay utility |
| B-P5-WAKEUP | P5.2 | implementation/high | plan-exec | full | recommended: shared SQL/context/fault harness; durable claim/cancel/local commit hard concurrency seam |
| B-P5-WAKEUP | P5.3 | implementation/high | plan-exec | full | recommended: reuse same runner/SQL/source setup; root hint revision/epoch CAS and tenant isolation hard seam, ready subset allowed |
| solo | P5.4 | implementation/high | plan-exec | full | recommended: distinct qualified external engine/FSM ABI and durable producer checkpoint; unknown effect/signal/FSM security hard seam |
| solo | P5.5 | implementation/high | plan-exec | full | recommended: distinct P4 outbox and wakeup consumer setup; digest mapping/cancel/lock ordering hard seam, no P4.6 prerequisite |
| solo | P5.6 | implementation/medium | plan-exec | light | recommended: distinct cold dist/installed source seal checkpoint after all branches, full declared hosted/release matrix belongs P9 |
| solo | P6.1 | implementation/high | plan-exec | light | recommended: distinct independent immutable engine/codec/source qualification before ABI adoption |
| B-P6-FIELDS | P6.2 | implementation/high | plan-exec | full | recommended: shared qualified engine/type/context PG setup; typed public field/policy seam |
| B-P6-FIELDS | P6.3 | implementation/high | plan-exec | full | recommended: same engine/catalog harness; async CIC/lock/crash hard seam, ready subsets allowed |
| B-P6-TRANSFER | P6.4 | implementation/high | plan-exec | full | recommended: shared SQL/source/map/target setup; checkpoint/concurrent jobs/unknown hard seam |
| B-P6-TRANSFER | P6.5 | implementation/high | plan-exec | full | recommended: same codec/storage SQL setup; snapshot/finalize/current policy hard seam |
| B-P6-SEARCH | P6.6 | implementation/high | plan-exec | full | recommended: shared schema/PG/provider context; current row/field ACL/query hard seam |
| B-P6-SEARCH | P6.7 | implementation/high | plan-exec | full | recommended: same provider/task/fault setup; late-sender/rebuild/swap hard seam |
| solo | P6.8 | implementation/high | plan-exec | light | recommended: distinct cold installed dist/environment checkpoint after all seven branch receipts |
| solo | P7.1 | implementation/medium | plan-exec | light | recommended: distinct qualified source/producer/installed checkpoint; deterministic evidence qualification/consolidation |
| solo | P7.2 | implementation/high | plan-exec | full | recommended: distinct qualified source/producer/installed checkpoint; hard public/DST/concurrency/current-authority seam requires bounded review |
| solo | P7.3 | implementation/high | plan-exec | full | recommended: distinct qualified source/producer/installed checkpoint; hard public/DST/concurrency/current-authority seam requires bounded review |
| solo | P7.4 | implementation/high | plan-exec | full | recommended: distinct qualified source/producer/installed checkpoint; hard public/DST/concurrency/current-authority seam requires bounded review |
| B-P7-INTL | P7.5 | implementation/high | plan-exec | light | recommended: shared intl package/Settings/decimal/reference source context, ready subsets sequential writes, no child agents |
| B-P7-INTL | P7.6 | implementation/high | plan-exec | full | recommended: shared intl package/Settings/decimal/reference source context, ready subsets sequential writes, no child agents |
| B-P7-INTL | P7.7 | implementation/high | plan-exec | full | recommended: shared intl package/Settings/decimal/reference source context, ready subsets sequential writes, no child agents |
| solo | P7.8 | implementation/high | plan-exec | full | recommended: distinct qualified source/producer/installed checkpoint; hard public/DST/concurrency/current-authority seam requires bounded review |
| solo | P7.9 | implementation/medium | plan-exec | light | recommended: distinct qualified source/producer/installed checkpoint; deterministic evidence qualification/consolidation |
| solo | P8.1 | implementation/medium | plan-exec | light | recommended: separate immutable source/equivalent/adoption intake before compiler work; deterministic qualification |
| B-P8-TOOLS | P8.2 | implementation/high | plan-exec | full | recommended: common compiled/schema/policy/guard context, ordered ready producer subset allowed; public/schema/current-authority hard seam needs bounded review |
| B-P8-TOOLS | P8.3 | implementation/high | plan-exec | full | recommended: common compiled/schema/policy/guard context, ordered ready producer subset allowed; public/schema/current-authority hard seam needs bounded review |
| B-P8-TOOLS | P8.4 | implementation/high | plan-exec | full | recommended: common compiled/schema/policy/guard context, ordered ready producer subset allowed; public/schema/current-authority hard seam needs bounded review |
| solo | P8.5 | implementation/high | plan-exec | light | recommended: separate modules-only read diagnostic independent of optional APItool/producer state; no hard effect seam |
| solo | P8.6 | implementation/high | plan-exec | full | recommended: separate actual selected producer/ownership/transport contract setup; security cross-domain mapping hard seam |
| solo | P8.7 | implementation/medium | plan-exec | light | recommended: distinct cold immutable ZIP/installed source checkpoint after all branch receipts; local seal and parity tests |
| solo | P9.1 | implementation/medium | plan-exec | light | recommended: deterministic producer/source/profile freeze before build; self-check and affected evidence suffice |
| solo | P9.2 | implementation/high | plan-exec | full | recommended: public split/composer metadata integrity hard seam; bounded independent review of named seam |
| solo | P9.3 | implementation/high | plan-exec | full | recommended: frozen compatible matrix/CI and fail-closed runner/publication helpers before grants; bounded review of credential/immutable operation seam |
| solo | P9.4 | implementation/high | plan-exec | full | recommended: populated upgrade/refusal/restore and tenant isolation hard seam; bounded independent review of named seam |
| solo | P9.5 | implementation/high | manual | full | recommended: actual remote runner dispatch/credential/source identity hard seam; bounded independent review of named seam |
| solo | P9.6 | implementation/high | manual | full | recommended: private source confidentiality/current authorization and independent domain hard seam; bounded independent review of named seam |
| solo | P9.7 | implementation/medium | plan-exec | light | recommended: deterministic seal of already tested publication helper and exact operation packet before grant; self-check and current affected evidence |
| solo | P9.8 | implementation/high | manual | full | recommended: irreversible multi-repo/version/assets partial-publication hard seam; bounded independent review of named seam |
| solo | P9.9 | implementation/medium | plan-exec | light | recommended: read-only final cold delivered matrix/passport consolidation using unchanged qualified fixture; self-check and affected evidence suffice |
| solo | P10.1 | implementation/medium | plan-exec | light | recommended: distinct released intake checkpoint prepares verifier before external evidence acceptance; deterministic local tests |
| B-P10-TARGETS | P10.2 | implementation/high | plan-exec | light | recommended: shared same-cohort/profile/import provenance context; independently ready central/cloud subsets without agents |
| B-P10-TARGETS | P10.3 | implementation/high | plan-exec | light | recommended: shared same-cohort/profile/import provenance context; independently ready central/cloud subsets without agents |
| B-P10-AP | P10.4 | implementation/high | plan-exec | full | recommended: shared deployed security/fault receipt context; all-edge mTLS/current authorization hard seam needs bounded independent review |
| B-P10-AP | P10.5 | implementation/high | plan-exec | full | recommended: shared deployed security/fault receipt context; final-write/nonrollback witness hard seam needs bounded independent review |
| solo | P10.6 | implementation/high | plan-exec | full | recommended: distinct actual FE/domain/recovery budget setup; authorization versus physical cleanup hard seam needs bounded review |
| solo | P10.7 | implementation/high | plan-exec | light | recommended: distinct closed-month telemetry/census external wait, no batch across timing window; raw population and affected semantic checks |
| solo | P10.8 | implementation/high | plan-exec | light | recommended: distinct actual provider/control-plane operation receipt context; no external writes, raw full selected ops qualification |
| solo | P10.9 | implementation/medium | plan-exec | light | recommended: distinct current eight-branch final seal after all acceptance windows; deterministic evidence consolidation |
| solo | P11.1 | implementation/high | plan-exec | light | recommended: distinct producer/source freeze and verifier preparation before receipt qualification; deterministic local checks |
| B-P11-QUALIFY | P11.2 | implementation/high | plan-exec | light | recommended: shared current consumer pins/config/test/atom context, ready ordered subsets allowed |
| B-P11-QUALIFY | P11.3 | implementation/high | plan-exec | light | recommended: same qualified consumer/test/atom join setup, distinct per-profile checks |
| B-P11-QUALIFY | P11.4 | implementation/high | plan-exec | light | recommended: same immutable selected-atom context; deterministic coverage and residual-owner checks |
| solo | P11.5 | implementation/high | plan-exec | full | recommended: distinct ready exact foreign diff checkpoint before authority grant; accepting actor and race/partial overwrite hard seam requires bounded independent review |
| solo | P11.6 | implementation/high | manual | full | recommended: distinct finite authority and foreign repository ownership checkpoint, manual dispatch; authenticated actor and concurrent overwrite/partial write hard seam |
| solo | P11.7 | implementation/medium | plan-exec | light | recommended: distinct consumer committed-result anchor after foreign write; read-only final currentness/coverage seal |

## 4. Update Log

Owned projection: status.md; журнал journal.jsonl владеет update history и actor/model provenance.
Создан 2026-09-15 plan-designer/gpt-6-astra: верхний слой. P1/P2/P3/P4/P5/P6/P7/P8/P9/P10/P11 детализированы 2026-09-15 plan-designer/gpt-5.6-sol; product execution не начат, история — journal/generated status.

## 5. Decision Log

Каталог решений: decisions/; собрать: `python3 plan-views.py decisions --plan-dir <plan>`.
