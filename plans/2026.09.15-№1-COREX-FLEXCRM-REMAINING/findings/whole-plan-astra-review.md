# Whole-plan review — Astra/high

РЕЖИМ: owner-authorized design review, research and plan repair; no phase audit or execution.
ВХОД: brief/02-astra-whole-plan-review.md; starting HEAD ec78b3ae6c4e079068dbe67d38f9c1251b10d920, clean tracked worktree.
СКОУП: PLAN1 P1–P11, 78 specs, 52 R/365 original parents; local AzGuard read-only and neutral public research.
НЕ ТРОГАТЬ: product source, Contracts/DDL/dependencies, foreign repositories/Brain, shared runtime, historical source snapshots and existing decision meaning.
Текущий вердикт: 10 подтверждённых findings исправлены; AWR-03 отклонён. Финальные статусы и evidence — в разделе «Итоговые resolutions — D17». Runtime acceptance не заявляется.

## Initial findings (recorded before owning contract repairs)

### AWR-01 — Incomplete finish input closure

- Evidence (RAG:—): initial scripts/task-check.py `design_ready()` expected set includes validators P9–P11 but omits P1–P8, itself, research/01-traceability.md, research/01a-acceptance-index.md, research/03-gates.md and early owning decisions. New mandatory whole-plan/AzGuard/open-source research is also absent. Exact initial bytes are pinned in artifacts/astra-whole-plan-review/input-inventory.initial.json.
- Impact: changing an indirectly consumed requirement/gate/validator after a finish can leave the checker reporting current; item-spec hashes alone cannot establish current design.
- Affected: all R/items; finish producer → every item pre-mutation admission.
- Proposed resolution: explicit stable input closure including new research/finding/decision/checker carriers and their immutable evidence, excluding volatile state/journal/bundle/finish itself. Test representative indirect drift and missing inputs using isolated fixtures.
- Acceptance: unchanged full design passes; changed criterion/gate/research/validator or mandatory retrieval invalidates finish; regenerated current finish passes.
- Status: OPEN; no contract fix yet.

### AWR-02 — Rolling integrated/roadmap carriers retain contradictory current states

- Evidence (RAG:—): initial roadmap P3/P4/P6/P7/P8 sections still say P8–P11 skeleton/pending and recommend P9 design; integrated research/05 ends several earlier phase sections with current pending successors, while plan.md declares all 11 detailed. plan Execution Rules still says current scope is P11-only.
- Impact: a cold reader encounters incompatible current authority/readiness and successor instructions; append-only decision history is valid but rolling current guidance is not authoritative as written.
- Affected: all phase navigation and finish handoff; no R/AC scope change.
- Proposed resolution: retain historical facts in journal/decisions/artifacts; label historical phase observations and make current rolling state/authority/one successor consistent after actual finish.
- Acceptance: current carriers agree on design completion and next P1.1 route only when finish passes; historical receipts remain byte-preserved.
- Status: OPEN; no contract fix yet.

## Retrieval observations

- Query 01: actual tool returned content, but ended mid-sentence in precedence and omitted exact promised source/test paths. Owner notified immediately; raw artifact preserved. This response alone does not satisfy mandatory verification. Query 02 is supplementary and pending.

## Resolutions and final checks

Историческая запись до завершения: pending complete required reads, primary source verification, all phase/item semantic audit and repairs. Итоговая таблица ниже задаёт текущий статус; inventory отличает inventoried bytes от fully read inputs.

### AWR-10 — Historical acceptance index contains incomplete extraction

- Evidence: research/01a-acceptance-index.md preserves only the first physical line of B36 AC-1–16 (all except AC-9 continue in the pinned snapshot). Its B34 AC-19 at L212 is a reference inside the invitation sequence to the B11 canonical AC-19, not a separately declared B34 acceptance item. The 365-row inventory remains an immutable historical identity set.
- Impact: hashing only the displayed fragment can lose no-direct-write, self-authorization, current-assignment and other conditions; treating the incidental reference as an independent new requirement changes provenance. P1 already requires snapshot comparison, but the design should expose these known extraction defects before execution.
- Proposed resolution: preserve every original parent/hash and snapshot unchanged; add an explicit source-text supplement with complete B36 clause spans and B34 reference provenance, bind it into P1/P11/finish, require each affected atom map to cover the complete original clause. Do not renumber/drop parents or fabricate runtime acceptance.
- Status: OPEN, recorded before supplement/owning contract repair.

