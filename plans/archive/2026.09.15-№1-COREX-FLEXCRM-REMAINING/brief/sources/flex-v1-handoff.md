# HANDOFF — 2026-07-27 — after P6.4

**Next:** waiting: борд `P2` плана COREX-CORE терминален (`~/projects/packages/corex/plans/COREX-CORE/plan.md`, строка `P2` без `🔴`) → close: /task:plan-close FLEX-2 P6.4

| Параметр | Значение |
|:--|:--|
| Model class | implementation |
| Effort | low |
| Capabilities | — |
| Context | same-session — item |
| Суть | P6.4 спроектирован и исполним, но закрывать НЕЧЕГО: делегат COREX-CORE Phase P2 в работе (`9/25 🟡`, P2.24 `🔴 Blocked`). Реальная работа сейчас — в ЧУЖОЙ репе (см. блок «Что запускать в репе делегата» ниже); в FLEX-2 возвращаемся только за закрытием майлстоуна |

```
# НЕ раньше: борд P2 в ~/projects/packages/corex/plans/COREX-CORE/plan.md терминален (🔴 не осталось)
/task:plan-close FLEX-2 P6.4

РЕЖИМ: закрытие майлстоуна-делегата — снять 7 пинов по §2 носителя, вписать в §1, отметить 🟢
ВХОД: 05-Projects/01-Products/FlexCRM/plans/FLEX-2/findings/P6.4-corex-core-p2-green.md (§1 пины, §2 команды, §3 известное состояние — НЕ пины) + phases/P6.md P6.4 (Validation: 4 public + 5 internal клозов)
СКОУП: 1) перепрогнать C1–C7 §2 в чужой репе; 2) записать значения ДОСЛОВНО в ячейки §1, frontmatter → status: pins-verified; 3) прогнать 4 public-Validation из корня vault; 4) статус P6.4 → 🟢, Status Board 3/26 → 4/26; 5) handoff + journal
НЕ ТРОГАТЬ: файлы чужой репы (только чтение, D14); пояснительную прозу §1–§2 носителя, называющую маркер PENDING (публичный пункт меряет ЯЧЕЙКИ таблицы, не вхождения слова); Validation остальных майлстоунов P6.5–P6.26 (Q103, отдельный вердикт владельца)
```

**Что запускать в репе делегата (не команда FLEX-2 — другая репа, свой план, свой routing):**
новая сессия, `cwd = ~/projects/packages/corex` → разбор блокера `/task:plan-design COREX-CORE P2.24`
(носитель красного пакетного `analyse`: `WorkspaceScope` generics + шаг `analyse` в CI-джоб
`tenancy-tests`, находки F1/F2 аудита, COREX-CORE D153) → далее phase-first по его handoff →
`/task:plan-close COREX-CORE P2` + `/task:plan-audit COREX-CORE P2` + semver-тег (COREX-CORE D17).
Модель/effort — по §3 Routing ЕГО плана, не по FLEX-2.

**Done:** P0 (2/2 🟢) · P1 (10/10 🟢) · P2 (9/9: 8 🟢, 1 🟠 P2.4) · P3 (5/5 🟢) · P4 (2/2 🟢) ·
P5 (3/3 🟢) · P7 (5/5 🟢) · P8 (6/6: 5 🟢, 1 🟠 P8.6) · P6 (3/26 🟡, P6.1–P6.3 🟢).

**Что сделано этим заходом (`plan-design FLEX-2 P6.4`):**
- Заведён носитель `findings/P6.4-corex-core-p2-green.md` — **7 пинов** делегата
  (`delegate_head`/`delegate_tags`/`delegate_board`/`poc_p21_verdict`/`oq5_resolution`/
  `composer_test`/`composer_analyse`) + команды C1–C7 + §3 «известное состояние, НЕ пины».
  Два пина сверх набора P6.3 — потому что ТЗ P6.4 само называет PoC-гейт P2.1 и OQ-5
  предусловиями, а «GREEN фазы» их не выражает. **Все ячейки — `PENDING`** сознательно:
  дерево делегата движется, снятое сегодня число к закрытию описывало бы другое дерево.
- Validation P6.4 разложена клозами через `·`: **4 public** (7 пинов присутствуют · ни одной
  ячейки `PENDING` · `DB_SCHEMA` держит префикс `FROZEN v1.` · доска фазы согласована) +
  **5 internal**-provenance. Все четыре public-грепа прогнаны из корня vault — работают
  (7 / 7 / 1 / 0 на текущем дереве).
- Компилятор `task_contract` на P6.4: было `pre_final: block:ACCEPTANCE_NOT_PUBLIC` →
  стало **`pre_mutation: allow · pre_final: allow`** (`actual_route=implementation/low`).
- ТЗ приведено к факту делегата: **25 items** фазы P2 (не 13, как называло ТЗ от 2026-07-13);
  PoC-гейт P2.1 = `🟢 GO` (COREX-CORE D76, закрыт 2026-07-18) — уже не блокер; OQ-5 =
  `Resolved → D75`; `DB_SCHEMA` = `FROZEN v1.2` (не v1.0). Добавлен критерий: `🟠` у items
  делегата закрытию не мешает, `🔴` — мешает.
