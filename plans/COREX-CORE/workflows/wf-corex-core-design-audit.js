export const meta = {
  name: 'corex-core-design-audit',
  description: 'RAG-верификация внешних фактов + адверсариальный аудит ТЗ P1/P2 + pre-mortem P2 плана COREX-CORE (только дизайн, кода нет)',
  whenToUse: 'Финализация проектирования COREX-CORE перед снятием гейта «Запускай P6»',
  phases: [
    { title: 'RAG', detail: 'верификация внешних несущих фактов (perplexity/context7), 8 тем' },
    { title: 'Find', detail: 'finder-аудит ТЗ P1/P2 против блупринтов/кода/схемы, 6 измерений' },
    { title: 'Premortem', detail: '3 независимых pre-mortem P2 (tenancy/auth = high-stakes)' },
    { title: 'Verify', detail: 'адверсариальная верификация находок аудита' },
  ],
}

// ---------------------------------------------------------------------------
// Общий контекст, вшиваемый в каждый промпт
// ---------------------------------------------------------------------------
const CTX = `
КОНТЕКСТ (факты репо, проверены 2026-07-12):
- Репо: /home/vostrikov/projects/packages/corex (Laravel-пакеты монорепо: packages/{core,auth,crm,tasks}).
- composer.lock: laravel/framework v13.15.0; php ^8.3; symfony/uid v8.1.0; ramsey/uuid 4.9.2;
  phpstan/phpstan 2.2.2 (level 6, larastan/larastan 3.10.0); pestphp/pest 4.7.3; orchestra/testbench 11.1.0.
- composer scripts: test=pest, lint=pint (fix), lint:check=pint --test, analyse=phpstan (--memory-limit=1G).
- Мастер-план: /home/vostrikov/projects/packages/corex/plans/COREX-CORE/
  (plan.md — Meta/Context/Rules/Routing/D-лог D1–D17; phases/P1.md — 13 items B-10; phases/P2.md — 13 items B-11;
   open-questions.md OQ-1..4; findings/P1-premortem.md).
- Спека (vault, только чтение): /home/vostrikov/Vaults/Brain/plans/flex/30_blueprints/B-10_core-module-loader.md;
  B-11_auth-tenancy.md; /home/vostrikov/Vaults/Brain/plans/flex/60_contracts/DB_SCHEMA.md (Status: DRAFT, §Конфликты C1–C6);
  /home/vostrikov/Vaults/Brain/plans/flex/20_research/R-11_stancl-readiness.md; R-15_cell-infra.md;
  /home/vostrikov/Vaults/Brain/plans/flex/50_execution/00_EXECUTION_ROADMAP.md.
- Сегодня 2026-07-12. Код писать ЗАПРЕЩЕНО (гейт владельца) — это дизайн-аудит плана.
`;

const RAG_PREAMBLE = `Ты RAG-верификатор внешних фактов для мастер-плана COREX-CORE.
${CTX}
ИНСТРУМЕНТЫ: сначала вызови ToolSearch("select:mcp__perplexity-web__search,mcp__perplexity-web__search_advanced")
и/или ToolSearch("select:mcp__context7__resolve-library-id,mcp__context7__query-docs"), затем ищи.
Для библиотечных API предпочитай context7; для состояния проектов/релизов/issues — perplexity.
НЕ отвечай по памяти: у каждого вердикта — источник (URL или context7-библиотека) и дата.
Если поиск недоступен/пуст — verdict='unclear', так и скажи. Формулируй impact_on_plan конкретно:
какой item/D# плана затронут и что в нём поменять.`;

const RAG_SCHEMA = {
  type: 'object',
  properties: {
    topic: { type: 'string' },
    verdicts: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          claim: { type: 'string', description: 'проверяемое утверждение плана' },
          verdict: { type: 'string', enum: ['confirmed', 'refuted', 'needs-attention', 'unclear'] },
          detail: { type: 'string', description: 'что нашёл: версии, цитаты, нюансы' },
          source: { type: 'string', description: 'URL или context7 id + дата' },
          impact_on_plan: { type: 'string', description: 'какой item/D# затронут и что менять; "нет" если ничего' },
        },
        required: ['claim', 'verdict', 'detail', 'source', 'impact_on_plan'],
      },
    },
    summary: { type: 'string' },
  },
  required: ['topic', 'verdicts', 'summary'],
};

