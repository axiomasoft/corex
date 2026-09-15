# Whole-plan design finish — CoreX/FlexCRM remaining

**Design finish:** verified

Дата: 2026-09-15. Автор: текущая owner-authorized design-review сессия по
brief/02-astra-whole-plan-review.md, выбранный владельцем маршрут Astra/high.
Это запись выполненного review, не provider/model attestation или phase audit consent.
Repository HEAD при starting review и pre-commit verification: `ec78b3ae6c4e079068dbe67d38f9c1251b10d920`.

## Основание результата

Полностью прочитаны и содержательно сопоставлены 11 phase contexts, 78 item specs,
все stable phase designs/RAG, решения D1–D16, owner briefs, 52 R и 365 исходных parent
записей, A01–A09, gates и owning checkers. Перечень просмотренных contracts с initial/
current hashes — artifacts/astra-whole-plan-review/reviewed-inputs.json; inventories
исторических raw деревьев означают hash qualification, не прочтение каждого архива.

10 подтверждённых findings исправлены в owning specs/Files/Inputs/Validation/designs.
AWR-03 отклонён после повторной проверки как ошибка самого аудита. Исходные findings
сохранены отдельно от итоговых resolutions в findings/whole-plan-astra-review.md.
D17 фиксирует основания, альтернативы, producer/consumer связи и scope изменений.

Исправлены полнота seal, current guidance, producer placement и shared DTO adoption,
undefined token/CI path, forward digest FK, циклическая release identity, P11 verifier
и ложная проверка Expected != Actual, неполная extraction исходных AC и write exclusivity.
Integrated compatibility/pre-mortem с failure → owner → case находится в
research/05-integrated-design.md, текущий раздел D17. Неразрешённого существенного
внутреннего design choice после этого review не осталось; новые source mismatches
при фактической квалификации требуют owning repair и нового current finish.

## Исследования и пределы доказательств

AzGuard исследован по разрешённому local source/tests, его dirty worktree сохранён.
research/06-azguard-patterns.md различает authorization/headless/UI panels, описывает
consumer usage, DI/lifetimes, enum/configuration, drivers/cache/precedence/scaffolding,
применимость и ограничения. Source inspection не считается запущенным upstream suite.

research/07-open-source-patterns.md содержит 7 новых нейтральных Perplexity queries,
реальные ответы/hashes и сравнение 7 canonical OSS repositories на immutable commits.
01/02/04 неполные, 03 содержит ложное duplicate rejection: они не являются успешным
подтверждением пробелов. Дополнительные завершённые 05/06/07 и непосредственные
source/test/license/compatibility checks закрыли соответствующие gaps; текущая серия
не имела login/timeout/no-answer. Прежние фазовые retrieval failures сохранены отдельно.
Metadata response hashes проверены на decompressed bytes, tar.gz/test bodies — на
фактических stored bytes. Upstream tests прочитаны в указанном scope, не запускались.

Explicit trusted context, typed data, scoped lifecycle и diagnostics применяются к
существующим seams. Новый role/engine writer, девятый extender, обязательный UI,
duplicate overwrite как guard и cache как revocation authority отвергнуты.

## Фактически выполненные проверки

- P1 inputs и P2–P11 design: 11 проверок, exit0.
- Изолированные negative guards: 13 тестов, PASS; drift criterion/gate/research/raw/
  validator/new/deleted input отказывает; equal observations принимаются только с
  bound evidence и prepared verifier; отсутствие/отказ verifier не становится PASS.
- Named plan-lint: exit0, 0 errors / 0 warnings.
- Named plan-downstream: exit0; first-command structural dispatch against HEAD.
  Unknown future/uncommitted inputs не являются runtime acceptance; все required
  команды будущих items остаются обязательными при их исполнении.
- Generated state (78), status, decision views и runnable P1.1 bundle: current.
- Root/phase Routing: все 78 model/effort/Exec/Review/Batch совпадают.
- Все 78 execution items: Not started. Git diff whitespace check: exit0.
- 428 исторических plan inputs и 467 AzGuard files неизменны; исходный journal prefix
  сохранён, добавлено только design bookkeeping. Изменений вне этого плана нет.

Exact commands/cwd/results: artifacts/astra-whole-plan-review/checks/final-check-results.json.
Initial failed self-checks сохранены в iteration-1 artifacts: canonical handoff исправлен;
raw PHP evidence переименован в .php.txt без изменения bytes, чтобы Pint не считал его
кодом монорепозитория. Product PHP tests/PG/installed/hosted/live suites здесь не выполнялись.

## Remaining actual gates / one successor

P1.1 выполняет actual source reconciliation, P1.2/3 — atom/equivalent mapping. Далее
нужны именованные source/ABI, API/schema/dependency, private/runner/release, effective
DI/AP/FE/month/provider и independently accepting-consumer receipts по owning specs.
Auth Laravel12 declaration не считается Laravel13 proof. Unknown/no-data/незакрытое
окно не превращаются в PASS; исходные thresholds и external residual owners сохранены.
Design finish не предоставляет protected write grants и не закрывает original milestones.

**Next:** exec-items: task:plan-exec 2026.09.15-№1-COREX-FLEXCRM-REMAINING P1.1 P1.2 P1.3 P1.4

Маршрут D18: B-P1-QUALIFY, frontier/high = gpt-5.6-sol/high, plan-exec/light;
native first ready carrier P1.1, explicit requested scope P1.1–P1.4 с sequential
closure/bundle/admission в том же root. Full dependency-chain admission пока не готов;
cold-start-root с reason design-root-terminal. Точный native launch — handoff.md;
byte-current runnable input — bundles/P1.1.json. В authoring сессии successor не исполнялся.
Перед item mutation повторить scripts/task-check.py --check design.

## D18 — Последующее поручение о группировке

Владелец после D17 поручил оптимизировать execution grouping и commit/push всей
подготовленной работы. Это разрешение на Git-публикацию текущего плана; runtime,
external authority и product release gates не меняются. Исходный D17 finish сохранён
в checks/design-finish.before-D18.md как исторический checkpoint.

Routing root/11phases:78 items,35 recommended envelopes (24groups +11solo), прежних
units было57. Все индивидуальные route/Exec/review и item contracts сохранены.
Max-route группы применяется к launch; source intake и final seal присоединены там,
где переиспользуют тот же context. research/08-execution-batches.md содержит все группы,
отказы от лишнего объединения, P4/P5 interleave, P10 window и честное измерение известных
cold-read bytes:32 173 559 →20 657 887 (−35,8%). Это не гарантия35 fresh sessions.

Native batch-routing-check.py:6 readiness/refusal checks PASS, actual compiler35 units,
готовый P1.1 ALLOW, неготовая P1 chain BATCH_STALE. Semantic Next перечисляет requested
scope по текущему lint; copyable native prompt начинает только ready P1.1 и явно
поручает дальнейшие per-item admissions в той же сессии. Никаких fake future receipts.
Final phase/lint/downstream/views и13existing guard tests повторно PASS после D18.

## Design inputs