- **§3 Routing +колонка `Exec`** по всей таблице (Q101 → Resolved, D36): делегатам
  `P6.3–P6.26` — явный `manual` против деривации §9 (`implementation` ∧ `low` → `plan-exec`),
  запрет D14 стал машинным, а не прозаическим. Парс route-колонки компилятором перепроверен.
- D35/D36 в `plan.md` §5; version 0.2.7 → 0.2.8; Q103 заведён.

**Remaining:** P6.4–P6.26 (23 майлстоуна-делегата). `/task:plan-exec FLEX-2 P6.x` для делегатов
ЗАПРЕЩЁН (D14, теперь и машинно — `Exec = manual` в §3); закрытие только `plan-close`.

**Sources of truth:** `FLEX-2/plan.md` (D1–D36, Routing §3 с колонкой `Exec`, Status Board §4) ·
`FLEX-2/phases/P6.md` (P6.3 закрыт, P6.4 спроектирован) ·
`FLEX-2/findings/P6.4-corex-core-p2-green.md` (носитель пинов, все `PENDING`) ·
`FLEX-2/findings/P6.3-corex-core-p1-green.md` (закрытый прецедент формы) ·
`FLEX-2/open-questions.md` (Q1–Q103) ·
`~/projects/packages/corex/plans/COREX-CORE/**` (делегат, только чтение).

**Open risks:**
- **Делегат стоит на `🔴 Blocked` (COREX-CORE P2.24)** — пока блокер не разобран в его репе,
  P6.4 не закрываем ни при каких обстоятельствах (Implementation Rules п.5).
- **Q103 — fail-open формы на P6.5–P6.26:** компилятор даёт им `pre_final: allow` только
  потому, что их Validation не называет `~/`-путь; по существу это то же утверждение о чужой
  репе, что блокировало P6.3/P6.4. Нужен вердикт владельца: прогнать паттерн D34/D35 по всем
  оставшимся одним заходом `/task:plan-design FLEX-2 P6` / just-in-time / не трогать.
  Не блокер — ближайший из них зависит от P6.4.
- **Q98 (unresolved):** делегат правит замороженный `DB_SCHEMA` (v1.0 → v1.2) через свой
  approval-gate, FLEX-2 эти правки не регистрирует. Публичный пин P6.4 сознательно проверяет
  ПРЕФИКС `FROZEN v1.`, поэтому подмену минорной версии он не поймает — цена названа в D35.
- **Q102 — шум линтера:** делегат-майлстоун по конструкции читает чужую репу, `plan_lint`
  метит каждый `~/`-путь как `PORTABILITY_EXTERNAL_INPUT` — цена честности, не регресс.
- **M6/Q88 — v1-обязательство без владельца:** `/api/v1` требует `corex/api`, майлстоуна нет;
  Search/DB-Ops — та же дыра. Решение владельца, маршрут `/task:plan-design FLEX-2 P6`.
  Блокирует только REST-часть P6.22.
- 15 items фазы P2 делегата уже несут `🟠 Done with deviations` — качество отклонений FLEX-2
  не аудировал (предмет `plan-audit COREX-CORE P2` в его репе).
- Аддитивные уточнения FROZEN (Q86 `vrt_`, Q91 `tsk_`, Q92 `root_`/`cal_`, Q99 `att_`) —
  approval-gate владельца.
- Трек P6 (унаследованные): stancl/tenancy v4 пин `dev-master#<sha>` (OQ-5 разрешён вариантом
  «в» — PoC на 8.4-окружении, scoped-bump `packages/tenancy` остаётся дверью реализации);
  `corex/ai` после модулей волны; control-plane P6.25; IdP P6.26 — делегат плана
  `2026.07.17-FLEX` (D28).
- FLEX-2 числится `Paused By: —`, но ACTIVE указывает на другой план — работа ведётся прямым
  поручением владельца.
- В рабочем дереве vault — крупный посторонний дифф (~236 файлов: Flex/FLEXID-переезд,
  AzGuard-доки, Agelto и др.), к этому заходу отношения не имеет.

**Workarounds/Deferred/Open questions:**
- workarounds: `validate.sh` в vault-репо нет — шаг пропускается на всех items этого плана;
  остальное — как в предыдущем handoff (P0 inline; P2.7–P2.9 по D12; Q74/Q75; P7 вне phase-first
  D13; P7.1/P6.2 под Sonnet-пином; P8.6 под sonnet/medium).
- deferred: мобильный клиент v1.1+ (B-37 §6.4); Inertia-SSR v1.1+ cloud-only; partner-API/
  OAuth 2.1+PKCE v1.1+; sandbox-периметр v2; расщепление corex/tasks↔flex/tasks (D29, Q93);
  mail/HR/whiteboard/merge — волна 3 (D7); FLEXCMS/FLEXSTORE — stubs (D4).
- open_questions: Q1–Q89 — бэклог на делегатах; Q90/Q93/Q97/Q100 исполнены P8.6;
  Q86/Q91/Q92/Q99 — approval-gate FROZEN (владелец); Q88 — решение владельца (M6);
  Q94/Q96 — scaffold; Q98 — процессный (unresolved); Q101 → Resolved (D36); Q102 — Exploration;
  **Q103 — Decision pending (владелец), заведён этим заходом**.
