#!/usr/bin/env python3
"""Prepare reviewable design carriers; apply no changes to the accepted contract."""
import copy
import hashlib
import json
from pathlib import Path
import shutil
import subprocess

OUT = Path(__file__).resolve().parent
PLAN = OUT.parents[1]
TASK = Path(shutil.which('maind')).resolve().parents[3] / 'packages/task/scripts/plan.py'


def save(name, value):
    path = OUT / name
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n')


def get(ref):
    envelope = json.loads(subprocess.check_output(['python3', str(TASK), 'get', str(PLAN), ref], text=True))
    assert envelope['ok']
    return envelope['data']['doc']


item, d120, d125, dossier = (get(ref) for ref in ['P09.9', 'D120', 'D125', 'knowledge/research-P09-design'])
for name, value in [('before-P09.9.json', item), ('before-D120.json', d120), ('before-D125.json', d125)]:
    save(name, value)

amendment = (
    'Уточнение P09.9, только после принятия PLAN3.D131 владельцем: поддерживаемая матрица '
    'PHP 8.4–8.5 × Laravel 12–13 × SQLite/PostgreSQL сохраняется полностью. Разрешены два '
    'repository-only тестовых стека с отдельными composer.json/composer.lock: Laravel12 '
    '12.69.3 + Testbench10.12.0 + Pest4.7.8 + PHPUnit12.5.33 + symfony/process7.4.19; '
    'Laravel13 13.35.0 + Testbench11.3.0 + Pest5.2.1 + PHPUnit13.3.4 + symfony/process8.1.7. '
    'Infection0.35.6, abstract-testframework-adapter0.5.1, include-interceptor1.0.0 и bounded '
    'Pest CLI adapter0.1.0 остаются тем же engine/route. Это единственное явное исключение '
    'из прежнего запрета снижения Pest/PHPUnit: только Laravel12 harness, не root development '
    'stack и не публичные package manifests. Root Pest5.2.1/PHPUnit13.3.4 и все остальные '
    'locked package records сохраняются; root lock допускает лишь изменение require metadata '
    'локального adapter, расширенного до точных альтернатив 4.7.8||5.2.1 и 12.5.33||13.3.4. '
    'Два harness lock генерируются на PHP8.4 и проверяются install/check-platform-reqs на '
    'обоих реальных интерпретаторах; во время матрицы выполняется только install, не update. '
    'Не использовать replace/provide aliases, vendor patches, ignore-platform-reqs, отключение '
    'security audit или фиксированные патчи вместо Infection. Одно дерево исходников/тестов '
    'и одинаковые требования raw Pest/coverage XML/JUnit/process evidence обязательны во всех '
    'восьми строках. MSI security95/messaging90/other75, uncovered/timeout/error semantics и '
    'все остальные положения D120 не изменяются. D125 repository-only имеет приоритет. '
    'Разрешение на изменение контрактов и стеков не запускает P09.5 или release-работы.'
)
new_first = (
    'P09.9 remains the bounded compatibility spike BEFORE P09.5. PLAN3.D131, only once '
    'accepted by the owner, authorizes Laravel-specific repository-only harness stacks: '
    'Laravel12 12.69.3/Testbench10.12.0/Pest4.7.8/PHPUnit12.5.33/Process7.4.19; '
    'Laravel13 13.35.0/Testbench11.3.0/Pest5.2.1/PHPUnit13.3.4/Process8.1.7. '
    'Infection0.35.6, abstract adapter0.5.1 and include interceptor1.0.0 remain pinned. '
    'The only permitted Pest/PHPUnit reduction is the explicit Laravel12 harness exception; '
    'root and package dependency pins stay unchanged, except root lock metadata for the '
    'local adapter exact version alternatives. Required runbook: knowledge/P09.9-stack-proposal#execution. '
    'Use two independent, hash-bound manifest/lock templates and separate per-cell vendors. '
    'Install a Laravel-specific lock on both PHP8.4/8.5; never update or select by PHP version. '
    'The eight SQLite/PG *_test rows must freshly run the same real tenancy Pest fragment, '
    'Laravel/PDO probe, raw coverage XML/JUnit and generated killed/escaped/uncovered/timeout/exit137 controls. '
    'Keep all required Infection flags; timeout/error controls are not kills or production MSI. '
    'The bounded CLI adapter remains fallback; no fake report/class-name rewriting or PHPUnit-only proof. '
    'Budgets remain 2h preflight + at most 2h adapter repair; fail blocked with raw evidence if '
    'any row fails. The solver evidence at artifacts/P09.9-stack-proposal is not runtime '
    'acceptance and does not discharge P09.9/P09.5. Other engine changes, stack drift, threshold '
    'relaxation and unsupported matrices require another exact owner decision.'
)
sections = copy.deepcopy(dossier['sections'])
for section in sections:
    if section['id'] == 'mutation':
        original = section['text'].split('\n\n', 1)
        assert original[0].startswith('P09.9 is a bounded compatibility spike')
        section['text'] = new_first + '\n\n' + original[1]

