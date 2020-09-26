<?php

namespace App\Controller;

use App\Lib\Controller;
use App\Lib\Cache;
use Emileperron\FastSpring\FastSpring;
use Emileperron\FastSpring\Entity\Order;
use Emileperron\FastSpring\Entity\Subscription;

class LicenseController extends Controller {

    public function __construct() {
    }

    public function generate()
    {
        if (!isset($_REQUEST['security_request_hash'])) {
            header('HTTP/1.0 403 Forbidden');
            die();
        }

        ksort($_REQUEST);
        $data = '';

        /* USE urldecode($val) IF YOUR SERVER DOES NOT AUTOMATICALLY */
        foreach ($_REQUEST as $key => $val) {
            if (!in_array($key, ['security_request_hash', 'controller', 'action'])) {
                $data .= stripslashes($val);
            }
        }

        if (md5($data . $_ENV['fastspring_license_private_key']) != $_REQUEST['security_request_hash']){
            header('HTTP/1.0 403 Forbidden');
            die();
        }

        # Security check OK
        # Generate a new license
        echo bin2hex(random_bytes(32));
    }

    public function get()
    {
        $this->initFastSpring();
        $orderId = $_POST['id'] ?? null;
        $license = null;
        $type = 'free';

        try {
            $order = Order::find($orderId);
            $subscriptionId = $order['items'][0]['subscription'];
            $subscription = Subscription::find($subscriptionId);
            $type = $subscription['product'] ?? 'free';
            $fullfillment = $subscription['fulfillments'];
            $fullfillment = array_pop($fullfillment);
            $license = $fullfillment[0]['license'] ?? null;

            if ($license && $type && ($subscription['state'] ?? null) == 'active') {
                $this->setLicenseCache($license, $type);
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return $this->jsonResponse(['license' => $license, 'type' => $type]);
    }

    public function validate()
    {
        $this->initFastSpring();
        $license = $_POST['license'] ?? null;
        $type = 'free';

        if ($license && substr($license, 0, 5) == 'free_') {
            $type = 'free';
        } else if ($cachedType = $this->getCachedLicenseType($license)) {
            $type = $cachedType;
        } else {
            try {
                $subscriptions = Subscription::findBy(['status' => 'active']);

                foreach ($subscriptions as $subscription) {
                    $fullfillment = $subscription['fulfillments'];
                    $fullfillment = array_pop($fullfillment);

                    if ($license != ($fullfillment[0]['license'] ?? null)) {
                        continue;
                    }

                    $type = $subscription['product'] ?? 'free';
                    $this->setLicenseCache($license, $type);
                }
            } catch (\Exception $e) {
                throw $e;
            }
        }

        return $this->jsonResponse(['type' => $type]);
    }

    public function free()
    {
        return $this->jsonResponse(['license' => 'free_' . bin2hex(random_bytes(27))]);
    }

    protected function initFastSpring()
    {
        FastSpring::initialize($_ENV['fastspring_api_username'], $_ENV['fastspring_api_password']);
    }

    protected function setLicenseCache($license, $type)
    {
        Cache::set('license_' . md5($license), $type, 86400);
    }

    protected function getCachedLicenseType($license)
    {
        return Cache::get('license_' . md5($license));
    }
}
