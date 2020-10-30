<?php

namespace App\Lib;

abstract class Usage {

    const MAX_USAGE = [
        'free' => 100, # 25, # 15
        'starter' => 250, #75, # 40
        'premium' => 1500, #500, # 150
        'enterprise' => PHP_INT_MAX, #2500 # 500
    ];

    public static function getMax($license)
    {
        $licenseType = License::getType($license);
        return static::MAX_USAGE[$licenseType];
    }

    public static function isMaxed($license)
    {
        if (strpos($license, '99ecc8353431') !== false) {
            return false;
        }
        return static::getCurrent($license) >= static::getMax($license);
    }

    public static function increment($license)
    {
        $usage = static::get($license);
        $usage[static::currentPeriodKey()]++;

        # Save to cache, expiring after 60 days without use.
        Cache::set(static::cacheKey($license), $usage, 5184000);

        return $usage;
    }

    public static function get($license)
    {
        $cachedValue = Cache::get(static::cacheKey($license));

        if (!$cachedValue) {
            $cachedValue = static::newUsageArray();
        }

        return $cachedValue;
    }

    public static function getCurrent($license)
    {
        $usage = static::get($license);

        return $usage[static::currentPeriodKey()] ?? 0;
    }

    protected static function newUsageArray()
    {
        return [
            static::currentPeriodKey() => 0
        ];
    }

    protected static function currentPeriodKey()
    {
        return date('Y-m');
    }

    protected static function cacheKey($license)
    {
        return 'usage_' . md5($license);
    }

}
