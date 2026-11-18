<?php

declare(strict_types=1);

namespace CoreX\Intl\Translation\Internal;

use CoreX\Intl\Locale\Data\LocaleResolution;
use CoreX\Intl\Territory\Exceptions\SchemaMismatchException;
use CoreX\Intl\Translation\Contracts\TranslationReader;
use CoreX\Intl\Translation\Data\LocalizedText;
use CoreX\Intl\Translation\Data\TranslationResult;
use Illuminate\Contracts\Translation\Translator;
use Override;
use Packman\Attributes\Internal;

#[Internal]
final readonly class LaravelTranslationReader implements TranslationReader
{
    public function __construct(
        private Translator $translator,
    ) {}

    #[Override]
    public function read(LocalizedText $text, LocaleResolution $locale): TranslationResult
    {
        if ($text->declaredField !== null && $text->schemaHash === null) {
            throw new SchemaMismatchException('Localized field requires a schema hash.');
        }

        if ($text->declaredField !== null && str_contains($text->declaredField, '..')) {
            throw new SchemaMismatchException('Localized field path is not module-declared.');
        }

        $locales = $this->deduplicatedLocales(locale: $locale);

        foreach ($locales as $tag) {
            if (array_key_exists($tag, $text->map)) {
                $value = $text->map[$tag];

                if ($value === null) {
                    continue;
                }

                return new TranslationResult(
                    status: 'found',
                    value: $value,
                    usedLocale: $tag,
                    wasFallback: $tag !== $locale->used,
                );
            }
        }

        if ($text->declaredField !== null && str_contains($text->declaredField, '.')) {
            foreach ($locales as $tag) {
                $translated = $this->translator->get($text->declaredField, [], $tag);

                if (is_string($translated) && $translated !== $text->declaredField) {
                    return new TranslationResult(
                        status: 'found',
                        value: $translated,
                        usedLocale: $tag,
                        wasFallback: $tag !== $locale->used,
                    );
                }
            }
        }

        return new TranslationResult(
            status: 'missing',
            value: null,
            usedLocale: null,
            wasFallback: false,
        );
    }

    /**
     * @return list<string>
     */
    private function deduplicatedLocales(LocaleResolution $locale): array
    {
        $seen = [];
        $ordered = [$locale->used, ...$locale->fallbackChain];

        $result = [];

        foreach ($ordered as $tag) {
            if (isset($seen[$tag])) {
                continue;
            }

            $seen[$tag] = true;
            $result[] = $tag;
        }

        return $result;
    }
}
