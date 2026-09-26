from pathlib import Path
import gzip, hashlib, json, re, shutil, subprocess, xml.etree.ElementTree as E

repo = Path.cwd()
plan = Path('plans/2026.09.19-№1-COREX-PLATFORM-HARDENING')
work = Path('build/P14.21')
out = plan / 'artifacts/P14.21-execution'
out.mkdir(parents=True, exist_ok=True)
sha = lambda p: hashlib.sha256(Path(p).read_bytes()).hexdigest()
def write(name, value):
    (out / name).write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n')

checks = {r['id']: r for r in json.loads((work / 'checks.json').read_text())}
for ident, r in checks.items():
    assert r['exit_code'] == 0, ident
    assert sha(r['log']) == r['log_sha256'], ident
    dest = out / Path(r['log']).name
    shutil.copyfile(r['log'], dest)
    r['log'] = dest.name
    r['status'] = 'green'

trees = {}
for lane in ['root-test', 'root-pg', 'api-test', 'api-pg', 'modules-test', 'transfer-test', 'transfer-pg']:
    source = work / f'{lane}.xml'
    tree = E.parse(source)
    cases = list(tree.iter('testcase'))
    failures = sum(t.find('failure') is not None for t in cases)
    errors = sum(t.find('error') is not None for t in cases)
    skips = sum(t.find('skipped') is not None for t in cases)
    assert cases and failures == errors == skips == 0, (lane, len(cases), failures, errors, skips)
    trees[lane] = cases
    archived = out / f'{lane}.xml.gz'
    archived.write_bytes(gzip.compress(source.read_bytes(), mtime=0))
    checks[lane]['junit'] = {'artifact': archived.name, 'sha256': sha(archived), 'uncompressed_sha256': sha(source),
                            'tests': len(cases), 'failures': failures, 'errors': errors, 'skipped': skips}

for pkg in ['api', 'modules', 'transfer']:
    source = work / f'bc-{pkg}.log'
    assert source.read_text().rstrip().endswith('bc-check: OK')
    shutil.copyfile(source, out / source.name)
    checks[f'bc-{pkg}'] = {'id': f'bc-{pkg}', 'argv': ['composer', 'bc-check'], 'cwd': str(repo),
        'environment': {'BC_CHECK_ONLY': pkg}, 'exit_code': 0, 'log': source.name, 'log_sha256': sha(source),
        'status': 'report-only-breaks' if pkg == 'api' else 'compatible',
        'breaks': 9 if pkg == 'api' else 0, 'skipped': False}

exact = work / 'root-exact-final.log'
assert '"result":"passed"' in exact.read_text().replace(' ', '')
shutil.copyfile(exact, out / exact.name)
checks['root-exact'] = {'id': 'root-exact', 'argv': ['composer', 'test'], 'cwd': str(repo),
    'environment': checks['root-test']['environment'], 'exit_code': 0, 'status': 'green',
    'log': exact.name, 'log_sha256': sha(exact)}
mutation = (out / checks['mutations']['log']).read_text()
assert mutation.count('OK — тест покраснел') == 34
assert not re.search(r'No tests found|"tests"\s*:\s*0[,}]', mutation)
for row in json.loads((work / 'mutation-targets.json').read_text()):
    assert sha(row['path']) == row['sha256'], row['path']
for name in ['mutation-targets.json', 'mutation-restoration.json', 'mutation-restoration-negative.log',
             'repair-pre.json', 'root-test.log', 'root-analyse.log']:
    shutil.copyfile(work / name, out / name)

