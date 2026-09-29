# Исключение: STR-005 — `tenancy/src/Membership/Internal`

**Нарушаемая статья.** STR-005

**Затронутые пути.** `packages/tenancy/src/Membership/Internal/**` (39 файлов — весь bounded
context Membership оформлен как `Internal/` пакета)

**Владелец.** `axiomasoft/corex-tenancy`

**Причина.** Membership вырос внутри пакета tenancy до появления правила «без внутреннего
`Internal/` внутри bounded context»; измеренный долг — `00-baseline-metrics.md` (`RAG:—`).

**Компенсирующий контроль.** Межпакетная граница по-прежнему проверяется `composer
package-graph-gate` → R2; реструктуризация зафиксирована в roadmap P9/P16.

**Условие снятия.** Закрытие P9 (перенос/группировка Membership вне паттерна пакетного
`Internal/`).