const RAG_TOPICS = [
  {
    key: 'stancl-v4',
    prompt: `Тема: состояние stancl/tenancy v4 и archtechx/jobpipeline на июль 2026.
Проверь утверждения плана (D8/D16, items P2.1+): (1) stancl/tenancy v4 НЕ зарелижен (0 тегов v4), пин dev-master#<sha> as 4.0.0 всё ещё необходим — или уже есть стабильный тег? (2) jobpipeline 2.0.0-rc7 — актуальный ли пин, есть ли стабильный 2.0.0? (3) Octane не поддержан stancl v4 (issue #1405) — статус? (4) broadcasting через BroadcastChannelPrefix, config-bootstrapper сломан (issue #1448) — статус? (5) совместимость stancl v4 с Laravel 13. Ищи: github stancl/tenancy releases/tags/issues, tenancyforlaravel.com/docs v4.`,
  },
  {
    key: 'uuid7-laravel13',
    prompt: `Тема: UUIDv7 в PHP/Laravel 13 (item P1.1).
Проверь: (1) symfony/uid 8.1 — API UuidV7 (класс, генерация, монотонность); (2) ramsey/uuid 4.9 — Uuid::uuid7(); (3) Laravel 13: что дефолтит трейт HasUuids (v4 или ordered v7?), есть ли HasVersion7Uuids, что рекомендует фреймворк; (4) колонка $table->uuid() в PG — тип uuid; (5) есть ли в Laravel 13 нативный Str::uuid7()/orderedUuid и на чём он основан. Вывод: какой генератор канонично использовать в P1.1 при обеих библиотеках в lock. context7: laravel/framework (v13), symfony/uid, ramsey/uuid.`,
  },
  {
    key: 'phpstan-custom-rules',
    prompt: `Тема: PHPStan 2.2 custom rules API + larastan 3.10 (items P1.13, P2.13).
Проверь: (1) интерфейс PHPStan\\Rules\\Rule в 2.x — сигнатуры getNodeType()/processNode(), RuleErrorBuilder (identifier обязателен в 2.x?); (2) PHPStan\\Testing\\RuleTestCase — как тестировать правила; (3) регистрация через extension.neon (services + rules) и подключение в phpstan.neon; (4) практика правил на raw-SQL строковые литералы (Node\\Scalar\\String_, анализ аргументов DB::statement/unprepared) — есть ли готовые пакеты-аналоги (напр. правила запрета DB::raw); (5) совместимость larastan 3.10 с phpstan 2.2 и level-переопределение per-path. context7: phpstan/phpstan v2, larastan/larastan v3.`,
  },
  {
    key: 'pg16-pgbouncer',
    prompt: `Тема: PostgreSQL 16 фичи + PgBouncer transaction pooling (items P1.6–P1.11, P1.13; R-15).
Проверь: (1) полный список того, что ломается в PgBouncer transaction mode: SET (не LOCAL), PREPARE/named prepared statements, LISTEN/NOTIFY, session advisory locks, temp tables, cursors WITH HOLD — подтверди по официальной документации PgBouncer «features»; (2) pg_advisory_xact_lock(hashtext(...)) — семантика hashtext (int4!) и риск коллизий, сигнатуры advisory-функций (int8 vs 2×int4); (3) UNIQUE NULLS NOT DISTINCT — синтаксис PG15+, поддержка в Laravel 13 schema builder (или нужен raw); (4) ltree: ограничения метки (символы/длина), GIST-индекс, оператор <@, subpath/nlevel; (5) BRIN по timestamptz — практика. Официальные доки postgresql.org/pgbouncer.org приоритетны.`,
  },
  {
    key: 'laravel13-breaking',
    prompt: `Тема: Laravel 13 — изменения, значимые для плана COREX-CORE (все items P1).
План писался с формулировками про «Laravel 12»/«PHP 8.4–8.5», а в lock — laravel/framework v13.15.0, php ^8.3. Проверь по upgrade guide 12→13 и релиз-нотам: (1) минимальный PHP для Laravel 13; (2) queued event listeners / ShouldQueue — изменения; (3) Cache tags (Redis) — изменения/деприкейты; (4) Encrypter/casts encrypted — изменения; (5) morphMap/enforceMorphMap — изменения; (6) package discovery (composer extra.laravel), формат vendor/composer/installed.json — стабильность чтения extra секции пакета; (7) bootstrap/cache каталог и атомарная запись кешей фреймворком (var_export + rename?) — как делает сам Laravel; (8) illuminate/redis pub/sub API. context7 laravel/framework v13 + laravel.com/docs/13.x/upgrade.`,
  },
  {
    key: 'test-lane',
    prompt: `Тема: тест-лейн Pest 4 / testbench 11 / docker PG16 (item P1.6, D12).
Проверь: (1) Pest 4 группы тестов (->group()) и запуск --group; (2) orchestra/testbench 11 — совместимость с Laravel 13, паттерн подключения нескольких path-пакетов монорепо, переключение database connection per-test; (3) docker-compose healthcheck для postgres:16 (pg_isready) — канонический сниппет; (4) phpunit.xml с двумя коннекшенами (sqlite :memory: дефолт + pgsql) — практика Laravel-пакетов; (5) параллельный запуск pest и изоляция PG-БД. context7: pestphp/pest v4, orchestra/testbench.`,
  },
  {
    key: 'p2-auth-stack',
    prompt: `Тема: auth-стек P2 — Fortify/OIDC на Laravel 13 (items P2.8–P2.11).
Проверь: (1) laravel/fortify — актуальная мажорная версия и совместимость с Laravel 13; (2) OIDC-клиент для Laravel 2026: laravel/socialite (+socialiteproviders), league/oauth2-client — что живо и канонично для подключения ВНЕШНЕГО IdP по OIDC (authorization code + PKCE); (3) jumbojett/openid-connect-php — жив ли; (4) паттерн boxed-Fortify + cloud-OIDC-client в одном пакете (конфиг-свитч) — есть ли известные грабли; (5) laravel/sanctum vs passport для API-токенов в мультитенантном SaaS — что рекомендуют в 2026. perplexity + context7.`,
  },
  {
    key: 'pg-subtle',
    prompt: `Тема: тонкие PG-факты для миграций P1 (items P1.6/P1.8/P1.10/P1.11).
Проверь: (1) ltree метка: допустимые символы (A-Za-z0-9_) и МАКС ДЛИНА метки/пути (256? 65535?) — uuid hex без дефисов = 32 символа, влезает?; (2) CREATE EXTENSION IF NOT EXISTS ltree — права (superuser? trusted extension в PG13+?), поведение в managed PG (RDS/Cloud SQL); (3) advisory lock через PgBouncer transaction mode: xact-вариант безопасен, session-вариант нет — подтверди; (4) BRIN индекс: pages_per_range дефолт, когда BRIN выигрывает у btree по timestamptz append-only; (5) jsonb размер значения — практический лимит и TOAST; (6) партиционирование append-only лога — когда пора (объёмы). Официальная документация PG16 приоритетна.`,
  },
];