# Every row names successful, non-skipped test cases from an actual JUnit carrier.
matrix = [
    ('R1', 'Low-cost forgery / overflow / no estimator I/O', 'root-test', ['estimates table-driven weights', 'denies overflow', 'does not call database, cache or network']),
    ('R2', 'Four-bucket hierarchy and concurrent global reserve', 'api-test', ['Reserve debits four hmac buckets', 'Default global enforcement observes', 'Enforced deficit rolls back']),
    ('R3', 'Single settlement, debt, no expired/missing refund', 'api-test', ['Settlement refunds once', 'Reject refunds once', 'Expired and missing reservations']),
    ('R4', 'HTTP/tool cost metadata, replay base-only, Retry-After', 'api-test', ['Http and exposed tool share', 'Budget denial rolls back', 'Http rate limit keeps retry after']),
    ('R5', 'Duplicate bulk submit, polling without notification, fenced revision', 'api-test', ['Same submission returns one operation', 'Expired lease takeover']),
    ('R6', 'Import checkpoint crash, revocation and cooperative cancellation', 'api-test', ['Import resume does not repeat', 'Stamp drift fails before']),
    ('R7', 'Unsealed export restart, immutable verified sealed parts', 'transfer-pg', ['aborts an unsealed capture', 'seals verified immutable parts', 'captures source pages']),
    ('R8', 'Foreign/revoked bulk result never reads storage, bounded failure page', 'api-test', ['Foreign caller and revoked field access', 'Failure cursor rejects limits']),
    ('R9', 'Atomic inbox/fanout, duplicate event materialization', 'api-test', ['Projection failure rolls back', 'Duplicate event creates one delivery', 'Postgresql inbox and delivery']),
    ('R10', 'Independent HMAC bytes, raw tamper and timestamp tolerance', 'api-test', ['Independent known hmac', 'Receiver denies raw tamper']),
    ('R11', 'Old/new overlap, explicit readiness/revoke', 'api-test', ['Old and new signatures verify', 'Http success does not infer readiness', 'Compromise can revoke']),
    ('R12', 'SSRF DNS set validation, pin/TLS, redirect/body/header bounds', 'api-test', ['Public dns set is canonicalized', 'Organization owned ranges', 'Loopback tls receiver', 'Redirect is returned once', 'Response body and headers', 'Socket peer must equal']),
    ('R13', 'ACK loss, stable identity/body, receiver dedup and lease fence', 'api-test', ['Ack loss repeats same id', 'Response matrix is fenced', 'Retry after jitter attempt']),
    ('R14', 'Same-byte replay, finite ranges/cycles, expiry and idempotency', 'api-test', ['Replay keeps delivery identity', 'Active lease changed', 'Range is bounded', 'Shared dispatcher preserves idempotency']),
    ('R15', 'PII/secret exclusion from topic projection and public attempts', 'api-test', ['Sensitive topics require', 'Projector exports only', 'Public attempt history has an exact safe shape', 'Detail attempts and subscription projection']),
    ('R16', 'Planted compatibility breaks refuse, additive topics accepted, stable snapshots', 'root-test', ['Planted webhook incompatibilities', 'Generation is byte stable']),
]
mapped = []
for ident, risk, lane, patterns in matrix:
    selected = []
    for pattern in patterns:
        matches = [t for t in trees[lane] if pattern in t.get('name', '')]
        assert matches, (ident, pattern)
        selected += [{'class': t.get('class'), 'name': t.get('name'), 'file': t.get('file'), 'status': 'passed'} for t in matches]
    mapped.append({'id': ident, 'risk': risk, 'carrier': lane, 'junit_sha256': checks[lane]['junit']['uncompressed_sha256'], 'tests': selected})
for ident, lane, pattern in [('R2', 'root-pg', 'Postgresql concurrent reserve'),
                             ('R6', 'transfer-pg', 'returns cancelled at the read boundary'),
                             ('R6', 'transfer-pg', 'does not write checkpoint, source, or target'),
                             ('R6', 'transfer-pg', 'commits local result and fenced checkpoint')]:
    matches = [t for t in trees[lane] if pattern in t.get('name', '')]
    assert matches, (ident, pattern)
    next(r for r in mapped if r['id'] == ident).setdefault('additional_carriers', []).append({
        'carrier': lane, 'junit_sha256': checks[lane]['junit']['uncompressed_sha256'],
        'tests': [{'class': t.get('class'), 'name': t.get('name'), 'file': t.get('file'), 'status': 'passed'} for t in matches]})
write('acceptance-matrix.json', mapped)
write('commands.json', list(checks.values()))

files = subprocess.check_output(['git', 'ls-files', '--cached', '--others', '--exclude-standard'], text=True).splitlines()
inputs = []
for name in sorted(set(files)):
    p = Path(name)
    if not p.is_file(): continue
    relevant = name.startswith(('tests/', 'scripts/', 'api/v1/')) or name in ['composer.json', 'composer.lock', 'phpunit.xml', 'phpstan.neon', 'pint.json']
    relevant |= name.startswith('packages/') and any('/' + part + '/' in name for part in ['src', 'tests', 'database', 'config'])
    relevant |= name.startswith('packages/') and p.name in ['composer.json', 'phpunit.xml', 'phpstan.neon', 'pint.json']
    if relevant: inputs.append({'path': name, 'sha256': sha(p)})
write('inputs.json', {'algorithm': 'sha256', 'members': inputs,
    'aggregate_sha256': hashlib.sha256(json.dumps(inputs, sort_keys=True, separators=(',', ':')).encode()).hexdigest()})
