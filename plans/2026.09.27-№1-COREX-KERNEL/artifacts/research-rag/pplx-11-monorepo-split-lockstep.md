# pplx-11 — Монорепо: split, lockstep, path repositories, metapackage

- Tool: perplexity-web `search` · Date: 2026-09-27
- Query: "PHP monorepo package split and release in 2026: symplify/monorepo-builder vs danharrin/monorepo-split-github-action, lockstep versioning of all packages (like Laravel framework, Filament, Symfony), per-package composer.json path repositories with \"self.version\", how Filament and Symfony keep independent split repos read-only; metapackage pattern"
- Status: provenance.

## Answer (verbatim, condensed whitespace)

For a new PHP monorepo in 2026, use lockstep versioning only if the packages are meant to be released and supported as one product. Use Composer path repositories for local development, split each package to its own distribution repo, and use a metapackage as the optional “install the whole suite” entry point.
danharrin/monorepo-split-github-action (latest v2.4.5) pushes package-directory history to target repositories and can propagate tags. symplify/monorepo-builder (12.7.2, July 31, 2026) manages Composer-file merging, validation, release automation. Not mutually exclusive.
Laravel's Illuminate repositories are read-only subtree splits of laravel/framework; Symfony component split workflow extracts component history.
Path repositories: root `{"type":"path","url":"packages/*","options":{"symlink":true}}`. `replace` with `self.version` only for an umbrella package; self.version does not synchronize versions or create tags.
Split repos are generated distribution mirrors; do not accept direct edits. Metapackage: `"type":"metapackage"` with requirements only.
Practical choice: lockstep tags + optional metapackage for a coordinated framework; danharrin split action; monorepo-builder only if its validation/release features are wanted.

## Sources
1. https://getcomposer.org/doc/05-repositories.md
2. https://getcomposer.org/doc/04-schema.md
