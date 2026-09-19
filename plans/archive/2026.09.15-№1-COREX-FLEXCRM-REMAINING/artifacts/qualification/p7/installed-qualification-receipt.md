# P7 installed portable utility qualification

**Schema:** installed-qualification/1
**Profile:** portable-utility-cohort/1 local source qualification only.

`verify-installed.py` archives the current `corex/{core,modules,recurrence,tasks,tasks-workflow,intl}` cohort from the worktree, then installs two independent Laravel 12 consumers through immutable ZIP distributions. Consumer A uses `composer update`, consumer B replays the same lock with `composer install`. The probe exercises equipment-maintenance and community-timetable recurrence profiles and verifies the public utility contracts load only from installed vendor paths.

| Profile | Actual result | Boundary |
|---|---|---|
| recurrence-only consumers | PASS: maintenance and timetable profiles match REC-12 | no task/calendar/model/database imports |
| locale/money/reference ports | PASS: intl contracts autoload from installed vendor | no eager network or dataset download |
| task planning/materialization ports | PASS: tasks and tasks-workflow contracts present | no tenancy/workflow engine bundled by default |
| optional intl-search bridge | PASS: qualified separately in utility-optional receipt | disabled-by-default, not part of base cohort lock |

Evidence: `evidence/installed.json`.
Lock: `fixtures/utility-cohort.lock`.

**Pending owners:** P9 hosted/released cohort matrix; P10 vendor/live timing and GEO-08 full corpus; P11 independently owned consumer pin/reconciliation; Q-P2-052 external gate before production address-driver adoption; P8 invoke receipt for OPT-07 authority boundary.
