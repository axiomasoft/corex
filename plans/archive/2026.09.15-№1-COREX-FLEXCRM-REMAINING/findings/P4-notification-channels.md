# P4.5 notification channels — execution receipt

Status: done with deviations.

The package now provides the frozen `RecipientResolver` and
`PreferenceRepository` seams, Settings-backed per-recipient preferences with
optimistic revision, a registered in-app channel, and a delivery coordinator
that rechecks current policy and recipient resolution before driver admission.
An unavailable driver or target is durably recorded as skipped; an uncertain
worker exception is recorded as unknown.

The package lint, PHPStan, SQLite suite (12 tests, 41 assertions), and isolated
PostgreSQL lane (2 tests, 2 assertions) passed. No Laravel mail adapter was registered:
the plan requires a matching G-DEPENDENCY receipt before adding the optional
mail dependency/provider surface. The in-app channel is the delivered boxed
profile.

The remaining deviation is only the unavailable coverage driver/root-wide
validation; coverage thresholds were not changed.

Version: p4-extension-receipts/1.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| CHN-01 | R16 | in-app driver | configured recipient | durable in-app delivery | `NotificationCenterTest.php` SHA-256 `8e197b821201450580ddb8dd545149b1b131b3aefdb4eb8cf02acddfe0f9274a` | PASS | delivery provider is optional |
| CHN-02 | R16 | preference store | changed preference revision | stale update rejects | `NotificationCenterTest.php` SHA-256 `8e197b821201450580ddb8dd545149b1b131b3aefdb4eb8cf02acddfe0f9274a` | PASS | settings owner persists scope |
| CHN-03 | R16 | recipient resolver | unresolved target | no driver admission | `NotificationCenterTest.php` SHA-256 `8e197b821201450580ddb8dd545149b1b131b3aefdb4eb8cf02acddfe0f9274a` | PASS | host supplies recipients |
| CHN-04 | R16 | policy | revoked current grant | delivery is denied | `NotificationCenterTest.php` SHA-256 `8e197b821201450580ddb8dd545149b1b131b3aefdb4eb8cf02acddfe0f9274a` | PASS | no cached grant |
| CHN-05 | R16 | driver registry | absent optional driver | default fail-closed | `NotificationCenterTest.php` SHA-256 `8e197b821201450580ddb8dd545149b1b131b3aefdb4eb8cf02acddfe0f9274a` | PASS | host tags drivers |
| CHN-06 | R16 | boxed profile | no mail credentials | only in-app channel is installed | `NotificationCenterTest.php` SHA-256 `8e197b821201450580ddb8dd545149b1b131b3aefdb4eb8cf02acddfe0f9274a` | PASS | mail requires separate receipt |
| CHN-07 | R16 | current context | reused worker context | no retained delivery grant | `NotificationCenterTest.php` SHA-256 `8e197b821201450580ddb8dd545149b1b131b3aefdb4eb8cf02acddfe0f9274a` | PASS | tenancy owns context reset |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| research/P4-extension-design.md | 957873c6995cb3811983217a67f99d5068416f4f46da8a1653650cbc595d143a | frozen channel seam |
| packages/notifications/tests/Feature/NotificationCenterTest.php | 8e197b821201450580ddb8dd545149b1b131b3aefdb4eb8cf02acddfe0f9274a | executed local cases |

## Adoption

| Surface | Proposal hash | Protected receipt | Supported local adapter | Installed contract | External owner/resume |
|---|---|---|---|---|---|
| `channel/1` | P2 catalogue | local test receipt | in-app + default-deny resolver | `corex/notifications` | P11 owns real adapters |
