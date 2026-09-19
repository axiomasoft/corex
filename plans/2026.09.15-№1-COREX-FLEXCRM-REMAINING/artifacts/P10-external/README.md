# P10 external evidence imports

Content-addressed immutable imports for central/cloud/security/ops qualification.

## Layout

```text
artifacts/P10-external/<sha256>.json   # sanitized p10-external-evidence/1 envelope
artifacts/P10-external/<sha256>.log    # retained raw log; digest bound in JSON
```

- `<sha256>` = lowercase hex SHA256 of the `.json` file bytes.
- No overwrite: collision rejects replacement.
- Public sanitized evidence only — no keys, tokens, or PII.

## Producer checklist

1. Bind to P9 released vector (`artifacts/P10-intake/cohort-binding.json`).
2. Schema `p10-external-evidence/1` — see `fixtures/P10-external-evidence/template.json`.
3. Include `deployed_release`, `registration_sha256`, `origin_sha256`, `sanitization` envelope.
4. Every assertion row: `condition`, `expected`, `observed`, `assertion`, `result`.
5. Validate before handoff: `python3 plans/.../scripts/effective-acceptance.py --validate-import <file>.json`.

## Consumer (CoreX plan executor)

Imports only — no HTTP, no enrollment, no remote ops from this repository.
