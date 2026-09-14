### 7.3 Safety-инварианты

1. **Изоляция stancl**: импорт `Stancl\*` разрешён только в corex/tenancy — PHPStan-правило (custom rule) + arch-тест Pest в CI.
2. **Нет FK через границу БД**: тенантские таблицы не ссылаются на root и наоборот; связь только по uuid-значениям (users.id = identity id).
3. **Один app-user на ячейку**; приложение никогда не коннектится к golden-шаблону; DDL к тенантским БД — только миграционный runner и B-15-воркер.
4. **Transaction pooling гигиена** (конвенция ядра, CI-линт): `SET LOCAL` only, без сессионных advisory locks (только `pg_advisory_xact_lock`), без temp tables, LISTEN/NOTIFY → Redis.
5. **Сессии** — database-driver в БД аккаунта (физическая изоляция) + `scope_sessions` middleware; **очереди** — central Redis, non-prefixed connection, tenant-контекст в payload (R-11 §2.4).
6. Пароли в cloud — только в root (у тенантских users `password_hash` NULL); tenant key в URL — slug, не uuid.
7. Кластеры, бэкапы, экспорты, ClickHouse — РФ-ЦОД (ФЗ-152); purge-протокол D13: экспорт до DROP, бэкап-retention ≤30 дней в ToS.
8. Компилятор матрицы транзакционен: AzGuard-ключи и scope-карта не расходятся (бамп cache-версии последним шагом).
9. Ключ тенанта не енумерируем (UUIDv7: случайная часть 62 бита); pending-болванки не резолвятся по host (slug NULL).
10. Impersonation: грант одноразовый по политике, TTL ≤ 1ч, деструктивные операции запрещены (§5.9 п.3).
11. **Н1 — единственный ключ связки root↔tenant**: `root_identities.id` (UUIDv7); телефон/email/ФИО ключом связки не являются нигде (аддитивная дельта B-34 §3.2/§8.4, канонизация — D23/Q64, FLEX-2 P6.2).
12. **Н2 — 🔴 запрет авто-merge/авто-привязки identity**: identity НЕ связываются автоматически по совпадению телефона/email ни в синке (IdentitySyncer), ни в приглашениях, ни в импорте; слияние учёток — только явная user-driven операция на IdP (v1.1+) (B-34 §3.2/§8.4, D23/Q64).
13. **Н3 — `root_identities.phone` без unique-индекса**: контактный атрибут, не ключ связки (B-34 §3.2, D23/Q64).
14. **Н4 — email приглашения = транспорт, не ключ связки**: акцепт приглашения (`wsp_invitations.email`) связывает по `identity_id`, аутентифицировавшемуся на IdP и предъявившему токен, независимо от того, на какой email пришло письмо (B-34 §3.2/§8.4, D23/Q64).

---