// ---------------------------------------------------------------------------
// Finder-аудит: 6 измерений
// ---------------------------------------------------------------------------
const FINDINGS_SCHEMA = {
  type: 'object',
  properties: {
    dimension: { type: 'string' },
    findings: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          title: { type: 'string' },
          severity: { type: 'string', enum: ['blocker', 'major', 'minor', 'note'] },
          location: { type: 'string', description: 'файл плана + item (напр. phases/P1.md P1.4) или D#' },
          evidence: { type: 'string', description: 'конкретика: цитата плана vs цитата спеки/кода, пути, строки' },
          recommendation: { type: 'string', description: 'что именно поменять в плане' },
        },
        required: ['title', 'severity', 'location', 'evidence', 'recommendation'],
      },
    },
    coverage_notes: { type: 'string', description: 'что проверил и что НЕ смог проверить' },
  },
  required: ['dimension', 'findings', 'coverage_notes'],
};

const FINDER_PREAMBLE = `Ты адверсариальный аудитор мастер-плана COREX-CORE (дизайн-фаза, кода не пишем).
${CTX}
Твоя задача — найти РЕАЛЬНЫЕ дефекты ТЗ: расхождения план↔спека↔код, дыры покрытия, противоречия
между items, неисполнимые Validation. НЕ засчитывай как дефект: сознательные решения D-лога
(D1–D17 НЕ пересматриваем), вынесенные open-questions (OQ-1..4), скоуп-отсечения (Scope Excluded).
Каждая находка — с точной цитатой/строкой как evidence и конкретной правкой плана как recommendation.
Пустой список находок легален, если измерение чистое. Финальный текст = JSON по схеме.`;

