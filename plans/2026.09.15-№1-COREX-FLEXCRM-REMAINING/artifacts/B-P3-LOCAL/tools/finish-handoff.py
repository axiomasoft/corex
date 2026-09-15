"""Finalize completed local scope; do not execute or admit identity items."""
from pathlib import Path
import json
import subprocess
from datetime import datetime, timezone
from task import plan_continuity, command_render, command_catalog, plan_evidence, plan_delivery

p = Path('plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING')
a = p / 'artifacts/B-P3-LOCAL'
root = Path.cwd()
def save(name, value):
    (a / name).write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n')

# The native Files carrier is a legacy semicolon sentence. Preserve it and
# additionally bind every actual changed product/test/report file explicitly.
changed = subprocess.check_output(['git', 'diff', '--name-only'], text=True).splitlines()
added = subprocess.check_output(['git', 'ls-files', '--others', '--exclude-standard'], text=True).splitlines()
paths = tuple(sorted({x for x in changed + added if x.startswith('packages/') or x == 'tests/Arch/LayerTest.php'} | {
    str(p / 'findings/P3-storage-context.md'), str(p / 'findings/P3-claim-maintenance.md'),
}))
before = plan_evidence._anchor(paths, repo_root=root)
assert all(row['state'] == 'file' for row in before['paths'])
save('P3.2-handoff-product-anchor-before.json', before)
estimate = json.loads((a / 'P3.2-active-continuity-estimate.json').read_text())
estimate.update(lifecycle='scope-complete', checkpoint='artifacts/B-P3-LOCAL/checkpoint.md')
decision = plan_continuity.reduce(plan_continuity.estimate_from_dict(estimate))
save('local-complete-continuity-estimate.json', estimate)
save('local-complete-continuity-decision.json', decision.as_dict())
admission = json.loads((a / 'B-P3-IDENTITY-handoff-admission.json').read_text())
assert admission['admission']['verdict'] == 'ALLOW' and not admission['identity_started']
next_exact = 'exec-items: task:plan-exec ' + p.name + ' P3.3 P3.4 P3.5 P3.6 P3.7'
carrier = command_render.parse_next_carrier(next_exact, context_kind=decision.outcome,
    item_ids=('P3.3', 'P3.4', 'P3.5', 'P3.6', 'P3.7'), continuity=decision)
save('B-P3-IDENTITY-handoff-carrier.json', carrier.as_dict())
runtime = Path('/home/vostrikov/projects/packages/swissknifeman/packages/task')
catalog = command_catalog.build_command_catalog(runtime / 'commands', runtime / 'configs/command-routing.json')
mapping = command_render.mapping_from_catalog(catalog, 'codex')
invocation = command_render.plan_provider_invocation(catalog, carrier, mapping,
    observed_class='implementation', observed_effort='high', inherited_minimum=('implementation', 'high'))
