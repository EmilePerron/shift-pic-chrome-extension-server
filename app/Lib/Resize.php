<?php

namespace App\Lib;

use Gumlet\ImageResize;

abstract class Resize {

    public static function process(string $path, array $settings = [], bool $overwrite = false)
    {
        if (Image::isAnimatedGif($path)) {
            $resizedPath = static::resizeAnimatedGif($path, $settings, $overwrite);
        } else {
            $resizedPath = static::resizeWithGumlet($path, $settings, $overwrite);
        }

        return $resizedPath;
    }

    public static function resizeWithGumlet(string $path, array $settings, bool $overwrite)
    {
        $image = new ImageResize($path);

        # Gumlet\ImageResize's resizeToBestFit is for a "contain" mode; we want "cover". Let's do the math ourselves...
        if (!($image->getSourceWidth() <= $settings['width'] && $image->getSourceHeight() <= $settings['height'])) {
            $ratio  = $image->getSourceHeight() / $image->getSourceWidth();
            $width = $settings['width'];
            $height = $width * $ratio;
            $image->resize($width, $height, false);
        }

        $image->quality_jpg = 100;
        $image->quality_webp = 100;
        $image->quality_png = 0;
        $image->quality_truecolor = true;
        $image->gamma_correct = false;

        if ($overwrite) {
            $image->save();
            return $path;
        }

        $tmpPath = tempnam('/tmp', 'img');
        $image->save($tmpPath);
        return $tmpPath;
    }

    public static function resizeAnimatedGif(string $path, array $settings, bool $overwrite)
    {
        $image = new \Imagick($path);
        $image = $image->coalesceImages();

        if (!($image->getImageWidth() <= $settings['width'] && $image->getImageHeight() <= $settings['height'])) {
            $ratio  = $image->getImageHeight() / $image->getImageWidth();
            $width = $settings['width'];
            $height = $width * $ratio;

            foreach ($image as $frame) {
                $frame->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1);
                $frame->thumbnailImage($width, $height);
                $frame->setImagePage($width, $height, 0, 0);
            }
        }

        $image = $image->deconstructImages();

        if ($overwrite) {
            $image->save();
            return $path;
        }

        $tmpPath = tempnam('/tmp', 'img');
        $image->writeImages($tmpPath, true);

        return $tmpPath;
    }
}
