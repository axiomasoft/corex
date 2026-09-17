# P4.4 notification outbox — execution receipt

Status: done with deviations.

The new `corex/notifications` package owns tenant-local `ntf_notifications`,
`ntf_deliveries`, and `ntf_recipient_state` tables. `SqlNotificationCenter`
persists an idempotent inbox intent and its `in_app` delivery in one database
transaction. `DeliveryStore` advances a durable claim epoch and ignores stale
completions; expired claims are retained as `unknown`, not blindly retried.

Local evidence on 2026-09-16:

- `composer lint -- packages/notifications packages/wakeup` — PASS
- `composer --working-dir=packages/notifications test` — PASS (12 tests, 41 assertions)
- `composer --working-dir=packages/notifications test:pg` — PASS (2 tests, 2 assertions)
- `vendor/bin/phpstan analyse -c phpstan.neon packages/notifications/src packages/wakeup/src --memory-limit=1G` — PASS
- `python3 .../scripts/task-check.py --check lint` — PASS (0 errors, 0 warnings)

The PostgreSQL lane uses the disposable `corex_test` target. It now proves an
independent delivery-row lock; the later digest run lock is recorded in the
P4.6 receipt.

The remaining deviation is coverage/root-wide validation: this runtime has no
Xdebug or PCOV driver, so the package coverage command cannot run. No coverage
threshold was changed.

Version: p4-extension-receipts/1.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| NTF-01 | R16 | notification center | duplicate request key | one durable intent | `NotificationCenterTest.php` SHA-256 `8e197b821201450580ddb8dd545149b1b131b3aefdb4eb8cf02acddfe0f9274a` | PASS | transport is separate |
| NTF-02 | R16 | policy | denied actor | no delivery admission | `NotificationCenterTest.php` SHA-256 `8e197b821201450580ddb8dd545149b1b131b3aefdb4eb8cf02acddfe0f9274a` | PASS | host policy owns grants |
| NTF-03 | R16 | delivery store | stale claim completion | stale completion ignored | `DeliveryAdmissionTest.php` SHA-256 `4a15cdfca3aa6d8210e14ba41754b31491d934216b86629199916d4975a3ba73` | PASS | PG proves locking |
| NTF-04 | R16 | delivery store | independent worker lock | one writer advances claim | `DeliveryAdmissionTest.php` SHA-256 `4a15cdfca3aa6d8210e14ba41754b31491d934216b86629199916d4975a3ba73` | PASS | disposable test DB only |
| NTF-05 | R16 | in-app driver | unavailable recipient | deny/skip is durable | `NotificationCenterTest.php` SHA-256 `8e197b821201450580ddb8dd545149b1b131b3aefdb4eb8cf02acddfe0f9274a` | PASS | recipient owner supplies resolution |
| NTF-06 | R16 | recovery | uncertain delivery | unknown is not falsely delivered | `NotificationCenterTest.php` SHA-256 `8e197b821201450580ddb8dd545149b1b131b3aefdb4eb8cf02acddfe0f9274a` | PASS | live provider is P11 |
| NTF-07 | R16 | boxed profile | no credentials | in-app module boots without external adapter | `NotificationCenterTest.php` SHA-256 `8e197b821201450580ddb8dd545149b1b131b3aefdb4eb8cf02acddfe0f9274a` | PASS | optional channel remains absent |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| research/P4-extension-design.md | 957873c6995cb3811983217a67f99d5068416f4f46da8a1653650cbc595d143a | frozen notification seam |
| packages/notifications/tests/Feature/NotificationCenterTest.php | 8e197b821201450580ddb8dd545149b1b131b3aefdb4eb8cf02acddfe0f9274a | executed local cases |

## Adoption

| Surface | Proposal hash | Protected receipt | Supported local adapter | Installed contract | External owner/resume |
|---|---|---|---|---|---|
| `notification/1` | P2 catalogue | local test receipt | durable SQL + in-app driver | `corex/notifications` | P11 owns real transport |
