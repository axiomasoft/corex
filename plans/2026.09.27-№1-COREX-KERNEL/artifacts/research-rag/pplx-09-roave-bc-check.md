# pplx-09 — Roave BC check: версия, PHP 8.4/8.5, internal-исключения, монорепо

- Tool: perplexity-web `search` · Date: 2026-09-27
- Query: "Roave backward-compatibility-check 2026 latest version, PHP 8.4/8.5 support, how to exclude @internal classes and Internal namespaces, baseline configuration file (.roave-backward-compatibility-check.xml), using it in a monorepo with many packages; alternatives for public API snapshot diffing in PHP"
- Status: provenance. VERIFY-AT-EXEC: `composer show roave/backward-compatibility-check`.

## Answer (verbatim, condensed whitespace)

Latest published Roave release 8.22.0 (September 16), requires PHP ~8.4.0 || ~8.5.0 — supports PHP 8.4 and 8.5, not 8.3. GitHub release page lags (8.13.0); Packagist is authoritative.
Excluding internal API: XML config supports <baseline> with <ignored-regex> entries over diagnostic text; not a mechanism that removes classes/namespaces from analysis. Example: `<ignored-regex>#\[BC\].*Acme\\.*\\Internal\\.*#</ignored-regex>` — illustrative; base filters on actual `--format=markdown` output.
Monorepo: config read from CWD; run per independently versioned package; needs Composer autoload coverage, committed changes, SemVer git tags; fetch full history. Don't assume "last minor" auto-detection is correct per package; use explicit references.
Alternatives: ondrejmirtes fork (old, PHP 8.1/8.2); committed API snapshots (reflection manifest per package, diffed in CI — more configurable for @internal and package boundaries, but you own exporter and BC rules); OpenAPI spec diffing for HTTP contracts.
Recommendation: Roave 8.22.0 per package on PHP 8.4/8.5 with tightly scoped baselines; add generated API snapshot/manifest check when precise internal-symbol exclusion is a hard requirement.

## Sources
1. https://packagist.org/packages/roave/backward-compatibility-check
2. https://github.com/Roave/BackwardCompatibilityCheck
3. https://packagist.org/packages/ondrejmirtes/backward-compatibility-check
4. https://tools.openapis.org/categories/all.html
