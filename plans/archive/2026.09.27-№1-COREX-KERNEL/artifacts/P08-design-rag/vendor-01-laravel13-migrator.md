# vendor-01 — Laravel 13.33.0 migrator / schema facts (primary source: installed framework code)

- Date: 2026-10-05
- Tool: local read of `vendor/laravel/framework/src/Illuminate` (composer.lock: laravel/framework v13.33.0)

| # | Fact | Location |
|:--|:--|:--|
| L1 | `Migrator::getMigrationFiles()` globs `<path>/*_*.php` per path (non-recursive), keys files by basename and sorts all paths together by basename. Two files with the same basename in different paths collapse into one key. | `Database/Migrations/Migrator.php:578-587` |
| L2 | The journal row is the basename (`getMigrationName`) in the `migrations` table of the connection the migrator runs on; there is no per-package journal. | `Migrator.php`, `DatabaseMigrationRepository` |
| L3 | `publishesMigrations()` = `publishes()` + optional timestamp rewrite on publish (`database.migrations.update_date_on_publish`); a published copy therefore can get a different basename than the package file. | `Support/ServiceProvider.php:326-333` |
| L4 | `Blueprint::createIndexName()` = lowercase `{prefix?}{table}_{columns}_{type}`, no length cap. PostgreSQL truncates identifiers to 63 bytes (NAMEDATALEN-1), so two long auto names can collide after truncation. | `Database/Schema/Blueprint.php:1826-1838` |
| L5 | Blueprint has no `check()` constraint method; CHECK constraints need raw SQL. SQLite has no `ALTER TABLE … ADD CONSTRAINT`. | `Database/Schema/Blueprint.php` (no method) |
| L6 | `HasUuids::newUniqueId()` returns `Str::uuid7()`. | `Database/Eloquent/Concerns/HasUuids.php:16-18` |