patch = {
    'title': 'Preflight Infection 0.35.6 with separate Laravel12/13 Pest stacks and reproducible locks',
    'summary': 'Verify eight PHP8.4/8.5 × Laravel12/13 × SQLite/PG rows using exact branch-specific Pest stacks.',
    'why': 'Resolve the Composer process constraint conflict without narrowing the supported matrix or weakening mutation evidence.',
    'scope': {'include': ['Two repository-only harness manifests/locks, selected by Laravel major.',
                          'Bounded Pest adapter exact alternatives and lock-aware receipt verification.',
                          'Fresh eight-row tenancy compatibility spike and negative controls.'],
              'exclude': ['P09.5 and all other items; production MSI and mutation-test improvements.',
                          'Public package dependency/API/schema/product changes, root dependency version changes.',
                          'Release qualification, RC, publishing, tags, push, workflow dispatch.']},
    'decisions': list(dict.fromkeys(item['decisions'] + ['D131'])),
    'reads': [{'ref': 'knowledge/P09.9-stack-proposal#execution', 'why': 'Approved bounded runbook and lock/receipt invariants', 'required': True},
              {'ref': 'D131', 'why': 'Exact owner-approved exception; must be accepted', 'required': True},
              {'ref': 'D120', 'why': 'Preserved MSI/quality policy and approved amendment', 'required': True},
              {'ref': 'D125', 'why': 'Repository-only scope', 'required': True},
              {'path': 'scripts/quality/infection-spike.py', 'why': 'Current prepare_cell/run_row/check_rows behavior', 'required': True},
              {'path': 'scripts/quality/infection-pest-adapter/README.md', 'why': 'Existing fallback limits and trace behavior', 'required': True}],
    'task_files': [{'path': path, 'change': change} for path, change in [
        ('scripts/quality/infection-stacks/laravel12/composer.json', 'create'),
        ('scripts/quality/infection-stacks/laravel12/composer.lock', 'create'),
        ('scripts/quality/infection-stacks/laravel13/composer.json', 'create'),
        ('scripts/quality/infection-stacks/laravel13/composer.lock', 'create'),
        ('scripts/quality/infection-compatibility.json', 'modify'),
        ('scripts/quality/infection-spike.py', 'modify'),
        ('scripts/quality/tests/test_infection_spike.py', 'modify'),
        ('scripts/quality/infection-pest-adapter/composer.json', 'modify'),
        ('scripts/quality/infection-pest-adapter/README.md', 'modify'),
        ('composer.lock', 'modify')]],
    'guidance': 'Execute only after D131 accepted and this exact patch applied. Read knowledge/P09.9-stack-proposal#execution. Preserve previous incompatible evidence and replace no old receipt. Stop after P09.9; no automatic successor execution.',
    'rules': item['rules'] + [
        'D131 must be accepted by the owner before changing pins; only the Laravel12 harness may use Pest4.7.8/PHPUnit12.5.33. Root composer.json and all package/product manifests unchanged.',
        'Update the root lock only for adapter require metadata; every other complete locked package record and root content-hash must remain unchanged.',
        'Two exact manifest/lock templates; per-cell install only, independent vendors, real platform requirements and audit. Receipt v2 binds Laravel-selected stack id, manifest/lock digests, source/tests/tools and actual runtime versions.',
        'Previous v1 receipts remain historical. Eight fresh rows with identical source/tests/control hashes; old partial Laravel13 receipts cannot be merged to claim success.',
        'Explicit scope ends at P09.9 even after GREEN. P09.5 and release work require a subsequent owner instruction.'
    ],
    'validation': copy.deepcopy(item['validation']) + [
        {'id': 'V5', 'cmd': 'scripts/php84 composer validate --strict', 'expect': 'exit0; root manifest/dependency pins unchanged and adapter lock metadata consistent', 'kind': 'acceptance'},
        {'id': 'V6', 'cmd': 'scripts/php84 python3 scripts/quality/infection-spike.py --check-stacks', 'expect': 'exit0 strict Composer validation/audit/lock-install/platform checks for both Laravel locks on both actual PHP versions; assert root locked records invariant', 'kind': 'acceptance'},
        {'id': 'V7', 'cmd': 'scripts/php84 vendor/bin/pint --test scripts/quality/infection-pest-adapter', 'expect': 'exit0 affected adapter scope', 'kind': 'acceptance'}
    ],
    'deliverables': ['Two exact repository-only harness manifest/lock templates and v2 policy/verifier.',
                     'Fresh eight-row compatible receipt with real Pest, coverage XML/JUnit, PDO witnesses and actual mutant/process controls, or explicit BLOCKED.',
                     'Root dependency invariance proof, per-stack audits/platform/install proof and completion limited to P09.9.'],
    'artifacts': [{'id': 'P09.9-compatibility', 'path': '@plan/artifacts/P09.9-execution-after-D131/matrix/receipt.json', 'role': 'output'},
                  {'id': 'P09.9-handoff', 'path': '@plan/artifacts/P09.9-execution-after-D131/completion.md', 'role': 'output'},
                  {'path': '@plan/artifacts/P09.9-stack-proposal/spike/receipt.json', 'role': 'input'}],
}
patch['validation'][0]['expect'] = 'exit0 all eight rows using the exact Laravel-selected stacks, fresh actual Pest + mutants + raw XML/JUnit/flags/runtime versions; no reused partial evidence'
patch['validation'][1]['expect'] = 'exit0 v2 full matrix; branch-specific exact pins and stack/lock hashes validated; reject missing/wrong/duplicate/stale rows'
patch['validation'][2]['expect'] = 'exit0 existing negatives plus wrong branch/lock, changed template, update instead of install, mixed stack versions, stale v1 receipt and root lock drift'
save('approved-plan-edits.json', [{'ref': 'D120', 'patch': {'decision': d120['decision'] + '\n\n' + amendment}},
                                   {'ref': 'knowledge/research-P09-design', 'patch': {'sections': sections}},
                                   {'ref': 'P09.9', 'patch': patch}])
