<?php

namespace App\Lib;

use Spatie\ImageOptimizer\OptimizerChainFactory;
use Spatie\ImageOptimizer\Optimizers\Jpegoptim;
use Spatie\ImageOptimizer\Optimizers\Pngquant;
use Spatie\ImageOptimizer\Optimizers\Cwebp;

abstract class Optimize {

    public static function process(string $path, array $settings = [], bool $overwrite = false)
    {
        $optimizerChain = OptimizerChainFactory::create()
            ->addOptimizer(new Jpegoptim([
                '-m' . $settings['quality'],
                '--strip-all',
                '--all-progressive',
            ]))
            ->addOptimizer(new Pngquant([
                '--quality=' . ($settings['quality'] - 10) . '-' . $settings['quality'],
            ]))
            ->addOptimizer(new Cwebp([
                '--q ' . $settings['quality'],
            ]));

        # TODO: Check for $settings['reformat'] and test if a different format could make the image lighter

        if ($overwrite) {
            $optimizerChain->optimize($path);
            return $path;
        }

        $tmpPath = tempnam('/tmp', 'img');
        $optimizerChain->optimize($path, $tmpPath);

        return $tmpPath;
    }

    public static function processAutoQuality(string $path, array $settings = [])
    {
        $originalFilesize = filesize($path);
        $qualities = [55, 65, 75, 85];
        $idealDifference = 0.00036;
        $acceptableDifference = 0.000425;
        $weightDifferenceThreshold = 0.15; # An ideal image that is over 15% larger than an acceptable one will not be used, returning the smaller acceptable image instead
        $previousWasAcceptable = false;
        $previousPath = null;
        $bestPath = null;
        $attempts = [];

        # Try every quality setting from the lowest to the highest, looking for one that matches our ideal/acceptable difference percentage.
        # If an acceptable one is found first, the next quality level will be checked to see if it's ideal and if the weight isn't much bigger (based on $weightDifferenceThreshold)
        # If an ideal one is found first, it is considered the best and tests stop there.
        foreach ($qualities as $quality) {
            $settings['quality'] = $quality;
            $processedPath = static::process($path, $settings, false);
            $difference = static::compareImages($path, $processedPath);
            $attempts[] = ['path' => $processedPath, 'difference' => $difference];

            if ($difference <= $idealDifference) {
                if ($previousWasAcceptable && filesize($previousPath) / filesize($processedPath) <= (1 - $weightDifferenceThreshold)) {
                    $bestPath = $previousPath;
                    break;
                } else {
                    $bestPath = $processedPath;
                    break;
                }
            } else if ($difference <= $acceptableDifference) {
                $previousWasAcceptable = true;
            } else if ($previousWasAcceptable) {
                $bestPath = $previousPath;
                break;
            }

            $previousPath = $processedPath;
        }

        # If no acceptable images are found, compare the difference percentage and weight of the results we have.
        # The lower quality might still be visually similar to the high quality, but with a much lower filesize.
        if (!$bestPath) {
            $highestQuality = $attempts[count($attempts) - 1];

            foreach ($attempts as $attempt) {
                if ($attempt['path'] == $highestQuality['path']) {
                    continue;
                }

                // Require at least 30% filesize savings for at most 10% higher difference percentage
                if (($attempt['difference'] / $highestQuality['difference']) <= 1.1 && (filesize($attempt['path']) / filesize($highestQuality['path'])) >= 1.3) {
                    $bestPath = $attempt['path'];
                    break;
                }
            }

            if (!$bestPath) {
                $bestPath = $highestQuality['path'];
            }
        }

        # Delete unused test images
        foreach ($attempts as $attempt) {
            if ($attempt['path'] != $bestPath) {
                unlink($attempt['path']);
            }
        }

        # If the optimized image is larger than the original, return the original
        if (($originalFilesize * 0.99) < filesize($bestPath)) {
            unlink($bestPath);
            $bestPath = $path;
        }

        return $bestPath;
    }

    protected static function compareImages($path1, $path2)
    {
        $image1 = new \Imagick();
        $image2 = new \Imagick();
        $image1->readImage($path1);
        $image2->readImage($path2);

        $result = $image1->compareImages($image2, \Imagick::METRIC_MEANSQUAREERROR);
        return $result[1];
    }
}
