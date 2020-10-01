<?php

namespace App\Lib;

use Emileperron\FastSpring\FastSpring;
use Emileperron\FastSpring\Entity\Order;
use Emileperron\FastSpring\Entity\Subscription;

abstract class License {

    public static function getType($license, bool $nullIfNone = false)
    {
        $type = $nullIfNone ? null : 'free';

        if ($license && substr($license, 0, 5) == 'free_') {
            $type = 'free';
        } else if ($cachedType = static::getCachedLicenseType($license)) {
            $type = $cachedType;
        } else {
            try {
                FastSpring::initialize($_ENV['fastspring_api_username'], $_ENV['fastspring_api_password']);
                $subscriptions = Subscription::findBy(['status' => 'active']);

                foreach ($subscriptions as $subscription) {
                    try {
                        $fulfillment = $subscription['fulfillments'];
                        $fulfillment = array_pop($fulfillment);

                        if ($license != ($fulfillment[0]['license'] ?? null)) {
                            continue;
                        }

                        $type = $subscription['product'] ?? 'free';
                        static::setLicenseCache($license, $type);
                    } catch (\Exception $e) {

                    }
                }
            } catch (\Exception $e) {
                throw $e;
            }
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

            foreach ($subscription['fulfillments'] as $key => $possiblefulfillment) {
                if (strpos($key, '_license_') !== false && isset($possiblefulfillment[0]['license'])) {
                    $license = $possiblefulfillment[0]['license'];
                    break;
                }
            }

            if ($license && $type && ($subscription['state'] ?? null) == 'active') {
                static::setLicenseCache($license, $type);
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return ['license' => $license, 'type' => $type];
    }

    protected static function cacheKey($license) {
        return 'license_' . md5($license);
    }

    protected static function setLicenseCache($license, $type)
    {
        Cache::set(static::cacheKey($license), $type, 86400);
    }

    protected static function getCachedLicenseType($license)
    {
        return Cache::get(static::cacheKey($license));
    }
}
