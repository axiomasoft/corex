<!-- plan-projection
projection: decision-current
projection_version: v1
source_scope: decisions/
through: 2026-09-12/D10
inputs_sha256: 86871a08ac20a109642eb5d6ec89b76c5e5d0afe332f66c3d0c26e27da0e36a7
generated_by: task plan-views decisions
-->

# Текущие решения

> Не править руками: это генерируемый вид.

| ID | Date | Status | Items | Решение |
|:--|:--|:--|:--|:--|
| D1 | 2026-09-12 | accepted | P1.1, P1.2, P2.1, P3.1, P3.2, P3.3 | Создать новый successor только для трёх подтверждённых test/CI tails и трёх decision-ready owner-debt slices. НЕ менять прошлые статусы/спеки/журнал/roadmap архивного COREX-CORE. Не переносить закрытые items; новые задачи описывают только недоставленный delta. Вариант «переисполнить P1–P3» отвергнут: архив уже терминален. Вариант «включить весь roadmap» отвергнут: продуктовые/год-2 фичи не являются необходимым post-P3 hardening. |
| D2 | 2026-09-12 | accepted | P1.1, P1.2, P2.1, P3.1, P3.2, P3.3 | Этот carrier — plan-owned, minimized execution input для решений P3, не замена SSOT Brain docs. Design candidate пока не committed и не execution-qualified; фиксация portable snapshot проверяется при owner-approved design finish. При изменении source hash получить новые extracts через следующий decision record; не обновлять D2 молча. Итоги ниже — наблюдения/предложения, НЕ owner approval. Ни один public-contract/schema/profile/release gate не снят. |
| D10 | 2026-09-12 | accepted | — | Владелец запустил ровно предложенный design-finish target: `$ task:plan-design 2026.09.12-№1-COREX-SUCCESSOR finish`. Запрос на завершение включает необходимый обратимый авторинг трёх зарегистрированных v2 carriers: `brief/00-brief.md`, `roadmap.md` и generated `open-questions.md`. Принять это как ограниченное продолжение разрешённого design scope; не требовать повторного подтверждения того же следующего шага. Отдельное сообщение с буквальным «разрешаю три файла» не поступало. Эта запись фиксирует основание и пределы исполнения пользовательского запроса; не приписывает владельцу принятие architecture/release/runtime outcomes. Исходный proposal D9 сохраняется; его status становится superseded и добавляется взаимная ссылка Superseded By. |
