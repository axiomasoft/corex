# PLAN3 P03 — design audit после D58, с исправлениями D59

**Вердикт: GREEN.** Все замечания закрыты в этой сессии; независимый reviewer и полный replay GREEN.
**Проверенный fingerprint:** `92a4f03454049c9e98fa4f2564334829a2aac9aa9b8f79a94ba09c806bbb1d07`.
**Исходный fingerprint:** `3ef396e88c2bec8e0950b927e6b185937cd3f2f5b57b3dc3b4cb6551f1a68bdc`.
**Reviewer/repair owner:** codex/gpt-6-astra/xhigh (provider-attested [route](2026-09-29-p03-design-recheck/route.json)).
Владелец дополнительно поручил исправить замечания в этой сессии. Product items P03.1/P03.2 не исполнялись.

## Найдено и исправлено

1. **B1 — Major:** `p03-transfer.py cmd_apply` менял namespace вызывающего класса, но не добавлял импорт
   остающегося на месте соседа. Полный Rector replay воспроизвёл fatal загрузки `CompiledModuleRegistry`
   (короткий `CompiledModule` разрешался в новый Internal namespace); Golden не стартовал.
   Исправление: P02 implicit-import pass с полной исходной symbol map, 12 imports. Регрессионная
   фикстура доказывает падение до переноса ссылки и рабочее создание исходного sibling после.
   [Исходный вывод](2026-09-29-p03-design-recheck/full-initial/golden.log).
2. **B2 — Major:** проверочные subjects не полностью проецировались по карте: package-relative NEON paths,
   короткие пути CORE_ZONE_DUMP/FOUNDATION_SUPPORT, packagePath и tenancy test source path; пять adapter hashes
   становились stale после механического rename/Pint. PHPStan и шесть root assertions падали.
   Исправление: точная проекция путей и пяти прежних cross-zone edges; AST-equivalence перед обновлением hash.
   Удалена только ссылка phpunit на пустой untracked Views; исправлен остаток P02 `attributes` в тесте
   агностичности. Два последних дефекта обнаружены в чистом checkout и скрывались остатками старого worktree.
   Нормы R-Z*, counts/ceilings, тесты и negative controls сохраняются.
   [PHPStan RED](2026-09-29-p03-design-recheck/full-initial/analyse.log),
   [root RED](2026-09-29-p03-design-recheck/full-fixed1/root-test.log),
   [AST negative](2026-09-29-p03-design-recheck/adapter-negative.log).
3. **B3 — Major:** Validation P03.2 вызывала старый P01 generator `--check`, который уже stale на исходном
   HEAD после P02 (695 frozen rules, 16 реконструируемых). Его regeneration нарушила бы frozen contract.
   Исправление: `golden-check` сравнивает все immutable поля с HEAD и требует ровно 376 manual additions.
   Отрицательные тесты ловят mutation generated и постороннее manual-правило.
   [RED на preimage](2026-09-29-p03-design-recheck/golden-legacy-preimage.log).
4. **B4 — Minor:** roadmap назначал отдельный обязательный phase audit без `audit-admission/v2`, на маршруте
   слабее Task frontier/xhigh, и отдельное закрытие вопреки D56.5. Исправление: owning close последнего GREEN item;
   adversarial audit — только при принятом candidate/run-bound admission. Execution-sheet P03.1/P03.2 сохранён.

Исходные A1–A5 из [первого аудита](2026-09-29-p03-design.md) перепроверены: восемь regression tests,
обе baseline, alias/composition/kernel-root rules, полный Composer lock refresh без dependency drift проходят.
Все решения и scope corrections закреплены [PLAN3.D59](../plans/2026.09.27-№1-COREX-KERNEL/decisions/D59-p03-full-replay-readiness.md).

## Проверка результата

- [Полный replay](2026-09-29-p03-design-recheck/final-replay.json) и
  [stack](2026-09-29-p03-design-recheck/final-stack/checks.json) — точные команды, exits и логи.
- Replay стартует с чистого detached HEAD `556c5478f428290a7b60959388783f860f1f29b0`; исходное дерево пользователя не переносится.
  После воспроизведения B3 сохранён failed carrier и продолжены проверки того же transfer candidate с исправленным
  golden-check; повторный перенос для изменения validation-only не выполнялся.
- Package vendors — отдельные копии локального установленного dependency set, все symlinks внутри scratch,
  autoload каждого из 21 roots пересобран; [setup evidence](2026-09-29-p03-design-recheck/package-setup.json).
- [Итоги тестов инструмента](2026-09-29-p03-design-recheck/all-tool-tests.log): 8 layout + 3 transfer regressions.
- [Независимый review](2026-09-29-p03-design-recheck/independent-review.json): Cursor grok-4.7/high, read-only,
  свежий контекст; проверяет исправления автора, не исполняет продукт.

**Финальное сведение root:** 18/18 проверок полного stack и все дополнительные replay gates — exit 0.
Root SQLite: 822 + 10; PostgreSQL: 240; Golden: 4; standalone package suites: 21; mutation: 34/34.
PHPStan, dependency analysis, strict ownership, rename-completeness, API/contract/naming/docs gates GREEN.
Layout: 1112 файлов, baseline 159/159, 0 new/stale. Frozen Golden unchanged. Test tools: 11 passed.
Независимый reviewer завершился GREEN; оставшиеся на момент его ответа проверки затем завершены root,
без изменения проверенного кода. AST negative повторён с доверенным HEAD hash: изменение return-value
и namespace отклонено именно сравнением AST; fixture не изменился. Предыдущий trust-guard negative сохранён отдельно.

## Границы

Design replay не закрывает P03.1/P03.2. В scratch использована база P02; гейт layout запускается из design artifact
с тем же конфигом и baseline, wiring composer/CI/docs устанавливается P03.1. Никакие dependency versions,
карта 278/68, baseline 436/159 или frozen Golden не менялись. Hosted CI и push остаются у владельца по D47.
После GREEN текущего design fingerprint следующий шаг — P03.1, затем P03.2 по execution-sheet 8–9.
