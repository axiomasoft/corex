# corex/tenancy

Laravel tenancy primitives for CoreX's physical PostgreSQL database-per-account
profile.

## Template integrity

The internal verifier accepts a ready template only when it has all of the
following:

- a lowercase SHA-256 `schema_hash` recorded in `root_templates`;
- a configured Ed25519-signed manifest from a configured trusted key; and
- a matching canonical, read-only PostgreSQL catalogue hash.

`tenancy.template_integrity.trusted_keys` and
`tenancy.template_integrity.manifests` are deliberately empty defaults.
Existing ready rows with a NULL hash are not compatible fallback templates.
Consumer write-path adoption is intentionally separate. Operational rollback
preserves the integrity metadata; the additive migration has no destructive
automatic down action.
