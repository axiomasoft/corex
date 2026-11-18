<?php

declare(strict_types=1);

namespace CoreX\Media\Laravel;

use CoreX\Media\Contracts\ConversionDriver;
use CoreX\Media\Data\ConversionProfile;
use CoreX\Media\Internal\GdThumbnailDriver;
use CoreX\Media\Internal\Support\MediaOptions;
use Illuminate\Contracts\Foundation\Application;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Builds the GD thumbnail driver from the `conversion.thumbnail` limits of the media options. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class GdThumbnailDriverFactory
{
    public function make(Application $app): ConversionDriver
    {
        $options = $app->make(MediaOptions::class);
        $thumbnail = is_array($options->conversion['thumbnail'] ?? null) ? $options->conversion['thumbnail'] : [];

        return new GdThumbnailDriver(new ConversionProfile(
            key: 'thumbnail',
            version: 'gd-v1',
            maxWidth: self::limit(thumbnail: $thumbnail, key: 'max_width', default: 4096),
            maxHeight: self::limit(thumbnail: $thumbnail, key: 'max_height', default: 4096),
            maxPixels: self::limit(thumbnail: $thumbnail, key: 'max_pixels', default: 20_000_000),
            maxOutputBytes: self::limit(thumbnail: $thumbnail, key: 'max_output_bytes', default: 10 * 1024 * 1024),
        ));
    }

    /** @param  array<array-key, mixed>  $thumbnail */
    private static function limit(array $thumbnail, string $key, int $default): int
    {
        $value = $thumbnail[$key] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }
}
