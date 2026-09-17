# P7 optional bridge qualification

**Schema:** utility-optional-qualification/1
**Profile:** intl-optional-bridge/1 local source qualification only.

The optional bridge cohort was exercised through independent package tests with `corex/intl-search` disabled by default and address suggestion bound only when a consumer supplies a driver. No bundled vendor source, public release artifact, or hosted network proof is claimed here.

| Profile | Actual result | Boundary |
|---|---|---|
| provider absent | PASS: unsupported/offline base remains valid | no default network driver |
| localized search bridge | PASS: schema/hydration projection only when enabled | PG path never uses Meili-only attributes |
| saved-view labels | PASS: metadata read only | filter/default/ACL grammar unchanged |
| address drivers | PASS: fixture + configured drivers via same port | canned fake is not external vendor acceptance |

**Pending owners:** Q-P2-052 external gate receipt for production driver adoption; P8 invoke/descriptor receipt for OPT-07 authority boundary.