### AWR-11 — Check-before-write is not exclusive ownership

- Evidence: P11-reconciliation-design permits “quiesced owning checkout or fresh conflict detection” when other writers do not honor the cooperative lock.
- Impact: checking a digest and then replacing a file can still overwrite a noncooperating writer between those operations; per-file atomic replacement alone is not compare-and-swap.
- Proposed resolution: require an actually exclusive cooperative lock or an owner-quiesced checkout throughout the finite operations. Fresh digest checks remain additional checks, never an alternative to exclusivity. Test an uncooperative-writer scenario as no-write refusal under WRITE-02.
- Status: OPEN, recorded before repair.

### AWR-06 — Undefined operation DTO and wrong CI path

- Evidence: P7 stable signatures use CoreX\Support\Data\OperationToken and attribute its producer to P6/P2; P6 declares only WriteToken. P6.2/4/6/8 use .github/workflows/tests.yml, whereas the qualified repository workflow is ci.yml.
- Impact: implementation requires an unplanned public type and CI changes at a nonexistent workflow; independent utility/data branches cannot satisfy their frozen contracts as written.
- Affected: P6/P7, R11/R12/R17/R23/R24/R25/R32; proposed correction reuses the exact WriteToken and names shared foundation producer without the engine prerequisite. Update Files/reads/checks together. Status OPEN, before repair.

### AWR-07 — Notification migration has a forward foreign key

- Evidence: P4-extension-design storage appendix assigns ntf_deliveries a digest_run FK, while ntf_digest_runs is produced later by P4.6; P4.4 must run independently before that successor.
- Impact: PostgreSQL cannot create a foreign key to a missing table. Require nullable digest_run column in initial outbox migration and add the FK only after creation of digest table in P4.6, with explicit populated-upgrade/order assertions. Status OPEN, before repair.

### AWR-08 — Release identity does not separate intent from derived outputs

- Evidence: P9-cohort-design includes recipe commit, split commit/tag object and dist digests in the p9-cohort/1 description frozen by P9.1; these outputs are produced by P9.2–4. Later metadata writes also require an explicit source/transform distinction.
- Impact: an executor can either invent future digests or mutate the supposedly frozen cohort/producer receipt. Define immutable intake identity over known source/vector/profile/baseline/transform intent, then separate build/recipe/publication attestations linked to that identity; no backward hash edge. Requalification remains mandatory on actual intent changes. Status OPEN, before repair.

### AWR-09 — P11 structural check rejects legitimate matching assertions

- Evidence: validate-p11.py report() requires Actual != Expected; a valid result such as expected count 1 / actual count 1 is rejected. The checker also does not invoke the prepared reconcile-consumer verifier, unlike P10's domain verifier.
- Impact: evidence authors must cosmetically rewrite passing observations; structural PASS can omit required semantic join/actor/preimage checks despite the specified first-carrier validation.
- Proposed resolution: remove inequality as a truth proxy, retain observed evidence binding and require the prepared verifier with an exact read-only CLI. Synthetic fixtures must not become consumer receipts. Status OPEN, before repair.

### AWR-03 — P3 routing disagrees across carriers

- Evidence: plan.md routes P3.1 as solo; phases/P3/P3.md places it in B-P3-LOCAL with P3.2. Initial hashes pinned.
- Impact: same requested item has different batch membership/context in root and phase; no runtime evidence justifies this difference.
- Resolution proposed: use owning root solo P3.1 and retain P3.2 grouping meaning without inventing mandatory batch; synchronize phase routing. Acceptance: exact item model/effort/Exec/Review/Batch parity. Status OPEN.

