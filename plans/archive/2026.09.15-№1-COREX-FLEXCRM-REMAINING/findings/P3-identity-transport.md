# P3.3 identity/transport receipts

Version: p3-adapter-receipts/1. Hash-qualified no-delta: existing internal transport and management identity boundary satisfy the local P3.3 cases; no provider registration, public contract, endpoint, token, schema, dependency, or runtime binding was added.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| identity-n1-n4 | R03 | existing management hydrator | Stable UUID identity across root and tenant; absent/deleted/denied membership | UUID-only identity, fail-closed deactivate, no resurrection | `ManagementIdentityHydratorTest.php` SHA-256 `db574746f11d0ac02ba2b08773e940f0f300e991f0bbfb243297863be8ef5b5d`; package test GREEN | PASS | Central enrollment remains G-CENTRAL/P10 |
| identity-no-merge-root-write | R03 | existing management hydrator | Shared phone or colliding email; tenant invocation | No merge; sync failure does not write root; management-only boundary | `ManagementIdentityHydratorTest.php` SHA-256 `db574746f11d0ac02ba2b08773e940f0f300e991f0bbfb243297863be8ef5b5d`; package test GREEN | PASS | Identity authority/profile writer remains P10 |
| jwt-mtls-negative | R04 | supplied `ChannelBinding` + `AccessTokenValidator` | issuer/audience/cnf/certificate/context mismatch or absent profile | Deny before HTTP; no guessed endpoint/token | `CentralApiClientTest.php` SHA-256 `9e681cea548bd51fc2c109c61fdd5035ef6ea41ec9324bf7fbf4a3ed181fd624`; package test GREEN | PASS | Actual issuer/client/certificate registration needs G-API/G-CENTRAL/P10 |
| response-wire-cache-negative | R04 | existing `StrictTransport` | malformed/duplicate JSON, wrong echo, cache, redirect, 304, bad media type | Typed protocol failure; no cached/silent acceptance | `StrictTransport.php` SHA-256 `a694b53d81208553ef9e97181da5876a134e517e2686db4f52faa1462edb3559`; package test GREEN | PASS | Deployed wire compatibility remains P9/P10 |
| revoke-uncertain-reconcile | R04 | existing `StrictTransport` | denied permission, lost response, 429/5xx, repeated/colliding request ID | Deny or uncertain outcome; only same logical ID retries under qualified deduplication | `CentralApiClientTest.php` SHA-256 `9e681cea548bd51fc2c109c61fdd5035ef6ea41ec9324bf7fbf4a3ed181fd624`; root PG GREEN | PASS | Durable pair/inbox/reconciliation is P3.4/P3.5 |
| transport-worker-reset | R04 | per-instance local transport state | Sequential independent fixture contexts | No token/context/history leakage across consumers | `StrictTransport.php` SHA-256 `a694b53d81208553ef9e97181da5876a134e517e2686db4f52faa1462edb3559`; package test GREEN | PASS | Fleet process isolation remains P10 |
| archive-lab-identity | R03 R04 | isolated SQLite/HTTP/certificate fixtures | Local conformance consumer only | Fixture proof is not a supported production profile | `P3.3-required-read-progress.json` SHA-256 `4ab9c552060cddf60937bf4507a4694028740d3cd6156ff67659b94f1aeb5228`; root tests GREEN | PASS | P9 installed contract and P10 deployed evidence remain pending |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| findings/P2-identity-profile.md | e9819018a40d8d1d99bf41690c6057722536e5b98f8d75d8cb8615a404c057d9 | Accepted identity profile and no-delta boundary |
| findings/P2-auth-ownership.md | 041196cc9db87c6f5141c73a0a26f224472082c8c6cf62acfc5af97320ff7af6 | Protected central/API ownership boundary |
| packages/tenancy/src/Membership/Internal/Transport/StrictTransport.php | 6728e236107ba7bcb20d25867508c388004ed381e4ef8f07d89016e61cccacdc | Existing typed read/revoke implementation |
| packages/tenancy/src/Membership/Internal/Transport/ChannelBinding.php | 3c70bf593da9e93eb6e1f27d64ca16de4a3d0e9733e29eece145fdd308f920b9 | Existing supplied-profile/mTLS boundary |
| packages/tenancy/tests/Feature/CentralApiClientTest.php | 9e681cea548bd51fc2c109c61fdd5035ef6ea41ec9324bf7fbf4a3ed181fd624 | Executed local transport conformance |
| packages/tenancy/tests/Feature/ManagementIdentityHydratorTest.php | db574746f11d0ac02ba2b08773e940f0f300e991f0bbfb243297863be8ef5b5d | Executed local identity conformance |

## Adoption

| Surface | Baseline/proposal digest | Local supported mode | Protected receipt | Registration | Remaining P10 gate |
|---|---|---|---|---|---|
| Existing internal identity/read/revoke transport | P2 identity profile `e9819018a40d8d1d99bf41690c6057722536e5b98f8d75d8cb8615a404c057d9` | Unregistered supplied profile; local conformance only | No new protected adoption; D31 is budget-only | No default DI/provider/endpoint/token registration | G-CENTRAL/G-API matching receipts, deployed issuer/mTLS/wire evidence and P9 installation |
