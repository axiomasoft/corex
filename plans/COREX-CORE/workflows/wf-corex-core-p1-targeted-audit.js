export const meta = {
  name: 'corex-core-p1-targeted-audit',
  description: 'Таргетированный финальный аудит фазы P1 (D63) — 4 Major-зоны волны 2, не замена канонического слепого аудита',
  whenToUse: 'После закрытия волны 2 (P1.14-P1.25), перед решением о запуске полного /task:plan-audit',
  phases: [
    { title: 'Review', detail: 'adversarial review по 4 зонам, параллельно, opus/high' },
    { title: 'Verify', detail: 'независимая верификация каждой находки Major/Blocker' },
  ],
}

const CTX = `КОНТЕКСТ:
- /home/vostrikov/projects/packages/corex — монорепо пакетов packages/{core,modules,crm,tasks,auth}.
- План: plans/COREX-CORE/ — plan.md (D1-D63), phases/P1.md (28 items), findings/, handoff.md.
- Волна 2 ремедиации (P1.14-P1.25) полностью закрыта (5 🟢, 21 🟠, 0 🔴). Это НЕ канонический слепой
  аудит фазы (тот описан в D45/P1.26 Code Guidance и требует Opus/xhigh без A-списка по всем 26 items) —
  это владельческая таргетированная проверка 4 зон с известными Major-отклонениями (D63), сужение
  зафиксировано в plan.md как решение, не подменяющее гейт P1.26.
- Ты — read-only адверсариальный ревьюер. Код НЕ правишь. Твоя задача — максимально скептично
  перепроверить конкретную зону: реализовано ли то, что заявлено в Completion Notes, а не найти
  повод похвалить работу.`