### AWR-04 — Frozen implementation needs undeclared producer files

- Evidence: research/P3-adapter-design.md requires PairStore::isFor for FinalWriteAdmission; P3.5 Files excludes Persistence/PairStore.php and P3.4 has no explicit ownership of this method. P4.4 owns InAppChannelDriver and NTF-05 driver outcomes, but NotificationChannelDriver interface Files first appears in successor P4.5. P4.6 requires notification DueSource without exact class path in Files, and Inputs still says exact P5 producer path must be reconciled. P6 shared DataScope/RecordRef/WriteToken are assigned only to engine-dependent P6.2, while independent P6.4/P6.6 consume shared data without producer edge/write path.
- Impact: executor must invent placement, break allowlist, or wait for an unintended prerequisite; source-only design claims hide build-time dependency.
- Affected: P3.4/5, P4.4/5/6, P5.5, P6.2/4/6 and their R/consumers.
- Proposed resolution: assign each exact seam to earliest legitimate producer, declare fixed shared foundation ownership without engine dependency, pin precise paths/reads/receipts, test producer-order/allowlist enforcement. Status OPEN pending full stable/spec review.

### AWR-05 — Public retrieval summaries are not reliable source proof

- Evidence: query01 version claims differ from current canonical tags for modules/permission/data. Query03 claims Filament PanelRegistry rejects duplicate IDs; its own cited 3.x source assigns panels[id] directly. Query02 and04 return only partial tables and lack requested semantic coverage; owner notified.
- Impact: importing summary claims could introduce unsupported conflict/lifetime/deletion guarantees.
- Resolution proposed: immutable canonical refs/source/tests + separate completed supplementary query per gap; reject fabricated version/duplicate guarantees, preserve incomplete responses as history. No upstream dependency adoption. Status OPEN; query05 storage isolation and query06 modules/DTO explanations returned complete text.

## Review correction — AWR-03 rejected

Direct reread of initial committed plan.md Routing and P3 phase Routing shows both
P3.1 rows use B-P3-LOCAL. The initial finding incorrectly described root as solo.
The attempted phase-only change was reverted; no routing correction is warranted.
AWR-03 status: REJECTED (review false positive), not a repaired product/design defect.
Final parity verification must compare actual tables rather than this initial claim.

## Итоговые resolutions — D17

**Whole-plan review:** resolved

Начальные observations выше сохраняются как история до исправлений. Текущие статусы
определяются этой таблицей; OPEN в первоначальной записи не означает оставшийся defect.

| Finding | Текущий статус | Owning repair / observable verification |
|:--|:--|:--|
| AWR-01 | RESOLVED | task-check.py full stable closure; isolated mutation tests research/gates/early validator/raw reply/new file/deleted snapshot and unchanged/journal cases |
| AWR-02 | RESOLVED | plan/roadmap current scope; integrated historical notes labelled; D17 current review; handoff only P1.1 after actual finish |
| AWR-03 | REJECTED | Initial routing claim disproved; root/phase both B-P3-LOCAL, temporary edit reverted |
| AWR-04 | RESOLVED | P3.4 PairStore::isFor producer; P4.4 channel interface; P4.6 exact DueSource; P6/P7 finite serial shared-data foundation receipt/Files/first boundary cases |
| AWR-05 | RESOLVED | research06/07, seven actual queries, completed05–07 supplements, pinned seven public sources/tests/licenses; failed claims explicitly rejected |
| AWR-06 | RESOLVED | P7 uses sole WriteToken and defined target revision; P6 finite CI paths use ci.yml; foundation protocol avoids engine prerequisite |
| AWR-07 | RESOLVED | P4.4 nullable digest column without forward FK; P4.6 migration000005 after parent tables; NTF-01/DIG-01/02 clean/populated/orphan checks |
| AWR-08 | RESOLVED | P9 intake intent + exact overlays, separate actual build/recipe/publication attestations; DIST-02/07 and INTAKE-06 enforce acyclic identity |
| AWR-09 | RESOLVED | P11 report accepts equal observations and invokes prepared --verify for execution stages; isolated equal1/red/missing/tampered evidence tests |
| AWR-10 | RESOLVED | supplement01b complete B36 clauses and B34 reference lineage; P1.2/P11.4 exact full-span/atom obligations; originals365 unchanged |
| AWR-11 | RESOLVED | P11 requires actual exclusive cooperative/quiesced mode; check-before-write is additional; WRITE-02 noncooperating-writer refusal |

