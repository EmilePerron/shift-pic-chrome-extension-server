<?php

namespace App\Lib;

use Emileperron\FastSpring\FastSpring;
use Emileperron\FastSpring\Entity\Order;
use Emileperron\FastSpring\Entity\Subscription;

abstract class License {

    public static function getSubscription($license)
    {
        if ($cachedSubscription = static::getCachedSubscription($license)) {
            return $cachedSubscription;
        }

        try {
            FastSpring::initialize($_ENV['fastspring_api_username'], $_ENV['fastspring_api_password']);
            $subscriptions = Subscription::findBy(['status' => 'active']);

            foreach ($subscriptions as $subscription) {
                try {
                    if ($license == static::getLicenseFromSubscription($subscription)) {
                        return static::setSubscriptionCache($license, $subscription);
                    }
                } catch (\Exception $e) { }
            }
        } catch (\Exception $e) { }

        return null;
    }

    public static function getType($license, bool $nullIfNone = false)
    {
        $type = $nullIfNone ? null : 'free';

        VAR_DUMP(static::getSubscription($license));

        if (!$license || substr($license, 0, 5) == 'free_') {
            $type = 'free';
        } else if ($cachedType = static::getCachedLicenseType($license)) {
            $type = $cachedType;
        } else if ($subscription = static::getSubscription($license)) {
            $type = $subscription['product'] ?? 'free';
            static::setLicenseCache($license, $type);
        }

        return $type;
    }

    public static function getFromOrder($orderId)
    {
        $license = null;
        $type = 'free';

        try {
            FastSpring::initialize($_ENV['fastspring_api_username'], $_ENV['fastspring_api_password']);
            $order = Order::find($orderId);
            $subscriptionId = $order['items'][0]['subscription'];
            $subscription = Subscription::find($subscriptionId);
            $type = $subscription['product'] ?? 'free';
            $license = static::getLicenseFromSubscription($subscription);

            if ($license && $type && ($subscription['state'] ?? null) == 'active') {
                static::setLicenseCache($license, $type);
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return ['license' => $license, 'type' => $type];
    }

    public static function getChangeType($from, $to)
    {
        $plans = ['free', 'starter', 'premium', 'enterprise'];
        return array_search($from, $plans) > array_search($to, $plans) ? 'downgrade' : 'upgrade';
    }

    public static function clearAllCaches($license)
    {
        $cacheKey = static::cacheKey($license);
        Cache::remove($cacheKey);
        Cache::remove('subscription_' . $cacheKey);
    }

    protected static function getLicenseFromSubscription($subscription)
    {
        foreach ($subscription['fulfillments'] as $key => $possiblefulfillment) {
            if (strpos($key, '_license_') !== false && isset($possiblefulfillment[0]['license'])) {
                return $possiblefulfillment[0]['license'];
                break;
            }
        }

        return null;
    }

    protected static function cacheKey($license) {
        return 'license_' . md5($license);
    }

    protected static function setLicenseCache($license, $type, $duration = 86400)
    {
        return Cache::set(static::cacheKey($license), $type, $duration);
    }

    protected static function getCachedLicenseType($license)
    {
        return Cache::get(static::cacheKey($license));
    }

    protected static function setSubscriptionCache($license, $subscription)
    {
        return Cache::set('subscription_' . static::cacheKey($license), $subscription, 86400);
    }

    protected static function getCachedSubscription($license)
    {
        return Cache::get('subscription_' . static::cacheKey($license));
    }
}
