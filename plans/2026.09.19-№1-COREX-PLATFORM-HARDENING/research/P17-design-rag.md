# P17 — bounded external evidence

## 1. Boundary

One focused Perplexity browser-MCP query was used for independently installable Laravel/Composer
release checks. Repository state, CoreX package names, the DoD mapping, profile composition and
release authority were derived only from repository evidence. The raw capture and exact query are
stored in `artifacts/P17-design-rag/P17-Q1.md`; official URLs were reachable directly on
2026-09-20.

## 2. Findings used

| Evidence | Design consequence |
|:--|:--|
| RAG:✅ Composer distinguishes locked `install` from dependency resolution by `update`; `--prefer-lowest` tests declared lower bounds. | P17.6 has both exact-candidate install and lower-bound resolution lanes. |
| RAG:✅ Composer builds package autoload metadata from installed package mappings, and `autoload-dev` is root-only. | Every fixture installs dist archives in a fresh consumer and scans generated autoload metadata for workspace leakage. |
| RAG:✅ Laravel package discovery is declared through `extra.laravel.providers`; provider `register`/`boot` effects are observable. | Pristine probes run discovery and assert package-specific boot effects, not only `class_exists`. |
| RAG:✅ Composer derives VCS package versions from tags and advises against a manual `version` field. | Candidate packages receive immutable `1.0.0` tags; P17 does not add `version` to Composer metadata. |
| RAG:✅ GitHub releases are tag-based immutable distribution records. | The publication packet binds exact tag/ref/artifact digests and forbids tag rewrite. |

## 3. Engineering policy, not attributed as an external requirement

Official docs do not prescribe CoreX's exact pristine-consumer layout, split-mirror provenance,
profile count, DoD count, external fixture set or cross-repository atomicity. Those are local
controls in D95–D97. The source material supports the underlying Composer/Laravel/GitHub behavior,
not the project-specific gate policy.

## 4. Primary sources

- Composer CLI: <https://getcomposer.org/doc/03-cli.md>
- Composer schema: <https://getcomposer.org/doc/04-schema.md>
- Composer versions: <https://getcomposer.org/doc/articles/versions.md>
- Laravel 12 package development: <https://laravel.com/docs/12.x/packages>
- GitHub releases: <https://docs.github.com/en/repositories/releasing-projects-on-github/about-releases>

No external code or prose is vendored into product files.