write('preflight.json', {'observed': '2026-09-26', 'app_env': 'testing', 'root_default_db': 'sqlite/:memory:',
    'pgsql': {'container': 'corex_pgsql_test', 'healthy': True, 'version': '16.14', 'host': '127.0.0.1', 'port': 5434,
              'database': 'corex_test', 'name_guard': '*_test', 'pg_isready_exit': 0, 'current_database_verified': True},
    'schema_setup': 'Disposable test migrations and api_p14* schemas; no production DDL',
    'network': 'Synthetic in-process receiver and explicit local TLS fixture only; no live external receiver',
    'php': '8.4.1', 'composer': '2.8.3', 'framework': 'Laravel 13.31',
    'api_pg_fallback': 'No package test:pg script: declared Feature pg group through root phpunit.xml, 8 tests, no skips'})

lines = ['# P14 execution qualification — P14.21', '', 'Кандидат проверен 2026-09-26 в текущем checkout; run `28e7a6a9b496ad724e2ca0981ab3a277b0febe96672a0b6f48dda3e3a74dbe5b`, attempt 1.',
    'Дизайн: [P14 §0–14](../research/P14-design.md), [D84](../decisions/D84-replay-retention-boundary.md). Это локальная qualification, без production rollout, внешних sends, migration или rotation.', '',
    '## Проверяемые носители', '', 'Полные argv, cwd, environment, exit codes, SHA-256 логов и JUnit находятся в [commands.json](../artifacts/P14.21-execution/commands.json).',
    'JUnit архивы содержат конкретные testcase names; во всех положительных полосах failures=errors=skips=0. PG env установлен явно, включая API tests без group tag. Counts полос пересекаются и не суммируются.', '',
    '| Носитель | Результат |', '|:--|:--|']
for ident in ['root-lint', 'root-analyse', 'root-test', 'root-pg', 'api-lint-check', 'api-analyse', 'api-test', 'api-pg', 'modules-lint-check', 'modules-analyse', 'modules-test', 'transfer-lint-check', 'transfer-analyse', 'transfer-test', 'transfer-pg', 'arch', 'api-contract', 'snapshot-compatibility', 'package-graph', 'provider-size', 'extension-contract', 'table-ownership', 'contract-guard', 'mutations']:
    r = checks[ident]
    count = f"; {r['junit']['tests']} tests; 0 skipped" if 'junit' in r else ''
    if ident == 'arch': count = '; 89 tests / 286 assertions'
    if ident == 'mutations': count = '; 34/34 nonempty sabotage carriers refuse; source bytes restored'
    lines.append(f"| `{ident}` — `{' '.join(r['argv'])}` | exit 0{count}; [log](../artifacts/P14.21-execution/{r['log']}) |")
lines += ['', 'Root `composer test` также повторён без дополнительных аргументов после repair; 815 tests / 3493 assertions. Root PG: 239 / 1149; API: 267 / 1877; modules: 83 / 328; transfer: 8 / 24 + PG 23 / 83. API PG fallback: 8 / 130.', '',
    'BC: `BC_CHECK_ONLY=modules composer bc-check` и `BC_CHECK_ONLY=transfer composer bc-check` — compatible, exit 0. `BC_CHECK_ONLY=api composer bc-check` — exit 0 по experimental report-only policy, **9 historical breaks** против старого baseline (`Dispatcher`, `GuardedToolInvoker`, `ApiController`). Это не clean API BC и не разрешение stable release; [полный отчёт](../artifacts/P14.21-execution/bc-api.log). Skipped BC lanes нет.', '',
    '## Acceptance / threat matrix §14', '', 'Конкретные testcase names, class/file и SHA-256 исходного JUnit: [acceptance-matrix.json](../artifacts/P14.21-execution/acceptance-matrix.json).', '',
    '| Риск | Проверяемый отказ / поведение | Носитель |', '|:--|:--|:--|']
for r in mapped:
    carriers = r['carrier'] + (', ' + ', '.join(sorted({a['carrier'] for a in r.get('additional_carriers', [])})) if r.get('additional_carriers') else '')
    lines.append(f"| {r['id']} | {r['risk']} | `{carriers}`; GREEN |")