save('D120-amendment.json', {'status': 'pending-owner-approval', 'append_verbatim': amendment})
save('P09.9-contract.json', {**item, **patch})

for major in ['12', '13']:
    source = OUT / 'spike/solves' / ('php8.4-laravel' + major)
    target = OUT / 'candidate-stacks' / ('laravel' + major)
    target.mkdir(parents=True, exist_ok=True)
    for name in ['composer.json', 'composer.lock']:
        shutil.copy2(source / name, target / name)

evidence = (
    'Source a83248a0; previous artifacts/P09.9-execution/completion.md and matrix/receipt.json '
    'remain incompatible. Primary tagged composer manifests were retrieved via HTTPS and are '
    'hash-bound in artifacts/P09.9-stack-proposal/{sources,spike/sources}/index.json. '
    'Pest4.7.8 requires Process ^7.4.13|^8.1.0 and exactly PHPUnit12.5.33 via require/conflict; '
    'Laravel12.69.3 requires Process ^7.2.0; Infection0.35.6 permits ^7.4. '
    'Process7.4.19 is their verified intersection. Pest5.2.1 requires Process ^8.1.7 and '
    'exactly PHPUnit13.3.4; Laravel13.35.0 and Testbench11.3.0 permit that version. '
    'Testbench10.12.0 selects core10.15.0; Testbench11.3.0 selects core11.5.0. '
    'Composer selects ParaTest7.20.0 for Pest4 and 7.25.0 for Pest5.\n\n'
    'Isolated spike graph: four real PHP8.4.26/8.5.11 × Laravel12/13 update --no-install '
    '--no-plugins --no-scripts solves exit0; check-platform-reqs --lock and audit --locked exit0. '
    'Each Laravel-specific PHP8.4 lock also installs with --dry-run and passes platform '
    'verification on PHP8.5; separate PHP8.5 solves pass too. Full root projections: '
    'same four solves/platform/audits and cross-PHP lock replay exit0 after adding the '
    'existing missing bcmath extension to the temporary PHP8.5 invocation only. Initial '
    'missing-extension/restart failures are retained, not concealed. Download/extraction '
    'of the matching php8.5-bcmath deb changed no system packages/configuration; hashes '
    'are in bcmath-package.json. COMPOSER_ALLOW_XDEBUG=1 prevents Composer from restarting '
    'without the temporary -d extension. No platform spoof/ignore flag, vendor patch '
    'or security-ignore policy was used.\n\n'
    'These are solve/platform/audit/dry-run proofs, not installation or real Pest4 mutation '
    'execution. Plugin activation, coverage/JUnit format, actual Pest4 tests and mutant '
    'classification remain mandatory acceptance after approval. No eight-row runtime '
    'GREEN, MSI measurement, hosted CI or release readiness is claimed. Root inputs '
    'are hash-invariant in protected-inputs.json; product code/dependencies unchanged.'
)
execution = (
    'Admission: owner accepts option A of D131; apply artifacts/P09.9-stack-proposal/approved-plan-edits.json '
    'using the currently installed Task plan.py. Its three patches change only D120, the '
    'P09.9 paragraph of the old mutation runbook, and P09.9 contract. They do not change '
    'lifecycle status/results. Verify current D120/P09.9 against before-* carriers before '
    'applying; rebase a conflicting later amendment instead of overwriting it. Archive '
    'the previous blocked context/result (already captured in before-P09.9.json and '
    'P09.9-execution), then plan.py reopen PLAN3 P09.9 --reason quoting owner approval. '
    'Do not reset via raw JSON status writes. Check current prerequisites. Run only '
    '$ task:plan-run 2026.09.27-№1-COREX-KERNEL P09.9; stop at that item. Approval of '
    'this design does not authorize P09.5 or a release successor.\n\n'
    'Materialize scripts/quality/infection-stacks/laravel{12,13}/composer.{json,lock} '
    'from candidate-stacks. These are sandbox templates: copy each to the cell root, '
    'along with scripts/quality/infection-pest-adapter, the current tenancy fragment and '
    'the same generated controls/probe. Their relative adapter path is intentionally '
    'resolved in the sandbox; do not run install in the template directory or link a '
    'cell vendor into root. No shared vendor, Composer aliases/replaces or dual framework '
    'autoloading. Testbench is included as an exact stack dependency; do not count its '
    'presence as a full Testbench/package suite run. Adapt only the local adapter manifest '
    'require to the hypothetical-adapter-composer.json exact Pest/PHPUnit alternatives, '
    'retain code behavior and version0.1.0; hash-bound source detects changes. Copy a '
    'locked adapter record into root via a targeted Composer lock update only after '
    'approval, preserving every other complete locked package record and content-hash. '
    'Do not update root Pest/PHPUnit/Laravel, root composer.json or package manifests. '
    'Locks captured here bind the hypothetical manifest; if content/ref changes during '
    'implementation, regenerate only that finite path record transparently and repeat '
    'the four stack install/platform/audit checks, not unrelated dependency updates.\n\n'
    'Policy/receipt v2: common engine versions + explicit Laravel12/Laravel13 stack maps; '
    'select exclusively by Laravel major, never whichever engine solves first. Pin '
    'Framework/Testbench/Pest/PHPUnit/Process and the transitive versions from each lock. '
    'prepare_cell copies manifest+lock, performs install --no-scripts --no-progress with '
    'only Pest and Infection extension plugins allowed; refuses update/no-lock fallback. '
    '--check-stacks materializes isolated dirs, validates strict manifests and lock '
    'consistency, installs and runs check-platform-reqs/audit on both actual PHP versions, '
    'and verifies root complete package-record invariance (only exact adapter require '
    'metadata may differ). Receipt binds stack id, original template manifest/lock '
    'digests, installed lock, all runtime versions, source/tests/controls/tools hashes, '
    'PHP binary/extensions, plugin/adapter trace, flags and process exits. Missing or '
    'mixed-stack cells, changed locks, old v1 receipts and stale/empty evidence fail.\n\n'
    'Fresh runtime acceptance: PHP8.4/8.5 × Laravel12/13 × sqlite/pgsql = eight rows. '
    'Keep the existing 13 cases: nine actual tenancy DatabaseValue cases, one real '
    'Laravel/PDO temporary-table probe and three controls, with 25 assertions. Use PCOV '
    'or Xdebug actually available; raw coverage XML/JUnit, not transformed class names. '
    'Infection0.35.6 --with-uncovered --with-timeouts --threads=1 --log-verbosity=all '
    '--debug uses the same bounded Pest CLI adapter. Each row must reproduce four real '
    'killed package mutants plus one each escaped, uncovered, timeout and controlled '
    'exit137 error; retain real stdout/stderr/exits and raw reports. Recognized generated '
    'compatibility timeout/error controls must be separately asserted and never counted '
    'as test kills; an unexpected discovery/bootstrap/PDO/coverage/OOM error is BLOCKED. '
    'Preserve strict warning/risky/empty-suite behavior. Any actual cross-Pest format '
    'difference needs version-specific validation from raw evidence, not relaxed '
    'assertions or normalization that fabricates success.\n\n'
    'PG runs serial after devops:db-test-preflight: Docker/readiness, actual *_test '
    'database and migration-state observation. Only connection-local temporary probe '
    'tables; no migrations/reset of application DB. Run current V1–V4 plus V5–V7 in '
    'P09.9-contract.json; V6 is an owned new option, not an existing executable feature. '
    'New negatives cover wrong Laravel engine, same lock reused across Laravel majors, '
    'wrong manifest hash, absent/mismatched lock, update fallback, stale v1/partial rows '
    'and root record drift. Keep current 19 receipt negatives. Budgets 2h + at most 2h '
    'fallback repair; block with exact evidence at budget/failure. Archive new results '
    'separately (P09.9-execution-after-D131) and never rewrite old incompatible proof. '
    'Self-check against these invariants; no public source change or broad test rewrite.\n\n'
    'Unchanged MSI policy: security95/messaging90/other75 per behavioral package-fragment; '
    'full source, uncovered denominator members, timeouts not kills, infrastructure '
    'errors block, syntax/compile invalid exclusions only with exact reproduction, '
    'equivalent exclusions exact/hash-bound with existing <=5% ceilings. P09.9 deliberately '
    'surviving controls are compatibility diagnostics only. P09.5 remains unexecuted; '
    'D125 excludes release qualification, RC/tags/publish and remote writes.'
)
save('document-input.json', {
    '$schema': 'urn:swissknifeman:task:plan:document:v1', 'id': 'P09.9-stack-proposal',
    'title': 'P09.9: два совместимых тестовых стека; OWNER-WAIT',
    'applies_to': ['P09.9'],
    'sections': [
        {'id': 'proposal', 'title': 'Предложение и точные версии', 'text':
         'Рекомендуемый вариант A: отдельные test-harness manifests/locks для Laravel12 и Laravel13. '
         'Root development stack остаётся Pest5.2.1/PHPUnit13.3.4. Единственное снижение закреплённых '
         'версий разрешается явно владельцем только в Laravel12 harness.\n\n'
         '| Laravel | Framework | Testbench | Pest | PHPUnit | Process |\n'
         '|---|---|---|---|---|---|\n|12|12.69.3|10.12.0|4.7.8|12.5.33|7.4.19|\n'
         '|13|13.35.0|11.3.0|5.2.1|13.3.4|8.1.7|\n\n'
         'Infection0.35.6, abstract adapter0.5.1, include interceptor1.0.0 и bounded adapter0.1.0 '
         'общие. Каждый Laravel lock проверен на обоих PHP. Матрица и пороги MSI95/90/75 сохранены. '
         'Цена: два воспроизводимых lock и проверка обоих семейств raw Pest reports. '
         'Вариант B: сохранить один закреплённый Pest5 стек; P09.9 остаётся blocked до upstream '
         'совместимости. Общий downgrade root стека до Pest4 не рекомендуется: затрагивает '
         'проверенный Laravel13 root без необходимости. Plain PHPUnit, ослабление порогов и '
         'сужение матрицы не являются вариантами этого предложения.'},
        {'id': 'evidence', 'title': 'Проверенные источники, solves и границы доказательства', 'text': evidence},
        {'id': 'amendment', 'title': 'Точная поправка к D120 и P09.9, ещё не применена', 'text':
         amendment + '\n\nMachine patch: artifacts/P09.9-stack-proposal/approved-plan-edits.json. '
         'Полный proposed item: artifacts/P09.9-stack-proposal/P09.9-contract.json. '
         'D120 и текущие acceptance/status/result P09.9 остаются неизменными до согласования. '
         'PLAN3.D131 — открытый вопрос владельцу; принятие варианта A разрешает эту точную '
         'поправку, не исполнение других items.'},
        {'id': 'execution', 'title': 'Контракт последующего исполнения только P09.9', 'text': execution},
    ],
    'provenance': {'path': 'artifacts/P09.9-stack-proposal/spike/receipt.json',
                   'sha256': hashlib.sha256((OUT / 'spike/receipt.json').read_bytes()).hexdigest(),
                   'note': 'Design evidence only. Four actual solve cells; no Pest4 runtime/MSI acceptance claimed.'},
})
save('decision-input.json', {
    'question': 'Одобрить вариант A: два repository-only harness стека, с Pest4.7.8/PHPUnit12.5.33 только для Laravel12, без изменения root pins/матрицы/MSI и без запуска P09.5/release?',
    'context': 'P09.9 blocked at a83248a0: Pest5.2.1 Process^8.1.7 cannot intersect Laravel12 Process^7.2.0. D120 and old mutation runbook prohibit silent version reduction. D125 remains repository-only.',
    'options': [
        {'id': 'A', 'text': amendment, 'pros': 'Four isolated spike and four root-projection solves/platform/audits pass; separate Laravel locks replay on PHP8.5. Existing Laravel13 root stays pinned; matrix and MSI stay strict.', 'cons': 'Two test harness locks and explicitly verified Pest4/5 report formats; runtime acceptance still required.'},
        {'id': 'B', 'text': 'Keep one Pest5.2.1/PHPUnit13.3.4 stack and P09.9 blocked until primary upstream dependencies permit Laravel12.', 'pros': 'No version-policy exception.', 'cons': 'Current exact constraints have no intersection; the complete supported matrix cannot run.'},
    ],
    'rationale': 'Recommend A: the minimum bounded dependency-policy exception preserving both supported Laravel majors and the existing root stack.',
    'applies_to': ['P09.9'],
    'evidence': [{'kind': 'repo', 'ref': 'artifacts/P09.9-execution/completion.md', 'note': 'Previous incompatible eight-row acceptance.'},
                 {'kind': 'repo', 'ref': 'artifacts/P09.9-stack-proposal/spike/receipt.json', 'note': 'Four actual interpreter solves, platform checks, audits and shared-lock replay.'},
                 {'kind': 'repo', 'ref': 'artifacts/P09.9-stack-proposal/receipt.json', 'note': 'Full root dependency projections; PHP8.5 uses extracted bcmath only.'},
                 {'kind': 'repo', 'ref': 'artifacts/P09.9-stack-proposal/sources/index.json', 'note': 'Exact tagged primary manifests and SHA256 captures.'},
                 {'kind': 'owner', 'ref': 'Owner message 2026-10-07: bounded P09.9 conflict proposal', 'note': 'No dependency pin changes before agreement; no P09.5/release execution.'}],
    'sections': [{'id': 'packet', 'title': 'Точная поправка и готовый контракт', 'text': 'knowledge/P09.9-stack-proposal; artifacts/P09.9-stack-proposal/approved-plan-edits.json. Await explicit owner acceptance of option A. No plan.py decide/grant/reopen/start/finish in this design session.'}],
})
save('task-runtime.json', {'launcher': str(TASK), 'sha256': hashlib.sha256(TASK.read_bytes()).hexdigest(),
                          'installed_adapter': '/home/vostrikov/.codex/plugins/cache/swissknifeman/task/0.28.4',
                          'note': 'Use current layout-v4 plan.py. Stale adapter plan-work.py instructions are inapplicable to the already migrated plan.'})
print('Prepared design-only packet; no accepted decision/item changes applied.')
