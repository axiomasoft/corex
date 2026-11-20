# Public consumer fixture

The consumer resolves CoreX Tasks and its CoreX runtime dependencies from sealed ZIP dist artifacts.
Its installation proof checks the resolved lock and reflects the Tasks provider from its separate vendor directory.

```sh
python3 scripts/distribution/verify_installed_consumers.py --candidate "$COREX_CANDIDATE_DIR" \
  --consumer public --evidence-dir "$COREX_EVIDENCE_DIR/public"
```
