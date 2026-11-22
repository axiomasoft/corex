<?php

declare(strict_types=1);

namespace CoreX\Media\Internal;

use CoreX\Media\Contracts\ConversionDriver;
use CoreX\Media\Data\ConversionInput;
use CoreX\Media\Data\ConversionOutput;
use CoreX\Media\Data\ConversionProfile;
use CoreX\Media\Exceptions\MediaOperationConflict;
use Packman\Attributes\Internal;

#[Internal]
final readonly class GdThumbnailDriver implements ConversionDriver
{
    public function __construct(private ConversionProfile $profile) {}

    public function profile(): ConversionProfile
    {
        return $this->profile;
    }

    public function convert(ConversionInput $input): ConversionOutput
    {
        if (! extension_loaded('gd') || ! in_array($input->sourceMimeType, ['image/jpeg', 'image/png'], true)) {
            throw new MediaOperationConflict('The GD thumbnail profile does not support this source.');
        }
        $facts = getimagesizefromstring($input->sourceBytes);

        if ($facts === false || ! in_array($facts['mime'], ['image/jpeg', 'image/png'], true)) {
            throw new MediaOperationConflict('The conversion source is not a supported image.');
        }
        [$sourceWidth, $sourceHeight] = [$facts[0], $facts[1]];

        if ($sourceWidth * $sourceHeight > $this->profile->maxPixels) {
            throw new MediaOperationConflict('The conversion source exceeds pixel limits.');
        }
        $source = imagecreatefromstring($input->sourceBytes);

        if ($source === false) {
            throw new MediaOperationConflict('The conversion source could not be decoded.');
        }

        [$width, $height] = $this->dimensions(sourceWidth: $sourceWidth, sourceHeight: $sourceHeight);
        $target = imagecreatetruecolor($width, $height);

        if ($target === false) {
            throw new MediaOperationConflict('The conversion target could not be allocated.');
        }

        if ($facts['mime'] === 'image/png') {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $color = imagecolorallocatealpha($target, 0, 0, 0, 127);

            if ($color === false) {
                throw new MediaOperationConflict('The conversion transparency could not be allocated.');
            }
            imagefill($target, 0, 0, $color);
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        ob_start();
        $written = $facts['mime'] === 'image/png' ? imagepng($target) : imagejpeg($target, quality: 85);
        $bytes = ob_get_clean();

        if (! $written || ! is_string($bytes) || $bytes === '' || strlen($bytes) > $this->profile->maxOutputBytes) {
            throw new MediaOperationConflict('The conversion output is invalid.');
        }

        return new ConversionOutput(bytes: $bytes, mimeType: $facts['mime'], width: $width, height: $height);
    }

    /** @return array{positive-int, positive-int} */
    private function dimensions(int $sourceWidth, int $sourceHeight): array
    {
        $scale = min(1, $this->profile->maxWidth / $sourceWidth, $this->profile->maxHeight / $sourceHeight);

        return [max(1, (int) floor($sourceWidth * $scale)), max(1, (int) floor($sourceHeight * $scale))];
    }
}
