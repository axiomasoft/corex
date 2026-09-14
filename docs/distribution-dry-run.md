# Local distribution dry run

`scripts/distribution/export.py` builds only the four approved CoreX package
candidates from the source SHA pinned in `export-policy.json`. It never reads
the checkout for package contents, creates a remote resource, or claims that a
candidate is published.

Run `python3 scripts/distribution/export.py --check` to compare two independent
deterministic builds. Generated `packages.json` is local test metadata; its
archive URLs are relative and cannot be used as a consumer path repository.
