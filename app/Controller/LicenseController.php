<?php

namespace App\Controller;

use App\Lib\Controller;
use App\Lib\Cache;
use App\Lib\License;

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

}
