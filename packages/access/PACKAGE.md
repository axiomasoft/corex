# axiomasoft/corex-access compatibility passport

This is factual evidence, not a Composer constraint, runtime registry, or release promise.

| Field | Recorded fact |
| --- | --- |
| Package / license / profile | `axiomasoft/corex-access` / MIT / AzGuard bridge on public `axioma-studio/azguard-core` ^0.3 |
| Policy / declared constraint | PHP 8.4–8.5 and Laravel 12–13 / `php:^8.4`, `illuminate/contracts:^12.0|^13.0`, `illuminate/support:^12.0|^13.0` |
| Private local proof | P4.5 inspected immutable AzGuard `8c83864f02017134b325224c6c8be590a3971485` and completed two fresh consumer roles. Private ZIP SHA-256: `b487ce8b703359cb57a77083c1dd44685d1cf7856053b9c66a76cf4cd07f1574`; root and dependency cold locks: `1f4d4d63b4285b025804618b1cb2e421fc04e6c03f600d77dc68a37e063fcb07` and `fddba0846b8bda14ebc73765276d7d8082a71f79fced346eedd5a10994b72c30` |
| Suite status | The sealed private proof passed one test with four assertions. The current PHP 8.4 / Laravel 13 package suite passes 38 tests after the Testbench upgrade. |
| Coverage policy | No current coverage measurement is claimed. |
| Limitations | AzGuard resolves as `0.3.0` from public GitHub `axiomasoft/azguard-private` commit `253cbf4` (`packages/core`), which is the current `main` and is newer than tag `v0.3.0`. Composer repositories stay application-owned. The historical private proof above predates this pin and the Laravel 13 lane. |

The package README contains the integration boundary and dependency limitations.
