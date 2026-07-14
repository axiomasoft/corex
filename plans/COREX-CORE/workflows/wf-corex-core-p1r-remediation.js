export const meta = {
  name: 'corex-core-p1r-remediation',
  description: 'Волна ремедиации P1 (items P1.14–P1.25) плана COREX-CORE: ветка+CI, per-item модель по Routing, mutation-check по патчам, adversarial review, stop-close при остановке',
  whenToUse: 'После /task:plan-design COREX-CORE P1 (ре-дизайн по 🔴 RED-аудиту). Предусловие: OQ-11 закрыт владельцем (иначе P1.18 строит не то). P1.26 (CHANGELOG+теги) — вручную после GREEN-аудита',
  phases: [
    { title: 'Impl', detail: 'реализация item по ТЗ (модель — из Routing §3 plan.md)' },
    { title: 'Verify', detail: 'механический верификатор (sonnet/low): гейт, mutation-check по патчам, дифф, CI' },
    { title: 'Review', detail: 'adversarial-ревью диффа (opus/high), без якоря на отчёт исполнителя' },
    { title: 'Close', detail: 'bookkeeping плана + handoff (sonnet/low)' },
  ],
}

const BRANCH = 'p1r-remediation'
const LINT = 'python3 /home/vostrikov/projects/packages/swissknifeman/packages/task/scripts/plan-lint.py plans/COREX-CORE'

const CTX = `
КОНТЕКСТ (репо проверено 2026-07-14):
- /home/vostrikov/projects/packages/corex — монорепо пакетов packages/{core,modules,crm,tasks,auth}.
- План: plans/COREX-CORE/ — plan.md (D1–D45), phases/P1.md (26 items), findings/, handoff.md.
- Волна 2 (P1.14–P1.26) — ремедиация после 🔴 RED-аудита фазы P1: 93 находки
  (findings/P1-audit-2026-07-13.md, коды A1..A93; матрица покрытия — findings/P1R-coverage-matrix.md).
- Pre-mortem волны: findings/P1R-premortem.md (кластеры C1–C8 — то, что волна НЕ закрывает).
- ВЕТКА (D45): вся работа — на ветке ${BRANCH}, каждый item пушится. «Локально зелено» НЕ доказательство:
  волна 1 (28 коммитов) никогда не пушилась, CI не видел ни строчки кода P1.
- ГЕЙТ (D34): docker compose up -d && composer lint:check && composer analyse && composer test && composer test:pg.
- MUTATION-CHECK: у каждого item'а в Validation названы кейсы. Патч-саботаж КОММИТИТСЯ
  (plans/COREX-CORE/mutations/<item>-<case>.patch), прогоняется scripts/mutation-check.sh (harness ставит
  P1.14) отдельным верификатором и воспроизводим кем угодно, включая plan-audit. Проза «я проверил»
  доказательством НЕ является.
- Коммиты: Conventional Commits по-русски; git add ТОЛЬКО по путям из Files item'а + файлы плана.
  git add -A / git commit -a ЗАПРЕЩЕНЫ.
- Сегодня 2026-07-14.
`

const ITEMS = [
  { id: 'P1.17', model: 'sonnet', effort: 'medium', deps: [], title: 'Гейт компиляции: валидатор' },
  { id: 'P1.18', model: 'opus', effort: 'high', deps: [], title: 'Lifecycle: seq-порядок, все claim-типы (D41), all-or-nothing enable (D40)' },
  { id: 'P1.19', model: 'opus', effort: 'high', deps: [], title: 'Миграции: publish-скоупы (D43), IdColumns, µs, sys_audit_log.seq (D42)' },
  { id: 'P1.20', model: 'sonnet', effort: 'medium', deps: ['P1.19'], title: 'ID-strategy: единый дефолт + типы колонок на PG' },
  { id: 'P1.21', model: 'opus', effort: 'high', deps: ['P1.19'], title: 'Audit: sensitive, killswitch, время события, курсор по seq' },
  { id: 'P1.22', model: 'sonnet', effort: 'medium', deps: ['P1.19'], title: 'Settings/FeatureFlags: каскад скоупов, payload, констрейнт-тесты' },
  { id: 'P1.23', model: 'opus', effort: 'high', deps: ['P1.19'], title: 'Departments: lock-then-read, after-commit, soft-delete' },
  { id: 'P1.24', model: 'sonnet', effort: 'medium', deps: ['P1.19'], title: 'PubSub: guard, durable-курсор (D44), ретеншн' },
  { id: 'P1.25', model: 'sonnet', effort: 'medium', deps: ['P1.18'], title: 'Lifecycle-события на DomainEvent + wiring-хвосты' },
]

