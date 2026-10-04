# pplx-01 — stancl/tenancy v4 release status, license, 3.10 compatibility

- Date: 2026-10-04
- Tool: perplexity-web (`mcp__perplexity__search`, default settings)
- Query: `stancl/tenancy v4 release status 2026: is version 4 published on Packagist as a stable tag, what license, and does stancl/tenancy 3.10 support Laravel 12 and Laravel 13?`

## Answer (verbatim)

## Status as of October 4, 2026

- Version 4 is not published as a stable Packagist release. Packagist lists v3.10.1 as the latest stable tag; it does not list a v4 tag. The v4 documentation still labels the version “WIP.”
- License: MIT.
- stancl/tenancy 3.10 supports both Laravel 12 and Laravel 13. Packagist’s v3.10.1 requirements include illuminate/support: ^10.0|^11.0|^12.0|^13.0 and, among development requirements, laravel/framework: ^10.0|^11.0|^12.0|^13.0.
So if you need a stable tagged release today, use 3.10.1; Laravel 12 and 13 are in its declared compatibility range.

Sources:
1. [packagist](https://packagist.org/packages/stancl/tenancy)

## Verification

Confirmed by primary capture `http-01-packagist-stancl-azguard.md` (no v4 tag; v3.10.1 MIT; Laravel ^10–^13).