const ZONES = [
  {
    key: 'connection-seam',
    title: 'Connection-шов модуль-системы (P1.16/D52)',
    prompt: `${CTX}

ЗОНА: connection-резолвинг модуль-системы (packages/modules).

Прочитай:
1) plans/COREX-CORE/phases/P1.md — item P1.16 (Completion Notes, Known Deviations, блок "✅ РАЗРЕШЕНО")
2) plans/COREX-CORE/plan.md — D52 (решение владельца: P1-форма шва, полный ctx→connection резолвинг — P2/D13)
3) packages/modules/src/**/*.php — все места, где резолвится connection для ModuleStateReader/RecordsRegistrar
   и смежных классов (grep по "connection", "?string \\$connection")
4) tests/Feature/ModuleRegistryTest.php, tests/Feature/ModuleLifecycleTest.php — тесты на шов

Вопросы для проверки:
- Реализация СООТВЕТСТВУЕТ ли ровно тому, что зафиксировано в D52 (P1-форма шва, не более)?
- Есть ли места в коде, где P1-форма шва (статический \\$connection) молча ломается при реальном
  multi-tenant сценарии (DB-per-account), а не просто "не поддерживает" (это ожидаемо и ОК по D52) —
  ищи именно ТИХИЕ поломки: неверный connection используется без ошибки/предупреждения.
- Тесты реально проверяют шов через РЕАЛЬНОЕ подключение к другому connection, или это заглушка/мок?
- Любые расхождения между тем, что claim'ает Completion Notes P1.16, и тем, что реально в коде.

Верни находки (Blocker/Major/Minor/Nit) с конкретным file:line и failure_scenario.`,
  },
  {
    key: 'audit-sensitive',
    title: 'Audit sensitive-утечки (P1.21)',
    prompt: `${CTX}

ЗОНА: маскировка/исключение sensitive-полей в audit log (packages/core/src/Audit/*).

Прочитай:
1) plans/COREX-CORE/phases/P1.md — item P1.21 (Completion Notes: diff по $fillable, маска, killswitch)
2) packages/core/src/Audit/AuditObserver.php — record(), exceptFor(), sensitiveFor()
3) packages/core/src/Concerns/HasAuditLog.php
4) tests/Feature/AuditLogTest.php — тесты (а) guarded-absent-on-create, (б) sensitive-mask,
   (в) killswitch capability-путь

Вопросы для проверки (АНТИ-ПОДГОНКА — ищи именно утечку PII, это compliance-риск):
- Действительно ли guarded/не-fillable поля (password, remember_token) НИКОГДА не попадают в changes
  ни на одном пути (create/update/restored/forceDeleted)? Проверь ВСЕ пути диспатча, не только create.
  Ищи забытые observer-методы или альтернативные пути записи в sys_audit_log мимо AuditObserver.
- killswitch (Config::auditEnabled()) реально гасит запись на capability-пути (::observe), не только
  на trait-пути (HasAuditLog)? Есть ли путь, где модель регистрируется в EntityRegistry с Auditable,
  но killswitch не проверяется (например прямой вызов записи из другого сервиса)?
- Маска sensitive-полей — действительно маска (••••••), а не просто отсутствие ключа? Тест реально
  проверяет ОБА направления (old И new)?
- Любой сценарий, где sensitive-значение попадает в БД в открытом виде несмотря на заявленную защиту.

Верни находки (Blocker/Major/Minor/Nit) с конкретным file:line и failure_scenario.`,
  },
  {
    key: 'featureflags-cascade',
    title: 'FeatureFlags-каскад override (P1.22)',
    prompt: `${CTX}

ЗОНА: каскад приоритетов override'ов в Settings/FeatureFlags (packages/core/src/Features/*,
packages/core/src/Settings/*).

Прочитай:
1) plans/COREX-CORE/phases/P1.md — item P1.22 (Completion Notes, Known Deviations)
2) plans/COREX-CORE/plan.md — Update Log запись после закрытия P1.22 (известный дефект: findOverride()
   остался single-scope exact-match, головной дефект A69 переоформлен в WAI докблоком)
3) packages/core/src/Features/DatabaseFeatureFlags.php — active()/value()/define()/findOverride()
4) tests/Feature/FeatureFlagsTest.php, tests/Feature/SettingsTest.php

Известно заранее (не находка, контекст): реального narrow→wide каскада (user→workspace→account
внутри ОДНОГО вызова) нет — это принятое отклонение (Known Deviation, паритетно P1.7), не ищи это
как новую находку. Твоя задача — оценить РЕАЛЬНЫЙ операционный риск этого отклонения:
- Есть ли в коде (не в тестах) хоть один вызывающий сайт (RegistryCompiler/ModuleLifecycle/др.),
  который ОШИБОЧНО предполагает автоматический каскад и получит неверный результат в проде?
- payload-фолбэк (A87) — value() действительно возвращает payload ФЛАГА, а не override, когда
  override.payload = NULL? Проверь граничные случаи (override существует но payload явно null vs
  override не существует).
- define() — реально атомарен под гонкой (UNIQUE + catch), не оставляет частично применённое
  состояние при исключении?
- UnsupportedScopeException для department в Settings — реально бросается на ВСЕХ 4 методах
  контракта (не только на одном/двух)?

Верни находки (Blocker/Major/Minor/Nit) с конкретным file:line и failure_scenario. Фокус на
РЕАЛЬНЫХ потребителях в коде, не на гипотетических.`,
  },
  {
    key: 'departments-race',
    title: 'Departments concurrent-move (P1.23)',
    prompt: `${CTX}

ЗОНА: сериализация move() в Departments (packages/core/src/Departments/*).

Прочитай:
1) plans/COREX-CORE/phases/P1.md — item P1.23 (Completion Notes, Known Deviations — особенно
   "Known Deviation 2": mutation-check headline A5-фикса (lock ДО re-read) не запинен тестом,
   зарегистрированный mutation-кейс бьёт по soft-delete guard, не по lock-ordering)
2) packages/core/src/Departments/DatabaseDepartmentRepository.php — move() целиком
3) tests/Feature/DepartmentTest.php — все тесты вокруг move()/лока/конкурентности
4) scripts/mutation-check.sh — зарегистрированные P1.23-* кейсы, что именно они патчат

Известно заранее (контекст, не находка): mutation-check для headline A5-фикса (lock взят ДО
чтения изменяемой строки, не после) отсутствует — зарегистрированный кейс проверяет другую вещь
(soft-delete re-read guard). Это уже задокументировано как Known Deviation. Твоя задача:
- ПОДТВЕРДИ или ОПРОВЕРГНИ экспериментально: реально ли текущий код move() берёт advisory-лок ДО
  чтения перемещаемой строки (не полагаясь на прозу Completion Notes) — прочитай код построчно,
  укажи точные номера строк для read/lock/re-read.
- Если код реализован верно, но просто НЕ ЗАПИНЕН тестом (как заявлено) — это подтверждает
  Known Deviation, дай точную формулировку недостающего теста/мутации, которая закрыла бы разрыв.
- Если КОД тоже неверен (не только тест) — это НОВАЯ находка Blocker/Major, опиши конкретный
  сценарий двух конкурентных move(), приводящий к тихому no-op (A5 из исходного RED-аудита).
- DepartmentMoved диспатчится строго afterCommit (не внутри транзакции)? Есть ли путь, где это
  нарушается (например при вложенных транзакциях)?

Верни находки (Blocker/Major/Minor/Nit) с конкретным file:line и failure_scenario.`,
  },
]