const IMPL_SCHEMA = {
  type: 'object',
  properties: {
    item: { type: 'string' },
    done: { type: 'boolean', description: 'true только если ВСЕ Deliverables сданы и гейт зелёный' },
    gate: { type: 'string', description: 'вывод четырёх гейт-команд: lint/analyse/test/test:pg — числа, не слова' },
    mutation_cases: { type: 'array', items: { type: 'string' }, description: 'имена кейсов из Validation, для которых закоммичены патчи в plans/COREX-CORE/mutations/' },
    commits: { type: 'array', items: { type: 'string' } },
    pushed: { type: 'boolean', description: 'ветка запушена (git push)' },
    files_outside_scope: { type: 'array', items: { type: 'string' }, description: 'тронутое вне Files item’а — честно; влияет на статус (🟠)' },
    pending_work: { type: 'string' },
    known_deviations: { type: 'string' },
    escalation: { type: 'string', description: '«no» либо причина + trigger (plan-protocol §10)' },
  },
  required: ['item', 'done', 'gate', 'mutation_cases', 'commits', 'pushed', 'files_outside_scope', 'pending_work', 'known_deviations', 'escalation'],
}

const REVIEW_SCHEMA = {
  type: 'object',
  properties: {
    item: { type: 'string' },
    verdict: { type: 'string', enum: ['GREEN', 'ATTENTION', 'RED'] },
    deliverables_met: { type: 'boolean' },
    false_claims: { type: 'array', items: { type: 'string' }, description: 'заявлено в Completion Notes, но в коде нет' },
    findings: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          severity: { type: 'string', enum: ['Blocker', 'Major', 'Minor', 'Nit'] },
          file: { type: 'string' },
          summary: { type: 'string' },
          failure_scenario: { type: 'string' },
        },
        required: ['severity', 'file', 'summary', 'failure_scenario'],
      },
    },
    summary: { type: 'string' },
  },
  required: ['item', 'verdict', 'deliverables_met', 'false_claims', 'findings', 'summary'],
}

function implPrompt(item) {
  return `Ты исполнитель item'а ${item.id} мастер-плана COREX-CORE (${item.title}).
${CTX}
ПОРЯДОК:
1. Прочитай (ты стартуешь ХОЛОДНЫМ): plans/COREX-CORE/plan.md (§2 Rules, §3 Routing, §5 D28–D45) →
   plans/COREX-CORE/phases/P1.md, раздел «### ${item.id} — …» ЦЕЛИКОМ (16 полей) → Required Reads по порядку.
2. Ветка: \`git switch ${BRANCH} 2>/dev/null || git switch -c ${BRANCH}\`. Рабочее дерево по ТВОИМ путям обязано
   быть чистым (чужой дифф — не трогать). Если по путям item'а есть незакоммиченное — СТОП, escalation.
3. Реализуй Scope Included. Scope Excluded не трогай.
4. Тесты по Validation. Для КАЖДОГО mutation-кейса из Validation закоммить патч-саботаж в
   plans/COREX-CORE/mutations/${item.id}-<case>.patch (реальный git-diff, применяемый \`git apply\`), чтобы
   раннер мог прогнать его САМ. Патч обязан ронять названный тест; зелёный патч = кейс не пинит границу.
5. Гейт (D34): docker compose up -d && composer lint:check && composer analyse && composer test && composer test:pg.
6. Коммит по путям из Files + git push origin ${BRANCH}. git add -A ЗАПРЕЩЁН. Чужой дифф в дереве
   (.claude/, CLAUDE.md, AGENTS.md) НЕ трогать и назвать в files_outside_scope.
7. Заполни Completion Notes / Pending Work / Known Deviations в phases/P1.md ФАКТАМИ.

ЗАПРЕЩЕНО: ослаблять Validation ради зелёного; помечать done при красном гейте; подгонять тест под
реализацию; править ТЗ item'а под то, что получилось; писать в Completion Notes то, чего нет в коде
(волна 1 получила RED именно за это).
ЭСКАЛАЦИЯ (§10): конфликт правил / неисполнимое требование / расхождение плана с кодом → done=false + escalation.`
}