const FINDERS = [
  {
    key: 'p1-vs-b10',
    prompt: `Измерение: полнота P1 против блупринта B-10.
Прочитай ЦЕЛИКОМ /home/vostrikov/Vaults/Brain/plans/flex/30_blueprints/B-10_core-module-loader.md
и plans/COREX-CORE/phases/P1.md. Сверь: (1) все контракты §3.1–§3.5 B-10 покрыты items (кроме отложенного
corex/components, D6)? (2) все acceptance criteria B-10 (AC-1..AC-конец) замаплены на Validation какого-то
item? Перечисли AC без владельца. (3) все инварианты §7 (perf-бюджеты, safety, transaction-pooling)
отражены в Implementation Rules/Code Guidance? (4) секции B-10, которые план вообще не упоминает —
законно ли (Scope Excluded/D-лог) или дыра.`,
  },
  {
    key: 'p1-vs-code',
    prompt: `Измерение: P1 против реального кода репо corex.
Прочитай plans/COREX-CORE/phases/P1.md, затем проверь по коду /home/vostrikov/projects/packages/corex:
(1) каждый путь из полей Files/Inputs существующих файлов — существует? (ls/glob; файлы «(new)» не проверяем);
(2) упомянутые классы/методы (Config::entityRegistryClass, HasConfiguredId, IdColumns, ServiceProviderModuleRegistrar,
DatabaseSettingsRepository, DatabaseAuditLogger, HasAuditLog, CoreEvent, FeatureResolver, NullTenantService,
ContainerTenantService, TenantService/TenantScope/BelongsToTenant) — реально есть в коде и в тех файлах?
(3) якорные номера строк (напр. HasConfiguredId.php:19) — актуальны ±5 строк?
(4) АНАХРОНИЗМЫ версий: план говорит «PHP 8.4–8.5», «level 8» (P1.13 Intent) — composer require php ^8.3,
phpstan.neon level 6, laravel 13.15; найди все такие расхождения план↔тулчейн по phases/P1.md и plan.md;
(5) названия тестов (ConfigurableIdTest, UlidGenerationTest, SettingsTest, AuditLogTest) — существуют?
(6) config/corex.php — какие ключи есть реально vs упоминаемые планом (tables.settings, corex.tenancy.account, corex.pubsub).`,
  },
  {
    key: 'plan-vs-dbschema',
    prompt: `Измерение: P1+P2 migration-items против DB_SCHEMA.
Прочитай /home/vostrikov/Vaults/Brain/plans/flex/60_contracts/DB_SCHEMA.md ЦЕЛИКОМ, затем
plans/COREX-CORE/phases/P1.md (items P1.6–P1.11) и phases/P2.md (migration-items P2.2/P2.6/P2.7/P2.8).
Сверь: (1) каждая таблица, которую создают items, есть в DB_SCHEMA и наоборот — таблицы sys_*/mod_* из
DB_SCHEMA, которые НИ ОДИН item не создаёт (дыра или законный перенос?); (2) конфликты C1–C6 DB_SCHEMA —
каждый отражён в плане (item/OQ/D#)? какие не отражены? (3) поколоночные расхождения, которые видно уже
сейчас (имена/типы/CHECK-констрейнты, упомянутые в Implementation Rules items) — точечно; (4) статус DRAFT:
какие items имеют FROZEN-предусловие, а какие migration-items его НЕ упоминают, хотя должны.`,
  },
  {
    key: 'p2-vs-b11',
    prompt: `Измерение: полнота и исполнимость P2 против B-11 + R-11.
Прочитай /home/vostrikov/Vaults/Brain/plans/flex/30_blueprints/B-11_auth-tenancy.md ЦЕЛИКОМ,
/home/vostrikov/Vaults/Brain/plans/flex/20_research/R-11_stancl-readiness.md ЦЕЛИКОМ и
plans/COREX-CORE/phases/P2.md ЦЕЛИКОМ. Сверь: (1) все контракты/потоки B-11 (§2–§7, Приложение A) покрыты
items? перечисли непокрытое и где это законно (D7 IdP, D14 биллинг-таблицы); (2) PoC-гейт P2.1 — все 7
сценариев Приложения A из B-11 присутствуют в его ТЗ? (3) выводы R-11 (§2.1–§2.6, известные issues) —
каждый учтён в каком-то item/D#? (4) DoR-качество каждого item P2: 16 полей заполнены, Required Reads
конечны с §-ссылками, Code Guidance не пустой, Validation — конкретные команды; перечисли items со слабыми
полями; (5) порядок P2 (handoff: P2.7 users до P2.6/P2.8) — согласован с Inputs/Deliverables items?`,
  },
  {
    key: 'cross-item',
    prompt: `Измерение: межитемная связность P1+P2.
Прочитай plans/COREX-CORE/phases/P1.md и phases/P2.md ЦЕЛИКОМ, plan.md (D-лог, Routing).
Найди: (1) противоречия между items — один item обещает то, что другой делает иначе (напр. кто биндит
контракты в ServiceProvider, кто владеет CompileModulesCommand на каждом шаге P1.3→P1.4→P1.5, interim-база
SettingChanged P1.7↔P1.9, DatabaseModuleStateReader P1.5↔P1.6); (2) Inputs, ссылающиеся на Deliverables
БОЛЕЕ ПОЗДНЕГО item (нарушение порядка); (3) заявленный порядок исполнения (Phase Context P1: P1.1→P1.12→
P1.2..) — не противоречит ли зависимостям полей; (4) дубли работы между items; (5) Files-коллизии: два item
правят один файл с несовместимыми ожиданиями; (6) P1↔P2 швы: контракты P1.12 достаточны для потребителей P2?
tenancy-контракты D13 (CoreX\\Tenancy\\Contracts) — какой item их создаёт, не потерялись ли между фазами.`,
  },
  {
    key: 'validation-exec',
    prompt: `Измерение: исполнимость Validation-команд и тестовой инфраструктуры.
Прочитай plans/COREX-CORE/phases/P1.md, phases/P2.md (поля Validation), plan.md §2, и реальные
composer.json (корень), phpunit.xml, phpstan.neon, pint.json репо /home/vostrikov/projects/packages/corex.
Проверь: (1) каждая Validation-команда существует как composer script/бинарь (composer lint vs lint:check —
league задачи писала «composer lint && composer analyse && composer test»; что канонично?);
(2) "pest --group=pg" — механика групп в конфиге есть/понадобится; (3) phpunit.xml сейчас — какие коннекшены
и env, что реально придётся менять в P1.6 (список правок полон?); (4) phpstan paths не включают packages/modules
— какие items это чинят и не забыт ли auth; (5) docker-compose.yml в репо нет — P1.6 его создаёт; нет ли других
скрытых пререквизитов (redis для P1.11 групп, порты); (6) tests/ структура — где живут Feature/Unit сейчас,
согласуется ли с путями тестов в items.`,
  },
];

