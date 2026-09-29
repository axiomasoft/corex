<?php

declare(strict_types=1);

namespace CoreX\Media\Support;

use CoreX\Config\ViolationList;

/** @internal Package composition type. */
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

    /** @param  array<string, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ViolationList;

        $maxUploadBytes = self::positiveInt($violations, 'max_upload_bytes', $config['max_upload_bytes'] ?? 10 * 1024 * 1024);
        $maxImagePixels = self::positiveInt($violations, 'max_image_pixels', $config['max_image_pixels'] ?? 20_000_000);
        $purposes = self::nonEmptyStringList($violations, 'purposes', $config['purposes'] ?? ['attachment']);
        $allowedMimeTypes = self::nonEmptyStringList(
            $violations,
            'allowed_mime_types',
            $config['allowed_mime_types'] ?? [],
        );
        $conversionDriverTag = self::nonEmpty(
            $violations,
            'conversion_driver_tag',
            $config['conversion_driver_tag'] ?? 'corex.media.conversion-drivers',
        );
        $conversion = is_array($config['conversion'] ?? null) ? $config['conversion'] : [];

        $violations->failIfAny();

        return new self(
            maxUploadBytes: $maxUploadBytes,
            maxImagePixels: $maxImagePixels,
            purposes: $purposes,
            allowedMimeTypes: $allowedMimeTypes,
            conversionDriverTag: $conversionDriverTag,
            conversion: $conversion,
        );
    }

    private static function nonEmpty(ViolationList $violations, string $path, mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $violations->add($path, 'must be a non-empty string');

        return 'invalid';
    }

    private static function positiveInt(ViolationList $violations, string $path, mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        $violations->add($path, 'must be a positive integer');

        return 1;
    }

    /**
     * @return list<string>
     */
    private static function nonEmptyStringList(ViolationList $violations, string $path, mixed $value): array
    {
        if (! is_array($value)) {
            $violations->add($path, 'must be a non-empty list of strings');

            return [];
        }

        $resolved = [];

        foreach ($value as $index => $item) {
            if (is_string($item) && $item !== '') {
                $resolved[] = $item;

                continue;
            }

            $violations->add($path.'.'.$index, 'must be a non-empty string');
        }

        if ($resolved === []) {
            $violations->add($path, 'must not be empty');
        }

        return $resolved;
    }
}
