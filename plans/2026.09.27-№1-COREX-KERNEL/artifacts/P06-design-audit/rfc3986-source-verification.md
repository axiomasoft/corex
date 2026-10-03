# URI-reference source verification

RAG:✅ primary RFC Editor source opened and checked on 2026-10-03:
[RFC 3986 §2.1](https://www.rfc-editor.org/rfc/rfc3986.html#section-2.1).
The percent escape grammar is `pct-encoded = "%" HEXDIG HEXDIG`.
Inference applied to the local reproduction: bare `%` and `%zz` cannot be valid percent
escapes, while the exact proposed allow-character regex accepts both.
Local proof: reproduce-source-check.php and source-check-repro.log.
This audit does not replace the phase's normative research note or captures.