function reviewPrompt(item, verify) {
  return `Ты adversarial-ревьюер item'а ${item.id} мастер-плана COREX-CORE. Read-only: код НЕ правишь.
${CTX}
Механический верификатор (отдельный агент, которому нечего защищать) прогнал проверки — вот СЫРОЙ вывод.
Верь ему и коду, а не прозе исполнителя:
${verify}

ПОРЯДОК (начни с диффа, а не с отчёта исполнителя — иначе получишь якорь):
1. git log/diff ветки ${BRANCH} по item'у — что РЕАЛЬНО изменилось.
2. ТЗ item'а: phases/P1.md «### ${item.id}» — Scope Included, Deliverables, Validation, Code Guidance.
3. Сверь КАЖДЫЙ пункт Deliverables с кодом. Не подтверждён кодом → deliverables_met=false → RED.
4. Только теперь прочитай Completion Notes item'а и найди false-claims (заявлено ≠ в коде).
5. Ищи регрессии в соседних подсистемах, которые тесты item'а не покрывают.

Вердикт: RED — не сдан / есть Blocker или Major; ATTENTION — сдан, есть Minor; GREEN — чисто.
Не занижай severity, чтобы «не останавливать волну»: RED останавливает ТОЛЬКО этот item и его
зависимые, остальные items волны продолжатся. Цены за честность нет.
Каждая находка несёт конкретный failure scenario (входные данные → неверный результат).`
}

function closePrompt(item, impl, review, verify) {
  return `Ты docs-curator плана COREX-CORE. Работа механическая, thinking низкий.
${CTX}
Item ${item.id}. Факты верификатора (сырой вывод команд):
${verify}
Отчёт исполнителя: ${JSON.stringify(impl)}
Вердикт ревью: ${JSON.stringify(review)}

ЗАДАЧА (только bookkeeping, КОД НЕ ТРОГАТЬ):
1. phases/P1.md «### ${item.id}»: Status = 🟢 Done, если вердикт GREEN И files_outside_scope пуст;
   иначе 🟠 Done with deviations (plan-protocol §6). Completion Notes — из МАШИННЫХ фактов раннера
   (гейт, mutation-кейсы, sha), не из прозы. Свободный текст — только в Known Deviations.
2. Обнови «## Phase Status» (строка ${item.id}) и plan.md «## 4. Status Board» + «## 6. Update Log»
   (одна строка: «${item.id} закрыт: <суть> — детали см. phases/P1.md ${item.id} Completion Notes»).
3. Перезапиши handoff.md: «# HANDOFF — 2026-07-14 — after ${item.id}», Next — launch-block на следующий
   item волны (или на /task:plan-audit COREX-CORE P1, если волна завершена), Done / Remaining /
   Sources of truth / Open risks / Workarounds-Deferred-Open questions.
4. ${LINT} — новых ошибок от диффа быть не должно.
5. git add plans/COREX-CORE && git commit -m "docs(plan): закрыть ${item.id} в COREX-CORE — статус, completion notes, handoff" && git push origin ${BRANCH}.

Верни одной строкой: статус + sha.`
}