const REVIEW_SCHEMA = {
  type: 'object',
  properties: {
    zone: { type: 'string' },
    verdict: { type: 'string', enum: ['GREEN', 'ATTENTION', 'RED'] },
    findings: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          severity: { type: 'string', enum: ['Blocker', 'Major', 'Minor', 'Nit'] },
          file: { type: 'string' },
          line: { type: 'number' },
          summary: { type: 'string' },
          failure_scenario: { type: 'string' },
        },
        required: ['severity', 'file', 'summary', 'failure_scenario'],
      },
    },
  },
  required: ['zone', 'verdict', 'findings'],
}

const VERDICT_SCHEMA = {
  type: 'object',
  properties: {
    refuted: { type: 'boolean' },
    reasoning: { type: 'string' },
  },
  required: ['refuted', 'reasoning'],
}

phase('Review')
const reviews = await parallel(
  ZONES.map((zone) => () =>
    agent(zone.prompt, {
      label: `review:${zone.key}`,
      phase: 'Review',
      model: 'opus',
      effort: 'high',
      schema: REVIEW_SCHEMA,
    }).then((r) => ({ zone, review: r }))
  )
)

const withFindings = reviews.filter(Boolean).filter((r) => r.review.findings?.length)
const highSeverity = withFindings.flatMap((r) =>
  r.review.findings
    .filter((f) => f.severity === 'Blocker' || f.severity === 'Major')
    .map((f) => ({ zoneKey: r.zone.key, zoneTitle: r.zone.title, finding: f }))
)

log(`Review done: ${reviews.filter(Boolean).length}/4 зон, ${highSeverity.length} Blocker/Major находок к верификации`)

phase('Verify')
const verified = await parallel(
  highSeverity.map((item) => () =>
    agent(
      `Адверсариально верифицируй эту находку из таргетированного аудита COREX-CORE (зона: ${item.zoneTitle}).
Твоя задача — попытаться ОПРОВЕРГНУТЬ находку (доказать, что она ложная/неточная), не защищать её.
Прочитай указанный файл/строку в /home/vostrikov/projects/packages/corex, прогони реальный код/тест если применимо.

Находка (severity=${item.finding.severity}): ${item.finding.summary}
Файл: ${item.finding.file}${item.finding.line ? ':' + item.finding.line : ''}
Сценарий отказа: ${item.finding.failure_scenario}

При неуверенности — refuted=false (не опровергнута).`,
      { label: `verify:${item.zoneKey}`, phase: 'Verify', model: 'opus', effort: 'medium', schema: VERDICT_SCHEMA }
    ).then((v) => ({ ...item, verdict: v }))
  )
)

const confirmed = verified.filter(Boolean).filter((v) => !v.verdict.refuted)
const refuted = verified.filter(Boolean).filter((v) => v.verdict.refuted)

return {
  scope: 'D63 таргетированный аудит — 4 зоны (не замена канонического слепого аудита P1.26)',
  zones_reviewed: reviews.filter(Boolean).map((r) => ({ zone: r.zone.key, verdict: r.review.verdict, findings_count: r.review.findings?.length ?? 0 })),
  all_findings: withFindings.flatMap((r) => r.review.findings.map((f) => ({ zone: r.zone.key, ...f }))),
  blocker_major_confirmed: confirmed.map((c) => ({ zone: c.zoneKey, ...c.finding, verification: c.verdict.reasoning })),
  blocker_major_refuted: refuted.map((c) => ({ zone: c.zoneKey, ...c.finding, verification: c.verdict.reasoning })),
}
