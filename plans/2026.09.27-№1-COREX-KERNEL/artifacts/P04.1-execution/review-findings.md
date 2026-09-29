review: cursor/grok-4.7/high findings:1

The seven-record Golden candidate matches the new attribute and scanner types, with no existing-record or routes/events/wire change. That candidate is approved. Do not treat promotion as a defect.

1. Untyped PHPDoc `list` escapes the payload check. `ambiguousNative()` in `packages/core/src/Attributes/Internal/SurfaceScanner.php` only rejects missing docs or docs that start with `mixed`, `object`, `array`, or `iterable` (lines 627–632). Bare `list`, `non-empty-list`, and `non-empty-array` are builtins (line 42), so `walkDoc()` accepts them (lines 767–771) and never requires an element type. A data property still fails closed (`list<` at lines 866–868); an Api method return or parameter does not. D63 says unspecified array elements are not a bypass, and examples §2 require `API-UNTYPED-PAYLOAD` for an array return with no item type.

```php
namespace Demo;
use CoreX\Attributes\Api;
#[Api(since: '1.0')]
interface View {
    /** @return list */
    public function rows(): array;
}
```

Expected: diagnostic `API-UNTYPED-PAYLOAD` on `Demo\View`. Current scanner: no diagnostic.
