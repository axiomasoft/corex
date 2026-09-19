# P3.5 final-write receipts

Version: p3-adapter-receipts/1. `FinalWriteAdmission` is a hidden, unregistered
adapter. It does not select an auth guard, publish cookies, register a provider,
or present fixture conformance as a production integration.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| final-insert-update-revoke-regrant | R06 | FinalWriteAdmission + PairStore | admitted, applied and regranted pair; callback INSERT | callback INSERT and application fence commit together on one connection transaction | `FinalWriteAdmissionConformanceTest.php` SHA-256 `8159fc34fa63f75137744ad87bcc3f00ca4505794725840774fa9d6bc2f430c5`; affected PG GREEN | PASS | Fixture tables are not a supported consumer profile; real consumer UPDATE/revoke wiring is P9/P10 |
| lock-held-lease-expiry | R06 | controlled Timing clock | callback succeeds, post-write fresh sample plus commit upper bound is 46 seconds | callback and application fence roll back | `FinalWriteAdmissionConformanceTest.php` SHA-256 `8159fc34fa63f75137744ad87bcc3f00ca4505794725840774fa9d6bc2f430c5`; affected PG GREEN | PASS | Platform clock/timeout receipt remains P10 |
| clock-domain-loss-deny | R06 | controlled Timing clock | post-write sample changes clock domain | no committed callback write or application fence | `FinalWriteAdmissionConformanceTest.php` SHA-256 `8159fc34fa63f75137744ad87bcc3f00ca4505794725840774fa9d6bc2f430c5`; affected PG GREEN | PASS | No wall-clock fallback; real suspend/restart evidence is P10 |
| remember-cookie-rollback | R06 | CredentialCookieQueue reference | final issuer denial after queued session/remember values | queue releases no cookie | `FencedCredentialIssuanceTest.php` SHA-256 `f4d72302aa7c7f91c52cb77a78ba4b070556d89e2b4ca4fb767240242b477ffa`; existing protected coverage | PASS | FinalWriteAdmission itself never publishes cookies |
| all-entry-enforced-or-absent | R06 | hidden unregistered adapter | guard, callback/API/PAT/OAuth/SSO/ws/job/command entry has no registration | no alternate entry is claimed as an enforced consumer | `FinalWriteAdmission.php` SHA-256 `717b4ef130330e977e89a81e97534e1ecdecc7453c92717cfd2733550cee9ba0`; source-only negative inspection | PASS | Consumer wiring remains P9/P10, not a local positive grant |
| absolute-session-boundary | R06 | EnforceSessionLifetime | authenticated marker reaches absolute expiry | middleware denies expired session | `MembershipExpiryTest.php` SHA-256 `11b78381ade3d74855c3d2acc1aaec0c7b8b5111045f9aa5b0bf8d24e9d40a0d`; existing protected coverage | PASS | Its production clock binding remains P10 |
| archive-lab-final-write | R06 | isolated PostgreSQL fixtures | local conformance only | proof remains explicitly non-production | `P3.5-required-read-progress.json` SHA-256 `d04a5d35c6869edd350b03602e87732d1ec932315cb408f25a3e690f875eb289` | PASS | P9 installed contract and P10 live receipts remain pending |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| findings/P3-durable-projection.md | 39b32858064f84ddc3e44b549f9ecd85b3ae59e1b61681666c12f961bcd8aa42 | P3.4 physical mapping and `PairStore::isFor` seam proof |
| packages/tenancy/src/Membership/Internal/Credentials/FinalWriteAdmission.php | 717b4ef130330e977e89a81e97534e1ecdecc7453c92717cfd2733550cee9ba0 | hidden same-connection final-write boundary |
| packages/tenancy/src/Membership/Internal/Persistence/PairStore.php | d7e1c31a8afe450f7b1c7e5d50809aad1885215ca6acc7b0eeafb2d243462414 | pair lock, state and final application-fence transition |
| packages/tenancy/tests/Pg/FinalWriteAdmissionConformanceTest.php | 8159fc34fa63f75137744ad87bcc3f00ca4505794725840774fa9d6bc2f430c5 | isolated PostgreSQL final-write conformance |
| decisions/D34-P3.5-final-write-adoption.md | 34625ae2e2f8008844db714271e125adac574716dd6e7500f8dc0808176b7a1a | scoped owner adoption receipt |

## Adoption

| Surface | Baseline/proposal digest | Local supported mode | Protected receipt | Registration | Remaining P10 gate |
|---|---|---|---|---|---|
| FinalWriteAdmission | final-write-candidate/1; D34 `34625ae2e2f8008844db714271e125adac574716dd6e7500f8dc0808176b7a1a` | Explicit caller supplies one connection, qualified clock and conservative remaining-commit bound | D34; P3.4 PairStore `isFor` evidence | None; no auth/DI/provider/cookie binding | installed consumer integration, platform clock/timeout/commit receipts, deployed lifetime evidence |
