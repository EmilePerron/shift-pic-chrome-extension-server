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
        $qualities = [55, 65, 75, 85, 90];
        $idealDifferencePercentage = 0.00036;
        $maxDifferencePercentage = 0.000425;
        $weightDifferenceThreshold = 0.15; # An ideal image that is over 15% larger than an acceptable one will not be used, returning the smaller acceptable image instead
        $previousWasAcceptable = false;
        $bestPath = null;
        $tmpPaths = [];

        foreach ($qualities as $quality) {
            $settings['quality'] = $quality;
            $processedPath = static::process($path, $settings, false);
            $difference = static::compareImages($path, $processedPath);

            if ($difference <= $idealDifferencePercentage) {
                if ($previousWasAcceptable && filesize($tmpPaths[count($tmpPaths) - 1]) / filesize($processedPath) <= (1 - $weightDifferenceThreshold)) {
                    $bestPath = array_pop($tmpPaths);
                    $tmpPaths[] = $processedPath;
                    break;
                } else {
                    $bestPath = $processedPath;
                    break;
                }
            } else if ($difference <= $maxDifferencePercentage) {
                $previousWasAcceptable = true;
            } else if ($previousWasAcceptable) {
                $bestPath = array_pop($tmpPaths);
                break;
            }

            $tmpPaths[] = $processedPath;
        }

        if (!$bestPath) {
            $bestPath = array_pop($tmpPaths);
        }

        # Clean test images
        foreach ($tmpPaths as $tmpPath) {
            if ($tmpPath != $bestPath) {
                unlink($tmpPath);
            }
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