// ---------------------------------------------------------------------------
// Pre-mortem P2 (high-stakes: tenant isolation)
// ---------------------------------------------------------------------------
const PREMORTEM_SCHEMA = {
  type: 'object',
  properties: {
    lens: { type: 'string' },
    causes: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          title: { type: 'string' },
          scenario: { type: 'string', description: 'как конкретно провалилось через 6–12 мес' },
          early_signal: { type: 'string', description: 'по какому сигналу заметить раньше' },
          mitigation: { type: 'string', description: 'что добавить/усилить в плане СЕЙЧАС' },
          owner_item: { type: 'string', description: 'какой item/D# должен нести митигацию' },
        },
        required: ['title', 'scenario', 'early_signal', 'mitigation', 'owner_item'],
      },
    },
  },
  required: ['lens', 'causes'],
};

const PREMORTEM_LENSES = [
  {
    key: 'tenant-isolation',
    prompt: `Ленза: изоляция тенантов и данных.
Прочитай plans/COREX-CORE/phases/P2.md ЦЕЛИКОМ и B-11 §7 (safety). Прошло 9 месяцев, P2 в проде,
случился ИНЦИДЕНТ ИЗОЛЯЦИИ (данные аккаунта А видны аккаунту Б, или миграция убила тенант-БД,
или central/tenant коннекшены перепутались). Назови 4–6 НЕЗАВИСИМЫХ причин, каждая — конкретный
механизм провала, привязанный к реальным items P2 (кеш без тег-изоляции, queue-джоба без tenant-контекста,
golden-шаблон дрейфанул, root-миграция ушла в тенант, workspace-scope обойдён raw-запросом...).`,
  },
  {
    key: 'stancl-dependency',
    prompt: `Ленза: зависимость от stancl/tenancy v4 (незарелижен, dev-master пин).
Прочитай plans/COREX-CORE/phases/P2.md (P2.1 PoC-гейт и все stancl-items) и R-11. Прошло 9 месяцев,
план провалился ИЗ-ЗА STANCL: назови 4–6 независимых причин (пин протух/breaking на master, v4
зарелизился несовместимо, PoC-сценарии прошли, а прод-кейс нет, fork-fallback F1/F2 оказался нежизнеспособен,
jobpipeline rc7 сломал очереди, Octane-запрет упёрся в перфоманс...). Для каждой — ранний сигнал и
что усилить в P2.1/D8/D16 сейчас.`,
  },
  {
    key: 'auth-rbac',
    prompt: `Ленза: auth/RBAC/identity.
Прочитай plans/COREX-CORE/phases/P2.md (items P2.7–P2.11) и B-11 §4–§6. Прошло 9 месяцев, провал в
auth-слое: назови 4–6 независимых причин (RoleMatrixCompiler разъехался с AzGuard-версией, ScopeResolver
компилирует неверные ltree-предикаты — privilege escalation, денормализация users разъехалась с identity
(D4 гипотеза), OIDC-шов не совместим с boxed-Fortify сессиями, impersonation-грант без аудита,
IdentitySyncer теряет обновления...). Для каждой — ранний сигнал и усиление плана.`,
  },
];

