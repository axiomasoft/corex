<?php

declare(strict_types=1);

namespace CoreX\Media\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Media\Data\ConversionProfile;
use CoreX\Media\Internal\GdThumbnailDriver;
use CoreX\Media\Internal\Support\MediaOptions;
use Illuminate\Contracts\Foundation\Application;

/** Builds the GD thumbnail driver from the `conversion.thumbnail` limits of the media options. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class GdThumbnailDriverFactory
{
    public function make(Application $app): GdThumbnailDriver
    {
        $options = $app->make(MediaOptions::class);
        $thumbnail = is_array($options->conversion['thumbnail'] ?? null) ? $options->conversion['thumbnail'] : [];

        return new GdThumbnailDriver(new ConversionProfile(
            key: 'thumbnail',
            version: 'gd-v1',
            maxWidth: self::limit($thumbnail, 'max_width', 4096),
            maxHeight: self::limit($thumbnail, 'max_height', 4096),
            maxPixels: self::limit($thumbnail, 'max_pixels', 20_000_000),
            maxOutputBytes: self::limit($thumbnail, 'max_output_bytes', 10 * 1024 * 1024),
        ));
    }

    /** @param  array<array-key, mixed>  $thumbnail */
    private static function limit(array $thumbnail, string $key, int $default): int
    {
        $value = $thumbnail[$key] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }
}
