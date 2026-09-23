# P7 receipts

## P7.12 — D100 behavior proof

Witnesses consume `CoreOptions::fromArray()` and one `SqlSavedViewRepository`
instance. Product code under `packages/core/src/` was not written by this item.

### TTL and SecretRef

`packages/core/tests/Unit/CoreOptionsTest.php`

- missing `settings` and missing `settings.cache_ttl` resolve to `3600`
- explicit `null` stays `null`
- `0` stays `0`
- `120` stays `120`
- `cache_store: ''` and `cache_ttl: -1` accumulate in one `InvalidConfiguration`
  with both violation strings
- `SecretRef` interpolation, `(string)`, and `var_dump` show `[redacted]`;
  `json_encode` does not contain the plaintext; `serialize` throws `LogicException`

### Dynamic default connection

`packages/core/tests/Views/SqlSavedViewRepositoryTest.php`

- one repository, injected recording `DatabaseManager`, no container rebind
  between `viewsFor` calls
- successive defaults `central` → `tenant` → `central` select those connection
  names
- explicit `views.connection = pinned` stays `pinned` on the same three defaults

### Validation

```
composer --working-dir=packages/core lint:check
exit 0
{"tool":"pint","result":"passed"}
```

```
composer --working-dir=packages/core analyse
exit 0
{"tool":"phpstan","result":"passed","errors":0}
```

```
composer --working-dir=packages/core test
exit 0
{"tool":"pest","result":"passed","tests":93,"passed":93,"assertions":223,"duration_ms":964}
```

```
composer --working-dir=packages/core test:pg
exit 0
{"tool":"pest","result":"passed","tests":37,"passed":37,"assertions":180,"duration_ms":7652}
```

Postgres preflight before `test:pg`: `pgsql` healthy, `pg_isready` on
`127.0.0.1:5434` accepted connections, `corex_test` exists.

```
git diff --stat -- packages/core/src/
 packages/core/src/Support/Data/DataScope.php       | 44 +++++++++++++++++++++-
 .../src/Tenancy/Contracts/ImpersonationService.php |  6 +--
 2 files changed, 44 insertions(+), 6 deletions(-)
```

That stat is the foreign baseline already present before P7.12. This item's
writes are `packages/core/tests/Unit/CoreOptionsTest.php`,
`packages/core/tests/Views/SqlSavedViewRepositoryTest.php`, and this receipt.
`CoreOptions.php` and `SqlSavedViewRepository.php` are absent from the diff.
