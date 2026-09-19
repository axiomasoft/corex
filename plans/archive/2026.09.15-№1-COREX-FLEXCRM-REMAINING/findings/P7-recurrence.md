# P7 recurrence adapter

**Schema:** p7-utility-receipts/1
**Item:** P7.2

Bounded `corex/recurrence` adapter over qualified `rlanvin/php-rrule` v3.0.0. Frozen `recurrence/1` DTOs, `RRuleRecurrenceExpander`, and REC-01..REC-12 local conformance are green in `packages/recurrence`.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| REC-01 | R20 | monthly Jan31 COUNT4 without invalid rollover | `a72c0d17f1b79929fe224e5e364093254b5aedea22a393ffde4bf77c4e47f0e7` | PASS | P7.2 | none |
| REC-02 | R20/B29 | America/New_York weekly civil time across DST | `4ae9edfb7906ae2e5851e54e1c2a3a106c0ea66a4d1face900e27c317d8a8f5b` | PASS | P7.2 | none |
| REC-03 | R20/B29 | spring gap omitted; nominal identity preserved | `db5f7ecb5354092ba6d02c735bd0bd51927ae0c5cf6873c3dd9c16a9ab24d850` | PASS | P7.2 | none |
| REC-04 | R20/B29 | autumn fold resolves once with TZID identity | `0cb3a74af3ea868331f0da4038cfe8c8d9ad9a5e98a27335974936955f64994e` | PASS | P7.2 | none |
| REC-05 | R20 | EXDATE reduces visible set; RDATE union once | `5fe9c3ec270ea2242582f3bc91d68a564c733750819a91c99a6e52a708d4b2d4` | PASS | P7.2 | none |
| REC-06 | R20 | COUNT+UNTIL invalid; unsupported profile denied | `96bdbcf5256c1ba74e5cadcd3d35c67022017aa998b226d2b820b82a6459d537` | PASS | P7.2 | none |
| REC-07 | R20 | DATE half-open window without midnight fiction | `c4a65787285a4279fd374065205098e788cc49ed9000b42034c1c3445d5bc361` | PASS | P7.2 | none |
| REC-08 | R20 | cursor pages stable keys; overrides move/cancel | `ea71e8fab1b2fe877be68cc37ed86cecbd1cd54d99d887fc66ca3324534499a9` | PASS | P7.2 | none |
| REC-09 | R20 | dense traversal stops within limits before emission | `ec54426a6c5e3c817c45ec525c4f89335706aadd81dae441b43c52779924adaa` | PASS | P7.2 | none |
| REC-10 | R20 | cursor definition hash mismatch rejects | `92fdf66755a820f73b1eced99e11ce6f928fc4ef4478cffd93e9c2681a8fe708` | PASS | P7.2 | none |
| REC-11 | R20 | pinned tzdb mismatch returns unsupported | `037be0e069a4fbe882c14432c96c7b9bc1c225323a9fd3bdcf5748c73ebd6d51` | PASS | P7.2 | none |
| REC-12 | R20 | equipment-maintenance and community-timetable consumers | `1351def050caa3d56f336261d5df05ec63ee2ea0a9e564a0d27597c8a583c97d` | PASS | P7.2 | none |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `artifacts/qualification/p7/recurrence-source.lock` | `39dea96b364aea45c996adeea80124917087dc864316919770115d5d74e6e58c` | immutable source pin |
| `research/P7-utility-design.md` | `e8030ad4a090f1bb13f6def331f8858606fe2716754d5e8e9b12c8ef2c4cbc43` | frozen bounded profile |
| `packages/recurrence/src/Contracts/RecurrenceExpander.php` | `68dc6b7679a8c0023102a183193ac2717fa5bb5486e8672bcb379367868f684b` | public expand port |
| `packages/recurrence/src/Internal/RRuleRecurrenceExpander.php` | `ca0b30934390f0727e4c5f61d4fe0fb954f39ccf078b6e8a17daf2c1fef3d8c3` | qualified wrapper |
| `packages/recurrence/tests/Unit/RRuleRecurrenceExpanderTest.php` | `b2b1b174ec95d2b4ec9d5c2c79df99d5a13909b38ea1d1cf6106c19a275e9aeb` | REC-01..11 executable proof |
| `packages/recurrence/tests/Consumers/ConsumerConformanceTest.php` | `e3178633234610f905ee091bcc95dd1e6bbc704d2a13909ac4cba20f8a9df288` | REC-12 neutral consumers |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| RecurrenceExpander | G-API | `68dc6b7679a8c0023102a183193ac2717fa5bb5486e8672bcb379367868f684b` | P7.2 | PASS | P7.4 may materialize |
| recurrence/1 DTO bundle | G-SCHEMA | `2ac89a40286326954e5ef97b1dbaba63fd55434376c31ddc0bde750539054866` | P7.2 | PASS | immutable revision keyed |
| rlanvin/php-rrule wrapper | G-DEPENDENCY | `39dea96b364aea45c996adeea80124917087dc864316919770115d5d74e6e58c` | P7.2 | PASS | upgrade only through P7.1 |