| Path | SHA256 |
|:--|:--|
| `artifacts/P1-design/context-measurement.json` | 09b2b21abe72be5c1f9dbd096321eb3596f236aff3325ba8a612714d3c1c1aa1 |
| `artifacts/P1-design/continuity-decision.json` | 8937935ed7858d6f850bdd19186a81ed364360765cb5e74751bf99f8d327eb75 |
| `artifacts/P1-design/contract-smoke.json` | 1bbadc4644a91f000fe1dfc2383e76d1f12878edf9a6db5462488305cf6a8b63 |
| `artifacts/P1-design/lifecycle-selection.json` | 6b2ea79121f2d7a40f87f05e2c7c6a488870545f9299aec6f0a4112103d5c7d6 |
| `artifacts/P1-design/next-carrier.json` | 3050c1c82e0f4e40de8a571c39a4ccd93fabae70198903b739d7bc631c39ec26 |
| `artifacts/P1-design/perplexity-query.txt` | 08e6db8a3b18bb0b7e2b767a2bbf34c3bc61f7c49d8eccd56f8cf0b6d3d7c22a |
| `artifacts/P1-design/perplexity-response.json` | 17ba8102a806f994e75d9b430a10291da8f7e8c0d2cab7353d9fdfd239f388e9 |
| `artifacts/P1-design/route-evidence.json` | ca5310c3a44da3da63f0a2991690d0c5e3d3662f80384a2f14334d93d1bcfadd |
| `artifacts/P1-design/validation.md` | 63f52c1db1cad531275d887e307245fb43a48b59ee525f333b541252295524d2 |
| `artifacts/P1-design/validator-smoke.json` | acb4426efbe98281c1e810cf7ebf484fbc71cd9dd09f4a5e11af1f0cce923d04 |
| `artifacts/P1-design/work-estimate.json` | e4dbf11cd6639ddb8ea41838df9e0eef43572c239c75cd423365edf4c045551d |
| `artifacts/P10-design/check-results-initial.json` | 3423e97e7bcc97db49eb37de9242db4e37976db1399810edb500af470b2186f3 |
| `artifacts/P10-design/check-results-intermediate.json` | 082d6639a2cc144290ca01bfe6e12b6d7d0bdc28d73c5ac73e19bc1f09a89d44 |
| `artifacts/P10-design/check-results.json` | b8f0fc936a36520e83345cb8e26eb86b44ac69b5b727687fb31d8e3631ebbea4 |
| `artifacts/P10-design/continuity-decision.json` | 55311a5e91fa12da03acb441e4c43f997e4bdcebe64377e2c0b3f2d447458922 |
| `artifacts/P10-design/contract-counts.json` | 2ac9bfb8d0ef0bef75d9db0471ce899968c92e0c27c300f7e0d20b1a745bec94 |
| `artifacts/P10-design/contract-smoke.json` | 13880b2152509a79de64b19f0b0d50806756b17e668ac0560f65b6bfcda99d4e |
| `artifacts/P10-design/design-check-results.json` | 81cd9ca9d5e8b553f0e76c051d0469660f8b8defd752c778f5626dd7356b3c40 |
| `artifacts/P10-design/handoff-receipt.json` | f1cd23004e1d66e95166e7953cf820530bc6e9b481396319208e1b63c02bef8a |
| `artifacts/P10-design/lifecycle-selection.json` | 9a8cbed0e8f9feeedc7861a5d76006770a81a7601e641dfd408aa91fd94af496 |
| `artifacts/P10-design/next-carrier.json` | 5d7275025d6389cfbca48dd15d5bfcc1ca7c579a1880d5304621c3f698c21da2 |
| `artifacts/P10-design/perplexity-initial-response.json` | 6d22e84676cf60ee420dcc4d2f1e7b1c0de1c3f964dc468cccf6575c560a6f15 |
| `artifacts/P10-design/perplexity-ops-failed.json` | ffa7f5611352ea13a79e7188e8447dcfd27cb41f80b74f1e47393d11606e1ac7 |
| `artifacts/P10-design/perplexity-ops-response.json` | b3139e3cf691abdaa046793b0920d1d5677ec5aac46e858e11288dc3593f76b2 |
| `artifacts/P10-design/perplexity-response.json` | 542c7dea71b0683d9a30336bc684d69ff7a009234417a734207e525da82cdbfb |
| `artifacts/P10-design/primary-10346fff24e322b2.html.gz` | 05a65b3f3043340c6b9cde2049152985b81f5e1170ff25309b3c8bb12c05a9f9 |
| `artifacts/P10-design/primary-10c2347db109ea1e.html.gz` | c8489160d0e1f85266ad0aa0692ea58d008553bc52116f81b9e94d694cc4dadf |
| `artifacts/P10-design/primary-15a0fd0f91bcc2c4.html.gz` | 550e58f814f4909cdb662e13a5fbb6699885037493c3af7a8db393907fae91cf |
| `artifacts/P10-design/primary-1a562210fe98eb6d.html.gz` | a5d20441d225e44db5ee7be4e1f41e200b21d4957829d991560f42388de6e572 |
| `artifacts/P10-design/primary-3d1f0dbea6f47149.html.gz` | 0833698241bece300c715bf12d9cdbe8c1597eccb50fb68df441dda57c4f0a47 |
| `artifacts/P10-design/primary-4e6d674bf887d330.html.gz` | 023351a6305d89d401feb03bb47bed64980ab36c27f63ea771051e1b4e73ebd8 |
| `artifacts/P10-design/primary-7b9a3e66dbb9cd31.html.gz` | c1d25d98ebdd07004a33717c9f7115365422f23ed2f674d3106ce1b341123038 |
| `artifacts/P10-design/primary-99db330be8cb828c.html.gz` | 730ddcb5b5dfbe987b5b07b8728f14de6f99a34ce1a4bc832b187ec866d11c88 |
| `artifacts/P10-design/primary-9df8106752836173.html.gz` | be82ce7b6a5265df774803a1ea7e6e5f5666d0e71166b69f6453a95b2f59ce08 |
| `artifacts/P10-design/primary-a04effa473fb145c.html.gz` | 0306296e3be3ead4a82b969e717937d896afaf320436ba7c9e3fed395bccb7e7 |
| `artifacts/P10-design/primary-d2339cc3e4d51f42.html.gz` | a815679f6cc010b2d613354d37752b2df35fb71723f79a984f7e5cbd9a53e8e9 |
| `artifacts/P10-design/primary-f561a6295e1b6a09.html.gz` | c8fe5780399b9fee1b8ade94de347a4e7f65972c4a864b7e459e807f129773e3 |
| `artifacts/P10-design/primary-nist80034.pdf.gz` | fe441dce0b21e775a62d10508d88a117c13b9fd3e13b9f4ce1f0d40bc05dc7bf |
| `artifacts/P10-design/primary-retrieval-failures.json` | 5c8c6e2e99a9fbaae989a25d56cb29f897e40bc5d585123ae3e70008efd5d6a5 |
| `artifacts/P10-design/primary-source-retrieval.json` | 8a3482bbcee5c035d749842e78e8e4e0ea646eb4da07147ee5ae3177d1b4df90 |
| `artifacts/P10-design/route-evidence-final.json` | 4b92e232c8c0e310e8b321575c809cac26ddb1563e3aef26fdaf4fd9ead00f65 |
| `artifacts/P10-design/route-evidence.json` | c194244febeade7683b5196604b2e95d57655f6d33f3df457284d92a0d426605 |
| `artifacts/P10-design/run-id.txt` | 2113cda6a6af38cd3d523bcb807a09321b5d6b76bbb473e91fec189291f0d674 |
| `artifacts/P10-design/source-baseline.json` | 7364613503c7285b229a555c8813489618577caa7d2e71432608c23d125e3586 |
| `artifacts/P10-design/source-observations.json` | 50591cfc88552535671865351ee636bc62c9d7eb667b6ba315d51f57a5a43de3 |
| `artifacts/P10-design/source-preservation.json` | 701ff61eaadddb850dc5e49356f04837e6021a1da9e23fcfeccd2d3320a676b1 |
| `artifacts/P10-design/validation.md` | 62e5bfa20724fd0c0dcc83d9a941c29a9edfb09ed25cf410059b3d5f6e3a75b0 |
| `artifacts/P10-design/work-estimate.json` | d8fc9f1a182e82977b445b5e10d6a0d64e949e3e6793312bad86f4d6014ec94b |
| `artifacts/P11-design/check-results-initial.json` | b8dc803e5f0f53322a77de6ea27393c100cf35798a516116430b13dc08dd8b21 |
| `artifacts/P11-design/check-results-intermediate.json` | 4c7189d6b790e27cb8fcac51b2e0b9fa347e1af950531b7c2b248ac9d2cca655 |
| `artifacts/P11-design/check-results.json` | 11da1fc7d4b81651cac512ded8057bcad112759748d6a9e667699f10864ed6c3 |
| `artifacts/P11-design/continuity-decision.json` | 2dff34b61c507234e5b9169ed09df5fe76df16f91626eaa0e4f82096dc0a70c6 |
| `artifacts/P11-design/contract-smoke.json` | e5bd1c3fc25f59ab01dc90ba281e8aeed05533964bbf4e72bfa9dbd4227f7878 |
| `artifacts/P11-design/handoff-receipt.json` | aa8901fee14044719a34833f7cfe662d52379db20ad5839a6288b86234629c16 |
| `artifacts/P11-design/lifecycle-selection.json` | 6b2ea79121f2d7a40f87f05e2c7c6a488870545f9299aec6f0a4112103d5c7d6 |
| `artifacts/P11-design/next-carrier.json` | 3b1611400c9e50110ec8ab2433feb465a3f2761b725ab448fdbb4c5489cc4a40 |
| `artifacts/P11-design/perplexity-response.json` | 4b3ac53dacaecdcc2a587a8ba92615d6f6427839c6736cec7dc0f273e3f3426a |
| `artifacts/P11-design/primary-0801e1ee8a70e53f.gz` | 9fcc0123f6649a987404dc03259134bad4d053855d47aa73d3ffd307080e98ac |
| `artifacts/P11-design/primary-37e861981f44da80.gz` | 59470421438dbb7ade37392ca3cf1225b360ff05feec95db443509230f5754a4 |
| `artifacts/P11-design/primary-72baf4e59af53fd9.gz` | 2c63528add67958feafdd8d90d65757e1c86b1b0c2b892b7296c90b959efc2a6 |
| `artifacts/P11-design/primary-d77223ddb3c3a83c.gz` | 5a24512c86dff907f015fc50ed9fad98da6e52b491f4d6717667835d1b9e295d |
| `artifacts/P11-design/primary-source-retrieval.json` | 1d4cb989b9c46df0ed0b410c3cee35e6fa6c530882783fb69b5804d83e72c2bb |
| `artifacts/P11-design/prior-design-checks.json` | f00c9402b8c97a216f504ce1b888c0d725cefb40785ecf5aaea2fa006b199774 |
| `artifacts/P11-design/remaining-design-inputs.json` | c318e06b6f3eca0136de07bc703cfc49c1cc17fb11d080db6431d66730d137d6 |
| `artifacts/P11-design/render-final-limitation.json` | 3ce1d1b1e1e5eac9c46e68845a3a781cfa7307fe40b2d376027f9db575dd4c22 |
| `artifacts/P11-design/retrieval-history.json` | c086146b51226ee5b898fae0ab4b699371c9451cf2fee1cc75a800858ba340fa |
| `artifacts/P11-design/route-evidence-final.json` | c43226ef8be3b4fe516b0bc030d87214e3dfa012b640c9318d3174eff2db351e |
| `artifacts/P11-design/route-evidence.json` | 9f449d93b7167cadd9aad294ea1aa0eac9f24372c31913b6003a326341792619 |
| `artifacts/P11-design/routing.json` | 733a3fe407041fd62bc4376b8afcd13ba12ab03fae80ab25e218d5a2a6759303 |
| `artifacts/P11-design/run-id.txt` | 0e20fe72227de92004d4d2b6a467254a4adc716d189923cf4a3cba37035c5ef3 |
| `artifacts/P11-design/source-baseline.json` | d7a8b8efbbb1b483db45ec287f085d1b287e7c4c613a9ca8b37601dac26c84ae |
| `artifacts/P11-design/source-observations.json` | 0379d471b1712fc503e9c1889d7b831a2ad96b32bfca65aebdb03b6582df8f83 |
| `artifacts/P11-design/source-preservation.json` | 1de29d93e379d370d4ee44f514ef166f6d1361de160d0dbbb3056c288547df8c |
| `artifacts/P11-design/validation.md` | 7467daafa203e973d2e381acebbe0be9646aa6edf11b17c7648866a276456eb3 |
| `artifacts/P11-design/work-estimate.json` | 392c3963d258fcb4015fe5c09c230140b1ed6813348166efedd2fc879db143c3 |
| `artifacts/P2-design/continuity-decision.json` | 3304b6d876b4f661d8151ffd3b9018c0749ae7b077c92850d67faa2bb5234ae9 |
| `artifacts/P2-design/contract-smoke.json` | a9aae8e59ce273cff18d1376aeb4fc4d9128898400c5425c9326dfbfe457eafb |
| `artifacts/P2-design/handoff-receipt.json` | 98a0c12b26d9b2516d27b63e1fd9e9ef68ab5d63d8f4d9e87cd0b3f83d994178 |
| `artifacts/P2-design/lifecycle-selection.json` | 282c6801882ab05881d150cdce060f497581097391ff3bd25ba2239e6f47af45 |
| `artifacts/P2-design/native-launch.txt` | 3d801c7bee69ba59e0b17e7b1f483ce48248546da14d9c79511699f36a4b0dce |
| `artifacts/P2-design/next-carrier.json` | 605f7bc2c43e1807b7c98c24a4fbd903f5faf2dd4f44d85e42e1cd4aff39be19 |
| `artifacts/P2-design/next-input-anchors.json` | 1b4753b3c4c66f1e347cf9f5768d1aadcb9957f59de341bdd32574cffed64ca4 |
| `artifacts/P2-design/perplexity-query.initial.txt` | 2be4d2c95d8db21b6db809d2b29929219d40094c828984d5458dff095611f872 |
| `artifacts/P2-design/perplexity-query.superseded.txt` | b48c72d9460c8594df14304e6596c56de822f51667d9f21ae848197ec8699e63 |
| `artifacts/P2-design/perplexity-query.txt` | dee8efbb075c9e6f53cd27295a70434b62b849305657bdf8a7679df78da745d2 |
| `artifacts/P2-design/perplexity-response.initial.json` | 0736410bbee68e0c81df0e8aa2faa232e95292ca83af5040cd0232fe29ad8672 |
| `artifacts/P2-design/perplexity-response.json` | 47f5cc274a28324cd794ef9a4f71ea5cdf6eaf12c203d525d872752fd130f692 |
| `artifacts/P2-design/perplexity-response.superseded.json` | 7a10de6995c910a674342a2715a828ce891ab66e26d8102d55056be3dac20908 |
| `artifacts/P2-design/route-evidence.final.json` | 1d55aa26ec48b6126ce4e5396e62725af2230e6f5e73961e9ede449049ed8b20 |
| `artifacts/P2-design/route-evidence.json` | ecf341b11f8c50c80ea15fa4b4bf6df2539ca227ea7149920323236173b5ea67 |
| `artifacts/P2-design/route-run-receipt.json` | 0d07dc323eee2914f3ad3d437cac242ffe2e6485d6fd992e66b0a74f0daf7fc6 |
| `artifacts/P2-design/source-observations.json` | 2b633feeb5a4111247db1d8268f096a21688f820a0f6c7a599e0c964e1c7e50b |
| `artifacts/P2-design/validation.md` | 5bca39c1ac9bf4e55c9fade6a1534a0f74140b5fe4b4d5e0173d52ca393fa002 |
| `artifacts/P2-design/work-estimate.json` | 8410dd5d5412bba13c6bebbacd3dceae4587f4389b031f447886ad844ec695cb |
| `artifacts/P3-design/authoring-notes.md` | 9c72e8617d3e5f7d2eda868ebaa50cb513dc2c302c88207b5ac43756831ff5d4 |
| `artifacts/P3-design/continuity-decision.json` | 0bf68ed06ed55a35b14f2f150c1e8a35492bf49997b142a689abb95edc5efdf0 |
| `artifacts/P3-design/contract-smoke.json` | 56dbfdd5e6ddca51e6d528768f666a025ec8a77b92aa5eeb120d175a3b8f43b6 |
| `artifacts/P3-design/handoff-receipt.json` | 5db1ba6e55f6a61a48c1c0918b6e06a4cb5b970cf3e081c26b2083b9b1adc30a |
| `artifacts/P3-design/journal-encoding-observation.json` | dcaa67acc8237500588fabe68c6d42bde6fc7c1b14c2aece7e0ceaa30e29788c |
| `artifacts/P3-design/lifecycle-selection.json` | 2c3994720423bb8ce8ed1e5f5bb9cc093158177662c66596e42d5ac255fe8246 |
| `artifacts/P3-design/native-launch.txt` | e6f695fd01a4966b261184f38aad3aebf11d69065e1dd2116cbf32fd01db9de5 |
| `artifacts/P3-design/next-carrier.json` | fba63c7a86730764016cf4d97e1afe137709a2a886a139d033f7b9b54e9afd5c |
| `artifacts/P3-design/next-input-anchors.json` | 5406bf0e3a407a79f277c728473b6795b8b291a1ff895d557fec130beb49bb41 |
| `artifacts/P3-design/perplexity-query.initial.txt` | 1ec044bad9bacce50074204241c50f498cb5f6b8a8d8ad9db2df29744d30a4c7 |
| `artifacts/P3-design/perplexity-query.transport.txt` | 3cf5fde96a3fb04805a5198710889379f5a4ab5a11a1b5468999800e7c39d4b3 |
| `artifacts/P3-design/perplexity-query.txt` | 9f17d818d1caf765ef0dd36a9beb8536de3b24c42d847be88384dca30147ecdf |
| `artifacts/P3-design/perplexity-response.initial.json` | 5b5951e569b699c6a1883b127a4fad1c40c81d150afeb75b14293d5c282ed883 |
| `artifacts/P3-design/perplexity-response.json` | 717bd18fd64205ecc4779da0136f79142c22068a44fe4dd95f16a8aaedbac6cb |
| `artifacts/P3-design/perplexity-response.transport.json` | a44fa9e12bfa8dbcefa33ffd88725349b4a09789870a7e3f523ee6f24589a7b4 |
| `artifacts/P3-design/primary-verification.json` | 19972eb3628a0fb2f9db54daae597b1e469ab45e88b9f32175cc4d0a2c3cfca1 |
| `artifacts/P3-design/route-evidence.final.json` | 603b0a6217cd0fa9b620be6d0be3623ec8deeda97f729da903bb88717bb0a3c9 |
| `artifacts/P3-design/route-evidence.json` | 9a2d0a0b229ec5878cba663987eed83c4010884a6b72d70b8a58b4894141811d |
| `artifacts/P3-design/route-run-receipt.json` | 5781c032273204d4370bf83ab23af186c3503ec6acd9f49a6caf0fd893d49c76 |
| `artifacts/P3-design/source-observations.json` | 8752efe839dfe47ccdc267cc8c55538b76681dc736557c9cb28f66ce4125a0b0 |
| `artifacts/P3-design/validation.md` | 9b2205d84145fd48a0cf6778d86fe9d0d6182e2ccdd5b9c3eb78480be70469cb |
| `artifacts/P3-design/work-estimate.json` | 369d3de9c9d61be5677b19bf95116892386d11736592cf60335ea34787d4784d |
| `artifacts/P4-design/authoring-notes.md` | 20fec67c53b6a7b9d6aee370d3ed37adb2e931ba750aacd99fcd9759ef317ed8 |
| `artifacts/P4-design/continuity-decision.json` | 1c66416b968f0e442337caa09160ea820fc9105eec49e37f486af1b7dbdc73f5 |
| `artifacts/P4-design/contract-smoke.json` | aab8332917f14a00ad1e1104cad5a00cceb98dff9bdc0dc428b6bcf73ccf3e1b |
| `artifacts/P4-design/final-integrity.json` | e1cacece9ed518c794ad65e9e77f5cdd7ad01a49a8a91d6e9551020038aba204 |
| `artifacts/P4-design/focused-checks.json` | c5a1ec9f0aefa44baf2472573c64d028ea508b6456da995e842b03615d3c6bcb |
| `artifacts/P4-design/handoff-receipt.json` | 8a6e6f1d5f00485844bf8491676e2a60146815158912e61c7775abfea8d14fb0 |
| `artifacts/P4-design/lifecycle-selection.json` | fad456bdde3317bc46f5814aad3acd11cf6edd99984423ae41a824d55bd95022 |
| `artifacts/P4-design/native-launch.txt` | b80189a32d61c87ad589f09a1540fa3fafbbce55611016735a3a34e707f6ce22 |
| `artifacts/P4-design/next-carrier.json` | 32d49cb201f6daf4940fb9a277eb7f3e6980ae73c7255143c2f9543c49273613 |
| `artifacts/P4-design/next-input-anchors.json` | 5ae4d50aafa6d036c93ddcdaddcca021e6566dc2c2a267451fa2f70355b17374 |
| `artifacts/P4-design/perplexity-query.focused.txt` | c1053e87da343efed62e6dd176e1b844cae6d799add378b5e0d492b758d5959d |
| `artifacts/P4-design/perplexity-query.initial.txt` | 5e6cc007cfbee776199e2b7c3e783c3d683582a660ec00b87ccdf0451d3468cb |
| `artifacts/P4-design/perplexity-query.txt` | 5b0c36a60f7a23245dbbe73317af53a950295f5de2c4575046709c8236e71bbc |
| `artifacts/P4-design/perplexity-response.focused.json` | ad5b384ab3ae1fcdd7d2a7ec2571a7d98070775fb139b56d47b8efe3a065a553 |
| `artifacts/P4-design/perplexity-response.initial.json` | a6f6f1f0bc0f7400dacf263ec1b9513cf06c63121d9175b6821317bcac68fc83 |
| `artifacts/P4-design/perplexity-response.json` | f17806380a5e5ebe2baf8a99c28111fbb3c7e444795ff5dc37db42638a089c88 |
| `artifacts/P4-design/primary-verification.additional.json` | 61fc3dbb0214385d32c15cb13d931edf8fec627ab7a3cbe51ecd23303de2d017 |
| `artifacts/P4-design/primary-verification.json` | 897519b661546a71db6f33a9a6cecb0929265ac708efee82fab26df3234fc50c |
| `artifacts/P4-design/route-evidence.final.json` | 048bc2c4e7a4537bcd75da29329ff7c156deb95625007039af4bb36e84fa39b2 |
| `artifacts/P4-design/route-evidence.json` | 610fddf9019762a1c0574d4e096548a43916c1f5eeb2ba16bcb81307093640ae |
| `artifacts/P4-design/source-observations.json` | ba0c1277a0b32d33182a125449cba1f1a527a525e64805e70f96869009dfaf10 |
| `artifacts/P4-design/validation.md` | c336fea2e573caa1f1d3e01a8a8f97fe9fc936a22731529c7f327978cd41ac20 |
| `artifacts/P4-design/work-estimate.json` | 57756d23ab005b42de7f00e0753fba3ec334a843f02a14fb587aab53bb1b40e5 |
| `artifacts/P5-design/check-results.json` | 82ccd79f1676291bb9e4cf5f13758329a740a6bd4e72db708c03e4f2f2460d4d |
| `artifacts/P5-design/check-results.resumed.json` | 18e57bf4217d52b142b73fac76dece45a65cbd4d1af9fd5979dcecd5f4fb4711 |
| `artifacts/P5-design/context-measurement.resumed.json` | 99a00159feb34287bbc63792dc79755052faf4bbdf3537f810cd635d5567c3c8 |
| `artifacts/P5-design/continuity-decision.json` | cadb37205b1aa98eeefcfaa12e1bcb290d5a27179f60c44c1be2460ebfeb7743 |
| `artifacts/P5-design/continuity-decision.resumed.json` | 1893a5940f345a05b4370a58eaac0609e5e94eb7296d528b894b7fef4e1e07d6 |
| `artifacts/P5-design/delivery-receipt.json` | fcbf31821d39d1a8afe44d75ce85bd05d8ce5e57cdf334e441dda2707a257f48 |
| `artifacts/P5-design/delivery-receipt.resumed.json` | bbd6135bf908e6c21b64d6f4de471e53363b7f2e95490271c0f9c3f3896f8b9f |
| `artifacts/P5-design/final-checks.resumed.json` | 62bd9c3a2a40286866ca3bf27aea1272e9485a812961c81216874dab7373163b |
| `artifacts/P5-design/final-shared-lint.json` | b9d397f66dfdc87414a92a53e72b7ea45187d060db0020d9b9970a1f02113cf7 |
| `artifacts/P5-design/lifecycle-selection.json` | 2c3994720423bb8ce8ed1e5f5bb9cc093158177662c66596e42d5ac255fe8246 |
| `artifacts/P5-design/lifecycle-selection.resumed.json` | fad456bdde3317bc46f5814aad3acd11cf6edd99984423ae41a824d55bd95022 |
| `artifacts/P5-design/next-carrier.json` | aad767a8849bce7174805202fbbf18f08a51aafbdf4d7cd2429c5533fa451fdf |
| `artifacts/P5-design/next-carrier.resumed.json` | e66211fe0964d51054779a40d772d4be9dcc610feccb58720ac96004afd68684 |
| `artifacts/P5-design/next-input-anchors.json` | 4a8699f1571161da4ab33bee4c964d7e8da18195ebaebb96fc4f8ccc6c507ca6 |
| `artifacts/P5-design/next-input-anchors.resumed.json` | 3642317f9b5725366381f21db982d5464fff4c5a15a842c7fbddbbbd85f2ba3d |
| `artifacts/P5-design/perplexity-login.retry-focused.json` | 9997b9bbaeac2db8f7ad739cdd4aaf6c78ce6247ec5501d94398c87503a02eb3 |
| `artifacts/P5-design/perplexity-login.retry-owner.json` | 0dfeb88300a826c1dbb654378e89166c2d38dce997841001e034a53818575900 |
| `artifacts/P5-design/perplexity-query.initial.txt` | c372197a4e6c1c8ae9c8a462614d5df06d6ee2bf984165a592927cedb4963221 |
| `artifacts/P5-design/perplexity-query.minimal-failed.txt` | 349fac87d974cd4d174fd5fe1c46aea5c5c19ebc9ac828a091f75cc9534131ba |
| `artifacts/P5-design/perplexity-query.resumed-digest-web.txt` | b0c10ad4ea3852d5edae05a83bf992c3fda8f1235f9aaa71049b8a59144015ec |
| `artifacts/P5-design/perplexity-query.resumed-root-digest.txt` | 2e7c96b3d9591fe36ffa5c385a7a4a728ddb1323c4b2b0d9d1c36100720fc781 |
| `artifacts/P5-design/perplexity-query.resumed-routing-web.txt` | 6ccb0624324ed6ca961346ec7f94af7e91f37e9652ac2b9e23997fdd48792bb7 |
| `artifacts/P5-design/perplexity-query.retry-focused-web.txt` | 0c7d18eecabc9032bc12a19987d116d87138786125381e3c2670ba4ea6efdba1 |
| `artifacts/P5-design/perplexity-query.retry-focused.txt` | faaa046bb5d2a1071925365ad44301214ea50be2d740005867daadb501c74c93 |
| `artifacts/P5-design/perplexity-query.retry-owner.txt` | 358d21be9289e0cf19bd058064e7d13e2dd2f6e0a1997a5e69cf408fd9502dd5 |
| `artifacts/P5-design/perplexity-query.supplementary-failed.txt` | d87d5e3f16444e52ab7715661150f60891f735e02e1c9479e080a30f7001ef1a |
| `artifacts/P5-design/perplexity-query.txt` | 358d21be9289e0cf19bd058064e7d13e2dd2f6e0a1997a5e69cf408fd9502dd5 |
| `artifacts/P5-design/perplexity-response.initial.json` | ff97fc56c30578407a73ffc21ddb7bcab6a53a037659c0735e9b334e62f17750 |
| `artifacts/P5-design/perplexity-response.json` | eb455634d98514641f998ecc219c00086956753f7e60d18d29a93b736cd05bfc |
| `artifacts/P5-design/perplexity-response.minimal-failed.json` | ff97fc56c30578407a73ffc21ddb7bcab6a53a037659c0735e9b334e62f17750 |
| `artifacts/P5-design/perplexity-response.resumed-digest-web.json` | 03bb2e2bbaad48983855279aba2d9d946eb82bdcbf899637524dcb3e2270eacb |
| `artifacts/P5-design/perplexity-response.resumed-root-digest.json` | 5b5951e569b699c6a1883b127a4fad1c40c81d150afeb75b14293d5c282ed883 |
| `artifacts/P5-design/perplexity-response.resumed-routing-web.json` | 9cb0067a1e72cc13b8855b8279461879a34587d6d956b55f08a87e2ae5840f07 |
| `artifacts/P5-design/perplexity-response.retry-focused.json` | 58cda18f90511173fb331cf331e091ebea0436c42c388773be70ebb8f9d7504c |
| `artifacts/P5-design/perplexity-response.retry-owner.json` | eb455634d98514641f998ecc219c00086956753f7e60d18d29a93b736cd05bfc |
| `artifacts/P5-design/perplexity-response.supplementary-failed.json` | ff97fc56c30578407a73ffc21ddb7bcab6a53a037659c0735e9b334e62f17750 |
| `artifacts/P5-design/primary-verification.json` | 18cac70d016d0183f256a5692a4b8ee51dba5130bc955e765a2db4a20cca77f0 |
| `artifacts/P5-design/primary-verification.resumed.json` | 729e7c5383031e7f6f34b1eb89ae4f4a6bd3f1c6754cf83992c3788845f9a43a |
| `artifacts/P5-design/rag-coverage.resumed.json` | 05a49ec3d0bc8e1885c0b8d981f819ddb439a9031485388141f9b66d152f8a68 |
| `artifacts/P5-design/rag-failure.md` | c8cd443c808a207c458a8197b6b3fc04f04d79e352eed30ecb777fb138e61bda |
| `artifacts/P5-design/retry-owner-observation.md` | 23a07622347c00749c94bc1e4a165c2c6dc2d701224ac550006948b057c390e8 |
| `artifacts/P5-design/route-evidence.final.json` | 015a64879cde71873ac0722064dabbd12250c14d60a17a195abc32e9c03039b0 |
| `artifacts/P5-design/route-evidence.final.resumed.json` | 96f92211eaed12b4eb7478591bd5f029266cf055498e9eb19afc4b663d8b6c3c |
| `artifacts/P5-design/route-evidence.json` | 7770325eb089941a21725d6264d397f333f28998c24b1c0d0f2da54903525acb |
| `artifacts/P5-design/route-evidence.resumed.json` | e831b7073c40d8050c6ba834ed6f58261aa98baec917cc246d7595a9d379df8a |
| `artifacts/P5-design/route-evidence.retry-owner.json` | c482347223734bcbd7be10dedf3e499dac040b0adb1cbbf61ee2a7deee262ae6 |
| `artifacts/P5-design/route-run-receipt.json` | 5a615fcf2db7a1bf3199bacdc6a5281b26876213506a1e156b0e5b050bace366 |
| `artifacts/P5-design/source-observations.json` | c9625b04c00911dd447d98e1d348661ef9e2e84987306d6c21fe99d0492658ab |
| `artifacts/P5-design/source-preservation.json` | 5785dcc252b3fe7bc2bd117b0f62873451952962c32f86da9b1726d9fcbc3ee2 |
| `artifacts/P5-design/source-preservation.resumed.json` | 0016b5943386d314494fd47c6b1247ecbd7283669ab8f230621e70337aaaefaa |
| `artifacts/P5-design/validation.md` | 5629bb4c1852edb6ad63db88c89e66da1b77d3d276ccce278ad75028c46fabb5 |
| `artifacts/P5-design/validation.resumed.md` | 59aafeaa38876f59b5f9e2c0aaa904451ea42b3bfc9e1e7f62f5d7bcdf376dad |
| `artifacts/P5-design/work-estimate.json` | 8e1b03a569defd433fe19f8f6544678adc31d67b0bc3308783a7c455e476a807 |
| `artifacts/P5-design/work-estimate.resumed.json` | 586a59833e258e14703ca3787a5fe4dafd50174e2ce13924598e582c0043997e |
| `artifacts/P6-design/check-results.json` | f9f19a99c85967ae8d7cfbc0e43eff6fcf6a02f1ff7586eed4a40a5708e40317 |
| `artifacts/P6-design/context-measurement.json` | d89a43a4c0c21520b81fc2a27ae94f80ed29e332637fb135d50f39ad17b03c95 |
| `artifacts/P6-design/continuity-decision.json` | 7b4336e197d4bd86d86e3a793bb11b38c1427769cca09dfa76b1ea7fe3aa27a6 |
| `artifacts/P6-design/contract-smoke.json` | 7b1456a7556570a87dc6cde2b5a644333b49275d721af21f8e98515afd6f4ab0 |
| `artifacts/P6-design/delivery-receipt.json` | 963d07991cab9551d974ba3f6f8e04667acce91af97d86f602cb4d6b66bde686 |
| `artifacts/P6-design/downstream-structural.json` | 0c785fdee1cbe864a1d5bdc08115f44b22168fc14723faee65f8c1af0d075792 |
| `artifacts/P6-design/initial-check-results.json` | d7fa71fd1c832a260c02ab9c7583516fdb05112215555a252bcddaea2a22529d |
| `artifacts/P6-design/lifecycle-selection.json` | 2c3994720423bb8ce8ed1e5f5bb9cc093158177662c66596e42d5ac255fe8246 |
| `artifacts/P6-design/next-carrier.json` | d2722f097bb6ef7669995c3c8a3c4ca785d5ee90f62da164633d8be35e1ea915 |
| `artifacts/P6-design/next-input-anchors.json` | 1e8e35a07f1f73b10a0799be9bb7d5bab7dcc30cb68d04f2f162e350a2478182 |
| `artifacts/P6-design/perplexity-query.initial.txt` | 1dff0e0cb9eb16c7d4bad5e02462866e54f76eda6e1f96d5a7db9de3bb31203f |
| `artifacts/P6-design/perplexity-query.search.txt` | 7ed6807df3d29be93586792ba1dae4835791cd4aa3829575744e9329cc8cc5c9 |
| `artifacts/P6-design/perplexity-query.transfer.txt` | 770c2d18aa4ec3159f46da461ef452972949942ff4f6a9771044485faa146398 |
| `artifacts/P6-design/perplexity-response.initial.json` | cdeb5f19017fe2a7bacb58ca872be9f1a502f2e5fdcc0fa6d82fe420dae49d2d |
| `artifacts/P6-design/perplexity-response.json` | 64718ac0778c90c965218131f3c931bd571fb4a813fbcf70736561b88693a604 |
| `artifacts/P6-design/perplexity-response.search.json` | 64718ac0778c90c965218131f3c931bd571fb4a813fbcf70736561b88693a604 |
| `artifacts/P6-design/perplexity-response.transfer.json` | 8308fe678474a0b8595aceae0937c1d7fb7a7b3d0564fba04807abed465918ac |
| `artifacts/P6-design/primary-verification.json` | 05f940923b27fa991264d0eab0a652fa0103e544e5300227326a2cc7f1a75b57 |
| `artifacts/P6-design/route-evidence.final.json` | cd6e8ac2667790fcb5528578366df151364c38d117800a810b2fa4fbe631ad97 |
| `artifacts/P6-design/route-evidence.json` | 1188153d042dc36d382a38c9345576b2b092a5ba73dbc7bdc3ffbddfc85faa94 |
| `artifacts/P6-design/route-run-receipt.json` | c17f4345bfe69ca6f654f0a07732f64529583cda297cda6443e825fa6e6dda55 |
| `artifacts/P6-design/source-preservation.json` | e983398609b46c97b29640a65164c2dc3a4467afe89aa697549e55ca1d3f3f47 |
| `artifacts/P6-design/validation.md` | 17907a7f027d145de3cab9a2a84d12bf616175c4026aa42b87f161dabe7350fa |
| `artifacts/P6-design/work-estimate.json` | a0fa3bddcd3e3c160e43e7a2ed18e964d8494a0bd99790139f55c2066dc73264 |
| `artifacts/P7-design/case-inventory.json` | f55c2339fdb790fffbc8be8d9608693171e1ebfae439793b4be62bf4dcaed8d0 |
| `artifacts/P7-design/check-results.json` | 7b0f91e26e90c759aa1eb661f884a97afd596d6233840d728c084c6886cf6f45 |
| `artifacts/P7-design/context-measurement.json` | 482b4b4ce127eb78a4b9c65203bf62177c7865a887e3aa134fbe9cac0a5d5841 |
| `artifacts/P7-design/continuity-decision.json` | ffb2f84b38d0bdac067798a604e3a10fa60c7b198d8f3479651e5b464bc3bea3 |
| `artifacts/P7-design/contract-smoke.json` | 9dce0caa0214b31302884ac685a1d30ec2ce21c8adaa4fb30e8fd48fb1f9dcbd |
| `artifacts/P7-design/delivery-receipt.json` | 6ebdb986e5c7fd10862b258505ca6b5d656f098aee663bdada2923537e7d65a0 |
| `artifacts/P7-design/downstream-structural.json` | 42514334644658e21018cfb61ff80829d97390022d6c39e657b0d2fc874d1fe3 |
| `artifacts/P7-design/initial-check-results.json` | 5720d17727729b3db7d8b434b638697f83f2cae28c4f76b6330e3d275d34a953 |
| `artifacts/P7-design/lifecycle-selection.json` | 348b23581f05f74575c43340ffcaedc94721fb212baa34fb42c221e375e7a38f |
| `artifacts/P7-design/next-carrier.json` | 30b7af57e0c1a53679d000328ef7f87e96865d6915dc75d69721d9eba654c081 |
| `artifacts/P7-design/next-input-anchors.json` | bca8f7e94b2ee963efc3b1d941aec906e7fdfc3237c5d4a98d39a8c7729568e8 |
| `artifacts/P7-design/perplexity-query.geo.txt` | b4397197d1627031f26ac8616c9e34309fe39ed6ae205edaa346418b3a15de88 |
| `artifacts/P7-design/perplexity-query.incomplete.txt` | cb3113c103e5bc4411d1eadc53811d53746ffec97dac7877ce7cbce41d8673d9 |
| `artifacts/P7-design/perplexity-query.intl.failed.txt` | b9f6834f8dd470a5c9a62570e3d583ede77ef922787a9160e383941054f0fca3 |
| `artifacts/P7-design/perplexity-query.intl.txt` | 23d8e1996a0069860b7f0a1c4cb48eb9a103c11b7faa1f19fc04f53219259964 |
| `artifacts/P7-design/perplexity-query.optional.txt` | 97db7941181a6b7a5d1f0e3681aa55d0e742acb8491dc1a38668ff4ed88272dc |
| `artifacts/P7-design/perplexity-query.recurrence.failed.txt` | 173c58f9e012c306cd0826220f0a4e39121404a65efad44655853b23f81e36b6 |
| `artifacts/P7-design/perplexity-query.recurrence.txt` | 120b4c993c05b13ed95b30246f049791a9834d9d73a08c47f8b6484218641c73 |
| `artifacts/P7-design/perplexity-response.geo.json` | d0d1a9251caf4ae25bc4c13aa331c8ebc118ff6153e30b9a320b5cdad06f10ae |
| `artifacts/P7-design/perplexity-response.incomplete.json` | 42a113094b70aca5c944b726984ae3bb65cb5a5b497d9585cf55c5eeec62b2bc |
| `artifacts/P7-design/perplexity-response.intl.failed.json` | 5b5951e569b699c6a1883b127a4fad1c40c81d150afeb75b14293d5c282ed883 |
| `artifacts/P7-design/perplexity-response.json` | e13758d0cd0f5a139ae3050f171b71bfb71158a94d59182222d8cce9c099446f |
| `artifacts/P7-design/perplexity-response.optional.json` | 98703ed6ae687ac3da4b87b1e685b1bcaf462a0c0160363093a5874a690cffc9 |
| `artifacts/P7-design/perplexity-response.recurrence.failed.json` | 5b5951e569b699c6a1883b127a4fad1c40c81d150afeb75b14293d5c282ed883 |
| `artifacts/P7-design/perplexity-response.recurrence.json` | f012d972aaa5c72e556063fc132c24e9aed46511391531effd307622ab9a44b3 |
| `artifacts/P7-design/primary-verification.json` | f0134aba9c6b47523a94afb36408898c729ddcbccc2cb760be31a293823ffc92 |
| `artifacts/P7-design/route-evidence.final.json` | bb235e02409c645817e50860839aa2d3e742a42f5cd96ad5b8f23f22d3bcbccd |
| `artifacts/P7-design/route-evidence.json` | 5c30dd79ffbf49e0dc889ea66848d5496a8130152108a3835843d1257b18aa16 |
| `artifacts/P7-design/route-run-receipt.json` | 7b8df422f330c50e39d3ef5c2fb4ed181b24ca218f5d73fb862438286052f4ff |
| `artifacts/P7-design/source-preservation.json` | 2d66385c9cb4c47c1497d8791c1be01cb5cfed536cd7a39e029eff14dd55bd56 |
| `artifacts/P7-design/validation.md` | f8f3981815cae55a263b3f79356e2c276769c03e9dbe5ba06ef58706dcdbf60a |
| `artifacts/P7-design/work-estimate.json` | de112fd2e3239d32669785750227a30c2d47d2381dddb3cee33a1d127c683035 |
| `artifacts/P8-design/case-inventory.json` | a8c613fa8caea7e69ba131608fa13fcd1651d28ca95d81b9df55b367d4a92c20 |
| `artifacts/P8-design/check-results.json` | 46f56d2aeb3bb5b11d8022fcb1bf48b4122dd4b6272fd8850c9761f6e940ab27 |
| `artifacts/P8-design/context-measurement.json` | f2c7f318ad79ddc6b8449ba84df67f7cfa836394133b1233e94e075cac91b1f2 |
| `artifacts/P8-design/continuity-decision.json` | 8738dec8ea5742baa32bb9f4336addf69b71c88d3c52208474a5136bc5af7925 |
| `artifacts/P8-design/contract-smoke.json` | d17f02f7789aef2d8dc9795a3bd3566490a4014eeb31706e021cd3b147308ec0 |
| `artifacts/P8-design/delivery-receipt.json` | 2ca6947bf98edf74b7a6e1968dff1725cac5759b6f2a0781c3f9772893e446d9 |
| `artifacts/P8-design/design-check-results.json` | 01912cf1122ba54e5f720eb98f4f059affa0d1831c4d87ff033cca68b83c8b3b |
| `artifacts/P8-design/downstream-structural.json` | 7473bc5962afbc5f7cee16b9b3ba21150b744ef16358aaa535d0275f53d00233 |
| `artifacts/P8-design/final-recommendation.md` | 2cab16afc0145ca1b93f82ec6bc0f28add6a71e83ecae7353201f6a9e6dd6501 |
| `artifacts/P8-design/git-status.after.txt` | f5e69a1ccec98e64db065c75b75571e8f804e546960d50ef7c6c4bac645f2b08 |
| `artifacts/P8-design/git-status.before.txt` | f5e69a1ccec98e64db065c75b75571e8f804e546960d50ef7c6c4bac645f2b08 |
| `artifacts/P8-design/lifecycle-selection.json` | 3b71af213ea6ca2fbcf67b2db5ed064580c1953bc81f3786c6434892c2a71244 |
| `artifacts/P8-design/next-carrier.json` | 949129cfdce20a414fbdf5b65b52369f785e209d76fa8f2a4c5b5c122a98109f |
| `artifacts/P8-design/next-input-anchors.json` | 5bc6b695f4e070772346bab0a9804d31570db6cc240137c374626ef2bcd12a3b |
| `artifacts/P8-design/parent-counts.json` | 4cefcf9c84e19256d590d1212491a133f86fd38c2c0a1f090cfa2a27d6b7906c |
| `artifacts/P8-design/perplexity-query.initial.txt` | 40cb7151110caf944cf55ad622882c0123f386d0495bea0503a619d0345c8c51 |
| `artifacts/P8-design/perplexity-query.schema.txt` | fbec5fa1870d2519bb71586f44b79528f776dfefb4ff1d17841ce3e318d0e420 |
| `artifacts/P8-design/perplexity-query.txt` | ff44148ef07f19b64e9b63dafb54e093b3e548c4a98ec5306a90ae2cc62fd4da |
| `artifacts/P8-design/perplexity-response.initial.json` | a74b759e211814574ce6e922d62a58954e7427002bde4428c5c418f7ee532a03 |
| `artifacts/P8-design/perplexity-response.json` | 3e1324c3ecee2ff99c20f7a98689b42f6d074a2b74e1ec08aecf562ce269de7c |
| `artifacts/P8-design/perplexity-response.schema.json` | bc552b2c41ebcddeebc378c73d18bc32abd5f41044fb29cac14a7fd7a505fd0b |
| `artifacts/P8-design/primary-verification.json` | 97a0bf24e08e7e15638da3eb8d0a9469a6a10aaca1fc056fa3edb6226b8db0ea |
| `artifacts/P8-design/product-baseline.json` | 7364613503c7285b229a555c8813489618577caa7d2e71432608c23d125e3586 |
| `artifacts/P8-design/route-evidence.final.json` | f1bacfaf89ec28577b7f878830adee2de13a2a7db666dae45805402c5b8c8471 |
| `artifacts/P8-design/route-evidence.json` | 0b9839b754089a4db2a62982640f5fe2014500beff139e32d59be96bd3122c2a |
| `artifacts/P8-design/route-run-receipt.json` | 5d65e4d36d992691763249d2414636af59df5118750b70d7551d7469d4e1b3b5 |
| `artifacts/P8-design/source-observations.json` | 5c15c38e5be0a3fc6e3771b5e2399195fee51df1c95e76a15b9c4dd639e48823 |
| `artifacts/P8-design/source-preservation.json` | 65ef2fed658a28de4e1da27991248faab2909be40cf94c46df1c2329f5f00cf7 |
| `artifacts/P8-design/validation.md` | 37e8fcc3ad08087fcfc7d52c503666d865db1d13c247047e1288c7713a48ccfe |
| `artifacts/P8-design/work-estimate.json` | 719c1d09f6bd775c29e75f9840c35f59ab1986b380ba516652997924a737979e |
| `artifacts/P9-design/case-contracts.json` | a655ef058ce26b9b8320c6b3f4bf0e83dd589c7a69e8beb66bea057005bdf551 |
| `artifacts/P9-design/check-results.json` | f2bc83ac3d774122794caf66ba893f8610baf6b2a3660f2ac0ca1cee674179ab |
| `artifacts/P9-design/context-measurement.json` | 18c35baf8aad12e66365707ba68e365e223e2ce6a32ed9f1cf8ea261aca215aa |
| `artifacts/P9-design/continuity-decision.json` | 99c27cd34ad1c60080724a997b92641813fd145eca8ec6262d13fcdd6360e8bc |
| `artifacts/P9-design/contract-smoke.json` | 85ef573260db5d9bb78502afd89ad4acf53d82cf3bb1954136c5ec3e7550f9ea |
| `artifacts/P9-design/design-check-results.json` | c7a6089fccf16dc615d60d49ab5fe46303df13ebb325fe6a3e01c69b8f9fb0d1 |
| `artifacts/P9-design/final-fragment.md` | 31cf882ee2ce5d86a0990998e8f3362fa42d9b33588c93b0ee4a7c78519a1649 |
| `artifacts/P9-design/handoff-receipt.json` | 68ae56915417e89941c8e58b1e315e9b4e404fb53365ae67d1d758b9b46ea8be |
| `artifacts/P9-design/lifecycle-selection.json` | 2c3994720423bb8ce8ed1e5f5bb9cc093158177662c66596e42d5ac255fe8246 |
| `artifacts/P9-design/next-carrier.json` | 11f160123e726613bfe74175a7f986bfaa0838d5f53b5b601c502226efe1aaf9 |
| `artifacts/P9-design/perplexity-initial-response.json` | 4ffb6fddd3436888d6bca377bf94c36990c75f36949c5d9b389eaecba171a959 |
| `artifacts/P9-design/perplexity-matrix-incomplete.json` | be1d7d990b1048445d6aa9472e9ef4b17031fcbd9941896d4a59839538b822f5 |
| `artifacts/P9-design/perplexity-response.json` | a3a12e80d97d2524a280da08ddb846f8bbfd38b059261fbd2cb40ca30d639472 |
| `artifacts/P9-design/primary-source-retrieval.json` | 91e6f6a2762629580dedcba497be407c73e062d31b4f5153c8fc90d6d13da43b |
| `artifacts/P9-design/primary-web-find.json` | 9a65bd9b426a07582c8d24c14606d56b801d666f128cdc24428d523776f8f73d |
| `artifacts/P9-design/primary-web-open.json` | d48887fb8371b8d6910f248b7cca806cfba562249419acea9e3a445ae13bc529 |
| `artifacts/P9-design/primary-web-supplement.json` | d4fdeb1fecdd848ced6abe83907f245f05c9160d81e81a156f40d285f48528ce |
| `artifacts/P9-design/primary/composer-basic.html.gz` | 2c63528add67958feafdd8d90d65757e1c86b1b0c2b892b7296c90b959efc2a6 |
| `artifacts/P9-design/primary/composer-cli.html.gz` | f6db3570fce56ba5e9652ee8402e70f60c36cb93c9c2fd6a709b65bd145b7086 |
| `artifacts/P9-design/primary/composer-repositories.html.gz` | d882ae6b9520362cb68bfb1d6377a2b7add609082c171b2eb4b68b1cd5522ae3 |
| `artifacts/P9-design/primary/git-archive.html.gz` | 9d3690adfa8797020ee4b6bf1656c8deaa438fb25dbdb488b46e4a9fc07982ba |
| `artifacts/P9-design/primary/github-contexts.html.gz` | 3f986b7b64cc45a705ccee05aea0058782908cec9657818c3ab03d3fe7073125 |
| `artifacts/P9-design/primary/github-immutable.html.gz` | 074df05e852e069a9baf7344c4d873193d12f79fe14687f53798c4751571e699 |
| `artifacts/P9-design/primary/github-security.html.gz` | 234b49984ca0a2b257fb27304dd3c1bf39b5d7b9f95a05ac04d206d5fce8b116 |
| `artifacts/P9-design/primary/laravel-migrations.html.gz` | d2887820762ea79fea11ae3ac71b0931a7f9b141c4abe0d00305adf50223b2cf |
| `artifacts/P9-design/primary/owasp-authorization.html.gz` | c8489160d0e1f85266ad0aa0692ea58d008553bc52116f81b9e94d694cc4dadf |
| `artifacts/P9-design/primary/postgres-dump.html.gz` | 05a65b3f3043340c6b9cde2049152985b81f5e1170ff25309b3c8bb12c05a9f9 |
| `artifacts/P9-design/primary/reproducible-timestamps.html.gz` | 82b10d7cfa6e2711feaa5f3fb0c6697d315a6b59fcb100f728f968bb824d371f |
| `artifacts/P9-design/primary/semver.html.gz` | 5fe48ab99a8c8ab1e395dc2eb32fe9272c051e8dc1f9e758dbf1ea2321459ee9 |
| `artifacts/P9-design/primary/slsa-provenance.html.gz` | b64104b7039cddf9dd75c6168a926dbacc9b750de9b6ccd01916aa13f5c37ee5 |
| `artifacts/P9-design/product-baseline.json` | 7364613503c7285b229a555c8813489618577caa7d2e71432608c23d125e3586 |
| `artifacts/P9-design/route-evidence-final.json` | 7c952e798c8b5a37b7ebfbe412b090fc48691b618535be7ef099ee658155bdd1 |
| `artifacts/P9-design/route-evidence.json` | 133ba6718c4e6e59fba0972d3058123135ac39631495099306a44a5b6bbfb78c |
| `artifacts/P9-design/routing.json` | ee6eaf6fee1f7fb852b3ace880050182dedd6440cb8f7d8535383004b0a4c95d |
| `artifacts/P9-design/run.json` | 61ba5c965e4fb6335c501ad16fa2adb54bf8c0e0800f07522d6386b61c1b560f |
| `artifacts/P9-design/source-observations.json` | 4b294344f16807970b9241b0d34c08fb6827882db5c91eaafdec4bada3b11e75 |
| `artifacts/P9-design/source-preservation.json` | a0c8d8cbc7dd7cd30abcd8a62eeb365f952bcbd87f081ecc2cf51a9610c66316 |
| `artifacts/P9-design/validation.md` | 08972f88739d7f9d5b9ed8b64c3b4e6b87830ce84fbcd2c01a8d10ae720fce8d |
| `artifacts/P9-design/work-estimate.json` | 72a129e06fe3dc000c5bc2dbc1cc379e14d9e28d0e94dace115877099483c194 |
| `artifacts/astra-whole-plan-review/azguard-inputs.initial.json` | 4458b7bfb218327a08936c92aee6c0eb3198f416a37059592a7548a9c6a6692b |
| `artifacts/astra-whole-plan-review/azguard-status.initial.txt` | 06cd6e6b574d601759cd625a453a42978e50f51cbb10baf85afb7877bf729368 |
| `artifacts/astra-whole-plan-review/baseline-checks.json` | 2f283e496ad5302bc0658325f2bb9987cb1bcd21a54f13835a79bfba68d8e1bd |
| `artifacts/astra-whole-plan-review/batch-routing-check.py` | 3987ede9bf0d5629c7829f32f282a71de0fbaa2317794dbc17e484d84f4c486d |
| `artifacts/astra-whole-plan-review/batch-routing.before.json` | a63b6aa078676e7bc07d4b2e90a6b847e32f9da88037be445d2d5c2c244d4aa4 |
| `artifacts/astra-whole-plan-review/batch-routing.selection.json` | 41c7e6080a71fc2aaf7c3e2bd293d9b20409cc037b417248b05054e76ab41b8d |
| `artifacts/astra-whole-plan-review/input-inventory.initial.json` | d4b51f4dcddf08482a640b1837831f2aab44b02bf121366bd692d6493dca3d36 |
| `artifacts/astra-whole-plan-review/perplexity-07-query.txt` | e381ff2c1e78950635293dfe7760196f486732e3d9d8c7fc1fe31f0f7d24148d |
| `artifacts/astra-whole-plan-review/perplexity-07-response.json` | 282853b30821548045b67c823be25be85eae682422c13905b39c0b7eda9a652c |
| `artifacts/astra-whole-plan-review/perplexity-query-01.txt` | cf202357983f46918cd9778768d082f85a92b049e00cb0bfd4ff001daf566e83 |
| `artifacts/astra-whole-plan-review/perplexity-query-02.txt` | d65b645d79a1a7773989db30fe1857ffcfeb2baca04de139bd42087596a4f770 |
| `artifacts/astra-whole-plan-review/perplexity-query-03.txt` | 32508296dc2ba750ed8a69ca7e66f46a21d4ee81a3f7ca5db226ea3862713b20 |
| `artifacts/astra-whole-plan-review/perplexity-query-04.txt` | 31ed10f2c99be8fcdd93e602467db8ea3cf67aab313943a2c4d3cee4c3996c8c |
| `artifacts/astra-whole-plan-review/perplexity-query-05.txt` | 4eaca333fdadd40aba6935c428a3ae91d2946265c5f8d714aa5cafde9717a0f4 |
| `artifacts/astra-whole-plan-review/perplexity-query-06.txt` | 72b40ef38f56f5b6fc54e2f41d1b13565a0a8d2c21fb98c500986936cba22dbc |
| `artifacts/astra-whole-plan-review/perplexity-response-01.json` | cc7ed1c6d0a6248b45f75d3ca8ea9c878c7ddee348e14754897c0e35b98ff3ad |
| `artifacts/astra-whole-plan-review/perplexity-response-02.json` | c703400a375490aca2ab0a3ef3c17ee717c557911a78d1d17e1db2111ed4be91 |
| `artifacts/astra-whole-plan-review/perplexity-response-03.json` | 868c3ef7e0def94c19d3112486c4c703e5c85ae59409b1c8a3cf1c708fed5d5f |
| `artifacts/astra-whole-plan-review/perplexity-response-04.json` | b87b351ec46a4e85c673ab9d3bd2f2da0a60dd53f8eae9e39e01bb44f4161c8f |
| `artifacts/astra-whole-plan-review/perplexity-response-05.json` | ff778dd6021e7c4c5f54bc7f3db014a8af2727928ac3692615caa5c562f81c42 |
| `artifacts/astra-whole-plan-review/perplexity-response-06.json` | 119bdffc58d8cc66c8779aeaa2de64b4f1b0bbf28628a6e68674f75a836d84c3 |
| `artifacts/astra-whole-plan-review/reviewed-inputs.json` | e147100741f76bff10b65fd37ada59f8e34946ed1572d51484582d829e919ee0 |
| `artifacts/astra-whole-plan-review/source-preservation.json` | 46c81b633e7ca3d307da8bea899f9f1ef51b529e80df47da1abefb5e7f89ba97 |
| `artifacts/astra-whole-plan-review/upstream/00fb4e69b5fa31d0.gz` | c1a5a9ea5263e36c3ac3178c54b9d98248ceca730c7a2e9935ff7d1127b4fd02 |
| `artifacts/astra-whole-plan-review/upstream/076fab1730e0c46f.gz` | c6202c5827378ca12556e1e3cdfee33a8c06905dd21bc83a3a6bc44e4105601b |
| `artifacts/astra-whole-plan-review/upstream/087ffc89b681d6bc.gz` | ed76c32e66a8a56cf06f15e6bd9f2f51b5c146642ae848c89aa5a7617b14242d |
| `artifacts/astra-whole-plan-review/upstream/09de22b23c1bbcdd.gz` | 053f494eb2b40062b9c2b8e05b4c72e56687da2e1c1f1fa628d1e056d0f0c2ff |
| `artifacts/astra-whole-plan-review/upstream/1c9e37e6fa33b921.gz` | f6c1eb183028ec74ecaebb1ba387d6b4f1e052213a74bcf02257b108029f7d77 |
| `artifacts/astra-whole-plan-review/upstream/1ea3b3fa2383aef4.gz` | feb484ba03eae7758a449961a317b27bb1d2cdeff8ccd2873d0adfdab9a40283 |
| `artifacts/astra-whole-plan-review/upstream/1ec5c09a730738de.gz` | 41b76e18c230aef2ed8eab929e5dc20cb2635e57e296ef510e665c74c500c214 |
| `artifacts/astra-whole-plan-review/upstream/20235b3d4cb6ad7f.gz` | 0be68f2a11f3126b59b12117e65067b6814557fc33e9bbd82d52bda3d59298f0 |
| `artifacts/astra-whole-plan-review/upstream/2820077f5eb3f113.gz` | d93787b6ff5464c013fa6152d7e5ecaf709bb24e488b71312d69854a61889a61 |
| `artifacts/astra-whole-plan-review/upstream/2ae0c576e1873e71.gz` | 2998a4d3df7cf07ec28178c887646c367d9f6e66a56f464754f54d709b76ac1f |
| `artifacts/astra-whole-plan-review/upstream/2de98de619248174.gz` | 24d80e2242a1be8f741e10c471307f17fe97b19dd7db1e598681d0649dc87bfd |
| `artifacts/astra-whole-plan-review/upstream/32c06bd434fd151c.gz` | 20f7d8c4a350476c6515910531eb0f4d38edb3fc918eae2dfef8669292d338fe |
| `artifacts/astra-whole-plan-review/upstream/359020c0b2b855b1.gz` | 7bc0509fd77f28a702948c56e701f43de6807a4d32a2f1850bb26f01fd995a1d |
| `artifacts/astra-whole-plan-review/upstream/35e1b859e60f798f.gz` | ad40cd0f4de67345434f49c9e723c8a6ac23a628e23f6a4eabfea43b6d200828 |
| `artifacts/astra-whole-plan-review/upstream/44ab59859559df58.gz` | 541095090c0cb69ec1dd8573865ee25ff40c22a2d8798eb9d7cba0398db6763c |
| `artifacts/astra-whole-plan-review/upstream/49bbfc83d38292e5.gz` | e4fdc6d4175af5395a67bafe8e7a14f5a90d3dfbebd054de7ec1279ed0415e71 |
| `artifacts/astra-whole-plan-review/upstream/5086e8ac6daa2ab0.gz` | 0607b979a77fb69f7a74d21ac1106077da0a2a76a5cda83062e543b85ab5e793 |
| `artifacts/astra-whole-plan-review/upstream/51d7106837b410e7.gz` | d870aaa2792dcb0d000bd76ce622d60fcf75c3e8fdac5de83c19546959690852 |
| `artifacts/astra-whole-plan-review/upstream/584c9f6f91e251cb.gz` | 1f3878a56faa172db7cba00339d1c8a6010ba95900b123cac83b16710190aa85 |
| `artifacts/astra-whole-plan-review/upstream/603a1d61dc04ded5.gz` | ad0f0a5d9dafee77a60576b98dd91aaff5fc6f6717a8dab19999962c4d36995a |
| `artifacts/astra-whole-plan-review/upstream/735ee3bbf7d71ae5.gz` | c8ca83799c17c02a648137571eb0cfbb2363d2bc5102a856366c94fb868361dd |
| `artifacts/astra-whole-plan-review/upstream/74ab60c7a04f76a2.gz` | e15de3b48b20281712225df14a2894aa313850406d29ba1958ba7ba7ef5d1afa |
| `artifacts/astra-whole-plan-review/upstream/81b82682426c3b00.gz` | 9e5ddd1e29a26fbd77dea7b501085ea8364c6e3460531e7501cdbb2aa243b45e |
| `artifacts/astra-whole-plan-review/upstream/941604c22333c4ab.gz` | f3bc732961fc14a1ce1d567e85e96746855dc94bbea9f683d7987bf88b35040f |
| `artifacts/astra-whole-plan-review/upstream/archtechx--tenancy.tar.gz` | b14c0a301957f6a0a74c4c2893b1dbfe892f3abc071c245bc0cec542bfcd4b68 |
| `artifacts/astra-whole-plan-review/upstream/b57e3fad819daffb.gz` | 7da3c108b02f1f21b0ca176636f4a18640caefbeda1097386daa220c756eaf04 |
| `artifacts/astra-whole-plan-review/upstream/e63420dfce109775.gz` | 71d7521a95b8e40ef55902f17aecaa2198f1036f9c17c2512e1ac082abe0b070 |
| `artifacts/astra-whole-plan-review/upstream/ec49707d932cd2e0.gz` | 575327968b8e753dc208f1ecfb9ef764d31c9ae805f743fedd44da4919cb068b |
| `artifacts/astra-whole-plan-review/upstream/ec76545ab8fef9eb.gz` | 7a8741a0d188c8181a1ee59d165ec118f5b9e9da137c12431d2d23a731f8d57e |
| `artifacts/astra-whole-plan-review/upstream/eca30ae952fdb6df.gz` | 6549baa81f5a4868cf129b22871216ae8b8e4fe739cd95583f6dee916f6a42e5 |
| `artifacts/astra-whole-plan-review/upstream/filamentphp--filament.tar.gz` | de5a22ad8304ebae08b412c84b80c1cc0c65697f854192313a75108820115d24 |
| `artifacts/astra-whole-plan-review/upstream/flysystem-tags.json` | eb23dc220521cb00f1a1e50eae355dd6ecd6ceaf806eca90b369eea2c7b9ccb6 |
| `artifacts/astra-whole-plan-review/upstream/nWidart--laravel-modules.tar.gz` | 60838cd30acbe7f86e2555782d27c693d396a9a21fff73047071e9d44284a918 |
| `artifacts/astra-whole-plan-review/upstream/projects.json` | 002211a3c3a063d1398ed4a187e50c100b197955940a6efc1832b54238af9fd2 |
| `artifacts/astra-whole-plan-review/upstream/source-projects.json` | 35556ac7266c342d363a72f05a1fd15b99667a6ee0a7a3203b6096c4eae832f8 |
| `artifacts/astra-whole-plan-review/upstream/source-tests/archtechx--tenancy/tests/BootstrapperTest.php.txt` | dc3af6114bcfd949ef1b79a3a654bdc429a4f19c235a4beebfa68fd7bbc7a248 |
| `artifacts/astra-whole-plan-review/upstream/source-tests/archtechx--tenancy/tests/QueueTest.php.txt` | 3799cc515d2ba83fa43c4e87e4a5ef9ce57130f9fda3fd88cbf14108f00e2498 |
| `artifacts/astra-whole-plan-review/upstream/source-tests/filamentphp--filament/tests/src/Panels/Configuration/DarkModeTest.php.txt` | 979e6a15e2c62d5c1719f3f77cfe513a6c31f078631c0dc70d8b6e7f3b5e7555 |
| `artifacts/astra-whole-plan-review/upstream/source-tests/nWidart--laravel-modules/tests/LaravelFileRepositoryTest.php.txt` | eba1300dea19de89c42bcca1d757f9da9b7008a06676bdb50e5c2a8533df20f2 |
| `artifacts/astra-whole-plan-review/upstream/source-tests/nWidart--laravel-modules/tests/LaravelModuleTest.php.txt` | f830009b78381367ae5a499308d62970404cfaeb20daef04206f9862e8f751d8 |
| `artifacts/astra-whole-plan-review/upstream/source-tests/spatie--laravel-data/tests/ValidationTest.php.txt` | 34dd2ce4681cbfb154143a2de862a4c026913a82ea6228221c49a170d4551927 |
| `artifacts/astra-whole-plan-review/upstream/source-tests/spatie--laravel-medialibrary/tests/Feature/FileAdder/MediaConversions/DeleteMediaFolderTest.php.txt` | 0d7ed79ef1f1832b4de6df2eb1acf4faa80e76772f5ab141a941906a1ef35f72 |
| `artifacts/astra-whole-plan-review/upstream/source-tests/spatie--laravel-medialibrary/tests/Feature/FileAdder/MediaConversions/DeleteMediaTest.php.txt` | 8ac23b66abcc930c0af328893605e78258c55455ffc3819d7f835963c252e7a6 |
| `artifacts/astra-whole-plan-review/upstream/source-tests/spatie--laravel-permission/tests/Integration/CacheTest.php.txt` | 113e70691e74d3b16ce80b8fbc73a0e41d94a368c9e2154ee18ba584425c7c3d |
| `artifacts/astra-whole-plan-review/upstream/source-tests/spatie--laravel-permission/tests/Integration/OctaneListenerTest.php.txt` | 34cd86baef6be564a14ef2b230956d7d4a21cc6cf1fda6c59d4556a23e1c7adb |
| `artifacts/astra-whole-plan-review/upstream/source-tests/spatie--laravel-permission/tests/Traits/TeamScopeTest.php.txt` | 0ae3f012b6932149101009cd349b630726cda48b4533370032591bd232925195 |
| `artifacts/astra-whole-plan-review/upstream/source-tests/thephpleague--flysystem/src/WhitespacePathNormalizerTest.php.txt` | a6e8595ade0fed2d8d3bf1a7dc2aa45312f789fccc1b333e702e8048feb5ec71 |
| `artifacts/astra-whole-plan-review/upstream/source-trees.json` | 1e38fe87d408ef7233fa7859cca6473e62317077f8805ef0e9d11cf2df35ff8b |
| `artifacts/astra-whole-plan-review/upstream/spatie--laravel-data.tar.gz` | 2ccedd7e7ea26789899c76c927b4fcf8b064a8c1c97b607e3bbe727394714e60 |
| `artifacts/astra-whole-plan-review/upstream/spatie--laravel-medialibrary.tar.gz` | 103273273d607cdcae3ccb9192b5707cf1c9de0f1e0b2a9a8b2abc9aa2dec60a |
| `artifacts/astra-whole-plan-review/upstream/spatie--laravel-permission.tar.gz` | 8a619712c481b8cd6d746e17a347821f10c443479506cac28d990aa1fad5dc5b |
| `artifacts/astra-whole-plan-review/upstream/test-retrievals.json` | f0308e3a39f4ebbf625c28d7979ecfdfd9fc0fe6add5b7d766ae240b04b917b7 |
| `artifacts/astra-whole-plan-review/upstream/thephpleague--flysystem.tar.gz` | 2a8cc378a5dd0ddd170b2833f1af061c60022a1a78f3534d761e3fd4a84e7262 |
| `artifacts/source-inventory.json` | f1d9b54443750b1cd44646ffd4f67f39f65f0d4b6ad4452db5eb8a78c3cc2a59 |
| `brief/00-owner-request.md` | aa86b6854d8873028f3460afe55ce9a9b4f8a300a9810dd37f5054812b7a34a2 |
| `brief/01-owner-design-policy.md` | cebda2cf4cc82630f5b2d02cffc5dc857922125737c538b32f8b68643934181a |
| `brief/02-astra-whole-plan-review.md` | 47c2ba432612d6b014bddc10124fb01007f1b9f7c880105619727400bb967458 |
| `brief/sources/archive-corex-core-handoff.md` | 74206f155f659be1ba39b906c51efb1f8f91beae9611c92a787b2b853bbb8ba9 |
| `brief/sources/archive-corex-core-plan.md` | 701524c977850c3060ced600d7c2496f9b63484bbfc633129d7627a1adba66bb |
| `brief/sources/archive-flex-code-handoff.md` | 70efaeac4843aab2f96a708d3037c826f7c9b37644885e0c0035c8b448ec558e |
| `brief/sources/archive-flex-code-plan.md` | 541339f73be7afa4842aab908ef33c20ef5c6e3457d83034b660f48f043d310f |
| `brief/sources/archive-flex-impl-handoff.md` | 70079908c8dd0d8536e9746e154d43351cd7eda223b05b6cfe6f634fa91d5b46 |
| `brief/sources/archive-flex-impl-plan.md` | 963373f1523b8fdab48315d60b9d3966b32294509da984942e9168829183162f |
| `brief/sources/archive-flexcrm-w2-handoff.md` | 6080e5d6836cacbaee522d3f2a746f841744e5c8b266f39b7396d74b3101a607 |
| `brief/sources/archive-flexcrm-w2-plan.md` | ae7ebc5925f821d06d799eb6b72d0bebbc41463a5f52a451f2dd3b84ad7ede5e |
| `brief/sources/b01.md` | 378701a9008bed4c7fce9f0998bea55eb61c207789bab4670231e930852f22ee |
| `brief/sources/b10.md` | 814f1170d2e51cca09716e93fa912b395c03036d19e439faa860fcdb2d3de5f0 |
| `brief/sources/b11.md` | bd57811f61b4d6c33118c8af2d7bbdaede0d3ac319ad7eda97c846359b517827 |
| `brief/sources/b12.md` | 4e74c72a0401b5c99fdd16720b5377d0c4f58b65ca24027cbd440670e88b48fd |
| `brief/sources/b13.md` | e81c0f7ea57837efc66e86955563da567491943741bafb5275a883ed916d24ff |
| `brief/sources/b15.md` | 810919efe436065b2a883e0953648325a95988e45007513e8a47298e20d59447 |
| `brief/sources/b16.md` | ed4096e7e7ac522a9a6d89d3c0870b0964ddd91e1ff5c29f3413072642afeaaf |
| `brief/sources/b18.md` | e5f9feddfd1151c25bb12e5f7600e5b439f507e784aaf714d6be6c4c1f05df34 |
| `brief/sources/b24.md` | a4b01d4b30cca5a4ede3823823bee38c06f1d405dc0cc7f3807bc230fe650fbb |
| `brief/sources/b26.md` | 49812ed63122507bed5b885ed5ca6ec450ed0ee460987f2c029336e1cb0bdc7d |
| `brief/sources/b27.md` | f61a280d9efee1366115b2756e58f745a6be242694845a34e00bd84c5398c009 |
| `brief/sources/b28.md` | e3db2ed5d07fc53d0c431f53fdbdb043086d3c6df1767875e1d749d656a44213 |
| `brief/sources/b29.md` | d0ea286ff35b82f8fb90638f8a6ca87826a18389b41f84375a24256a11235d1a |
| `brief/sources/b30.md` | 963362a71c74d1ac8d71095d31c375ad25802420f4da720d10c2eba75b15cf1e |
| `brief/sources/b31.md` | 8f0942012368d234011345852f359bad4eb627c9d611641119633ae97fe39059 |
| `brief/sources/b32.md` | 443b67549c2c469a58ab990b3f48a072112bd8633279d43007d138e1e34cabb4 |
| `brief/sources/b33.md` | be6366fc074821e4d5cfd58e3b5c953875a45cce8f948da6f93a57b419d15a27 |
| `brief/sources/b34.md` | 69605804c77b821e739c9e99099b335d216734366f97de28f2d81a0b9fc3bda6 |
| `brief/sources/b35.md` | 1e04b0973e41bdeaf7e253fa351a99b543a24a55d42b17f5a64964c42b05399b |
| `brief/sources/b36.md` | d69535f45fba7097e0c307e173c2ec797638e54d5114e5ef37c02013e3da0d5f |
| `brief/sources/b38.md` | ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4 |
| `brief/sources/flex-d14.md` | 6ea8db1e7d6c424dd47b5932a21d4a694a1d3e106644259effdd1c6a842b2ad5 |
| `brief/sources/flex-d18.md` | f1ef2aa023c3febb03b33e756fe177635f62f20eedb09279a85feead11076d9b |
| `brief/sources/flex-d22.md` | d72fa73fda6cf5826055920b5bdac79b1d69feb7cd8a06c6ea6f9b61eab23a3b |
| `brief/sources/flex-d23.md` | 038d4463a5532131b6fee261c63dca5dd6c89de4475ecdb24f4cb28fce27f8e1 |
| `brief/sources/flex-d24.md` | 8ddbc84581f8ac6bbdcd77f630e79eef0da12c51afc710b57bd78c5003ca131c |
| `brief/sources/flex-d28.md` | 7983d69446582ed201e545c4f7dccf5d37548445018e5801f098613b42f6ab5f |
| `brief/sources/flex-d29.md` | 271bd9a507ab7e0f4ec49603a3b66c5b50cb4acf7d136574bfac5adccbbcbb1c |
| `brief/sources/flex-d33.md` | cfbdbf87028c873449f317ac55b098db5bf2f1768bbbbc434ab3b45e5c284928 |
| `brief/sources/flex-d37.md` | c2f1788d1ddc7519f9967467b9919fe731beb1bc18039b3a0ad5597e8da1b052 |
| `brief/sources/flex-d38.md` | faf15e9767df36c07e12a97a43f7d41e0c2fe8a6b935326e40d2b986bf1e26e0 |
| `brief/sources/flex-d39.md` | 906dcf4ea8d9ed9a14206ed0616c43cd5a8dcc7a60575f0a4a7f36629328eb05 |
| `brief/sources/flex-d40.md` | e6594a4bfc4c387bd133039e54c231b2780b403cf0411b3b55f60455db1aed8e |
| `brief/sources/flex-d41.md` | bc9b9e6fb92b81a176ed9d94048bccec7008a88e8f03e916528fcd8503ae478a |
| `brief/sources/flex-d42.md` | 39e16b9af98b06ad1dbc3cbb64aa19283b5cfce27eef706332db187dab8b1d0c |
| `brief/sources/flex-d43.md` | 876b69bb79c7f1af9b0892e102af55fd7092381c8f67bfd8ce3a985ba100121b |
| `brief/sources/flex-d46.md` | d6bd01610d84523ba3fd23308761911364bc008b3b513ce7d46d7b0f0bce53fe |
| `brief/sources/flex-d47.md` | 964d35ca50c9490442b58eb7f8307ab88c5ae5069cd7bb5e329f069fcc7cbce8 |
| `brief/sources/flex-d48.md` | 70f49266e250698a44813ab3fb63fe8f3c6ca11d69ef6b10ea3f30cf4b4df16f |
| `brief/sources/flex-d49.md` | 9894ad65e48d0178874a3365b699d0b3e9a238fd1345979e6ff38e4404192907 |
| `brief/sources/flex-d50-committed.md` | 3005cde18fec7c7c50a37712e684340dad32fd4e0c65c32599fa8234c1f7fbe8 |
| `brief/sources/flex-d50.md` | 3005cde18fec7c7c50a37712e684340dad32fd4e0c65c32599fa8234c1f7fbe8 |
| `brief/sources/flex-handoff.md` | 1d6805f348b348b89ad007e757df4b4976fb896a14cafe799f33a886562ee656 |
| `brief/sources/flex-installed-inputs.md` | 72576328514566f9e3acfd69eb6f869bd1f57bacce0239834d6bb492dcd4326b |
| `brief/sources/flex-installed.md` | 5c6c4b97d60e7959bfda21a1c74460413faecd6cf7e0596e3131a04fda9f14d4 |
| `brief/sources/flex-p117.md` | 1950e92a99a37e6915bd4c49866548adcea315ac2f8de8fe6af5a1286d312bd7 |
| `brief/sources/flex-p6-10.md` | 8c23a34e8ab587f5f8f475e06daf30f83c1b933117e9b454bc4915623b8d5c30 |
| `brief/sources/flex-p6-11.md` | ea1159f37cbd331ca6964b5b24040c5f5595b3583a1a2998d616d082bd2b22c5 |
| `brief/sources/flex-p6-12.md` | d04a3a046d49679f2b7bb0a04b4b136151848c3588386a15749f5909c8afc77e |
| `brief/sources/flex-p6-13.md` | ea47e890759e9f095449c2d4e1b0e1843e51fa0f356b97d982cd3b760a9be8b0 |
| `brief/sources/flex-p6-14.md` | 80521368394dab00292f09ab2b2aef142d074017ccb417bd50033b077c77b15c |
| `brief/sources/flex-p6-15.md` | 5a8cc7b461f04ee791eea1232f79258cf672cf5bd2df1089e464f962fa98e62a |
| `brief/sources/flex-p6-16.md` | 15f1057d792d0bbfdee10547ea78069e78fff9a4149f07eb4b6d2c8bda6e8066 |
| `brief/sources/flex-p6-17.md` | 11e27d74460faf79fa7b13a5ce4da2e1cb662991dd6de1c58a7ddd792a31f7ae |
| `brief/sources/flex-p6-18.md` | bbc15c62ab3979406305f2349a00d22a618270c424e0c88ac338982c120814bc |
| `brief/sources/flex-p6-19.md` | 7e52411e33cdd5d55c63435066901a61486b90fb8dd30c774d7cb57cf62375c4 |
| `brief/sources/flex-p6-20.md` | 773c25dcb39d2e29326a6b1b254c82b2f840ee5d830a20f628779b522902e3e7 |
| `brief/sources/flex-p6-21.md` | 5a06808257be0f7be2b68d9b6e5d436dba460818d320edf4d1247f473d12d021 |
| `brief/sources/flex-p6-22.md` | 491eb06b1d99d5479618d10d94f42de4111774cc5d957104fe02b88e28554458 |
| `brief/sources/flex-p6-23.md` | 8dad84a38fc716daa774729c3c082a7503315c541cd9d2af8faa9ffb8af36a20 |
| `brief/sources/flex-p6-24.md` | 22138cea63f31034ea7b85cae0dbcdedddc8f50716cd9e1f74fa4806add4df8b |
| `brief/sources/flex-p6-25.md` | f2b6d677cc0ddbc9a7eab98390990b37d3ed864786b018a64856d17be388ac8a |
| `brief/sources/flex-p6-26.md` | 7df574250787be2f250aa03193caa876b49ada5d1d9c2056cef9c7d5fe49a884 |
| `brief/sources/flex-p6-5.md` | 7e8cde0849f3174b7b2c1b15e1134ac0e2a4379e927ca1a36e1331a3688f355c |
| `brief/sources/flex-p6-6.md` | f945f407ae44d39a25b46275dfe1114a432827fca52bc91bc68da5f9e9fb565d |
| `brief/sources/flex-p6-7.md` | 474d301a092eed05fbf7827c1dc53bf1700db3a8f136fb0007ea37b118c08e66 |
| `brief/sources/flex-p6-8.md` | c0795cbd97197f58d65bedaa8da419a53d54c51d5a53c89cc7cecbc8fedf4e1b |
| `brief/sources/flex-p6-9.md` | af21b9c639b76186c3b0fff1b0892506677e7b98977d3e3f1611f470a04f515d |
| `brief/sources/flex-p6-map.md` | 259d31a50eed9f07aecc9ac3d5c6cc9f805ab3cb99fb7c7b33999fd80880b482 |
| `brief/sources/flex-p6.committed.md` | 7c45f1f195031770e0a583e9a9934cdd352fbfba9bd55d352395e6ae87449bd7 |
| `brief/sources/flex-p6.md` | 66bd8384e002f0ab3b3343e91f2120e6419e2de71458c2dba0827a5a40ad94e8 |
| `brief/sources/flex-p65-contract.md` | 9629b863d725e54121622444e56a3fe7d8194db8554ba0608c11a9c246fc2d4a |
| `brief/sources/flex-p65-progress.md` | a4e71cd130f0ffe741f64ae02e6aefa7c588b80579fb4fcb9a397db74d1d3fca |
| `brief/sources/flex-p91.md` | b737c32df791404f5d1774cf07cb9e90fd779d500478233d3cef5a2f8e7481f0 |
| `brief/sources/flex-plan.md` | c75cf985d7cf02c35bd82601763a05ce2c60f9f50f61351ee36bf51665e190c3 |
| `brief/sources/flex-v1-handoff.md` | 669e1d8317c94b0f75e511d620b79176f7bceed019131807217f349d476e1bd5 |
| `brief/sources/flex-v1-plan.md` | b83484681c40e0ee91932ab1cd22b1e426dc7fac149e394f0f8eaff4e9714ec5 |
| `brief/sources/vault-b38.committed.md` | b0d01790576653088bbfe51bd85b2937a6124053f7cc3a7a738f9519a1990d8b |
| `brief/sources/vault-b38.md` | ea8276254c90db6c24f97cdac2475e426fc16bad2b4cbdbe9e57d0b9061a66c4 |
| `brief/sources/vault-flex-handoff.committed.md` | 669e1d8317c94b0f75e511d620b79176f7bceed019131807217f349d476e1bd5 |
| `brief/sources/vault-flex-handoff.md` | 1d6805f348b348b89ad007e757df4b4976fb896a14cafe799f33a886562ee656 |
| `brief/sources/vault-flex-plan.committed.md` | b83484681c40e0ee91932ab1cd22b1e426dc7fac149e394f0f8eaff4e9714ec5 |
| `brief/sources/vault-flex-plan.md` | c75cf985d7cf02c35bd82601763a05ce2c60f9f50f61351ee36bf51665e190c3 |
| `decisions/D1.md` | 1b13c87b2364105e870b622b0d2f85bc2769882cb930ab82c139447352bb811d |
| `decisions/D10-workflow-wakeup.md` | cf30071864f81d7ce2b882653c8b06dfd5dab36e62d948a8ec1a9d76f1241381 |
| `decisions/D11-data-import-search.md` | deb3640ccb7a03be98d24fa515132a6912506dc0af3ff3eb6454c80c66f5b6bc |
| `decisions/D12-portable-utilities.md` | 517b369dbaf33cd5588aa6bdb380286d93b091aece5f1dd87ce05bc50923adb0 |
| `decisions/D13-tool-introspection.md` | 4470953848bfbe2c24b54782db1fed20ec90a2cc95b9d1d0c04b517cecc02fb6 |
| `decisions/D14-release-cohort.md` | cfabaff5040c6d2c0a00a4c5e52c341ea5ab5d7ec2af2236bfe37e9264c4aa20 |
| `decisions/D15-effective-acceptance.md` | 2a069bf51368af81161ab959025b45ebda32fbe3350957910221e602ba5f7f62 |
| `decisions/D16-consumer-reconciliation.md` | b2dc87759ee29b66ecec8ec8a65c15389c2a5cf91c36e57d9ce3632b05111682 |
| `decisions/D17-whole-plan-review.md` | cb8500c380d261ece723bcbe110e756477684d01f96818d339d3daec8064b5be |
| `decisions/D18-execution-batches.md` | 167ac553aa0d5e7bdd0afc6b53455a1c2b216b2413da1838538a3a2f74018340 |
| `decisions/D19-read-budget-advisory.md` | 2468dd354eafd9e71b2f786f98f3dfa8be12ada031323e6050b3f587ede89377 |
| `decisions/D2.md` | 3d6352dd37ded3e1caca32af16313796e22c58b4d927cf4ad247f8184886f818 |
| `decisions/D3.md` | 6af2445122495b980efc08f65ae3023c9b76ae388e3e7e355aed1be0bbc79c46 |
| `decisions/D4.md` | 618495807599d167c46e933d05927e895ac81d249c0ef9f6cf6ac6c02b65ef16 |
| `decisions/D5.md` | fc3c85cc58f7280f90487623c900f94602ad9e3d2ee11dd58d1729bf622cdf67 |
| `decisions/D6.md` | c547fe6117e1dd166820483dd3909661e6a63cb01880f28862b932a81b978e4e |
| `decisions/D7.md` | 68d714b48321f16fe4e311f19d5f764630632db8fe840dc67d992127d55df832 |
| `decisions/D8.md` | 69c62efb10d483ec735d27ae5b00b029c5bb209d1e1b71cb65baeee957d516d4 |
| `decisions/D9-headless-extensions.md` | 2aef863b44bef787110781cff3b69b1fd2b585048850074a71a1c5a224e43e68 |
| `decisions/_archive.md` | 1056f17d8aaba1662f8cae084c0e5cd5daf8b622b37095c9a8e3eb4085adb06b |
| `decisions/_current.md` | f09e69751b505d260f9e844d3f6c1010c894da12e40500dadfb806dc943fa05e |
| `decisions/_index.md` | dcff3343ae7f210b46cc59c25a8efc2f78c1717b53f986e60a6825e1b1b5a907 |
| `findings/whole-plan-astra-review.md` | 94802b4c906b347203a7c45e4fd687d3a9814cc2fe2574bd0ae6ce8d27d7ac5b |
| `open-questions.md` | ea75c39b48227c5671d7b1979f05e947362e8174a59c802590b5715ab60fb212 |
| `phases/P1/P1.1.md` | 238d75f26d1f881d6918b6cf520250e047d68e050e215090d2fa619d9573da3c |
| `phases/P1/P1.2.md` | 36bb11309c77cddf48dd2a5ad644e9aa12237563b82e0d6848cf10f1e8dd48a9 |
| `phases/P1/P1.3.md` | c2c7b835993fbbc5ab97c1056a4833e5d9784b753040050852def3453535d71d |
| `phases/P1/P1.4.md` | 11fa0e02e02a325de78842abded03525838651ee218dc873df08f26bcb127fbb |
| `phases/P1/P1.md` | 8c9f6187ee976cca55e44f4d7fe406787b2710a735f07b83e9d4695612501418 |
| `phases/P10/P10.1.md` | 430eab1d70a403a8cb5137c14cf3702f8a58aa40d6875b78c5a95659d8043f2e |
| `phases/P10/P10.2.md` | 0e0f51fd66b34e0948ae8f84666ac2443f6d0a3fb723cb4541ae72e6bbf0611c |
| `phases/P10/P10.3.md` | 1ec070306c30ebada098c9a059c9d2ff6f2f5e2c8c7bf981c2c999af91ad461f |
| `phases/P10/P10.4.md` | 3666710febacaca64ea84bc40b96fab5487ff3c562ac0ef921605fdea50745a8 |
| `phases/P10/P10.5.md` | 9020ae0afcc511c93c865319987d8157ed65669c1c6dc637d533dc0d616db45e |
| `phases/P10/P10.6.md` | be459bbda20b714fa417348dc56b477c59275fbcffe5ff191baa32724296db90 |
| `phases/P10/P10.7.md` | 2a119835b2b5ab91cdbef7f4e8c36faa22d40a685051d3698ae5c1c2006453ae |
| `phases/P10/P10.8.md` | 44bedb80840788c58cf57ed5ba2a22a05a010f61328136688837ba68ba4eab7d |
| `phases/P10/P10.9.md` | 43f75295d3a2004c90a03cfd820be4185eb1c6ab7e8d436cac5c0befa60b6681 |
| `phases/P10/P10.md` | 27c09ad50bed6ded5c49b596d82db6d49ab1689703b7bf11d3c51a9ae5fc5097 |
| `phases/P11/P11.1.md` | 794dfee9e85a11f9fcb17634c6a0aed5c12826c3bf26f2e0bf6502fef21ae738 |
| `phases/P11/P11.2.md` | 6e58e39559f60ebc783f517bb1e7820670951e9234eb8fa086e52f4f27561698 |
| `phases/P11/P11.3.md` | 1282238b3557482de31eee65e3dbf254b9f1ba463c30d31184a0c3fbbeaf03ba |
| `phases/P11/P11.4.md` | f85eb5a8f10daef56ee3a97d9bd33cceebe4cd94778ae45cc8706b9faa9a3d95 |
| `phases/P11/P11.5.md` | b7a09e32c98fac7897206b0d6709eb9c5dfd41a8974d0ffa282b06fc4acf9287 |
| `phases/P11/P11.6.md` | 4d3db2712d1938191270270c0b09276d3207b6bf75e314ea01d018e5cc53a5bf |
| `phases/P11/P11.7.md` | fe590af9f12a1b3d7fe28ddee814a843e168d3cb075a7f5e097a513b3d39a3cc |
| `phases/P11/P11.md` | a05100b3f35d261b4d3aebc2af62f6528f9a63b61c33bfa0c628c17ab477f40d |
| `phases/P2/P2.1.md` | 75830a58b0d1811015d2515640202bb0295565c9bc21b46da78fcfc95d57b428 |
| `phases/P2/P2.2.md` | 535fd9687f53eb8ee17b858e40226d8a3868310a58780f634a68f022309dcb4e |
| `phases/P2/P2.3.md` | 97c4e3ed6ef8067ccb09eeb1cc37441b4ab9f0ea298b0ab72e68aad0b615309f |
| `phases/P2/P2.4.md` | 97ba3d3dd68671f49624d74053e23c41b60b2bbcdcc01083d406f9b4b51e6ea5 |
| `phases/P2/P2.md` | 395ee3900d98501338c71670734c8501f122d349df62588768b7f9bc221bf35f |
| `phases/P3/P3.1.md` | 83fe7fd78824a0a5d3d823ec4d905bf244d0ae617c7a153cb83573a1e50960c6 |
| `phases/P3/P3.2.md` | f7fdbe9b9e199444d6c7fe3d80c1775a7a725620fc78d38941c492c05b4d3f92 |
| `phases/P3/P3.3.md` | 8735318de9f71c70b334d5bea1a5ae0a014ac4947c4896d09c73d7435990f8db |
| `phases/P3/P3.4.md` | 110094dd528c3b655abd28a347be3ed8479130773ef4dac9f2f94acd861485fe |
| `phases/P3/P3.5.md` | 572d36797a974d98005edd2fe7daedf15381ba9c5cd6286138a24ec635f424f8 |
| `phases/P3/P3.6.md` | 5fe60fc22c5b15b169a5b6a3aeeea9fa46fbdcec8de8411abe500e1f39424025 |
| `phases/P3/P3.7.md` | e2062d5ac76c0da7c0a357421cb32b300139731d50f104788688382a04e4699d |
| `phases/P3/P3.md` | eda83c240c8ddd1f87c4928105e59a70f27a6f59e46419211c9516e082bdc262 |
| `phases/P4/P4.1.md` | bc3bfebcab4feed496d2e815ce1d355c25fb11bec6303b9908123ba30c897bb0 |
| `phases/P4/P4.2.md` | 938923b14e6db56e51f80489c8b21b5a2e7004670b5e381607c7c80ee7eed492 |
| `phases/P4/P4.3.md` | 008bb82892dbc63001096a8ebb750fd5b8747e6062124e8e06e38d0c6e479f6a |
| `phases/P4/P4.4.md` | ecc5eae1f4c85c5b9afdc11c5b1d05f69ffcf05f7bb62ca75c4ac4a1c147baed |
| `phases/P4/P4.5.md` | 7739d0df3557fc882101e1339a356227f1e502959adf9b798ee3a72ac720dba8 |
| `phases/P4/P4.6.md` | 1950310f4ccd9892fdac85c3b85722c33bb35e10e7990de55bc35450e4eb818e |
| `phases/P4/P4.7.md` | 29e6a498acffa03033cc203ab86af457cc77ac55b24924fbb387f12aa372b35b |
| `phases/P4/P4.8.md` | f84e3e9e4939be82b2f35f9e5f890e2419ad1939e49000dac1060b6ce7141769 |
| `phases/P4/P4.md` | c50765731fb64b75f026335aa613ccdcdb7231056116b4e61c16aae44cbad1f0 |
| `phases/P5/P5.1.md` | ae548e36e3a28eb8b802db453262329e985d9f362bdf9169a507003278256e34 |
| `phases/P5/P5.2.md` | a0b4b45c777376329bf674ba91540d2b8b0700fa4d2d261e5ad63880671fe41e |
| `phases/P5/P5.3.md` | 6867806ef177e03c7aae823de414551a009c92c46bcce5352c47e1bdfd403795 |
| `phases/P5/P5.4.md` | 608ee2c875a677583040277a9902d3b67502b475071f6e81b6e6aedded9ef146 |
| `phases/P5/P5.5.md` | 453824bff9f6ff4c091ac9b9e8e6f716f24e8402365dc919de00aec316f8ff71 |
| `phases/P5/P5.6.md` | b110acacef6c6e6b4302fdfa917838b172893acd9bc9355681a8d3b2dc301e35 |
| `phases/P5/P5.md` | c81bbdd13b71204284d446aeb6e31e9f417146a875f01617cf5ec5133d6d9d45 |
| `phases/P6/P6.1.md` | b8e74d592f307825bcf86679cacb756eeb6b0cf612e1df201dca48df59105d2e |
| `phases/P6/P6.2.md` | d78560542eac026bbf72b7e03ff8ef10f6d301e5641bd3996ca4ff431c2142d1 |
| `phases/P6/P6.3.md` | ae4378e7c5dd81e3fec72de4483b5b433416ec3c1b2a732eac5e1f868a706522 |
| `phases/P6/P6.4.md` | b7ae58b4d59182c7fef9ec4ca17167d3b2b102d227971dd05cb3949f13746115 |
| `phases/P6/P6.5.md` | 97385b8dd7903e9d1b723ecb78de53c06818fc1671aa6134e2d819976e6be3b3 |
| `phases/P6/P6.6.md` | 18bfda23310a989894ed4233f90db2615564bcde05c312321bf7d8e62512e4ff |
| `phases/P6/P6.7.md` | 65ccaabeee6da73644efb3a176783a9e6cfe4c6a479b41aa78101b9f627bed6a |
| `phases/P6/P6.8.md` | 4808c683072a1caf546d92c99417e45cd1ad1ea1cc1ea27245f18d6423de70d8 |
| `phases/P6/P6.md` | efa2f8d145c16d5dd33f8a98b8bea212b42bf3291d68d6af3e290d0b31e46fca |
| `phases/P7/P7.1.md` | 80b982ccc0735f20dbe2d11c5a0657c356248769e310cf7b318b1819ab66ce7e |
| `phases/P7/P7.2.md` | 1a909072934ae9449eb102c0bc758e3fe093f3952b7793fb9dd516431557d8f8 |
| `phases/P7/P7.3.md` | 6a04bc15451bdb2cc7e6cd5b5cd1d3a5a566eb9ee2046b5b5dca18de83762375 |
| `phases/P7/P7.4.md` | e11e137bb11cea2339890c7d94c76506b90b4412b9f67e3b1732d608fc009b13 |
| `phases/P7/P7.5.md` | dd2a573adfdda896818ba6f5a2dae194a4407531638d4da8559ebdd8bc0d6103 |
| `phases/P7/P7.6.md` | b5feae3eba5ee51d3f1f9b80812646413cbf6f8d1a80461a61d88c41cc6313a0 |
| `phases/P7/P7.7.md` | e488f4abf19961b08e21d7d8b6b403405f97cc60b71519a48c83508a8de3ee8d |
| `phases/P7/P7.8.md` | ba52375138e5ba536d647a7cdf43937ef11298b4d4aa97cfa8a4093131169f8d |
| `phases/P7/P7.9.md` | a7c9960156f7800e70c28585ec5ea77c781b41e140b5b6c8f3f8c8a2833f04c1 |
| `phases/P7/P7.md` | 2da96803a1cca5b21ceb18958b51a1a53773593cf26a23ce8c699438903613d5 |
| `phases/P8/P8.1.md` | ba5fac92cfc5420376ad6b7044dd987973aec29b91ab382fee62284244c0151f |
| `phases/P8/P8.2.md` | 18909044e5d3234480a4bae08e950c02562a0422dee37fca0c0f01b81f9034fb |
| `phases/P8/P8.3.md` | 010e6166076f3cab8425d533ba06e8e067c50593c68607cebd32a5bc6f311b0e |
| `phases/P8/P8.4.md` | 91967e0432eb5d921a5f3688e977aff29b7c9f65147c77357352a3f30ce65257 |
| `phases/P8/P8.5.md` | 5cbc88adbaf7d49f5a516d4c9214673b9123daa7789fe6ed5925c09c4056eec2 |
| `phases/P8/P8.6.md` | 57f66238ac62c3e3e38275d1dbec5bf17a5b4da4ce5c83a5fbc653a3bf067db7 |
| `phases/P8/P8.7.md` | 670863303d0ce78451e6f5796d5d639c8a8882ba8ce6abaff1c61415d388c127 |
| `phases/P8/P8.md` | dd4609e9e73414ed7f40d39aab20a8ea1b74788c630d5aa26775ef9ad2b2b22f |
| `phases/P9/P9.1.md` | ceb95f40cc3991a76fc65e89a63cbc39764c3ed1eafbef739ed0fcf3dc7da50d |
| `phases/P9/P9.2.md` | a886cca9342109927b38bb00781953b7e4be6afa7ccf5eea734b27520b1509cc |
| `phases/P9/P9.3.md` | 374c5e29eb424b5e9a6a1f517d4baa14e2f7a4bebf94e7a8cb718ee486662939 |
| `phases/P9/P9.4.md` | ee074137076a81c669afaddc9d41f7f34cd6513d9b21b69a5396381514b7537b |
| `phases/P9/P9.5.md` | 8fd49b1a430255a8c747c674cf980515ba494fb8f8756a98ff58958f7721d5fe |
| `phases/P9/P9.6.md` | fbc3186f840012d906c81dba72a87a8d7bf48169d2b941d6c1489f4ecefdab77 |
| `phases/P9/P9.7.md` | be3207274a4c7a3ee43408c33eee70ef903c32491d31273a7f566b276ff40b43 |
| `phases/P9/P9.8.md` | 68fa0383610479490b9e45cad12078e8f78751ef5f9a5867bb10ffcd480d74b4 |
| `phases/P9/P9.9.md` | 760a1e8d729f277ccd69b14633512ce06261a2be82a3c6aab2b8536f175a3999 |
| `phases/P9/P9.md` | b26325f2a95b53f7cd6b37214b2c93f8200b2dc918a06e16b4f29338ebbfd89a |
| `plan.md` | 8f3d6e8713f5827b105391836593885dd77d0e5576a17e604fcdc7d78985ba36 |
| `research/00-sources.md` | 0cec5f58a1bcab6f6e0bfa32b3b09269b50c7cd7d7a030b1a78bdca6a58189f3 |
| `research/01-traceability.md` | 818d0faef9074b7c38c97f1b6fc2c09641bbe0928f272552d2d435d0515cde93 |
| `research/01a-acceptance-index.md` | 49e7888b52214d2a64592a3748561ce128914ad136ad8d582f6c97fbb51d4360 |
| `research/01b-acceptance-source-supplement.md` | dbcffc4197212d9fd5dca30b0f03a68b1967cb63c7331cef99c3d6bf817be2d2 |
| `research/02-baseline.md` | 0179e0e638ab4b5b98b6cea549334b61339654d38ec644461b7e0bd428e0e90a |
| `research/03-gates.md` | b9a2ad93007d76152c25dbf2f4c6b908c2182482900c1f7f144c6ffb5857db3f |
| `research/04-architecture.md` | 2460dfa9a457b23da029b73a254c2ef7a44c47dd5537f2a95e7d0c98c77a1ed5 |
| `research/05-integrated-design.md` | 6aca22518b510e17d108e0878baaa512b152596b56d75b185adc73494c117e9e |
| `research/06-azguard-patterns.md` | 33e6a1cc959aa5070134061eafe36f90ba701c2132d98dea56a3324370609c1b |
| `research/07-open-source-patterns.md` | 1c74909638574cbf9fc6179e697a8be114df3c4ba07305587559191b6481a5f0 |
| `research/08-execution-batches.md` | 9bd1b1b0caad5e4384842c6bbc0aef8e91e9da470e46d173ffa7fcc3ee0ee0c0 |
| `research/09-read-budget-policy.json` | 8679753e218011ddf1331e93e7d055fce18cce83324a4c60661c0cc5e32bd80f |
| `research/P1-design-rag.md` | ab10a68d233c02c636e0f72441e6bc40aec53e9862ca65f614c241985d885860 |
| `research/P10-design-rag.md` | 22580a49bf9650f93f3cf09172bde07ef6ecc191e447ae13920607a3c4c8e65a |
| `research/P10-effective-design.md` | 3067c20f88fa2e8d00f729fd1c3cf9f01b3e4aeddc314afa1d26966f760a30ac |
| `research/P11-design-rag.md` | fcc73a3a6562f37de7f0d5d2296baa1fb136bb9bd96f99227f820af0c36b3b34 |
| `research/P11-reconciliation-design.md` | 32cce51ebff33a8980f54d1864be6a0e5c15adb7a18f8974f7f17d72a5d310af |
| `research/P2-contract-design.md` | 7eefb1385378065d4e039d81353940516f8d9df413e8274a2dfba8fe3f07ef9e |
| `research/P2-design-rag.md` | b4715c31e66df2de63d07db2ca6539b994089c7c3f1d7b68d4b9b9a9a6251626 |
| `research/P3-adapter-design.md` | 05dc04b765f133b4779a173c534e15d0ce6eb7ea020f77b5987cf5e391bf5525 |
| `research/P3-design-rag.md` | ccbd75f3a229b2032c7bbd603dc8c2cc745907ee2614d68b4f169d1ee510f40f |
| `research/P4-design-rag.md` | 87ad139479bd4cbd96aef681b5f7ded723206e6ec4735e0114ed3d0555876090 |
| `research/P4-extension-design.md` | 957873c6995cb3811983217a67f99d5068416f4f46da8a1653650cbc595d143a |
| `research/P5-design-rag.md` | a6f1c22c688d24b427f2c0f64f2755c154cbba969f7b955dbbc381cf9bc068cc |
| `research/P5-wakeup-design.md` | ac703e502544138dd6acebaf3778fe20968186b0aacec14049ed16a4f337f3fc |
| `research/P6-data-design.md` | 149932325e6a7c48495a81e56e3ae2054f105fe9dde3147a83955f831d3a02cb |
| `research/P6-design-rag.md` | 9dfeee763636a232dd8ad3773e096398c2b56d1c4fd9216a7ecad3efa298cec2 |
| `research/P7-design-rag.md` | 0e9e3b02190e21405acba959670fce23e9921f0e0508d3185c1086100676a7c0 |
| `research/P7-utility-design.md` | e8030ad4a090f1bb13f6def331f8858606fe2716754d5e8e9b12c8ef2c4cbc43 |
| `research/P8-design-rag.md` | d1a2cbfe4dee9fe0c48b013cc746b195a43a68bf6d4f17f11ca024245d2796e3 |
| `research/P8-tool-design.md` | ded33e03fc260e632c06a4fec0e66e404cb29e6383e921e24c679ffadd8802ca |
| `research/P9-cohort-design.md` | 87828181adc3de1af50a098e7882faa5204a1d7e610466b36b242c9b2aa85aeb |
| `research/P9-design-rag.md` | 1d731248f60816445d947d9018f92344e1fc83487a04c372d81e886a130701b1 |
| `roadmap.md` | 5093dc5695d7866473762f90a4c5c961a4f659c24a6411f360aa18b8ba0f8de1 |
| `scripts/task-check.py` | f0bb52405c15f6529491264a3ee4b152410d2d4266ccb0b8a5f9a9201d6a6ed3 |
| `scripts/test-design-guards.py` | 8cfaa13ed785af4c5370483e3e7c24ef5ce1debb5028bb325f4bc45d9ff5cd1b |
| `scripts/validate-p1.py` | c38b23e475cf2fdc8fe4134cd04d42cf627e9c23c6a2d28384b0b5123f2adee8 |
| `scripts/validate-p10.py` | 557ee231bd5e786551a24b638224dcd1c494870fea2cd83e61677e790c596c49 |
| `scripts/validate-p11.py` | 3ad17b7568ca7aef50356e5b0fc4baf7cd03418a9289ccb4f83ed478a5b718c2 |
| `scripts/validate-p2.py` | ffe778784b0c34a833ecc1819eadf2803d5db77fde333aa6a6fe077adc83d2e3 |
| `scripts/validate-p3.py` | 4ca8724ac8c16acf097740d3eaa2bb276ca50aff94433a9abd4eedd6a8641787 |
| `scripts/validate-p4.py` | 724f046aedcf91e322436a1f7845187ae7e36c8e3c2919d072b7f8c41edccc27 |
| `scripts/validate-p5.py` | 64b5aeb9d66523d9180f8ae2ac5287df341e4e1836871a7991441e67ff9d26b9 |
| `scripts/validate-p6.py` | 7dff092df1617a4cd96e4dfff47e2677fdedda201673d7f9d989ce439ff94d70 |
| `scripts/validate-p7.py` | d66e495e378db35096eb980a21c611833d4bed4c7b30bfde34ba6bfeb76f81fb |
| `scripts/validate-p8.py` | 2b01d55aeecd7cd9932e1c7b06419b81453b856a02d231ebabb56f8ba8730a1c |
| `scripts/validate-p9.py` | 352752c91efc8af60aea8ab566b65c671f8424b4d6948107badab00514cf95d8 |

## D19 execution policy rebind

Owner-approved read-budget advisory scope and P1.4 producer annotation bookkeeping only. No new independent whole-plan review or product acceptance claimed. Original review evidence retained; all stable inputs enumerated and digest-bound.
