---
id: D17
date: 2026-09-15
status: accepted
item: design
items: [design]
supersedes: []
superseded_by: null
---
# D17 — Whole-plan review, source research and executable seam corrections

Дата: 2026-09-15. Actor: текущая owner-authorized Astra/high design-review сессия по
`brief/02-astra-whole-plan-review.md`. Статус: accepted design corrections в пределах
прямого задания владельца. Это не provider/model attestation, Task route substitution,
phase audit consent или protected runtime/API/DDL/dependency/release/foreign grant.

## Основание и границы

52 R, все365 исторических parent identities, A01–A09, FE/DI/AP/original thresholds,
physical DB-per-account,8extenders и независимые engines сохраняются. D1–D16 и raw
история не переписываются. Эта запись supersedes только перечисленные несовместимые
текущие детали owning design; прежние phase decisions остаются происхождением дизайна.
Все78 execution items остаются Not started; P1.1 в этой сессии не выполняется.

## Решение

1. **Finish input closure (AWR-01).** `scripts/task-check.py::design_inputs` задаёт
   полную stable closure: plan/roadmap/open questions, brief snapshots, research,
   decisions, все phase contexts/specs/validators, сам checker и его негативные тесты,
   source inventory, whole-plan findings и immutable phase/review research evidence.
   Точные hashes фиксируются после stable edits. Journal, generated views/bundles,
   handoff, review read-progress/check logs и finish itself исключены как volatile.
   Изменения этих views проверяются их собственными генераторами/lint, не выдаются
   за новое design acceptance. Future runtime scripts/findings не становятся design
   inputs только из-за создания во время execution.
2. **Current guidance (AWR-02).** Ранние phase progress statements в rolling roadmap
   и integrated design обозначаются историческими. Текущий scope — full-plan review;
   единственный рекомендуемый successor после настоящего finish — P1.1,
   implementation/medium = gpt-5.6-terra/medium, native design-root-terminal handoff.
   История состояний остаётся в journal. Authoring session не dispatch-ит successor.
3. **Producer ownership (AWR-04/07).** P3.4 производит hidden PairStore::isFor,
   P3.5 потребляет. P4.4 производит NotificationChannelDriver до InAppChannelDriver;
   P4.5 добавляет реализации. P4.6 владеет exact NotificationDigestDueSource и migration
   000005, добавляющей digest FK после создания parent table. P5.5 по-прежнему не
   зависит от P4.6; accepted actual P5.2/5.5 reports — prerequisites P4.6.
4. **Shared DTO foundation (AWR-04/06).** Один exact core DataScope/RecordRef/WriteToken
   набор; frozen finite first-consumer protocol в P6 design. Первый реально ready
   из P6.2/4/6 или P7.4/6/7 serially создаёт эти3файла, core tests и shared receipt;
   остальные проверяют и переиспользуют. Пересекающаяся запись не параллелится;
   источники engine не prerequisite независимых transfer/search/intl веток.
   OperationToken не добавляется. P7 явно использует WriteToken с определённой
   expected revision semantics. P6 CI path исправлен на существующий ci.yml.
5. **Research (AWR-05).** Новые Perplexity01–07 плюс pinned source/tests; неполные
   ответы и ошибочные claims сохранены с corrections. AzGuard authorization panels,
   CoreX descriptors и Filament UI panels — разные contracts. Переносим scoped
   lifecycle, explicit DI, stable identity и diagnostics; отвергаем overwrite как
   collision guard, wildcard/null как account authority, cache как revocation fence,
   optional UI/role engine как обязательную CoreX dependency.
6. **Release identity (AWR-08).** P9.1 freezes intake intent и exact metadata overlays.
   P9.2 build attestation/P9.3–4 recipe evidence/remote publication — отдельные
   downstream subjects, связанные с unchanged candidate SHA. Никаких будущих hashes
   в intake, split self-reference или молчаливого изменения sealed producer.
7. **Consumer verifier/exclusive writes (AWR-09/11).** P11 structural checker допускает
   Expected==Actual и требует фактический P11.1 verifier `--verify` для execution
   reports. Он не запускается при design. Cooperative lock либо owner-quiesced
   checkout обязателен на весь finite packet; check-before-write не его замена.
8. **Lossless source spans (AWR-10).** Supplement01b сохраняет полные B36 multiline
   clauses и происхождение incidental B34 AC-19 reference, не меняя365 original
   labels/hashes. P1/P11 обязаны связать complete clauses с actual atom map.

## Почему

Исходные дефекты и source evidence зафиксированы до исправлений в whole-plan findings.

### Отклонённое finding и alternatives

AWR-03 оказался ошибкой аудита: root и phase изначально одинаково задавали B-P3-LOCAL
для P3.1. Временная phase-only правка отменена; фактический routing не меняется.

Не выбраны новый79-й item/дополнительный engine gate для shared DTO, копирование DTO
в sibling packages или interface-per-class; finite serial first-consumer adoption
сохраняет существующие независимые execution branches. Не выбраны sealed intake с
пустыми future hashes/его дописывание, source.summary как acceptance, переписывание
original acceptance index и broad foreign compare-before-write без эксклюзивности.

## Проверка и предел решения

Owning files содержат exact Files/Inputs/Code Guidance/Validation/Deliverables и
существующие observable case obligations. Негативные tests design guards выполняются
только в isolated temporary copies; они не создают producer/consumer receipts.
Общий semantic pre-mortem, проверка всех фаз, routing/views/lint/downstream и byte-current
finish обязательны. Итоговые фактические результаты находятся в whole-plan findings
и artifacts/astra-whole-plan-review/checks; наличие D17 не означает, что эти проверки
уже прошли. Реальные source/adoption/hosted/released/effective/month/consumer gates
остаются именованными будущими prerequisites, а не неизвестными design alternatives.
