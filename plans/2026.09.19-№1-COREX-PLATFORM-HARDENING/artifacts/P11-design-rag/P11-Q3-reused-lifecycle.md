# P11-Q3 — reused lifecycle evidence

**Source:** `research/perplexity-2026-09-19.md` Q13  
**Original access:** 2026-09-19

P11 reuses the already captured synthesis for Drupal definition caches, Symfony compiler passes and
Shopware plugin lifecycle. It does not issue another broad query for the same premise.

Reused boundary:

- discover/declare, normalize, validate, order, compile/cache and controlled invalidation are a
  useful external pattern;
- Shopware's install/activate/deactivate/uninstall lifecycle informs operational language;
- CoreX does not copy that lifecycle one-to-one: code registry publication and per-tenant module
  activation are separate axes;
- durable identity/fingerprint behavior remains a CoreX decision, not an externally documented
  guarantee.
