# Release tooling discovery

Date: 2026-10-06
Tool: mcp__perplexity__search
Query: For a Laravel PHP 8.4 monorepo preparing its first 1.0.0-rc.1, verify from primary sources current Infection PHP MSI versus covered MSI thresholds and zero mutants behavior, Rector PHP 8.4 and rector-laravel sets configuration, danharrin monorepo split github action inputs and token permissions, CycloneDX PHP Composer SBOM command. Avoid changing an accepted lockstep strategy. Cite official repos/docs; concise implementation pitfalls only.

Raw tool result (including the incomplete answer; not sufficient for verification):

```json
+{
  "content": [
    {
      "type": "text",
      "text": "Keep the accepted lockstep strategy: one 1.0.0-rc.1 release propagated to every distributable package. The relevant implementation checks are Infection’s mutation scope and empty-run handling, Rector’s explicit PHP target, the split action’s environment-based authentication, and Composer’s CycloneDX:make-sbom command.\n\n## Infection gates\n\nOfficial definitions distinguish the two denominators—not two names for the same threshold.\n“Defeated” includes killed mutants, timeouts, and errors in the documented formula. Current documentation also provides --with-timeouts to count timeouts as escaped instead. Either threshold fails the build when its actual score is below the configured minimum; both may be enforced together. There is no official universal release threshold such as 80/90—those numbers are project policy.\n```\n# Example policy only: retain your already-agreed thresholds. vendor/bin/infection --with-uncovered \\ --min-msi=80 --min-covered-msi=90\n```\n- Scope matters: since Infection\n\nSources:\n1. [infection.github](https://infection.github.io/guide/)"
    }
  ]
}
```
