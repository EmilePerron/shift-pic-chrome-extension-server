<?php

namespace App\Lib;

abstract class Stats {

    public const PROCESSED_KEY = 'stats_processed_count';

    public static function processedCount()
    {
        return Cache::get(static::PROCESSED_KEY, 0);
    }

    public static function incrementProcessed()
    {
        $count = static::processedCount();
        $count++;
        return Cache::set(static::PROCESSED_KEY, $count);
    }
}
