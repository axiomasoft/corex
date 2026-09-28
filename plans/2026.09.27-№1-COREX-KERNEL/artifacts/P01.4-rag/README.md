# P01.4 execution research

| Capture | Question | Verification |
|:--|:--|:--|
| [pplx-01](pplx-01-action-inputs.md) | zizmor-action v0.6.4 inputs and pinact checksums | Retrieval; upstream checked below |
| [http-01](http-01-action-inputs.md) | Exact zizmor inputs | Upstream action.yml; `advanced-security`, `persona`, `version`, `inputs`, `online-audits` exist |
| [http-02](http-02-action-pin.md) | Action tag/pin identity | v0.6.4 and final SHA action.yml bytes match |
| [http-03](http-03-dependabot-secrets.md) | Credentials for Dependabot-triggered CI | GitHub Docs confirms identically named secrets in both stores |

Targets and tool versions reuse fresh E6/E7 captures from P01 design. pinact v5.0.0 was downloaded from
the upstream GitHub release and checked against its `pinact_5.0.0_checksums.txt`; see execution tool receipt.
