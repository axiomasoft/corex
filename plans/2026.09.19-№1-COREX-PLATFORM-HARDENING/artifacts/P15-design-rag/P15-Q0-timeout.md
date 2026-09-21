# P15-Q0 — timeout

Status: `timeout`

Query:

> Using only current primary official sources such as the PHP manual, Composer schema documentation, and Laravel documentation, summarize the migration and validation obligations when a PHP Composer library renames public classes or interfaces, promoted public properties, a Laravel Artisan command class while preserving its command signature, and the Composer package name. Also cover a class FQCN that may be persisted in a database. Focus on a pre-1.0 library, explain limitations of PHP class_alias and Composer replace, and give practical repo-wide completeness checks. Provide direct primary-source links and distinguish sourced facts from recommendations.

Observed MCP result: `MCP error -32001: Request timed out` after the default
60-second client timeout. No answer from this attempt is used as evidence.
