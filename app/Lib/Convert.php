<?php

namespace App\Lib;

use Spatie\ImageOptimizer\OptimizerChainFactory;
use Spatie\ImageOptimizer\Optimizers\Jpegoptim;
use Spatie\ImageOptimizer\Optimizers\Pngquant;
use Spatie\ImageOptimizer\Optimizers\Cwebp;

abstract class Convert {

    public static function process(string $path)
    {
        if (in_array(exif_imagetype($path), [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP])) {
            return $path;
        }

        $tmpPath = tempnam('/tmp', 'img');

        # RAW photo file conversion might use the embedded JPEG previews, the first few of which can have quite low resolutions...
        # Therefore, we start looking for previews at index 4, and go to the third, second or first if errors are thrown.
        $mimeType = mime_content_type($path);
        if ($mimeType == 'application/octet-stream') {
            for ($i = 4; $i >= 0; $i--) {
                try {
                    $image = new \Imagick($path . ($i ? '[' . $i . ']' : ''));
                } catch (\Exception $e) {}
            }
        } else {
            $image = new \Imagick($path);
        }

        $image->setImageFormat('jpg');
        $image->writeImage($tmpPath);

        return $tmpPath;
    }

}
