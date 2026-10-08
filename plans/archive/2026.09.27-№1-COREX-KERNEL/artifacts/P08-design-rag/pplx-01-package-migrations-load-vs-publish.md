# pplx-01 — package migrations: loadMigrationsFrom vs publishesMigrations

- Date: 2026-10-05
- Tool: perplexity-web (`mcp__perplexity-web__search`, default settings)
- Query: `Laravel 12/13 package development: loadMigrationsFrom versus publishesMigrations for package-owned database schema — which is recommended when the package owns its tables, how does the migrations table journal behave, and pitfalls with multiple database connections (central vs tenant database-per-tenant) and migration file name collisions`

## Answer (verbatim)

"Approach","What it does","Best fit"
"loadMigrationsFrom($path)","Registers the package directory as a migration source; package migrations are discovered by the normal migrate command without copying them into the app.","The package owns its schema and should deliver schema changes through package releases."
"publishesMigrations([...])","Makes package migration files publishable into the app's migrations directory; publishing adjusts the filename timestamp. Published files become application files to manage.","The host app needs to review, edit, reorder, or selectively adopt migrations."

## Sources

The tool returned the table without a source list. Contract-relevant claims are confirmed against the installed
framework source instead (RAG:—, `vendor/laravel/framework` v13.33.0), see `vendor-01-laravel13-migrator.md`.
