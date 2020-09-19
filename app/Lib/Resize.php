<?php

namespace App\Lib;

use Gumlet\ImageResize;

abstract class Resize {

    public static function process(string $path, array $settings = [], bool $overwrite = false)
    {
        $image = (new ImageResize($path))->resizeToBestFit($settings['width'], $settings['height']);
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

}