lines += ['', 'R12 URL-policy negative data sets дополнительно покрывают private/mixed/IPv6/IP-literal/host syntax; локальный TLS receiver проверяет wire bytes и hostname. Production policy не получает loopback exception. R16 запускает planted frozen-contract violations с exit 1 и additive optional fixtures с exit 0; generator `--check` проверяет пять snapshots и manifest, а не переписывает baseline молча.', '',
    '## Восстановленные обязательные проверки', '',
    'Исходные root analyse/test были RED: 7 analysis errors, а suite — 662 passed, 5 failures, 148 errors (815 total). P11 canonical hash/snapshot publication отсутствовали в dirty baseline; P14 HTTP projection теряла cost metadata/Retry-After; existing safe error declarations и webhook topic projection не были внесены в gates. Bounded repair восстановил уже принятые contracts без новых dependencies или смены public signatures. Подробности и original-carrier fingerprint: [repair-pre.json](../artifacts/P14.21-execution/repair-pre.json), baseline logs; post-validation receipt сохраняется в journal и artifacts.', '',
    'Обязательный mutation-check сначала отказал на 6 stale patches. Старый failed 3-way rollback сбросил dirty bytes; они восстановлены из pre-run копии. Harness теперь сохраняет исходные bytes и logical index entries до apply и возвращает их при отказе; 6 patches реанкорены к тем же sabotage boundaries. Исправлен renamed state-reader filter; empty carrier отвергается, поэтому прежний vacuous результат не считается GREEN. Финальный all run: 34/34 nonempty RED under sabotage, harness exit 0; [negative dirty+staged restoration proof](../artifacts/P14.21-execution/mutation-restoration.json).', '',
    '## Эксплуатационная граница и retained limits', '',
    '- Cost support по умолчанию выключен (`cost.enabled=false`). После явного включения global budget enforced, account/workspace/client observe по умолчанию; observe может накапливать debt. Host задаёт budgets, общий configured connection и стабильный installation id/HMAC secret. Refund однократный; unknown/expired/missing settlement не освобождает estimate. HTTP/tool используют общий invoker; replay стоит admission base 1.',
    '- Bulk требует optional публичный TransferFactory и host admission adapter, который связывает trusted scope/client/definition и текущую authorization/field policy. Literal bulk routes потребляют admitted bindings; общий HTTP/tool dispatcher сам по себе не доказывает этот host integration. Default result reader deny. Import resumable по atomic checkpoint; cancel cooperative и сохраняет уже committed эффекты, без обещания rollback всего job. Уже начатый remote call не прерывается автоматически.',
    '- Export unsealed attempt abort/restart; sealed immutable parts проверяются по hashes под текущей authority. Transfer defaults: input/result 1 GiB, row 1 MiB, 256 fields, JSON depth 32, batch 500, retained row errors 1000, runtime 30 s, memory 128 MiB. Guards на boundaries; blocking call требует host timeout/worker backstop. Additional cap/stamp fixtures находятся в transfer PG receipt.',
    '- Webhooks at-least-once без ordering. Durable receiver inbox+effect обязательны при ACK loss; sender lease fence не отменяет ушедший HTTP. Canonical body 20 KiB; active subscriptions/account и matching/event 100; keys 32 bytes, максимум 2 в overlap, timestamp ±300 s; revoke требует explicit readiness (compromise branch допускает немедленную потерю continuity).',
    '- HTTPS:443; весь DNS set validated и pinned, TLS hostname/SNI preserved, redirects/proxies/fallback DNS denied. Connect/total timeouts 3/10 s, discarded response 64 KiB, headers 16 KiB. Retry: lease 30 s; 12 attempts / 72 h per cycle; full jitter base 5 s, cap 1 h, min 1 s; bounded Retry-After 24 h.',
    '- D84 replay: та же delivery/id/body/destination, максимум 3 cycles внутри абсолютных 7 d; range ≤24 h, ≤100 entries, fixed upper bound, parent idempotency и scoped cursor. Body TTL 7 d; metadata/attempts 30 d; shorter PII TTL сокращает replay horizon вместе с body. Active delivery не prune; batch ≤100. Delivery tombstone и P5 inbox сохраняются весь redelivery horizon, по умолчанию indefinite. History retention не означает новую delivery после expiry.',
    '- Worker требует active trusted tenant; multi-account scheduler отсутствует. Delivery default off; локальная проверка не даёт live-send или production secret/migration authority. Existing local G-API/G-SECRET grants и P14.13/P14.14 security review provenance сохраняются; signer, rotation и SSRF algorithm этим repair не менялись.', '',
    'Host setup и все caps: [API README](../../../packages/api/README.md), [API policy](../../../docs/architecture/api-policy.md), [message policy](../../../docs/architecture/message-policy.md).', '',
    '## Freshness', '',
    'Exact relevant product/test/tool/config inputs (including negative fixtures, snapshots and package manifests) и aggregate SHA-256: [inputs.json](../artifacts/P14.21-execution/inputs.json). [preflight.json](../artifacts/P14.21-execution/preflight.json) фиксирует host/container split и isolated target. Qualification не заявляет coverage percentage, live performance SLA или migration readiness production. Dirty predecessor work остаётся отдельно от scoped commit; receipt квалифицирует фактический checkout.', '']
(plan / 'findings/P14-execution-validation.md').write_text('\n'.join(lines))
print(json.dumps({'checks': len(checks), 'matrix_rows': len(mapped), 'input_files': len(inputs), 'receipt': str(plan / 'findings/P14-execution-validation.md')}, ensure_ascii=False))
