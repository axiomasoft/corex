# P2 auth ownership — existing seams and future cases

Local source inspection only; cases below are observable future requirements, **not executed test results**. No new authentication/RBAC engine, central settings or role-writer choice.

## Boundaries

| Boundary | Port | Owner | Default or absence | Evidence | Gate or no-delta |
| :-- | :-- | :-- | :-- | :-- | :-- |
| authentication | consumer Laravel guard + selected PrincipalUserProvider | consumer authenticates; central owns UUID identity; adapter resolves active/nondeleted principal | Absent principal => deny; remember token permanently denied by selected provider; no global auth.* override | packages/auth/src/Identity/PrincipalUserProvider.php @ d8a9f2dc4d8cdd1a1578da3d043adcfb156b9a4c9e093ee7b43fa4e9eb194235; full body inspected; source witness only | G-CENTRAL/G-PRIVATE scoped selected profile; no-delta provider |
| authorization | CoreX\Api\Contracts\OperationAuthorizer::allows + CoreX\Auth\Contracts\AccessChecker | consumer resource/operation policy; AzGuard own RBAC; actor never authority | Absent policy binds default deny; operation capability still requires row-scoped handler checks | packages/api/src/ApiServiceProvider.php @ e4ccb9efbd4bcefd8edeb03d84410ab92bcd0048d6f1aadd95ff7893b3adb73a; full body inspected; source witness only | no-delta existing SPI; any break G-API major |
| workspace | CoreX\Tenancy\Contracts\PrincipalWorkspaceResolver::resolveFor | tenancy initialized DB and consumer trusted principal; resolver only membership lookup | Null slug active default; foreign/deleted/no membership deny; no body connection override | packages/tenancy/src/Contracts/PrincipalWorkspaceResolver.php @ 37a7e92f70228209318b2537751e2b29083719b445579bea523ed0c15e4677c7; full body inspected; source witness only | no-delta; P3/P9 all-entry/lifetime qualification |
| role-writer | existing AzGuard/consumer ownable-scope role writer, no CoreX engine | product policy owner C08; external roles adapter acceptance | Invalid scope must not become unqualified allow; C08 fallback-versus-deny unresolved externally; no new writer | packages/auth/src/CoreAuthServiceProvider.php @ 3823f81bf137263940a6fd03aa986dd3e0047f854c3c53ef0e07ec60ba1598f9; full body inspected; source witness only | G-PRIVATE; C08 owner scope packet and immutable source; no local grant |
| optional-private | CoreAuthServiceProvider scoped AccessChecker/ScopeAvailability/ScopeResolver; conditional PermissionLayer | optional adapter owns AzGuard dependency; public host without package independent | Public consumer boots without auth package; installing adapter itself requires AzGuard. Missing optional permission layer/provider is not grant | packages/auth/tests/Feature/WorkspaceScopeWiringTest.php @ a7010b0efa7be3879a10d5b2d661bfc1ad65ae0992bcfb9279cc97f1bb79eb8e; full body inspected; source witness only | G-PRIVATE/G-DEPENDENCY installation qualification P9; public provider is no-delta |

## Cases

| Case | Given | Expected | Owning phase |
| :-- | :-- | :-- | :-- |
| payload-identity | trusted archive principal A; body acting/identity B | Resolved authenticated A remains authority; forged B has no membership/role power; denial/no foreign record | P3/P9; P10 accepted target |
| payload-connection | lab account A already initialized; body connection/account B | No body-driven DB switch; lookup/policy scope A; wrong-account access denied before final SQL | P3/P9; P10 late-query receipt |
| absent-policy | public archive host installs API with no authorizer override | Default denies operation and side effect; envelope error preserved; no permissive registration | P3/P9 |
| absent-private | laboratory public host omits corex/auth and AzGuard entirely | Cold install/boot and deny-default API work without private secrets; no auth capability promised | P9 |
| worker-context-reset | one long-lived worker archive A -> lab B -> absent tenant/principal job | Fresh trusted identity/connection each boundary; no A record in B; absent context denies; owning 1000-switch zero-leak SQL proof remains future | P3/P9; P10 selected-worker qualification |
| certificate-mismatch | valid-looking access JWT but supplied current TLS certificate has different thumbprint/account/product | CentralUnauthorized before HTTP/side effects; permission remains separate even with valid proof; proxy headers untrusted absent receipt | P3/P9; P10 DI qualification |
| unsupported-entry | selected adapter registered for HTTP but route/job/CLI path not in accepted inventory | Unregistered path denied/no supported-capability claim; owner lists all accepted entries before rollout | P3/P9; P10 DI25–DI33 |
| uncertain-revoke | central may commit original request then timeout/429/5xx | RevokeUncertain retains original request ID and exact payload; no false success, fresh-ID retry or optimistic cache grant; owner reconciliation/fence precedes activation | P3/P9; P10 AP06–AP13 |

## 0. Receipt boundary

Source-backed existing scoped/stateless bindings retain lifetimes. CoreAuthServiceProvider does not rewrite auth guards/providers; optional workspace wiring absence/null class is silent skip, not blanket allow. Tests inspected at actual source hashes below; no test execution claimed. Every R03–R07/R40/R43/R46/R51/R52 mixed atom and G/DI/AP obligation is retained in P2-identity-profile and sealed P1 map/ledger. C08/C09 choices require external owning packets before affected registration, not catalogue confirmation.

## Sources

| Path | SHA256 | Receipt class |
| :-- | :-- | :-- |
| packages/auth/src/CoreAuthServiceProvider.php | 3823f81bf137263940a6fd03aa986dd3e0047f854c3c53ef0e07ec60ba1598f9 | full existing src/test body inspected; source witness, not executed/installed/live |
| packages/auth/src/Contracts/AccessChecker.php | 7ec57968cafe3b073f16f0ded3f4ccbbac12b4dfdd90a1f5eae15fa9d630e4c0 | full existing src/test body inspected; source witness, not executed/installed/live |
| packages/auth/tests/Feature/WorkspaceScopeWiringTest.php | a7010b0efa7be3879a10d5b2d661bfc1ad65ae0992bcfb9279cc97f1bb79eb8e | full existing src/test body inspected; source witness, not executed/installed/live |
| packages/api/tests/Feature/PlatformTest.php | fb6a35ec3f30419f66bb9bbc098fde22fd4bc8c7d52f13c1ff572521f893993b | full existing src/test body inspected; source witness, not executed/installed/live |
| packages/tenancy/tests/Feature/CentralApiClientTest.php | 9e681cea548bd51fc2c109c61fdd5035ef6ea41ec9324bf7fbf4a3ed181fd624 | full existing src/test body inspected; source witness, not executed/installed/live |
| packages/tenancy/tests/Feature/MembershipRuntimeRegistrationTest.php | f7f61d130c11989df3ec99645cdd6e149a04de863c4456c892a5235031d6978a | full existing src/test body inspected; source witness, not executed/installed/live |
| packages/tenancy/tests/Feature/PrincipalWorkspaceTest.php | 3c4723956c0b02418553752bd9667944122e18ead805a63b228e1ee409dd77f1 | full existing src/test body inspected; source witness, not executed/installed/live |
| packages/auth/src/Identity/PrincipalUserProvider.php | d8a9f2dc4d8cdd1a1578da3d043adcfb156b9a4c9e093ee7b43fa4e9eb194235 | full existing src/test body inspected; source witness, not executed/installed/live |

