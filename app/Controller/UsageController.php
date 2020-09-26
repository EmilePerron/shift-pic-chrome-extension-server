<?php

namespace App\Controller;

use App\Lib\Controller;
use App\Lib\Cache;
use App\Lib\Usage;

class UsageController extends Controller {

    public function get()
    {
        $license = $_POST['license'] ?? null;
        return $this->jsonResponse(['usage' => Usage::getCurrent($license)]);
    }
}
