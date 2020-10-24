<?php

namespace App\Lib;

abstract class Usage {

    const MAX_USAGE = [
        'free' => 25, # 15
        'starter' => 75, # 40
        'premium' => 500, # 150
        'enterprise' => 2500 # 500
    ];

    public static function getMax($license)
    {
        $licenseType = License::getType($license);
        return static::MAX_USAGE[$licenseType];
    }

    public static function isMaxed($license)
    {
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