All11phase contexts/78specs, stable phase designs/RAG/checkers,52R/365parent text,
A01–A09 and cross-phase gates were reviewed. `reviewed-inputs.json` distinguishes
required fully read contracts from merely hash-qualified historical/raw evidence.
Actual future atomic classification remains P1.2 work: no fabricated atom ledger.

Initial and repaired phase structural checks all pass (P1 inputs, P2–P11 design);
13 isolated design/consumer-checker guard tests pass. Final current results are stored
under artifacts/astra-whole-plan-review/checks; design-finish is valid only when its
complete input table matches current bytes and its gate exits0. Structural checks do
not prove PHP/runtime/installed/hosted/DI/AP/month/consumer truth.

Historical preservation check:428 pinned historical plan inputs unchanged;467 inventoried
AzGuard files and its initial dirty status unchanged. Product code/API/DDL/dependencies,
foreign/Brain/shared runtime and historical journals/decisions were not rewritten.
Journal receives only append-only design bookkeeping. Current source HEAD remains the
actual initial ec78b3ae6c4e079068dbe67d38f9c1251b10d920; no commit/publication performed.

### Research limits and actual future gates

Perplexity01/02/04 incomplete,03 duplicate-rejection claim false;05/06/07 supplement
coverage closed by direct source/test inspection. Current series had no login/timeout
failure. Historical phase RAG failures remain separately recorded; browser closure was
never classified as failure by itself. Selected comparisons inform seams, not new deps.
No upstream suite was run during this design-only review.

Explicit trusted context/DI/immutable typed data and diagnostics fit existing CoreX
ports. Duplicate engine/role writer/ninth extender/mandatory UI and cache-as-authority
were rejected. All detailed alternatives and independent primary origins are in06/07.
The integrated D17 pre-mortem records concrete failure→owner→case relationships.

No unresolved substantive internal design choice remains in this review. Named future
source/ABI/API/schema/dependency/private/runner/release/effective/calendar/provider and
independently accepting-consumer gates remain required; actual mismatch reopens affected
source/design work, never weakens acceptance. All78 items stay Not started.

**Current Next — D18:** B-P1-QUALIFY frontier/high = gpt-5.6-sol/high; first ready
carrier P1.1, then P1.2–P1.4 in the same root after closure/admission. Native initial
cold-start-root (design-root-terminal); no automatic execution in this design session.

### Финальная проверка собственных артефактов

Initial lint failure после rewrite handoff исправлен точной canonical формой заголовка,
Next и launch fields. Initial downstream RED возник от Pint discovery 12 новых raw
upstream PHP tests; локальные carriers переименованы в `.php.txt` без изменения bytes
или SHA256, manifest обновлён. Первичные неуспешные результаты сохранены в checks/iteration-1-*.
Повторные lint/downstream завершились exit0. Downstream остаётся first-command structural
dispatch against HEAD: missing future/uncommitted paths дают unknown, не runtime PASS.
Ручная сверка foundation Files дополнительно включила root phpunit.xml во все три
eligible P7 writers; первоначальный собственный routing parser адаптирован к P6 table
layout. Фактические root/phase routes совпадают по всем78 items; статус всех78 Not started.

## Последующее поручение владельца — D18

После завершения whole-plan review владелец запросил grouping и commit/push всей работы.
Первоначальный10-findings итог сохранён; дополнительная организационная находка и
57→35 envelopes/cost/readiness evidence — research/08-execution-batches.md, D18.
Прежние заявления no commit/publication описывают завершённый D17 review checkpoint;
новое разрешение охватывает Git этого плана, не product releases/foreign writes.
