# corex/tenancy

Laravel tenancy primitives for CoreX's physical PostgreSQL database-per-account
profile.

## Template integrity

Template-dependent operations fail closed by default. A ready template must
have all of the following before it can be cloned, provisioned, claimed from a
pending pool, activated, or used by a migration wave:

- a lowercase SHA-256 `schema_hash` recorded in `root_templates`;
- a configured Ed25519-signed manifest from a configured trusted key; and
- a matching canonical, read-only PostgreSQL catalogue hash.

`tenancy.template_integrity.trusted_keys` and
`tenancy.template_integrity.manifests` are deliberately empty defaults.
Existing ready rows with a NULL hash are not compatible fallback templates.
Operational rollback stops affected operations and preserves the integrity
metadata; the additive migration has no destructive automatic down action.
