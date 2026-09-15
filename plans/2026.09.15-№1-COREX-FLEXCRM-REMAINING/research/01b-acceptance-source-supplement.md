# Дополнение к историческому acceptance index

AWR-10: исходные 365 parent IDs, R mappings и Text SHA256 из 01a сохраняются. Это
source-text supplement, не изменение snapshots/исторических критериев и не новые R.
P1.2 обязан разложить **полный** clause text ниже; P11 проверяет те же selected atoms.

## B36: полные многострочные критерии

Источник: `brief/sources/b36.md`, SHA256 `d69535f45fba7097e0c307e173c2ec797638e54d5114e5ef37c02013e3da0d5f`, §8.5, L686–717.
Каждый блок начинается исходным AC label; переносы и продолжения взяты из snapshot.

- AC-1. Миграция создаёт все `att_*`-таблицы §2 с точными CHECK/FK ON DELETE/индексами/COMMENT;
  `tsk_`/`cal_`/`crm_` DDL канона не меняется (§1 «изменения: НЕТ»).
- AC-2. Старт дня создаёт `att_day_sessions` со `status='open'` через `StateMachine::fire`, не
  прямой INSERT-без-guard'а (safety-инвариант §8.4 п.1).
- AC-3. Стоп без подтверждения (`pending_confirmation`) не входит в `att_timesheet_daily`
  (safety-инвариант §8.4 п.6).
- AC-4. Wakeup-sweep переводит сессию `open` дольше `att.auto_close_threshold_hours=16`
  (Q78) в `auto_closed` и создаёт `att_correction_requests` implicit-пометкой, не теряя
  открытую сессию молча (поток 2).
- AC-5. Правка вне grace-окна (`att.correction_grace_hours=72`) отклоняется без
  `CorrectionRequestService::request` (`RetroactiveEditRequiresCorrectionException`, §4).
- AC-6. Вставка второго `approved`-отсутствия того же сотрудника с пересекающимся диапазоном
  отклоняется constraint violation (safety-инвариант §8.4 п.4).
- AC-7. Табель — идемпотентный upsert по `(workspace_id, user_id, day)`, повторный ночной прогон
  не создаёт дублей (`UNIQUE NULLS NOT DISTINCT`, §2).
- AC-8. `exportT13` не хранится как сущность — вычисляется по запросу из `att_timesheet_daily`
  (вход 6).
- AC-9. `geo_control_enabled` — `false` на новом графике по умолчанию (safety-инвариант §8.4 п.2).
- AC-10. Переключение графика (`assignSchedule`) не удаляет историю прежних назначений —
  `effective_to` проставляется, строка не удаляется (поток 3).
- AC-11. Tenant-isolation — 0 кросс-account утечек по всем семи `att_*` таблицам (safety-инвариант
  §8.4 п.5, сверка линза P7.4).
- AC-12. Reject correction/absence не мутирует исходные данные `att_day_sessions`/факт отсутствия
  — только `status='rejected'` на запросе.
- AC-13. Реестр событий `att.*` — 0 коллизий с `tsk.*`/`cal.*`/`crm.*`/`soc.*`/`mkg.*`/`wfl.*`/
  `cst.*`/`ai.*`/control-plane неймспейсами (сверено §4.2/S3 §2 delta).
- AC-14. Approval-действия (`confirm`/`approve`) требуют permission key, self-authorization
  отклоняется (`att.day_session.confirm`/`att.correction.approve`/`att.absence.approve`, §3.13).
- AC-15. Guard control_mode переходов читает АКТУАЛЬНОЕ назначение графика через
  `att_schedule_assignments (effective_to IS NULL)`, не кэш/устаревшее значение (§3.13).
- AC-16. Мониторинг-функции (скриншоты/кейлог) отсутствуют в кодовой базе v1 — не флаг-выключены,
  а не реализованы (safety-инвариант §8.4 п.3).

## B34 AC-19: происхождение исторической строки

Строка index с parent `B34 AC-19`/L212 — incidental reference на канонический B11 AC-19
в invitation sequence, а не новый объявленный AC-19 раздела B34 §8.5. Parent остаётся
в 365 для сохранения lineage; mapping отмечает `source-reference`, ссылается на B11 AC-19
и B34 invitation sequence L205–212, не объявляет его новым независимым требованием.

Полная последовательность соответствующего source span:

1. Приглашение создаётся в tenant-БД (`wsp_invitations`, канон AC-19, без изменений);
   письмо ведёт на acceptance-роут central (`id.flex.ru/invite/{token}`), НЕ на tenant-приложение.
2. IdP аутентифицирует (или регистрирует) приглашённого → `root_identities` create/find →
   **root пишет `root_identity_accounts`** (status=active, sync_status=pending) → событие
   `IdentityMembershipGranted` → `IdentitySyncer::syncToAccount()` создаёт users-строку →
   редирект на поддомен CRM, где tenant-приложение валидирует токен приглашения, помечает
   `wsp_invitations.accepted` и создаёт `wsp_workspace_members` (+роль из role_id — канон
   AC-19). Лимит `users_max` — на акцепте (канон B-11 §7.2).

Source snapshot SHA256 `69605804c77b821e739c9e99099b335d216734366f97de28f2d81a0b9fc3bda6`.
Original index Text SHA256 остаётся hash исторической третьей ячейки. Отдельный hash
полного source span связывается в P1 mapping Correction/Required evidence; hash fragment
не заменяет доказательство покрытия продолжений. Все app/ops owners и исходные thresholds
сохраняются; один receipt может покрыть эквивалентные atoms лишь при явном mapping.