// ---------------------------------------------------------------------------
// Verify
// ---------------------------------------------------------------------------
const VERDICT_SCHEMA = {
  type: 'object',
  properties: {
    finding_title: { type: 'string' },
    real: { type: 'boolean' },
    confidence: { type: 'string', enum: ['high', 'medium', 'low'] },
    reasoning: { type: 'string', description: 'что перепроверил в файлах и почему вердикт таков' },
    corrected_recommendation: { type: 'string', description: 'уточнённая правка плана (или пусто, если не real)' },
  },
  required: ['finding_title', 'real', 'confidence', 'reasoning'],
};

// ---------------------------------------------------------------------------
// Оркестрация: RAG ∥ Find ∥ Premortem; Verify стартует по готовности Find
// ---------------------------------------------------------------------------
log('Старт: 8 RAG-тем ∥ 6 finder-измерений ∥ 3 pre-mortem-лензы');

const ragPromise = parallel(RAG_TOPICS.map(t => () =>
  agent(`${RAG_PREAMBLE}\n\n${t.prompt}`, {
    label: `rag:${t.key}`, phase: 'RAG', schema: RAG_SCHEMA, model: 'sonnet', effort: 'medium',
  })
));

const premortemPromise = parallel(PREMORTEM_LENSES.map(p => () =>
  agent(`Ты ведёшь pre-mortem по протоколу Klein (prospective hindsight) для high-stakes фазы P2
(tenancy/auth — изоляция тенантов) плана COREX-CORE.\n${CTX}\n${p.prompt}\n
Причины должны быть КОНКРЕТНЫМИ механизмами (не «плохое тестирование»), митигации — правками плана
(усиление item-полей, новый AC-тест, новый D#), НЕ кодом. Финальный текст = JSON по схеме.`, {
    label: `premortem:${p.key}`, phase: 'Premortem', schema: PREMORTEM_SCHEMA, model: 'opus', effort: 'high',
  })
));

