<?php

namespace App\Controller;

use App\Lib\Controller;
use App\Lib\Cache;
use App\Lib\License;
use Emileperron\FastSpring\FastSpring;
use Emileperron\FastSpring\Entity\Product;

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
        $orderId = $_POST['id'] ?? null;
        return $this->jsonResponse(License::getFromOrder($orderId));
    }

    public function validate()
    {
        $license = $_POST['license'] ?? null;
        return $this->jsonResponse(['type' => License::getType($license)]);
    }

    public function validateChange()
    {
        $currentLicense = $_POST['currentLicense'] ?? null;
        $license = $_POST['license'] ?? null;

        if (substr($currentLicense, 0, 5) == 'free_' && substr($license, 0, 5) == 'free_') {
            return $this->jsonResponse(['error' => 'unknown_license']);
        }

        if (substr($currentLicense, 0, 5) != 'free_' && substr($license, 0, 5) == 'free_') {
            return $this->jsonResponse(['error' => 'manual_downgrade_not_allowed']);
        }

        $type = License::getType($license, true);

        if (!$type) {
            return $this->jsonResponse(['error' => 'unknown_license']);
        }

        return $this->jsonResponse(['license' => $license, 'type' => $type]);
    }

    public function free()
    {
        return $this->jsonResponse(['license' => 'free_' . bin2hex(random_bytes(27))]);
    }

    public function change()
    {
        $license = $_POST['license'] ?? null;
        $desiredPlan = $_POST['plan'] ?? null;
        $newLicense = null;

        if (!$license || !$desiredPlan) {
            return $this->jsonResponse(['error' => 'invalid_payload']);
        }

        FastSpring::initialize($_ENV['fastspring_api_username'], $_ENV['fastspring_api_password']);
        $currentSubscription = License::getSubscriptionFromLicense($license);
        $currentPlan = License::getType($license, true);
        $changeType = License::getChangeType($currentPlan, $desiredPlan);

        if (!$currentSubscription || !$currentPlan) {
            return $this->jsonResponse(['error' => 'invalid_payload']);
        }

        if ($desiredPlan == 'free') {
            try {
                $response = FastSpring::delete('subscriptions', [$currentSubscription['id']]);
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'The subscription is already canceled') !== false) {
                    return $this->jsonResponse([
                        'license' => $license,
                        'plan' => $desiredPlan,
                    ]);
                }
            }
        } else {
            try {
                $desiredProduct = Product::find($desiredPlan);

                if (!$desiredProduct) {
                    return $this->jsonResponse(['error' => 'invalid_payload']);
                }

                $response = FastSpring::post('subscriptions', [
                    'subscriptions' => [
                        [
                            'subscription' => $currentSubscription['id'],
                            'product' => $desiredPlan,
                            'quantity' => 1,
                            'prorate' => $changeType == 'upgrade',
                        ]
                    ]
                ]);
            } catch (\Exception $e)  {}
        }

        if (isset($response['subscriptions'][0]['result']) && $response['subscriptions'][0]['result'] == 'success') {
            License::clearAllCaches($license);

            if ($changeType == 'downgrade') {
                License::setDowngradeCache($license, $currentPlan, $currentSubscription['nextInSeconds'] - time());
            } else {
                License::setDowngradeCache($license, null, 1);
            }
        } else {
            return $this->jsonResponse(['error' => 'fastspring_error']);
        }

        return $this->jsonResponse([
            'license' => $license,
            'plan' => $desiredPlan,
        ]);
    }

}
