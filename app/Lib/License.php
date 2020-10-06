<?php

namespace App\Lib;

use Emileperron\FastSpring\FastSpring;
use Emileperron\FastSpring\Entity\Order;
use Emileperron\FastSpring\Entity\Subscription;

abstract class License {

    public static function getSubscriptionFromLicense($license)
    {
        $subscription = null;
        $subscriptionId = static::getCachedSubscriptionId($license);

        if ($subscriptionId) {
            if ($cachedSusbcription = Cache::get('subscription_' . $subscriptionId)) {
                return $cachedSusbcription;
            }

            try {
                FastSpring::initialize($_ENV['fastspring_api_username'], $_ENV['fastspring_api_password']);
                $subscription = Subscription::find($subscriptionId);
                Cache::set('subscription_' . $subscriptionId, $subscription);
            } catch (\Exception $e) { }
        }

        return $subscription;
    }

    public static function getType($license, bool $nullIfNone = false)
    {
        $type = $nullIfNone ? null : 'free';

        if ($downgradedFromPlan = static::getDowngradeCache($license)) {
            $type = $downgradedFromPlan;
        } else if (!$license || substr($license, 0, 5) == 'free_') {
            $type = 'free';
        } else if ($subscription = static::getSubscriptionFromLicense($license)) {
            $type = $subscription['product'] ?? $type;
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

            if ($license) {
                static::setSubscriptionIdCache($license, $subscriptionId);
                Cache::set('subscription_' . $subscriptionId, $subscription, 86400);
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
        $subscriptionId = static::getCachedSubscriptionId($license);
        Cache::remove('subscription_' . $subscriptionId);
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

    protected static function setSubscriptionIdCache($license, $subscriptionId)
    {
        return Cache::set('subscription_id_' . static::cacheKey($license), $subscriptionId);
    }

    protected static function getCachedSubscriptionId($license)
    {
        return Cache::get('subscription_id_' . static::cacheKey($license));
    }

    public static function setDowngradeCache($license, $higherPlan, $untilTimespan)
    {
        return Cache::set('downgrade_' . md5($license), $higherPlan, $untilTimespan);
    }

    public static function getDowngradeCache($license)
    {
        return Cache::get('downgrade_' . md5($license));
    }
}
