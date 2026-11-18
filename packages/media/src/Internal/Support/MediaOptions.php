<?php

declare(strict_types=1);

namespace CoreX\Media\Internal\Support;

use CoreX\Media\Internal\Value;
use Packman\Attributes\Internal;
use Packman\Config\Laravel\ConfigurationValidator;

/** @internal Package composition type. */
#[Internal]
final readonly class MediaOptions
{
    /**
     * @param  list<string>  $purposes
     * @param  list<string>  $allowedMimeTypes
     * @param  array<string, mixed>  $conversion
     */
    public function __construct(
        public int $maxUploadBytes,
        public int $maxImagePixels,
        public array $purposes,
        public array $allowedMimeTypes,
        public string $conversionDriverTag,
        public array $conversion,
    ) {}

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;

        $maxUploadBytes = self::positiveInt(violations: $violations, path: 'max_upload_bytes', value: $config['max_upload_bytes'] ?? 10 * 1024 * 1024);
        $maxImagePixels = self::positiveInt(violations: $violations, path: 'max_image_pixels', value: $config['max_image_pixels'] ?? 20_000_000);
        $purposes = self::nonEmptyStringList(violations: $violations, path: 'purposes', value: $config['purposes'] ?? ['attachment']);
        $allowedMimeTypes = self::nonEmptyStringList(
            violations: $violations,
            path: 'allowed_mime_types',
            value: $config['allowed_mime_types'] ?? [],
        );
        $conversionDriverTag = self::nonEmpty(
            violations: $violations,
            path: 'conversion_driver_tag',
            value: $config['conversion_driver_tag'] ?? 'corex.media.conversion-drivers',
        );
        $conversion = is_array($config['conversion'] ?? null) ? $config['conversion'] : [];

        $violations->failIfAny();

        return new self(
            maxUploadBytes: $maxUploadBytes,
            maxImagePixels: $maxImagePixels,
            purposes: $purposes,
            allowedMimeTypes: $allowedMimeTypes,
            conversionDriverTag: $conversionDriverTag,
            conversion: Value::map($conversion),
        );
    }

    private static function nonEmpty(ConfigurationValidator $violations, string $path, mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $violations->add(path: $path, message: 'must be a non-empty string');

        return 'invalid';
    }

    private static function positiveInt(ConfigurationValidator $violations, string $path, mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        $violations->add(path: $path, message: 'must be a positive integer');

        return 1;
    }

    /**
     * @return list<string>
     */
    private static function nonEmptyStringList(ConfigurationValidator $violations, string $path, mixed $value): array
    {
        if (! is_array($value)) {
            $violations->add(path: $path, message: 'must be a non-empty list of strings');

            return [];
        }

        $resolved = [];

        foreach ($value as $index => $item) {
            if (is_string($item) && $item !== '') {
                $resolved[] = $item;

                continue;
            }

            $violations->add(path: $path.'.'.$index, message: 'must be a non-empty string');
        }

        if ($resolved === []) {
            $violations->add(path: $path, message: 'must not be empty');
        }

        return $resolved;
    }
}