assert invocation.ok
save('B-P3-IDENTITY-provider-recommendation.json', {
    'invocation': invocation.invocation, 'model_selector': invocation.model_selector,
    'effort_selector': invocation.effort_selector, 'role': 'successor recommendation only; not provider-thread attestation or execution grant',
})
fragment = f'''**Next:** {next_exact}

| Параметр | Значение |
|---|---|
| Batch | B-P3-IDENTITY (P3.3–P3.7) |
| Model | gpt-5.6-terra |
| Thinking | high |
| Context | continue-root |

{command_render.render_continuity_decision(decision)}

'''
body = '''
**Done:** P3.1 и P3.2 closed-green, каждый attempt 1 в одном root. B-P3-LOCAL полностью завершён. Новых START/terminal при восстановлении проекций не было. Все обязательные локальные проверки GREEN.

**Remaining:** Только следующий B-P3-IDENTITY (P3.3–P3.7), выполнение не начато и настоящим handoff не разрешается. Фаза P3 открыта. В следующем авторизованном execution сначала выполнить source-complete нормализацию собственного Required Reads, materialize bundle и native admission; сохранить все исходные источники/проверки/индивидуальные лимиты. D23 advisory budget относится только к завершённому B-P3-LOCAL. После ALLOW предполагаемая команда: `$ task:plan-exec 2026.09.15-№1-COREX-FLEXCRM-REMAINING P3.3 P3.4 P3.5 P3.6 P3.7`.

**Sources of truth:** plan.md; journal.jsonl; phases/P3/P3.1.state.md; phases/P3/P3.2.state.md; findings/P3-storage-context.md; findings/P3-claim-maintenance.md; artifacts/B-P3-LOCAL/P3.1-check-results.json; artifacts/B-P3-LOCAL/P3.2-check-results.json; artifacts/B-P3-LOCAL/B-P3-IDENTITY-next-admission.json; artifacts/B-P3-LOCAL/checkpoint.md; decisions/D23-P3-local-budget-owner-decision.md; decisions/D25-P3-local-owner-adoption.md; artifacts/B-P3-LOCAL/owner-adoption-receipts.json; artifacts/B-P3-LOCAL/decision-metadata-reconciliation.json.

**Open risks:** P9 installed-cohort и P10 effective provider/IAM/usage/export/deployed qualification остаются отдельными owning gates. Доказана optional private native SDK/MinIO квалификация, не публичный S3/AWS/production профиль. Полный upstream запуск 83/88 PASS с пятью публичными режимами FAIL сохранён; разрешённый private профиль 83/83 PASS. Перед identity G-STORAGE/G-DEPENDENCY/иными защищёнными изменениями проверить точные matching owner receipts; P2 proposals и общая поддержка улучшений не заменяют scoped receipts.

**Workarounds and limitations:** Existing archive hook теперь требует доверенный verifier для каждого архива, включая local; size-only writer или legacy ready flags без verifier fail closed. Это отражено в changelog и проверено до READY и реального DROP. Публичные Contracts, DDL, общий runtime, рабочие lockfiles и default DI не менялись. Изолированные SDK/совместимость/MinIO использованы только тестами. D24/D26/D27 конечные local corrections сохраняют все обязательные источники и проверки. D28 касается только producer readiness для необходимого handoff P3.7: четыре отсутствующих identity reports сохраняют pending; P3.7 не выполнялся. Native metadata нормализация D25–28 не меняет принятое разрешение; исходные байты D25 с исходным acceptance SHA архивированы.

**Parallel briefs:** Нет дочерних агентов. Выполнение P3.1 → P3.2 последовательное, проверки независимых carriers выполнены с изолированными targets. Полные чтения не повторять: P3.1 105/105, P3.2 101/101 фактически завершены с SHA-qualified reuse; изменения и пять текущих carriers прочитаны отдельно. Никаких повторных проверок кода после GREEN bookkeeping.
'''
# Use the actual existing budget decision filename rather than an invented path.
body = body.replace('В следующем авторизованном execution сначала выполнить source-complete нормализацию собственного Required Reads, materialize bundle и native admission; сохранить все исходные источники/проверки/индивидуальные лимиты.', 'D29 исправил только source-complete read carrier следующего P3.3; bundle материализован и native batch admission ALLOW. Этот admission относится к структуре/маршрутизации, не к фактически завершённым чтениям, строгому identity budget или protected owner gates: проверить их в следующем авторизованном execution до START/записей.')
body = body.replace('artifacts/B-P3-LOCAL/B-P3-IDENTITY-next-admission.json;', 'artifacts/B-P3-LOCAL/B-P3-IDENTITY-handoff-admission.json; decisions/D29-identity-handoff-read-carrier.md;')
budget = next((p / 'decisions').glob('D23*')).name
body = body.replace('D23-P3-local-budget-owner-decision.md', budget)
(p / 'handoff.md').write_text('# HANDOFF — 2026-09-15 — after P3.2\n\n' + fragment + body)
(a / 'checkpoint.md').write_text('''# B-P3-LOCAL — scope complete

P3.1 and P3.2 native closed-green, original attempt 1, same root; no agents. P3 remains open. Product execution stops at P3.2. All mandatory checks and both semantic reports GREEN; do not repeat code checks for bookkeeping.

Owner accepted exact two scoped receipts bound to packet SHA256 4751d9796cd98b6bf73b3b5e21cd451917b8f68d264fb305cc9558de54f0d1e9. Acceptance original preserved; D25 metadata normalization provenance is decision-metadata-reconciliation.json. Budget D23 advisory only local two items; all sources, individual read limits, mandatory checks and shared runtime preserved.

P3.1 run 354839f50fe4c06d63579f587e557fcc8892cd7522c38573506b92aa5cd4f6aa. P3.2 run e59a7a507384076c5c01e2f446b5b156e2d6a53788ebe52f6b2e33b7d1358201. Native journal/state/check-results are authoritative. Decision projection interrupted after P3.2 terminal, recovered without second START/terminal; P3.2-projection-recovery.json records this honestly.

Required actual reads complete: required-read-progress.json 105 selections; P3.2-required-read-progress.json 101 selections. Reuse unchanged qualified actual source reads; do not load whole corpus again. Final own code delta proof P3.2-final-bundle-delta-proof.json. No public Contracts/schema/default DI/shared lock/runtime delta.

Product result: symlink/traversal/context/private filesystem guards and optional ObjectTenantStorage; workspace and physical DB context restore conformance; real separate-worker PG locks/unique-index authority/crash/lost response replay; template exhaustion/tamper; physical pg_dump/pg_restore archive readability/hash/size/account binding and actual DROP crash/retry conformance. Existing lifecycle verifier now mandatory, latest export ties deterministic. All exact reports and root/package/Arch/contracts/antiweakening/D98 checks preserved. Tenancy final 463 passed, 1493 assertions, one intentional SDK-absence skip (actual absence separately qualified). PHP8.4/8.5 × Laravel12/13 scoped compatibility proven in disposable labs; upstream private 83/83 PASS, full public-mode exclusions explicit.

Next handoff B-P3-IDENTITY P3.3–P3.7 only. Native materialize_next_ready returned no bundle; next-admission verdict BATCH_STALE because own original P3.3 semicolon Required Reads unresolvable. No identity START or product execution. Source-complete future own read carrier repair/admission and matching owner receipts required before protected writes; local D23 does not extend. Native handoff uses manual implementation/high P3.3 to avoid falsely promising executable admission; future post-ALLOW command and full scope in handoff.md.

P9/P10 installed/effective production qualifications remain owning gates. No new owner decision required to finish local scope. Only owned disposable MinIO removed after completed checks; existing PG test container preserved. Safe cleanup receipt storage-service-cleanup.json. One scoped final commit and verified native terminal delivery finish this local invocation.
''')
checkpoint = a / 'checkpoint.md'
t = checkpoint.read_text()
t = t.replace('Native materialize_next_ready returned no bundle; next-admission verdict BATCH_STALE because own original P3.3 semicolon Required Reads unresolvable.', 'Historical next-admission BATCH_STALE preserved. D29 repaired only original next P3.3 read carrier, preserving all sources/limits/checks. Native materialize_next_ready produced bundles/P3.3.json; current B-P3-IDENTITY-handoff-admission.json verdict ALLOW for routing/structure only.')
t = t.replace('Source-complete future own read carrier repair/admission and matching owner receipts required before protected writes;', 'Actual complete identity reads, strict budget/per-read admission, current route and matching owner receipts still required before future START/protected writes;')
t = t.replace('Native handoff uses manual implementation/high P3.3 to avoid falsely promising executable admission;', 'Native handoff recommends exec-items for identity after actual carrier repair and structural ALLOW;')
checkpoint.write_text(t)
after = plan_evidence._anchor(paths, repo_root=root)
save('P3.2-handoff-product-anchor-after.json', after)
save('P3.2-closure-freshness.json', {
    'scope': 'Post-terminal projection recovery and final handoff bookkeeping; original pre-terminal graph not reconstructed.',
    'native_actual_file_closure': plan_evidence.verify_closure(before, after),
    'actual_paths': list(paths),
    'legacy_files_carrier': 'Native original semicolon Files sentence is preserved; explicit real file anchor supplements its missing-path anchor without a runtime/spec mutation.',
    'observed_at': datetime.now(timezone.utc).isoformat(),
})
receipt = plan_delivery.build_terminal_receipt(p, 'P3.2')
plan_delivery.verify_receipt(p, receipt)
plan_delivery.write_receipt('/tmp/corex-p3-final-preflight.json', receipt)
print('Final handoff, explicit unchanged product anchors and native terminal preflight PASS.')
