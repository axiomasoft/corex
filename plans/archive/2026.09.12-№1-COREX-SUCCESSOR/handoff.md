# HANDOFF — 2026-09-14 — after P3

**Next:** план закрыт; 11 owner decisions разрешены. Detailed specs созданы в `2026.09.12-№2-COREX-APPROVED-SPECS`; актуальное ожидание и отдельный guarded запуск находятся в его `handoff.md`. Terminal P3 items не запускаются повторно.

**Context:** continue-root

| Context | continue-root |
|:--|:--|

**Done:** P1/P2 results сохранены. P3.1–P3.3 доставили evidence/proposals D6–D8. Владелец ответил тремя блоками: D6 «утверждаю», D7 «да, если ты счтаешь это личшим решением» с подтверждением рекомендации ассистентом, D8 «да». Distinct owner-provenance D11–D21 фиксируют branch choices. Все 11 request outcomes=`apply` в пределах исходного `future-spec-design-only`; pending=0. D6–D8 сохранены неизменными как evidence/proposal records.

| Owner record | Request | Selected option |
|:--|:--|:--|
| D11 | NORM-COVERAGE | effective80-target85 |
| D12 | NORM-L0-STACK | l0-php83 |
| D13 | NORM-FORWARD-SOURCES | minimal-forward-corrections |
| D14 | TEMPLATE-PARITY | hash-amendment-spec |
| D15 | BOXED-INIT | unsupported-until-consumer |
| D16 | DOMAIN-OWNER | consumer-control-plane-owner |
| D17 | ROLE-WRITE | consumer-writer-owner |
| D18 | RELEASE-COREX | public-candidate-split-spec |
| D19 | AUTH-PRIVATE-CHANNEL | private-immutable-source-spec |
| D20 | STANCL-SOURCE | owned-fixed-metadata-source-spec |
| D21 | COMPAT-PROOF | minimum-factual-proof-spec |

**Remaining:** Исполнимых successor items в этом плане нет. Executable specs уже созданы в successor; P1 и P2.1 завершены по его journal. Оставшиеся runtime/distribution outcomes этим планом не принимаются. Каждый следующий spec должен закрепить intent/scope, exact inputs/source hashes, owning files/project/operator, validation/negative acceptance, deliverables/rollback и оставшиеся authority/input gates. D15 принимает deferred boxed-workspaces outcome до конкретного потребителя, а не требует runtime adapter сейчас. Consumer DNS/TLS и role writer specs принадлежат concrete consumer project; ownership choice не переносит эти реализации в L0. Normative/source correction specs — docs-only. Template integrity и distribution требуют самостоятельных scope и проверок; поднятие floor до85 и compatibility.json tooling не включены.

**Authority:** D11–D21 — принятые owner branch choices для отдельных будущих specs. Согласование документальной поправки DB_SCHEMA D13 записано по контексту вопроса, включавшего роль владельца источника FlexCRM, без более широких schema прав. Concrete root_templates DDL amendment, public Contracts/major, dependency changes и external create/fork/push/tag/release/Packagist/secrets/DNS/TLS операции сохраняют отдельные scoped gates, перечисленные в D14/D16/D18–D20. Publication допускается только после подготовленного exact source/artifact/ref result, GREEN pristine proof и отдельного разрешения. Private install требует source-owner confirmed channel/ref и narrowly scoped read credential authority. No phase audit admitted.

**Verification:** Для каждого блока queue response schema, proposal bytes/digests и current request/policy/action digests проверены до фиксации. Добавлены claim/response события, исходные request строки не переписаны. Decision/gate views regenerated и проверены через owning generators. Journal/status и все phase carriers сохранены побайтно относительно начала каждого owner-response turn: P3 не реопенена. plan_requests.resolve() не вызван, поскольку его runtime continuation добавила бы in-progress к исходному terminal item. Product tests не требовались для этих decision-only changes; full mirror lint не объявляется GREEN.

**Sources of truth:** plan.md; brief/00-brief.md; roadmap.md; decisions/D1–D10 evidence/carrier records; decisions/D11–D21 owner records с exact names в generated decisions/_index.md; decisions/queue.jsonl; journal.jsonl. decisions/_gates.md и open-questions.md — owned views. Handoff — navigation, не дублирующий owner record. Canonical home Brain/CoreX/plans; repository mirror inputs сохранены.

**Open risks / missing inputs:** Immutable private AzGuard compatible0.2 ref и подтверждённый canonical channel ещё неизвестны. Stancl mirror maintainer/org/repo/tag/version не определены; root lock отличается от выбранного package ref. Исторический auth CI install failure не имеет доказанной единственной причины. Local D4/D5 receipts не runner GREEN текущего дерева; pristine public/private install proof отсутствует. Concrete consumer/operator/entrypoints для D16/D17 и consumer trigger для D15 не названы. Исторический P2.1 metadata gap отдельно квалифицирован текущим owner запросом в D22; исходные terminal строки и product acceptance сохранены. Previous optional-index/orchestration warnings не являются новым acceptance результатом.

**Deferred / exclusions:** Existing docs divergence сохранена; sync ограничивать plans при отдельном переносе в canonical home. Historical archive не менять/не реопенить. Runtime/distribution/normative debts не закрыты самим выбором owner branch. Новые пакеты, workspace Move/Rename, year-2 features, public interfaces, release/tag/push не выполнялись.

**Compatibility qualification:** D22 and findings/v2-compatibility.md own the 2026-09-14 metadata delta. Current bookkeeping write-site codes are not historical provider attestation. No phase re-entry or product validation rerun.
