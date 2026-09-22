# Departments — смысл «отдел» у ядра, auth и tenancy

`RAG:✅` P6-Q5: оргструктура — capability, не shared kernel. D34 оставляет
зону Departments в ядре и называет её кандидатом уровня 1. D8 не делает из
двух файлов пакет. Этот item записывает наблюдаемый смысл и условие
`roadmap.md` §7. Зона остаётся в ядре.

## Ядро

`CoreX\Departments\Department` — read-model каталога: идентификатор, workspace,
родитель, `path`, имя, руководитель, порядок. `DatabaseDepartmentRepository`
хранит оргдерево: перенос проверяет workspace и отвергает цикл
(`DepartmentCycleException`). Eloquent-строка `CoreX\Models\Department` — та
же таблица `sys_departments`, с `path` как ltree и soft-delete, чтобы
удалённый отдел выпадал из выборки.

## Auth

`CoreX\Auth\Access\ScopePredicate` читает отдел как границу доступа
`dept` и `dept_tree`. Предикат берёт `users.department_id` и
`sys_departments.path` и решает, какие записи principal видит. Это не
каталог оргструктуры и не владелец таблицы.

## Tenancy

Tenancy сажает тот же каталог в workspace. `users.department_id` ссылается
на `sys_departments`, а `sys_departments.workspace_id` — на `wsp_workspaces`.
`CoreX\Tenancy\Models\User` хранит `department_id`. В тестах тип workspace
бывает строкой `department`: это вид пространства, не узел оргдерева ядра
и не scope-предикат auth.

## Следствие

Три потребителя — ядро, auth и tenancy — опираются на одну таблицу и на три
разных смысла: каталог, граница доступа, размещение в workspace. Условие
возврата на уровень 1 записано в `roadmap.md` §7: второй продукт без
оргдерева либо подтверждённое расхождение смысла «отдел» между auth и CRM.
До этого условия семантика репозитория отделов не меняется.
