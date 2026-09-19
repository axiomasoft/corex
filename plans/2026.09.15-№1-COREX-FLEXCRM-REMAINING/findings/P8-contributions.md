# P8 selected contributions and external ownership

**Schema:** p8-tool-receipts/1  
**Item:** P8.6

Finite selected P3–P7 producer contributions bound to compiled `Api::tool` tuples through
neutral archive/lab fixture modules. Nineteen B33/B34 control-plane tool names remain an
external ownership map only — zero tenant auto-registration and no root identity writer from
account agents.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| CONTRIB-01 | R08 | All R08 B33/B34 control-plane tools classified external; zero tenant exposure; root identity writer never invoked from account agent | `c09b4c48e3357203ed0dd418cc0c74f79cdb00f3553c143a90942926471ce950`; `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | PASS | P8.6 | installed seal in P8.7 |
| CONTRIB-02 | R08 | Selected P3 storage/claim/identity-read/account-list receipts map to actual supported mechanics | `c09b4c48e3357203ed0dd418cc0c74f79cdb00f3553c143a90942926471ce950`; `00513cc4eb2a08c8c350ad2f814f4ead1f5cd4e375d76f55d1d9ddb738fc8e55`; `ebcbde4ca6f6bdd7a4f7c41e4f902656d1cb592e1d01e2c3caff70c34914ff33` | PASS | P8.6 | P9 installed consumer |
| CONTRIB-03 | R08 | Only included P4 panel approved receipt adds finite contribution; no media/notification inference | `c09b4c48e3357203ed0dd418cc0c74f79cdb00f3553c143a90942926471ce950`; `00513cc4eb2a08c8c350ad2f814f4ead1f5cd4e375d76f55d1d9ddb738fc8e55` | PASS | P8.6 | consumer panel adapters |
| CONTRIB-04 | R08 | Selected P5 local wakeup binding only; no control-plane reverse edge | `c09b4c48e3357203ed0dd418cc0c74f79cdb00f3553c143a90942926471ce950`; `00513cc4eb2a08c8c350ad2f814f4ead1f5cd4e375d76f55d1d9ddb738fc8e55` | PASS | P8.6 | consumer engine binding |
| CONTRIB-05 | R08 | P6 search adapter keeps read/idempotency bounds; no bypass | `c09b4c48e3357203ed0dd418cc0c74f79cdb00f3553c143a90942926471ce950`; `00513cc4eb2a08c8c350ad2f814f4ead1f5cd4e375d76f55d1d9ddb738fc8e55` | PASS | P8.6 | consumer search ACL |
| CONTRIB-06 | R36 | P7 locale/FX/geo/task contributions keep optional semantics; no CRM/RUB default | `c09b4c48e3357203ed0dd418cc0c74f79cdb00f3553c143a90942926471ce950`; `00513cc4eb2a08c8c350ad2f814f4ead1f5cd4e375d76f55d1d9ddb738fc8e55`; `af91f50050c920b584437139d807505c14374ed497008fac6f640e69d1a2b178` | PASS | P8.6 | consumer utility wiring |
| CONTRIB-07 | R36 | Archive and lab cohorts independent; read and guarded mutation against exact contracts | `c09b4c48e3357203ed0dd418cc0c74f79cdb00f3553c143a90942926471ce950`; `ebcbde4ca6f6bdd7a4f7c41e4f902656d1cb592e1d01e2c3caff70c34914ff33`; `af91f50050c920b584437139d807505c14374ed497008fac6f640e69d1a2b178` | PASS | P8.6 | cold install in P8.7 |
| CONTRIB-08 | R50 | 100% included frozen rows have exact tuple/hash/effect/owner receipt | `c09b4c48e3357203ed0dd418cc0c74f79cdb00f3553c143a90942926471ce950`; `00513cc4eb2a08c8c350ad2f814f4ead1f5cd4e375d76f55d1d9ddb738fc8e55`; `846eddec614133c748e5517d7db6dc5e6957673e6d2818a22eeb9257c277293a` | PASS | P8.6 | seal in P8.7 |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `delivery/tool-contribution-qualification-20260915/evidence/tool-contributions.log` | `c09b4c48e3357203ed0dd418cc0c74f79cdb00f3553c143a90942926471ce950` | CONTRIB-01–08 pest receipt |
| `packages/api/docs/tool-integration.md` | `8944d73d8796a11de04cf2c361ec491a4ab9359333f1450e7008dc3ac7f28586` | integration map document |
| `packages/api/tests/Feature/ToolContributionsTest.php` | `846eddec614133c748e5517d7db6dc5e6957673e6d2818a22eeb9257c277293a` | CONTRIB feature cases |
| `packages/api/tests/Support/ArchiveToolModule.php` | `ebcbde4ca6f6bdd7a4f7c41e4f902656d1cb592e1d01e2c3caff70c34914ff33` | archive cohort fixture |
| `packages/api/tests/Support/LabToolModule.php` | `af91f50050c920b584437139d807505c14374ed497008fac6f640e69d1a2b178` | lab cohort fixture |
| `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | external19 ownership map |
| `delivery/tool-contribution-qualification-20260915/fixtures/selected-contributions.json` | `00513cc4eb2a08c8c350ad2f814f4ead1f5cd4e375d76f55d1d9ddb738fc8e55` | selected producer bindings |
| `delivery/tool-contribution-qualification-20260915/fixtures/archive-lab-policy.json` | `2d6988241d9e579bceaa95e274dddcf6137a67a6aa52efdae30b201e31fd0644` | archive/lab policy baseline |
| `findings/P8-invocation.md` | `4d8c6636dd5b70775d9f011464086baf4c08147c50752e4bffd92c8c805d5441` | P8.4 predecessor qualification |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| external control-plane ownership map | G-API | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | P8.6 | PASS | installed seal in P8.7 |
| selected P3–P7 contribution bindings | G-API | `00513cc4eb2a08c8c350ad2f814f4ead1f5cd4e375d76f55d1d9ddb738fc8e55` | P8.6 | PASS | P9 consumer cohort |
| archive/lab neutral fixture wrappers | G-API | `846eddec614133c748e5517d7db6dc5e6957673e6d2818a22eeb9257c277293a` | P8.6 | PASS | cold install in P8.7 |
| live control-plane MCP/central handlers | G-API | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | P10 | not-applicable | central deployment owner |