function stopClosePrompt(item, stage, reason) {
  return `Ты docs-curator плана COREX-CORE. Волна ОСТАНОВЛЕНА на ${item.id} (стадия: ${stage}).
${CTX}
Причина: ${reason}

ЗАДАЧА (план не имеет права лгать о состоянии — это ровно та ошибка, за которую фаза получила RED):
1. phases/P1.md «### ${item.id}»: Status = 🔴 Blocked, Escalation Needed = yes + причина,
   Pending Work = что успели (sha частичных коммитов, если есть).
2. Зафиксируй состояние рабочего дерева: вывод \`git status --porcelain\` — в Pending Work item'а.
   Незакоммиченное НЕ выбрасывай: \`git stash push -m "P1R-${item.id}-partial"\` и назови stash-ref.
3. Обнови «## Phase Status» + plan.md Status Board.
4. Перезапиши handoff.md: Next — launch-block на РАЗБОР остановки (/task:plan-design COREX-CORE ${item.id}
   при ре-дизайне ТЗ, либо повторный Workflow с resumeFromRunId после точечного фикса).
5. ${LINT}; git add plans/COREX-CORE && git commit -m "docs(plan): остановка волны на ${item.id} — 🔴 Blocked, handoff" && git push origin ${BRANCH}.`
}

// ---------------------------------------------------------------------------
// Верификатор: ОТДЕЛЬНЫЙ механический агент. В Workflow-рантайме нет не-LLM-раннера
// (bash() как хука не существует), поэтому «детерминированность» обеспечивается иначе:
// у верификатора НЕТ задачи «сдать item» (ему нечего защищать), промпт запрещает чинить и
// интерпретировать, а сами проверки лежат в репо (patch-файлы + scripts/mutation-check.sh) —
// их может перепрогнать кто угодно, включая plan-audit. Это честная планка, а не «раннер».
// ---------------------------------------------------------------------------
function verifyPrompt(item) {
  return `Ты МЕХАНИЧЕСКИЙ верификатор. Твоя задача — ВЫПОЛНИТЬ команды и вернуть их СЫРОЙ вывод.
Ты НЕ чинишь код, НЕ интерпретируешь результаты, НЕ решаешь, сдан ли item. Ничего не защищаешь.
Репо: /home/vostrikov/projects/packages/corex, ветка ${BRANCH}, item ${item.id}.

Выполни по порядку и верни вывод КАЖДОЙ команды дословно (включая ненулевые exit-коды):
1) git status --porcelain
2) docker compose up -d && (pg_isready или docker compose ps)
3) composer lint:check ; composer analyse ; composer test ; composer test:pg
4) mutation-check: для каждого файла plans/COREX-CORE/mutations/${item.id}-*.patch выполни
   bash scripts/mutation-check.sh <patch>. Скрипт применяет патч-саботаж, гоняет названный тест,
   ТРЕБУЕТ ненулевой exit (тест обязан ПОКРАСНЕТЬ) и откатывает патч. Если патчей нет —
   верни строку «MUTATION-CHECK НЕ ВЫПОЛНЕН: нет патчей для ${item.id}».
5) git show --stat HEAD (дифф последнего коммита item'а)
6) gh run list --branch ${BRANCH} --limit 3 --json conclusion,displayTitle,status
   (если gh недоступен — верни «CI НЕ ПРОВЕРЕН: gh недоступен», НЕ подменяй локальным прогоном)

Ничего не добавляй от себя. Вывод команд — единственное, что от тебя нужно.`
}

// ---------------------------------------------------------------------------
// Прогон. RED останавливает ITEM и его зависимых — не всю волну (иначе у ревьюера
// появляется цена за честность и он занижает severity до ATTENTION).
// ---------------------------------------------------------------------------
const done = []
const blocked = []
const reviewFindings = []