const findResults = await parallel(FINDERS.map(f => () =>
  agent(`${FINDER_PREAMBLE}\n\n${f.prompt}`, {
    label: `find:${f.key}`, phase: 'Find', schema: FINDINGS_SCHEMA, model: 'opus', effort: 'high',
  })
));

// Дедуп по location+title и отсев note (notes идут в отчёт без верификации)
const allFindings = findResults.filter(Boolean).flatMap(r =>
  (r.findings || []).map(f => ({ ...f, dimension: r.dimension }))
);
const seen = new Set();
const deduped = allFindings.filter(f => {
  const key = (f.location + '|' + f.title).toLowerCase().replace(/\s+/g, ' ').slice(0, 100);
  if (seen.has(key)) return false;
  seen.add(key);
  return true;
});
const toVerify = deduped.filter(f => f.severity !== 'note');
const notes = deduped.filter(f => f.severity === 'note');
log(`Find: ${allFindings.length} сырых → ${deduped.length} после дедупа; верифицируем ${toVerify.length} (notes: ${notes.length})`);

const verified = await parallel(toVerify.map(f => () =>
  agent(`Ты адверсариальный верификатор находки аудита плана COREX-CORE. Твоя задача — ОПРОВЕРГНУТЬ её.
${CTX}
НАХОДКА [${f.severity}] из измерения «${f.dimension}»:
- Титул: ${f.title}
- Локация: ${f.location}
- Evidence: ${f.evidence}
- Рекомендация: ${f.recommendation}

Перепроверь по ПЕРВОИСТОЧНИКАМ (сами файлы плана, блупринты vault, код репо — читай конкретные места).
Находка НЕ real, если: (а) evidence не подтверждается файлами; (б) это сознательное решение D-лога D1–D17
или вынесенный OQ-1..4; (в) это законный Scope Excluded; (г) рекомендация пересматривает утверждённые
решения. При сомнении — real=false (default to refuted). Финальный текст = JSON по схеме.`, {
    label: `verify:${(f.location || '').slice(0, 30)}`, phase: 'Verify', schema: VERDICT_SCHEMA, model: 'opus', effort: 'high',
  }).then(v => ({ finding: f, verdict: v }))
));

const rag = (await ragPromise).filter(Boolean);
const premortem = (await premortemPromise).filter(Boolean);

const confirmed = verified.filter(Boolean).filter(v => v.verdict && v.verdict.real);
const rejected = verified.filter(Boolean).filter(v => v.verdict && !v.verdict.real);
log(`Verify: ${confirmed.length} подтверждено, ${rejected.length} опровергнуто. RAG: ${rag.length}/8 тем. Premortem: ${premortem.length}/3 лензы.`);

return {
  rag,
  confirmed_findings: confirmed,
  rejected_findings: rejected.map(v => ({ title: v.finding.title, location: v.finding.location, why_rejected: v.verdict.reasoning })),
  notes,
  premortem,
  coverage: findResults.filter(Boolean).map(r => ({ dimension: r.dimension, coverage: r.coverage_notes })),
};
