# Исключение: DATA-004 — cross-package FK в cloud-профиле auth

**Нарушаемая статья.** DATA-004

**Затронутые пути.**
`packages/access/database/migrations/tenant-cloud/2026_07_26_200004_add_aut_cross_package_fks.php`;
столбцы `aut_role_assignments.workspace_id → wsp_workspaces.id`,
`aut_role_assignments.granted_by → users.id`.

**Владелец.** `corex/access`

**Причина.** Таблицы `wsp_workspaces` и `users` принадлежат `corex/tenancy` и отсутствуют в
boxed-профиле; `corex/access` должен оставаться функциональным без tenancy в boxed-установке.

**Компенсирующий контроль.** Отдельный publish-тег `corex-auth-migrations-tenant-cloud`: cloud-профиль
ставит FK отдельной миграцией; boxed публикует только каталог `tenant/` без этих ограничений.

**Условие снятия.** Постоянное по решению D102 — снятие не планируется без нового решения
владельца данных и миграции схемы.