// Ветку создаёт первый impl-агент (в Workflow-рантайме bash-хука нет) — это записано в его промпте.
log(`Волна на ветке ${BRANCH}; «локально зелено» не засчитывается — item закрывается только при зелёном CI (D45).`)

for (const item of ITEMS) {
  const blockedDep = item.deps.find((d) => blocked.some((b) => b.item === d))
  if (blockedDep) {
    blocked.push({ item: item.id, stage: 'skipped', reason: `зависит от заблокированного ${blockedDep}` })
    log(`⏭ ${item.id} пропущен: зависит от заблокированного ${blockedDep}`)
    continue
  }

  phase('Impl')
  const impl = await agent(implPrompt(item), {
    label: `impl:${item.id}`, phase: 'Impl', model: item.model, effort: item.effort, schema: IMPL_SCHEMA,
  })

  if (!impl || !impl.done) {
    const reason = impl ? (impl.escalation && impl.escalation !== 'no' ? `ESCALATION: ${impl.escalation}` : 'done=false') : 'агент не вернул результат'
    blocked.push({ item: item.id, stage: 'impl', reason })
    await agent(stopClosePrompt(item, 'impl', reason), { label: `stop-close:${item.id}`, phase: 'Close', model: 'sonnet', effort: 'low' })
    log(`⛔ ${item.id} заблокирован (impl): ${reason}. Волна продолжается по независимым items.`)
    continue
  }

  phase('Verify')
  const verify = await agent(verifyPrompt(item), {
    label: `verify:${item.id}`, phase: 'Verify', model: 'sonnet', effort: 'low',
  })

  phase('Review')
  const review = await agent(reviewPrompt(item, verify), {
    label: `review:${item.id}`, phase: 'Review', model: 'opus', effort: 'high', schema: REVIEW_SCHEMA,
  })

  if (!review || review.verdict === 'RED') {
    const reason = review ? `RED: ${review.summary}` : 'ревьюер не вернул результат'
    blocked.push({ item: item.id, stage: 'review', reason, findings: review ? review.findings : [] })
    await agent(stopClosePrompt(item, 'review', reason), { label: `stop-close:${item.id}`, phase: 'Close', model: 'sonnet', effort: 'low' })
    log(`⛔ ${item.id} заблокирован (review RED). Зависимые items будут пропущены.`)
    continue
  }

  reviewFindings.push(...review.findings.map((f) => ({ ...f, item: item.id })))

  phase('Close')
  await agent(closePrompt(item, impl, review, verify), { label: `close:${item.id}`, phase: 'Close', model: 'sonnet', effort: 'low' })

  done.push({ item: item.id, verdict: review.verdict, findings: review.findings.length })
  log(`✅ ${item.id} — ${review.verdict} (${review.findings.length} находок). Сдано: ${done.length}/${ITEMS.length}`)
}

// Бюджет ATTENTION: накопленные Minor — тоже долг, а не «зелёное»
const minors = reviewFindings.filter((f) => f.severity === 'Minor' || f.severity === 'Major')

return {
  wave: 'P1 remediation (волна 2)',
  completed: done,
  blocked,
  review_findings: reviewFindings,
  attention_debt: minors.length,
  next: blocked.length
    ? `Волна неполна: заблокировано ${blocked.length} items (${blocked.map((b) => b.item).join(', ')}). Разобрать причины (см. 🔴 Blocked в phases/P1.md), затем повторный прогон/ре-дизайн. Аудит фазы НЕ запускать.`
    : minors.length > 10
      ? `Все items сданы, но накоплен долг ревью: ${minors.length} Major/Minor находок. Разобрать их ДО аудита фазы (иначе аудит вернёт их же).`
      : 'Все items волны сданы → /task:plan-audit COREX-CORE P1 (Opus/xhigh, НОВАЯ сессия, ВСЛЕПУЮ — без A-списка). GREEN → P1.26 (CHANGELOG + теги + PR в main) → /task:plan-close COREX-CORE P1.',
}
